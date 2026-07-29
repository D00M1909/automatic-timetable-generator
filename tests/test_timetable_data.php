<?php
// Run: C:\xampp\php\php.exe tests\test_timetable_data.php
require_once __DIR__ . '/../includes/timetable_data.php';

$schedules = tt_load();
assert(count($schedules) > 0, 'no schedules loaded');

$by_name = [];
foreach ($schedules as $s) { $by_name[$s['class_name']] = $s; }

function cell(array $schedule, string $entry): array {
    foreach ($schedule['sessions'] as $session) {
        if (trim($session['entry']) === $entry) { return $session; }
    }
    throw new RuntimeException("entry not found: $entry");
}

$sy_ece = $by_name['SY ECE A'];
$c = cell($sy_ece, 'DBMS- MA-003');
assert($c['subject'] === 'Database Management Systems', 'DBMS subject: ' . $c['subject']);
assert($c['faculty'] === 'Dr. M A Ansari', 'DBMS faculty: ' . $c['faculty']);
assert($c['room'] === '003', 'DBMS room: ' . $c['room']);
assert($c['is_lab'] === false);

$c = cell($sy_ece, 'DBMS Lab- MA-107');
assert($c['is_lab'] === true);
assert($c['subject'] === 'Database Management Systems- Lab', 'lab subject: ' . $c['subject']);

$c = cell($sy_ece, 'DM&GT-NF-102');
assert($c['subject'] === 'Discrete Mathematics & Graph Theory', 'DM&GT: ' . $c['subject']);

$cse_a = $by_name['SY CSE A'];
$c = cell($cse_a, 'OOP -PKH-001');
assert($c['subject'] === 'Object Oriented Programming', 'OOP: ' . $c['subject']);
assert($c['faculty'] === 'Dr. Poonamkumar Hanwate', 'OOP faculty: ' . $c['faculty']);

// Multi-faculty course narrowed by nothing stays as the full list.
$c = cell($cse_a, 'MDMC-Minor');
assert(strpos($c['faculty'], '/') !== false, 'MDMC faculty list: ' . $c['faculty']);

// "Online" is a room, not a subject fragment.
$be_cs = $by_name['BE CS- A'];
$c = cell($be_cs, 'VAPT - DJ -Online');
assert($c['room'] === 'Online', 'VAPT room: ' . $c['room']);
assert($c['faculty'] === 'Ms. Dhanashri Joshi', 'VAPT faculty: ' . $c['faculty']);

// "Online Mode" is the same room, however the sheet spells the cell around it, and the
// subject_code must not absorb the venue or a trailing "Minor"/"Mode" qualifier.
$courses = tt_prepare_courses($cse_a['course_faculty'] ?? []);
foreach ([
    'MDMC Lab Online Mode' => ['MDMC', 'Online', true],
    'MDMC Minor Online Mode' => ['MDMC', 'Online', false],
    'MDMC-Minor Online Mode' => ['MDMC', 'Online', false],
    'MDMC - Lab Online Mode' => ['MDMC', 'Online', true],
    'Mini Project Online MOde' => ['MINI PROJECT', 'Online', false],
    'MDMC-Minor' => ['MDMC', '', false],
] as $entry => [$code, $room, $lab]) {
    $r = tt_resolve($entry, $courses);
    assert($r['subject_code'] === $code, "$entry code: {$r['subject_code']}");
    assert($r['room'] === $room, "$entry room: {$r['room']}");
    assert($r['is_lab'] === $lab, "$entry lab");
}

// Punctuation/spacing variants of the same name collapse to one canonical spelling.
assert(tt_canonical_faculty('Dr.Arati Kothari') === 'Dr. Arati Kothari', 'no-space title');
assert(tt_canonical_faculty('Dr Ayushi Godiya') === 'Dr. Ayushi Godiya', 'no-period title');
assert(tt_canonical_faculty('Dr. Sudheer Kadam') === 'Dr. Sudhir Kadam', 'aliased spelling');
assert(tt_canonical_faculty('Dr. MA Ansari') === 'Dr. M A Ansari', 'squashed initials');
assert(tt_canonical_faculty('Priyanka Patil') === 'Ms. Priyanka Patil', 'missing title');
$faculty_list = tt_faculty_list($schedules);
assert(!in_array('Dr Poonamkumar Hanwate', $faculty_list, true), 'stale spelling should not survive: ' . implode(', ', $faculty_list));
assert(count($faculty_list) === count(array_unique($faculty_list)), 'faculty list has duplicates');

// 12-hour sheet times must sort chronologically, not lexically.
$times = tt_time_slots($schedules);
assert($times[0] === '09.30 to 10.30', 'first slot: ' . $times[0]);
assert(array_search('1.15 to 2.15', $times, true) > array_search('11.30 to 12.30', $times, true), 'afternoon sorted before morning');

// Coverage: most teaching cells must resolve to a real course with a faculty.
$total = $unresolved = 0;
foreach ($schedules as $s) {
    foreach ($s['sessions'] as $session) {
        if (!empty($session['is_break']) || trim($session['entry']) === '') { continue; }
        $total++;
        if ($session['faculty'] === '') { $unresolved++; }
    }
}
$rate = 1 - $unresolved / max(1, $total);
printf("resolved %d/%d cells (%.1f%%)\n", $total - $unresolved, $total, $rate * 100);
assert($rate > 0.85, 'resolution rate too low');

echo "OK\n";
