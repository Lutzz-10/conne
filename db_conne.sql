-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 02, 2026 at 01:57 PM
-- Server version: 8.4.3
-- PHP Version: 8.5.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_conne`
--

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int NOT NULL,
  `post_id` int NOT NULL,
  `user_id` int NOT NULL,
  `content` varchar(250) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `file` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `post_id`, `user_id`, `content`, `image`, `file`, `file_name`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'Halo juga! Semangat belajar #CONNE', NULL, NULL, NULL, '2026-11-01 14:30:54', '2026-11-01 14:30:54'),
(2, 1, 1, 'Terima kasih! #Semangat', NULL, NULL, NULL, '2026-11-01 14:30:54', '2026-11-01 14:30:54'),
(3, 2, 1, 'Tetap semangat belajar PHP #RPL', 'uploads/comments/57c551316208ff18189cf924aabb0637.jpg', NULL, NULL, '2026-11-01 14:30:54', '2026-11-02 00:09:35');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `content` varchar(250) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `file` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `user_id`, `content`, `image`, `file`, `file_name`, `created_at`, `updated_at`) VALUES
(1, 1, 'Halo semuanya! Selamat datang di #CONNE', NULL, NULL, NULL, '2026-11-01 14:30:31', '2026-11-01 14:30:31'),
(2, 2, 'Saya sedang belajar PHP Native dan MySQL #RPL', NULL, NULL, NULL, '2026-11-01 14:30:31', '2026-11-01 14:30:31'),
(3, 1, 'Orang orang tampan #Ganteng', 'uploads/posts/d3c08989effc4d7232462308257c8d8e.jpg', NULL, NULL, '2026-11-01 14:30:31', '2026-11-01 15:44:05'),
(4, 1, 'nihaooo', NULL, NULL, NULL, '2026-11-01 15:54:06', '2026-11-01 15:54:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `bio` text,
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `bio`, `profile_photo`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@conne.com', '$2y$12$hLTq.BTamF1N0hrPk3UDtO1td.CG/s4Vg7GEL90wh59ikqLXI7Uh.', 'Halo! Saya pengguna CONNE.', 'uploads/profiles/70f7f355c6278123c664df5c6d2071a5.jpg', '2026-11-01 14:30:17', '2026-11-01 16:38:40'),
(2, 'budi', 'budi@conne.com', '$2y$12$hLTq.BTamF1N0hrPk3UDtO1td.CG/s4Vg7GEL90wh59ikqLXI7Uh.', 'Belajar PHP Native.', NULL, '2026-11-01 14:30:17', '2026-11-01 14:57:21'),
(4, 'lutfi', 'lutfimaulida001@gmail.com', '$2y$12$/hMlzcAPrDPx/HTDKA88i.1ttZznUuZRPWOHM.ncU4NUIrC330l0u', NULL, NULL, '2026-11-02 13:55:02', '2026-11-02 13:55:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_comments_created_at` (`created_at`),
  ADD KEY `idx_comments_post_id` (`post_id`),
  ADD KEY `idx_comments_user_id` (`user_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_posts_created_at` (`created_at`),
  ADD KEY `idx_posts_user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
