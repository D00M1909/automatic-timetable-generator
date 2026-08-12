<?php
/**
 * Excel (.xlsx) export for every timetable view.
 *
 * Takes the same query parameters as view.php (source, mode, key, id) so the download
 * button is just a link carrying the current query string.
 *
 * Layout follows the department's own workbooks: an ADYPU doc-control header block,
 * merged day headers over their period columns, frozen panes, the same colour legend
 * as the screen, and A4 landscape fit-to-width page setup so the file prints correctly
 * without anyone adjusting it.
 *
 * Every view is flattened into one structure before it reaches the writer:
 *
 *   sheet = [
 *     'title'  => sheet name,
 *     'blocks' => [[
 *        'caption' => optional heading above the table,
 *        'head'    => row-header column titles (['Time Slot'] or ['Room No.', ...]),
 *        'groups'  => [['label' => 'MONDAY', 'cols' => ['09:30-10:30', ...]], ...]
 *                     -- omit for a single-level header and use 'cols' instead,
 *        'cols'    => [column titles] for a single-level header,
 *        'rows'    => [['head' => [...], 'cells' => [['text','sub','style'], ...]], ...],
 *     ], ...],
 *   ]
 *
 * so the writer never needs to know which view it came from.
 */

require_once 'includes/config.php';
require_once 'includes/timetable_data.php';
require_once 'includes/room_matrix.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

const XL_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Cell fills, matching the on-screen legend so a printed sheet reads the same way.
const XL_STYLES = [
    'lecture' => 'E8F5E9',
    'lab'     => 'E3F2FD',
    'blocked' => 'ECEFF1',
    'minor'   => 'FFF3E0',
    'break'   => 'FFF8E1',
    'lunch'   => 'FFECB3',
    'free'    => null,
];

$source = ($_GET['source'] ?? 'official') === 'generated' ? 'generated' : 'official';
$mode = (string) ($_GET['mode'] ?? 'master');
$key = trim((string) ($_GET['key'] ?? ''));
$id = intval($_GET['id'] ?? 0);

$sheet_data = $source === 'generated'
    ? xl_build_generated($conn, $mode, $key, $id)
    : xl_build_official($mode, $key, $conn);

xl_send($sheet_data);

// ---------------------------------------------------------------- official source

