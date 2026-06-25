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

        /* Sidebar */
        .sidebar {
            position: fixed; left: 0; top: 0; width: 240px; height: 100vh;
            background: #6B1B5E; color: white; overflow-y: auto; z-index: 100;
        }
        .sidebar-header {
            padding: 15px; background: #5a1850; border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; gap: 10px;
        }
        .sidebar-header img { width: 40px; height: 40px; border-radius: 50%; background: white; }
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
        .menu-item i { width: 20px; text-align: center; }
        .menu-section {
            padding: 8px 20px; font-size: 11px; text-transform: uppercase;
            color: rgba(255,255,255,0.5); letter-spacing: 1px; margin-top: 10px;
        }

        /* Top Header */
        .top-header {
            position: fixed; left: 240px; top: 0; right: 0; height: 50px;
            background: #F5A623; color: white; display: flex;
            align-items: center; justify-content: space-between; padding: 0 25px; z-index: 99;
        }
        .top-header-left { display: flex; align-items: center; gap: 15px; font-size: 14px; }
        .top-header-right { display: flex; align-items: center; gap: 20px; font-size: 13px; }
        .top-header-right a { color: white; text-decoration: none; }

        /* Content Area */
        .content-wrapper {
            margin-left: 240px; margin-top: 50px; padding: 25px; min-height: calc(100vh - 50px);
        }
        .breadcrumb {
            font-size: 13px; color: #666; margin-bottom: 20px;
        }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        /* Cards */
        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card {
            background: white; border-radius: 4px; padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 3px solid #00BFA5;
        }
        .stat-card h3 { font-size: 13px; color: #888; text-transform: uppercase; margin-bottom: 8px; }
        .stat-card .count { font-size: 28px; font-weight: 600; color: #333; }
        .stat-card .link { margin-top: 10px; font-size: 12px; }
        .stat-card .link a { color: #3498db; text-decoration: none; }

        /* Content Box */
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
            display: inline-block; padding: 8px 20px; background: #3498db; color: white;
            text-decoration: none; border-radius: 3px; border: none; cursor: pointer; font-size: 13px;
        }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #219a52; }
        .btn-warning { background: #e67e22; }
        .btn-warning:hover { background: #d35400; }

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
        .action-card h4 { color: #333; margin-bottom: 8px; font-size: 15px; }
        .action-card p { font-size: 13px; color: #666; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div style="width:40px;height:40px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#6B1B5E;font-weight:bold;font-size:18px;">A</div>
            <h2>Ajeenkya DY Patil<br><span style="font-size:11px;font-weight:400;opacity:0.8;">University ERP</span></h2>
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="menu-item active">
                <span>📊</span> Dashboard
            </a>
            <div class="menu-section">Timetable</div>
            <a href="setup.php" class="menu-item">
                <span>⚙️</span> Manage Data
            </a>
            <a href="generate.php" class="menu-item">
                <span>🔄</span> Generate Timetable
            </a>
            <a href="view.php" class="menu-item">
                <span>👁️</span> View Timetable
            </a>
        </div>
    </div>

    <!-- Top Header -->
    <div class="top-header">
        <div class="top-header-left">
            <span style="font-size:18px;cursor:pointer;">☰</span>
            <span>Active Academic Year: 2025-26 Summer Term</span>
        </div>
        <div class="top-header-right">
            <span>🔔 0</span>
            <span>Welcome, User</span>
            <a href="#">Logout</a>
        </div>
    </div>

    <!-- Content -->
    <div class="content-wrapper">
        <div class="breadcrumb">
            <a href="index.php">Home</a> / <span>Manage Time Table</span> / <span>Dashboard</span>
        </div>

        <?php if($years_count > 0 && $classes_count > 0 && $faculty_count > 0 && $subjects_count > 0): ?>
            <div class="alert alert-success">✅ System is ready to generate timetable.</div>
        <?php else: ?>
            <div class="alert alert-warning">⚠️ Incomplete setup. Please configure all required data before generating.</div>
        <?php endif; ?>

        <div class="card-grid">
            <div class="stat-card">
                <h3>Academic Years</h3>
                <div class="count"><?php echo $years_count; ?></div>
                <div class="link"><a href="setup.php">Manage →</a></div>
            </div>
            <div class="stat-card">
                <h3>Classes</h3>
                <div class="count"><?php echo $classes_count; ?></div>
                <div class="link"><a href="setup.php">Manage →</a></div>
            </div>
            <div class="stat-card">
                <h3>Faculty</h3>
                <div class="count"><?php echo $faculty_count; ?></div>
                <div class="link"><a href="setup.php">Manage →</a></div>
            </div>
            <div class="stat-card">
                <h3>Subjects</h3>
                <div class="count"><?php echo $subjects_count; ?></div>
                <div class="link"><a href="setup.php">Manage →</a></div>
            </div>
            <div class="stat-card">
                <h3>Assignments</h3>
                <div class="count"><?php echo $assignments_count; ?></div>
                <div class="link"><a href="setup.php">Manage →</a></div>
            </div>
        </div>

        <div class="content-box">
            <div class="content-box-header">Quick Actions</div>
            <div class="content-box-body">
                <div class="action-grid">
                    <div class="action-card" onclick="window.location.href='setup.php'">
                        <h4>⚙️ Configure Data</h4>
                        <p>Add years, classes, faculty, subjects and assign them</p>
                    </div>
                    <div class="action-card" onclick="window.location.href='generate.php'">
                        <h4>🔄 Generate Timetable</h4>
                        <p>Run the automatic timetable generation algorithm</p>
                    </div>
                    <div class="action-card" onclick="window.location.href='view.php'">
                        <h4>👁️ View Timetable</h4>
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
                    <br><a href="generate.php" class="btn btn-success">Generate Timetable Now</a>
                <?php else: ?>
                    <p style="color: #e67e22; font-weight: 600;">Please configure all required data before generating.</p>
                    <br><a href="setup.php" class="btn btn-warning">Go to Setup</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
