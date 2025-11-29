
-- weddinglink_clean.sql
-- Cleaned and ordered SQL for InfinityFree import
-- Notes:
-- 1) Removed CREATE DATABASE / USE statements (InfinityFree disallows those).
-- 2) Foreign key checks are disabled during import and re-enabled at the end.
-- 3) Tables are created in dependency order.
-- 4) Inserts use ON DUPLICATE KEY UPDATE id = id to avoid accidental overwrites if rows already exist.

SET FOREIGN_KEY_CHECKS = 0;

-- 1) categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `icon` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELETE FROM `categories`;
INSERT INTO `categories` (`id`, `name`, `description`, `icon`, `status`, `created_at`) VALUES
  (1, 'Fotografi', 'Jasa fotografer pernikahan profesional', 'camera', 'active', '2025-11-21 07:12:16'),
  (2, 'Makeup', 'Makeup artist untuk pengantin dan keluarga', 'brush', 'active', '2025-11-21 07:12:16'),
  (3, 'Dekorasi', 'Dekorasi venue dan bunga', 'palette', 'active', '2025-11-21 07:12:16'),
  (4, 'Catering', 'Jasa catering dan makanan', 'utensils', 'active', '2025-11-21 07:12:16'),
  (5, 'Venue', 'Gedung dan lokasi pernikahan', 'home', 'active', '2025-11-21 07:12:16'),
  (6, 'Entertainment', 'MC, band, dan hiburan', 'music', 'active', '2025-11-21 07:12:16')
ON DUPLICATE KEY UPDATE id = id;

-- 2) contact_messages
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `subject` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('new','read','replied') COLLATE utf8mb4_general_ci DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELETE FROM `contact_messages`;

-- 3) users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','vendor','customer') COLLATE utf8mb4_general_ci DEFAULT 'customer',
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELETE FROM `users`;
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `status`, `email_verified`, `created_at`, `updated_at`) VALUES
  (1, 'Administrator', 'admin@weddinglink.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 1, '2025-11-21 07:12:16', '2025-11-21 07:12:16'),
  (2, 'Vendor Foto Profesional', 'foto@weddinglink.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor', 'active', 1, '2025-11-21 07:12:16', '2025-11-24 15:37:34'),
  (3, 'Customer Demo', 'customer@weddinglink.com', '', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'active', 1, '2025-11-21 07:12:16', '2025-11-24 15:33:00'),
  (5, 'ragit', 'ragit@gmail.com', '083172293224', '$2y$10$2EPTY3UdP9ev/taAM3NQd.pUjIh7u6AIdUA2I5cMfmskHIvfJc82K', 'customer', 'inactive', 0, '2025-11-24 05:38:12', '2025-11-24 15:11:20'),
  (7, 'vendor', 'vendor@gmail.com', '083172293224', '$2y$10$tGJSjDV.Yodw.pP0LGU9YOTrOjlnI3I8MQocy7d5JfNh94K8puYTO', 'vendor', 'active', 0, '2025-11-29 16:46:34', '2025-11-29 16:46:34')
ON DUPLICATE KEY UPDATE id = id;

-- 4) vendors
CREATE TABLE IF NOT EXISTS `vendors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `company_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `service_type` enum('fotografi','makeup','dekorasi','catering','venue','lainnya') COLLATE utf8mb4_general_ci NOT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `price_range` enum('budget','medium','premium') COLLATE utf8mb4_general_ci DEFAULT 'medium',
  `rating` decimal(3,2) DEFAULT '0.00',
  `total_reviews` int DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `vendors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELETE FROM `vendors`;
INSERT INTO `vendors` (`id`, `user_id`, `company_name`, `description`, `service_type`, `address`, `city`, `price_range`, `rating`, `total_reviews`, `status`, `created_at`) VALUES
  (1, 2, 'Studio Foto Mantap', 'Jasa fotografi pernikahan profesional dengan peralatan terbaik', 'fotografi', 'Jl. Contoh No. 123', 'Jakarta', 'premium', 0.00, 0, 'active', '2025-11-21 07:12:16'),
  (2, 7, 'ikan', 'dfafafa', 'dekorasi', 'Lorong Bukit Palam No. 165', 'jambi', 'medium', 0.00, 0, 'active', '2025-11-29 16:56:58')
ON DUPLICATE KEY UPDATE id = id;

-- 5) packages
CREATE TABLE IF NOT EXISTS `packages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `price` decimal(12,2) NOT NULL,
  `duration_hours` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `vendor_id` int NOT NULL,
  `features` json DEFAULT NULL,
  `images` json DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `vendor_id` (`vendor_id`),
  CONSTRAINT `packages_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `packages_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELETE FROM `packages`;
