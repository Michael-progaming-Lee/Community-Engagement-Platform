-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 27, 2025 at 02:21 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `community_engagement_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_credentials`
--

CREATE TABLE `admin_credentials` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_credentials`
--

INSERT INTO `admin_credentials` (`id`, `username`, `email`, `password`, `created_at`) VALUES
(1, 'admin', 'admin@community.com', 'admin123', '2025-02-15 08:22:33');

-- --------------------------------------------------------

--
-- Table structure for table `banned_products`
--

CREATE TABLE `banned_products` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_seller_id` int(11) DEFAULT NULL,
  `banned_by` int(11) DEFAULT NULL,
  `banned_date` datetime DEFAULT current_timestamp(),
  `reason` varchar(255) DEFAULT 'Admin decision'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `banned_products`
--

INSERT INTO `banned_products` (`id`, `product_id`, `product_name`, `product_seller_id`, `banned_by`, `banned_date`, `reason`) VALUES
(1, 3, 'fsf', 2, 1, '2025-03-23 22:45:20', 'Admin decision'),
(2, 1, 'blender', 1, 1, '2025-03-23 22:58:26', 'Admin decision'),
(3, 1, 'blender', 1, 1, '2025-03-23 23:25:49', 'Admin decision'),
(4, 1, 'blender', 1, 1, '2025-03-26 19:23:09', 'Admin decision'),
(5, 1, 'blender', 1, 1, '2025-03-26 19:24:45', 'Admin decision');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_status`
--

CREATE TABLE `delivery_status` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL COMMENT 'ID from purchase_history table',
  `product_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `is_rental` tinyint(1) DEFAULT 0,
  `sent_for_delivery` tinyint(1) DEFAULT 0,
  `sent_date` timestamp NULL DEFAULT NULL,
  `received_by_buyer` tinyint(1) DEFAULT 0,
  `received_date` timestamp NULL DEFAULT NULL,
  `payment_processed` tinyint(1) DEFAULT 0,
  `status` varchar(50) DEFAULT 'pending' COMMENT 'pending, shipped, delivered, cancelled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_status`
--

