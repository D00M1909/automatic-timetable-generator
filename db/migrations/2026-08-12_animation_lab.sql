-- Animation Lab and the three external bookings that occupy it (faculty meeting,
-- 2026-08-12). SY BT, TY BI (bioinformatics) and M.Tech 1st year Medical Biotech
-- are other departments' classes: they are not scheduled by us, they just consume
-- the room. room_unavailable is exactly that — generate.php:120 already pre-loads
-- it into $room_daily_schedule, so no scheduler change is needed.
--
-- source='manual' so seed_from_excel.php's excel-owned wipe leaves the room alone,
-- same flag the FY rooms use.
--
-- Both 1:30-3:30 bookings are recorded against the 13:15-14:15 + 14:15-15:15 rows:
-- our grid has no 1:30 boundary, and snapping was the agreed call.
--
-- Idempotent: re-running inserts nothing. Slot and day ids are resolved by value,
-- never hardcoded, because they differ between the dump and a live install.

INSERT INTO rooms (building_id, room_name, room_type, capacity, has_projector, has_ac, floor_number, source)
SELECT b.building_id, 'Animation Lab', 'lab', 30, 1, 1, 1, 'manual'
FROM buildings b
WHERE b.building_name = 'ULC'
  AND NOT EXISTS (SELECT 1 FROM (SELECT room_name FROM rooms) r WHERE r.room_name = 'Animation Lab')
LIMIT 1;

INSERT INTO room_unavailable (room_id, day_id, slot_id, reason)
SELECT r.room_id, d.day_id, ts.slot_id, x.reason
FROM (
              SELECT 'Tuesday'   AS day_name, '13:15:00' AS start_time, 'SY BT' AS reason
    UNION ALL SELECT 'Tuesday',   '14:15:00', 'SY BT'
    UNION ALL SELECT 'Wednesday', '10:30:00', 'TY BI (Bioinformatics)'
    UNION ALL SELECT 'Wednesday', '11:30:00', 'TY BI (Bioinformatics)'
    UNION ALL SELECT 'Friday',    '13:15:00', 'M.Tech 1st Year Medical Biotech'
    UNION ALL SELECT 'Friday',    '14:15:00', 'M.Tech 1st Year Medical Biotech'
) x
JOIN rooms r         ON r.room_name = 'Animation Lab'
JOIN working_days d  ON d.day_name = x.day_name
JOIN time_slots ts   ON ts.start_time = x.start_time
                    AND ts.slot_type = 'class'
                    AND ts.year_of_study IS NULL
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT room_id, day_id, slot_id FROM room_unavailable) ru
    WHERE ru.room_id = r.room_id AND ru.day_id = d.day_id AND ru.slot_id = ts.slot_id
);