function xl_build_official(string $mode, string $key, $conn): array {
    $schedules = tt_load();
    $blocks_by_room = tt_external_blocks($conn);
    $class_names = array_map(static fn($s) => $s['class_name'], $schedules);

    if ($mode === 'all_rooms') {
        $matrix = tt_room_matrix_official($schedules, $blocks_by_room, XL_DAYS);
        return ['title' => 'All Rooms', 'blocks' => [xl_matrix_block($matrix)]];
    }

    $times = xl_official_teaching_times($schedules);

    if ($mode === 'faculty' || $mode === 'all_faculty') {
        $names = $mode === 'faculty' && $key !== '' ? [$key] : tt_faculty_list($schedules);
        $blocks = [];
        foreach ($names as $name) {
            $grid = [];
            foreach ($schedules as $schedule) {
                foreach ($schedule['sessions'] as $session) {
                    if (!empty($session['is_break']) || ($session['faculty'] ?? '') !== $name) { continue; }
                    $grid[$session['time'] ?? ''][$session['day'] ?? ''] = [
                        'text' => $schedule['class_name'],
                        'sub' => (string) ($session['subject_code'] ?? ''),
                        'style' => !empty($session['is_lab']) ? 'lab' : 'lecture',
                    ];
                }
            }
            if ($grid) { $blocks[] = xl_day_grid_block($name, $grid, $times); }
        }
        return ['title' => $mode === 'faculty' ? ($key ?: 'Faculty') : 'All Faculty', 'blocks' => $blocks];
    }

    if ($mode === 'room') {
        $grid = [];
        foreach ($schedules as $schedule) {
            foreach ($schedule['sessions'] as $session) {
                if (!empty($session['is_break'])) { continue; }
                if (tt_normalize_room((string) ($session['room'] ?? '')) !== $key) { continue; }
                $grid[$session['time'] ?? ''][$session['day'] ?? ''] = [
                    'text' => $schedule['class_name'],
                    'sub' => (string) ($session['subject_code'] ?? ''),
                    'style' => !empty($session['is_lab']) ? 'lab' : 'lecture',
                ];
            }
        }
        foreach ($times as $time) {
            foreach ($blocks_by_room[$key][tt_time_key($time)] ?? [] as $day => $reason) {
                if (isset($grid[$time][$day])) { continue; }
                $grid[$time][$day] = ['text' => $reason, 'sub' => 'BLOCKED', 'style' => 'blocked'];
            }
        }
        return ['title' => $key ?: 'Room', 'blocks' => [xl_day_grid_block($key, $grid, $times)]];
    }

    if ($mode === 'class' && $key !== '') {
        foreach ($schedules as $schedule) {
            if ($schedule['id'] !== $key && $schedule['class_name'] !== $key) { continue; }
            $grid = [];
            foreach ($schedule['sessions'] as $session) {
                if (!empty($session['is_break'])) { continue; }
                $grid[$session['time'] ?? ''][$session['day'] ?? ''] = [
                    'text' => (string) ($session['subject_code'] ?: $session['entry']),
                    'sub' => (string) ($session['faculty'] ?? ''),
                    'style' => !empty($session['is_lab']) ? 'lab' : 'lecture',
                ];
            }
            return ['title' => $schedule['class_name'], 'blocks' => [xl_day_grid_block($schedule['class_name'], $grid, tt_time_slots([$schedule]))]];
        }
    }

    // master / year / anything else: one table per day, classes across the top.
    $shown = $class_names;
    if (($mode === 'year' || $mode === 'year_classes') && $key !== '') {
        $shown = array_values(array_filter($class_names, static fn($n) => tt_year_code($n) === $key));
    }
    $by_day = [];
    foreach ($schedules as $schedule) {
        foreach ($schedule['sessions'] as $session) {
            if (!empty($session['is_break'])) { continue; }
            $by_day[$session['day'] ?? ''][$session['time'] ?? ''][$schedule['class_name']] = [
                'text' => (string) ($session['subject_code'] ?: $session['entry']),
                'sub' => (string) ($session['faculty'] ?? ''),
                'style' => !empty($session['is_lab']) ? 'lab' : 'lecture',
            ];
        }
    }
    $blocks = [];
    foreach (XL_DAYS as $day) {
        $blocks[] = xl_wide_block($day, $by_day[$day] ?? [], $times, $shown);
    }
    return ['title' => $key !== '' ? tt_year_label($key) : 'Master', 'blocks' => $blocks];
}

/** Teaching-time labels only — break rows carry no data worth a column. */
function xl_official_teaching_times(array $schedules): array {
    $breaks = [];
    foreach ($schedules as $s) {
        foreach ($s['sessions'] as $session) {
            if (!empty($session['is_break'])) { $breaks[$session['time'] ?? ''] = true; }
        }
    }
    return array_values(array_filter(tt_time_slots($schedules), static fn($t) => !isset($breaks[$t])));
}

// --------------------------------------------------------------- generated source

