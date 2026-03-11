-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 11, 2026 at 09:28 AM
-- Server version: 8.0.31
-- PHP Version: 8.0.26

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
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `equipment_name`, `category`, `equipment_id`, `model`, `description`, `status`, `location`, `operator_id`, `purchase_date`, `daily_rate`, `hourly_rate`, `fuel_type`, `total_usage_hours`, `year_manufactured`, `weight_kg`, `last_maintenance`, `icon`, `image_path`, `created_at`, `updated_at`) VALUES
(1, 'Tractor MF 315', 'tractor', 'EQ-001', 'forgess', 'gg', 'available', 'Blantyre Depot', 20, '2026-03-08', '200000.00', '16500.00', 'diesel', 0, 2024, '3500.00', '2026-03-08', 'fa-tractor', 'assets/images/equipment/1772980299_images (2).jpg', '2026-03-08 14:31:39', '2026-03-09 08:22:25'),
(2, 'Tractor MF 315', 'tractor', 'EQ-002', 'forgess', 'ddd', 'available', 'Blantyre Depot', 23, '2026-03-08', '200000.00', '16500.00', 'diesel', 0, 2024, '3500.00', '2026-03-08', 'fa-wheat-awn', 'assets/images/equipment/1772980419_images (2).jpg', '2026-03-08 14:33:39', '2026-03-09 17:03:28');

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
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_availability_operator_day` (`operator_id`,`day_of_week`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `operator_availability`
--

INSERT INTO `operator_availability` (`id`, `operator_id`, `day_of_week`, `start_time`, `end_time`, `is_available`, `note`, `created_at`) VALUES
(1, 23, 1, '06:06:00', '18:06:00', 1, NULL, '2026-03-10 03:06:50');

-- --------------------------------------------------------

--
-- Table structure for table `operator_skills`
--

DROP TABLE IF EXISTS `operator_skills`;
CREATE TABLE IF NOT EXISTS `operator_skills` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `operator_id` int NOT NULL,
  `skill_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `skill_level` enum('beginner','intermediate','advanced','expert') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'intermediate',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_operator_skills_operator_id` (`operator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `operator_skills`
--

INSERT INTO `operator_skills` (`id`, `operator_id`, `skill_name`, `skill_level`, `created_at`) VALUES
(1, 23, 'Tractor operator', 'intermediate', '2026-03-10 03:05:59');

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
  `password_reset_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
(23, 'Adrian Malika', 'adrianmalika61@gmail.com', '$2y$10$d/i6tBSHLCYDWFvmavCjxue8TRutyCjKLLX8NjnXAnPP2wckomXWi', 'operator', '2026-03-09 08:41:09', '2026-03-09 08:41:09', NULL, NULL),
(26, 'Adrian Malika', 'adrianmalika03@gmail.com', '$2y$10$chIfx6qEoHV2oid5IGb13.REJIXe336V0A3PKNhCT3fVID8cXdchK', 'operator', '2026-03-10 01:57:32', '2026-03-10 01:58:15', NULL, NULL);

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
