-- ══════════════════════════════════════════════════════════════
-- PAK GRAMMAR SCHOOL PATTOKI — School Management System
-- Full Database Schema (v2.0)
-- Run this ONCE on a fresh database. Drop old college_db if migrating.
-- ══════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────
-- USERS (login accounts — admin, teacher)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(30) NOT NULL DEFAULT 'teacher',  -- matches roles.role_key (admin/teacher/accountant/librarian/receptionist/...)
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `teacher_id` INT DEFAULT NULL,         -- links to teachers.id when role='teacher'
    `student_id` INT DEFAULT NULL,         -- links to students.id when role='student'
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `failed_attempts` INT NOT NULL DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- CLASSES (e.g. "Class 9", "Class 11")
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `classes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_name` VARCHAR(50) NOT NULL,      -- e.g. "Class 9", "Nursery"
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- SECTIONS (e.g. "A", "B" within a class)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `section_name` VARCHAR(20) NOT NULL,     -- e.g. "A", "B"
    `class_teacher_id` INT DEFAULT NULL,     -- homeroom teacher
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_class_section` (`class_id`, `section_name`)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- SUBJECTS / COURSES
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(20) UNIQUE NOT NULL,
    `course_name` VARCHAR(100) NOT NULL,
    `course_type` VARCHAR(50) DEFAULT 'Compulsory',  -- Compulsory / Elective
    `class_id` INT DEFAULT NULL,             -- which class this subject belongs to
    `credits` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- TEACHERS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_code` VARCHAR(20) UNIQUE NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `gender` VARCHAR(10) DEFAULT '',
    `email` VARCHAR(100) DEFAULT '',
    `phone` VARCHAR(20) DEFAULT '',
    `address` VARCHAR(255) DEFAULT '',
    `qualification` VARCHAR(150) DEFAULT '',
    `joining_date` DATE DEFAULT NULL,
    `fixed_salary` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `picture` VARCHAR(255) DEFAULT '',
    `scan_token` VARCHAR(40) DEFAULT NULL UNIQUE,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- link table: which subjects a teacher teaches in which section
CREATE TABLE IF NOT EXISTS `teacher_subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_teacher_course_section` (`teacher_id`,`course_id`,`section_id`)
) ENGINE=InnoDB;

