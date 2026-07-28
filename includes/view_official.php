<?php
// Official (Excel import) timetable views.
// Expects $view_mode (master|class|faculty) and $selected_key from view.php.

$schedules = tt_load();
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$class_names = array_map(static fn($s) => $s['class_name'], $schedules);
$faculty_names = tt_faculty_list($schedules);

/** [time][day] => session, for one schedule. */
function official_grid(array $schedule): array {
    $grid = [];
    foreach ($schedule['sessions'] as $session) {
        $day = $session['day'] ?? '';
        $time = $session['time'] ?? '';
        if ($day && $time) { $grid[$time][$day] = $session; }
    }
    return $grid;
}

function official_cell(array $session, string $context): string {
    $out = '<div class="subject-name">' . htmlspecialchars($session['subject_code'] ?: $session['entry']) . '</div>';
    $title = $session['subject'] !== '' ? $session['subject'] : $session['entry'];
    if ($context === 'faculty') {
        $out .= '<div class="class-name">' . htmlspecialchars($title) . '</div>';
    } elseif ($session['faculty'] !== '') {
        $out .= '<div class="faculty-name">' . htmlspecialchars($session['faculty']) . '</div>';
    }
    if ($session['room'] !== '') {
        $out .= '<div class="room-name">' . htmlspecialchars($session['room']) . '</div>';
    }
    if (!empty($session['is_lab'])) { $out .= '<div class="lab-badge">LAB</div>'; }
    return $out;
}

function official_time_cell(string $time, string $break_label): void {
    $class = $break_label === '' ? '' : (stripos($break_label, 'lunch') !== false ? 'lunch-cell' : 'break-cell');
    echo '<td class="time-cell ' . $class . '">' . htmlspecialchars($time);
    if ($break_label !== '') { echo '<div class="break-label">' . htmlspecialchars($break_label) . '</div>'; }
    echo '</td>';
}

/** Duration comes from the Excel merged-cell height: 1 or 2 timetable slots. */
function official_duration_slots(?array $session): int {
    return max(1, (int) ($session['duration_slots'] ?? 1));
}
?>

<?php if (!$schedules): ?>
    <div class="no-data">
        <h3>No imported timetable data found</h3>
        <p>Run <code>import_excel_timetables.ps1</code> after placing the Excel workbook in this project.</p>
    </div>

<?php elseif ($view_mode === 'master'): ?>
    <?php
    // [day][time][class_name] => session
    $master = [];
    $times = tt_time_slots($schedules);
    $breaks = [];
    foreach ($schedules as $schedule) {
        foreach ($schedule['sessions'] as $session) {
            $day = $session['day'] ?? '';
            $time = $session['time'] ?? '';
            if (!$time) { continue; }
            if (!empty($session['is_break'])) { $breaks[$time] = trim((string) $session['entry']); continue; }
            if ($day) { $master[$day][$time][$schedule['class_name']] = $session; }
        }
    }
    ?>
    <?php foreach ($days as $day): ?>
        <?php $covered = array_fill_keys($class_names, 0); ?>
        <div class="master-day-section">
            <div class="master-day-title"><?php echo $day; ?></div>
            <div class="timetable-container">
                <table class="master-grid">
                    <tr>
                        <th class="time-header">Time Slot</th>
                        <?php foreach ($class_names as $name): ?>
                            <th class="class-header"><?php echo htmlspecialchars($name); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <?php foreach ($times as $time): ?>
                        <tr>
                            <?php $break_label = $breaks[$time] ?? ''; official_time_cell($time, $break_label); ?>
                            <?php if ($break_label !== ''): ?>
                                <td colspan="<?php echo count($class_names); ?>" class="<?php echo stripos($break_label, 'lunch') !== false ? 'lunch-cell' : 'break-cell'; ?>"><?php echo htmlspecialchars($break_label); ?></td>
                            <?php else: ?>
                                <?php foreach ($class_names as $name): ?>
                                    <?php
                                    if ($covered[$name] > 0) { $covered[$name]--; continue; }
                                    $session = $master[$day][$time][$name] ?? null;
                                    $duration = official_duration_slots($session);
                                    if ($session && $duration > 1) { $covered[$name] = $duration - 1; }
                                    ?>
                                    <td<?php echo $duration > 1 && $session ? ' rowspan="' . $duration . '"' : ''; ?> class="<?php echo $session ? 'class-slot' . (!empty($session['is_lab']) ? ' lab' : '') : 'empty-slot'; ?>">
                                        <?php echo $session ? official_cell($session, 'class') : '--'; ?>
                                    </td>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

