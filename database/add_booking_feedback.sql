-- Customer feedback after completed bookings (run on existing fes_db)
CREATE TABLE IF NOT EXISTS `booking_feedback` (
  `feedback_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `customer_id` int NOT NULL COMMENT 'users.user_id',
  `operator_id` int DEFAULT NULL COMMENT 'bookings.operator_id at time of feedback',
  `rating` tinyint UNSIGNED NOT NULL COMMENT '1-5 overall service',
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`),
  UNIQUE KEY `uq_booking_feedback_booking` (`booking_id`),
  KEY `idx_booking_feedback_customer` (`customer_id`),
  KEY `idx_booking_feedback_operator` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;