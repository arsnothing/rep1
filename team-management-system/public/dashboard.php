<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();

/* اگر به هر دلیل helper criminal_record_expiring_personnel در bootstrap نبود
   (مثلاً فایل قدیمی deploy شده)، اینجا به‌صورت محلی تعریف می‌شود تا داشبورد
   بدون خطا کار کند. */
if (!function_exists('criminal_record_expiring_personnel')) {
    function criminal_record_expiring_personnel(int $warningDays = 30, int $validityMonths = 6): array {
        global $pdo;
        static $cache = null;
        $cacheKey = $warningDays.'-'.$validityMonths;
        if (is_array($cache) && isset($cache[$cacheKey])) return $cache[$cacheKey];

        $out = [];
        try {
            $hasCol = (bool)$pdo->query("SHOW COLUMNS FROM personnel LIKE 'criminal_record_issue_date'")->fetch();
            if (!$hasCol) return $cache[$cacheKey] = $out;

            $expiryExpr = "DATE_ADD(p.criminal_record_issue_date, INTERVAL $validityMonths MONTH)";
            $sql = "SELECT p.id, p.first_name, p.last_name, p.national_id, p.criminal_record_issue_date,
                           ct.category_key AS unit, ct.category_name AS unit_label,
                           $expiryExpr AS expiry_date,
                           DATEDIFF($expiryExpr, CURDATE()) AS days_left
                    FROM personnel p
                    JOIN category_types ct ON ct.id=p.category_type_id
                    WHERE p.personnel_status <> 'dismissed'
                      AND p.criminal_record_issue_date IS NOT NULL
                      AND $expiryExpr <= DATE_ADD(CURDATE(), INTERVAL $warningDays DAY)
                    ORDER BY days_left ASC, p.last_name, p.first_name";
            $st = $pdo->prepare($sql);
            $st->execute();
            foreach ($st->fetchAll() as $row) {
                $days = (int)$row['days_left'];
                $out[] = [
                    'personnel_id' => (int)$row['id'],
                    'full_name'    => trim($row['first_name'].' '.$row['last_name']),
                    'national_id'  => (string)$row['national_id'],
                    'unit'         => (string)$row['unit_label'],
                    'issue_date'   => (string)$row['criminal_record_issue_date'],
                    'expiry_date'  => (string)$row['expiry_date'],
                    'days_left'    => $days,
                    'status'       => $days < 0 ? 'expired' : 'warning',
                ];
            }
        } catch (Throwable $e) {
            error_log('[criminal_record_expiring] '.$e->getMessage());
        }
        return $cache[$cacheKey] = $out;
    }
}

$allowedUnits = allowed_units();
$counts = ['information'=>0,'operations'=>0];

/* ---- نوتیف: افرادی با گواهی سوء پیشینهٔ منقضی یا نزدیک به انقضا ---- */
$criminalAlerts = criminal_record_expiring_personnel(30, 6);
$criminalExpiredCount = 0; $criminalWarningCount = 0;
foreach ($criminalAlerts as $a) {
    if ($a['status'] === 'expired') $criminalExpiredCount++;
    else $criminalWarningCount++;
}
$st=$pdo->query("SELECT ct.category_key, COUNT(*) AS c FROM personnel p JOIN category_types ct ON ct.id=p.category_type_id WHERE ".active_personnel_sql('p')." GROUP BY ct.category_key");
foreach($st->fetchAll() as $row){ if(isset($counts[$row['category_key']])) $counts[$row['category_key']]=(int)$row['c']; }

/* ---- گزارش عملکرد: مستقیم از دیتابیس و با همان فیلترهای فهرست عناصر ---- */
$reportOpen = !empty($_GET['report']);
$filter=(string)($_GET['filter']??''); if(!in_array($filter,['active','information','operations','dismissed'],true)) $filter='';
$groupBy='team';
$provinceId=(int)($_GET['province_id']??0); $cityId=(int)($_GET['city_id']??0);
$categoryNumber=substr(preg_replace('/[^0-9]/','',fa_to_en_digits(trim((string)($_GET['category_number']??'')))),0,3);
$commanderNumber=substr(preg_replace('/[^0-9]/','',fa_to_en_digits(trim((string)($_GET['commander_number']??'')))),0,30);
$hasCommanderColumn=false;
try { $hasCommanderColumn=(bool)$pdo->query("SHOW COLUMNS FROM personnel LIKE 'commander_number'")->fetch(); } catch (Throwable $e) { $hasCommanderColumn=false; }
$q=fa_to_en_digits(trim((string)($_GET['q']??'')));
$q=preg_replace('/\s+/u',' ',strtr($q,["\u{00A0}"=>' ',"\u{200C}"=>' ',"\u{200D}"=>' ',"\u{064A}"=>'ی',"\u{0649}"=>'ی',"\u{0643}"=>'ک']));

