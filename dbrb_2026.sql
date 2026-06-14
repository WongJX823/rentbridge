-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 14, 2026 at 01:06 PM
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
DROP DATABASE IF EXISTS `dbrb_2026`;

-- Create fresh database
CREATE DATABASE `dbrb_2026` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Use the database
USE `dbrb_2026`;
-- --------------------------------------------------------

--
-- Table structure for table `agents`
--

DROP TABLE IF EXISTS `agents`;
CREATE TABLE `agents` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
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
-- Truncate table before insert `agents`
--

TRUNCATE TABLE `agents`;
--
-- Dumping data for table `agents`
--

INSERT INTO `agents` (`user_id`, `full_name`, `preferred_name`, `staff_id`, `department`, `phone`, `allow_whatsapp`, `availability`, `max_caseload`, `current_caseload`) VALUES
(15, 'Dr. Aminah Binti Yusof', 'Aminah', 'AGT001', 'FTMK', '012-7778899', 1, 'available', 5, 1),
(16, 'En. Kumaran A/L Selvam', 'Kumaran', 'AGT002', 'FKE', '012-8889900', 1, 'busy', 5, 3),
(17, 'Cik Nurul Aiman', 'Nurul', 'AGT003', 'FKM', '012-9990011', 0, 'available', 5, 0),
(18, 'Mr. Lim Chee Keong', 'Chee Keong', 'AGT004', 'FTMK', '012-0001122', 1, 'off_duty', 3, 0),
(34, 'Dr. Hairul Bin Anuar', 'Hairul', 'AGT005', 'FTMK', '012-9991111', 1, 'available', 5, 2),
(35, 'Pn. Salmah Binti Hasan', 'Salmah', 'AGT006', 'FKE', '012-9992222', 1, 'available', 5, 1);

-- --------------------------------------------------------

--
-- Table structure for table `agent_commissions`
--

DROP TABLE IF EXISTS `agent_commissions`;
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

--
-- Truncate table before insert `agent_commissions`
--

TRUNCATE TABLE `agent_commissions`;
--
-- Dumping data for table `agent_commissions`
--

