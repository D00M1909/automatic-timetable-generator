<?php
/**
 * Loads the imported official timetable (data/imported_timetables.json) and resolves
 * each cell ("DBMS- MA-003") into subject / faculty / room using the per-sheet
 * course-faculty table that ships inside the same JSON.
 */

const TT_STOPWORDS = ['of', 'the', 'and', 'with', 'to', 'from', 'another', 'program', 'using', 'in', 'for', 'a', 'an'];

// Sheets spell the same person's name two ways somewhere in the workbook; map the minority
// spelling to the majority one so both views (and the DB clone) show one name per person.
const TT_FACULTY_ALIASES = [
    'kavita kushwah' => 'Kavita Kaushwah',
    'sudheer kadam' => 'Sudhir Kadam',
    'm a ansari' => 'M A Ansari',
    'ma ansari' => 'M A Ansari',
];

// Sheets sometimes drop the title entirely for a name that has one everywhere else.
const TT_FACULTY_BARE_ALIASES = [
    'priyanka patil' => 'Ms. Priyanka Patil',
];

/** "Dr.Arati Kothari" / "Dr Arati Kothari" / "Dr. Arati Kothari" -> one spelling. */
function tt_canonical_faculty(string $name): string {
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') { return $name; }
    if (!preg_match('/^(Dr|Mr|Mrs|Ms|Prof)\.?\s*(.*)$/i', $name, $m)) {
        return TT_FACULTY_BARE_ALIASES[strtolower($name)] ?? $name;
    }
    $title = ucfirst(strtolower($m[1]));
    $rest = trim($m[2]);
    $rest = TT_FACULTY_ALIASES[strtolower($rest)] ?? $rest;
    return $rest === '' ? $name : "$title. $rest";
}

function tt_load(?string $path = null): array {
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $path = $path ?: __DIR__ . '/../data/imported_timetables.json';
    $raw = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    $schedules = is_array($raw['schedules'] ?? null) ? $raw['schedules'] : [];
    foreach ($schedules as &$schedule) {
        $courses = tt_prepare_courses($schedule['course_faculty'] ?? []);
        foreach ($schedule['sessions'] as &$session) {
            $session += tt_resolve($session['entry'] ?? '', $courses, !empty($session['is_break']));
        }
        unset($session);
    }
    unset($schedule);
    usort($schedules, static fn($a, $b) => strnatcasecmp($a['class_name'] ?? '', $b['class_name'] ?? ''));
    return $cache = $schedules;
}

/** Precompute acronyms for each course row of one class sheet. */
function tt_prepare_courses(array $course_faculty): array {
    $out = [];
    foreach ($course_faculty as $item) {
        $course = trim(preg_replace('/\s+/', ' ', (string) ($item['course'] ?? '')));
        if ($course === '') { continue; }
        // Same course name repeated inside one cell ("X\n\nX") - keep it once.
        $half = (int) ((strlen($course) - 1) / 2);
        if ($half > 3 && substr($course, 0, $half) === substr($course, -$half)) {
            $course = trim(substr($course, 0, $half));
        }
        $bracket = preg_match('/\(([A-Za-z]{2,6})\)/', $course, $m) ? strtoupper($m[1]) : '';
        $plain = preg_replace('/\([^)]*\)/', ' ', $course);
        $words = preg_split('/[^A-Za-z0-9]+/', $plain, -1, PREG_SPLIT_NO_EMPTY);
        $significant = array_values(array_filter($words, static fn($w) => !in_array(strtolower($w), TT_STOPWORDS, true)));
        $faculty_raw = trim(preg_replace('/\s+/', ' ', (string) ($item['faculty'] ?? '')));
        $faculty = implode('/', array_map(static fn($p) => tt_canonical_faculty(trim($p)), explode('/', $faculty_raw)));
        $out[] = [
            'course' => $course,
            'faculty' => $faculty,
            'bracket' => $bracket,
            'acronym' => tt_acronym($significant),
            'acronym_all' => tt_acronym($words),
            'upper' => strtoupper($course),
            'is_lab' => (bool) preg_match('/\b(lab|laboratory)\b/i', $course),
        ];
    }
    return $out;
}

function tt_acronym(array $words): string {
    return strtoupper(implode('', array_map(static fn($w) => $w[0], $words)));
}