function xl_build_generated($conn, string $mode, string $key, int $id): array {
    $days = db_get_rows($conn, "SELECT * FROM working_days WHERE is_working=1 ORDER BY day_order");
    $slots = db_get_rows($conn, "SELECT * FROM time_slots WHERE is_active=1 ORDER BY slot_number");
    $rooms = db_get_rows($conn, "SELECT r.*, b.building_name FROM rooms r JOIN buildings b ON r.building_id = b.building_id ORDER BY r.room_name");

    if ($mode === 'all_rooms') {
        $matrix = tt_room_matrix_generated($conn, $days, $slots, $rooms);
        return ['title' => 'All Rooms', 'blocks' => [xl_matrix_block($matrix)]];
    }

    $day_names = array_column($days, 'day_name');
    $times = [];
    foreach ($slots as $slot) {
        if ($slot['slot_type'] !== 'class') { continue; }
        $times[date('H:i', strtotime($slot['start_time'])) . '-' . date('H:i', strtotime($slot['end_time']))] = true;
    }
    $times = array_keys($times);
    sort($times);

    $sql = "SELECT t.*, d.day_name, ts.start_time, ts.end_time, ts.slot_type,
                   s.subject_name, f.faculty_name, c.class_name, r.room_name
            FROM timetable t
            JOIN working_days d ON d.day_id = t.day_id
            JOIN time_slots ts ON ts.slot_id = t.slot_id
            LEFT JOIN subjects s ON s.subject_id = t.subject_id
            LEFT JOIN faculty f ON f.faculty_id = t.faculty_id
            LEFT JOIN classes c ON c.class_id = t.class_id
            LEFT JOIN rooms r ON r.room_id = t.room_id";
    $rows = db_get_rows($conn, $sql);
    foreach ($rows as &$row) {
        $row['label'] = date('H:i', strtotime($row['start_time'])) . '-' . date('H:i', strtotime($row['end_time']));
    }
    unset($row);

    if ($mode === 'faculty' || $mode === 'all_faculty') {
        $people = db_get_rows($conn, "SELECT faculty_id, faculty_name FROM faculty ORDER BY faculty_name");
        if ($mode === 'faculty' && $id > 0) {
            $people = array_values(array_filter($people, static fn($p) => (int) $p['faculty_id'] === $id));
        }
        $blocks = [];
        foreach ($people as $person) {
            $grid = [];
            foreach ($rows as $row) {
                if ((int) $row['faculty_id'] !== (int) $person['faculty_id']) { continue; }
                $grid[$row['label']][$row['day_name']] = [
                    'text' => (string) $row['class_name'],
                    'sub' => (string) $row['subject_name'],
                    'style' => $row['is_lab'] ? 'lab' : 'lecture',
                ];
            }
            if ($grid) { $blocks[] = xl_day_grid_block($person['faculty_name'], $grid, $times); }
        }
        return ['title' => $mode === 'faculty' ? 'Faculty' : 'All Faculty', 'blocks' => $blocks];
    }

    if ($mode === 'room' || $mode === 'class') {
        $is_room = $mode === 'room';
        $name = '';
        $grid = [];
        foreach ($rows as $row) {
            if ((int) ($is_room ? $row['room_id'] : $row['class_id']) !== $id) { continue; }
            $name = (string) ($is_room ? $row['room_name'] : $row['class_name']);
            $grid[$row['label']][$row['day_name']] = [
                'text' => $is_room ? (string) $row['class_name'] : (string) $row['subject_name'],
                'sub' => $is_room ? (string) $row['subject_name'] : (string) $row['faculty_name'],
                'style' => $row['is_lab'] ? 'lab' : 'lecture',
            ];
        }
        if ($is_room) {
            $sql = "SELECT d.day_name, ts.start_time, ts.end_time, ru.reason
                    FROM room_unavailable ru
                    JOIN working_days d ON d.day_id = ru.day_id
                    JOIN time_slots ts ON ts.slot_id = ru.slot_id
                    WHERE ru.room_id = ?";
            foreach (db_get_rows($conn, $sql, "i", [$id]) as $block) {
                $label = date('H:i', strtotime($block['start_time'])) . '-' . date('H:i', strtotime($block['end_time']));
                if (isset($grid[$label][$block['day_name']])) { continue; }
                $grid[$label][$block['day_name']] = ['text' => (string) $block['reason'], 'sub' => 'BLOCKED', 'style' => 'blocked'];
            }
            if ($name === '') {
                $row = db_get_row($conn, "SELECT room_name FROM rooms WHERE room_id = ?", "i", [$id]);
                $name = (string) ($row['room_name'] ?? 'Room');
            }
        }
        return ['title' => $name ?: ucfirst($mode), 'blocks' => [xl_day_grid_block($name, $grid, $times)]];
    }

    // master / year: one table per day, classes across the top.
    $classes = db_get_rows($conn, "SELECT class_id, class_name, year_of_study FROM classes ORDER BY class_name");
    if ($key !== '') {
        $classes = array_values(array_filter($classes, static fn($c) => tt_year_code($c['class_name']) === $key));
    }
    $names = array_column($classes, 'class_name');
    $by_day = [];
    foreach ($rows as $row) {
        $by_day[$row['day_name']][$row['label']][(string) $row['class_name']] = [
            'text' => (string) $row['subject_name'],
            'sub' => (string) $row['faculty_name'],
            'style' => $row['is_lab'] ? 'lab' : 'lecture',
        ];
    }
    $blocks = [];
    foreach ($day_names as $day) {
        $blocks[] = xl_wide_block($day, $by_day[$day] ?? [], $times, $names);
    }
    return ['title' => $key !== '' ? tt_year_label($key) : 'Master', 'blocks' => $blocks];
}

// -------------------------------------------------------------- block builders

