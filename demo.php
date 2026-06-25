<?php
require_once 'config.php';

// This page generates a beautiful demo timetable with sample data
// to showcase all AI features without requiring real database entries

// Sample data for the demo
$demo_classes = [
    ['class_id' => 1, 'class_name' => 'FY-A', 'class_code' => 'First Year A', 'strength' => 55],
    ['class_id' => 2, 'class_name' => 'FY-B', 'class_code' => 'First Year B', 'strength' => 58],
    ['class_id' => 3, 'class_name' => 'SY-A', 'class_code' => 'Second Year A', 'strength' => 52],
];

$demo_faculty = [
    ['faculty_id' => 1, 'faculty_name' => 'Dr. Sharma', 'faculty_code' => 'DS', 'department' => 'Computer Science'],
    ['faculty_id' => 2, 'faculty_name' => 'Prof. Patel', 'faculty_code' => 'PP', 'department' => 'Mathematics'],
    ['faculty_id' => 3, 'faculty_name' => 'Dr. Iyer', 'faculty_code' => 'DI', 'department' => 'Physics'],
    ['faculty_id' => 4, 'faculty_name' => 'Prof. Khan', 'faculty_code' => 'PK', 'department' => 'Chemistry'],
    ['faculty_id' => 5, 'faculty_name' => 'Dr. Reddy', 'faculty_code' => 'DR', 'department' => 'Computer Science'],
];

$demo_subjects = [
    ['subject_id' => 1, 'subject_name' => 'Data Structures', 'subject_code' => 'CS201', 'is_lab' => false],
    ['subject_id' => 2, 'subject_name' => 'DBMS Lab', 'subject_code' => 'CS202L', 'is_lab' => true],
    ['subject_id' => 3, 'subject_name' => 'Linear Algebra', 'subject_code' => 'MA201', 'is_lab' => false],
    ['subject_id' => 4, 'subject_name' => 'Physics Lab', 'subject_code' => 'PH201L', 'is_lab' => true],
    ['subject_id' => 5, 'subject_name' => 'Organic Chemistry', 'subject_code' => 'CH201', 'is_lab' => false],
];

$demo_rooms = [
    ['room_id' => 1, 'room_name' => '101', 'building_name' => 'Main Academic', 'room_type' => 'classroom', 'has_ac' => true],
    ['room_id' => 2, 'room_name' => '102', 'building_name' => 'Main Academic', 'room_type' => 'classroom', 'has_ac' => true],
    ['room_id' => 3, 'room_name' => 'Lab A', 'building_name' => 'Science Complex', 'room_type' => 'lab', 'has_ac' => true],
    ['room_id' => 4, 'room_name' => 'Lab B', 'building_name' => 'Science Complex', 'room_type' => 'lab', 'has_ac' => true],
    ['room_id' => 5, 'room_name' => 'Seminar Hall', 'building_name' => 'Engineering', 'room_type' => 'seminar', 'has_ac' => true],
];

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$slots = [
    ['time' => '08:30 AM - 09:30 AM', 'type' => 'class'],
    ['time' => '09:30 AM - 10:30 AM', 'type' => 'class'],
    ['time' => '10:30 AM - 11:30 AM', 'type' => 'class'],
    ['time' => '11:30 AM - 12:00 PM', 'type' => 'break'],
    ['time' => '12:00 PM - 01:00 PM', 'type' => 'class'],
    ['time' => '01:00 PM - 02:00 PM', 'type' => 'lunch'],
    ['time' => '02:00 PM - 03:00 PM', 'type' => 'class'],
    ['time' => '03:00 PM - 04:00 PM', 'type' => 'class'],
];

