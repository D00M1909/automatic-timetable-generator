<?php
// Generated (scheduler) timetable views - restored from the pre-import version of view.php.
// Expects $conn, $view_mode (master|class|faculty|room) and $selected_id from view.php.

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

                <?php if ($view_mode !== 'master'): ?>
                <form method="GET" class="filter-bar">
                    <input type="hidden" name="source" value="generated">
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
                                        <td class="time-cell <?php echo $slot['slot_type'] === 'lunch' ? 'lunch-cell' : ($slot['slot_type'] === 'break' ? 'break-cell' : ''); ?>">
                                            <?php echo format_time($slot['start_time']); ?> - <?php echo format_time($slot['end_time']); ?>
                                            <?php if ($slot['slot_type'] === 'lunch' || $slot['slot_type'] === 'break'): ?>
                                                <div class="break-label"><?php echo $slot['slot_type'] === 'lunch' ? 'Lunch Break' : 'Short Break'; ?></div>
                                            <?php endif; ?>
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
                                    <td class="time-cell <?php echo $slot['slot_type'] === 'lunch' ? 'lunch-cell' : ($slot['slot_type'] === 'break' ? 'break-cell' : ''); ?>">
                                        <?php echo format_time($slot['start_time']); ?> TO <?php echo format_time($slot['end_time']); ?>
                                        <?php if ($slot['slot_type'] === 'lunch' || $slot['slot_type'] === 'break'): ?>
                                            <div class="break-label"><?php echo $slot['slot_type'] === 'lunch' ? 'Lunch Break' : 'Short Break'; ?></div>
                                        <?php endif; ?>
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