$provinces=$pdo->query("SELECT id,province_name FROM provinces WHERE is_active=1 AND province_code <> 'UNKNOWN' ORDER BY province_name")->fetchAll();
$cities=[]; if($provinceId){$stLoc=$pdo->prepare("SELECT c.id,c.city_name FROM cities c JOIN provinces p ON p.id=c.province_id WHERE c.province_id=? AND p.province_code='TEH' AND c.is_active=1 ORDER BY c.city_name");$stLoc->execute([$provinceId]);$cities=$stLoc->fetchAll();}

$reportRows=[]; $reportTotal=0;
if($reportOpen){
    $ph=implode(',',array_fill(0,count($allowedUnits),'?'));
    $where=["ct.category_key IN ($ph)"]; $params=$allowedUnits;
    if($filter==='dismissed') $where[]="p.personnel_status='dismissed'"; else $where[]=active_personnel_sql('p');
    if($filter==='information'||$filter==='operations'){ $where[]='ct.category_key=?'; $params[]=$filter; }
    if($provinceId){$where[]='p.province_id=?';$params[]=$provinceId;}
    if($cityId){$where[]='p.city_id=?';$params[]=$cityId;}
    if($hasCommanderColumn && $commanderNumber!==''){$where[]='p.commander_number=?';$params[]=$commanderNumber;}
    if($categoryNumber!==''){$where[]='cn.unit_number=?';$params[]=$categoryNumber;}
    if($q!==''){$where[]='(p.full_name LIKE ? OR p.national_id LIKE ? OR p.mobile LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}
    $bothUnits = !in_array($filter,['information','operations'],true) && count($allowedUnits)>1;
    $labelSql = $bothUnits
        ? "CONCAT(IF(ct.category_key='information','اطلاعاتی','عملیاتی'),' · ',COALESCE(t.team_name,'بدون تیم'))"
        : "COALESCE(t.team_name,'بدون تیم')";
    $orderSql = 'MIN(ct.id), MIN(COALESCE(t.team_number,99))';
    $sql="SELECT $labelSql AS label, COUNT(*) AS cnt
          FROM personnel p
          JOIN category_types ct ON ct.id=p.category_type_id
          JOIN positions pos ON pos.id=p.position_id
          LEFT JOIN category_numbers cn ON cn.id=p.category_number_id
          LEFT JOIN personnel_groups g ON g.id=p.group_id
          LEFT JOIN teams t ON t.id=p.team_id
          WHERE ".implode(' AND ',$where)."
          GROUP BY label ORDER BY $orderSql";
    $st=$pdo->prepare($sql); $st->execute($params);
    foreach($st->fetchAll() as $row){
        $reportRows[]=['label'=>(string)$row['label'],'count'=>(int)$row['cnt']];
        $reportTotal+=(int)$row['cnt'];
    }
}
$filterLabels=['active'=>'عناصر فعال','information'=>'عناصر اطلاعاتی','operations'=>'عناصر عملیاتی','dismissed'=>'عناصر راکد'];

require __DIR__ . '/../app/partials/header.php';
?>
<section class="page-head dashboard-head">
  <div>
    <h1>داشبورد</h1>
  </div>
</section>

<div class="dashboard-stats">
  <a class="stat-card stat-card-accent" href="personnel.php?unit=information"><span>رسته اطلاعاتی</span><strong><?= $counts['information'] ?></strong><small>عنصر</small></a>
  <a class="stat-card" href="personnel.php?unit=operations"><span>رسته عملیاتی</span><strong><?= $counts['operations'] ?></strong><small>عنصر</small></a>
  <div class="stat-card"><span>سطح دسترسی</span><strong class="role-text"><?= e(match(user()['role']) {'super_admin'=>'مدیر کل','hr_manager'=>'منابع انسانی','info_commander'=>'فرمانده اطلاعاتی','ops_commander'=>'فرمانده عملیاتی'}) ?></strong></div>
</div>

<section class="panel report-panel<?= $reportOpen?' is-open':'' ?>">
  <div class="report-panel-head">
    <div>
      <h2>گزارش عملکرد عناصر</h2>
    </div>
    <?php if($reportOpen): ?>
      <a class="btn secondary report-toggle" href="dashboard.php">بستن گزارش عملکرد</a>
    <?php else: ?>
      <a class="btn primary report-toggle" href="dashboard.php?report=1">نمایش گزارش عملکرد</a>
    <?php endif; ?>
  </div>

  <?php if($reportOpen): ?>
  <div class="report-body" id="reportBody">
    <form class="filter-bar dashboard-filters" method="get" id="dashboardFilters" data-auto-filter>
      <input type="hidden" name="report" value="1">
      <div class="smart-filter" data-placeholder="استان">
        <button type="button" class="smart-filter-trigger" aria-haspopup="listbox" aria-expanded="false"><span class="smart-filter-value">استان</span><span class="smart-filter-arrow">⌄</span></button>
        <div class="smart-filter-menu"><input type="search" class="smart-filter-search" placeholder="جست‌وجوی استان" autocomplete="off"><div class="smart-filter-options" role="listbox"></div></div>
        <select name="province_id" class="smart-filter-native" aria-label="استان"><option value="" hidden <?= !$provinceId?'selected':'' ?>>استان</option><?php foreach($provinces as $p):?><option value="<?=$p['id']?>" <?= $provinceId===(int)$p['id']?'selected':'' ?>><?=e($p['province_name'])?></option><?php endforeach;?></select>
      </div>
      <div class="smart-filter" data-placeholder="شهرستان">
        <button type="button" class="smart-filter-trigger" aria-haspopup="listbox" aria-expanded="false" <?= !$provinceId?'disabled':'' ?>><span class="smart-filter-value">شهرستان</span><span class="smart-filter-arrow">⌄</span></button>
        <div class="smart-filter-menu"><input type="search" class="smart-filter-search" placeholder="جست‌وجوی شهرستان" autocomplete="off" <?= !$provinceId?'disabled':'' ?>><div class="smart-filter-options" role="listbox"></div></div>
        <select name="city_id" class="smart-filter-native" aria-label="شهرستان" <?= !$provinceId?'disabled':'' ?>><option value="" hidden <?= !$cityId?'selected':'' ?>>شهرستان</option><?php foreach($cities as $c):?><option value="<?=$c['id']?>" <?= $cityId===(int)$c['id']?'selected':'' ?>><?=e($c['city_name'])?></option><?php endforeach;?></select>
      </div>
      <div class="smart-filter" data-placeholder="وضعیت عناصر">
        <button type="button" class="smart-filter-trigger" aria-haspopup="listbox" aria-expanded="false"><span class="smart-filter-value">وضعیت عناصر</span><span class="smart-filter-arrow">⌄</span></button>
        <div class="smart-filter-menu"><div class="smart-filter-options" role="listbox"></div></div>
        <select name="filter" class="smart-filter-native" aria-label="وضعیت عناصر"><option value="" hidden <?= $filter===''?'selected':'' ?>>وضعیت عناصر</option><?php foreach($filterLabels as $k=>$v):?><option value="<?=e($k)?>" <?= $filter===$k?'selected':'' ?>><?=e($v)?></option><?php endforeach;?></select>
      </div>
      <?php if($hasCommanderColumn): ?><input type="text" name="commander_number" class="filter-control filter-number filter-commander-dash" inputmode="numeric" maxlength="30" autocomplete="off" placeholder="شماره قائد" value="<?= e($commanderNumber) ?>" aria-label="شماره قائد"><?php endif; ?>
      <input type="text" name="category_number" class="filter-control filter-number" inputmode="numeric" maxlength="3" autocomplete="off" placeholder="شماره دسته" value="<?= e($categoryNumber) ?>" aria-label="شماره دسته">
      <input type="search" name="q" class="filter-control filter-search" placeholder="جستجو نام، کد ملی یا موبایل" value="<?= e($_GET['q']??'') ?>" aria-label="جستجو">
      <button class="filter-btn" type="submit">جستجو</button>
    </form>

    <div class="report-toolbar">
      <div class="report-count">تعداد نمایش‌داده‌شده: <strong><?= fa_digits((string)$reportTotal) ?> عنصر</strong></div>
    </div>

    <div class="chart-card">
      <div class="chart-wrap"></div>
    </div>
  </div>
  <?php endif; ?>
</section>

<?php if (!empty($criminalAlerts)): ?>
<section class="panel criminal-alerts-panel" id="criminalAlertsPanel">
  <header class="criminal-alerts-head" id="criminalAlertsHead" role="button" tabindex="0" aria-expanded="false" aria-controls="criminalAlertsBody">
    <h2>
      <span class="criminal-alerts-icon">⚠</span>
      نوتیف تمدید گواهی سوء پیشینه
      <?php if ($criminalExpiredCount + $criminalWarningCount > 0): ?>
        <span class="criminal-alerts-total">(<?= fa_digits((string)($criminalExpiredCount + $criminalWarningCount)) ?>)</span>
      <?php endif; ?>
    </h2>
    <div class="criminal-alerts-counts">
      <?php if ($criminalExpiredCount > 0): ?>
        <span class="criminal-alert-badge criminal-alert-expired"><?= fa_digits((string)$criminalExpiredCount) ?> منقضی</span>
      <?php endif; ?>
      <?php if ($criminalWarningCount > 0): ?>
        <span class="criminal-alert-badge criminal-alert-warning"><?= fa_digits((string)$criminalWarningCount) ?> نزدیک به انقضا</span>
      <?php endif; ?>
      <span class="criminal-alerts-toggle" aria-hidden="true">▾</span>
    </div>
  </header>
  <div class="criminal-alerts-body" id="criminalAlertsBody" hidden>
    <ul class="criminal-alerts-list">
      <?php foreach ($criminalAlerts as $a): ?>
        <li class="criminal-alert-row criminal-alert-<?= e($a['status']) ?>">
          <a class="criminal-alert-card" href="personnel_form.php?id=<?= (int)$a['personnel_id'] ?>" target="_blank" rel="noopener">
            <span class="criminal-alert-name"><?= e($a['full_name']) ?></span>
            <span class="criminal-alert-meta"><?= e($a['unit']) ?> · کد ملی <?= e(fa_digits($a['national_id'])) ?></span>
            <span class="criminal-alert-date">صدور <?= e(jalali_display($a['issue_date'])) ?> · انقضا <?= e(jalali_display($a['expiry_date'])) ?></span>
            <span class="criminal-alert-days criminal-alert-days-<?= e($a['status']) ?>">
              <?php if ($a['status'] === 'expired'): ?>
                <?= fa_digits((string)(-1*$a['days_left'])) ?> روز گذشته
              <?php else: ?>
                <?= fa_digits((string)$a['days_left']) ?> روز مانده
              <?php endif; ?>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<script>
(function(){
  const head = document.getElementById('criminalAlertsHead');
  const body = document.getElementById('criminalAlertsBody');
  if (!head || !body) return;
  function toggle() {
    const open = body.hidden;
    body.hidden = !open;
    head.setAttribute('aria-expanded', open ? 'true' : 'false');
    head.classList.toggle('is-open', open);
  }
  head.addEventListener('click', toggle);
  head.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
  });
})();
</script>
<?php endif; ?>

