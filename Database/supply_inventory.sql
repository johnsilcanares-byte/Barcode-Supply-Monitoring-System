-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 12, 2026 at 04:56 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `supply_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin') DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(4, 'admin', '', '$2y$10$kMSTs3QNJEEs1lbtkPORc.xzSU7gRbyOIJubMpuDvx6LMmPY.5q9W', 'admin', '2026-04-20 12:45:12');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `price` decimal(10,2) DEFAULT 0.00,
  `barcode` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `expiration_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `name`, `stock`, `price`, `barcode`, `quantity`, `is_active`, `expiration_date`) VALUES
(9, 'tissue', 18, 222.00, '833992870530', 0, 1, NULL),
(10, 'chair', 2, 222.00, '834613396354', 0, 1, NULL),
(11, 'charger', 1, 200.00, '914223459688', 0, 1, NULL),
(12, 'case', 22, 150.00, '914877562224', 0, 1, NULL),
(13, 'AIRCON', 23, 2500.00, '918238911199', 0, 1, NULL),
(14, 'battery', 12, 150.00, '919805671428', 0, 1, NULL),
(15, 'perfume', 18, 250.00, '037942888991', 0, 1, NULL),
(16, 'RGB LIGHTS', 1, 350.00, '139561632186', 0, 1, NULL),
(17, 'bag', 4, 150.00, '170191802665', 0, 1, NULL),
(18, 'socket', 4, 80.00, '170763791774', 0, 1, NULL),
(19, 'sardines', 25, 20.00, '6901073803052', 0, 1, NULL),
(20, 'Nature Spring', 1, 15.00, '5350701002168', 0, 1, NULL),
(21, 'Tinapay', 19, 5.00, '0792382371655', 0, 1, NULL),
(22, 'Bulad', 4, 20.00, '6281006056756', 0, 1, NULL),
(23, 'Bagoong', 2, 60.00, '8853502009994', 0, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'pcs',
  `cost_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `minimum_stock` int(11) DEFAULT 0,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_date` datetime DEFAULT current_timestamp(),
  `delivery_date` datetime DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `supplier_id`, `item_id`, `quantity`, `status`, `created_at`, `total_amount`, `order_date`, `delivery_date`, `expected_delivery_date`, `actual_delivery_date`, `notes`, `priority`, `created_by`, `updated_at`) VALUES