INSERT INTO `agent_commissions` (`id`, `contract_id`, `agent_id`, `base_rent`, `commission_pct`, `commission_amt`, `sst_pct`, `sst_amt`, `total_payable`, `status`, `earned_at`, `released_at`, `paid_at`, `payment_ref`) VALUES
(1, 1, 15, 600.00, 100.00, 600.00, 6.00, 36.00, 636.00, 'paid', '2026-04-22 06:00:00', '2026-04-25 02:00:00', '2026-04-30 02:00:00', NULL),
(2, 2, 16, 2400.00, 100.00, 2400.00, 6.00, 144.00, 2544.00, 'earned', '2026-05-28 09:00:00', NULL, NULL, NULL),
(3, 3, 18, 380.00, 100.00, 380.00, 6.00, 22.80, 402.80, 'paid', '2026-01-14 03:00:00', '2026-01-20 02:00:00', '2026-02-01 01:00:00', NULL),
(4, 4, 15, 420.00, 100.00, 420.00, 6.00, 25.20, 445.20, 'paid', '2026-01-25 06:00:00', '2026-02-01 02:00:00', '2026-02-10 01:00:00', NULL),
(5, 5, 16, 550.00, 100.00, 550.00, 6.00, 33.00, 583.00, 'paid', '2026-02-10 08:00:00', '2026-02-15 01:00:00', '2026-02-25 02:00:00', NULL),
(6, 6, 15, 460.00, 100.00, 460.00, 6.00, 27.60, 487.60, 'paid', '2026-02-25 03:00:00', '2026-03-01 01:00:00', '2026-03-10 03:00:00', NULL),
(7, 7, 16, 700.00, 100.00, 700.00, 6.00, 42.00, 742.00, 'paid', '2026-03-10 07:00:00', '2026-03-15 01:00:00', '2026-03-25 02:00:00', NULL),
(8, 8, 18, 320.00, 100.00, 320.00, 6.00, 19.20, 339.20, 'paid', '2026-03-28 08:00:00', '2026-04-01 01:00:00', '2026-04-10 02:00:00', NULL),
(9, 9, 15, 1100.00, 100.00, 1100.00, 6.00, 66.00, 1166.00, 'paid', '2026-04-10 09:00:00', '2026-04-15 01:00:00', '2026-04-25 02:00:00', NULL),
(10, 10, 18, 360.00, 100.00, 360.00, 6.00, 21.60, 381.60, 'released', '2026-04-26 06:00:00', '2026-05-01 02:00:00', NULL, NULL),
(11, 11, 16, 1050.00, 100.00, 1050.00, 6.00, 63.00, 1113.00, 'earned', '2026-05-10 10:00:00', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `agent_verifications`
--

DROP TABLE IF EXISTS `agent_verifications`;
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
  `outcome` enum('in_progress','passed','passed_with_disclosure','failed') NOT NULL DEFAULT 'in_progress',
  `student_proceeded_with_disclosure` tinyint(1) DEFAULT NULL,
  `student_decision_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `agent_verifications`
--

TRUNCATE TABLE `agent_verifications`;
--
-- Dumping data for table `agent_verifications`
--

INSERT INTO `agent_verifications` (`id`, `booking_id`, `agent_id`, `started_at`, `submitted_at`, `deadline_at`, `property_matches_listing`, `property_address_correct`, `facilities_match`, `landlord_id_matches`, `ownership_doc_sighted`, `inspection_notes`, `issues_found`, `issue_severity`, `outcome`, `student_proceeded_with_disclosure`, `student_decision_at`) VALUES
(1, 1, 15, '2026-04-13 01:00:00', '2026-04-15 02:00:00', '2026-04-18 01:00:00', 1, 1, 1, 1, 1, 'Property is in excellent condition. Landlord cooperative. All facilities match listing.', NULL, 'none', 'passed', NULL, NULL),
(2, 2, 16, '2026-05-16 02:00:00', '2026-05-20 06:00:00', '2026-05-21 02:00:00', 1, 1, 1, 1, 1, 'Beautiful renovated unit. Owner is the registered landlord. All checks pass.', NULL, 'none', 'passed', NULL, NULL),
(3, 5, 15, '2026-06-10 06:00:00', NULL, '2026-06-15 06:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'none', 'in_progress', NULL, NULL),
(4, 6, 18, '2026-05-29 02:00:00', '2026-05-31 08:00:00', '2026-06-03 02:00:00', 0, 1, 0, 1, 1, 'Property is structurally OK and landlord identity verified, BUT condition is much worse than photos suggest.', 'Major mold issue on north-facing wall in bedroom. Bathroom plumbing leaks. WiFi router not present. Listing photos appear to be 2+ years old. Recommend property be re-listed only after these are fixed.', 'major', 'failed', NULL, NULL),
(5, 8, 18, '2026-01-11 02:00:00', '2026-01-13 07:00:00', '2026-01-16 02:00:00', 1, 1, 1, 1, 1, 'Older property but well-maintained. Landlord is friendly.', NULL, 'none', 'passed', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `agent_verification_photos`
--

DROP TABLE IF EXISTS `agent_verification_photos`;
CREATE TABLE `agent_verification_photos` (
  `id` int(11) NOT NULL,
  `verification_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `caption` varchar(150) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `agent_verification_photos`
--

TRUNCATE TABLE `agent_verification_photos`;
-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
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
  `status` enum('pending_landlord','rejected_by_landlord','pending_agent','agent_assigned','agent_verifying','agent_verified','verification_failed','contract_pending','active','completed','cancelled_by_student','cancelled_by_landlord','cancelled_by_admin') NOT NULL DEFAULT 'pending_landlord',
  `student_note` text DEFAULT NULL,
  `landlord_response` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `rejected_agents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rejected_agents`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `bookings`
--

TRUNCATE TABLE `bookings`;
--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `student_id`, `property_id`, `landlord_id`, `agent_id`, `start_date`, `end_date`, `duration_type`, `monthly_rent`, `deposit`, `status`, `student_note`, `landlord_response`, `cancellation_reason`, `cancelled_by`, `rejected_agents`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 10, 15, '2026-05-01', '2027-04-30', '1_year', 600.00, 600.00, 'active', 'Looking forward to moving in soon, thanks!', NULL, NULL, NULL, NULL, '2026-04-12 02:00:00', '2026-06-13 05:58:38'),
(2, 9, 4, 12, 16, '2026-06-01', '2027-05-31', '1_year', 2400.00, 2400.00, 'active', 'I will rent with two other friends.', NULL, NULL, NULL, NULL, '2026-05-15 03:00:00', '2026-06-13 05:58:38'),
(3, 3, 1, 10, NULL, '2026-08-01', '2027-07-31', '1_year', 450.00, 450.00, 'pending_landlord', 'I am a quiet student, would love to take this room.', NULL, NULL, NULL, NULL, '2026-06-09 01:00:00', '2026-06-13 05:58:38'),
(4, 4, 5, 12, NULL, '2026-09-01', '2027-08-31', '1_year', 500.00, 500.00, 'pending_agent', 'Hi, can I book this room for the September semester?', NULL, NULL, NULL, NULL, '2026-06-09 06:00:00', '2026-06-13 05:58:38'),
(5, 5, 5, 12, 15, '2026-09-15', '2027-08-31', '1_year', 500.00, 500.00, 'agent_verifying', 'Booking for September.', NULL, NULL, NULL, NULL, '2026-06-10 01:00:00', '2026-06-13 05:58:38'),
(6, 6, 6, 13, 18, '2026-08-15', '2026-12-15', 'custom', 380.00, 380.00, 'verification_failed', 'Need it for one semester.', NULL, 'Property did not match listing photos. Walls had mold not shown in pictures.', NULL, NULL, '2026-05-28 08:00:00', '2026-06-13 05:58:38'),
(7, 6, 10, 12, 16, '2026-09-01', '2027-08-31', '1_year', 1800.00, 1800.00, 'cancelled_by_student', 'Heard from friend the area was nice.', NULL, 'Decided to rent with another group instead.', 6, NULL, '2026-05-20 02:00:00', '2026-06-13 05:58:38'),
(8, 5, 6, 13, 18, '2026-01-15', '2026-05-14', 'custom', 380.00, 380.00, 'completed', 'One semester only.', NULL, NULL, NULL, NULL, '2026-01-10 01:00:00', '2026-06-13 05:58:38'),
(9, 19, 11, 10, 15, '2026-02-01', '2027-01-31', '1_year', 420.00, 420.00, 'completed', NULL, NULL, NULL, NULL, NULL, '2026-01-15 02:00:00', '2026-06-14 06:42:37'),
(10, 20, 16, 12, 16, '2026-02-15', '2027-02-14', '1_year', 550.00, 550.00, 'active', NULL, NULL, NULL, NULL, NULL, '2026-01-20 03:00:00', '2026-06-14 06:42:37'),
(11, 21, 12, 10, 15, '2026-03-01', '2027-02-28', '1_year', 460.00, 460.00, 'active', NULL, NULL, NULL, NULL, NULL, '2026-02-15 01:00:00', '2026-06-14 06:42:37'),
(12, 22, 14, 29, 16, '2026-03-15', '2027-03-14', '1_year', 700.00, 700.00, 'active', NULL, NULL, NULL, NULL, NULL, '2026-02-28 06:00:00', '2026-06-14 06:42:37'),
(13, 23, 20, 31, 18, '2026-04-01', '2026-07-31', 'custom', 320.00, 320.00, 'active', NULL, NULL, NULL, NULL, NULL, '2026-03-20 02:00:00', '2026-06-14 06:42:37'),
(14, 24, 21, 32, 15, '2026-04-15', '2027-04-14', '1_year', 1100.00, 1100.00, 'active', NULL, NULL, NULL, NULL, NULL, '2026-03-25 08:00:00', '2026-06-14 06:42:37'),
(15, 25, 24, 33, 18, '2026-05-01', '2026-08-31', 'custom', 360.00, 360.00, 'active', NULL, NULL, NULL, NULL, NULL, '2026-04-18 01:00:00', '2026-06-14 06:42:37'),
(16, 26, 28, 12, 16, '2026-05-15', '2027-05-14', '1_year', 1050.00, 1050.00, 'active', NULL, NULL, NULL, NULL, NULL, '2026-04-25 06:00:00', '2026-06-14 06:42:37'),
(17, 27, 17, 12, 16, '2026-06-01', '2026-10-31', 'custom', 480.00, 480.00, 'contract_pending', NULL, NULL, NULL, NULL, NULL, '2026-05-20 03:00:00', '2026-06-14 06:42:37'),
(18, 28, 22, 32, 34, '2026-06-15', '2027-06-14', '1_year', 950.00, 950.00, 'agent_verifying', NULL, NULL, NULL, NULL, NULL, '2026-05-30 02:00:00', '2026-06-14 06:42:37'),
(19, 19, 18, 30, NULL, '2026-07-01', '2027-06-30', '1_year', 1600.00, 1600.00, 'pending_agent', NULL, NULL, NULL, NULL, NULL, '2026-06-05 06:00:00', '2026-06-14 06:42:37'),
(20, 20, 13, 29, 35, '2026-07-01', '2026-12-31', 'custom', 900.00, 900.00, 'agent_verifying', NULL, NULL, NULL, NULL, NULL, '2026-06-07 03:00:00', '2026-06-14 06:42:37'),
(21, 21, 25, 11, NULL, '2026-08-01', '2027-07-31', '1_year', 850.00, 850.00, 'pending_landlord', NULL, NULL, NULL, NULL, NULL, '2026-06-08 01:00:00', '2026-06-14 06:42:37'),
(22, 22, 15, 31, NULL, '2026-08-01', '2027-07-31', '1_year', 2200.00, 2200.00, 'pending_landlord', NULL, NULL, NULL, NULL, NULL, '2026-06-10 02:00:00', '2026-06-14 06:42:37'),
(23, 24, 23, 33, NULL, '2026-09-01', '2027-08-31', '1_year', 380.00, 380.00, 'pending_landlord', NULL, NULL, NULL, NULL, NULL, '2026-06-11 05:00:00', '2026-06-14 06:42:37');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
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

