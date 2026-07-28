<?php
/**
 * Clones the imported Excel timetable (data/imported_timetables.json, via tt_load())
 * into the generator's master data: buildings, rooms, classes, faculty, subjects,
 * subject_assignments, time_slots. Re-run whenever the workbook changes.
 *
 * Idempotent: wipes the tables it owns, then reinserts from the JSON. Faculty
 * preferences/unavailability tied to the wiped time_slots are cleared too — they were
 * set against the old placeholder slots and don't carry over.
 *
 * Run: C:\xampp\php\php.exe seed_from_excel.php
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/timetable_data.php';

$schedules = tt_load();
if (!$schedules) {
    fwrite(STDERR, "No schedules loaded — is data/imported_timetables.json present? Run import_excel_timetables.ps1 first.\n");
    exit(1);
}

// Rooms: fixed room list confirmed by the timetable head. Room type per Excel usage —
// any room ever used for a lab is lab-capable and also takes lectures (dual-use).
const ROOM_TYPES = [
    '001' => 'classroom', '002' => 'lab', '003' => 'classroom',
    '101' => 'lab', '102' => 'lab', '103' => 'lab',
    '104-105' => 'lab', '106' => 'lab', '107' => 'lab', '108' => 'classroom',
    '109' => 'classroom', '110' => 'classroom',
    'Online' => 'lab',
];

const TIME_SLOTS = [
    [1, '09:30:00', '10:30:00', 'class'],
    [2, '10:30:00', '11:30:00', 'class'],
    [3, '11:30:00', '12:30:00', 'class'],
    [4, '12:30:00', '13:15:00', 'lunch'],
    [5, '13:15:00', '14:15:00', 'class'],
    [6, '14:15:00', '15:15:00', 'class'],
    [7, '15:15:00', '15:30:00', 'break'],
    [8, '15:30:00', '16:30:00', 'class'],
    [9, '16:30:00', '17:30:00', 'class'],
];

function class_year_of_study(string $class_name): int {
    return match (strtoupper(substr($class_name, 0, 2))) {
        'SY' => 2, 'TY' => 3, 'BE' => 4, default => 2,
    };
}

function class_code(string $class_name): string {
    $code = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', $class_name));
    return trim(preg_replace('/-+/', '-', $code), '-');
}

/** Short, unique-ish faculty code from a canonical "Dr. First Last" name. */
function faculty_code(string $name, array $used): string {
    $rest = preg_replace('/^(Dr|Mr|Mrs|Ms|Prof)\.?\s*/i', '', $name);
    $words = preg_split('/\s+/', trim($rest), -1, PREG_SPLIT_NO_EMPTY);
    $code = strtoupper(implode('', array_map(fn($w) => $w[0], $words)));
    if ($code === '') { $code = strtoupper(substr($name, 0, 2)); }
    $base = $code;
    $n = 2;
    while (isset($used[$code])) { $code = $base . $n++; }
    return $code;
}

