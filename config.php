<?php
// Database Configuration for Timetable System
// XAMPP default: host=localhost, user=root, password='', db=timetable_db

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
?>
