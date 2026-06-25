<?php
require_once 'config.php';

$message = '';
$message_type = '';

// Fetch data
$classes = db_get_rows($conn, "SELECT * FROM classes WHERE year_id IN (SELECT year_id FROM years WHERE year_status='active')");
$working_days = db_get_rows($conn, "SELECT * FROM working_days WHERE is_working=1 ORDER BY day_order");
$time_slots = db_get_rows($conn, "SELECT * FROM time_slots WHERE is_active=1 ORDER BY slot_number");
$assignments = db_get_rows($conn, "SELECT sa.*, s.subject_name, s.subject_type, s.lecture_hours_per_week, s.lab_hours_per_week, f.faculty_name, f.max_hours_per_day, f.max_hours_per_week, c.class_name, c.strength FROM subject_assignments sa JOIN subjects s ON sa.subject_id = s.subject_id JOIN faculty f ON sa.faculty_id = f.faculty_id JOIN classes c ON sa.class_id = c.class_id ORDER BY sa.class_id, sa.subject_id");
$rooms = db_get_rows($conn, "SELECT r.*, b.building_name, b.has_ac as building_ac FROM rooms r JOIN buildings b ON r.building_id = b.building_id WHERE r.room_type IN ('classroom','lab','seminar') ORDER BY r.capacity");
$faculty_unavailable = db_get_rows($conn, "SELECT * FROM faculty_unavailable");
$room_unavailable = db_get_rows($conn, "SELECT * FROM room_unavailable");
$faculty_absences = db_get_rows($conn, "SELECT * FROM faculty_absences WHERE status='pending'");

$days = $working_days;
$slots = $time_slots;
$class_slots = array_filter($slots, function($s) { return $s['slot_type'] === 'class'; });
$class_slots = array_values($class_slots); // reindex
$assignment_list = $assignments;
$class_list = $classes;

$can_generate = count($class_list) > 0 && count($assignment_list) > 0 && count($days) > 0 && count($class_slots) > 0 && count($rooms) > 0;
$total_slots_per_week = count($days) * count($class_slots);
$total_required_slots = 0;
foreach ($assignment_list as $a) { $total_required_slots += $a['lecture_hours_per_week'] + $a['lab_hours_per_week']; }

