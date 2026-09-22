<?php
declare(strict_types=1);

/*
 * تنظیمات اتصال به پایگاه دادهٔ MySQL/MariaDB.
 *
 * برای فعال‌سازی پایگاه داده:
 *   ۱) این فایل را به «config/database.php» کپی کنید.
 *   ۲) مقادیر زیر را با اطلاعات سرور خود پر کنید.
 *   ۳) اسکیمای «database/schema.sql» را روی همان دیتابیس اجرا کنید.
 *
 * تا زمانی که فایل «config/database.php» ساخته نشود، سامانه مانند قبل
 * گزارش‌ها را در «data/reports.json» ذخیره می‌کند و بدون دیتابیس هم کار می‌کند.
 *
 * می‌توانید به‌جای مقادیر ثابت از متغیرهای محیطی (getenv) استفاده کنید.
 */

return [
  'enabled'  => true,
  'host'     => getenv('DB_HOST') ?: '127.0.0.1',
  'port'     => (int)(getenv('DB_PORT') ?: 3306),
  'database' => getenv('DB_NAME') ?: 'faraja_reports',
  'username' => getenv('DB_USER') ?: 'faraja',
  'password' => getenv('DB_PASS') ?: '',
  'charset'  => 'utf8mb4',
];
