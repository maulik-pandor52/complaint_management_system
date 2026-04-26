-- Building Maintenance Complaint and Resolution Tracking System
-- Student Enrollment: 230210107038 (U=38)
-- Tech: PHP + MySQL
--
-- This file provides a clean DB structure export (schema + minimal seed data)
-- for the assignment deliverable.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Create database (optional)
CREATE DATABASE IF NOT EXISTS `complaint_database` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `complaint_database`;

-- -----------------------------
-- Roles
-- -----------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `role_id` INT NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(1, 'Admin'),
(2, 'Staff'),
(3, 'User')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);

-- -----------------------------
-- Users
-- -----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role_id` INT NOT NULL DEFAULT 3,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Status Master
-- Keep your existing rows if you already have them.
-- We add only the statuses needed for missing features.
-- -----------------------------
DROP TABLE IF EXISTS `status_master`;
CREATE TABLE `status_master` (
  `status_id` INT NOT NULL AUTO_INCREMENT,
  `status_name` VARCHAR(80) NOT NULL,
  PRIMARY KEY (`status_id`),
  UNIQUE KEY `uq_status_name` (`status_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed status names used by the project
INSERT INTO `status_master` (`status_id`, `status_name`) VALUES
(1, 'Pending'),
(2, 'Assigned'),
(3, 'Resolved'),
(4, 'Closed'),
(5, 'Reopened - Pending Approval'),
(6, 'Reopened - Assigned'),
(7, 'Verified'),
(8, 'Escalated'),
(9, 'Declined'),
(10, 'In Progress')
ON DUPLICATE KEY UPDATE status_name = VALUES(status_name);

-- -----------------------------
-- Complaint Categories
-- -----------------------------
DROP TABLE IF EXISTS `complaint_categories`;
CREATE TABLE `complaint_categories` (
  `category_id` INT NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(120) NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`category_id`),
  KEY `idx_category_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `complaint_categories` (`category_id`, `category_name`, `status`) VALUES
(1, 'Plumbing Leakage', 1),
(2, 'Broken Doors/Windows', 1),
(3, 'Wall Cracks', 1),
(4, 'Ceiling Damage', 1),
(5, 'Paint / Tile Repair', 1),
(6, 'Washroom Fixture Damage', 1)
ON DUPLICATE KEY UPDATE
  `category_name` = VALUES(`category_name`),
  `status` = VALUES(`status`);

-- -----------------------------
-- Area Master (A=2: Campus -> Building -> Spot)
-- -----------------------------
DROP TABLE IF EXISTS `area_master`;
CREATE TABLE `area_master` (
  `area_id` INT NOT NULL AUTO_INCREMENT,
  `level1` VARCHAR(120) NOT NULL, -- Campus
  `level2` VARCHAR(120) NOT NULL, -- Building
  `level3` VARCHAR(120) DEFAULT NULL, -- Spot
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`area_id`),
  KEY `idx_area_status` (`status`),
  KEY `idx_area_l1_l2` (`level1`, `level2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `area_master` (`area_id`, `level1`, `level2`, `level3`, `status`) VALUES
(1, 'Main Campus', 'Block A', 'Classroom 101', 1),
(2, 'Main Campus', 'Block A', 'Classroom 102', 1),
(3, 'Main Campus', 'Block B', 'Washroom 1', 1),
(4, 'Main Campus', 'Block B', 'Seminar Hall', 1),
(5, 'Main Campus', 'Admin Block', 'Office 2', 1),
(6, 'Main Campus', 'Library Block', 'Reading Hall', 1)
ON DUPLICATE KEY UPDATE
  `level1` = VALUES(`level1`),
  `level2` = VALUES(`level2`),
  `level3` = VALUES(`level3`),
  `status` = VALUES(`status`);

-- -----------------------------
-- Complaints
-- -----------------------------
DROP TABLE IF EXISTS `complaints`;
CREATE TABLE `complaints` (
  `complaint_id` INT NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `category_id` INT NOT NULL,
  `area_id` INT NOT NULL,
  `exact_location` VARCHAR(255) DEFAULT NULL,
  `user_id` INT NOT NULL,
  `priority` VARCHAR(30) NOT NULL DEFAULT 'Medium',
  `status_id` INT NOT NULL DEFAULT 1,
  `initial_sla_due` DATETIME DEFAULT NULL,
  `resolution_sla_due` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`complaint_id`),
  KEY `idx_complaints_user` (`user_id`),
  KEY `idx_complaints_status` (`status_id`),
  KEY `idx_complaints_area` (`area_id`),
  KEY `idx_complaints_category` (`category_id`),
  CONSTRAINT `fk_complaints_category` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`category_id`),
  CONSTRAINT `fk_complaints_area` FOREIGN KEY (`area_id`) REFERENCES `area_master` (`area_id`),
  CONSTRAINT `fk_complaints_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_complaints_status` FOREIGN KEY (`status_id`) REFERENCES `status_master` (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Complaint Attachments
-- Stores complaint proof + action proof
-- -----------------------------
DROP TABLE IF EXISTS `complaint_attachments`;
CREATE TABLE `complaint_attachments` (
  `attachment_id` INT NOT NULL AUTO_INCREMENT,
  `complaint_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `attachment_type` VARCHAR(30) NOT NULL DEFAULT 'complaint_proof',
  `uploaded_by` INT DEFAULT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attachment_id`),
  KEY `idx_att_complaint` (`complaint_id`),
  CONSTRAINT `fk_att_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Complaint History (timeline)
-- -----------------------------
DROP TABLE IF EXISTS `complaint_history`;
CREATE TABLE `complaint_history` (
  `history_id` INT NOT NULL AUTO_INCREMENT,
  `complaint_id` INT NOT NULL,
  `status_id` INT NOT NULL,
  `updated_by` INT DEFAULT NULL,
  `remark` VARCHAR(255) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `idx_hist_complaint` (`complaint_id`),
  KEY `idx_hist_updated_at` (`updated_at`),
  CONSTRAINT `fk_hist_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_status` FOREIGN KEY (`status_id`) REFERENCES `status_master` (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Assignments (Admin -> Staff)
-- -----------------------------
DROP TABLE IF EXISTS `assignments`;
CREATE TABLE `assignments` (
  `assignment_id` INT NOT NULL AUTO_INCREMENT,
  `complaint_id` INT NOT NULL,
  `staff_id` INT NOT NULL,
  `assigned_by` INT NOT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `uq_assignment_complaint_once` (`complaint_id`),
  KEY `idx_assignment_staff` (`staff_id`),
  CONSTRAINT `fk_assign_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_assign_admin` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------
-- Feedback (extra feature)
-- -----------------------------
DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `feedback_id` INT NOT NULL AUTO_INCREMENT,
  `complaint_id` INT NOT NULL,
  `rating` INT NOT NULL,
  `comments` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`),
  UNIQUE KEY `uq_feedback_once` (`complaint_id`),
  CONSTRAINT `fk_feedback_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
