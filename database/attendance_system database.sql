-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 16, 2025 at 07:18 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `attendance_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_notes`
--

CREATE TABLE `admin_notes` (
  `note_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `note_date` date DEFAULT NULL,
  `note_title` varchar(255) DEFAULT NULL,
  `note_content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_notes`
--

INSERT INTO `admin_notes` (`note_id`, `admin_id`, `note_date`, `note_title`, `note_content`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-01-26', 'admin note', 'test etst stetsetsettest test\r\ntest', '2025-01-25 16:10:27', '2025-01-25 16:10:27'),
(3, 1, '2025-01-22', 'test test ', 'testsetsetsetsetsetsettsetsetttsetsetste test\ntesting\ntest', '2025-01-25 16:11:25', '2025-01-25 16:19:03'),
(4, 1, '2025-01-22', 'asdfsdfsadf', 'asdfasdf asdfasdf', '2025-01-25 16:12:04', '2025-01-25 16:12:04'),
(5, 1, '2025-01-23', 'Note1', 'The quick brown fox', '2025-01-25 16:18:46', '2025-01-25 16:18:46'),
(6, 1, '2025-01-27', 'To do', 'Breakfast', '2025-01-27 01:26:38', '2025-01-27 01:26:38'),
(8, 1, NULL, 'Test', 'Testest', '2025-07-13 08:01:00', '2025-07-13 08:01:00'),
(9, 1, '2025-07-02', 'Test', 'Testsdaf ddddddddddd', '2025-07-13 08:02:28', '2025-07-13 08:47:55'),
(10, 1, '2025-07-02', 'Test 1', 'dsdsdsd', '2025-07-13 08:14:10', '2025-07-13 08:48:06'),
(12, 1, '2025-07-03', 'testset', 'testsetsetset', '2025-07-13 08:40:24', '2025-07-13 08:40:24'),
(13, 1, '2025-07-03', 'testse', 'tse setset setset', '2025-07-13 08:40:46', '2025-07-13 08:40:46'),
(14, 1, '2025-07-03', 'testse', 'setsesetset', '2025-07-13 08:40:51', '2025-07-13 08:40:51'),
(17, 1, '2025-08-11', 'Testasd', 'sdfgsdfg', '2025-08-11 14:01:50', '2025-08-11 14:02:13'),
(18, 1, '2025-08-12', 'hehe boi', 'testsetsetsets', '2025-08-13 02:49:07', '2025-08-13 02:49:07');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `in_time` datetime DEFAULT NULL,
  `out_time` datetime DEFAULT NULL,
  `status` enum('Present','Absent','Late') DEFAULT 'Absent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `student_id`, `schedule_id`, `attendance_date`, `in_time`, `out_time`, `status`, `created_at`, `updated_at`) VALUES
(3, '22-00222', 67, '2025-07-08', NULL, NULL, 'Present', '2025-07-08 02:21:12', '2025-07-08 02:22:25'),
(6, '22-00222', 68, '2025-07-13', NULL, NULL, 'Present', '2025-07-13 14:15:49', '2025-07-13 14:34:06'),
(7, '99911123232', 68, '2025-07-13', NULL, NULL, 'Present', '2025-07-13 14:15:49', '2025-07-13 14:34:06'),
(11, '22-00222', 69, '2025-08-11', '2025-08-11 23:02:58', '2025-08-11 23:06:26', 'Present', '2025-08-11 15:02:58', '2025-08-11 15:06:26'),
(12, '99911123232', 69, '2025-08-11', '2025-08-11 23:06:07', '2025-08-11 23:06:30', 'Present', '2025-08-11 15:06:07', '2025-08-11 15:06:30'),
(15, '22-00222', 67, '2025-08-12', '2025-08-12 08:07:30', '2025-08-12 08:27:41', 'Present', '2025-08-12 00:07:30', '2025-08-13 03:29:09'),
(18, '99911123232', 67, '2025-08-12', '2025-08-12 08:27:37', NULL, 'Present', '2025-08-12 00:27:37', '2025-08-13 03:33:36');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `day_of_week` varchar(10) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Open','Closed') DEFAULT 'Closed',
  `school_year` varchar(9) NOT NULL COMMENT 'Format: 2024-2025',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`schedule_id`, `subject_id`, `section_id`, `teacher_id`, `day_of_week`, `start_time`, `end_time`, `status`, `school_year`, `created_at`, `updated_at`) VALUES
(67, 11, 18, 5, 'Tuesday', '08:00:00', '09:00:00', 'Open', '2024-2025', '2025-07-08 00:11:45', '2025-08-12 00:19:29'),
(68, 5, 18, 5, 'Sunday', '19:30:00', '20:30:00', 'Closed', '2024-2025', '2025-07-13 11:34:34', '2025-08-13 03:16:14'),
(69, 9, 18, 5, 'Monday', '23:01:00', '23:59:00', 'Closed', '2024-2025', '2025-08-11 15:01:59', '2025-08-12 00:06:00'),
(70, 14, 18, 5, 'Tuesday', '12:34:00', '13:34:00', 'Open', '2024-2025', '2025-08-12 00:35:13', '2025-08-12 00:35:24');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL,
  `section_name` varchar(20) NOT NULL,
  `grade_level` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`section_id`, `section_name`, `grade_level`) VALUES
(10, 'A', 1),
(13, 'A', 2),
(14, 'B', 10),
(16, 'B', 1),
(17, 'C', 1),
(18, 'A', 10);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` varchar(20) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `grade_level` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `firstname`, `lastname`, `middlename`, `email`, `grade_level`, `section_id`) VALUES
('22-00222', 'Marylouu', 'Blanco', 'B', 'marylou.blanco@hercorcollege.edu.ph', 10, 18),
('99911123232', 'Benedict', 'Clarito', 'A', 'benedictarby.clarito@hercorcollege.edu.ph', 10, 18);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_name`, `description`) VALUES
(1, 'Math', ''),
(3, 'Science', NULL),
(5, 'Filipino', ''),
(7, 'ICT', NULL),
(9, 'Thesis I', ''),
(11, 'English', 'English example descriptionsss???'),
(13, 'PED 101', ''),
(14, 'Thesis II', 'Thesis 2 final defense');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(50) NOT NULL,
  `middlename` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `firstname`, `lastname`, `middlename`) VALUES
(5, 'Jhon', 'Martin', 'De');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_notes`
--

