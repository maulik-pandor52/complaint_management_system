-- Incremental migrations for existing databases (safe-ish)
-- Run these if you already have `complaint_database` created and tables exist.
-- Always take a backup before running migrations.

USE `complaint_database`;

-- 1) Add new statuses (if missing)
INSERT IGNORE INTO `status_master` (`status_name`) VALUES
('Verified'),
('Escalated'),
('Reopened - Pending Approval'),
('Reopened - Assigned');

-- 2) Complaints: exact location field (Feature #9)
-- If your MySQL/MariaDB does not support "IF NOT EXISTS" for ALTER,
-- run these manually after checking the column doesn't already exist.
ALTER TABLE `complaints`
  ADD COLUMN `exact_location` VARCHAR(255) NULL AFTER `area_id`;

-- 3) Attachments: support action proof (Feature #6)
ALTER TABLE `complaint_attachments`
  ADD COLUMN `attachment_type` VARCHAR(30) NOT NULL DEFAULT 'complaint_proof' AFTER `file_path`,
  ADD COLUMN `uploaded_by` INT NULL AFTER `attachment_type`,
  ADD COLUMN `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `uploaded_by`;

-- 4) Helpful indexes
-- If index exists, MySQL will error; create only if missing.
CREATE INDEX `idx_complaints_sla_res` ON `complaints` (`resolution_sla_due`, `status_id`);
CREATE INDEX `idx_complaints_sla_init` ON `complaints` (`initial_sla_due`, `status_id`);
