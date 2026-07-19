
-- ================================================
-- Changes added for minor-subject last-slot constraint
-- ================================================

-- Table: year_working_days (maps year_of_study → days where last-slot = minor)
CREATE TABLE IF NOT EXISTS year_working_days (
  id INT AUTO_INCREMENT PRIMARY KEY,
  year_of_study INT NOT NULL,
  day_id INT NOT NULL,
  UNIQUE KEY unique_year_day (year_of_study, day_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Column added to subjects
ALTER TABLE subjects ADD COLUMN IF NOT EXISTS is_minor TINYINT(1) DEFAULT 0;

-- Column added to classes
ALTER TABLE classes ADD COLUMN IF NOT EXISTS year_of_study TINYINT(1) DEFAULT 2 AFTER year_id;

-- Column added to faculty
ALTER TABLE faculty ADD COLUMN IF NOT EXISTS assigned_hours_per_week INT DEFAULT 0 AFTER max_hours_per_week;