INSERT INTO `delivery_status` (`id`, `purchase_id`, `product_id`, `buyer_id`, `seller_id`, `amount`, `is_rental`, `sent_for_delivery`, `sent_date`, `received_by_buyer`, `received_date`, `payment_processed`, `status`, `created_at`, `updated_at`) VALUES
(1, 18, 2, 2, 1, 600.00, 1, 1, '2025-03-15 17:11:57', 1, '2025-03-15 17:14:00', 1, 'delivered', '2025-03-15 16:33:34', '2025-03-15 17:14:00'),
(2, 19, 2, 2, 1, 600.00, 1, 1, '2025-03-15 16:58:18', 1, '2025-03-15 16:58:37', 1, 'delivered', '2025-03-15 16:42:27', '2025-03-15 16:58:37'),
(3, 20, 2, 2, 1, 600.00, 1, 1, '2025-03-15 17:23:13', 1, '2025-03-15 17:23:42', 1, 'delivered', '2025-03-15 17:22:00', '2025-03-15 17:23:42'),
(4, 21, 2, 2, 1, 600.00, 1, 1, '2025-03-15 18:47:12', 1, '2025-03-15 18:48:19', 1, 'delivered', '2025-03-15 18:46:42', '2025-03-15 18:48:19'),
(5, 22, 2, 2, 1, 600.00, 1, 1, '2025-03-15 19:12:19', 1, '2025-03-15 19:17:18', 1, 'delivered', '2025-03-15 19:11:45', '2025-03-15 19:17:18'),
(6, 23, 1, 2, 1, 4500.00, 0, 1, '2025-03-16 01:22:38', 1, '2025-03-16 01:23:10', 1, 'delivered', '2025-03-16 01:21:43', '2025-03-16 01:23:10'),
(7, 24, 2, 2, 1, 1800.00, 1, 1, '2025-03-16 01:22:43', 1, '2025-03-16 01:23:08', 1, 'delivered', '2025-03-16 01:21:43', '2025-03-16 01:23:08'),
(8, 25, 2, 2, 1, 100.00, 1, 1, '2025-03-24 18:15:09', 1, '2025-03-26 00:14:14', 1, 'delivered', '2025-03-24 18:14:04', '2025-03-26 00:14:14'),
(9, 26, 2, 2, 1, 600.00, 1, 0, NULL, 0, NULL, 0, 'pending', '2025-03-27 00:43:05', '2025-03-27 00:43:05');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `message`, `related_id`, `is_read`, `created_at`) VALUES
(1, 1, 'new_sale', 'New rental: Michael has made a rental of your product \'saw\' for $600.00. Please arrange delivery.', 1, 1, '2025-03-15 16:33:34'),
(2, 1, 'new_sale', 'New rental: Michael has made a rental of your product \'saw\' for $600.00. Please arrange delivery.', 2, 1, '2025-03-15 16:42:27'),
(3, 2, 'product_shipped', 'Your product \'saw\' has been shipped. Please confirm when you receive it.', 2, 1, '2025-03-15 16:58:18'),
(4, 1, 'payment_received', 'Product \'saw\' delivery confirmed. $600.00 has been added to your account balance.', 2, 1, '2025-03-15 16:58:37'),
(5, 2, 'product_shipped', 'Your product \'saw\' has been shipped. Please confirm when you receive it.', 1, 1, '2025-03-15 17:11:57'),
(6, 1, 'payment_received', 'Product \'saw\' delivery confirmed. $600.00 has been added to your account balance.', 1, 1, '2025-03-15 17:14:00'),
(7, 1, 'new_sale', 'New rental: Michael has made a rental of your product \'saw\' for $600.00. Please arrange delivery.', 3, 1, '2025-03-15 17:22:00'),
(8, 2, 'product_shipped', 'Your product \'saw\' has been shipped. Please confirm when you receive it.', 3, 1, '2025-03-15 17:23:13'),
(9, 1, 'payment_received', 'Product \'saw\' delivery confirmed. $600.00 has been added to your account balance.', 3, 1, '2025-03-15 17:23:42'),
(11, 1, 'rental_returned', 'Product \'saw\' has been returned by the renter.', 2, 1, '2025-03-15 18:23:09'),
(12, 1, 'new_sale', 'New rental: Michael has made a rental of your product \'saw\' for $600.00. Please arrange delivery.', 4, 1, '2025-03-15 18:46:42'),
(13, 2, 'product_shipped', 'Your product \'saw\' has been shipped. Please confirm when you receive it.', 4, 1, '2025-03-15 18:47:12'),
(14, 1, 'payment_received', 'Product \'saw\' delivery confirmed. $600.00 has been added to your account balance.', 4, 1, '2025-03-15 18:48:19'),
(15, 1, 'rental_returned', 'Product \'saw\' has been returned by the renter.', 2, 1, '2025-03-15 18:48:43'),
(16, 1, 'new_sale', 'New rental: Michael has made a rental of your product \'saw\' for $600.00. Please arrange delivery.', 5, 1, '2025-03-15 19:11:45'),
(17, 2, 'product_shipped', 'Your product \'saw\' has been shipped. Please confirm when you receive it.', 5, 1, '2025-03-15 19:12:19'),
(18, 1, 'payment_received', 'Product \'saw\' delivery confirmed. $600.00 has been added to your account balance.', 5, 1, '2025-03-15 19:17:18'),
(19, 1, 'new_sale', 'New purchase: Michael has made a purchase of your product \'blender\' for $4,500.00. Please arrange delivery.', 6, 1, '2025-03-16 01:21:43'),
(20, 1, 'new_sale', 'New rental: Michael has made a rental of your product \'saw\' for $1,800.00. Please arrange delivery.', 7, 1, '2025-03-16 01:21:43'),
(21, 2, 'product_shipped', 'Your product \'blender\' has been shipped. Please confirm when you receive it.', 6, 1, '2025-03-16 01:22:38'),
(22, 2, 'product_shipped', 'Your product \'saw\' has been shipped. Please confirm when you receive it.', 7, 1, '2025-03-16 01:22:43'),
(23, 1, 'payment_received', 'Product \'saw\' delivery confirmed. $1,800.00 has been added to your account balance.', 7, 1, '2025-03-16 01:23:08'),
(24, 1, 'payment_received', 'Product \'blender\' delivery confirmed. $4,500.00 has been added to your account balance.', 6, 1, '2025-03-16 01:23:10'),
(25, 1, 'rental_returned', 'Product \'saw\' has been returned by the renter.', 2, 1, '2025-03-16 01:24:08'),
(26, 1, 'product_banned', 'Your product \'blender\' has been banned by an administrator. It will no longer be visible to buyers.', 1, 1, '2025-03-24 04:25:49'),
(27, 1, 'new_sale', 'New rental: Michael has made a rental of your product \'saw\' for $100.00. Please arrange delivery.', 8, 1, '2025-03-24 18:14:04'),
(28, 2, 'product_shipped', 'Your product \'saw\' has been shipped. Please confirm when you receive it.', 8, 1, '2025-03-24 18:15:09'),
(29, 1, 'payment_received', 'Product \'saw\' delivery confirmed. $100.00 has been added to your account balance.', 8, 1, '2025-03-26 00:14:14'),
(30, 1, 'product_banned', 'Your product \'blender\' has been banned by an administrator. It will no longer be visible to buyers.', 1, 0, '2025-03-27 00:23:09'),
(31, 1, 'product_banned', 'Your product \'blender\' has been banned by an administrator. It will no longer be visible to buyers.', 1, 0, '2025-03-27 00:24:45'),
(32, 1, 'new_sale', 'New rental: Michael has made a rental of your product \'saw\' for $600.00. Please arrange delivery.', 9, 0, '2025-03-27 00:43:05'),
(33, 2, 'rental_overdue', 'Your rental of \'saw\' is overdue by 2 days. \n                                A late fee of 5% of the rental price per day (approximately $10.00 so far) \n                                will be charged when you return the item. Please return it as soon as possible.', 13, 1, '2025-03-27 00:43:12'),
(34, 1, 'rental_returned', 'Product \'saw\' has been returned by the renter.', 2, 0, '2025-03-27 00:43:27'),
(35, 1, 'rental_returned', 'Product \'saw\' has been returned by the renter.', 2, 0, '2025-03-27 00:43:31');

