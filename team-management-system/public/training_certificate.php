<?php
require __DIR__.'/../app/bootstrap.php'; require_login();
$courseOptions=training_course_options();
$hasApplicantsTable=ensure_training_applicants_table();
$canEdit=can_manage_personnel();

$courseKey=trim((string)($_GET['course_key']??''));
if($courseKey!=='' && !isset($courseOptions[$courseKey])) $courseKey='';

$hasCommanderColumn=false;
try { $hasCommanderColumn=(bool)$pdo->query("SHOW COLUMNS FROM personnel LIKE 'commander_number'")->fetch(); } catch (Throwable $e) { $hasCommanderColumn=false; }

/* ---- واجدین شرایط: متقاضیان همان دوره ---- */
$people=[];
if($courseKey!=='' && $hasApplicantsTable){
    $allowed=allowed_units();$ph=implode(',',array_fill(0,count($allowed),'?'));
    $sql="SELECT p.id,p.full_name,p.national_id,p.mobile,ct.category_key AS unit,cn.unit_number,
          pos.position_name AS position_label,g.group_number AS group_no,t.team_number AS team_no,t.team_name AS team_type"
        .($hasCommanderColumn?",p.commander_number":'')
        ." FROM personnel p
          JOIN training_applicants ta ON ta.personnel_id=p.id AND ta.course_key=?
          JOIN category_types ct ON ct.id=p.category_type_id
          LEFT JOIN category_numbers cn ON cn.id=p.category_number_id
          LEFT JOIN positions pos ON pos.id=p.position_id
          LEFT JOIN personnel_groups g ON g.id=p.group_id
          LEFT JOIN teams t ON t.id=p.team_id
          WHERE ct.category_key IN ($ph) AND ".active_personnel_sql('p')." ORDER BY p.full_name";
    $st=$pdo->prepare($sql);$st->execute(array_merge([$courseKey],$allowed));$people=$st->fetchAll();
}

$errorMessages=[
 'course'=>'دوره آموزشی را انتخاب کنید.',
 'date'=>'تاریخ صدور گواهی صحیح نیست.',
 'image'=>'قالب گواهی باید PDF (یا JPG/PNG/WEBP) و حداکثر ۸ مگابایت باشد.',
 'serial'=>'سریال گواهی را وارد کنید.',
 'people'=>'حداقل یک عنصر را به‌عنوان واجد شرایط انتخاب کنید.',
 'schema'=>'ساختار دیتابیس نیاز به به‌روزرسانی دارد. فایل database/team_management.sql را اجرا کنید.',
 'save'=>'صدور گواهی انجام نشد.',
];
require __DIR__.'/../app/partials/header.php'; ?>
<section class="page-head equipment-head"><div><h1>آموزش</h1></div></section>
<div class="training-tabs training-tabs-equal"><?= training_tabs('training_certificate.php') ?></div>
<?php if(isset($_GET['error'])):?><div class="alert danger"><?=e($errorMessages[$_GET['error']]??'صدور گواهی انجام نشد.')?></div><?php endif;?>
<?php if(!$hasApplicantsTable): ?><div class="alert danger">جدول متقاضیان روی پایگاه داده وجود ندارد. فایل database/team_management.sql را اجرا کنید.</div><?php endif; ?>

<form class="training-filters" method="get" id="certCourseForm" data-auto-filter>
  <div class="filter-bar training-course-row">
    <div class="smart-filter training-course-filter" data-placeholder="دوره آموزشی">
      <button type="button" class="smart-filter-trigger" aria-haspopup="listbox" aria-expanded="false"><span class="smart-filter-value">دوره آموزشی</span><span class="smart-filter-arrow">⌄</span></button>
      <div class="smart-filter-menu"><input type="search" class="smart-filter-search" placeholder="جست‌وجوی دوره" autocomplete="off"><div class="smart-filter-options" role="listbox"></div></div>
      <select name="course_key" class="smart-filter-native" aria-label="دوره آموزشی"><option value="" hidden <?= $courseKey===''?'selected':'' ?>>انتخاب دوره آموزشی</option><?php foreach($courseOptions as $k=>$v):?><option value="<?=e($k)?>" <?= $courseKey===$k?'selected':'' ?>><?=e($v)?></option><?php endforeach;?></select>
    </div>
  </div>
</form>

