-- phpMyAdmin SQL Dump
-- version 4.9.11
-- https://www.phpmyadmin.net/
--
-- Host: db5019666126.hosting-data.io
-- Generation Time: May 03, 2026 at 08:38 AM
-- Server version: 10.11.14-MariaDB-log
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbs15304861`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_by` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `target_course` varchar(255) DEFAULT NULL,
  `target_year` varchar(100) DEFAULT NULL,
  `target_section` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_reads`
--

CREATE TABLE `announcement_reads` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `read_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `signatory` varchar(100) NOT NULL,
  `requirement_id` int(11) DEFAULT 0,
  `course` varchar(100) NOT NULL,
  `document` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Requires Action','Totally Rejected') DEFAULT 'Pending',
  `rejection_reason` text DEFAULT NULL,
  `require_resubmit` tinyint(1) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `rejection_count` int(11) NOT NULL DEFAULT 0,
  `auto_approved` tinyint(1) NOT NULL DEFAULT 0,
  `auto_approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `username`, `signatory`, `requirement_id`, `course`, `document`, `status`, `rejection_reason`, `require_resubmit`, `submitted_at`, `reviewed_at`, `remarks`, `rejection_count`, `auto_approved`, `auto_approved_at`) VALUES
(75, 'MA22013930', 'pau', 0, 'BSIS', 'N/A', 'Approved', NULL, 0, '2026-03-25 08:26:54', '2026-03-25 16:26:54', NULL, 0, 0, NULL),
(77, 'MA22013931', 'eliseo', 0, 'BTVTED', 'N/A', 'Approved', NULL, 0, '2026-03-29 05:57:10', '2026-03-29 13:57:10', NULL, 0, 0, NULL),
(92, '0123546', 'paulovictoria', 27, 'ACT', 'N/A', 'Approved', NULL, 0, '2026-04-17 08:42:11', NULL, NULL, 0, 1, '2026-04-17 16:42:11'),
(93, 'MA22013930', 'paulovictoria', 27, 'BSIS', 'N/A', 'Approved', NULL, 0, '2026-04-17 08:42:11', NULL, NULL, 0, 1, '2026-04-17 16:42:11');

-- --------------------------------------------------------

--
-- Table structure for table `application_rejection_log`
--

CREATE TABLE `application_rejection_log` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `signatory` varchar(100) NOT NULL,
  `rejection_reason` text DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `rejected_at` datetime DEFAULT current_timestamp(),
  `rejection_number` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_applications`
--