-- --------------------------------------------------------

--
-- Table structure for table `price_negotiation`
--

CREATE TABLE `price_negotiation` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `original_price` decimal(10,2) NOT NULL,
  `proposed_price` decimal(10,2) NOT NULL,
  `seller_response` enum('pending','accepted','rejected') DEFAULT 'pending',
  `final_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `price_negotiation`
--

INSERT INTO `price_negotiation` (`id`, `product_id`, `user_id`, `seller_id`, `original_price`, `proposed_price`, `seller_response`, `final_price`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 1, 4500.00, 5000.00, 'pending', NULL, '2025-03-22 20:43:53', '2025-03-22 20:43:53');

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `id` int(11) NOT NULL,
  `product_seller_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_category` varchar(255) NOT NULL,
  `product_description` text NOT NULL,
  `product_quantity` int(11) DEFAULT NULL,
  `product_cost` decimal(10,0) DEFAULT NULL,
  `product_img` text DEFAULT NULL,
  `product_qr_code` varchar(255) DEFAULT NULL,
  `listing_type` varchar(10) DEFAULT 'sell',
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `weekly_rate` decimal(10,2) DEFAULT NULL,
  `monthly_rate` decimal(10,2) DEFAULT NULL,
  `status` enum('approved','banned') DEFAULT 'approved' COMMENT 'Product status: approved or banned'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`id`, `product_seller_id`, `product_name`, `product_category`, `product_description`, `product_quantity`, `product_cost`, `product_img`, `product_qr_code`, `listing_type`, `daily_rate`, `weekly_rate`, `monthly_rate`, `status`) VALUES
(1, 1, 'blender', 'Appliances', 'Spineeroo', 2, 4500, 'uploads/67d072d3c60dd.png', 'qrcodes/product_1.png', 'sell', NULL, NULL, NULL, 'approved'),
(2, 1, 'saw', 'Tool', 'cut cut', 8, NULL, 'uploads/67d073290bd4c.jpg', 'qrcodes/product_2.png', 'rent', 100.00, 600.00, 1200.00, 'approved'),
(4, 4, 'Hilti jack hammer', 'Tool', 'Brand New electric Hilti hammer with drill bit', 5, 80000, 'uploads/67e499457fc93.jpg', 'qrcodes/product_4.png', 'sell', NULL, NULL, NULL, 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `product_comments`
--

CREATE TABLE `product_comments` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_comments`
--