<?php if($reportOpen): ?>
<script src="<?= e(asset_url('assets/filter-bar.js')) ?>" defer></script>
<script>
(function(){
const rows = <?= json_encode($reportRows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
const chartBox = document.querySelector('.chart-wrap');
const colors = ['#244b3b','#5c8b74','#94b8a6','#3f6d59','#7aa18d','#b9d0c4','#2f5e4a','#a8c5b6','#4f7d68'];
const fa = n => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
const esc = v => String(v).replace(/[<>&"]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));

function renderBar(){
  const max = Math.max(...rows.map(r=>r.count), 1);
  chartBox.innerHTML = `<div class="fake-bar-chart">${rows.map((r,i)=>`<div class="bar-item" style="--delay:${i*60}ms;--bar-color:${colors[i%colors.length]}">
      <div class="bar-label"><span>${esc(r.label)}</span><strong>${fa(r.count)}</strong></div>
      <div class="bar-track"><div class="bar-fill" style="--target-width:${(r.count/max)*100}%;"></div></div>
    </div>`).join('')}</div>`;
}
function draw(){
  if(!rows.length){ chartBox.innerHTML='<div class="chart-empty"><strong>داده‌ای برای نمایش وجود ندارد.</strong></div>'; return; }
  renderBar();
}
document.querySelectorAll('#dashboardFilters [name="category_number"],#dashboardFilters [name="commander_number"]').forEach(el=>el.addEventListener('input',function(){
  this.value=this.value.replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/\D/g,'').slice(0,this.name==='category_number'?3:30).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
}));
draw();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../app/partials/footer.php'; ?>
