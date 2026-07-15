<?php
require_once 'includes/config.php';

$view_mode = $_GET['mode'] ?? 'master';
$selected_id = intval($_GET['id'] ?? 0);

$classes = db_get_rows($conn, "SELECT * FROM classes ORDER BY class_name");
$faculty = db_get_rows($conn, "SELECT * FROM faculty ORDER BY faculty_name");
$rooms = db_get_rows($conn, "SELECT r.*, b.building_name FROM rooms r JOIN buildings b ON r.building_id = b.building_id ORDER BY r.room_name");
$days = db_get_rows($conn, "SELECT * FROM working_days WHERE is_working=1 ORDER BY day_order");
$slots = db_get_rows($conn, "SELECT * FROM time_slots WHERE is_active=1 ORDER BY slot_number");

$day_list = $days;
$slot_list = $slots;

$timetable_data = [];
$selected_name = '';

// For master view, we need all classes
$master_timetable = [];
if ($view_mode === 'master') {
    // Fetch all timetable entries with class info
    $query = "SELECT t.*, d.day_name, d.day_order, ts.start_time, ts.end_time, ts.slot_type, s.subject_name, s.subject_code, f.faculty_name, f.faculty_code, c.class_name, c.class_code, r.room_name, b.building_name FROM timetable t JOIN working_days d ON t.day_id = d.day_id JOIN time_slots ts ON t.slot_id = ts.slot_id LEFT JOIN subjects s ON t.subject_id = s.subject_id LEFT JOIN faculty f ON t.faculty_id = f.faculty_id LEFT JOIN classes c ON t.class_id = c.class_id LEFT JOIN rooms r ON t.room_id = r.room_id LEFT JOIN buildings b ON r.building_id = b.building_id ORDER BY d.day_order, ts.slot_number, c.class_name";
    $rows = db_get_rows($conn, $query);

    // Organize by [day_id][slot_id][class_id]
    foreach ($rows as $row) {
        $master_timetable[$row['day_id']][$row['slot_id']][$row['class_id']] = $row;
    }
    $selected_name = 'All Classes - Master View';
} elseif ($selected_id > 0) {
    if ($view_mode === 'class') {
        $query = "SELECT t.*, d.day_name, d.day_order, ts.start_time, ts.end_time, ts.slot_type, s.subject_name, s.subject_code, f.faculty_name, f.faculty_code, c.class_name, r.room_name, b.building_name FROM timetable t JOIN working_days d ON t.day_id = d.day_id JOIN time_slots ts ON t.slot_id = ts.slot_id LEFT JOIN subjects s ON t.subject_id = s.subject_id LEFT JOIN faculty f ON t.faculty_id = f.faculty_id LEFT JOIN classes c ON t.class_id = c.class_id LEFT JOIN rooms r ON t.room_id = r.room_id LEFT JOIN buildings b ON r.building_id = b.building_id WHERE t.class_id = ? ORDER BY d.day_order, ts.slot_number";
        $rows = db_get_rows($conn, $query, "i", [$selected_id]);
        $sel = db_get_row($conn, "SELECT class_name FROM classes WHERE class_id = ?", "i", [$selected_id]);
        $selected_name = $sel ? $sel['class_name'] : '';
    } elseif ($view_mode === 'faculty') {
        $query = "SELECT t.*, d.day_name, d.day_order, ts.start_time, ts.end_time, ts.slot_type, s.subject_name, s.subject_code, f.faculty_name, f.faculty_code, c.class_name, r.room_name, b.building_name FROM timetable t JOIN working_days d ON t.day_id = d.day_id JOIN time_slots ts ON t.slot_id = ts.slot_id LEFT JOIN subjects s ON t.subject_id = s.subject_id LEFT JOIN faculty f ON t.faculty_id = f.faculty_id LEFT JOIN classes c ON t.class_id = c.class_id LEFT JOIN rooms r ON t.room_id = r.room_id LEFT JOIN buildings b ON r.building_id = b.building_id WHERE t.faculty_id = ? ORDER BY d.day_order, ts.slot_number";
        $rows = db_get_rows($conn, $query, "i", [$selected_id]);
        $sel = db_get_row($conn, "SELECT faculty_name FROM faculty WHERE faculty_id = ?", "i", [$selected_id]);
        $selected_name = $sel ? $sel['faculty_name'] : '';
    } else {
        $query = "SELECT t.*, d.day_name, d.day_order, ts.start_time, ts.end_time, ts.slot_type, s.subject_name, s.subject_code, f.faculty_name, f.faculty_code, c.class_name, r.room_name, b.building_name FROM timetable t JOIN working_days d ON t.day_id = d.day_id JOIN time_slots ts ON t.slot_id = ts.slot_id LEFT JOIN subjects s ON t.subject_id = s.subject_id LEFT JOIN faculty f ON t.faculty_id = f.faculty_id LEFT JOIN classes c ON t.class_id = c.class_id LEFT JOIN rooms r ON t.room_id = r.room_id LEFT JOIN buildings b ON r.building_id = b.building_id WHERE t.room_id = ? ORDER BY d.day_order, ts.slot_number";
        $rows = db_get_rows($conn, $query, "i", [$selected_id]);
        $sel = db_get_row($conn, "SELECT CONCAT(r.room_name, ' (', b.building_name, ')') as name FROM rooms r JOIN buildings b ON r.building_id = b.building_id WHERE r.room_id = ?", "i", [$selected_id]);
        $selected_name = $sel ? $sel['name'] : '';
    }
    foreach ($rows as $row) {
        $timetable_data[$row['day_id']][$row['slot_id']] = $row;
    }
}

