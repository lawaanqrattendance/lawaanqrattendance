-- Migration: Add in_time and out_time columns to attendance for in/out scanning
-- Run on MySQL (XAMPP). Backup your database before applying.

ALTER TABLE `attendance`
    ADD COLUMN IF NOT EXISTS `in_time` DATETIME NULL AFTER `attendance_date`,
    ADD COLUMN IF NOT EXISTS `out_time` DATETIME NULL AFTER `in_time`;

-- Optional integrity rule if supported (MySQL 8.0.16+):
-- ALTER TABLE `attendance`
--   ADD CONSTRAINT `chk_out_after_in` CHECK (`out_time` IS NULL OR `in_time` IS NULL OR `out_time` >= `in_time`);

-- Note: The existing unique key on (student_id, schedule_id, attendance_date)
-- continues to enforce one row per student per period per day.
