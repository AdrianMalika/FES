-- FES: equipment maintenance tracking (run once on fes_db)
-- InnoDB, utf8mb4

CREATE TABLE IF NOT EXISTS `equipment_maintenance` (
  `maintenance_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_id` VARCHAR(100) NOT NULL,
  `maintenance_type` ENUM('routine','repair','overhaul','inspection') NOT NULL,
  `status` ENUM('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `scheduled_date` DATE NOT NULL,
  `completed_date` DATE NULL DEFAULT NULL,
  `cost` DECIMAL(12,2) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `admin_notes` TEXT NULL,
  `created_by` INT NULL DEFAULT NULL COMMENT 'users.user_id',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`maintenance_id`),
  KEY `idx_em_equipment` (`equipment_id`),
  KEY `idx_em_status` (`status`),
  KEY `idx_em_scheduled` (`scheduled_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