(20, 10, NULL, NULL, 'Completed', '2025-09-06 03:06:23', 2500.00, '2025-09-05 23:06:23', NULL, '2025-09-07', '2025-09-07', '2222', 'Medium', 1, '2025-09-07 17:36:13'),
(22, 10, NULL, NULL, 'Cancelled', '2025-09-07 17:03:35', 666.00, '2025-09-07 13:03:35', NULL, '2025-09-12', NULL, 'ingat', 'High', 1, '2025-09-07 17:17:15'),
(23, 10, NULL, NULL, 'Completed', '2025-09-07 17:06:22', 5000.00, '2025-09-07 13:06:22', NULL, '2025-09-08', NULL, '22', 'Medium', 1, '2025-09-07 17:31:25'),
(24, 11, NULL, NULL, 'Completed', '2025-09-07 17:38:19', 2500.00, '2025-09-07 13:38:19', NULL, '2025-09-07', NULL, '222', 'High', 1, '2025-09-07 17:40:01'),
(26, 11, NULL, NULL, 'Cancelled', '2025-09-07 17:40:38', 250.00, '2025-09-07 13:40:38', NULL, '2025-09-07', NULL, '', 'Low', 1, '2025-09-07 17:42:23'),
(27, 11, NULL, NULL, 'Completed', '2025-09-07 17:43:18', 2500.00, '2025-09-07 13:43:18', NULL, '2025-09-09', NULL, '32', 'High', 1, '2025-09-07 17:43:59'),
(28, 10, NULL, NULL, 'Cancelled', '2026-04-19 08:41:03', 280.00, '2026-04-19 16:41:03', NULL, '2026-04-21', NULL, 'ht', 'High', 1, '2026-04-19 08:42:26'),
(29, 10, NULL, NULL, 'Cancelled', '2026-04-19 08:46:43', 350.00, '2026-04-19 16:46:43', NULL, '2026-04-21', NULL, 'Kupal', 'High', 1, '2026-04-19 08:47:34'),
(30, 12, NULL, NULL, 'Completed', '2026-04-19 08:48:56', 80.00, '2026-04-19 16:48:56', NULL, '0000-00-00', NULL, 'jhhh', 'Medium', 1, '2026-04-19 08:49:06');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `purchase_order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `po_id`, `inventory_id`, `item_name`, `quantity`, `unit_cost`, `total_cost`, `received_quantity`, `purchase_order_id`, `item_id`, `unit_price`, `total_price`) VALUES
(14, 20, NULL, 'AIRCON', 1, 2500.00, 2500.00, 0, 0, 13, 0.00, 0.00),
(16, 22, NULL, 'chair', 3, 222.00, 666.00, 0, 0, 10, 0.00, 0.00),
(17, 23, NULL, 'AIRCON', 2, 2500.00, 5000.00, 0, 0, 13, 0.00, 0.00),
(18, 24, NULL, 'AIRCON', 1, 2500.00, 2500.00, 0, 0, 13, 0.00, 0.00),
(20, 26, NULL, 'perfume', 1, 250.00, 250.00, 0, 0, 15, 0.00, 0.00),
(21, 27, NULL, 'AIRCON', 1, 2500.00, 2500.00, 0, 0, 13, 0.00, 0.00),
(22, 28, NULL, 'socket', 3, 80.00, 240.00, 0, 0, 18, 0.00, 0.00),
(23, 28, NULL, 'sardines', 2, 20.00, 40.00, 0, 0, 19, 0.00, 0.00),
(24, 29, NULL, 'RGB LIGHTS', 1, 350.00, 350.00, 0, 0, 16, 0.00, 0.00),
(25, 30, NULL, 'socket', 1, 80.00, 80.00, 0, 0, 18, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sale_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `user_id`, `item_id`, `quantity`, `total_amount`, `created_at`, `sale_date`) VALUES
(5, 1, 14, 10, 1500.00, '2025-09-05 01:07:24', '2025-09-04 21:07:24'),
(7, 1, 9, 1, 222.00, '2025-09-06 04:42:08', '2025-09-06 00:42:08'),
(8, 1, 11, 1, 200.00, '2025-09-06 04:42:08', '2025-09-06 00:42:08'),
(10, 1, 9, 2, 444.00, '2025-09-06 05:40:11', '2025-09-06 01:40:11'),
(11, 1, 15, 1, 250.00, '2025-09-06 05:42:17', '2025-09-06 01:42:17'),
(12, 1, 15, 2, 500.00, '2025-09-06 05:44:14', '2025-09-06 01:44:14'),
(13, 1, 16, 1, 350.00, '2025-09-06 06:55:36', '2025-09-06 02:55:36'),
(14, 1, 16, 1, 350.00, '2025-09-06 15:43:09', '2025-09-06 11:43:09'),
(15, 1, 18, 1, 80.00, '2025-09-06 16:43:49', '2025-09-06 12:43:49'),
(16, 1, 10, 1, 222.00, '2025-09-06 16:47:02', '2025-09-06 12:47:02'),
(18, 1, 17, 1, 150.00, '2025-09-06 17:35:47', '2025-09-06 13:35:47'),
(19, 1, 18, 1, 80.00, '2025-09-06 19:40:03', '2025-09-06 15:40:03'),
(20, 1, 9, 1, 222.00, '2025-09-07 02:00:49', '2025-09-06 22:00:49'),
(21, 1, 15, 1, 250.00, '2025-09-07 02:48:08', '2025-09-06 22:48:08'),
(22, 1, 13, 1, 2500.00, '2025-09-07 18:40:53', '2025-09-07 14:40:53');

-- --------------------------------------------------------

--
-- Table structure for table `scanner_requests`
--

CREATE TABLE `scanner_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scanner_requests`
--

INSERT INTO `scanner_requests` (`id`, `user_id`, `status`, `created_at`) VALUES
(1, 4, 'approved', '2026-04-21 03:18:15'),
(2, 7, 'approved', '2026-04-21 03:37:29'),
(3, 8, 'approved', '2026-04-21 03:41:29'),
(4, 9, 'pending', '2026-07-12 02:52:06');

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `old_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `adjustment_type` enum('increase','decrease') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `adjusted_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_adjustments`
--

