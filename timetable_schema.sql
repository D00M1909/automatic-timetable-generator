-- Automatic Timetable Generation System - Database Schema
-- Run this in phpMyAdmin or mysql command line

CREATE DATABASE IF NOT EXISTS timetable_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE timetable_db;

-- Academic Years Table
CREATE TABLE IF NOT EXISTS years (
    year_id INT AUTO_INCREMENT PRIMARY KEY,
    year_name VARCHAR(50) NOT NULL,
    year_status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Classes/Divisions Table
CREATE TABLE IF NOT EXISTS classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    year_id INT NOT NULL,
    class_name VARCHAR(100) NOT NULL,
    class_code VARCHAR(20) NOT NULL,
    strength INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (year_id) REFERENCES years(year_id) ON DELETE CASCADE
);

-- Faculty Table
CREATE TABLE IF NOT EXISTS faculty (
    faculty_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_name VARCHAR(100) NOT NULL,
    faculty_code VARCHAR(20) NOT NULL UNIQUE,
    department VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    max_hours_per_day INT DEFAULT 6,
    max_hours_per_week INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Subjects Table
CREATE TABLE IF NOT EXISTS subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20) NOT NULL UNIQUE,
    subject_type ENUM('lecture', 'lab', 'both') DEFAULT 'lecture',
    lecture_hours_per_week INT DEFAULT 0,
    lab_hours_per_week INT DEFAULT 0,
    department VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Subject Assignment - Which faculty teaches which subject to which class
CREATE TABLE IF NOT EXISTS subject_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    faculty_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (class_id, subject_id)
);

-- Time Slots Configuration
CREATE TABLE IF NOT EXISTS time_slots (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    slot_number INT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_type ENUM('class', 'break', 'lunch') DEFAULT 'class',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Working Days Configuration
CREATE TABLE IF NOT EXISTS working_days (
    day_id INT AUTO_INCREMENT PRIMARY KEY,
    day_name VARCHAR(20) NOT NULL,
    day_order INT NOT NULL,
    is_working TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Generated Timetable
CREATE TABLE IF NOT EXISTS timetable (
    timetable_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    day_id INT NOT NULL,
    slot_id INT NOT NULL,
    subject_id INT,
    faculty_id INT,
    assignment_id INT,
    is_lab TINYINT(1) DEFAULT 0,
    is_substitute TINYINT(1) DEFAULT 0,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (day_id) REFERENCES working_days(day_id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES time_slots(slot_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE SET NULL,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE SET NULL,
    FOREIGN KEY (assignment_id) REFERENCES subject_assignments(assignment_id) ON DELETE SET NULL,
    UNIQUE KEY unique_slot (class_id, day_id, slot_id)
);

-- Faculty Unavailable Slots (for blocking specific times)
CREATE TABLE IF NOT EXISTS faculty_unavailable (
    unavailable_id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    day_id INT NOT NULL,
    slot_id INT NOT NULL,
    reason VARCHAR(255),
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    FOREIGN KEY (day_id) REFERENCES working_days(day_id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES time_slots(slot_id) ON DELETE CASCADE
);

-- Insert default working days
INSERT INTO working_days (day_name, day_order, is_working) VALUES
('Monday', 1, 1),
('Tuesday', 2, 1),
('Wednesday', 3, 1),
('Thursday', 4, 1),
('Friday', 5, 1),
('Saturday', 6, 0),
('Sunday', 7, 0);

-- Insert default time slots (customize as needed)
INSERT INTO time_slots (slot_number, start_time, end_time, slot_type) VALUES
(1, '08:30:00', '09:30:00', 'class'),
(2, '09:30:00', '10:30:00', 'class'),
(3, '10:30:00', '11:30:00', 'class'),
(4, '11:30:00', '12:00:00', 'break'),
(5, '12:00:00', '13:00:00', 'class'),
(6, '13:00:00', '14:00:00', 'lunch'),
(7, '14:00:00', '15:00:00', 'class'),
(8, '15:00:00', '16:00:00', 'class');