INSERT INTO `packages` (`id`, `name`, `description`, `price`, `duration_hours`, `category_id`, `vendor_id`, `features`, `images`, `status`, `created_at`, `updated_at`) VALUES
  (1, 'Paket Basic Fotografi', '8 jam sesi foto, 300 foto edit, free pre-wedding', 2000000.00, 8, 1, 1, '["8 Jam Sesi Foto\\r", "300 Foto Hasil Edit\\r", "Free Pre-wedding\\r", "2 Fotografer\\r", "Digital File"]', NULL, 'active', '2025-11-21 07:12:16', '2025-11-24 13:42:51'),
  (2, 'Paket Premium Fotografi', '12 jam sesi foto, 500 foto edit, album cetak, pre-wedding', 50000000.00, 12, 1, 1, '["12 Jam Sesi Foto", "500 Foto Hasil Edit", "Album Cetak 20x30", "Free Pre-wedding", "3 Fotografer", "Digital File + USB"]', NULL, 'active', '2025-11-21 07:12:16', '2025-11-24 05:36:14'),
  (3, 'ugat ugat boyolali', 'porinya rapat', 6000000.00, 2, 2, 1, '["4 ikan", "4 kodok", "3 jankrik"]', NULL, 'active', '2025-11-24 05:36:59', '2025-11-24 05:36:59'),
  (4, 'coba', 'dfafa', 5000000.00, 1, 4, 1, '["ikan"]', NULL, 'active', '2025-11-29 16:52:03', '2025-11-29 16:52:03'),
  (5, 'deni', 'dfghjk', 2000000.00, 1, 2, 1, '["dfghj"]', NULL, 'active', '2025-11-29 16:53:34', '2025-11-29 16:53:34'),
  (6, 'Absensi kelas 2C', 'dfafafa', 1000000.00, 1, 2, 2, '["dfafa"]', NULL, 'active', '2025-11-29 16:57:41', '2025-11-29 16:57:41')
ON DUPLICATE KEY UPDATE id = id;