// Pre-compute blocked maps for fast lookup
$faculty_blocked = [];
foreach ($faculty_unavailable as $fu) {
    $faculty_blocked[$fu['faculty_id']][$fu['day_id']][$fu['slot_id']] = true;
}
foreach ($faculty_absences as $fa) {
    $faculty_blocked[$fa['faculty_id']][$fa['day_id']][$fa['slot_id']] = true;
}
$room_blocked = [];
foreach ($room_unavailable as $ru) {
    $room_blocked[$ru['room_id']][$ru['day_id']][$ru['slot_id']] = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    csrf_check();

    if (!$can_generate) {
        $message = "Cannot generate: Missing required data (ensure classes, assignments, rooms, and time slots are configured).";
        $message_type = "error";
        audit_log($conn, 'GENERATE_FAIL', $message);
    } else {
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM timetable");

            $faculty_daily_hours = [];
            $faculty_weekly_hours = [];
            $class_daily_schedule = [];
            $faculty_daily_schedule = [];
            $room_daily_schedule = [];
            $class_room_tracking = []; // track last room per class per day for energy efficiency

            $success = true;
            $errors = [];

            // Sort assignments: labs first (harder to place), then by total hours descending
            usort($assignment_list, function($a, $b) {
                $a_total = $a['lab_hours_per_week'] + $a['lecture_hours_per_week'];
                $b_total = $b['lab_hours_per_week'] + $b['lecture_hours_per_week'];
                if ($a['lab_hours_per_week'] != $b['lab_hours_per_week']) {
                    return $b['lab_hours_per_week'] - $a['lab_hours_per_week'];
                }
                return $b_total - $a_total;
            });

            foreach ($class_list as $class) {
                $class_id = $class['class_id'];
                $class_strength = $class['strength'];
                $class_assignments = array_filter($assignment_list, function($a) use ($class_id) { return $a['class_id'] == $class_id; });

                foreach ($class_assignments as $assignment) {
                    $assignment_id = $assignment['assignment_id'];
                    $subject_id = $assignment['subject_id'];
                    $faculty_id = $assignment['faculty_id'];
                    $lecture_hours = $assignment['lecture_hours_per_week'];
                    $lab_hours = $assignment['lab_hours_per_week'];
                    $subject_type = $assignment['subject_type'];

                    // Respect subject_type
                    if ($subject_type === 'lecture') $lab_hours = 0;
                    if ($subject_type === 'lab') $lecture_hours = 0;

                    // Place lectures
                    $lectures_placed = 0;
                    $attempts = 0;
                    $max_attempts = 2000;

                    while ($lectures_placed < $lecture_hours && $attempts < $max_attempts) {
                        $attempts++;

                        // Score-based slot selection instead of pure random
                        $best_score = -9999;
                        $best_day = null;
                        $best_slot = null;
                        $best_room = null;

                        foreach ($days as $day) {
                            foreach ($class_slots as $slot) {
                                $day_id = $day['day_id'];
                                $slot_id = $slot['slot_id'];

                                // Hard constraints
                                if (isset($class_daily_schedule[$class_id][$day_id][$slot_id])) continue;
                                if (isset($faculty_daily_schedule[$faculty_id][$day_id][$slot_id])) continue;
                                if (isset($faculty_blocked[$faculty_id][$day_id][$slot_id])) continue;

                                $faculty_day_hours = $faculty_daily_hours[$faculty_id][$day_id] ?? 0;
                                $faculty_week_hours = $faculty_weekly_hours[$faculty_id] ?? 0;
                                if ($faculty_day_hours >= $assignment['max_hours_per_day']) continue;
                                if ($faculty_week_hours >= $assignment['max_hours_per_week']) continue;

                                // Find best room
                                $room_score = -9999;
                                $selected_room = null;
                                foreach ($rooms as $room) {
                                    if ($room['room_type'] !== 'classroom' && $room['room_type'] !== 'seminar') continue;
                                    if ($room['capacity'] < $class_strength) continue;
                                    if (isset($room_daily_schedule[$room['room_id']][$day_id][$slot_id])) continue;
                                    if (isset($room_blocked[$room['room_id']][$day_id][$slot_id])) continue;

                                    $rs = 0;
                                    // Prefer same building as previous slot (energy efficiency)
                                    $prev_building = $class_room_tracking[$class_id][$day_id] ?? null;
                                    if ($prev_building && $prev_building == $room['building_id']) {
                                        $rs += 15; // Energy bonus
                                    }
                                    // Prefer AC rooms in summer (if configured)
                                    if ($room['has_ac'] || $room['building_ac']) $rs += 5;
                                    // Prefer smaller adequate rooms (efficiency)
                                    $rs += max(0, 20 - abs($room['capacity'] - $class_strength));

                                    if ($rs > $room_score) {
                                        $room_score = $rs;
                                        $selected_room = $room;
                                    }
                                }
                                if (!$selected_room) continue;

                                // Score this slot
                                $score = $room_score;
                                // Spread across days (penalize same day)
                                $day_count = 0;
                                foreach ($class_slots as $s) {
                                    if (isset($class_daily_schedule[$class_id][$day_id][$s['slot_id']])) $day_count++;
                                }
                                $score -= $day_count * 3;

                                // Prefer earlier slots slightly
                                $score -= $slot['slot_number'] * 0.5;

                                if ($score > $best_score) {
                                    $best_score = $score;
                                    $best_day = $day;
                                    $best_slot = $slot;
                                    $best_room = $selected_room;
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

                            $class_daily_schedule[$class_id][$day_id][$slot_id] = true;
                            $faculty_daily_schedule[$faculty_id][$day_id][$slot_id] = true;
                            $room_daily_schedule[$room_id][$day_id][$slot_id] = true;
                            $faculty_daily_hours[$faculty_id][$day_id] = ($faculty_daily_hours[$faculty_id][$day_id] ?? 0) + 1;
                            $faculty_weekly_hours[$faculty_id] = ($faculty_weekly_hours[$faculty_id] ?? 0) + 1;
                            $class_room_tracking[$class_id][$day_id] = $best_room['building_id'];
                            $lectures_placed++;
                        } else {
                            break; // No valid slot found
                        }
                    }

                    if ($lectures_placed < $lecture_hours) {
                        $errors[] = "Could not place all lecture hours ({$lectures_placed}/{$lecture_hours}) for {$assignment['subject_name']} in {$assignment['class_name']}.";
                        $success = false;
                    }

                    // Place labs
                    $labs_placed = 0;
                    $lab_attempts = 0;
                    $max_lab_attempts = 1000;

                    while ($labs_placed < $lab_hours && $lab_attempts < $max_lab_attempts) {
                        $lab_attempts++;
                        $best_score = -9999;
                        $best_day = null;
                        $best_idx = null;
                        $best_room = null;

                        foreach ($days as $day) {
                            $day_id = $day['day_id'];
                            for ($i = 0; $i < count($class_slots) - 1; $i++) {
                                $slot1 = $class_slots[$i];
                                $slot2 = $class_slots[$i + 1];

                                if (isset($class_daily_schedule[$class_id][$day_id][$slot1['slot_id']]) || 
                                    isset($class_daily_schedule[$class_id][$day_id][$slot2['slot_id']])) continue;
                                if (isset($faculty_daily_schedule[$faculty_id][$day_id][$slot1['slot_id']]) || 
                                    isset($faculty_daily_schedule[$faculty_id][$day_id][$slot2['slot_id']])) continue;
                                if (isset($faculty_blocked[$faculty_id][$day_id][$slot1['slot_id']]) || 
                                    isset($faculty_blocked[$faculty_id][$day_id][$slot2['slot_id']])) continue;

                                $faculty_day_hours = $faculty_daily_hours[$faculty_id][$day_id] ?? 0;
                                $faculty_week_hours = $faculty_weekly_hours[$faculty_id] ?? 0;
                                if (($faculty_day_hours + 2) > $assignment['max_hours_per_day']) continue;
                                if (($faculty_week_hours + 2) > $assignment['max_hours_per_week']) continue;

                                // Find lab room for 2 consecutive slots
                                $room_score = -9999;
                                $selected_room = null;
                                foreach ($rooms as $room) {
                                    if ($room['room_type'] !== 'lab') continue;
                                    if ($room['capacity'] < $class_strength) continue;
                                    if (isset($room_daily_schedule[$room['room_id']][$day_id][$slot1['slot_id']]) || 
                                        isset($room_daily_schedule[$room['room_id']][$day_id][$slot2['slot_id']])) continue;
                                    if (isset($room_blocked[$room['room_id']][$day_id][$slot1['slot_id']]) || 
                                        isset($room_blocked[$room['room_id']][$day_id][$slot2['slot_id']])) continue;

                                    $rs = 0;
                                    $prev_building = $class_room_tracking[$class_id][$day_id] ?? null;
                                    if ($prev_building && $prev_building == $room['building_id']) $rs += 15;
                                    if ($room['has_ac'] || $room['building_ac']) $rs += 5;
                                    $rs += max(0, 20 - abs($room['capacity'] - $class_strength));

                                    if ($rs > $room_score) {
                                        $room_score = $rs;
                                        $selected_room = $room;
                                    }
                                }
                                if (!$selected_room) continue;

                                $score = $room_score;
                                $day_count = 0;
                                foreach ($class_slots as $s) {
                                    if (isset($class_daily_schedule[$class_id][$day_id][$s['slot_id']])) $day_count++;
                                }
                                $score -= $day_count * 3;

                                if ($score > $best_score) {
                                    $best_score = $score;
                                    $best_day = $day;
                                    $best_idx = $i;
                                    $best_room = $selected_room;
                                }
                            }
                        }

                        if ($best_day && $best_idx !== null && $best_room) {
                            $day_id = $best_day['day_id'];
                            $slot1 = $class_slots[$best_idx];
                            $slot2 = $class_slots[$best_idx + 1];
                            $room_id = $best_room['room_id'];

                            foreach ([$slot1, $slot2] as $slot) {
                                $stmt = $conn->prepare("INSERT INTO timetable (class_id, day_id, slot_id, room_id, subject_id, faculty_id, assignment_id, is_lab, energy_score) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)");
                                $stmt->bind_param("iiiiiiii", $class_id, $day_id, $slot['slot_id'], $room_id, $subject_id, $faculty_id, $assignment_id, $best_score);
                                $stmt->execute();
                                $stmt->close();

                                $class_daily_schedule[$class_id][$day_id][$slot['slot_id']] = true;
                                $faculty_daily_schedule[$faculty_id][$day_id][$slot['slot_id']] = true;
                                $room_daily_schedule[$room_id][$day_id][$slot['slot_id']] = true;
                                $faculty_daily_hours[$faculty_id][$day_id] = ($faculty_daily_hours[$faculty_id][$day_id] ?? 0) + 1;
                                $faculty_weekly_hours[$faculty_id] = ($faculty_weekly_hours[$faculty_id] ?? 0) + 1;
                            }
                            $class_room_tracking[$class_id][$day_id] = $best_room['building_id'];
                            $labs_placed += 2;
                        } else {
                            break;
                        }
                    }

                    if ($labs_placed < $lab_hours && $lab_hours > 0) {
                        $errors[] = "Could not place all lab hours ({$labs_placed}/{$lab_hours}) for {$assignment['subject_name']} in {$assignment['class_name']}.";
                        $success = false;
                    }
                }
            }

            if ($success && empty($errors)) {
                $conn->commit();
                $message = "AI-optimized timetable generated successfully! Energy-aware room allocation applied.";
                $message_type = "success";
                audit_log($conn, 'GENERATE_SUCCESS', "Timetable generated with " . count($assignment_list) . " assignments");
            } else {
                $conn->rollback();
                $message = "Generation failed due to hard constraints. Adjust data and try again.";
                if (!empty($errors)) { $message .= "<br>" . implode("<br>", array_slice($errors, 0, 5)); }
                $message_type = "error";
                audit_log($conn, 'GENERATE_FAIL', implode("; ", array_slice($errors, 0, 5)));
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error during generation: " . $e->getMessage();
            $message_type = "error";
            audit_log($conn, 'GENERATE_ERROR', $e->getMessage());
        }
    }
}

$timetable_count = db_get_row($conn, "SELECT COUNT(*) as count FROM timetable")['count'] ?? 0;
$avg_energy = db_get_row($conn, "SELECT AVG(energy_score) as avg FROM timetable")['avg'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Timetable - AI Smart Timetable</title>
    <?php common_styles(); ?>
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

        <div class="content-box">
            <div class="content-box-header">Configuration Summary</div>
            <div class="content-box-body">
                <div class="stats-grid">
                    <div class="stat-card"><div class="number"><?php echo count($class_list); ?></div><div class="label">Classes</div></div>
                    <div class="stat-card"><div class="number"><?php echo count($assignment_list); ?></div><div class="label">Assignments</div></div>
                    <div class="stat-card"><div class="number"><?php echo count($days); ?></div><div class="label">Working Days</div></div>
                    <div class="stat-card"><div class="number"><?php echo count($class_slots); ?></div><div class="label">Slots/Day</div></div>
                    <div class="stat-card"><div class="number"><?php echo count($rooms); ?></div><div class="label">Rooms</div></div>
                    <div class="stat-card"><div class="number"><?php echo $total_slots_per_week; ?></div><div class="label">Total Slots/Week</div></div>
                    <div class="stat-card"><div class="number"><?php echo $total_required_slots; ?></div><div class="label">Required Slots</div></div>
                </div>

                <?php if ($total_required_slots > $total_slots_per_week * count($class_list)): ?>
                    <div class="alert alert-error">
                        <strong>Constraint Issue:</strong> You need <?php echo $total_required_slots; ?> slots but only have <?php echo $total_slots_per_week * count($class_list); ?> available.
                    </div>
                <?php endif; ?>
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
                    <li style="padding:6px 0;border-bottom:1px solid #ecf0f1;"><span style="color:#27ae60;margin-right:8px;">&#10003;</span> Energy-aware: prefers same building for consecutive slots</li>
                    <li style="padding:6px 0;"><span style="color:#27ae60;margin-right:8px;">&#10003;</span> Subject type (lecture/lab/both) strictly respected</li>
                </ul>
            </div>
        </div>

        <div class="content-box">
            <div class="content-box-header">Generate AI-Optimized Timetable</div>
            <div class="content-box-body" style="text-align: center;">
                <p style="margin-bottom: 20px; color: #666; font-size: 13px;">
                    <?php if ($timetable_count > 0): ?>
                        A timetable already exists with <?php echo $timetable_count; ?> entries. Generating again will overwrite it.
                        <br><span style="color:#888;">Average energy optimization score: <?php echo round($avg_energy, 1); ?></span>
                    <?php else: ?>
                        Click the button below to run the AI scheduling engine with conflict detection and energy-aware room allocation.
                    <?php endif; ?>
                </p>
                <form method="POST">
                    <?php csrf_field(); ?>
                    <button type="submit" name="generate" value="1" class="btn btn-success" <?php echo (!$can_generate || $total_required_slots > $total_slots_per_week * count($class_list)) ? 'disabled' : ''; ?>>
                        <svg><use href="#icon-refresh"/></svg> Generate AI Timetable Now
                    </button>
                </form>
                <?php if (!$can_generate): ?>
                    <p style="margin-top: 15px; color: #e74c3c; font-size: 13px;">Please complete setup first (classes, assignments, and rooms required).</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($timetable_count > 0): ?>
        <div class="content-box">
            <div class="content-box-header">Generated Timetable Preview</div>
            <div class="content-box-body">
                <a href="view.php" class="btn">View Full Timetable &rarr;</a>
                <a href="view.php?mode=faculty" class="btn">View by Faculty</a>
                <a href="view.php?mode=room" class="btn">View by Room</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
