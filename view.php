<?php
require_once 'config.php';

$view_mode = $_GET['mode'] ?? 'class';
$selected_id = $_GET['id'] ?? 0;

$classes = $conn->query("SELECT * FROM classes ORDER BY class_name");
$faculty = $conn->query("SELECT * FROM faculty ORDER BY faculty_name");
$days = $conn->query("SELECT * FROM working_days WHERE is_working=1 ORDER BY day_order");
$slots = $conn->query("SELECT * FROM time_slots WHERE is_active=1 ORDER BY slot_number");

$day_list = [];
while ($row = $days->fetch_assoc()) { $day_list[] = $row; }

$slot_list = [];
while ($row = $slots->fetch_assoc()) { $slot_list[] = $row; }

$timetable_data = [];
if ($selected_id > 0) {
    if ($view_mode === 'class') {
        $query = "SELECT t.*, d.day_name, d.day_order, ts.start_time, ts.end_time, ts.slot_type, s.subject_name, s.subject_code, f.faculty_name, f.faculty_code, c.class_name FROM timetable t JOIN working_days d ON t.day_id = d.day_id JOIN time_slots ts ON t.slot_id = ts.slot_id LEFT JOIN subjects s ON t.subject_id = s.subject_id LEFT JOIN faculty f ON t.faculty_id = f.faculty_id LEFT JOIN classes c ON t.class_id = c.class_id WHERE t.class_id = $selected_id ORDER BY d.day_order, ts.slot_number";
    } else {
        $query = "SELECT t.*, d.day_name, d.day_order, ts.start_time, ts.end_time, ts.slot_type, s.subject_name, s.subject_code, f.faculty_name, f.faculty_code, c.class_name FROM timetable t JOIN working_days d ON t.day_id = d.day_id JOIN time_slots ts ON t.slot_id = ts.slot_id LEFT JOIN subjects s ON t.subject_id = s.subject_id LEFT JOIN faculty f ON t.faculty_id = f.faculty_id LEFT JOIN classes c ON t.class_id = c.class_id WHERE t.faculty_id = $selected_id ORDER BY d.day_order, ts.slot_number";
    }
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $timetable_data[$row['day_id']][$row['slot_id']] = $row;
    }
}