<?php elseif ($view_mode === 'faculty'): ?>
    <form method="GET" class="filter-bar">
        <input type="hidden" name="source" value="official">
        <input type="hidden" name="mode" value="faculty">
        <label style="font-size:13px;font-weight:600;">Select Faculty:</label>
        <select name="key" onchange="this.form.submit()">
            <option value="">-- Select Faculty --</option>
            <?php foreach ($faculty_names as $name): ?>
                <option value="<?php echo htmlspecialchars($name); ?>" <?php echo $name === $selected_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($selected_key === ''): ?>
        <div class="no-data"><h3>Select a faculty member to view their timetable</h3></div>
    <?php else: ?>
        <?php
        $grid = [];
        $breaks = [];
        $times = tt_time_slots($schedules);
        foreach ($schedules as $schedule) {
            foreach ($schedule['sessions'] as $session) {
                $time = $session['time'] ?? '';
                if (!$time) { continue; }
                if (!empty($session['is_break'])) { $breaks[$time] = trim((string) $session['entry']); continue; }
                if (($session['faculty'] ?? '') !== $selected_key) { continue; }
                $session['subject'] = $schedule['class_name'];
                $grid[$time][$session['day']][] = $session;
            }
        }
        $covered = array_fill_keys($days, 0);
        ?>
        <h2 style="margin:0 0 12px;color:#6B1B5E;"><?php echo htmlspecialchars($selected_key); ?></h2>
        <div class="timetable-container">
            <table class="timetable-grid">
                <tr><th style="min-width:150px;">Time Slot</th><?php foreach ($days as $day): ?><th><?php echo $day; ?></th><?php endforeach; ?></tr>
                <?php foreach ($times as $time): ?>
                    <tr>
                        <?php $break_label = $breaks[$time] ?? ''; official_time_cell($time, $break_label); ?>
                        <?php if ($break_label !== ''): ?>
                            <td colspan="<?php echo count($days); ?>" class="<?php echo stripos($break_label, 'lunch') !== false ? 'lunch-cell' : 'break-cell'; ?>"><?php echo htmlspecialchars($break_label); ?></td>
                        <?php else: ?>
                            <?php foreach ($days as $day): ?>
                                <?php
                                if ($covered[$day] > 0) { $covered[$day]--; continue; }
                                $items = $grid[$time][$day] ?? [];
                                $duration = count($items) === 1 ? official_duration_slots($items[0]) : 1;
                                if ($duration > 1) { $covered[$day] = $duration - 1; }
                                ?>
                                <td<?php echo $duration > 1 ? ' rowspan="' . $duration . '"' : ''; ?> class="<?php echo $items ? 'class-slot' . (!empty($items[0]['is_lab']) ? ' lab' : '') : 'empty-slot'; ?>">
                                    <?php
                                    if (!$items) { echo '--'; }
                                    foreach ($items as $session) { echo official_cell($session, 'faculty'); }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

<?php else: ?>
    <?php
    $selected = null;
    foreach ($schedules as $schedule) {
        if ($schedule['id'] === $selected_key || $schedule['class_name'] === $selected_key) { $selected = $schedule; break; }
    }
    ?>
    <form method="GET" class="filter-bar">
        <input type="hidden" name="source" value="official">
        <input type="hidden" name="mode" value="class">
        <label style="font-size:13px;font-weight:600;">Select Class:</label>
        <select name="key" onchange="this.form.submit()">
            <option value="">-- Select Class --</option>
            <?php foreach ($schedules as $schedule): ?>
                <option value="<?php echo htmlspecialchars($schedule['id']); ?>" <?php echo $selected && $schedule['id'] === $selected['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($schedule['class_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (!$selected): ?>
        <div class="no-data"><h3>Select a class to view its timetable</h3></div>
    <?php else: ?>
        <?php $grid = official_grid($selected); $times = tt_time_slots([$selected]); $covered = array_fill_keys($days, 0); ?>
        <h2 style="margin:0 0 5px;color:#6B1B5E;"><?php echo htmlspecialchars($selected['class_name']); ?></h2>
        <p class="schedule-meta">
            <?php echo htmlspecialchars($selected['program'] ?? ''); ?>
            <?php if (!empty($selected['semester'])): ?> | Semester <?php echo htmlspecialchars($selected['semester']); ?><?php endif; ?>
            <?php if (!empty($selected['effective_from'])): ?> | <?php echo htmlspecialchars($selected['effective_from']); ?><?php endif; ?>
        </p>

        <div class="timetable-container">
            <table class="timetable-grid">
                <tr><th style="min-width:150px;">Time Slot</th><?php foreach ($days as $day): ?><th><?php echo $day; ?></th><?php endforeach; ?></tr>
                <?php foreach ($times as $time): $break_label = tt_break_label($grid, $time); ?>
                    <tr>
                        <?php official_time_cell($time, $break_label); ?>
                        <?php if ($break_label !== ''): ?>
                            <td colspan="<?php echo count($days); ?>" class="<?php echo stripos($break_label, 'lunch') !== false ? 'lunch-cell' : 'break-cell'; ?>"><?php echo htmlspecialchars($break_label); ?></td>
                        <?php else: ?>
                            <?php foreach ($days as $day): ?>
                                <?php
                                if ($covered[$day] > 0) { $covered[$day]--; continue; }
                                $session = $grid[$time][$day] ?? null;
                                $duration = official_duration_slots($session);
                                if ($session && $duration > 1) { $covered[$day] = $duration - 1; }
                                ?>
                                <td<?php echo $duration > 1 && $session ? ' rowspan="' . $duration . '"' : ''; ?> class="<?php echo $session ? 'class-slot' . (!empty($session['is_lab']) ? ' lab' : '') : 'empty-slot'; ?>">
                                    <?php echo $session ? official_cell($session, 'class') : '--'; ?>
                                </td>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <?php if (!empty($selected['course_faculty'])): ?>
            <div class="course-list">
                <h3 style="color:#6B1B5E;">Course &amp; Faculty</h3>
                <table><tr><th>Course</th><th>Faculty</th></tr>
                    <?php foreach ($selected['course_faculty'] as $item): ?>
                        <tr><td><?php echo htmlspecialchars(trim(preg_replace('/\s+/', ' ', $item['course'] ?? ''))); ?></td><td><?php echo htmlspecialchars($item['faculty'] ?? ''); ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
