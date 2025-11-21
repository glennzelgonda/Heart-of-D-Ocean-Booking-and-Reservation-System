-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 21, 2025 at 07:27 AM
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
-- Database: `beach_resort`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `room` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `guests` int(11) NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `booking_id`, `name`, `email`, `phone`, `room`, `date`, `guests`, `status`, `timestamp`) VALUES
(1, 'RESORT1763658268585', 'Marjhon', 'marjhonmatalog278@gmail.com', '098145289729', 'Premium 838 — ₱7,800', '2025-11-26', 3, 'confirmed', '2025-11-20 17:04:28'),
(2, 'RESORT1763658941422', 'Marjhon', 'marjhonmatalog278@gmail.com', '098145289729', 'Heartsuite — ₱11,800', '2025-11-11', 2, 'confirmed', '2025-11-20 17:15:41'),
(3, 'RESORT1763659910979', 'Marjhon', 'marjhonmatalog278@gmail.com', '098145289729', 'White House — ₱30,000', '2025-11-10', 2, 'cancelled', '2025-11-20 17:31:50'),
(4, 'RESORT1763660941663', 'Marjhon', 'marjhonmatalog278@gmail.com', '098145289729', 'White House — ₱30,000', '2025-11-02', 2, 'pending', '2025-11-20 17:49:01'),
(5, 'RESORT1763663948413', 'Marjhon', 'marjhonmatalog278@gmail.com', '098145289729', 'Beatrice B — ₱6,800', '2025-12-23', 4, 'pending', '2025-11-20 18:39:08'),
(6, 'RESORT1763664052972', 'Marjhon', 'marjhonmatalog278@gmail.com', '098145289729', 'Giant Kubo — ₱6,800', '2025-12-23', 2, 'pending', '2025-11-20 18:40:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