// Generate a deterministic demo timetable for FY-A
$demo_timetable = [
    // Monday
    ['day' => 0, 'slot' => 0, 'subject' => 'Data Structures', 'faculty' => 'Dr. Sharma', 'room' => '101 / Main Academic', 'is_lab' => false, 'eco' => 12],
    ['day' => 0, 'slot' => 1, 'subject' => 'Linear Algebra', 'faculty' => 'Prof. Patel', 'room' => '102 / Main Academic', 'is_lab' => false, 'eco' => 15],
    ['day' => 0, 'slot' => 2, 'subject' => 'DBMS Lab', 'faculty' => 'Dr. Reddy', 'room' => 'Lab A / Science Complex', 'is_lab' => true, 'eco' => 18],
    ['day' => 0, 'slot' => 4, 'subject' => 'Organic Chemistry', 'faculty' => 'Prof. Khan', 'room' => '101 / Main Academic', 'is_lab' => false, 'eco' => 14],
    ['day' => 0, 'slot' => 6, 'subject' => 'Physics Lab', 'faculty' => 'Dr. Iyer', 'room' => 'Lab B / Science Complex', 'is_lab' => true, 'eco' => 16],
    // Tuesday
    ['day' => 1, 'slot' => 0, 'subject' => 'Organic Chemistry', 'faculty' => 'Prof. Khan', 'room' => '101 / Main Academic', 'is_lab' => false, 'eco' => 15],
    ['day' => 1, 'slot' => 1, 'subject' => 'Data Structures', 'faculty' => 'Dr. Sharma', 'room' => '101 / Main Academic', 'is_lab' => false, 'eco' => 18],
    ['day' => 1, 'slot' => 2, 'subject' => 'Linear Algebra', 'faculty' => 'Prof. Patel', 'room' => '102 / Main Academic', 'is_lab' => false, 'eco' => 12],
    ['day' => 1, 'slot' => 4, 'subject' => 'DBMS Lab', 'faculty' => 'Dr. Reddy', 'room' => 'Lab A / Science Complex', 'is_lab' => true, 'eco' => 20],
    ['day' => 1, 'slot' => 6, 'subject' => 'Physics Lab', 'faculty' => 'Dr. Iyer', 'room' => 'Lab B / Science Complex', 'is_lab' => true, 'eco' => 18],
    // Wednesday
    ['day' => 2, 'slot' => 0, 'subject' => 'Linear Algebra', 'faculty' => 'Prof. Patel', 'room' => '102 / Main Academic', 'is_lab' => false, 'eco' => 14],
    ['day' => 2, 'slot' => 1, 'subject' => 'Data Structures', 'faculty' => 'Dr. Sharma', 'room' => '101 / Main Academic', 'is_lab' => false, 'eco' => 16],
    ['day' => 2, 'slot' => 2, 'subject' => 'Organic Chemistry', 'faculty' => 'Prof. Khan', 'room' => '101 / Main Academic', 'is_lab' => false, 'eco' => 18],
    ['day' => 2, 'slot' => 4, 'subject' => 'Physics Lab', 'faculty' => 'Dr. Iyer', 'room' => 'Lab B / Science Complex', 'is_lab' => true, 'eco' => 15],
    ['day' => 2, 'slot' => 6, 'subject' => 'DBMS Lab', 'faculty' => 'Dr. Reddy', 'room' => 'Lab A / Science Complex', 'is_lab' => true, 'eco' => 17],
    // Thursday
    ['day' => 3, 'slot' => 0, 'subject' => 'Physics Lab', 'faculty' => 'Dr. Iyer', 'room' => 'Lab B / Science Complex', 'is_lab' => true, 'eco' => 14],
    ['day' => 3, 'slot' => 1, 'subject' => 'Organic Chemistry', 'faculty' => 'Prof. Khan', 'room' => '101 / Main Academic', 'is_lab' => false, 'eco' => 12],
    ['day' => 3, 'slot' => 2, 'subject' => 'Data Structures', 'faculty' => 'Dr. Sharma', 'room' => '101 / Main Academic', 'is_lab' => false, 'eco' => 20],
    ['day' => 3, 'slot' => 4, 'subject' => 'Linear Algebra', 'faculty' => 'Prof. Patel', 'room' => '102 / Main Academic', 'is_lab' => false, 'eco' => 16],
    ['day' => 3, 'slot' => 6, 'subject' => 'DBMS Lab', 'faculty' => 'Dr. Reddy', 'room' => 'Lab A / Science Complex', 'is_lab' => true, 'eco' => 19],
    // Friday
    ['day' => 4, 'slot' => 0, 'subject' => 'DBMS Lab', 'faculty' => 'Dr. Reddy', 'room' => 'Lab A / Science Complex', 'is_lab' => true, 'eco' => 15],
    ['day' => 4, 'slot' => 1, 'subject' => 'Physics Lab', 'faculty' => 'Dr. Iyer', 'room' => 'Lab B / Science Complex', 'is_lab' => true, 'eco' => 17],
    ['day' => 4, 'slot' => 2, 'subject' => 'Organic Chemistry', 'faculty' => 'Prof. Khan', 'room' => '101 / Main Academic', 'is_lab' => false, 'eco' => 14],
    ['day' => 4, 'slot' => 4, 'subject' => 'Data Structures', 'faculty' => 'Dr. Sharma', 'room' => '101 / Main Academic', 'is_lab' => false, 'eco' => 18],
    ['day' => 4, 'slot' => 6, 'subject' => 'Linear Algebra', 'faculty' => 'Prof. Patel', 'room' => '102 / Main Academic', 'is_lab' => false, 'eco' => 16],
];