/** Time down the side, weekdays across — the class / faculty / room view. */
function xl_day_grid_block(string $caption, array $grid, array $times): array {
    $rows = [];
    foreach ($times as $time) {
        $cells = [];
        foreach (XL_DAYS as $day) {
            $cells[] = $grid[$time][$day] ?? ['text' => '', 'sub' => '', 'style' => 'free'];
        }
        $rows[] = ['head' => [$time], 'cells' => $cells];
    }
    return ['caption' => $caption, 'head' => ['Time Slot'], 'cols' => XL_DAYS, 'rows' => $rows];
}

/** Time down the side, every class across — one per day, the master view. */
function xl_wide_block(string $day, array $day_grid, array $times, array $class_names): array {
    $rows = [];
    foreach ($times as $time) {
        $cells = [];
        foreach ($class_names as $name) {
            $cells[] = $day_grid[$time][$name] ?? ['text' => '', 'sub' => '', 'style' => 'free'];
        }
        $rows[] = ['head' => [$time], 'cells' => $cells];
    }
    return ['caption' => $day, 'head' => ['Time Slot'], 'cols' => $class_names, 'rows' => $rows];
}

/** Rooms down the side, day > period across — the All Rooms matrix. */
function xl_matrix_block(array $matrix): array {
    $groups = [];
    foreach ($matrix['days'] as $day_name => $periods) {
        $groups[] = ['label' => strtoupper($day_name), 'cols' => array_column($periods, 'label')];
    }
    $rows = [];
    foreach ($matrix['rooms'] as $room) {
        $cells = [];
        foreach ($matrix['days'] as $day_name => $periods) {
            foreach ($periods as $period) {
                $cell = $matrix['cells'][$room['name']][$day_name][$period['key']] ?? null;
                $cells[] = $cell === null
                    ? ['text' => '', 'sub' => '', 'style' => 'free']
                    : ['text' => $cell['text'], 'sub' => $cell['sub'], 'style' => $cell['blocked'] ? 'blocked' : ($cell['lab'] ? 'lab' : 'lecture')];
            }
        }
        $rows[] = ['head' => [$room['name'], $room['type'], (string) $room['capacity']], 'cells' => $cells];
    }
    return ['caption' => '', 'head' => ['Room No.', 'Class Type', 'Capacity'], 'groups' => $groups, 'rows' => $rows];
}

// -------------------------------------------------------------------- writer

function xl_send(array $sheet_data): void {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    // Excel forbids : \ / ? * [ ] in sheet names and caps them at 31 characters.
    $sheet->setTitle(substr(preg_replace('/[\\\\\/:*?\[\]]+/', '-', $sheet_data['title']) ?: 'Timetable', 0, 31));

    $widest = 1;
    foreach ($sheet_data['blocks'] as $block) {
        $widest = max($widest, count($block['head']) + xl_block_width($block));
    }

    $row = xl_write_header($sheet, $sheet_data['title'], $widest);
    $first_table_row = 0;
    foreach ($sheet_data['blocks'] as $block) {
        $row = xl_write_block($sheet, $block, $row, $first_table_row);
        $row += 2; // a blank row between tables, so they do not read as one grid
    }

    // Wide enough that "Dr. Yogesh Mali" fits on one line, so most cells stay two
    // lines tall instead of three.
    $sheet->getColumnDimension('A')->setWidth(22);
    for ($c = 2; $c <= $widest; $c++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(17);
    }
    // Freeze the row-header column and everything above the first table's data.
    if ($first_table_row > 0) {
        $sheet->freezePane(Coordinate::stringFromColumnIndex(2) . $first_table_row);
    }

    $setup = $sheet->getPageSetup();
    $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $setup->setPaperSize(PageSetup::PAPERSIZE_A4);
    $setup->setFitToWidth(1);
    $setup->setFitToHeight(0);
    $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);

    $name = preg_replace('/[^A-Za-z0-9 _-]+/', '-', $sheet_data['title']) . ' Timetable - ' . date('M Y') . '.xlsx';

    // Any stray output (a notice, a BOM) corrupts the archive, so discard it first.
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}

function xl_block_width(array $block): int {
    if (isset($block['groups'])) {
        $n = 0;
        foreach ($block['groups'] as $group) { $n += count($group['cols']); }
        return $n;
    }
    return count($block['cols'] ?? []);
}

