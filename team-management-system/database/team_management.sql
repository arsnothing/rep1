-- =====================================================================
--  سامانه مدیریت منابع انسانی — پایگاه داده کامل (نسخه ۶.۳)
--  فقط همین یک فایل را import کنید:
--    ۱) در phpMyAdmin یک دیتابیس با نام team_management_v2 بسازید (utf8mb4_unicode_ci)
--    ۲) دیتابیس را انتخاب کنید ← Import ← همین فایل
--  ورود: admin  (رمز همان رمز قبلی سامانه)
--  داده نمونه: ۶۲ عنصر (هر رسته: ۱ فرمانده دسته، ۳ فرمانده گروه، ۹ سر تیم، ۱۸ عنصر)
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TRIGGER IF EXISTS `trg_personnel_validate_bi`;
DROP TRIGGER IF EXISTS `trg_personnel_validate_bu`;
DROP TABLE IF EXISTS `training_applicants`, `training_documents`, `training_records`,
  `personnel_order_equipment`, `personnel_order_documents`, `personnel_order_members`, `personnel_orders`,
  `personnel_equipment`, `equipment_stock`, `financial_transaction_items`, `financial_transactions`,
  `disciplinary_reports`, `disciplinary_report_subjects`, `personnel_dismissals`, `personnel_documents`,
  `personnel_history`, `personnel`, `city_districts`, `cities`, `provinces`, `teams`, `personnel_groups`,
  `positions`, `category_numbers`, `category_types`, `users`, `team_locations`, `migration_quarantine`;

