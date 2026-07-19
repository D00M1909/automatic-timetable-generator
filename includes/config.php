<?php
// Database Configuration for Timetable System
// XAMPP default: host=localhost, user=root, password='', db=timetable_db

session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'timetable_db');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to handle special characters
$conn->set_charset("utf8mb4");

// Base URL - adjust if your project folder name is different
$base_url = "http://localhost/timetable/";

// ==========================================
// SECURITY HELPERS
// ==========================================

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token()) . '">';
}

function csrf_check() {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die("CSRF token validation failed. Please refresh the page and try again.");
    }
}

// ==========================================
// DATABASE HELPERS (Prepared Statements)
// ==========================================

function db_query($conn, $sql, $types = "", $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    if ($stmt->error) {
        error_log("Execute failed: " . $stmt->error);
    }
    return $stmt;
}

function db_get_rows($conn, $sql, $types = "", $params = []) {
    $stmt = db_query($conn, $sql, $types, $params);
    if (!$stmt) return [];
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function db_get_row($conn, $sql, $types = "", $params = []) {
    $rows = db_get_rows($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

function db_insert($conn, $sql, $types, $params) {
    $stmt = db_query($conn, $sql, $types, $params);
    if (!$stmt) return false;
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

function db_execute($conn, $sql, $types = "", $params = []) {
    $stmt = db_query($conn, $sql, $types, $params);
    if (!$stmt) return false;
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

// ==========================================
// AUDIT LOGGING
// ==========================================

function audit_log($conn, $action, $details = "", $user_id = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    db_execute($conn, 
        "INSERT INTO timetable_audit_log (action, user_id, details, ip_address) VALUES (?, ?, ?, ?)",
        "siss", [$action, $user_id, $details, $ip]
    );
}

// ==========================================
// FLASH MESSAGES
// ==========================================

function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ==========================================
// SYSTEM CONFIG
// ==========================================

function get_config($conn, $key, $default = '') {
    $row = db_get_row($conn, "SELECT config_value FROM system_config WHERE config_key = ?", "s", [$key]);
    return $row ? $row['config_value'] : $default;
}

function set_config($conn, $key, $value) {
    db_execute($conn, 
        "INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?",
        "sss", [$key, $value, $value]
    );
}

// ==========================================
// COMMON SVG ICONS
// ==========================================
function svg_defs() {
    echo '
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
            <symbol id="icon-print" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></symbol>
            <symbol id="icon-delete" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></symbol>
            <symbol id="icon-edit" viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></symbol>
            <symbol id="icon-room" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></symbol>
            <symbol id="icon-ai" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></symbol>
            <symbol id="icon-chart" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></symbol>
            <symbol id="icon-star" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></symbol>
            <symbol id="icon-energy" viewBox="0 0 24 24"><path d="M11 21h-1l1-7H7.5c-.58 0-.57-.32-.38-.66.19-.34.05-.08.07-.12C8.48 10.94 10.42 7.54 13 3h1l-1 7h3.5c.49 0 .56.33.47.51l-.07.15C12.96 17.55 11 21 11 21z"/></symbol>
            <symbol id="icon-demo" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></symbol>
        </defs>
    </svg>';
}

function sidebar($active_page = '') {
    $items = [
        ['index.php', 'icon-dashboard', 'Dashboard'],
        ['setup.php', 'icon-settings', 'Manage Data'],
        ['generate.php', 'icon-refresh', 'Generate Timetable'],
        ['view.php', 'icon-eye', 'View Timetable'],
        ['demo.php', 'icon-demo', 'How It Works'],
    ];
    echo '<div class="sidebar">';
    echo '<div class="sidebar-header"><div style="width:40px;height:40px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#6B1B5E;font-weight:bold;font-size:18px;">A</div><h2>Ajeenkya DY Patil<br><span style="font-size:11px;font-weight:400;opacity:0.8;">University ERP</span></h2></div>';
    echo '<div class="sidebar-menu">';
    echo '<a href="index.php" class="menu-item ' . ($active_page === 'index' ? 'active' : '') . '"><svg><use href="#icon-dashboard"/></svg> Dashboard</a>';
    echo '<div class="menu-section">Timetable</div>';
    foreach ($items as $item) {
        if ($item[0] === 'index.php') continue;
        $page = str_replace('.php', '', $item[0]);
        $is_active = ($active_page === $page) ? 'active' : '';
        echo '<a href="' . $item[0] . '" class="menu-item ' . $is_active . '"><svg><use href="#' . $item[1] . '"/></svg> ' . $item[2] . '</a>';
    }
    echo '</div></div>';
}

function top_header() {
    echo '<div class="top-header">
        <div class="top-header-left">
            <span style="font-size:18px;cursor:pointer;">&#9776;</span>
            <span>Active Academic Year: 2025-26 Summer Term</span>
        </div>
        <div class="top-header-right">
            <span style="display:flex;align-items:center;gap:6px;"><svg width="16" height="16" fill="white" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg> 0</span>
            <span>Welcome, User</span>
            <a href="#">Logout</a>
        </div>
    </div>';
}

function breadcrumb($items) {
    echo '<div class="breadcrumb">';
    echo '<a href="index.php">Home</a>';
    foreach ($items as $item) {
        echo ' / <span>' . htmlspecialchars($item) . '</span>';
    }
    echo '</div>';
}

function flash_message() {
    $flash = get_flash();
    if ($flash) {
        echo '<div class="alert alert-' . $flash['type'] . '">' . htmlspecialchars($flash['message']) . '</div>';
    }
}

function common_styles() {
    echo '<link rel="stylesheet" href="assets/css/style.css">';
    echo '<link rel="stylesheet" href="assets/css/print.css" media="print">';
    echo '<script src="assets/js/script.js" defer></script>';
}