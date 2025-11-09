-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 09, 2025 at 12:53 PM
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
(5, 'Small Appliances - أجهزة صغيرة', 'small-appliances', '2025-10-30 05:18:39');

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
(1, 'محمد', 'admin@local', '0799186062', 'ثثثثثثثثثثث', '2025-10-30 05:23:41');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `total`, `payment_method`, `payment_status`, `status`, `created_at`) VALUES
(1, 1, 570.00, 'cod', 'pending', 'new', '2025-10-30 05:23:41');

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
(1, 1, NULL, 'Moulinex Food Processor 1000W - محضر طعام', 570.00, 1);

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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `description`, `price`, `stock`, `main_image`, `created_at`, `updated_at`) VALUES
(12, 5, 'الكل ربحانين مع هايسنس والتنين', '----', 'الكل ربحانين مع هايسنس والتنين...\r\nالعب واربح خصومات وجوائز كتيرة فورا وعالاكيد عند شرائك مكيف هايسنس من معرض العجوري\r\n(📍 فرع الرصيفة📍 )\r\n#رابط_الواتساب :\r\n‏https://wsend.co/96253610044\r\n📍ا https://goo.gl/maps/mUr9BiU8oFXTG7fm9 \r\nا☎️0795510570 - 05/3610044 ☎️\r\n(📍 فرع البقعة📍 ) \r\n#رابط_الواتساب :\r\n‏https://wsend.co/962781079107\r\nا https://maps.app.goo.gl/tinyWmE7RSsVE9JB7 \r\nا☎️0787461632-0781079107☎️\r\n👈 خدمة التوصيل متوفرة\r\n#amman #مكيفات # #conditioner  #Hisense #مكيفات #صيف2025  #تخفيضات #الاردن #conti #تركيب_فوري #تركيب_مجاني #مكيفات #تبريد #صيف_بارد #خصومات #مكيف #Dishwasher #HisenseAC #hisense_air #hisenseairconditioner #HisenseLaserTVGlobalNo1', 0.00, 0, 'fc11006f3f66d7c7.jpg', '2025-10-30 05:35:22', '2025-10-30 05:35:22'),
(13, 4, 'عروض الصيف الأقوى دائماً من #معرض_العجوري', '------', 'عروض الصيف الأقوى دائماً من #معرض_العجوري\r\nلا تشيل هم درجات 🔥 الحرارة مع #عروض الصيف\r\nتشكيلة #مميزة من المكيفات بافضل الاسعار في #المملكة \r\nبالاضافة الى خصومات الاجهزه الكهربائية\r\nلحــــــق عـــــروض صــــيف 2024 وخــلي صــيفك أبـــرد مع معرض العجوري للأجهزة الكهربائية ajouri electronics\r\n#عــــــروض_خـــــاصة #لفترة_محدودة \r\n#تركيب_فوري  \r\n-------------------------------------\r\n#رابط_الواتساب :\r\nhttps://wsend.co/96253610044\r\nموقعنا على الخرائط :\r\nhttps://goo.gl/maps/bdff8NGKzR8vz9LP8 \r\n👈 خدمة التوصيل متوفرة\r\nلمزيد من المعلومات اتصل على الارقام التالية :    \r\n 05/3610044 - 0795510570 - 0787461632 \r\n#amman  #مكيفات #TCL #conditioner #AUX     #Hisense #مكيفات #صيف2024 #sharp  #تخفيضات #جري #الاردن #conti \r\n #خصومات #condor  #beko  #tcl  ‎#تركيب_فوري #mec #natonal', 0.00, 0, '2a74dd0daf5166f1.jpg', '2025-10-30 05:36:26', '2025-10-30 05:36:26');

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
(13, 12, 'image', 'fc11006f3f66d7c7.jpg', 0, '2025-10-30 05:35:22'),
(14, 12, 'image', '6fa9602192d43d37.jpg', 1, '2025-10-30 05:35:22'),
(15, 12, 'image', '510ab212ef2f172d.jpg', 2, '2025-10-30 05:35:22'),
(16, 12, 'image', '0f17e3e350f65061.jpg', 3, '2025-10-30 05:35:22'),
(17, 13, 'image', '2a74dd0daf5166f1.jpg', 0, '2025-10-30 05:36:26');

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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_media`
--
ALTER TABLE `product_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `product_media`
--
ALTER TABLE `product_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
