-- Optional: log table for daily maintenance digest emails (also auto-created by includes/fes_maintenance_mail.php)

CREATE TABLE IF NOT EXISTS `fes_maintenance_email_sent` (
  `sent_date` DATE NOT NULL,
  PRIMARY KEY (`sent_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
