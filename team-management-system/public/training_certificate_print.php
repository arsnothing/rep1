<?php
/**
 * چاپ گواهی پایان دوره آموزشی — هر عنصر یک صفحه.
 * اطلاعات روی قالب گواهی نشانده می‌شود: عکس پرونده در کادر بالا سمت چپ،
 * زیر آن تاریخ و زیر آن سریال گواهی؛ نام، نام پدر، کد ملی و عنوان دوره در متن گواهی.
 * مختصات به‌صورت درصدی نسبت به خود قالب تعریف شده‌اند تا در هر اندازهٔ کاغذی درست بنشینند.
 */
require __DIR__ . '/../app/bootstrap.php';
require_login();

$courseOptions = training_course_options();
$courseKey = trim((string)($_GET['course_key'] ?? ''));
if (!isset($courseOptions[$courseKey])) { http_response_code(404); exit('دوره یافت نشد.'); }

$issueDate = substr(trim((string)($_GET['date'] ?? '')), 0, 10);
$serialBase = fa_to_en_digits(trim((string)($_GET['serial'] ?? '')));
$serialNumeric = $serialBase !== '' && ctype_digit($serialBase);
$serialWidth = strlen($serialBase);

$template = basename((string)($_GET['tpl'] ?? ''));
$templatePath = $template !== '' ? storage_path('documents') . $template : '';
$usesCustom = $template !== '' && is_file($templatePath);
$templateUrl = $usesCustom
    ? 'training_certificate_image.php?name=' . rawurlencode($template)
    : asset_url('assets/certificate-template.jpg');
$fallbackNotice = !empty($_GET['fallback']);

$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? ''))))));
if (!$ids) { http_response_code(400); exit('عنصری انتخاب نشده است.'); }

