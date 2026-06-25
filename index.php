<?php
require_once 'config.php';

$years_count = $conn->query("SELECT COUNT(*) as count FROM years WHERE year_status='active'")->fetch_assoc()['count'];
$classes_count = $conn->query("SELECT COUNT(*) as count FROM classes")->fetch_assoc()['count'];
$faculty_count = $conn->query("SELECT COUNT(*) as count FROM faculty")->fetch_assoc()['count'];
$subjects_count = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
$assignments_count = $conn->query("SELECT COUNT(*) as count FROM subject_assignments")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Timetable Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }

        .sidebar {
            position: fixed; left: 0; top: 0; width: 240px; height: 100vh;
            background: #6B1B5E; color: white; overflow-y: auto; z-index: 100;
        }
        .sidebar-header {
            padding: 15px; background: #5a1850; border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; gap: 10px;
        }
        .sidebar-header h2 { font-size: 14px; font-weight: 600; }
        .sidebar-menu { padding: 10px 0; }
        .menu-item {
            padding: 12px 20px; display: flex; align-items: center; gap: 12px;
            cursor: pointer; transition: background 0.2s; text-decoration: none; color: rgba(255,255,255,0.85);
            font-size: 14px; border-left: 3px solid transparent;
        }
        .menu-item:hover, .menu-item.active {
            background: rgba(255,255,255,0.1); color: white; border-left-color: #F5A623;
        }
        .menu-item svg { width: 18px; height: 18px; fill: currentColor; opacity: 0.8; }
        .menu-item:hover svg, .menu-item.active svg { opacity: 1; }
        .menu-section {
            padding: 8px 20px; font-size: 11px; text-transform: uppercase;
            color: rgba(255,255,255,0.5); letter-spacing: 1px; margin-top: 10px;
        }

        .top-header {
            position: fixed; left: 240px; top: 0; right: 0; height: 50px;
            background: #F5A623; color: white; display: flex;
            align-items: center; justify-content: space-between; padding: 0 25px; z-index: 99;
        }
        .top-header-left { display: flex; align-items: center; gap: 15px; font-size: 14px; }
        .top-header-right { display: flex; align-items: center; gap: 20px; font-size: 13px; }
        .top-header-right a { color: white; text-decoration: none; }

        .content-wrapper {
            margin-left: 240px; margin-top: 50px; padding: 25px; min-height: calc(100vh - 50px);
        }
        .breadcrumb {
            font-size: 13px; color: #666; margin-bottom: 20px;
        }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card {
            background: white; border-radius: 4px; padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 3px solid #00BFA5;
        }
        .stat-card h3 { font-size: 13px; color: #888; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        .stat-card h3 svg { width: 16px; height: 16px; fill: #888; }
        .stat-card .count { font-size: 28px; font-weight: 600; color: #333; }
        .stat-card .link { margin-top: 10px; font-size: 12px; }
        .stat-card .link a { color: #3498db; text-decoration: none; }

        .content-box {
            background: white; border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;
        }
        .content-box-header {
            padding: 15px 20px; border-bottom: 1px solid #eee;
            font-size: 16px; font-weight: 600; color: #333;
        }
        .content-box-body { padding: 20px; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 20px; background: #3498db; color: white;
            text-decoration: none; border-radius: 3px; border: none; cursor: pointer; font-size: 13px;
        }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #219a52; }
        .btn-warning { background: #e67e22; }
        .btn-warning:hover { background: #d35400; }
        .btn svg { width: 14px; height: 14px; fill: currentColor; }

        .alert {
            padding: 12px 15px; border-radius: 3px; margin-bottom: 20px; font-size: 13px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }

        .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .action-card {
            border: 1px solid #e0e0e0; border-radius: 4px; padding: 20px; text-align: center;
            cursor: pointer; transition: all 0.2s; background: #fafafa;
        }
        .action-card:hover { border-color: #3498db; background: #f0f7ff; }
        .action-card svg { width: 32px; height: 32px; fill: #6B1B5E; margin-bottom: 10px; }
        .action-card h4 { color: #333; margin-bottom: 8px; font-size: 15px; }
        .action-card p { font-size: 13px; color: #666; }
    </style>
</head>
<body>
    <!-- SVG Icons Definitions -->
    <svg style="display:none">
        <defs>
            <symbol id="icon-dashboard" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></symbol>
            <symbol id="icon-settings" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.59.91l-2.39-.96c-.22-.08-.47 0-.59.22L3.16 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></symbol>
            <symbol id="icon-refresh" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></symbol>
            <symbol id="icon-eye" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></symbol>
            <symbol id="icon-calendar" viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></symbol>
            <symbol id="icon-building" viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></symbol>
            <symbol id="icon-user" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></symbol>
            <symbol id="icon-book" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></symbol>
            <symbol id="icon-link" viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></symbol>
            <symbol id="icon-arrow-right" viewBox="0 0 24 24"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></symbol>
        </defs>
    </svg>

    <div class="sidebar">
        <div class="sidebar-header">
            <div style="width:40px;height:40px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#6B1B5E;font-weight:bold;font-size:18px;">A</div>
            <h2>Ajeenkya DY Patil<br><span style="font-size:11px;font-weight:400;opacity:0.8;">University ERP</span></h2>
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="menu-item active">
                <svg><use href="#icon-dashboard"/></svg> Dashboard
            </a>
            <div class="menu-section">Timetable</div>
            <a href="setup.php" class="menu-item">
                <svg><use href="#icon-settings"/></svg> Manage Data
            </a>
            <a href="generate.php" class="menu-item">
                <svg><use href="#icon-refresh"/></svg> Generate Timetable
            </a>
            <a href="view.php" class="menu-item">
                <svg><use href="#icon-eye"/></svg> View Timetable
            </a>
        </div>
    </div>

    <div class="top-header">
        <div class="top-header-left">
            <span style="font-size:18px;cursor:pointer;">&#9776;</span>
            <span>Active Academic Year: 2025-26 Summer Term</span>
        </div>
        <div class="top-header-right">
            <span>&#128276; 0</span>
            <span>Welcome, User</span>
            <a href="#">Logout</a>
        </div>
    </div>

    <div class="content-wrapper">
        <div class="breadcrumb">
            <a href="index.php">Home</a> / <span>Manage Time Table</span> / <span>Dashboard</span>
        </div>

        <?php if($years_count > 0 && $classes_count > 0 && $faculty_count > 0 && $subjects_count > 0): ?>
            <div class="alert alert-success">System is ready to generate timetable.</div>
        <?php else: ?>
            <div class="alert alert-warning">Incomplete setup. Please configure all required data before generating.</div>
        <?php endif; ?>

        <div class="card-grid">
            <div class="stat-card">
                <h3><svg><use href="#icon-calendar"/></svg> Academic Years</h3>
                <div class="count"><?php echo $years_count; ?></div>
                <div class="link"><a href="setup.php">Manage &rarr;</a></div>
            </div>
            <div class="stat-card">
                <h3><svg><use href="#icon-building"/></svg> Classes</h3>
                <div class="count"><?php echo $classes_count; ?></div>
                <div class="link"><a href="setup.php">Manage &rarr;</a></div>
            </div>
            <div class="stat-card">
                <h3><svg><use href="#icon-user"/></svg> Faculty</h3>
                <div class="count"><?php echo $faculty_count; ?></div>
                <div class="link"><a href="setup.php">Manage &rarr;</a></div>
            </div>
            <div class="stat-card">
                <h3><svg><use href="#icon-book"/></svg> Subjects</h3>
                <div class="count"><?php echo $subjects_count; ?></div>
                <div class="link"><a href="setup.php">Manage &rarr;</a></div>
            </div>
            <div class="stat-card">
                <h3><svg><use href="#icon-link"/></svg> Assignments</h3>
                <div class="count"><?php echo $assignments_count; ?></div>
                <div class="link"><a href="setup.php">Manage &rarr;</a></div>
            </div>
        </div>

        <div class="content-box">
            <div class="content-box-header">Quick Actions</div>
            <div class="content-box-body">
                <div class="action-grid">
                    <div class="action-card" onclick="window.location.href='setup.php'">
                        <svg><use href="#icon-settings"/></svg>
                        <h4>Configure Data</h4>
                        <p>Add years, classes, faculty, subjects and assign them</p>
                    </div>
                    <div class="action-card" onclick="window.location.href='generate.php'">
                        <svg><use href="#icon-refresh"/></svg>
                        <h4>Generate Timetable</h4>
                        <p>Run the automatic timetable generation algorithm</p>
                    </div>
                    <div class="action-card" onclick="window.location.href='view.php'">
                        <svg><use href="#icon-eye"/></svg>
                        <h4>View Timetable</h4>
                        <p>View generated timetables by class or faculty</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-box">
            <div class="content-box-header">System Status</div>
            <div class="content-box-body">
                <?php if($years_count > 0 && $classes_count > 0 && $faculty_count > 0 && $subjects_count > 0): ?>
                    <p style="color: #27ae60; font-weight: 600;">Ready to generate timetable. All required data is configured.</p>
                    <br><a href="generate.php" class="btn btn-success"><svg><use href="#icon-refresh"/></svg> Generate Timetable Now</a>
                <?php else: ?>
                    <p style="color: #e67e22; font-weight: 600;">Please configure all required data before generating.</p>
                    <br><a href="setup.php" class="btn btn-warning">Go to Setup</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
