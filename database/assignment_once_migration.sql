-- Enforce permanent one-time assignment per complaint.
-- Run once on existing databases before using the updated assignment workflow.

ALTER TABLE assignments
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER assigned_at;

DELETE a
FROM assignments a
JOIN assignments keep_row
  ON keep_row.complaint_id = a.complaint_id
 AND keep_row.assignment_id < a.assignment_id;

UPDATE assignments SET is_active = 1;

ALTER TABLE assignments
  DROP INDEX uq_assignment_once,
  ADD UNIQUE KEY uq_assignment_complaint_once (complaint_id);
