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
$faculty_timetables = [];
if ($view_mode === 'all_faculty') {
    // One query for everyone, grouped in PHP — a query per faculty member would be dozens of round trips.
    $query = "SELECT t.*, d.day_name, ts.start_time, ts.end_time, ts.slot_type, s.subject_name, f.faculty_name, c.class_name, r.room_name, b.building_name FROM timetable t JOIN working_days d ON t.day_id = d.day_id JOIN time_slots ts ON t.slot_id = ts.slot_id LEFT JOIN subjects s ON t.subject_id = s.subject_id LEFT JOIN faculty f ON t.faculty_id = f.faculty_id LEFT JOIN classes c ON t.class_id = c.class_id LEFT JOIN rooms r ON t.room_id = r.room_id LEFT JOIN buildings b ON r.building_id = b.building_id WHERE t.faculty_id IS NOT NULL ORDER BY d.day_order, ts.slot_number";
    foreach (db_get_rows($conn, $query) as $row) {
        $faculty_timetables[$row['faculty_id']][$row['day_id']][$row['slot_id']] = $row;
    }
} elseif ($view_mode === 'master' || $view_mode === 'year' || $view_mode === 'year_classes' || $view_mode === 'year_faculty') {
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

/** Slot type decides the cell colour before any booking does. */
function generated_cell_class($slot, $cell_data) {
    if ($slot['slot_type'] === 'break') { return 'break-cell'; }
    if ($slot['slot_type'] === 'lunch') { return 'lunch-cell'; }
    if (!$cell_data) { return 'empty-slot'; }
    return 'class-slot' . ($cell_data['is_lab'] ? ' lab' : '');
}

function generated_time_cell($slot, $sep = '-') {
    $class = $slot['slot_type'] === 'lunch' ? 'lunch-cell' : ($slot['slot_type'] === 'break' ? 'break-cell' : '');
    echo '<td class="time-cell ' . $class . '">' . format_time($slot['start_time']) . ' ' . $sep . ' ' . format_time($slot['end_time']);
    if ($slot['slot_type'] === 'lunch' || $slot['slot_type'] === 'break') {
        echo '<div class="break-label">' . ($slot['slot_type'] === 'lunch' ? 'Lunch Break' : 'Short Break') . '</div>';
    }
    echo '</td>';
}

/** One printable day section of the master grid, limited to $classes. */
function generated_master_day($day, $classes, $master_timetable, $slot_list) {
    ?>
    <div class="master-day-section">
        <div class="master-day-title"><?php echo htmlspecialchars($day['day_name']); ?></div>
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
                        <?php generated_time_cell($slot); ?>
                        <?php foreach ($classes as $class): ?>
                            <?php $cell_data = $master_timetable[$day['day_id']][$slot['slot_id']][$class['class_id']] ?? null; ?>
                            <td class="<?php echo generated_cell_class($slot, $cell_data); ?>">
                                <?php if ($slot['slot_type'] === 'break' || $slot['slot_type'] === 'lunch' || !$cell_data): ?>
                                    --
                                <?php else: ?>
                                    <div class="subject-name"><?php echo htmlspecialchars($cell_data['subject_name'] ?? ''); ?></div>
                                    <div class="faculty-name"><?php echo htmlspecialchars($cell_data['faculty_name'] ?? ''); ?></div>
                                    <div class="room-name"><?php echo htmlspecialchars(($cell_data['room_name'] ?? '') . ' / ' . ($cell_data['building_name'] ?? '')); ?></div>
                                    <?php if ($cell_data['is_lab']): ?><div class="lab-badge">LAB</div><?php endif; ?>
                                    <?php if ($cell_data['energy_score'] > 0): ?><div class="energy-badge">Eco+<?php echo $cell_data['energy_score']; ?></div><?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
    <?php
}

/** Distinct room_id/faculty_id (with display name) actually booked for a set of class ids. */
function generated_year_resources($master_timetable, $class_ids) {
    $class_ids = array_flip($class_ids);
    $rooms = []; $faculty = [];
    foreach ($master_timetable as $day) {
        foreach ($day as $slot) {
            foreach ($slot as $class_id => $cell) {
                if (!isset($class_ids[$class_id])) { continue; }
                if (!empty($cell['room_id'])) { $rooms[$cell['room_id']] = $cell['room_name'] . ' - ' . ($cell['building_name'] ?? ''); }
                if (!empty($cell['faculty_id'])) { $faculty[$cell['faculty_id']] = $cell['faculty_name']; }
            }
        }
    }
    asort($rooms); asort($faculty);
    return [$rooms, $faculty];
}

/** Full week schedule for one room or faculty member, regardless of year — same query shape as the single room/faculty view. */
function generated_entity_schedule($conn, $type, $id) {
    $col = $type === 'room' ? 't.room_id' : ($type === 'class' ? 't.class_id' : 't.faculty_id');
    $query = "SELECT t.*, d.day_name, d.day_order, ts.start_time, ts.end_time, ts.slot_type, s.subject_name, s.subject_code, f.faculty_name, f.faculty_code, c.class_name, r.room_name, b.building_name FROM timetable t JOIN working_days d ON t.day_id = d.day_id JOIN time_slots ts ON t.slot_id = ts.slot_id LEFT JOIN subjects s ON t.subject_id = s.subject_id LEFT JOIN faculty f ON t.faculty_id = f.faculty_id LEFT JOIN classes c ON t.class_id = c.class_id LEFT JOIN rooms r ON t.room_id = r.room_id LEFT JOIN buildings b ON r.building_id = b.building_id WHERE $col = ? ORDER BY d.day_order, ts.slot_number";
    $rows = db_get_rows($conn, $query, "i", [$id]);
    $data = [];
    foreach ($rows as $row) { $data[$row['day_id']][$row['slot_id']] = $row; }
    return $data;
}

/** The time x day table used by the class, faculty and room views. */
function generated_person_table($timetable_data, $day_list, $slot_list, $view_mode) {
    ?>
    <div class="timetable-container">
        <table class="timetable-grid">
            <tr>
                <th style="min-width:160px;">Time Slot</th>
                <?php foreach ($day_list as $day): ?><th><?php echo htmlspecialchars($day['day_name']); ?></th><?php endforeach; ?>
            </tr>
            <?php foreach ($slot_list as $slot): ?>
                <tr>
                    <?php generated_time_cell($slot, 'TO'); ?>
                    <?php foreach ($day_list as $day): ?>
                        <?php $cell_data = $timetable_data[$day['day_id']][$slot['slot_id']] ?? null; ?>
                        <td class="<?php echo generated_cell_class($slot, $cell_data); ?>">
                            <?php if ($slot['slot_type'] === 'break' || $slot['slot_type'] === 'lunch' || !$cell_data): ?>
                                --
                            <?php else: ?>
                                <div class="subject-name"><?php echo htmlspecialchars($cell_data['subject_name'] ?? ''); ?></div>
                                <?php if ($view_mode === 'class'): ?>
                                    <div class="faculty-name"><?php echo htmlspecialchars($cell_data['faculty_name'] ?? ''); ?></div>
                                <?php elseif ($view_mode === 'faculty' || $view_mode === 'all_faculty'): ?>
                                    <div class="class-name"><?php echo htmlspecialchars($cell_data['class_name'] ?? ''); ?></div>
                                <?php else: ?>
                                    <div class="class-name"><?php echo htmlspecialchars($cell_data['class_name'] ?? ''); ?></div>
                                    <div class="faculty-name"><?php echo htmlspecialchars($cell_data['faculty_name'] ?? ''); ?></div>
                                <?php endif; ?>
                                <div class="room-name"><?php echo htmlspecialchars(($cell_data['room_name'] ?? '') . ' / ' . ($cell_data['building_name'] ?? '')); ?></div>
                                <?php if ($cell_data['is_lab']): ?><div class="lab-badge">LAB</div><?php endif; ?>
                                <?php if ($cell_data['energy_score'] > 0): ?><div class="energy-badge">Eco+<?php echo $cell_data['energy_score']; ?></div><?php endif; ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
}
?>

                <?php if (!in_array($view_mode, ['master', 'year', 'all_faculty'], true)): ?>
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
                            <?php generated_master_day($day, $classes, $master_timetable, $slot_list); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="master-empty">
                            <h3>No Master Timetable Data Available</h3>
                            <p>Please generate the timetable first to see all classes at once.</p>
                            <br><a href="generate.php" class="btn btn-success">Generate Timetable</a>
                        </div>
                    <?php endif; ?>

                <?php elseif ($view_mode === 'year'): ?>

                    <?php
                    $class_names = array_column($classes, 'class_name');
                    $years = tt_year_list($class_names);
                    $shown_years = in_array($selected_key, $years, true) ? [$selected_key] : $years;
                    ?>
                    <form method="GET" class="filter-bar">
                        <input type="hidden" name="source" value="generated">
                        <input type="hidden" name="mode" value="year">
                        <label style="font-size:13px;font-weight:600;">Select Year:</label>
                        <select name="key" onchange="this.form.submit()">
                            <option value="">-- All Years --</option>
                            <?php foreach ($years as $code): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code === $selected_key ? 'selected' : ''; ?>><?php echo htmlspecialchars(tt_year_label($code) . ' (' . $code . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php if (empty($master_timetable) || !$years): ?>
                        <div class="master-empty">
                            <h3>No Timetable Data To Group By Year</h3>
                            <p>Generate the timetable, and name classes with a year prefix (FY / SY / TY / BE).</p>
                            <br><a href="generate.php" class="btn btn-success">Generate Timetable</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($shown_years as $code): ?>
                            <?php $year_classes = array_values(array_filter($classes, static fn($c) => tt_year_code($c['class_name']) === $code)); ?>
                            <h2 class="year-title"><?php echo htmlspecialchars(tt_year_label($code)); ?><span class="year-sub"><?php echo count($year_classes); ?> classes</span></h2>
                            <?php foreach ($day_list as $day): ?>
                                <?php generated_master_day($day, $year_classes, $master_timetable, $slot_list); ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php if (count($shown_years) === 1): ?>
                            <div class="filter-bar" style="margin-top:20px;">
                                <a class="btn" href="view.php?source=generated&mode=year_classes&key=<?php echo urlencode($shown_years[0]); ?>">Print: All Class Timetables (PDF)</a>
                                <a class="btn" href="view.php?source=generated&mode=year_faculty&key=<?php echo urlencode($shown_years[0]); ?>">Print: All Faculty Timetables (PDF)</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                <?php elseif ($view_mode === 'year_classes' || $view_mode === 'year_faculty'): ?>

                    <?php
                    $year_classes = array_values(array_filter($classes, static fn($c) => tt_year_code($c['class_name']) === $selected_key));
                    $year_class_ids = array_column($year_classes, 'class_id');
                    [$year_room_ids, $year_faculty_ids] = generated_year_resources($master_timetable, $year_class_ids);
                    ?>
                    <h2 class="year-title"><?php echo htmlspecialchars(tt_year_label($selected_key)); ?> &mdash; <?php echo $view_mode === 'year_classes' ? 'Class Timetables' : 'Faculty Timetables'; ?></h2>

                    <?php if ($view_mode === 'year_classes'): ?>
                        <?php foreach ($year_classes as $class): ?>
                            <?php $entity_rows = generated_entity_schedule($conn, 'class', $class['class_id']); ?>
                            <div class="master-day-section">
                                <div class="master-day-title"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                <?php generated_person_table($entity_rows, $day_list, $slot_list, 'class'); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($year_faculty_ids as $faculty_id => $faculty_label): ?>
                            <div class="master-day-section">
                                <div class="master-day-title"><?php echo htmlspecialchars($faculty_label); ?></div>
                                <?php generated_person_table(generated_entity_schedule($conn, 'faculty', $faculty_id), $day_list, $slot_list, 'faculty'); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <?php elseif ($view_mode === 'all_faculty'): ?>

                    <?php if (!$faculty_timetables): ?>
                        <div class="master-empty">
                            <h3>No Faculty Timetable Data Available</h3>
                            <p>Please generate the timetable first.</p>
                            <br><a href="generate.php" class="btn btn-success">Generate Timetable</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($faculty as $person): ?>
                            <?php $data = $faculty_timetables[$person['faculty_id']] ?? null; ?>
                            <?php if (!$data) { continue; } ?>
                            <div class="master-day-section">
                                <div class="master-day-title"><?php echo htmlspecialchars($person['faculty_name']); ?></div>
                                <?php generated_person_table($data, $day_list, $slot_list, 'all_faculty'); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                <?php else: ?>

                    <?php if ($selected_id > 0 && !empty($timetable_data)): ?>
                        <?php generated_person_table($timetable_data, $day_list, $slot_list, $view_mode); ?>

                        <?php if ($view_mode === 'class'): ?>
                            <?php
                            $course_faculty = [];
                            foreach ($rows as $row) {
                                if (empty($row['subject_name'])) { continue; }
                                $course_faculty[$row['subject_name'] . '|' . $row['faculty_name']] = [$row['subject_name'], $row['faculty_name'] ?? ''];
                            }
                            ksort($course_faculty);
                            ?>
                            <?php if ($course_faculty): ?>
                                <div class="course-list">
                                    <h3 style="color:#6B1B5E;">Course &amp; Faculty</h3>
                                    <table><tr><th>Course</th><th>Faculty</th></tr>
                                        <?php foreach ($course_faculty as [$course, $fac]): ?>
                                            <tr><td><?php echo htmlspecialchars($course); ?></td><td><?php echo htmlspecialchars($fac); ?></td></tr>
                                        <?php endforeach; ?>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

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
