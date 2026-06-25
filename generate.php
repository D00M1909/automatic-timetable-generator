<?php
require_once 'config.php';

$message = '';
$message_type = '';

$classes = $conn->query("SELECT * FROM classes WHERE year_id IN (SELECT year_id FROM years WHERE year_status='active')");
$working_days = $conn->query("SELECT * FROM working_days WHERE is_working=1 ORDER BY day_order");
$time_slots = $conn->query("SELECT * FROM time_slots WHERE is_active=1 ORDER BY slot_number");
$assignments = $conn->query("SELECT sa.*, s.subject_name, s.subject_type, s.lecture_hours_per_week, s.lab_hours_per_week, f.faculty_name, f.max_hours_per_day, f.max_hours_per_week, c.class_name FROM subject_assignments sa JOIN subjects s ON sa.subject_id = s.subject_id JOIN faculty f ON sa.faculty_id = f.faculty_id JOIN classes c ON sa.class_id = c.class_id ORDER BY sa.class_id, sa.subject_id");

$days = [];
while ($row = $working_days->fetch_assoc()) { $days[] = $row; }
$slots = [];
$class_slots = [];
while ($row = $time_slots->fetch_assoc()) {
    $slots[] = $row;
    if ($row['slot_type'] === 'class') { $class_slots[] = $row; }
}
$assignment_list = [];
while ($row = $assignments->fetch_assoc()) { $assignment_list[] = $row; }
$class_list = [];
while ($row = $classes->fetch_assoc()) { $class_list[] = $row; }

$can_generate = count($class_list) > 0 && count($assignment_list) > 0 && count($days) > 0 && count($class_slots) > 0;
$total_slots_per_week = count($days) * count($class_slots);
$total_required_slots = 0;
foreach ($assignment_list as $a) { $total_required_slots += $a['lecture_hours_per_week'] + $a['lab_hours_per_week']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    if (!$can_generate) {
        $message = "Cannot generate: Missing required data.";
        $message_type = "error";
    } else {
        $conn->query("DELETE FROM timetable");
        $faculty_daily_hours = [];
        $faculty_weekly_hours = [];
        $class_daily_schedule = [];
        $faculty_daily_schedule = [];

        $success = true;
        $errors = [];

        foreach ($class_list as $class) {
            $class_id = $class['class_id'];
            $class_assignments = array_filter($assignment_list, function($a) use ($class_id) { return $a['class_id'] == $class_id; });

            foreach ($class_assignments as $assignment) {
                $assignment_id = $assignment['assignment_id'];
                $subject_id = $assignment['subject_id'];
                $faculty_id = $assignment['faculty_id'];
                $lecture_hours = $assignment['lecture_hours_per_week'];
                $lab_hours = $assignment['lab_hours_per_week'];

                $lectures_placed = 0;
                $attempts = 0;
                $max_attempts = 1000;
                while ($lectures_placed < $lecture_hours && $attempts < $max_attempts) {
                    $attempts++;
                    $day = $days[array_rand($days)];
                    $slot = $class_slots[array_rand($class_slots)];
                    $day_id = $day['day_id'];
                    $slot_id = $slot['slot_id'];

                    $class_busy = isset($class_daily_schedule[$class_id][$day_id][$slot_id]);
                    $faculty_busy = isset($faculty_daily_schedule[$faculty_id][$day_id][$slot_id]);
                    $faculty_day_hours = $faculty_daily_hours[$faculty_id][$day_id] ?? 0;
                    $faculty_week_hours = $faculty_weekly_hours[$faculty_id] ?? 0;

                    if (!$class_busy && !$faculty_busy && 
                        $faculty_day_hours < $assignment['max_hours_per_day'] && 
                        $faculty_week_hours < $assignment['max_hours_per_week']) {

                        $conn->query("INSERT INTO timetable (class_id, day_id, slot_id, subject_id, faculty_id, assignment_id, is_lab) VALUES ($class_id, $day_id, $slot_id, $subject_id, $faculty_id, $assignment_id, 0)");

                        $class_daily_schedule[$class_id][$day_id][$slot_id] = true;
                        $faculty_daily_schedule[$faculty_id][$day_id][$slot_id] = true;
                        $faculty_daily_hours[$faculty_id][$day_id] = $faculty_day_hours + 1;
                        $faculty_weekly_hours[$faculty_id] = $faculty_week_hours + 1;
                        $lectures_placed++;
                    }
                }
                if ($lectures_placed < $lecture_hours) {
                    $errors[] = "Could not place all lecture hours for {$assignment['subject_name']} in {$assignment['class_name']}.";
                    $success = false;
                }

                $labs_placed = 0;
                $lab_attempts = 0;
                $max_lab_attempts = 500;
                while ($labs_placed < $lab_hours && $lab_attempts < $max_lab_attempts) {
                    $lab_attempts++;
                    $day = $days[array_rand($days)];
                    $day_id = $day['day_id'];

                    for ($i = 0; $i < count($class_slots) - 1; $i++) {
                        $slot1 = $class_slots[$i];
                        $slot2 = $class_slots[$i + 1];
                        $s1_free = !isset($class_daily_schedule[$class_id][$day_id][$slot1['slot_id']]);
                        $s2_free = !isset($class_daily_schedule[$class_id][$day_id][$slot2['slot_id']]);
                        $f1_free = !isset($faculty_daily_schedule[$faculty_id][$day_id][$slot1['slot_id']]);
                        $f2_free = !isset($faculty_daily_schedule[$faculty_id][$day_id][$slot2['slot_id']]);
                        $faculty_day_hours = $faculty_daily_hours[$faculty_id][$day_id] ?? 0;
                        $faculty_week_hours = $faculty_weekly_hours[$faculty_id] ?? 0;

                        if ($s1_free && $s2_free && $f1_free && $f2_free &&
                            ($faculty_day_hours + 2) <= $assignment['max_hours_per_day'] &&
                            ($faculty_week_hours + 2) <= $assignment['max_hours_per_week']) {

                            foreach ([$slot1, $slot2] as $slot) {
                                $conn->query("INSERT INTO timetable (class_id, day_id, slot_id, subject_id, faculty_id, assignment_id, is_lab) VALUES ($class_id, $day_id, {$slot['slot_id']}, $subject_id, $faculty_id, $assignment_id, 1)");
                                $class_daily_schedule[$class_id][$day_id][$slot['slot_id']] = true;
                                $faculty_daily_schedule[$faculty_id][$day_id][$slot['slot_id']] = true;
                                $faculty_daily_hours[$faculty_id][$day_id] = ($faculty_daily_hours[$faculty_id][$day_id] ?? 0) + 1;
                                $faculty_weekly_hours[$faculty_id] = ($faculty_weekly_hours[$faculty_id] ?? 0) + 1;
                            }
                            $labs_placed += 2;
                            break;
                        }
                    }
                }
                if ($labs_placed < $lab_hours && $lab_hours > 0) {
                    $errors[] = "Could not place all lab hours for {$assignment['subject_name']} in {$assignment['class_name']}.";
                    $success = false;
                }
            }
        }

        if ($success && empty($errors)) {
            $message = "Timetable generated successfully!";
            $message_type = "success";
        } else {
            $message = "Timetable generated with warnings. Some slots could not be filled.";
            if (!empty($errors)) { $message .= "<br>" . implode("<br>", array_slice($errors, 0, 5)); }
            $message_type = "warning";
        }
    }
}

