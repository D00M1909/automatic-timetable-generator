<?php
/**
 * One-off FY data seeder — transcribed from FY/fy info.png (hour patterns) and
 * FY/WhatsApp Image ... .jpeg (division-wise faculty allocation). Faculty names
 * are a best-effort read of a handwritten photo — spot-check before treating
 * as final. All rows tagged source='manual' so seed_from_excel.php never
 * touches them. Idempotent: wipes only FY (year_of_study=1) rows first.
 *
 * Run: C:\xampp\php\php.exe seed_fy_data.php
 */
require_once __DIR__ . '/includes/config.php';

$conn->begin_transaction();
try {
    // ---- Wipe existing FY data (idempotent re-run) ----
    $conn->query("DELETE FROM faculty_unavailable WHERE faculty_id IN (SELECT faculty_id FROM faculty WHERE source='manual')");
    $conn->query("DELETE FROM subject_assignments WHERE class_id IN (SELECT class_id FROM classes WHERE year_of_study=1)");
    $conn->query("DELETE FROM timetable WHERE class_id IN (SELECT class_id FROM classes WHERE year_of_study=1)");
    $conn->query("DELETE FROM classes WHERE year_of_study=1");
    $conn->query("DELETE FROM subjects WHERE source='manual'");
    $conn->query("DELETE FROM faculty WHERE source='manual'");
    $conn->query("DELETE FROM rooms WHERE source='manual'");
    $conn->query("DELETE FROM time_slots WHERE year_of_study=1");

    // ---- Time grid: 08:30-17:30, 5 days, lunch 12:30-13:30 ----
    $slot_defs = [
        [1, '08:30:00', '09:30:00', 'class'],
        [2, '09:30:00', '10:30:00', 'class'],
        [3, '10:30:00', '11:30:00', 'class'],
        [4, '11:30:00', '12:30:00', 'class'],
        [5, '12:30:00', '13:30:00', 'lunch'],
        [6, '13:30:00', '14:30:00', 'class'],
        [7, '14:30:00', '15:30:00', 'class'],
        [8, '15:30:00', '16:30:00', 'class'],
        [9, '16:30:00', '17:30:00', 'class'],
    ];
    foreach ($slot_defs as [$num, $start, $end, $type]) {
        db_insert($conn, "INSERT INTO time_slots (slot_number, start_time, end_time, slot_type, is_active, year_of_study, source) VALUES (?, ?, ?, ?, 1, 1, 'manual')", "isss", [$num, $start, $end, $type]);
    }
    // First class slot after lunch = slot_number 6 -> Core divisions' floor.
    $core_min_start = 6;

    // ---- Rooms (from FY Excel sheet room list) ----
    $year_row = db_get_row($conn, "SELECT year_id FROM years WHERE year_status='active' ORDER BY year_id LIMIT 1");
    $year_id = $year_row['year_id'] ?? db_insert($conn, "INSERT INTO years (year_name, year_status) VALUES ('2026-27', 'active')", "", []);
    $building_row = db_get_row($conn, "SELECT building_id FROM buildings ORDER BY building_id LIMIT 1");
    $building_id = $building_row['building_id'];

    $room_defs = [
        ['ULC-6 007', 'classroom', 60], ['ULC-6 008', 'classroom', 60], ['ULC-6 009', 'classroom', 60], ['ULC-6 010', 'classroom', 60],
        ['ULC-6 001', 'classroom', 60], ['ULC-6 002', 'classroom', 60], ['ULC-6 003', 'classroom', 60],
        ['ULC-6 101', 'classroom', 60], ['ULC-6 102', 'classroom', 60], ['ULC-6 103', 'classroom', 60],
        ['ULC 1 608', 'classroom', 60], ['ULC 1 407', 'classroom', 60], ['ULC 1 505', 'classroom', 60],
        ['ULC 1 504', 'seminar', 30],       // Workshop/Drawing hall
        ['ULC 1 306', 'lab', 30],           // Mechanics lab
        ['ULC 1 204', 'lab', 60],           // BEEE lab
        ['ULC 1 304', 'lab', 30],           // Physics lab
        ['ULC 6 004', 'lab', 30],           // Computer Lab
        ['ULC 6 005', 'lab', 30],           // Computer Lab
        ['ULC 1 302', 'lab', 30],           // Computer Lab
    ];
    $room_ids = [];
    foreach ($room_defs as [$name, $type, $cap]) {
        $room_ids[$name] = db_insert($conn, "INSERT INTO rooms (building_id, room_name, room_type, capacity, has_projector, has_ac, floor_number, source) VALUES (?, ?, ?, ?, 1, 1, 1, 'manual')", "issi", [$building_id, $name, $type, $cap]);
    }

    // ---- Subjects (hour patterns from fy info.png) ----
    // [name, code, type, lecture_hours, lecture_pattern, lab_hours, lab_pattern]
    $subject_defs = [
        ['Applied Mathematics I', 'FY-MATH1', 'lecture', 4, '2,1,1', 0, null],
        ['Fundamental of Engineering Mechanics', 'FY-MECH', 'lecture', 3, '2,1', 0, null],
        ['Fundamental of Engineering Mechanics Laboratory', 'FY-MECHLAB', 'lab', 0, null, 2, '2'],
        ['Computational Mechanics for Emerging Technologies', 'FY-CMET', 'lecture', 3, '2,1', 0, null],
        ['Computational Mechanics for Emerging Technologies Lab', 'FY-CMETLAB', 'lab', 0, null, 2, '2'],
        ['Fundamental of Engineering Physics', 'FY-PHYS', 'lecture', 3, '2,1', 0, null],
        ['Fundamental of Engineering Physics Laboratory', 'FY-PHYSLAB', 'lab', 0, null, 2, '2'],
        ['Fundamental of Electrical and Electronics', 'FY-EE', 'lecture', 3, '2,1', 0, null],
        ['Fundamental of Electrical and Electronics Lab', 'FY-EELAB', 'lab', 0, null, 2, '2'],
        ['Fundamental of Programming I', 'FY-PROG1', 'lecture', 2, '2', 0, null],
        ['Computer Programming Lab I', 'FY-PROGLAB', 'lab', 0, null, 3, '3'],
        ['Communication Skills', 'FY-COMM', 'lecture', 2, '1,1', 0, null],
        ['Engineering Design Thinking and Prototyping', 'FY-EDTP', 'lecture', 2, '2', 0, null],
        ['Student Enrichment Activities I', 'FY-SEA1', 'lecture', 2, '2', 0, null],
        ['Bharatiya Knowledge System I', 'FY-BKS1', 'lecture', 2, '2', 0, null],
    ];
    $subject_ids = [];
    foreach ($subject_defs as [$name, $code, $type, $lec, $lecp, $lab, $labp]) {
        $subject_ids[$code] = db_insert(
            $conn,
            "INSERT INTO subjects (subject_name, subject_code, subject_type, lecture_hours_per_week, lecture_block_pattern, lab_hours_per_week, lab_block_pattern, is_minor, source) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'manual')",
            "sssisis",
            [$name, $code, $type, $lec, $lecp, $lab, $labp]
        );
    }

    // ---- Faculty: named + placeholder codes seen in the allocation photo ----
    // Named-with-constraint faculty (from Dr. Gilotra's email):
    //   Sachin Rajas / Saroj Nanda / AmarNath Dutta: unavailable before 10:30 (slot_number < 3)
    //   Neelima / Manavi / Niteen / Ashish: 8:30-4:30 (within grid bounds, no rows needed)
    //   Vivek: 9:30-5:30 (unavailable slot_number 1)
    $faculty_names = [
        'Shital Solanki', 'Sachin Rajas', 'Dr Manavi Gilotra', 'Niteen Bhirud', 'Ashish Singh', 'Vivek Gupta',
        'Nikhil Mane', 'Mohit Surwade', 'Abhijeet Alwale', 'Mr Virendra', 'Sharayu Give', 'Arabaz Shaikh',
        'Saroj Nanda', 'Neelima Madam', 'Amarnath Dutta', 'Dr Debanjali Roy', 'Dr Sushant Das', 'Mr George',
        // Placeholder codes from the photo (not-yet-assigned real names) - kept as their own faculty
        // rows so subject_assignments can still reference them; swap for real names once confirmed.
        'MF1', 'MF2', 'CNF1', 'CNF2', 'CNF3', 'MechF1', 'Industry People',
    ];
    $faculty_ids = [];
    $used_codes = [];
    foreach ($faculty_names as $name) {
        $code_base = strtoupper(preg_replace('/[^A-Z]/i', '', substr(str_replace(' ', '', $name), 0, 6)));
        if ($code_base === '') $code_base = 'FAC';
        $code = $code_base;
        $n = 2;
        while (isset($used_codes[$code])) { $code = $code_base . $n++; }
        $used_codes[$code] = true;
        $faculty_ids[$name] = db_insert($conn, "INSERT INTO faculty (faculty_name, faculty_code, department, max_hours_per_day, max_hours_per_week, source) VALUES (?, ?, 'FY Engineering', 6, 30, 'manual')", "ss", [$name, $code]);
    }

    // Faculty availability constraints
    $day_ids = array_column(db_get_rows($conn, "SELECT day_id FROM working_days WHERE is_working=1"), 'day_id');
    $slot_rows = db_get_rows($conn, "SELECT slot_id, slot_number FROM time_slots WHERE year_of_study=1 AND slot_type='class'");
    $slot_id_by_number = array_column($slot_rows, 'slot_id', 'slot_number');

    $after_1030_only = ['Sachin Rajas', 'Saroj Nanda', 'Amarnath Dutta']; // slot_number 1,2 blocked
    $after_930_only = ['Vivek Gupta']; // slot_number 1 blocked
    foreach ($day_ids as $day_id) {
        foreach ($after_1030_only as $name) {
            foreach ([1, 2] as $sn) {
                db_execute($conn, "INSERT INTO faculty_unavailable (faculty_id, day_id, slot_id, reason) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason=reason", "iiis", [$faculty_ids[$name], $day_id, $slot_id_by_number[$sn], 'Before 10:30 - per faculty email']);
            }
        }
        foreach ($after_930_only as $name) {
            db_execute($conn, "INSERT INTO faculty_unavailable (faculty_id, day_id, slot_id, reason) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason=reason", "iiis", [$faculty_ids[$name], $day_id, $slot_id_by_number[1], 'Before 9:30 - per faculty email']);
        }
    }

    // ---- Classes: 16 divisions, Core (A-F) window >= post-lunch, CS (rest) unrestricted ----
    $core_divisions = ['A', 'B', 'C', 'D', 'E', 'F'];
    $cs_divisions = ['M', 'N', 'O', 'P', 'S', 'T', 'U', 'V', 'W', 'X'];
    $class_ids = [];
    foreach ($core_divisions as $d) {
        $class_ids[$d] = db_insert($conn, "INSERT INTO classes (year_id, year_of_study, class_name, class_code, strength, source, min_start_slot_number) VALUES (?, 1, ?, ?, 60, 'manual', ?)", "issi", [$year_id, "FY $d", "FY-$d", $core_min_start]);
    }
    foreach ($cs_divisions as $d) {
        $class_ids[$d] = db_insert($conn, "INSERT INTO classes (year_id, year_of_study, class_name, class_code, strength, source) VALUES (?, 1, ?, ?, 60, 'manual')", "iss", [$year_id, "FY $d", "FY-$d"]);
    }

    // ---- Subject assignments: division -> subject code -> faculty name, per the photo ----
    // Column order in the photo: A B C D E F M N O P S T U V W X
    $div_order = array_merge($core_divisions, $cs_divisions);

    function zip_assign($div_order, $faculty_row) {
        // $faculty_row has one entry per division in $div_order order; null = not offered to that division.
        return array_combine($div_order, $faculty_row);
    }

    $assignments_by_subject = [
        'FY-MATH1' => zip_assign($div_order, ['Shital Solanki','Sachin Rajas','Dr Manavi Gilotra','Shital Solanki','Shital Solanki','MF1','Dr Manavi Gilotra','Dr Manavi Gilotra','Dr Manavi Gilotra','Sachin Rajas','MF1','MF1','MF2','MF2','MF2','MF1']),
        'FY-MECH' => zip_assign($div_order, ['Niteen Bhirud','Ashish Singh','Vivek Gupta','Nikhil Mane','Mohit Surwade','Ashish Singh', null,null,null,null,null,null,null,null,null,null]),
        'FY-MECHLAB' => zip_assign($div_order, ['Niteen Bhirud','Ashish Singh','Vivek Gupta','Nikhil Mane','Mohit Surwade','Ashish Singh', null,null,null,null,null,null,null,null,null,null]),
        'FY-CMET' => zip_assign($div_order, [null,null,null,null,null,null,'CNF1','CNF1','CNF1','CNF1','CNF1','Industry People','Industry People','Industry People','Industry People','Industry People']),
        'FY-CMETLAB' => zip_assign($div_order, [null,null,null,null,null,null,'CNF1','CNF1','CNF1','CNF1','CNF1','Industry People','Industry People','Industry People','Industry People','Industry People']),
        'FY-PHYS' => zip_assign($div_order, [null,null,null,null,null,null,'CNF2','CNF2','Industry People','Industry People','Industry People','Dr Debanjali Roy','Dr Sushant Das','Dr Debanjali Roy','Mr George','Dr Debanjali Roy']),
        'FY-PHYSLAB' => zip_assign($div_order, [null,null,null,null,null,null,'CNF2','CNF2','Industry People','Industry People','Industry People','Dr Debanjali Roy','Dr Sushant Das','Dr Debanjali Roy','Mr George','Dr Debanjali Roy']),
        'FY-EE' => zip_assign($div_order, ['Abhijeet Alwale','Abhijeet Alwale','Mr Virendra','Sharayu Give','Mr Virendra','Mr Virendra','Arabaz Shaikh','Abhijeet Alwale', null,null,null,null,null,null,null,null]),
        'FY-EELAB' => zip_assign($div_order, ['Abhijeet Alwale','Abhijeet Alwale','Mr Virendra','Sharayu Give','Mr Virendra','Mr Virendra','Arabaz Shaikh','Abhijeet Alwale', null,null,null,null,null,null,null,null]),
        'FY-PROG1' => zip_assign($div_order, ['CNF2','CNF2','CNF2','CNF2','CNF2','Industry People','CNF3','CNF3','Saroj Nanda','Saroj Nanda','Saroj Nanda','Saroj Nanda','CNF1','CNF1','CNF3','CNF1']),
        'FY-PROGLAB' => zip_assign($div_order, ['CNF2','CNF2','CNF2','CNF2','CNF2','Industry People','CNF3','CNF3','Saroj Nanda','Saroj Nanda','Saroj Nanda','Saroj Nanda','CNF1','CNF1','CNF3','CNF1']),
        'FY-COMM' => zip_assign($div_order, ['Industry People','Industry People','Industry People','Industry People','Industry People','Industry People','Neelima Madam','Amarnath Dutta','Amarnath Dutta','Neelima Madam','Neelima Madam','Industry People','Industry People','Amarnath Dutta','Neelima Madam','Amarnath Dutta']),
        'FY-EDTP' => zip_assign($div_order, ['Vivek Gupta','Nikhil Mane','Vivek Gupta','Nikhil Mane','Vivek Gupta','Mohit Surwade','Ashish Singh','Ashish Singh','Ashish Singh','Amarnath Dutta','Amarnath Dutta','Amarnath Dutta','Amarnath Dutta','Amarnath Dutta','Amarnath Dutta','Amarnath Dutta']),
        'FY-SEA1' => zip_assign($div_order, ['Mohit Surwade','Nikhil Mane','Vivek Gupta','CNF1','CNF3','Abhijeet Alwale','Saroj Nanda','Neelima Madam','Ashish Singh','MechF1','Mr George','Mohit Surwade','Aish Singh','MechF1','MechF1','Aish Singh']),
        'FY-BKS1' => zip_assign($div_order, ['Niteen Bhirud','Mohit Surwade','Vivek Gupta','Nikhil Mane','Shital Solanki','Mr Virendra','CNF1','CNF3','Abhijeet Alwale','Saroj Nanda','Dr Manavi Gilotra','Abhijeet Alwale','MF2','Mr George','Mohit Surwade','Neelima Madam']),
    ];

    // 'Aish Singh' in the photo is very likely 'Ashish Singh' abbreviated - normalize.
    $name_aliases = ['Aish Singh' => 'Ashish Singh'];

    $assignment_count = 0;
    $skipped = 0;
    foreach ($assignments_by_subject as $code => $per_division) {
        foreach ($per_division as $div => $fac_name) {
            if ($fac_name === null) { $skipped++; continue; }
            $fac_name = $name_aliases[$fac_name] ?? $fac_name;
            if (!isset($faculty_ids[$fac_name]) || !isset($class_ids[$div]) || !isset($subject_ids[$code])) { $skipped++; continue; }
            db_insert($conn, "INSERT INTO subject_assignments (class_id, subject_id, faculty_id) VALUES (?, ?, ?)", "iii", [$class_ids[$div], $subject_ids[$code], $faculty_ids[$fac_name]]);
            $assignment_count++;
        }
    }

    $conn->commit();
    printf("Seeded FY: %d rooms, %d subjects, %d faculty, %d classes (%d Core, %d CS), %d subject_assignments (%d not offered).\n",
        count($room_ids), count($subject_ids), count($faculty_ids), count($class_ids), count($core_divisions), count($cs_divisions), $assignment_count, $skipped);
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, "FY seed failed, rolled back: " . $e->getMessage() . "\n");
    exit(1);
}
