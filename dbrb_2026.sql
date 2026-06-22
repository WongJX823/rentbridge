-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 21, 2026 at 12:02 PM
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
-- Database: `dbrb_2026`
--

-- --------------------------------------------------------

--
-- Table structure for table `agents`
--

CREATE TABLE `agents` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `preferred_name` varchar(50) NOT NULL DEFAULT '',
  `staff_id` varchar(20) NOT NULL,
  `department` varchar(80) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `allow_whatsapp` tinyint(1) NOT NULL DEFAULT 0,
  `availability` enum('available','busy','off_duty') NOT NULL DEFAULT 'available',
  `max_caseload` int(11) NOT NULL DEFAULT 5,
  `current_caseload` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agents`
--

INSERT INTO `agents` (`user_id`, `full_name`, `avatar_path`, `preferred_name`, `staff_id`, `department`, `phone`, `allow_whatsapp`, `availability`, `max_caseload`, `current_caseload`) VALUES
(15, 'Dr. Aminah Binti Yusof', 'uploads/avatars/placeholder.jpg', 'Aminah', 'AGT001', 'FTMK', '012-7778899', 1, 'available', 5, 1),
(16, 'En. Kumaran A/L Selvam', 'uploads/avatars/placeholder.jpg', 'Kumaran', 'AGT002', 'FKE', '012-8889900', 1, 'busy', 5, 3),
(17, 'Cik Nurul Aiman', 'uploads/avatars/placeholder.jpg', 'Nurul', 'AGT003', 'FKM', '012-9990011', 0, 'available', 5, 0),
(18, 'Mr. Lim Chee Keong', 'uploads/avatars/placeholder.jpg', 'Chee Keong', 'AGT004', 'FTMK', '012-0001122', 1, 'off_duty', 3, 0),
(34, 'Dr. Hairul Bin Anuar', 'uploads/avatars/placeholder.jpg', 'Hairul', 'AGT005', 'FTMK', '012-9991111', 1, 'available', 5, 2),
(35, 'Pn. Salmah Binti Hasan', 'uploads/avatars/placeholder.jpg', 'Salmah', 'AGT006', 'FKE', '012-9992222', 1, 'available', 5, 1);

-- --------------------------------------------------------

--
-- Table structure for table `agent_commissions`
--

CREATE TABLE `agent_commissions` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `base_rent` decimal(8,2) NOT NULL COMMENT 'Total monthly rent across all co-tenants',
  `commission_pct` decimal(5,2) NOT NULL DEFAULT 100.00 COMMENT '100% = 1 month rent',
  `commission_amt` decimal(8,2) NOT NULL,
  `sst_pct` decimal(5,2) NOT NULL DEFAULT 6.00,
  `sst_amt` decimal(8,2) NOT NULL,
  `total_payable` decimal(8,2) NOT NULL,
  `status` enum('pending','earned','released','paid') NOT NULL DEFAULT 'pending',
  `earned_at` timestamp NULL DEFAULT NULL COMMENT 'When contract activates',
  `released_at` timestamp NULL DEFAULT NULL COMMENT 'When admin approves payout',
  `paid_at` timestamp NULL DEFAULT NULL COMMENT 'When actual payment made (Phase N)',
  `payment_ref` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agent_verifications`
--

