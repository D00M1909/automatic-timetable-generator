<?php
require_once 'includes/config.php';

$total_sessions = db_get_row($conn, "SELECT COUNT(*) as cnt FROM timetable")['cnt'] ?? 0;
$total_labs = db_get_row($conn, "SELECT COUNT(*) as cnt FROM timetable WHERE is_lab=1")['cnt'] ?? 0;
$total_classes = db_get_row($conn, "SELECT COUNT(*) as cnt FROM classes")['cnt'] ?? 0;
$timetable_exists = $total_sessions > 0;

$constraint_violations = 0;
if ($timetable_exists) {
    $violations = db_get_row($conn, "SELECT COUNT(*) as cnt FROM timetable t
        JOIN time_slots ts ON t.slot_id = ts.slot_id
        JOIN subjects s ON t.subject_id = s.subject_id
        JOIN classes c ON t.class_id = c.class_id
        JOIN year_working_days y ON c.year_of_study = y.year_of_study AND t.day_id = y.day_id
        WHERE ts.slot_number = (SELECT MAX(slot_number) FROM time_slots WHERE slot_type='class') AND s.is_minor = 0");
    $constraint_violations = $violations ? $violations['cnt'] : 0;
}

$features = [
    ['icon-star', 'Faculty preferences', 'Preferred/neutral/avoid slots per faculty member weight the placement score.'],
    ['icon-building', 'Building continuity', 'Sessions for a class stay in the same building where possible, to cut walking between rooms.'],
    ['icon-calendar', 'Minor-subject slots', 'The last slot of the day is reserved for minor subjects on each year\'s designated days.'],
    ['icon-refresh', 'Constraint ordering', 'The hardest-to-place classes (Third Year, then Second) are scheduled first.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How It Works - Smart Timetable</title>
    <?php common_styles(); ?>
    <style>
        .demo-hero { background: linear-gradient(135deg, #6B1B5E 0%, #8e2a7c 100%); border-radius: 12px; padding: 32px; color: white; margin-bottom: 24px; }
        .demo-hero h1 { font-size: 24px; font-weight: 700; margin-bottom: 10px; }
        .demo-hero p { font-size: 14px; opacity: 0.9; max-width: 700px; line-height: 1.6; }
        .demo-hero .stat-row { display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap; }
        .demo-hero .stat { background: rgba(255,255,255,0.15); padding: 8px 16px; border-radius: 8px; font-size: 13px; }
        .demo-hero .stat strong { font-size: 18px; display: block; }

        .feature-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .feature-item { background: white; border-radius: 8px; padding: 16px; border: 1px solid #eee; }
        .feature-item svg { width: 22px; height: 22px; fill: #6B1B5E; margin-bottom: 8px; }
        .feature-item h5 { font-size: 13px; color: #333; margin-bottom: 4px; }
        .feature-item p { font-size: 12px; color: #888; line-height: 1.5; }
    </style>
</head>
<body>
    <?php svg_defs(); ?>
    <?php sidebar('demo'); ?>
    <?php top_header(); ?>

    <div class="content-wrapper">
        <?php breadcrumb(['How It Works']); ?>

        <div class="demo-hero">
            <h1>How the Timetable Engine Works</h1>
            <p>A scoring-based scheduler places every class session slot by slot, avoiding room and faculty clashes and applying the constraints below.</p>
            <?php if ($timetable_exists): ?>
            <div class="stat-row">
                <div class="stat"><strong><?php echo $total_classes; ?></strong>Classes</div>
                <div class="stat"><strong><?php echo $total_sessions; ?></strong>Sessions placed</div>
                <div class="stat"><strong><?php echo $total_labs; ?></strong>Lab sessions</div>
                <div class="stat"><strong style="color:<?php echo $constraint_violations > 0 ? '#ffcdd2' : '#c8e6c9'; ?>"><?php echo $constraint_violations; ?></strong>Minor-slot violations</div>
            </div>
            <?php endif; ?>
        </div>

        <div class="feature-list">
            <?php foreach ($features as [$icon, $title, $desc]): ?>
                <div class="feature-item">
                    <svg><use href="#<?php echo $icon; ?>"/></svg>
                    <h5><?php echo htmlspecialchars($title); ?></h5>
                    <p><?php echo htmlspecialchars($desc); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <p style="margin-top:24px;">
            <?php if ($timetable_exists): ?>
                <a href="view.php?source=generated" class="btn" style="background:#6B1B5E;">View the generated timetable</a>
            <?php else: ?>
                <a href="generate.php" class="btn">Generate a timetable to see it in action</a>
            <?php endif; ?>
        </p>
    </div>
</body>
</html>
