<?php
require __DIR__.'/../app/bootstrap.php'; require_login();
$courseOptions=training_course_options();
$error=''; $success='';

/* جدول متقاضیان (اگر نباشد خودکار ساخته می‌شود) و ستون اختیاری شماره قائد */
$hasApplicantsTable=ensure_training_applicants_table();
$hasCommanderColumn=false;
try { $hasCommanderColumn=(bool)$pdo->query("SHOW COLUMNS FROM personnel LIKE 'commander_number'")->fetch(); } catch (Throwable $e) { $hasCommanderColumn=false; }

/* ---- ثبت / حذف متقاضی (تکی یا گروهی) برای دوره انتخابی ---- */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['op'])){
 verify_csrf();
 if(!can_manage_personnel()){http_response_code(403);exit('دسترسی غیرمجاز');}
 $course=(string)($_POST['course_key']??'');
 $op=(string)$_POST['op'];
 // op: add:ID | remove:ID | add_selected | remove_selected
 $mode=''; $ids=[];
 if(preg_match('/^(add|remove):(\d+)$/',$op,$m)){ $mode=$m[1]; $ids=[(int)$m[2]]; }
 elseif($op==='add_selected' || $op==='remove_selected'){
  $mode=$op==='add_selected'?'add':'remove';
  $picked=$_POST[$mode==='add'?'add_ids':'remove_ids']??[];
  $ids=array_values(array_unique(array_filter(array_map('intval',is_array($picked)?$picked:[]))));
 }
 if(!$hasApplicantsTable) $error='جدول متقاضیان روی پایگاه داده ساخته نشد. فایل database/team_management.sql را اجرا کنید.';
 elseif(!isset($courseOptions[$course])) $error='ابتدا دوره آموزشی را انتخاب کنید.';
 elseif($mode==='' || !$ids) $error='حداقل یک عنصر را انتخاب کنید.';
 else{
  try{
   // فقط عناصر فعالِ داخل محدوده دسترسی کاربر
   $ph=implode(',',array_fill(0,count($ids),'?'));
   $st=$pdo->prepare("SELECT p.id,ct.category_key AS unit FROM personnel p JOIN category_types ct ON ct.id=p.category_type_id WHERE p.id IN ($ph) AND ".active_personnel_sql('p'));
   $st->execute($ids);
   $valid=[]; foreach($st->fetchAll() as $r){ if(can_view_unit($r['unit'])) $valid[]=(int)$r['id']; }
   if(!$valid) throw new RuntimeException('عنصر انتخاب‌شده معتبر نیست.');
   $count=0;
   if($mode==='add'){
    $done=$pdo->prepare('SELECT 1 FROM training_records WHERE personnel_id=? AND course_key=? LIMIT 1');
    $ins=$pdo->prepare('INSERT IGNORE INTO training_applicants(course_key,personnel_id,applicant_type,created_by) VALUES(?,?,?,?)');
    foreach($valid as $pid){
     $done->execute([$pid,$course]);
     $ins->execute([$course,$pid,$done->fetchColumn()?'retraining':'new',user()['id']??null]);
     $count+=$ins->rowCount();
    }
    $success=$count ? fa_digits((string)$count).' عنصر به متقاضیان دوره «'.$courseOptions[$course].'» اضافه شد.' : 'عناصر انتخاب‌شده از قبل متقاضی این دوره بودند.';
   }else{
    $del=$pdo->prepare('DELETE FROM training_applicants WHERE course_key=? AND personnel_id=?');
    foreach($valid as $pid){ $del->execute([$course,$pid]); $count+=$del->rowCount(); }
    $success=fa_digits((string)$count).' عنصر از متقاضیان دوره «'.$courseOptions[$course].'» حذف شد.';
   }
  }catch(RuntimeException $e){ $error=$e->getMessage(); }
  catch(PDOException $e){ error_log('[training_applicants] '.$e->getMessage()); $error='ذخیره اطلاعات انجام نشد.'; }
 }
}

/* ---- فیلترها ---- */
$courseKey=trim((string)($_GET['course_key']??''));
if($courseKey!=='' && !isset($courseOptions[$courseKey])) $courseKey='';

$q=fa_to_en_digits(trim((string)($_GET['q']??'')));
$q=strtr($q,["\u{00A0}"=>' ',"\u{200C}"=>' ',"\u{200D}"=>' ',"\u{064A}"=>'ی',"\u{0649}"=>'ی',"\u{0643}"=>'ک']);
$q=preg_replace('/\s+/u',' ',$q);