INSERT INTO `product_comments` (`id`, `product_id`, `user_id`, `username`, `comment_text`, `created_at`) VALUES
(1, 2, 2, 'Michael', 'very good', '2025-03-27 00:41:36');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_history`
--

CREATE TABLE `purchase_history` (
  `id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `purchase_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `rental_start_date` date DEFAULT NULL,
  `rental_end_date` date DEFAULT NULL,
  `return_date` datetime DEFAULT NULL,
  `rental_duration` int(11) DEFAULT NULL,
  `duration_unit` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_history`
--

INSERT INTO `purchase_history` (`id`, `buyer_id`, `product_id`, `status`, `price`, `purchase_date`, `rental_start_date`, `rental_end_date`, `return_date`, `rental_duration`, `duration_unit`) VALUES
(1, 2, 2, '0', 600.00, '2025-03-11 19:33:47', NULL, NULL, NULL, NULL, NULL),
(2, 2, 1, '0', 9000.00, '2025-03-11 19:52:37', NULL, NULL, NULL, NULL, NULL),
(3, 2, 1, '0', 9000.00, '2025-03-11 19:57:51', NULL, NULL, NULL, NULL, NULL),
(4, 2, 2, '0', 600.00, '2025-03-11 21:02:23', NULL, NULL, NULL, NULL, NULL),
(5, 2, 2, '0', 1200.00, '2025-03-12 00:26:31', NULL, NULL, NULL, NULL, NULL),
(6, 2, 2, '0', 600.00, '2025-03-13 00:50:54', NULL, NULL, NULL, NULL, NULL),
(7, 2, 2, '0', 600.00, '2025-03-13 01:02:07', NULL, NULL, NULL, NULL, NULL),
(8, 2, 2, '0', 600.00, '2025-03-13 01:08:19', NULL, NULL, NULL, NULL, NULL),
(9, 2, 2, '0', 600.00, '2025-03-13 01:35:58', NULL, NULL, NULL, NULL, NULL),
(10, 2, 2, '0', 600.00, '2025-03-13 01:40:24', NULL, NULL, NULL, NULL, NULL),
(11, 2, 2, '0', 600.00, '2025-03-13 01:50:15', NULL, NULL, NULL, NULL, NULL),
(12, 2, 2, 'returned', 0.00, '2025-03-13 02:08:07', '2025-03-13', '1970-01-01', NULL, 7, '0'),
(13, 2, 2, 'returned', 0.00, '2025-03-13 02:20:47', '2025-03-13', '1970-01-01', NULL, 7, '0'),
(14, 2, 2, 'returned', 600.00, '2025-03-14 01:53:14', '2025-03-14', '1970-01-01', NULL, 7, '0'),
(15, 2, 2, 'returned', 600.00, '2025-03-14 01:58:52', '2025-03-14', '1970-01-01', NULL, 7, '0'),
(16, 2, 2, 'returned', 600.00, '2025-03-14 02:07:12', '2025-03-14', '2025-03-21', NULL, 7, '0'),
(17, 2, 2, 'returned', 600.00, '2025-03-14 02:21:32', '2025-03-14', '2025-03-21', NULL, 7, '0'),
(18, 2, 2, 'completed', 600.00, '2025-03-15 16:33:34', '2025-03-15', '2025-03-22', NULL, 7, '0'),
(19, 2, 2, 'completed', 600.00, '2025-03-15 16:42:27', '2025-03-15', '2025-03-22', NULL, 7, '0'),
(20, 2, 2, 'completed', 600.00, '2025-03-15 17:22:00', '2025-03-15', '2025-03-22', NULL, 7, '0'),
(21, 2, 2, 'completed', 600.00, '2025-03-15 18:46:42', '2025-03-15', '2025-03-22', NULL, 7, '0'),
(22, 2, 2, 'completed', 600.00, '2025-03-15 19:11:45', '2025-03-15', '2025-03-22', NULL, 7, '0'),
(23, 2, 1, 'completed', 4500.00, '2025-03-16 01:21:43', NULL, NULL, NULL, NULL, NULL),
(24, 2, 2, 'completed', 1800.00, '2025-03-16 01:21:43', '2025-03-16', '2025-04-03', NULL, 18, '0'),
(25, 2, 2, 'completed', 100.00, '2025-03-24 18:14:04', '2025-03-24', '2025-03-25', NULL, 1, '0'),
(26, 2, 2, 'returned', 600.00, '2025-03-27 00:43:05', '2025-03-27', '2025-04-03', NULL, 7, '0');

