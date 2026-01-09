-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 14, 2025 at 12:12 PM
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
-- Database: `v2.0.1_dev`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_id` bigint(20) NOT NULL,
  `actor_type` varchar(56) NOT NULL,
  `type_id` bigint(20) NOT NULL,
  `type` varchar(56) NOT NULL,
  `parent_type_id` bigint(20) DEFAULT NULL,
  `parent_type` varchar(56) DEFAULT NULL,
  `activity` varchar(56) NOT NULL,
  `message` varchar(512) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `allowances`
--

CREATE TABLE `allowances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `amount` double NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `allowance_payslip`
--

CREATE TABLE `allowance_payslip` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `allowance_id` bigint(20) UNSIGNED NOT NULL,
  `payslip_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `position` varchar(255) NOT NULL,
  `source` varchar(255) NOT NULL,
  `status_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidate_notification`
--

CREATE TABLE `candidate_notification` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `candidate_id` bigint(20) UNSIGNED NOT NULL,
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_push` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidate_statuses`
--

CREATE TABLE `candidate_statuses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `color` varchar(255) NOT NULL DEFAULT 'primary',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ch_favorites`
--

CREATE TABLE `ch_favorites` (
  `id` char(36) NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `favorite_id` bigint(20) NOT NULL,
  `type_id` bigint(20) DEFAULT NULL,
  `type` varchar(28) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ch_messages`
--