CREATE TABLE `archived_applications` (
  `id` int(11) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `orig_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `signatory` varchar(100) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `requirement_id` int(11) DEFAULT NULL,
  `document` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `rejection_count` int(11) DEFAULT 0,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archived_applications`
--

INSERT INTO `archived_applications` (`id`, `semester`, `school_year`, `orig_id`, `username`, `signatory`, `course`, `requirement_id`, `document`, `status`, `rejection_reason`, `rejection_count`, `submitted_at`, `reviewed_at`, `archived_at`) VALUES
(16, '1st Semester', '2026-2027', 24, 'Username-00001', 'RusselSig', 'BSIS', 1, 'Instructor_Schedule_Reynaldo_Santos__1__1771331859.pdf', 'Approved', NULL, 0, '2026-02-17 07:37:48', '2026-02-17 20:38:55', '2026-02-20 21:46:31'),
(17, '1st Semester', '2026-2027', 25, 'Username-00001', 'RusselSig', 'BSIS', 0, 'N/A', 'Approved', NULL, 0, '2026-02-17 07:37:48', '2026-02-17 20:37:48', '2026-02-20 21:46:31'),
(18, '1st Semester', '2026-2027', 26, 'joshualastimado', 'RusselSig', 'BSOM', 1, 'Instructor_Schedule_Reynaldo_Santos__1__1771331896.pdf', 'Requires Action', 'Incomplete information on the form', 1, '2026-02-17 07:38:25', '2026-02-17 20:38:47', '2026-02-20 21:46:31'),
(19, '1st Semester', '2026-2027', 28, 'Username-00001', 'paulov', 'BSIS', 5, 'signatory_history_RusselSig_all-time_2026-02-17_070145_1771332010.csv', 'Requires Action', 'Document is blurry or unreadable', 1, '2026-02-17 07:40:20', '2026-02-17 20:40:47', '2026-02-20 21:46:31'),
(20, '1st Semester', '2026-2027', 29, 'Username-00001', 'paulov', 'BSIS', 0, 'N/A', 'Approved', NULL, 0, '2026-02-17 07:40:20', '2026-02-17 20:40:20', '2026-02-20 21:46:31'),
(21, '1st Semester', '2026-2027', 30, 'dandanan', 'RusselSig', 'ACT', 0, 'N/A', 'Approved', NULL, 0, '2026-02-17 23:13:26', '2026-02-18 12:13:26', '2026-02-20 21:46:31'),
(22, '1st Semester', '2026-2027', 31, 'dandanan', 'Russel', 'ACT', 7, 'Activity_1_Case_Study_Marquina_Marlon_1771388037.pdf', 'Approved', NULL, 0, '2026-02-17 23:14:13', '2026-02-18 12:19:01', '2026-02-20 21:46:31'),
(23, '1st Semester', '2026-2027', 32, 'dandanan', 'paulov', 'ACT', 0, 'N/A', 'Approved', NULL, 0, '2026-02-17 23:17:44', '2026-02-18 12:17:44', '2026-02-20 21:46:31'),
(24, '1st Semester', '2026-2027', 33, 'dandanan', 'paulov', 'ACT', 5, 'Activity_1_Case_Study_Marquina_Marlon_1771388243.pdf', 'Approved', NULL, 0, '2026-02-17 23:17:44', '2026-02-18 12:27:45', '2026-02-20 21:46:31'),
(25, '1st Semester', '2026-2027', 34, 'dandanan', 'RusselSig', 'ACT', 1, 'Activity_1_Case_Study_Marquina_Marlon_RESUBMITTED_1771388712.pdf', 'Approved', NULL, 0, '2026-02-18 12:25:12', '2026-02-18 12:26:16', '2026-02-20 21:46:31'),
(26, '1st Semester', '2026-2027', 35, 'mareyes', 'RusselSig', 'HB', 0, 'N/A', 'Approved', NULL, 0, '2026-02-18 00:42:46', '2026-02-18 13:42:46', '2026-02-20 21:46:31'),
(27, '1st Semester', '2026-2027', 36, 'mareyes', 'RusselSig', 'HB', 8, 'IS-ePT-423_INFOSHEET1_1771393313.pdf', 'Approved', NULL, 0, '2026-02-18 00:42:46', '2026-02-18 13:43:21', '2026-02-20 21:46:31');

-- --------------------------------------------------------

--
-- Table structure for table `archived_course_requirements`
--

CREATE TABLE `archived_course_requirements` (
  `id` int(11) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `orig_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `requirement_id` int(11) DEFAULT NULL,
  `signatory_id` int(11) DEFAULT NULL,
  `document_type_id` varchar(255) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `sections` varchar(255) DEFAULT NULL,
  `requirements_configured` tinyint(1) DEFAULT 0,
  `archived_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archived_course_requirements`
--

INSERT INTO `archived_course_requirements` (`id`, `semester`, `school_year`, `orig_id`, `course_id`, `requirement_id`, `signatory_id`, `document_type_id`, `year_level`, `sections`, `requirements_configured`, `archived_at`) VALUES
(25, '1st Semester', '2026-2027', 72, 8, 0, 50, 'N/A', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(26, '1st Semester', '2026-2027', 79, 2, 0, 38, 'N/A', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(27, '1st Semester', '2026-2027', 80, 8, 0, 38, 'N/A', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(28, '1st Semester', '2026-2027', 84, 2, 1, 38, 'PDF (.pdf), Word (.docx)', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(29, '1st Semester', '2026-2027', 85, 8, 1, 38, 'PDF (.pdf), Word (.docx)', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(30, '1st Semester', '2026-2027', 87, 4, 1, 38, 'PDF (.pdf), Word (.docx)', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(31, '1st Semester', '2026-2027', 88, 9, 1, 38, 'PDF (.pdf), Word (.docx)', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(32, '1st Semester', '2026-2027', 89, 2, 0, 29, 'N/A', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(33, '1st Semester', '2026-2027', 91, 2, 5, 29, 'Any File (.*)', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(34, '1st Semester', '2026-2027', 93, 2, 7, 39, 'PDF (.pdf)', '1st Year', 'A', 1, '2026-02-20 21:46:31'),
(35, '1st Semester', '2026-2027', 94, 1, 8, 38, 'PDF (.pdf), Word (.doc)', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(36, '1st Semester', '2026-2027', 95, 11, 8, 38, 'PDF (.pdf), Word (.doc)', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(37, '1st Semester', '2026-2027', 96, 2, 8, 38, 'PDF (.pdf), Word (.doc)', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(38, '1st Semester', '2026-2027', 97, 8, 8, 38, 'PDF (.pdf), Word (.doc)', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(39, '1st Semester', '2026-2027', 98, 4, 8, 38, 'PDF (.pdf), Word (.doc)', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(40, '1st Semester', '2026-2027', 99, 9, 8, 38, 'PDF (.pdf), Word (.doc)', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31'),
(41, '1st Semester', '2026-2027', 100, 11, 0, 38, 'N/A', 'All Years', 'All Sections', 1, '2026-02-20 21:46:31');

-- --------------------------------------------------------

--
-- Table structure for table `archived_draft_requirements`
--

CREATE TABLE `archived_draft_requirements` (
  `id` int(11) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `orig_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `requirement_id` int(11) DEFAULT NULL,
  `signatory_id` int(11) DEFAULT NULL,
  `document_type_id` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `archived_signatory_history`
--

CREATE TABLE `archived_signatory_history` (
  `id` int(11) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `orig_id` int(11) DEFAULT NULL,
  `signatory_username` varchar(100) DEFAULT NULL,
  `student_user` varchar(100) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archived_signatory_history`
--

INSERT INTO `archived_signatory_history` (`id`, `semester`, `school_year`, `orig_id`, `signatory_username`, `student_user`, `action`, `reason`, `remarks`, `created_at`, `archived_at`) VALUES
(38, '1st Semester', '2026-2027', 29, 'JoshuaSig', 'joshualastimado', 'Requires Action', 'Document contains errors or corrections', 'Instructor_Schedule_Reynaldo_Santos__1__1771244057.pdf', '2026-02-16 07:14:35', '2026-02-20 21:46:31'),
(39, '1st Semester', '2026-2027', 30, 'JoshuaSig', 'joshualastimado', 'Requires Action', 'Document is blurry or unreadable', 'Instructor_Schedule_Reynaldo_Santos__1__RESUBMITTED_1771244119.pdf', '2026-02-16 07:15:41', '2026-02-20 21:46:31'),
(40, '1st Semester', '2026-2027', 31, 'JoshuaSig', 'joshualastimado', 'Requires Action', 'Photo/image quality is too poor', 'Instructor_Schedule_Reynaldo_Santos__1__RESUBMITTED_1771244213.pdf', '2026-02-16 07:17:02', '2026-02-20 21:46:31'),
(41, '1st Semester', '2026-2027', 32, 'JoshuaSig', 'joshualastimado', 'Approved', 'Requirement met and validated', 'Instructor_Schedule_Reynaldo_Santos__1__RESUBMITTED_1771244593.pdf', '2026-02-16 07:25:46', '2026-02-20 21:46:31'),
(42, '1st Semester', '2026-2027', 33, 'JoshuaSig', 'joshualastimado', 'Requires Action', 'Incomplete information on the form', 'Instructor_Schedule_Reynaldo_Santos__1__1771245019.pdf', '2026-02-16 07:30:31', '2026-02-20 21:46:31'),
(43, '1st Semester', '2026-2027', 34, 'JoshuaSig', 'joshualastimado', 'Requires Action', 'Photo/image quality is too poor', 'Instructor_Schedule_Reynaldo_Santos__1__RESUBMITTED_1771245044.pdf', '2026-02-16 07:30:56', '2026-02-20 21:46:31'),
(44, '1st Semester', '2026-2027', 35, 'JoshuaSig', 'joshualastimado', 'Requires Action', 'Document is blurry or unreadable', 'Instructor_Schedule_Miguel_Don_Gatchalian_RESUBMITTED_1771245072.pdf', '2026-02-16 07:31:20', '2026-02-20 21:46:31'),
(45, '1st Semester', '2026-2027', 36, 'JoshuaSig', 'joshualastimado', 'Totally Rejected', 'Incomplete information on the form', 'Instructor_Schedule_Reynaldo_Santos__1__RESUBMITTED_1771245097.pdf', '2026-02-16 07:31:48', '2026-02-20 21:46:31'),
(46, '1st Semester', '2026-2027', 37, 'RusselSig', 'Username-00001', 'Requires Action', 'Document is blurry or unreadable', '5ead8de3-d24f-435e-858d-ef0b216e9f96_1771328160.jpg', '2026-02-17 06:38:11', '2026-02-20 21:46:31'),
(47, '1st Semester', '2026-2027', 38, 'RusselSig', 'Username-00001', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-02-17 06:42:26', '2026-02-20 21:46:31'),
(48, '1st Semester', '2026-2027', 39, 'RusselSig', 'Username-00001', 'Requires Action', 'Wrong document type submitted', 'Class_Adviser_Import_Template__4__RESUBMITTED_1771328343.xlsx', '2026-02-17 06:43:42', '2026-02-20 21:46:31'),
(49, '1st Semester', '2026-2027', 40, 'RusselSig', 'Username-00001', 'Approved (Bulk)', 'Requirement met', 'Instructor_Schedule_Reynaldo_Santos__1__1771328377.pdf', '2026-02-17 06:43:51', '2026-02-20 21:46:31'),
(50, '1st Semester', '2026-2027', 41, 'RusselSig', 'Username-00001', 'Approved (Bulk)', 'Requirement met', 'Instructor_Schedule_Reynaldo_Santos__1__1771328386.pdf', '2026-02-17 06:43:53', '2026-02-20 21:46:31'),
(51, '1st Semester', '2026-2027', 42, 'RusselSig', 'Username-00001', 'Requires Action', 'Document contains errors or corrections', 'Class_Adviser_Import_Template__3__RESUBMITTED_1771328806.xlsx', '2026-02-17 06:47:11', '2026-02-20 21:46:31'),
(52, '1st Semester', '2026-2027', 43, 'RusselSig', 'Username-00001', 'Totally Rejected', 'Photo/image quality is too poor', 'Class_Adviser_Import_Template__4__RESUBMITTED_1771328873.xlsx', '2026-02-17 06:48:21', '2026-02-20 21:46:31'),
(53, '1st Semester', '2026-2027', 44, 'RusselSig', 'Username-00001', 'Approved', 'Overturned — manually approved by signatory', 'Class_Adviser_Import_Template__4__RESUBMITTED_1771328873.xlsx', '2026-02-17 06:50:08', '2026-02-20 21:46:31'),
(54, '1st Semester', '2026-2027', 45, 'RusselSig', 'joshualastimado', 'Requires Action', 'Document is blurry or unreadable', 'Instructor_Schedule_Reynaldo_Santos__1__1771329889.pdf', '2026-02-17 07:05:16', '2026-02-20 21:46:31'),
(55, '1st Semester', '2026-2027', 46, 'RusselSig', 'Username-00001', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-02-17 07:37:48', '2026-02-20 21:46:31'),
(56, '1st Semester', '2026-2027', 47, 'RusselSig', 'joshualastimado', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-02-17 07:38:25', '2026-02-20 21:46:31'),
(57, '1st Semester', '2026-2027', 48, 'RusselSig', 'joshualastimado', 'Requires Action', 'Incomplete information on the form', 'Instructor_Schedule_Reynaldo_Santos__1__1771331896.pdf', '2026-02-17 07:38:47', '2026-02-20 21:46:31'),
(58, '1st Semester', '2026-2027', 49, 'RusselSig', 'Username-00001', 'Approved', 'Requirement met and validated', 'Instructor_Schedule_Reynaldo_Santos__1__1771331859.pdf', '2026-02-17 07:38:55', '2026-02-20 21:46:31'),
(59, '1st Semester', '2026-2027', 50, 'paulov', 'Username-00001', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-02-17 07:40:20', '2026-02-20 21:46:31'),
(60, '1st Semester', '2026-2027', 51, 'paulov', 'Username-00001', 'Requires Action', 'Document is blurry or unreadable', 'signatory_history_RusselSig_all-time_2026-02-17_070145_1771332010.csv', '2026-02-17 07:40:47', '2026-02-20 21:46:31'),
(61, '1st Semester', '2026-2027', 52, 'RusselSig', 'dandanan', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-02-17 23:13:26', '2026-02-20 21:46:31'),
(62, '1st Semester', '2026-2027', 53, 'paulov', 'dandanan', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-02-17 23:17:44', '2026-02-20 21:46:31'),
(63, '1st Semester', '2026-2027', 54, 'Russel', 'dandanan', 'Approved', 'Requirement met and validated', 'Activity_1_Case_Study_Marquina_Marlon_1771388037.pdf', '2026-02-17 23:19:01', '2026-02-20 21:46:31'),
(64, '1st Semester', '2026-2027', 55, 'RusselSig', 'dandanan', 'Requires Action', 'Incomplete information on the form', 'Activity_1_Case_Study_Marquina_Marlon_1771388254.pdf', '2026-02-17 23:21:29', '2026-02-20 21:46:31'),
(65, '1st Semester', '2026-2027', 56, 'RusselSig', 'dandanan', 'Requires Action', 'Photo/image quality is too poor', 'Activity_1_Case_Study_Marquina_Marlon_RESUBMITTED_1771388584.pdf', '2026-02-17 23:23:45', '2026-02-20 21:46:31'),
(66, '1st Semester', '2026-2027', 57, 'RusselSig', 'dandanan', 'Requires Action', 'Document is blurry or unreadable', 'Activity_1_Case_Study_Marquina_Marlon_RESUBMITTED_1771388657.pdf', '2026-02-17 23:24:35', '2026-02-20 21:46:31'),
(67, '1st Semester', '2026-2027', 58, 'RusselSig', 'dandanan', 'Totally Rejected', 'Photo/image quality is too poor', 'Activity_1_Case_Study_Marquina_Marlon_RESUBMITTED_1771388712.pdf', '2026-02-17 23:25:39', '2026-02-20 21:46:31'),
(68, '1st Semester', '2026-2027', 59, 'RusselSig', 'dandanan', 'Approved', 'Overturned — manually approved by signatory', 'Activity_1_Case_Study_Marquina_Marlon_RESUBMITTED_1771388712.pdf', '2026-02-17 23:26:16', '2026-02-20 21:46:31'),
(69, '1st Semester', '2026-2027', 60, 'paulov', 'dandanan', 'Approved', 'Requirement met and validated', 'Activity_1_Case_Study_Marquina_Marlon_1771388243.pdf', '2026-02-17 23:27:45', '2026-02-20 21:46:31'),
(70, '1st Semester', '2026-2027', 61, 'RusselSig', 'mareyes', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-02-18 00:42:46', '2026-02-20 21:46:31'),
(71, '1st Semester', '2026-2027', 62, 'RusselSig', 'mareyes', 'Approved', 'Requirement met and validated', 'IS-ePT-423_INFOSHEET1_1771393313.pdf', '2026-02-18 00:43:21', '2026-02-20 21:46:31');

-- --------------------------------------------------------

--
-- Table structure for table `archived_student_status`
--

CREATE TABLE `archived_student_status` (
  `id` int(11) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `admin_approved` tinyint(1) DEFAULT 0,
  `final_clearance_status` varchar(50) DEFAULT NULL,
  `admin_messaged` tinyint(1) DEFAULT 0,
  `admin_message_text` text DEFAULT NULL,
  `archived_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archived_student_status`
--

INSERT INTO `archived_student_status` (`id`, `semester`, `school_year`, `username`, `admin_approved`, `final_clearance_status`, `admin_messaged`, `admin_message_text`, `archived_at`) VALUES
(30, '1st Semester', '2026-2027', 'MA2201023', 0, 'not_requested', 0, '', '2026-02-20 21:46:31'),
(31, '1st Semester', '2026-2027', 'MA22013932', 0, 'not_requested', 0, '', '2026-02-20 21:46:31'),
(32, '1st Semester', '2026-2027', 'desrobles', 0, 'not_requested', 0, '', '2026-02-20 21:46:31'),
(33, '1st Semester', '2026-2027', 'MA220123', 0, 'not_requested', 0, '', '2026-02-20 21:46:31'),
(34, '1st Semester', '2026-2027', 'joshualastimado', 0, 'not_requested', 1, 'Try ONline mf', '2026-02-20 21:46:31'),
(35, '1st Semester', '2026-2027', 'dandanan', 0, 'not_requested', 1, 'hello testing', '2026-02-20 21:46:31'),
(36, '1st Semester', '2026-2027', 'Username-00001', 0, 'not_requested', 0, '', '2026-02-20 21:46:31'),
(37, '1st Semester', '2026-2027', 'Username-00002', 0, 'not_requested', 0, '', '2026-02-20 21:46:31'),
(38, '1st Semester', '2026-2027', 'usertryu', 0, 'not_requested', 0, '', '2026-02-20 21:46:31'),
(39, '1st Semester', '2026-2027', 'bsom3b', 0, 'not_requested', 0, '', '2026-02-20 21:46:31'),
(40, '1st Semester', '2026-2027', 'bsca2b', 0, 'not_requested', 0, '', '2026-02-20 21:46:31'),
(41, '1st Semester', '2026-2027', 'tryirishbtvted', 0, 'not_requested', 0, '', '2026-02-20 21:46:31'),
(42, '1st Semester', '2026-2027', 'trytry', 0, 'not_requested', 0, '', '2026-02-20 21:46:31'),
(43, '1st Semester', '2026-2027', 'mareyes', 1, 'cleared', 0, '', '2026-02-20 21:46:31'),
(44, '1st Semester', '2026-2027', 'MA220192387', 0, '', 0, NULL, '2026-02-20 21:46:31'),
(60, '1st Semester', '2027-2028', 'MA2201023', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(61, '1st Semester', '2027-2028', 'MA22013932', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(62, '1st Semester', '2027-2028', 'desrobles', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(63, '1st Semester', '2027-2028', 'MA220123', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(64, '1st Semester', '2027-2028', 'joshualastimado', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(65, '1st Semester', '2027-2028', 'dandanan', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(66, '1st Semester', '2027-2028', 'Username-00001', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(67, '1st Semester', '2027-2028', 'Username-00002', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(68, '1st Semester', '2027-2028', 'usertryu', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(69, '1st Semester', '2027-2028', 'bsom3b', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(70, '1st Semester', '2027-2028', 'bsca2b', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(71, '1st Semester', '2027-2028', 'tryirishbtvted', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(72, '1st Semester', '2027-2028', 'trytry', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(73, '1st Semester', '2027-2028', 'mareyes', 0, '', 0, NULL, '2026-02-20 21:49:38'),
(74, '1st Semester', '2027-2028', 'MA220192387', 0, '', 0, NULL, '2026-02-20 21:49:38');

-- --------------------------------------------------------

--
-- Table structure for table `archived_user_accounts`
--

CREATE TABLE `archived_user_accounts` (
  `id` int(11) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `orig_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `password_last_updated` datetime DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `year` varchar(20) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `adviser_username` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','signatory','admin','sg_officer') NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `signatory_type` varchar(100) DEFAULT NULL,
  `department` varchar(500) DEFAULT NULL,
  `course` varchar(255) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `semester_field` varchar(50) DEFAULT NULL,
  `school_year_field` varchar(50) DEFAULT NULL,
  `final_clearance_status` varchar(50) DEFAULT 'not_requested',
  `status` enum('active','inactive') DEFAULT 'active',
  `admin_approved` tinyint(1) DEFAULT 0,
  `admin_messaged` tinyint(1) DEFAULT 0,
  `admin_message_text` text DEFAULT NULL,
  `admin_message_sent_at` datetime DEFAULT NULL,
  `admin_approved_at` datetime DEFAULT NULL,
  `admin_approved_by` varchar(50) DEFAULT NULL,
  `can_add_admin` tinyint(1) DEFAULT 1,
  `archived_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clearance_requests`
--

CREATE TABLE `clearance_requests` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `course` varchar(100) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clearance_requirements`
--

CREATE TABLE `clearance_requirements` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `signatory_id` int(11) NOT NULL,
  `requirement_name` varchar(255) NOT NULL,
  `status` enum('Pending','Completed') NOT NULL DEFAULT 'Pending',
  `file` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `duration` int(11) DEFAULT 4
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `year_level`, `duration`) VALUES
(14, 'ACT', NULL, 2),
(15, 'BSAIS', NULL, 4),
(16, 'BSCA', NULL, 4),
(17, 'BSIS', NULL, 4),
(18, 'BSOM', NULL, 4),
(23, 'Book Keeping', NULL, 1),
(24, 'CCS', NULL, 1),
(25, 'DHRMT', NULL, 3),
(26, 'Electrical Installation And Maintenance', NULL, 1),
(28, 'Shielded Metal Arc Welding', NULL, 1),
(29, 'BTVTED', NULL, 4),
(30, 'Hotel and Restaurant Services', NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `course_requirements`
--

CREATE TABLE `course_requirements` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `requirement_id` int(11) NOT NULL,
  `signatory_id` int(11) DEFAULT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_type_id` text DEFAULT NULL,
  `requirements_configured` tinyint(1) DEFAULT 0,
  `year_level` varchar(20) DEFAULT NULL,
  `sections` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_requirements`
--

INSERT INTO `course_requirements` (`id`, `course_id`, `requirement_id`, `signatory_id`, `document_type`, `document_type_id`, `requirements_configured`, `year_level`, `sections`) VALUES
(133, 1, 0, 38, '', 'N/A', 1, '4th Year', 'C'),
(134, 1, 0, 80, '', 'N/A', 1, '4th Year', 'C'),
(135, 1, 16, 89, '', 'PDF (.pdf)', 1, '4th Year', 'C'),
(142, 2, 0, 80, '', 'N/A', 1, 'All Years', 'All Sections'),
(143, 1, 0, 80, '', 'N/A', 1, 'All Years', 'All Sections'),
(146, 17, 0, 118, '', 'N/A', 1, '4th Year', 'C'),
(150, 29, 0, 108, '', 'N/A', 1, 'All Years', 'All Sections'),
(176, 14, 27, 106, '', 'AUTO_APPROVE', 1, 'All Years', 'All Sections'),
(177, 17, 27, 106, '', 'AUTO_APPROVE', 1, 'All Years', 'All Sections');

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `extensions` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `draft_requirements`
--

CREATE TABLE `draft_requirements` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `requirement_id` int(11) NOT NULL,
  `signatory_id` int(11) DEFAULT NULL,
  `document_type_id` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `username`, `message`, `type`, `is_read`, `created_at`) VALUES
(149, 'MA22013932', 'Your application (ID: 72) has been Approved.', 'success', 1, '2026-03-03 11:49:46'),
(150, 'MA22013932', 'Your application (ID: 73) was automatically approved (no file required).', 'success', 1, '2026-03-02 22:50:30'),
(151, 'MA22013932', 'Your application (ID: 74) was automatically approved (no file required).', 'success', 1, '2026-03-02 22:53:45'),
(152, 'adminkaren', 'Student Russel M. Begelia (MA22013932) has requested final clearance verification.', 'info', 0, '2026-03-02 22:53:58'),
(153, 'MA22013932', 'Pending Notice from Admin: not yet', '', 1, '2026-03-02 22:55:05'),
(154, 'MA22013932', '???? Congratulations! Your final clearance has been approved by the admin. You can now generate your clearance form.', 'success', 1, '2026-03-02 22:55:51'),
(155, 'MA22013930', 'Your application (ID: 75) was automatically approved (no file required).', 'success', 0, '2026-03-25 04:26:55'),
(156, 'MA22013930', 'Your application (ID: 76) was automatically approved (no file required).', 'success', 0, '2026-03-25 04:36:42'),
(157, 'adminkaren', 'Student Marlon Marquina (MA22013930) has requested final clearance verification.', 'info', 0, '2026-03-25 04:37:06'),
(158, 'MA22013930', '???? Congratulations! Your final clearance has been approved by the admin. You can now generate your clearance form.', 'success', 0, '2026-03-25 04:41:02'),
(159, 'MA22013931', 'Your application (ID: 77) was automatically approved (no file required).', 'success', 0, '2026-03-29 01:57:10'),
(160, '0123546', 'Your application (ID: 89) was automatically approved (no file required).', 'success', 0, '2026-04-17 00:37:02'),
(161, 'adminkaren', 'Student josh try today (0123546) has requested final clearance verification.', 'info', 0, '2026-04-17 00:37:43'),
(162, '0123546', '???? Congratulations! Your final clearance has been approved by the admin. You can now generate your clearance form.', 'success', 0, '2026-04-17 00:44:17');

-- --------------------------------------------------------

--
-- Table structure for table `rejection_reasons`
--

CREATE TABLE `rejection_reasons` (
  `id` int(11) NOT NULL,
  `signatory_id` int(11) DEFAULT NULL,
  `reason_text` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `requires_reupload` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rejection_reasons`
--

INSERT INTO `rejection_reasons` (`id`, `signatory_id`, `reason_text`, `category`, `requires_reupload`) VALUES
(6, NULL, 'Document is blurry or unreadable', 'Document Quality', 1),
(7, NULL, 'Incomplete information on the form', 'Content Issues', 0),
(8, NULL, 'Wrong document type submitted', 'Document Type', 1),
(9, NULL, 'Signature missing', 'Missing Requirements', 0),
(10, NULL, 'Document is expired or outdated', 'Document Validity', 1),
(11, NULL, 'File format not accepted', 'Technical Issues', 1),
(12, NULL, 'Document does not match student information', 'Information Mismatch', 1),
(13, NULL, 'Required fields are not filled out', 'Content Issues', 0),
(14, NULL, 'Photo/image quality is too poor', 'Document Quality', 1),
(15, NULL, 'Document contains errors or corrections', 'Content Issues', 1);

-- --------------------------------------------------------

--
-- Table structure for table `requirement_library`
--

CREATE TABLE `requirement_library` (
  `id` int(11) NOT NULL,
  `requirement_name` varchar(255) NOT NULL,
  `signatory_id` int(11) DEFAULT NULL,
  `signatory_type` varchar(100) DEFAULT NULL,
  `allowed_formats` text DEFAULT NULL COMMENT 'Comma-separated list of allowed file formats',
  `auto_approve` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requirement_library`
--

INSERT INTO `requirement_library` (`id`, `requirement_name`, `signatory_id`, `signatory_type`, `allowed_formats`, `auto_approve`) VALUES
(16, 'irish book', 89, NULL, 'PDF (.pdf)', 0),
(17, 'Journal', 38, NULL, 'Word (.doc)', 0),
(18, 'ircite certificate', 118, NULL, 'PDF (.pdf)', 0),
(27, 'Approved No Requirement', 106, NULL, 'AUTO_APPROVE', 1);

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `year` varchar(50) NOT NULL,
  `section_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `course_id`, `year`, `section_name`) VALUES
(13, 0, '', 'B'),
(62, 1, '1st Year', 'A'),
(117, 1, '1st Year', 'B'),
(118, 1, '1st Year', 'C'),
(119, 1, '1st Year', 'D'),
(120, 1, '1st Year', 'E'),
(63, 1, '2nd Year', 'A'),
(125, 1, '2nd Year', 'B'),
(126, 1, '2nd Year', 'C'),
(64, 1, '3rd Year', 'A'),
(123, 1, '3rd Year', 'B'),
(124, 1, '3rd Year', 'C'),
(65, 1, '4th Year', 'A'),
(121, 1, '4th Year', 'B'),
(122, 1, '4th Year', 'C'),
(61, 2, '1st Year', 'A'),
(72, 2, '1st Year', 'B'),
(73, 2, '1st Year', 'C'),
(74, 2, '1st Year', 'D'),
(91, 2, '1st Year', 'E'),
(58, 2, '2nd Year', 'A'),
(71, 2, '2nd Year', 'B'),
(70, 2, '2nd Year', 'C'),
(60, 3, '4th Year', 'A'),
(66, 4, '1st Year', 'A'),
(67, 4, '2nd Year', 'A'),
(68, 4, '3rd Year', 'A'),
(86, 4, '3rd Year', 'B'),
(87, 4, '3rd Year', 'C'),
(88, 4, '3rd Year', 'D'),
(89, 4, '3rd Year', 'E'),
(90, 4, '3rd Year', 'F'),
(59, 4, '4th Year', 'A'),
(26, 5, '1st Year', 'A'),
(15, 5, '4th Year', 'A'),
(16, 5, '4th Year', 'B'),
(14, 7, '1st Year', 'A'),
(81, 8, '2nd Year', 'A'),
(82, 8, '2nd Year', 'B'),
(83, 8, '2nd Year', 'C'),
(84, 8, '2nd Year', 'D'),
(85, 8, '2nd Year', 'E'),
(75, 8, '4th Year', 'A'),
(76, 8, '4th Year', 'B'),
(77, 8, '4th Year', 'C'),
(78, 8, '4th Year', 'D'),
(79, 8, '4th Year', 'E'),
(80, 8, '4th Year', 'F'),
(92, 9, '1st Year', 'A'),
(93, 9, '1st Year', 'B'),
(94, 9, '1st Year', 'C'),
(95, 9, '1st Year', 'D'),
(96, 9, '1st Year', 'E'),
(107, 9, '4th Year', 'A'),
(108, 9, '4th Year', 'B'),
(109, 9, '4th Year', 'C'),
(110, 9, '4th Year', 'D'),
(111, 9, '4th Year', 'E'),
(112, 9, '4th Year', 'F'),
(113, 9, '4th Year', 'G'),
(114, 9, '4th Year', 'H'),
(27, 10, '4th Year', 'A'),
(97, 11, '1st Year', 'A'),
(98, 11, '1st Year', 'B'),
(99, 11, '1st Year', 'C'),
(100, 11, '1st Year', 'D'),
(102, 11, '1st Year', 'E'),
(103, 11, '1st Year', 'F'),
(104, 11, '1st Year', 'G'),
(105, 11, '1st Year', 'H'),
(106, 11, '1st Year', 'I'),
(115, 12, '1st Year', 'A'),
(116, 12, '1st Year', 'B'),
(19, 13, '4th Year', 'A'),
(37, 14, '1st Year', 'A'),
(127, 14, '1st Year', 'B'),
(128, 14, '1st Year', 'C'),
(130, 14, '1st Year', 'D'),
(214, 14, '1st Year', 'E'),
(281, 14, '1st Year', 'F'),
(282, 14, '1st Year', 'G'),
(283, 14, '1st Year', 'H'),
(284, 14, '1st Year', 'I'),
(285, 14, '1st Year', 'J'),
(286, 14, '1st Year', 'K'),
(288, 14, '1st Year', 'L'),
(289, 14, '1st Year', 'M'),
(290, 14, '1st Year', 'N'),
(291, 14, '1st Year', 'O'),
(292, 14, '1st Year', 'P'),
(131, 14, '2nd Year', 'A'),
(132, 14, '2nd Year', 'B'),
(133, 14, '2nd Year', 'C'),
(215, 14, '2nd Year', 'D'),
(216, 14, '2nd Year', 'E'),
(134, 15, '1st Year', 'A'),
(135, 15, '1st Year', 'B'),
(136, 15, '1st Year', 'C'),
(217, 15, '1st Year', 'D'),
(218, 15, '1st Year', 'E'),
(137, 15, '2nd Year', 'A'),
(138, 15, '2nd Year', 'B'),
(139, 15, '2nd Year', 'C'),
(140, 15, '2nd Year', 'D'),
(141, 15, '2nd Year', 'E'),
(142, 15, '3rd Year', 'A'),
(143, 15, '3rd Year', 'B'),
(144, 15, '3rd Year', 'C'),
(145, 15, '3rd Year', 'D'),
(146, 15, '3rd Year', 'E'),
(29, 15, '4th Year', 'A'),
(147, 15, '4th Year', 'B'),
(148, 15, '4th Year', 'C'),
(149, 15, '4th Year', 'D'),
(150, 15, '4th Year', 'E'),
(151, 15, '4th Year', 'F'),
(152, 16, '1st Year', 'A'),
(153, 16, '1st Year', 'B'),
(154, 16, '1st Year', 'C'),
(155, 16, '1st Year', 'D'),
(156, 16, '1st Year', 'E'),
(157, 16, '2nd Year', 'A'),
(158, 16, '2nd Year', 'B'),
(159, 16, '2nd Year', 'C'),
(160, 16, '2nd Year', 'D'),
(161, 16, '2nd Year', 'E'),
(162, 16, '3rd Year', 'A'),
(163, 16, '3rd Year', 'B'),
(164, 16, '3rd Year', 'C'),
(165, 16, '3rd Year', 'D'),
(166, 16, '3rd Year', 'E'),
(167, 16, '4th Year', 'A'),
(168, 16, '4th Year', 'B'),
(169, 16, '4th Year', 'C'),
(170, 16, '4th Year', 'D'),
(171, 16, '4th Year', 'E'),
(172, 17, '1st Year', 'A'),
(173, 17, '1st Year', 'B'),
(174, 17, '1st Year', 'C'),
(175, 17, '1st Year', 'D'),
(176, 17, '1st Year', 'E'),
(177, 17, '2nd Year', 'A'),
(178, 17, '2nd Year', 'B'),
(179, 17, '2nd Year', 'C'),
(180, 17, '2nd Year', 'D'),
(181, 17, '2nd Year', 'E'),
(182, 17, '3rd Year', 'A'),
(183, 17, '3rd Year', 'B'),
(184, 17, '3rd Year', 'C'),
(185, 17, '3rd Year', 'D'),
(186, 17, '3rd Year', 'E'),
(187, 17, '4th Year', 'A'),
(188, 17, '4th Year', 'B'),
(189, 17, '4th Year', 'C'),
(190, 17, '4th Year', 'D'),
(191, 17, '4th Year', 'E'),
(21, 18, '1st Year', 'A'),
(31, 18, '1st Year', 'B'),
(192, 18, '1st Year', 'C'),
(193, 18, '1st Year', 'D'),
(194, 18, '1st Year', 'E'),
(22, 18, '2nd Year', 'A'),
(32, 18, '2nd Year', 'B'),
(195, 18, '2nd Year', 'C'),
(196, 18, '2nd Year', 'D'),
(197, 18, '2nd Year', 'E'),
(33, 18, '3rd Year', 'A'),
(34, 18, '3rd Year', 'B'),
(198, 18, '3rd Year', 'C'),
(199, 18, '3rd Year', 'D'),
(200, 18, '3rd Year', 'E'),
(20, 18, '4th Year', 'A'),
(35, 18, '4th Year', 'B'),
(201, 18, '4th Year', 'C'),
(202, 18, '4th Year', 'D'),
(203, 18, '4th Year', 'E'),
(204, 19, '1st Year', 'A'),
(205, 19, '1st Year', 'B'),
(206, 19, '1st Year', 'C'),
(207, 19, '1st Year', 'D'),
(208, 19, '1st Year', 'E'),
(209, 19, '2nd Year', 'A'),
(210, 19, '2nd Year', 'B'),
(211, 19, '2nd Year', 'C'),
(212, 19, '2nd Year', 'D'),
(213, 19, '2nd Year', 'E'),
(17, 20, '4th Year', 'A'),
(18, 20, '4th Year', 'B'),
(239, 23, '1st Year', 'A'),
(240, 23, '1st Year', 'B'),
(241, 23, '1st Year', 'C'),
(242, 23, '1st Year', 'D'),
(243, 23, '1st Year', 'E'),
(36, 23, '4th Year', 'A'),
(244, 24, '1st Year', 'A'),
(245, 24, '1st Year', 'B'),
(246, 24, '1st Year', 'C'),
(247, 24, '1st Year', 'D'),
(248, 24, '1st Year', 'E'),
(28, 24, '4th Year', 'A'),
(30, 25, '1st Year', 'A'),
(249, 25, '1st Year', 'B'),
(250, 25, '1st Year', 'C'),
(251, 25, '1st Year', 'D'),
(252, 25, '1st Year', 'E'),
(253, 25, '2nd Year', 'A'),
(254, 25, '2nd Year', 'B'),
(255, 25, '2nd Year', 'C'),
(256, 25, '2nd Year', 'D'),
(257, 25, '2nd Year', 'E'),
(258, 25, '3rd Year', 'A'),
(259, 25, '3rd Year', 'B'),
(260, 25, '3rd Year', 'C'),
(261, 25, '3rd Year', 'D'),
(262, 25, '3rd Year', 'E'),
(38, 26, '1st Year', 'A'),
(39, 26, '1st Year', 'B'),
(263, 26, '1st Year', 'C'),
(264, 26, '1st Year', 'D'),
(265, 26, '1st Year', 'E'),
(40, 26, '2nd Year', 'A'),
(41, 26, '3rd Year', 'B'),
(276, 28, '1st Year', 'A'),
(277, 28, '1st Year', 'B'),
(278, 28, '1st Year', 'C'),
(279, 28, '1st Year', 'D'),
(280, 28, '1st Year', 'E'),
(219, 29, '1st Year', 'A'),
(220, 29, '1st Year', 'B'),
(221, 29, '1st Year', 'C'),
(222, 29, '1st Year', 'D'),
(223, 29, '1st Year', 'E'),
(224, 29, '2nd Year', 'A'),
(225, 29, '2nd Year', 'B'),
(226, 29, '2nd Year', 'C'),
(227, 29, '2nd Year', 'D'),
(228, 29, '2nd Year', 'E'),
(229, 29, '3rd Year', 'A'),
(230, 29, '3rd Year', 'B'),
(231, 29, '3rd Year', 'C'),
(232, 29, '3rd Year', 'D'),
(233, 29, '3rd Year', 'E'),
(234, 29, '4th Year', 'A'),
(235, 29, '4th Year', 'B'),
(236, 29, '4th Year', 'C'),
(237, 29, '4th Year', 'D'),
(238, 29, '4th Year', 'E'),
(266, 30, '1st Year', 'A'),
(267, 30, '1st Year', 'B'),
(268, 30, '1st Year', 'C'),
(269, 30, '1st Year', 'D'),
(270, 30, '1st Year', 'E'),
(271, 30, '2nd Year', 'A'),
(272, 30, '2nd Year', 'B'),
(273, 30, '2nd Year', 'C'),
(274, 30, '2nd Year', 'D'),
(275, 30, '2nd Year', 'E'),
(42, 31, '1st Year', 'A'),
(43, 31, '1st Year', 'B'),
(46, 31, '4th Year', 'A'),
(47, 31, '4th Year', 'B'),
(54, 31, '4th Year', 'C'),
(44, 32, '1st Year', 'A'),
(45, 32, '2nd Year', 'A'),
(48, 33, '1st Year', 'A'),
(50, 33, '1st Year', 'B'),
(51, 34, '1st Year', 'A'),
(56, 34, '1st Year', 'B'),
(49, 34, '2nd Year', 'A'),
(53, 35, '4th Year', 'A');

-- --------------------------------------------------------

--
-- Table structure for table `signatory_history`
--

CREATE TABLE `signatory_history` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `signatory_username` varchar(100) NOT NULL,
  `student_user` varchar(255) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `student_username` varchar(100) NOT NULL,
  `action_taken` enum('Approved','Rejected','Requires Action') NOT NULL,
  `reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `signatory_history`
--

INSERT INTO `signatory_history` (`id`, `application_id`, `signatory_username`, `student_user`, `action`, `student_username`, `action_taken`, `reason`, `remarks`, `action_date`) VALUES
(100, 72, 'migs', 'MA22013932', 'Approved', '', 'Approved', 'Requirement met and validated', 'BSIS_4C-G4-CHAPTER_1-5_GRP_4_A_Y_2025-2026_1772509679.pdf', '2026-03-03 03:49:46'),
(101, 0, 'paulovictoria', 'MA22013932', 'Approved', '', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-03-03 03:50:30'),
(102, 0, 'marissa', 'MA22013932', 'Approved', '', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-03-03 03:53:45'),
(103, 0, 'pau', 'MA22013930', 'Approved', '', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-03-25 08:26:55'),
(104, 0, 'paulovictoria', 'MA22013930', 'Approved', '', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-03-25 08:36:42'),
(105, 0, 'eliseo', 'MA22013931', 'Approved', '', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-03-29 05:57:10'),
(106, 0, 'paulovictoria', '0123546', 'Approved', '', 'Approved', 'Auto-approved: no file required', 'N/A', '2026-04-17 04:37:02');

-- --------------------------------------------------------

--
-- Table structure for table `signatory_prerequisites`
--

CREATE TABLE `signatory_prerequisites` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `before_type` varchar(100) NOT NULL,
  `signatory_type` varchar(100) NOT NULL,
  `admin_enabled` tinyint(1) DEFAULT 1,
  `signatory_enabled` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `signatory_prerequisites`
--

INSERT INTO `signatory_prerequisites` (`id`, `course_id`, `before_type`, `signatory_type`, `admin_enabled`, `signatory_enabled`, `created_at`) VALUES
(6, 1, 'PTCA', 'Class Adviser', 1, 0, '2026-02-22 04:30:23'),
(16, 12, 'Scholarship Office', 'Student Government (SG)', 1, 0, '2026-02-24 04:56:25'),
(17, 12, 'Class Adviser', 'Student Government (SG)', 1, 0, '2026-02-24 04:56:25'),
(18, 12, 'Scholarship Office', 'Class Adviser', 1, 1, '2026-02-24 04:56:38'),
(19, 12, 'Scholarship Office', 'Program Head', 1, 1, '2026-02-24 06:33:48'),
(20, 12, 'PTCA', 'Program Head', 1, 1, '2026-02-24 06:33:48'),
(21, 12, 'Student Government (SG)', 'Program Head', 1, 1, '2026-02-24 06:33:48'),
(22, 12, 'Class Adviser', 'Program Head', 1, 1, '2026-02-24 06:33:48'),
(23, 1, 'Class Adviser', 'Program Head', 1, 1, '2026-03-02 22:29:41'),
(29, 2, 'Librarian', 'Program Head', 1, 1, '2026-03-24 02:39:22'),
(30, 2, 'Student Government (SG)', 'Program Head', 1, 1, '2026-03-24 02:39:22'),
(31, 2, 'PTCA', 'Program Head', 1, 1, '2026-03-24 02:39:22'),
(32, 2, 'Class Adviser', 'Program Head', 1, 1, '2026-03-24 02:39:22'),
(33, 2, 'Librarian', 'Student Government (SG)', 1, 0, '2026-03-24 02:39:57'),
(34, 2, 'PTCA', 'Student Government (SG)', 1, 0, '2026-03-24 02:39:57'),
(35, 2, 'Class Adviser', 'Student Government (SG)', 1, 0, '2026-03-24 02:39:57'),
(36, 2, 'Librarian', 'Class Adviser', 1, 0, '2026-03-24 02:40:05'),
(37, 2, 'PTCA', 'Class Adviser', 1, 0, '2026-03-24 02:40:05');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `enrollment_status` varchar(50) DEFAULT NULL,
  `course_section` varchar(50) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `street` varchar(100) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_requirements`
--

CREATE TABLE `student_requirements` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `requirement_id` int(11) NOT NULL,
  `status` enum('pending','cleared') DEFAULT 'pending',
  `date_cleared` datetime DEFAULT NULL,
  `signed_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `current_semester` varchar(20) DEFAULT NULL,
  `current_school_year` varchar(20) DEFAULT NULL,
  `requirement_lock` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `current_semester`, `current_school_year`, `requirement_lock`) VALUES
(1, '1st Semester', '2028-2029', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `password_last_updated` datetime DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `year` varchar(20) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `adviser_username` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','signatory','admin','sg_officer') NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `signatory_type` varchar(100) DEFAULT NULL,
  `department` varchar(500) DEFAULT NULL,
  `course` varchar(255) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `school_year` varchar(50) DEFAULT NULL,
  `final_clearance_status` varchar(50) NOT NULL DEFAULT 'not_requested',
  `status` enum('active','inactive') DEFAULT 'active',
  `admin_approved` tinyint(1) DEFAULT 0,
  `admin_messaged` tinyint(1) DEFAULT 0,
  `admin_message_text` text DEFAULT NULL,
  `admin_message_sent_at` datetime DEFAULT NULL,
  `admin_approved_at` datetime DEFAULT NULL,
  `admin_approved_by` varchar(50) DEFAULT NULL,
  `can_add_admin` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `sex`, `email`, `email_verified`, `password_last_updated`, `course_id`, `year`, `section`, `adviser_username`, `password`, `role`, `profile_pic`, `signatory_type`, `department`, `course`, `student_id`, `birthdate`, `contact`, `street`, `city`, `province`, `semester`, `school_year`, `final_clearance_status`, `status`, `admin_approved`, `admin_messaged`, `admin_message_text`, `admin_message_sent_at`, `admin_approved_at`, `admin_approved_by`, `can_add_admin`) VALUES
(1, 'adminkaren', 'Karen-Ann Rose Payumo', NULL, 'russel.begelia@bpc.edu.ph', 0, NULL, NULL, NULL, NULL, NULL, '$2y$10$MORo3BfLcccQNdcZIFWrcejXaeCwPuVERJ0Vn.J3j5Mnht/AxRwqm', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(105, 'MA22013930', 'Marlon Marquina', NULL, 'makamimayumi@gmail.com', 1, '2026-03-25 04:25:13', NULL, '4th Year', 'C', NULL, '$2y$10$diZQirxnjmGOUu720ry87eLNiO4Pbsfb54EGtpT1xl9Zp7JzZLHu.', 'student', NULL, '', '', 'BSIS', NULL, NULL, NULL, NULL, NULL, NULL, '1st Semester', '2028-2029', 'not_requested', 'active', 0, 0, NULL, NULL, '2026-03-25 04:41:02', NULL, 1),
(106, 'paulovictoria', 'Paulo A. Victoria, MIT', NULL, 'alexma000499@gmail.com', 1, '2026-03-25 04:30:25', NULL, '', '', NULL, '$2y$10$jGorj7TD1bJrhehETzouQOjbvKwub76doCI52LbPEPBUtOiR6IV2i', 'signatory', NULL, 'Program Head', 'ACT,BSIS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(107, 'melodydejesus', 'Melody De Jesus, MM', NULL, 'melody@yahoo.com', 0, NULL, NULL, '', '', NULL, '$2y$10$gvU1WpIo5H.6ocRxDo/17.3HWSZkA.LQGDuu8tbh3BqaywdGoNhkW', 'signatory', NULL, 'Program Head', 'BSOM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(108, 'eliseo', 'Eliseo Amaninche, PhD', NULL, 'marlonmarquina8@gmail.com', 1, '2026-03-28 23:26:46', NULL, '', '', NULL, '$2y$10$hVsnOtPwuiKfjPRlBNUnYeXudJ9pbjQSanLeFUOZJM8QgHyWZrw8u', 'signatory', NULL, 'Program Head', 'BTVTED', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(109, 'marites', 'Marites Morillo, MBA', NULL, 'marites@yahoo.com', 0, NULL, NULL, '', '', NULL, '$2y$10$WkiI0kn18.6XOpgD/gMYme19PxpPnB9KR1suz.x36KVvJgk1dNEnq', 'signatory', NULL, 'Program Head', 'BSAIS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(110, 'luzdasma', 'Ma. Luz Dasmariñas, EdD., PhD., DBA, LCB, LPT', NULL, 'luzdasma@yahoo.com', 0, '2026-04-14 02:37:41', NULL, '', '', NULL, '$2y$10$isSTgDfVIj1hVFjdS6.6A.Zm.xbqFUDkiu0DRpPP/IDUZexOETOZm', 'signatory', NULL, 'Program Head', 'BSCA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(111, 'melbau', 'Melanie Bautista', NULL, 'melbau@yahoo.com', 0, NULL, NULL, '', '', NULL, '$2y$10$ggODXRr5FKaAafHOE9dv5e3z591UA9t6hnl3FvGc34otfsSMDKuF.', 'signatory', NULL, 'Program Head', 'CCS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(112, 'meloliver', 'Mel-Oliver Balagtas', NULL, 'meloliver@yahoo.com', 0, NULL, NULL, '', '', NULL, '$2y$10$x0WrMhrWbB/UcXWW78bY6O2ezG1ndnwj44wy/rmF/xhp0SHLoNHYe', 'signatory', NULL, 'Program Head', 'DHRMT', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(113, 'annesantos', 'Anne Santos, MBA', NULL, 'anne@yahoo.com', 0, NULL, NULL, '', '', NULL, '$2y$10$uPi8D6g3azY.J7izX6Mf4u00WPzyI7il.WZ5V8naMjM7QS8/ufkz.', 'signatory', NULL, 'Scholarship Office', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(114, 'marissa', 'Marissa Mendoza', NULL, 'marissa@yahoo.com', 0, NULL, NULL, '', '', NULL, '$2y$10$J4t5gqjswx5EmimOkfgt3euv3e5TciOzpl.jxMj7fCOXD2C9MDoKi', 'signatory', NULL, 'Student Government (SG)', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(115, 'jeansotto', 'Jean Sotto, PhD', NULL, 'jeansotto@yahoo.com', 0, NULL, NULL, '', '', NULL, '$2y$10$0XE0/OV3MjJw/6kP6t6sYuDqz36cr.hWYCmRaw4hfjs07TZLQSYu.', 'signatory', NULL, 'Research Office', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(116, 'samuel', 'Samuel Calubaquib', NULL, 'samuelcalubaquib@yahoo.com', 0, NULL, NULL, '', '', NULL, '$2y$10$sd.r2KOUQwIML04YML1R2eaKVefXRrGzU6OYktQqkB9WUjO7GQxPG', 'signatory', NULL, 'PTCA', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(117, 'jennylyncruz', 'Jennylyn Cruz', NULL, 'jennylyncruz@yahoo.com', 0, NULL, NULL, NULL, NULL, NULL, '$2y$10$EaV8AnZCMX3DxGcPXp/.i.KetAXCITKnBVrEy7PvSo4co9TtwCuUq', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 0),
(118, 'pau', 'Paulo Victoria', NULL, 'paulo.victoria@bpc.edu.ph', 0, '2026-03-25 04:06:25', NULL, '4th Year', 'BSIS|4th Year|C', NULL, '$2y$10$Y0z8tGP67ioyTIZ1P7dkhe7yl/JvYV6BEQR0BnY5Pj193LOiJRuyi', 'signatory', NULL, 'Class Adviser', 'BSIS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1),
(119, '0123546', 'josh try today', NULL, 'jlastimado123123@gmail.com', 0, '2026-04-17 00:34:49', NULL, '1st Year', 'A', NULL, '$2y$10$VZRJG.zolmtxOdODztVoqunJUMa8i3nyKYYtRsnfv.13g.fH6Co2W', 'student', NULL, '', '', 'ACT', NULL, NULL, NULL, NULL, NULL, NULL, '1st Semester', '2028-2029', 'cleared', 'active', 1, 0, NULL, NULL, '2026-04-17 00:44:17', NULL, 1),
(120, 'MA22013931', 'Alexis Garcia', NULL, 'duaytrevor@gmail.com', 1, '2026-03-29 01:54:44', NULL, '4th Year', 'A', NULL, '$2y$10$IwGUj4egMp2oEm71UMebKOfPmLkSoPEXMmZ8NMQdvu8LY.QdSg9Km', 'student', NULL, '', '', 'BTVTED', NULL, NULL, NULL, NULL, NULL, NULL, '1st Semester', '2028-2029', 'not_requested', 'active', 0, 0, NULL, NULL, NULL, NULL, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `announcement_id` (`announcement_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `application_rejection_log`
--
ALTER TABLE `application_rejection_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_application_id` (`application_id`);

--
-- Indexes for table `archived_applications`
--
ALTER TABLE `archived_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `archived_course_requirements`
--
ALTER TABLE `archived_course_requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `archived_draft_requirements`
--
ALTER TABLE `archived_draft_requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `archived_signatory_history`
--
ALTER TABLE `archived_signatory_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `archived_student_status`
--
ALTER TABLE `archived_student_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `archived_user_accounts`
--
ALTER TABLE `archived_user_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clearance_requests`
--
ALTER TABLE `clearance_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clearance_requirements`
--
ALTER TABLE `clearance_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_name` (`course_name`);

--
-- Indexes for table `course_requirements`
--
ALTER TABLE `course_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `requirement_id` (`requirement_id`),
  ADD KEY `idx_signatory_id` (`signatory_id`),
  ADD KEY `idx_document_type_id` (`document_type_id`(768));

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `draft_requirements`
--
ALTER TABLE `draft_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_draft_req_req` (`requirement_id`),
  ADD KEY `fk_draft_req_sig` (`signatory_id`),
  ADD KEY `fk_draft_req_doctype` (`document_type_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rejection_reasons`
--
ALTER TABLE `rejection_reasons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_signatory_id` (`signatory_id`);

--
-- Indexes for table `requirement_library`
--
ALTER TABLE `requirement_library`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_signatory_id` (`signatory_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section` (`course_id`,`year`,`section_name`);

--
-- Indexes for table `signatory_history`
--
ALTER TABLE `signatory_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `student_username` (`student_username`);

--
-- Indexes for table `signatory_prerequisites`
--
ALTER TABLE `signatory_prerequisites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rule` (`course_id`,`before_type`,`signatory_type`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `student_requirements`
--
ALTER TABLE `student_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `requirement_id` (`requirement_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_clearance_status` (`final_clearance_status`,`admin_approved`),
  ADD KEY `idx_user_role_status` (`role`,`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `application_rejection_log`
--
ALTER TABLE `application_rejection_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `archived_applications`
--
ALTER TABLE `archived_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `archived_course_requirements`
--
ALTER TABLE `archived_course_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `archived_draft_requirements`
--
ALTER TABLE `archived_draft_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `archived_signatory_history`
--
ALTER TABLE `archived_signatory_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `archived_student_status`
--
ALTER TABLE `archived_student_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `archived_user_accounts`
--
ALTER TABLE `archived_user_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `clearance_requests`
--
ALTER TABLE `clearance_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clearance_requirements`
--
ALTER TABLE `clearance_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `course_requirements`
--
ALTER TABLE `course_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `draft_requirements`
--
ALTER TABLE `draft_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `rejection_reasons`
--
ALTER TABLE `rejection_reasons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `requirement_library`
--
ALTER TABLE `requirement_library`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=293;

--
-- AUTO_INCREMENT for table `signatory_history`
--
ALTER TABLE `signatory_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `signatory_prerequisites`
--
ALTER TABLE `signatory_prerequisites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_requirements`
--
ALTER TABLE `student_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clearance_requirements`
--
ALTER TABLE `clearance_requirements`
  ADD CONSTRAINT `clearance_requirements_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