-- --------------------------------------------------------

--
-- Table structure for table `rental_products`
--

CREATE TABLE `rental_products` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `rental_price` decimal(10,2) NOT NULL,
  `rental_start_date` date NOT NULL,
  `rental_end_date` date NOT NULL,
  `rental_duration` int(11) NOT NULL,
  `duration_unit` varchar(20) NOT NULL DEFAULT 'days',
  `status` enum('rented','returned','overdue') NOT NULL DEFAULT 'rented',
  `return_date` date DEFAULT NULL,
  `late_fee` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `overdue_notified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag to track if overdue notification has been sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rental_products`
--

INSERT INTO `rental_products` (`id`, `product_id`, `buyer_id`, `seller_id`, `rental_price`, `rental_start_date`, `rental_end_date`, `rental_duration`, `duration_unit`, `status`, `return_date`, `late_fee`, `notes`, `created_at`, `updated_at`, `overdue_notified`) VALUES
(2, 2, 2, 1, 0.00, '2025-03-13', '1970-01-01', 7, 'weekly', 'returned', '2025-03-13', 0.00, 'Purchase History ID: 13\nReturned on: 2025-03-13', '2025-03-13 02:20:47', '2025-03-13 02:22:52', 0),
(3, 2, 2, 1, 600.00, '2025-03-14', '1970-01-01', 7, 'weekly', 'returned', '2025-03-14', 600.00, 'Purchase History ID: 14\nReturned on: 2025-03-14', '2025-03-14 01:53:14', '2025-03-14 01:58:29', 0),
(4, 2, 2, 1, 600.00, '2025-03-14', '1970-01-01', 7, 'weekly', 'returned', '2025-03-14', 600.00, 'Purchase History ID: 15\nReturned on: 2025-03-14', '2025-03-14 01:58:52', '2025-03-14 02:06:23', 0),
(5, 2, 2, 1, 600.00, '2025-03-14', '2025-03-21', 7, 'weekly', 'returned', '2025-03-14', 0.00, 'Purchase History ID: 16\nReturned on: 2025-03-14', '2025-03-14 02:07:12', '2025-03-14 02:15:44', 0),
(6, 2, 2, 1, 600.00, '2025-03-14', '2025-03-21', 7, 'weekly', 'returned', '2025-03-14', 0.00, 'Purchase History ID: 17\nReturned on: 2025-03-14', '2025-03-14 02:21:32', '2025-03-14 02:21:58', 0),
(7, 2, 2, 1, 600.00, '2025-03-15', '2025-03-22', 7, 'weekly', 'returned', '2025-03-15', 0.00, 'Purchase History ID: 18\nReturned on: 2025-03-15', '2025-03-15 16:33:34', '2025-03-15 16:33:57', 0),
(8, 2, 2, 1, 600.00, '2025-03-15', '2025-03-22', 7, 'weekly', 'returned', '2025-03-15', 0.00, 'Purchase History ID: 19\nReturned on: 2025-03-15', '2025-03-15 16:42:27', '2025-03-15 17:34:35', 0),
(9, 2, 2, 1, 600.00, '2025-03-15', '2025-03-22', 7, 'weekly', 'returned', '2025-03-15', 0.00, 'Purchase History ID: 20\nReturned on: 2025-03-15', '2025-03-15 17:22:00', '2025-03-15 18:23:09', 0),
(10, 2, 2, 1, 600.00, '2025-03-15', '2025-03-22', 7, 'weekly', 'returned', '2025-03-15', 0.00, 'Purchase History ID: 21\nReturned on: 2025-03-15', '2025-03-15 18:46:42', '2025-03-15 18:48:43', 0),
(11, 2, 2, 1, 600.00, '2025-03-15', '2025-03-22', 7, 'weekly', 'returned', '2025-03-16', 0.00, 'Purchase History ID: 22\nReturned on: 2025-03-16', '2025-03-15 19:11:45', '2025-03-16 01:24:08', 0),
(12, 2, 2, 1, 1800.00, '2025-03-16', '2025-04-03', 18, 'weekly', 'returned', '2025-03-27', 0.00, 'Purchase History ID: 24\nReturned on: 2025-03-27', '2025-03-16 01:21:43', '2025-03-27 00:43:27', 0),
(13, 2, 2, 1, 100.00, '2025-03-24', '2025-03-25', 1, 'daily', 'rented', NULL, 0.00, 'Purchase History ID: 25', '2025-03-24 18:14:04', '2025-03-27 00:43:12', 1),
(14, 2, 2, 1, 600.00, '2025-03-27', '2025-04-03', 7, 'weekly', 'returned', '2025-03-27', 0.00, 'Purchase History ID: 26\nReturned on: 2025-03-27', '2025-03-27 00:43:05', '2025-03-27 00:43:31', 0);