ALTER TABLE `sections`
    ADD CONSTRAINT `fk_section_class_teacher`
    FOREIGN KEY (`class_teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL;

ALTER TABLE `users`
    ADD CONSTRAINT `fk_user_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL;

-- ─────────────────────────────────────────────
-- STUDENTS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `roll_no` VARCHAR(20) UNIQUE NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `gender` VARCHAR(10) DEFAULT '',
    `date_of_birth` DATE DEFAULT NULL,
    `class_id` INT DEFAULT NULL,
    `section_id` INT DEFAULT NULL,
    `guardian_name` VARCHAR(100) DEFAULT '',
    `guardian_phone` VARCHAR(20) DEFAULT '',
    `guardian_email` VARCHAR(100) DEFAULT '',
    `guardian_relation` VARCHAR(30) DEFAULT '',
    `address` VARCHAR(255) DEFAULT '',
    `picture` VARCHAR(255) DEFAULT '',
    `enrollment_date` DATE DEFAULT NULL,
    `status` ENUM('Active','Inactive','Graduated','Transferred') NOT NULL DEFAULT 'Active',
    -- Extended admission fields (matching common school ERP admission forms)
    `cnic_bform` VARCHAR(20) DEFAULT '',         -- CNIC or B-Form number
    `scan_token` VARCHAR(40) DEFAULT NULL UNIQUE, -- non-guessable token encoded in the ID card QR for scanner attendance
    `blood_group` VARCHAR(5) DEFAULT '',
    `religion` VARCHAR(30) DEFAULT '',
    `caste` VARCHAR(50) DEFAULT '',
    `identification_mark` VARCHAR(150) DEFAULT '',
    `disease_if_any` VARCHAR(150) DEFAULT '',
    `previous_school` VARCHAR(150) DEFAULT '',
    `previous_id_board_roll_no` VARCHAR(50) DEFAULT '',
    `is_orphan` TINYINT(1) NOT NULL DEFAULT 0,
    `additional_note` VARCHAR(255) DEFAULT '',
    `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `mobile_for_sms` VARCHAR(20) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- which elective/optional subjects a student is enrolled in
CREATE TABLE IF NOT EXISTS `student_courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_student_course` (`student_id`,`course_id`)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- ATTENDANCE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `attendance_date` DATE NOT NULL,
    `status` ENUM('Present','Absent','Leave','Late') NOT NULL DEFAULT 'Present',
    `marked_by` INT DEFAULT NULL,           -- users.id
    `remarks` VARCHAR(255) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`marked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uniq_student_date` (`student_id`,`attendance_date`)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- EXAMS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `exams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exam_name` VARCHAR(100) NOT NULL,       -- e.g. "Mid Term 2026"
    `class_id` INT NOT NULL,
    `exam_date` DATE DEFAULT NULL,
    `academic_year` VARCHAR(20) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `exam_subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exam_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `max_marks` INT NOT NULL DEFAULT 100,
    `pass_marks` INT NOT NULL DEFAULT 33,
    `exam_date` DATE DEFAULT NULL,
    FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_exam_course` (`exam_id`,`course_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `exam_results` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exam_subject_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `marks_obtained` DECIMAL(6,2) DEFAULT NULL,
    `is_absent` TINYINT(1) NOT NULL DEFAULT 0,
    `entered_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`entered_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uniq_examsubject_student` (`exam_subject_id`,`student_id`)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- FEES
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fee_structures` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT NOT NULL,
    `fee_type` VARCHAR(50) NOT NULL,         -- Tuition, Transport, Lab, etc.
    `amount` DECIMAL(10,2) NOT NULL,
    `frequency` ENUM('Monthly','Quarterly','Annually','One-Time') NOT NULL DEFAULT 'Monthly',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `fee_invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `fee_structure_id` INT DEFAULT NULL,
    `description` VARCHAR(150) DEFAULT '',
    `billing_month` VARCHAR(20) DEFAULT '',     -- e.g. "June 2026", display label for the invoice
    `amount_due` DECIMAL(10,2) NOT NULL,
    `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `fine_after_due` DECIMAL(10,2) NOT NULL DEFAULT 0,  -- flat fine amount applied if paid after due date
    `due_date` DATE DEFAULT NULL,
    `billing_period` VARCHAR(20) DEFAULT '', -- e.g. "2026-06"
    `bank_name` VARCHAR(50) DEFAULT '',
    `bank_account` VARCHAR(50) DEFAULT '',
    `status` ENUM('Unpaid','Partial','Paid','Overdue') NOT NULL DEFAULT 'Unpaid',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `fee_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_method` VARCHAR(30) DEFAULT 'Cash',
    `receipt_no` VARCHAR(30) DEFAULT '',
    `received_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`invoice_id`) REFERENCES `fee_invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- TIMETABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `timetable` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `section_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `teacher_id` INT DEFAULT NULL,
    `day_of_week` ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    `period_number` INT NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uniq_section_day_period` (`section_id`,`day_of_week`,`period_number`)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- TIMETABLE BREAKS (editable Break/Recess rows shown across all days)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `timetable_breaks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `section_id` INT NOT NULL,
    `after_period` INT NOT NULL DEFAULT 0,
    `label` VARCHAR(50) NOT NULL DEFAULT 'Break',
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- NOTICES / ANNOUNCEMENTS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `body` TEXT NOT NULL,
    `audience` ENUM('All','Teachers','Students','Admins') NOT NULL DEFAULT 'All',
    `posted_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- ACTIVITY LOG (basic audit trail)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` VARCHAR(255) DEFAULT '',
    `ip_address` VARCHAR(45) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- INSTITUTE SETTINGS (single-row config table)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT PRIMARY KEY DEFAULT 1,
    `school_name` VARCHAR(150) NOT NULL DEFAULT 'My School',
    `tagline` VARCHAR(200) DEFAULT '',
    `address` VARCHAR(255) DEFAULT '',
    `phone` VARCHAR(30) DEFAULT '',
    `email` VARCHAR(100) DEFAULT '',
    `logo` VARCHAR(255) DEFAULT '',
    `principal_name` VARCHAR(100) DEFAULT '',
    `principal_signature` VARCHAR(255) DEFAULT '',
    `academic_year` VARCHAR(20) DEFAULT '',
    `bank_name` VARCHAR(50) DEFAULT '',
    `bank_address` VARCHAR(150) DEFAULT '',
    `bank_account` VARCHAR(50) DEFAULT '',
    `default_fine_after_due` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- ROLES & PERMISSIONS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_key` VARCHAR(30) UNIQUE NOT NULL,   -- machine name, e.g. 'accountant'
    `role_label` VARCHAR(50) NOT NULL,         -- display name, e.g. 'Accountant'
    `is_system` TINYINT(1) NOT NULL DEFAULT 0, -- system roles (admin/teacher) can't be deleted
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Permission flags per role per module. Simple model: one row per role+module
-- with can_view/can_add/can_edit/can_delete flags, matching Eskooly's model.
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `module_key` VARCHAR(40) NOT NULL,   -- e.g. 'students', 'fees', 'exams'
    `can_view` TINYINT(1) NOT NULL DEFAULT 0,
    `can_add` TINYINT(1) NOT NULL DEFAULT 0,
    `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
    `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_role_module` (`role_id`, `module_key`)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- SALARY (Teachers only, per current scope)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `salary_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `salary_month` VARCHAR(20) NOT NULL,    -- e.g. "2026-06"
    `fixed_salary` DECIMAL(10,2) NOT NULL,
    `bonus_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `deduction_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `net_salary` DECIMAL(10,2) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_method` VARCHAR(30) DEFAULT 'Cash',
    `receipt_no` VARCHAR(30) DEFAULT '',
    `notes` VARCHAR(255) DEFAULT '',
    `paid_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`paid_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uniq_teacher_month` (`teacher_id`, `salary_month`)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────────────────────────
-- SEED DATA
-- ─────────────────────────────────────────────
INSERT INTO `settings` (`id`, `school_name`) VALUES (1, 'PAK GRAMMAR SCHOOL PATTOKI')
    ON DUPLICATE KEY UPDATE id = id;

INSERT INTO `roles` (`role_key`, `role_label`, `is_system`) VALUES
    ('admin', 'Administrator', 1),
    ('teacher', 'Teacher', 1),
    ('student', 'Student', 1),
    ('accountant', 'Accountant', 0),
    ('librarian', 'Librarian', 0),
    ('receptionist', 'Receptionist', 0);

INSERT INTO `classes` (`class_name`, `sort_order`) VALUES
    ('Nursery', 1), ('Class 1', 2), ('Class 2', 3), ('Class 3', 4),
    ('Class 4', 5), ('Class 5', 6), ('Class 6', 7), ('Class 7', 8),
    ('Class 8', 9), ('Class 9', 10), ('Class 10', 11),
    ('Class 11 (Part 1)', 12), ('Class 12 (Part 2)', 13);

-- Default admin: username = admin / password = ChangeMe@123 (must change on first login)
-- Hash generated for 'ChangeMe@123' — installer will regenerate this safely; placeholder only.
