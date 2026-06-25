<?php
require_once 'config.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_year') {
        $year_name = $conn->real_escape_string($_POST['year_name']);
        $year_status = $conn->real_escape_string($_POST['year_status']);
        $conn->query("INSERT INTO years (year_name, year_status) VALUES ('$year_name', '$year_status')");
        $message = "Year added successfully!"; $message_type = "success";
    }
    if ($action === 'add_class') {
        $year_id = intval($_POST['year_id']);
        $class_name = $conn->real_escape_string($_POST['class_name']);
        $class_code = $conn->real_escape_string($_POST['class_code']);
        $strength = intval($_POST['strength']);
        $conn->query("INSERT INTO classes (year_id, class_name, class_code, strength) VALUES ($year_id, '$class_name', '$class_code', $strength)");
        $message = "Class added successfully!"; $message_type = "success";
    }
    if ($action === 'add_faculty') {
        $faculty_name = $conn->real_escape_string($_POST['faculty_name']);
        $faculty_code = $conn->real_escape_string($_POST['faculty_code']);
        $department = $conn->real_escape_string($_POST['department']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $max_hours_day = intval($_POST['max_hours_per_day']);
        $max_hours_week = intval($_POST['max_hours_per_week']);
        $conn->query("INSERT INTO faculty (faculty_name, faculty_code, department, email, phone, max_hours_per_day, max_hours_per_week) VALUES ('$faculty_name', '$faculty_code', '$department', '$email', '$phone', $max_hours_day, $max_hours_week)");
        $message = "Faculty added successfully!"; $message_type = "success";
    }
    if ($action === 'add_subject') {
        $subject_name = $conn->real_escape_string($_POST['subject_name']);
        $subject_code = $conn->real_escape_string($_POST['subject_code']);
        $subject_type = $conn->real_escape_string($_POST['subject_type']);
        $lecture_hours = intval($_POST['lecture_hours_per_week']);
        $lab_hours = intval($_POST['lab_hours_per_week']);
        $department = $conn->real_escape_string($_POST['department']);
        $conn->query("INSERT INTO subjects (subject_name, subject_code, subject_type, lecture_hours_per_week, lab_hours_per_week, department) VALUES ('$subject_name', '$subject_code', '$subject_type', $lecture_hours, $lab_hours, '$department')");
        $message = "Subject added successfully!"; $message_type = "success";
    }
    if ($action === 'add_assignment') {
        $class_id = intval($_POST['class_id']);
        $subject_id = intval($_POST['subject_id']);
        $faculty_id = intval($_POST['faculty_id']);
        $conn->query("INSERT INTO subject_assignments (class_id, subject_id, faculty_id) VALUES ($class_id, $subject_id, $faculty_id)");
        $message = "Assignment added successfully!"; $message_type = "success";
    }
}

$years = $conn->query("SELECT * FROM years ORDER BY year_id DESC");
$classes = $conn->query("SELECT c.*, y.year_name FROM classes c JOIN years y ON c.year_id = y.year_id ORDER BY c.class_id DESC");
$faculty = $conn->query("SELECT * FROM faculty ORDER BY faculty_id DESC");
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_id DESC");
$assignments = $conn->query("SELECT sa.*, c.class_name, s.subject_name, f.faculty_name FROM subject_assignments sa JOIN classes c ON sa.class_id = c.class_id JOIN subjects s ON sa.subject_id = s.subject_id JOIN faculty f ON sa.faculty_id = f.faculty_id ORDER BY sa.assignment_id DESC");

