-- =======================================================
-- Academic Project Submission & Evaluation Portal Schema
-- Compatible with MySQL 5.7+ / 8.0+ & MariaDB 10.3+
-- =======================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `evaluation_scores`;
DROP TABLE IF EXISTS `evaluations`;
DROP TABLE IF EXISTS `evaluation_criteria`;
DROP TABLE IF EXISTS `title_review_history`;
DROP TABLE IF EXISTS `project_reviews`;
DROP TABLE IF EXISTS `project_files`;
DROP TABLE IF EXISTS `project_members`;
DROP TABLE IF EXISTS `project_technologies`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `technologies`;
DROP TABLE IF EXISTS `project_categories`;
DROP TABLE IF EXISTS `teacher_profiles`;
DROP TABLE IF EXISTS `student_profiles`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `users`;

-- 1. Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'student',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `email_verified_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Departments Table
CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL UNIQUE,
  `code` VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Student Profiles
CREATE TABLE `student_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `student_id` VARCHAR(50) NOT NULL UNIQUE,
  `department_id` INT NOT NULL,
  `degree_program` VARCHAR(100) NOT NULL,
  `semester` INT NOT NULL DEFAULT 8,
  `section` VARCHAR(10) NOT NULL DEFAULT 'A',
  `profile_picture` VARCHAR(255) NULL,
  CONSTRAINT `fk_sp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sp_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  INDEX `idx_sp_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Teacher Profiles
CREATE TABLE `teacher_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `department_id` INT NOT NULL,
  `designation` VARCHAR(100) NOT NULL DEFAULT 'Faculty Evaluator',
  CONSTRAINT `fk_tp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tp_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Project Categories
CREATE TABLE `project_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Technologies
CREATE TABLE `technologies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(80) NOT NULL UNIQUE,
  `category` VARCHAR(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Projects Table
CREATE TABLE `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `submission_code` VARCHAR(50) NOT NULL UNIQUE,
  `student_owner_id` INT NOT NULL,
  `project_title` VARCHAR(255) NOT NULL,
  `short_description` VARCHAR(500) NOT NULL,
  `detailed_description` TEXT NOT NULL,
  `problem_statement` TEXT NOT NULL,
  `proposed_solution` TEXT NOT NULL,
  `objectives` TEXT NOT NULL,
  `category_id` INT NOT NULL,
  `project_type` VARCHAR(50) NOT NULL DEFAULT 'Semester Project',
  `academic_session` VARCHAR(50) NOT NULL DEFAULT 'Fall 2026',
  `title_image_url` VARCHAR(500) NULL,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_prj_owner` FOREIGN KEY (`student_owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prj_cat` FOREIGN KEY (`category_id`) REFERENCES `project_categories` (`id`),
  INDEX `idx_prj_code` (`submission_code`),
  INDEX `idx_prj_owner` (`student_owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Project Technologies Mapping
CREATE TABLE `project_technologies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `technology_id` INT NOT NULL,
  CONSTRAINT `fk_pt_prj` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pt_tech` FOREIGN KEY (`technology_id`) REFERENCES `technologies` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_prj_tech` (`project_id`, `technology_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Project Team Members (Max 3 students total)
CREATE TABLE `project_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `student_name` VARCHAR(150) NOT NULL,
  `student_id_number` VARCHAR(50) NOT NULL,
  `student_email` VARCHAR(150) NULL,
  `member_role` VARCHAR(50) NOT NULL DEFAULT 'Member',
  CONSTRAINT `fk_pm_prj` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Project Files (SRS Document & Source Code ZIP)
CREATE TABLE `project_files` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `uploaded_by` INT NOT NULL,
  `file_type` VARCHAR(50) NOT NULL, -- 'srs', 'source_code', 'screenshot'
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `storage_path` VARCHAR(500) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` BIGINT NOT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pf_prj` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pf_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Project Reviews (Title & Workflow Status)
CREATE TABLE `project_reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL UNIQUE,
  `teacher_id` INT NULL,
  `title_status` VARCHAR(50) NOT NULL DEFAULT 'Pending Approval',
  `review_status` VARCHAR(50) NOT NULL DEFAULT 'Submitted',
  `teacher_comments` TEXT NULL,
  `reviewed_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pr_prj` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pr_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Title Decision History
CREATE TABLE `title_review_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `teacher_id` INT NOT NULL,
  `previous_title` VARCHAR(255) NOT NULL,
  `submitted_title` VARCHAR(255) NOT NULL,
  `decision` VARCHAR(50) NOT NULL,
  `reason` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_trh_prj` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trh_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Evaluation Rubric Criteria (100-mark rubric)
CREATE TABLE `evaluation_criteria` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `maximum_marks` DECIMAL(5,2) NOT NULL,
  `description` TEXT NULL,
  `display_order` INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Project Evaluations
CREATE TABLE `evaluations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL UNIQUE,
  `teacher_id` INT NOT NULL,
  `total_marks` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `overall_grade` VARCHAR(10) NULL,
  `overall_feedback` TEXT NULL,
  `evaluation_status` VARCHAR(50) NOT NULL DEFAULT 'Not Evaluated',
  `published_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_eval_prj` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eval_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Evaluation Scores
CREATE TABLE `evaluation_scores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `evaluation_id` INT NOT NULL,
  `criteria_id` INT NOT NULL,
  `obtained_marks` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `comments` TEXT NULL,
  CONSTRAINT `fk_es_eval` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_es_crit` FOREIGN KEY (`criteria_id`) REFERENCES `evaluation_criteria` (`id`),
  UNIQUE KEY `uk_eval_criteria` (`evaluation_id`, `criteria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Notifications
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `notification_type` VARCHAR(50) NOT NULL,
  `related_project_id` INT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Audit Logs
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
