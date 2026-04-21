-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 21, 2026 at 01:34 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fes_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `operator_id` int DEFAULT NULL,
  `equipment_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `booking_date` date NOT NULL,
  `service_days` int NOT NULL DEFAULT '1',
  `service_type` varchar(50) NOT NULL,
  `service_location` varchar(255) NOT NULL,
  `contact_phone` varchar(50) NOT NULL,
  `field_lat` decimal(10,7) DEFAULT NULL,
  `field_lng` decimal(10,7) DEFAULT NULL,
  `field_address` varchar(255) DEFAULT NULL,
  `field_polygon` longtext,
  `field_hectares` decimal(10,2) DEFAULT NULL,
  `notes` text,
  `estimated_total_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('unpaid','pending','paid','failed') NOT NULL DEFAULT 'unpaid',
  `payment_tx_ref` varchar(120) DEFAULT NULL,
  `payment_paid_at` datetime DEFAULT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `operator_start_time` datetime DEFAULT NULL COMMENT 'Set when operator marks job In progress',
  `operator_end_time` datetime DEFAULT NULL COMMENT 'Set when operator marks job Completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `customer_id` (`customer_id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `status` (`status`),
  KEY `idx_bookings_operator_id` (`operator_id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `customer_id`, `operator_id`, `equipment_id`, `booking_date`, `service_days`, `service_type`, `service_location`, `contact_phone`, `field_lat`, `field_lng`, `field_address`, `field_polygon`, `field_hectares`, `notes`, `estimated_total_cost`, `payment_status`, `payment_tx_ref`, `payment_paid_at`, `status`, `operator_start_time`, `operator_end_time`, `created_at`, `updated_at`) VALUES
(9, 16, 23, 'EQ-001', '2026-03-20', 1, 'harvesting', 'Lat -15.813997782742078, Lng 35.000212914097375', '0987654321', -15.8139978, 35.0002129, '', '[[34.999402249576605,-15.81374363113106],[34.9991198832833,-15.81440091912404],[34.99995787357352,-15.81467259753731],[35.000212914097375,-15.813997782742078]]', 0.74, 'rfgehngdhngfdsa', 106512.54, 'paid', 'fesb30d7c870e9f3c7e6038124dcb590b09', '2026-04-02 05:25:37', 'completed', '2026-03-20 04:23:34', '2026-04-02 18:19:09', '2026-03-18 14:26:35', '2026-04-05 14:27:18'),
(10, 27, 26, 'EQ-002', '2026-04-13', 1, 'harvesting', 'Lat -15.814349865883898, Lng 34.99919374745511', '0987654321', -15.8143499, 34.9991937, '', '[[35.000020536663556,-15.814599432803234],[35.000425825492016,-15.813476379239958],[34.99960714205986,-15.813273605015425],[34.99937207453934,-15.813733746232714],[34.99919374745511,-15.814349865883898]]', 1.23, 'call when here', 113801.15, 'paid', 'fes026315328dc0110ce59199e8e84b8e66', '2026-04-05 16:37:30', 'completed', '2026-04-05 16:31:33', '2026-04-05 16:35:41', '2026-04-05 14:02:24', '2026-04-05 14:37:30'),
(11, 27, NULL, 'EQ-003', '2026-04-07', 1, 'harvesting', 'Lat -15.813474593242177, Lng 34.9986706988532', '0123654789', -15.8134746, 34.9986707, '', '[[34.999094912083905,-15.81371326201328],[34.99918119274133,-15.813529936750555],[34.999278258480615,-15.813550690562536],[34.999479580014565,-15.813038762586316],[34.99908053197481,-15.812803552000616],[34.99891875574269,-15.813056057471016],[34.99883607011256,-15.81320825239699],[34.9986706988532,-15.813474593242177]]', 0.47, 'njjinj', 106572.12, 'unpaid', NULL, NULL, 'cancelled', NULL, NULL, '2026-04-05 18:04:54', '2026-04-05 18:05:33'),
(12, 27, 23, 'EQ-003', '2026-04-14', 2, 'harvesting', 'Lat -15.802984798666245, Lng 35.04569457243454', '+2658851561', -15.8029848, 35.0456946, '', '[[35.045660289153716,-15.801988573609947],[35.04666136095682,-15.801922598267865],[35.04677792411209,-15.803195918578112],[35.0456465758418,-15.803624755221804],[35.04569457243454,-15.802984798666245]]', 1.82, 'calll me', 240629.41, 'paid', 'fes740c618ed878b27bd462acbf06929472', '2026-04-13 12:37:43', 'completed', '2026-04-13 12:34:10', '2026-04-13 12:34:38', '2026-04-13 10:24:58', '2026-04-13 10:37:43');

-- --------------------------------------------------------

--
-- Table structure for table `booking_feedback`
--

DROP TABLE IF EXISTS `booking_feedback`;
CREATE TABLE IF NOT EXISTS `booking_feedback` (
  `feedback_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `customer_id` int NOT NULL COMMENT 'users.user_id',
  `operator_id` int DEFAULT NULL COMMENT 'bookings.operator_id at time of feedback',
  `rating` tinyint UNSIGNED NOT NULL COMMENT '1-5 overall service',
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`),
  UNIQUE KEY `uq_booking_feedback_booking` (`booking_id`),
  KEY `idx_booking_feedback_customer` (`customer_id`),
  KEY `idx_booking_feedback_operator` (`operator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_feedback`
--

INSERT INTO `booking_feedback` (`feedback_id`, `booking_id`, `customer_id`, `operator_id`, `rating`, `comment`, `created_at`) VALUES
(1, 9, 16, 23, 4, 'Well worked!!!', '2026-04-03 03:30:53'),
(2, 10, 27, 26, 3, 'Good job!!!', '2026-04-05 14:36:38'),
(3, 12, 27, 23, 5, 'qwedcvbnm', '2026-04-13 10:38:39');

-- --------------------------------------------------------

--
-- Table structure for table `booking_payments`
--

DROP TABLE IF EXISTS `booking_payments`;
CREATE TABLE IF NOT EXISTS `booking_payments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `user_id` int NOT NULL COMMENT 'users.user_id (matches bookings.customer_id in this app)',
  `tx_ref` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` int NOT NULL COMMENT 'Whole units (e.g. MWK)',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MWK',
  `provider` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'stripe',
  `status` enum('pending','paid','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tx_ref` (`tx_ref`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_payments`
--

INSERT INTO `booking_payments` (`id`, `booking_id`, `user_id`, `tx_ref`, `amount`, `currency`, `provider`, `status`, `created_at`, `updated_at`) VALUES
(1, 9, 16, 'fes1e866210e71b5bd6d311a77546a914e8', 106513, 'USD', 'stripe', 'paid', '2026-04-02 02:37:40', '2026-04-02 02:39:46'),
(2, 9, 16, 'fesff9ef19c17c7d2069ecba0d77e6476e7', 106513, 'USD', 'stripe', 'cancelled', '2026-04-02 02:44:28', '2026-04-02 02:44:49'),
(3, 9, 16, 'fes11a137655049c9599fe300646782e5ca', 106513, 'USD', 'stripe', 'paid', '2026-04-02 02:44:49', '2026-04-02 02:47:05'),
(4, 9, 16, 'fesbfa09b79ea2927cc2c661cd5e199a2e2', 106513, 'USD', 'stripe', 'paid', '2026-04-02 02:50:49', '2026-04-02 02:51:30'),
(5, 9, 16, 'fesb30d7c870e9f3c7e6038124dcb590b09', 106513, 'MWK', 'stripe', 'paid', '2026-04-02 03:24:48', '2026-04-02 03:25:37'),
(6, 10, 27, 'fes026315328dc0110ce59199e8e84b8e66', 113802, 'MWK', 'stripe', 'paid', '2026-04-05 14:36:41', '2026-04-05 14:37:30'),
(7, 12, 27, 'fes740c618ed878b27bd462acbf06929472', 240630, 'MWK', 'stripe', 'paid', '2026-04-13 10:35:17', '2026-04-13 10:37:43');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
CREATE TABLE IF NOT EXISTS `customers` (
  `customer_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `user_id`, `phone`, `address`, `city`, `created_at`, `updated_at`) VALUES
(2, 16, '0993616624', 'md', 'Blantyre', '2026-03-01 20:39:33', '2026-04-03 09:10:51'),
(5, 27, NULL, NULL, NULL, '2026-04-05 13:48:30', '2026-04-05 13:48:30');

-- --------------------------------------------------------

--
-- Table structure for table `damage_reports`
--

DROP TABLE IF EXISTS `damage_reports`;
CREATE TABLE IF NOT EXISTS `damage_reports` (
  `damage_report_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `operator_id` int NOT NULL,
  `equipment_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('minor','major','critical') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('submitted','acknowledged','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`damage_report_id`),
  KEY `idx_damage_booking` (`booking_id`),
  KEY `idx_damage_operator` (`operator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `damage_reports`
--

INSERT INTO `damage_reports` (`damage_report_id`, `booking_id`, `operator_id`, `equipment_id`, `description`, `severity`, `photo_path`, `status`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 9, 23, 'EQ-001', 'flat tire', 'major', 'assets/uploads/damage_reports/dr_3eb7bd7874cad70d.jpg', 'acknowledged', 'will look into it asap', '2026-03-30 00:42:26', '2026-04-02 15:55:45');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

DROP TABLE IF EXISTS `equipment`;
CREATE TABLE IF NOT EXISTS `equipment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `equipment_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `equipment_id` varchar(100) NOT NULL,
  `model` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `status` enum('available','in_use','maintenance','retired') NOT NULL DEFAULT 'available',
  `location` varchar(255) NOT NULL,
  `operator_id` int DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `daily_rate` decimal(10,2) NOT NULL,
  `hourly_rate` decimal(10,2) DEFAULT '0.00',
  `per_hectare_rate` decimal(10,2) DEFAULT NULL,
  `fuel_type` varchar(50) DEFAULT NULL,
  `total_usage_hours` int DEFAULT '0',
  `year_manufactured` int DEFAULT NULL,
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `last_maintenance` date DEFAULT NULL,
  `icon` varchar(100) NOT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `operator_id` (`operator_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `equipment_name`, `category`, `equipment_id`, `model`, `description`, `status`, `location`, `operator_id`, `purchase_date`, `daily_rate`, `hourly_rate`, `per_hectare_rate`, `fuel_type`, `total_usage_hours`, `year_manufactured`, `weight_kg`, `last_maintenance`, `icon`, `image_path`, `created_at`, `updated_at`) VALUES
(4, 'Tractor MF 315', 'harvester', 'EQ-001', 'forgess', 'Crop harvester', 'available', 'Blantyre Depot', 0, '2026-03-17', 40000.00, 15000.00, 30000.00, 'diesel', 0, 2024, 3500.00, '2026-03-18', 'fa-wheat-awn', 'assets/images/equipment/1773842640_images (2).jpg', '2026-03-18 14:04:00', '2026-04-02 16:19:09'),
(5, 'Tractor MF 316', 'harvester', 'EQ-002', 'forgess', 'crop harvestor', 'available', 'Blantyre Depot', 0, '2026-03-17', 40000.00, 15000.00, 30000.00, 'diesel', 0, 2024, 3500.00, '2026-01-18', 'fa-wheat-awn', 'assets/images/equipment/1775396344_images (4).jpg', '2026-04-05 13:39:04', '2026-04-05 14:35:41'),
(6, 'Tractor MF 317', 'harvester', 'EQ-003', 'forgess', 'hv', 'available', 'Blantyre Depot', 0, '2026-03-17', 40000.00, 15000.00, 30000.00, 'diesel', 0, 2024, 3500.00, '2026-01-07', 'fa-wheat-awn', 'assets/images/equipment/1775396400_images (1).jpg', '2026-04-05 13:40:00', '2026-04-13 10:34:38');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_maintenance`
--

DROP TABLE IF EXISTS `equipment_maintenance`;
CREATE TABLE IF NOT EXISTS `equipment_maintenance` (
  `maintenance_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `maintenance_type` enum('routine','repair','overhaul','inspection') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `scheduled_date` date NOT NULL,
  `completed_date` date DEFAULT NULL,
  `cost` decimal(12,2) DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`maintenance_id`),
  KEY `idx_em_equipment` (`equipment_id`),
  KEY `idx_em_status` (`status`),
  KEY `idx_em_scheduled` (`scheduled_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment_maintenance`
--

INSERT INTO `equipment_maintenance` (`maintenance_id`, `equipment_id`, `maintenance_type`, `status`, `scheduled_date`, `completed_date`, `cost`, `description`, `admin_notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'EQ-001', 'repair', 'completed', '2026-04-06', '0000-00-00', 30000.00, 'fix broken window', '', 18, '2026-04-05 11:12:43', '2026-04-05 11:13:02'),
(2, 'EQ-001', 'inspection', 'scheduled', '2026-04-07', NULL, 50000.00, 'general inspection', NULL, 18, '2026-04-05 11:13:48', '2026-04-05 11:13:48');

-- --------------------------------------------------------

--
-- Table structure for table `fes_maintenance_email_sent`
--

DROP TABLE IF EXISTS `fes_maintenance_email_sent`;
CREATE TABLE IF NOT EXISTS `fes_maintenance_email_sent` (
  `sent_date` date NOT NULL,
  PRIMARY KEY (`sent_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fes_maintenance_email_sent`
--

INSERT INTO `fes_maintenance_email_sent` (`sent_date`) VALUES
('2026-04-05'),
('2026-04-07'),
('2026-04-09'),
('2026-04-14'),
('2026-04-21');

-- --------------------------------------------------------

--
-- Table structure for table `operator_availability`
--

DROP TABLE IF EXISTS `operator_availability`;
CREATE TABLE IF NOT EXISTS `operator_availability` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `operator_id` int NOT NULL,
  `day_of_week` tinyint UNSIGNED NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_availability_operator_day` (`operator_id`,`day_of_week`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `operator_availability`
--

INSERT INTO `operator_availability` (`id`, `operator_id`, `day_of_week`, `start_time`, `end_time`, `is_available`, `note`, `created_at`) VALUES
(2, 23, 1, '08:00:00', '17:00:00', 1, NULL, '2026-04-03 07:45:21');

-- --------------------------------------------------------

--
-- Table structure for table `operator_skills`
--

DROP TABLE IF EXISTS `operator_skills`;
CREATE TABLE IF NOT EXISTS `operator_skills` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `operator_id` int NOT NULL,
  `skill_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `skill_level` enum('beginner','intermediate','advanced','expert') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'intermediate',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_operator_skills_operator_id` (`operator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `operator_skills`
--

INSERT INTO `operator_skills` (`id`, `operator_id`, `skill_name`, `skill_level`, `created_at`) VALUES
(2, 23, 'land_prep', 'advanced', '2026-04-03 06:35:32'),
(3, 26, 'harvesting', 'intermediate', '2026-04-05 16:20:25'),
(4, 23, 'harvesting', 'advanced', '2026-04-13 12:30:37');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('customer','admin','operator') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password_reset_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password_hash`, `role`, `created_at`, `updated_at`, `password_reset_token`, `password_reset_expires`) VALUES
(16, 'Jon Snow', 'customer@mail.com', '$2y$10$DAFtazNfYgEdrNrWU6Kt8ulGTcSHKDbo/dTKxQiVzt1Y7YL5PuGCO', 'customer', '2026-03-01 20:39:33', '2026-04-03 09:28:45', NULL, NULL),
(18, 'Admin', 'Admin@mail.com', '$2y$10$kUWPC5RtFGjyXj/4R1sKMeBCSb5f/7MmJJob0j6GBZANtcBse0Cpe', 'admin', '2026-03-01 20:54:34', '2026-03-01 20:54:52', NULL, NULL),
(23, 'Adrian Malika 61', 'adrianmalika61@gmail.com', '$2y$10$d/i6tBSHLCYDWFvmavCjxue8TRutyCjKLLX8NjnXAnPP2wckomXWi', 'operator', '2026-03-09 08:41:09', '2026-03-20 02:39:38', NULL, NULL),
(26, 'Adrian Malika 03', 'adrianmalika03@gmail.com', '$2y$10$chIfx6qEoHV2oid5IGb13.REJIXe336V0A3PKNhCT3fVID8cXdchK', 'operator', '2026-03-10 01:57:32', '2026-03-20 02:39:46', NULL, NULL),
(27, 'Jack Mark', 'jackmark@gmail.com', '$2y$10$eireSu.IjrAEy3ML/LfBjesJf5lpaPoa7eccjsg.nReS/lVFIrcCm', 'customer', '2026-04-05 13:48:30', '2026-04-05 13:48:30', NULL, NULL);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `operator_availability`
--
ALTER TABLE `operator_availability`
  ADD CONSTRAINT `fk_operator_availability_user` FOREIGN KEY (`operator_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `operator_skills`
--
ALTER TABLE `operator_skills`
  ADD CONSTRAINT `fk_operator_skills_user` FOREIGN KEY (`operator_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