--
-- Truncate table before insert `contracts`
--

TRUNCATE TABLE `contracts`;
--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `contract_code`, `booking_id`, `student_id`, `landlord_id`, `agent_id`, `property_id`, `start_date`, `end_date`, `monthly_rent`, `deposit`, `terms`, `student_signature`, `student_signed_at`, `student_sign_ip`, `landlord_signature`, `landlord_signed_at`, `landlord_sign_ip`, `agent_signature`, `agent_signed_at`, `agent_sign_ip`, `contract_pdf_path`, `generated_pdf_path`, `generated_at`, `generated_by`, `signed_pdf_path`, `signed_uploaded_at`, `signed_uploaded_by`, `doc_hash`, `upload_method`, `status`, `activated_at`, `created_at`) VALUES
(1, 'RB-2026-00001', 1, 2, 10, 15, 2, '2026-05-01', '2027-04-30', 600.00, 600.00, 'Standard one-year tenancy agreement. The Tenant agrees to pay monthly rent on or before the 1st of each month. The Landlord agrees to maintain the property in habitable condition. Termination requires 30 days written notice.', NULL, '2026-04-20 02:30:00', NULL, NULL, '2026-04-21 08:00:00', NULL, NULL, '2026-04-22 05:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'generated', 'active', '2026-04-22 06:00:00', '2026-04-18 01:00:00'),
(2, 'RB-2026-00002', 2, 9, 12, 16, 4, '2026-06-01', '2027-05-31', 2400.00, 2400.00, 'Standard one-year tenancy agreement for the whole unit. The Tenant and co-tenants agree to share monthly rent equally and pay before the 1st of each month.', NULL, '2026-05-25 03:00:00', NULL, NULL, '2026-05-27 06:00:00', NULL, NULL, '2026-05-28 08:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'generated', 'active', '2026-05-28 09:00:00', '2026-05-22 02:00:00'),
(3, 'RB-2026-00003', 8, 5, 13, 18, 6, '2026-01-15', '2026-05-14', 380.00, 380.00, 'Standard 4-month semester tenancy agreement.', NULL, '2026-01-13 08:00:00', NULL, NULL, '2026-01-14 01:00:00', NULL, NULL, '2026-01-14 02:30:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'generated', 'completed', '2026-01-14 03:00:00', '2026-01-13 07:30:00'),
(4, 'RB-2026-00004', 9, 19, 10, 15, 11, '2026-02-01', '2027-01-31', 420.00, 420.00, 'Standard 1-year tenancy.', NULL, '2026-01-22 03:00:00', NULL, NULL, '2026-01-23 02:00:00', NULL, NULL, '2026-01-25 01:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'generated', 'completed', '2026-01-25 06:00:00', '2026-01-20 02:00:00'),
(5, 'RB-2026-00005', 10, 20, 12, 16, 16, '2026-02-15', '2027-02-14', 550.00, 550.00, 'Standard 1-year tenancy.', NULL, '2026-02-05 02:00:00', NULL, NULL, '2026-02-08 03:00:00', NULL, NULL, '2026-02-10 06:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'generated', 'active', '2026-02-10 08:00:00', '2026-02-01 01:00:00'),
(6, 'RB-2026-00006', 11, 21, 10, 15, 12, '2026-03-01', '2027-02-28', 460.00, 460.00, 'Standard 1-year tenancy.', NULL, '2026-02-22 02:00:00', NULL, NULL, '2026-02-23 06:00:00', NULL, NULL, '2026-02-25 01:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'generated', 'active', '2026-02-25 03:00:00', '2026-02-18 02:00:00'),
(7, 'RB-2026-00007', 12, 22, 29, 16, 14, '2026-03-15', '2027-03-14', 700.00, 700.00, 'Standard 1-year tenancy.', NULL, '2026-03-07 01:00:00', NULL, NULL, '2026-03-08 03:00:00', NULL, NULL, '2026-03-10 05:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'generated', 'active', '2026-03-10 07:00:00', '2026-03-02 06:00:00'),
(8, 'RB-2026-00008', 13, 23, 31, 18, 20, '2026-04-01', '2026-07-31', 320.00, 320.00, 'Standard 4-month tenancy.', NULL, '2026-03-25 02:00:00', NULL, NULL, '2026-03-26 03:00:00', NULL, NULL, '2026-03-28 06:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'generated', 'active', '2026-03-28 08:00:00', '2026-03-22 01:00:00'),
(9, 'RB-2026-00009', 14, 24, 32, 15, 21, '2026-04-15', '2027-04-14', 1100.00, 1100.00, 'Standard 1-year tenancy.', NULL, '2026-04-05 03:00:00', NULL, NULL, '2026-04-08 06:00:00', NULL, NULL, '2026-04-10 07:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'generated', 'active', '2026-04-10 09:00:00', '2026-04-01 05:00:00'),
(10, 'RB-2026-00010', 15, 25, 33, 18, 24, '2026-05-01', '2026-08-31', 360.00, 360.00, 'Standard 4-month tenancy.', NULL, '2026-04-23 01:00:00', NULL, NULL, '2026-04-24 03:00:00', NULL, NULL, '2026-04-26 04:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'generated', 'active', '2026-04-26 06:00:00', '2026-04-20 02:00:00'),
(11, 'RB-2026-00011', 16, 26, 12, 16, 28, '2026-05-15', '2027-05-14', 1050.00, 1050.00, 'Standard 1-year tenancy.', NULL, '2026-05-05 06:00:00', NULL, NULL, '2026-05-08 03:00:00', NULL, NULL, '2026-05-10 08:00:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'generated', 'active', '2026-05-10 10:00:00', '2026-04-30 04:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `user_a` int(11) NOT NULL COMMENT 'Always lower user_id',
  `user_b` int(11) NOT NULL COMMENT 'Always higher user_id',
  `property_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `context_type` enum('property_inquiry','booking','friend','agent_case','other') NOT NULL DEFAULT 'other',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `last_message_preview` varchar(120) DEFAULT NULL,
  `last_sender_id` int(11) DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `locked_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `conversations`
--

TRUNCATE TABLE `conversations`;
--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `user_a`, `user_b`, `property_id`, `booking_id`, `context_type`, `last_message_at`, `last_message_preview`, `last_sender_id`, `is_locked`, `locked_reason`, `created_at`) VALUES
(1, 3, 10, 1, NULL, 'property_inquiry', '2026-06-09 01:30:00', 'Sure, let me arrange a viewing this Saturday.', 10, 0, NULL, '2026-06-08 10:00:00'),
(2, 4, 12, 5, NULL, 'property_inquiry', '2026-06-09 06:30:00', 'Yes the room is still available!', 12, 0, NULL, '2026-06-09 06:00:00'),
(3, 2, 10, 2, NULL, 'booking', '2026-06-13 08:12:57', 'What\'s included in the rent?', 2, 0, NULL, '2026-04-15 03:00:00'),
(4, 9, 12, 4, NULL, 'booking', '2026-05-27 07:00:00', 'Contract sent for signing.', 12, 0, NULL, '2026-05-20 04:00:00'),
(5, 6, 13, 6, NULL, 'property_inquiry', '2026-05-30 02:00:00', 'I am sorry about the mold issue, I will fix it.', 13, 0, NULL, '2026-05-28 09:00:00'),
(6, 16, 27, NULL, 17, 'agent_case', NULL, NULL, NULL, 0, NULL, '2026-06-14 06:46:01');

-- --------------------------------------------------------

--
-- Table structure for table `co_tenancy_posts`
--

DROP TABLE IF EXISTS `co_tenancy_posts`;
CREATE TABLE `co_tenancy_posts` (
  `id` int(11) NOT NULL,
  `poster_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `title` varchar(150) DEFAULT NULL COMMENT 'optional headline, defaults to property title',
  `message` text NOT NULL COMMENT 'why they want housemates, lifestyle preferences',
  `housemates_needed` int(11) NOT NULL DEFAULT 1 COMMENT 'how many more co-tenants wanted',
  `status` enum('open','filled','cancelled','expired') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `co_tenancy_posts`
--

TRUNCATE TABLE `co_tenancy_posts`;
-- --------------------------------------------------------

--
-- Table structure for table `co_tenants`
--

DROP TABLE IF EXISTS `co_tenants`;
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

--
-- Truncate table before insert `co_tenants`
--

TRUNCATE TABLE `co_tenants`;
--
-- Dumping data for table `co_tenants`
--

INSERT INTO `co_tenants` (`id`, `booking_id`, `student_id`, `is_primary`, `full_name`, `ic_number`, `phone`, `email`, `home_address`, `sign_order`, `signed_at`, `signature_data`, `added_at`, `added_by`, `status`, `notes`) VALUES
(1, 1, 2, 1, 'Wong Jia Xi', 'PENDING', '012-3456789', 'jiaxi@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-04-12 02:00:00', 2, 'signed', NULL),
(2, 3, 3, 1, 'Lim Mei Ling', 'PENDING', '012-9876543', 'meiling@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-06-09 01:00:00', 3, 'pending', NULL),
(3, 4, 4, 1, 'Ali Bin Abdullah', 'PENDING', '013-1234567', 'alibaba@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-06-09 06:00:00', 4, 'pending', NULL),
(4, 5, 5, 1, 'Ramesh Kumar', 'PENDING', '011-2233445', 'ramesh@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-06-10 01:00:00', 5, 'pending', NULL),
(5, 8, 5, 1, 'Ramesh Kumar', 'PENDING', '011-2233445', 'ramesh@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-01-10 01:00:00', 5, 'signed', NULL),
(6, 6, 6, 1, 'Siti Aishah', 'PENDING', '019-3344556', 'siti@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-05-28 08:00:00', 6, 'pending', NULL),
(7, 7, 6, 1, 'Siti Aishah', 'PENDING', '019-3344556', 'siti@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-05-20 02:00:00', 6, 'pending', NULL),
(8, 2, 9, 1, 'Kelvin Lee', 'PENDING', '012-6677889', 'kelvin@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-05-15 03:00:00', 9, 'signed', NULL),
(9, 19, 19, 1, 'Mohd Azlan Bin Ismail', 'PENDING', '012-7890123', 'azlan@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-06-05 06:00:00', 19, 'pending', NULL),
(10, 9, 19, 1, 'Mohd Azlan Bin Ismail', 'PENDING', '012-7890123', 'azlan@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-01-15 02:00:00', 19, 'signed', NULL),
(11, 20, 20, 1, 'Devi A/P Murugan', 'PENDING', '012-8901234', 'devi@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-06-07 03:00:00', 20, 'pending', NULL),
(12, 10, 20, 1, 'Devi A/P Murugan', 'PENDING', '012-8901234', 'devi@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-01-20 03:00:00', 20, 'signed', NULL),
(13, 21, 21, 1, 'Mohd Farid Bin Hashim', 'PENDING', '011-9012345', 'farid@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-06-08 01:00:00', 21, 'pending', NULL),
(14, 11, 21, 1, 'Mohd Farid Bin Hashim', 'PENDING', '011-9012345', 'farid@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-02-15 01:00:00', 21, 'signed', NULL),
(15, 22, 22, 1, 'Kavitha A/P Selvaraj', 'PENDING', '019-0123456', 'kavitha@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-06-10 02:00:00', 22, 'pending', NULL),
(16, 12, 22, 1, 'Kavitha A/P Selvaraj', 'PENDING', '019-0123456', 'kavitha@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-02-28 06:00:00', 22, 'signed', NULL),
(17, 13, 23, 1, 'Mohd Syafiq Bin Adnan', 'PENDING', '012-1234560', 'syafiq@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-03-20 02:00:00', 23, 'signed', NULL),
(18, 23, 24, 1, 'Jasmine Tan', 'PENDING', '013-2345601', 'jasmine@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-06-11 05:00:00', 24, 'pending', NULL),
(19, 14, 24, 1, 'Jasmine Tan', 'PENDING', '013-2345601', 'jasmine@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-03-25 08:00:00', 24, 'signed', NULL),
(20, 15, 25, 1, 'Mohd Hafiz Bin Yusoff', 'PENDING', '014-3456012', 'hafiz@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-04-18 01:00:00', 25, 'signed', NULL),
(21, 16, 26, 1, 'Amelia Wong', 'PENDING', '012-4560123', 'amelia@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-04-25 06:00:00', 26, 'signed', NULL),
(22, 17, 27, 1, 'Mohd Zafri Bin Karim', 'PENDING', '015-5601234', 'zafri@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-05-20 03:00:00', 27, 'pending', NULL),
(23, 18, 28, 1, 'Nadia Binti Razak', 'PENDING', '016-6012345', 'nadia@student.utem.edu.my', NULL, 1, NULL, NULL, '2026-05-30 02:00:00', 28, 'pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `friends`
--

DROP TABLE IF EXISTS `friends`;
CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_a` int(11) NOT NULL COMMENT 'Always the lower user_id',
  `user_b` int(11) NOT NULL COMMENT 'Always the higher user_id',
  `became_friends_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `friends`
--

TRUNCATE TABLE `friends`;
-- --------------------------------------------------------

--
-- Table structure for table `friend_requests`
--

DROP TABLE IF EXISTS `friend_requests`;
CREATE TABLE `friend_requests` (
  `id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `message` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `friend_requests`
--

TRUNCATE TABLE `friend_requests`;
-- --------------------------------------------------------

--
-- Table structure for table `landlords`
--

DROP TABLE IF EXISTS `landlords`;
CREATE TABLE `landlords` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `preferred_name` varchar(50) NOT NULL DEFAULT '',
  `ic_no` varchar(20) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `allow_whatsapp` tinyint(1) NOT NULL DEFAULT 0,
  `address` varchar(255) DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `landlords`
--

TRUNCATE TABLE `landlords`;
--
-- Dumping data for table `landlords`
--

INSERT INTO `landlords` (`user_id`, `full_name`, `preferred_name`, `ic_no`, `phone`, `allow_whatsapp`, `address`, `verified`) VALUES
(10, 'Ahmad Bin Hassan', 'Ahmad', '780512-04-5678', '012-1112233', 1, 'No 23, Jalan Sutera 5, Taman Sutera, 75450 Ayer Keroh, Melaka', 1),
(11, 'Wong Soo Lan', 'Wong', '850923-08-1234', '012-2223344', 1, 'No 15, Jalan Indah 7, Taman Indah, 75450 Ayer Keroh, Melaka', 0),
(12, 'Priya A/P Subramaniam', 'Priya', '820714-06-9012', '012-3334455', 1, 'No 88, Lorong Permai 2, Bukit Beruang, 75450 Melaka', 1),
(13, 'Chen Wei Ming', 'Chen', '770308-10-3456', '012-4445566', 0, 'No 7, Jalan Cheng Heng, Taman Cheng, 75250 Melaka', 1),
(14, 'Raj Singh', 'Raj', '700615-14-7890', '012-5556677', 1, 'No 42, Jalan Bunga Raya, Taman Bunga, 75100 Melaka', 0),
(29, 'Fauziah Binti Saad', 'Fauziah', '760412-04-1122', '012-7771234', 1, NULL, 0),
(30, 'Tan Boon Heng', 'Tan', '690819-08-3344', '012-7772345', 0, NULL, 0),
(31, 'Ismail Bin Yaakub', 'Ismail', '720625-06-5566', '012-7773456', 1, NULL, 0),
(32, 'Kumar A/L Raman', 'Kumar', '801107-10-7788', '012-7774567', 1, NULL, 0),
(33, 'Lim Soo Mei', 'Lim', '850314-08-9900', '012-7775678', 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
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
-- Truncate table before insert `messages`
--

TRUNCATE TABLE `messages`;
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
(18, 6, 16, '📋 Co-tenant details requested\nPlease fill in the names and IC numbers of everyone who will rent this property with you.', 'co_tenant_form', '{\"booking_id\":17,\"property_title\":\"Beruang Garden View Room\"}', '2026-06-14 06:46:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `move_in_inspections`
--

DROP TABLE IF EXISTS `move_in_inspections`;
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

--
-- Truncate table before insert `move_in_inspections`
--

TRUNCATE TABLE `move_in_inspections`;
-- --------------------------------------------------------

--
-- Table structure for table `move_in_photos`
--

DROP TABLE IF EXISTS `move_in_photos`;
CREATE TABLE `move_in_photos` (
  `id` int(11) NOT NULL,
  `inspection_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `caption` varchar(150) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `move_in_photos`
--

TRUNCATE TABLE `move_in_photos`;
-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
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
-- Truncate table before insert `notifications`
--

TRUNCATE TABLE `notifications`;
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
(11, 27, 'cotenant_form_request', 'Agent requested co-tenant details', 'Please open the chat to fill in co-tenant info for \"Beruang Garden View Room\".', '/rentbridge/chat.php?id=6', 0, '2026-06-14 06:46:01');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

DROP TABLE IF EXISTS `properties`;
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
  `viewing_mode` enum('landlord_led','agent_led','either') NOT NULL DEFAULT 'either',
  `agent_verified_at` timestamp NULL DEFAULT NULL,
  `agent_verified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `properties`
--

TRUNCATE TABLE `properties`;
--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `landlord_id`, `title`, `property_type`, `address`, `city`, `postcode`, `state`, `latitude`, `longitude`, `maps_url`, `monthly_rent`, `deposit`, `description`, `facilities`, `furnishing`, `status`, `viewing_mode`, `agent_verified_at`, `agent_verified_by`, `created_at`, `updated_at`) VALUES
(1, 10, 'Cozy Single Room Near UTeM Main Gate', 'room', 'No 23, Jalan Sutera 5, Taman Sutera', 'Ayer Keroh', '75450', 'Melaka', NULL, NULL, NULL, 500.00, 450.00, 'A bright, well-ventilated single room walking distance to UTeM main entrance. Comes with bed, study table, wardrobe.', 'WiFi, attached bathroom, parking, kitchen access', 'partial', 'available', 'either', '2026-04-15 02:00:00', 15, '2026-04-10 01:00:00', '2026-06-14 09:43:39'),
(2, 10, 'Master Bedroom with Aircond', 'room', 'No 23, Jalan Sutera 5, Taman Sutera', 'Ayer Keroh', '75450', 'Melaka', NULL, NULL, NULL, 700.00, 600.00, 'Upgraded master room with private bathroom, queen bed and air-conditioning.', 'WiFi, aircond, private bathroom, parking', 'full', 'rented', 'either', '2026-05-01 03:00:00', 15, '2026-04-12 06:00:00', '2026-06-14 09:43:39'),
(3, 11, 'Studio Apartment in Taman Indah', 'studio', 'No 15, Jalan Indah 7, Taman Indah', 'Ayer Keroh', '75450', 'Melaka', NULL, NULL, NULL, 1100.00, 850.00, 'Compact studio for one person, fully furnished. Brand new building.', 'WiFi, aircond, kitchen, gym access', 'full', 'pending_approval', 'agent_led', NULL, NULL, '2026-06-08 02:30:00', '2026-06-14 09:43:39'),
(4, 12, '3-Bedroom Whole Unit, Bukit Beruang', 'whole_unit', 'No 88, Lorong Permai 2', 'Bukit Beruang', '75450', 'Melaka', NULL, NULL, NULL, 2400.00, 2400.00, 'Spacious 3-bedroom terrace house for groups of 3-4 students. Recently renovated.', 'WiFi, aircond, washing machine, parking 2 cars', 'full', 'booked', 'landlord_led', '2026-05-20 06:00:00', 16, '2026-04-25 01:00:00', '2026-06-13 05:58:38'),
(5, 12, 'Quiet Bedroom for Studious Student', 'room', 'No 88, Lorong Permai 2', 'Bukit Beruang', '75450', 'Melaka', NULL, NULL, NULL, 500.00, 500.00, 'Peaceful neighborhood, ideal for serious students. Strict no-party policy.', 'WiFi, study desk, shared kitchen', 'partial', 'available', 'either', '2026-05-15 01:00:00', 16, '2026-05-01 03:00:00', '2026-06-13 05:58:38'),
(6, 13, 'Cheng Heng Family-Style Room', 'room', 'No 7, Jalan Cheng Heng', 'Cheng', '75250', 'Melaka', NULL, NULL, NULL, 380.00, 380.00, 'Old-school family home with affordable rent. Landlord stays nearby. Great for budget-conscious students.', 'WiFi, parking, garden', 'partial', 'available', 'landlord_led', NULL, NULL, '2026-05-10 05:00:00', '2026-06-13 05:58:38'),
(7, 13, 'Hidden Listing — Maintenance', 'room', 'No 7, Jalan Cheng Heng', 'Cheng', '75250', 'Melaka', NULL, NULL, NULL, 350.00, 350.00, 'Temporarily off the market while plumbing is being repaired.', 'WiFi, parking', 'none', 'hidden', 'either', NULL, NULL, '2026-04-20 02:00:00', '2026-06-13 05:58:38'),
(8, 14, 'Bunga Raya Suspicious Listing', 'room', 'No 42, Jalan Bunga Raya', 'Melaka City', '75100', 'Melaka', NULL, NULL, NULL, 250.00, 0.00, 'Very cheap room, no deposit needed. Contact me direct.', 'Nothing', 'none', 'rejected', 'either', NULL, NULL, '2026-05-25 10:00:00', '2026-06-13 05:58:38'),
(9, 11, 'Newly Listed Studio (Awaiting Approval)', 'studio', 'No 15, Jalan Indah 7, Block B', 'Ayer Keroh', '75450', 'Melaka', NULL, NULL, NULL, 1100.00, 800.00, 'Brand new studio unit, just finished renovation. Waiting for admin approval.', 'WiFi, aircond, kitchen', 'full', 'pending_approval', 'either', NULL, NULL, '2026-06-09 07:00:00', '2026-06-14 09:43:39'),
(10, 12, 'Beruang Family Home Whole Unit', 'whole_unit', 'No 90, Lorong Permai 2', 'Bukit Beruang', '75450', 'Melaka', NULL, NULL, NULL, 1800.00, 1800.00, 'Whole single-storey terrace, ideal for 3 students.', 'WiFi, aircond in living room, parking', 'partial', 'available', 'either', '2026-05-25 02:00:00', 18, '2026-05-12 04:00:00', '2026-06-13 05:58:38'),
(11, 10, 'New, cheap house near UTeM', 'whole_unit', '33, Jalan Sejahtera 5, Taman Bukit Tambun Perdana 2', 'Durian Tunggal', '76100', 'Melaka', NULL, NULL, NULL, 800.00, 800.00, 'New unfurnished, so give cheap price.', 'None', 'none', 'pending_approval', 'either', NULL, NULL, '2026-06-13 08:42:55', '2026-06-13 08:42:55'),
(12, 10, 'Sutera Single Room A2', 'room', 'No 23B, Jalan Sutera 5', 'Ayer Keroh', '75450', 'Melaka', NULL, NULL, NULL, 500.00, 460.00, 'Slightly larger room with aircond.', 'WiFi, aircond', 'partial', 'rented', 'either', '2026-02-20 02:00:00', 15, '2026-02-12 01:00:00', '2026-06-14 09:43:39'),
(13, 29, 'Indah Studio Premium', 'studio', 'No 8, Jalan Indah 10', 'Ayer Keroh', '75450', 'Melaka', NULL, NULL, NULL, 1100.00, 900.00, 'High-end studio with full kitchen.', 'WiFi, aircond, kitchen, gym', 'full', 'available', 'either', '2026-03-05 06:00:00', 16, '2026-02-28 03:00:00', '2026-06-14 09:43:39'),
(14, 29, 'Indah Studio Budget', 'studio', 'No 9, Jalan Indah 10', 'Ayer Keroh', '75450', 'Melaka', NULL, NULL, NULL, 850.00, 700.00, 'Budget studio option in same building.', 'WiFi, fan, kitchen', 'partial', 'rented', 'either', '2026-03-08 06:00:00', 16, '2026-03-01 03:00:00', '2026-06-14 09:43:39'),
(15, 31, 'Keroh Heights Unit 3A', 'whole_unit', 'Block A-3-2, Keroh Heights', 'Ayer Keroh', '75450', 'Melaka', NULL, NULL, NULL, 2200.00, 2200.00, '3-bedroom apartment for groups.', 'WiFi, aircond, parking', 'partial', 'available', 'either', '2026-04-10 01:00:00', 18, '2026-04-02 02:00:00', '2026-06-14 06:42:37'),
(16, 12, 'Beruang Premium Room', 'room', 'No 92, Lorong Permai 2', 'Bukit Beruang', '75450', 'Melaka', NULL, NULL, NULL, 700.00, 550.00, 'Larger master room with attached bath.', 'WiFi, aircond, private bath', 'full', 'rented', 'either', '2026-02-18 02:00:00', 16, '2026-02-10 06:00:00', '2026-06-14 09:43:39'),
(17, 12, 'Beruang Garden View Room', 'room', 'No 94, Lorong Permai 2', 'Bukit Beruang', '75450', 'Melaka', NULL, NULL, NULL, 500.00, 480.00, 'Room facing garden.', 'WiFi, fan, garden access', 'partial', 'available', 'either', '2026-03-22 03:00:00', 18, '2026-03-15 02:00:00', '2026-06-14 09:43:39'),
(18, 30, 'Bukit Beruang 2-Bed', 'whole_unit', 'No 56, Jalan Permai 4', 'Bukit Beruang', '75450', 'Melaka', NULL, NULL, NULL, 1600.00, 1600.00, '2-bedroom unit for 2-3 students.', 'WiFi, aircond, parking', 'partial', 'available', 'either', '2026-04-15 06:00:00', 15, '2026-04-08 02:00:00', '2026-06-14 06:42:37'),
(19, 13, 'Tunggal Family House Room', 'room', 'No 12, Jalan Durian 3', 'Durian Tunggal', '76100', 'Melaka', NULL, NULL, NULL, 500.00, 350.00, 'Affordable room with friendly landlord.', 'WiFi, parking, garden', 'partial', 'available', 'landlord_led', '2026-03-25 01:00:00', 16, '2026-03-18 03:00:00', '2026-06-14 09:43:39'),
(20, 31, 'Tunggal Budget Room', 'room', 'No 45, Jalan Durian 7', 'Durian Tunggal', '76100', 'Melaka', NULL, NULL, NULL, 380.00, 320.00, 'Cheapest option near transport hub.', 'WiFi, fan', 'none', 'rented', 'landlord_led', '2026-04-02 06:00:00', 18, '2026-03-28 01:00:00', '2026-06-14 09:43:39'),
(21, 32, 'Melaka City Loft Studio', 'studio', 'Unit 12-3, Melaka City Tower', 'Melaka City', '75100', 'Melaka', NULL, NULL, NULL, 1100.00, 1100.00, 'Modern loft studio in city center.', 'WiFi, aircond, gym, pool', 'full', 'rented', 'either', '2026-04-25 07:00:00', 15, '2026-04-18 05:00:00', '2026-06-14 06:42:37'),
(22, 32, 'Melaka City Standard Studio', 'studio', 'Unit 8-5, Melaka City Tower', 'Melaka City', '75100', 'Melaka', NULL, NULL, NULL, 950.00, 950.00, 'Same building, smaller unit.', 'WiFi, aircond, gym', 'full', 'available', 'either', '2026-05-10 03:00:00', 16, '2026-05-02 01:00:00', '2026-06-14 06:42:37'),
(23, 33, 'Cheng Family Home Room A', 'room', 'No 18, Taman Cheng Indah', 'Cheng', '75250', 'Melaka', NULL, NULL, NULL, 380.00, 380.00, 'Quiet suburban room.', 'WiFi, parking', 'partial', 'available', 'either', '2026-05-15 02:00:00', 18, '2026-05-08 03:00:00', '2026-06-14 06:42:37'),
(24, 33, 'Cheng Family Home Room B', 'room', 'No 18A, Taman Cheng Indah', 'Cheng', '75250', 'Melaka', NULL, NULL, NULL, 360.00, 360.00, 'Sister room to Room A.', 'WiFi, parking', 'partial', 'rented', 'either', '2026-05-18 02:00:00', 18, '2026-05-08 03:00:00', '2026-06-14 06:42:37'),
(25, 11, 'Newly Listed Studio', 'studio', 'Block C-2-1, Indah Heights', 'Ayer Keroh', '75450', 'Melaka', NULL, NULL, NULL, 1100.00, 850.00, 'Just listed, looking for tenants.', 'WiFi, aircond', 'full', 'pending_approval', 'either', NULL, NULL, '2026-06-05 06:00:00', '2026-06-14 09:43:39'),
(26, 30, 'Yet Another Pending', 'room', 'No 78, Jalan Mawar', 'Bukit Beruang', '75450', 'Melaka', NULL, NULL, NULL, 500.00, 500.00, 'Recent listing awaiting approval.', 'WiFi', 'partial', 'pending_approval', 'either', NULL, NULL, '2026-06-08 02:00:00', '2026-06-14 06:42:37'),
(27, 14, 'Suspicious Cheap Room', 'room', 'No 99, Jalan ABC', 'Melaka City', '75100', 'Melaka', NULL, NULL, NULL, 180.00, 0.00, 'Very cheap, no deposit.', 'Nothing', 'none', 'rejected', 'either', NULL, NULL, '2026-04-30 09:00:00', '2026-06-14 06:42:37'),
(28, 12, 'Premium Beruang Studio', 'studio', 'Block B-3-1, Permai Heights', 'Bukit Beruang', '75450', 'Melaka', NULL, NULL, NULL, 1100.00, 1050.00, 'Premium studio with full furnishing.', 'WiFi, aircond, kitchen, gym', 'full', 'rented', 'either', '2026-05-25 06:00:00', 16, '2026-05-18 01:00:00', '2026-06-14 09:43:39'),
(29, 13, 'Quiet Studio Cheng', 'studio', 'Unit 5, Taman Cheng Permai', 'Cheng', '75250', 'Melaka', NULL, NULL, NULL, 750.00, 750.00, 'Peaceful studio in residential area.', 'WiFi, aircond', 'partial', 'available', 'either', '2026-05-28 02:00:00', 15, '2026-05-22 03:00:00', '2026-06-14 06:42:37'),
(30, 29, 'Hidden Maintenance', 'room', 'No 9, Jalan Indah 10', 'Ayer Keroh', '75450', 'Melaka', NULL, NULL, NULL, 500.00, 430.00, 'Temporarily off market.', 'WiFi, fan', 'partial', 'hidden', 'either', NULL, NULL, '2026-03-10 03:00:00', '2026-06-14 09:43:39');

-- --------------------------------------------------------

--
-- Table structure for table `property_documents`
--

DROP TABLE IF EXISTS `property_documents`;
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
-- Truncate table before insert `property_documents`
--

TRUNCATE TABLE `property_documents`;
-- --------------------------------------------------------

--
-- Table structure for table `property_images`
--

DROP TABLE IF EXISTS `property_images`;
CREATE TABLE `property_images` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `property_images`
--

TRUNCATE TABLE `property_images`;
--
-- Dumping data for table `property_images`
--

INSERT INTO `property_images` (`id`, `property_id`, `image_path`, `is_primary`, `uploaded_at`) VALUES
(5, 1, 'uploads/properties/seed_room_01.jpg', 1, '2026-06-13 05:58:38'),
(6, 1, 'uploads/properties/seed_room_02.jpg', 0, '2026-06-13 05:58:38'),
(7, 2, 'uploads/properties/seed_room_03.jpg', 1, '2026-06-13 05:58:38'),
(8, 3, 'uploads/properties/seed_studio_01.jpg', 1, '2026-06-13 05:58:38'),
(9, 4, 'uploads/properties/seed_house_01.jpg', 1, '2026-06-13 05:58:38'),
(10, 4, 'uploads/properties/seed_house_02.jpg', 0, '2026-06-13 05:58:38'),
(11, 5, 'uploads/properties/seed_room_04.jpg', 1, '2026-06-13 05:58:38'),
(12, 6, 'uploads/properties/seed_room_05.jpg', 1, '2026-06-13 05:58:38'),
(13, 9, 'uploads/properties/seed_studio_02.jpg', 1, '2026-06-13 05:58:38'),
(14, 10, 'uploads/properties/seed_house_03.jpg', 1, '2026-06-13 05:58:38'),
(15, 11, 'uploads/properties/prop_6a2d180fce3e75.36212523.png', 1, '2026-06-13 08:42:55'),
(16, 11, 'uploads/properties/prop_6a2d180fceca91.70717973.png', 0, '2026-06-13 08:42:55'),
(17, 11, 'uploads/properties/prop_6a2d180fcf02a0.55995498.png', 0, '2026-06-13 08:42:55');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `preferred_name` varchar(50) NOT NULL DEFAULT '',
  `matric_no` varchar(20) NOT NULL,
  `university` varchar(80) NOT NULL DEFAULT 'UTeM',
  `phone` varchar(20) NOT NULL,
  `looking_for_housing` tinyint(1) NOT NULL DEFAULT 0,
  `housing_pref_city` varchar(80) DEFAULT NULL,
  `housing_pref_max_rent` decimal(8,2) DEFAULT NULL,
  `housing_pref_move_in` date DEFAULT NULL,
  `housing_bio` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `students`
--

TRUNCATE TABLE `students`;
--
-- Dumping data for table `students`
--

INSERT INTO `students` (`user_id`, `full_name`, `preferred_name`, `matric_no`, `university`, `phone`, `looking_for_housing`, `housing_pref_city`, `housing_pref_max_rent`, `housing_pref_move_in`, `housing_bio`) VALUES
(2, 'Wong Jia Xi', 'Jia Xi', 'B032310495', 'UTeM', '012-3456789', 0, NULL, NULL, NULL, NULL),
(3, 'Lim Mei Ling', 'Mei Ling', 'B032310123', 'UTeM', '012-9876543', 1, 'Ayer Keroh', 500.00, '2026-09-01', 'Quiet, study-focused, non-smoker. Looking for a clean place near campus.'),
(4, 'Ali Bin Abdullah', 'Ali', 'B032310234', 'UTeM', '013-1234567', 1, 'Durian Tunggal', 600.00, '2026-08-15', 'Easy-going engineering student. Like cooking on weekends.'),
(5, 'Ramesh Kumar', 'Ramesh', 'B032310345', 'UTeM', '011-2233445', 0, NULL, NULL, NULL, NULL),
(6, 'Siti Aishah', 'Aishah', 'B032310456', 'UTeM', '019-3344556', 0, NULL, NULL, NULL, NULL),
(7, 'Tan Wei Zhe', 'Wei Zhe', 'B032310567', 'UTeM', '012-4455667', 0, NULL, NULL, NULL, NULL),
(8, 'Farah Aliyah', 'Farah', 'B032310678', 'UTeM', '014-5566778', 0, NULL, NULL, NULL, NULL),
(9, 'Kelvin Lee', 'Kelvin', 'B032310789', 'UTeM', '012-6677889', 0, NULL, NULL, NULL, NULL),
(19, 'Mohd Azlan Bin Ismail', 'Azlan', 'B032310890', 'UTeM', '012-7890123', 0, NULL, NULL, NULL, NULL),
(20, 'Devi A/P Murugan', 'Devi', 'B032310901', 'UTeM', '012-8901234', 1, NULL, NULL, NULL, NULL),
(21, 'Mohd Farid Bin Hashim', 'Farid', 'B032310912', 'UTeM', '011-9012345', 0, NULL, NULL, NULL, NULL),
(22, 'Kavitha A/P Selvaraj', 'Kavitha', 'B032310923', 'UTeM', '019-0123456', 1, NULL, NULL, NULL, NULL),
(23, 'Mohd Syafiq Bin Adnan', 'Syafiq', 'B032310934', 'UTeM', '012-1234560', 0, NULL, NULL, NULL, NULL),
(24, 'Jasmine Tan', 'Jasmine', 'B032310945', 'UTeM', '013-2345601', 0, NULL, NULL, NULL, NULL),
(25, 'Mohd Hafiz Bin Yusoff', 'Hafiz', 'B032310956', 'UTeM', '014-3456012', 0, NULL, NULL, NULL, NULL),
(26, 'Amelia Wong', 'Amelia', 'B032310967', 'UTeM', '012-4560123', 1, NULL, NULL, NULL, NULL),
(27, 'Mohd Zafri Bin Karim', 'Zafri', 'B032310978', 'UTeM', '015-5601234', 0, NULL, NULL, NULL, NULL),
(28, 'Nadia Binti Razak', 'Nadia', 'B032310989', 'UTeM', '016-6012345', 1, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
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
-- Truncate table before insert `users`
--

TRUNCATE TABLE `users`;
--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `primary_role`, `status`, `last_used_role`, `created_at`, `updated_at`) VALUES
(1, 'admin@rentbridge.local', '$2y$10$BxRuOiygZVg/5uATlN9XNedi.WCt.AORx4b2I/TZTLA5pK7o.AIpq', 'admin', 'active', NULL, '2026-06-10 05:49:31', '2026-06-11 08:07:07'),
(2, 'jiaxi@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-01-15 02:00:00', '2026-06-13 05:58:38'),
(3, 'meiling@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-02-03 06:22:00', '2026-06-13 05:58:38'),
(4, 'alibaba@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-03-10 01:15:00', '2026-06-13 05:58:38'),
(5, 'ramesh@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-03-20 03:00:00', '2026-06-13 05:58:38'),
(6, 'siti@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-04-05 08:45:00', '2026-06-13 05:58:38'),
(7, 'weizhe@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-04-12 00:30:00', '2026-06-13 05:58:38'),
(8, 'farah@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'suspended', NULL, '2026-04-20 05:00:00', '2026-06-13 05:58:38'),
(9, 'kelvin@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-05-01 02:10:00', '2026-06-13 05:58:38'),
(10, 'ahmad@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', NULL, '2026-01-20 01:00:00', '2026-06-13 05:58:38'),
(11, 'wong@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', NULL, '2026-02-15 03:30:00', '2026-06-13 05:58:38'),
(12, 'priya@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', NULL, '2026-02-28 06:15:00', '2026-06-13 05:58:38'),
(13, 'chen@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', NULL, '2026-03-08 08:00:00', '2026-06-13 05:58:38'),
(14, 'raj@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', NULL, '2026-04-12 02:45:00', '2026-06-13 05:58:38'),
(15, 'inspector1@utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'agent', 'active', NULL, '2026-01-05 00:00:00', '2026-06-13 05:58:38'),
(16, 'inspector2@utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'agent', 'active', NULL, '2026-01-10 01:30:00', '2026-06-13 05:58:38'),
(17, 'inspector3@utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'agent', 'pending', NULL, '2026-05-15 06:00:00', '2026-06-13 05:58:38'),
(18, 'inspector4@utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'agent', 'active', NULL, '2026-02-01 02:00:00', '2026-06-13 05:58:38'),
(19, 'azlan@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-01-08 01:00:00', '2026-06-14 06:44:17'),
(20, 'devi@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-01-22 02:30:00', '2026-06-14 06:44:17'),
(21, 'farid@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-02-05 03:00:00', '2026-06-14 06:44:17'),
(22, 'kavitha@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-02-18 06:00:00', '2026-06-14 06:44:17'),
(23, 'syafiq@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-03-02 02:00:00', '2026-06-14 06:44:17'),
(24, 'jasmine@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-03-15 01:30:00', '2026-06-14 06:44:17'),
(25, 'hafiz@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-04-08 08:00:00', '2026-06-14 06:44:17'),
(26, 'amelia@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-04-25 05:00:00', '2026-06-14 06:44:17'),
(27, 'zafri@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-05-12 03:00:00', '2026-06-14 06:44:17'),
(28, 'nadia@student.utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'student', 'active', NULL, '2026-06-01 02:00:00', '2026-06-14 06:44:17'),
(29, 'fauziah@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', NULL, '2026-01-10 03:00:00', '2026-06-14 06:44:17'),
(30, 'tan@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', NULL, '2026-02-08 06:00:00', '2026-06-14 06:44:17'),
(31, 'ismail@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', NULL, '2026-03-12 02:00:00', '2026-06-14 06:44:17'),
(32, 'kumar@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', NULL, '2026-04-20 07:00:00', '2026-06-14 06:44:17'),
(33, 'lim@landlord.com', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'landlord', 'active', NULL, '2026-05-25 03:00:00', '2026-06-14 06:44:17'),
(34, 'inspector5@utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'agent', 'active', NULL, '2026-02-15 01:00:00', '2026-06-14 06:44:17'),
(35, 'inspector6@utem.edu.my', '$2y$10$i/gJoP6kqSaRPkSzU7Ud5OsauO7MddrtBsLDbC.COEfjThJ0hEI9u', 'agent', 'active', NULL, '2026-03-20 02:00:00', '2026-06-14 06:44:17');

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
  ADD KEY `fk_prop_verifier` (`agent_verified_by`);

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
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `co_tenancy_posts`
--
ALTER TABLE `co_tenancy_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `property_documents`
--
ALTER TABLE `property_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `property_images`
--
ALTER TABLE `property_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

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
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

CREATE TABLE IF NOT EXISTS saved_properties (
    id           INT NOT NULL AUTO_INCREMENT,
    user_id      INT NOT NULL,
    property_id  INT NOT NULL,
    saved_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_user_property (user_id, property_id),
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    INDEX idx_user  (user_id),
    INDEX idx_saved (saved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;