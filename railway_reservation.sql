-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 20, 2025 at 11:05 AM
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
-- Database: `railway_reservation`
--

DELIMITER $$
--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_fare` (`p_train_id` INT, `p_from_station` VARCHAR(100), `p_to_station` VARCHAR(100)) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
    DECLARE v_fare DECIMAL(10,2);
    
    -- Try to get direct fare
    SELECT base_fare INTO v_fare
    FROM station_pricing
    WHERE train_id = p_train_id 
    AND from_station = p_from_station 
    AND to_station = p_to_station;
    
    -- If no direct fare, try reverse direction
    IF v_fare IS NULL THEN
        SELECT base_fare INTO v_fare
        FROM station_pricing
        WHERE train_id = p_train_id 
        AND from_station = p_to_station 
        AND to_station = p_from_station;
    END IF;
    
    -- Return fare or 0 if not found
    RETURN COALESCE(v_fare, 0);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `last_login` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `email`, `last_login`, `created_at`) VALUES
(1, 'RailYatra', '$2y$10$8K1p/bFhBDKj8ULHLSwB/.kRYKHXnXHNqnhUGCHxHRuUY0wBpVOxG', 'admin@gmail.com', '2025-03-23 05:20:13', '2025-03-22 20:10:47');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `train_id` int(11) NOT NULL,
  `from_station` varchar(100) DEFAULT NULL,
  `to_station` varchar(100) DEFAULT NULL,
  `distance` decimal(10,2) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `journey_date` date NOT NULL,
  `pnr_number` varchar(20) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `train_id`, `from_station`, `to_station`, `distance`, `booking_date`, `journey_date`, `pnr_number`, `total_amount`, `status`, `created_at`) VALUES
(1, 2, 14, 'Solapur', 'Pakni', NULL, '2025-04-02', '2025-04-05', '2504713592', 80.00, 'Approved', '2025-04-02 17:26:54'),
(3, 2, 14, 'Pakni', 'pune', NULL, '0000-00-00', '2025-04-16', '2504005122', 0.00, 'Cancelled', '2025-04-16 13:58:08'),
(4, 2, 14, 'Pakni', 'pune', NULL, '0000-00-00', '2025-04-23', '2504866439', 0.00, 'Cancelled', '2025-04-16 13:58:50'),
(6, 2, 14, 'Solapur', 'Pakni', NULL, '0000-00-00', '2025-04-20', '2504797478', 20.00, 'Cancelled', '2025-04-20 08:05:31'),
(7, 2, 14, 'Solapur', 'Pakni', NULL, '0000-00-00', '2025-04-20', '2504148849', 20.00, 'Cancelled', '2025-04-20 08:06:34'),
(8, 2, 14, 'Solapur', 'Pakni', NULL, '0000-00-00', '2025-04-20', '2504896974', 50.00, 'Pending', '2025-04-20 08:11:27');

-- --------------------------------------------------------

--
-- Table structure for table `food_menu`
--

CREATE TABLE `food_menu` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_menu`
--