CREATE TABLE `ch_messages` (
  `id` char(36) NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `from_id` bigint(20) NOT NULL,
  `to_id` bigint(20) NOT NULL,
  `type_id` bigint(20) DEFAULT NULL,
  `type` varchar(28) DEFAULT NULL,
  `body` varchar(5000) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `country_code` varchar(28) DEFAULT NULL,
  `country_iso_code` varchar(28) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `doj` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `zip` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `lang` varchar(28) NOT NULL DEFAULT 'en',
  `remember_token` text DEFAULT NULL,
  `email_verification_mail_sent` tinyint(4) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `acct_create_mail_sent` tinyint(4) NOT NULL DEFAULT 1,
  `internal_purpose` tinyint(4) NOT NULL DEFAULT 0,
  `default_workspace_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_meeting`
--

CREATE TABLE `client_meeting` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `meeting_id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_notifications`
--

CREATE TABLE `client_notifications` (
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `is_system` tinyint(1) DEFAULT 1,
  `is_push` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_project`
--

CREATE TABLE `client_project` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_workspace`
--

CREATE TABLE `client_workspace` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `commenter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `commenter_type` varchar(255) DEFAULT 'App\\Models\\User',
  `commentable_type` varchar(255) NOT NULL,
  `commentable_id` bigint(20) UNSIGNED NOT NULL,
  `content` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comment_attachments`
--

CREATE TABLE `comment_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `comment_id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `contract_type_id` bigint(20) UNSIGNED NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `promisor_sign` text DEFAULT NULL,
  `promisee_sign` text DEFAULT NULL,
  `signed_pdf` text DEFAULT NULL,
  `created_by` varchar(56) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contract_types`
--

CREATE TABLE `contract_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custom_fieldables`
--

CREATE TABLE `custom_fieldables` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `custom_field_id` bigint(20) UNSIGNED NOT NULL,
  `custom_fieldable_id` bigint(20) UNSIGNED NOT NULL,
  `custom_fieldable_type` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custom_fields`
--

CREATE TABLE `custom_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `module` varchar(255) NOT NULL,
  `field_label` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `field_type` varchar(255) NOT NULL,
  `guide_text` text DEFAULT NULL,
  `options` text DEFAULT NULL,
  `required` varchar(255) DEFAULT NULL,
  `visibility` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deductions`
--

CREATE TABLE `deductions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(512) NOT NULL DEFAULT 'amount',
  `percentage` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deduction_payslip`
--

CREATE TABLE `deduction_payslip` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `deduction_id` bigint(20) UNSIGNED NOT NULL,
  `payslip_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `placeholders` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`placeholders`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estimates_invoices`
--

CREATE TABLE `estimates_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `zip_code` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `note` longtext DEFAULT NULL,
  `personal_note` longtext DEFAULT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `total` double NOT NULL,
  `tax_amount` double DEFAULT NULL,
  `final_total` double NOT NULL,
  `created_by` varchar(56) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estimates_invoice_item`
--

CREATE TABLE `estimates_invoice_item` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL,
  `estimates_invoice_id` bigint(20) UNSIGNED NOT NULL,
  `qty` double NOT NULL,
  `unit_id` bigint(20) DEFAULT NULL,
  `rate` varchar(255) NOT NULL,
  `tax_id` bigint(20) DEFAULT NULL,
  `amount` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `expense_type_id` bigint(20) UNSIGNED NOT NULL,
  `amount` double NOT NULL,
  `note` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `created_by` varchar(56) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_types`
--

CREATE TABLE `expense_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(191) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `client_id` bigint(20) UNSIGNED DEFAULT NULL,
  `favoritable_type` varchar(255) NOT NULL,
  `favoritable_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fcm_tokens`
--

CREATE TABLE `fcm_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `client_id` bigint(20) UNSIGNED DEFAULT NULL,
  `fcm_token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interviews`
--

CREATE TABLE `interviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `candidate_id` bigint(20) UNSIGNED NOT NULL,
  `interviewer_id` bigint(20) UNSIGNED NOT NULL,
  `round` varchar(255) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `mode` enum('online','offline') NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `unit_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `price` double NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `languages`
--

CREATE TABLE `languages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `languages`
--

INSERT INTO `languages` (`id`, `name`, `code`, `created_at`, `updated_at`) VALUES
(4, 'English', 'en', '2023-06-29 09:37:47', '2023-06-29 09:37:47'),
(30, 'Hindi', 'hn', '2024-04-09 04:12:36', '2024-04-09 04:12:36'),
(31, 'Amharic', 'am', '2024-04-10 01:32:04', '2024-04-10 01:32:04'),
(32, 'Korean', 'ko', '2024-04-10 04:00:08', '2024-04-10 04:00:08'),
(33, 'Vietnamese', 'vn', '2024-04-10 04:45:26', '2024-04-10 04:45:26'),
(34, 'Portuguese', 'pt', '2024-04-10 23:11:05', '2024-04-10 23:11:05'),
(35, 'Español', 'es', '2024-04-11 05:03:18', '2024-04-11 05:03:18'),
(36, 'Français', 'fr', '2024-04-11 05:07:52', '2024-04-11 05:07:52'),
(37, 'Arabic', 'ar', '2024-04-11 05:37:27', '2024-04-11 05:37:27'),
(38, 'Dutch', 'nl', '2024-04-11 05:44:31', '2024-04-11 05:44:31'),
(39, 'Turkish', 'tr', '2024-04-11 05:52:41', '2024-04-11 05:52:41'),
(40, 'Indonesia', 'Ina', '2024-04-11 05:57:20', '2024-04-11 05:57:20'),
(41, 'Thai', 'TH', '2024-04-11 06:01:59', '2024-04-11 06:01:59'),
(42, 'Hrvatski', 'hr', '2024-04-11 06:06:25', '2024-04-11 06:06:25'),
(43, 'Italian', 'it', '2024-04-11 06:16:08', '2024-04-11 06:16:08');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lead_form_id` bigint(20) UNSIGNED DEFAULT NULL,
  `workspace_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(256) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) UNSIGNED DEFAULT NULL,
  `stage_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `company` varchar(255) NOT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `pinterest` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `zip` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `country_iso_code` char(2) DEFAULT NULL,
  `country_code` varchar(5) DEFAULT NULL,
  `is_converted` tinyint(1) NOT NULL DEFAULT 0,
  `converted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_follow_ups`
--

CREATE TABLE `lead_follow_ups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lead_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `follow_up_at` datetime NOT NULL,
  `type` enum('call','email','meeting','sms','other') DEFAULT NULL,
  `status` enum('pending','completed','rescheduled') NOT NULL DEFAULT 'pending',
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_forms`
--

CREATE TABLE `lead_forms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `source_id` bigint(20) UNSIGNED NOT NULL,
  `stage_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_to` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_form_fields`
--

CREATE TABLE `lead_form_fields` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `form_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_mapped` tinyint(1) NOT NULL DEFAULT 0,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `placeholder` varchar(255) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `validation_rules` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_sources`
--

CREATE TABLE `lead_sources` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lead_stages`
--

CREATE TABLE `lead_stages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `color` varchar(255) NOT NULL DEFAULT 'primary',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_editors`
--

CREATE TABLE `leave_editors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `from_time` time DEFAULT NULL,
  `to_time` time DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `visible_to_all` tinyint(4) NOT NULL DEFAULT 0,
  `action_by` bigint(20) UNSIGNED DEFAULT NULL,
  `comment` text DEFAULT NULL COMMENT 'For admin and leave editor',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_request_visibility`
--

CREATE TABLE `leave_request_visibility` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `leave_request_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `letters`
--

CREATE TABLE `letters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `template_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `letter_date` date NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `letter_templates`
--

CREATE TABLE `letter_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `letter_variables`
--

CREATE TABLE `letter_variables` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `label` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'text',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `media`
--

CREATE TABLE `media` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(36) DEFAULT NULL,
  `collection_name` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `disk` varchar(255) NOT NULL,
  `conversions_disk` varchar(255) DEFAULT NULL,
  `size` bigint(20) UNSIGNED NOT NULL,
  `manipulations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `custom_properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `generated_conversions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `responsive_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `order_column` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meetings`
--

CREATE TABLE `meetings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `start_date_time` timestamp NULL DEFAULT NULL,
  `end_date_time` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meeting_user`
--

CREATE TABLE `meeting_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `meeting_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_orders`
--

CREATE TABLE `menu_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `client_id` bigint(20) UNSIGNED DEFAULT NULL,
  `menu_order` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`menu_order`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_resets_table', 1),
(3, '2019_08_19_000000_create_failed_jobs_table', 1),
(4, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(5, '2023_01_05_044027_create_clients_table', 2),
(6, '2023_01_09_041709_create_projects_table', 3),
(7, '2023_01_12_095035_create_tasks_table', 4),
(8, '2023_01_25_061517_create_project_user_table', 5),
(9, '2023_01_27_044336_create_project_client_table', 6),
(10, '2023_01_30_050742_create_task_user_table', 7),
(11, '2023_01_30_060853_create_task_user_table', 8),
(12, '2023_03_02_051733_create_todo_table', 9),
(13, '2023_03_06_043616_create_status_table', 9),
(14, '2023_03_14_045106_create_permission_tables', 10),
(15, '2023_06_14_074411_create_settings_table', 11),
(16, '2023_06_15_074941_create_meetings_table', 12),
(19, '2023_06_15_073618_create_meeting_users_table', 13),
(21, '2023_06_15_074054_create_meeting_clients_table', 14),
(22, '2023_06_15_111007_create_meetings_table', 15),
(23, '2023_06_15_121717_create_meeting_user_table', 16),
(24, '2023_06_15_121752_create_client_meeting_table', 16),
(25, '2023_06_16_999999_add_active_status_to_users', 17),
(26, '2023_06_16_999999_add_avatar_to_users', 17),
(27, '2023_06_16_999999_add_dark_mode_to_users', 17),
(28, '2023_06_16_999999_add_messenger_color_to_users', 17),
(29, '2023_06_16_999999_create_chatify_favorites_table', 17),
(30, '2023_06_16_999999_create_chatify_messages_table', 17),
(34, '2023_06_16_132714_create_workspaces_table', 18),
(36, '2023_06_16_133749_create_workspace_client_table', 18),
(37, '2023_06_16_133505_create_workspace_user_table', 19),
(39, '2023_06_19_100748_alter_projects_table_add_column_workspace_id', 20),
(40, '2023_06_19_111816_alter_projects_table_add_column_created_by', 21),
(41, '2023_06_19_124749_alter_tasks_table_add_columns', 22),
(42, '2023_06_19_134537_alter_meeting_and_todo_tables_add_columns', 23),
(44, '2023_06_20_152434_alter_ch_messages_add_column_workspace_id', 24),
(45, '2023_06_24_162358_alter_ch_messages_add_column_workspace_id', 25),
(46, '2023_06_28_101921_alter_ch_favorites_table_add_column_workspace_id', 25),
(47, '2023_06_29_105758_create_languages_table', 26),
(48, '2023_08_22_124156_create_tags_table', 27),
(49, '2023_08_22_175355_create_project_tags_table', 28),
(50, '2023_08_22_180431_create_project_tag_table', 29),
(51, '2023_08_22_181203_create_project_tag_table', 30),
(52, '2023_08_24_050550_create_project_tag_table', 31),
(53, '2023_09_11_140028_create_todos_table', 32),
(54, '2023_09_11_085224_create_todos_table', 33),
(55, '2023_09_19_063727_create_leave_requests_table', 34),
(56, '2023_09_19_134017_create_leave_editors_table', 35),
(59, '2023_09_20_124013_create_contract_types_table', 36),
(60, '2023_09_20_123424_create_contracts_table', 37),
(61, '2023_09_25_084705_create_payment_methods_table', 38),
(62, '2023_09_25_070729_create_payslips_table', 39),
(63, '2023_09_25_094532_create_allowances_table', 40),
(64, '2023_09_25_094632_create_deductions_table', 40),
(65, '2023_09_27_124345_create_allowance_payslip_table', 41),
(66, '2023_09_27_124747_create_deduction_payslip_table', 41),
(67, '2023_09_29_131131_create_notes_table', 42),
(68, '2023_10_03_115025_create_updates_table', 43),
(69, '2023_10_03_115438_create_updates_table', 44),
(70, '2023_12_18_100056_create_time_trackers_table', 45),
(71, '2023_12_19_044413_create_time_trackers_table', 46),
(74, '2023_12_19_065522_create_time_trackers_table', 47),
(75, '2024_01_17_121328_create_media_table', 48),
(76, '2024_01_24_135513_create_activity_logs_table', 49),
(77, '2024_01_29_135126_create_taxes_table', 50),
(78, '2024_01_30_070848_create_units_table', 51),
(79, '2024_01_31_045129_create_items_table', 52),
(80, '2024_02_02_142029_create_estimates_invoices_table', 53),
(81, '2024_02_02_162343_create_estimates_invoice_item_table', 53),
(82, '2024_02_12_093738_create_expense_types_table', 54),
(83, '2024_02_12_094118_create_expenses_table', 54),
(84, '2024_02_16_043457_create_payments_table', 55),
(85, '2024_02_26_101512_create_milestones_table', 56),
(87, '2024_03_05_074505_create_notifications_table', 57),
(88, '2024_03_05_092120_create_client_notifications_table', 57),
(89, '2024_03_05_092139_create_notification_user_table', 57),
(91, '2024_03_11_120312_create_templates_table', 58),
(93, '2024_04_13_142557_create_priorities_table', 59),
(95, '2024_04_29_122911_create_role_status_table', 60),
(96, '2024_05_02_074714_create_leave_request_visibility_table', 61),
(98, '2024_05_17_054155_create_user_client_preferences_table', 62),
(100, '2024_10_09_050915_create_menu_orders_table', 63),
(101, '2024_10_21_132437_create_fcm_tokens_table', 64),
(103, '2024_08_09_100000_create_comments_table', 66),
(104, '2024_08_09_090054_create_comment_attachments_table', 67),
(105, '2024_11_28_055136_create_favorites_table', 68),
(106, '2024_11_30_044132_create_pinned_table', 69),
(107, '2025_01_24_042906_create_status_timelines_table', 70),
(108, '2025_01_25_055631_create_reminders_table', 71),
(109, '2025_01_16_085659_create_recuring_tasks', 72),
(110, '2025_02_11_053441_add_parent_id_to_tasks_table', 73),
(111, '2025_02_12_041617_add_time_entry_switch_to_projects_table', 74),
(112, '2025_02_12_060404_add_task_completion_percentage_to_tasks_table', 75),
(113, '2025_02_12_063159_create_task_lists_table', 76),
(114, '2025_02_12_064430_add_billing_type_to_tasks_table', 77),
(115, '2025_02_12_073313_create_tasks_time_entries', 78),
(116, '2025_03_22_142031_add_drawing_fields_to_notes_table', 79),
(119, '2025_04_18_112754_create_leads_table', 82),
(120, '2025_04_23_060440_create_lead_follow_ups_table', 83),
(123, '2025_04_26_060116_create_email_templates', 84),
(124, '2025_04_26_060254_create_scheduled_emails', 85),
(125, '2025_04_28_114140_create_custom_fields_table', 86),
(126, '2025_04_29_102352_add_is_converted_to_leads_table', 87),
(127, '2025_05_01_035845_create_candidate_statuses_table', 88),
(128, '2025_05_01_040103_create_interview_table', 89),
(129, '2025_05_01_055823_create_candidate_notification_table', 90),
(130, '2025_05_01_035955_create_candidate_table', 91),
(131, '2025_04_17_124915_create_lead_sources', 92),
(132, '2025_04_17_125219_create_lead_stages_table', 93),
(133, '2025_06_13_084602_create_lead_forms_table', 94),
(134, '2025_06_13_091415_create_lead_form_fields_table', 95),
(135, '2025_06_13_102308_add_custom_fields_to_leads_table', 96);

-- --------------------------------------------------------

--
-- Table structure for table `milestones`
--

CREATE TABLE `milestones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `cost` double NOT NULL,
  `progress` double NOT NULL DEFAULT 0,
  `description` longtext DEFAULT NULL,
  `created_by` varchar(56) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(191) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `note_type` varchar(255) NOT NULL DEFAULT 'text',
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `creator_id` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `drawing_data` longtext DEFAULT NULL,
  `color` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `from_id` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_user`
--

CREATE TABLE `notification_user` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `is_system` tinyint(1) DEFAULT 1,
  `is_push` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` double NOT NULL,
  `payment_date` date NOT NULL,
  `note` text DEFAULT NULL,
  `created_by` varchar(56) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payslips`
--

CREATE TABLE `payslips` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `month` varchar(128) NOT NULL,
  `working_days` double NOT NULL,
  `lop_days` double NOT NULL,
  `paid_days` double NOT NULL,
  `basic_salary` double NOT NULL,
  `leave_deduction` double NOT NULL,
  `ot_hours` double NOT NULL DEFAULT 0,
  `ot_rate` double NOT NULL DEFAULT 0,
  `ot_payment` double NOT NULL DEFAULT 0,
  `total_allowance` double NOT NULL DEFAULT 0,
  `incentives` double NOT NULL DEFAULT 0,
  `bonus` double NOT NULL DEFAULT 0,
  `total_earnings` double NOT NULL,
  `total_deductions` double NOT NULL DEFAULT 0,
  `net_pay` double NOT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `status` tinyint(4) NOT NULL,
  `note` text DEFAULT NULL,
  `created_by` varchar(56) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `guard_name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'edit_tasks', 'web', '2023-03-14 00:32:57', '2023-03-14 00:32:57'),
(3, 'delete_tasks', 'web', '2023-03-14 01:09:18', '2023-03-14 01:09:18'),
(4, 'edit_projects', 'web', '2023-03-14 03:22:52', '2023-03-14 03:22:52'),
(5, 'delete_projects', 'web', '2023-03-14 03:23:43', '2023-03-14 03:23:43'),
(6, 'edit_clients', 'web', '2023-03-14 03:23:53', '2023-03-14 03:23:53'),
(7, 'delete_clients', 'web', '2023-03-14 03:24:01', '2023-03-14 03:24:01'),
(8, 'create_projects', 'web', '2023-03-14 23:12:29', '2023-03-14 23:12:29'),
(9, 'create_tasks', 'web', '2023-03-14 23:12:34', '2023-03-14 23:12:34'),
(10, 'create_clients', 'web', '2023-03-14 23:12:38', '2023-03-14 23:12:38'),
(11, 'edit_users', 'web', '2023-03-17 00:51:15', '2023-03-17 00:51:15'),
(12, 'delete_users', 'web', '2023-03-17 00:57:41', '2023-03-17 00:57:41'),
(13, 'manage_users', 'web', '2023-03-27 00:04:49', '2023-03-27 00:04:49'),
(14, 'manage_projects', 'web', '2023-03-27 00:04:54', '2023-03-27 00:04:54'),
(15, 'manage_tasks', 'web', '2023-03-27 00:04:58', '2023-03-27 00:04:58'),
(16, 'manage_clients', 'web', '2023-03-27 00:05:02', '2023-03-27 00:05:02'),
(17, 'create_users', 'web', '2023-03-27 00:19:25', '2023-03-27 00:19:25'),
(22, 'create_workspaces', 'web', '2023-08-12 10:48:04', '2023-08-12 10:48:04'),
(23, 'manage_workspaces', 'web', '2023-08-12 10:48:04', '2023-08-12 10:48:04'),
(24, 'edit_workspaces', 'web', '2023-08-12 10:48:04', '2023-08-12 10:48:04'),
(25, 'delete_workspaces', 'web', '2023-08-12 10:48:04', '2023-08-12 10:48:04'),
(26, 'create_meetings', 'web', '2023-08-12 10:50:22', '2023-08-12 10:50:22'),
(27, 'manage_meetings', 'web', '2023-08-12 10:50:22', '2023-08-12 10:50:22'),
(28, 'edit_meetings', 'web', '2023-08-12 10:50:22', '2023-08-12 10:50:22'),
(29, 'delete_meetings', 'web', '2023-08-12 10:50:22', '2023-08-12 10:50:22'),
(37, 'delete_projects', 'client', '2023-09-08 11:57:55', '2023-09-08 11:57:55'),
(38, 'manage_projects', 'client', '2023-09-08 11:57:55', '2023-09-08 11:57:55'),
(39, 'create_tasks', 'client', '2023-09-08 12:32:02', '2023-09-08 12:32:02'),
(40, 'create_projects', 'client', '2023-09-08 12:33:36', '2023-09-08 12:33:36'),
(41, 'manage_tasks', 'client', '2023-09-08 12:33:48', '2023-09-08 12:33:48'),
(42, 'edit_tasks', 'client', '2023-09-08 12:33:59', '2023-09-08 12:33:59'),
(43, 'delete_tasks', 'client', '2023-09-08 12:34:13', '2023-09-08 12:34:13'),
(44, 'create_users', 'client', '2023-09-08 12:34:25', '2023-09-08 12:34:25'),
(45, 'manage_users', 'client', '2023-09-08 12:34:37', '2023-09-08 12:34:37'),
(46, 'edit_users', 'client', '2023-09-08 12:34:56', '2023-09-08 12:34:56'),
(47, 'delete_users', 'client', '2023-09-08 12:35:08', '2023-09-08 12:35:08'),
(48, 'create_clients', 'client', '2023-09-08 12:35:25', '2023-09-08 12:35:25'),
(49, 'manage_clients', 'client', '2023-09-08 12:35:37', '2023-09-08 12:35:37'),
(50, 'edit_clients', 'client', '2023-09-08 12:35:49', '2023-09-08 12:35:49'),
(51, 'delete_clients', 'client', '2023-09-08 12:36:01', '2023-09-08 12:36:01'),
(52, 'create_workspaces', 'client', '2023-09-08 12:36:17', '2023-09-08 12:36:17'),
(53, 'manage_workspaces', 'client', '2023-09-08 12:36:34', '2023-09-08 12:36:34'),
(54, 'edit_workspaces', 'client', '2023-09-08 12:36:45', '2023-09-08 12:36:45'),
(55, 'delete_workspaces', 'client', '2023-09-08 12:37:01', '2023-09-08 12:37:01'),
(56, 'create_meetings', 'client', '2023-09-08 12:37:16', '2023-09-08 12:37:16'),
(57, 'manage_meetings', 'client', '2023-09-08 12:37:29', '2023-09-08 12:37:29'),
(58, 'edit_meetings', 'client', '2023-09-08 12:37:42', '2023-09-08 12:37:42'),
(59, 'delete_meetings', 'client', '2023-09-08 12:37:55', '2023-09-08 12:37:55'),
(60, 'edit_projects', 'client', '2023-09-08 13:10:48', '2023-09-08 13:10:48'),
(61, 'create_contracts', 'client', '2023-09-23 23:32:01', '2023-09-23 23:32:01'),
(62, 'manage_contracts', 'client', '2023-09-23 23:32:44', '2023-09-23 23:32:44'),
(63, 'edit_contracts', 'client', '2023-09-23 23:32:56', '2023-09-23 23:32:56'),
(64, 'delete_contracts', 'client', '2023-09-23 23:33:04', '2023-09-23 23:33:04'),
(65, 'delete_contracts', 'web', '2023-09-23 23:33:16', '2023-09-23 23:33:16'),
(66, 'edit_contracts', 'web', '2023-09-23 23:33:26', '2023-09-23 23:33:26'),
(67, 'manage_contracts', 'web', '2023-09-23 23:33:37', '2023-09-23 23:33:37'),
(68, 'create_contracts', 'web', '2023-09-23 23:33:50', '2023-09-23 23:33:50'),
(69, 'create_timesheet', 'web', '2023-12-19 07:13:52', '2023-12-19 07:13:52'),
(70, 'delete_timesheet', 'web', '2023-12-19 07:13:55', '2023-12-19 07:13:55'),
(71, 'manage_timesheet', 'web', '2023-12-19 07:13:55', '2023-12-19 07:13:55'),
(72, 'create_timesheet', 'client', '2023-12-19 07:15:11', '2023-12-19 07:15:11'),
(73, 'delete_timesheet', 'client', '2023-12-19 07:15:11', '2023-12-19 07:15:11'),
(74, 'manage_timesheet', 'client', '2023-12-19 07:15:11', '2023-12-19 07:15:11'),
(75, 'create_payslips', 'client', '2023-12-22 05:46:34', '2023-12-22 05:46:34'),
(76, 'edit_payslips', 'client', '2023-12-22 05:46:35', '2023-12-22 05:46:35'),
(77, 'delete_payslips', 'client', '2023-12-22 05:46:35', '2023-12-22 05:46:35'),
(78, 'manage_payslips', 'client', '2023-12-22 05:46:35', '2023-12-22 05:46:35'),
(79, 'create_payslips', 'web', '2023-12-22 05:46:52', '2023-12-22 05:46:52'),
(80, 'edit_payslips', 'web', '2023-12-22 05:46:53', '2023-12-22 05:46:53'),
(81, 'delete_payslips', 'web', '2023-12-22 05:46:53', '2023-12-22 05:46:53'),
(82, 'manage_payslips', 'web', '2023-12-22 05:46:53', '2023-12-22 05:46:53'),
(83, 'access_all_data', 'web', '2024-01-08 07:22:04', '2024-01-08 13:47:12'),
(85, 'access_all_data', 'client', '2024-01-08 07:22:04', '2024-01-08 13:47:12'),
(86, 'manage_activity_log', 'web', '2024-01-15 13:46:23', '2024-01-15 13:46:23'),
(87, 'delete_activity_log', 'web', '2024-01-15 13:46:23', '2024-01-15 13:46:23'),
(88, 'manage_activity_log', 'client', '2024-01-15 13:46:23', '2024-01-15 13:46:23'),
(89, 'delete_activity_log', 'client', '2024-01-15 13:46:23', '2024-01-15 13:46:23'),
(90, 'create_estimates_invoices', 'web', '2024-02-07 06:15:12', '2024-02-07 06:15:12'),
(91, 'manage_estimates_invoices', 'web', '2024-02-07 06:15:12', '2024-02-07 06:15:12'),
(92, 'edit_estimates_invoices', 'web', '2024-02-07 06:15:12', '2024-02-07 06:15:12'),
(93, 'delete_estimates_invoices', 'web', '2024-02-07 06:15:13', '2024-02-07 06:15:13'),
(94, 'create_estimates_invoices', 'client', '2024-02-07 06:15:30', '2024-02-07 06:15:30'),
(95, 'manage_estimates_invoices', 'client', '2024-02-07 06:15:30', '2024-02-07 06:15:30'),
(96, 'edit_estimates_invoices', 'client', '2024-02-07 06:15:31', '2024-02-07 06:15:31'),
(97, 'delete_estimates_invoices', 'client', '2024-02-07 06:15:31', '2024-02-07 06:15:31'),
(98, 'create_expenses', 'client', '2024-02-13 01:06:26', '2024-02-13 01:06:26'),
(99, 'manage_expenses', 'client', '2024-02-13 01:06:27', '2024-02-13 01:06:27'),
(100, 'edit_expenses', 'client', '2024-02-13 01:06:27', '2024-02-13 01:06:27'),
(101, 'delete_expenses', 'client', '2024-02-13 01:06:27', '2024-02-13 01:06:27'),
(102, 'create_expenses', 'web', '2024-02-13 01:11:45', '2024-02-13 01:11:45'),
(103, 'manage_expenses', 'web', '2024-02-13 01:11:45', '2024-02-13 01:11:45'),
(104, 'edit_expenses', 'web', '2024-02-13 01:11:45', '2024-02-13 01:11:45'),
(105, 'delete_expenses', 'web', '2024-02-13 01:11:45', '2024-02-13 01:11:45'),
(106, 'create_milestones', 'web', '2024-02-26 04:28:09', '2024-02-26 04:28:09'),
(107, 'manage_milestones', 'web', '2024-02-26 04:28:09', '2024-02-26 04:28:09'),
(108, 'edit_milestones', 'web', '2024-02-26 04:28:09', '2024-02-26 04:28:09'),
(109, 'delete_milestones', 'web', '2024-02-26 04:28:09', '2024-02-26 04:28:09'),
(110, 'create_milestones', 'client', '2024-02-26 04:28:35', '2024-02-26 04:28:35'),
(111, 'manage_milestones', 'client', '2024-02-26 04:28:35', '2024-02-26 04:28:35'),
(112, 'edit_milestones', 'client', '2024-02-26 04:28:36', '2024-02-26 04:28:36'),
(113, 'delete_milestones', 'client', '2024-02-26 04:28:36', '2024-02-26 04:28:36'),
(114, 'manage_system_notifications', 'client', '2024-03-05 06:57:10', '2024-03-05 06:57:10'),
(115, 'delete_system_notifications', 'client', '2024-03-05 06:57:10', '2024-03-05 06:57:10'),
(116, 'manage_system_notifications', 'web', '2024-03-05 06:57:21', '2024-03-05 06:57:21'),
(117, 'delete_system_notifications', 'web', '2024-03-05 06:57:21', '2024-03-05 06:57:21'),
(126, 'create_statuses', 'client', '2024-08-21 08:48:24', '2024-08-21 08:48:24'),
(127, 'manage_statuses', 'client', '2024-08-21 08:48:24', '2024-08-21 08:48:24'),
(128, 'edit_statuses', 'client', '2024-08-21 08:48:24', '2024-08-21 08:48:24'),
(129, 'delete_statuses', 'client', '2024-08-21 08:48:24', '2024-08-21 08:48:24'),
(130, 'create_statuses', 'web', '2024-08-21 08:48:54', '2024-08-21 08:48:54'),
(131, 'manage_statuses', 'web', '2024-08-21 08:48:54', '2024-08-21 08:48:54'),
(132, 'edit_statuses', 'web', '2024-08-21 08:48:54', '2024-08-21 08:48:54'),
(133, 'delete_statuses', 'web', '2024-08-21 08:48:54', '2024-08-21 08:48:54'),
(134, 'create_priorities', 'web', '2024-08-22 05:39:31', '2024-08-22 05:39:31'),
(135, 'manage_priorities', 'web', '2024-08-22 05:39:31', '2024-08-22 05:39:31'),
(136, 'edit_priorities', 'web', '2024-08-22 05:39:31', '2024-08-22 05:39:31'),
(137, 'delete_priorities', 'web', '2024-08-22 05:39:31', '2024-08-22 05:39:31'),
(138, 'create_priorities', 'client', '2024-08-22 05:39:51', '2024-08-22 05:39:51'),
(139, 'manage_priorities', 'client', '2024-08-22 05:39:51', '2024-08-22 05:39:51'),
(140, 'edit_priorities', 'client', '2024-08-22 05:39:51', '2024-08-22 05:39:51'),
(141, 'delete_priorities', 'client', '2024-08-22 05:39:51', '2024-08-22 05:39:51'),
(142, 'create_tags', 'web', '2024-08-22 01:01:55', '2024-08-22 01:01:55'),
(143, 'manage_tags', 'web', '2024-08-22 01:01:55', '2024-08-22 01:01:55'),
(144, 'edit_tags', 'web', '2024-08-22 01:01:55', '2024-08-22 01:01:55'),
(145, 'delete_tags', 'web', '2024-08-22 01:01:55', '2024-08-22 01:01:55'),
(146, 'create_tags', 'client', '2024-08-22 01:02:18', '2024-08-22 01:02:18'),
(147, 'manage_tags', 'client', '2024-08-22 01:02:18', '2024-08-22 01:02:18'),
(148, 'edit_tags', 'client', '2024-08-22 01:02:18', '2024-08-22 01:02:18'),
(149, 'delete_tags', 'client', '2024-08-22 01:02:18', '2024-08-22 01:02:18'),
(150, 'create_media', 'web', '2024-08-22 23:59:40', '2024-08-22 23:59:40'),
(151, 'manage_media', 'web', '2024-08-22 23:59:40', '2024-08-22 23:59:40'),
(152, 'delete_media', 'web', '2024-08-22 23:59:40', '2024-08-22 23:59:40'),
(153, 'create_media', 'client', '2024-08-22 23:59:59', '2024-08-22 23:59:59'),
(154, 'manage_media', 'client', '2024-08-22 23:59:59', '2024-08-22 23:59:59'),
(155, 'delete_media', 'client', '2024-08-23 00:00:00', '2024-08-23 00:00:00'),
(156, 'create_contract_types', 'web', '2024-08-29 01:56:12', '2024-08-29 01:56:12'),
(157, 'manage_contract_types', 'web', '2024-08-29 01:56:12', '2024-08-29 01:56:12'),
(158, 'edit_contract_types', 'web', '2024-08-29 01:56:12', '2024-08-29 01:56:12'),
(159, 'delete_contract_types', 'web', '2024-08-29 01:56:12', '2024-08-29 01:56:12'),
(160, 'create_contract_types', 'client', '2024-08-29 01:56:29', '2024-08-29 01:56:29'),
(161, 'manage_contract_types', 'client', '2024-08-29 01:56:29', '2024-08-29 01:56:29'),
(162, 'edit_contract_types', 'client', '2024-08-29 01:56:29', '2024-08-29 01:56:29'),
(163, 'delete_contract_types', 'client', '2024-08-29 01:56:29', '2024-08-29 01:56:29'),
(164, 'create_allowances', 'web', '2024-08-29 03:31:00', '2024-08-29 03:31:00'),
(165, 'manage_allowances', 'web', '2024-08-29 03:31:00', '2024-08-29 03:31:00'),
(166, 'edit_allowances', 'web', '2024-08-29 03:31:00', '2024-08-29 03:31:00'),
(167, 'delete_allowances', 'web', '2024-08-29 03:31:00', '2024-08-29 03:31:00'),
(168, 'create_deductions', 'web', '2024-08-29 03:32:10', '2024-08-29 03:32:10'),
(169, 'manage_deductions', 'web', '2024-08-29 03:32:10', '2024-08-29 03:32:10'),
(170, 'edit_deductions', 'web', '2024-08-29 03:32:10', '2024-08-29 03:32:10'),
(171, 'delete_deductions', 'web', '2024-08-29 03:32:10', '2024-08-29 03:32:10'),
(172, 'create_payment_methods', 'web', '2024-08-29 03:32:10', '2024-08-29 03:32:10'),
(173, 'manage_payment_methods', 'web', '2024-08-29 03:32:10', '2024-08-29 03:32:10'),
(174, 'edit_payment_methods', 'web', '2024-08-29 03:32:10', '2024-08-29 03:32:10'),
(175, 'delete_payment_methods', 'web', '2024-08-29 03:32:10', '2024-08-29 03:32:10'),
(176, 'create_allowances', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(177, 'manage_allowances', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(178, 'edit_allowances', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(179, 'delete_allowances', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(180, 'create_deductions', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(181, 'manage_deductions', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(182, 'edit_deductions', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(183, 'delete_deductions', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(184, 'create_payment_methods', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(185, 'manage_payment_methods', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(186, 'edit_payment_methods', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(187, 'delete_payment_methods', 'client', '2024-08-29 03:33:01', '2024-08-29 03:33:01'),
(192, 'create_payments', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(193, 'manage_payments', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(194, 'edit_payments', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(195, 'delete_payments', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(196, 'create_taxes', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(197, 'manage_taxes', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(198, 'edit_taxes', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(199, 'delete_taxes', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(200, 'create_units', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(201, 'manage_units', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(202, 'edit_units', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(203, 'delete_units', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(204, 'create_items', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(205, 'manage_items', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(206, 'edit_items', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(207, 'delete_items', 'web', '2024-08-29 04:58:21', '2024-08-29 04:58:21'),
(212, 'create_payments', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(213, 'manage_payments', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(214, 'edit_payments', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(215, 'delete_payments', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(216, 'create_taxes', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(217, 'manage_taxes', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(218, 'edit_taxes', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(219, 'delete_taxes', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(220, 'create_units', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(221, 'manage_units', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(222, 'edit_units', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(223, 'delete_units', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(224, 'create_items', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(225, 'manage_items', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(226, 'edit_items', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(227, 'delete_items', 'client', '2024-08-29 04:58:43', '2024-08-29 04:58:43'),
(228, 'create_expense_types', 'client', '2024-08-29 05:25:21', '2024-08-29 05:25:21'),
(229, 'manage_expense_types', 'client', '2024-08-29 05:25:21', '2024-08-29 05:25:21'),
(230, 'edit_expense_types', 'client', '2024-08-29 05:25:21', '2024-08-29 05:25:21'),
(231, 'delete_expense_types', 'client', '2024-08-29 05:25:21', '2024-08-29 05:25:21'),
(232, 'create_expense_types', 'web', '2024-08-29 05:25:38', '2024-08-29 05:25:38'),
(233, 'manage_expense_types', 'web', '2024-08-29 05:25:38', '2024-08-29 05:25:38'),
(234, 'edit_expense_types', 'web', '2024-08-29 05:25:38', '2024-08-29 05:25:38'),
(235, 'delete_expense_types', 'web', '2024-08-29 05:25:38', '2024-08-29 05:25:38'),
(333, 'create_leads', 'web', '2025-04-26 04:14:30', '2025-04-26 04:14:30'),
(334, 'manage_leads', 'web', '2025-04-26 04:14:30', '2025-04-26 04:14:30'),
(335, 'edit_leads', 'web', '2025-04-26 04:14:30', '2025-04-26 04:14:30'),
(336, 'delete_leads', 'web', '2025-04-26 04:14:30', '2025-04-26 04:14:30'),
(337, 'send_email', 'web', '2025-05-05 05:28:46', '2025-05-05 05:28:46'),
(338, 'create_email_template', 'web', '2025-05-05 05:28:46', '2025-05-05 05:28:46'),
(339, 'manage_email_template', 'web', '2025-05-05 05:28:46', '2025-05-05 05:28:46'),
(340, 'delete_email_template', 'web', '2025-05-05 05:28:46', '2025-05-05 05:28:46'),
(341, 'manage_candidate', 'web', '2025-05-05 05:28:58', '2025-05-05 05:35:31'),
(342, 'create_candidate', 'web', '2025-05-05 05:28:58', '2025-05-05 05:28:58'),
(343, 'edit_candidate', 'web', '2025-05-05 05:28:58', '2025-05-05 05:28:58'),
(344, 'delete_candidate', 'web', '2025-05-05 05:28:58', '2025-05-05 05:28:58'),
(345, 'edit_candidate_status', 'web', '2025-05-05 05:28:58', '2025-05-05 05:36:41'),
(346, 'manage_candidate_status', 'web', '2025-05-05 05:28:58', '2025-05-05 05:28:58'),
(347, 'edit_interview', 'web', '2025-05-05 05:28:58', '2025-05-05 05:56:24'),
(348, 'create_interview', 'web', '2025-05-05 05:28:58', '2025-05-05 05:28:58'),
(349, 'manage_interview', 'web', '2025-05-05 05:28:58', '2025-05-05 05:56:03'),
(350, 'delete_interview', 'web', '2025-05-05 05:28:58', '2025-05-05 05:28:58'),
(351, 'create_candidate_status', 'web', '2025-05-05 05:28:58', '2025-05-05 05:28:58'),
(352, 'delete_candidate_status', 'web', '2025-05-05 05:28:58', '2025-05-05 05:28:58');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pinned`
--

CREATE TABLE `pinned` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `client_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pinnable_type` varchar(255) NOT NULL,
  `pinnable_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `priorities`
--

CREATE TABLE `priorities` (
  `id` bigint(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `color` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `status_id` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `priority_id` bigint(20) DEFAULT NULL,
  `budget` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `is_favorite` tinyint(4) NOT NULL DEFAULT 0,
  `task_accessibility` varchar(28) NOT NULL DEFAULT 'assigned_users',
  `note` longtext DEFAULT NULL,
  `client_can_discuss` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp(),
  `enable_tasks_time_entries` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_tag`
--

CREATE TABLE `project_tag` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `tag_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_user`
--

CREATE TABLE `project_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_tasks`
--

CREATE TABLE `recurring_tasks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `frequency` enum('daily','weekly','monthly','yearly') NOT NULL,
  `day_of_week` text DEFAULT NULL,
  `day_of_month` text DEFAULT NULL,
  `month_of_year` text DEFAULT NULL,
  `starts_from` date DEFAULT NULL,
  `number_of_occurrences` text DEFAULT NULL,
  `completed_occurrences` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_created_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `remindable_type` varchar(255) NOT NULL,
  `remindable_id` bigint(20) UNSIGNED NOT NULL,
  `frequency_type` enum('daily','weekly','monthly') NOT NULL,
  `frequency_value` int(11) DEFAULT NULL,
  `day_of_week` int(11) DEFAULT NULL,
  `day_of_month` int(11) DEFAULT NULL,
  `time_of_day` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `guard_name` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'web', '2023-03-13 23:28:49', '2023-09-24 06:53:41'),
(2, 'member', 'web', '2023-03-13 23:28:49', '2023-09-24 06:53:41'),
(3, 'Client', 'client', '2023-12-22 07:45:49', '2024-11-21 05:38:48');

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_status`
--

CREATE TABLE `role_status` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `status_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scheduled_emails`
--

CREATE TABLE `scheduled_emails` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `email_template_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `placeholders` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`placeholders`)),
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `scheduled_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `variable` varchar(255) DEFAULT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `variable`, `value`, `created_at`, `updated_at`) VALUES
(5, 'general_settings', '{\"company_title\":\"Taskify\",\"currency_full_form\":\"Indian Rupee\",\"currency_symbol\":\"\\u20b9\",\"currency_code\":\"INR\",\"timezone\":\"Asia\\/Kolkata\",\"date_format\":\"DD-MM-YYYY|d-m-Y\",\"footer_text\":\"<p>made with \\u2764\\ufe0f by&nbsp;<a href=\\\"https:\\/\\/www.infinitietech.com\\/\\\" target=\\\"_blank\\\" rel=\\\"noopener\\\">Infinitie Technologies<\\/a><\\/p>\",\"full_logo\":\"\",\"half_logo\":\"\",\"favicon\":\"\",\"site_url\":\"https:\\/\\/google.com\",\"currency_symbol_position\":\"before\",\"currency_formate\":\"comma_separated\",\"decimal_points_in_currency\":\"2\",\"time_format\":\"H:i:s\",\"toast_position\":\"toast-top-right\",\"toast_time_out\":\"5\",\"upcomingBirthdays\":1,\"upcomingWorkAnniversaries\":1,\"membersOnLeave\":1,\"priLangAsAuth\":1,\"currency_format\":null,\"decimal_points\":\"2\",\"allowed_file_types\":\".png,.jpg,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.txt\",\"allowSignup\":1,\"max_attempts\":\"5\",\"lock_time\":\"1\",\"allowed_max_upload_size\":\"512\",\"max_files\":\"10\",\"recaptcha_enabled\":0,\"recaptcha_site_key\":\"\",\"recaptcha_secret_key\":\"\"}', '2023-06-14 02:18:25', '2025-08-13 09:58:32'),
(9, 'pusher_settings', NULL, '2023-06-21 00:03:13', '2023-10-08 19:39:20'),
(10, 'email_settings', NULL, '2023-06-21 03:13:07', '2025-08-14 08:00:03'),
(11, 'media_storage_settings', NULL, '2024-01-22 01:33:48', '2024-02-11 19:37:32'),
(12, 'sms_gateway_settings', NULL, '2024-03-28 19:51:39', '2024-04-01 23:15:13'),
(13, 'privacy_policy', '{\"privacy_policy\":\"<h1 data-start=\\\"144\\\" data-end=\\\"162\\\">Privacy Policy<\\/h1>\\r\\n<p data-start=\\\"163\\\" data-end=\\\"194\\\"><strong data-start=\\\"163\\\" data-end=\\\"194\\\">Last updated: July 10, 2025<\\/strong><\\/p>\\r\\n<hr data-start=\\\"471\\\" data-end=\\\"474\\\">\\r\\n<h3 data-start=\\\"476\\\" data-end=\\\"502\\\">1. <strong data-start=\\\"483\\\" data-end=\\\"502\\\">Data We Collect<\\/strong><\\/h3>\\r\\n<p data-start=\\\"504\\\" data-end=\\\"542\\\"><strong data-start=\\\"504\\\" data-end=\\\"521\\\">Personal Data<\\/strong> (provided by you):<\\/p>\\r\\n<ul data-start=\\\"543\\\" data-end=\\\"592\\\">\\r\\n<li data-start=\\\"543\\\" data-end=\\\"574\\\">\\r\\n<p data-start=\\\"545\\\" data-end=\\\"574\\\">Name, Email, Phone, Address<\\/p>\\r\\n<\\/li>\\r\\n<li data-start=\\\"575\\\" data-end=\\\"592\\\">\\r\\n<p data-start=\\\"577\\\" data-end=\\\"592\\\">Login details<\\/p>\\r\\n<\\/li>\\r\\n<\\/ul>\\r\\n<p data-start=\\\"594\\\" data-end=\\\"637\\\"><strong data-start=\\\"594\\\" data-end=\\\"608\\\">Usage Data<\\/strong> (collected automatically):<\\/p>\\r\\n<ul data-start=\\\"638\\\" data-end=\\\"732\\\">\\r\\n<li data-start=\\\"638\\\" data-end=\\\"693\\\">\\r\\n<p data-start=\\\"640\\\" data-end=\\\"693\\\">IP address, browser type, pages visited, time spent<\\/p>\\r\\n<\\/li>\\r\\n<li data-start=\\\"694\\\" data-end=\\\"732\\\">\\r\\n<p data-start=\\\"696\\\" data-end=\\\"732\\\">Mobile device info (if applicable)<\\/p>\\r\\n<\\/li>\\r\\n<\\/ul>\\r\\n<hr data-start=\\\"907\\\" data-end=\\\"910\\\">\\r\\n<h3 data-start=\\\"912\\\" data-end=\\\"934\\\">2. <strong data-start=\\\"919\\\" data-end=\\\"934\\\">Use of Data<\\/strong><\\/h3>\\r\\n<p data-start=\\\"936\\\" data-end=\\\"956\\\">We use your data to:<\\/p>\\r\\n<ul data-start=\\\"957\\\" data-end=\\\"1150\\\">\\r\\n<li data-start=\\\"957\\\" data-end=\\\"994\\\">\\r\\n<p data-start=\\\"959\\\" data-end=\\\"994\\\">Provide and maintain our services<\\/p>\\r\\n<\\/li>\\r\\n<li data-start=\\\"995\\\" data-end=\\\"1018\\\">\\r\\n<p data-start=\\\"997\\\" data-end=\\\"1018\\\">Manage your account<\\/p>\\r\\n<\\/li>\\r\\n<li data-start=\\\"1019\\\" data-end=\\\"1079\\\">\\r\\n<p data-start=\\\"1021\\\" data-end=\\\"1079\\\">Contact you with updates or promotions (you can opt out)<\\/p>\\r\\n<\\/li>\\r\\n<li data-start=\\\"1080\\\" data-end=\\\"1116\\\">\\r\\n<p data-start=\\\"1082\\\" data-end=\\\"1116\\\">Improve performance and security<\\/p>\\r\\n<\\/li>\\r\\n<li data-start=\\\"1117\\\" data-end=\\\"1150\\\">\\r\\n<p data-start=\\\"1119\\\" data-end=\\\"1150\\\">Comply with legal obligations<\\/p>\\r\\n<\\/li>\\r\\n<\\/ul>\\r\\n<hr data-start=\\\"1819\\\" data-end=\\\"1822\\\">\\r\\n<h3 data-start=\\\"1824\\\" data-end=\\\"1846\\\">3. <strong data-start=\\\"1831\\\" data-end=\\\"1846\\\">Your Rights<\\/strong><\\/h3>\\r\\n<p data-start=\\\"1848\\\" data-end=\\\"1856\\\">You can:<\\/p>\\r\\n<ul data-start=\\\"1857\\\" data-end=\\\"1976\\\">\\r\\n<li data-start=\\\"1857\\\" data-end=\\\"1896\\\">\\r\\n<p data-start=\\\"1859\\\" data-end=\\\"1896\\\">Access, update, or delete your data<\\/p>\\r\\n<\\/li>\\r\\n<li data-start=\\\"1897\\\" data-end=\\\"1936\\\">\\r\\n<p data-start=\\\"1899\\\" data-end=\\\"1936\\\">Withdraw consent (where applicable)<\\/p>\\r\\n<\\/li>\\r\\n<\\/ul>\\r\\n<hr data-start=\\\"2263\\\" data-end=\\\"2266\\\">\\r\\n<h3 data-start=\\\"2268\\\" data-end=\\\"2293\\\">4. <strong data-start=\\\"2275\\\" data-end=\\\"2293\\\">Policy Updates<\\/strong><\\/h3>\\r\\n<p data-start=\\\"2295\\\" data-end=\\\"2408\\\">We may update this policy occasionally. Changes take effect once posted. We\'ll notify you of significant updates.<\\/p>\\r\\n<hr data-start=\\\"2410\\\" data-end=\\\"2413\\\">\\r\\n<h3 data-start=\\\"2415\\\" data-end=\\\"2437\\\">5.&nbsp;<strong data-start=\\\"2423\\\" data-end=\\\"2437\\\">Contact Us<\\/strong><\\/h3>\\r\\n<ul data-start=\\\"2439\\\" data-end=\\\"2507\\\">\\r\\n<li data-start=\\\"2439\\\" data-end=\\\"2476\\\">\\r\\n<p data-start=\\\"2441\\\" data-end=\\\"2476\\\"><strong data-start=\\\"2441\\\" data-end=\\\"2451\\\">Email:<\\/strong> <a class=\\\"cursor-pointer\\\" rel=\\\"noopener\\\" data-start=\\\"2452\\\" data-end=\\\"2474\\\">info@infinitietech.com<\\/a><\\/p>\\r\\n<\\/li>\\r\\n<li data-start=\\\"2477\\\" data-end=\\\"2507\\\">\\r\\n<p data-start=\\\"2479\\\" data-end=\\\"2507\\\"><strong data-start=\\\"2479\\\" data-end=\\\"2489\\\">Phone:<\\/strong> +91 99746 92496<\\/p>\\r\\n<\\/li>\\r\\n<\\/ul>\"}', '2024-10-14 12:02:09', '2025-07-10 06:43:17'),
(14, 'about_us', NULL, '2024-10-14 12:02:09', '2025-07-07 05:16:14'),
(15, 'terms_conditions', NULL, '2024-10-14 12:02:09', '2025-07-07 05:18:30'),
(16, 'slack_settings', NULL, '2025-01-30 01:01:27', '2025-04-28 11:16:16'),
(44, 'google_calendar_settings', NULL, '2025-04-08 09:19:05', '2025-06-10 10:34:49'),
(45, 'company_information', '{\"dnr\":null,\"companyEmail\":\"infinitie@gmail.com\",\"companyPhone\":\"1234567891\",\"companyAddress\":\"bhuj\",\"companyCity\":null,\"companyState\":\"gujrat\",\"companyCountry\":\"India\",\"companyZip\":\"370001\",\"companyWebsite\":null,\"companyVatNumber\":\"56987789\",\"companyVATNumber\":\"56987789\"}', '2025-04-12 11:36:46', '2025-06-10 11:43:40'),
(46, 'whatsapp_settings', NULL, '2025-04-16 04:49:29', '2025-04-16 04:49:29'),
(47, 'ai_model_settings', NULL, '2025-05-12 07:11:19', '2025-07-28 07:24:04'),
(48, 'pwa_settings', '{\"name\":\"Taskify\",\"short_name\":\"Taskify\",\"theme_color\":\"#80a9ac\",\"background_color\":\"#fff0f3\",\"logo\":\"\\/storage\\/images\\/icons\\/logo-512x512.png\",\"description\":\"A task management app to boost productivity\"}', '2025-06-05 11:39:43', '2025-06-25 11:51:19');

-- --------------------------------------------------------

--
-- Table structure for table `statuses`
--

CREATE TABLE `statuses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `color` varchar(255) NOT NULL,
  `is_default` text NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `status_timelines`
--

CREATE TABLE `status_timelines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `entity_type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `previous_status` varchar(255) DEFAULT NULL,
  `new_color` varchar(255) DEFAULT NULL,
  `old_color` varchar(255) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `color` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`id`, `title`, `slug`, `color`, `created_at`, `updated_at`) VALUES
(3, 'wadad', 'wadad', 'primary', '2025-07-09 06:36:02', '2025-07-09 06:36:02');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `billing_type` enum('none','billable','non-billable') NOT NULL DEFAULT 'none',
  `status_id` bigint(20) NOT NULL DEFAULT 1,
  `priority_id` bigint(20) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `note` longtext DEFAULT NULL,
  `client_can_discuss` tinyint(1) DEFAULT 0,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp(),
  `completion_percentage` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Completion percentage (0 to 100)',
  `task_list_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_lists`
--

CREATE TABLE `task_lists` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_time_entries`
--

CREATE TABLE `task_time_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` text NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `entry_date` date NOT NULL,
  `is_billable` tinyint(1) NOT NULL DEFAULT 0,
  `entry_type` enum('standard','flexible') NOT NULL,
  `standard_hours` time DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_user`
--

CREATE TABLE `task_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `taxes`
--

CREATE TABLE `taxes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(512) NOT NULL,
  `percentage` double DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `templates`
--

CREATE TABLE `templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `status` tinyint(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `templates`
--

INSERT INTO `templates` (`id`, `type`, `name`, `subject`, `content`, `status`, `created_at`, `updated_at`) VALUES
(1, 'email', 'account_creation', NULL, '<div style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; background-color: #ffffff; color: #718096; height: 100%; line-height: 1.4; margin: 0; padding: 0; width: 100%!important;\">\n<table style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; background-color: #edf2f7; margin: 0; padding: 0; width: 100%;\" role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\">\n<tbody>\n<tr>\n<td style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';\" align=\"center\">\n<table style=\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\'; margin: 0px; padding: 0px; width: 100%; height: 659.844px;\" role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\">\n<tbody>\n<tr style=\"height: 77.0938px;\">\n<td style=\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\'; padding: 25px 0px; height: 77.0938px; text-align: center;\"><span style=\"font-size: 18px;\">{COMPANY_LOGO}</span></td>\n</tr>\n<tr style=\"height: 486.25px;\">\n<td style=\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\'; background-color: rgb(237, 242, 247); margin: 0px; padding: 0px; width: 100%; border: hidden !important; height: 486.25px;\" width=\"100%\">\n<table class=\"m_2051327272198114809inner-body\" style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; background-color: #ffffff; border-color: #e8e5ef; border-radius: 2px; border-width: 1px; margin: 0 auto; padding: 0; width: 570px;\" role=\"presentation\" width=\"570\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\">\n<tbody>\n<tr>\n<td style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; max-width: 100vw; padding: 32px;\">\n<h1 style=\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\'; color: rgb(61, 72, 82); font-size: 18px; font-weight: bold; margin-top: 0px; text-align: left;\">Hello, {FIRST_NAME} {LAST_NAME}</h1>\n<p style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;\">Your account has been successfully created.</p>\n<p style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;\"><strong>Username:</strong> {USER_NAME}<br><strong>Password:</strong> {PASSWORD}</p>\n<p style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;\">If you did not create an account, no further action is required.</p>\n<p style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; font-size: 16px; line-height: 1.5em; margin-top: 0; text-align: left;\">To access your account, simply click on the link below or copy and paste it into your browser:</p>\n{SITE_URL}<br><br>Regards,</td>\n</tr>\n<tr>\n<td style=\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\'; max-width: 100vw; padding: 32px;\">\n<h1 style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; color: #3d4852; font-size: 18px; font-weight: bold; margin-top: 0; text-align: left;\">&nbsp;</h1>\n</td>\n</tr>\n</tbody>\n</table>\n</td>\n</tr>\n<tr style=\"height: 96.5px;\">\n<td style=\"box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\'; height: 96.5px;\">\n<table class=\"m_2051327272198114809footer\" style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; margin: 0 auto; padding: 0; text-align: center; width: 570px;\" role=\"presentation\" width=\"570\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\">\n<tbody>\n<tr>\n<td style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; max-width: 100vw; padding: 32px;\" align=\"center\">\n<p style=\"box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; line-height: 1.5em; margin-top: 0; color: #b0adc5; font-size: 12px; text-align: center;\">&copy; {CURRENT_YEAR} {COMPANY_TITLE}. All rights reserved.</p>\n</td>\n</tr>\n</tbody>\n</table>\n</td>\n</tr>\n</tbody>\n</table>\n</td>\n</tr>\n</tbody>\n</table>\n<div class=\"yj6qo\">&nbsp;</div>\n<div class=\"adL\">&nbsp;</div>\n</div>', 0, '2025-04-08 12:13:13', '2025-04-26 07:37:04'),
(17, 'email', 'interview_assignment', 'test email', 'testttttttttt', 1, '2025-05-20 05:36:58', '2025-05-20 05:36:58'),
(18, 'sms', 'interview_assignment', NULL, 'TEST', 1, '2025-05-21 09:24:06', '2025-05-21 09:24:06'),
(19, 'sms', 'interview_status_update', NULL, 'Deep SEARCH TEST UPDATE STATUS.', 1, '2025-05-21 09:24:53', '2025-05-21 09:24:53'),
(20, 'push', 'project_assignment', 'testing', '{ASSIGNEE_FIRST_NAME} {ASSIGNEE_LAST_NAME} assigned you new project: {PROJECT_TITLE}, ID:#{PROJECT_ID}.', 1, '2025-06-05 05:33:46', '2025-06-05 05:33:51'),
(21, 'push', 'project_status_updation', 'testing', '{UPDATER_FIRST_NAME} {UPDATER_LAST_NAME} has updated the status of project {PROJECT_TITLE}, ID:#{PROJECT_ID}, from {OLD_STATUS} to {NEW_STATUS}.', 1, '2025-06-05 05:34:04', '2025-06-05 05:34:04');

-- --------------------------------------------------------

--
-- Table structure for table `time_trackers`
--

CREATE TABLE `time_trackers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `start_date_time` datetime NOT NULL,
  `end_date_time` datetime DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `todos`
--

CREATE TABLE `todos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` varchar(255) NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `creator_id` bigint(20) UNSIGNED NOT NULL,
  `creator_type` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `updates`
--

CREATE TABLE `updates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `version` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `updates`
--

INSERT INTO `updates` (`id`, `version`, `created_at`, `updated_at`) VALUES
(1, '1.0.0', '2023-10-03 06:26:04', '2023-10-03 06:26:04'),
(2, '1.0.1', '2023-10-30 05:35:10', '2023-10-30 05:35:10'),
(3, '1.0.2', '2024-01-27 04:21:48', '2024-01-27 04:21:53'),
(4, '1.0.3', '2024-01-27 04:22:09', '2024-01-27 04:22:16'),
(5, '1.0.4', '2024-03-01 12:53:15', '2024-03-01 12:53:15'),
(6, '1.0.5', '2024-04-09 05:25:03', '2024-04-09 05:25:03'),
(7, '1.0.6', '2024-07-08 07:50:53', '2024-07-08 07:50:53'),
(8, '1.0.7', '2024-07-08 07:50:53', '2024-07-08 07:50:53'),
(9, '1.0.8', '2024-07-08 07:51:38', '2024-07-08 07:51:38'),
(10, '1.0.9', '2024-07-11 12:39:04', '2024-07-11 12:39:04'),
(11, '1.0.10', '2025-01-31 03:58:54', '2025-01-31 03:58:54'),
(12, '1.1.0', '2025-01-31 03:59:07', '2025-01-31 03:59:07'),
(13, '1.1.1', '2025-01-31 04:02:09', '2025-01-31 04:02:09'),
(14, '1.1.2', '2025-01-31 04:40:45', '2025-01-31 04:40:45'),
(15, '1.1.3', '2025-01-31 04:41:20', '2025-01-31 04:41:20'),
(16, '1.2.0', '2025-03-28 04:41:20', '2025-03-28 04:41:20'),
(17, '1.2.1', '2025-05-06 06:29:39', '2025-05-06 06:29:39'),
(19, '1.2.2', '2025-06-30 11:02:28', '2025-06-30 11:02:28'),
(20, '2.0.0', '2025-07-15 06:39:34', '2025-07-15 06:39:34'),
(21, '2.0.1', '2025-08-14 06:39:34', '2025-08-14 06:39:34');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `country_code` varchar(28) DEFAULT NULL,
  `country_iso_code` varchar(28) DEFAULT NULL,
  `phone` varchar(56) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `zip` varchar(56) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `dob` date DEFAULT NULL,
  `doj` date DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) NOT NULL DEFAULT 'avatar.png',
  `active_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'For chatify messenger',
  `dark_mode` tinyint(1) NOT NULL DEFAULT 0,
  `messenger_color` varchar(255) DEFAULT NULL,
  `lang` varchar(28) NOT NULL DEFAULT 'en',
  `remember_token` text DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `default_workspace_id` bigint(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_client_preferences`
--

CREATE TABLE `user_client_preferences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` varchar(56) NOT NULL,
  `table_name` varchar(255) NOT NULL,
  `visible_columns` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `enabled_notifications` longtext DEFAULT NULL,
  `default_view` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_workspace`
--

CREATE TABLE `user_workspace` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `workspace_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workspaces`
--

CREATE TABLE `workspaces` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `is_primary` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_logs_workspace_id_index` (`workspace_id`);

--
-- Indexes for table `allowances`
--
ALTER TABLE `allowances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `allowances_workspace_id_foreign` (`workspace_id`);

--
-- Indexes for table `allowance_payslip`
--
ALTER TABLE `allowance_payslip`
  ADD PRIMARY KEY (`id`),
  ADD KEY `allowance_payslip_allowance_id_foreign` (`allowance_id`),
  ADD KEY `allowance_payslip_payslip_id_foreign` (`payslip_id`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidates_status_id_foreign` (`status_id`);

--
-- Indexes for table `candidate_notification`
--
ALTER TABLE `candidate_notification`
  ADD PRIMARY KEY (`id`),
  ADD KEY `candidate_notification_candidate_id_foreign` (`candidate_id`),
  ADD KEY `candidate_notification_notification_id_foreign` (`notification_id`);

--
-- Indexes for table `candidate_statuses`
--
ALTER TABLE `candidate_statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ch_favorites`
--
ALTER TABLE `ch_favorites`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ch_messages`
--
ALTER TABLE `ch_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clients_email_unique` (`email`);

--
-- Indexes for table `client_meeting`
--
ALTER TABLE `client_meeting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_meeting_meeting_id_client_id_unique` (`meeting_id`,`client_id`),
  ADD KEY `client_meeting_client_id_foreign` (`client_id`);

--
-- Indexes for table `client_notifications`
--
ALTER TABLE `client_notifications`
  ADD PRIMARY KEY (`client_id`,`notification_id`),
  ADD KEY `client_notifications_notification_id_foreign` (`notification_id`);

--
-- Indexes for table `client_project`
--
ALTER TABLE `client_project`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_client_project_id_client_id_unique` (`project_id`,`client_id`),
  ADD KEY `project_client_client_id_foreign` (`client_id`);

--
-- Indexes for table `client_workspace`
--
ALTER TABLE `client_workspace`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_workspace_workspace_id_client_id_unique` (`workspace_id`,`client_id`),
  ADD KEY `client_workspace_client_id_foreign` (`client_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comments_commentable_type_commentable_id_index` (`commentable_type`,`commentable_id`),
  ADD KEY `comments_parent_id_index` (`parent_id`),
  ADD KEY `comments_user_id_index` (`commenter_id`);

--
-- Indexes for table `comment_attachments`
--
ALTER TABLE `comment_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comment_attachments_comment_id_foreign` (`comment_id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contracts_workspace_id_foreign` (`workspace_id`),
  ADD KEY `contracts_project_id_foreign` (`project_id`),
  ADD KEY `contracts_client_id_foreign` (`client_id`),
  ADD KEY `contracts_contract_type_id_foreign` (`contract_type_id`);

--
-- Indexes for table `contract_types`
--
ALTER TABLE `contract_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_types_workspace_id_foreign` (`workspace_id`);

--
-- Indexes for table `custom_fieldables`
--
ALTER TABLE `custom_fieldables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `custom_fieldables_custom_field_id_foreign` (`custom_field_id`);

--
-- Indexes for table `custom_fields`
--
ALTER TABLE `custom_fields`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deductions`
--
ALTER TABLE `deductions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deduction_payslip`
--
ALTER TABLE `deduction_payslip`
  ADD PRIMARY KEY (`id`),
  ADD KEY `deduction_payslip_deduction_id_foreign` (`deduction_id`),
  ADD KEY `deduction_payslip_payslip_id_foreign` (`payslip_id`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email_templates_workspace_id_foreign` (`workspace_id`);

--
-- Indexes for table `estimates_invoices`
--
ALTER TABLE `estimates_invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `estimates_invoices_client_id_foreign` (`client_id`),
  ADD KEY `estimates_invoices_workspace_id_foreign` (`workspace_id`);

--
-- Indexes for table `estimates_invoice_item`
--
ALTER TABLE `estimates_invoice_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `estimates_invoices_items_item_id_foreign` (`item_id`),
  ADD KEY `estimates_invoices_items_estimate_invoice_id_foreign` (`estimates_invoice_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expenses_workspace_id_foreign` (`workspace_id`),
  ADD KEY `expenses_user_id_foreign` (`user_id`),
  ADD KEY `expenses_expense_type_id_foreign` (`expense_type_id`);

--
-- Indexes for table `expense_types`
--
ALTER TABLE `expense_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expense_types_workspace_id_foreign` (`workspace_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `favorites_favoritable_type_favoritable_id_index` (`favoritable_type`,`favoritable_id`),
  ADD KEY `favorites_user_id_foreign` (`user_id`),
  ADD KEY `favorites_client_id_foreign` (`client_id`);

--
-- Indexes for table `fcm_tokens`
--
ALTER TABLE `fcm_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fcm_tokens_user_id_foreign` (`user_id`),
  ADD KEY `fcm_tokens_client_id_foreign` (`client_id`);

--
-- Indexes for table `interviews`
--
ALTER TABLE `interviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `interviews_candidate_id_foreign` (`candidate_id`),
  ADD KEY `interviews_interviewer_id_foreign` (`interviewer_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leads_workspace_id_foreign` (`workspace_id`),
  ADD KEY `leads_assigned_to_foreign` (`assigned_to`),
  ADD KEY `leads_created_by_foreign` (`created_by`),
  ADD KEY `leads_source_id_foreign` (`source_id`),
  ADD KEY `leads_stage_id_foreign` (`stage_id`);

--
-- Indexes for table `lead_follow_ups`
--
ALTER TABLE `lead_follow_ups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_follow_ups_lead_id_foreign` (`lead_id`),
  ADD KEY `lead_follow_ups_assigned_to_foreign` (`assigned_to`);

--
-- Indexes for table `lead_forms`
--
ALTER TABLE `lead_forms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lead_forms_slug_unique` (`slug`),
  ADD KEY `lead_forms_created_by_foreign` (`created_by`),
  ADD KEY `lead_forms_workspace_id_foreign` (`workspace_id`),
  ADD KEY `lead_forms_source_id_foreign` (`source_id`),
  ADD KEY `lead_forms_stage_id_foreign` (`stage_id`),
  ADD KEY `lead_forms_assigned_to_foreign` (`assigned_to`);

--
-- Indexes for table `lead_form_fields`
--
ALTER TABLE `lead_form_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lead_form_fields_form_id_foreign` (`form_id`);

--
-- Indexes for table `lead_sources`
--
ALTER TABLE `lead_sources`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lead_stages`
--
ALTER TABLE `lead_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lead_stages_slug_unique` (`slug`);

--
-- Indexes for table `leave_editors`
--
ALTER TABLE `leave_editors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_editors_user_id_foreign` (`user_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_requests_user_id_foreign` (`user_id`),
  ADD KEY `leave_requests_workspace_id_foreign` (`workspace_id`);

--
-- Indexes for table `leave_request_visibility`
--
ALTER TABLE `leave_request_visibility`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_request_visibility_leave_request_id_foreign` (`leave_request_id`),
  ADD KEY `leave_request_visibility_user_id_foreign` (`user_id`);

--
-- Indexes for table `letters`
--
ALTER TABLE `letters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `letters_user_id_foreign` (`user_id`),
  ADD KEY `letters_template_id_foreign` (`template_id`),
  ADD KEY `letters_created_by_foreign` (`created_by`),
  ADD KEY `letters_workspace_id_user_id_index` (`workspace_id`,`user_id`),
  ADD KEY `letters_workspace_id_template_id_index` (`workspace_id`,`template_id`),
  ADD KEY `letters_workspace_id_letter_date_index` (`workspace_id`,`letter_date`);

--
-- Indexes for table `letter_templates`
--
ALTER TABLE `letter_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `letter_templates_created_by_foreign` (`created_by`),
  ADD KEY `letter_templates_workspace_id_category_index` (`workspace_id`,`category`),
  ADD KEY `letter_templates_workspace_id_is_active_index` (`workspace_id`,`is_active`);

--
-- Indexes for table `letter_variables`
--
ALTER TABLE `letter_variables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `letter_variables_workspace_id_name_unique` (`workspace_id`,`name`),
  ADD KEY `letter_variables_created_by_foreign` (`created_by`),
  ADD KEY `letter_variables_workspace_id_is_active_index` (`workspace_id`,`is_active`);

--
-- Indexes for table `media`
--
ALTER TABLE `media`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `media_uuid_unique` (`uuid`),
  ADD KEY `media_model_type_model_id_index` (`model_type`,`model_id`),
  ADD KEY `media_order_column_index` (`order_column`);

--
-- Indexes for table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `meeting_user`
--
ALTER TABLE `meeting_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meeting_user_meeting_id_user_id_unique` (`meeting_id`,`user_id`),
  ADD KEY `meeting_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `menu_orders`
--
ALTER TABLE `menu_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `menu_orders_user_id_foreign` (`user_id`),
  ADD KEY `menu_orders_client_id_foreign` (`client_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `milestones`
--
ALTER TABLE `milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `milestones_project_id_foreign` (`project_id`),
  ADD KEY `milestones_workspace_id_foreign` (`workspace_id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notes_workspace_id_foreign` (`workspace_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_workspace_id_foreign` (`workspace_id`);

--
-- Indexes for table `notification_user`
--
ALTER TABLE `notification_user`
  ADD PRIMARY KEY (`user_id`,`notification_id`),
  ADD KEY `notification_user_notification_id_foreign` (`notification_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD KEY `password_resets_email_index` (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_methods_workspace_id_foreign` (`workspace_id`);

--
-- Indexes for table `payslips`
--
ALTER TABLE `payslips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payslips_user_id_foreign` (`user_id`),
  ADD KEY `payslips_workspace_id_foreign` (`workspace_id`),
  ADD KEY `payslips_payment_method_id_foreign` (`payment_method_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `pinned`
--
ALTER TABLE `pinned`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pinned_pinnable_type_pinnable_id_index` (`pinnable_type`,`pinnable_id`),
  ADD KEY `pinned_user_id_foreign` (`user_id`),
  ADD KEY `pinned_client_id_foreign` (`client_id`);

--
-- Indexes for table `priorities`
--
ALTER TABLE `priorities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_status_id_foreign` (`status_id`);

--
-- Indexes for table `project_tag`
--
ALTER TABLE `project_tag`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_tag_project_id_foreign` (`project_id`),
  ADD KEY `project_tag_tag_id_foreign` (`tag_id`);

--
-- Indexes for table `project_user`
--
ALTER TABLE `project_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_user_project_id_user_id_unique` (`project_id`,`user_id`),
  ADD KEY `project_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `recurring_tasks`
--
ALTER TABLE `recurring_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recurring_tasks_task_id_foreign` (`task_id`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reminders_remindable_type_remindable_id_index` (`remindable_type`,`remindable_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `role_status`
--
ALTER TABLE `role_status`
  ADD PRIMARY KEY (`role_id`,`status_id`),
  ADD KEY `role_status_status_id_foreign` (`status_id`);

--
-- Indexes for table `scheduled_emails`
--
ALTER TABLE `scheduled_emails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `scheduled_emails_workspace_id_foreign` (`workspace_id`),
  ADD KEY `scheduled_emails_email_template_id_foreign` (`email_template_id`),
  ADD KEY `scheduled_emails_user_id_foreign` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `statuses`
--
ALTER TABLE `statuses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `status_timelines`
--
ALTER TABLE `status_timelines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status_timelines_entity_id_entity_type_index` (`entity_id`,`entity_type`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_project_id_foreign` (`project_id`),
  ADD KEY `tasks_parent_id_foreign` (`parent_id`),
  ADD KEY `tasks_task_list_id_foreign` (`task_list_id`);

--
-- Indexes for table `task_lists`
--
ALTER TABLE `task_lists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_lists_project_id_foreign` (`project_id`);

--
-- Indexes for table `task_time_entries`
--
ALTER TABLE `task_time_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_time_entries_task_id_foreign` (`task_id`);

--
-- Indexes for table `task_user`
--
ALTER TABLE `task_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `task_user_task_id_user_id_unique` (`task_id`,`user_id`),
  ADD KEY `task_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `taxes`
--
ALTER TABLE `taxes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `templates`
--
ALTER TABLE `templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `templates_type_name_unique` (`type`,`name`);

--
-- Indexes for table `time_trackers`
--
ALTER TABLE `time_trackers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `time_trackers_workspace_id_foreign` (`workspace_id`),
  ADD KEY `time_trackers_user_id_foreign` (`user_id`);

--
-- Indexes for table `todos`
--
ALTER TABLE `todos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `updates`
--
ALTER TABLE `updates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `user_client_preferences`
--
ALTER TABLE `user_client_preferences`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_workspace`
--
ALTER TABLE `user_workspace`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_workspace_workspace_id_user_id_unique` (`workspace_id`,`user_id`),
  ADD KEY `user_workspace_user_id_foreign` (`user_id`);

--
-- Indexes for table `workspaces`
--
ALTER TABLE `workspaces`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `allowances`
--
ALTER TABLE `allowances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `allowance_payslip`
--
ALTER TABLE `allowance_payslip`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `candidate_notification`
--
ALTER TABLE `candidate_notification`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `candidate_statuses`
--
ALTER TABLE `candidate_statuses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_meeting`
--
ALTER TABLE `client_meeting`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_project`
--
ALTER TABLE `client_project`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_workspace`
--
ALTER TABLE `client_workspace`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comment_attachments`
--
ALTER TABLE `comment_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contract_types`
--
ALTER TABLE `contract_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_fieldables`
--
ALTER TABLE `custom_fieldables`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_fields`
--
ALTER TABLE `custom_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deductions`
--
ALTER TABLE `deductions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deduction_payslip`
--
ALTER TABLE `deduction_payslip`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `estimates_invoices`
--
ALTER TABLE `estimates_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `estimates_invoice_item`
--
ALTER TABLE `estimates_invoice_item`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_types`
--
ALTER TABLE `expense_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fcm_tokens`
--
ALTER TABLE `fcm_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interviews`
--
ALTER TABLE `interviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `languages`
--
ALTER TABLE `languages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lead_follow_ups`
--
ALTER TABLE `lead_follow_ups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lead_forms`
--
ALTER TABLE `lead_forms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lead_form_fields`
--
ALTER TABLE `lead_form_fields`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lead_sources`
--
ALTER TABLE `lead_sources`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lead_stages`
--
ALTER TABLE `lead_stages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_editors`
--
ALTER TABLE `leave_editors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_request_visibility`
--
ALTER TABLE `leave_request_visibility`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `letters`
--
ALTER TABLE `letters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `letter_templates`
--
ALTER TABLE `letter_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `letter_variables`
--
ALTER TABLE `letter_variables`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `media`
--
ALTER TABLE `media`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meetings`
--
ALTER TABLE `meetings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meeting_user`
--
ALTER TABLE `meeting_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_orders`
--
ALTER TABLE `menu_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT for table `milestones`
--
ALTER TABLE `milestones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payslips`
--
ALTER TABLE `payslips`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=373;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pinned`
--
ALTER TABLE `pinned`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `priorities`
--
ALTER TABLE `priorities`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_tag`
--
ALTER TABLE `project_tag`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_user`
--
ALTER TABLE `project_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recurring_tasks`
--
ALTER TABLE `recurring_tasks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `scheduled_emails`
--
ALTER TABLE `scheduled_emails`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `statuses`
--
ALTER TABLE `statuses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `status_timelines`
--
ALTER TABLE `status_timelines`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_lists`
--
ALTER TABLE `task_lists`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_time_entries`
--
ALTER TABLE `task_time_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_user`
--
ALTER TABLE `task_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `taxes`
--
ALTER TABLE `taxes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `templates`
--
ALTER TABLE `templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `time_trackers`
--
ALTER TABLE `time_trackers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `todos`
--
ALTER TABLE `todos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `updates`
--
ALTER TABLE `updates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_client_preferences`
--
ALTER TABLE `user_client_preferences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_workspace`
--
ALTER TABLE `user_workspace`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workspaces`
--
ALTER TABLE `workspaces`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `allowances`
--
ALTER TABLE `allowances`
  ADD CONSTRAINT `allowances_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `allowance_payslip`
--
ALTER TABLE `allowance_payslip`
  ADD CONSTRAINT `allowance_payslip_allowance_id_foreign` FOREIGN KEY (`allowance_id`) REFERENCES `allowances` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `allowance_payslip_payslip_id_foreign` FOREIGN KEY (`payslip_id`) REFERENCES `payslips` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `candidates`
--
ALTER TABLE `candidates`
  ADD CONSTRAINT `candidates_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `candidate_statuses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `candidate_notification`
--
ALTER TABLE `candidate_notification`
  ADD CONSTRAINT `candidate_notification_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidate_notification_notification_id_foreign` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_meeting`
--
ALTER TABLE `client_meeting`
  ADD CONSTRAINT `client_meeting_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_meeting_meeting_id_foreign` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_notifications`
--
ALTER TABLE `client_notifications`
  ADD CONSTRAINT `client_notifications_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_notifications_notification_id_foreign` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_project`
--
ALTER TABLE `client_project`
  ADD CONSTRAINT `project_client_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_client_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_workspace`
--
ALTER TABLE `client_workspace`
  ADD CONSTRAINT `client_workspace_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `client_workspace_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comment_attachments`
--
ALTER TABLE `comment_attachments`
  ADD CONSTRAINT `comment_attachments_comment_id_foreign` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_contract_type_id_foreign` FOREIGN KEY (`contract_type_id`) REFERENCES `contract_types` (`id`),
  ADD CONSTRAINT `contracts_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contract_types`
--
ALTER TABLE `contract_types`
  ADD CONSTRAINT `contract_types_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `custom_fieldables`
--
ALTER TABLE `custom_fieldables`
  ADD CONSTRAINT `custom_fieldables_custom_field_id_foreign` FOREIGN KEY (`custom_field_id`) REFERENCES `custom_fields` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deduction_payslip`
--
ALTER TABLE `deduction_payslip`
  ADD CONSTRAINT `deduction_payslip_deduction_id_foreign` FOREIGN KEY (`deduction_id`) REFERENCES `deductions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deduction_payslip_payslip_id_foreign` FOREIGN KEY (`payslip_id`) REFERENCES `payslips` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD CONSTRAINT `email_templates_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `estimates_invoices`
--
ALTER TABLE `estimates_invoices`
  ADD CONSTRAINT `estimates_invoices_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `estimates_invoices_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `estimates_invoice_item`
--
ALTER TABLE `estimates_invoice_item`
  ADD CONSTRAINT `estimates_invoices_items_estimate_invoice_id_foreign` FOREIGN KEY (`estimates_invoice_id`) REFERENCES `estimates_invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `estimates_invoices_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_expense_type_id_foreign` FOREIGN KEY (`expense_type_id`) REFERENCES `expense_types` (`id`),
  ADD CONSTRAINT `expenses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expense_types`
--
ALTER TABLE `expense_types`
  ADD CONSTRAINT `expense_types_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fcm_tokens`
--
ALTER TABLE `fcm_tokens`
  ADD CONSTRAINT `fcm_tokens_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fcm_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interviews`
--
ALTER TABLE `interviews`
  ADD CONSTRAINT `interviews_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interviews_interviewer_id_foreign` FOREIGN KEY (`interviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leads_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leads_source_id_foreign` FOREIGN KEY (`source_id`) REFERENCES `lead_sources` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leads_stage_id_foreign` FOREIGN KEY (`stage_id`) REFERENCES `lead_stages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leads_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lead_follow_ups`
--
ALTER TABLE `lead_follow_ups`
  ADD CONSTRAINT `lead_follow_ups_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lead_follow_ups_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lead_forms`
--
ALTER TABLE `lead_forms`
  ADD CONSTRAINT `lead_forms_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `lead_forms_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lead_forms_source_id_foreign` FOREIGN KEY (`source_id`) REFERENCES `lead_sources` (`id`),
  ADD CONSTRAINT `lead_forms_stage_id_foreign` FOREIGN KEY (`stage_id`) REFERENCES `lead_stages` (`id`),
  ADD CONSTRAINT `lead_forms_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lead_form_fields`
--
ALTER TABLE `lead_form_fields`
  ADD CONSTRAINT `lead_form_fields_form_id_foreign` FOREIGN KEY (`form_id`) REFERENCES `lead_forms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_editors`
--
ALTER TABLE `leave_editors`
  ADD CONSTRAINT `leave_editors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_request_visibility`
--
ALTER TABLE `leave_request_visibility`
  ADD CONSTRAINT `leave_request_visibility_leave_request_id_foreign` FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_request_visibility_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `letters`
--
ALTER TABLE `letters`
  ADD CONSTRAINT `letters_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `letters_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `letter_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `letters_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `letters_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `letter_templates`
--
ALTER TABLE `letter_templates`
  ADD CONSTRAINT `letter_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `letter_templates_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `letter_variables`
--
ALTER TABLE `letter_variables`
  ADD CONSTRAINT `letter_variables_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `letter_variables_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meeting_user`
--
ALTER TABLE `meeting_user`
  ADD CONSTRAINT `meeting_user_meeting_id_foreign` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meeting_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `menu_orders`
--
ALTER TABLE `menu_orders`
  ADD CONSTRAINT `menu_orders_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `milestones`
--
ALTER TABLE `milestones`
  ADD CONSTRAINT `milestones_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `milestones_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_user`
--
ALTER TABLE `notification_user`
  ADD CONSTRAINT `notification_user_notification_id_foreign` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD CONSTRAINT `payment_methods_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payslips`
--
ALTER TABLE `payslips`
  ADD CONSTRAINT `payslips_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
  ADD CONSTRAINT `payslips_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payslips_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pinned`
--
ALTER TABLE `pinned`
  ADD CONSTRAINT `pinned_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pinned_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_tag`
--
ALTER TABLE `project_tag`
  ADD CONSTRAINT `project_tag_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_user`
--
ALTER TABLE `project_user`
  ADD CONSTRAINT `project_user_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recurring_tasks`
--
ALTER TABLE `recurring_tasks`
  ADD CONSTRAINT `recurring_tasks_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_status`
--
ALTER TABLE `role_status`
  ADD CONSTRAINT `role_status_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_status_status_id_foreign` FOREIGN KEY (`status_id`) REFERENCES `statuses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scheduled_emails`
--
ALTER TABLE `scheduled_emails`
  ADD CONSTRAINT `scheduled_emails_email_template_id_foreign` FOREIGN KEY (`email_template_id`) REFERENCES `email_templates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scheduled_emails_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scheduled_emails_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_task_list_id_foreign` FOREIGN KEY (`task_list_id`) REFERENCES `task_lists` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `task_lists`
--
ALTER TABLE `task_lists`
  ADD CONSTRAINT `task_lists_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_time_entries`
--
ALTER TABLE `task_time_entries`
  ADD CONSTRAINT `task_time_entries_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_user`
--
ALTER TABLE `task_user`
  ADD CONSTRAINT `task_user_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `time_trackers`
--
ALTER TABLE `time_trackers`
  ADD CONSTRAINT `time_trackers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `time_trackers_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_workspace`
--
ALTER TABLE `user_workspace`
  ADD CONSTRAINT `user_workspace_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_workspace_workspace_id_foreign` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
