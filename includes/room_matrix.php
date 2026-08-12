<?php
/**
 * The All Rooms matrix: rooms down the left, day > period across.
 *
 * Shape follows the department's own master sheet
 * (FY/8 MARCH Master TT_FINAL Even Term_AY 2025-26_FE blank.xls, sheet SOE_STT):
 * `Room No. | Class Type | Capacity` then a block of period columns per day. It
 * answers "which room is free when", which one-table-per-room cannot show.
 *
 * Both sources build the same structure so the HTML renderer and the Excel writer
 * consume one grid rather than two that drift apart:
 *
 *   days   => [day_name => [['key' => '0930', 'label' => '09:30-10:30'], ...]]
 *   rooms  => [['name' => '101', 'type' => 'CR', 'capacity' => 60], ...]
 *   cells  => [room_name][day_name][period_key] => [
 *                 'text' => 'SY CSE A', 'sub' => 'DBMS',
 *                 'lab' => bool, 'blocked' => bool
 *             ]
 *
 * Break and lunch periods are left out: they are empty for every room, so they would
 * only cost width on an already wide sheet.
 */

/** Short room-type code, matching the reference sheet's "CR" column. */
function tt_room_type_code(string $type): string {
    static $codes = ['classroom' => 'CR', 'lab' => 'LAB', 'seminar' => 'SEM', 'auditorium' => 'AUD'];
    return $codes[strtolower($type)] ?? strtoupper(substr($type, 0, 3));
}

/** Matrix for the official (Excel import) source. */
function tt_room_matrix_official(array $schedules, array $external_blocks, array $day_names): array {
    // A time is a break if any class marks it as one — the sheets agree across classes.
    $break_times = [];
    foreach ($schedules as $s) {
        foreach ($s['sessions'] as $session) {
            if (!empty($session['is_break'])) { $break_times[$session['time'] ?? ''] = true; }
        }
    }
    $periods = [];
    foreach (tt_time_slots($schedules) as $time) {
        if (isset($break_times[$time])) { continue; }
        $periods[] = ['key' => tt_time_key($time), 'label' => $time];
    }
    $days = array_fill_keys($day_names, $periods);

    $cells = [];
    foreach ($schedules as $s) {
        foreach ($s['sessions'] as $session) {
            if (!empty($session['is_break'])) { continue; }
            $room = tt_normalize_room((string) ($session['room'] ?? ''));
            $day = $session['day'] ?? '';
            $time = $session['time'] ?? '';
            if ($room === '' || $day === '' || $time === '' || isset($break_times[$time])) { continue; }
            $cells[$room][$day][tt_time_key($time)] = [
                'text' => $s['class_name'],
                'sub' => (string) ($session['subject_code'] ?? ''),
                'lab' => !empty($session['is_lab']),
                'blocked' => false,
            ];
        }
    }
    tt_room_matrix_add_blocks($cells, $external_blocks);

    // The workbook carries no room metadata, so type and capacity stay blank here
    // rather than the columns disappearing — the printed sheet keeps one shape.
    $rooms = [];
    foreach (tt_room_list($schedules, array_keys($external_blocks)) as $name) {
        $rooms[] = ['name' => $name, 'type' => '', 'capacity' => ''];
    }
    return ['days' => $days, 'rooms' => $rooms, 'cells' => $cells];
}

