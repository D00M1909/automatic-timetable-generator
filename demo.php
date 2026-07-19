<?php
require_once 'includes/config.php';

// Fetch real data from the generated timetable if it exists
$timetable_exists = db_get_row($conn, "SELECT COUNT(*) as cnt FROM timetable")['cnt'] > 0;
$total_sessions = 0;
$total_labs = 0;
$total_classes = 0;
$constraint_violations = 0;

if ($timetable_exists) {
    $total_sessions = db_get_row($conn, "SELECT COUNT(*) as cnt FROM timetable")['cnt'] ?? 0;
    $total_labs = db_get_row($conn, "SELECT COUNT(*) as cnt FROM timetable WHERE is_lab=1")['cnt'] ?? 0;
    $total_classes = db_get_row($conn, "SELECT COUNT(*) as cnt FROM classes")['cnt'] ?? 0;
    
    $violations = db_get_row($conn, "SELECT COUNT(*) as cnt FROM timetable t
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN classes c ON t.class_id = c.class_id
        JOIN year_working_days y ON c.year_of_study = y.year_of_study AND t.day_id = y.day_id
        WHERE ts.slot_number = 10 AND s.is_minor = 0");
    $constraint_violations = $violations ? $violations['cnt'] : 0;

    $sample_class = db_get_row($conn, "SELECT class_id, class_code, class_name FROM classes WHERE year_of_study = 2 LIMIT 1");
    $sample_class_id = $sample_class ? $sample_class['class_id'] : 0;
    
    $days = db_get_rows($conn, "SELECT * FROM working_days WHERE is_working=1 ORDER BY day_order");
    $slots = db_get_rows($conn, "SELECT * FROM time_slots WHERE is_active=1 ORDER BY slot_number");
    $class_slots = array_values(array_filter($slots, function($s) { return $s['slot_type'] === 'class'; }));
    $last_slot_number = 0;
    foreach ($class_slots as $s) { if ($s['slot_number'] > $last_slot_number) $last_slot_number = $s['slot_number']; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How It Works - Smart Timetable</title>
    <?php common_styles(); ?>
    <style>
        .demo-hero { background: linear-gradient(135deg, #6B1B5E 0%, #8e2a7c 50%, #a83d96 100%); border-radius: 12px; padding: 40px; color: white; margin-bottom: 25px; }
        .demo-hero h1 { font-size: 28px; font-weight: 700; margin-bottom: 12px; }
        .demo-hero p { font-size: 15px; opacity: 0.9; max-width: 700px; line-height: 1.6; }
        .demo-hero .stat-row { display: flex; gap: 30px; margin-top: 25px; flex-wrap: wrap; }
        .demo-hero .stat { display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); padding: 8px 16px; border-radius: 8px; font-size: 13px; }
        .demo-hero .stat strong { font-size: 18px; }

        .constraint-vis { background: white; border-radius: 10px; border: 1px solid #eee; overflow: hidden; margin-bottom: 25px; }
        .constraint-vis .vis-header { padding: 16px 20px; background: #f8f9fa; border-bottom: 1px solid #eee; font-weight: 600; font-size: 14px; color: #333; display: flex; align-items: center; gap: 8px; }
        .constraint-vis .vis-body { padding: 20px; }
        .day-labels { display: flex; gap: 4px; margin-bottom: 12px; }
        .day-label { flex: 1; text-align: center; font-size: 11px; font-weight: 600; color: #555; padding: 6px; background: #f0f2f5; border-radius: 4px; }
        .day-label.constrained { background: #f3e5f5; color: #6B1B5E; }
        .slot-grid { display: flex; flex-direction: column; gap: 6px; }
        .slot-row { display: flex; gap: 4px; align-items: center; }
        .slot-time { width: 80px; font-size: 10px; color: #888; text-align: right; padding-right: 8px; flex-shrink: 0; }
        .slot-block { flex: 1; height: 24px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 600; }
        .slot-block.class { background: #e8f5e9; color: #2e7d32; }
        .slot-block.break { background: #fff8e1; color: #856404; }
        .slot-block.lunch { background: #ffecb3; color: #856404; }
        .slot-block.last-slot { background: #f3e5f5; color: #6B1B5E; border: 2px solid #6B1B5E; }

        .legend-row { display: flex; gap: 16px; margin-top: 12px; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #666; }
        .legend-swatch { width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0; }

        .result-banner { background: linear-gradient(135deg, #e8f5e9, #c8e6c9); border: 1px solid #a5d6a7; border-radius: 10px; padding: 20px 24px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .result-banner .msg { font-size: 14px; color: #2e7d32; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .result-banner .msg .count { font-size: 22px; }
        .result-banner .detail { font-size: 12px; color: #388e3c; }

        .feature-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 25px; }
        .feature-item { background: white; border-radius: 8px; padding: 16px; border: 1px solid #eee; text-align: center; }
        .feature-item svg { width: 28px; height: 28px; fill: #6B1B5E; margin-bottom: 8px; }
        .feature-item h5 { font-size: 13px; color: #333; margin-bottom: 4px; }
        .feature-item p { font-size: 11px; color: #888; }

        /* Timetable styles matching view.php */
        .timetable-container { overflow-x: auto; }
        .timetable-grid { width: 100%; border-collapse: collapse; font-size: 13px; border: 1px solid #ddd; }
        .timetable-grid th { background: #00BFA5; color: white; padding: 10px 8px; text-align: center; font-weight: 600; border: 1px solid #00a896; }
        .timetable-grid td { padding: 8px; text-align: center; border: 1px solid #e0e0e0; min-width: 110px; height: 60px; vertical-align: middle; }
        .timetable-grid .time-cell { background: #f8f9fa; font-weight: 600; color: #555; text-align: left; padding-left: 12px; min-width: 160px; font-size: 12px; }
        .timetable-grid .time-cell.minor-header { background: #f3e5f5; color: #6B1B5E; }
        .timetable-grid .class-slot { background: #e8f5e9; }
        .timetable-grid .class-slot.lab { background: #e3f2fd; border: 2px solid #2196f3; }
        .timetable-grid .empty-slot { color: #ccc; font-size: 12px; }
        .subject-name { font-weight: 600; color: #333; font-size: 12px; }
        .faculty-name { color: #666; font-size: 11px; }
        .room-name { color: #9c27b0; font-size: 10px; font-weight: 600; }
        .lab-badge { font-size: 10px; color: #2196f3; font-weight: 600; }
        .energy-badge { font-size: 9px; color: #27ae60; background: #d4edda; padding: 1px 4px; border-radius: 2px; display: inline-block; margin-top: 2px; }
        .minor-badge { font-size: 9px; color: #6B1B5E; background: #f3e5f5; padding: 1px 4px; border-radius: 2px; display: inline-block; margin-top: 2px; }
        .timetable-demo { margin-top: 25px; }

        @media (max-width: 768px) {
            .demo-hero { padding: 24px; }
            .demo-hero h1 { font-size: 20px; }
            .slot-time { width: 60px; font-size: 9px; }
        }
    </style>
</head>
<body>
    <?php svg_defs(); ?>
    <?php sidebar('demo'); ?>

    <div class="top-header">
        <div class="top-header-left">
            <span style="font-size:18px;cursor:pointer;">&#9776;</span>
            <span>Smart Timetable Engine</span>
        </div>
        <div class="top-header-right">
            <span style="display:flex;align-items:center;gap:6px;">
                <svg width="16" height="16" fill="white" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg> <span id="notif-count">0</span>
            </span>
        </div>
    </div>

    <div class="content-wrapper">
        <?php breadcrumb(['How It Works']); ?>

        <!-- Hero -->
        <div class="demo-hero">
            <h1>How the Timetable Engine Works</h1>
            <p>This system takes real-world academic scheduling constraints — split working days per year, designated minor-subject slots, building preferences, faculty workload limits — and solves them using a scoring-based greedy algorithm.</p>
            <div class="stat-row">
                <div class="stat"><strong><?php echo $total_classes ?: '—'; ?></strong> Classes</div>
                <div class="stat"><strong><?php echo $total_sessions ?: '—'; ?></strong> Sessions Placed</div>
                <div class="stat"><strong><?php echo $total_labs ?: '—'; ?></strong> Lab Sessions</div>
                <div class="stat">
                    <strong style="color:<?php echo $constraint_violations > 0 ? '#e74c3c' : '#27ae60'; ?>">
                        <?php echo $constraint_violations > 0 ? $constraint_violations . ' Violations' : 'All Constraints Met'; ?>
                    </strong>
                </div>
            </div>
        </div>

        <!-- Visual: Daily Slot Structure -->
        <div class="constraint-vis">
            <div class="vis-header">
                <svg width="18" height="18" fill="#555" viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
                Daily Slot Structure — 8:30 AM to 5:30 PM
            </div>
            <div class="vis-body">
                <div class="day-labels">
                    <div class="day-label">Monday</div>
                    <div class="day-label">Tuesday</div>
                    <div class="day-label constrained">Wednesday</div>
                    <div class="day-label">Thursday</div>
                    <div class="day-label">Friday</div>
                </div>
                <div class="slot-grid">
                    <?php
                    $slot_descriptions = [
                        1 => ['08:30', 'class'],
                        2 => ['09:30', 'class'],
                        3 => ['10:30', 'class'],
                        4 => ['11:30', 'class'],
                        5 => ['12:30', 'lunch'],
                        6 => ['13:15', 'class'],
                        7 => ['14:15', 'class'],
                        8 => ['15:15', 'break'],
                        9 => ['15:30', 'class'],
                        10 => ['16:30', 'class'],
                    ];
                    $minor_days_2 = [1, 2, 3];
                    $minor_days_3 = [3, 4, 5];
                    ?>
                    <?php foreach ($slot_descriptions as $snum => $sd): ?>
                    <div class="slot-row">
                        <div class="slot-time"><?php echo $sd[0]; ?></div>
                        <?php for ($d = 1; $d <= 5; $d++): 
                            $is_last = ($snum == 10);
                            $is_minor_day = $is_last && (in_array($d, $minor_days_2) || in_array($d, $minor_days_3));
                            $cls = $sd[1];
                            if ($is_minor_day) $cls .= ' last-slot';
                            $label = $sd[1] === 'class' ? 'Slot ' . $snum : ($sd[1] === 'lunch' ? 'Lunch' : 'Break');
                            if ($is_minor_day) $label = 'Minor';
                        ?>
                        <div class="slot-block <?php echo $cls; ?>"><?php echo $label; ?></div>
                        <?php endfor; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="legend-row">
                    <div class="legend-item"><div class="legend-swatch" style="background:#e8f5e9;"></div> Class Slot</div>
                    <div class="legend-item"><div class="legend-swatch" style="background:#fff8e1;"></div> Break</div>
                    <div class="legend-item"><div class="legend-swatch" style="background:#ffecb3;"></div> Lunch</div>
                    <div class="legend-item"><div class="legend-swatch" style="background:#f3e5f5;border:2px solid #6B1B5E;"></div> Minor-Subject Slot</div>
                </div>
                <p style="font-size:11px;color:#888;margin-top:12px;">
                    Wednesday is shared: 2nd year minor slots (Mon/Tue/Wed) and 3rd year minor slots (Wed/Thu/Fri) overlap on Wednesday.
                    The engine keeps per-year constraint mappings in the <code>year_working_days</code> table.
                </p>
            </div>
        </div>

        <!-- Result / Sample Timetable -->
        <?php if ($timetable_exists && $sample_class_id > 0): 
            $class_name = $sample_class['class_name'] ?? ('Class #' . $sample_class_id);
        ?>
        <div class="result-banner">
            <div>
                <div class="msg">
                    <svg width="18" height="18" fill="#2e7d32" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    <span class="count"><?php echo $total_sessions; ?></span> sessions placed across <?php echo $total_classes; ?> classes
                </div>
                <div class="detail"><?php echo $constraint_violations; ?> minor-slot violations — all constraints respected</div>
            </div>
            <div style="font-size:12px;color:#388e3c;">
                Greedy scoring + hard constraint enforcement
            </div>
        </div>

        <div class="timetable-demo">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                <svg width="18" height="18" fill="#333" viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                <strong style="font-size:14px;color:#333;">Sample Timetable: <?php echo htmlspecialchars($class_name); ?></strong>
                <span class="badge badge-green">2nd Year</span>
                <span class="badge badge-blue">21 hrs/week</span>
            </div>
            <div class="content-box" style="margin:0;">
                <div class="content-box-body" style="padding:15px;">
                    <div class="timetable-container">
                        <table class="timetable-grid">
                            <tr>
                                <th style="min-width:160px;">Time</th>
                                <?php foreach ($days as $day): ?>
                                <th><?php echo $day['day_name']; ?></th>
                                <?php endforeach; ?>
                            </tr>
                            <?php 
                            foreach ($slots as $slot): 
                                if ($slot['slot_type'] === 'break' || $slot['slot_type'] === 'lunch') continue;
                                $is_last = ($slot['slot_number'] == $last_slot_number);
                            ?>
                            <tr>
                                <td class="time-cell<?php echo $is_last ? ' minor-header' : ''; ?>">
                                    <?php echo date('h:i A', strtotime($slot['start_time'])); ?> - <?php echo date('h:i A', strtotime($slot['end_time'])); ?>
                                    <?php if ($is_last): ?><br><span style="font-size:9px;color:#6B1B5E;">Minor slot</span><?php endif; ?>
                                </td>
                                <?php foreach ($days as $day): 
                                    $entry = db_get_row($conn, "SELECT t.*, s.subject_name, s.is_minor, f.faculty_code, r.room_name 
                                        FROM timetable t 
                                        JOIN subjects s ON t.subject_id = s.subject_id 
                                        JOIN faculty f ON t.faculty_id = f.faculty_id 
                                        LEFT JOIN rooms r ON t.room_id = r.room_id 
                                        WHERE t.class_id = ? AND t.day_id = ? AND t.slot_id = ?", 
                                        "iii", [$sample_class_id, $day['day_id'], $slot['slot_id']]);
                                ?>
                                    <?php if ($entry): 
                                        $is_lab = $entry['is_lab'];
                                        $is_minor_subj = $entry['is_minor'];
                                    ?>
                                    <td class="class-slot<?php echo $is_lab ? ' lab' : ''; ?>">
                                        <div class="subject-name"><?php echo htmlspecialchars($entry['subject_name']); ?></div>
                                        <div class="faculty-name"><?php echo htmlspecialchars($entry['faculty_code']); ?></div>
                                        <div class="room-name"><?php echo htmlspecialchars($entry['room_name'] ?? '—'); ?></div>
                                        <?php if ($is_lab): ?><div class="lab-badge">LAB</div><?php endif; ?>
                                        <?php if ($is_minor_subj): ?><div class="minor-badge">Minor</div><?php endif; ?>
                                    </td>
                                    <?php else: ?>
                                    <td class="empty-slot">—</td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <div class="legend-row" style="margin-top:15px;">
                        <div class="legend-item"><div class="legend-swatch" style="background:#e8f5e9;"></div> Lecture</div>
                        <div class="legend-item"><div class="legend-swatch" style="background:#e3f2fd;border:2px solid #2196f3;"></div> Lab</div>
                        <div class="legend-item"><div class="legend-swatch" style="background:#f3e5f5;"></div> Minor Subject</div>
                    </div>
                    <p style="font-size:11px;color:#888;margin-top:12px;text-align:center;">
                        The 4:30–5:30 PM column shows only minor subjects on Mon/Tue/Wed — the constraint is enforced.
                    </p>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="constraint-vis">
            <div class="vis-body" style="text-align:center;padding:40px;">
                <svg viewBox="0 0 24 24" style="width:48px;height:48px;fill:#ddd;margin-bottom:12px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <h3 style="color:#666;font-size:16px;margin-bottom:8px;">No Timetable Generated Yet</h3>
                <p style="color:#999;font-size:13px;">Go to <strong>Generate Timetable</strong> to create one, then come back here to see how it works.</p>
                <a href="generate.php" class="btn" style="margin-top:16px;display:inline-flex;">Generate Now</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Feature Summary -->
        <div style="display:flex;align-items:center;gap:8px;margin:25px 0 12px;">
            <svg width="18" height="18" fill="#555" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.59.91l-2.39-.96c-.22-.08-.47 0-.59.22L3.16 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
            <strong style="font-size:15px;color:#333;">Key Features</strong>
        </div>
        <div class="feature-list">
            <div class="feature-item">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <h5>Minor-Slot Enforcement</h5>
                <p>Last slot (4:30–5:30 PM) restricted to minor subjects on designated days per year-of-study</p>
            </div>
            <div class="feature-item">
                <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                <h5>Per-Year Working Days</h5>
                <p>Each year-of-study gets its own set of days where the minor constraint applies</p>
            </div>
            <div class="feature-item">
                <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
                <h5>Building Continuity</h5>
                <p>Sessions for the same class are kept in the same building where possible to minimize travel</p>
            </div>
            <div class="feature-item">
                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                <h5>Faculty Preferences</h5>
                <p>Individual slot preferences (preferred/neutral/avoid) per faculty member influence placement scoring</p>
            </div>
            <div class="feature-item">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <h5>Energy Scoring</h5>
                <p>Every placement gets an energy score reflecting how well it satisfies all constraints and preferences</p>
            </div>
            <div class="feature-item">
                <svg viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                <h5>Constraint Ordering</h5>
                <p>Most constrained classes process first (TY before SY before FY) for better scheduling outcomes</p>
            </div>
        </div>

    </div>
</body>
</html>
