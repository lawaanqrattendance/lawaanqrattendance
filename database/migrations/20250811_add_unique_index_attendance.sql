-- Attendance deduplication and unique index migration
-- Created: 2025-08-11 21:36:51 +08:00

-- NOTE: Review and back up your database before running this script.
-- This script will remove duplicate attendance rows and enforce a uniqueness constraint
-- on (student_id, schedule_id, attendance_date).

-- 1) Inspect duplicates (optional)
-- SELECT student_id, schedule_id, attendance_date, COUNT(*) AS cnt
-- FROM attendance
-- GROUP BY student_id, schedule_id, attendance_date
-- HAVING cnt > 1;

-- 2) Deduplicate: keep the most recent row (highest attendance_id), delete others
DELETE a1
FROM attendance a1
JOIN attendance a2
  ON a1.student_id = a2.student_id
 AND a1.schedule_id = a2.schedule_id
 AND a1.attendance_date = a2.attendance_date
 AND a1.attendance_id < a2.attendance_id;

-- 3) Create composite unique index to enforce one row per student/schedule/date
ALTER TABLE attendance
  ADD UNIQUE KEY uniq_student_schedule_date (student_id, schedule_id, attendance_date);

-- 4) Validate (optional)
-- SELECT student_id, schedule_id, attendance_date, COUNT(*) AS cnt
-- FROM attendance
-- GROUP BY student_id, schedule_id, attendance_date
-- HAVING cnt > 1;