$provinceId=(int)($_GET['province_id']??0);
$categoryNumber=substr(preg_replace('/[^0-9]/','',fa_to_en_digits(trim((string)($_GET['category_number']??'')))),0,3);
$commanderNumber=substr(preg_replace('/[^0-9]/','',fa_to_en_digits(trim((string)($_GET['commander_number']??'')))),0,30);
$statusFilter=(string)($_GET['status']??'');
if(!in_array($statusFilter,['applicants','not_passed','retraining','completed','dismissed'],true)) $statusFilter='';
$unitFilter=(string)($_GET['unit']??'');
if(!in_array($unitFilter,['information','operations'],true) || !can_view_unit($unitFilter)) $unitFilter='';

$provinces=$pdo->query("SELECT id,province_name FROM provinces WHERE is_active=1 AND province_code <> 'UNKNOWN' ORDER BY province_name")->fetchAll();

$people=[];
/* سه وضعیت، هرکدام با رنگ خودش:
   نگذرانده (قرمز) · نیاز به آموزش دوباره (زرد) · گذرانده (سبز)
   «متقاضیان» فقط با کلیک روی دکمه‌اش نمایش داده می‌شود. */
$statusLabels=[
 'completed'  =>'عناصر آموزش دیده',
 'applicants' =>'عناصر نیازمند آموزش',
 'retraining' =>'عناصر نیازمند به بازآموزی',
 'not_passed' =>'عناصر آموزش ندیده',
 'dismissed'  =>'عناصر راکد',
];
/* ترتیب نمایش بخش‌ها دست‌نخورده می‌ماند: آموزش ندیده، نیازمند بازآموزی، آموزش دیده، سپس نیازمند آموزش و راکد. */
$sections=[
 'not_passed'=>['title'=>$statusLabels['not_passed'],'row'=>'is-notpassed'],
 'retraining'=>['title'=>$statusLabels['retraining'],'row'=>'is-retraining'],
 'completed'=>['title'=>$statusLabels['completed'],'row'=>'is-done'],
 'applicants'=>['title'=>$statusLabels['applicants'],'row'=>'is-applicants'],
 'dismissed'=>['title'=>$statusLabels['dismissed'],'row'=>'is-dismissed'],
];
$grouped=['not_passed'=>[],'retraining'=>[],'completed'=>[],'applicants'=>[],'dismissed'=>[]];
$doneMap=[]; $applicantMap=[];