CREATE TABLE `agent_verifications` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitted_at` timestamp NULL DEFAULT NULL,
  `deadline_at` timestamp NULL DEFAULT NULL COMMENT '5 days from started_at',
  `property_matches_listing` tinyint(1) DEFAULT NULL,
  `property_address_correct` tinyint(1) DEFAULT NULL,
  `facilities_match` tinyint(1) DEFAULT NULL,
  `landlord_id_matches` tinyint(1) DEFAULT NULL,
  `ownership_doc_sighted` tinyint(1) DEFAULT NULL,
  `inspection_notes` text DEFAULT NULL,
  `issues_found` text DEFAULT NULL,
  `issue_severity` enum('none','minor','major') DEFAULT 'none',
  `outcome` enum('in_progress','passed','passed_with_disclosure','failed','aborted') NOT NULL DEFAULT 'in_progress',
  `student_proceeded_with_disclosure` tinyint(1) DEFAULT NULL,
  `student_decision_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agent_verification_photos`
--

CREATE TABLE `agent_verification_photos` (
  `id` int(11) NOT NULL,
  `verification_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `caption` varchar(150) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `duration_type` enum('1_semester','2_semesters','1_year','custom') NOT NULL DEFAULT 'custom',
  `monthly_rent` decimal(8,2) NOT NULL,
  `deposit` decimal(8,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending_landlord','rejected_by_landlord','pending_agent','agent_assigned','agent_verifying','agent_verified','verification_failed','inspection_aborted','contract_pending','active','completed','cancelled_by_student','cancelled_by_landlord','cancelled_by_admin') NOT NULL DEFAULT 'pending_landlord',
  `signed_contract_path` varchar(255) DEFAULT NULL,
  `signed_uploaded_at` timestamp NULL DEFAULT NULL,
  `signed_uploaded_by` int(11) DEFAULT NULL,
  `student_note` text DEFAULT NULL,
  `landlord_response` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `rejected_agents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rejected_agents`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL if guest submitted',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `status` enum('new','read','replied','archived') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replied_at` timestamp NULL DEFAULT NULL,
  `replied_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `user_id`, `ip_address`, `user_agent`, `status`, `created_at`, `replied_at`, `replied_by`) VALUES
(1, 'Ahmad', 'ahmad@landlord.com', 'Property document registration', 'What document should I update for 100% register successfully', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'new', '2026-06-15 06:29:42', NULL, NULL),
(2, 'Ahmad', 'ahmad@landlord.com', 'Property document registration', 'What document should I update for 100% register successfully', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'new', '2026-06-15 06:29:55', NULL, NULL),
(3, 'Ahmad', 'ahmad@landlord.com', 'Property document registration', 'What document should I update for 100% register successfully', 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'new', '2026-06-15 06:30:18', NULL, NULL),
(4, 'Jia Xi Wong', 'wongjiaxi@gmail.com', 'Property document registration', 'What document', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'new', '2026-06-18 03:09:24', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `contract_code` varchar(20) NOT NULL COMMENT 'e.g. RB-2026-00001',
  `booking_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `monthly_rent` decimal(8,2) NOT NULL,
  `deposit` decimal(8,2) NOT NULL,
  `terms` text NOT NULL COMMENT 'Standard tenancy terms (Markdown)',
  `student_signature` varchar(255) DEFAULT NULL,
  `student_signed_at` timestamp NULL DEFAULT NULL,
  `student_sign_ip` varchar(45) DEFAULT NULL,
  `landlord_signature` varchar(255) DEFAULT NULL,
  `landlord_signed_at` timestamp NULL DEFAULT NULL,
  `landlord_sign_ip` varchar(45) DEFAULT NULL,
  `agent_signature` varchar(255) DEFAULT NULL,
  `agent_signed_at` timestamp NULL DEFAULT NULL,
  `agent_sign_ip` varchar(45) DEFAULT NULL,
  `contract_pdf_path` varchar(255) DEFAULT NULL,
  `generated_pdf_path` varchar(255) DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `signed_pdf_path` varchar(255) DEFAULT NULL,
  `signed_uploaded_at` timestamp NULL DEFAULT NULL,
  `signed_uploaded_by` int(11) DEFAULT NULL,
  `doc_hash` varchar(64) DEFAULT NULL,
  `upload_method` enum('generated','external_upload','legacy_canvas') NOT NULL DEFAULT 'generated',
  `status` enum('pending_signatures','active','completed','terminated') NOT NULL DEFAULT 'pending_signatures',
  `activated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `user_a` int(11) NOT NULL COMMENT 'Always lower user_id',
  `user_b` int(11) DEFAULT NULL,
  `property_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `context_type` enum('property_inquiry','booking','friend','agent_case','other','contract_prep','housemate_group') NOT NULL DEFAULT 'other',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `last_message_preview` varchar(120) DEFAULT NULL,
  `last_sender_id` int(11) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `locked_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `user_a`, `user_b`, `property_id`, `booking_id`, `context_type`, `last_message_at`, `last_message_preview`, `last_sender_id`, `is_locked`, `locked_reason`, `created_at`) VALUES
(1, 3, 10, NULL, NULL, 'property_inquiry', '2026-06-09 01:30:00', 'Sure, let me arrange a viewing this Saturday.', 10, 0, NULL, '2026-06-08 10:00:00'),
(2, 4, 12, NULL, NULL, 'property_inquiry', '2026-06-09 06:30:00', 'Yes the room is still available!', 12, 0, NULL, '2026-06-09 06:00:00'),
(3, 2, 10, NULL, NULL, 'booking', '2026-06-13 08:12:57', 'What\'s included in the rent?', 2, 0, NULL, '2026-04-15 03:00:00'),
(4, 9, 12, NULL, NULL, 'booking', '2026-05-27 07:00:00', 'Contract sent for signing.', 12, 0, NULL, '2026-05-20 04:00:00'),
(5, 6, 13, NULL, NULL, 'property_inquiry', '2026-05-30 02:00:00', 'I am sorry about the mold issue, I will fix it.', 13, 0, NULL, '2026-05-28 09:00:00'),
(6, 16, 27, NULL, NULL, 'agent_case', '2026-06-16 10:09:18', 'Can I view it?', 27, 0, NULL, '2026-06-14 06:46:01'),
(7, 3, 27, 1, NULL, '', NULL, NULL, NULL, 0, NULL, '2026-06-18 03:35:10');

-- --------------------------------------------------------

--
-- Table structure for table `conversation_participants`
--

CREATE TABLE `conversation_participants` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `co_tenancy_applications`
--

CREATE TABLE `co_tenancy_applications` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `co_tenancy_posts`
--

CREATE TABLE `co_tenancy_posts` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `title` varchar(150) DEFAULT NULL COMMENT 'optional headline, defaults to property title',
  `message` text NOT NULL COMMENT 'why they want housemates, lifestyle preferences',
  `housemates_needed` int(11) NOT NULL DEFAULT 1 COMMENT 'how many more co-tenants wanted',
  `status` enum('open','filled','cancelled','expired') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `group_conversation_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `co_tenancy_posts`
--

INSERT INTO `co_tenancy_posts` (`id`, `poster_id`, `property_id`, `title`, `message`, `housemates_needed`, `status`, `created_at`, `updated_at`, `group_conversation_id`) VALUES
(2, 3, 1, NULL, '1234', 5, 'open', '2026-06-18 03:34:53', '2026-06-18 03:34:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `co_tenants`
--

CREATE TABLE `co_tenants` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL COMMENT 'RentBridge user_id if linked (leader only typically)',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = leader who applied, 0 = additional co-tenant',
  `full_name` varchar(150) NOT NULL,
  `ic_number` varchar(20) NOT NULL COMMENT 'NRIC e.g. 030303-03-0303',
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `home_address` varchar(255) DEFAULT NULL,
  `sign_order` int(11) NOT NULL DEFAULT 1,
  `signed_at` timestamp NULL DEFAULT NULL,
  `signature_data` text DEFAULT NULL COMMENT 'base64 PNG of signature (Phase 2)',
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `added_by` int(11) NOT NULL COMMENT 'user_id who added this row',
  `status` enum('pending','signed','removed') NOT NULL DEFAULT 'pending',
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `friends`
--

CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_a` int(11) NOT NULL COMMENT 'Always the lower user_id',
  `user_b` int(11) NOT NULL COMMENT 'Always the higher user_id',
  `became_friends_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `friend_requests`
--

CREATE TABLE `friend_requests` (
  `id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `message` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `landlords`
--

CREATE TABLE `landlords` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `preferred_name` varchar(50) NOT NULL DEFAULT '',
  `avatar_path` varchar(255) DEFAULT NULL,
  `ic_no` varchar(20) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `allow_whatsapp` tinyint(1) NOT NULL DEFAULT 0,
  `address` varchar(255) DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `landlords`
--

INSERT INTO `landlords` (`user_id`, `full_name`, `preferred_name`, `avatar_path`, `ic_no`, `phone`, `allow_whatsapp`, `address`, `verified`) VALUES
(10, 'Ahmad Bin Hassan', 'Ahmad', 'uploads/avatars/placeholder.jpg', '780512-04-5678', '012-1112233', 1, 'No 23, Jalan Sutera 5, Taman Sutera, 75450 Ayer Keroh, Melaka', 1),
(11, 'Wong Soo Lan', 'Wong', 'uploads/avatars/placeholder.jpg', '850923-08-1234', '012-2223344', 1, 'No 15, Jalan Indah 7, Taman Indah, 75450 Ayer Keroh, Melaka', 0),
(12, 'Priya A/P Subramaniam', 'Priya', 'uploads/avatars/placeholder.jpg', '820714-06-9012', '012-3334455', 1, 'No 88, Lorong Permai 2, Bukit Beruang, 75450 Melaka', 1),
(13, 'Chen Wei Ming', 'Chen', 'uploads/avatars/placeholder.jpg', '770308-10-3456', '012-4445566', 0, 'No 7, Jalan Cheng Heng, Taman Cheng, 75250 Melaka', 1),
(14, 'Raj Singh', 'Raj', 'uploads/avatars/placeholder.jpg', '700615-14-7890', '012-5556677', 1, 'No 42, Jalan Bunga Raya, Taman Bunga, 75100 Melaka', 0),
(29, 'Fauziah Binti Saad', 'Fauziah', 'uploads/avatars/placeholder.jpg', '760412-04-1122', '012-7771234', 1, NULL, 0),
(30, 'Tan Boon Heng', 'Tan', 'uploads/avatars/placeholder.jpg', '690819-08-3344', '012-7772345', 1, NULL, 0),
(31, 'Ismail Bin Yaakub', 'Ismail', 'uploads/avatars/placeholder.jpg', '720625-06-5566', '012-7773456', 1, NULL, 0),
(32, 'Kumar A/L Raman', 'Kumar', 'uploads/avatars/placeholder.jpg', '801107-10-7788', '012-7774567', 1, NULL, 0),
(33, 'Lim Soo Mei', 'Lim', 'uploads/avatars/placeholder.jpg', '850314-08-9900', '012-7775678', 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `message_type` varchar(40) NOT NULL DEFAULT 'text',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender_id`, `body`, `message_type`, `metadata`, `sent_at`, `read_at`) VALUES
(1, 1, 3, 'Hi! I am interested in the Cozy Single Room. Is it still available for September?', 'text', NULL, '2026-06-08 10:00:00', '2026-06-08 11:00:00'),
(2, 1, 10, 'Hello Mei Ling, yes still available. Would you like to view it?', 'text', NULL, '2026-06-09 00:30:00', '2026-06-09 01:00:00'),
(3, 1, 3, 'Yes please. When is convenient for you?', 'text', NULL, '2026-06-09 01:00:00', '2026-06-09 01:25:00'),
(4, 1, 10, 'Sure, let me arrange a viewing this Saturday.', 'text', NULL, '2026-06-09 01:30:00', NULL),
(5, 2, 4, 'Hi, is the Quiet Bedroom still available?', 'text', NULL, '2026-06-09 06:00:00', '2026-06-09 06:15:00'),
(6, 2, 12, 'Yes the room is still available!', 'text', NULL, '2026-06-09 06:30:00', NULL),
(7, 3, 2, 'Hi Ahmad, thank you for accepting my booking.', 'text', NULL, '2026-04-15 03:00:00', '2026-04-15 04:00:00'),
(8, 3, 10, 'My pleasure Jia Xi! See you on May 1st.', 'text', NULL, '2026-04-15 06:00:00', '2026-04-15 06:30:00'),
(9, 3, 2, 'Could I move in a day earlier? My old place ends April 30.', 'text', NULL, '2026-04-21 08:00:00', '2026-04-21 08:30:00'),
(10, 3, 10, 'Welcome aboard, looking forward to seeing you.', 'text', NULL, '2026-04-21 09:00:00', '2026-04-22 01:00:00'),
(11, 4, 9, 'Hi Priya, my two friends will join me for the whole unit.', 'text', NULL, '2026-05-20 04:00:00', '2026-05-20 05:00:00'),
(12, 4, 12, 'No problem. Please list their names in the booking note.', 'text', NULL, '2026-05-20 06:00:00', '2026-05-20 07:00:00'),
(13, 4, 9, 'Done! Tan Wei Zhe and Lim Mei Ling.', 'text', NULL, '2026-05-22 01:00:00', '2026-05-22 02:00:00'),
(14, 4, 12, 'Contract sent for signing.', 'text', NULL, '2026-05-27 07:00:00', '2026-05-27 08:00:00'),
(15, 5, 6, 'Hi, the agent said the property has mold and the booking was cancelled?', 'text', NULL, '2026-05-28 09:30:00', '2026-05-29 01:00:00'),
(16, 5, 13, 'I am sorry about the mold issue, I will fix it.', 'text', NULL, '2026-05-30 02:00:00', NULL),
(17, 3, 2, 'What\'s included in the rent?', 'text', NULL, '2026-06-13 08:12:57', '2026-06-13 08:18:59'),
(18, 6, 16, '📋 Co-tenant details requested\nPlease fill in the names and IC numbers of everyone who will rent this property with you.', 'co_tenant_form', '{\"booking_id\":17,\"property_title\":\"Beruang Garden View Room\"}', '2026-06-14 06:46:01', '2026-06-16 10:06:06'),
(19, 6, 27, 'Can I view it?', 'text', NULL, '2026-06-16 10:09:18', NULL),
(20, 7, 27, 'Hi! I\'m interested in joining your co-tenancy for \"Unfurnished room near UTeM\".\n\nProperty: http://localhost/rentbridge/property.php?id=1', 'text', NULL, '2026-06-18 03:35:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `move_in_inspections`
--

CREATE TABLE `move_in_inspections` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `inspected_at` timestamp NULL DEFAULT NULL,
  `inventory_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'array of {item, condition, notes}' CHECK (json_valid(`inventory_items`)),
  `overall_notes` text DEFAULT NULL,
  `student_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `student_acknowledged_at` timestamp NULL DEFAULT NULL,
  `landlord_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `landlord_acknowledged_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `move_in_photos`
--

CREATE TABLE `move_in_photos` (
  `id` int(11) NOT NULL,
  `inspection_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `caption` varchar(150) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'e.g. booking_request, contract_ready',
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link_url`, `is_read`, `created_at`) VALUES
(1, 3, 'booking_pending', 'Your booking is awaiting landlord response', 'Booking #3 for \"Cozy Single Room Near UTeM Main Gate\" is awaiting landlord response.', '/rentbridge/student/booking.php?id=3', 0, '2026-06-09 01:01:00'),
(2, 10, 'booking_request', 'New tenancy application', 'Lim Mei Ling submitted booking #3 for \"Cozy Single Room Near UTeM Main Gate\".', '/rentbridge/landlord/booking.php?id=3', 0, '2026-06-09 01:01:00'),
(3, 15, 'agent_assigned', 'New inspection case assigned', 'Booking #5 needs property inspection within 5 days.', '/rentbridge/agent/inspection.php?booking_id=5', 0, '2026-06-10 06:01:00'),
(4, 2, 'contract_active', 'Tenancy active!', 'Your contract RB-2026-00001 is now active.', '/rentbridge/student/booking.php?id=1', 1, '2026-04-22 06:01:00'),
(5, 10, 'contract_active', 'Tenancy active!', 'Contract RB-2026-00001 has been signed by all parties.', '/rentbridge/landlord/booking.php?id=1', 1, '2026-04-22 06:01:00'),
(6, 1, 'admin_alert', 'Booking needs manual review', 'Booking #4 has been pending agent assignment for more than 24 hours.', '/rentbridge/admin/booking.php?id=4', 0, '2026-06-09 07:00:00'),
(7, 6, 'booking_cancelled', 'Tenancy cancelled', 'Tenancy #6 cancelled due to failed inspection.', '/rentbridge/student/bookings.php', 1, '2026-05-31 09:00:00'),
(8, 11, 'property_pending', 'Property awaiting review', 'Your property \"Studio Apartment in Taman Indah\" is awaiting admin approval.', '/rentbridge/landlord/properties.php', 0, '2026-06-08 02:31:00'),
(9, 1, 'admin_alert', 'New property awaiting review', 'Wong Soo Lan submitted \"Studio Apartment in Taman Indah\" for approval.', '/rentbridge/admin/property.php?id=3', 0, '2026-06-08 02:31:00'),
(10, 9, 'contract_active', 'Tenancy active!', 'Your contract RB-2026-00002 is now active.', '/rentbridge/student/booking.php?id=2', 1, '2026-05-28 09:01:00'),
(11, 27, 'cotenant_form_request', 'Agent requested co-tenant details', 'Please open the chat to fill in co-tenant info for \"Beruang Garden View Room\".', '/rentbridge/chat.php?id=6', 0, '2026-06-14 06:46:01'),
(12, 15, 'property_assignment', 'New property assigned for review', 'You\'ve been assigned to review property #25', '/rentbridge/agent/property_review.php?id=25', 0, '2026-06-18 03:25:47');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `property_type` enum('room','studio','whole_unit') NOT NULL,
  `address` text NOT NULL,
  `city` varchar(80) NOT NULL,
  `postcode` varchar(10) NOT NULL,
  `state` varchar(50) NOT NULL DEFAULT 'Melaka',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `maps_url` varchar(500) DEFAULT NULL,
  `monthly_rent` decimal(8,2) NOT NULL,
  `deposit` decimal(8,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `facilities` text DEFAULT NULL,
  `furnishing` enum('none','partial','full') NOT NULL DEFAULT 'partial',
  `status` enum('pending_approval','available','booked','rented','hidden','rejected') NOT NULL DEFAULT 'pending_approval',
  `assigned_agent_id` int(11) DEFAULT NULL,
  `agent_assigned_at` timestamp NULL DEFAULT NULL,
  `agent_status` enum('pending','inspecting','accepted','rejected','timeout') DEFAULT NULL,
  `inspection_completed_at` datetime DEFAULT NULL,
  `inspection_scheduled_at` datetime DEFAULT NULL,
  `inspection_access_method` varchar(50) DEFAULT NULL,
  `inspection_access_detail` text DEFAULT NULL,
  `assignment_round` int(11) NOT NULL DEFAULT 0,
  `viewing_mode` enum('landlord_led','agent_led','either') NOT NULL DEFAULT 'either',
  `agent_verified_at` timestamp NULL DEFAULT NULL,
  `agent_verified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `landlord_id`, `title`, `property_type`, `address`, `city`, `postcode`, `state`, `latitude`, `longitude`, `maps_url`, `monthly_rent`, `deposit`, `description`, `facilities`, `furnishing`, `status`, `assigned_agent_id`, `agent_assigned_at`, `agent_status`, `inspection_completed_at`, `inspection_scheduled_at`, `inspection_access_method`, `inspection_access_detail`, `assignment_round`, `viewing_mode`, `agent_verified_at`, `agent_verified_by`, `created_at`, `updated_at`) VALUES
(1, 10, 'Unfurnished room near UTeM', 'room', 'Jalan TBP 3', 'Ayer Keroh', '75450', 'Melaka', 2.3140000, 102.3200000, NULL, 600.00, 1200.00, 'Bring your own furniture. Quiet area.', 'WiFi, parking', 'none', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(2, 10, 'Empty room in Bukit Beruang', 'room', 'Jalan BB 12', 'Bukit Beruang', '75450', 'Melaka', 2.3220000, 102.3050000, NULL, 650.00, 1300.00, 'Unfurnished, suitable for long-term tenant.', 'WiFi, parking, shared kitchen', 'none', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(3, 10, 'Basic room Durian Tunggal', 'room', 'Jalan Sejahtera 9', 'Durian Tunggal', '76100', 'Melaka', 2.2580000, 102.2520000, NULL, 680.00, 1360.00, 'Affordable unfurnished option.', 'WiFi, fan, parking', 'none', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(4, 10, 'Unfurnished single room Cheng', 'room', 'Taman Cheng Baru', 'Cheng', '75250', 'Melaka', 2.2200000, 102.2300000, NULL, 720.00, 1440.00, 'Bring your own bed/table. Quiet kampung area.', 'WiFi, parking', 'none', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(5, 10, 'Empty whole-unit terrace', 'whole_unit', 'Taman Saujana 4', 'Hang Tuah Jaya', '75450', 'Melaka', 2.2950000, 102.3000000, NULL, 750.00, 1500.00, 'Empty 3-bedroom terrace. Bring your own furniture.', 'WiFi, parking, kitchen, garden', 'none', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(6, 10, 'Basic studio Ayer Keroh', 'studio', 'Jalan Sutera 5', 'Ayer Keroh', '75450', 'Melaka', 2.3170000, 102.3225000, NULL, 780.00, 1560.00, 'Unfurnished studio. Suitable for single occupant.', 'WiFi, kitchen, parking', 'none', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(7, 10, 'Unfurnished room Batu Berendam', 'room', 'Taman BB Indah 7', 'Batu Berendam', '75350', 'Melaka', 2.2580000, 102.2700000, NULL, 800.00, 1600.00, 'Empty room near airport area.', 'WiFi, parking, washing machine', 'none', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(8, 10, 'Empty heritage shoplot room', 'room', 'Jalan Hang Jebat', 'Melaka', '75200', 'Melaka', 2.1950000, 102.2470000, NULL, 900.00, 1800.00, 'Unfurnished room in heritage area.', 'WiFi, attached bath', 'none', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(9, 10, 'Partial-furnished room near UTeM', 'room', 'Jalan TBP 7', 'Ayer Keroh', '75450', 'Melaka', 2.3145000, 102.3210000, NULL, 1000.00, 2000.00, 'Bed + wardrobe provided. Walking distance to campus.', 'WiFi, aircond, attached bath, parking', 'partial', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(10, 10, 'Mid-range studio Hang Tuah Jaya', 'studio', 'Pangsapuri Saujana, Block A', 'Hang Tuah Jaya', '75450', 'Melaka', 2.2955000, 102.3005000, NULL, 1080.00, 2160.00, 'Studio with basic furniture. Pool access.', 'WiFi, aircond, kitchen, fridge, swimming pool', 'partial', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(11, 10, 'Partial whole-unit Bukit Beruang', 'whole_unit', 'No. 17, Jalan BB 5', 'Bukit Beruang', '75450', 'Melaka', 2.3225000, 102.3055000, NULL, 1150.00, 2300.00, '3-bedroom terrace, partially furnished.', 'WiFi, aircond, washing machine, kitchen, parking', 'partial', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(12, 10, 'Comfortable room Cheng', 'room', 'Lorong Cheng 8', 'Cheng', '75250', 'Melaka', 2.2210000, 102.2310000, NULL, 1180.00, 2360.00, 'Bed + study desk + wardrobe.', 'WiFi, aircond, parking, washing machine', 'partial', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(13, 10, '2-bed apartment Hang Tuah Jaya', 'whole_unit', 'Pangsapuri Saujana, Block C', 'Hang Tuah Jaya', '75450', 'Melaka', 2.2955000, 102.3005000, NULL, 1200.00, 2400.00, '2-bedroom apartment. Pool and gym.', 'WiFi, aircond, kitchen, fridge, swimming pool, gym', 'partial', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(14, 10, 'Partial townhouse Batu Berendam', 'whole_unit', 'Jalan BB Utama 3', 'Batu Berendam', '75350', 'Melaka', 2.2590000, 102.2710000, NULL, 1250.00, 2500.00, '3-bed townhouse with basic furniture.', 'WiFi, aircond, kitchen, parking, garden, washing machine', 'partial', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(15, 10, 'Mid-range studio Melaka Raya', 'studio', 'Jalan Melaka Raya 8', 'Melaka', '75000', 'Melaka', 2.1900000, 102.2480000, NULL, 1300.00, 2600.00, 'City center studio with basic furniture.', 'WiFi, aircond, kitchen, fridge, security', 'partial', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(16, 10, 'Partial 3-bed unit Ayer Keroh', 'whole_unit', 'No. 28, Jalan TBP 6', 'Ayer Keroh', '75450', 'Melaka', 2.3135000, 102.3195000, NULL, 1350.00, 2700.00, 'Suit 3-4 students. Bed + wardrobe in each room.', 'WiFi, aircond, washing machine, kitchen, parking', 'partial', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(17, 10, 'Spacious partial whole-unit Durian Tunggal', 'whole_unit', 'Taman Bukit Tambun Perdana 2', 'Durian Tunggal', '76100', 'Melaka', 2.2585000, 102.2525000, NULL, 1400.00, 2800.00, '3-bedroom terrace, partially furnished.', 'WiFi, aircond, washing machine, kitchen, parking, garden', 'partial', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(18, 10, 'Fully furnished master room near UTeM', 'room', 'Jalan TBP 2, Taman Bukit Pasir Indah', 'Ayer Keroh', '75450', 'Melaka', 2.3120000, 102.3180000, NULL, 1200.00, 2400.00, 'Master room with private bath. Move in with just your luggage.', 'WiFi, aircond, private bath, balcony, parking, washing machine', 'full', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(19, 10, 'Fully furnished studio AEON area', 'studio', 'Apartment Sutera, Jalan Sutera 1', 'Ayer Keroh', '75450', 'Melaka', 2.3170000, 102.3225000, NULL, 1300.00, 2600.00, 'Move-in ready studio. Near AEON mall.', 'WiFi, aircond, kitchen, fridge, parking, security, washing machine', 'full', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(20, 10, 'Fully furnished room Bukit Beruang', 'room', 'Jalan BB 18', 'Bukit Beruang', '75450', 'Melaka', 2.3230000, 102.3060000, NULL, 1400.00, 2800.00, 'Newly renovated. All furniture provided. 5 mins to UTeM IT block.', 'WiFi, aircond, attached bath, washing machine, study desk', 'full', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(21, 10, 'Fully furnished apartment Saujana', 'whole_unit', 'Pangsapuri Saujana, Block B', 'Hang Tuah Jaya', '75450', 'Melaka', 2.2960000, 102.3010000, NULL, 1500.00, 3000.00, '2-bedroom fully furnished apartment.', 'WiFi, aircond, kitchen, fridge, swimming pool, gym, security, washing machine', 'full', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(22, 10, 'Premium fully furnished townhouse', 'whole_unit', 'No. 88, Jalan BB 5', 'Bukit Beruang', '75450', 'Melaka', 2.3225000, 102.3055000, NULL, 1600.00, 3200.00, '4-bedroom fully furnished townhouse. Perfect for groups.', 'WiFi, aircond, washing machine, kitchen, parking, garden, full furniture', 'full', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(23, 10, 'Fully furnished studio Melaka Raya', 'studio', 'Jalan Melaka Raya 13', 'Melaka', '75000', 'Melaka', 2.1900000, 102.2480000, NULL, 1700.00, 3400.00, 'City center studio. Modern interior.', 'WiFi, aircond, kitchen, fridge, washing machine, security, smart TV', 'full', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(24, 10, 'Luxury fully furnished apartment Hang Tuah Jaya', 'whole_unit', 'Pangsapuri Saujana Indah, Block A', 'Hang Tuah Jaya', '75450', 'Melaka', 2.2950000, 102.3000000, NULL, 1800.00, 3600.00, '3-bedroom luxury apartment. All inclusive.', 'WiFi, aircond, kitchen, fridge, swimming pool, gym, security, washing machine, dryer', 'full', 'available', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'either', NULL, NULL, '2026-06-17 09:56:50', '2026-06-17 09:56:50'),
(25, 10, 'Ahmad House 1', 'whole_unit', '50, Jalan Sejahtera 4', 'Durian Tunggal', '76100', 'Melaka', NULL, NULL, NULL, 1000.00, 1000.00, NULL, NULL, 'none', 'pending_approval', 15, '2026-06-18 03:25:47', 'pending', NULL, NULL, NULL, NULL, 1, 'either', NULL, NULL, '2026-06-18 03:25:47', '2026-06-18 03:25:47');

-- --------------------------------------------------------

--
-- Table structure for table `property_agent_assignments`
--

CREATE TABLE `property_agent_assignments` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL,
  `outcome` enum('pending','accepted','rejected','timeout','reassigned') NOT NULL DEFAULT 'pending',
  `rejection_reason` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_agent_assignments`
--

INSERT INTO `property_agent_assignments` (`id`, `property_id`, `agent_id`, `round_number`, `assigned_at`, `responded_at`, `outcome`, `rejection_reason`) VALUES
(1, 25, 15, 1, '2026-06-18 03:25:47', NULL, 'pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `property_documents`
--

CREATE TABLE `property_documents` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `document_type` enum('ownership_proof','utility_bill','other') NOT NULL DEFAULT 'other',
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(150) DEFAULT NULL,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `mime_type` varchar(80) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_documents`
--

INSERT INTO `property_documents` (`id`, `property_id`, `document_type`, `file_path`, `original_name`, `file_size`, `mime_type`, `uploaded_by`, `uploaded_at`, `notes`) VALUES
(2, 1, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(3, 2, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(4, 3, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(5, 4, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(6, 5, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(7, 6, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(8, 7, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(9, 8, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(10, 9, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(11, 10, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(12, 11, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(13, 12, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(14, 13, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(15, 14, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(16, 15, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(17, 16, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(18, 17, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(19, 18, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(20, 19, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(21, 20, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(22, 21, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(23, 22, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(24, 23, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(25, 24, 'other', 'uploads/property_docs/placeholder.pdf', NULL, 0, NULL, 10, '2026-06-17 10:22:57', 'Sample document for demo'),
(33, 25, 'ownership_proof', 'uploads/property_docs/25_1781753147_165a16ea.pdf', 'placeholder.pdf', 1044365, 'application/pdf', 10, '2026-06-18 03:25:47', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `property_images`
--

CREATE TABLE `property_images` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_images`
--

INSERT INTO `property_images` (`id`, `property_id`, `image_path`, `is_primary`, `uploaded_at`) VALUES
(1, 1, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(2, 2, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(3, 3, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(4, 4, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(5, 5, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(6, 6, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(7, 7, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(8, 8, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(9, 9, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(10, 10, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(11, 11, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(12, 12, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(13, 13, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(14, 14, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(15, 15, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(16, 16, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(17, 17, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(18, 18, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(19, 19, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(20, 20, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(21, 21, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(22, 22, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(23, 23, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(24, 24, 'uploads/properties/placeholder.jpg', 1, '2026-06-17 10:00:19'),
(32, 25, 'uploads/properties/25_1781753147_f453b3d5.jpg', 1, '2026-06-18 03:25:47');

-- --------------------------------------------------------

--
-- Table structure for table `saved_properties`
--

CREATE TABLE `saved_properties` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saved_properties`
--

INSERT INTO `saved_properties` (`id`, `user_id`, `property_id`, `saved_at`) VALUES
(3, 3, 1, '2026-06-18 03:50:27');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `preferred_name` varchar(50) NOT NULL DEFAULT '',
  `avatar_path` varchar(255) DEFAULT NULL,
  `matric_no` varchar(20) NOT NULL,
  `university` varchar(80) NOT NULL DEFAULT 'UTeM',
  `phone` varchar(20) NOT NULL,
  `allow_whatsapp` tinyint(1) NOT NULL DEFAULT 0,
  `looking_for_housing` tinyint(1) NOT NULL DEFAULT 0,
  `housing_pref_city` varchar(80) DEFAULT NULL,
  `housing_pref_max_rent` decimal(8,2) DEFAULT NULL,
  `housing_pref_move_in` date DEFAULT NULL,
  `housing_bio` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`user_id`, `full_name`, `preferred_name`, `avatar_path`, `matric_no`, `university`, `phone`, `allow_whatsapp`, `looking_for_housing`, `housing_pref_city`, `housing_pref_max_rent`, `housing_pref_move_in`, `housing_bio`) VALUES
(2, 'Wong Jia Xi', 'Jia Xi', 'uploads/avatars/placeholder.jpg', 'B032310495', 'UTeM', '012-3456789', 0, 0, NULL, NULL, NULL, NULL),
(3, 'Lim Mei Ling', 'Mei Ling', 'uploads/avatars/placeholder.jpg', 'B032310123', 'UTeM', '012-9876543', 0, 1, 'Ayer Keroh', 500.00, '2026-09-01', 'Quiet, study-focused, non-smoker. Looking for a clean place near campus.'),
(4, 'Ali Bin Abdullah', 'Ali', 'uploads/avatars/placeholder.jpg', 'B032310234', 'UTeM', '013-1234567', 0, 1, 'Durian Tunggal', 600.00, '2026-08-15', 'Easy-going engineering student. Like cooking on weekends.'),
(5, 'Ramesh Kumar', 'Ramesh', 'uploads/avatars/placeholder.jpg', 'B032310345', 'UTeM', '011-2233445', 0, 0, NULL, NULL, NULL, NULL),
(6, 'Siti Aishah', 'Aishah', 'uploads/avatars/placeholder.jpg', 'B032310456', 'UTeM', '019-3344556', 0, 0, NULL, NULL, NULL, NULL),
(7, 'Tan Wei Zhe', 'Wei Zhe', 'uploads/avatars/placeholder.jpg', 'B032310567', 'UTeM', '012-4455667', 0, 0, NULL, NULL, NULL, NULL),
(8, 'Farah Aliyah', 'Farah', 'uploads/avatars/placeholder.jpg', 'B032310678', 'UTeM', '014-5566778', 0, 0, NULL, NULL, NULL, NULL),
(9, 'Kelvin Lee', 'Kelvin', 'uploads/avatars/placeholder.jpg', 'B032310789', 'UTeM', '012-6677889', 0, 0, NULL, NULL, NULL, NULL),
(19, 'Mohd Azlan Bin Ismail', 'Azlan', 'uploads/avatars/placeholder.jpg', 'B032310890', 'UTeM', '012-7890123', 0, 0, NULL, NULL, NULL, NULL),
(20, 'Devi A/P Murugan', 'Devi', 'uploads/avatars/placeholder.jpg', 'B032310901', 'UTeM', '012-8901234', 0, 1, NULL, NULL, NULL, NULL),
(21, 'Mohd Farid Bin Hashim', 'Farid', 'uploads/avatars/placeholder.jpg', 'B032310912', 'UTeM', '011-9012345', 0, 0, NULL, NULL, NULL, NULL),
(22, 'Kavitha A/P Selvaraj', 'Kavitha', 'uploads/avatars/placeholder.jpg', 'B032310923', 'UTeM', '019-0123456', 0, 1, NULL, NULL, NULL, NULL),
(23, 'Mohd Syafiq Bin Adnan', 'Syafiq', 'uploads/avatars/placeholder.jpg', 'B032310934', 'UTeM', '012-1234560', 0, 0, NULL, NULL, NULL, NULL),
(24, 'Jasmine Tan', 'Jasmine', 'uploads/avatars/placeholder.jpg', 'B032310945', 'UTeM', '013-2345601', 0, 0, NULL, NULL, NULL, NULL),
(25, 'Mohd Hafiz Bin Yusoff', 'Hafiz', 'uploads/avatars/placeholder.jpg', 'B032310956', 'UTeM', '014-3456012', 0, 0, NULL, NULL, NULL, NULL),
(26, 'Amelia Wong', 'Amelia', 'uploads/avatars/placeholder.jpg', 'B032310967', 'UTeM', '012-4560123', 0, 1, NULL, NULL, NULL, NULL),
(27, 'Mohd Zafri Bin Karim', 'Zafri', 'uploads/avatars/placeholder.jpg', 'B032310978', 'UTeM', '015-5601234', 1, 1, NULL, NULL, NULL, NULL),
(28, 'Nadia Binti Razak', 'Nadia', 'uploads/avatars/placeholder.jpg', 'B032310989', 'UTeM', '016-6012345', 0, 1, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `primary_role` enum('student','landlord','agent','admin') NOT NULL,
  `status` enum('active','pending','suspended','rejected') NOT NULL DEFAULT 'active',
  `last_used_role` enum('student','landlord','agent','admin') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `primary_role`, `status`, `last_used_role`, `created_at`, `updated_at`) VALUES
(1, 'admin@rentbridge.local', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'admin', 'active', NULL, '2026-06-10 05:49:31', '2026-06-18 03:08:37'),
(2, 'jiaxi@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-01-15 02:00:00', '2026-06-18 03:08:37'),
(3, 'meiling@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-02-03 06:22:00', '2026-06-18 03:08:37'),
(4, 'alibaba@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-03-10 01:15:00', '2026-06-18 03:08:37'),
(5, 'ramesh@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-03-20 03:00:00', '2026-06-18 03:08:37'),
(6, 'siti@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-04-05 08:45:00', '2026-06-18 03:08:37'),
(7, 'weizhe@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-04-12 00:30:00', '2026-06-18 03:08:37'),
(8, 'farah@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'suspended', NULL, '2026-04-20 05:00:00', '2026-06-18 03:08:37'),
(9, 'kelvin@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-05-01 02:10:00', '2026-06-18 03:08:37'),
(10, 'ahmad@landlord.com', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'landlord', 'active', NULL, '2026-01-20 01:00:00', '2026-06-18 03:08:37'),
(11, 'wong@landlord.com', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'landlord', 'active', NULL, '2026-02-15 03:30:00', '2026-06-18 03:08:37'),
(12, 'priya@landlord.com', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'landlord', 'active', NULL, '2026-02-28 06:15:00', '2026-06-18 03:08:37'),
(13, 'chen@landlord.com', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'landlord', 'active', NULL, '2026-03-08 08:00:00', '2026-06-18 03:08:37'),
(14, 'raj@landlord.com', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'landlord', 'active', NULL, '2026-04-12 02:45:00', '2026-06-18 03:08:37'),
(15, 'inspector1@utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'agent', 'active', NULL, '2026-01-05 00:00:00', '2026-06-18 03:08:37'),
(16, 'inspector2@utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'agent', 'active', NULL, '2026-01-10 01:30:00', '2026-06-18 03:08:37'),
(17, 'inspector3@utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'agent', 'pending', NULL, '2026-05-15 06:00:00', '2026-06-18 03:08:37'),
(18, 'inspector4@utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'agent', 'active', NULL, '2026-02-01 02:00:00', '2026-06-18 03:08:37'),
(19, 'azlan@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-01-08 01:00:00', '2026-06-18 03:08:37'),
(20, 'devi@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-01-22 02:30:00', '2026-06-18 03:08:37'),
(21, 'farid@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-02-05 03:00:00', '2026-06-18 03:08:37'),
(22, 'kavitha@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-02-18 06:00:00', '2026-06-18 03:08:37'),
(23, 'syafiq@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-03-02 02:00:00', '2026-06-18 03:08:37'),
(24, 'jasmine@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-03-15 01:30:00', '2026-06-18 03:08:37'),
(25, 'hafiz@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-04-08 08:00:00', '2026-06-18 03:08:37'),
(26, 'amelia@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-04-25 05:00:00', '2026-06-18 03:08:37'),
(27, 'zafri@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-05-12 03:00:00', '2026-06-18 03:08:37'),
(28, 'nadia@student.utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'student', 'active', NULL, '2026-06-01 02:00:00', '2026-06-18 03:08:37'),
(29, 'fauziah@landlord.com', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'landlord', 'active', NULL, '2026-01-10 03:00:00', '2026-06-18 03:08:37'),
(30, 'tan@landlord.com', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'landlord', 'active', NULL, '2026-02-08 06:00:00', '2026-06-18 03:08:37'),
(31, 'ismail@landlord.com', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'landlord', 'active', NULL, '2026-03-12 02:00:00', '2026-06-18 03:08:37'),
(32, 'kumar@landlord.com', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'landlord', 'active', NULL, '2026-04-20 07:00:00', '2026-06-18 03:08:37'),
(33, 'lim@landlord.com', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'landlord', 'active', NULL, '2026-05-25 03:00:00', '2026-06-18 03:08:37'),
(34, 'inspector5@utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'agent', 'active', NULL, '2026-02-15 01:00:00', '2026-06-18 03:08:37'),
(35, 'inspector6@utem.edu.my', '$2y$10$UxOTjCguXl9fcWWaNvvjtuxZfqHP2vM7Hba4BYob0n455/Hsv1s3y', 'agent', 'active', NULL, '2026-03-20 02:00:00', '2026-06-18 03:08:37');

-- --------------------------------------------------------

--
-- Table structure for table `verification_codes`
--

CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `purpose` varchar(40) NOT NULL COMMENT 'e.g. password_change, email_verify',
  `code` varchar(10) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD KEY `idx_assignment` (`availability`,`current_caseload`);

--
-- Indexes for table `agent_commissions`
--
ALTER TABLE `agent_commissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contract_id` (`contract_id`),
  ADD KEY `idx_agent_status` (`agent_id`,`status`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `agent_verifications`
--
ALTER TABLE `agent_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `agent_id` (`agent_id`),
  ADD KEY `idx_outcome` (`outcome`),
  ADD KEY `idx_deadline` (`deadline_at`);

--
-- Indexes for table `agent_verification_photos`
--
ALTER TABLE `agent_verification_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_verification` (`verification_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cancelled_by` (`cancelled_by`),
  ADD KEY `idx_student_status` (`student_id`,`status`),
  ADD KEY `idx_landlord_status` (`landlord_id`,`status`),
  ADD KEY `idx_agent_status` (`agent_id`,`status`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `replied_by` (`replied_by`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contract_code` (`contract_code`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `landlord_id` (`landlord_id`),
  ADD KEY `agent_id` (`agent_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_contract_code` (`contract_code`),
  ADD KEY `idx_booking` (`booking_id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pair_context` (`user_a`,`user_b`,`property_id`,`booking_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_user_a` (`user_a`,`last_message_at`),
  ADD KEY `idx_user_b` (`user_b`,`last_message_at`);

--
-- Indexes for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_conv_user` (`conversation_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `co_tenancy_applications`
--
ALTER TABLE `co_tenancy_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_post_applicant` (`post_id`,`applicant_id`),
  ADD KEY `applicant_id` (`applicant_id`);

--
-- Indexes for table `co_tenancy_posts`
--
ALTER TABLE `co_tenancy_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_poster` (`poster_id`);

--
-- Indexes for table `co_tenants`
--
ALTER TABLE `co_tenants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `idx_booking` (`booking_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pair` (`user_a`,`user_b`),
  ADD KEY `idx_user_a` (`user_a`),
  ADD KEY `idx_user_b` (`user_b`);

--
-- Indexes for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pair_pending` (`requester_id`,`receiver_id`,`status`),
  ADD KEY `idx_receiver_status` (`receiver_id`,`status`),
  ADD KEY `idx_requester_status` (`requester_id`,`status`);

--
-- Indexes for table `landlords`
--
ALTER TABLE `landlords`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `ic_no` (`ic_no`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_convo_time` (`conversation_id`,`sent_at`),
  ADD KEY `idx_unread` (`conversation_id`,`read_at`);

--
-- Indexes for table `move_in_inspections`
--
ALTER TABLE `move_in_inspections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contract_id` (`contract_id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Indexes for table `move_in_photos`
--
ALTER TABLE `move_in_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inspection` (`inspection_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`,`created_at`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_city_status` (`city`,`status`),
  ADD KEY `idx_landlord` (`landlord_id`),
  ADD KEY `fk_prop_verifier` (`agent_verified_by`),
  ADD KEY `idx_assigned_agent` (`assigned_agent_id`),
  ADD KEY `idx_agent_status` (`agent_status`);

--
-- Indexes for table `property_agent_assignments`
--
ALTER TABLE `property_agent_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_agent` (`agent_id`),
  ADD KEY `idx_outcome` (`outcome`);

--
-- Indexes for table `property_documents`
--
ALTER TABLE `property_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_type` (`document_type`);

--
-- Indexes for table `property_images`
--
ALTER TABLE `property_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_property` (`property_id`,`is_primary`);

--
-- Indexes for table `saved_properties`
--
ALTER TABLE `saved_properties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_property` (`user_id`,`property_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_saved` (`saved_at`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `matric_no` (`matric_no`),
  ADD KEY `idx_looking` (`looking_for_housing`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role_status` (`primary_role`,`status`);

--
-- Indexes for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_purpose` (`user_id`,`purpose`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agent_commissions`
--
ALTER TABLE `agent_commissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `agent_verifications`
--
ALTER TABLE `agent_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `agent_verification_photos`
--
ALTER TABLE `agent_verification_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `co_tenancy_applications`
--
ALTER TABLE `co_tenancy_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `co_tenancy_posts`
--
ALTER TABLE `co_tenancy_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `co_tenants`
--
ALTER TABLE `co_tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friend_requests`
--
ALTER TABLE `friend_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `move_in_inspections`
--
ALTER TABLE `move_in_inspections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `move_in_photos`
--
ALTER TABLE `move_in_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `property_agent_assignments`
--
ALTER TABLE `property_agent_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `property_documents`
--
ALTER TABLE `property_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `property_images`
--
ALTER TABLE `property_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `saved_properties`
--
ALTER TABLE `saved_properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `verification_codes`
--
ALTER TABLE `verification_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agents`
--
ALTER TABLE `agents`
  ADD CONSTRAINT `agents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_commissions`
--
ALTER TABLE `agent_commissions`
  ADD CONSTRAINT `agent_commissions_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_commissions_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_verifications`
--
ALTER TABLE `agent_verifications`
  ADD CONSTRAINT `agent_verifications_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_verifications_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_verification_photos`
--
ALTER TABLE `agent_verification_photos`
  ADD CONSTRAINT `agent_verification_photos_ibfk_1` FOREIGN KEY (`verification_id`) REFERENCES `agent_verifications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_4` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_ibfk_5` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD CONSTRAINT `contact_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `contact_messages_ibfk_2` FOREIGN KEY (`replied_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_ibfk_3` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_ibfk_4` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_ibfk_5` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user_a`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user_b`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_3` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `conversations_ibfk_4` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  ADD CONSTRAINT `conversation_participants_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversation_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `co_tenancy_applications`
--
ALTER TABLE `co_tenancy_applications`
  ADD CONSTRAINT `co_tenancy_applications_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `co_tenancy_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `co_tenancy_applications_ibfk_2` FOREIGN KEY (`applicant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `co_tenancy_posts`
--
ALTER TABLE `co_tenancy_posts`
  ADD CONSTRAINT `co_tenancy_posts_ibfk_1` FOREIGN KEY (`poster_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `co_tenancy_posts_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `co_tenants`
--
ALTER TABLE `co_tenants`
  ADD CONSTRAINT `co_tenants_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `co_tenants_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `co_tenants_ibfk_3` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `friends`
--
ALTER TABLE `friends`
  ADD CONSTRAINT `friends_ibfk_1` FOREIGN KEY (`user_a`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `friends_ibfk_2` FOREIGN KEY (`user_b`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD CONSTRAINT `friend_requests_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `friend_requests_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `landlords`
--
ALTER TABLE `landlords`
  ADD CONSTRAINT `landlords_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `move_in_inspections`
--
ALTER TABLE `move_in_inspections`
  ADD CONSTRAINT `move_in_inspections_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `move_in_inspections_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `move_in_photos`
--
ALTER TABLE `move_in_photos`
  ADD CONSTRAINT `move_in_photos_ibfk_1` FOREIGN KEY (`inspection_id`) REFERENCES `move_in_inspections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `fk_prop_verifier` FOREIGN KEY (`agent_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `properties_ibfk_2` FOREIGN KEY (`assigned_agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `property_agent_assignments`
--
ALTER TABLE `property_agent_assignments`
  ADD CONSTRAINT `property_agent_assignments_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_agent_assignments_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_documents`
--
ALTER TABLE `property_documents`
  ADD CONSTRAINT `property_documents_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_images`
--
ALTER TABLE `property_images`
  ADD CONSTRAINT `property_images_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_properties`
--
ALTER TABLE `saved_properties`
  ADD CONSTRAINT `saved_properties_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `saved_properties_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD CONSTRAINT `verification_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
-- --------------------------------------------------------
-- Table: agent_transfer_requests
-- Agent requests to hand off responsibility for a property
-- --------------------------------------------------------
CREATE TABLE `agent_transfer_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `requesting_agent_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending_admin','approved','rejected','finding_agent','completed') NOT NULL DEFAULT 'pending_admin',
  `admin_id` int(11) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `admin_decided_at` timestamp NULL DEFAULT NULL,
  `new_agent_id` int(11) DEFAULT NULL,
  `batch_number` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_property` (`property_id`),
  KEY `idx_requesting_agent` (`requesting_agent_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: agent_transfer_notifications
-- Tracks which agents were offered a transfer and their response
-- --------------------------------------------------------
CREATE TABLE `agent_transfer_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_request_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `batch_number` int(11) NOT NULL DEFAULT 1,
  `outcome` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `notified_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transfer_request` (`transfer_request_id`),
  KEY `idx_agent` (`agent_id`),
  CONSTRAINT `atn_ibfk_1` FOREIGN KEY (`transfer_request_id`) REFERENCES `agent_transfer_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `atn_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `agent_transfer_requests`
  ADD CONSTRAINT `atr_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `atr_ibfk_2` FOREIGN KEY (`requesting_agent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `atr_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `atr_ibfk_4` FOREIGN KEY (`new_agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
