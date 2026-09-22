-- ---------------------------------------------------------------------
-- اگر دیتابیس قدیمی را نگه داشته‌اید و نمی‌خواهید team_management.sql را
-- از نو import کنید، فقط همین فایل را روی دیتابیس team_management_v2 اجرا کنید.
-- ستون‌ها و جدول‌هایی که نسخه جدید سامانه لازم دارد را اضافه می‌کند.
-- اجرای دوباره آن بی‌خطر است. (MariaDB 10.2+)
-- ---------------------------------------------------------------------

ALTER TABLE `personnel`
  ADD COLUMN IF NOT EXISTS `commander_number` VARCHAR(30) DEFAULT NULL AFTER `city_id`,
  ADD COLUMN IF NOT EXISTS `district_id` INT(10) UNSIGNED DEFAULT NULL AFTER `city_id`;

ALTER TABLE `personnel_history`
  ADD COLUMN IF NOT EXISTS `commander_number` VARCHAR(30) DEFAULT NULL AFTER `city_id`,
  ADD COLUMN IF NOT EXISTS `district_id` INT(10) UNSIGNED DEFAULT NULL AFTER `city_id`;

-- شماره قائد همه عناصر فعلی: ۱ (در صورت خالی بودن)
UPDATE `personnel` SET `commander_number` = '1' WHERE `commander_number` IS NULL OR `commander_number` = '';

