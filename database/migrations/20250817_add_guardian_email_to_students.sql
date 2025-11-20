-- Migration: Add guardian_email to students for parent/guardian notifications
-- Created: 2025-08-17 11:21:00 +08:00
-- Run on MySQL (XAMPP). Backup your database before applying.

-- 1) Add nullable guardian_email column to students
ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `guardian_email` VARCHAR(255) NULL AFTER `email`;

-- 2) Optional: index for faster lookups by guardian email
ALTER TABLE `students`
  ADD INDEX `idx_guardian_email` (`guardian_email`);