$years_list = $conn->query("SELECT * FROM years WHERE year_status='active'");
$classes_list = $conn->query("SELECT * FROM classes");
$faculty_list = $conn->query("SELECT * FROM faculty");
$subjects_list = $conn->query("SELECT * FROM subjects");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Data - Timetable Management</title>
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
        .btn svg { width: 14px; height: 14px; fill: currentColor; }

        .alert {
            padding: 12px 15px; border-radius: 3px; margin-bottom: 20px; font-size: 13px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; font-size: 13px; }
        .form-group input, .form-group select {
            width: 100%; padding: 8px 10px; border: 1px solid #ddd; border-radius: 3px; font-size: 13px;
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #3498db; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #00BFA5; color: white; font-weight: 600; }
        tr:hover { background: #f8f9fa; }

        .tabs { display: flex; border-bottom: 2px solid #eee; margin-bottom: 1rem; }
        .tab { padding: 10px 20px; cursor: pointer; border-bottom: 3px solid transparent; font-size: 14px; color: #666; }
        .tab.active { border-bottom-color: #00BFA5; color: #00BFA5; font-weight: 600; }
        .tab:hover { background: #f8f9fa; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <svg style="display:none">
        <defs>
            <symbol id="icon-dashboard" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></symbol>
            <symbol id="icon-settings" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.59.91l-2.39-.96c-.22-.08-.47 0-.59.22L3.16 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></symbol>
            <symbol id="icon-refresh" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></symbol>
            <symbol id="icon-eye" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></symbol>
        </defs>
    </svg>

    <div class="sidebar">
        <div class="sidebar-header">
            <div style="width:40px;height:40px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#6B1B5E;font-weight:bold;font-size:18px;">A</div>
            <h2>Ajeenkya DY Patil<br><span style="font-size:11px;font-weight:400;opacity:0.8;">University ERP</span></h2>
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="menu-item">
                <svg><use href="#icon-dashboard"/></svg> Dashboard
            </a>
            <div class="menu-section">Timetable</div>
            <a href="setup.php" class="menu-item active">
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
            <a href="index.php">Home</a> / <span>Manage Time Table</span> / <span>Manage Data</span>
        </div>

        <?php if($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab active" onclick="showTab('years')">Years</div>
            <div class="tab" onclick="showTab('classes')">Classes</div>
            <div class="tab" onclick="showTab('faculty')">Faculty</div>
            <div class="tab" onclick="showTab('subjects')">Subjects</div>
            <div class="tab" onclick="showTab('assignments')">Assignments</div>
        </div>

        <div id="years" class="tab-content active">
            <div class="content-box">
                <div class="content-box-header">Add Academic Year</div>
                <div class="content-box-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_year">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Year Name</label>
                                <input type="text" name="year_name" required placeholder="e.g., First Year">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="year_status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn">Add Year</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Existing Years</div>
                <div class="content-box-body">
                    <table>
                        <tr><th>ID</th><th>Year Name</th><th>Status</th></tr>
                        <?php while($row = $years->fetch_assoc()): ?>
                        <tr><td><?php echo $row['year_id']; ?></td><td><?php echo $row['year_name']; ?></td><td><?php echo $row['year_status']; ?></td></tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>

        <div id="classes" class="tab-content">
            <div class="content-box">
                <div class="content-box-header">Add Class</div>
                <div class="content-box-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_class">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Select Year</label>
                                <select name="year_id" required>
                                    <option value="">-- Select Year --</option>
                                    <?php $years->data_seek(0); while($row = $years->fetch_assoc()): ?>
                                    <option value="<?php echo $row['year_id']; ?>"><?php echo $row['year_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Class Name</label>
                                <input type="text" name="class_name" required placeholder="e.g., Class A">
                            </div>
                            <div class="form-group">
                                <label>Class Code</label>
                                <input type="text" name="class_code" required placeholder="e.g., FY-A">
                            </div>
                            <div class="form-group">
                                <label>Student Strength</label>
                                <input type="number" name="strength" value="0" min="0">
                            </div>
                        </div>
                        <button type="submit" class="btn">Add Class</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Existing Classes</div>
                <div class="content-box-body">
                    <table>
                        <tr><th>ID</th><th>Class</th><th>Code</th><th>Year</th><th>Strength</th></tr>
                        <?php while($row = $classes->fetch_assoc()): ?>
                        <tr><td><?php echo $row['class_id']; ?></td><td><?php echo $row['class_name']; ?></td><td><?php echo $row['class_code']; ?></td><td><?php echo $row['year_name']; ?></td><td><?php echo $row['strength']; ?></td></tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>

        <div id="faculty" class="tab-content">
            <div class="content-box">
                <div class="content-box-header">Add Faculty</div>
                <div class="content-box-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_faculty">
                        <div class="form-grid">
                            <div class="form-group"><label>Faculty Name</label><input type="text" name="faculty_name" required></div>
                            <div class="form-group"><label>Faculty Code</label><input type="text" name="faculty_code" required></div>
                            <div class="form-group"><label>Department</label><input type="text" name="department"></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email"></div>
                            <div class="form-group"><label>Phone</label><input type="text" name="phone"></div>
                            <div class="form-group"><label>Max Hours/Day</label><input type="number" name="max_hours_per_day" value="6" min="1"></div>
                            <div class="form-group"><label>Max Hours/Week</label><input type="number" name="max_hours_per_week" value="30" min="1"></div>
                        </div>
                        <button type="submit" class="btn">Add Faculty</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Existing Faculty</div>
                <div class="content-box-body">
                    <table>
                        <tr><th>ID</th><th>Name</th><th>Code</th><th>Department</th><th>Max/Day</th><th>Max/Week</th></tr>
                        <?php while($row = $faculty->fetch_assoc()): ?>
                        <tr><td><?php echo $row['faculty_id']; ?></td><td><?php echo $row['faculty_name']; ?></td><td><?php echo $row['faculty_code']; ?></td><td><?php echo $row['department']; ?></td><td><?php echo $row['max_hours_per_day']; ?></td><td><?php echo $row['max_hours_per_week']; ?></td></tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>

        <div id="subjects" class="tab-content">
            <div class="content-box">
                <div class="content-box-header">Add Subject</div>
                <div class="content-box-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_subject">
                        <div class="form-grid">
                            <div class="form-group"><label>Subject Name</label><input type="text" name="subject_name" required></div>
                            <div class="form-group"><label>Subject Code</label><input type="text" name="subject_code" required></div>
                            <div class="form-group">
                                <label>Subject Type</label>
                                <select name="subject_type">
                                    <option value="lecture">Lecture Only</option>
                                    <option value="lab">Lab Only</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Lecture Hours/Week</label><input type="number" name="lecture_hours_per_week" value="3" min="0"></div>
                            <div class="form-group"><label>Lab Hours/Week</label><input type="number" name="lab_hours_per_week" value="2" min="0"></div>
                            <div class="form-group"><label>Department</label><input type="text" name="department"></div>
                        </div>
                        <button type="submit" class="btn">Add Subject</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Existing Subjects</div>
                <div class="content-box-body">
                    <table>
                        <tr><th>ID</th><th>Name</th><th>Code</th><th>Type</th><th>Lec Hrs</th><th>Lab Hrs</th></tr>
                        <?php while($row = $subjects->fetch_assoc()): ?>
                        <tr><td><?php echo $row['subject_id']; ?></td><td><?php echo $row['subject_name']; ?></td><td><?php echo $row['subject_code']; ?></td><td><?php echo $row['subject_type']; ?></td><td><?php echo $row['lecture_hours_per_week']; ?></td><td><?php echo $row['lab_hours_per_week']; ?></td></tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>

        <div id="assignments" class="tab-content">
            <div class="content-box">
                <div class="content-box-header">Assign Subject to Class & Faculty</div>
                <div class="content-box-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_assignment">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Select Class</label>
                                <select name="class_id" required>
                                    <option value="">-- Select Class --</option>
                                    <?php while($row = $classes_list->fetch_assoc()): ?>
                                    <option value="<?php echo $row['class_id']; ?>"><?php echo $row['class_name']; ?> (<?php echo $row['class_code']; ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Select Subject</label>
                                <select name="subject_id" required>
                                    <option value="">-- Select Subject --</option>
                                    <?php while($row = $subjects_list->fetch_assoc()): ?>
                                    <option value="<?php echo $row['subject_id']; ?>"><?php echo $row['subject_name']; ?> (<?php echo $row['subject_code']; ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Select Faculty</label>
                                <select name="faculty_id" required>
                                    <option value="">-- Select Faculty --</option>
                                    <?php while($row = $faculty_list->fetch_assoc()): ?>
                                    <option value="<?php echo $row['faculty_id']; ?>"><?php echo $row['faculty_name']; ?> (<?php echo $row['faculty_code']; ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn">Add Assignment</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Existing Assignments</div>
                <div class="content-box-body">
                    <table>
                        <tr><th>ID</th><th>Class</th><th>Subject</th><th>Faculty</th></tr>
                        <?php while($row = $assignments->fetch_assoc()): ?>
                        <tr><td><?php echo $row['assignment_id']; ?></td><td><?php echo $row['class_name']; ?></td><td><?php echo $row['subject_name']; ?></td><td><?php echo $row['faculty_name']; ?></td></tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
