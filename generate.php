<?php
require_once 'config.php';

$message = '';
$message_type = '';

// Fetch active data for generation
$classes = db_get_rows($conn, "SELECT * FROM classes WHERE year_id IN (SELECT year_id FROM years WHERE year_status='active') ORDER BY class_name");
$working_days = db_get_rows($conn, "SELECT * FROM working_days WHERE is_working=1 ORDER BY day_order");
$time_slots = db_get_rows($conn, "SELECT * FROM time_slots WHERE is_active=1 ORDER BY slot_number");
$assignments = db_get_rows($conn, "SELECT sa.*, s.subject_name, s.subject_type, s.lecture_hours_per_week, s.lab_hours_per_week, f.faculty_name, f.max_hours_per_day, f.max_hours_per_week, c.class_name, c.strength FROM subject_assignments sa JOIN subjects s ON sa.subject_id = s.subject_id JOIN faculty f ON sa.faculty_id = f.faculty_id JOIN classes c ON sa.class_id = c.class_id ORDER BY sa.class_id, sa.subject_id");
$rooms = db_get_rows($conn, "SELECT r.*, b.building_name, b.has_ac as building_ac FROM rooms r JOIN buildings b ON r.building_id = b.building_id WHERE r.room_type IN ('classroom','lab','seminar') ORDER BY r.capacity");
$faculty_unavailable = db_get_rows($conn, "SELECT * FROM faculty_unavailable");
$room_unavailable = db_get_rows($conn, "SELECT * FROM room_unavailable");

$days = $working_days;
$class_slots = array_values(array_filter($time_slots, function($s) { return $s['slot_type'] === 'class'; }));