$conn->begin_transaction();
try {
    // Wipe owned master tables. Deleting classes/subjects/faculty cascades away
    // subject_assignments and timetable rows that reference them (see timetable_db.sql
    // FK definitions); deleting time_slots cascades stale faculty_preferences/
    // faculty_unavailable/time_preferences/room_unavailable set against old slots.
    foreach (['classes', 'subjects', 'faculty', 'time_slots', 'rooms', 'buildings'] as $t) {
        $conn->query("DELETE FROM `$t`");
    }

    // ---- Building + rooms ----
    $building_id = db_insert($conn, "INSERT INTO buildings (building_name, has_ac, energy_rating) VALUES (?, 1, 'A')", "s", ['ULC']);
    foreach (ROOM_TYPES as $room_name => $room_type) {
        $capacity = $room_name === 'Online' ? 9999 : 60;
        db_insert($conn, "INSERT INTO rooms (building_id, room_name, room_type, capacity, has_projector, has_ac, floor_number) VALUES (?, ?, ?, ?, 1, 1, 1)",
            "issi", [$building_id, $room_name, $room_type, $capacity]);
    }

    // ---- Time slots ----
    foreach (TIME_SLOTS as [$num, $start, $end, $type]) {
        db_insert($conn, "INSERT INTO time_slots (slot_number, start_time, end_time, slot_type, is_active) VALUES (?, ?, ?, ?, 1)",
            "isss", [$num, $start, $end, $type]);
    }

    // ---- Year (reuse the active one; create if missing) ----
    $year = db_get_row($conn, "SELECT year_id FROM years WHERE year_status='active' ORDER BY year_id LIMIT 1");
    $year_id = $year['year_id'] ?? db_insert($conn, "INSERT INTO years (year_name, year_status) VALUES ('2026-27', 'active')", "", []);

    // ---- Classes ----
    $class_ids = []; // class_name => class_id
    foreach ($schedules as $s) {
        $name = $s['class_name'];
        $class_ids[$name] = db_insert(
            $conn,
            "INSERT INTO classes (year_id, year_of_study, class_name, class_code, strength) VALUES (?, ?, ?, ?, 60)",
            "iiss",
            [$year_id, class_year_of_study($name), $name, class_code($name)]
        );
    }

    // ---- Subjects: group every non-break session by subject_code across all classes ----
    // Hours per subject are approximated as the max weekly count seen for that code in
    // any single class — the sheets don't give every class identical hours for a code
    // that recurs across years, so this is a deliberate upper-bound simplification.
    $per_class_code = []; // class_name => code => ['lecture'=>n,'lab'=>n,'faculty'=>[name=>count]]
    $code_names = []; // code => [display_name => count]
    $code_is_minor = []; // code => bool
    foreach ($schedules as $s) {
        foreach ($s['sessions'] as $sess) {
            if (!empty($sess['is_break']) || ($sess['subject_code'] ?? '') === '') { continue; }
            $code = $sess['subject_code'];
            $kind = $sess['is_lab'] ? 'lab' : 'lecture';
            // duration_slots comes from the Excel merged-cell height (1 or 2 hours);
            // a 2-hour lab is one occurrence but counts as 2 hours/week, matching how
            // generate.php places labs as a 2-consecutive-slot block.
            $duration = max(1, (int) ($sess['duration_slots'] ?? 1));
            $per_class_code[$s['class_name']][$code][$kind] = ($per_class_code[$s['class_name']][$code][$kind] ?? 0) + $duration;
            if (($sess['faculty'] ?? '') !== '') {
                $per_class_code[$s['class_name']][$code]['faculty'][$sess['faculty']] =
                    ($per_class_code[$s['class_name']][$code]['faculty'][$sess['faculty']] ?? 0) + 1;
            }
            $code_names[$code][$sess['subject']] = ($code_names[$code][$sess['subject']] ?? 0) + 1;
            $code_is_minor[$code] = $code_is_minor[$code] ?? (bool) preg_match('/minor/i', $sess['subject']);
        }
    }

    $subject_ids = []; // code => subject_id
    foreach ($code_names as $code => $names) {
        arsort($names);
        $display_name = array_key_first($names);
        $lecture_hours = 0; $lab_hours = 0;
        foreach ($per_class_code as $codes) {
            if (!isset($codes[$code])) { continue; }
            $lecture_hours = max($lecture_hours, $codes[$code]['lecture'] ?? 0);
            $lab_hours = max($lab_hours, $codes[$code]['lab'] ?? 0);
        }
        $type = $lecture_hours > 0 && $lab_hours > 0 ? 'both' : ($lab_hours > 0 ? 'lab' : 'lecture');
        $subject_ids[$code] = db_insert(
            $conn,
            "INSERT INTO subjects (subject_name, subject_code, subject_type, lecture_hours_per_week, lab_hours_per_week, is_minor) VALUES (?, ?, ?, ?, ?, ?)",
            "sssiii",
            [$display_name, $code, $type, $lecture_hours, $lab_hours, (int) $code_is_minor[$code]]
        );
    }

    // ---- Faculty: every distinct canonical name in each sheet's course_faculty list ----
    // (not just names that won a per-cell match) — covers co-listed lab-rotation staff too.
    $faculty_names = [];
    foreach ($schedules as $s) {
        $courses = tt_prepare_courses($s['course_faculty'] ?? []);
        foreach ($courses as $c) {
            foreach (explode('/', $c['faculty']) as $p) {
                $p = trim($p);
                if ($p !== '') { $faculty_names[$p] = true; }
            }
        }
    }
    $faculty_names = array_keys($faculty_names);
    natcasesort($faculty_names);

    $faculty_ids = []; // name => faculty_id
    $used_codes = [];
    foreach ($faculty_names as $name) {
        $code = faculty_code($name, $used_codes);
        $used_codes[$code] = true;
        $faculty_ids[$name] = db_insert(
            $conn,
            "INSERT INTO faculty (faculty_name, faculty_code, department) VALUES (?, ?, 'Computer Science')",
            "ss",
            [$name, $code]
        );
    }

    // ---- Subject assignments: one (class, subject) row, primary faculty = the name
    // that actually taught the most cells of that subject for that class. Subjects with
    // no faculty resolved anywhere for that class (Mini Project/Seminar/IAR — genuinely
    // blank in the workbook) get no assignment row, matching the source. ----
    $assignment_count = 0;
    $skipped_no_faculty = 0;
    foreach ($per_class_code as $class_name => $codes) {
        $class_id = $class_ids[$class_name];
        foreach ($codes as $code => $info) {
            $subject_id = $subject_ids[$code] ?? null;
            if (!$subject_id) { continue; }
            $fac_counts = $info['faculty'] ?? [];
            if (!$fac_counts) { $skipped_no_faculty++; continue; }
            arsort($fac_counts);
            $primary_faculty = array_key_first($fac_counts);
            $faculty_id = $faculty_ids[$primary_faculty] ?? null;
            if (!$faculty_id) { $skipped_no_faculty++; continue; }
            db_insert(
                $conn,
                "INSERT INTO subject_assignments (class_id, subject_id, faculty_id) VALUES (?, ?, ?)",
                "iii",
                [$class_id, $subject_id, $faculty_id]
            );
            $assignment_count++;
        }
    }

    $conn->commit();

    printf(
        "Seeded: 1 building, %d rooms, %d classes, %d subjects, %d faculty, %d subject_assignments, %d time_slots.\n",
        count(ROOM_TYPES), count($class_ids), count($subject_ids), count($faculty_ids), $assignment_count, count(TIME_SLOTS)
    );
    printf("Skipped %d class/subject pairs with no resolvable faculty in the workbook (kept unassigned, matching the source).\n", $skipped_no_faculty);
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, "Seed failed, rolled back: " . $e->getMessage() . "\n");
    exit(1);
}