-- مناطق شهرستان (برای فیلد «منطقه» در فرم عنصر)
CREATE TABLE IF NOT EXISTS `city_districts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `city_id` int(10) UNSIGNED NOT NULL,
  `district_number` tinyint(3) UNSIGNED NOT NULL,
  `district_name` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_city_district` (`city_id`,`district_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول متقاضیان آموزش
CREATE TABLE IF NOT EXISTS `training_applicants` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_key` varchar(100) NOT NULL,
  `personnel_id` int(10) UNSIGNED NOT NULL,
  `applicant_type` varchar(20) NOT NULL DEFAULT 'new',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_training_applicant` (`course_key`,`personnel_id`),
  KEY `idx_training_applicants_person` (`personnel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول تجهیزات حکم ماموریتی
CREATE TABLE IF NOT EXISTS `personnel_order_equipment` (
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
  KEY `idx_order_equipment_stock` (`stock_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- عکس‌های نمونه (demo) عکس واقعی نیستند
UPDATE `personnel` SET `profile_photo_path` = NULL WHERE `profile_photo_path` LIKE '%demo-%';

-- ستون‌های تکمیلی پرونده عنصر (شهرت، دین، مذهب، سلامت، گواهینامه، تلفن ثابت و معرف)
ALTER TABLE `personnel`
  ADD COLUMN IF NOT EXISTS `alias_name` VARCHAR(120) DEFAULT NULL AFTER `father_name`,
  ADD COLUMN IF NOT EXISTS `religion` VARCHAR(60) DEFAULT NULL AFTER `alias_name`,
  ADD COLUMN IF NOT EXISTS `denomination` VARCHAR(60) DEFAULT NULL AFTER `religion`,
  ADD COLUMN IF NOT EXISTS `health_status` VARCHAR(20) DEFAULT NULL AFTER `denomination`,
  ADD COLUMN IF NOT EXISTS `health_note` VARCHAR(255) DEFAULT NULL AFTER `health_status`,
  ADD COLUMN IF NOT EXISTS `license_types` VARCHAR(120) DEFAULT NULL AFTER `health_note`,
  ADD COLUMN IF NOT EXISTS `license_level` VARCHAR(20) DEFAULT NULL AFTER `license_types`,
  ADD COLUMN IF NOT EXISTS `landline_phone` VARCHAR(20) DEFAULT NULL AFTER `license_level`,
  ADD COLUMN IF NOT EXISTS `referrer_first_name` VARCHAR(80) DEFAULT NULL AFTER `landline_phone`,
  ADD COLUMN IF NOT EXISTS `referrer_last_name` VARCHAR(120) DEFAULT NULL AFTER `referrer_first_name`,
  ADD COLUMN IF NOT EXISTS `referrer_national_id` CHAR(10) DEFAULT NULL AFTER `referrer_last_name`,
  ADD COLUMN IF NOT EXISTS `referrer_mobile` VARCHAR(20) DEFAULT NULL AFTER `referrer_national_id`,
  ADD COLUMN IF NOT EXISTS `languages` VARCHAR(255) DEFAULT NULL AFTER `referrer_mobile`,
  ADD COLUMN IF NOT EXISTS `criminal_record_issue_date` DATE DEFAULT NULL AFTER `languages`;

ALTER TABLE `personnel_history`
  ADD COLUMN IF NOT EXISTS `alias_name` VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `religion` VARCHAR(60) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `denomination` VARCHAR(60) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `health_status` VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `health_note` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `license_types` VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `license_level` VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `landline_phone` VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `referrer_first_name` VARCHAR(80) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `referrer_last_name` VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `referrer_national_id` CHAR(10) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `referrer_mobile` VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `languages` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `criminal_record_issue_date` DATE DEFAULT NULL;

-- بررسی نتیجه:
SHOW COLUMNS FROM `personnel` LIKE 'commander_number';

-- ---------------------------------------------------------------------
-- سریال گواهی پایان دوره (تب «صدور گواهی آموزش»)
-- ---------------------------------------------------------------------
ALTER TABLE `training_records`
  ADD COLUMN IF NOT EXISTS `certificate_serial` VARCHAR(50) DEFAULT NULL AFTER `training_date`;

-- ---------------------------------------------------------------------
-- توضیحات دوره (بخش «دوره‌های گذرانده» در پرونده عنصر)
-- ---------------------------------------------------------------------
ALTER TABLE `training_records`
  ADD COLUMN IF NOT EXISTS `description` TEXT DEFAULT NULL AFTER `training_date`;

-- ---------------------------------------------------------------------
-- وضعیت گواهینامه: سطح تسلط جداگانه برای هر نوع + عنوان گواهینامه ویژه
-- (قالب جدید license_level مثل «motorcycle:high,grade2:low»)
-- ---------------------------------------------------------------------
ALTER TABLE `personnel`
  MODIFY COLUMN `license_level` VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `license_special_title` VARCHAR(120) DEFAULT NULL AFTER `license_level`;

ALTER TABLE `personnel_history`
  MODIFY COLUMN `license_level` VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `license_special_title` VARCHAR(120) DEFAULT NULL AFTER `license_level`;

-- ---------------------------------------------------------------------
-- اطلاعات فردی جدید: کد پستی، مهارت ورزشی و حرفه و تخصص
-- (زبان خارجی از فهرست انتخابی به متن آزاد تغییر کرده است)
-- ---------------------------------------------------------------------
ALTER TABLE `personnel`
  ADD COLUMN IF NOT EXISTS `postal_code` VARCHAR(10) DEFAULT NULL AFTER `languages`,
  ADD COLUMN IF NOT EXISTS `sport_skill` VARCHAR(150) DEFAULT NULL AFTER `postal_code`,
  ADD COLUMN IF NOT EXISTS `profession` VARCHAR(150) DEFAULT NULL AFTER `sport_skill`;

ALTER TABLE `personnel_history`
  ADD COLUMN IF NOT EXISTS `postal_code` VARCHAR(10) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `sport_skill` VARCHAR(150) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `profession` VARCHAR(150) DEFAULT NULL;

-- ---------------------------------------------------------------------
-- حرفه و مهارت + سابقه عضویت (پاپ‌آپ‌های جدید فرم)
-- حذف ستون‌های قدیمی sport_skill، profession، postal_code و افزودن ستون‌های جدید
-- professional_skills (JSON برای آرایهٔ اینپوت‌ها)
-- membership_history (CSV برای کلیدهای faraja, army, sepah, volunteer_police)
-- ---------------------------------------------------------------------
ALTER TABLE `personnel`
  DROP COLUMN IF EXISTS `sport_skill`,
  DROP COLUMN IF EXISTS `profession`,
  DROP COLUMN IF EXISTS `postal_code`,
  ADD COLUMN IF NOT EXISTS `professional_skills` TEXT DEFAULT NULL AFTER `languages`,
  ADD COLUMN IF NOT EXISTS `membership_history` VARCHAR(255) DEFAULT NULL AFTER `professional_skills`;

ALTER TABLE `personnel_history`
  DROP COLUMN IF EXISTS `sport_skill`,
  DROP COLUMN IF EXISTS `profession`,
  DROP COLUMN IF EXISTS `postal_code`,
  ADD COLUMN IF NOT EXISTS `professional_skills` TEXT DEFAULT NULL AFTER `languages`,
  ADD COLUMN IF NOT EXISTS `membership_history` VARCHAR(255) DEFAULT NULL AFTER `professional_skills`;

-- ---------------------------------------------------------------------
-- امور رفاهی: عنوان (سفر / بسته معیشتی / کارت هدیه / واریز نقدی)،
-- شماره سری و ستون «نوع» برای مواردی که مبلغ ندارند
-- ---------------------------------------------------------------------
ALTER TABLE `financial_transactions`
  ADD COLUMN IF NOT EXISTS `welfare_title` VARCHAR(30) NOT NULL DEFAULT 'cash' AFTER `transfer_type`,
  ADD COLUMN IF NOT EXISTS `series` VARCHAR(50) DEFAULT NULL AFTER `reason`;

ALTER TABLE `financial_transaction_items`
  MODIFY COLUMN `amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `detail` VARCHAR(150) DEFAULT NULL AFTER `amount`;

-- ---------------------------------------------------------------------
-- پرونده انضباطی: نام تشویق‌کننده/تذکردهنده/توبیخ‌کننده + برگه سند
-- (برگه تشویق/توبیخ در قالب personnel_documents ذخیره و از اینجا لینک می‌شود)
-- ---------------------------------------------------------------------
ALTER TABLE `disciplinary_reports`
  ADD COLUMN IF NOT EXISTS `issuer_name` VARCHAR(120) DEFAULT NULL AFTER `subject_title`,
  ADD COLUMN IF NOT EXISTS `document_id` INT(10) UNSIGNED DEFAULT NULL AFTER `description`;