$can_generate = count($classes) > 0 && count($assignments) > 0 && count($days) > 0 && count($class_slots) > 0 && count($rooms) > 0;
$total_required_slots = 0;
foreach ($assignments as $a) { $total_required_slots += $a['lecture_hours_per_week'] + $a['lab_hours_per_week']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    csrf_check();

    if (!$can_generate) {
        $message = "Cannot generate: Missing required data (ensure you have active classes, subjects, faculty, and rooms).";
        $message_type = "error";
        audit_log($conn, 'GENERATE_FAIL', "Insufficient data to generate timetable");
    } else {
        // Check if there's enough capacity
        $total_available_slots = count($days) * count($class_slots) * count($classes);
        if ($total_required_slots > $total_available_slots) {
            $message = "Not enough slots available! Required: $total_required_slots, Available: $total_available_slots";
            $message_type = "error";
            audit_log($conn, 'GENERATE_FAIL', "Capacity exceeded");
        } else {
            $conn->begin_transaction();
            try {
                // Clear existing timetable
                $conn->query("DELETE FROM timetable");
                
                $faculty_daily_hours = [];
                $faculty_weekly_hours = [];
                $class_daily_schedule = [];
                $faculty_daily_schedule = [];
                $room_daily_schedule = [];
                $class_room_tracking = []; 
                $errors = [];

                // Pre-load explicitly blocked times
                foreach ($faculty_unavailable as $fu) { 
                    $faculty_daily_schedule[$fu['faculty_id']][$fu['day_id']][$fu['slot_id']] = true; 
                }
                foreach ($room_unavailable as $ru) { 
                    $room_daily_schedule[$ru['room_id']][$ru['day_id']][$ru['slot_id']] = true; 
                }

                // Sort assignments: Labs first, then largest hour loads
                usort($assignments, function($a, $b) {
                    if ($a['lab_hours_per_week'] != $b['lab_hours_per_week']) 
                        return $b['lab_hours_per_week'] - $a['lab_hours_per_week'];
                    return ($b['lecture_hours_per_week'] + $b['lab_hours_per_week']) - 
                           ($a['lecture_hours_per_week'] + $a['lab_hours_per_week']);
                });

                foreach ($classes as $class) {
                    $class_id = $class['class_id'];
                    $class_strength = $class['strength'];
                    $class_assignments = array_filter($assignments, function($a) use ($class_id) { 
                        return $a['class_id'] == $class_id; 
                    });

                    foreach ($class_assignments as $assignment) {
                        $assignment_id = $assignment['assignment_id'];
                        $subject_id = $assignment['subject_id'];
                        $faculty_id = $assignment['faculty_id'];
                        $lecture_hours = $assignment['subject_type'] === 'lab' ? 0 : $assignment['lecture_hours_per_week'];
                        $lab_hours = $assignment['subject_type'] === 'lecture' ? 0 : $assignment['lab_hours_per_week'];

                        // --- PLACE LABS (Requires 2 consecutive slots) ---
                        $labs_placed = 0;
                        while ($labs_placed < $lab_hours) {
                            $best_score = -9999;
                            $best_day = null; 
                            $best_idx = null; 
                            $best_room = null;

                            foreach ($days as $day) {
                                $day_id = $day['day_id'];
                                for ($i = 0; $i < count($class_slots) - 1; $i++) {
                                    $slot1 = $class_slots[$i];
                                    $slot2 = $class_slots[$i + 1];

                                    // Check Conflicts
                                    if (isset($class_daily_schedule[$class_id][$day_id][$slot1['slot_id']]) || 
                                        isset($class_daily_schedule[$class_id][$day_id][$slot2['slot_id']])) continue;
                                    
                                    if (isset($faculty_daily_schedule[$faculty_id][$day_id][$slot1['slot_id']]) || 
                                        isset($faculty_daily_schedule[$faculty_id][$day_id][$slot2['slot_id']])) continue;
                                    
                                    $f_day_hrs = $faculty_daily_hours[$faculty_id][$day_id] ?? 0;
                                    $f_week_hrs = $faculty_weekly_hours[$faculty_id] ?? 0;
                                    if (($f_day_hrs + 2) > $assignment['max_hours_per_day'] || 
                                        ($f_week_hrs + 2) > $assignment['max_hours_per_week']) continue;

                                    // Score Rooms
                                    foreach ($rooms as $room) {
                                        if ($room['room_type'] !== 'lab' || $room['capacity'] < $class_strength) continue;
                                        if (isset($room_daily_schedule[$room['room_id']][$day_id][$slot1['slot_id']]) || 
                                            isset($room_daily_schedule[$room['room_id']][$day_id][$slot2['slot_id']])) continue;

                                        $score = 0;
                                        if ($room['has_ac'] || $room['building_ac']) $score += 10;
                                        $score += max(0, 20 - abs($room['capacity'] - $class_strength));

                                        if ($score > $best_score) {
                                            $best_score = $score; 
                                            $best_day = $day; 
                                            $best_idx = $i; 
                                            $best_room = $room;
                                        }
                                    }
                                }
                            }

                            if ($best_day && $best_idx !== null && $best_room) {
                                $day_id = $best_day['day_id'];
                                foreach ([$class_slots[$best_idx], $class_slots[$best_idx + 1]] as $slot) {
                                    $stmt = $conn->prepare("INSERT INTO timetable (class_id, day_id, slot_id, room_id, subject_id, faculty_id, assignment_id, is_lab, energy_score) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)");
                                    $stmt->bind_param("iiiiiiii", $class_id, $day_id, $slot['slot_id'], $best_room['room_id'], $subject_id, $faculty_id, $assignment_id, $best_score);
                                    $stmt->execute();
                                    $stmt->close();
                                    
                                    $class_daily_schedule[$class_id][$day_id][$slot['slot_id']] = $subject_id;
                                    $faculty_daily_schedule[$faculty_id][$day_id][$slot['slot_id']] = true;
                                    $room_daily_schedule[$best_room['room_id']][$day_id][$slot['slot_id']] = true;
                                    $faculty_daily_hours[$faculty_id][$day_id] = ($faculty_daily_hours[$faculty_id][$day_id] ?? 0) + 1;
                                    $faculty_weekly_hours[$faculty_id] = ($faculty_weekly_hours[$faculty_id] ?? 0) + 1;
                                }
                                $labs_placed += 2;
                            } else {
                                $errors[] = "Failed to place Lab: {$assignment['subject_name']} for {$assignment['class_name']}";
                                break;
                            }
                        }

                        // --- PLACE LECTURES (Single slots) ---
                        $lectures_placed = 0;
                        while ($lectures_placed < $lecture_hours) {
                            $best_score = -9999;
                            $best_day = null; 
                            $best_slot = null; 
                            $best_room = null;

                            foreach ($days as $day) {
                                $day_id = $day['day_id'];
                                foreach ($class_slots as $slot) {
                                    $slot_id = $slot['slot_id'];

                                    // Check Conflicts
                                    if (isset($class_daily_schedule[$class_id][$day_id][$slot_id]) || 
                                        isset($faculty_daily_schedule[$faculty_id][$day_id][$slot_id])) continue;
                                    
                                    $f_day_hrs = $faculty_daily_hours[$faculty_id][$day_id] ?? 0;
                                    $f_week_hrs = $faculty_weekly_hours[$faculty_id] ?? 0;
                                    if ($f_day_hrs >= $assignment['max_hours_per_day'] || 
                                        $f_week_hrs >= $assignment['max_hours_per_week']) continue;

                                    // Check subject spread (prevent same subject twice in one day)
                                    $subject_today = false;
                                    foreach ($class_slots as $s) {
                                        if (isset($class_daily_schedule[$class_id][$day_id][$s['slot_id']]) && 
                                            $class_daily_schedule[$class_id][$day_id][$s['slot_id']] == $subject_id) {
                                            $subject_today = true; 
                                            break;
                                        }
                                    }
                                    if ($subject_today) continue;

                                    // Score Rooms
                                    foreach ($rooms as $room) {
                                        if ($room['room_type'] === 'lab' || $room['capacity'] < $class_strength) continue;
                                        if (isset($room_daily_schedule[$room['room_id']][$day_id][$slot_id])) continue;

                                        $score = 0;
                                        $prev_building = $class_room_tracking[$class_id][$day_id] ?? null;
                                        if ($prev_building && $prev_building == $room['building_id']) $score += 15;
                                        if ($room['has_ac'] || $room['building_ac']) $score += 5;
                                        $score += max(0, 20 - abs($room['capacity'] - $class_strength));

                                        if ($score > $best_score) {
                                            $best_score = $score; 
                                            $best_day = $day; 
                                            $best_slot = $slot; 
                                            $best_room = $room;
                                        }
                                    }
                                }
                            }

                            if ($best_day && $best_slot && $best_room) {
                                $day_id = $best_day['day_id'];
                                $slot_id = $best_slot['slot_id'];
                                $room_id = $best_room['room_id'];

                                $stmt = $conn->prepare("INSERT INTO timetable (class_id, day_id, slot_id, room_id, subject_id, faculty_id, assignment_id, is_lab, energy_score) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)");
                                $stmt->bind_param("iiiiiiii", $class_id, $day_id, $slot_id, $room_id, $subject_id, $faculty_id, $assignment_id, $best_score);
                                $stmt->execute();
                                $stmt->close();
                                
                                $class_daily_schedule[$class_id][$day_id][$slot_id] = $subject_id;
                                $faculty_daily_schedule[$faculty_id][$day_id][$slot_id] = true;
                                $room_daily_schedule[$room_id][$day_id][$slot_id] = true;
                                $faculty_daily_hours[$faculty_id][$day_id] = ($faculty_daily_hours[$faculty_id][$day_id] ?? 0) + 1;
                                $faculty_weekly_hours[$faculty_id] = ($faculty_weekly_hours[$faculty_id] ?? 0) + 1;
                                $class_room_tracking[$class_id][$day_id] = $best_room['building_id'];
                                $lectures_placed++;
                            } else {
                                $errors[] = "Failed to place Lecture: {$assignment['subject_name']} for {$assignment['class_name']}";
                                break;
                            }
                        }
                    }
                }

                $conn->commit();
                
                $count = db_get_row($conn, "SELECT COUNT(*) as cnt FROM timetable")['cnt'] ?? 0;
                
                if (empty($errors)) {
                    $message = "AI-optimized timetable generated successfully! <strong>$count</strong> sessions scheduled.";
                    $message_type = "success";
                    audit_log($conn, 'GENERATE_SUCCESS', "Timetable generated with $count sessions");
                } else {
                    $message = "Generation completed with some errors. Scheduled: <strong>$count</strong> sessions.";
                    $message .= "<ul style='margin-top:10px; padding-left:20px; font-size:12px;'><li>" . implode("</li><li>", array_slice($errors, 0, 8)) . "</li></ul>"; 
                    $message_type = "warning";
                    audit_log($conn, 'GENERATE_PARTIAL', implode("; ", array_slice($errors, 0, 5)));
                }
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error during generation: " . $e->getMessage();
                $message_type = "error";
                audit_log($conn, 'GENERATE_ERROR', $e->getMessage());
            }
        }
    }
}