$ph = implode(',', array_fill(0, count($ids), '?'));
$st = $pdo->prepare("SELECT p.id,p.full_name,p.father_name,p.national_id,p.profile_photo_path,ct.category_key AS unit
                     FROM personnel p JOIN category_types ct ON ct.id=p.category_type_id
                     WHERE p.id IN ($ph) ORDER BY p.full_name");
$st->execute($ids);
$rows = array_values(array_filter($st->fetchAll(), static fn($r) => can_view_unit($r['unit'])));
if (!$rows) { http_response_code(403); exit('دسترسی غیرمجاز'); }

/* تاریخ به سه بخش سال / ماه / روز تقسیم می‌شود تا داخل کادرهای چاپ‌شدهٔ قالب بنشیند. */
$jalali = jalali_display($issueDate);          // ۱۴۰۴/۰۶/۱۰
$dateParts = explode('/', fa_to_en_digits($jalali));
$dateYear  = $dateParts[0] ?? '';
$dateMonth = $dateParts[1] ?? '';
$dateDay   = $dateParts[2] ?? '';
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>گواهی <?= e($courseOptions[$courseKey]) ?></title>
<link rel="stylesheet" href="<?= e(asset_url('assets/style.css')) ?>">
<style>
  /* ── مختصات همه بر حسب درصدِ خودِ قالب گواهی ── */
  body{background:#eef2f0;margin:0;padding:22px;font-family:Vazirmatn,Tahoma,Arial,sans-serif}
  .cert-toolbar{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:18px;flex-wrap:wrap}
  .cert-toolbar button,.cert-toolbar a{display:inline-flex;align-items:center;justify-content:center;height:42px;padding:0 18px;
    border:1px solid #d9e4df;border-radius:12px;background:#fff;color:#244b3b;font:inherit;font-weight:800;font-size:13px;
    text-decoration:none;cursor:pointer}
  .cert-toolbar button{background:#244b3b;border-color:#244b3b;color:#fff}
  .cert-note{width:min(1100px,100%);margin:0 auto 16px;padding:12px 16px;border:1px dashed #d8c9a6;border-radius:12px;
    background:#fdf8ec;color:#7a6320;font-size:12.5px;line-height:1.9;text-align:center}

  .cert-page{display:flex;align-items:center;justify-content:center;margin:0 auto 20px;page-break-after:always}
  .cert-page:last-of-type{page-break-after:auto}
  /* نسبت ابعاد قالب با جاوااسکریپت از خود تصویر خوانده می‌شود تا هیچ‌وقت کشیده نشود. */
  .cert-canvas{position:relative;width:min(1100px,100%);aspect-ratio:2200/1700;background:#fff;
    box-shadow:0 10px 30px rgba(23,43,77,.08);overflow:hidden}
  .cert-canvas>img.cert-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:fill;display:block}
  .cert-canvas>*:not(img){position:absolute;z-index:2}

  /* کادر عکس بالا سمت چپ */
  .f-photo{left:11.73%;top:21.94%;width:8.09%;height:14.94%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#fff}
  .f-photo img{width:100%;height:100%;object-fit:cover;display:block}

  /* تاریخ: سال / ماه / روز داخل کادر دوم */
  .f-date{top:39.50%;height:3.6%;display:flex;align-items:center;justify-content:center;
    font-weight:800;color:#12251c;line-height:1;font-variant-numeric:tabular-nums}
  .f-year {left:12.05%;width:2.95%}
  .f-month{left:15.20%;width:2.05%}
  .f-day  {left:17.55%;width:2.15%}

  /* سریال گواهی داخل کادر سوم */
  .f-serial{left:11.60%;top:44.55%;width:8.45%;height:3.6%;display:flex;align-items:center;justify-content:center;
    font-weight:800;color:#12251c;line-height:1;font-variant-numeric:tabular-nums}

  /* متن گواهی */
  .f-line{height:2.6%;display:flex;align-items:center;justify-content:center;font-weight:800;color:#12251c;line-height:1;
    white-space:nowrap;padding:0 .5%;box-sizing:border-box;overflow:hidden}
  .f-name  {left:42.90%;top:53.90%;width:17.30%}
  .f-father{left:28.90%;top:53.90%;width:11.00%}
  .f-nid   {left:13.00%;top:53.90%;width:11.50%}
  .f-course{left:49.70%;top:59.30%;width:16.30%}

  @media print{
    @page{size:A4 landscape;margin:0}
    body{background:#fff;padding:0}
    .cert-toolbar,.cert-note{display:none!important}
    .cert-page{width:100vw;height:100vh;margin:0}
    .cert-canvas{width:auto;height:100vh;max-width:100vw;max-height:100vh;box-shadow:none}
  }
</style>
</head>
<body>
<div class="cert-toolbar">
  <button type="button" onclick="window.print()">ذخیره به‌صورت PDF</button>
  <a href="training_certificate.php?course_key=<?= e(urlencode($courseKey)) ?>">بازگشت به صدور گواهی</a>
</div>
<?php if ($fallbackNotice): ?>
<div class="cert-note">سرور نتوانست فایل PDF آپلودشده را به تصویر تبدیل کند، بنابراین قالب پیش‌فرض سامانه استفاده شد. برای استفاده از قالب خودتان، آن را به‌صورت تصویر (JPG/PNG) آپلود کنید یا افزونهٔ Imagick / Ghostscript روی سرور فعال شود.</div>
<?php endif; ?>

<?php foreach ($rows as $index => $r):
    $serial = $serialNumeric ? str_pad((string)((int)$serialBase + $index), $serialWidth, '0', STR_PAD_LEFT) : $serialBase;
    $hasPhoto = profile_photo_exists($r['profile_photo_path'] ?? '');
?>
<section class="cert-page"><div class="cert-canvas">
  <img class="cert-bg" src="<?= e($templateUrl) ?>" alt="">
  <div class="f-photo">
    <img src="<?= $hasPhoto
        ? 'personnel_file.php?type=photo&amp;id=' . (int)$r['id']
        : e(asset_url('assets/default-avatar.svg')) ?>" alt="">
  </div>
  <div class="f-date f-year"><?= e(fa_digits($dateYear)) ?></div>
  <div class="f-date f-month"><?= e(fa_digits($dateMonth)) ?></div>
  <div class="f-date f-day"><?= e(fa_digits($dateDay)) ?></div>
  <div class="f-serial"><?= e(fa_digits($serial)) ?></div>

  <div class="f-line f-name"><?= e($r['full_name']) ?></div>
  <div class="f-line f-father"><?= e($r['father_name'] ?: '—') ?></div>
  <div class="f-line f-nid"><?= e(fa_digits((string)$r['national_id'])) ?></div>
  <div class="f-line f-course"><?= e($courseOptions[$courseKey]) ?></div>
</div></section>
<?php endforeach; ?>

<script>
/* اندازهٔ قلم متن‌ها نسبت به عرض هر صفحهٔ گواهی تنظیم می‌شود تا در چاپ و نمایش یکسان بماند. */
(function(){
  function applyRatio(){
    var img = document.querySelector('.cert-bg');
    if(img && img.naturalWidth && img.naturalHeight){
      document.querySelectorAll('.cert-canvas').forEach(function(c){
        c.style.aspectRatio = img.naturalWidth + ' / ' + img.naturalHeight;
      });
    }
  }
  function fit(){
    applyRatio();
    document.querySelectorAll('.cert-canvas').forEach(function(page){
      var w = page.getBoundingClientRect().width;
      if(!w) return;
      page.style.setProperty('--cw', w + 'px');
      page.querySelectorAll('.f-line').forEach(function(el){ el.style.fontSize = (w*0.0150)+'px'; });
      page.querySelectorAll('.f-date').forEach(function(el){ el.style.fontSize = (w*0.0135)+'px'; });
      var s = page.querySelector('.f-serial'); if(s) s.style.fontSize = (w*0.0145)+'px';
      // اگر نام یا عنوان دوره از جای خالی پهن‌تر شد، کمی کوچک‌تر می‌شود تا نصفه نماند.
      page.querySelectorAll('.f-line').forEach(function(el){
        var size = parseFloat(el.style.fontSize);
        while(el.scrollWidth > el.clientWidth && size > 6){ size -= .4; el.style.fontSize = size+'px'; }
      });
    });
  }
  window.addEventListener('resize', fit);
  window.addEventListener('beforeprint', fit);
  window.addEventListener('load', function(){ fit(); setTimeout(function(){ fit(); window.print(); }, 500); });
  fit();
})();
</script>
</body>
</html>