--
-- Triggers `rental_products`
--
DELIMITER $$
CREATE TRIGGER `after_rent_product` AFTER INSERT ON `rental_products` FOR EACH ROW BEGIN
    UPDATE product 
    SET product_quantity = product_quantity - 1
    WHERE id = NEW.product_id AND product_quantity > 0;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_return_product` AFTER UPDATE ON `rental_products` FOR EACH ROW BEGIN
    IF NEW.status = 'returned' AND OLD.status = 'rented' THEN
        UPDATE product 
        SET product_quantity = product_quantity + 1
        WHERE id = NEW.product_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `rental_reports`
--

CREATE TABLE `rental_reports` (
  `id` int(11) NOT NULL,
  `rental_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `days_overdue` int(11) NOT NULL,
  `report_reason` text NOT NULL,
  `report_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','reviewed','resolved') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `resolved_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `Id` int(11) NOT NULL,
  `Username` varchar(200) DEFAULT NULL,
  `Email` varchar(200) DEFAULT NULL,
  `Age` int(11) DEFAULT NULL,
  `Parish` varchar(50) DEFAULT NULL,
  `Password` varchar(200) DEFAULT NULL,
  `AccountBalance` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`Id`, `Username`, `Email`, `Age`, `Parish`, `Password`, `AccountBalance`, `created_at`, `status`) VALUES