/**
 * Split a timetable cell into its parts and match it against the class course list.
 * Returns keys: subject, subject_code, faculty, room, is_lab.
 */
function tt_resolve(string $entry, array $courses, bool $is_break = false): array {
    $text = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $entry)));
    $blank = ['subject' => $text, 'subject_code' => '', 'faculty' => '', 'room' => '', 'is_lab' => false];
    if ($is_break || $text === '') { return $blank; }

    $is_lab = (bool) preg_match('/\b(lab|laboratory)\b/i', $text);

    // Room is the trailing token: a number, a number range, or "Online".
    $room = '';
    if (preg_match('/(?:^|[-\s])(Online|\d{2,3}(?:\s*(?:-|&|and)\s*\d{2,3})?)\s*(?:lab)?\s*$/i', $text, $m)) {
        $room = trim($m[1]);
        $text = trim(substr($text, 0, strrpos($text, $m[1])), " -_\t");
    }

    $body = trim(preg_replace('/\b(lab|laboratory)\b/i', ' ', $text), " -_\t");
    $chunks = array_values(array_filter(array_map('trim', preg_split('/[-_]+/', $body)), static fn($c) => $c !== ''));
    $code = $chunks[0] ?? '';
    // Second chunk is faculty initials only when it looks like initials (short, no spaces).
    $initials = '';
    if (isset($chunks[1]) && preg_match('/^[A-Za-z]{1,4}\d?(\s*\/\s*[A-Za-z]{1,4}\d?)*$/', $chunks[1])) {
        $initials = strtoupper($chunks[1]);
    }
    if ($code === '') { return $blank + ['room' => $room, 'is_lab' => $is_lab]; }

    $best = null;
    $best_score = 0;
    foreach ($courses as $c) {
        $score = tt_score($code, $c);
        if ($score <= 0) { continue; }
        if ($c['is_lab'] === $is_lab) { $score += 5; }
        if ($score > $best_score) { $best_score = $score; $best = $c; }
    }

    $faculty = $best ? tt_pick_faculty($best['faculty'], $initials) : '';
    if ($faculty === '' && $initials !== '') { $faculty = tt_faculty_by_initials($initials, $courses); }
    return [
        'subject' => $best ? $best['course'] : $code,
        'subject_code' => strtoupper($code),
        'faculty' => $faculty,
        'room' => $room,
        'is_lab' => $is_lab,
    ];
}

function tt_score(string $code, array $course): int {
    // Cell codes the sheets write differently from the course title.
    static $aliases = ['MDMC' => 'MDM'];
    $code = strtoupper(preg_replace('/[^A-Za-z0-9&]/', '', $code));
    if ($code === '') { return 0; }
    $code = $aliases[$code] ?? $code;
    $acr = preg_replace('/[^A-Z0-9]/', '', $course['acronym']);
    $acr_all = preg_replace('/[^A-Z0-9]/', '', $course['acronym_all']);
    if ($course['bracket'] !== '' && $code === $course['bracket']) { return 100; }
    if ($code === $acr || $code === $acr_all) { return 90; }
    if (strlen($code) >= 2 && (strpos($acr, $code) === 0 || strpos($acr_all, $code) === 0)) { return 70; }
    if (strpos($course['upper'], $code) === 0) { return 60; }
    // "DBMS" -> "DataBase Management Systems": letters in order anywhere in the name.
    if (strlen($code) >= 3 && tt_subsequence($code, $course['upper'])) { return 40; }
    return 0;
}

function tt_subsequence(string $needle, string $haystack): bool {
    $pos = 0;
    foreach (str_split($needle) as $ch) {
        $pos = strpos($haystack, $ch, $pos);
        if ($pos === false) { return false; }
        $pos++;
    }
    return true;
}

/** Course faculty may be a "A/ B/ C" list; narrow it with the cell initials when possible. */
function tt_pick_faculty(string $faculty, string $initials): string {
    if ($faculty === '' || $initials === '' || strpos($faculty, '/') === false) { return $faculty; }
    foreach (explode('/', $faculty) as $person) {
        $person = trim($person);
        if ($person !== '' && tt_initials_match($initials, $person)) { return $person; }
    }
    return $faculty;
}

