<?php
require __DIR__.'/../app/bootstrap.php'; require_login();
$courseOptions=training_course_options();

$courseKey=trim((string)($_GET['course_key']??''));
if($courseKey!=='' && !isset($courseOptions[$courseKey])) $courseKey='';

/* ---- فقط دو کنترل: انتخاب دوره و جستجوی کد ملی ---- */
$q=substr(preg_replace('/[^0-9]/','',fa_to_en_digits(trim((string)($_GET['q']??'')))),0,10);

$hasApplicantsTable=ensure_training_applicants_table();

$allowed=allowed_units();$ph=implode(',',array_fill(0,count($allowed),'?'));
$where=["ct.category_key IN ($ph)", active_personnel_sql('p')]; $params=$allowed;
if($q!==''){$where[]='p.national_id LIKE ?';$params[]='%'.$q.'%';}

/* فقط عناصری که در تب «متقاضیان آموزش» برای همین دوره ثبت شده‌اند */
$people=[];
if($courseKey!=='' && $hasApplicantsTable){
$hasCommanderColumn=false;
try { $hasCommanderColumn=(bool)$pdo->query("SHOW COLUMNS FROM personnel LIKE 'commander_number'")->fetch(); } catch (Throwable $e) { $hasCommanderColumn=false; }
$sql="SELECT p.id,p.full_name,p.national_id,p.mobile,ct.category_key AS unit,cn.unit_number,g.group_number AS group_no,t.team_number AS team_no,t.team_name AS team_type,pos.position_name AS position_label"
      .($hasCommanderColumn?",p.commander_number":'')
      ." FROM personnel p
      JOIN training_applicants ta ON ta.personnel_id=p.id AND ta.course_key=?
      JOIN category_types ct ON ct.id=p.category_type_id
      LEFT JOIN category_numbers cn ON cn.id=p.category_number_id
      LEFT JOIN positions pos ON pos.id=p.position_id
      LEFT JOIN personnel_groups g ON g.id=p.group_id
      LEFT JOIN teams t ON t.id=p.team_id
      WHERE ".implode(' AND ',$where)." ORDER BY p.full_name";
$st=$pdo->prepare($sql);$st->execute(array_merge([$courseKey],$params));$people=$st->fetchAll();
}

/* دوره‌هایی که هر فرد قبلاً گذرانده، تا در لیست مشخص باشد */
$doneMap=[];
if($courseKey!==''){
    $done=$pdo->prepare('SELECT personnel_id FROM training_records WHERE course_key=?');
    $done->execute([$courseKey]);
    foreach($done->fetchAll() as $row) $doneMap[(int)$row['personnel_id']]=true;
}
require __DIR__.'/../app/partials/header.php'; ?>
<section class="page-head equipment-head"><div><h1>آموزش</h1></div></section>
<div class="training-tabs training-tabs-equal"><?= training_tabs('training.php') ?></div>
<?php if(isset($_GET['saved'])):?><div class="alert success">دوره آموزشی برای عنصر انتخاب‌شده ثبت شد.</div><?php endif;?>
<?php if(isset($_GET['error'])):?><div class="alert danger"><?=['date'=>'تاریخ واردشده صحیح نیست.','course'=>'دوره آموزشی را انتخاب کنید.','person'=>'عنصر انتخاب‌شده معتبر نیست.','dismissed'=>'این عنصر برکنار شده است؛ ثبت آموزش برای او امکان‌پذیر نیست.','not_applicant'=>'این عنصر نیازمند دوره انتخاب‌شده نیست. ابتدا در تب «متقاضیان آموزش» ثبتش کنید.','file'=>'اسناد باید PDF/JPG/PNG/WEBP و حداکثر ۸ مگابایت باشند.','schema'=>'ساختار دیتابیس نیاز به به‌روزرسانی دارد. فایل database/team_management.sql را اجرا کنید.'][$_GET['error']]??'ثبت انجام نشد.'?></div><?php endif;?>

<form class="training-filters" method="get" id="trainingFilters" data-auto-filter>
  <div class="filter-bar training-course-row">
    <div class="smart-filter training-course-filter" id="trainingCourseSmart" data-placeholder="دوره آموزشی">
      <button type="button" class="smart-filter-trigger" aria-haspopup="listbox" aria-expanded="false"><span class="smart-filter-value">دوره آموزشی</span><span class="smart-filter-arrow">⌄</span></button>
      <div class="smart-filter-menu"><input type="search" class="smart-filter-search" placeholder="جست‌وجوی دوره" autocomplete="off"><div class="smart-filter-options" role="listbox"></div></div>
      <select name="course_key" class="smart-filter-native" id="trainingCourseSelect" aria-label="دوره آموزشی"><option value="" hidden <?= $courseKey===''?'selected':'' ?>>انتخاب دوره آموزشی</option><?php foreach($courseOptions as $k=>$v):?><option value="<?=e($k)?>" <?= $courseKey===$k?'selected':'' ?>><?=e($v)?></option><?php endforeach;?></select>
    </div>
    <input type="search" name="q" class="filter-control filter-search" inputmode="numeric" maxlength="10" placeholder="جستجو با کد ملی" value="<?= e(fa_digits($q)) ?>" aria-label="جستجو با کد ملی">
    <button class="filter-btn" type="submit">جستجو</button>
  </div>
</form>

