<?php
declare(strict_types=1);

/*
 * لایهٔ اتصال به پایگاه داده و ذخیرهٔ گزارش‌ها.
 * اگر فایل تنظیمات (config/database.php) موجود و فعال باشد و اتصال برقرار شود،
 * گزارش‌ها در MySQL/MariaDB ذخیره می‌شوند؛ در غیر این صورت null برمی‌گرداند تا
 * فراخواننده به ذخیره‌سازی JSON بازگردد.
 */

function reportDatabaseConfig(): ?array {
  $path = __DIR__ . '/../config/database.php';
  if (!is_file($path)) return null;
  $config = require $path;
  if (!is_array($config) || empty($config['enabled'])) return null;
  return $config;
}

function reportDatabaseConnection(): ?PDO {
  static $pdo = null;
  static $tried = false;
  if ($tried) return $pdo;
  $tried = true;

  $config = reportDatabaseConfig();
  if ($config === null || !class_exists('PDO')) return null;

  $charset = $config['charset'] ?? 'utf8mb4';
  $dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['host'] ?? '127.0.0.1',
    (int)($config['port'] ?? 3306),
    $config['database'] ?? '',
    $charset
  );

  try {
    $pdo = new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
  } catch (Throwable $error) {
    // اتصال ناموفق نباید ثبت گزارش را متوقف کند؛ به JSON بازمی‌گردیم.
    $pdo = null;
  }
  return $pdo;
}

function jsonColumn($value): ?string {
  if ($value === null) return null;
  $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  return $encoded === false ? null : $encoded;
}

/**
 * ذخیرهٔ یک گزارش نرمال‌شده در پایگاه داده.
 * در صورت نبود پایگاه داده null و در صورت موفقیت شناسهٔ درج‌شده را برمی‌گرداند.
 */
function saveReportToDatabase(array $report): ?int {
  $pdo = reportDatabaseConnection();
  if ($pdo === null) return null;

  $form     = is_array($report['form'] ?? null) ? $report['form'] : [];
  $location = is_array($report['location'] ?? null) ? $report['location'] : [];
  $time     = is_array($report['time'] ?? null) ? $report['time'] : [];
  $incident = is_array($form['incident'] ?? null) ? $form['incident'] : [];

  $known = null;
  if (array_key_exists('known', $location)) $known = $location['known'] ? 1 : 0;

  try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
      'INSERT INTO reports
        (public_id, report_type, category, subtype,
         location_mode, location_known, province, city, latitude, longitude,
         postal_code, building_plaque, floor, unit,
         time_mode, time_date, time_clock, time_approximate,
         form_data, created_at)
       VALUES
        (:public_id, :report_type, :category, :subtype,
         :location_mode, :location_known, :province, :city, :latitude, :longitude,
         :postal_code, :building_plaque, :floor, :unit,
         :time_mode, :time_date, :time_clock, :time_approximate,
         :form_data, :created_at)'
    );

    $stmt->execute([
      ':public_id'        => $report['id'] ?? bin2hex(random_bytes(8)),
      ':report_type'      => $report['reportType'] ?? 'گزارش',
      ':category'         => $report['category'] ?? '',
      ':subtype'          => $report['subtype'] ?? null,
      ':location_mode'    => $location['mode'] ?? null,
      ':location_known'   => $known,
      ':province'         => $location['province'] ?? null,
      ':city'             => $location['city'] ?? null,
      ':latitude'         => isset($location['latitude']) && is_numeric($location['latitude']) ? $location['latitude'] : null,
      ':longitude'        => isset($location['longitude']) && is_numeric($location['longitude']) ? $location['longitude'] : null,
      ':postal_code'      => $location['postalCode'] ?? null,
      ':building_plaque'  => $location['buildingPlaque'] ?? null,
      ':floor'            => $location['floor'] ?? null,
      ':unit'             => $location['unit'] ?? null,
      ':time_mode'        => $time['mode'] ?? null,
      ':time_date'        => $time['date'] ?? null,
      ':time_clock'       => $time['clock'] ?? null,
      ':time_approximate' => $time['approximate'] ?? null,
      ':form_data'        => jsonColumn($form) ?? '{}',
      ':created_at'       => isset($report['created_at'])
        ? date('Y-m-d H:i:s', strtotime((string)$report['created_at']))
        : date('Y-m-d H:i:s'),
    ]);

    $reportId = (int)$pdo->lastInsertId();

    // موضوعات «نوع جرم یا تخلف»
    if (isset($incident['crimeTypes']) && is_array($incident['crimeTypes'])) {
      $topicStmt = $pdo->prepare(
        'INSERT INTO report_incident_topics (report_id, topic_id, topic_label, answers)
         VALUES (:report_id, :topic_id, :topic_label, :answers)'
      );
      foreach ($incident['crimeTypes'] as $topicId => $answers) {
        if (!is_array($answers) || !count($answers)) continue;
        $topicStmt->execute([
          ':report_id'   => $reportId,
          ':topic_id'    => (string)$topicId,
          ':topic_label' => is_string($answers['__label'] ?? null) ? $answers['__label'] : null,
          ':answers'     => jsonColumn($answers),
        ]);
      }
    }

    // بخش‌های مشترک گزارش وقوع
    $related = isset($incident['related']) && is_array($incident['related']) && count($incident['related']) ? $incident['related'] : null;
    $source  = isset($incident['source'])  && is_array($incident['source'])  && count($incident['source'])  ? $incident['source']  : null;
    if ($related !== null || $source !== null) {
      $detailStmt = $pdo->prepare(
        'INSERT INTO report_incident_details (report_id, related, source)
         VALUES (:report_id, :related, :source)'
      );
      $detailStmt->execute([
        ':report_id' => $reportId,
        ':related'   => jsonColumn($related),
        ':source'    => jsonColumn($source),
      ]);
    }

    // مستندات
    if (isset($report['documents']) && is_array($report['documents'])) {
      $docStmt = $pdo->prepare(
        'INSERT INTO report_documents (report_id, doc_type, name, mime, size_bytes, data)
         VALUES (:report_id, :doc_type, :name, :mime, :size_bytes, :data)'
      );
      foreach ($report['documents'] as $document) {
        if (!is_array($document)) continue;
        $docStmt->execute([
          ':report_id'  => $reportId,
          ':doc_type'   => $document['type'] ?? null,
          ':name'       => $document['name'] ?? null,
          ':mime'       => $document['mime'] ?? null,
          ':size_bytes' => isset($document['size']) && is_numeric($document['size']) ? (int)$document['size'] : null,
          ':data'       => $document['data'] ?? null,
        ]);
      }
    }

    $pdo->commit();
    return $reportId;
  } catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // در صورت خطای درج، به ذخیره‌سازی JSON بازمی‌گردیم.
    return null;
  }
}