INSERT INTO `stock_adjustments` (`id`, `item_id`, `old_stock`, `new_stock`, `quantity`, `adjustment_type`, `reason`, `adjusted_by`, `created_at`) VALUES
(1, 10, 1, 21, 20, 'increase', 'Stock count', 1, '2026-04-19 07:48:13'),
(2, 10, 21, 1, 20, 'decrease', 'Stock count', 1, '2026-04-19 07:48:35'),
(3, 20, 2, 1, 1, 'decrease', 'Stock count', 1, '2026-04-19 08:08:22'),
(4, 13, 9, 0, 0, 'increase', 'Stock received', 1, '2026-04-19 08:50:09'),
(5, 13, 0, 18, 0, 'increase', 'Stock received', 1, '2026-04-19 08:50:31'),
(6, 21, 20, 21, 1, 'increase', 'Stock count', 1, '2026-04-19 12:44:40'),
(7, 21, 21, 22, 1, 'increase', 'Stock count', 1, '2026-04-19 12:52:43'),
(8, 21, 22, 23, 1, 'increase', 'Stock count', 1, '2026-04-19 12:53:59'),
(9, 21, 23, 24, 1, 'increase', 'Stock count', 1, '2026-04-19 12:54:03'),
(10, 21, 24, 25, 1, 'increase', 'Stock count', 1, '2026-04-19 12:54:44'),
(11, 13, 18, 19, 1, 'increase', 'Stock count', 4, '2026-04-20 14:02:19'),
(12, 13, 19, 20, 1, 'increase', 'Stock count', 4, '2026-04-20 14:08:45'),
(13, 13, 20, 21, 1, 'increase', 'Stock count', 4, '2026-04-20 14:09:00'),
(14, 13, 21, 22, 1, 'increase', 'Stock count', 4, '2026-04-20 14:09:13'),
(15, 13, 22, 23, 1, 'increase', 'Stock count', 4, '2026-04-20 14:10:45'),
(16, 10, 1, 2, 1, 'increase', 'Stock count', 4, '2026-04-20 14:41:41'),
(17, 22, 12, 5, 7, 'decrease', 'Stock count', 4, '2026-04-20 15:40:11'),
(18, 21, 25, 24, 1, 'decrease', 'Stock count', 4, '2026-04-21 03:50:28'),
(19, 21, 24, 20, 0, 'increase', 'Stock received', 4, '2026-04-21 03:50:57'),
(20, 21, 20, 19, 1, 'decrease', 'Stock count', 7, '2026-04-21 04:47:02');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('cashier','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scanner_approved` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`, `scanner_approved`) VALUES
(9, 'user', '', '$2y$10$2cQuaOIOCmOo6g21o0TKg.RJOv2A9mZppBHWUWjKN85tiDeUroMxu', 'user', '2026-07-12 02:49:47', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_sku` (`sku`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `fk_purchase_orders_created_by` (`created_by`),
  ADD KEY `fk_purchase_orders_supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `fk_inventory_item_id` (`item_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `sales_ibfk_2` (`item_id`);

--
-- Indexes for table `scanner_requests`
--
ALTER TABLE `scanner_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `scanner_requests`
--
ALTER TABLE `scanner_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