<div class="panel training-list-panel">
  <div class="training-list-head">
    <div><h2>عناصر نیازمند آموزش</h2><p><?= $courseKey!=='' ? fa_digits((string)count($people)).' عنصر · دوره: '.e($courseOptions[$courseKey]) : '' ?></p></div>
    <?php if($courseKey!==''): ?><a class="btn secondary training-list-link" href="training_applicants.php?course_key=<?=e(urlencode($courseKey))?>">مدیریت نیازمندان آموزش</a><?php endif; ?>
  </div>
  <?php if($courseKey===''): ?>
  <div class="personnel-empty"><strong>دوره آموزشی انتخاب نشده است.</strong></div>
  <?php elseif($people): ?>
  <div class="training-member-list">
    <?php foreach($people as $index=>$p): $done=isset($doneMap[(int)$p['id']]); ?>
    <div class="training-member-row">
      <span class="training-member-index"><?=fa_digits((string)($index+1))?></span>
      <div class="training-member-main">
        <a class="training-member-name" href="personnel_view.php?id=<?=(int)$p['id']?>&tab=training"><?=e($p['full_name'])?></a>
        <?php
          /* زیر نام: قائد · سمت · رسته · دسته · گروه · تیم · شماره تماس همراه */
          $meta=[];
          if(!empty($hasCommanderColumn) && trim((string)($p['commander_number']??''))!=='') $meta[]='شماره قائد '.fa_digits((string)$p['commander_number']);
          if(trim((string)($p['position_label']??''))!=='') $meta[]=$p['position_label'];
          $meta[]=unit_label($p['unit']);
          if(trim((string)($p['unit_number']??''))!=='') $meta[]='شماره دسته '.fa_digits((string)$p['unit_number']);
          if(($p['group_no']??null)!==null) $meta[]='گروه '.fa_digits((string)(int)$p['group_no']);
          if(($p['team_no']??null)!==null) $meta[]=($p['team_type'] ?: 'تیم '.fa_digits((string)(int)$p['team_no']));
          if(trim((string)($p['mobile']??''))!=='') $meta[]=fa_digits((string)$p['mobile']);
          $meta=array_values(array_filter(array_map('trim',$meta),static fn($v)=>$v!==''));
        ?>
        <small><?=e(implode(' · ',$meta))?></small>
      </div>
      <?php if($done): ?><span class="training-done-chip">بازآموزی</span><?php endif; ?>
      <?php if(can_manage_personnel()): ?>
      <button type="button" class="training-add-btn" data-pv-open="trainingModal" data-person-id="<?=(int)$p['id']?>" data-person-name="<?=e($p['full_name'])?>" aria-label="ثبت دوره برای <?=e($p['full_name'])?>">+</button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php elseif($q!==''): ?>
  <div class="personnel-empty"><strong>موردی پیدا نشد.</strong></div>
  <?php else: ?>
  <div class="personnel-empty"><strong>موردی ثبت نشده است</strong></div>
  <?php endif; ?>
</div>

<?php if(can_manage_personnel()): ?>
<div class="pv-modal" id="trainingModal" aria-hidden="true">
  <div class="pv-modal-backdrop" data-pv-close></div>
  <section class="pv-modal-card" role="dialog" aria-modal="true" aria-labelledby="trainingModalTitle">
    <header class="pv-modal-head">
      <div><span class="pv-modal-eyebrow">ثبت دوره آموزشی</span><h2 id="trainingModalTitle" data-person-name-target>ثبت دوره</h2></div>
      <button type="button" class="pv-modal-close" data-pv-close aria-label="بستن">×</button>
    </header>
    <form method="post" action="training_store.php" enctype="multipart/form-data" class="pv-modal-form">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <input type="hidden" name="personnel_id" value="" data-person-id-target>
      <input type="hidden" name="course_key" value="<?=e($courseKey)?>">
      <input type="hidden" name="return_query" value="<?=e(http_build_query(array_filter(['course_key'=>$courseKey,'q'=>$q],fn($v)=>$v!==''&&$v!==null)))?>">
      <label class="pv-field pv-field-wide"><span>دوره آموزشی</span><input type="text" value="<?= $courseKey!=='' ? e($courseOptions[$courseKey]) : '' ?>" placeholder="ابتدا از بالای صفحه دوره را انتخاب کنید" readonly></label>
      <label class="pv-field pv-field-wide"><span>تاریخ</span><input name="training_date_jalali" class="jalali" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="۱۴۰۷/۰۷/۰۷"></label>
      <div class="document-upload-card pv-upload-card pv-field-wide">
        <div class="document-upload-icon">▤</div>
        <div><strong>اسناد دوره</strong><small>حداکثر ۵ فایل، هرکدام تا ۸MB</small></div>
        <label class="file-picker"><span data-file-label="انتخاب فایل">انتخاب فایل</span><input type="file" name="training_documents[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp"></label>
      </div>
      <div class="pv-modal-actions">
        <button type="submit" class="btn pv-btn-success"<?= $courseKey===''?' disabled':'' ?>>ثبت</button>
        <button type="button" class="btn secondary" data-pv-close>انصراف</button>
      </div>
    </form>
  </section>
</div>
<?php endif; ?>

<script src="<?= e(asset_url('assets/filter-bar.js')) ?>" defer></script>
<script src="<?= e(asset_url('assets/pv-modal.js')) ?>" defer></script>
<script>
(function(){
 /* کادر جستجو فقط کد ملی می‌پذیرد (حداکثر ۱۰ رقم، نمایش فارسی) */
 var nid=document.querySelector('#trainingFilters [name="q"]');
 if(nid){nid.addEventListener('input',function(){this.value=this.value.replace(/[۰-۹]/g,function(d){return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d));}).replace(/[^0-9]/g,'').slice(0,10).replace(/[0-9]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[+d];});});}
})();
</script>
<?php require __DIR__.'/../app/partials/footer.php'; ?>