CREATE TABLE `teacher_notes` (
  `note_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `note_date` date DEFAULT NULL,
  `note_title` varchar(255) DEFAULT NULL,
  `note_content` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_notes`
--

INSERT INTO `teacher_notes` (`note_id`, `teacher_id`, `note_date`, `note_title`, `note_content`, `created_at`, `updated_at`) VALUES
(1, 5, '2025-01-28', 'Attendance System Deadline', 'Xampp configured, settings confirmed. user trained', '2025-01-25 15:43:01', '2025-01-25 15:43:01'),
(2, 5, '2025-01-27', 'Deliver System', 'Train User', '2025-01-25 15:46:40', '2025-01-25 15:46:40'),
(3, 5, '2025-01-26', 'Finalize', 'Finalize System plus test', '2025-01-25 15:49:02', '2025-01-25 15:49:02'),
(4, 5, '2025-01-25', 'Calendar Creation', 'Calendar Working', '2025-01-25 15:52:20', '2025-01-25 15:52:20'),
(5, 5, '2025-01-24', 'Test', 'test', '2025-01-25 15:57:59', '2025-01-25 15:57:59'),
(6, 5, '2025-01-23', 'test 1 ', 'teset 12asdsd asdfasdf', '2025-01-25 15:58:10', '2025-01-25 15:58:10'),
(8, 5, '2025-01-28', 'Finalize', 'asdf asdf adf  lorem ipsum', '2025-01-25 16:01:18', '2025-01-25 16:01:18'),
(17, 5, '2025-07-07', 'TEst', 'testsetset', '2025-07-13 09:51:55', '2025-07-13 09:51:55'),
(23, 5, '2025-07-02', 'Test', 'test test', '2025-07-13 11:56:25', '2025-07-13 11:56:25'),
(24, 5, '2025-08-13', 'test', 'testsets', '2025-08-13 03:22:17', '2025-08-13 03:22:17');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_sections`
--

CREATE TABLE `teacher_sections` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_sections`
--

INSERT INTO `teacher_sections` (`id`, `teacher_id`, `section_id`, `created_at`) VALUES
(101, 5, 18, '2025-08-13 03:15:49'),
(102, 5, 14, '2025-08-13 03:15:49');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `reference_id` varchar(20) DEFAULT NULL COMMENT 'student_id or teacher_id',
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_code` varchar(6) DEFAULT NULL,
  `code_expiry` datetime DEFAULT NULL,
  `verification_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `reference_id`, `email_verified`, `verification_code`, `code_expiry`, `verification_date`) VALUES
(1, 'admin', '$2y$10$jzPrDhufbRWeGZxAG.dXreelEnoNC0O90KIBasN4iO46fuXzgG.4C', 'admin', NULL, 0, NULL, NULL, NULL),
(10, 'jhon.martin', '$2y$10$qzr2edV1I5rGz3u1I5gET.bBlSEc88EH3no8WRHYknb23UgqE7MAW', 'teacher', '5', 0, NULL, NULL, NULL),
(58, 'benedict.clarito', '$2y$10$uM1qycWw5xyaEu4mXdb27uqn7uejmtzwWflc/wVwF9Q4cGspORmgu', 'student', '99911123232', 0, '235189', '2025-08-12 08:21:21', '2025-07-05 17:11:43'),
(59, 'marylou.blanco', '$2y$10$Gnc33pLYcRK60YnlT/VtvenGFTWWnuiveSQ2jPi5zkeKBelF.K2ga', 'student', '22-00222', 0, '938230', '2025-08-12 08:20:52', '2025-07-05 17:22:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notes`
--
ALTER TABLE `admin_notes`
  ADD PRIMARY KEY (`note_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `uniq_student_schedule_date` (`student_id`,`schedule_id`,`attendance_date`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `idx_attendance_date` (`attendance_date`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `idx_schedule_day_time` (`day_of_week`,`start_time`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`section_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `idx_student_name` (`lastname`,`firstname`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_name` (`subject_name`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`);

--
-- Indexes for table `teacher_notes`
--
ALTER TABLE `teacher_notes`
  ADD PRIMARY KEY (`note_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_section_unique` (`teacher_id`,`section_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notes`
--
ALTER TABLE `admin_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `teacher_notes`
--
ALTER TABLE `teacher_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`);

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`),
  ADD CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`);

--
-- Constraints for table `teacher_notes`
--
ALTER TABLE `teacher_notes`
  ADD CONSTRAINT `teacher_notes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `teacher_sections`
--
ALTER TABLE `teacher_sections`
  ADD CONSTRAINT `teacher_sections_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_sections_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