/** The ADYPU doc-control header block the department's own sheets carry. */
function xl_write_header($sheet, string $title, int $widest): int {
    $last = Coordinate::stringFromColumnIndex(max(4, $widest - 4));
    $sheet->setCellValue('A1', 'Ajeenkya DY Patil University — School of Engineering');
    $sheet->mergeCells('A1:' . $last . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->setCellValue('A2', 'Time Table and Class Allotment — ' . $title);
    $sheet->mergeCells('A2:' . $last . '2');
    $sheet->getStyle('A2')->getFont()->setSize(11);
    $sheet->setCellValue('A3', 'Doc No: ADYPU/SOE/F-017    Issue Date: 1st Sept 2021    Revision No: 0    Exported: ' . date('d M Y, g:i a'));
    $sheet->mergeCells('A3:' . $last . '3');
    $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true);
    $sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    return 5;
}

/** Write one table; reports the first data row back so the writer can freeze panes. */
function xl_write_block($sheet, array $block, int $row, int &$first_table_row): int {
    $head_cols = count($block['head']);
    $width = xl_block_width($block);
    if ($width === 0) { return $row; }

    if (($block['caption'] ?? '') !== '') {
        $sheet->setCellValue([1, $row], $block['caption']);
        $sheet->mergeCells([1, $row, min($head_cols + $width, 40), $row]);
        $sheet->getStyle([1, $row])->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle([1, $row])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3E5F5');
        $row++;
    }

    $header_rows = isset($block['groups']) ? 2 : 1;
    $col = 1;
    foreach ($block['head'] as $label) {
        $sheet->setCellValue([$col, $row], $label);
        if ($header_rows === 2) { $sheet->mergeCells([$col, $row, $col, $row + 1]); }
        $col++;
    }
    if (isset($block['groups'])) {
        foreach ($block['groups'] as $group) {
            $span = count($group['cols']);
            if ($span === 0) { continue; }
            $sheet->setCellValue([$col, $row], $group['label']);
            if ($span > 1) { $sheet->mergeCells([$col, $row, $col + $span - 1, $row]); }
            foreach ($group['cols'] as $i => $label) {
                $sheet->setCellValue([$col + $i, $row + 1], $label);
            }
            $col += $span;
        }
    } else {
        foreach ($block['cols'] as $label) {
            $sheet->setCellValue([$col, $row], $label);
            $col++;
        }
    }

    $header_range = [1, $row, $head_cols + $width, $row + $header_rows - 1];
    $sheet->getStyle($header_range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($header_range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('6B1B5E');
    $sheet->getStyle($header_range)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

    $row += $header_rows;
    if ($first_table_row === 0) { $first_table_row = $row; }

    foreach ($block['rows'] as $data_row) {
        $col = 1;
        foreach ($data_row['head'] as $i => $label) {
            // Written as explicit text: room numbers like "101" would otherwise be
            // typed as numbers and right-align, while "001" and "104-105" stay text
            // and align left, leaving the column visibly ragged.
            $sheet->setCellValueExplicit([$col, $row], (string) $label, DataType::TYPE_STRING);
            $sheet->getStyle([$col, $row])->getFont()->setBold(true);
            $sheet->getStyle([$col, $row])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
            $sheet->getStyle([$col, $row])->getAlignment()
                ->setHorizontal($i === 0 ? Alignment::HORIZONTAL_LEFT : Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $col++;
        }
        foreach ($data_row['cells'] as $cell) {
            $text = trim($cell['text'] . ($cell['sub'] !== '' ? "\n" . $cell['sub'] : ''));
            // Labels, never values — keep Excel from typing anything as a number or date.
            $sheet->setCellValueExplicit([$col, $row], $text, DataType::TYPE_STRING);
            $fill = XL_STYLES[$cell['style']] ?? null;
            if ($fill !== null) {
                $sheet->getStyle([$col, $row])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
            }
            $col++;
        }
        // -1 leaves customHeight off so Excel auto-fits the row to its wrapped
        // content. A fixed height clipped the second line ("CV" over "Ms. Mitali
        // Dey") and any faculty name long enough to wrap onto a third.
        $sheet->getRowDimension($row)->setRowHeight(-1);
        $row++;
    }

    $sheet->getStyle([1, $row - count($block['rows']) - $header_rows, $head_cols + $width, $row - 1])
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle([$head_cols + 1, $row - count($block['rows']), $head_cols + $width, $row - 1])
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

    return $row;
}
