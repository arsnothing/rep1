<?php
require_once __DIR__ . '/../bootstrap.php';
$appName = $config['app']['name'];
$helpData = require __DIR__ . '/../help.php';
$pageHelp = $helpData[basename($_SERVER['PHP_SELF'] ?? '')] ?? null;
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$navItems = [
    ['dashboard.php','داشبورد'],
    ['finance_history.php','امور مالی','finance_form.php'],
    ['equipment.php','آماد','equipment_status.php','equipment_return.php'],
    ['orders.php','احکام'],
    ['training_applicants.php','آموزش','training.php','training_report.php'],
    ['map.php','حوزه استحفاظی'],
    ['personnel.php','فهرست عناصر','personnel_view.php','personnel_file.php'],
];
?>
<!doctype html><html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($appName) ?></title>
<script>(function(){try{var t=localStorage.getItem('tms-theme');if(t==='dark'||t==='light')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<link rel="stylesheet" href="<?= e(asset_url('assets/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/theme.css')) ?>">
</head><body>
<header class="topbar"><div class="brand"><img class="brand-logo" src="<?= e($config['app']['base_url']) ?>/assets/logo.png" alt="نشان سامانه"><div><strong><?= e($appName) ?></strong><small>سازمان رزم سوم فراجا</small></div></div>
<nav class="topnav">
<?php foreach ($navItems as $item): ?>
<?php $isActive = $currentPage === $item[0] || in_array($currentPage, array_slice($item, 2), true); ?>
<a class="nav-btn<?= $isActive ? ' active' : '' ?>" href="<?= e($config['app']['base_url']) ?>/<?= e($item[0]) ?>"><?= e($item[1]) ?></a>
<?php endforeach; ?>
<?php if (can_manage_personnel()): ?><a class="nav-btn nav-accent<?= $currentPage === 'personnel_form.php' ? ' active-form' : '' ?>" href="<?= e($config['app']['base_url']) ?>/personnel_form.php"><span class="nav-accent-label">افزودن عنصر</span><span class="nav-accent-plus" aria-hidden="true">+</span></a><?php endif; ?>
<button type="button" class="nav-icon-btn" id="themeToggle" aria-label="تغییر حالت روشن/تاریک" title="حالت روشن / تاریک">
  <svg class="ic-moon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5a8.5 8.5 0 1 0 10.7 10.7z" fill="currentColor"/></svg>
  <svg class="ic-sun" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></g></svg>
</button>

<a class="nav-btn nav-logout" href="<?= e($config['app']['base_url']) ?>/logout.php">خروج</a>
</nav></header>
<?php if ($pageHelp): ?>
<div class="help-modal" id="helpModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="helpTitle">
  <div class="help-backdrop" data-help-close></div>
  <section class="help-card">
    <header class="help-head">
      <div class="help-head-icon" aria-hidden="true">؟</div>
      <h2 id="helpTitle"><?= e($pageHelp[0]) ?></h2>
      <button type="button" class="help-close" data-help-close aria-label="بستن">×</button>
    </header>
    <div class="help-body">
      <?php foreach ($pageHelp[1] as [$helpHeading, $helpItems]): ?>
      <section class="help-section">
        <h3><?= e($helpHeading) ?></h3>
        <ul><?php foreach ($helpItems as $helpItem): ?><li><?= e($helpItem) ?></li><?php endforeach; ?></ul>
      </section>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php endif; ?>
<main class="container">
<?php
/* ---------------------------------------------------------------------------
 * منطق دکمه «بازگشت»:
 *  - صفحه‌های اصلی منو و تب‌های داخل آن‌ها (مثل تب‌های آموزش، آماد، احکام) بازگشت ندارند؛
 *    کاربر با خود تب‌ها و منوی بالا جابه‌جا می‌شود.
 *  - فقط صفحه‌هایی که «داخل» یک صفحه دیگر باز می‌شوند بازگشت دارند و به همان صفحه برمی‌گردند:
 *      نمایش پروفایل  ← صفحه‌ای که از آن آمده‌اید (پیش‌فرض: فهرست عناصر)
 *      ویرایش پرونده ← نمایش پروفایل همان عنصر
 *      ثبت واریز      ← امور مالی
 *      تاریخچه تغییرات ← فهرست عناصر  ·  جزئیات تاریخچه ← تاریخچه تغییرات
 *      پرونده انضباطی و زیرصفحه‌هایش ← صفحه والد خودشان
 * ------------------------------------------------------------------------- */
$pageLabels = [
    'dashboard.php'=>'داشبورد','finance_history.php'=>'امور مالی','equipment.php'=>'آماد','equipment_status.php'=>'آماد',
    'equipment_return.php'=>'آماد','orders.php'=>'احکام','training_applicants.php'=>'آموزش','training.php'=>'آموزش',
    'training_report.php'=>'آموزش','map.php'=>'حوزه استحفاظی','personnel.php'=>'فهرست عناصر',
    'personnel_history.php'=>'تاریخچه تغییرات','personnel_view.php'=>'پروفایل',
];
$qid = (int)($_GET['id'] ?? 0);
$backTarget = null;
switch ($currentPage) {
    case 'personnel_view.php':
        // صفحه مبدأ را به خاطر می‌سپاریم تا با عوض‌کردن تب‌های پروفایل از دست نرود.
        $originKey = 'pv_origin_' . $qid;
        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $refPath = (string)parse_url($ref, PHP_URL_PATH);
        $refPage = basename($refPath);
        $refHost = (string)parse_url($ref, PHP_URL_HOST);
        $sameHost = $refHost === '' || $refHost === ($_SERVER['HTTP_HOST'] ?? '') || $refHost === parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if ($ref !== '' && $sameHost && isset($pageLabels[$refPage]) && $refPage !== 'personnel_view.php') {
            $refQuery = (string)parse_url($ref, PHP_URL_QUERY);
            $_SESSION[$originKey] = [$refPage . ($refQuery !== '' ? '?' . $refQuery : ''), $pageLabels[$refPage]];
        }
        $backTarget = $_SESSION[$originKey] ?? ['personnel.php', 'فهرست عناصر'];
        break;
    case 'personnel_form.php':
        if ($qid) $backTarget = ['personnel_view.php?id=' . $qid, 'نمایش پروفایل'];
        break;
    case 'personnel_history.php':      $backTarget = ['personnel.php', 'فهرست عناصر']; break;
    case 'personnel_history_view.php': $backTarget = ['personnel_history.php', 'تاریخچه تغییرات']; break;
    case 'finance_form.php':           $backTarget = ['finance_history.php', 'امور مالی']; break;
    case 'disciplinary.php':
        $backTarget = $qid ? ['personnel_view.php?id=' . $qid . '&tab=disciplinary', 'پروفایل'] : ['personnel.php', 'فهرست عناصر'];
        break;
    case 'disciplinary_form.php':
        $backTarget = ['disciplinary.php' . ($qid ? '?id=' . $qid : ''), 'پرونده انضباطی'];
        break;
    case 'disciplinary_reports.php':
        $pidBack = (int)($_GET['personnel_id'] ?? 0);
        $backTarget = ['disciplinary.php' . ($pidBack ? '?id=' . $pidBack : ''), 'پرونده انضباطی'];
        break;
}
?>
<?php if ($backTarget && empty($hideBackbar)): ?>
  <div class="global-backbar">
    <a class="global-back-btn" href="<?= e($config['app']['base_url']) ?>/<?= e($backTarget[0]) ?>" aria-label="بازگشت به <?= e($backTarget[1]) ?>">بازگشت</a>
  </div>
<?php endif; ?>
