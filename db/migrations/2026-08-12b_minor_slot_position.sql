-- The minor slot is not always the last slot of the day (faculty meeting, 2026-08-12).
--
-- Until now generate.php reserved the *last* class slot on each of a year's rows in
-- this table. That is right for SY (Mon/Tue/Wed, 16:30-17:30) but wrong for TY, whose
-- minor sits at 09:30-10:30 — the *first* slot of the shared grid.
--
-- Careful: this table does double duty. generate.php:177 also treats its rows as the
-- only days a year's classes may meet at all. So a day cannot simply be dropped to say
-- "no minor here" — TY teaches 18 sessions on Wednesday in the imported workbook, and
-- deleting its Wednesday row would make those unschedulable. Hence the new column
-- rather than row deletions.
--
-- slot_number semantics:
--   NULL = this year meets on this day, but no slot is reserved for a minor
--   n    = slot_number n on this day is reserved for minor subjects
--
-- Every existing row is therefore given an explicit value: SY and BE keep the last
-- class slot they already reserved, TY moves to slot 1 on Thursday and Friday only.
--
-- Idempotent: the ALTER is guarded and the rows for years 2-4 are fully reconciled,
-- so any number of runs from any prior state converges on the same result.

ALTER TABLE year_working_days ADD COLUMN IF NOT EXISTS slot_number INT NULL AFTER day_id;

DELETE FROM year_working_days WHERE year_of_study IN (2, 3, 4);

-- Second Year: Mon/Tue/Wed, minor in the last class slot of the shared grid (16:30-17:30).
INSERT INTO year_working_days (year_of_study, day_id, slot_number)
SELECT 2, d.day_id, (SELECT MAX(slot_number) FROM time_slots WHERE slot_type = 'class' AND year_of_study IS NULL)
FROM working_days d WHERE d.day_name IN ('Monday', 'Tuesday', 'Wednesday');

-- Third Year: meets Wed/Thu/Fri; minor is the first class slot (09:30-10:30) on
-- Thursday and Friday. Wednesday carries no reservation — see the note in the design
-- doc about the workbook also showing a Wednesday online minor.
INSERT INTO year_working_days (year_of_study, day_id, slot_number)
SELECT 3, d.day_id, NULL FROM working_days d WHERE d.day_name = 'Wednesday';
INSERT INTO year_working_days (year_of_study, day_id, slot_number)
SELECT 3, d.day_id, 1 FROM working_days d WHERE d.day_name IN ('Thursday', 'Friday');

-- Final Year: unchanged from before — Wed/Thu/Fri, last class slot reserved.
INSERT INTO year_working_days (year_of_study, day_id, slot_number)
SELECT 4, d.day_id, (SELECT MAX(slot_number) FROM time_slots WHERE slot_type = 'class' AND year_of_study IS NULL)
FROM working_days d WHERE d.day_name IN ('Wednesday', 'Thursday', 'Friday');