if($courseKey!==''){
 $allowed=$unitFilter!=='' ? [$unitFilter] : allowed_units();
 $ph=implode(',',array_fill(0,count($allowed),'?'));
 // عناصر راکد فقط وقتی خوانده می‌شوند که همین وضعیت انتخاب شده باشد.
 $where=["ct.category_key IN ($ph)"]; $params=$allowed;
 $where[] = $statusFilter==='dismissed' ? "p.personnel_status='dismissed'" : active_personnel_sql('p');
 if($provinceId){$where[]='p.province_id=?';$params[]=$provinceId;}
 if($categoryNumber!==''){$where[]='cn.unit_number=?';$params[]=$categoryNumber;}
 if($hasCommanderColumn && $commanderNumber!==''){$where[]='p.commander_number LIKE ?';$params[]='%'.$commanderNumber.'%';}
 if($q!==''){$where[]='(p.full_name LIKE ? OR p.national_id LIKE ? OR p.mobile LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}

 $sql="SELECT p.id,p.full_name,p.mobile,p.personnel_status,ct.category_key AS unit,ct.category_name AS unit_label,cn.unit_number,
       pos.position_key AS position_type,pos.position_name AS position_label,g.group_number AS group_no,t.team_number AS team_no,t.team_name AS team_type"
     .($hasCommanderColumn?",p.commander_number":'')
     ." FROM personnel p
       JOIN category_types ct ON ct.id=p.category_type_id
       LEFT JOIN category_numbers cn ON cn.id=p.category_number_id
       LEFT JOIN positions pos ON pos.id=p.position_id
       LEFT JOIN personnel_groups g ON g.id=p.group_id
       LEFT JOIN teams t ON t.id=p.team_id
       WHERE ".implode(' AND ',$where)." ORDER BY p.full_name";
 $st=$pdo->prepare($sql);$st->execute($params);$people=$st->fetchAll();

 $done=$pdo->prepare('SELECT DISTINCT personnel_id FROM training_records WHERE course_key=?');
 $done->execute([$courseKey]);
 foreach($done->fetchAll() as $row) $doneMap[(int)$row['personnel_id']]=true;
 if($hasApplicantsTable){
  $ap=$pdo->prepare('SELECT personnel_id FROM training_applicants WHERE course_key=?');
  $ap->execute([$courseKey]);
  foreach($ap->fetchAll() as $row) $applicantMap[(int)$row['personnel_id']]=true;
 }
 foreach($people as $p){
  $pid=(int)$p['id'];
  $isDismissed = (($p['personnel_status']??'active')==='dismissed');
  $isApp=isset($applicantMap[$pid]); $isDone=isset($doneMap[$pid]);
  if($isDismissed){ $p['_status']='dismissed'; $p['_applicant']=false; $grouped['dismissed'][]=$p; continue; }
  $p['_status'] = !$isDone ? 'not_passed' : ($isApp ? 'retraining' : 'completed');
  $p['_applicant'] = $isApp;
  $grouped[$p['_status']][]=$p;
  if($isApp) $grouped['applicants'][]=$p;
 }
}

/* پارامترهای فعلی برای لینک دکمه‌های وضعیت و حفظ فیلترها بعد از ثبت */
function applicants_query(array $override=[]): string {
 $params=array_merge($_GET,$override);
 return http_build_query(array_filter($params,fn($v)=>$v!==''&&$v!==null));
}
$canEdit=can_manage_personnel() && $hasApplicantsTable;
require __DIR__.'/../app/partials/header.php'; ?>
<section class="page-head equipment-head"><div><h1>آموزش</h1></div></section>
<div class="training-tabs training-tabs-equal"><?= training_tabs('training_applicants.php') ?></div>
<?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<?php if(!$hasApplicantsTable): ?><div class="alert danger">جدول متقاضیان روی پایگاه داده وجود ندارد. فایل database/team_management.sql را روی پایگاه داده اجرا کنید.</div><?php endif; ?>

<form class="training-filters applicants-filter-panel" method="get" id="applicantsFilters" data-auto-filter>
  <div class="filter-bar training-course-row">
    <div class="smart-filter" data-placeholder="انتخاب دوره آموزشی">
      <button type="button" class="smart-filter-trigger" aria-haspopup="listbox" aria-expanded="false"><span class="smart-filter-value">انتخاب دوره آموزشی</span><span class="smart-filter-arrow">⌄</span></button>
      <div class="smart-filter-menu"><input type="search" class="smart-filter-search" placeholder="جست‌وجوی دوره" autocomplete="off"><div class="smart-filter-options" role="listbox"></div></div>
      <select name="course_key" class="smart-filter-native" aria-label="دوره آموزشی"><option value="" hidden <?= $courseKey===''?'selected':'' ?>>انتخاب دوره آموزشی</option><?php foreach($courseOptions as $k=>$v):?><option value="<?=e($k)?>" <?= $courseKey===$k?'selected':'' ?>><?=e($v)?></option><?php endforeach;?></select>
    </div>
  </div>
  <div class="filter-bar training-report-filters">
    <div class="smart-filter tf-province" data-placeholder="استان">
      <button type="button" class="smart-filter-trigger" aria-haspopup="listbox" aria-expanded="false"><span class="smart-filter-value">استان</span><span class="smart-filter-arrow">⌄</span></button>
      <div class="smart-filter-menu"><input type="search" class="smart-filter-search" placeholder="جست‌وجوی استان" autocomplete="off"><div class="smart-filter-options" role="listbox"></div></div>
      <select name="province_id" class="smart-filter-native" aria-label="استان"><option value="" hidden <?= !$provinceId?'selected':'' ?>>استان</option><?php foreach($provinces as $pr):?><option value="<?=$pr['id']?>" <?= $provinceId===(int)$pr['id']?'selected':'' ?>><?=e($pr['province_name'])?></option><?php endforeach;?></select>
    </div>
    <?php if($hasCommanderColumn): ?>
    <input type="text" name="commander_number" class="filter-control filter-number" inputmode="numeric" maxlength="30" autocomplete="off" placeholder="شماره قائد" value="<?= e($commanderNumber) ?>" aria-label="شماره قائد">
    <?php endif; ?>
    <input type="text" name="category_number" class="filter-control filter-number" inputmode="numeric" maxlength="3" autocomplete="off" placeholder="شماره دسته" value="<?= e($categoryNumber) ?>" aria-label="شماره دسته">
    <div class="smart-filter tf-unit" data-placeholder="انتخاب رسته">
      <button type="button" class="smart-filter-trigger" aria-haspopup="listbox" aria-expanded="false"><span class="smart-filter-value">انتخاب رسته</span><span class="smart-filter-arrow">⌄</span></button>
      <div class="smart-filter-menu"><div class="smart-filter-options" role="listbox"></div></div>
      <select name="unit" class="smart-filter-native" aria-label="انتخاب رسته"><option value="" hidden <?= $unitFilter===''?'selected':'' ?>>انتخاب رسته</option><option value="information" <?= $unitFilter==='information'?'selected':'' ?>>رسته اطلاعاتی</option><option value="operations" <?= $unitFilter==='operations'?'selected':'' ?>>رسته عملیاتی</option></select>
    </div>
    <div class="smart-filter tf-status" data-placeholder="وضعیت عناصر">
      <button type="button" class="smart-filter-trigger" aria-haspopup="listbox" aria-expanded="false"><span class="smart-filter-value">وضعیت عناصر</span><span class="smart-filter-arrow">⌄</span></button>
      <div class="smart-filter-menu"><div class="smart-filter-options" role="listbox"></div></div>
      <select name="status" class="smart-filter-native" aria-label="وضعیت عناصر"><option value="" hidden <?= $statusFilter===''?'selected':'' ?>>وضعیت عناصر</option><?php foreach($statusLabels as $k=>$v):?><option value="<?=e($k)?>" <?= $statusFilter===$k?'selected':'' ?>><?=e($v)?></option><?php endforeach;?></select>
    </div>
    <input type="search" name="q" class="filter-control filter-search" placeholder="جستجو نام، کد ملی یا موبایل" value="<?= e($_GET['q']??'') ?>" aria-label="جستجو">
    <button class="filter-btn" type="submit">جستجو</button>
  </div>
</form>

<?php $printTitle='فهرست متقاضیان آموزش'.($courseKey!==''?' — '.$courseOptions[$courseKey]:''); require __DIR__.'/../app/partials/print_frame.php'; ?>
<div class="panel applicants-panel print-list">
  <div class="training-list-head">
    <div><h2>متقاضیان آموزش</h2><p><?= $courseKey!=='' ? fa_digits((string)count($people)).' عنصر · دوره: '.e($courseOptions[$courseKey]) : '' ?></p></div>
    <?php if($courseKey!==''): ?>
    <div class="training-list-actions">
      <a class="status-toggle status-toggle-applicants<?= $statusFilter==='applicants'?' active':'' ?>" href="training_applicants.php?<?=e(applicants_query(['status'=>'applicants']))?>">متقاضیان<span class="toggle-count"><?=fa_digits((string)count($grouped['applicants']))?></span></a>
      <?php if($statusFilter!==''): ?><a class="status-toggle" href="training_applicants.php?<?=e(applicants_query(['status'=>'']))?>">بازگشت به همه</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if($courseKey===''): ?>
    <div class="personnel-empty"><strong>ابتدا دوره آموزشی را انتخاب کنید.</strong></div>
  <?php elseif(!$people): ?>
    <div class="personnel-empty"><strong>موردی پیدا نشد.</strong></div>
  <?php else: ?>
  <form method="post" action="training_applicants.php?<?=e(applicants_query())?>" id="applicantsForm">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="course_key" value="<?=e($courseKey)?>">
    <?php foreach($sections as $key=>$sec): ?>
      <?php
        // «نیازمند آموزش» و «راکد» فقط وقتی نشان داده می‌شوند که همان وضعیت انتخاب شده باشد.
        if(in_array($key,['applicants','dismissed'],true) ? $statusFilter!==$key : ($statusFilter!=='' && $statusFilter!==$key)) continue;
        $rows=$grouped[$key];
        $hasAdd=false; foreach($rows as $rr){ if(!$rr['_applicant']) $hasAdd=true; }
      ?>
      <section class="applicant-section <?= $sec['row'] ?>" data-bulk-section>
        <div class="applicant-section-head">
          <div class="applicant-section-title">
            <?php if($canEdit && $hasAdd): ?><label class="applicant-check" title="انتخاب همه"><input type="checkbox" data-bulk-all aria-label="انتخاب همه"></label><?php endif; ?>
            <div><h3><?= e($sec['title']) ?></h3></div>
          </div>
          <div class="applicant-section-tools">
            <?php if($key==='applicants'): ?>
            <button type="button" class="applicant-pdf-btn" onclick="window.print()"<?= $rows?'':' disabled' ?>>دریافت PDF</button>
            <?php endif; ?>
            <?php if($canEdit && $hasAdd): ?>
            <button type="submit" class="btn pv-btn-success applicant-bulk-btn" name="op" value="add_selected" data-bulk-btn="add" disabled><?= $key==='completed'?'ثبت انتخاب‌شده‌ها برای بازآموزی':'ثبت انتخاب‌شده‌ها به‌عنوان نیازمند آموزش' ?></button>
            <?php endif; ?>
            <span class="applicant-count"><?= fa_digits((string)count($rows)) ?> عنصر</span>
          </div>
        </div>
        <?php if($rows): ?>
          <?php foreach($rows as $index=>$p): $pid=(int)$p['id']; $st=$p['_status']; $isApp=$p['_applicant']; $rowCls=$sections[$st]['row']; ?>
          <div class="applicant-row <?= $rowCls ?>">
            <?php if($canEdit): ?><?php if($isApp): ?><span class="applicant-check" aria-hidden="true"></span><?php else: ?><label class="applicant-check"><input type="checkbox" name="add_ids[]" value="<?=$pid?>" data-bulk-item="add" aria-label="انتخاب <?=e($p['full_name'])?>"></label><?php endif; ?><?php endif; ?>
            <span class="applicant-index"><?= fa_digits((string)($index+1)) ?></span>
            <div class="applicant-main">
              <a class="applicant-name" href="personnel_view.php?id=<?=$pid?>&tab=training"><?=e($p['full_name'])?></a>
              <?php
                /* زیر نام: قائد · سمت · رسته · دسته · گروه · تیم · شماره تماس همراه */
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
              <small><?=e(implode(' · ',$meta))?></small>
            </div>
            <?php if($isApp && $st==='not_passed'): ?><span class="applicant-chip c-applicant">نیازمند آموزش</span><?php endif; ?>
            <?php if($st==='not_passed'): ?><span class="applicant-chip c-notpassed">آموزش ندیده</span>
            <?php elseif($st==='retraining'): ?><span class="applicant-chip c-retrain">نیازمند بازآموزی</span>
            <?php elseif($st==='dismissed'): ?><span class="applicant-chip c-dismissed">راکد</span>
            <?php else: ?><span class="applicant-chip c-done">آموزش دیده</span><?php endif; ?>
            <?php if($canEdit): ?>
              <?php if($isApp): ?>
              <button type="submit" class="btn secondary applicant-action-btn" name="op" value="remove:<?=$pid?>" data-single-btn>حذف از نیازمندان آموزش</button>
              <?php else: ?>
              <button type="submit" class="btn pv-btn-success applicant-action-btn" name="op" value="add:<?=$pid?>" data-single-btn><?= $st==='completed'?'ثبت بازآموزی':'ثبت نیازمند آموزش' ?></button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="applicant-row applicant-row-empty">موردی ثبت نشده است</div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </form>
  <?php endif; ?>
</div>

<script src="<?= e(asset_url('assets/filter-bar.js')) ?>" defer></script>
<script>
(function(){
 /* ارقام کادرهای شماره دسته و شماره قائد فارسی و فقط عددی بمانند. */
 document.querySelectorAll('[name="category_number"],[name="commander_number"]').forEach(function(el){
  el.addEventListener('input',function(){
   this.value=this.value.replace(/[۰-۹]/g,function(d){return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d));}).replace(/[^0-9]/g,'').replace(/[0-9]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[+d];});
  });
 });
 /* انتخاب گروهی: با انتخاب هر تیک، دکمه‌های تکی ردیف‌ها غیرفعال می‌شوند */
 var singles=document.querySelectorAll('[data-single-btn]');
 function syncAll(){
  var any=document.querySelectorAll('[data-bulk-item]:checked').length>0;
  singles.forEach(function(b){ b.disabled=any; b.title=any?'برای ثبت گروهی از دکمه بالای بخش استفاده کنید':''; });
 }
 document.querySelectorAll('[data-bulk-section]').forEach(function(sec){
  var all=sec.querySelector('[data-bulk-all]');
  var items=sec.querySelectorAll('[data-bulk-item]');
  var btns=sec.querySelectorAll('[data-bulk-btn]');
  function sync(){
   var n=0, kinds={add:0,remove:0};
   items.forEach(function(i){ if(i.checked){ n++; kinds[i.dataset.bulkItem]++; } i.closest('.applicant-row').classList.toggle('is-selected',i.checked); });
   btns.forEach(function(b){ b.disabled=!kinds[b.dataset.bulkBtn]; });
   if(all){ all.checked=items.length>0 && n===items.length; all.indeterminate=n>0 && n<items.length; }
   syncAll();
  }
  items.forEach(function(i){ i.addEventListener('change',sync); });
  if(all) all.addEventListener('change',function(){ items.forEach(function(i){ i.checked=all.checked; }); sync(); });
  sync();
 });
})();
</script>
<?php require __DIR__.'/../app/partials/footer.php'; ?>