(1, 'Spike', 'Spike@gmail.com', 23, 'Trelawny', 'Spike', 9400.00, '2025-03-11 17:38:08', 'active'),
(2, 'Michael', 'Michael@gmail.com', 23, 'Trelawny', 'Spike', 52400.00, '2025-03-11 17:38:08', 'active'),
(3, 'Micah', 'SSS@gmail.com', 23, 'Trelawny', '123', 0.00, '2025-03-16 23:35:47', 'active'),
(4, 'Bobby', 'stay@home.com', 25, 'Trelawny', '123456', 0.00, '2025-03-27 00:16:14', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `users_cart`
--

CREATE TABLE `users_cart` (
  `Id` int(11) NOT NULL,
  `UserID` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_category` varchar(255) NOT NULL,
  `product_description` text NOT NULL,
  `product_quantity` int(11) DEFAULT NULL,
  `product_cost` decimal(10,0) DEFAULT NULL,
  `product_img` text DEFAULT NULL,
  `product_total` decimal(10,0) DEFAULT NULL,
  `rental_start_date` date DEFAULT NULL,
  `rental_end_date` date DEFAULT NULL,
  `rental_duration` int(11) DEFAULT NULL,
  `duration_unit` varchar(20) DEFAULT NULL,
  `listing_type` varchar(50) NOT NULL DEFAULT 'purchase'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `banned_products`
--
ALTER TABLE `banned_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `banned_by` (`banned_by`);

--
-- Indexes for table `delivery_status`
--
ALTER TABLE `delivery_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `type` (`type`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `price_negotiation`
--
ALTER TABLE `price_negotiation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_seller_id` (`product_seller_id`);

--
-- Indexes for table `product_comments`
--
ALTER TABLE `product_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `purchase_history`
--
ALTER TABLE `purchase_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rental_products`
--
ALTER TABLE `rental_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buyer_idx` (`buyer_id`),
  ADD KEY `product_idx` (`product_id`),
  ADD KEY `status_idx` (`status`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `rental_reports`
--
ALTER TABLE `rental_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rental_id` (`rental_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `users_cart`
--
ALTER TABLE `users_cart`
  ADD PRIMARY KEY (`Id`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `banned_products`
--
ALTER TABLE `banned_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `delivery_status`
--
ALTER TABLE `delivery_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `price_negotiation`
--
ALTER TABLE `price_negotiation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_comments`
--
ALTER TABLE `product_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_history`
--
ALTER TABLE `purchase_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `rental_products`
--
ALTER TABLE `rental_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `rental_reports`
--
ALTER TABLE `rental_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users_cart`
--
ALTER TABLE `users_cart`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `banned_products`
--
ALTER TABLE `banned_products`
  ADD CONSTRAINT `banned_products_ibfk_1` FOREIGN KEY (`banned_by`) REFERENCES `users` (`Id`);

--
-- Constraints for table `price_negotiation`
--
ALTER TABLE `price_negotiation`
  ADD CONSTRAINT `price_negotiation_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`),
  ADD CONSTRAINT `price_negotiation_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`Id`),
  ADD CONSTRAINT `price_negotiation_ibfk_3` FOREIGN KEY (`seller_id`) REFERENCES `users` (`Id`);

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`product_seller_id`) REFERENCES `users` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `product_comments`
--
ALTER TABLE `product_comments`
  ADD CONSTRAINT `product_comments_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_products`
--
ALTER TABLE `rental_products`
  ADD CONSTRAINT `rental_products_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rental_products_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rental_products_ibfk_3` FOREIGN KEY (`seller_id`) REFERENCES `users` (`Id`) ON DELETE CASCADE;

--
-- Constraints for table `users_cart`
--
ALTER TABLE `users_cart`
  ADD CONSTRAINT `users_cart_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`Id`) ON DELETE CASCADE,
  ADD CONSTRAINT `users_cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