$timetable_count = db_get_row($conn, "SELECT COUNT(*) as count FROM timetable")['count'] ?? 0;
$avg_energy = db_get_row($conn, "SELECT AVG(energy_score) as avg FROM timetable")['avg'] ?? 0;
$timetable_exists = $timetable_count > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Timetable - AI Smart Timetable</title>
    <?php common_styles(); ?>
    <style>
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-badge.active { background: #27ae60; color: white; }
        .status-badge.ready { background: #3498db; color: white; }
        .status-badge.warning { background: #f39c12; color: white; }
        .status-badge.error { background: #e74c3c; color: white; }
        
        .view-buttons { 
            display: flex; 
            gap: 12px; 
            flex-wrap: wrap; 
            justify-content: center;
            margin-top: 15px;
        }
        .view-buttons .btn {
            padding: 8px 20px;
            font-size: 13px;
        }
        .view-buttons .btn-purple {
            background: #6B1B5E;
        }
        .view-buttons .btn-purple:hover {
            background: #5a1850;
        }
        .view-buttons .btn-outline {
            background: transparent;
            border: 2px solid #6B1B5E;
            color: #6B1B5E;
        }
        .view-buttons .btn-outline:hover {
            background: #6B1B5E;
            color: white;
        }
    </style>
</head>
<body>
    <?php svg_defs(); ?>
    <?php sidebar('generate'); ?>
    <?php top_header(); ?>

    <div class="content-wrapper">
        <?php breadcrumb(['Manage Time Table', 'Generate Timetable']); ?>

        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($timetable_exists): ?>
            <div class="alert alert-info">
                <strong>Current Status:</strong> <?php echo $timetable_count; ?> sessions already scheduled.
                <span style="display:block;font-size:12px;margin-top:5px;color:#888;">Average energy optimization score: <?php echo round($avg_energy, 1); ?></span>
            </div>
        <?php endif; ?>

        <div class="content-box">
            <div class="content-box-header">Configuration Summary</div>
            <div class="content-box-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo count($classes); ?></div>
                        <div class="label">Active Classes</div>
                        <?php if(count($classes) > 0): ?>
                            <div class="label" style="font-size:10px;color:#27ae60;margin-top:4px;"><?php echo implode(', ', array_column($classes, 'class_name')); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo count($assignments); ?></div>
                        <div class="label">Subject Assignments</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo count($days); ?></div>
                        <div class="label">Working Days</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo count($class_slots); ?></div>
                        <div class="label">Slots Per Day</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo count($rooms); ?></div>
                        <div class="label">Available Rooms</div>
                        <?php if(count($rooms) > 0): ?>
                            <div class="label" style="font-size:10px;color:#888;margin-top:4px;">
                                <?php echo count(array_filter($rooms, function($r) { return $r['room_type'] === 'classroom'; })); ?> classrooms, 
                                <?php echo count(array_filter($rooms, function($r) { return $r['room_type'] === 'lab'; })); ?> labs
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $total_required_slots; ?></div>
                        <div class="label">Required Slots</div>
                        <?php if($total_required_slots > 0): ?>
                            <div class="label" style="font-size:10px;color:#888;margin-top:4px;">
                                Available: <?php echo count($days) * count($class_slots) * count($classes); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-box">
            <div class="content-box-header">AI Algorithm Constraints</div>
            <div class="content-box-body">
                <div class="alert alert-info">The AI engine will respect these rules and optimize for energy efficiency:</div>
                <ul style="list-style:none;margin:10px 0;font-size:13px;">
                    <li style="padding:6px 0;border-bottom:1px solid #ecf0f1;"><span style="color:#27ae60;margin-right:8px;">&#10003;</span> No class can have two subjects at the same time</li>
                    <li style="padding:6px 0;border-bottom:1px solid #ecf0f1;"><span style="color:#27ae60;margin-right:8px;">&#10003;</span> No faculty can teach two classes at the same time</li>
                    <li style="padding:6px 0;border-bottom:1px solid #ecf0f1;"><span style="color:#27ae60;margin-right:8px;">&#10003;</span> Faculty unavailable slots are strictly blocked</li>
                    <li style="padding:6px 0;border-bottom:1px solid #ecf0f1;"><span style="color:#27ae60;margin-right:8px;">&#10003;</span> Faculty daily/weekly hour limits enforced</li>
                    <li style="padding:6px 0;border-bottom:1px solid #ecf0f1;"><span style="color:#27ae60;margin-right:8px;">&#10003;</span> Lab sessions require 2 consecutive slots</li>
                    <li style="padding:6px 0;border-bottom:1px solid #ecf0f1;"><span style="color:#27ae60;margin-right:8px;">&#10003;</span> Room capacity must fit class strength</li>
                    <li style="padding:6px 0;"><span style="color:#27ae60;margin-right:8px;">&#10003;</span> Energy-aware: prefers same building for consecutive slots</li>
                </ul>
            </div>
        </div>

        <div class="content-box">
            <div class="content-box-header">Generate AI-Optimized Timetable</div>
            <div class="content-box-body" style="text-align: center;">
                <?php if($can_generate): ?>
                    <?php if($total_required_slots > count($days) * count($class_slots) * count($classes)): ?>
                        <div class="alert alert-error" style="text-align:left;">
                            <strong>Capacity Issue:</strong> Required <?php echo $total_required_slots; ?> slots but only 
                            <?php echo count($days) * count($class_slots) * count($classes); ?> available.
                        </div>
                    <?php else: ?>
                        <p style="margin-bottom: 15px; color: #27ae60; font-size: 14px;">
                            <span style="font-size:24px;">✓</span> System is ready to generate an AI-optimized timetable.
                        </p>
                        <p style="margin-bottom: 20px; color: #666; font-size: 13px;">
                            This will schedule <strong><?php echo $total_required_slots; ?></strong> sessions across 
                            <strong><?php echo count($classes); ?></strong> classes using <strong><?php echo count($rooms); ?></strong> rooms.
                        </p>
                        <form method="POST" onsubmit="return confirm('This will delete the existing timetable and generate a new one. Continue?');">
                            <?php csrf_field(); ?>
                            <button type="submit" name="generate" value="1" class="btn btn-success" style="padding:12px 40px;font-size:16px;">
                                <svg><use href="#icon-refresh"/></svg> Generate AI Timetable Now
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-error" style="text-align:left;">
                        <strong>Cannot Generate:</strong> Missing required data.
                        <ul style="margin-top:8px;padding-left:20px;font-size:13px;">
                            <?php if(count($classes) == 0): ?><li>No active classes found</li><?php endif; ?>
                            <?php if(count($assignments) == 0): ?><li>No subject assignments found</li><?php endif; ?>
                            <?php if(count($days) == 0): ?><li>No working days configured</li><?php endif; ?>
                            <?php if(count($class_slots) == 0): ?><li>No class time slots configured</li><?php endif; ?>
                            <?php if(count($rooms) == 0): ?><li>No rooms available</li><?php endif; ?>
                        </ul>
                    </div>
                    <a href="setup.php" class="btn">Go to Setup</a>
                <?php endif; ?>
                
                <?php if($timetable_exists && $can_generate): ?>
                    <div class="view-buttons">
                        <a href="view.php" class="btn btn-purple">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                            View Full Timetable
                        </a>
                        <a href="view.php?mode=faculty" class="btn btn-outline">
                            View by Faculty
                        </a>
                        <a href="view.php?mode=room" class="btn btn-outline">
                            View by Room
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if(!$timetable_exists && $can_generate): ?>
                    <div style="margin-top:20px;padding-top:20px;border-top:1px solid #eee;color:#888;font-size:13px;">
                        <svg width="16" height="16" fill="#888" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        Generate a timetable first, then view it here.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>