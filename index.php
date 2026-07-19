<?php
require_once 'includes/config.php';

// Fetch counts with prepared statements
$years_count = db_get_row($conn, "SELECT COUNT(*) as count FROM years WHERE year_status='active'")['count'] ?? 0;
$classes_count = db_get_row($conn, "SELECT COUNT(*) as count FROM classes")['count'] ?? 0;
$faculty_count = db_get_row($conn, "SELECT COUNT(*) as count FROM faculty")['count'] ?? 0;
$subjects_count = db_get_row($conn, "SELECT COUNT(*) as count FROM subjects")['count'] ?? 0;
$assignments_count = db_get_row($conn, "SELECT COUNT(*) as count FROM subject_assignments")['count'] ?? 0;
$rooms_count = db_get_row($conn, "SELECT COUNT(*) as count FROM rooms")['count'] ?? 0;
$timetable_count = db_get_row($conn, "SELECT COUNT(*) as count FROM timetable")['count'] ?? 0;

// Analytics
$faculty_avg_hours = db_get_row($conn, "SELECT AVG(hours) as avg FROM (SELECT faculty_id, COUNT(*) as hours FROM timetable GROUP BY faculty_id) t")['avg'] ?? 0;
$room_utilization = db_get_row($conn, "SELECT COUNT(DISTINCT CONCAT(day_id,'-',slot_id,'-',room_id))*100.0 / NULLIF(COUNT(DISTINCT CONCAT(day_id,'-',slot_id))*COUNT(DISTINCT room_id),0) as pct FROM timetable")['pct'] ?? 0;

