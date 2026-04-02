-- Booking payment columns + booking_payments audit table (run on existing fes_db).
-- Used by Stripe Checkout in Pages/customer/pay_*.php.

ALTER TABLE `bookings`
  ADD COLUMN `payment_status` ENUM('unpaid','pending','paid','failed') NOT NULL DEFAULT 'unpaid' AFTER `estimated_total_cost`,
  ADD COLUMN `payment_tx_ref` VARCHAR(120) DEFAULT NULL AFTER `payment_status`,
  ADD COLUMN `payment_paid_at` DATETIME DEFAULT NULL AFTER `payment_tx_ref`;

CREATE TABLE IF NOT EXISTS `booking_payments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `user_id` int NOT NULL COMMENT 'users.user_id (customer)',
  `tx_ref` varchar(120) NOT NULL,
  `amount` int NOT NULL COMMENT 'Whole units (e.g. MWK)',
  `currency` varchar(3) NOT NULL DEFAULT 'MWK',
  `provider` varchar(20) NOT NULL DEFAULT 'stripe',
  `status` enum('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tx_ref` (`tx_ref`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
