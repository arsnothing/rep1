<?php
/** نمایش تصویر گواهی دوره در صفحهٔ چاپ (فقط برای کاربران واردشده). */
require __DIR__ . '/../app/bootstrap.php';
require_login();

$name = basename((string)($_GET['name'] ?? ''));
if ($name === '' || !preg_match('/^certificate_[A-Za-z0-9_]*_?[a-f0-9]{20}\.(jpg|png|webp)$/', $name)) {
    http_response_code(404); exit('فایل یافت نشد.');
}
$path = storage_path('documents') . $name;
if (!is_file($path)) { http_response_code(404); exit('فایل یافت نشد.'); }

$mime = mime_content_type($path) ?: '';
if (!str_starts_with($mime, 'image/')) { http_response_code(404); exit('فایل یافت نشد.'); }

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($path);