<form class="panel certificate-panel" method="post" action="training_certificate_store.php" enctype="multipart/form-data" id="certForm">
  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
  <input type="hidden" name="course_key" value="<?=e($courseKey)?>">

  <div class="training-list-head">
    <div><h2>صدور گواهی آموزش</h2><p><?= $courseKey!=='' ? 'دوره: '.e($courseOptions[$courseKey]).' · '.fa_digits((string)count($people)).' عنصر واجد شرایط' : '' ?></p></div>
  </div>

  <div class="cert-fields">
    <label class="cert-field"><span>سریال گواهی</span>
      <input type="text" name="certificate_serial" maxlength="30" autocomplete="off" placeholder="۱۰۰۱" required>
      <small class="cert-field-note">اگر عدد باشد، برای هر عنصر یکی‌یکی زیاد می‌شود.</small>
    </label>
    <label class="cert-field"><span>تاریخ صدور گواهی</span>
      <input type="text" name="issue_date_jalali" class="jalali" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="۱۴۰۷/۰۷/۰۷" required>
    </label>
    <div class="document-upload-card cert-upload">
      <div class="document-upload-icon">▣</div>
      <div><strong>فایل قالب گواهی (PDF)</strong><small>یک فایل، حداکثر ۸MB — خالی بماند از قالب پیش‌فرض سامانه استفاده می‌شود</small></div>
      <label class="file-picker"><span>انتخاب فایل</span><input type="file" name="certificate_image" accept="application/pdf,image/jpeg,image/png,image/webp"></label>
    </div>
  </div>

  <div class="cert-people-head">
    <h3>واجدین شرایط صدور گواهی</h3>
    <?php if($people): ?><label class="applicant-check cert-all" title="انتخاب همه"><input type="checkbox" id="certAll" aria-label="انتخاب همه"><span>انتخاب همه</span></label><?php endif; ?>
  </div>

  <?php if($courseKey===''): ?>
    <div class="personnel-empty"><strong>ابتدا دوره آموزشی را انتخاب کنید.</strong></div>
  <?php elseif(!$people): ?>
    <div class="personnel-empty"><strong>موردی ثبت نشده است</strong></div>
  <?php else: ?>
    <div class="cert-people">
    <?php foreach($people as $index=>$p): $pid=(int)$p['id'];
      $meta=[];
      if($hasCommanderColumn && trim((string)($p['commander_number']??''))!=='') $meta[]='شماره قائد '.fa_digits((string)$p['commander_number']);
      if(trim((string)($p['position_label']??''))!=='') $meta[]=$p['position_label'];
      $meta[]=unit_label($p['unit']);
      if(trim((string)($p['unit_number']??''))!=='') $meta[]='شماره دسته '.fa_digits((string)$p['unit_number']);
      if(($p['group_no']??null)!==null) $meta[]='گروه '.fa_digits((string)(int)$p['group_no']);
      if(($p['team_no']??null)!==null) $meta[]=($p['team_type'] ?: 'تیم '.fa_digits((string)(int)$p['team_no']));
      if(trim((string)($p['mobile']??''))!=='') $meta[]=fa_digits((string)$p['mobile']);
      $meta=array_values(array_filter(array_map('trim',$meta),static fn($v)=>$v!==''));
    ?>
      <label class="cert-person">
        <span class="applicant-check"><input type="checkbox" name="personnel_ids[]" value="<?=$pid?>" data-cert-item aria-label="انتخاب <?=e($p['full_name'])?>"></span>
        <span class="applicant-index"><?=fa_digits((string)($index+1))?></span>
        <span class="cert-person-main">
          <strong><?=e($p['full_name'])?></strong>
          <small><?=e(implode(' · ',$meta))?></small>
        </span>
      </label>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="cert-actions">
    <button type="submit" class="btn primary" id="certSubmit" <?= ($canEdit && $people)?'':'disabled' ?>>ثبت و دریافت PDF</button>
    <span class="cert-hint">پس از ثبت، فایل گواهی‌ها به‌صورت PDF قابل ذخیره است.</span>
  </div>
</form>

<script src="<?= e(asset_url('assets/filter-bar.js')) ?>" defer></script>
<script>
(function(){
 var all=document.getElementById('certAll');
 var items=[].slice.call(document.querySelectorAll('[data-cert-item]'));
 var submit=document.getElementById('certSubmit');
 function sync(){
  var n=items.filter(function(i){return i.checked;}).length;
  items.forEach(function(i){ i.closest('.cert-person').classList.toggle('is-selected',i.checked); });
  if(all){ all.checked=items.length>0 && n===items.length; all.indeterminate=n>0 && n<items.length; }
  if(submit) submit.disabled = n===0;
 }
 items.forEach(function(i){ i.addEventListener('change',sync); });
 if(all) all.addEventListener('change',function(){ items.forEach(function(i){ i.checked=all.checked; }); sync(); });
 sync();
 /* سریال گواهی: ارقام فارسی نمایش داده می‌شوند */
 var serial=document.querySelector('[name="certificate_serial"]');
 if(serial){serial.addEventListener('input',function(){this.value=this.value.replace(/[0-9]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[+d];});});}
 /* تاریخ شمسی با ارقام فارسی */
 var toEn=function(s){return (s||'').replace(/[۰-۹]/g,function(d){return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d));}).replace(/\D/g,'');};
 document.querySelectorAll('#certForm input.jalali').forEach(function(el){
  function mask(){var v=toEn(el.value).slice(0,8),out=v.slice(0,4);if(v.length>4)out+='/'+v.slice(4,6);if(v.length>6)out+='/'+v.slice(6,8);el.value=out.replace(/\d/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];});}
  el.addEventListener('input',mask); el.addEventListener('paste',function(){setTimeout(mask,0);}); mask();
 });
})();
</script>
<?php require __DIR__.'/../app/partials/footer.php'; ?>