-- ---------------------------------------------------------------------
-- کاربران
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` enum('super_admin','hr_manager','info_commander','ops_commander') NOT NULL DEFAULT 'hr_manager',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `role`, `is_active`) VALUES
(1, 'admin', '$2y$12$5ZmUzwKSGfhYxkpD2anFVe0uDMH0WLDAzccmDwkgV5XMvv.d0WFM.', 'مدیر سامانه', 'super_admin', 1);

-- ---------------------------------------------------------------------
-- ساختار سازمانی: رسته، دسته، گروه، تیم، سمت
-- ---------------------------------------------------------------------
CREATE TABLE `category_types` (
  `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_key` varchar(40) NOT NULL,
  `category_name` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category_types_key` (`category_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `category_types` (`id`, `category_key`, `category_name`, `is_active`) VALUES
(1, 'information', 'رسته اطلاعاتی', 1),
(2, 'operations', 'رسته عملیاتی', 1);

CREATE TABLE `category_numbers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_type_id` tinyint(3) UNSIGNED NOT NULL,
  `unit_number` varchar(10) NOT NULL,
  `unit_name` varchar(80) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category_number` (`category_type_id`,`unit_number`),
  UNIQUE KEY `uq_category_number_pair` (`id`,`category_type_id`),
  CONSTRAINT `fk_category_number_type` FOREIGN KEY (`category_type_id`) REFERENCES `category_types` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `category_numbers` (`id`, `category_type_id`, `unit_number`, `unit_name`, `is_active`) VALUES
(1, 1, '1', 'دسته ۱', 1),
(2, 2, '1', 'دسته ۱', 1);

CREATE TABLE `personnel_groups` (
  `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_number` tinyint(3) UNSIGNED NOT NULL,
  `group_name` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personnel_groups_number` (`group_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `personnel_groups` (`id`, `group_number`, `group_name`, `is_active`) VALUES
(1, 1, 'گروه ۱', 1),
(2, 2, 'گروه ۲', 1),
(3, 3, 'گروه ۳', 1);

CREATE TABLE `teams` (
  `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_type_id` tinyint(3) UNSIGNED NOT NULL,
  `team_number` tinyint(3) UNSIGNED NOT NULL,
  `team_name` varchar(40) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_team_type_number` (`category_type_id`,`team_number`),
  UNIQUE KEY `uq_team_pair` (`id`,`category_type_id`),
  CONSTRAINT `fk_team_type` FOREIGN KEY (`category_type_id`) REFERENCES `category_types` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `teams` (`id`, `category_type_id`, `team_number`, `team_name`, `is_active`) VALUES
(1, 1, 1, 'پیاده', 1),
(2, 1, 2, 'موتوری', 1),
(3, 1, 3, 'خودرویی', 1),
(4, 2, 1, 'تیم ۱', 1),
(5, 2, 2, 'تیم ۲', 1),
(6, 2, 3, 'تیم ۳', 1);

CREATE TABLE `positions` (
  `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `position_key` varchar(40) NOT NULL,
  `position_name` varchar(80) NOT NULL,
  `allows_team` tinyint(1) NOT NULL DEFAULT 0,
  `allows_group` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_positions_key` (`position_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `positions` (`id`, `position_key`, `position_name`, `allows_team`, `allows_group`, `is_active`) VALUES
(1, 'unit_commander', 'فرمانده دسته', 0, 0, 1),
(2, 'group_commander', 'فرمانده گروه', 0, 1, 1),
(3, 'team_leader', 'سر تیم', 1, 1, 1),
(4, 'element', 'عنصر', 1, 1, 1);

-- ---------------------------------------------------------------------
-- جغرافیا: استان، شهرستان، منطقه
-- ---------------------------------------------------------------------
CREATE TABLE `provinces` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `province_name` varchar(100) NOT NULL,
  `province_code` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_province_name` (`province_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `provinces` (`id`, `province_name`, `province_code`, `is_active`) VALUES
(1, 'آذربایجان شرقی', 'EAZ', 1),
(2, 'آذربایجان غربی', 'WAZ', 1),
(3, 'اردبیل', 'ARD', 1),
(4, 'اصفهان', 'ISF', 1),
(5, 'البرز', 'ALB', 1),
(6, 'ایلام', 'ILM', 1),
(7, 'بوشهر', 'BUS', 1),
(8, 'تهران', 'TEH', 1),
(9, 'چهارمحال و بختیاری', 'CHB', 1),
(10, 'خراسان جنوبی', 'SKH', 1),
(11, 'خراسان رضوی', 'RKH', 1),
(12, 'خراسان شمالی', 'NKH', 1),
(13, 'خوزستان', 'KHU', 1),
(14, 'زنجان', 'ZAN', 1),
(15, 'سمنان', 'SEM', 1),
(16, 'سیستان و بلوچستان', 'SBL', 1),
(17, 'فارس', 'FAR', 1),
(18, 'قزوین', 'QAZ', 1),
(19, 'قم', 'QOM', 1),
(20, 'کردستان', 'KOR', 1),
(21, 'کرمان', 'KER', 1),
(22, 'کرمانشاه', 'KRM', 1),
(23, 'کهگیلویه و بویراحمد', 'KBA', 1),
(24, 'گلستان', 'GLS', 1),
(25, 'گیلان', 'GIL', 1),
(26, 'لرستان', 'LOR', 1),
(27, 'مازندران', 'MAZ', 1),
(28, 'مرکزی', 'MKZ', 1),
(29, 'هرمزگان', 'HRZ', 1),
(30, 'همدان', 'HAM', 1),
(31, 'یزد', 'YAZ', 1);

CREATE TABLE `cities` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `province_id` smallint(5) UNSIGNED NOT NULL,
  `city_name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_city_province_name` (`province_id`,`city_name`),
  UNIQUE KEY `uq_city_pair` (`id`,`province_id`),
  CONSTRAINT `fk_city_province` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cities` (`id`, `province_id`, `city_name`, `is_active`) VALUES
(1, 8, 'تهران', 1),
(2, 8, 'ری', 1),
(3, 8, 'شمیرانات', 1),
(4, 8, 'اسلامشهر', 1),
(5, 8, 'شهریار', 1),
(6, 8, 'رباط‌کریم', 1),
(7, 8, 'بهارستان', 1),
(8, 8, 'ملارد', 1),
(9, 8, 'قدس', 1),
(10, 8, 'پاکدشت', 1),
(11, 8, 'پیشوا', 1),
(12, 8, 'ورامین', 1),
(13, 8, 'قرچک', 1),
(14, 8, 'دماوند', 1),
(15, 8, 'فیروزکوه', 1),
(16, 8, 'پردیس', 1);

CREATE TABLE `city_districts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `city_id` int(10) UNSIGNED NOT NULL,
  `district_number` tinyint(3) UNSIGNED NOT NULL,
  `district_name` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_city_district` (`city_id`,`district_number`),
  CONSTRAINT `fk_district_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `city_districts` (`id`, `city_id`, `district_number`, `district_name`, `is_active`) VALUES
(1, 1, 1, 'منطقه 1', 1),
(2, 1, 2, 'منطقه 2', 1),
(3, 1, 3, 'منطقه 3', 1),
(4, 1, 4, 'منطقه 4', 1),
(5, 1, 5, 'منطقه 5', 1),
(6, 1, 6, 'منطقه 6', 1),
(7, 1, 7, 'منطقه 7', 1),
(8, 1, 8, 'منطقه 8', 1),
(9, 1, 9, 'منطقه 9', 1),
(10, 1, 10, 'منطقه 10', 1),
(11, 1, 11, 'منطقه 11', 1),
(12, 1, 12, 'منطقه 12', 1),
(13, 1, 13, 'منطقه 13', 1),
(14, 1, 14, 'منطقه 14', 1),
(15, 1, 15, 'منطقه 15', 1),
(16, 1, 16, 'منطقه 16', 1),
(17, 1, 17, 'منطقه 17', 1),
(18, 1, 18, 'منطقه 18', 1),
(19, 1, 19, 'منطقه 19', 1),
(20, 1, 20, 'منطقه 20', 1),
(21, 1, 21, 'منطقه 21', 1),
(22, 1, 22, 'منطقه 22', 1);

-- ---------------------------------------------------------------------
-- عناصر
-- ---------------------------------------------------------------------
CREATE TABLE `personnel` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `full_name` varchar(210) GENERATED ALWAYS AS (trim(concat(`first_name`,' ',`last_name`))) STORED,
  `source_full_name` varchar(210) DEFAULT NULL,
  `national_id` char(10) NOT NULL,
  `position_id` tinyint(3) UNSIGNED NOT NULL,
  `category_type_id` tinyint(3) UNSIGNED NOT NULL,
  `category_number_id` int(10) UNSIGNED NOT NULL,
  `province_id` smallint(5) UNSIGNED NOT NULL,
  `city_id` int(10) UNSIGNED NOT NULL,
  `district_id` int(10) UNSIGNED DEFAULT NULL,
  `commander_number` varchar(30) DEFAULT NULL,
  `group_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `team_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `marital_status` enum('single','married','separated') DEFAULT NULL,
  `education_status` varchar(50) DEFAULT NULL,
  `residence_address` text DEFAULT NULL,
  `mobile` varchar(20) NOT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `organizational_code` varchar(30) DEFAULT NULL,
  `secondary_job` varchar(150) DEFAULT NULL,
  `secondary_job_address` text DEFAULT NULL,
  `secondary_job_phone` varchar(20) DEFAULT NULL,
  `iban` char(26) DEFAULT NULL,
  `iban_holder_name` varchar(150) DEFAULT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `personnel_status` enum('active','dismissed') NOT NULL DEFAULT 'active',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `command_scope` varchar(160) GENERATED ALWAYS AS (case
      when `position_id` = 1 then concat('U:',`province_id`,':',`city_id`,':',`category_type_id`,':',`category_number_id`)
      when `position_id` = 2 then concat('G:',`province_id`,':',`city_id`,':',`category_type_id`,':',`category_number_id`,':',`group_id`)
      when `position_id` = 3 then concat('T:',`province_id`,':',`city_id`,':',`category_type_id`,':',`category_number_id`,':',`group_id`,':',`team_id`)
      else NULL end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personnel_national_id` (`national_id`),
  UNIQUE KEY `uq_personnel_identity` (`first_name`,`last_name`,`position_id`,`category_type_id`,`category_number_id`,`province_id`,`city_id`),
  UNIQUE KEY `uq_personnel_command_scope` (`command_scope`),
  KEY `idx_personnel_group` (`group_id`),
  KEY `idx_personnel_location` (`province_id`,`city_id`),
  KEY `idx_personnel_name` (`last_name`,`first_name`),
  KEY `idx_personnel_status` (`personnel_status`),
  KEY `fk_personnel_category` (`category_type_id`),
  KEY `fk_personnel_position` (`position_id`),
  KEY `fk_personnel_category_number` (`category_number_id`,`category_type_id`),
  KEY `fk_personnel_city` (`city_id`,`province_id`),
  KEY `fk_personnel_district` (`district_id`),
  KEY `fk_personnel_team` (`team_id`,`category_type_id`),
  KEY `fk_personnel_created_by` (`created_by`),
  KEY `fk_personnel_updated_by` (`updated_by`),
  CONSTRAINT `fk_personnel_category` FOREIGN KEY (`category_type_id`) REFERENCES `category_types` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_personnel_category_number` FOREIGN KEY (`category_number_id`,`category_type_id`) REFERENCES `category_numbers` (`id`,`category_type_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_personnel_province` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_personnel_city` FOREIGN KEY (`city_id`,`province_id`) REFERENCES `cities` (`id`,`province_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_personnel_district` FOREIGN KEY (`district_id`) REFERENCES `city_districts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_personnel_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_personnel_group` FOREIGN KEY (`group_id`) REFERENCES `personnel_groups` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_personnel_team` FOREIGN KEY (`team_id`,`category_type_id`) REFERENCES `teams` (`id`,`category_type_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_personnel_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_personnel_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personnel_history` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `source_full_name` varchar(210) DEFAULT NULL,
  `national_id` char(10) NOT NULL,
  `position_id` tinyint(3) UNSIGNED NOT NULL,
  `category_type_id` tinyint(3) UNSIGNED NOT NULL,
  `category_number_id` int(10) UNSIGNED NOT NULL,
  `province_id` smallint(5) UNSIGNED NOT NULL,
  `city_id` int(10) UNSIGNED NOT NULL,
  `district_id` int(10) UNSIGNED DEFAULT NULL,
  `commander_number` varchar(30) DEFAULT NULL,
  `group_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `team_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `marital_status` varchar(30) DEFAULT NULL,
  `education_status` varchar(60) DEFAULT NULL,
  `residence_address` text DEFAULT NULL,
  `mobile` varchar(30) NOT NULL,
  `emergency_phone` varchar(30) DEFAULT NULL,
  `organizational_code` varchar(50) DEFAULT NULL,
  `secondary_job` varchar(150) DEFAULT NULL,
  `secondary_job_address` text DEFAULT NULL,
  `secondary_job_phone` varchar(30) DEFAULT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `iban_holder_name` varchar(160) DEFAULT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `personnel_status` enum('active','dismissed') NOT NULL DEFAULT 'active',
  `original_created_at` timestamp NULL DEFAULT NULL,
  `original_updated_at` timestamp NULL DEFAULT NULL,
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_history_personnel` (`personnel_id`,`id`),
  KEY `idx_history_changed_at` (`changed_at`),
  KEY `idx_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_history_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personnel_documents` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` int(10) UNSIGNED NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_doc_person` (`personnel_id`),
  KEY `fk_doc_user` (`created_by`),
  CONSTRAINT `fk_doc_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_doc_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personnel_dismissals` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` int(10) UNSIGNED NOT NULL,
  `dismissal_type` enum('deputy','unit_commander','group_commander','resignation') DEFAULT NULL,
  `dismissal_date` date DEFAULT NULL,
  `reason` varchar(500) NOT NULL,
  `dismissed_by` int(10) UNSIGNED DEFAULT NULL,
  `dismissed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personnel_dismissal` (`personnel_id`),
  KEY `idx_dismissal_date` (`dismissed_at`),
  KEY `fk_dismissal_user` (`dismissed_by`),
  CONSTRAINT `fk_dismissal_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dismissal_user` FOREIGN KEY (`dismissed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- پرونده انضباطی
-- ---------------------------------------------------------------------
CREATE TABLE `disciplinary_report_subjects` (
  `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject_key` varchar(40) NOT NULL,
  `subject_name` varchar(120) NOT NULL,
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_disciplinary_subject_key` (`subject_key`),
  KEY `idx_disciplinary_subject_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `disciplinary_report_subjects` (`id`, `subject_key`, `subject_name`, `sort_order`, `is_active`) VALUES
(1, 'name_one', 'نام اول', 1, 1),
(2, 'name_two', 'نام دوم', 2, 1),
(3, 'name_three', 'نام سوم', 3, 1);

CREATE TABLE `disciplinary_reports` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` int(10) UNSIGNED NOT NULL,
  `report_type` enum('encouragement','warning','reprimand') NOT NULL,
  `subject_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `subject_title` varchar(120) DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_disciplinary_person` (`personnel_id`,`id`),
  KEY `idx_disciplinary_type_subject` (`personnel_id`,`report_type`,`subject_id`),
  KEY `idx_disciplinary_report_date` (`personnel_id`,`report_date`),
  KEY `fk_disciplinary_subject` (`subject_id`),
  KEY `fk_disciplinary_created_by` (`created_by`),
  CONSTRAINT `fk_disciplinary_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_disciplinary_subject` FOREIGN KEY (`subject_id`) REFERENCES `disciplinary_report_subjects` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_disciplinary_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- امور مالی
-- ---------------------------------------------------------------------
CREATE TABLE `financial_transactions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_type` enum('single','group') NOT NULL DEFAULT 'single',
  `transfer_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_finance_date` (`transfer_date`),
  KEY `fk_finance_created` (`created_by`),
  KEY `fk_finance_updated` (`updated_by`),
  CONSTRAINT `fk_finance_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_finance_updated` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `financial_transaction_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `personnel_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `iban` char(26) DEFAULT NULL,
  `beneficiary_name` varchar(150) DEFAULT NULL,
  `item_note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fin_item_tx` (`transaction_id`),
  KEY `idx_fin_item_person` (`personnel_id`),
  CONSTRAINT `fk_fin_item_tx` FOREIGN KEY (`transaction_id`) REFERENCES `financial_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fin_item_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- آماد
-- ---------------------------------------------------------------------
CREATE TABLE `equipment_stock` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_type` varchar(100) NOT NULL,
  `serial_number` varchar(120) DEFAULT NULL,
  `plate` varchar(30) DEFAULT NULL,
  `model` varchar(120) DEFAULT NULL,
  `color` varchar(80) DEFAULT NULL,
  `status` enum('healthy','needs_repair','in_repair','repaired') NOT NULL DEFAULT 'healthy',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_equipment_stock_serial` (`serial_number`),
  UNIQUE KEY `uq_equipment_stock_plate` (`plate`),
  KEY `idx_equipment_stock_type` (`equipment_type`),
  KEY `idx_equipment_stock_status` (`status`),
  KEY `fk_equipment_stock_created` (`created_by`),
  KEY `fk_equipment_stock_updated` (`updated_by`),
  CONSTRAINT `fk_equipment_stock_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_stock_updated` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personnel_equipment` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` int(10) UNSIGNED NOT NULL,
  `equipment_type` varchar(100) NOT NULL,
  `serial_number` varchar(120) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `model` varchar(120) DEFAULT NULL,
  `color` varchar(80) DEFAULT NULL,
  `plate` varchar(30) DEFAULT NULL,
  `status` enum('healthy','needs_repair','in_repair','repaired') NOT NULL DEFAULT 'healthy',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_equipment_person` (`personnel_id`),
  KEY `idx_equipment_status` (`status`),
  KEY `idx_equipment_dates` (`delivery_date`,`return_date`),
  KEY `fk_equipment_created` (`created_by`),
  KEY `fk_equipment_updated` (`updated_by`),
  CONSTRAINT `fk_equipment_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_equipment_updated` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- احکام
-- ---------------------------------------------------------------------
CREATE TABLE `personnel_orders` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` int(10) UNSIGNED NOT NULL,
  `order_type` enum('mission','responsibility') NOT NULL,
  `order_date` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `order_number` varchar(60) DEFAULT NULL,
  `duration_days` smallint(5) UNSIGNED DEFAULT NULL,
  `weapon_status` enum('with','without') DEFAULT NULL,
  `weapon_type` varchar(120) DEFAULT NULL,
  `weapon_serial` varchar(120) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `vehicle_type` enum('car','motorcycle') DEFAULT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `is_renewable` tinyint(1) NOT NULL DEFAULT 0,
  `renewed_until` date DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `responsibility_position` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_orders_person` (`personnel_id`),
  KEY `idx_orders_dates` (`start_date`,`end_date`),
  KEY `idx_orders_number` (`order_number`),
  KEY `fk_order_created` (`created_by`),
  KEY `fk_order_updated` (`updated_by`),
  CONSTRAINT `fk_order_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_order_updated` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personnel_order_members` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(10) UNSIGNED NOT NULL,
  `personnel_id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_member` (`order_id`,`personnel_id`),
  KEY `idx_order_members_personnel` (`personnel_id`),
  KEY `fk_order_members_created_by` (`created_by`),
  CONSTRAINT `fk_order_members_order` FOREIGN KEY (`order_id`) REFERENCES `personnel_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_members_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_members_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personnel_order_documents` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(10) UNSIGNED NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order_doc_order` (`order_id`),
  KEY `fk_order_doc_creator` (`created_by`),
  CONSTRAINT `fk_order_doc_order` FOREIGN KEY (`order_id`) REFERENCES `personnel_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_doc_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personnel_order_equipment` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(10) UNSIGNED NOT NULL,
  `stock_id` int(10) UNSIGNED DEFAULT NULL,
  `equipment_type` varchar(100) NOT NULL,
  `serial_number` varchar(120) DEFAULT NULL,
  `plate` varchar(30) DEFAULT NULL,
  `model` varchar(120) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_equipment` (`order_id`,`stock_id`),
  KEY `idx_order_equipment_stock` (`stock_id`),
  CONSTRAINT `fk_order_equipment_order` FOREIGN KEY (`order_id`) REFERENCES `personnel_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_equipment_stock` FOREIGN KEY (`stock_id`) REFERENCES `equipment_stock` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- آموزش
-- ---------------------------------------------------------------------
CREATE TABLE `training_records` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_id` int(10) UNSIGNED NOT NULL,
  `course_key` varchar(100) NOT NULL,
  `course_name` varchar(150) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'completed',
  `training_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `certificate_serial` varchar(50) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_training_person_course` (`personnel_id`,`course_key`),
  KEY `idx_training_course` (`course_key`),
  KEY `idx_training_date` (`personnel_id`,`training_date`),
  KEY `fk_training_created` (`created_by`),
  KEY `fk_training_updated` (`updated_by`),
  CONSTRAINT `fk_training_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_training_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_training_updated` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `training_documents` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `training_record_id` int(10) UNSIGNED NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_training_doc_record` (`training_record_id`),
  KEY `fk_training_doc_user` (`created_by`),
  CONSTRAINT `fk_training_doc_record` FOREIGN KEY (`training_record_id`) REFERENCES `training_records` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_training_doc_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `training_applicants` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_key` varchar(100) NOT NULL,
  `personnel_id` int(10) UNSIGNED NOT NULL,
  `applicant_type` varchar(20) NOT NULL DEFAULT 'new',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_training_applicant` (`course_key`,`personnel_id`),
  KEY `idx_training_applicants_person` (`personnel_id`),
  KEY `fk_training_applicants_created` (`created_by`),
  CONSTRAINT `fk_training_applicants_person` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_training_applicants_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- تریگرها: کنترل جایگاه سازمانی + ثبت تاریخچه تغییرات عنصر
-- ---------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER `trg_personnel_validate_bi` BEFORE INSERT ON `personnel` FOR EACH ROW BEGIN
  DECLARE v_team_type TINYINT UNSIGNED DEFAULT NULL;
  DECLARE v_group_ok INT DEFAULT 0;
  IF NEW.position_id = 1 THEN
    SET NEW.team_id = NULL, NEW.group_id = NULL;
  ELSEIF NEW.position_id = 2 THEN
    SET NEW.team_id = NULL;
  END IF;
  IF NEW.group_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_group_ok FROM personnel_groups WHERE id = NEW.group_id AND is_active = 1;
    IF v_group_ok = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'گروه انتخاب‌شده نامعتبر است.'; END IF;
  END IF;
  IF NEW.team_id IS NOT NULL THEN
    SELECT category_type_id INTO v_team_type FROM teams WHERE id = NEW.team_id AND is_active = 1 LIMIT 1;
    IF v_team_type IS NULL OR v_team_type <> NEW.category_type_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'تیم انتخاب‌شده با نوع رسته سازگار نیست.';
    END IF;
  END IF;
END$$

CREATE TRIGGER `trg_personnel_validate_bu` BEFORE UPDATE ON `personnel` FOR EACH ROW BEGIN
  DECLARE v_team_type TINYINT UNSIGNED DEFAULT NULL;
  DECLARE v_group_ok INT DEFAULT 0;
  INSERT INTO personnel_history(personnel_id,first_name,last_name,source_full_name,national_id,position_id,category_type_id,category_number_id,province_id,city_id,district_id,commander_number,group_id,team_id,birth_date,father_name,marital_status,education_status,residence_address,mobile,emergency_phone,organizational_code,secondary_job,secondary_job_address,secondary_job_phone,iban,iban_holder_name,profile_photo_path,personnel_status,original_created_at,original_updated_at,changed_by)
  VALUES (OLD.id,OLD.first_name,OLD.last_name,OLD.source_full_name,OLD.national_id,OLD.position_id,OLD.category_type_id,OLD.category_number_id,OLD.province_id,OLD.city_id,OLD.district_id,OLD.commander_number,OLD.group_id,OLD.team_id,OLD.birth_date,OLD.father_name,OLD.marital_status,OLD.education_status,OLD.residence_address,OLD.mobile,OLD.emergency_phone,OLD.organizational_code,OLD.secondary_job,OLD.secondary_job_address,OLD.secondary_job_phone,OLD.iban,OLD.iban_holder_name,OLD.profile_photo_path,OLD.personnel_status,OLD.created_at,OLD.updated_at,NEW.updated_by);
  IF NEW.position_id = 1 THEN
    SET NEW.team_id = NULL, NEW.group_id = NULL;
  ELSEIF NEW.position_id = 2 THEN
    SET NEW.team_id = NULL;
  END IF;
  IF NEW.group_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_group_ok FROM personnel_groups WHERE id = NEW.group_id AND is_active = 1;
    IF v_group_ok = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'گروه انتخاب‌شده نامعتبر است.'; END IF;
  END IF;
  IF NEW.team_id IS NOT NULL THEN
    SELECT category_type_id INTO v_team_type FROM teams WHERE id = NEW.team_id AND is_active = 1 LIMIT 1;
    IF v_team_type IS NULL OR v_team_type <> NEW.category_type_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'تیم انتخاب‌شده با نوع رسته سازگار نیست.';
    END IF;
  END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------
-- داده نمونه عناصر — شمیرانات، شماره قائد ۱
-- هر رسته: ۱ فرمانده دسته · ۳ فرمانده گروه · هر گروه ۳ تیم (۱ سر تیم + ۲ عنصر)
-- ---------------------------------------------------------------------
INSERT INTO `personnel` (`id`, `first_name`, `last_name`, `source_full_name`, `national_id`, `position_id`, `category_type_id`, `category_number_id`, `province_id`, `city_id`, `district_id`, `commander_number`, `group_id`, `team_id`, `birth_date`, `father_name`, `marital_status`, `education_status`, `residence_address`, `mobile`, `emergency_phone`, `organizational_code`, `secondary_job`, `secondary_job_address`, `secondary_job_phone`, `iban`, `iban_holder_name`, `profile_photo_path`, `personnel_status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'امیر', 'وحیدی', 'امیر وحیدی', '0010000372', 1, 1, 1, 8, 3, NULL, '1', NULL, NULL, '1989-02-27', 'ابوالفضل', 'single', 'bachelor', 'ولنجک، خیابان یمن، کوچه 1، پلاک 70', '09122465742', '02122507493', '4000013', 'کارمند بانک', 'تهران، میدان ونک، پلاک 77', '02188758022', 'IR890170000000000000107922', 'امیر وحیدی', NULL, 'active', 1, 1, '2026-09-01 08:07:00', '2026-09-01 08:07:00'),
(2, 'روح‌الله', 'رفیعی', 'روح‌الله رفیعی', '0010000747', 2, 1, 1, 8, 3, NULL, '1', 1, NULL, '1984-12-23', 'قربان', 'married', 'diploma', 'دزاشیب، خیابان جماران، کوچه 20، پلاک 87', '09124719068', '02122427756', '4000026', 'مغازه‌دار', 'تجریش، بازار تجریش، پلاک 30', '02188390610', 'IR640170000000000000115841', 'روح‌الله رفیعی', NULL, 'active', 1, 1, '2026-09-01 08:14:00', '2026-09-01 08:14:00'),
(3, 'بهروز', 'حیدری', 'بهروز حیدری', '0010001115', 3, 1, 1, 8, 3, NULL, '1', 1, 1, '1984-01-10', 'علی‌اکبر', 'married', 'master', 'کامرانیه، خیابان سلیمی، کوچه 39، پلاک 44', '09125360791', '02122708795', '4000039', 'مغازه‌دار', 'تهران، خیابان میرداماد، پلاک 175', '02188124675', 'IR390170000000000000123760', 'بهروز حیدری', NULL, 'active', 1, 1, '2026-09-01 08:21:00', '2026-09-01 08:21:00'),
(4, 'روح‌الله', 'شریفی', 'روح‌الله شریفی', '0010001484', 4, 1, 1, 8, 3, NULL, '1', 1, 1, '1977-01-01', 'احمد', 'married', 'associate', 'دربند، خیابان سعدآباد، کوچه 6، پلاک 115', '09129026000', '02122783508', '4000052', 'تعمیرکار', 'تهران، خیابان شریعتی، پلاک 9', '02188791847', 'IR140170000000000000131679', 'روح‌الله شریفی', NULL, 'active', 1, 1, '2026-09-01 08:28:00', '2026-09-01 08:28:00'),
(5, 'قاسم', 'زمانی', 'قاسم زمانی', '0010001859', 4, 1, 1, 8, 3, NULL, '1', 1, 1, '1990-04-14', 'رمضان', 'married', 'bachelor', 'اقدسیه، بلوار ارتش، کوچه 30، پلاک 24', '09129931516', '02122666189', '4000065', 'معلم', 'تهران، خیابان شریعتی، پلاک 153', '02188219020', 'IR860170000000000000139598', 'قاسم زمانی', NULL, 'active', 1, 1, '2026-09-01 08:35:00', '2026-09-01 08:35:00'),
(6, 'سهیل', 'صادقی', 'سهیل صادقی', '0010002227', 3, 1, 1, 8, 3, NULL, '1', 1, 2, '1981-11-14', 'حسینعلی', 'married', 'master', 'فرمانیه، خیابان دیباجی، کوچه 16، پلاک 70', '09126588934', '02122151857', '4000078', 'کارشناس فنی', 'تهران، بزرگراه صدر، پلاک 83', '02188414701', 'IR610170000000000000147517', 'سهیل صادقی', NULL, 'active', 1, 1, '2026-09-01 08:42:00', '2026-09-01 08:42:00'),
(7, 'مرتضی', 'ولیزاده', 'مرتضی ولیزاده', '0010002596', 4, 1, 1, 8, 3, NULL, '1', 1, 2, '1979-06-09', 'غلامرضا', 'married', 'phd', 'دزاشیب، خیابان جماران، کوچه 11، پلاک 80', '09123425626', '02122288789', '4000091', 'حسابدار', 'تهران، خیابان میرداماد، پلاک 85', '02188109002', 'IR360170000000000000155436', 'مرتضی ولیزاده', NULL, 'active', 1, 1, '2026-09-01 08:49:00', '2026-09-01 08:49:00'),
(8, 'اسماعیل', 'شجاعی', 'اسماعیل شجاعی', '0010002960', 4, 1, 1, 8, 3, NULL, '1', 1, 2, '1975-12-11', 'احمد', 'married', 'master', 'زعفرانیه، خیابان مقدس اردبیلی، کوچه 15، پلاک 81', '09123196982', '02122180176', '4000104', 'کارشناس فروش', 'تجریش، بازار تجریش، پلاک 16', '02188104263', 'IR110170000000000000163355', 'اسماعیل شجاعی', NULL, 'active', 1, 1, '2026-09-01 08:56:00', '2026-09-01 08:56:00'),
(9, 'یونس', 'خسروی', 'یونس خسروی', '0010003339', 3, 1, 1, 8, 3, NULL, '1', 1, 3, '1986-02-16', 'عباس', 'married', 'bachelor', 'کامرانیه، خیابان سلیمی، کوچه 31، پلاک 42', '09123289328', '02122028179', '4000117', 'کارشناس فنی', 'تجریش، بازار تجریش، پلاک 149', '02188294581', 'IR830170000000000000171274', 'یونس خسروی', NULL, 'active', 1, 1, '2026-09-01 09:03:00', '2026-09-01 09:03:00'),
(10, 'جواد', 'شجاعی', 'جواد شجاعی', '0010003703', 4, 1, 1, 8, 3, NULL, '1', 1, 3, '1983-06-01', 'علی‌اکبر', 'single', 'master', 'اقدسیه، بلوار ارتش، کوچه 16، پلاک 28', '09126634861', '02122666216', '4000130', 'طراح', 'تجریش، بازار تجریش، پلاک 214', '02188451315', 'IR580170000000000000179193', 'جواد شجاعی', NULL, 'active', 1, 1, '2026-09-01 09:10:00', '2026-09-01 09:10:00'),
(11, 'مسعود', 'خسروی', 'مسعود خسروی', '0010004076', 4, 1, 1, 8, 3, NULL, '1', 1, 3, '1980-06-26', 'حسن', 'married', 'bachelor', 'اقدسیه، بلوار ارتش، کوچه 30، پلاک 115', '09123472277', '02122777785', '4000143', 'تعمیرکار', 'تجریش، بازار تجریش، پلاک 3', '02188521903', 'IR330170000000000000187112', 'مسعود خسروی', NULL, 'active', 1, 1, '2026-09-01 09:17:00', '2026-09-01 09:17:00'),
(12, 'رضا', 'پاکزاد', 'رضا پاکزاد', '0010004440', 2, 1, 1, 8, 3, NULL, '1', 2, NULL, '1999-07-11', 'رمضان', 'married', 'associate', 'قیطریه، خیابان صدر، کوچه 35، پلاک 93', '09128070502', '02122124033', '4000156', 'راننده', 'تهران، میدان ونک، پلاک 203', '02188791420', 'IR080170000000000000195031', 'رضا پاکزاد', NULL, 'active', 1, 1, '2026-09-01 09:24:00', '2026-09-01 09:24:00'),
(13, 'بابک', 'حبیبی', 'بابک حبیبی', '0010004815', 3, 1, 1, 8, 3, NULL, '1', 2, 1, '1986-06-01', 'حسینعلی', 'single', 'phd', 'کامرانیه، خیابان سلیمی، کوچه 15، پلاک 89', '09129932955', '02122226948', '4000169', 'تعمیرکار', 'تهران، خیابان شریعتی، پلاک 250', '02188245402', 'IR800170000000000000202950', 'بابک حبیبی', NULL, 'active', 1, 1, '2026-09-01 09:31:00', '2026-09-01 09:31:00'),
(14, 'منصور', 'کرمانی', 'منصور کرمانی', '0010005188', 4, 1, 1, 8, 3, NULL, '1', 2, 1, '2001-04-21', 'عباس', 'married', 'bachelor', 'اقدسیه، بلوار ارتش، کوچه 33، پلاک 96', '09122607264', '02122018407', '4000182', 'کارشناس فنی', 'تهران، خیابان ولیعصر، پلاک 118', '02188256614', 'IR550170000000000000210869', 'منصور کرمانی', NULL, 'active', 1, 1, '2026-09-01 09:38:00', '2026-09-01 09:38:00'),
(15, 'محمد', 'تهرانی', 'محمد تهرانی', '0010005552', 4, 1, 1, 8, 3, NULL, '1', 2, 1, '1988-01-20', 'رحیم', 'married', 'diploma', 'فرمانیه، خیابان دیباجی، کوچه 6، پلاک 40', '09129437074', '02122075875', '4000195', 'کارمند بانک', 'تهران، بزرگراه صدر، پلاک 9', '02188045786', 'IR300170000000000000218788', 'محمد تهرانی', NULL, 'active', 1, 1, '2026-09-01 09:45:00', '2026-09-01 09:45:00'),
(16, 'منصور', 'حاتمی', 'منصور حاتمی', '0010005927', 3, 1, 1, 8, 3, NULL, '1', 2, 2, '2000-07-19', 'ابوالفضل', 'married', 'associate', 'تجریش، خیابان شهید باهنر، کوچه 2، پلاک 22', '09128373960', '02122082628', '4000208', 'راننده', 'تهران، خیابان شریعتی، پلاک 24', '02188777479', 'IR050170000000000000226707', 'منصور حاتمی', NULL, 'active', 1, 1, '2026-09-01 09:52:00', '2026-09-01 09:52:00'),
(17, 'مجید', 'اصفهانی', 'مجید اصفهانی', '0010006291', 4, 1, 1, 8, 3, NULL, '1', 2, 2, '1982-12-27', 'جعفر', 'single', 'diploma', 'کامرانیه، خیابان سلیمی، کوچه 32، پلاک 96', '09129045423', '02122774677', '4000221', 'مغازه‌دار', 'تهران، میدان ونک، پلاک 89', '02188189279', 'IR770170000000000000234626', 'مجید اصفهانی', NULL, 'active', 1, 1, '2026-09-01 09:59:00', '2026-09-01 09:59:00'),
(18, 'مازیار', 'رنجبر', 'مازیار رنجبر', '0010006664', 4, 1, 1, 8, 3, NULL, '1', 2, 2, '1979-05-24', 'محمود', 'married', 'diploma', 'الهیه، خیابان فرشته، کوچه 30، پلاک 61', '09122641968', '02122540961', '4000234', 'طراح', 'تهران، خیابان شریعتی، پلاک 125', '02188048253', 'IR520170000000000000242545', 'مازیار رنجبر', NULL, 'active', 1, 1, '2026-09-01 10:06:00', '2026-09-01 10:06:00'),
(19, 'احسان', 'کرمانی', 'احسان کرمانی', '0010007032', 3, 1, 1, 8, 3, NULL, '1', 2, 3, '1984-09-07', 'احمد', 'married', 'master', 'لواسان، بلوار امام خمینی، کوچه 35، پلاک 48', '09121171561', '02122894174', '4000247', 'پیمانکار', 'تهران، خیابان شریعتی، پلاک 76', '02188881492', 'IR270170000000000000250464', 'احسان کرمانی', NULL, 'active', 1, 1, '2026-09-01 10:13:00', '2026-09-01 10:13:00'),
(20, 'نیما', 'توکلی', 'نیما توکلی', '0010007407', 4, 1, 1, 8, 3, NULL, '1', 2, 3, '1981-03-07', 'قربان', 'married', 'diploma', 'فرمانیه، خیابان دیباجی، کوچه 31، پلاک 38', '09126977584', '02122929572', '4000260', 'پیمانکار', 'تهران، خیابان شریعتی، پلاک 228', '02188375713', 'IR020170000000000000258383', 'نیما توکلی', NULL, 'active', 1, 1, '2026-09-01 10:20:00', '2026-09-01 10:20:00'),
(21, 'جعفر', 'حاتمی', 'جعفر حاتمی', '0010007776', 4, 1, 1, 8, 3, NULL, '1', 2, 3, '1983-02-03', 'رمضان', 'married', 'diploma', 'زعفرانیه، خیابان مقدس اردبیلی، کوچه 33، پلاک 100', '09123175197', '02122292992', '4000273', 'طراح', 'تهران، خیابان میرداماد، پلاک 129', '02188911792', 'IR740170000000000000266302', 'جعفر حاتمی', NULL, 'active', 1, 1, '2026-09-01 10:27:00', '2026-09-01 10:27:00'),
(22, 'پویا', 'منصوری', 'پویا منصوری', '0010008144', 2, 1, 1, 8, 3, NULL, '1', 3, NULL, '1975-10-26', 'جعفر', 'married', 'associate', 'دربند، خیابان سعدآباد، کوچه 36، پلاک 57', '09129624699', '02122617995', '4000286', 'آزاد', 'تهران، خیابان ولیعصر، پلاک 259', '02188743818', 'IR490170000000000000274221', 'پویا منصوری', NULL, 'active', 1, 1, '2026-09-01 10:34:00', '2026-09-01 10:34:00'),
(23, 'مجید', 'شجاعی', 'مجید شجاعی', '0010008519', 3, 1, 1, 8, 3, NULL, '1', 3, 1, '1980-08-09', 'غلامرضا', 'married', 'diploma', 'تجریش، خیابان شهید باهنر، کوچه 26، پلاک 21', '09121624212', '02122619645', '4000299', 'کارشناس فنی', 'تجریش، بازار تجریش، پلاک 290', '02188243903', 'IR240170000000000000282140', 'مجید شجاعی', NULL, 'active', 1, 1, '2026-09-01 10:41:00', '2026-09-01 10:41:00'),
(24, 'مرتضی', 'حبیبی', 'مرتضی حبیبی', '0010008888', 4, 1, 1, 8, 3, NULL, '1', 3, 1, '1990-09-12', 'رمضان', 'married', 'diploma', 'کامرانیه، خیابان سلیمی، کوچه 4، پلاک 93', '09127563053', '02122889729', '4000312', 'پیمانکار', 'تهران، خیابان شریعتی، پلاک 55', '02188600158', 'IR960170000000000000290059', 'مرتضی حبیبی', NULL, 'active', 1, 1, '2026-09-01 10:48:00', '2026-09-01 10:48:00'),
(25, 'مهران', 'تهرانی', 'مهران تهرانی', '0010009256', 4, 1, 1, 8, 3, NULL, '1', 3, 1, '1988-02-13', 'رمضان', 'married', 'phd', 'ولنجک، خیابان یمن، کوچه 21، پلاک 62', '09123241439', '02122699248', '4000325', 'کارمند بانک', 'تهران، خیابان شریعتی، پلاک 78', '02188346650', 'IR710170000000000000297978', 'مهران تهرانی', NULL, 'active', 1, 1, '2026-09-01 10:55:00', '2026-09-01 10:55:00'),
(26, 'علی', 'اصفهانی', 'علی اصفهانی', '0010009620', 3, 1, 1, 8, 3, NULL, '1', 3, 2, '1992-11-19', 'اسدالله', 'married', 'associate', 'دزاشیب، خیابان جماران، کوچه 31، پلاک 62', '09122237812', '02122946583', '4000338', 'آزاد', 'تجریش، بازار تجریش، پلاک 289', '02188184781', 'IR460170000000000000305897', 'علی اصفهانی', NULL, 'active', 1, 1, '2026-09-01 11:02:00', '2026-09-01 11:02:00'),
(27, 'سهیل', 'اصفهانی', 'سهیل اصفهانی', '0010009991', 4, 1, 1, 8, 3, NULL, '1', 3, 2, '1976-03-04', 'احمد', 'married', 'master', 'قیطریه، خیابان صدر، کوچه 8، پلاک 70', '09125221202', '02122093357', '4000351', 'طراح', 'تهران، خیابان شریعتی، پلاک 86', '02188858580', 'IR210170000000000000313816', 'سهیل اصفهانی', NULL, 'active', 1, 1, '2026-09-01 11:09:00', '2026-09-01 11:09:00'),
(28, 'هومن', 'طاهری', 'هومن طاهری', '0010010361', 4, 1, 1, 8, 3, NULL, '1', 3, 2, '1978-10-18', 'اسدالله', 'single', 'master', 'زعفرانیه، خیابان مقدس اردبیلی، کوچه 20، پلاک 66', '09125720477', '02122145795', '4000364', 'طراح', 'تهران، خیابان ولیعصر، پلاک 271', '02188088492', 'IR930170000000000000321735', 'هومن طاهری', NULL, 'active', 1, 1, '2026-09-01 11:16:00', '2026-09-01 11:16:00'),
(29, 'ایمان', 'مرادی', 'ایمان مرادی', '0010010734', 3, 1, 1, 8, 3, NULL, '1', 3, 3, '1978-10-11', 'عباس', 'married', 'bachelor', 'تجریش، خیابان شهید باهنر، کوچه 8، پلاک 47', '09124053109', '02122700642', '4000377', 'طراح', 'تهران، خیابان شریعتی، پلاک 169', '02188329697', 'IR680170000000000000329654', 'ایمان مرادی', NULL, 'active', 1, 1, '2026-09-01 11:23:00', '2026-09-01 11:23:00'),
(30, 'فرهاد', 'سبحانی', 'فرهاد سبحانی', '0010011102', 4, 1, 1, 8, 3, NULL, '1', 3, 3, '1985-10-17', 'منوچهر', 'married', 'bachelor', 'الهیه، خیابان فرشته، کوچه 38، پلاک 9', '09123331093', '02122595635', '4000390', 'راننده', 'تهران، خیابان شریعتی، پلاک 54', '02188932112', 'IR430170000000000000337573', 'فرهاد سبحانی', NULL, 'active', 1, 1, '2026-09-01 11:30:00', '2026-09-01 11:30:00'),
(31, 'کیوان', 'حاتمی', 'کیوان حاتمی', '0010011471', 4, 1, 1, 8, 3, NULL, '1', 3, 3, '1989-05-08', 'ابوالفضل', 'married', 'associate', 'زعفرانیه، خیابان مقدس اردبیلی، کوچه 19، پلاک 84', '09122712597', '02122783703', '4000403', 'کارشناس فنی', 'تهران، خیابان میرداماد، پلاک 155', '02188241888', 'IR180170000000000000345492', 'کیوان حاتمی', NULL, 'active', 1, 1, '2026-09-01 11:37:00', '2026-09-01 11:37:00'),
(32, 'حسین', 'بیگی', 'حسین بیگی', '0010011846', 1, 2, 2, 8, 3, NULL, '1', NULL, NULL, '1999-04-22', 'رمضان', 'married', 'master', 'دربند، خیابان سعدآباد، کوچه 35، پلاک 65', '09128809496', '02122731570', '4000416', 'کارشناس فروش', 'تهران، خیابان ولیعصر، پلاک 298', '02188446897', 'IR900170000000000000353411', 'حسین بیگی', NULL, 'active', 1, 1, '2026-09-01 11:44:00', '2026-09-01 11:44:00'),
(33, 'امیر', 'محمدی', 'امیر محمدی', '0010012214', 2, 2, 2, 8, 3, NULL, '1', 1, NULL, '1993-08-05', 'نعمت‌الله', 'married', 'master', 'زعفرانیه، خیابان مقدس اردبیلی، کوچه 33، پلاک 91', '09125100076', '02122556530', '4000429', 'تعمیرکار', 'تهران، خیابان میرداماد، پلاک 165', '02188381958', 'IR650170000000000000361330', 'امیر محمدی', NULL, 'active', 1, 1, '2026-09-01 11:51:00', '2026-09-01 11:51:00'),
(34, 'بهروز', 'اسدی', 'بهروز اسدی', '0010012583', 3, 2, 2, 8, 3, NULL, '1', 1, 4, '1988-07-21', 'حسن', 'single', 'phd', 'لواسان، بلوار امام خمینی، کوچه 28، پلاک 18', '09121094366', '02122226725', '4000442', 'کارشناس فنی', 'تهران، بزرگراه صدر، پلاک 24', '02188020218', 'IR400170000000000000369249', 'بهروز اسدی', NULL, 'active', 1, 1, '2026-09-01 11:58:00', '2026-09-01 11:58:00'),
(35, 'فرشید', 'طاهری', 'فرشید طاهری', '0010012958', 4, 2, 2, 8, 3, NULL, '1', 1, 4, '1992-06-18', 'رمضان', 'married', 'bachelor', 'لواسان، بلوار امام خمینی، کوچه 10، پلاک 50', '09121319670', '02122434886', '4000455', 'معلم', 'تهران، میدان ونک، پلاک 104', '02188676197', 'IR150170000000000000377168', 'فرشید طاهری', NULL, 'active', 1, 1, '2026-09-01 12:05:00', '2026-09-01 12:05:00'),
(36, 'رسول', 'خسروی', 'رسول خسروی', '0010013326', 4, 2, 2, 8, 3, NULL, '1', 1, 4, '1988-12-17', 'رمضان', 'married', 'associate', 'دزاشیب، خیابان جماران، کوچه 9، پلاک 59', '09125208850', '02122513102', '4000468', 'کارشناس فنی', 'تجریش، بازار تجریش، پلاک 184', '02188726737', 'IR870170000000000000385087', 'رسول خسروی', NULL, 'active', 1, 1, '2026-09-01 12:12:00', '2026-09-01 12:12:00'),
(37, 'یونس', 'رحیمی', 'یونس رحیمی', '0010013695', 3, 2, 2, 8, 3, NULL, '1', 1, 5, '1995-01-08', 'حسن', 'single', 'diploma', 'نیاوران، خیابان پاسداران، کوچه 23، پلاک 76', '09128211224', '02122999513', '4000481', 'کارشناس فنی', 'تجریش، بازار تجریش، پلاک 281', '02188997997', 'IR620170000000000000393006', 'یونس رحیمی', NULL, 'active', 1, 1, '2026-09-01 12:19:00', '2026-09-01 12:19:00'),
(38, 'مازیار', 'زمانی', 'مازیار زمانی', '0010014063', 4, 2, 2, 8, 3, NULL, '1', 1, 5, '1976-11-03', 'جعفر', 'single', 'diploma', 'دربند، خیابان سعدآباد، کوچه 31، پلاک 105', '09124035026', '02122064129', '4000494', 'حسابدار', 'تهران، میدان ونک، پلاک 169', '02188227284', 'IR370170000000000000400925', 'مازیار زمانی', NULL, 'active', 1, 1, '2026-09-01 12:26:00', '2026-09-01 12:26:00'),
(39, 'بابک', 'پاکزاد', 'بابک پاکزاد', '0010014438', 4, 2, 2, 8, 3, NULL, '1', 1, 5, '1982-12-10', 'حسن', 'married', 'associate', 'فرمانیه، خیابان دیباجی، کوچه 31، پلاک 69', '09129478772', '02122357607', '4000507', 'آزاد', 'تهران، خیابان شریعتی، پلاک 229', '02188544577', 'IR120170000000000000408844', 'بابک پاکزاد', NULL, 'active', 1, 1, '2026-09-01 12:33:00', '2026-09-01 12:33:00'),
(40, 'مازیار', 'عباسی', 'مازیار عباسی', '0010014802', 3, 2, 2, 8, 3, NULL, '1', 1, 6, '1992-12-02', 'ابوالفضل', 'married', 'bachelor', 'تجریش، خیابان شهید باهنر، کوچه 28، پلاک 25', '09128668232', '02122131055', '4000520', 'کارشناس فنی', 'تهران، خیابان شریعتی، پلاک 91', '02188645606', 'IR840170000000000000416763', 'مازیار عباسی', NULL, 'active', 1, 1, '2026-09-01 12:40:00', '2026-09-01 12:40:00'),
(41, 'ایمان', 'طاهری', 'ایمان طاهری', '0010015175', 4, 2, 2, 8, 3, NULL, '1', 1, 6, '1983-09-03', 'جعفر', 'married', 'associate', 'قیطریه، خیابان صدر، کوچه 38، پلاک 56', '09122656500', '02122424007', '4000533', 'کارشناس فروش', 'تهران، بزرگراه صدر، پلاک 281', '02188065186', 'IR590170000000000000424682', 'ایمان طاهری', NULL, 'active', 1, 1, '2026-09-01 12:47:00', '2026-09-01 12:47:00'),
(42, 'حمید', 'احمدی', 'حمید احمدی', '0010015541', 4, 2, 2, 8, 3, NULL, '1', 1, 6, '1981-10-17', 'عباس', 'married', 'bachelor', 'الهیه، خیابان فرشته، کوچه 40، پلاک 33', '09121131188', '02122125861', '4000546', 'طراح', 'تهران، خیابان میرداماد، پلاک 122', '02188471654', 'IR340170000000000000432601', 'حمید احمدی', NULL, 'active', 1, 1, '2026-09-01 12:54:00', '2026-09-01 12:54:00'),
(43, 'نیما', 'سلیمانی', 'نیما سلیمانی', '0010015914', 2, 2, 2, 8, 3, NULL, '1', 2, NULL, '1994-01-25', 'علی‌اکبر', 'single', 'associate', 'نیاوران، خیابان پاسداران، کوچه 22، پلاک 30', '09129067856', '02122412356', '4000559', 'پیمانکار', 'تهران، خیابان میرداماد، پلاک 33', '02188961272', 'IR090170000000000000440520', 'نیما سلیمانی', NULL, 'active', 1, 1, '2026-09-01 13:01:00', '2026-09-01 13:01:00'),
(44, 'فرهاد', 'عباسی', 'فرهاد عباسی', '0010016287', 3, 2, 2, 8, 3, NULL, '1', 2, 4, '1976-04-20', 'غلامرضا', 'married', 'associate', 'کامرانیه، خیابان سلیمی، کوچه 8، پلاک 117', '09126797364', '02122400095', '4000572', 'حسابدار', 'تهران، خیابان میرداماد، پلاک 199', '02188455557', 'IR810170000000000000448439', 'فرهاد عباسی', NULL, 'active', 1, 1, '2026-09-01 13:08:00', '2026-09-01 13:08:00'),
(45, 'یاسر', 'سلیمانی', 'یاسر سلیمانی', '0010016651', 4, 2, 2, 8, 3, NULL, '1', 2, 4, '1991-05-06', 'جعفر', 'single', 'diploma', 'زعفرانیه، خیابان مقدس اردبیلی، کوچه 27، پلاک 34', '09127218087', '02122124677', '4000585', 'حسابدار', 'تهران، میدان ونک، پلاک 263', '02188367093', 'IR560170000000000000456358', 'یاسر سلیمانی', NULL, 'active', 1, 1, '2026-09-01 13:15:00', '2026-09-01 13:15:00'),
(46, 'حامد', 'اکبری', 'حامد اکبری', '0010017021', 4, 2, 2, 8, 3, NULL, '1', 2, 4, '1979-09-17', 'رمضان', 'single', 'phd', 'زعفرانیه، خیابان مقدس اردبیلی، کوچه 26، پلاک 90', '09124646159', '02122999778', '4000598', 'کارمند', 'تجریش، بازار تجریش، پلاک 89', '02188423077', 'IR310170000000000000464277', 'حامد اکبری', NULL, 'active', 1, 1, '2026-09-01 13:22:00', '2026-09-01 13:22:00'),
(47, 'وحید', 'ابراهیمی', 'وحید ابراهیمی', '0010017399', 3, 2, 2, 8, 3, NULL, '1', 2, 5, '2000-08-01', 'عباس', 'married', 'diploma', 'زعفرانیه، خیابان مقدس اردبیلی، کوچه 18، پلاک 73', '09125259439', '02122613227', '4000611', 'حسابدار', 'تهران، خیابان میرداماد، پلاک 199', '02188509469', 'IR060170000000000000472196', 'وحید ابراهیمی', NULL, 'active', 1, 1, '2026-09-01 13:29:00', '2026-09-01 13:29:00'),
(48, 'جعفر', 'تهرانی', 'جعفر تهرانی', '0010017763', 4, 2, 2, 8, 3, NULL, '1', 2, 5, '1977-01-13', 'ابوالفضل', 'married', 'bachelor', 'فرمانیه، خیابان دیباجی، کوچه 15، پلاک 4', '09123354522', '02122406258', '4000624', 'معلم', 'تهران، میدان ونک، پلاک 190', '02188502122', 'IR780170000000000000480115', 'جعفر تهرانی', NULL, 'active', 1, 1, '2026-09-01 13:36:00', '2026-09-01 13:36:00'),
(49, 'یونس', 'غفاری', 'یونس غفاری', '0010018131', 4, 2, 2, 8, 3, NULL, '1', 2, 5, '1993-01-01', 'جعفر', 'married', 'diploma', 'ولنجک، خیابان یمن، کوچه 6، پلاک 107', '09127400915', '02122016957', '4000637', 'راننده', 'تهران، میدان ونک، پلاک 160', '02188610401', 'IR530170000000000000488034', 'یونس غفاری', NULL, 'active', 1, 1, '2026-09-01 13:43:00', '2026-09-01 13:43:00'),
(50, 'یونس', 'جمالی', 'یونس جمالی', '0010018506', 3, 2, 2, 8, 3, NULL, '1', 2, 6, '1979-06-17', 'غلامرضا', 'married', 'bachelor', 'نیاوران، خیابان پاسداران، کوچه 26، پلاک 43', '09122711027', '02122074911', '4000650', 'تعمیرکار', 'تهران، خیابان شریعتی، پلاک 224', '02188065563', 'IR280170000000000000495953', 'یونس جمالی', NULL, 'active', 1, 1, '2026-09-01 13:50:00', '2026-09-01 13:50:00'),
(51, 'مرتضی', 'فتحی', 'مرتضی فتحی', '0010018875', 4, 2, 2, 8, 3, NULL, '1', 2, 6, '1991-08-25', 'علی‌اکبر', 'married', 'diploma', 'کامرانیه، خیابان سلیمی، کوچه 10، پلاک 113', '09127943983', '02122047985', '4000663', 'مغازه‌دار', 'تهران، خیابان میرداماد، پلاک 249', '02188975330', 'IR030170000000000000503872', 'مرتضی فتحی', NULL, 'active', 1, 1, '2026-09-01 13:57:00', '2026-09-01 13:57:00'),
(52, 'رامین', 'فتحی', 'رامین فتحی', '0010019243', 4, 2, 2, 8, 3, NULL, '1', 2, 6, '2001-06-02', 'غلامرضا', 'married', 'bachelor', 'کامرانیه، خیابان سلیمی، کوچه 15، پلاک 106', '09121796396', '02122863133', '4000676', 'پیمانکار', 'تهران، خیابان میرداماد، پلاک 67', '02188244715', 'IR750170000000000000511791', 'رامین فتحی', NULL, 'active', 1, 1, '2026-09-01 14:04:00', '2026-09-01 14:04:00'),
(53, 'کامران', 'کاظمی', 'کامران کاظمی', '0010019618', 2, 2, 2, 8, 3, NULL, '1', 3, NULL, '1990-03-23', 'عباس', 'married', 'master', 'دزاشیب، خیابان جماران، کوچه 34، پلاک 46', '09128490483', '02122555504', '4000689', 'طراح', 'تجریش، بازار تجریش، پلاک 167', '02188350165', 'IR500170000000000000519710', 'کامران کاظمی', NULL, 'active', 1, 1, '2026-09-01 14:11:00', '2026-09-01 14:11:00'),
(54, 'جلال', 'وحیدی', 'جلال وحیدی', '0010019987', 3, 2, 2, 8, 3, NULL, '1', 3, 4, '1994-04-27', 'رمضان', 'married', 'diploma', 'ولنجک، خیابان یمن، کوچه 30، پلاک 15', '09127300963', '02122491839', '4000702', 'راننده', 'تهران، میدان ونک، پلاک 184', '02188011301', 'IR250170000000000000527629', 'جلال وحیدی', NULL, 'active', 1, 1, '2026-09-01 14:18:00', '2026-09-01 14:18:00'),
(55, 'روح‌الله', 'یزدانی', 'روح‌الله یزدانی', '0010020357', 4, 2, 2, 8, 3, NULL, '1', 3, 4, '1997-08-19', 'نعمت‌الله', 'single', 'associate', 'دزاشیب، خیابان جماران، کوچه 12، پلاک 89', '09121914113', '02122129155', '4000715', 'کارمند بانک', 'تهران، خیابان میرداماد، پلاک 153', '02188366038', 'IR970170000000000000535548', 'روح‌الله یزدانی', NULL, 'active', 1, 1, '2026-09-01 14:25:00', '2026-09-01 14:25:00'),
(56, 'رضا', 'کرمانی', 'رضا کرمانی', '0010020721', 4, 2, 2, 8, 3, NULL, '1', 3, 4, '1987-01-05', 'رمضان', 'single', 'diploma', 'ولنجک، خیابان یمن، کوچه 16، پلاک 104', '09123769850', '02122456216', '4000728', 'کارمند', 'تهران، میدان ونک، پلاک 222', '02188000029', 'IR720170000000000000543467', 'رضا کرمانی', NULL, 'active', 1, 1, '2026-09-01 14:32:00', '2026-09-01 14:32:00'),
(57, 'حبیب', 'احمدی', 'حبیب احمدی', '0010021094', 3, 2, 2, 8, 3, NULL, '1', 3, 5, '1983-07-04', 'حسن', 'married', 'master', 'زعفرانیه، خیابان مقدس اردبیلی، کوچه 27، پلاک 58', '09121812150', '02122445442', '4000741', 'حسابدار', 'تجریش، بازار تجریش، پلاک 44', '02188572150', 'IR470170000000000000551386', 'حبیب احمدی', NULL, 'active', 1, 1, '2026-09-01 14:39:00', '2026-09-01 14:39:00'),
(58, 'علی', 'قاسمی', 'علی قاسمی', '0010021469', 4, 2, 2, 8, 3, NULL, '1', 3, 5, '1977-04-28', 'رمضان', 'single', 'master', 'نیاوران، خیابان پاسداران، کوچه 9، پلاک 9', '09121460342', '02122847749', '4000754', 'حسابدار', 'تهران، میدان ونک، پلاک 251', '02188152526', 'IR220170000000000000559305', 'علی قاسمی', NULL, 'active', 1, 1, '2026-09-01 14:46:00', '2026-09-01 14:46:00'),
(59, 'مازیار', 'اصفهانی', 'مازیار اصفهانی', '0010021833', 4, 2, 2, 8, 3, NULL, '1', 3, 5, '1978-02-22', 'عباس', 'married', 'diploma', 'اقدسیه، بلوار ارتش، کوچه 25، پلاک 34', '09125248830', '02122837415', '4000767', 'مغازه‌دار', 'تهران، خیابان میرداماد، پلاک 298', '02188769500', 'IR940170000000000000567224', 'مازیار اصفهانی', NULL, 'active', 1, 1, '2026-09-01 14:53:00', '2026-09-01 14:53:00'),
(60, 'علی', 'ابراهیمی', 'علی ابراهیمی', '0010022201', 3, 2, 2, 8, 3, NULL, '1', 3, 6, '1988-04-19', 'علی‌اکبر', 'married', 'bachelor', 'زعفرانیه، خیابان مقدس اردبیلی، کوچه 24، پلاک 95', '09123725799', '02122870028', '4000780', 'کارمند', 'تجریش، بازار تجریش، پلاک 67', '02188054548', 'IR690170000000000000575143', 'علی ابراهیمی', NULL, 'active', 1, 1, '2026-09-01 15:00:00', '2026-09-01 15:00:00'),
(61, 'یاسر', 'باقری', 'یاسر باقری', '0010022570', 4, 2, 2, 8, 3, NULL, '1', 3, 6, '1981-12-15', 'محمود', 'married', 'bachelor', 'اقدسیه، بلوار ارتش، کوچه 19، پلاک 113', '09123901318', '02122958412', '4000793', 'کارمند بانک', 'تهران، بزرگراه صدر، پلاک 89', '02188052992', 'IR440170000000000000583062', 'یاسر باقری', NULL, 'active', 1, 1, '2026-09-01 15:07:00', '2026-09-01 15:07:00'),
(62, 'جلال', 'یوسفی', 'جلال یوسفی', '0010022945', 4, 2, 2, 8, 3, NULL, '1', 3, 6, '1993-11-17', 'علی‌اکبر', 'married', 'master', 'دربند، خیابان سعدآباد، کوچه 18، پلاک 19', '09122886569', '02122712152', '4000806', 'کارشناس فنی', 'تهران، خیابان شریعتی، پلاک 134', '02188287434', 'IR190170000000000000590981', 'جلال یوسفی', NULL, 'active', 1, 1, '2026-09-01 15:14:00', '2026-09-01 15:14:00');

SET FOREIGN_KEY_CHECKS = 1;