/** Cell code the sheet never spells out as a course - fall back to whoever the initials name. */
function tt_faculty_by_initials(string $initials, array $courses): string {
    $hits = [];
    foreach ($courses as $c) {
        foreach (explode('/', $c['faculty']) as $person) {
            $person = trim($person);
            if ($person !== '' && tt_initials_match($initials, $person)) { $hits[$person] = true; }
        }
    }
    return count($hits) === 1 ? array_key_first($hits) : '';
}

/** "PKH" matches "Dr Poonamkumar Hanwate"; "MA" matches "Dr. M A Ansari". */
function tt_initials_match(string $initials, string $name): bool {
    $name = strtolower(preg_replace('/\b(dr|mr|mrs|ms|prof)\b\.?/i', '', $name));
    $letters = str_split(strtolower(preg_replace('/[^A-Za-z]/', '', $initials)));
    $pos = 0;
    foreach ($letters as $ch) {
        $pos = strpos($name, $ch, $pos);
        if ($pos === false) { return false; }
        $pos++;
    }
    return true;
}

/** Distinct faculty names across every imported class, sorted. */
function tt_faculty_list(array $schedules): array {
    $names = [];
    foreach ($schedules as $s) {
        foreach ($s['sessions'] as $session) {
            $name = $session['faculty'] ?? '';
            if ($name !== '' && empty($session['is_break'])) { $names[$name] = true; }
        }
    }
    $names = array_keys($names);
    natcasesort($names);
    return array_values($names);
}

// Study year lives in the class-name prefix ("SY CSE A", "TY-SE-A", "BE CS- A") in both
// the Excel import and the DB; the `years` table is the academic year (2026-27), not this.
const TT_YEAR_LABELS = ['FY' => 'First Year', 'SY' => 'Second Year', 'TY' => 'Third Year', 'BE' => 'Final Year'];

function tt_year_code(string $class_name): string {
    return preg_match('/^\s*(FY|SY|TY|BE)\b/i', $class_name, $m) ? strtoupper($m[1]) : '';
}

function tt_year_label(string $code): string {
    return TT_YEAR_LABELS[$code] ?? $code;
}

/** Year codes present in the given class names, in study order. */
function tt_year_list(array $class_names): array {
    $found = array_filter(array_map('tt_year_code', $class_names));
    return array_values(array_intersect(array_keys(TT_YEAR_LABELS), $found));
}

// Excel spells the combined lab two ways; treat them as the same room.
function tt_normalize_room(string $room): string {
    $room = trim($room);
    return preg_match('/^104\s*(?:&|and|-)\s*105$/i', $room) ? '104-105' : $room;
}

/** Distinct rooms across every imported class, sorted. */
function tt_room_list(array $schedules): array {
    $rooms = [];
    foreach ($schedules as $s) {
        foreach ($s['sessions'] as $session) {
            $room = $session['room'] ?? '';
            if ($room !== '' && empty($session['is_break'])) { $rooms[tt_normalize_room($room)] = true; }
        }
    }
    $rooms = array_keys($rooms);
    natcasesort($rooms);
    return array_values($rooms);
}

/** Ordered list of distinct time slots across the given schedules. */
function tt_time_slots(array $schedules): array {
    $times = [];
    foreach ($schedules as $s) {
        foreach (($s['time_slots'] ?? []) as $time) {
            $time = trim((string) $time);
            if ($time !== '' && !in_array($time, $times, true)) { $times[] = $time; }
        }
        foreach ($s['sessions'] as $session) {
            $time = $session['time'] ?? '';
            if ($time !== '' && !in_array($time, $times, true)) { $times[] = $time; }
        }
    }
    usort($times, static fn($a, $b) => strcmp(tt_time_key($a), tt_time_key($b)));
    return $times;
}

/** Sheet times are 12-hour with no am/pm: anything before 8 is an afternoon slot. */
function tt_time_key(string $time): string {
    if (!preg_match('/(\d{1,2})[.:](\d{2})/', $time, $m)) { return $time; }
    $hour = (int) $m[1];
    if ($hour < 8) { $hour += 12; }
    return sprintf('%02d%02d', $hour, (int) $m[2]);
}

/** Break label for a time row, or '' when the row is a teaching row. */
function tt_break_label(array $sessions_by_time, string $time): string {
    foreach ($sessions_by_time[$time] ?? [] as $session) {
        if (!empty($session['is_break'])) { return trim((string) $session['entry']); }
    }
    return '';
}
