-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 18, 2026 at 01:37 PM
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
  `equipment_id` varchar(50) NOT NULL,
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
  `status` enum('pending','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `customer_id` (`customer_id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `status` (`status`),
  KEY `idx_bookings_operator_id` (`operator_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `customer_id`, `operator_id`, `equipment_id`, `booking_date`, `service_days`, `service_type`, `service_location`, `contact_phone`, `field_lat`, `field_lng`, `field_address`, `field_polygon`, `field_hectares`, `notes`, `estimated_total_cost`, `status`, `created_at`, `updated_at`) VALUES
(5, 16, NULL, 'EQ-002', '2026-03-19', 1, 'harvesting', 'Lat -15.802805415416017, Lng 35.04574500482184', '0123456789', -15.8028054, 35.0457450, '', '[[35.04572688549649,-15.802009238430443],[35.04669324945749,-15.801945311675837],[35.046795925628345,-15.802660127878369],[35.04574500482184,-15.802805415416017]]', 0.92, 'NAONRAW', 113423.78, 'pending', '2026-03-17 11:24:05', '2026-03-18 11:00:05'),
(6, 16, 23, 'EQ-002', '2026-03-26', 1, 'land_prep', 'Lat -15.814461767184198, Lng 34.99920895591592', '0987654321', -15.8144618, 34.9992090, '', '[[34.99942948711649,-15.813744730572495],[35.00028119382523,-15.813920331610746],[35.000151916914035,-15.814622734237304],[34.99920895591592,-15.814461767184198]]', 0.79, 'Please call me on 0987654321 to confirm this booking and discuss any details', 106955.12, 'pending', '2026-03-18 11:26:17', '2026-03-18 11:39:52');

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `user_id`, `phone`, `address`, `city`, `created_at`, `updated_at`) VALUES
(2, 16, NULL, NULL, NULL, '2026-03-01 20:39:33', '2026-03-01 20:39:33');

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
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `equipment_name`, `category`, `equipment_id`, `model`, `description`, `status`, `location`, `operator_id`, `purchase_date`, `daily_rate`, `hourly_rate`, `per_hectare_rate`, `fuel_type`, `total_usage_hours`, `year_manufactured`, `weight_kg`, `last_maintenance`, `icon`, `image_path`, `created_at`, `updated_at`) VALUES
(3, 'Tractor MF 315', 'harvester', 'EQ-002', 'forgess', 'HAARVESTER', 'available', 'Blantyre Depot', 0, '2026-03-17', 40000.00, 15000.00, 30000.00, 'diesel', 0, 2024, 3500.00, '2026-03-17', 'fa-wheat-awn', 'assets/images/equipment/1773746505_farmers-portrait.png', '2026-03-17 11:21:45', '2026-03-17 11:21:45');

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password_hash`, `role`, `created_at`, `updated_at`, `password_reset_token`, `password_reset_expires`) VALUES
(16, 'Customer 1', 'customer@mail.com', '$2y$10$DAFtazNfYgEdrNrWU6Kt8ulGTcSHKDbo/dTKxQiVzt1Y7YL5PuGCO', 'customer', '2026-03-01 20:39:33', '2026-03-01 20:39:33', NULL, NULL),
(18, 'Admin', 'Admin@mail.com', '$2y$10$kUWPC5RtFGjyXj/4R1sKMeBCSb5f/7MmJJob0j6GBZANtcBse0Cpe', 'admin', '2026-03-01 20:54:34', '2026-03-01 20:54:52', NULL, NULL),
(23, 'Adrian Malik a 61', 'adrianmalika61@gmail.com', '$2y$10$d/i6tBSHLCYDWFvmavCjxue8TRutyCjKLLX8NjnXAnPP2wckomXWi', 'operator', '2026-03-09 08:41:09', '2026-03-18 11:39:29', NULL, NULL),
(26, 'Adrian Malik a 03', 'adrianmalika03@gmail.com', '$2y$10$chIfx6qEoHV2oid5IGb13.REJIXe336V0A3PKNhCT3fVID8cXdchK', 'operator', '2026-03-10 01:57:32', '2026-03-18 11:39:41', NULL, NULL);

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
