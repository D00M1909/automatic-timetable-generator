<?php
require_once 'includes/config.php';

$message = '';
$message_type = '';

// Fetch active data for generation
$classes = db_get_rows($conn, "SELECT * FROM classes WHERE year_id IN (SELECT year_id FROM years WHERE year_status='active') ORDER BY class_name");
// Sort classes by constraint severity: FY (1, per-division time window) and
// TY (3, minor-day reservation) are most constrained, placed first; then SY
// (2, minor-day reservation); BE (4) has no extra constraints, placed last.
usort($classes, function($a, $b) {
    $order = [1 => 0, 3 => 0, 2 => 1, 4 => 2];
    $ao = $order[$a['year_of_study']] ?? 3;
    $bo = $order[$b['year_of_study']] ?? 3;
    return $ao - $bo;
});
$working_days = db_get_rows($conn, "SELECT * FROM working_days WHERE is_working=1 ORDER BY day_order");
$time_slots = db_get_rows($conn, "SELECT * FROM time_slots WHERE is_active=1 ORDER BY slot_number");
	$assignments = db_get_rows($conn, "SELECT sa.*, s.subject_name, s.subject_type, s.lecture_hours_per_week, s.lab_hours_per_week, s.lecture_block_pattern, s.lab_block_pattern, s.is_minor, f.faculty_name, f.max_hours_per_day, f.max_hours_per_week, c.class_name, c.strength, c.year_of_study FROM subject_assignments sa JOIN subjects s ON sa.subject_id = s.subject_id JOIN faculty f ON sa.faculty_id = f.faculty_id JOIN classes c ON sa.class_id = c.class_id ORDER BY sa.class_id, sa.subject_id");
	$rooms = db_get_rows($conn, "SELECT r.*, b.building_name, b.has_ac as building_ac FROM rooms r JOIN buildings b ON r.building_id = b.building_id WHERE r.room_type IN ('classroom','lab','seminar') ORDER BY r.capacity");
	$faculty_unavailable = db_get_rows($conn, "SELECT * FROM faculty_unavailable");
	$room_unavailable = db_get_rows($conn, "SELECT * FROM room_unavailable");
	$faculty_preferences = db_get_rows($conn, "SELECT * FROM faculty_preferences");
	$year_working_days = db_get_rows($conn, "SELECT * FROM year_working_days");

	$days = $working_days;
	// time_slots.year_of_study NULL = shared across all years (existing SY/TY/BE grid);
	// a specific value = only that year's classes may use this slot (e.g. FY's own grid).
	function class_slots_for(array $time_slots, $year_of_study): array {
	    return array_values(array_filter($time_slots, function($s) use ($year_of_study) {
	        return $s['slot_type'] === 'class' && ($s['year_of_study'] === null || (int)$s['year_of_study'] === (int)$year_of_study);
	    }));
	}

	// Parses "2,1,1" into [2,1,1]. Falls back to the current default behavior
	// when no pattern is set: lecture hours become N separate 1-hour blocks,
	// lab hours become floor(N/2) blocks of 2 (matches pre-FY behavior exactly).
	function parse_block_pattern(?string $pattern, int $total_hours, bool $is_lab): array {
	    if ($pattern !== null && trim($pattern) !== '') {
	        $blocks = array_map('intval', explode(',', $pattern));
	        $sum = array_sum($blocks);
	        if ($sum !== $total_hours) {
	            throw new RuntimeException("Block pattern '$pattern' sums to $sum, expected $total_hours");
	        }
	        return $blocks;
	    }
	    if ($is_lab) {
	        $blocks = [];
	        for ($h = 0; $h + 2 <= $total_hours; $h += 2) $blocks[] = 2;
	        return $blocks;
	    }
	    return array_fill(0, $total_hours, 1);
	}

	// Build lookup: year_of_study → the only day_ids that year's classes ever meet on
	// (e.g. Second Year Mon/Tue/Wed, Third/Fourth Year Wed/Thu/Fri); the last slot on
	// those days is additionally reserved for minor subjects.
	$year_minor_days = [];
	foreach ($year_working_days as $ywd) {
	    $year_minor_days[$ywd['year_of_study']][] = $ywd['day_id'];
	}
	// Find the last class slot number (for minor subject last-slot constraint) — across
	// all years' class-type slots, matching the pre-scoping behavior since only SY/TY/BE
	// use the minor mechanism and they all share the one global (year_of_study=NULL) grid.
	$last_slot_number = 0;
	foreach ($time_slots as $s) {
	    if ($s['slot_type'] === 'class' && $s['slot_number'] > $last_slot_number) $last_slot_number = $s['slot_number'];
	}