function format_time($time) {
    return date('h:i A', strtotime($time));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Timetable - Timetable Management</title>
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
            display: flex; justify-content: space-between; align-items: center;
        }
        .content-box-body { padding: 20px; }

        .btn {
            display: inline-block; padding: 6px 16px; background: #3498db; color: white;
            text-decoration: none; border-radius: 3px; border: none; cursor: pointer; font-size: 13px;
        }
        .btn:hover { background: #2980b9; }
        .btn-active { background: #27ae60; }
        .btn-print { background: #6c757d; }
        .btn-print:hover { background: #5a6268; }

        .filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 15px; flex-wrap: wrap; }
        .filter-bar select { padding: 6px 10px; border: 1px solid #ddd; border-radius: 3px; min-width: 220px; font-size: 13px; }
        .view-toggle { display: flex; gap: 5px; margin-bottom: 15px; }

        /* Timetable Grid - matches ERP style */
        .timetable-container { overflow-x: auto; }
        .timetable-grid {
            width: 100%; border-collapse: collapse; font-size: 13px;
            border: 1px solid #ddd;
        }
        .timetable-grid th {
            background: #00BFA5; color: white; padding: 10px 8px;
            text-align: center; font-weight: 600; border: 1px solid #00a896;
        }
        .timetable-grid td {
            padding: 8px; text-align: center; border: 1px solid #e0e0e0;
            min-width: 100px; height: 60px; vertical-align: middle;
        }
        .timetable-grid .time-cell {
            background: #f8f9fa; font-weight: 600; color: #555;
            text-align: left; padding-left: 12px; min-width: 160px;
        }
        .timetable-grid .break-cell {
            background: #fff8e1; color: #856404; font-style: italic;
        }
        .timetable-grid .lunch-cell {
            background: #ffecb3; color: #856404; font-weight: 600;
        }
        .timetable-grid .class-slot {
            background: #e8f5e9;
        }
        .timetable-grid .class-slot.lab {
            background: #e3f2fd; border: 2px solid #2196f3;
        }
        .timetable-grid .empty-slot {
            color: #ccc;
        }
        .subject-name { font-weight: 600; color: #333; font-size: 12px; }
        .faculty-name { color: #666; font-size: 11px; }
        .class-name { color: #3498db; font-size: 11px; }
        .lab-badge { font-size: 10px; color: #2196f3; font-weight: 600; }

        .no-data { text-align: center; padding: 40px; color: #666; }
        .legend { display: flex; gap: 20px; margin-top: 15px; font-size: 12px; }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-box { width: 16px; height: 16px; border: 1px solid #ddd; }

        @media print {
            .sidebar, .top-header, .filter-bar, .view-toggle, .btn-print { display: none; }
            .content-wrapper { margin-left: 0; margin-top: 0; }
            .content-box { box-shadow: none; border: 1px solid #ddd; }
        }
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
            <a href="generate.php" class="menu-item"><span>🔄</span> Generate Timetable</a>
            <a href="view.php" class="menu-item active"><span>👁️</span> View Timetable</a>
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
            <a href="index.php">Home</a> / <span>Manage Time Table</span> / <span>Student Time Table</span>
        </div>

        <div class="content-box">
            <div class="content-box-header">
                <span>View Timetable</span>
                <button class="btn btn-print" onclick="window.print()">🖨️ Print</button>
            </div>
            <div class="content-box-body">
                <div class="view-toggle">
                    <a href="?mode=class" class="btn <?php echo $view_mode === 'class' ? 'btn-active' : ''; ?>">By Class</a>
                    <a href="?mode=faculty" class="btn <?php echo $view_mode === 'faculty' ? 'btn-active' : ''; ?>">By Faculty</a>
                </div>

                <form method="GET" class="filter-bar">
                    <input type="hidden" name="mode" value="<?php echo $view_mode; ?>">
                    <label style="font-size:13px;font-weight:600;">Select <?php echo $view_mode === 'class' ? 'Class' : 'Faculty'; ?>:</label>
                    <select name="id" onchange="this.form.submit()">
                        <option value="">-- Select <?php echo $view_mode === 'class' ? 'Class' : 'Faculty'; ?> --</option>
                        <?php 
                        $list = $view_mode === 'class' ? $classes : $faculty;
                        $list->data_seek(0);
                        while ($row = $list->fetch_assoc()): 
                            $id = $view_mode === 'class' ? $row['class_id'] : $row['faculty_id'];
                            $name = $view_mode === 'class' ? $row['class_name'] . ' (' . $row['class_code'] . ')' : $row['faculty_name'] . ' (' . $row['faculty_code'] . ')';
                        ?>
                        <option value="<?php echo $id; ?>" <?php echo $selected_id == $id ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endwhile; ?>
                    </select>
                </form>

                <?php if ($selected_id > 0 && !empty($timetable_data)): ?>
                    <div class="timetable-container">
                        <table class="timetable-grid">
                            <tr>
                                <th style="min-width:160px;">Time Slot</th>
                                <?php foreach ($day_list as $day): ?>
                                <th><?php echo $day['day_name']; ?></th>
                                <?php endforeach; ?>
                            </tr>
                            <?php foreach ($slot_list as $slot): ?>
                            <tr>
                                <td class="time-cell">
                                    <?php echo format_time($slot['start_time']); ?> TO <?php echo format_time($slot['end_time']); ?>
                                </td>
                                <?php foreach ($day_list as $day): ?>
                                    <?php 
                                    $cell_data = $timetable_data[$day['day_id']][$slot['slot_id']] ?? null;
                                    $cell_class = '';

                                    if ($slot['slot_type'] === 'break') {
                                        $cell_class = 'break-cell';
                                    } elseif ($slot['slot_type'] === 'lunch') {
                                        $cell_class = 'lunch-cell';
                                    } elseif ($cell_data) {
                                        $cell_class = 'class-slot';
                                        if ($cell_data['is_lab']) { $cell_class .= ' lab'; }
                                    } else {
                                        $cell_class = 'empty-slot';
                                    }
                                    ?>
                                    <td class="<?php echo $cell_class; ?>">
                                        <?php if ($slot['slot_type'] === 'break'): ?>
                                            --
                                        <?php elseif ($slot['slot_type'] === 'lunch'): ?>
                                            --
                                        <?php elseif ($cell_data): ?>
                                            <div class="subject-name"><?php echo $cell_data['subject_name']; ?></div>
                                            <?php if ($view_mode === 'class'): ?>
                                                <div class="faculty-name"><?php echo $cell_data['faculty_name']; ?></div>
                                            <?php else: ?>
                                                <div class="class-name"><?php echo $cell_data['class_name']; ?></div>
                                            <?php endif; ?>
                                            <?php if ($cell_data['is_lab']): ?>
                                                <div class="lab-badge">LAB</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            --
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>

                    <div class="legend">
                        <div class="legend-item"><div class="legend-box" style="background:#e8f5e9;"></div> Lecture</div>
                        <div class="legend-item"><div class="legend-box" style="background:#e3f2fd;border:2px solid #2196f3;"></div> Lab</div>
                        <div class="legend-item"><div class="legend-box" style="background:#fff8e1;"></div> Break</div>
                        <div class="legend-item"><div class="legend-box" style="background:#ffecb3;"></div> Lunch</div>
                    </div>

                <?php elseif ($selected_id > 0): ?>
                    <div class="no-data">
                        <h3>No timetable data found</h3>
                        <p>Please generate the timetable first.</p>
                        <br><a href="generate.php" class="btn">Go to Generate</a>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <h3>Select a <?php echo $view_mode === 'class' ? 'Class' : 'Faculty'; ?> to view the timetable</h3>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
