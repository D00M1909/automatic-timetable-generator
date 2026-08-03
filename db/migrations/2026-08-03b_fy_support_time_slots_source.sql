-- Follow-on to 2026-08-03_fy_support.sql: found during Task 3 execution that
-- seed_from_excel.php's unconditional `DELETE FROM time_slots` would wipe
-- FY's manually-entered time_slots rows (Task 11) on every Excel reseed,
-- same problem the source flag on classes/subjects/faculty/rooms/buildings
-- already solves. Extend it to time_slots too.
ALTER TABLE time_slots ADD COLUMN source ENUM('excel','manual') NOT NULL DEFAULT 'excel' AFTER year_of_study;