INSERT INTO `food_menu` (`id`, `vendor_id`, `item_name`, `description`, `price`, `is_available`, `created_at`) VALUES
(1, 1, 'Veg Thali', 'Rice, 3 rotis, dal, 2 sabzi, salad, papad', 120.00, 1, '2025-03-23 10:26:57'),
(2, 1, 'Misal Pav', 'Spicy misal with 2 pav', 60.00, 1, '2025-03-23 10:26:57'),
(3, 1, 'Cold Coffee', 'Refreshing cold coffee with ice cream', 50.00, 1, '2025-03-23 10:26:57'),
(4, 2, 'Shenga Chutney', 'Famous Solapuri peanut chutney', 40.00, 1, '2025-03-23 10:26:57'),
(5, 2, 'Vada Pav', 'Classic Maharashtra snack', 20.00, 1, '2025-03-23 10:26:57'),
(6, 2, 'Bhadang', 'Spicy puffed rice mix', 30.00, 1, '2025-03-23 10:26:57'),
(7, 3, 'Poha', 'Fresh and hot poha', 25.00, 1, '2025-03-23 10:26:57'),
(8, 3, 'Samosa', 'Crispy samosa with chutney', 15.00, 1, '2025-03-23 10:26:57'),
(9, 3, 'Tea', 'Hot masala tea', 12.00, 1, '2025-03-23 10:26:57'),
(10, 4, 'Mini Thali', 'Rice, 2 rotis, dal, 1 sabzi', 80.00, 1, '2025-03-23 10:26:57'),
(11, 4, 'Biryani', 'Veg/Chicken biryani with raita', 140.00, 1, '2025-03-23 10:26:57'),
(12, 4, 'Lassi', 'Sweet or salted lassi', 40.00, 1, '2025-03-23 10:26:57'),
(13, 5, 'Idli Sambar', 'Steamed rice cakes with lentil soup', 50.00, 1, '2025-04-20 08:45:32'),
(14, 5, 'Masala Dosa', 'Crispy rice crepe with potato filling', 80.00, 1, '2025-04-20 08:45:32'),
(15, 5, 'Filter Coffee', 'Traditional South Indian coffee', 25.00, 1, '2025-04-20 08:45:32'),
(16, 6, 'Bisi Bele Bath', 'Spicy rice dish with vegetables', 70.00, 1, '2025-04-20 08:45:32'),
(17, 6, 'Mysore Pak', 'Traditional sweet made with ghee', 40.00, 1, '2025-04-20 08:45:32'),
(18, 6, 'CTR Dosa', 'Famous crispy dosa from Bangalore', 90.00, 1, '2025-04-20 08:45:32'),
(19, 7, 'Appam with Stew', 'Rice pancake with vegetable stew', 90.00, 1, '2025-04-20 08:45:32'),
(20, 7, 'Puttu Kadala', 'Steamed rice cake with chickpea curry', 70.00, 1, '2025-04-20 08:45:32'),
(21, 7, 'Kerala Parotta', 'Layered flatbread with curry', 60.00, 1, '2025-04-20 08:45:32'),
(22, 8, 'Karimeen Pollichathu', 'Pearl spot fish wrapped in banana leaf', 180.00, 1, '2025-04-20 08:45:32'),
(23, 8, 'Kappa Meen Curry', 'Tapioca with fish curry', 120.00, 1, '2025-04-20 08:45:32'),
(24, 8, 'Pazham Pori', 'Banana fritters', 40.00, 1, '2025-04-20 08:45:32'),
(25, 9, 'Dal Baati Churma', 'Traditional Rajasthani meal', 150.00, 1, '2025-04-20 08:45:33'),
(26, 9, 'Pyaaz Kachori', 'Spicy onion filled snack', 40.00, 1, '2025-04-20 08:45:33'),
(27, 9, 'Ghewar', 'Sweet disc-shaped dessert', 80.00, 1, '2025-04-20 08:45:33'),
(28, 10, 'Ker Sangri', 'Traditional desert beans and berries', 110.00, 1, '2025-04-20 08:45:33'),
(29, 10, 'Makhaniya Lassi', 'Butter flavored yogurt drink', 60.00, 1, '2025-04-20 08:45:33'),
(30, 10, 'Mirchi Bada', 'Chili fritters', 35.00, 1, '2025-04-20 08:45:33'),
(31, 11, 'Kombdi Vade', 'Chicken curry with rice flour puris', 120.00, 1, '2025-04-20 08:45:33'),
(32, 11, 'Sol Kadhi', 'Kokum and coconut milk drink', 40.00, 1, '2025-04-20 08:45:33'),
(33, 11, 'Malvani Thali', 'Complete meal with coastal specialties', 180.00, 1, '2025-04-20 08:45:33'),
(34, 12, 'Goan Fish Curry', 'Spicy fish curry with rice', 160.00, 1, '2025-04-20 08:45:33'),
(35, 12, 'Prawn Balchao', 'Spicy prawn pickle', 180.00, 1, '2025-04-20 08:45:33'),
(36, 12, 'Bebinca', 'Traditional Goan layered dessert', 70.00, 1, '2025-04-20 08:45:33'),
(37, 13, 'Kosha Mangsho', 'Bengali style mutton curry', 150.00, 1, '2025-04-20 08:45:33'),
(38, 13, 'Luchi Aloor Dom', 'Fried bread with potato curry', 80.00, 1, '2025-04-20 08:45:33'),
(39, 13, 'Rosogolla', 'Sweet cottage cheese balls in syrup', 15.00, 1, '2025-04-20 08:45:33'),
(40, 14, 'Momos', 'Steamed dumplings with chutney', 70.00, 1, '2025-04-20 08:45:33'),
(41, 14, 'Thukpa', 'Noodle soup with vegetables', 90.00, 1, '2025-04-20 08:45:33'),
(42, 14, 'Darjeeling Tea', 'Premium tea from the hills', 30.00, 1, '2025-04-20 08:45:33');