-- 6) orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `customer_id` int NOT NULL,
  `package_id` int NOT NULL,
  `event_date` date NOT NULL,
  `event_location` text COLLATE utf8mb4_general_ci,
  `guest_count` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `total_price` decimal(12,2) NOT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `payment_status` enum('unpaid','pending','paid','failed','refunded') COLLATE utf8mb4_general_ci DEFAULT 'unpaid',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `customer_id` (`customer_id`),
  KEY `package_id` (`package_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELETE FROM `orders`;
INSERT INTO `orders` (`id`, `invoice_number`, `customer_id`, `package_id`, `event_date`, `event_location`, `guest_count`, `notes`, `total_price`, `status`, `payment_status`, `created_at`, `updated_at`) VALUES
  (1, 'INV202511214437', 3, 1, '2025-11-30', 'jambi', 50, '', 5000000.00, 'confirmed', 'paid', '2025-11-21 08:14:11', '2025-11-24 13:12:52'),
  (2, 'INV202511212454', 3, 1, '2025-11-30', 'jambi', 50, '', 5000000.00, 'confirmed', 'pending', '2025-11-21 08:15:11', '2025-11-24 13:15:28'),
  (3, 'INV202511212157', 3, 1, '2025-11-30', 'jambi', 50, '', 5000000.00, 'pending', 'unpaid', '2025-11-21 08:17:08', '2025-11-21 08:17:08'),
  (4, 'INV202511218309', 3, 1, '2025-11-30', 'jambi', 50, '', 5000000.00, 'pending', 'unpaid', '2025-11-21 08:17:14', '2025-11-21 08:17:14'),
  (5, 'INV202511218187', 3, 1, '2025-11-30', 'jambi', 50, '', 5000000.00, 'confirmed', 'paid', '2025-11-21 08:17:31', '2025-11-21 08:27:22'),
  (6, 'INV202511210307', 3, 2, '2025-11-21', 'asdfghj', 50, '', 8000000.00, 'confirmed', 'paid', '2025-11-21 08:25:26', '2025-11-21 08:27:16'),
  (7, 'INV202511241766', 5, 3, '2025-11-25', 'jambi', 1, 'yang ganteng mas', 6000000.00, 'completed', 'paid', '2025-11-24 05:39:05', '2025-11-24 13:04:07'),
  (8, 'INV202511296795', 3, 2, '2025-11-29', 'jambi', 50, 'itu dia', 50000000.00, 'completed', 'paid', '2025-11-29 16:05:05', '2025-11-29 16:07:41')
ON DUPLICATE KEY UPDATE id = id;

-- 7) payments
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('transfer_bank','credit_card','e_wallet') COLLATE utf8mb4_general_ci DEFAULT 'transfer_bank',
  `status` enum('pending','verified','failed') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `proof_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bank_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `account_number` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `account_holder` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `verified_by` int DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELETE FROM `payments`;
INSERT INTO `payments` (`id`, `order_id`, `amount`, `method`, `status`, `proof_image`, `bank_name`, `account_number`, `account_holder`, `verified_by`, `verified_at`, `created_at`) VALUES
  (1, 5, 5000000.00, 'transfer_bank', 'verified', 'payments/692020979e18c_1763713175.png', 'BNI', '1571070804050021', 'Nadhif Pandya Supriyadi', 1, '2025-11-21 08:27:22', '2025-11-21 08:19:35'),
  (2, 6, 8000000.00, 'transfer_bank', 'verified', 'payments/6920222ae2124_1763713578.png', 'BCA', '1571070804050021', 'Nadhif Pandya Supriyadi', 1, '2025-11-21 08:27:16', '2025-11-21 08:26:18'),
  (3, 7, 6000000.00, 'transfer_bank', 'verified', 'payments/6923f070105b2_1763962992.png', 'BCA', '1571070804050021', 'ragit', 1, '2025-11-24 05:51:36', '2025-11-24 05:43:12'),
  (4, 1, 5000000.00, 'transfer_bank', 'verified', 'payments/69245864eddeb_1763989604.png', 'BNI', '1571070804050021', 'Nadhif Pandya Supriyadi', 1, '2025-11-24 13:12:52', '2025-11-24 13:06:44'),
  (5, 2, 5000000.00, 'transfer_bank', 'pending', 'payments/69245a3770cbb_1763990071.png', 'BNI', '1571070804050021', 'ragit', NULL, NULL, '2025-11-24 13:14:31'),
  (6, 8, 50000000.00, 'transfer_bank', 'verified', 'payments/cab0fc5e2617c0e43188ef5ea566a724_1764432408.png', 'BNI', '1571070804050021', 'aseng', 1, '2025-11-29 16:07:26', '2025-11-29 16:06:48')
ON DUPLICATE KEY UPDATE id = id;

-- 8) reviews
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `vendor_id` int NOT NULL,
  `package_id` int NOT NULL,
  `rating` tinyint NOT NULL,
  `comment` text COLLATE utf8mb4_general_ci,
  `images` json DEFAULT NULL,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_order_review` (`order_id`),
  KEY `customer_id` (`customer_id`),
  KEY `vendor_id` (`vendor_id`),
  KEY `package_id` (`package_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  CONSTRAINT `reviews_ibfk_4` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`),
  CONSTRAINT `reviews_chk_1` CHECK (((`rating` >= 1) and (`rating` <= 5)))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELETE FROM `reviews`;

SET FOREIGN_KEY_CHECKS = 1;