/** Matrix for the generated (scheduler) source. */
function tt_room_matrix_generated($conn, array $day_rows, array $slot_rows, array $room_rows): array {
    // Only the shared grid is used for the column headers: FY's parallel rows carry the
    // same slot_numbers at different times, and a room is shared by both, so times are
    // merged on the clock value rather than on slot_number.
    $periods = [];
    $slot_key = [];
    foreach ($slot_rows as $slot) {
        if ($slot['slot_type'] !== 'class') { continue; }
        $key = substr(str_replace(':', '', (string) $slot['start_time']), 0, 4);
        $slot_key[$slot['slot_id']] = $key;
        if (!isset($periods[$key])) {
            $periods[$key] = [
                'key' => $key,
                'label' => date('H:i', strtotime($slot['start_time'])) . '-' . date('H:i', strtotime($slot['end_time'])),
            ];
        }
    }
    ksort($periods);
    $periods = array_values($periods);

    $day_names = array_column($day_rows, 'day_name');
    $days = array_fill_keys($day_names, $periods);
    $day_name_by_id = array_column($day_rows, 'day_name', 'day_id');

    $sql = "SELECT t.day_id, t.slot_id, t.is_lab, r.room_name, c.class_name, s.subject_name
            FROM timetable t
            JOIN rooms r ON r.room_id = t.room_id
            LEFT JOIN classes c ON c.class_id = t.class_id
            LEFT JOIN subjects s ON s.subject_id = t.subject_id";
    $cells = [];
    foreach (db_get_rows($conn, $sql) as $row) {
        $day = $day_name_by_id[$row['day_id']] ?? '';
        $key = $slot_key[$row['slot_id']] ?? '';
        if ($day === '' || $key === '') { continue; }
        $cells[tt_normalize_room((string) $row['room_name'])][$day][$key] = [
            'text' => (string) ($row['class_name'] ?? ''),
            'sub' => (string) ($row['subject_name'] ?? ''),
            'lab' => !empty($row['is_lab']),
            'blocked' => false,
        ];
    }

    $blocks = [];
    $sql = "SELECT r.room_name, d.day_name, ts.start_time, ru.reason
            FROM room_unavailable ru
            JOIN rooms r ON r.room_id = ru.room_id
            JOIN working_days d ON d.day_id = ru.day_id
            JOIN time_slots ts ON ts.slot_id = ru.slot_id";
    foreach (db_get_rows($conn, $sql) as $row) {
        $key = substr(str_replace(':', '', (string) $row['start_time']), 0, 4);
        $blocks[tt_normalize_room((string) $row['room_name'])][$key][$row['day_name']] = (string) ($row['reason'] ?? '');
    }
    tt_room_matrix_add_blocks($cells, $blocks);

    $rooms = [];
    foreach ($room_rows as $row) {
        $rooms[] = [
            'name' => tt_normalize_room((string) $row['room_name']),
            'type' => tt_room_type_code((string) ($row['room_type'] ?? '')),
            'capacity' => (string) ($row['capacity'] ?? ''),
        ];
    }
    return ['days' => $days, 'rooms' => $rooms, 'cells' => $cells];
}

/**
 * Fold external bookings into the cell map. $blocks is [room][time_key][day] => reason.
 * A real booking is never overwritten: a clash between the workbook and a block is the
 * department's to resolve, and hiding it would make the sheet lie.
 */
function tt_room_matrix_add_blocks(array &$cells, array $blocks): void {
    foreach ($blocks as $room => $by_key) {
        foreach ($by_key as $key => $by_day) {
            foreach ($by_day as $day => $reason) {
                if (isset($cells[$room][$day][$key])) { continue; }
                $cells[$room][$day][$key] = ['text' => $reason, 'sub' => '', 'lab' => false, 'blocked' => true];
            }
        }
    }
}

/** Render the matrix as HTML. */
function tt_render_room_matrix(array $matrix): void {
    $days = $matrix['days'];
    if (!$matrix['rooms'] || !$days) {
        echo '<div class="no-data"><h3>No rooms to show</h3></div>';
        return;
    }
    ?>
    <div class="timetable-container">
        <table class="room-matrix">
            <thead>
            <tr>
                <th rowspan="2">Room No.</th>
                <th rowspan="2">Class Type</th>
                <th rowspan="2">Capacity</th>
                <?php foreach ($days as $day_name => $periods): ?>
                    <th class="day-header" colspan="<?php echo max(1, count($periods)); ?>"><?php echo htmlspecialchars(strtoupper($day_name)); ?></th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach ($days as $periods): ?>
                    <?php foreach ($periods as $period): ?>
                        <th class="period-header"><?php echo htmlspecialchars($period['label']); ?></th>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($matrix['rooms'] as $room): ?>
                <tr>
                    <td class="room-cell"><?php echo htmlspecialchars($room['name']); ?></td>
                    <td class="meta-cell"><?php echo htmlspecialchars($room['type']); ?></td>
                    <td class="meta-cell"><?php echo htmlspecialchars((string) $room['capacity']); ?></td>
                    <?php foreach ($days as $day_name => $periods): ?>
                        <?php foreach ($periods as $i => $period): ?>
                            <?php $cell = $matrix['cells'][$room['name']][$day_name][$period['key']] ?? null; ?>
                            <td class="<?php
                                echo $i === 0 ? 'day-start ' : '';
                                echo $cell ? ($cell['blocked'] ? 'm-blocked' : 'm-busy' . ($cell['lab'] ? ' lab' : '')) : 'm-free';
                            ?>">
                                <?php if (!$cell): ?>
                                    &middot;
                                <?php else: ?>
                                    <span class="m-class"><?php echo htmlspecialchars($cell['text']); ?></span>
                                    <?php if ($cell['sub'] !== ''): ?><span class="m-sub"><?php echo htmlspecialchars($cell['sub']); ?></span><?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