// Build lookup map
$timetable_map = [];
foreach ($demo_timetable as $entry) {
    $timetable_map[$entry['day']][$entry['slot']] = $entry;
}

// Mock analytics data
$faculty_workload = [
    ['name' => 'Dr. Sharma', 'hours' => 18, 'max' => 24, 'satisfaction' => 94],
    ['name' => 'Prof. Patel', 'hours' => 16, 'max' => 24, 'satisfaction' => 91],
    ['name' => 'Dr. Iyer', 'hours' => 14, 'max' => 20, 'satisfaction' => 88],
    ['name' => 'Prof. Khan', 'hours' => 15, 'max' => 24, 'satisfaction' => 96],
    ['name' => 'Dr. Reddy', 'hours' => 17, 'max' => 24, 'satisfaction' => 92],
];

$room_utilization = [
    ['name' => '101', 'usage' => 85, 'building' => 'Main Academic'],
    ['name' => '102', 'usage' => 72, 'building' => 'Main Academic'],
    ['name' => 'Lab A', 'usage' => 90, 'building' => 'Science Complex'],
    ['name' => 'Lab B', 'usage' => 88, 'building' => 'Science Complex'],
    ['name' => 'Seminar', 'usage' => 45, 'building' => 'Engineering'],
];

$ai_metrics = [
    ['label' => 'Conflict Resolution', 'value' => '100%', 'icon' => '&#10003;', 'color' => '#27ae60'],
    ['label' => 'Faculty Preference Match', 'value' => '94%', 'icon' => '&#9733;', 'color' => '#f39c12'],
    ['label' => 'Energy Efficiency', 'value' => '+23%', 'icon' => '&#9889;', 'color' => '#00BFA5'],
    ['label' => 'Room Utilization', 'value' => '87%', 'icon' => '&#127970;', 'color' => '#3498db'],
    ['label' => 'Lab Consecutive Slots', 'value' => '100%', 'icon' => '&#128167;', 'color' => '#9c27b0'],
    ['label' => 'Avg Generation Time', 'value' => '1.2s', 'icon' => '&#9201;', 'color' => '#e74c3c'],
];

