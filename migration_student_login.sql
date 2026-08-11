-- ══════════════════════════════════════════════════════════════
-- Migration: Add student login support
-- Run this ONCE on your existing Railway database (via HeidiSQL,
-- same way you ran database.sql before). Safe to run even if some
-- parts already exist — uses IF NOT EXISTS / IGNORE where possible.
-- ══════════════════════════════════════════════════════════════

-- 1. Link users to a student record (mirrors the existing teacher_id column)
ALTER TABLE `users` ADD COLUMN `student_id` INT DEFAULT NULL AFTER `teacher_id`;

-- 2. Add "Students" as a notice audience option
ALTER TABLE `notices` MODIFY `audience` ENUM('All','Teachers','Students','Admins') NOT NULL DEFAULT 'All';

-- 3. Register "student" as a proper system role
INSERT IGNORE INTO `roles` (`role_key`, `role_label`, `is_system`) VALUES ('student', 'Student', 1);
