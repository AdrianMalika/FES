-- Typical cost ranges for maintenance types (admin UI hints). Run after add_maintenance.sql.

CREATE TABLE IF NOT EXISTS `maintenance_cost_hints` (
  `maintenance_type` ENUM('routine','repair','overhaul','inspection') NOT NULL,
  `min_cost` INT UNSIGNED NOT NULL,
  `max_cost` INT UNSIGNED NOT NULL,
  `notes` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`maintenance_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `maintenance_cost_hints` (`maintenance_type`, `min_cost`, `max_cost`, `notes`) VALUES
  ('routine', 15000, 35000, NULL),
  ('inspection', 5000, 15000, NULL),
  ('repair', 30000, 150000, NULL),
  ('overhaul', 150000, 500000, NULL)
ON DUPLICATE KEY UPDATE
  `min_cost` = VALUES(`min_cost`),
  `max_cost` = VALUES(`max_cost`),
  `notes` = VALUES(`notes`);