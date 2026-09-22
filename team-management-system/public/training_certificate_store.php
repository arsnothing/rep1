<?php
/**
 * صدور گواهی آموزش: قالب گواهی (PDF یا تصویر)، تاریخ و سریال برای عناصر انتخاب‌شده ثبت
 * می‌شود و در پایان صفحهٔ چاپ گواهی‌ها باز می‌شود تا خروجی PDF گرفته شود.
 */
require __DIR__ . '/../app/bootstrap.php';
require_login();
if (!can_manage_personnel()) { http_response_code(403); exit('دسترسی غیرمجاز'); }
verify_csrf();

$courseOptions = training_course_options();
$courseKey = trim((string)($_POST['course_key'] ?? ''));
$dateInput = trim((string)($_POST['issue_date_jalali'] ?? ''));
$serialInput = fa_to_en_digits(trim((string)($_POST['certificate_serial'] ?? '')));
$ids = $_POST['personnel_ids'] ?? [];
$ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []))));

$back = static function (string $flag) use ($courseKey): never {
    redirect('training_certificate.php?' . http_build_query(array_filter(['course_key' => $courseKey, 'error' => $flag])));
};

if (!isset($courseOptions[$courseKey])) $back('course');
if (!$ids) $back('people');
if ($serialInput === '') $back('serial');

$issueDate = parse_jalali_input($dateInput);
if ($issueDate === null) $back('date');

/* ---- قالب گواهی: PDF یا تصویر، تا ۸ مگابایت (اختیاری) ---- */
$allowedMime = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
];
$templateName = '';      // نام فایل تصویری قالب در storage/documents
$sourceName   = '';      // فایل اصلی آپلودشده (برای ثبت در مستندات)
$sourceMime   = '';
$sourceSize   = 0;
$templateFallback = 0;   // ۱ یعنی سرور نتوانست PDF را تبدیل کند و قالب پیش‌فرض به‌کار رفت

$file = $_FILES['certificate_image'] ?? null;
$hasUpload = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && ($file['name'] ?? '') !== '';

if ($hasUpload) {
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 8 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) $back('image');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowedMime[$mime])) $back('image');
    $ext = $allowedMime[$mime];

    $stored = 'certificate_' . preg_replace('/[^a-z0-9_]/i', '', $courseKey) . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], storage_path('documents') . $stored)) $back('image');

    $sourceName = $stored; $sourceMime = $mime; $sourceSize = (int)$file['size'];

    if ($ext === 'pdf') {
        // صفحهٔ اول PDF به تصویر تبدیل می‌شود تا بتوان اطلاعات را روی آن نشاند.
        $png = preg_replace('/\.pdf$/i', '.png', $stored);
        if (pdf_first_page_to_png(storage_path('documents') . $stored, storage_path('documents') . $png)) {
            $templateName = $png;
        } else {
            $templateFallback = 1;   // قالب پیش‌فرض سامانه استفاده می‌شود
        }
    } else {
        $templateName = $stored;
    }
}

/* ---- فقط عناصر فعالِ داخل محدودهٔ دسترسی کاربر ---- */
$ph = implode(',', array_fill(0, count($ids), '?'));
$st = $pdo->prepare("SELECT p.id,ct.category_key AS unit
                     FROM personnel p JOIN category_types ct ON ct.id=p.category_type_id
                     WHERE p.id IN ($ph) AND " . active_personnel_sql('p') . ' ORDER BY p.full_name');
$st->execute($ids);
$valid = [];
foreach ($st->fetchAll() as $row) { if (can_view_unit($row['unit'])) $valid[] = (int)$row['id']; }
if (!$valid) $back('people');

/* ---- سریال: اگر عددی باشد برای هر عنصر یکی زیاد می‌شود ---- */
$serialNumeric = ctype_digit($serialInput);
$serialWidth = strlen($serialInput);
$serialFor = static function (int $index) use ($serialInput, $serialNumeric, $serialWidth): string {
    if (!$serialNumeric) return $serialInput;
    return str_pad((string)((int)$serialInput + $index), $serialWidth, '0', STR_PAD_LEFT);
};

try {
    $hasDate = (bool)$pdo->query("SHOW COLUMNS FROM training_records LIKE 'training_date'")->fetch();
    $hasSerial = (bool)$pdo->query("SHOW COLUMNS FROM training_records LIKE 'certificate_serial'")->fetch();

    $pdo->beginTransaction();

    $insRecord = $hasDate
        ? $pdo->prepare('INSERT INTO training_records(personnel_id,course_key,course_name,status,training_date,created_by,updated_by) VALUES(?,?,?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE course_name=VALUES(course_name),status=VALUES(status),training_date=VALUES(training_date),updated_by=VALUES(updated_by),id=LAST_INSERT_ID(id)')
        : $pdo->prepare('INSERT INTO training_records(personnel_id,course_key,course_name,status,created_by,updated_by) VALUES(?,?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE course_name=VALUES(course_name),status=VALUES(status),updated_by=VALUES(updated_by),id=LAST_INSERT_ID(id)');
    $findRecord = $pdo->prepare('SELECT id FROM training_records WHERE personnel_id=? AND course_key=? LIMIT 1');
    $setSerial  = $hasSerial ? $pdo->prepare('UPDATE training_records SET certificate_serial=? WHERE id=?') : null;
    $insDoc     = $pdo->prepare('INSERT INTO training_documents(training_record_id,original_name,stored_name,mime_type,file_size,created_by) VALUES(?,?,?,?,?,?)');
    $hasApplicants = ensure_training_applicants_table();
    $delApplicant  = $hasApplicants ? $pdo->prepare('DELETE FROM training_applicants WHERE course_key=? AND personnel_id=?') : null;

    $userId = user()['id'] ?? null;
    $docLabel = 'گواهی ' . $courseOptions[$courseKey] . ($sourceName !== '' ? '.' . pathinfo($sourceName, PATHINFO_EXTENSION) : '');

    foreach ($valid as $index => $pid) {
        if ($hasDate) $insRecord->execute([$pid, $courseKey, $courseOptions[$courseKey], 'completed', $issueDate, $userId, $userId]);
        else          $insRecord->execute([$pid, $courseKey, $courseOptions[$courseKey], 'completed', $userId, $userId]);

        $recordId = (int)$pdo->lastInsertId();
        if ($recordId < 1) { $findRecord->execute([$pid, $courseKey]); $recordId = (int)$findRecord->fetchColumn(); }
        if ($recordId < 1) throw new RuntimeException('شناسه دوره یافت نشد.');

        if ($setSerial) $setSerial->execute([$serialFor($index), $recordId]);
        if ($sourceName !== '') $insDoc->execute([$recordId, $docLabel, $sourceName, $sourceMime, $sourceSize, $userId]);
        if ($delApplicant) $delApplicant->execute([$courseKey, $pid]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[training_certificate_store] ' . $e->getMessage());
    $back('save');
}

// صفحهٔ چاپ گواهی‌ها؛ از همان‌جا «ذخیره به‌صورت PDF» انجام می‌شود.
redirect('training_certificate_print.php?' . http_build_query(array_filter([
    'course_key' => $courseKey,
    'date'       => $issueDate,
    'serial'     => $serialInput,
    'tpl'        => $templateName,
    'fallback'   => $templateFallback ?: null,
    'ids'        => implode(',', $valid),
], static fn($v) => $v !== null && $v !== '')));