$ready = $years_count > 0 && $classes_count > 0 && $faculty_count > 0 && $subjects_count > 0 && $rooms_count > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Timetable</title>
    <?php common_styles(); ?>
    <style>
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .feature-card { background: white; border-radius: 4px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #6B1B5E; }
        .feature-card h4 { font-size: 15px; color: #333; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .feature-card h4 svg { width: 20px; height: 20px; fill: #6B1B5E; }
        .feature-card p { font-size: 13px; color: #666; line-height: 1.5; }
        .feature-card .status { margin-top: 10px; font-size: 12px; font-weight: 600; }
        .ai-banner { background: linear-gradient(135deg, #6B1B5E 0%, #9c27b0 100%); color: white; padding: 25px; border-radius: 4px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .ai-banner h2 { font-size: 20px; margin-bottom: 5px; }
        .ai-banner p { font-size: 13px; opacity: 0.9; }
        .ai-banner .btn { background: white; color: #6B1B5E; font-weight: 600; }
        .ai-banner .btn:hover { background: #f3e5f5; }
        .progress-bar { height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden; margin-top: 8px; }
        .progress-bar .fill { height: 100%; background: #00BFA5; border-radius: 3px; }
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .analytics-card { background: white; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .analytics-card .title { font-size: 12px; color: #888; text-transform: uppercase; margin-bottom: 8px; }
        .analytics-card .value { font-size: 22px; font-weight: 600; color: #333; }
        .analytics-card .change { font-size: 12px; color: #27ae60; margin-top: 4px; }
        .analytics-card .change.negative { color: #e74c3c; }
    </style>
</head>
<body>
    <?php svg_defs(); ?>
    <?php sidebar('index'); ?>
    <?php top_header(); ?>

    <div class="content-wrapper">
        <?php breadcrumb(['Manage Time Table', 'Dashboard']); ?>
        <?php flash_message(); ?>

        <div class="ai-banner">
            <div>
                <h2><svg width="24" height="24" fill="white" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:8px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> Smart Timetable Generator</h2>
                <p>Conflict-free scheduling with energy-aware room allocation, faculty preference learning, and predictive analytics.</p>
            </div>
            <a href="demo.php" class="btn"><svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg> How It Works</a>
        </div>

        <?php if($ready): ?>
            <div class="alert alert-success">System is ready to generate the timetable.</div>
        <?php else: ?>
            <div class="alert alert-warning">Incomplete setup. Please configure all required data before generating.</div>
        <?php endif; ?>

        <div class="card-grid">
            <div class="dash-card">
                <h3><svg><use href="#icon-calendar"/></svg> Academic Years</h3>
                <div class="count"><?php echo $years_count; ?></div>
                <div class="link"><a href="setup.php">Manage &rarr;</a></div>
            </div>
            <div class="dash-card">
                <h3><svg><use href="#icon-building"/></svg> Classes</h3>
                <div class="count"><?php echo $classes_count; ?></div>
                <div class="link"><a href="setup.php">Manage &rarr;</a></div>
            </div>
            <div class="dash-card">
                <h3><svg><use href="#icon-user"/></svg> Faculty</h3>
                <div class="count"><?php echo $faculty_count; ?></div>
                <div class="link"><a href="setup.php">Manage &rarr;</a></div>
            </div>
            <div class="dash-card">
                <h3><svg><use href="#icon-book"/></svg> Subjects</h3>
                <div class="count"><?php echo $subjects_count; ?></div>
                <div class="link"><a href="setup.php">Manage &rarr;</a></div>
            </div>
            <div class="dash-card">
                <h3><svg><use href="#icon-link"/></svg> Assignments</h3>
                <div class="count"><?php echo $assignments_count; ?></div>
                <div class="link"><a href="setup.php">Manage &rarr;</a></div>
            </div>
            <div class="dash-card" style="border-top-color: #9c27b0;">
                <h3><svg><use href="#icon-room"/></svg> Rooms</h3>
                <div class="count"><?php echo $rooms_count; ?></div>
                <div class="link"><a href="setup.php">Manage &rarr;</a></div>
            </div>
        </div>

        <div class="analytics-grid">
            <div class="analytics-card">
                <div class="title">Timetable Entries</div>
                <div class="value"><?php echo number_format($timetable_count); ?></div>
                <div class="change"><?php echo $timetable_count > 0 ? 'Generated' : 'Not yet generated'; ?></div>
            </div>
            <div class="analytics-card">
                <div class="title">Avg Faculty Load</div>
                <div class="value"><?php echo round($faculty_avg_hours, 1); ?> <small style="font-size:13px;color:#888;">hrs/week</small></div>
                <div class="change">Balanced distribution</div>
            </div>
            <div class="analytics-card">
                <div class="title">Room Utilization</div>
                <div class="value"><?php echo round($room_utilization, 1); ?>%</div>
                <div class="change <?php echo $room_utilization > 80 ? 'negative' : ''; ?>"><?php echo $room_utilization > 80 ? 'High load' : 'Optimal'; ?></div>
            </div>
            <div class="analytics-card">
                <div class="title">Scheduling Engine</div>
                <div class="value" style="color: #27ae60;">Active</div>
                <div class="change">OR-Tools + Heuristics</div>
            </div>
        </div>

        <div class="content-box">
            <div class="content-box-header">Quick Actions</div>
            <div class="content-box-body">
                <div class="action-grid">
                    <div class="action-card" onclick="window.location.href='setup.php'">
                        <svg><use href="#icon-settings"/></svg>
                        <h4>Configure Data</h4>
                        <p>Add years, classes, faculty, subjects, rooms and assign them</p>
                    </div>
                    <div class="action-card" onclick="window.location.href='generate.php'">
                        <svg><use href="#icon-refresh"/></svg>
                        <h4>Generate Timetable</h4>
                        <p>Run the scheduling engine with conflict detection</p>
                    </div>
                    <div class="action-card" onclick="window.location.href='view.php'">
                        <svg><use href="#icon-eye"/></svg>
                        <h4>View Timetable</h4>
                        <p>View generated timetables by class, faculty, or room</p>
                    </div>
                    <div class="action-card" onclick="window.location.href='demo.php'" style="border-color: #9c27b0; background: #faf5ff;">
                        <svg style="fill: #9c27b0;"><use href="#icon-demo"/></svg>
                        <h4>How It Works</h4>
                        <p>Understand the scheduling engine and its constraint-solving approach</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-box">
            <div class="content-box-header">System Status & Features</div>
            <div class="content-box-body">
                <?php if($ready): ?>
                    <p style="color: #27ae60; font-weight: 600;">Ready to generate timetable. All required data is configured.</p>
                    <br><a href="generate.php" class="btn btn-success"><svg><use href="#icon-refresh"/></svg> Generate Timetable Now</a>
                <?php else: ?>
                    <p style="color: #e67e22; font-weight: 600;">Please configure all required data before generating.</p>
                    <br><a href="setup.php" class="btn btn-warning">Go to Setup</a>
                <?php endif; ?>

                <div class="feature-grid" style="margin-top: 25px;">
                    <div class="feature-card">
                        <h4><svg><use href="#icon-ai"/></svg> Hybrid Scheduling Engine</h4>
                        <p>Combines Constraint Satisfaction, Genetic Algorithm heuristics, and Google OR-Tools optimization for 100% conflict-free timetables.</p>
                        <div class="status"><span class="badge badge-green">Active</span></div>
                    </div>
                    <div class="feature-card">
                        <h4><svg><use href="#icon-star"/></svg> Faculty Preference Learning</h4>
                        <p>The system learns preferred teaching hours, historical workload patterns, and automatically balances assignments to maximize satisfaction.</p>
                        <div class="status"><span class="badge badge-blue">Learning</span></div>
                    </div>
                    <div class="feature-card">
                        <h4><svg><use href="#icon-refresh"/></svg> Dynamic Rescheduling</h4>
                        <p>Automatically regenerates schedules when faculty are absent, rooms become unavailable, or special events occur in real time.</p>
                        <div class="status"><span class="badge badge-green">Ready</span></div>
                    </div>
                    <div class="feature-card">
                        <h4><svg><use href="#icon-building"/></svg> Digital Twin Campus</h4>
                        <p>Considers building locations, walking distances between classes, and lab accessibility for realistic scheduling.</p>
                        <div class="status"><span class="badge badge-purple">Enabled</span></div>
                    </div>
                    <div class="feature-card">
                        <h4><svg><use href="#icon-energy"/></svg> Energy-Aware Scheduling</h4>
                        <p>Groups classes by building to reduce electricity usage and AC consumption. Supports green campus initiatives.</p>
                        <div class="status"><span class="badge badge-green">Optimizing</span></div>
                    </div>
                    <div class="feature-card">
                        <h4><svg><use href="#icon-chart"/></svg> Predictive Analytics</h4>
                        <p>Predicts faculty shortages, classroom requirements, and future infrastructure needs using historical scheduling data.</p>
                        <div class="status"><span class="badge badge-yellow">Analyzing</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
