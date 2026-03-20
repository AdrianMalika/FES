-- Add operator work timestamps on bookings (used by Pages/operator/job_details.php).
-- Run once on existing databases that were created before these columns existed.

ALTER TABLE `bookings`
  ADD COLUMN `operator_start_time` datetime DEFAULT NULL COMMENT 'Set when operator marks job In progress' AFTER `status`,
  ADD COLUMN `operator_end_time` datetime DEFAULT NULL COMMENT 'Set when operator marks job Completed' AFTER `operator_start_time`;
