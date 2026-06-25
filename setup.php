<?php
require_once 'config.php';

// ============================
// HANDLE ALL POST ACTIONS
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // --- YEARS ---
    if ($action === 'add_year') {
        $name = $_POST['year_name'] ?? '';
        $status = $_POST['year_status'] ?? 'active';
        db_insert($conn, "INSERT INTO years (year_name, year_status) VALUES (?, ?)", "ss", [$name, $status]);
        audit_log($conn, 'ADD_YEAR', "Added year: $name");
        set_flash('success', 'Year added successfully!');
        header("Location: setup.php?tab=years"); exit;
    }
    if ($action === 'edit_year') {
        $id = intval($_POST['year_id'] ?? 0);
        $name = $_POST['year_name'] ?? '';
        $status = $_POST['year_status'] ?? 'active';
        db_execute($conn, "UPDATE years SET year_name=?, year_status=? WHERE year_id=?", "ssi", [$name, $status, $id]);
        audit_log($conn, 'EDIT_YEAR', "Updated year ID: $id");
        set_flash('success', 'Year updated successfully!');
        header("Location: setup.php?tab=years"); exit;
    }
    if ($action === 'delete_year') {
        $id = intval($_POST['year_id'] ?? 0);
        db_execute($conn, "DELETE FROM years WHERE year_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_YEAR', "Deleted year ID: $id");
        set_flash('success', 'Year deleted successfully!');
        header("Location: setup.php?tab=years"); exit;
    }

    // --- CLASSES ---
    if ($action === 'add_class') {
        $year_id = intval($_POST['year_id'] ?? 0);
        $name = $_POST['class_name'] ?? '';
        $code = $_POST['class_code'] ?? '';
        $strength = intval($_POST['strength'] ?? 0);
        db_insert($conn, "INSERT INTO classes (year_id, class_name, class_code, strength) VALUES (?, ?, ?, ?)", "issi", [$year_id, $name, $code, $strength]);
        audit_log($conn, 'ADD_CLASS', "Added class: $name");
        set_flash('success', 'Class added successfully!');
        header("Location: setup.php?tab=classes"); exit;
    }
    if ($action === 'edit_class') {
        $id = intval($_POST['class_id'] ?? 0);
        $year_id = intval($_POST['year_id'] ?? 0);
        $name = $_POST['class_name'] ?? '';
        $code = $_POST['class_code'] ?? '';
        $strength = intval($_POST['strength'] ?? 0);
        db_execute($conn, "UPDATE classes SET year_id=?, class_name=?, class_code=?, strength=? WHERE class_id=?", "issii", [$year_id, $name, $code, $strength, $id]);
        audit_log($conn, 'EDIT_CLASS', "Updated class ID: $id");
        set_flash('success', 'Class updated successfully!');
        header("Location: setup.php?tab=classes"); exit;
    }
    if ($action === 'delete_class') {
        $id = intval($_POST['class_id'] ?? 0);
        db_execute($conn, "DELETE FROM classes WHERE class_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_CLASS', "Deleted class ID: $id");
        set_flash('success', 'Class deleted successfully!');
        header("Location: setup.php?tab=classes"); exit;
    }

    // --- FACULTY ---
    if ($action === 'add_faculty') {
        $name = $_POST['faculty_name'] ?? '';
        $code = $_POST['faculty_code'] ?? '';
        $dept = $_POST['department'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $max_day = intval($_POST['max_hours_per_day'] ?? 6);
        $max_week = intval($_POST['max_hours_per_week'] ?? 30);
        db_insert($conn, "INSERT INTO faculty (faculty_name, faculty_code, department, email, phone, max_hours_per_day, max_hours_per_week) VALUES (?, ?, ?, ?, ?, ?, ?)", "sssssii", [$name, $code, $dept, $email, $phone, $max_day, $max_week]);
        audit_log($conn, 'ADD_FACULTY', "Added faculty: $name");
        set_flash('success', 'Faculty added successfully!');
        header("Location: setup.php?tab=faculty"); exit;
    }
    if ($action === 'edit_faculty') {
        $id = intval($_POST['faculty_id'] ?? 0);
        $name = $_POST['faculty_name'] ?? '';
        $code = $_POST['faculty_code'] ?? '';
        $dept = $_POST['department'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $max_day = intval($_POST['max_hours_per_day'] ?? 6);
        $max_week = intval($_POST['max_hours_per_week'] ?? 30);
        db_execute($conn, "UPDATE faculty SET faculty_name=?, faculty_code=?, department=?, email=?, phone=?, max_hours_per_day=?, max_hours_per_week=? WHERE faculty_id=?", "sssssiii", [$name, $code, $dept, $email, $phone, $max_day, $max_week, $id]);
        audit_log($conn, 'EDIT_FACULTY', "Updated faculty ID: $id");
        set_flash('success', 'Faculty updated successfully!');
        header("Location: setup.php?tab=faculty"); exit;
    }
    if ($action === 'delete_faculty') {
        $id = intval($_POST['faculty_id'] ?? 0);
        db_execute($conn, "DELETE FROM faculty WHERE faculty_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_FACULTY', "Deleted faculty ID: $id");
        set_flash('success', 'Faculty deleted successfully!');
        header("Location: setup.php?tab=faculty"); exit;
    }

    // --- SUBJECTS ---
    if ($action === 'add_subject') {
        $name = $_POST['subject_name'] ?? '';
        $code = $_POST['subject_code'] ?? '';
        $type = $_POST['subject_type'] ?? 'lecture';
        $lec = intval($_POST['lecture_hours_per_week'] ?? 0);
        $lab = intval($_POST['lab_hours_per_week'] ?? 0);
        $dept = $_POST['department'] ?? '';
        db_insert($conn, "INSERT INTO subjects (subject_name, subject_code, subject_type, lecture_hours_per_week, lab_hours_per_week, department) VALUES (?, ?, ?, ?, ?, ?)", "sssiss", [$name, $code, $type, $lec, $lab, $dept]);
        audit_log($conn, 'ADD_SUBJECT', "Added subject: $name");
        set_flash('success', 'Subject added successfully!');
        header("Location: setup.php?tab=subjects"); exit;
    }
    if ($action === 'edit_subject') {
        $id = intval($_POST['subject_id'] ?? 0);
        $name = $_POST['subject_name'] ?? '';
        $code = $_POST['subject_code'] ?? '';
        $type = $_POST['subject_type'] ?? 'lecture';
        $lec = intval($_POST['lecture_hours_per_week'] ?? 0);
        $lab = intval($_POST['lab_hours_per_week'] ?? 0);
        $dept = $_POST['department'] ?? '';
        db_execute($conn, "UPDATE subjects SET subject_name=?, subject_code=?, subject_type=?, lecture_hours_per_week=?, lab_hours_per_week=?, department=? WHERE subject_id=?", "sssissi", [$name, $code, $type, $lec, $lab, $dept, $id]);
        audit_log($conn, 'EDIT_SUBJECT', "Updated subject ID: $id");
        set_flash('success', 'Subject updated successfully!');
        header("Location: setup.php?tab=subjects"); exit;
    }
    if ($action === 'delete_subject') {
        $id = intval($_POST['subject_id'] ?? 0);
        db_execute($conn, "DELETE FROM subjects WHERE subject_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_SUBJECT', "Deleted subject ID: $id");
        set_flash('success', 'Subject deleted successfully!');
        header("Location: setup.php?tab=subjects"); exit;
    }

    // --- ASSIGNMENTS ---
    if ($action === 'add_assignment') {
        $class_id = intval($_POST['class_id'] ?? 0);
        $subject_id = intval($_POST['subject_id'] ?? 0);
        $faculty_id = intval($_POST['faculty_id'] ?? 0);
        db_insert($conn, "INSERT INTO subject_assignments (class_id, subject_id, faculty_id) VALUES (?, ?, ?)", "iii", [$class_id, $subject_id, $faculty_id]);
        audit_log($conn, 'ADD_ASSIGNMENT', "Added assignment: class=$class_id, subject=$subject_id, faculty=$faculty_id");
        set_flash('success', 'Assignment added successfully!');
        header("Location: setup.php?tab=assignments"); exit;
    }
    if ($action === 'delete_assignment') {
        $id = intval($_POST['assignment_id'] ?? 0);
        db_execute($conn, "DELETE FROM subject_assignments WHERE assignment_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_ASSIGNMENT', "Deleted assignment ID: $id");
        set_flash('success', 'Assignment deleted successfully!');
        header("Location: setup.php?tab=assignments"); exit;
    }

    // --- ROOMS ---
    if ($action === 'add_room') {
        $building_id = intval($_POST['building_id'] ?? 0);
        $name = $_POST['room_name'] ?? '';
        $type = $_POST['room_type'] ?? 'classroom';
        $capacity = intval($_POST['capacity'] ?? 0);
        $has_proj = isset($_POST['has_projector']) ? 1 : 0;
        $has_ac = isset($_POST['has_ac']) ? 1 : 0;
        $floor = intval($_POST['floor_number'] ?? 1);
        db_insert($conn, "INSERT INTO rooms (building_id, room_name, room_type, capacity, has_projector, has_ac, floor_number) VALUES (?, ?, ?, ?, ?, ?, ?)", "issiiii", [$building_id, $name, $type, $capacity, $has_proj, $has_ac, $floor]);
        audit_log($conn, 'ADD_ROOM', "Added room: $name");
        set_flash('success', 'Room added successfully!');
        header("Location: setup.php?tab=rooms"); exit;
    }
    if ($action === 'edit_room') {
        $id = intval($_POST['room_id'] ?? 0);
        $building_id = intval($_POST['building_id'] ?? 0);
        $name = $_POST['room_name'] ?? '';
        $type = $_POST['room_type'] ?? 'classroom';
        $capacity = intval($_POST['capacity'] ?? 0);
        $has_proj = isset($_POST['has_projector']) ? 1 : 0;
        $has_ac = isset($_POST['has_ac']) ? 1 : 0;
        $floor = intval($_POST['floor_number'] ?? 1);
        db_execute($conn, "UPDATE rooms SET building_id=?, room_name=?, room_type=?, capacity=?, has_projector=?, has_ac=?, floor_number=? WHERE room_id=?", "issiiiii", [$building_id, $name, $type, $capacity, $has_proj, $has_ac, $floor, $id]);
        audit_log($conn, 'EDIT_ROOM', "Updated room ID: $id");
        set_flash('success', 'Room updated successfully!');
        header("Location: setup.php?tab=rooms"); exit;
    }
    if ($action === 'delete_room') {
        $id = intval($_POST['room_id'] ?? 0);
        db_execute($conn, "DELETE FROM rooms WHERE room_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_ROOM', "Deleted room ID: $id");
        set_flash('success', 'Room deleted successfully!');
        header("Location: setup.php?tab=rooms"); exit;
    }

    // --- BUILDINGS ---
    if ($action === 'add_building') {
        $name = $_POST['building_name'] ?? '';
        $has_ac = isset($_POST['has_ac']) ? 1 : 0;
        $rating = $_POST['energy_rating'] ?? 'B';
        db_insert($conn, "INSERT INTO buildings (building_name, has_ac, energy_rating) VALUES (?, ?, ?)", "sis", [$name, $has_ac, $rating]);
        audit_log($conn, 'ADD_BUILDING', "Added building: $name");
        set_flash('success', 'Building added successfully!');
        header("Location: setup.php?tab=rooms"); exit;
    }
    if ($action === 'edit_building') {
        $id = intval($_POST['building_id'] ?? 0);
        $name = $_POST['building_name'] ?? '';
        $has_ac = isset($_POST['has_ac']) ? 1 : 0;
        $rating = $_POST['energy_rating'] ?? 'B';
        db_execute($conn, "UPDATE buildings SET building_name=?, has_ac=?, energy_rating=? WHERE building_id=?", "sisi", [$name, $has_ac, $rating, $id]);
        audit_log($conn, 'EDIT_BUILDING', "Updated building ID: $id");
        set_flash('success', 'Building updated successfully!');
        header("Location: setup.php?tab=rooms"); exit;
    }
    if ($action === 'delete_building') {
        $id = intval($_POST['building_id'] ?? 0);
        db_execute($conn, "DELETE FROM buildings WHERE building_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_BUILDING', "Deleted building ID: $id");
        set_flash('success', 'Building deleted successfully!');
        header("Location: setup.php?tab=rooms"); exit;
    }

    // --- FACULTY PREFERENCES ---
    if ($action === 'add_preference') {
        $faculty_id = intval($_POST['faculty_id'] ?? 0);
        $day_id = intval($_POST['day_id'] ?? 0);
        $slot_id = intval($_POST['slot_id'] ?? 0);
        $level = $_POST['preference_level'] ?? 'neutral';
        db_execute($conn, "INSERT INTO faculty_preferences (faculty_id, day_id, slot_id, preference_level) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE preference_level=?", "iiiss", [$faculty_id, $day_id, $slot_id, $level, $level]);
        audit_log($conn, 'ADD_PREFERENCE', "Set preference for faculty=$faculty_id");
        set_flash('success', 'Preference saved successfully!');
        header("Location: setup.php?tab=preferences"); exit;
    }
    if ($action === 'delete_preference') {
        $id = intval($_POST['preference_id'] ?? 0);
        db_execute($conn, "DELETE FROM faculty_preferences WHERE preference_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_PREFERENCE', "Deleted preference ID: $id");
        set_flash('success', 'Preference deleted successfully!');
        header("Location: setup.php?tab=preferences"); exit;
    }

    // --- FACULTY UNAVAILABLE ---
    if ($action === 'add_unavailable') {
        $faculty_id = intval($_POST['faculty_id'] ?? 0);
        $day_id = intval($_POST['day_id'] ?? 0);
        $slot_id = intval($_POST['slot_id'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        db_execute($conn, "INSERT INTO faculty_unavailable (faculty_id, day_id, slot_id, reason) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason=?", "iiiss", [$faculty_id, $day_id, $slot_id, $reason, $reason]);
        audit_log($conn, 'ADD_UNAVAILABLE', "Set unavailable for faculty=$faculty_id");
        set_flash('success', 'Unavailable slot saved successfully!');
        header("Location: setup.php?tab=unavailable"); exit;
    }
    if ($action === 'delete_unavailable') {
        $id = intval($_POST['unavailable_id'] ?? 0);
        db_execute($conn, "DELETE FROM faculty_unavailable WHERE unavailable_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_UNAVAILABLE', "Deleted unavailable ID: $id");
        set_flash('success', 'Unavailable slot deleted successfully!');
        header("Location: setup.php?tab=unavailable"); exit;
    }
}

// ============================
// FETCH DATA FOR DISPLAY
// ============================
$years = db_get_rows($conn, "SELECT * FROM years ORDER BY year_id DESC");
$classes = db_get_rows($conn, "SELECT c.*, y.year_name FROM classes c JOIN years y ON c.year_id = y.year_id ORDER BY c.class_id DESC");
$faculty = db_get_rows($conn, "SELECT * FROM faculty ORDER BY faculty_id DESC");
$subjects = db_get_rows($conn, "SELECT * FROM subjects ORDER BY subject_id DESC");
$assignments = db_get_rows($conn, "SELECT sa.*, c.class_name, s.subject_name, f.faculty_name FROM subject_assignments sa JOIN classes c ON sa.class_id = c.class_id JOIN subjects s ON sa.subject_id = s.subject_id JOIN faculty f ON sa.faculty_id = f.faculty_id ORDER BY sa.assignment_id DESC");
$rooms = db_get_rows($conn, "SELECT r.*, b.building_name FROM rooms r JOIN buildings b ON r.building_id = b.building_id ORDER BY r.room_id DESC");
$buildings = db_get_rows($conn, "SELECT * FROM buildings ORDER BY building_id DESC");
$preferences = db_get_rows($conn, "SELECT fp.*, f.faculty_name, d.day_name, ts.start_time, ts.end_time FROM faculty_preferences fp JOIN faculty f ON fp.faculty_id = f.faculty_id JOIN working_days d ON fp.day_id = d.day_id JOIN time_slots ts ON fp.slot_id = ts.slot_id ORDER BY fp.preference_id DESC");
$unavailable = db_get_rows($conn, "SELECT fu.*, f.faculty_name, d.day_name, ts.start_time, ts.end_time FROM faculty_unavailable fu JOIN faculty f ON fu.faculty_id = f.faculty_id JOIN working_days d ON fu.day_id = d.day_id JOIN time_slots ts ON fu.slot_id = ts.slot_id ORDER BY fu.unavailable_id DESC");
$working_days = db_get_rows($conn, "SELECT * FROM working_days WHERE is_working=1 ORDER BY day_order");
$time_slots = db_get_rows($conn, "SELECT * FROM time_slots WHERE is_active=1 AND slot_type='class' ORDER BY slot_number");

// Lists for dropdowns
$years_list = db_get_rows($conn, "SELECT * FROM years WHERE year_status='active' ORDER BY year_id DESC");
$classes_list = db_get_rows($conn, "SELECT * FROM classes ORDER BY class_name");
$faculty_list = db_get_rows($conn, "SELECT * FROM faculty ORDER BY faculty_name");
$subjects_list = db_get_rows($conn, "SELECT * FROM subjects ORDER BY subject_name");
$buildings_list = db_get_rows($conn, "SELECT * FROM buildings ORDER BY building_name");

$active_tab = $_GET['tab'] ?? 'years';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Data - AI Smart Timetable</title>
    <?php common_styles(); ?>
    <style>
        .edit-form { display: none; background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 10px; border: 1px solid #e0e0e0; }
        .edit-form.active { display: block; }
        .action-btns { display: flex; gap: 5px; }
        .action-btns form { display: inline; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .checkbox-group input { width: auto; }
        .preference-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .preference-card { background: #f8f9fa; padding: 15px; border-radius: 4px; border-left: 4px solid #00BFA5; }
        .preference-card.avoid { border-left-color: #e74c3c; }
        .preference-card.preferred { border-left-color: #27ae60; }
    </style>
</head>
<body>
    <?php svg_defs(); ?>
    <?php sidebar('setup'); ?>
    <?php top_header(); ?>

    <div class="content-wrapper">
        <?php breadcrumb(['Manage Time Table', 'Manage Data']); ?>
        <?php flash_message(); ?>

        <div class="tabs">
            <div class="tab <?php echo $active_tab=='years'?'active':''; ?>" onclick="window.location.href='?tab=years'">Years</div>
            <div class="tab <?php echo $active_tab=='classes'?'active':''; ?>" onclick="window.location.href='?tab=classes'">Classes</div>
            <div class="tab <?php echo $active_tab=='faculty'?'active':''; ?>" onclick="window.location.href='?tab=faculty'">Faculty</div>
            <div class="tab <?php echo $active_tab=='subjects'?'active':''; ?>" onclick="window.location.href='?tab=subjects'">Subjects</div>
            <div class="tab <?php echo $active_tab=='assignments'?'active':''; ?>" onclick="window.location.href='?tab=assignments'">Assignments</div>
            <div class="tab <?php echo $active_tab=='rooms'?'active':''; ?>" onclick="window.location.href='?tab=rooms'">Rooms</div>
            <div class="tab <?php echo $active_tab=='preferences'?'active':''; ?>" onclick="window.location.href='?tab=preferences'">Preferences</div>
            <div class="tab <?php echo $active_tab=='unavailable'?'active':''; ?>" onclick="window.location.href='?tab=unavailable'">Unavailable</div>
        </div>

        <!-- ==================== YEARS ==================== -->
        <div id="years" class="tab-content <?php echo $active_tab=='years'?'active':''; ?>">
            <div class="content-box">
                <div class="content-box-header">Add Academic Year</div>
                <div class="content-box-body">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_year">
                        <div class="form-grid">
                            <div class="form-group"><label>Year Name</label><input type="text" name="year_name" required placeholder="e.g., First Year"></div>
                            <div class="form-group"><label>Status</label><select name="year_status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                        </div>
                        <button type="submit" class="btn">Add Year</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Existing Years</div>
                <div class="content-box-body">
                    <table>
                        <tr><th>ID</th><th>Year Name</th><th>Status</th><th class="text-right">Actions</th></tr>
                        <?php foreach($years as $row): ?>
                        <tr>
                            <td><?php echo $row['year_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['year_name']); ?></td>
                            <td><span class="badge <?php echo $row['year_status']=='active'?'badge-green':'badge-yellow'; ?>"><?php echo $row['year_status']; ?></span></td>
                            <td class="text-right">
                                <button class="btn btn-sm" onclick="toggleEdit('year-edit-<?php echo $row['year_id']; ?>')">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this year?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_year">
                                    <input type="hidden" name="year_id" value="<?php echo $row['year_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><svg width="14" height="14"><use href="#icon-delete"/></svg></button>
                                </form>
                            </td>
                        </tr>
                        <tr><td colspan="4">
                            <div id="year-edit-<?php echo $row['year_id']; ?>" class="edit-form">
                                <form method="POST">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="edit_year">
                                    <input type="hidden" name="year_id" value="<?php echo $row['year_id']; ?>">
                                    <div class="form-grid">
                                        <div class="form-group"><label>Year Name</label><input type="text" name="year_name" value="<?php echo htmlspecialchars($row['year_name']); ?>" required></div>
                                        <div class="form-group"><label>Status</label><select name="year_status"><option value="active" <?php echo $row['year_status']=='active'?'selected':''; ?>>Active</option><option value="inactive" <?php echo $row['year_status']=='inactive'?'selected':''; ?>>Inactive</option></select></div>
                                    </div>
                                    <button type="submit" class="btn btn-sm">Save</button>
                                </form>
                            </div>
                        </td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== CLASSES ==================== -->
        <div id="classes" class="tab-content <?php echo $active_tab=='classes'?'active':''; ?>">
            <div class="content-box">
                <div class="content-box-header">Add Class</div>
                <div class="content-box-body">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_class">
                        <div class="form-grid">
                            <div class="form-group"><label>Select Year</label><select name="year_id" required><option value="">-- Select Year --</option><?php foreach($years_list as $y): ?><option value="<?php echo $y['year_id']; ?>"><?php echo htmlspecialchars($y['year_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Class Name</label><input type="text" name="class_name" required placeholder="e.g., Class A"></div>
                            <div class="form-group"><label>Class Code</label><input type="text" name="class_code" required placeholder="e.g., FY-A"></div>
                            <div class="form-group"><label>Student Strength</label><input type="number" name="strength" value="0" min="0"></div>
                        </div>
                        <button type="submit" class="btn">Add Class</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Existing Classes</div>
                <div class="content-box-body">
                    <table>
                        <tr><th>ID</th><th>Class</th><th>Code</th><th>Year</th><th>Strength</th><th class="text-right">Actions</th></tr>
                        <?php foreach($classes as $row): ?>
                        <tr>
                            <td><?php echo $row['class_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['class_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['year_name']); ?></td>
                            <td><?php echo $row['strength']; ?></td>
                            <td class="text-right">
                                <button class="btn btn-sm" onclick="toggleEdit('class-edit-<?php echo $row['class_id']; ?>')">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this class?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_class">
                                    <input type="hidden" name="class_id" value="<?php echo $row['class_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><svg width="14" height="14"><use href="#icon-delete"/></svg></button>
                                </form>
                            </td>
                        </tr>
                        <tr><td colspan="6">
                            <div id="class-edit-<?php echo $row['class_id']; ?>" class="edit-form">
                                <form method="POST">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="edit_class">
                                    <input type="hidden" name="class_id" value="<?php echo $row['class_id']; ?>">
                                    <div class="form-grid">
                                        <div class="form-group"><label>Select Year</label><select name="year_id" required><?php foreach($years_list as $y): ?><option value="<?php echo $y['year_id']; ?>" <?php echo $y['year_id']==$row['year_id']?'selected':''; ?>><?php echo htmlspecialchars($y['year_name']); ?></option><?php endforeach; ?></select></div>
                                        <div class="form-group"><label>Class Name</label><input type="text" name="class_name" value="<?php echo htmlspecialchars($row['class_name']); ?>" required></div>
                                        <div class="form-group"><label>Class Code</label><input type="text" name="class_code" value="<?php echo htmlspecialchars($row['class_code']); ?>" required></div>
                                        <div class="form-group"><label>Student Strength</label><input type="number" name="strength" value="<?php echo $row['strength']; ?>" min="0"></div>
                                    </div>
                                    <button type="submit" class="btn btn-sm">Save</button>
                                </form>
                            </div>
                        </td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== FACULTY ==================== -->
        <div id="faculty" class="tab-content <?php echo $active_tab=='faculty'?'active':''; ?>">
            <div class="content-box">
                <div class="content-box-header">Add Faculty</div>
                <div class="content-box-body">
                    <form method="POST">
                        <?php csrf_field(); ?>
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
                        <tr><th>ID</th><th>Name</th><th>Code</th><th>Department</th><th>Max/Day</th><th>Max/Week</th><th class="text-right">Actions</th></tr>
                        <?php foreach($faculty as $row): ?>
                        <tr>
                            <td><?php echo $row['faculty_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['faculty_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td><?php echo $row['max_hours_per_day']; ?></td>
                            <td><?php echo $row['max_hours_per_week']; ?></td>
                            <td class="text-right">
                                <button class="btn btn-sm" onclick="toggleEdit('faculty-edit-<?php echo $row['faculty_id']; ?>')">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this faculty?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_faculty">
                                    <input type="hidden" name="faculty_id" value="<?php echo $row['faculty_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><svg width="14" height="14"><use href="#icon-delete"/></svg></button>
                                </form>
                            </td>
                        </tr>
                        <tr><td colspan="7">
                            <div id="faculty-edit-<?php echo $row['faculty_id']; ?>" class="edit-form">
                                <form method="POST">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="edit_faculty">
                                    <input type="hidden" name="faculty_id" value="<?php echo $row['faculty_id']; ?>">
                                    <div class="form-grid">
                                        <div class="form-group"><label>Faculty Name</label><input type="text" name="faculty_name" value="<?php echo htmlspecialchars($row['faculty_name']); ?>" required></div>
                                        <div class="form-group"><label>Faculty Code</label><input type="text" name="faculty_code" value="<?php echo htmlspecialchars($row['faculty_code']); ?>" required></div>
                                        <div class="form-group"><label>Department</label><input type="text" name="department" value="<?php echo htmlspecialchars($row['department']); ?>"></div>
                                        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($row['email']); ?>"></div>
                                        <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo htmlspecialchars($row['phone']); ?>"></div>
                                        <div class="form-group"><label>Max Hours/Day</label><input type="number" name="max_hours_per_day" value="<?php echo $row['max_hours_per_day']; ?>" min="1"></div>
                                        <div class="form-group"><label>Max Hours/Week</label><input type="number" name="max_hours_per_week" value="<?php echo $row['max_hours_per_week']; ?>" min="1"></div>
                                    </div>
                                    <button type="submit" class="btn btn-sm">Save</button>
                                </form>
                            </div>
                        </td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== SUBJECTS ==================== -->
        <div id="subjects" class="tab-content <?php echo $active_tab=='subjects'?'active':''; ?>">
            <div class="content-box">
                <div class="content-box-header">Add Subject</div>
                <div class="content-box-body">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_subject">
                        <div class="form-grid">
                            <div class="form-group"><label>Subject Name</label><input type="text" name="subject_name" required></div>
                            <div class="form-group"><label>Subject Code</label><input type="text" name="subject_code" required></div>
                            <div class="form-group"><label>Subject Type</label><select name="subject_type"><option value="lecture">Lecture Only</option><option value="lab">Lab Only</option><option value="both">Both</option></select></div>
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
                        <tr><th>ID</th><th>Name</th><th>Code</th><th>Type</th><th>Lec Hrs</th><th>Lab Hrs</th><th class="text-right">Actions</th></tr>
                        <?php foreach($subjects as $row): ?>
                        <tr>
                            <td><?php echo $row['subject_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['subject_code']); ?></td>
                            <td><span class="badge badge-blue"><?php echo $row['subject_type']; ?></span></td>
                            <td><?php echo $row['lecture_hours_per_week']; ?></td>
                            <td><?php echo $row['lab_hours_per_week']; ?></td>
                            <td class="text-right">
                                <button class="btn btn-sm" onclick="toggleEdit('subject-edit-<?php echo $row['subject_id']; ?>')">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this subject?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_subject">
                                    <input type="hidden" name="subject_id" value="<?php echo $row['subject_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><svg width="14" height="14"><use href="#icon-delete"/></svg></button>
                                </form>
                            </td>
                        </tr>
                        <tr><td colspan="7">
                            <div id="subject-edit-<?php echo $row['subject_id']; ?>" class="edit-form">
                                <form method="POST">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="edit_subject">
                                    <input type="hidden" name="subject_id" value="<?php echo $row['subject_id']; ?>">
                                    <div class="form-grid">
                                        <div class="form-group"><label>Subject Name</label><input type="text" name="subject_name" value="<?php echo htmlspecialchars($row['subject_name']); ?>" required></div>
                                        <div class="form-group"><label>Subject Code</label><input type="text" name="subject_code" value="<?php echo htmlspecialchars($row['subject_code']); ?>" required></div>
                                        <div class="form-group"><label>Subject Type</label><select name="subject_type"><option value="lecture" <?php echo $row['subject_type']=='lecture'?'selected':''; ?>>Lecture Only</option><option value="lab" <?php echo $row['subject_type']=='lab'?'selected':''; ?>>Lab Only</option><option value="both" <?php echo $row['subject_type']=='both'?'selected':''; ?>>Both</option></select></div>
                                        <div class="form-group"><label>Lecture Hours/Week</label><input type="number" name="lecture_hours_per_week" value="<?php echo $row['lecture_hours_per_week']; ?>" min="0"></div>
                                        <div class="form-group"><label>Lab Hours/Week</label><input type="number" name="lab_hours_per_week" value="<?php echo $row['lab_hours_per_week']; ?>" min="0"></div>
                                        <div class="form-group"><label>Department</label><input type="text" name="department" value="<?php echo htmlspecialchars($row['department']); ?>"></div>
                                    </div>
                                    <button type="submit" class="btn btn-sm">Save</button>
                                </form>
                            </div>
                        </td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== ASSIGNMENTS ==================== -->
        <div id="assignments" class="tab-content <?php echo $active_tab=='assignments'?'active':''; ?>">
            <div class="content-box">
                <div class="content-box-header">Assign Subject to Class & Faculty</div>
                <div class="content-box-body">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_assignment">
                        <div class="form-grid">
                            <div class="form-group"><label>Select Class</label><select name="class_id" required><option value="">-- Select Class --</option><?php foreach($classes_list as $r): ?><option value="<?php echo $r['class_id']; ?>"><?php echo htmlspecialchars($r['class_name']); ?> (<?php echo htmlspecialchars($r['class_code']); ?>)</option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Select Subject</label><select name="subject_id" required><option value="">-- Select Subject --</option><?php foreach($subjects_list as $r): ?><option value="<?php echo $r['subject_id']; ?>"><?php echo htmlspecialchars($r['subject_name']); ?> (<?php echo htmlspecialchars($r['subject_code']); ?>)</option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Select Faculty</label><select name="faculty_id" required><option value="">-- Select Faculty --</option><?php foreach($faculty_list as $r): ?><option value="<?php echo $r['faculty_id']; ?>"><?php echo htmlspecialchars($r['faculty_name']); ?> (<?php echo htmlspecialchars($r['faculty_code']); ?>)</option><?php endforeach; ?></select></div>
                        </div>
                        <button type="submit" class="btn">Add Assignment</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Existing Assignments</div>
                <div class="content-box-body">
                    <table>
                        <tr><th>ID</th><th>Class</th><th>Subject</th><th>Faculty</th><th class="text-right">Actions</th></tr>
                        <?php foreach($assignments as $row): ?>
                        <tr>
                            <td><?php echo $row['assignment_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                            <td class="text-right">
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this assignment?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_assignment">
                                    <input type="hidden" name="assignment_id" value="<?php echo $row['assignment_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><svg width="14" height="14"><use href="#icon-delete"/></svg></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== ROOMS & BUILDINGS ==================== -->
        <div id="rooms" class="tab-content <?php echo $active_tab=='rooms'?'active':''; ?>">
            <div class="content-box">
                <div class="content-box-header">Add Building</div>
                <div class="content-box-body">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_building">
                        <div class="form-grid">
                            <div class="form-group"><label>Building Name</label><input type="text" name="building_name" required></div>
                            <div class="form-group"><label>Energy Rating</label><select name="energy_rating"><option value="A">A (Best)</option><option value="B">B</option><option value="C">C</option></select></div>
                            <div class="form-group"><div class="checkbox-group"><input type="checkbox" name="has_ac" id="b_ac" checked><label for="b_ac" style="margin-bottom:0;">Has AC</label></div></div>
                        </div>
                        <button type="submit" class="btn">Add Building</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Existing Buildings</div>
                <div class="content-box-body">
                    <table>
                        <tr><th>ID</th><th>Name</th><th>AC</th><th>Rating</th><th class="text-right">Actions</th></tr>
                        <?php foreach($buildings as $row): ?>
                        <tr>
                            <td><?php echo $row['building_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['building_name']); ?></td>
                            <td><?php echo $row['has_ac'] ? 'Yes' : 'No'; ?></td>
                            <td><span class="badge badge-purple"><?php echo $row['energy_rating']; ?></span></td>
                            <td class="text-right">
                                <button class="btn btn-sm" onclick="toggleEdit('building-edit-<?php echo $row['building_id']; ?>')">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this building?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_building">
                                    <input type="hidden" name="building_id" value="<?php echo $row['building_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><svg width="14" height="14"><use href="#icon-delete"/></svg></button>
                                </form>
                            </td>
                        </tr>
                        <tr><td colspan="5">
                            <div id="building-edit-<?php echo $row['building_id']; ?>" class="edit-form">
                                <form method="POST">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="edit_building">
                                    <input type="hidden" name="building_id" value="<?php echo $row['building_id']; ?>">
                                    <div class="form-grid">
                                        <div class="form-group"><label>Building Name</label><input type="text" name="building_name" value="<?php echo htmlspecialchars($row['building_name']); ?>" required></div>
                                        <div class="form-group"><label>Energy Rating</label><select name="energy_rating"><option value="A" <?php echo $row['energy_rating']=='A'?'selected':''; ?>>A</option><option value="B" <?php echo $row['energy_rating']=='B'?'selected':''; ?>>B</option><option value="C" <?php echo $row['energy_rating']=='C'?'selected':''; ?>>C</option></select></div>
                                        <div class="form-group"><div class="checkbox-group"><input type="checkbox" name="has_ac" id="b_ac_<?php echo $row['building_id']; ?>" <?php echo $row['has_ac']?'checked':''; ?>><label for="b_ac_<?php echo $row['building_id']; ?>" style="margin-bottom:0;">Has AC</label></div></div>
                                    </div>
                                    <button type="submit" class="btn btn-sm">Save</button>
                                </form>
                            </div>
                        </td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <div class="content-box">
                <div class="content-box-header">Add Room</div>
                <div class="content-box-body">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_room">
                        <div class="form-grid">
                            <div class="form-group"><label>Building</label><select name="building_id" required><option value="">-- Select Building --</option><?php foreach($buildings_list as $b): ?><option value="<?php echo $b['building_id']; ?>"><?php echo htmlspecialchars($b['building_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Room Name</label><input type="text" name="room_name" required placeholder="e.g., 101 or Lab A"></div>
                            <div class="form-group"><label>Room Type</label><select name="room_type"><option value="classroom">Classroom</option><option value="lab">Lab</option><option value="seminar">Seminar</option><option value="auditorium">Auditorium</option></select></div>
                            <div class="form-group"><label>Capacity</label><input type="number" name="capacity" value="60" min="1"></div>
                            <div class="form-group"><label>Floor</label><input type="number" name="floor_number" value="1" min="1"></div>
                            <div class="form-group"><div class="checkbox-group"><input type="checkbox" name="has_projector" id="r_proj" checked><label for="r_proj" style="margin-bottom:0;">Projector</label></div></div>
                            <div class="form-group"><div class="checkbox-group"><input type="checkbox" name="has_ac" id="r_ac" checked><label for="r_ac" style="margin-bottom:0;">AC</label></div></div>
                        </div>
                        <button type="submit" class="btn">Add Room</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Existing Rooms</div>
                <div class="content-box-body">
                    <table>
                        <tr><th>ID</th><th>Building</th><th>Room</th><th>Type</th><th>Capacity</th><th>Floor</th><th class="text-right">Actions</th></tr>
                        <?php foreach($rooms as $row): ?>
                        <tr>
                            <td><?php echo $row['room_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['building_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                            <td><span class="badge badge-blue"><?php echo $row['room_type']; ?></span></td>
                            <td><?php echo $row['capacity']; ?></td>
                            <td><?php echo $row['floor_number']; ?></td>
                            <td class="text-right">
                                <button class="btn btn-sm" onclick="toggleEdit('room-edit-<?php echo $row['room_id']; ?>')">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this room?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_room">
                                    <input type="hidden" name="room_id" value="<?php echo $row['room_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><svg width="14" height="14"><use href="#icon-delete"/></svg></button>
                                </form>
                            </td>
                        </tr>
                        <tr><td colspan="7">
                            <div id="room-edit-<?php echo $row['room_id']; ?>" class="edit-form">
                                <form method="POST">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="edit_room">
                                    <input type="hidden" name="room_id" value="<?php echo $row['room_id']; ?>">
                                    <div class="form-grid">
                                        <div class="form-group"><label>Building</label><select name="building_id" required><?php foreach($buildings_list as $b): ?><option value="<?php echo $b['building_id']; ?>" <?php echo $b['building_id']==$row['building_id']?'selected':''; ?>><?php echo htmlspecialchars($b['building_name']); ?></option><?php endforeach; ?></select></div>
                                        <div class="form-group"><label>Room Name</label><input type="text" name="room_name" value="<?php echo htmlspecialchars($row['room_name']); ?>" required></div>
                                        <div class="form-group"><label>Room Type</label><select name="room_type"><option value="classroom" <?php echo $row['room_type']=='classroom'?'selected':''; ?>>Classroom</option><option value="lab" <?php echo $row['room_type']=='lab'?'selected':''; ?>>Lab</option><option value="seminar" <?php echo $row['room_type']=='seminar'?'selected':''; ?>>Seminar</option><option value="auditorium" <?php echo $row['room_type']=='auditorium'?'selected':''; ?>>Auditorium</option></select></div>
                                        <div class="form-group"><label>Capacity</label><input type="number" name="capacity" value="<?php echo $row['capacity']; ?>" min="1"></div>
                                        <div class="form-group"><label>Floor</label><input type="number" name="floor_number" value="<?php echo $row['floor_number']; ?>" min="1"></div>
                                        <div class="form-group"><div class="checkbox-group"><input type="checkbox" name="has_projector" id="r_proj_<?php echo $row['room_id']; ?>" <?php echo $row['has_projector']?'checked':''; ?>><label for="r_proj_<?php echo $row['room_id']; ?>" style="margin-bottom:0;">Projector</label></div></div>
                                        <div class="form-group"><div class="checkbox-group"><input type="checkbox" name="has_ac" id="r_ac_<?php echo $row['room_id']; ?>" <?php echo $row['has_ac']?'checked':''; ?>><label for="r_ac_<?php echo $row['room_id']; ?>" style="margin-bottom:0;">AC</label></div></div>
                                    </div>
                                    <button type="submit" class="btn btn-sm">Save</button>
                                </form>
                            </div>
                        </td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== PREFERENCES ==================== -->
        <div id="preferences" class="tab-content <?php echo $active_tab=='preferences'?'active':''; ?>">
            <div class="content-box">
                <div class="content-box-header">Set Faculty Preference</div>
                <div class="content-box-body">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_preference">
                        <div class="form-grid">
                            <div class="form-group"><label>Faculty</label><select name="faculty_id" required><option value="">-- Select Faculty --</option><?php foreach($faculty_list as $r): ?><option value="<?php echo $r['faculty_id']; ?>"><?php echo htmlspecialchars($r['faculty_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Day</label><select name="day_id" required><option value="">-- Select Day --</option><?php foreach($working_days as $d): ?><option value="<?php echo $d['day_id']; ?>"><?php echo $d['day_name']; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Time Slot</label><select name="slot_id" required><option value="">-- Select Slot --</option><?php foreach($time_slots as $s): ?><option value="<?php echo $s['slot_id']; ?>"><?php echo $s['start_time']; ?> - <?php echo $s['end_time']; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Preference Level</label><select name="preference_level"><option value="preferred">Preferred</option><option value="neutral" selected>Neutral</option><option value="avoid">Avoid</option></select></div>
                        </div>
                        <button type="submit" class="btn">Save Preference</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Existing Preferences</div>
                <div class="content-box-body">
                    <div class="preference-grid">
                        <?php foreach($preferences as $row): ?>
                        <div class="preference-card <?php echo $row['preference_level']; ?>">
                            <div class="flex-between">
                                <strong><?php echo htmlspecialchars($row['faculty_name']); ?></strong>
                                <span class="badge <?php echo $row['preference_level']=='preferred'?'badge-green':($row['preference_level']=='avoid'?'badge-red':'badge-yellow'); ?>"><?php echo ucfirst($row['preference_level']); ?></span>
                            </div>
                            <div style="margin-top:8px;font-size:12px;color:#666;">
                                <?php echo $row['day_name']; ?> | <?php echo $row['start_time']; ?> - <?php echo $row['end_time']; ?>
                            </div>
                            <form method="POST" style="margin-top:10px;" onsubmit="return confirm('Delete this preference?');">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_preference">
                                <input type="hidden" name="preference_id" value="<?php echo $row['preference_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($preferences)): ?><div class="no-data">No preferences set yet.</div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== UNAVAILABLE ==================== -->
        <div id="unavailable" class="tab-content <?php echo $active_tab=='unavailable'?'active':''; ?>">
            <div class="content-box">
                <div class="content-box-header">Set Faculty Unavailable Slot</div>
                <div class="content-box-body">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_unavailable">
                        <div class="form-grid">
                            <div class="form-group"><label>Faculty</label><select name="faculty_id" required><option value="">-- Select Faculty --</option><?php foreach($faculty_list as $r): ?><option value="<?php echo $r['faculty_id']; ?>"><?php echo htmlspecialchars($r['faculty_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Day</label><select name="day_id" required><option value="">-- Select Day --</option><?php foreach($working_days as $d): ?><option value="<?php echo $d['day_id']; ?>"><?php echo $d['day_name']; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Time Slot</label><select name="slot_id" required><option value="">-- Select Slot --</option><?php foreach($time_slots as $s): ?><option value="<?php echo $s['slot_id']; ?>"><?php echo $s['start_time']; ?> - <?php echo $s['end_time']; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Reason</label><input type="text" name="reason" placeholder="e.g., Department Meeting"></div>
                        </div>
                        <button type="submit" class="btn">Block Slot</button>
                    </form>
                </div>
            </div>
            <div class="content-box">
                <div class="content-box-header">Blocked Slots</div>
                <div class="content-box-body">
                    <table>
                        <tr><th>Faculty</th><th>Day</th><th>Time</th><th>Reason</th><th class="text-right">Actions</th></tr>
                        <?php foreach($unavailable as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                            <td><?php echo $row['day_name']; ?></td>
                            <td><?php echo $row['start_time']; ?> - <?php echo $row['end_time']; ?></td>
                            <td><?php echo htmlspecialchars($row['reason']); ?></td>
                            <td class="text-right">
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this block?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_unavailable">
                                    <input type="hidden" name="unavailable_id" value="<?php echo $row['unavailable_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($unavailable)): ?><tr><td colspan="5" class="no-data">No unavailable slots set.</td></tr><?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleEdit(id) {
            var el = document.getElementById(id);
            el.classList.toggle('active');
        }
    </script>
</body>
</html>