$max_class_slots = max(array_map(fn($yos) => count(class_slots_for($time_slots, $yos)), array_unique(array_column($classes, 'year_of_study'))) ?: [0]);
$can_generate = count($classes) > 0 && count($assignments) > 0 && count($days) > 0 && $max_class_slots > 0 && count($rooms) > 0;
$total_required_slots = 0;
foreach ($assignments as $a) { $total_required_slots += $a['lecture_hours_per_week'] + $a['lab_hours_per_week']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    csrf_check();

    if (!$can_generate) {
        $message = "Cannot generate: Missing required data (ensure you have active classes, subjects, faculty, and rooms).";
        $message_type = "error";
        audit_log($conn, 'GENERATE_FAIL', "Insufficient data to generate timetable");
    } else {
        $total_available_slots = count($days) * $max_class_slots * count($classes);
        if ($total_required_slots > $total_available_slots) {
            $message = "Not enough slots available! Required: $total_required_slots, Available: $total_available_slots";
            $message_type = "error";
            audit_log($conn, 'GENERATE_FAIL', "Capacity exceeded");
        } else {
            $conn->begin_transaction();
            try {
                $conn->query("DELETE FROM timetable");
                
                $faculty_daily_hours = [];
                $faculty_weekly_hours = [];
                $class_daily_schedule = [];
                $faculty_daily_schedule = [];
                $room_daily_schedule = [];
                $online_room_ids = array_column(array_filter($rooms, function($r) { return $r['room_name'] === 'Online'; }), 'room_id');
                $class_room_tracking = []; 
                $errors = [];
                $lab_errors = [];
                $placed_sessions = []; // In-memory collection of all successful placements

                // Pre-load faculty unavailable slots
                foreach ($faculty_unavailable as $fu) { 
                    $faculty_daily_schedule[$fu['faculty_id']][$fu['day_id']][$fu['slot_id']] = true; 
                }
                
                // Pre-load room unavailable slots
                foreach ($room_unavailable as $ru) { 
                    $room_daily_schedule[$ru['room_id']][$ru['day_id']][$ru['slot_id']] = true; 
                }

                // Build faculty preferences lookup for quick access
                $faculty_pref_lookup = [];
                foreach ($faculty_preferences as $fp) {
                    $faculty_pref_lookup[$fp['faculty_id']][$fp['day_id']][$fp['slot_id']] = $fp['preference_level'];
                }

	                // Build flat list of all sessions to place (sorted by difficulty, across ALL classes)
	                $all_sessions = [];
	                foreach ($classes as $class) {
	                    $cid = $class['class_id'];
	                    $ca = array_filter($assignments, function($a) use ($cid) { return $a['class_id'] == $cid; });
	                    foreach ($ca as $a) {
	                        $lh = $a['subject_type'] === 'lecture' ? 0 : $a['lab_hours_per_week'];
	                        $leh = $a['subject_type'] === 'lab' ? 0 : $a['lecture_hours_per_week'];
	                        foreach (parse_block_pattern($a['lab_block_pattern'] ?? null, $lh, true) as $size) {
	                            $all_sessions[] = ['type'=>'lab','size'=>$size,'a'=>$a,'c'=>$class];
	                        }
	                        foreach (parse_block_pattern($a['lecture_block_pattern'] ?? null, $leh, false) as $size) {
	                            $all_sessions[] = ['type'=>'lecture','size'=>$size,'a'=>$a,'c'=>$class];
	                        }
	                    }
	                }

	                // Sort: minors first (to grab last slots), then labs, then by hours descending
	                usort($all_sessions, function($a, $b) {
	                    $am = $a['a']['is_minor']; $bm = $b['a']['is_minor'];
	                    if ($am != $bm) return $bm - $am;
	                    $al = ($a['type']==='lab')?1:0; $bl = ($b['type']==='lab')?1:0;
	                    if ($al != $bl) return $bl - $al;
	                    $ah = $a['a']['lecture_hours_per_week']+$a['a']['lab_hours_per_week'];
	                    $bh = $b['a']['lecture_hours_per_week']+$b['a']['lab_hours_per_week'];
	                    return $bh - $ah;
	                });

	                // Place sessions globally (not class-by-class)
	                foreach ($all_sessions as $sess) {
	                    $assignment = $sess['a'];
	                    $class = $sess['c'];
	                    $is_lab = ($sess['type'] === 'lab');
	                    $class_id = $class['class_id'];
	                    $class_strength = $class['strength'];
	                    $assignment_id = $assignment['assignment_id'];
	                    $subject_id = $assignment['subject_id'];
	                    $faculty_id = $assignment['faculty_id'];
	                    $preferred_slot_id = $assignment['preferred_slot_id'] ?? null;
	                    // Minor subjects are cross-class combined sessions: the same faculty legitimately
	                    // teaches several classes' minor slot at once, so exempt it from the normal
	                    // one-class-at-a-time faculty clash/hour-cap rules (mirrors the Online room exemption).
	                    $is_minor_assignment = (bool) $assignment['is_minor'];
	                    // Each year of study only runs on its own fixed day set (e.g. Second Year
	                    // Mon/Tue/Wed, Third/Fourth Year Wed/Thu/Fri) — classes for that year never
	                    // meet outside it. Falls back to all working days if a year has no mapping.
	                    $minor_days = $year_minor_days[$assignment['year_of_study']] ?? null;
	                    $class_days = $minor_days ? array_values(array_filter($days, fn($d) => in_array($d['day_id'], $minor_days))) : $days;
	                    $class_slots = class_slots_for($time_slots, $class['year_of_study']);
	                    // Per-class time window (e.g. FY "Core" divisions can't start before a
	                    // given slot; "CS" divisions have no restriction — both columns NULL).
	                    $min_start = $class['min_start_slot_number'];
	                    $max_end   = $class['max_end_slot_number'];
	                    if ($min_start !== null || $max_end !== null) {
	                        $class_slots = array_values(array_filter($class_slots, function($s) use ($min_start, $max_end) {
	                            if ($min_start !== null && $s['slot_number'] < $min_start) return false;
	                            if ($max_end !== null && $s['slot_number'] > $max_end) return false;
	                            return true;
	                        }));
	                    }

	                    if ($is_lab) {
	                        // --- PLACE LAB (size consecutive slots) ---
	                        $size = $sess['size'];
	                        $best_score = -9999; $best_day = null; $best_idx = null; $best_room = null;
	                        foreach ($class_days as $day) {
	                            $day_id = $day['day_id'];
	                            for ($i = 0; $i <= count($class_slots) - $size; $i++) {
	                                $block = array_slice($class_slots, $i, $size);
                                // $class_slots may exclude breaks/lunch, so array-adjacent != actually consecutive
	                                $contiguous = true;
	                                for ($k = 1; $k < $size; $k++) {
	                                    if ($block[$k]['slot_number'] - $block[$k-1]['slot_number'] != 1) { $contiguous = false; break; }
	                                }
	                                if (!$contiguous) continue;
	                                $clash = false;
	                                foreach ($block as $s) { if (isset($class_daily_schedule[$class_id][$day_id][$s['slot_id']])) { $clash = true; break; } }
	                                if ($clash) continue;
	                                if (!$is_minor_assignment) {
	                                    foreach ($block as $s) { if (isset($faculty_daily_schedule[$faculty_id][$day_id][$s['slot_id']])) { $clash = true; break; } }
	                                    if ($clash) continue;
	                                }
	                                $fd = $faculty_daily_hours[$faculty_id][$day_id] ?? 0;
	                                $fw = $faculty_weekly_hours[$faculty_id] ?? 0;
	                                if (!$is_minor_assignment && (($fd + $size) > $assignment['max_hours_per_day'] || ($fw + $size) > $assignment['max_hours_per_week'])) continue;
	                                $avoid = false; $any_preferred = false;
	                                foreach ($block as $s) {
	                                    $p = $faculty_pref_lookup[$faculty_id][$day_id][$s['slot_id']] ?? 'neutral';
	                                    if ($p === 'avoid') { $avoid = true; break; }
	                                    if ($p === 'preferred') $any_preferred = true;
	                                }
	                                if ($avoid) continue;
	                                $touches_last_slot = false;
	                                foreach ($block as $s) { if ($s['slot_number'] == $last_slot_number) { $touches_last_slot = true; break; } }
	                                if ($touches_last_slot && in_array($day_id, $minor_days ?? []) && !$assignment['is_minor']) continue;
	                                foreach ($rooms as $room) {
	                                    if ($room['room_type'] !== 'lab') continue;
	                                    $is_online = in_array($room['room_id'], $online_room_ids, true);
	                                    if (!$is_online) {
	                                        $room_clash = false;
	                                        foreach ($block as $s) { if (isset($room_daily_schedule[$room['room_id']][$day_id][$s['slot_id']])) { $room_clash = true; break; } }
	                                        if ($room_clash) continue;
	                                    }
	                                    $score = 5;
	                                    if ($room['has_ac']) $score += 5;
	                                    $score += max(0, 20 - abs($room['capacity'] - $class_strength));
	                                    if ($preferred_slot_id) { foreach ($block as $s) { if ($s['slot_id'] == $preferred_slot_id) { $score += 100; break; } } }
	                                    if ($any_preferred) $score += 30;
	                                    if ($assignment['is_minor'] && in_array($day_id, $minor_days ?? [])) $score += 200;
	                                    if ($score > $best_score) { $best_score=$score; $best_day=$day; $best_idx=$i; $best_room=$room; }
	                                }
	                            }
	                        }
	                        if ($best_day && $best_idx !== null && $best_room) {
	                            $day_id = $best_day['day_id'];
	                            foreach (array_slice($class_slots, $best_idx, $size) as $slot) {
	                                $placed_sessions[] = ['class_id'=>$class_id,'day_id'=>$day_id,'slot_id'=>$slot['slot_id'],'room_id'=>$best_room['room_id'],'subject_id'=>$subject_id,'faculty_id'=>$faculty_id,'assignment_id'=>$assignment_id,'is_lab'=>1,'energy_score'=>$best_score];
	                                $class_daily_schedule[$class_id][$day_id][$slot['slot_id']] = $subject_id;
	                                if (!$is_minor_assignment) {
	                                    $faculty_daily_schedule[$faculty_id][$day_id][$slot['slot_id']] = true;
	                                    $faculty_daily_hours[$faculty_id][$day_id] = ($faculty_daily_hours[$faculty_id][$day_id]??0) + 1;
	                                    $faculty_weekly_hours[$faculty_id] = ($faculty_weekly_hours[$faculty_id]??0) + 1;
	                                }
	                                if (!in_array($best_room['room_id'], $online_room_ids, true)) { $room_daily_schedule[$best_room['room_id']][$day_id][$slot['slot_id']] = true; }
	                            }
	                        } else {
	                            $errors[] = "Failed to place Lab: {$assignment['subject_name']} for {$class['class_name']}";
	                        }
	                    } else {
	                        // --- PLACE LECTURE ---
	                        $size = $sess['size'];
	                        if ($size === 1) {
	                        // --- PLACE LECTURE (single slot) ---
	                        $best_score = -9999; $best_day = null; $best_slot = null; $best_room = null;
	                        foreach ($class_days as $day) {
	                            $day_id = $day['day_id'];
	                            foreach ($class_slots as $slot) {
	                                $slot_id = $slot['slot_id'];
	                                if (isset($class_daily_schedule[$class_id][$day_id][$slot_id]) ||
	                                    (!$is_minor_assignment && isset($faculty_daily_schedule[$faculty_id][$day_id][$slot_id]))) continue;
	                                $fd = $faculty_daily_hours[$faculty_id][$day_id] ?? 0;
	                                $fw = $faculty_weekly_hours[$faculty_id] ?? 0;
	                                if (!$is_minor_assignment && ($fd >= $assignment['max_hours_per_day'] || $fw >= $assignment['max_hours_per_week'])) continue;
	                                // Minor subjects legitimately stack multiple hours on the same day
	                                // (lab block + last-slot lecture); only non-minor subjects spread across days.
	                                if (!$is_minor_assignment) {
	                                    $st = false;
	                                    foreach ($class_slots as $s) {
	                                        if (isset($class_daily_schedule[$class_id][$day_id][$s['slot_id']]) &&
	                                            $class_daily_schedule[$class_id][$day_id][$s['slot_id']] == $subject_id) { $st = true; break; }
	                                    }
	                                    if ($st) continue;
	                                }
	                                $pref = $faculty_pref_lookup[$faculty_id][$day_id][$slot_id] ?? 'neutral';
	                                if ($pref === 'avoid') continue;
	                                $is_last_slot = ($slot['slot_number'] == $last_slot_number);
	                                if ($is_last_slot && in_array($day_id, $minor_days ?? [])) {
	                                    if (!$assignment['is_minor']) continue;
	                                    $score = 200;
	                                } else { $score = 0; }
	                                foreach ($rooms as $room) {
	                                    if ($room['capacity']<$class_strength) continue;
	                                    $is_online = in_array($room['room_id'], $online_room_ids, true);
	                                    if (!$is_online && isset($room_daily_schedule[$room['room_id']][$day_id][$slot_id])) continue;
	                                    $s = $score;
	                                    $prev = $class_room_tracking[$class_id][$day_id] ?? null;
	                                    if ($prev && $prev==$room['building_id']) $s += 15;
	                                    if ($room['has_ac']||$room['building_ac']) $s += 5;
	                                    $s += max(0, 20-abs($room['capacity']-$class_strength));
	                                    if ($preferred_slot_id && $slot_id==$preferred_slot_id) $s += 100;
	                                    if ($pref === 'preferred') $s += 30;
	                                    if ($s > $best_score) { $best_score=$s; $best_day=$day; $best_slot=$slot; $best_room=$room; }
	                                }
	                            }
	                        }
	                        if ($best_day && $best_slot && $best_room) {
	                            $placed_sessions[] = ['class_id'=>$class_id,'day_id'=>$best_day['day_id'],'slot_id'=>$best_slot['slot_id'],'room_id'=>$best_room['room_id'],'subject_id'=>$subject_id,'faculty_id'=>$faculty_id,'assignment_id'=>$assignment_id,'is_lab'=>0,'energy_score'=>$best_score];
	                            $class_daily_schedule[$class_id][$best_day['day_id']][$best_slot['slot_id']] = $subject_id;
	                            if (!$is_minor_assignment) {
	                                $faculty_daily_schedule[$faculty_id][$best_day['day_id']][$best_slot['slot_id']] = true;
	                                $faculty_daily_hours[$faculty_id][$best_day['day_id']] = ($faculty_daily_hours[$faculty_id][$best_day['day_id']]??0) + 1;
	                                $faculty_weekly_hours[$faculty_id] = ($faculty_weekly_hours[$faculty_id]??0) + 1;
	                            }
	                            if (!in_array($best_room['room_id'], $online_room_ids, true)) { $room_daily_schedule[$best_room['room_id']][$best_day['day_id']][$best_slot['slot_id']] = true; }
	                            $class_room_tracking[$class_id][$best_day['day_id']] = $best_room['building_id'];
	                        } else {
	                            $errors[] = "Failed to place Lecture: {$assignment['subject_name']} for {$class['class_name']}";
	                        }
	                        } else {
	                        // Continuous lecture block (e.g. the "2" in a "2,1,1" pattern).
	                        // Same consecutive-block search as the lab path, but room_type is any
	                        // non-lab room and is_lab is written as 0.
	                        $best_score = -9999; $best_day = null; $best_idx = null; $best_room = null;
	                        foreach ($class_days as $day) {
	                            $day_id = $day['day_id'];
	                            for ($i = 0; $i <= count($class_slots) - $size; $i++) {
	                                $block = array_slice($class_slots, $i, $size);
	                                $contiguous = true;
	                                for ($k = 1; $k < $size; $k++) {
	                                    if ($block[$k]['slot_number'] - $block[$k-1]['slot_number'] != 1) { $contiguous = false; break; }
	                                }
	                                if (!$contiguous) continue;
	                                $clash = false;
	                                foreach ($block as $s) { if (isset($class_daily_schedule[$class_id][$day_id][$s['slot_id']])) { $clash = true; break; } }
	                                if ($clash) continue;
	                                if (!$is_minor_assignment) {
	                                    foreach ($block as $s) { if (isset($faculty_daily_schedule[$faculty_id][$day_id][$s['slot_id']])) { $clash = true; break; } }
	                                    if ($clash) continue;
	                                }
	                                $fd = $faculty_daily_hours[$faculty_id][$day_id] ?? 0;
	                                $fw = $faculty_weekly_hours[$faculty_id] ?? 0;
	                                if (!$is_minor_assignment && (($fd + $size) > $assignment['max_hours_per_day'] || ($fw + $size) > $assignment['max_hours_per_week'])) continue;
	                                $avoid = false; $any_preferred = false;
	                                foreach ($block as $s) {
	                                    $p = $faculty_pref_lookup[$faculty_id][$day_id][$s['slot_id']] ?? 'neutral';
	                                    if ($p === 'avoid') { $avoid = true; break; }
	                                    if ($p === 'preferred') $any_preferred = true;
	                                }
	                                if ($avoid) continue;
	                                foreach ($rooms as $room) {
	                                    if ($room['room_type'] === 'lab') continue;
	                                    if ($room['capacity'] < $class_strength) continue;
	                                    $is_online = in_array($room['room_id'], $online_room_ids, true);
	                                    if (!$is_online) {
	                                        $room_clash = false;
	                                        foreach ($block as $s) { if (isset($room_daily_schedule[$room['room_id']][$day_id][$s['slot_id']])) { $room_clash = true; break; } }
	                                        if ($room_clash) continue;
	                                    }
	                                    $score = 0;
	                                    if ($room['has_ac'] || $room['building_ac']) $score += 5;
	                                    $score += max(0, 20 - abs($room['capacity'] - $class_strength));
	                                    if ($preferred_slot_id) { foreach ($block as $s) { if ($s['slot_id'] == $preferred_slot_id) { $score += 100; break; } } }
	                                    if ($any_preferred) $score += 30;
	                                    if ($score > $best_score) { $best_score=$score; $best_day=$day; $best_idx=$i; $best_room=$room; }
	                                }
	                            }
	                        }
	                        if ($best_day && $best_idx !== null && $best_room) {
	                            $day_id = $best_day['day_id'];
	                            foreach (array_slice($class_slots, $best_idx, $size) as $slot) {
	                                $placed_sessions[] = ['class_id'=>$class_id,'day_id'=>$day_id,'slot_id'=>$slot['slot_id'],'room_id'=>$best_room['room_id'],'subject_id'=>$subject_id,'faculty_id'=>$faculty_id,'assignment_id'=>$assignment_id,'is_lab'=>0,'energy_score'=>$best_score];
	                                $class_daily_schedule[$class_id][$day_id][$slot['slot_id']] = $subject_id;
	                                if (!$is_minor_assignment) {
	                                    $faculty_daily_schedule[$faculty_id][$day_id][$slot['slot_id']] = true;
	                                    $faculty_daily_hours[$faculty_id][$day_id] = ($faculty_daily_hours[$faculty_id][$day_id]??0) + 1;
	                                    $faculty_weekly_hours[$faculty_id] = ($faculty_weekly_hours[$faculty_id]??0) + 1;
	                                }
	                                if (!in_array($best_room['room_id'], $online_room_ids, true)) { $room_daily_schedule[$best_room['room_id']][$day_id][$slot['slot_id']] = true; }
	                            }
	                        } else {
	                            $errors[] = "Failed to place Lecture block: {$assignment['subject_name']} for {$class['class_name']}";
	                        }
	                        }
	                    }
	                }
	                // ---- Backtracking Pass: try to resolve failures by removing low-energy placements ----
	                if (!empty($errors)) {
	                    $retry_errors = [];
	                    foreach ($errors as $err) {
	                        // Parse the error to find the assignment
	                        if (preg_match('/Failed to place (Lecture|Lab): (.+) for (.+)/', $err, $m)) {
	                            $subj_name = $m[2];
	                            $class_name = $m[3];
	                            
	                            // Find the assignment that failed
	                            $failed_assignments = array_filter($assignments, function($a) use ($subj_name, $class_name) {
	                                return $a['subject_name'] == $subj_name && $a['class_name'] == $class_name;
	                            });
	                            
	                            foreach ($failed_assignments as $fa) {
	                                // Find this faculty's lowest-energy placed session
	                                $fac_id = $fa['faculty_id'];
	                                $candidates = [];
	                                foreach ($placed_sessions as $idx => $ps) {
	                                    if ($ps['faculty_id'] == $fac_id && $ps['energy_score'] < 100) {
	                                        $candidates[$idx] = $ps['energy_score'];
	                                    }
	                                }
	                                asort($candidates);
	                                
	                                foreach ($candidates as $remove_idx => $score) {
	                                    $removed = $placed_sessions[$remove_idx];
	                                    
	                                    // Remove from tracking arrays
	                                    unset($class_daily_schedule[$removed['class_id']][$removed['day_id']][$removed['slot_id']]);
	                                    unset($faculty_daily_schedule[$removed['faculty_id']][$removed['day_id']][$removed['slot_id']]);
	                                    unset($room_daily_schedule[$removed['room_id']][$removed['day_id']][$removed['slot_id']]);
	                                    $faculty_daily_hours[$removed['faculty_id']][$removed['day_id']] = max(0, ($faculty_daily_hours[$removed['faculty_id']][$removed['day_id']] ?? 1) - 1);
	                                    $faculty_weekly_hours[$removed['faculty_id']] = max(0, ($faculty_weekly_hours[$removed['faculty_id']] ?? 1) - 1);
	                                    unset($placed_sessions[$remove_idx]);
	                                    
	                                    // Try placing the removed session back (it may find a new spot)
	                                    $removed_ok = false;
	                                    // $removed can belong to a different class than $fa (matched only by
                                    // shared faculty), so its own class's year decides the allowed days.
                                    $removed_class = array_filter($classes, fn($c) => $c['class_id'] == $removed['class_id']);
                                    $removed_class_row = $removed_class ? reset($removed_class) : null;
                                    $removed_year = $removed_class_row['year_of_study'] ?? null;
                                    $retry_minor_days = $year_minor_days[$removed_year] ?? null;
	                                    $retry_days = $retry_minor_days ? array_values(array_filter($days, fn($d) => in_array($d['day_id'], $retry_minor_days))) : $days;
	                                    // $class_slots is scoped per-class in the main placement loop above (Task 4/5) -
	                                    // by the time this backtracking pass runs it holds a stale value from whichever
	                                    // class was processed last, so recompute it here for the class actually being retried.
	                                    $retry_slots = class_slots_for($time_slots, $removed_year);
	                                    $retry_min_start = $removed_class_row['min_start_slot_number'] ?? null;
	                                    $retry_max_end = $removed_class_row['max_end_slot_number'] ?? null;
	                                    if ($retry_min_start !== null || $retry_max_end !== null) {
	                                        $retry_slots = array_values(array_filter($retry_slots, function($s) use ($retry_min_start, $retry_max_end) {
	                                            if ($retry_min_start !== null && $s['slot_number'] < $retry_min_start) return false;
	                                            if ($retry_max_end !== null && $s['slot_number'] > $retry_max_end) return false;
	                                            return true;
	                                        }));
	                                    }
	                                    foreach ($retry_days as $day) {
	                                        foreach ($retry_slots as $slot) {
	                                            $sid = $slot['slot_id'];
	                                            if (isset($class_daily_schedule[$removed['class_id']][$day['day_id']][$sid]) ||
	                                                isset($faculty_daily_schedule[$fac_id][$day['day_id']][$sid])) continue;
	                                            foreach ($rooms as $room) {
	                                                if (!in_array($room['room_id'], $online_room_ids, true) && isset($room_daily_schedule[$room['room_id']][$day['day_id']][$sid])) continue;
	                                                // Place it back
	                                                $placed_sessions[] = [
	                                                    'class_id' => $removed['class_id'], 'day_id' => $day['day_id'], 'slot_id' => $sid,
	                                                    'room_id' => $room['room_id'], 'subject_id' => $removed['subject_id'],
	                                                    'faculty_id' => $fac_id, 'assignment_id' => $removed['assignment_id'],
	                                                    'is_lab' => $removed['is_lab'], 'energy_score' => 10,
	                                                ];
	                                                $class_daily_schedule[$removed['class_id']][$day['day_id']][$sid] = $removed['subject_id'];
	                                                $faculty_daily_schedule[$fac_id][$day['day_id']][$sid] = true;
	                                                if (!in_array($room['room_id'], $online_room_ids, true)) { $room_daily_schedule[$room['room_id']][$day['day_id']][$sid] = true; }
	                                                $faculty_daily_hours[$fac_id][$day['day_id']] = ($faculty_daily_hours[$fac_id][$day['day_id']] ?? 0) + 1;
	                                                $faculty_weekly_hours[$fac_id] = ($faculty_weekly_hours[$fac_id] ?? 0) + 1;
	                                                $removed_ok = true;
	                                                break 3;
	                                            }
	                                        }
	                                    }
	                                    
	                                    if ($removed_ok) {
	                                        // The backtrack worked — remove this from errors
	                                        continue 2; // Continue to next error
	                                    } else {
	                                        // Restore the removal
	                                        $placed_sessions[$remove_idx] = $removed;
	                                        $class_daily_schedule[$removed['class_id']][$removed['day_id']][$removed['slot_id']] = $removed['subject_id'];
	                                        $faculty_daily_schedule[$removed['faculty_id']][$removed['day_id']][$removed['slot_id']] = true;
	                                        if (!in_array($removed['room_id'], $online_room_ids, true)) { $room_daily_schedule[$removed['room_id']][$removed['day_id']][$removed['slot_id']] = true; }
	                                        $faculty_daily_hours[$removed['faculty_id']][$removed['day_id']] = ($faculty_daily_hours[$removed['faculty_id']][$removed['day_id']] ?? 0) + 1;
	                                        $faculty_weekly_hours[$removed['faculty_id']] = ($faculty_weekly_hours[$removed['faculty_id']] ?? 0) + 1;
	                                    }
	                                }
	                                $retry_errors[] = $err;
	                            }
	                        }
	                    }
	                    $errors = $retry_errors;
	                }

	                // Batch insert all placed sessions into the database
                if (!empty($placed_sessions)) {
                    $stmt = $conn->prepare("INSERT INTO timetable (class_id, day_id, slot_id, room_id, subject_id, faculty_id, assignment_id, is_lab, energy_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($placed_sessions as $ps) {
                        $stmt->bind_param("iiiiiiiii", $ps['class_id'], $ps['day_id'], $ps['slot_id'], $ps['room_id'], $ps['subject_id'], $ps['faculty_id'], $ps['assignment_id'], $ps['is_lab'], $ps['energy_score']);
                        $stmt->execute();
                    }
                    $stmt->close();
                }

                $conn->commit();
                
                $count = db_get_row($conn, "SELECT COUNT(*) as cnt FROM timetable")['cnt'] ?? 0;
                $lab_count_result = db_get_row($conn, "SELECT COUNT(*) as cnt FROM timetable WHERE is_lab = 1")['cnt'] ?? 0;
                
                if (empty($errors)) {
                    $message = "Timetable generated successfully! <strong>$count</strong> sessions scheduled.";
                    $message .= " <strong>$lab_count_result</strong> lab sessions placed.";
                    $message_type = "success";
                    audit_log($conn, 'GENERATE_SUCCESS', "Timetable generated with $count sessions");
                } else {
                    $message = "Generation completed with some errors. Scheduled: <strong>$count</strong> sessions.";
                    $message .= " <strong>$lab_count_result</strong> lab sessions placed.";
                    $message .= "<ul style='margin-top:10px; padding-left:20px; font-size:12px;'>";
                    foreach (array_slice($errors, 0, 8) as $err) {
                        $message .= "<li>" . htmlspecialchars($err) . "</li>";
                    }
                    if (count($errors) > 8) {
                        $message .= "<li>... and " . (count($errors) - 8) . " more errors</li>";
                    }
                    $message .= "</ul>";
                    $message_type = "warning";
                    audit_log($conn, 'GENERATE_PARTIAL', implode("; ", array_slice($errors, 0, 5)));
                }
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error during generation: " . $e->getMessage();
                $message_type = "error";
                audit_log($conn, 'GENERATE_ERROR', $e->getMessage());
            }
        }
    }
}

