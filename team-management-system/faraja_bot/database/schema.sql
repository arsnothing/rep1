-- =====================================================================
--  پایگاه دادهٔ سامانهٔ گزارش (فرجا)
--  MySQL 5.7+ / MariaDB 10.2+  (به‌دلیل ستون‌های نوع JSON)
--  charset: utf8mb4 برای پشتیبانی کامل از فارسی و ایموجی
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `faraja_reports`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `faraja_reports`;

-- ---------------------------------------------------------------------
--  جدول اصلی گزارش‌ها
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reports` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id`         CHAR(16)        NOT NULL,               -- شناسهٔ عمومی (hex)
  `report_type`       VARCHAR(32)     NOT NULL DEFAULT 'گزارش',
  `category`          VARCHAR(32)     NOT NULL,               -- افراد / املاک / رویداد / اشیاء
  `subtype`           VARCHAR(64)         NULL,               -- زیربخش (مثلاً «بسته مشکوک»)

  -- محل وقوع
  `location_mode`     VARCHAR(16)         NULL,               -- current / map / unknown
  `location_known`    TINYINT(1)          NULL,
  `province`          VARCHAR(64)         NULL,
  `city`              VARCHAR(64)         NULL,
  `latitude`          DECIMAL(10,7)       NULL,
  `longitude`         DECIMAL(10,7)       NULL,
  `postal_code`       VARCHAR(20)         NULL,
  `building_plaque`   VARCHAR(20)         NULL,
  `floor`             VARCHAR(20)         NULL,
  `unit`              VARCHAR(20)         NULL,

  -- زمان وقوع
  `time_mode`         VARCHAR(16)         NULL,               -- اکنون / دقیق / تقریبی
  `time_date`         VARCHAR(16)         NULL,               -- 1403/05/12
  `time_clock`        VARCHAR(8)          NULL,               -- 14:30
  `time_approximate`  VARCHAR(255)        NULL,

  -- کل داده‌های فرم (شامل بخش‌های متغیر: افراد، خودروها، ساکنین ملک و …)
  `form_data`         JSON            NOT NULL,

  `created_at`        DATETIME        NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reports_public_id` (`public_id`),
  KEY `idx_reports_category` (`category`),
  KEY `idx_reports_subtype` (`subtype`),
  KEY `idx_reports_province_city` (`province`, `city`),
  KEY `idx_reports_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  موضوعات «نوع جرم یا تخلف» انتخاب‌شده در گزارش وقوع
--  (هر گزارش می‌تواند چند موضوع داشته باشد)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `report_incident_topics` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id`     BIGINT UNSIGNED NOT NULL,
  `topic_id`      VARCHAR(64)     NOT NULL,                   -- کلید موضوع (theft، explosion و …)
  `topic_label`   VARCHAR(128)        NULL,                   -- عنوان فارسی موضوع
  `answers`       JSON                NULL,                   -- پاسخ سؤالات همان موضوع
  PRIMARY KEY (`id`),
  KEY `idx_incident_report` (`report_id`),
  KEY `idx_incident_topic` (`topic_id`),
  CONSTRAINT `fk_incident_report`
    FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  بخش‌های مشترک گزارش وقوع: «همکاران و افراد مرتبط» و «نحوهٔ اطلاع»
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `report_incident_details` (
  `report_id`     BIGINT UNSIGNED NOT NULL,
  `related`       JSON                NULL,                   -- همکاران و افراد مرتبط
  `source`        JSON                NULL,                   -- نحوهٔ اطلاع
  PRIMARY KEY (`report_id`),
  CONSTRAINT `fk_incident_details_report`
    FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  مستندات پیوست‌شده به گزارش
--  data به‌صورت data-URI (base64) ذخیره می‌شود؛ برای حجم بالا می‌توانید
--  به‌جای LONGTEXT مسیر فایل روی دیسک را نگه دارید.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `report_documents` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id`     BIGINT UNSIGNED NOT NULL,
  `doc_type`      VARCHAR(16)         NULL,                   -- image / file
  `name`          VARCHAR(255)        NULL,
  `mime`          VARCHAR(128)        NULL,
  `size_bytes`    INT UNSIGNED        NULL,
  `data`          LONGTEXT            NULL,                   -- data:...;base64,...
  PRIMARY KEY (`id`),
  KEY `idx_documents_report` (`report_id`),
  CONSTRAINT `fk_documents_report`
    FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
