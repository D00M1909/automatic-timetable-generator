<?php
require_once 'config.php';

// ============================
// HANDLE ALL POST ACTIONS
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_year') {
        $name = $_POST['year_name'] ?? '';
        $status = $_POST['year_status'] ?? 'active';
        db_insert($conn, "INSERT INTO years (year_name, year_status) VALUES (?, ?)", "ss", [$name, $status]);
        audit_log($conn, 'ADD_YEAR', "Added year: $name");
        set_flash('success', 'Year added successfully!');
        header("Location: setup.php"); exit;
    }
    if ($action === 'edit_year') {
        $id = intval($_POST['year_id'] ?? 0);
        $name = $_POST['year_name'] ?? '';
        $status = $_POST['year_status'] ?? 'active';
        db_execute($conn, "UPDATE years SET year_name=?, year_status=? WHERE year_id=?", "ssi", [$name, $status, $id]);
        audit_log($conn, 'EDIT_YEAR', "Updated year ID: $id");
        set_flash('success', 'Year updated successfully!');
        header("Location: setup.php"); exit;
    }
    if ($action === 'delete_year') {
        $id = intval($_POST['year_id'] ?? 0);
        db_execute($conn, "DELETE FROM years WHERE year_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_YEAR', "Deleted year ID: $id");
        set_flash('success', 'Year deleted successfully!');
        header("Location: setup.php"); exit;
    }

    if ($action === 'add_class') {
        $year_id = intval($_POST['year_id'] ?? 0);
        $name = $_POST['class_name'] ?? '';
        $code = $_POST['class_code'] ?? '';
        $strength = intval($_POST['strength'] ?? 0);
        db_insert($conn, "INSERT INTO classes (year_id, class_name, class_code, strength) VALUES (?, ?, ?, ?)", "issi", [$year_id, $name, $code, $strength]);
        audit_log($conn, 'ADD_CLASS', "Added class: $name");
        set_flash('success', 'Class added successfully!');
        header("Location: setup.php"); exit;
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
        header("Location: setup.php"); exit;
    }
    if ($action === 'delete_class') {
        $id = intval($_POST['class_id'] ?? 0);
        db_execute($conn, "DELETE FROM classes WHERE class_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_CLASS', "Deleted class ID: $id");
        set_flash('success', 'Class deleted successfully!');
        header("Location: setup.php"); exit;
    }

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
        header("Location: setup.php"); exit;
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
        header("Location: setup.php"); exit;
    }
    if ($action === 'delete_faculty') {
        $id = intval($_POST['faculty_id'] ?? 0);
        db_execute($conn, "DELETE FROM faculty WHERE faculty_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_FACULTY', "Deleted faculty ID: $id");
        set_flash('success', 'Faculty deleted successfully!');
        header("Location: setup.php"); exit;
    }

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
        header("Location: setup.php"); exit;
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
        header("Location: setup.php"); exit;
    }
    if ($action === 'delete_subject') {
        $id = intval($_POST['subject_id'] ?? 0);
        db_execute($conn, "DELETE FROM subjects WHERE subject_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_SUBJECT', "Deleted subject ID: $id");
        set_flash('success', 'Subject deleted successfully!');
        header("Location: setup.php"); exit;
    }

    if ($action === 'add_assignment') {
        $class_id = intval($_POST['class_id'] ?? 0);
        $subject_id = intval($_POST['subject_id'] ?? 0);
        $faculty_id = intval($_POST['faculty_id'] ?? 0);
        db_insert($conn, "INSERT INTO subject_assignments (class_id, subject_id, faculty_id) VALUES (?, ?, ?)", "iii", [$class_id, $subject_id, $faculty_id]);
        audit_log($conn, 'ADD_ASSIGNMENT', "Added assignment: class=$class_id, subject=$subject_id, faculty=$faculty_id");
        set_flash('success', 'Assignment added successfully!');
        header("Location: setup.php"); exit;
    }
    if ($action === 'delete_assignment') {
        $id = intval($_POST['assignment_id'] ?? 0);
        db_execute($conn, "DELETE FROM subject_assignments WHERE assignment_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_ASSIGNMENT', "Deleted assignment ID: $id");
        set_flash('success', 'Assignment deleted successfully!');
        header("Location: setup.php"); exit;
    }

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
        header("Location: setup.php"); exit;
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
        header("Location: setup.php"); exit;
    }
    if ($action === 'delete_room') {
        $id = intval($_POST['room_id'] ?? 0);
        db_execute($conn, "DELETE FROM rooms WHERE room_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_ROOM', "Deleted room ID: $id");
        set_flash('success', 'Room deleted successfully!');
        header("Location: setup.php"); exit;
    }

    if ($action === 'add_building') {
        $name = $_POST['building_name'] ?? '';
        $has_ac = isset($_POST['has_ac']) ? 1 : 0;
        $rating = $_POST['energy_rating'] ?? 'B';
        db_insert($conn, "INSERT INTO buildings (building_name, has_ac, energy_rating) VALUES (?, ?, ?)", "sis", [$name, $has_ac, $rating]);
        audit_log($conn, 'ADD_BUILDING', "Added building: $name");
        set_flash('success', 'Building added successfully!');
        header("Location: setup.php"); exit;
    }
    if ($action === 'edit_building') {
        $id = intval($_POST['building_id'] ?? 0);
        $name = $_POST['building_name'] ?? '';
        $has_ac = isset($_POST['has_ac']) ? 1 : 0;
        $rating = $_POST['energy_rating'] ?? 'B';
        db_execute($conn, "UPDATE buildings SET building_name=?, has_ac=?, energy_rating=? WHERE building_id=?", "sisi", [$name, $has_ac, $rating, $id]);
        audit_log($conn, 'EDIT_BUILDING', "Updated building ID: $id");
        set_flash('success', 'Building updated successfully!');
        header("Location: setup.php"); exit;
    }
    if ($action === 'delete_building') {
        $id = intval($_POST['building_id'] ?? 0);
        db_execute($conn, "DELETE FROM buildings WHERE building_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_BUILDING', "Deleted building ID: $id");
        set_flash('success', 'Building deleted successfully!');
        header("Location: setup.php"); exit;
    }

    if ($action === 'add_preference') {
        $faculty_id = intval($_POST['faculty_id'] ?? 0);
        $day_id = intval($_POST['day_id'] ?? 0);
        $slot_id = intval($_POST['slot_id'] ?? 0);
        $level = $_POST['preference_level'] ?? 'neutral';
        db_execute($conn, "INSERT INTO faculty_preferences (faculty_id, day_id, slot_id, preference_level) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE preference_level=?", "iiiss", [$faculty_id, $day_id, $slot_id, $level, $level]);
        audit_log($conn, 'ADD_PREFERENCE', "Set preference for faculty=$faculty_id");
        set_flash('success', 'Preference saved successfully!');
        header("Location: setup.php"); exit;
    }
    if ($action === 'delete_preference') {
        $id = intval($_POST['preference_id'] ?? 0);
        db_execute($conn, "DELETE FROM faculty_preferences WHERE preference_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_PREFERENCE', "Deleted preference ID: $id");
        set_flash('success', 'Preference deleted successfully!');
        header("Location: setup.php"); exit;
    }

    if ($action === 'add_unavailable') {
        $faculty_id = intval($_POST['faculty_id'] ?? 0);
        $day_id = intval($_POST['day_id'] ?? 0);
        $slot_id = intval($_POST['slot_id'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        db_execute($conn, "INSERT INTO faculty_unavailable (faculty_id, day_id, slot_id, reason) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason=?", "iiiss", [$faculty_id, $day_id, $slot_id, $reason, $reason]);
        audit_log($conn, 'ADD_UNAVAILABLE', "Set unavailable for faculty=$faculty_id");
        set_flash('success', 'Unavailable slot saved successfully!');
        header("Location: setup.php"); exit;
    }
    if ($action === 'delete_unavailable') {
        $id = intval($_POST['unavailable_id'] ?? 0);
        db_execute($conn, "DELETE FROM faculty_unavailable WHERE unavailable_id=?", "i", [$id]);
        audit_log($conn, 'DELETE_UNAVAILABLE', "Deleted unavailable ID: $id");
        set_flash('success', 'Unavailable slot deleted successfully!');
        header("Location: setup.php"); exit;
    }
}

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

$years_list = db_get_rows($conn, "SELECT * FROM years WHERE year_status='active' ORDER BY year_id DESC");
$classes_list = db_get_rows($conn, "SELECT * FROM classes ORDER BY class_name");
$faculty_list = db_get_rows($conn, "SELECT * FROM faculty ORDER BY faculty_name");
$subjects_list = db_get_rows($conn, "SELECT * FROM subjects ORDER BY subject_name");
$buildings_list = db_get_rows($conn, "SELECT * FROM buildings ORDER BY building_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Data - AI Smart Timetable</title>
    <?php common_styles(); ?>
    <style>
        .stats-bar { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-pill { background: white; border-radius: 12px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 12px; min-width: 140px; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid rgba(0,0,0,0.04); }
        .stat-pill:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .stat-pill .icon-wrap { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .stat-pill .icon-wrap svg { width: 22px; height: 22px; fill: white; }
        .stat-pill .content { display: flex; flex-direction: column; }
        .stat-pill .num { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1; }
        .stat-pill .lbl { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
        .stat-pill.years .icon-wrap { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-pill.classes .icon-wrap { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .stat-pill.faculty .icon-wrap { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .stat-pill.subjects .icon-wrap { background: linear-gradient(135deg, #43e97b, #38f9d7); }
        .stat-pill.assignments .icon-wrap { background: linear-gradient(135deg, #fa709a, #fee140); }
        .stat-pill.rooms .icon-wrap { background: linear-gradient(135deg, #a8edea, #fed6e3); }
        .stat-pill.rooms .icon-wrap svg { fill: #6B1B5E; }

        .section-card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04); margin-bottom: 16px; overflow: hidden; border: 1px solid rgba(0,0,0,0.04); transition: box-shadow 0.3s; }
        .section-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .section-header { padding: 16px 24px; background: linear-gradient(135deg, #6B1B5E 0%, #8e2a7c 100%); color: white; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center; user-select: none; transition: background 0.2s; }
        .section-header:hover { background: linear-gradient(135deg, #5a1850 0%, #7a2369 100%); }
        .section-header .header-left { display: flex; align-items: center; gap: 12px; }
        .section-header .header-left svg { width: 20px; height: 20px; fill: currentColor; opacity: 0.9; }
        .section-header .toggle-icon { width: 28px; height: 28px; border-radius: 6px; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .section-header .toggle-icon svg { width: 16px; height: 16px; fill: white; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .section-header.collapsed .toggle-icon svg { transform: rotate(-90deg); }
        
        /* Updated Smooth Transitions */
        .section-body { padding: 24px; display: grid; grid-template-rows: 1fr; transition: grid-template-rows 0.4s ease-in-out, padding 0.4s ease-in-out; }
        .section-body > div.inner-wrapper { overflow: hidden; }
        .section-body.collapsed { grid-template-rows: 0fr; padding: 0 24px; }

        .form-section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: #6B1B5E; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #f0e6f5; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .form-group { margin-bottom: 0; }
        .form-group label { font-size: 12px; font-weight: 600; color: #555; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
        .form-group input, .form-group select, .form-group textarea { border: 1.5px solid #e0e0e0; border-radius: 8px; padding: 10px 12px; font-size: 13px; transition: all 0.2s; background: #fafafa; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #6B1B5E; background: white; box-shadow: 0 0 0 3px rgba(107, 27, 94, 0.08); outline: none; }
        .form-group select { cursor: pointer; }
        .btn-submit { margin-top: 8px; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; border: none; cursor: pointer; color: white; }
        .btn-submit svg { width: 16px; height: 16px; fill: currentColor; }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(107, 27, 94, 0.25); }
        .btn-submit:active { transform: translateY(0); }

        .checkbox-group { display: flex; align-items: center; gap: 10px; padding: 8px 0; }
        .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; accent-color: #6B1B5E; cursor: pointer; }
        .checkbox-group label { margin-bottom: 0; cursor: pointer; font-size: 13px; color: #444; }

        .data-table { max-height: 320px; overflow-y: auto; border-radius: 8px; border: 1px solid #eee; margin-top: 15px;}
        .data-table table { font-size: 13px; border-collapse: separate; border-spacing: 0; }
        .data-table th { background: linear-gradient(135deg, #f8f9fa, #f0f2f5); color: #555; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 16px; border-bottom: 2px solid #e0e0e0; position: sticky; top: 0; z-index: 10; }
        .data-table td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .data-table tr:hover td { background: #faf8fb; }
        .data-table tr:last-child td { border-bottom: none; }

        .action-btn { padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; }
        .action-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.12); }
        .action-btn svg { width: 14px; height: 14px; fill: currentColor; }
        .action-btn.edit { background: #e3f2fd; color: #1976d2; }
        .action-btn.edit:hover { background: #bbdefb; }
        .action-btn.delete { background: #ffebee; color: #c62828; }
        .action-btn.delete:hover { background: #ffcdd2; }

        .edit-form { display: none; background: linear-gradient(135deg, #faf8fb, #f5f0f7); padding: 20px; border-radius: 10px; margin: 8px 0; border: 1.5px solid #e8d5f0; animation: slideDown 0.3s ease; }
        .edit-form.active { display: block; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .preference-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; }
        .preference-card { background: white; padding: 16px 20px; border-radius: 10px; border: 1.5px solid #e0e0e0; border-left: 4px solid #00BFA5; transition: all 0.2s; position: relative; overflow: hidden; }
        .preference-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #00BFA5, #00d4b8); opacity: 0; transition: opacity 0.2s; }
        .preference-card:hover::before { opacity: 1; }
        .preference-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .preference-card.avoid { border-left-color: #e74c3c; }
        .preference-card.avoid::before { background: linear-gradient(90deg, #e74c3c, #ff6b6b); }
        .preference-card.preferred { border-left-color: #27ae60; }
        .preference-card.preferred::before { background: linear-gradient(90deg, #27ae60, #2ecc71); }
        .preference-card .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .preference-card .faculty-name { font-weight: 700; color: #333; font-size: 14px; }
        .preference-card .meta { font-size: 12px; color: #888; display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .preference-card .meta svg { width: 14px; height: 14px; fill: #aaa; }

        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }

        .section-subtitle { font-size: 13px; font-weight: 700; color: #444; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .section-subtitle svg { width: 18px; height: 18px; fill: #6B1B5E; }

        .empty-state { text-align: center; padding: 40px; color: #999; font-size: 13px; }
        .empty-state svg { width: 48px; height: 48px; fill: #ddd; margin-bottom: 12px; }

        .data-table::-webkit-scrollbar { width: 6px; }
        .data-table::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
        .data-table::-webkit-scrollbar-thumb { background: #c0c0c0; border-radius: 3px; }
        .data-table::-webkit-scrollbar-thumb:hover { background: #a0a0a0; }

        @media (max-width: 768px) { .stats-bar { gap: 8px; } .stat-pill { min-width: 120px; padding: 12px 16px; } .section-body { padding: 16px; } .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php svg_defs(); ?>
    <?php sidebar('setup'); ?>
    <?php top_header(); ?>

    <div class="content-wrapper">
        <?php breadcrumb(['Manage Time Table', 'Manage Data']); ?>
        <?php flash_message(); ?>

        <div class="stats-bar">
            <div class="stat-pill years">
                <div class="icon-wrap"><svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg></div>
                <div class="content"><div class="num"><?php echo count($years); ?></div><div class="lbl">Years</div></div>
            </div>
            <div class="stat-pill classes">
                <div class="icon-wrap"><svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg></div>
                <div class="content"><div class="num"><?php echo count($classes); ?></div><div class="lbl">Classes</div></div>
            </div>
            <div class="stat-pill faculty">
                <div class="icon-wrap"><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div>
                <div class="content"><div class="num"><?php echo count($faculty); ?></div><div class="lbl">Faculty</div></div>
            </div>
            <div class="stat-pill subjects">
                <div class="icon-wrap"><svg viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg></div>
                <div class="content"><div class="num"><?php echo count($subjects); ?></div><div class="lbl">Subjects</div></div>
            </div>
            <div class="stat-pill assignments">
                <div class="icon-wrap"><svg viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg></div>
                <div class="content"><div class="num"><?php echo count($assignments); ?></div><div class="lbl">Assignments</div></div>
            </div>
            <div class="stat-pill rooms">
                <div class="icon-wrap"><svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg></div>
                <div class="content"><div class="num"><?php echo count($rooms); ?></div><div class="lbl">Rooms</div></div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header" onclick="toggleSection('sec-years')">
                <div class="header-left">
                    <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
                    Academic Years
                </div>
                <div class="toggle-icon"><svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg></div>
            </div>
            <div class="section-body" id="sec-years">
                <div class="inner-wrapper">
                    <div class="form-section-title">Add New Academic Year</div>
                    <form method="POST" style="margin-bottom:20px;" class="track-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_year">
                        <div class="form-grid">
                            <div class="form-group"><label>Year Name</label><input type="text" name="year_name" required placeholder="e.g., First Year"></div>
                            <div class="form-group"><label>Status</label><select name="year_status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                        </div>
                        <button type="submit" class="btn btn-submit btn-success" style="background:#27ae60;">
                            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Add Year
                        </button>
                    </form>
                    <div class="data-table">
                        <table>
                            <tr><th>ID</th><th>Year Name</th><th>Status</th><th class="text-right">Actions</th></tr>
                            <?php foreach($years as $row): ?>
                            <tr>
                                <td><?php echo $row['year_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['year_name']); ?></td>
                                <td><span class="badge <?php echo $row['year_status']=='active'?'badge-green':'badge-yellow'; ?>"><?php echo $row['year_status']; ?></span></td>
                                <td class="text-right">
                                    <button type="button" class="action-btn edit" onclick="toggleEdit('year-edit-<?php echo $row['year_id']; ?>')">
                                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg> Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this year?');" class="track-form">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_year">
                                        <input type="hidden" name="year_id" value="<?php echo $row['year_id']; ?>">
                                        <button type="submit" class="action-btn delete"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                    </form>
                                </td>
                            </tr>
                            <tr><td colspan="4" style="padding:0;">
                                <div id="year-edit-<?php echo $row['year_id']; ?>" class="edit-form">
                                    <div class="form-section-title">Edit Year</div>
                                    <form method="POST" class="track-form">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="edit_year">
                                        <input type="hidden" name="year_id" value="<?php echo $row['year_id']; ?>">
                                        <div class="form-grid">
                                            <div class="form-group"><label>Year Name</label><input type="text" name="year_name" value="<?php echo htmlspecialchars($row['year_name']); ?>" required></div>
                                            <div class="form-group"><label>Status</label><select name="year_status"><option value="active" <?php echo $row['year_status']=='active'?'selected':''; ?>>Active</option><option value="inactive" <?php echo $row['year_status']=='inactive'?'selected':''; ?>>Inactive</option></select></div>
                                        </div>
                                        <button type="submit" class="btn btn-submit" style="background:#3498db;"><svg viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg> Save Changes</button>
                                    </form>
                                </div>
                            </td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header collapsed" onclick="toggleSection('sec-classes')">
                <div class="header-left">
                    <svg viewBox="0 0 24 24"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg> Classes
                </div>
                <div class="toggle-icon"><svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg></div>
            </div>
            <div class="section-body collapsed" id="sec-classes">
                <div class="inner-wrapper">
                    <div class="form-section-title">Add New Class</div>
                    <form method="POST" style="margin-bottom:20px;" class="track-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_class">
                        <div class="form-grid">
                            <div class="form-group"><label>Select Year</label><select name="year_id" required><option value="">-- Select Year --</option><?php foreach($years_list as $y): ?><option value="<?php echo $y['year_id']; ?>"><?php echo htmlspecialchars($y['year_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Class Name</label><input type="text" name="class_name" required placeholder="e.g., Class A"></div>
                            <div class="form-group"><label>Class Code</label><input type="text" name="class_code" required placeholder="e.g., FY-A"></div>
                            <div class="form-group"><label>Student Strength</label><input type="number" name="strength" value="0" min="0"></div>
                        </div>
                        <button type="submit" class="btn btn-submit btn-success" style="background:#27ae60;">
                            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Add Class
                        </button>
                    </form>
                    <div class="data-table">
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
                                    <button type="button" class="action-btn edit" onclick="toggleEdit('class-edit-<?php echo $row['class_id']; ?>')">
                                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg> Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this class?');" class="track-form">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_class">
                                        <input type="hidden" name="class_id" value="<?php echo $row['class_id']; ?>">
                                        <button type="submit" class="action-btn delete"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                    </form>
                                </td>
                            </tr>
                            <tr><td colspan="6" style="padding:0;">
                                <div id="class-edit-<?php echo $row['class_id']; ?>" class="edit-form">
                                    <div class="form-section-title">Edit Class</div>
                                    <form method="POST" class="track-form">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="edit_class">
                                        <input type="hidden" name="class_id" value="<?php echo $row['class_id']; ?>">
                                        <div class="form-grid">
                                            <div class="form-group"><label>Select Year</label><select name="year_id" required><?php foreach($years_list as $y): ?><option value="<?php echo $y['year_id']; ?>" <?php echo $y['year_id']==$row['year_id']?'selected':''; ?>><?php echo htmlspecialchars($y['year_name']); ?></option><?php endforeach; ?></select></div>
                                            <div class="form-group"><label>Class Name</label><input type="text" name="class_name" value="<?php echo htmlspecialchars($row['class_name']); ?>" required></div>
                                            <div class="form-group"><label>Class Code</label><input type="text" name="class_code" value="<?php echo htmlspecialchars($row['class_code']); ?>" required></div>
                                            <div class="form-group"><label>Student Strength</label><input type="number" name="strength" value="<?php echo $row['strength']; ?>" min="0"></div>
                                        </div>
                                        <button type="submit" class="btn btn-submit" style="background:#3498db;"><svg viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg> Save Changes</button>
                                    </form>
                                </div>
                            </td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header collapsed" onclick="toggleSection('sec-faculty')">
                <div class="header-left">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg> Faculty
                </div>
                <div class="toggle-icon"><svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg></div>
            </div>
            <div class="section-body collapsed" id="sec-faculty">
                <div class="inner-wrapper">
                    <div class="form-section-title">Add New Faculty</div>
                    <form method="POST" style="margin-bottom:20px;" class="track-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_faculty">
                        <div class="form-grid">
                            <div class="form-group"><label>Faculty Name</label><input type="text" name="faculty_name" required placeholder="Full name"></div>
                            <div class="form-group"><label>Faculty Code</label><input type="text" name="faculty_code" required placeholder="e.g., DS"></div>
                            <div class="form-group"><label>Department</label><input type="text" name="department" placeholder="e.g., Computer Science"></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="faculty@university.edu"></div>
                            <div class="form-group"><label>Phone</label><input type="text" name="phone" placeholder="+91..."></div>
                            <div class="form-group"><label>Max Hours/Day</label><input type="number" name="max_hours_per_day" value="6" min="1" max="12"></div>
                            <div class="form-group"><label>Max Hours/Week</label><input type="number" name="max_hours_per_week" value="30" min="1" max="60"></div>
                        </div>
                        <button type="submit" class="btn btn-submit btn-success" style="background:#27ae60;">
                            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Add Faculty
                        </button>
                    </form>
                    <div class="data-table">
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
                                    <button type="button" class="action-btn edit" onclick="toggleEdit('faculty-edit-<?php echo $row['faculty_id']; ?>')">
                                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg> Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this faculty?');" class="track-form">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_faculty">
                                        <input type="hidden" name="faculty_id" value="<?php echo $row['faculty_id']; ?>">
                                        <button type="submit" class="action-btn delete"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                    </form>
                                </td>
                            </tr>
                            <tr><td colspan="7" style="padding:0;">
                                <div id="faculty-edit-<?php echo $row['faculty_id']; ?>" class="edit-form">
                                    <div class="form-section-title">Edit Faculty</div>
                                    <form method="POST" class="track-form">
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
                                        <button type="submit" class="btn btn-submit" style="background:#3498db;"><svg viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg> Save Changes</button>
                                    </form>
                                </div>
                            </td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header collapsed" onclick="toggleSection('sec-subjects')">
                <div class="header-left">
                    <svg viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg> Subjects
                </div>
                <div class="toggle-icon"><svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg></div>
            </div>
            <div class="section-body collapsed" id="sec-subjects">
                <div class="inner-wrapper">
                    <div class="form-section-title">Add New Subject</div>
                    <form method="POST" style="margin-bottom:20px;" class="track-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_subject">
                        <div class="form-grid">
                            <div class="form-group"><label>Subject Name</label><input type="text" name="subject_name" required placeholder="e.g., Data Structures"></div>
                            <div class="form-group"><label>Subject Code</label><input type="text" name="subject_code" required placeholder="e.g., CS201"></div>
                            <div class="form-group"><label>Subject Type</label><select name="subject_type"><option value="lecture">Lecture Only</option><option value="lab">Lab Only</option><option value="both">Both</option></select></div>
                            <div class="form-group"><label>Lecture Hours/Week</label><input type="number" name="lecture_hours_per_week" value="3" min="0"></div>
                            <div class="form-group"><label>Lab Hours/Week</label><input type="number" name="lab_hours_per_week" value="2" min="0"></div>
                            <div class="form-group"><label>Department</label><input type="text" name="department" placeholder="e.g., Computer Science"></div>
                        </div>
                        <button type="submit" class="btn btn-submit btn-success" style="background:#27ae60;">
                            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Add Subject
                        </button>
                    </form>
                    <div class="data-table">
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
                                    <button type="button" class="action-btn edit" onclick="toggleEdit('subject-edit-<?php echo $row['subject_id']; ?>')">
                                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg> Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this subject?');" class="track-form">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_subject">
                                        <input type="hidden" name="subject_id" value="<?php echo $row['subject_id']; ?>">
                                        <button type="submit" class="action-btn delete"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                    </form>
                                </td>
                            </tr>
                            <tr><td colspan="7" style="padding:0;">
                                <div id="subject-edit-<?php echo $row['subject_id']; ?>" class="edit-form">
                                    <div class="form-section-title">Edit Subject</div>
                                    <form method="POST" class="track-form">
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
                                        <button type="submit" class="btn btn-submit" style="background:#3498db;"><svg viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg> Save Changes</button>
                                    </form>
                                </div>
                            </td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header collapsed" onclick="toggleSection('sec-assignments')">
                <div class="header-left">
                    <svg viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg> Assignments
                </div>
                <div class="toggle-icon"><svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg></div>
            </div>
            <div class="section-body collapsed" id="sec-assignments">
                <div class="inner-wrapper">
                    <div class="form-section-title">Add New Assignment</div>
                    <form method="POST" style="margin-bottom:20px;" class="track-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_assignment">
                        <div class="form-grid">
                            <div class="form-group"><label>Select Class</label><select name="class_id" required><option value="">-- Select Class --</option><?php foreach($classes_list as $r): ?><option value="<?php echo $r['class_id']; ?>"><?php echo htmlspecialchars($r['class_name']); ?> (<?php echo htmlspecialchars($r['class_code']); ?>)</option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Select Subject</label><select name="subject_id" required><option value="">-- Select Subject --</option><?php foreach($subjects_list as $r): ?><option value="<?php echo $r['subject_id']; ?>"><?php echo htmlspecialchars($r['subject_name']); ?> (<?php echo htmlspecialchars($r['subject_code']); ?>)</option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Select Faculty</label><select name="faculty_id" required><option value="">-- Select Faculty --</option><?php foreach($faculty_list as $r): ?><option value="<?php echo $r['faculty_id']; ?>"><?php echo htmlspecialchars($r['faculty_name']); ?> (<?php echo htmlspecialchars($r['faculty_code']); ?>)</option><?php endforeach; ?></select></div>
                        </div>
                        <button type="submit" class="btn btn-submit btn-success" style="background:#27ae60;">
                            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Add Assignment
                        </button>
                    </form>
                    <div class="data-table">
                        <table>
                            <tr><th>ID</th><th>Class</th><th>Subject</th><th>Faculty</th><th class="text-right">Actions</th></tr>
                            <?php foreach($assignments as $row): ?>
                            <tr>
                                <td><?php echo $row['assignment_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                                <td class="text-right">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this assignment?');" class="track-form">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_assignment">
                                        <input type="hidden" name="assignment_id" value="<?php echo $row['assignment_id']; ?>">
                                        <button type="submit" class="action-btn delete"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header collapsed" onclick="toggleSection('sec-rooms')">
                <div class="header-left">
                    <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg> Rooms & Buildings
                </div>
                <div class="toggle-icon"><svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg></div>
            </div>
            <div class="section-body collapsed" id="sec-rooms">
                <div class="inner-wrapper">
                    <div class="two-col">
                        <div>
                            <div class="section-subtitle"><svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg> Add Building</div>
                            <form method="POST" style="margin-bottom:20px;" class="track-form">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="add_building">
                                <div class="form-grid">
                                    <div class="form-group"><label>Building Name</label><input type="text" name="building_name" required></div>
                                    <div class="form-group"><label>Energy Rating</label><select name="energy_rating"><option value="A">A (Best)</option><option value="B">B</option><option value="C">C</option></select></div>
                                    <div class="form-group"><div class="checkbox-group"><input type="checkbox" name="has_ac" id="b_ac" checked><label for="b_ac" style="margin-bottom:0;">Has AC</label></div></div>
                                </div>
                                <button type="submit" class="btn btn-submit btn-success" style="background:#27ae60;">
                                    <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Add Building
                                </button>
                            </form>
                            <div class="data-table">
                                <table>
                                    <tr><th>ID</th><th>Name</th><th>AC</th><th>Rating</th><th class="text-right">Actions</th></tr>
                                    <?php foreach($buildings as $row): ?>
                                    <tr>
                                        <td><?php echo $row['building_id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['building_name']); ?></td>
                                        <td><?php echo $row['has_ac'] ? 'Yes' : 'No'; ?></td>
                                        <td><span class="badge badge-purple"><?php echo $row['energy_rating']; ?></span></td>
                                        <td class="text-right">
                                            <button type="button" class="action-btn edit" onclick="toggleEdit('building-edit-<?php echo $row['building_id']; ?>')">
                                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg> Edit
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this building?');" class="track-form">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_building">
                                                <input type="hidden" name="building_id" value="<?php echo $row['building_id']; ?>">
                                                <button type="submit" class="action-btn delete"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr><td colspan="5" style="padding:0;">
                                        <div id="building-edit-<?php echo $row['building_id']; ?>" class="edit-form">
                                            <div class="form-section-title">Edit Building</div>
                                            <form method="POST" class="track-form">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="edit_building">
                                                <input type="hidden" name="building_id" value="<?php echo $row['building_id']; ?>">
                                                <div class="form-grid">
                                                    <div class="form-group"><label>Building Name</label><input type="text" name="building_name" value="<?php echo htmlspecialchars($row['building_name']); ?>" required></div>
                                                    <div class="form-group"><label>Energy Rating</label><select name="energy_rating"><option value="A" <?php echo $row['energy_rating']=='A'?'selected':''; ?>>A</option><option value="B" <?php echo $row['energy_rating']=='B'?'selected':''; ?>>B</option><option value="C" <?php echo $row['energy_rating']=='C'?'selected':''; ?>>C</option></select></div>
                                                    <div class="form-group"><div class="checkbox-group"><input type="checkbox" name="has_ac" id="b_ac_<?php echo $row['building_id']; ?>" <?php echo $row['has_ac']?'checked':''; ?>><label for="b_ac_<?php echo $row['building_id']; ?>" style="margin-bottom:0;">Has AC</label></div></div>
                                                </div>
                                                <button type="submit" class="btn btn-submit" style="background:#3498db;"><svg viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg> Save Changes</button>
                                            </form>
                                        </div>
                                    </td></tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </div>
                        <div>
                            <div class="section-subtitle"><svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg> Add Room</div>
                            <form method="POST" style="margin-bottom:20px;" class="track-form">
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
                                <button type="submit" class="btn btn-submit btn-success" style="background:#27ae60;">
                                    <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Add Room
                                </button>
                            </form>
                            <div class="data-table">
                                <table>
                                    <tr><th>ID</th><th>Building</th><th>Room</th><th>Type</th><th>Cap</th><th class="text-right">Actions</th></tr>
                                    <?php foreach($rooms as $row): ?>
                                    <tr>
                                        <td><?php echo $row['room_id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['building_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['room_name']); ?></td>
                                        <td><span class="badge badge-blue"><?php echo $row['room_type']; ?></span></td>
                                        <td><?php echo $row['capacity']; ?></td>
                                        <td class="text-right">
                                            <button type="button" class="action-btn edit" onclick="toggleEdit('room-edit-<?php echo $row['room_id']; ?>')">
                                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg> Edit
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this room?');" class="track-form">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_room">
                                                <input type="hidden" name="room_id" value="<?php echo $row['room_id']; ?>">
                                                <button type="submit" class="action-btn delete"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr><td colspan="6" style="padding:0;">
                                        <div id="room-edit-<?php echo $row['room_id']; ?>" class="edit-form">
                                            <div class="form-section-title">Edit Room</div>
                                            <form method="POST" class="track-form">
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
                                                <button type="submit" class="btn btn-submit" style="background:#3498db;"><svg viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg> Save Changes</button>
                                            </form>
                                        </div>
                                    </td></tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header collapsed" onclick="toggleSection('sec-preferences')">
                <div class="header-left">
                    <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg> Faculty Preferences
                </div>
                <div class="toggle-icon"><svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg></div>
            </div>
            <div class="section-body collapsed" id="sec-preferences">
                <div class="inner-wrapper">
                    <div class="form-section-title">Set Faculty Preference</div>
                    <form method="POST" style="margin-bottom:20px;" class="track-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_preference">
                        <div class="form-grid">
                            <div class="form-group"><label>Faculty</label><select name="faculty_id" required><option value="">-- Select Faculty --</option><?php foreach($faculty_list as $r): ?><option value="<?php echo $r['faculty_id']; ?>"><?php echo htmlspecialchars($r['faculty_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Day</label><select name="day_id" required><option value="">-- Select Day --</option><?php foreach($working_days as $d): ?><option value="<?php echo $d['day_id']; ?>"><?php echo $d['day_name']; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Time Slot</label><select name="slot_id" required><option value="">-- Select Slot --</option><?php foreach($time_slots as $s): ?><option value="<?php echo $s['slot_id']; ?>"><?php echo $s['start_time']; ?> - <?php echo $s['end_time']; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Preference Level</label><select name="preference_level"><option value="preferred">Preferred</option><option value="neutral" selected>Neutral</option><option value="avoid">Avoid</option></select></div>
                        </div>
                        <button type="submit" class="btn btn-submit btn-success" style="background:#27ae60;">
                            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Save Preference
                        </button>
                    </form>
                    <div class="preference-grid">
                        <?php foreach($preferences as $row): ?>
                        <div class="preference-card <?php echo $row['preference_level']; ?>">
                            <div class="card-header">
                                <span class="faculty-name"><?php echo htmlspecialchars($row['faculty_name']); ?></span>
                                <span class="badge <?php echo $row['preference_level']=='preferred'?'badge-green':($row['preference_level']=='avoid'?'badge-red':'badge-yellow'); ?>"><?php echo ucfirst($row['preference_level']); ?></span>
                            </div>
                            <div class="meta">
                                <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg> <?php echo $row['day_name']; ?>
                                <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg> <?php echo $row['start_time']; ?> - <?php echo $row['end_time']; ?>
                            </div>
                            <form method="POST" onsubmit="return confirm('Delete this preference?');" class="track-form">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_preference">
                                <input type="hidden" name="preference_id" value="<?php echo $row['preference_id']; ?>">
                                <button type="submit" class="action-btn delete"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg> Remove</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($preferences)): ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            <div>No preferences set yet.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header collapsed" onclick="toggleSection('sec-unavailable')">
                <div class="header-left">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg> Faculty Unavailable Slots
                </div>
                <div class="toggle-icon"><svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg></div>
            </div>
            <div class="section-body collapsed" id="sec-unavailable">
                <div class="inner-wrapper">
                    <div class="form-section-title">Block Unavailable Slot</div>
                    <form method="POST" style="margin-bottom:20px;" class="track-form">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_unavailable">
                        <div class="form-grid">
                            <div class="form-group"><label>Faculty</label><select name="faculty_id" required><option value="">-- Select Faculty --</option><?php foreach($faculty_list as $r): ?><option value="<?php echo $r['faculty_id']; ?>"><?php echo htmlspecialchars($r['faculty_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Day</label><select name="day_id" required><option value="">-- Select Day --</option><?php foreach($working_days as $d): ?><option value="<?php echo $d['day_id']; ?>"><?php echo $d['day_name']; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Time Slot</label><select name="slot_id" required><option value="">-- Select Slot --</option><?php foreach($time_slots as $s): ?><option value="<?php echo $s['slot_id']; ?>"><?php echo $s['start_time']; ?> - <?php echo $s['end_time']; ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Reason</label><input type="text" name="reason" placeholder="e.g., Department Meeting"></div>
                        </div>
                        <button type="submit" class="btn btn-submit btn-success" style="background:#e74c3c;">
                            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg> Block Slot
                        </button>
                    </form>
                    <div class="data-table">
                        <table>
                            <tr><th>Faculty</th><th>Day</th><th>Time</th><th>Reason</th><th class="text-right">Actions</th></tr>
                            <?php foreach($unavailable as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['faculty_name']); ?></td>
                                <td><?php echo $row['day_name']; ?></td>
                                <td><?php echo $row['start_time']; ?> - <?php echo $row['end_time']; ?></td>
                                <td><?php echo htmlspecialchars($row['reason']); ?></td>
                                <td class="text-right">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this block?');" class="track-form">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_unavailable">
                                        <input type="hidden" name="unavailable_id" value="<?php echo $row['unavailable_id']; ?>">
                                        <button type="submit" class="action-btn delete"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg> Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($unavailable)): ?>
                            <tr><td colspan="5" class="empty-state">
                                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                                <div>No unavailable slots set.</div>
                            </td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Restore active section only if saved
            var openSectionId = localStorage.getItem('adypu_active_section');
            if (openSectionId) {
                var el = document.getElementById(openSectionId);
                if (el) {
                    // Make sure this section is open and all others are closed
                    document.querySelectorAll('.section-body').forEach(function(sec) {
                        sec.classList.add('collapsed');
                        sec.previousElementSibling.classList.add('collapsed');
                    });
                    el.classList.remove('collapsed');
                    el.previousElementSibling.classList.remove('collapsed');
                } else {
                    // If the saved section doesn't exist, clear the storage
                    localStorage.removeItem('adypu_active_section');
                }
            }

            // Restore scroll position
            var scrollPos = localStorage.getItem('adypu_scroll_pos');
            if (scrollPos) {
                window.scrollTo({ top: parseInt(scrollPos), behavior: 'instant' });
                localStorage.removeItem('adypu_scroll_pos');
            }
        });

        function toggleSection(id) {
            var body = document.getElementById(id);
            var header = body.previousElementSibling;
            var isCollapsed = body.classList.contains('collapsed');
            
            // If the clicked section is already open, close it
            if (!isCollapsed) {
                body.classList.add('collapsed');
                header.classList.add('collapsed');
                localStorage.removeItem('adypu_active_section');
                return;
            }
            
            // Close all sections first
            document.querySelectorAll('.section-body').forEach(function(sec) {
                sec.classList.add('collapsed');
                sec.previousElementSibling.classList.add('collapsed');
            });

            // Open the clicked section
            body.classList.remove('collapsed');
            header.classList.remove('collapsed');
            localStorage.setItem('adypu_active_section', id);
        }

        function toggleEdit(id) {
            var el = document.getElementById(id);
            if (el) {
                el.classList.toggle('active');
            }
        }

        // Track which form is being submitted
        document.querySelectorAll('.track-form').forEach(function(form) {
            form.addEventListener('submit', function() {
                // Find the closest section wrapper to identify which part of the page we are in
                var parentSection = this.closest('.section-body');
                if (parentSection) {
                    localStorage.setItem('adypu_active_section', parentSection.id);
                }
                localStorage.setItem('adypu_scroll_pos', window.scrollY);
            });
        });
    </script>
</body>
</html>