$timetable_count = $conn->query("SELECT COUNT(*) as count FROM timetable")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Timetable - Timetable Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }

        .sidebar {
            position: fixed; left: 0; top: 0; width: 240px; height: 100vh;
            background: #6B1B5E; color: white; overflow-y: auto; z-index: 100;
        }
        .sidebar-header {
            padding: 15px; background: #5a1850; border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; gap: 10px;
        }
        .sidebar-header h2 { font-size: 14px; font-weight: 600; }
        .sidebar-menu { padding: 10px 0; }
        .menu-item {
            padding: 12px 20px; display: flex; align-items: center; gap: 12px;
            cursor: pointer; transition: background 0.2s; text-decoration: none; color: rgba(255,255,255,0.85);
            font-size: 14px; border-left: 3px solid transparent;
        }
        .menu-item:hover, .menu-item.active {
            background: rgba(255,255,255,0.1); color: white; border-left-color: #F5A623;
        }
        .menu-section {
            padding: 8px 20px; font-size: 11px; text-transform: uppercase;
            color: rgba(255,255,255,0.5); letter-spacing: 1px; margin-top: 10px;
        }

        .top-header {
            position: fixed; left: 240px; top: 0; right: 0; height: 50px;
            background: #F5A623; color: white; display: flex;
            align-items: center; justify-content: space-between; padding: 0 25px; z-index: 99;
        }
        .top-header-left { display: flex; align-items: center; gap: 15px; font-size: 14px; }
        .top-header-right { display: flex; align-items: center; gap: 20px; font-size: 13px; }
        .top-header-right a { color: white; text-decoration: none; }

        .content-wrapper {
            margin-left: 240px; margin-top: 50px; padding: 25px; min-height: calc(100vh - 50px);
        }
        .breadcrumb {
            font-size: 13px; color: #666; margin-bottom: 20px;
        }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .content-box {
            background: white; border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;
        }
        .content-box-header {
            padding: 15px 20px; border-bottom: 1px solid #eee;
            font-size: 16px; font-weight: 600; color: #333;
        }
        .content-box-body { padding: 20px; }

        .btn {
            display: inline-block; padding: 8px 20px; background: #3498db; color: white;
            text-decoration: none; border-radius: 3px; border: none; cursor: pointer; font-size: 13px;
        }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; font-size: 14px; padding: 10px 25px; }
        .btn-success:hover { background: #219a52; }
        .btn:disabled { background: #95a5a6; cursor: not-allowed; }

        .alert {
            padding: 12px 15px; border-radius: 3px; margin-bottom: 20px; font-size: 13px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin: 15px 0; }
        .stat-card { background: #f8f9fa; padding: 15px; border-radius: 4px; text-align: center; border-top: 3px solid #00BFA5; }
        .stat-card .number { font-size: 24px; font-weight: bold; color: #333; }
        .stat-card .label { color: #666; font-size: 12px; margin-top: 5px; }

        .info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 12px 15px; margin: 15px 0; border-radius: 0 3px 3px 0; font-size: 13px; }
        .constraint-list { list-style: none; margin: 10px 0; font-size: 13px; }
        .constraint-list li { padding: 6px 0; border-bottom: 1px solid #ecf0f1; }
        .constraint-list li:before { content: "✓"; color: #27ae60; margin-right: 8px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div style="width:40px;height:40px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#6B1B5E;font-weight:bold;font-size:18px;">A</div>
            <h2>Ajeenkya DY Patil<br><span style="font-size:11px;font-weight:400;opacity:0.8;">University ERP</span></h2>
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="menu-item"><span>📊</span> Dashboard</a>
            <div class="menu-section">Timetable</div>
            <a href="setup.php" class="menu-item"><span>⚙️</span> Manage Data</a>
            <a href="generate.php" class="menu-item active"><span>🔄</span> Generate Timetable</a>
            <a href="view.php" class="menu-item"><span>👁️</span> View Timetable</a>
        </div>
    </div>

    <div class="top-header">
        <div class="top-header-left">
            <span style="font-size:18px;cursor:pointer;">☰</span>
            <span>Active Academic Year: 2025-26 Summer Term</span>
        </div>
        <div class="top-header-right">
            <span>🔔 0</span>
            <span>Welcome, User</span>
            <a href="#">Logout</a>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="breadcrumb">
            <a href="index.php">Home</a> / <span>Manage Time Table</span> / <span>Generate Timetable</span>
        </div>

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
                    <div class="stat-card"><div class="number"><?php echo $total_slots_per_week; ?></div><div class="label">Total Slots/Week</div></div>
                    <div class="stat-card"><div class="number"><?php echo $total_required_slots; ?></div><div class="label">Required Slots</div></div>
                </div>

                <?php if ($total_required_slots > $total_slots_per_week * count($class_list)): ?>
                    <div class="alert alert-error">
                        ⚠️ <strong>Constraint Issue:</strong> You need <?php echo $total_required_slots; ?> slots but only have <?php echo $total_slots_per_week * count($class_list); ?> available.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-box">
            <div class="content-box-header">Algorithm Constraints</div>
            <div class="content-box-body">
                <div class="info-box">The generator will respect these rules:</div>
                <ul class="constraint-list">
                    <li>No class can have two subjects at the same time</li>
                    <li>No faculty can teach two classes at the same time</li>
                    <li>Faculty daily hours won't exceed their configured maximum</li>
                    <li>Faculty weekly hours won't exceed their configured maximum</li>
                    <li>Lab sessions require 2 consecutive slots</li>
                    <li>Break and lunch slots are automatically respected</li>
                </ul>
            </div>
        </div>

        <div class="content-box">
            <div class="content-box-header">Generate Timetable</div>
            <div class="content-box-body" style="text-align: center;">
                <p style="margin-bottom: 20px; color: #666; font-size: 13px;">
                    <?php if ($timetable_count > 0): ?>
                        A timetable already exists with <?php echo $timetable_count; ?> entries. Generating again will overwrite it.
                    <?php else: ?>
                        Click the button below to generate the timetable automatically.
                    <?php endif; ?>
                </p>
                <form method="POST">
                    <button type="submit" name="generate" value="1" class="btn btn-success" <?php echo (!$can_generate || $total_required_slots > $total_slots_per_week * count($class_list)) ? 'disabled' : ''; ?>>
                        🚀 Generate Timetable Now
                    </button>
                </form>
                <?php if (!$can_generate): ?>
                    <p style="margin-top: 15px; color: #e74c3c; font-size: 13px;">Please complete setup first.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($timetable_count > 0): ?>
        <div class="content-box">
            <div class="content-box-header">Generated Timetable Preview</div>
            <div class="content-box-body">
                <a href="view.php" class="btn">View Full Timetable →</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