-- --------------------------------------------------------

--
-- Table structure for table `food_orders`
--

CREATE TABLE `food_orders` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `menu_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `delivery_station` varchar(100) NOT NULL,
  `status` enum('Pending','Approved','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_orders`
--

INSERT INTO `food_orders` (`id`, `booking_id`, `menu_item_id`, `quantity`, `total_amount`, `delivery_station`, `status`, `created_at`) VALUES
(1, 1, 6, 2, 60.00, 'Pakni', 'Approved', '2025-04-02 17:26:54'),
(2, 8, 6, 1, 30.00, 'Pakni', 'Pending', '2025-04-20 08:11:27');

-- --------------------------------------------------------

--
-- Table structure for table `food_vendors`
--

CREATE TABLE `food_vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `station_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `contact_number` varchar(15) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_vendors`
--

INSERT INTO `food_vendors` (`id`, `name`, `station_name`, `description`, `contact_number`, `status`, `created_at`) VALUES
(1, 'Pune Station Food Court', 'Pune', 'Multi-cuisine restaurant serving fresh and hygienic food', '9876543210', 'Active', '2025-03-23 10:26:57'),
(2, 'Solapur Snacks Center', 'Solapur', 'Famous for local snacks and beverages', '9876543211', 'Active', '2025-03-23 10:26:57'),
(3, 'Daund Junction Cafe', 'Daund Jn', 'Quick bites and refreshments', '9876543212', 'Active', '2025-03-23 10:26:57'),
(4, 'Kurduvadi Food Plaza', 'Kurduvadi', 'Traditional Maharashtra cuisine', '9876543213', 'Active', '2025-03-23 10:26:57'),
(5, 'Chennai Central Canteen', 'Chennai', 'South Indian delicacies and snacks', '9876543230', 'Active', '2025-04-20 08:45:32'),
(6, 'Bangalore Junction Food Court', 'Bangalore', 'Multi-cuisine food court with local specialties', '9876543231', 'Active', '2025-04-20 08:45:32'),
(7, 'Kerala Kitchen', 'Trivandrum', 'Authentic Kerala cuisine and snacks', '9876543232', 'Active', '2025-04-20 08:45:32'),
(8, 'Kochi Food Plaza', 'Kochi', 'Seafood specialties and local favorites', '9876543233', 'Active', '2025-04-20 08:45:32'),
(9, 'Jaipur Flavors', 'Jaipur', 'Authentic Rajasthani cuisine and sweets', '9876543234', 'Active', '2025-04-20 08:45:33'),
(10, 'Jodhpur Rasoi', 'Jodhpur', 'Traditional desert cuisine and local specialties', '9876543235', 'Active', '2025-04-20 08:45:33'),
(11, 'Mumbai Tiffin Service', 'Mumbai', 'Maharashtrian and Konkan cuisine', '9876543236', 'Active', '2025-04-20 08:45:33'),
(12, 'Goa Spice Garden', 'Madgaon', 'Goan seafood and local specialties', '9876543237', 'Active', '2025-04-20 08:45:33'),
(13, 'Bengali Bhujon', 'Kolkata', 'Authentic Bengali cuisine and sweets', '9876543238', 'Active', '2025-04-20 08:45:33'),
(14, 'Himalayan Delights', 'New Jalpaiguri', 'North-Eastern and Himalayan specialties', '9876543239', 'Active', '2025-04-20 08:45:33');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `icon` varchar(50) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `passengers`
--

CREATE TABLE `passengers` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `id_proof` varchar(50) DEFAULT NULL,
  `seat_number` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `passengers`
--

INSERT INTO `passengers` (`id`, `booking_id`, `name`, `age`, `gender`, `id_proof`, `seat_number`, `created_at`) VALUES
(1, 1, 'Rushikesh Rakhe', 22, 'Male', NULL, NULL, '2025-04-02 17:26:54'),
(3, 3, 'Rushikesh Rakhe', 22, 'Male', '1651651651651', NULL, '2025-04-16 13:58:08'),
(4, 4, 'Rushikesh Rakhe', 23, 'Male', '902272117245', NULL, '2025-04-16 13:58:50'),
(6, 6, 'Rushikesh Rakhe', 22, 'Male', NULL, NULL, '2025-04-20 08:05:31'),
(7, 7, 'Rushikesh Rakhe', 22, 'Male', NULL, NULL, '2025-04-20 08:06:34'),
(8, 8, 'Rushikesh Rakhe', 22, 'Male', NULL, NULL, '2025-04-20 08:11:27');

-- --------------------------------------------------------

--
-- Table structure for table `routes`
--

CREATE TABLE `routes` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `source_station` varchar(100) NOT NULL,
  `destination_station` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `routes`
--

INSERT INTO `routes` (`id`, `name`, `source_station`, `destination_station`, `created_at`) VALUES
(1, 'Solapur-Pune Route', 'Solapur', 'Pune', '2025-03-23 10:13:42'),
(2, 'Pune-Solapur Route', 'Pune', 'Solapur', '2025-03-23 10:13:42'),
(14, 'Solapur Mumbai', 'Solapur', 'Mumbai', '2025-04-02 04:22:20'),
(15, 'Chennai-Bangalore Route', 'Chennai', 'Bangalore', '2025-04-20 08:45:32'),
(16, 'Trivandrum-Kochi Route', 'Trivandrum', 'Kochi', '2025-04-20 08:45:32'),
(17, 'Jaipur-Jodhpur Route', 'Jaipur', 'Jodhpur', '2025-04-20 08:45:32'),
(18, 'Mumbai-Goa Route', 'Mumbai', 'Madgaon', '2025-04-20 08:45:33'),
(19, 'Kolkata-NJP Route', 'Kolkata', 'New Jalpaiguri', '2025-04-20 08:45:33');

-- --------------------------------------------------------

--
-- Table structure for table `seats`
--

CREATE TABLE `seats` (
  `id` int(11) NOT NULL,
  `train_id` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('booking_window', '60'),
('cancellation_fee', '20');

-- --------------------------------------------------------

--
-- Table structure for table `station_pricing`
--

CREATE TABLE `station_pricing` (
  `id` int(11) NOT NULL,
  `train_id` int(11) NOT NULL,
  `from_station` varchar(100) NOT NULL,
  `to_station` varchar(100) NOT NULL,
  `distance` decimal(10,2) NOT NULL,
  `base_fare` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `station_pricing`
--

INSERT INTO `station_pricing` (`id`, `train_id`, `from_station`, `to_station`, `distance`, `base_fare`, `created_at`) VALUES
(16, 14, 'Solapur', 'Pakni', 20.00, 20.00, '2025-04-02 04:22:20'),
(17, 14, 'Pakni', 'pune', 20.00, 20.00, '2025-04-02 04:22:20'),
(18, 14, 'pune', 'Mumbai', 250.00, 120.00, '2025-04-02 04:22:20'),
(20, 15, 'Chennai', 'Arakkonam', 70.00, 100.00, '2025-04-20 08:45:32'),
(21, 15, 'Arakkonam', 'Katpadi', 80.00, 120.00, '2025-04-20 08:45:32'),
(22, 15, 'Katpadi', 'Jolarpettai', 60.00, 90.00, '2025-04-20 08:45:32'),
(23, 15, 'Jolarpettai', 'Bangalore', 140.00, 200.00, '2025-04-20 08:45:32'),
(24, 15, 'Chennai', 'Bangalore', 350.00, 450.00, '2025-04-20 08:45:32'),
(25, 16, 'Trivandrum', 'Kollam', 65.00, 80.00, '2025-04-20 08:45:32'),
(26, 16, 'Kollam', 'Alappuzha', 120.00, 150.00, '2025-04-20 08:45:32'),
(27, 16, 'Alappuzha', 'Ernakulam', 50.00, 70.00, '2025-04-20 08:45:32'),
(28, 16, 'Ernakulam', 'Kochi', 15.00, 30.00, '2025-04-20 08:45:32'),
(29, 16, 'Trivandrum', 'Kochi', 250.00, 300.00, '2025-04-20 08:45:32'),
(30, 17, 'Jaipur', 'Ajmer', 135.00, 180.00, '2025-04-20 08:45:33'),
(31, 17, 'Ajmer', 'Beawar', 55.00, 80.00, '2025-04-20 08:45:33'),
(32, 17, 'Beawar', 'Pali Marwar', 100.00, 120.00, '2025-04-20 08:45:33'),
(33, 17, 'Pali Marwar', 'Jodhpur', 70.00, 90.00, '2025-04-20 08:45:33'),
(34, 17, 'Jaipur', 'Jodhpur', 360.00, 400.00, '2025-04-20 08:45:33'),
(35, 18, 'Mumbai', 'Panvel', 40.00, 60.00, '2025-04-20 08:45:33'),
(36, 18, 'Panvel', 'Ratnagiri', 210.00, 250.00, '2025-04-20 08:45:33'),
(37, 18, 'Ratnagiri', 'Kankavali', 120.00, 150.00, '2025-04-20 08:45:33'),
(38, 18, 'Kankavali', 'Madgaon', 130.00, 180.00, '2025-04-20 08:45:33'),
(39, 18, 'Mumbai', 'Madgaon', 500.00, 550.00, '2025-04-20 08:45:33'),
(40, 19, 'Kolkata', 'Barddhaman', 100.00, 120.00, '2025-04-20 08:45:33'),
(41, 19, 'Barddhaman', 'Malda Town', 220.00, 260.00, '2025-04-20 08:45:33'),
(42, 19, 'Malda Town', 'Kishanganj', 120.00, 150.00, '2025-04-20 08:45:33'),
(43, 19, 'Kishanganj', 'New Jalpaiguri', 80.00, 100.00, '2025-04-20 08:45:33'),
(44, 19, 'Kolkata', 'New Jalpaiguri', 520.00, 600.00, '2025-04-20 08:45:33');

-- --------------------------------------------------------

--
-- Table structure for table `trains`
--

CREATE TABLE `trains` (
  `id` int(11) NOT NULL,
  `train_number` varchar(20) NOT NULL,
  `train_name` varchar(100) NOT NULL,
  `source_station` varchar(100) NOT NULL,
  `destination_station` varchar(100) NOT NULL,
  `departure_time` time NOT NULL,
  `arrival_time` time NOT NULL,
  `total_seats` int(11) NOT NULL,
  `fare` decimal(10,2) NOT NULL,
  `status` enum('Active','Delayed','Cancelled') DEFAULT 'Active',
  `route_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `booked_seats` int(11) NOT NULL DEFAULT 1,
  `delay_duration` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trains`
--

INSERT INTO `trains` (`id`, `train_number`, `train_name`, `source_station`, `destination_station`, `departure_time`, `arrival_time`, `total_seats`, `fare`, `status`, `route_id`, `created_at`, `booked_seats`, `delay_duration`) VALUES
(14, '11018', '	Hutatma Express', 'Solapur', 'Mumbai', '09:48:00', '15:48:00', 50, 0.00, 'Active', 14, '2025-04-02 04:22:20', 2, NULL),
(15, '12345', 'Chennai Express', 'Chennai', 'Bangalore', '08:00:00', '14:30:00', 100, 0.00, 'Active', 15, '2025-04-20 08:45:32', 0, NULL),
(16, '16302', 'Kerala Express', 'Trivandrum', 'Kochi', '06:45:00', '13:00:00', 90, 0.00, 'Active', 16, '2025-04-20 08:45:32', 0, NULL),
(17, '14707', 'Rajasthan Royals', 'Jaipur', 'Jodhpur', '10:15:00', '16:30:00', 120, 0.00, 'Active', 17, '2025-04-20 08:45:32', 0, NULL),
(18, '10111', 'Konkan Kanya', 'Mumbai', 'Madgaon', '23:00:00', '10:30:00', 110, 0.00, 'Active', 18, '2025-04-20 08:45:33', 0, NULL),
(19, '13141', 'Darjeeling Mail', 'Kolkata', 'New Jalpaiguri', '22:00:00', '08:00:00', 130, 0.00, 'Active', 19, '2025-04-20 08:45:33', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `train_stations`
--

CREATE TABLE `train_stations` (
  `id` int(11) NOT NULL,
  `train_id` int(11) NOT NULL,
  `station_name` varchar(100) NOT NULL,
  `arrival_time` time DEFAULT NULL,
  `departure_time` time DEFAULT NULL,
  `stop_number` int(11) NOT NULL,
  `platform_number` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `train_stations`
--

INSERT INTO `train_stations` (`id`, `train_id`, `station_name`, `arrival_time`, `departure_time`, `stop_number`, `platform_number`, `created_at`) VALUES
(24, 14, 'Solapur', NULL, '09:48:00', 1, '3', '2025-04-02 04:22:20'),
(25, 14, 'Pakni', '10:00:00', '10:10:00', 2, '3', '2025-04-02 04:22:20'),
(26, 14, 'pune', '12:50:00', '13:00:00', 3, '2', '2025-04-02 04:22:20'),
(27, 14, 'Mumbai', '15:48:00', '16:05:00', 4, '4', '2025-04-02 04:22:20'),
(28, 14, 'Mumbai', '15:48:00', NULL, 5, '2', '2025-04-02 04:22:20'),
(29, 15, 'Chennai', NULL, '08:00:00', 1, '3', '2025-04-20 08:45:32'),
(30, 15, 'Arakkonam', '09:15:00', '09:20:00', 2, '1', '2025-04-20 08:45:32'),
(31, 15, 'Katpadi', '10:45:00', '10:50:00', 3, '2', '2025-04-20 08:45:32'),
(32, 15, 'Jolarpettai', '12:00:00', '12:05:00', 4, '1', '2025-04-20 08:45:32'),
(33, 15, 'Bangalore', '14:30:00', NULL, 5, '4', '2025-04-20 08:45:32'),
(34, 16, 'Trivandrum', NULL, '06:45:00', 1, '1', '2025-04-20 08:45:32'),
(35, 16, 'Kollam', '08:00:00', '08:05:00', 2, '2', '2025-04-20 08:45:32'),
(36, 16, 'Alappuzha', '10:30:00', '10:35:00', 3, '1', '2025-04-20 08:45:32'),
(37, 16, 'Ernakulam', '12:10:00', '12:15:00', 4, '3', '2025-04-20 08:45:32'),
(38, 16, 'Kochi', '13:00:00', NULL, 5, '2', '2025-04-20 08:45:32'),
(39, 17, 'Jaipur', NULL, '10:15:00', 1, '3', '2025-04-20 08:45:32'),
(40, 17, 'Ajmer', '12:00:00', '12:10:00', 2, '1', '2025-04-20 08:45:32'),
(41, 17, 'Beawar', '13:15:00', '13:20:00', 3, '2', '2025-04-20 08:45:32'),
(42, 17, 'Pali Marwar', '15:00:00', '15:05:00', 4, '1', '2025-04-20 08:45:32'),
(43, 17, 'Jodhpur', '16:30:00', NULL, 5, '2', '2025-04-20 08:45:32'),
(44, 18, 'Mumbai', NULL, '23:00:00', 1, '5', '2025-04-20 08:45:33'),
(45, 18, 'Panvel', '23:45:00', '23:50:00', 2, '2', '2025-04-20 08:45:33'),
(46, 18, 'Ratnagiri', '04:30:00', '04:35:00', 3, '1', '2025-04-20 08:45:33'),
(47, 18, 'Kankavali', '07:15:00', '07:20:00', 4, '1', '2025-04-20 08:45:33'),
(48, 18, 'Madgaon', '10:30:00', NULL, 5, '2', '2025-04-20 08:45:33'),
(49, 19, 'Kolkata', NULL, '22:00:00', 1, '4', '2025-04-20 08:45:33'),
(50, 19, 'Barddhaman', '23:40:00', '23:45:00', 2, '3', '2025-04-20 08:45:33'),
(51, 19, 'Malda Town', '03:30:00', '03:35:00', 3, '2', '2025-04-20 08:45:33'),
(52, 19, 'Kishanganj', '06:15:00', '06:20:00', 4, '1', '2025-04-20 08:45:33'),
(53, 19, 'New Jalpaiguri', '08:00:00', NULL, 5, '3', '2025-04-20 08:45:33');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `role`, `created_at`) VALUES
(1, 'Admin User', 'admin@example.com', '$2y$10$8Ux8l2nrRUz9M0jY1rHEyOYMlHYX8Q9q5Q9Q9Q9Q9Q9Q9Q9Q9Q', '1234567890', 'admin', '2025-03-23 10:13:42'),
(2, 'Rushikesh Rakhe', 'User@gmail.com', '$2y$10$cylkpQRHmabfXCQUxopgh.3F6T1N8X9AoK7t5.MoisQHSxY7xbbXy', '8482925270', 'user', '2025-03-23 10:56:50');

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
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pnr_number` (`pnr_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `train_id` (`train_id`);

--
-- Indexes for table `food_menu`
--
ALTER TABLE `food_menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`);

--
-- Indexes for table `food_orders`
--
ALTER TABLE `food_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `food_vendors`
--
ALTER TABLE `food_vendors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `passengers`
--
ALTER TABLE `passengers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `routes`
--
ALTER TABLE `routes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `seats`
--
ALTER TABLE `seats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `train_id` (`train_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `station_pricing`
--
ALTER TABLE `station_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_station_pair` (`train_id`,`from_station`,`to_station`);

--
-- Indexes for table `trains`
--
ALTER TABLE `trains`
  ADD PRIMARY KEY (`id`),
  ADD KEY `route_id` (`route_id`);

--
-- Indexes for table `train_stations`
--
ALTER TABLE `train_stations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `train_id` (`train_id`);

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
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `food_menu`
--
ALTER TABLE `food_menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `food_orders`
--
ALTER TABLE `food_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `food_vendors`
--
ALTER TABLE `food_vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `passengers`
--
ALTER TABLE `passengers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `routes`
--
ALTER TABLE `routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `seats`
--
ALTER TABLE `seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `station_pricing`
--
ALTER TABLE `station_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `trains`
--
ALTER TABLE `trains`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `train_stations`
--
ALTER TABLE `train_stations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`train_id`) REFERENCES `trains` (`id`);

--
-- Constraints for table `food_menu`
--
ALTER TABLE `food_menu`
  ADD CONSTRAINT `food_menu_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `food_vendors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `food_orders`
--
ALTER TABLE `food_orders`
  ADD CONSTRAINT `food_orders_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `food_orders_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `food_menu` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `passengers`
--
ALTER TABLE `passengers`
  ADD CONSTRAINT `passengers_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`);

--
-- Constraints for table `seats`
--
ALTER TABLE `seats`
  ADD CONSTRAINT `seats_ibfk_1` FOREIGN KEY (`train_id`) REFERENCES `trains` (`id`);

--
-- Constraints for table `station_pricing`
--
ALTER TABLE `station_pricing`
  ADD CONSTRAINT `station_pricing_ibfk_1` FOREIGN KEY (`train_id`) REFERENCES `trains` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trains`
--
ALTER TABLE `trains`
  ADD CONSTRAINT `trains_ibfk_1` FOREIGN KEY (`route_id`) REFERENCES `routes` (`id`);

--
-- Constraints for table `train_stations`
--
ALTER TABLE `train_stations`
  ADD CONSTRAINT `train_stations_ibfk_1` FOREIGN KEY (`train_id`) REFERENCES `trains` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