function format_time($time) {
    return date('h:i A', strtotime($time));
}

function pdf_export_link($mode, $id) {
    return "view.php?mode=" . urlencode($mode) . "&id=" . intval($id) . "&export=pdf";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Timetable - AI Smart Timetable</title>
    <?php common_styles(); ?>
    <style>
        .timetable-container { overflow-x: auto; }
        .timetable-grid { width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #ddd; }
        .timetable-grid th { background: #00BFA5; color: white; padding: 10px 8px; text-align: center; font-weight: 600; border: 1px solid #00a896; }
        .timetable-grid td { padding: 8px; text-align: center; border: 1px solid #e0e0e0; min-width: 110px; height: 60px; vertical-align: middle; }
        .timetable-grid .time-cell { background: #f8f9fa; font-weight: 600; color: #555; text-align: left; padding-left: 12px; min-width: 160px; }
        .timetable-grid .break-cell { background: #fff8e1; color: #856404; font-style: italic; }
        .timetable-grid .lunch-cell { background: #ffecb3; color: #856404; font-weight: 600; }
        .timetable-grid .class-slot { background: #e8f5e9; }
        .timetable-grid .class-slot.lab { background: #e3f2fd; border: 2px solid #2196f3; }
        .timetable-grid .empty-slot { color: #ccc; }
        .subject-name { font-weight: 600; color: #333; font-size: 12px; }
        .faculty-name { color: #666; font-size: 11px; }
        .class-name { color: #3498db; font-size: 11px; }
        .room-name { color: #9c27b0; font-size: 10px; font-weight: 600; }
        .building-name { color: #888; font-size: 10px; }
        .lab-badge { font-size: 10px; color: #2196f3; font-weight: 600; }
        .energy-badge { font-size: 9px; color: #27ae60; background: #d4edda; padding: 1px 4px; border-radius: 2px; display: inline-block; margin-top: 2px; }
        .view-toggle { display: flex; gap: 5px; margin-bottom: 15px; }
        .filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 15px; flex-wrap: wrap; }
        .filter-bar select { padding: 6px 10px; border: 1px solid #ddd; border-radius: 3px; min-width: 220px; font-size: 13px; }
        .legend { display: flex; gap: 20px; margin-top: 15px; font-size: 12px; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-box { width: 16px; height: 16px; border: 1px solid #ddd; }
        .print-header { display: none; }

        /* Master View Styles */
        .master-grid { width: 100%; border-collapse: collapse; font-size: 11px; border: 1px solid #ddd; }
        .master-grid th { background: #6B1B5E; color: white; padding: 8px 4px; text-align: center; font-weight: 600; border: 1px solid #5a1850; font-size: 11px; }
        .master-grid th.time-header { background: #00BFA5; min-width: 130px; }
        .master-grid th.class-header { background: #7B2D6E; font-size: 10px; }
        .master-grid td { padding: 4px; text-align: center; border: 1px solid #e0e0e0; min-width: 90px; height: 50px; vertical-align: middle; }
        .master-grid .time-cell { background: #f8f9fa; font-weight: 600; color: #555; text-align: left; padding-left: 10px; min-width: 130px; font-size: 11px; }
        .master-grid .break-cell { background: #fff8e1; color: #856404; font-style: italic; }
        .master-grid .lunch-cell { background: #ffecb3; color: #856404; font-weight: 600; }
        .master-grid .class-slot { background: #e8f5e9; }
        .master-grid .class-slot.lab { background: #e3f2fd; border: 2px solid #2196f3; }
        .master-grid .empty-slot { color: #ccc; font-size: 11px; }
        .master-grid .subject-name { font-weight: 600; color: #333; font-size: 10px; }
        .master-grid .faculty-name { color: #666; font-size: 9px; }
        .master-grid .room-name { color: #9c27b0; font-size: 8px; font-weight: 600; }
        .master-grid .lab-badge { font-size: 8px; color: #2196f3; font-weight: 600; }
        .master-grid .energy-badge { font-size: 8px; color: #27ae60; background: #d4edda; padding: 1px 3px; border-radius: 2px; display: inline-block; margin-top: 1px; }

        .master-day-section { margin-bottom: 25px; }
        .master-day-title { font-size: 16px; font-weight: 700; color: #6B1B5E; margin-bottom: 8px; padding: 8px 12px; background: linear-gradient(135deg, #f3e5f5, #e8d5f0); border-radius: 4px; border-left: 4px solid #6B1B5E; }

        .master-empty { text-align: center; padding: 60px 20px; color: #888; }
        .master-empty h3 { color: #6B1B5E; margin-bottom: 10px; }

        @media print {
            .master-day-section { page-break-inside: avoid; margin-bottom: 15px; }
            .master-grid { font-size: 9px; }
            .master-grid td { height: auto; padding: 3px; }
            .master-day-title { background: #f3e5f5 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <?php svg_defs(); ?>
    <?php sidebar('view'); ?>
    <?php top_header(); ?>

    <div class="content-wrapper">
        <?php breadcrumb(['Manage Time Table', 'Student Time Table']); ?>

        <div class="content-box">
            <div class="content-box-header">
                <span>View Timetable</span>
                <div style="display:flex;gap:8px;">
                    <button class="btn btn-print" onclick="window.print()">
                        <svg><use href="#icon-print"/></svg> Print
                    </button>
                </div>
            </div>
            <div class="content-box-body">
                <div class="print-header">
                    <h2>Ajeenkya DY Patil University</h2>
                    <p>AI-Generated Academic Timetable | <?php echo date('F Y'); ?></p>
                    <?php if ($selected_name): ?><p><strong><?php echo htmlspecialchars($selected_name); ?></strong></p><?php endif; ?>
                </div>

                <div class="view-toggle">
                    <a href="?mode=master" class="btn <?php echo $view_mode === 'master' ? 'btn-success' : ''; ?>">Master View (All Classes)</a>
                    <a href="?mode=class" class="btn <?php echo $view_mode === 'class' ? 'btn-success' : ''; ?>">By Class</a>
                    <a href="?mode=faculty" class="btn <?php echo $view_mode === 'faculty' ? 'btn-success' : ''; ?>">By Faculty</a>
                    <a href="?mode=room" class="btn <?php echo $view_mode === 'room' ? 'btn-success' : ''; ?>">By Room</a>
                </div>

                <?php if ($view_mode !== 'master'): ?>
                <form method="GET" class="filter-bar">
                    <input type="hidden" name="mode" value="<?php echo htmlspecialchars($view_mode); ?>">
                    <label style="font-size:13px;font-weight:600;">Select <?php echo $view_mode === 'class' ? 'Class' : ($view_mode === 'faculty' ? 'Faculty' : 'Room'); ?>:</label>
                    <select name="id" onchange="this.form.submit()">
                        <option value="">-- Select <?php echo $view_mode === 'class' ? 'Class' : ($view_mode === 'faculty' ? 'Faculty' : 'Room'); ?> --</option>
                        <?php 
                        $list = $view_mode === 'class' ? $classes : ($view_mode === 'faculty' ? $faculty : $rooms);
                        foreach ($list as $row): 
                            if ($view_mode === 'class') {
                                $id = $row['class_id']; $name = $row['class_name'] . ' (' . $row['class_code'] . ')';
                            } elseif ($view_mode === 'faculty') {
                                $id = $row['faculty_id']; $name = $row['faculty_name'] . ' (' . $row['faculty_code'] . ')';
                            } else {
                                $id = $row['room_id']; $name = $row['room_name'] . ' - ' . $row['building_name'] . ' (' . $row['room_type'] . ')';
                            }
                        ?>
                        <option value="<?php echo $id; ?>" <?php echo $selected_id == $id ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>

                <?php if ($view_mode === 'master'): ?>

                    <?php if (!empty($master_timetable) && count($classes) > 0): ?>

                        <?php foreach ($day_list as $day): ?>
                        <div class="master-day-section">
                            <div class="master-day-title"><?php echo $day['day_name']; ?></div>
                            <div class="timetable-container">
                                <table class="master-grid">
                                    <tr>
                                        <th class="time-header" style="min-width:130px;">Time Slot</th>
                                        <?php foreach ($classes as $class): ?>
                                        <th class="class-header"><?php echo htmlspecialchars($class['class_name']); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php foreach ($slot_list as $slot): ?>
                                    <tr>
                                        <td class="time-cell">
                                            <?php echo format_time($slot['start_time']); ?> - <?php echo format_time($slot['end_time']); ?>
                                        </td>
                                        <?php foreach ($classes as $class): ?>
                                            <?php 
                                            $cell_data = $master_timetable[$day['day_id']][$slot['slot_id']][$class['class_id']] ?? null;
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
                                                    <div class="subject-name"><?php echo htmlspecialchars($cell_data['subject_name'] ?? ''); ?></div>
                                                    <div class="faculty-name"><?php echo htmlspecialchars($cell_data['faculty_name'] ?? ''); ?></div>
                                                    <div class="room-name"><?php echo htmlspecialchars(($cell_data['room_name'] ?? '') . ' / ' . ($cell_data['building_name'] ?? '')); ?></div>
                                                    <?php if ($cell_data['is_lab']): ?>
                                                        <div class="lab-badge">LAB</div>
                                                    <?php endif; ?>
                                                    <?php if ($cell_data['energy_score'] > 0): ?>
                                                        <div class="energy-badge">Eco+<?php echo $cell_data['energy_score']; ?></div>
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
                        </div>
                        <?php endforeach; ?>

                        <div class="legend">
                            <div class="legend-item"><div class="legend-box" style="background:#e8f5e9;"></div> Lecture</div>
                            <div class="legend-item"><div class="legend-box" style="background:#e3f2fd;border:2px solid #2196f3;"></div> Lab</div>
                            <div class="legend-item"><div class="legend-box" style="background:#fff8e1;"></div> Break</div>
                            <div class="legend-item"><div class="legend-box" style="background:#ffecb3;"></div> Lunch</div>
                            <div class="legend-item"><span class="energy-badge" style="margin:0;">Eco+</span> Energy Optimized</div>
                        </div>

                    <?php else: ?>
                        <div class="master-empty">
                            <h3>No Master Timetable Data Available</h3>
                            <p>Please generate the timetable first to see all classes at once.</p>
                            <br><a href="generate.php" class="btn btn-success">Generate Timetable</a>
                        </div>
                    <?php endif; ?>

                <?php else: ?>

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
                                                <div class="subject-name"><?php echo htmlspecialchars($cell_data['subject_name'] ?? ''); ?></div>
                                                <?php if ($view_mode === 'class'): ?>
                                                    <div class="faculty-name"><?php echo htmlspecialchars($cell_data['faculty_name'] ?? ''); ?></div>
                                                <?php elseif ($view_mode === 'faculty'): ?>
                                                    <div class="class-name"><?php echo htmlspecialchars($cell_data['class_name'] ?? ''); ?></div>
                                                <?php else: ?>
                                                    <div class="class-name"><?php echo htmlspecialchars($cell_data['class_name'] ?? ''); ?></div>
                                                    <div class="faculty-name"><?php echo htmlspecialchars($cell_data['faculty_name'] ?? ''); ?></div>
                                                <?php endif; ?>
                                                <div class="room-name"><?php echo htmlspecialchars(($cell_data['room_name'] ?? '') . ' / ' . ($cell_data['building_name'] ?? '')); ?></div>
                                                <?php if ($cell_data['is_lab']): ?>
                                                    <div class="lab-badge">LAB</div>
                                                <?php endif; ?>
                                                <?php if ($cell_data['energy_score'] > 0): ?>
                                                    <div class="energy-badge">Eco+<?php echo $cell_data['energy_score']; ?></div>
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
                            <div class="legend-item"><div class="legend-box" style="background:#f3e5f5;"></div> Room Allocated</div>
                            <div class="legend-item"><span class="energy-badge" style="margin:0;">Eco+</span> Energy Optimized</div>
                        </div>

                    <?php elseif ($selected_id > 0): ?>
                        <div class="no-data">
                            <h3>No timetable data found</h3>
                            <p>Please generate the timetable first.</p>
                            <br><a href="generate.php" class="btn">Go to Generate</a>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <h3>Select a <?php echo $view_mode === 'class' ? 'Class' : ($view_mode === 'faculty' ? 'Faculty' : 'Room'); ?> to view the timetable</h3>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>