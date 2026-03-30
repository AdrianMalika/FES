-- Damage reports from operators (run on existing fes_db)
CREATE TABLE IF NOT EXISTS `damage_reports` (
  `damage_report_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `operator_id` int NOT NULL,
  `equipment_id` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `severity` enum('minor','major','critical') NOT NULL,
  `photo_path` varchar(500) DEFAULT NULL,
  `status` enum('submitted','acknowledged','closed') NOT NULL DEFAULT 'submitted',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`damage_report_id`),
  KEY `idx_damage_booking` (`booking_id`),
  KEY `idx_damage_operator` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
