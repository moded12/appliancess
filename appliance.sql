-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 01, 2026 at 02:31 PM
-- Server version: 10.5.29-MariaDB
-- PHP Version: 8.4.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `appliance`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `info` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `created_at`) VALUES
(1, 'Refrigerators - ثلاجات', 'refrigerators', '2025-10-30 05:18:39'),
(2, 'Washing Machines - غسالات', 'washing-machines', '2025-10-30 05:18:39'),
(3, 'Microwaves - مايكرويف', 'microwaves', '2025-10-30 05:18:39'),
(4, 'Air Conditioners - مكيفات', 'air-conditioners', '2025-10-30 05:18:39'),
(5, 'Small Appliances - أجهزة صغيرة .', 'small-appliances', '2025-10-30 05:18:39'),
(6, '555', '555', '2025-11-10 05:55:27'),
(7, 'gas heater', 'gas-heater', '2025-11-10 15:15:35'),
(8, 'شاشات', '-', '2025-11-10 15:19:44');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `discount_type` enum('percent','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `address`, `created_at`) VALUES
(1, 'محمد', 'admin@local', '0799186062', 'عمان', '2025-10-30 05:23:41'),
(2, 'ااا', NULL, '07000000000', 'ددد', '2025-11-11 12:18:29'),
(3, '11111111', NULL, '111111111', '111111111111111', '2025-11-11 12:20:17'),
(4, 'Mohammad Al Ajouri', NULL, '55555555', 'االاا', '2025-11-11 21:17:40'),
(5, 'محمد احمد', NULL, '098552225', 'شنلر', '2025-11-12 17:00:14');

-- --------------------------------------------------------

--
-- Table structure for table `homepage_slider`
--

CREATE TABLE `homepage_slider` (
  `id` int(11) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cod','stripe') NOT NULL DEFAULT 'cod',
  `payment_status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `status` enum('new','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `coupon_id` int(11) DEFAULT NULL,
  `gateway` varchar(32) DEFAULT NULL,
  `transaction_id` varchar(64) DEFAULT NULL,
  `payment_meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_meta`)),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `currency` varchar(10) NOT NULL DEFAULT 'USD'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `total`, `payment_method`, `payment_status`, `status`, `created_at`, `coupon_id`, `gateway`, `transaction_id`, `payment_meta`, `updated_at`, `currency`) VALUES
(1, 1, 570.00, 'cod', 'pending', 'new', '2025-10-30 05:23:41', NULL, NULL, NULL, NULL, '2025-11-10 15:17:45', 'USD'),
(7, 2, 1.00, 'cod', 'pending', 'new', '2025-12-05 08:38:25', NULL, 'cod', NULL, '[]', '2025-12-05 08:38:25', 'USD'),
(8, 2, 1.00, '', '', '', '2025-12-05 09:27:30', NULL, 'card_stub', NULL, '{\"card_last4\":\"5784\",\"card_holder\":\"mohammad\",\"card_exp\":\"02/27\"}', '2025-12-05 09:27:30', 'USD'),
(9, 2, 1.00, '', '', '', '2025-12-05 09:31:20', NULL, 'card_stub', NULL, '{\"card_last4\":\"5784\",\"card_holder\":\"mohammad\",\"card_exp\":\"02/27\"}', '2025-12-05 09:31:20', 'USD'),
(10, 2, 0.43, '', '', '', '2025-12-05 10:27:53', NULL, 'card_stub', NULL, '{\"card_last4\":\"5784\",\"card_holder\":\"mohammad\",\"card_exp\":\"02/27\"}', '2025-12-05 10:27:53', 'USD'),
(11, 2, 0.21, '', '', '', '2025-12-05 10:32:11', NULL, 'card_stub', NULL, '{\"card_last4\":\"5784\",\"card_holder\":\"mohammad\",\"card_exp\":\"02/27\"}', '2025-12-05 10:32:11', 'USD'),
(12, 2, 0.21, '', '', '', '2025-12-05 10:40:06', NULL, 'card_stub', NULL, '{\"card_last4\":\"5784\",\"card_holder\":\"mohammad\",\"card_exp\":\"02/27\"}', '2025-12-05 10:40:06', 'USD'),
(13, 2, 0.21, '', '', '', '2025-12-05 10:44:18', NULL, 'card_stub', NULL, '{\"card_last4\":\"5784\",\"card_holder\":\"mohammad\",\"card_exp\":\"02/27\"}', '2025-12-05 10:44:18', 'USD'),
(14, 2, 0.21, '', '', '', '2025-12-05 10:46:12', NULL, 'card_stub', NULL, '{\"card_last4\":\"5784\",\"card_holder\":\"mohammad\",\"card_exp\":\"02/27\"}', '2025-12-05 10:46:12', 'USD'),
(15, 2, 0.21, '', '', '', '2025-12-05 10:49:14', NULL, 'card_stub', NULL, '{\"card_last4\":\"5784\",\"card_holder\":\"mohammad\",\"card_exp\":\"02/27\"}', '2025-12-05 10:49:14', 'USD');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `name`, `price`, `qty`) VALUES
(1, 1, NULL, 'Moulinex Food Processor 1000W - محضر طعام', 570.00, 1),
(8, 7, 31, '', 1.00, 1),
(9, 8, 31, '', 1.00, 1),
(10, 9, 31, '', 1.00, 1),
(11, 10, 27, '', 0.43, 1),
(12, 11, 24, '', 0.21, 1),
(13, 12, 24, '', 0.21, 1),
(14, 13, 24, '', 0.21, 1),
(15, 14, 24, '', 0.21, 1),
(16, 15, 24, '', 0.21, 1);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` enum('new','processing','shipped','completed','cancelled') NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `main_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `views` int(11) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `description`, `price`, `stock`, `main_image`, `created_at`, `updated_at`, `views`, `is_featured`) VALUES
(23, 4, '11111', '11111', 'ؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤؤ', 0.05, 4, '/public/uploads/2eee57cb7162c75c.jpg', '2025-11-10 06:20:12', '2025-12-28 00:00:20', 40, 0),
(24, 6, 'اقوي عروض وخصومات الجمعة البيضاء', '-', NULL, 0.21, 0, '/public/uploads/11af98a0fe0e73ad.jpg', '2025-11-10 14:45:55', '2025-12-31 16:26:13', 32, 0),
(27, 8, 'شاشة 32', '-32', 'شاشة 32', 0.43, 2, '/public/uploads/52c637003794d4a5.jpg', '2025-11-10 15:20:20', '2025-12-28 12:43:43', 44, 1),
(28, 5, 'Big Friday مع العجوري غيررر🔥🔥🔥 مكواة عامودي conti 33JD ميكرويف Green Home 66JD فرن sizzler 209 JD غسالة Genral top 170 Jd', 'big-friday-conti-33jd-green-home-66jd-sizzler-209-jd-genral-top-170-jd', 'عروض ما بتتعوض💣\r\nBig Friday مع العجوري غيررر🔥🔥🔥\r\nمكواة عامودي conti 33JD\r\nميكرويف Green Home 66JD\r\nفرن sizzler 209 JD\r\nغسالة Genral top 170 Jd \r\nبأسعار مميزة ومثالية بس مع العجوري💯🔹️\r\nالعجوري.. عنوان التميّز بكل صفقة شراء🛍🪄\r\nسارع قبل ما تنتهي العروض متاحة حتى نفاذ الكمية⏳️\r\nتابعونا بأستمرار لمعرفة المزيد من العروض💫\r\n▪︎بنستقبلكم يوميا\r\n▪︎البقعة من11 ص إلى 9 مساء\r\n▪︎الرصيفة من 10 ص إلى 12 مساء\r\nنسعد ونرحب بكم في معارضنا🙏🏻\r\nبالإضافة لخدمة التوصيل لجميع أنحاء الأردن🚀\r\nمتواجدين في:\r\n📍 الفرع الاول : \r\n  الرصيفة - قرب جسر ماركا \r\n📍الفرع الثاني: \r\n  البقعة - دوار النصيرات \r\n📱تواصل معنا:\r\n🔹️فرع الرصيفة:\r\n▪︎ 053610044\r\n▪︎ 0795510570\r\n▪︎ 0795239293\r\n🔹️فرع البقعة:\r\n▪︎ 0787461632\r\n▪︎ ‭0799633150‬\r\n▪︎ 0781079107\r\n•\r\n•\r\n•\r\n#العجوري\r\n#عروض\r\n#black_friday\r\n#offers\r\n#viral', 0.00, 20, '/public/uploads/a8ac672263286156.jpg', '2025-11-10 15:28:48', '2026-01-01 12:14:40', 27, 0),
(31, 2, '🎁 \" بين العيدين.. هديتين\" بانتظارك!', 'byn-al-ydyn-hdytyn-bantzark', 'بدك طلب معين بس بعيد عليك المكان؟\r\nبنوصلك وين ما كنت بسرعة الصاروخ🚀 \r\n📌توصيل لجميع أنحاء الأردن\r\n▪︎بنستقبلكم يوميا\r\n▪︎البقعة من11 ص إلى 9 مساء\r\n▪︎الرصيفة من 10 ص إلى 12 مساء\r\nنسعد ونرحب بكم في معارضنا🙏🏻\r\nمتواجدين في:\r\n📍 الفرع الاول : \r\n  الرصيفة - قرب جسر ماركا \r\n📍الفرع الثاني: \r\n  البقعة - دوار النصيرات \r\n📱تواصل معنا:\r\n🔹️فرع الرصيفة:\r\n▪︎ 053610044\r\n▪︎ 0795510570\r\n▪︎ 0795239293\r\n🔹️فرع البقعة:\r\n▪︎ 0787461632\r\n▪︎ ‭0799633150‬\r\n▪︎ 0781079107\r\nhttps://wtsi.me/962781079107\r\n(📍 فرع الرصيفة📍 )\r\n https://goo.gl/maps/mUr9BiU8oFXTG7fm9 \r\n0795510570 - 05/3610044 \r\nمعرض العجوري للاجهزة الكهربائية\r\n(📍 فرع البقعة📍 )  \r\nhttps://maps.app.goo.gl/tinyWmE7RSsVE9JB7 \r\n☎️0787461632-0781079107\r\n👈 خدمة التوصيل متوفرة\r\n⏳ لفترة محدودة فقط.. لا تفوّت الفرصة!\r\n#عروض #خصومات #فاتورتك_بتربحك_سيارة #فاتورتك_بتربحك_هدايا #دولاب_الجوائز_الفورية #معرض_العجوري  #ajouri_electronics #discounts  #google_tv #TCL #بناسونك #ثلاجة #جلاية  #صيف #بيكو #غسالة #jordan #Refrigerator #washingmachine #Panasonic', 1.00, 8, '/public/uploads/be21abdacac6f64e.jpg', '2025-11-11 06:15:52', '2026-01-01 11:23:43', 62, 0),
(32, 5, 'غسالة', 'ghsalt', 'عسالةفل مكفولة', 190.00, 19, '', '2025-11-12 16:58:50', '2026-01-01 10:43:37', 9, 0);

-- --------------------------------------------------------

--
-- Table structure for table `product_media`
--

CREATE TABLE `product_media` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `media_type` enum('image','video') NOT NULL DEFAULT 'image',
  `file` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_media`
--

INSERT INTO `product_media` (`id`, `product_id`, `media_type`, `file`, `sort_order`, `created_at`) VALUES
(18, 31, 'image', '/public/uploads/1465e04e5b0bff94.jpg', 0, '2025-11-11 06:15:52'),
(19, 31, 'image', '/public/uploads/38b9b2f520147ea9.jpg', 0, '2025-11-11 06:15:52'),
(20, 32, 'image', '/public/uploads/bf72fd57ee561957.jpg', 0, '2026-01-01 10:43:24'),
(21, 31, 'image', '/public/uploads/26f365ed63f300c8.jpg', 0, '2026-01-01 10:50:09'),
(22, 31, 'image', '/public/uploads/bdc1ab8a02e62b53.jpg', 0, '2026-01-01 10:50:56');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','editor') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'admin@local', '$2y$10$aSPDHlk4w7OCI7RJeFmc2OSb5SbaLdYyurIeLgULmy9B6TmerzZdm', 'admin', '2025-10-30 05:18:39');

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `homepage_slider`
--
ALTER TABLE `homepage_slider`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_homepage_slider_active` (`is_active`,`sort_order`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `coupon_id` (`coupon_id`),
  ADD KEY `idx_orders_payment_method` (`payment_method`),
  ADD KEY `idx_orders_payment_status` (`payment_status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `uniq_products_slug` (`slug`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_products_views` (`views`),
  ADD KEY `idx_products_featured` (`is_featured`);

--
-- Indexes for table `product_media`
--
ALTER TABLE `product_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `homepage_slider`
--
ALTER TABLE `homepage_slider`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `product_media`
--
ALTER TABLE `product_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_media`
--
ALTER TABLE `product_media`
  ADD CONSTRAINT `product_media_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