$timetable_count = db_get_row($conn, "SELECT COUNT(*) as count FROM timetable")['count'] ?? 0;
$avg_energy = db_get_row($conn, "SELECT AVG(energy_score) as avg FROM timetable")['avg'] ?? 0;
$timetable_exists = $timetable_count > 0;
$lab_count = db_get_row($conn, "SELECT COUNT(*) as cnt FROM timetable WHERE is_lab = 1")['cnt'] ?? 0;
$assignments_with_labs = count(array_filter($assignments, function($a) { return $a['lab_hours_per_week'] > 0; }));
$lab_rooms = count(array_filter($rooms, function($r) { return $r['room_type'] === 'lab'; }));
$classrooms = count(array_filter($rooms, function($r) { return $r['room_type'] === 'classroom'; }));
$assignments_with_preferred = count(array_filter($assignments, function($a) { return !empty($a['preferred_slot_id']); }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Timetable</title>
    <?php common_styles(); ?>
    <style>
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-badge.active { background: #27ae60; color: white; }
        .status-badge.ready { background: #3498db; color: white; }
        .status-badge.warning { background: #f39c12; color: white; }
        .status-badge.error { background: #e74c3c; color: white; }
        
        .view-buttons { 
            display: flex; 
            gap: 12px; 
            flex-wrap: wrap; 
            justify-content: center;
            margin-top: 15px;
        }
        .view-buttons .btn {
            padding: 8px 20px;
            font-size: 13px;
        }
        .view-buttons .btn-purple {
            background: #6B1B5E;
        }
        .view-buttons .btn-purple:hover {
            background: #5a1850;
        }
        .view-buttons .btn-outline {
            background: transparent;
            border: 2px solid #6B1B5E;
            color: #6B1B5E;
        }
        .view-buttons .btn-outline:hover {
            background: #6B1B5E;
            color: white;
        }
        .generate-section {
            text-align: center;
            padding: 30px 20px;
        }
        .generate-section .icon-large svg {
            width: 48px;
            height: 48px;
            fill: #6B1B5E;
            margin-bottom: 15px;
        }
        .generate-section h3 {
            color: #333;
            font-size: 18px;
            margin-bottom: 8px;
        }
        .generate-section p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .generate-section .stats-summary {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            margin: 20px 0 25px 0;
        }
        .generate-section .stats-summary .stat-item {
            text-align: center;
        }
        .generate-section .stats-summary .stat-item .number {
            font-size: 22px;
            font-weight: 700;
            color: #6B1B5E;
        }
        .generate-section .stats-summary .stat-item .label {
            font-size: 12px;
            color: #888;
            margin-top: 2px;
        }
        .btn-generate {
            padding: 14px 50px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #27ae60;
            color: white;
        }
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.3);
        }
        .btn-generate svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }
        .btn-generate:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        .btn-generate:disabled:hover {
            transform: none;
            box-shadow: none;
        }
        .feature-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #90caf9;
            margin-left: 6px;
        }
    </style>
</head>
<body>
    <?php svg_defs(); ?>
    <?php sidebar('generate'); ?>
    <?php top_header(); ?>

    <div class="content-wrapper">
        <?php breadcrumb(['Timetable', 'Generate']); ?>

        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($timetable_exists): ?>
            <div class="alert alert-info">
                <strong>Current Status:</strong> <?php echo $timetable_count; ?> sessions already scheduled.
                <span style="display:block;font-size:12px;margin-top:5px;color:#888;">
                    Average energy score: <?php echo round($avg_energy, 1); ?> | Lab sessions: <?php echo $lab_count; ?>
                    <?php if($assignments_with_preferred > 0): ?>
                        | <span class="feature-badge"><?php echo $assignments_with_preferred; ?> assignments have preferred slots</span>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="content-box">
            <div class="content-box-header">Configuration Summary</div>
            <div class="content-box-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo count($classes); ?></div>
                        <div class="label">Classes</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo count($assignments); ?></div>
                        <div class="label">Subject Assignments</div>
                        <div class="label" style="font-size:10px;color:#888;margin-top:4px;">
                            <?php echo $assignments_with_labs; ?> with labs
                            <?php if($assignments_with_preferred > 0): ?>
                                <br><span class="feature-badge"><?php echo $assignments_with_preferred; ?> with preferred slots</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo count($days); ?></div>
                        <div class="label">Working Days</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $max_class_slots; ?></div>
                        <div class="label">Slots Per Day</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo count($rooms); ?></div>
                        <div class="label">Rooms</div>
                        <div class="label" style="font-size:10px;color:#888;margin-top:4px;">
                            <?php echo $classrooms; ?> classrooms, <?php echo $lab_rooms; ?> labs
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $total_required_slots; ?></div>
                        <div class="label">Required Slots</div>
                        <div class="label" style="font-size:10px;color:#888;margin-top:4px;">
                            Available: <?php echo count($days) * $max_class_slots * count($classes); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-box">
            <div class="content-box-header">Generate Timetable</div>
            <div class="content-box-body">
                <div class="generate-section">
                    <?php if($can_generate): ?>
                        <?php if($total_required_slots > count($days) * $max_class_slots * count($classes)): ?>
                            <div class="alert alert-error" style="text-align:left;">
                                <strong>Capacity Issue:</strong> Required <?php echo $total_required_slots; ?> slots but only 
                                <?php echo count($days) * $max_class_slots * count($classes); ?> available.
                            </div>
                        <?php else: ?>
                            <div class="icon-large">
                                <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
                            </div>
                            <h3>Ready to Generate</h3>
                            <p>Schedule <strong><?php echo $total_required_slots; ?></strong> sessions across 
                            <strong><?php echo count($classes); ?></strong> classes using <strong><?php echo count($rooms); ?></strong> rooms.</p>
                            
                            <div class="stats-summary">
                                <div class="stat-item">
                                    <div class="number"><?php echo count($classes); ?></div>
                                    <div class="label">Classes</div>
                                </div>
                                <div class="stat-item">
                                    <div class="number"><?php echo count($assignments); ?></div>
                                    <div class="label">Assignments</div>
                                </div>
                                <div class="stat-item">
                                    <div class="number"><?php echo $total_required_slots; ?></div>
                                    <div class="label">Total Sessions</div>
                                </div>
                                <div class="stat-item">
                                    <div class="number"><?php echo $assignments_with_labs; ?></div>
                                    <div class="label">Lab Sessions</div>
                                </div>
                                <?php if($assignments_with_preferred > 0): ?>
                                <div class="stat-item">
                                    <div class="number"><?php echo $assignments_with_preferred; ?></div>
                                    <div class="label">With Preferred Slots</div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if($assignments_with_preferred > 0): ?>
                            <div style="margin-bottom:15px;font-size:13px;color:#666;background:#e8f5e9;padding:10px 20px;border-radius:8px;display:inline-block;">
                                <svg width="16" height="16" fill="#2e7d32" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                                <strong><?php echo $assignments_with_preferred; ?></strong> assignments have preferred time slots set. The engine will prioritize these slots.
                            </div>
                            <?php endif; ?>

                            <form method="POST" onsubmit="return confirm('This will delete the existing timetable and generate a new one. Continue?');">
                                <?php csrf_field(); ?>
                                <button type="submit" name="generate" value="1" class="btn-generate">
                                    <svg><use href="#icon-refresh"/></svg> Generate Now
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-error" style="text-align:left;">
                            <strong>Cannot Generate:</strong> Missing required data.
                            <ul style="margin-top:8px;padding-left:20px;font-size:13px;">
                                <?php if(count($classes) == 0): ?><li>No active classes found</li><?php endif; ?>
                                <?php if(count($assignments) == 0): ?><li>No subject assignments found</li><?php endif; ?>
                                <?php if(count($days) == 0): ?><li>No working days configured</li><?php endif; ?>
                                <?php if($max_class_slots == 0): ?><li>No time slots configured</li><?php endif; ?>
                                <?php if(count($rooms) == 0): ?><li>No rooms available</li><?php endif; ?>
                            </ul>
                        </div>
                        <a href="setup.php" class="btn" style="margin-top:10px;">Go to Setup</a>
                    <?php endif; ?>
                    
                    <?php if($timetable_exists && $can_generate): ?>
                        <div style="margin-top:25px;padding-top:20px;border-top:1px solid #eee;">
                            <p style="color:#888;font-size:13px;margin-bottom:12px;">View the generated timetable:</p>
                            <div class="view-buttons">
                                <a href="view.php?source=generated&mode=master" class="btn btn-purple">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                    Master View
                                </a>
                                <a href="view.php?source=generated&mode=class" class="btn btn-outline">By Class</a>
                                <a href="view.php?source=generated&mode=faculty" class="btn btn-outline">By Faculty</a>
                                <a href="view.php?source=generated&mode=room" class="btn btn-outline">By Room</a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!$timetable_exists && $can_generate): ?>
                        <div style="margin-top:20px;padding-top:20px;border-top:1px solid #eee;color:#888;font-size:13px;">
                            <svg width="16" height="16" fill="#888" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            Generate a timetable first, then view it here.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>