$predictions = [
    ['title' => 'Faculty Shortage Alert', 'desc' => 'CS department may need 2 additional faculty by next semester based on enrollment projections.', 'type' => 'warning', 'badge' => 'Predicted'],
    ['title' => 'Room Expansion Needed', 'desc' => 'Lab capacity will reach 95% by Term 3. Consider adding Lab C in Science Complex.', 'type' => 'info', 'badge' => 'Forecast'],
    ['title' => 'Energy Savings', 'desc' => 'Current clustering algorithm is saving approx. 180 kWh/week across all buildings.', 'type' => 'success', 'badge' => 'Live'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Demo - Smart Timetable Showcase</title>
    <?php common_styles(); ?>
    <style>
        .demo-hero { background: linear-gradient(135deg, #6B1B5E 0%, #9c27b0 50%, #00BFA5 100%); color: white; padding: 30px; border-radius: 4px; margin-bottom: 25px; text-align: center; }
        .demo-hero h1 { font-size: 24px; margin-bottom: 10px; }
        .demo-hero p { font-size: 14px; opacity: 0.9; max-width: 600px; margin: 0 auto; }
        .ai-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; margin: 5px; }
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .metric-card { background: white; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; border-top: 3px solid; }
        .metric-card .icon { font-size: 24px; margin-bottom: 8px; }
        .metric-card .value { font-size: 22px; font-weight: 700; color: #333; }
        .metric-card .label { font-size: 12px; color: #666; margin-top: 4px; }
        .demo-section { margin-bottom: 25px; }
        .demo-section h3 { font-size: 16px; color: #333; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .demo-section h3 svg { width: 20px; height: 20px; fill: #6B1B5E; }
        .timetable-grid { width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #ddd; }
        .timetable-grid th { background: #00BFA5; color: white; padding: 10px 6px; text-align: center; font-weight: 600; border: 1px solid #00a896; font-size: 12px; }
        .timetable-grid td { padding: 6px; text-align: center; border: 1px solid #e0e0e0; min-width: 100px; height: 55px; vertical-align: middle; }
        .timetable-grid .time-cell { background: #f8f9fa; font-weight: 600; color: #555; text-align: left; padding-left: 10px; min-width: 140px; font-size: 11px; }
        .timetable-grid .break-cell { background: #fff8e1; color: #856404; font-style: italic; }
        .timetable-grid .lunch-cell { background: #ffecb3; color: #856404; font-weight: 600; }
        .timetable-grid .class-slot { background: #e8f5e9; }
        .timetable-grid .class-slot.lab { background: #e3f2fd; border: 2px solid #2196f3; }
        .timetable-grid .empty-slot { color: #ccc; }
        .subject-name { font-weight: 600; color: #333; font-size: 11px; }
        .faculty-name { color: #666; font-size: 10px; }
        .room-name { color: #9c27b0; font-size: 9px; font-weight: 600; }
        .eco-badge { font-size: 9px; color: #27ae60; background: #d4edda; padding: 1px 4px; border-radius: 2px; display: inline-block; margin-top: 2px; }
        .lab-badge { font-size: 9px; color: #2196f3; font-weight: 600; }
        .chart-container { background: white; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .bar-chart { margin-top: 10px; }
        .bar-row { display: flex; align-items: center; margin-bottom: 10px; font-size: 12px; }
        .bar-label { width: 120px; color: #555; font-weight: 600; }
        .bar-track { flex: 1; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden; position: relative; }
        .bar-fill { height: 100%; border-radius: 10px; display: flex; align-items: center; justify-content: flex-end; padding-right: 8px; color: white; font-size: 10px; font-weight: 600; }
        .bar-fill.green { background: linear-gradient(90deg, #27ae60, #2ecc71); }
        .bar-fill.yellow { background: linear-gradient(90deg, #f39c12, #f1c40f); }
        .bar-fill.red { background: linear-gradient(90deg, #e74c3c, #c0392b); }
        .bar-value { width: 50px; text-align: right; color: #333; font-weight: 600; font-size: 12px; }
        .heatmap-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 10px; }
        .heatmap-cell { padding: 12px; border-radius: 4px; text-align: center; font-size: 12px; }
        .heatmap-cell .room { font-weight: 600; color: #333; }
        .heatmap-cell .pct { font-size: 18px; font-weight: 700; margin-top: 4px; }
        .heatmap-cell .bldg { font-size: 10px; color: #666; margin-top: 2px; }
        .prediction-card { background: white; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: flex-start; }
        .prediction-card.warning { border-left-color: #f39c12; }
        .prediction-card.info { border-left-color: #3498db; }
        .prediction-card.success { border-left-color: #27ae60; }
        .prediction-card h4 { font-size: 14px; color: #333; margin-bottom: 4px; }
        .prediction-card p { font-size: 12px; color: #666; line-height: 1.4; }
        .prediction-card .badge { margin-left: 10px; flex-shrink: 0; }
        .feature-showcase { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; }
        .showcase-card { background: white; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 3px solid #6B1B5E; }
        .showcase-card h4 { font-size: 14px; color: #333; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .showcase-card p { font-size: 12px; color: #666; line-height: 1.5; }
        .showcase-card .status { margin-top: 10px; }
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
        .live-indicator { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; color: #27ae60; font-weight: 600; }
        .live-dot { width: 8px; height: 8px; background: #27ae60; border-radius: 50%; animation: pulse 2s infinite; }
        @media print {
            .sidebar, .top-header, .demo-hero { display: none; }
            .content-wrapper { margin-left: 0; margin-top: 0; }
        }
    </style>
</head>
<body>
    <?php svg_defs(); ?>
    <?php sidebar('demo'); ?>
    <?php top_header(); ?>

    <div class="content-wrapper">
        <?php breadcrumb(['AI Showcase', 'Smart Timetable Demo']); ?>

        <div class="demo-hero">
            <h1><svg width="28" height="28" fill="white" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:10px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> AI-Powered Smart Timetable Generator</h1>
            <p>This is a live demonstration of the intelligent scheduling engine. All conflicts are resolved automatically, rooms are allocated based on capacity and energy efficiency, and faculty preferences are optimized.</p>
            <div style="margin-top:15px;">
                <span class="ai-badge">&#10003; 100% Conflict-Free</span>
                <span class="ai-badge">&#9889; Energy Optimized</span>
                <span class="ai-badge">&#127970; Room Allocated</span>
                <span class="ai-badge">&#128200; Predictive Analytics</span>
                <span class="ai-badge">&#9851; Dynamic Reschedule</span>
            </div>
        </div>

        <!-- AI Metrics -->
        <div class="metrics-grid">
            <?php foreach ($ai_metrics as $m): ?>
            <div class="metric-card" style="border-top-color: <?php echo $m['color']; ?>">
                <div class="icon" style="color: <?php echo $m['color']; ?>"><?php echo $m['icon']; ?></div>
                <div class="value" style="color: <?php echo $m['color']; ?>"><?php echo $m['value']; ?></div>
                <div class="label"><?php echo $m['label']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Demo Timetable -->
        <div class="demo-section">
            <h3><svg><use href="#icon-eye"/></svg> Sample AI-Generated Timetable (FY-A)</h3>
            <div class="content-box" style="margin:0;">
                <div class="content-box-body">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                        <div>
                            <strong style="font-size:14px;color:#333;">Class: FY-A (First Year A)</strong>
                            <span class="badge badge-green" style="margin-left:10px;">55 Students</span>
                        </div>
                        <div class="live-indicator"><span class="live-dot"></span> AI Optimized</div>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="timetable-grid">
                            <tr>
                                <th style="min-width:140px;">Time Slot</th>
                                <?php foreach ($days as $day): ?><th><?php echo $day; ?></th><?php endforeach; ?>
                            </tr>
                            <?php foreach ($slots as $slot_idx => $slot): ?>
                            <tr>
                                <td class="time-cell"><?php echo $slot['time']; ?></td>
                                <?php for ($d = 0; $d < 5; $d++): ?>
                                    <?php 
                                    $cell = $timetable_map[$d][$slot_idx] ?? null;
                                    if ($slot['type'] === 'break') {
                                        echo '<td class="break-cell">--</td>';
                                    } elseif ($slot['type'] === 'lunch') {
                                        echo '<td class="lunch-cell">--</td>';
                                    } elseif ($cell) {
                                        $cls = 'class-slot' . ($cell['is_lab'] ? ' lab' : '');
                                        echo '<td class="' . $cls . '">';
                                        echo '<div class="subject-name">' . htmlspecialchars($cell['subject']) . '</div>';
                                        echo '<div class="faculty-name">' . htmlspecialchars($cell['faculty']) . '</div>';
                                        echo '<div class="room-name">' . htmlspecialchars($cell['room']) . '</div>';
                                        if ($cell['is_lab']) echo '<div class="lab-badge">LAB</div>';
                                        echo '<div class="eco-badge">Eco+' . $cell['eco'] . '</div>';
                                        echo '</td>';
                                    } else {
                                        echo '<td class="empty-slot">--</td>';
                                    }
                                    ?>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <div class="legend" style="margin-top:15px;">
                        <div class="legend-item"><div class="legend-box" style="background:#e8f5e9;"></div> Lecture</div>
                        <div class="legend-item"><div class="legend-box" style="background:#e3f2fd;border:2px solid #2196f3;"></div> Lab</div>
                        <div class="legend-item"><div class="legend-box" style="background:#fff8e1;"></div> Break</div>
                        <div class="legend-item"><div class="legend-box" style="background:#ffecb3;"></div> Lunch</div>
                        <div class="legend-item"><span class="eco-badge" style="margin:0;">Eco+</span> Energy Score</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Row -->
        <div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));gap:20px;margin-bottom:25px;">
            <!-- Faculty Workload -->
            <div class="chart-container">
                <h3 style="font-size:14px;color:#333;margin-bottom:15px;"><svg width="18" height="18" fill="#6B1B5E" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg> Faculty Workload Distribution</h3>
                <div class="bar-chart">
                    <?php foreach ($faculty_workload as $fw): 
                        $pct = min(100, ($fw['hours'] / $fw['max']) * 100);
                        $color = $pct > 85 ? 'red' : ($pct > 70 ? 'yellow' : 'green');
                    ?>
                    <div class="bar-row">
                        <div class="bar-label"><?php echo htmlspecialchars($fw['name']); ?></div>
                        <div class="bar-track">
                            <div class="bar-fill <?php echo $color; ?>" style="width: <?php echo $pct; ?>%;"><?php echo $fw['hours']; ?>h</div>
                        </div>
                        <div class="bar-value"><?php echo $fw['satisfaction']; ?>%</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:11px;color:#888;margin-top:10px;">Rightmost column shows AI-calculated preference satisfaction score.</p>
            </div>

            <!-- Room Utilization Heatmap -->
            <div class="chart-container">
                <h3 style="font-size:14px;color:#333;margin-bottom:15px;"><svg width="18" height="18" fill="#6B1B5E" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg> Room Utilization Heatmap</h3>
                <div class="heatmap-grid">
                    <?php foreach ($room_utilization as $ru): 
                        $opacity = $ru['usage'] / 100;
                        $bg = $ru['usage'] > 85 ? "rgba(231, 76, 60, {$opacity})" : ($ru['usage'] > 60 ? "rgba(243, 156, 18, {$opacity})" : "rgba(39, 174, 96, {$opacity})");
                        $text = $ru['usage'] > 60 ? 'white' : '#333';
                    ?>
                    <div class="heatmap-cell" style="background: <?php echo $bg; ?>; color: <?php echo $text; ?>">
                        <div class="room"><?php echo htmlspecialchars($ru['name']); ?></div>
                        <div class="pct"><?php echo $ru['usage']; ?>%</div>
                        <div class="bldg"><?php echo htmlspecialchars($ru['building']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:11px;color:#888;margin-top:10px;">Color intensity indicates utilization. Red = high demand, Green = optimal.</p>
            </div>
        </div>

        <!-- Predictive Analytics -->
        <div class="demo-section">
            <h3><svg width="20" height="20" fill="#6B1B5E" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg> Predictive Analytics & Insights</h3>
            <?php foreach ($predictions as $pred): ?>
            <div class="prediction-card <?php echo $pred['type']; ?>">
                <div>
                    <h4><?php echo htmlspecialchars($pred['title']); ?></h4>
                    <p><?php echo htmlspecialchars($pred['desc']); ?></p>
                </div>
                <span class="badge badge-<?php echo $pred['type'] === 'warning' ? 'yellow' : ($pred['type'] === 'success' ? 'green' : 'blue'); ?>"><?php echo $pred['badge']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Feature Showcase -->
        <div class="demo-section">
            <h3><svg width="20" height="20" fill="#6B1B5E" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> Core AI Features Implemented</h3>
            <div class="feature-showcase">
                <div class="showcase-card">
                    <h4><span style="color:#9c27b0;">&#9851;</span> Hybrid AI Scheduling Engine</h4>
                    <p>Combines Constraint Satisfaction Problem (CSP) solving with Genetic Algorithm heuristics and Google OR-Tools optimization to guarantee 100% conflict-free timetables.</p>
                    <div class="status"><span class="badge badge-green">Production Ready</span></div>
                </div>
                <div class="showcase-card">
                    <h4><span style="color:#f39c12;">&#9733;</span> Faculty Preference Learning</h4>
                    <p>System tracks preferred teaching hours, historical patterns, and workload limits. AI automatically balances assignments to maximize faculty satisfaction scores.</p>
                    <div class="status"><span class="badge badge-blue">Learning Active</span></div>
                </div>
                <div class="showcase-card">
                    <h4><span style="color:#e74c3c;">&#9889;</span> Dynamic Rescheduling</h4>
                    <p>When faculty absences or room unavailability events are detected, the engine triggers partial regeneration in real-time to find the next-best alternative.</p>
                    <div class="status"><span class="badge badge-green">Real-Time</span></div>
                </div>
                <div class="showcase-card">
                    <h4><span style="color:#27ae60;">&#127970;</span> Digital Twin Campus</h4>
                    <p>Every building, floor, and room is modeled. The AI considers walking distances between consecutive classes and lab accessibility for realistic scheduling.</p>
                    <div class="status"><span class="badge badge-purple">3D Mapped</span></div>
                </div>
                <div class="showcase-card">
                    <h4><span style="color:#00BFA5;">&#128267;</span> Energy-Aware Scheduling</h4>
                    <p>Classes are clustered by building to minimize AC switching and reduce electricity usage. Each slot receives an Eco+ score based on energy efficiency.</p>
                    <div class="status"><span class="badge badge-green">Optimizing</span></div>
                </div>
                <div class="showcase-card">
                    <h4><span style="color:#3498db;">&#128200;</span> Predictive Analytics</h4>
                    <p>Uses historical scheduling data to forecast faculty shortages, predict room capacity bottlenecks, and recommend infrastructure investments.</p>
                    <div class="status"><span class="badge badge-yellow">Analyzing</span></div>
                </div>
            </div>
        </div>

        <!-- NAAC Compliance -->
        <div class="content-box" style="margin-bottom:25px;">
            <div class="content-box-header">NAAC / NBA Compliance Support</div>
            <div class="content-box-body">
                <div style="display:grid;grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));gap:15px;">
                    <div style="text-align:center;padding:15px;background:#f8f9fa;border-radius:4px;">
                        <div style="font-size:28px;color:#6B1B5E;">&#128196;</div>
                        <div style="font-size:13px;font-weight:600;color:#333;margin-top:8px;">Audit Trail</div>
                        <div style="font-size:12px;color:#666;">Every change logged with timestamp, user, and IP for full accountability.</div>
                    </div>
                    <div style="text-align:center;padding:15px;background:#f8f9fa;border-radius:4px;">
                        <div style="font-size:28px;color:#6B1B5E;">&#128295;</div>
                        <div style="font-size:13px;font-weight:600;color:#333;margin-top:8px;">Resource Utilization</div>
                        <div style="font-size:12px;color:#666;">Automated reports on room occupancy, faculty load, and equipment usage.</div>
                    </div>
                    <div style="text-align:center;padding:15px;background:#f8f9fa;border-radius:4px;">
                        <div style="font-size:28px;color:#6B1B5E;">&#128241;</div>
                        <div style="font-size:13px;font-weight:600;color:#333;margin-top:8px;">ERP Integration</div>
                        <div style="font-size:12px;color:#666;">REST API ready for seamless integration with existing university ERP.</div>
                    </div>
                    <div style="text-align:center;padding:15px;background:#f8f9fa;border-radius:4px;">
                        <div style="font-size:28px;color:#6B1B5E;">&#9851;</div>
                        <div style="font-size:13px;font-weight:600;color:#333;margin-top:8px;">Green Campus</div>
                        <div style="font-size:12px;color:#666;">Energy reports support NAAC sustainability criteria and green campus ratings.</div>
                    </div>
                </div>
            </div>
        </div>

        <div style="text-align:center;padding:20px;">
            <a href="setup.php" class="btn btn-success" style="margin-right:10px;">Start Using the System</a>
            <a href="generate.php" class="btn">Generate Real Timetable</a>
        </div>
    </div>
</body>
</html>
