-- Task 2: source flag so seed_from_excel.php's wipe-and-rebuild never deletes
-- hand-entered FY rows (classes/subjects/faculty/rooms/buildings have no
-- other way to distinguish "came from the Excel/JSON import" from "typed
-- into setup.php by hand").
ALTER TABLE classes   ADD COLUMN source ENUM('excel','manual') NOT NULL DEFAULT 'excel' AFTER year_of_study;
ALTER TABLE subjects  ADD COLUMN source ENUM('excel','manual') NOT NULL DEFAULT 'excel' AFTER is_minor;
ALTER TABLE faculty   ADD COLUMN source ENUM('excel','manual') NOT NULL DEFAULT 'excel' AFTER assigned_hours_per_week;
ALTER TABLE rooms     ADD COLUMN source ENUM('excel','manual') NOT NULL DEFAULT 'excel' AFTER floor_number;
ALTER TABLE buildings ADD COLUMN source ENUM('excel','manual') NOT NULL DEFAULT 'excel' AFTER has_ac;

-- Task 5: per-class time window (Core >= first-slot-after-12:15, CS = no
-- restriction). NULL means "no restriction", so existing SY/TY/BE rows are
-- unaffected.
ALTER TABLE classes ADD COLUMN min_start_slot_number INT NULL AFTER source;
ALTER TABLE classes ADD COLUMN max_end_slot_number   INT NULL AFTER min_start_slot_number;

-- Task 4: FY's own 5-day/08:30-18:30 grid must not leak into or out of
-- SY/TY/BE's existing 3-day/09:30-17:30 grid. NULL = shared across all
-- years (current 9 rows keep this), a specific year_of_study int = only
-- that year's classes may use this slot.
ALTER TABLE time_slots ADD COLUMN year_of_study TINYINT(1) NULL AFTER slot_type;

-- Task 6: block patterns for multi-slot continuous lecture/lab sessions.
-- Comma-separated block sizes summing to lecture_hours_per_week /
-- lab_hours_per_week, e.g. "2,1,1". NULL = existing default behavior
-- (lecture: N separate 1-hour sessions; lab: floor(N/2) blocks of 2).
ALTER TABLE subjects ADD COLUMN lecture_block_pattern VARCHAR(20) NULL AFTER lecture_hours_per_week;
ALTER TABLE subjects ADD COLUMN lab_block_pattern     VARCHAR(20) NULL AFTER lab_hours_per_week;
