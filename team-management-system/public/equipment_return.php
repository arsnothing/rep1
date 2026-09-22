<?php
require __DIR__.'/../app/bootstrap.php'; require_login();

$hasPersonStatusCol=false;
try { $hasPersonStatusCol=(bool)$pdo->query("SHOW COLUMNS FROM personnel_equipment LIKE 'status'")->fetch(); } catch (Throwable $e) { $hasPersonStatusCol=false; }

/* پاپ‌آپ پروفایل، فهرست زنده‌ی تجهیزات همان لحظه‌ی شخص را با fetch از همین نقطه می‌خواند. */
if(($_GET['action']??'')==='person_equipment'){
 header('Content-Type: application/json; charset=utf-8');
 $pid=(int)($_GET['id']??0);
 try{
  $st=$pdo->prepare('SELECT p.id,p.full_name,p.mobile,p.national_id,ct.category_key AS unit FROM personnel p JOIN category_types ct ON ct.id=p.category_type_id WHERE p.id=?');
  $st->execute([$pid]); $person=$st->fetch();
  if(!$person || !can_view_unit($person['unit'])){ http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }
  $items=[];
  if($hasPersonStatusCol){
   $st2=$pdo->prepare('SELECT id,equipment_type,serial_number,model,color,plate,status FROM personnel_equipment WHERE personnel_id=? ORDER BY created_at DESC');
   $st2->execute([$pid]); $items=$st2->fetchAll();
  }
  echo json_encode(['person'=>['id'=>(int)$person['id'],'name'=>$person['full_name'],'mobile'=>$person['mobile'],'national_id'=>$person['national_id']],'items'=>$items,'has_status'=>$hasPersonStatusCol],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 }catch(Throwable $e){ http_response_code(500); echo json_encode(['error'=>'failed']); }
 exit;
}

$statusOptions = equipment_status_options();
$hasStockTable=false;
try { $hasStockTable=(bool)$pdo->query("SHOW TABLES LIKE 'equipment_stock'")->fetch(); } catch (Throwable $e) { $hasStockTable=false; }
$personToggleOptions = ['healthy'=>$statusOptions['healthy'],'needs_repair'=>$statusOptions['needs_repair']];
$repairQueueOptions  = ['repaired'=>$statusOptions['repaired'],'in_repair'=>$statusOptions['in_repair'],'needs_repair'=>$statusOptions['needs_repair']];
$error=''; $success='';

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update_person_status'){
 verify_csrf();
 if(!can_manage_personnel()){http_response_code(403);exit('دسترسی غیرمجاز');}
 if(!$hasPersonStatusCol){ $error='ساختار دیتابیس نیاز به به‌روزرسانی دارد. فایل database/team_management.sql را اجرا کنید.'; }
 else{
  $pid=(int)($_POST['personnel_id']??0);
  $selected=$_POST['selected']??[]; $statuses=$_POST['status']??[];
  if(!$pid || !is_array($selected) || !$selected) $error='حداقل یک تجهیز را انتخاب کنید.';
  else{
   $st=$pdo->prepare('SELECT p.id,ct.category_key AS unit FROM personnel p JOIN category_types ct ON ct.id=p.category_type_id WHERE p.id=?'); $st->execute([$pid]); $person=$st->fetch();
   if(!$person || !can_view_unit($person['unit'])) $error='این عنصر خارج از محدوده دسترسی شماست.';
   else{
    $upd=$pdo->prepare('UPDATE personnel_equipment SET status=?,updated_by=? WHERE id=? AND personnel_id=?');
    $count=0;
    foreach($selected as $eid){
     $eid=(int)$eid; $status=(string)($statuses[$eid]??'');
     if(!$eid || !isset($personToggleOptions[$status])) continue;
     $upd->execute([$status,user()['id'],$eid,$pid]); $count++;
    }
    if(!$count) $error='وضعیت معتبری برای اقلام انتخاب‌شده ثبت نشد.';
    else $success=fa_digits((string)$count).' مورد با موفقیت به‌روزرسانی شد.';
   }
  }
 }
}

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update_repair_status'){
 verify_csrf();
 if(!can_manage_personnel()){http_response_code(403);exit('دسترسی غیرمجاز');}
 if(!$hasPersonStatusCol){ $error='ساختار دیتابیس نیاز به به‌روزرسانی دارد. فایل database/team_management.sql را اجرا کنید.'; }
 else{
  $id=(int)($_POST['id']??0); $status=(string)($_POST['status']??''); $source=(string)($_POST['source']??'assigned');
  if(!$id || !isset($repairQueueOptions[$status])) $error='وضعیت انتخاب‌شده معتبر نیست.';
  elseif($source==='stock' && $hasStockTable){ $pdo->prepare('UPDATE equipment_stock SET status=?,updated_by=? WHERE id=?')->execute([$status,user()['id'],$id]); $success='وضعیت تعمیر به‌روزرسانی شد.'; }
  else{ $pdo->prepare('UPDATE personnel_equipment SET status=?,updated_by=? WHERE id=?')->execute([$status,user()['id'],$id]); $success='وضعیت تعمیر به‌روزرسانی شد.'; }
 }
}

$people=$pdo->query("SELECT p.id,p.full_name,p.national_id,p.mobile,ct.category_key AS unit FROM personnel p JOIN category_types ct ON ct.id=p.category_type_id WHERE ".active_personnel_sql('p')." ORDER BY p.full_name")->fetchAll();

$repairQueue=[];
if($hasPersonStatusCol){
 $rq=$pdo->query("SELECT pe.*, p.full_name, ct.category_key AS unit FROM personnel_equipment pe JOIN personnel p ON p.id=pe.personnel_id JOIN category_types ct ON ct.id=p.category_type_id WHERE pe.status IN ('needs_repair','in_repair') ORDER BY pe.updated_at DESC")->fetchAll();
 foreach($rq as $r){ if(can_view_unit($r['unit'])){ $r['source']='assigned'; $repairQueue[]=$r; } }
}
/* آمادهای انبار هم اگر خراب باشند در همین صف می‌آیند (تنها جای تغییر وضعیت) */
if($hasStockTable){
 foreach($pdo->query("SELECT id,equipment_type,serial_number,plate,model,status,updated_at FROM equipment_stock WHERE status IN ('needs_repair','in_repair') ORDER BY updated_at DESC")->fetchAll() as $r){
  $r['source']='stock'; $r['full_name']='انبار آماد'; $repairQueue[]=$r;
 }
}

require __DIR__.'/../app/partials/header.php'; ?>
<section class="page-head equipment-head"><div><h1>آماد</h1></div></section>
<div class="training-tabs training-tabs-equal">
  <a class="training-tab" href="equipment.php">ثبت آماد</a>
  <a class="training-tab" href="equipment_status.php">گزارش وضعیت آمادی</a>
  <a class="training-tab active" href="equipment_return.php">ثبت بازتحویل</a>
</div>
<?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<?php if(!$hasPersonStatusCol): ?>
<div class="alert danger">ساختار دیتابیس نیاز به به‌روزرسانی دارد. فایل database/team_management.sql را روی پایگاه داده اجرا کنید تا ثبت بازتحویل فعال شود.</div>
<?php else: ?>
<div class="panel">
  <div class="section-title">ثبت بازتحویل</div>
  <div class="order-person-picker wide">
    <div class="order-person-search">
      <input type="search" id="returnSearch" class="filter-control filter-search" placeholder="جستجو با کد ملی یا نام و نام خانوادگی" autocomplete="off">
    </div>
    <div class="order-person-list" id="returnPersonList">
      <?php foreach($people as $p): if(can_view_unit($p['unit'])): ?>
      <button type="button" class="op-item" data-search="<?=e(mb_strtolower($p['full_name'].' '.$p['national_id'],'UTF-8'))?>" data-id="<?=(int)$p['id']?>">
        <span class="op-name"><?=e($p['full_name'])?></span>
        <span class="op-nid"><?=e(fa_digits((string)$p['national_id']))?></span>
      </button>
      <?php endif; endforeach; ?>
    </div>
    <div class="order-person-empty" id="returnPersonEmpty" hidden>موردی با این جستجو پیدا نشد.</div>
  </div>
</div>

<div class="panel">
  <div class="section-title">صف تعمیرات</div>
  <?php if(!$repairQueue): ?>
  <p class="empty">در حال حاضر آمادی در صف تعمیر نیست.</p>
  <?php else: ?>
  <div class="repair-queue wide">
    <?php foreach($repairQueue as $r): ?>
    <div class="repair-row">
      <div class="repair-info">
        <strong><?=e($r['full_name'])?></strong>
        <span><?=e($r['equipment_type'])?><?php if($r['serial_number']||$r['plate']):?> — <?=e($r['plate']?:$r['serial_number'])?><?php endif;?></span>
      </div>
      <form method="post" data-auto-filter class="repair-status-form">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="action" value="update_repair_status">
        <input type="hidden" name="id" value="<?=(int)$r['id']?>">
        <input type="hidden" name="source" value="<?=e($r['source'])?>">
        <select name="status">
          <?php foreach($repairQueueOptions as $sk=>$sv):?><option value="<?=e($sk)?>" <?=$r['status']===$sk?'selected':''?>><?=e($sv)?></option><?php endforeach;?>
        </select>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="pv-modal return-modal" id="returnModal" aria-hidden="true">
  <div class="pv-modal-backdrop" data-return-close></div>
  <section class="pv-modal-card pv-modal-card-wide" role="dialog" aria-modal="true" aria-labelledby="returnModalTitle">
    <header class="pv-modal-head return-modal-head">
      <div class="return-person">
        <div class="return-avatar">
          <img id="returnPersonPhoto" alt="" src="<?=e(asset_url('assets/default-avatar.svg'))?>" data-default="<?=e(asset_url('assets/default-avatar.svg'))?>">
        </div>
        <div class="return-person-text">
          <span class="pv-modal-eyebrow">ثبت بازتحویل</span>
          <h2 id="returnModalTitle">—</h2>
          <div class="return-person-meta" id="returnPersonMeta"></div>
        </div>
      </div>
      <button type="button" class="pv-modal-close" data-return-close aria-label="بستن">×</button>
    </header>
    <form method="post" id="returnForm" class="return-form">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="update_person_status">
      <input type="hidden" name="personnel_id" id="returnPersonId">
      <div class="return-items-head" id="returnItemsHead" hidden>
        <label class="return-select-all"><input type="checkbox" id="returnSelectAll"><span>انتخاب همه</span></label>
        <span class="return-items-count" id="returnItemsCount"></span>
      </div>
      <div class="return-items" id="returnItems"></div>
      <div class="pv-modal-actions equip-return-actions"><button class="btn primary" type="submit" id="returnSubmit" disabled>ثبت</button><button class="btn secondary" type="button" data-return-close>انصراف</button></div>
    </form>
  </section>
</div>
<script>
(function(){
 var STATUS_OPTIONS=<?=json_encode($personToggleOptions,JSON_UNESCAPED_UNICODE)?>;
 var search=document.getElementById('returnSearch');
 var list=document.getElementById('returnPersonList');
 var empty=document.getElementById('returnPersonEmpty');
 var items=list?[...list.querySelectorAll('.op-item')]:[];
 var modal=document.getElementById('returnModal');
 if(modal && modal.parentElement!==document.body) document.body.appendChild(modal);

 var faFold=function(v){return String(v==null?'':v).replace(/[۰-۹٠-٩]/g,function(d){var i='۰۱۲۳۴۵۶۷۸۹'.indexOf(d);return i>-1?String(i):String('٠١٢٣٤٥٦٧٨٩'.indexOf(d));}).toLocaleLowerCase('fa-IR');};

 function filterList(){
  var q=faFold((search.value||'').trim()); var shown=0;
  items.forEach(function(el){ var hit=!q||faFold(el.dataset.search||'').indexOf(q)!==-1; el.hidden=!hit; if(hit) shown++; });
  if(empty) empty.hidden = shown!==0;
 }
 search&&search.addEventListener('input',filterList);

 function unitLabel(it){ return it.plate?('پلاک: '+it.plate):(it.serial_number?('سریال: '+it.serial_number):'—'); }

 var fa='۰۱۲۳۴۵۶۷۸۹';
 function toFa(v){ return String(v==null?'':v).replace(/[0-9]/g,function(d){return fa[+d];}); }
 var box=document.getElementById('returnItems');
 var itemsHead=document.getElementById('returnItemsHead');
 var selectAll=document.getElementById('returnSelectAll');
 var countEl=document.getElementById('returnItemsCount');
 var submitBtn=document.getElementById('returnSubmit');

 function syncSelection(){
  var boxes=box.querySelectorAll('input[name="selected[]"]');
  var n=0; boxes.forEach(function(b){ if(b.checked) n++; b.closest('.equip-return-row').classList.toggle('is-selected',b.checked); });
  countEl.textContent = n ? toFa(n)+' از '+toFa(boxes.length)+' مورد انتخاب شده' : toFa(boxes.length)+' مورد';
  selectAll.checked = boxes.length>0 && n===boxes.length;
  selectAll.indeterminate = n>0 && n<boxes.length;
  submitBtn.disabled = n===0;
 }

 function renderItems(items){
  itemsHead.hidden = !items.length;
  if(!items.length){ box.innerHTML='<div class="return-empty">تجهیزی برای این عنصر ثبت نشده است.</div>'; syncSelection(); return; }
  var html='';
  items.forEach(function(it){
   var def=(it.status==='needs_repair'||it.status==='in_repair')?'needs_repair':'healthy';
   // فقط دو حالت دارد؛ به‌جای دراپ‌داون یک سوییچ دوحالته نمایش داده می‌شود.
   var opts='';
   Object.keys(STATUS_OPTIONS).forEach(function(k){ opts+='<label class="seg-opt seg-'+k+'"><input type="radio" name="status['+it.id+']" value="'+k+'"'+(k===def?' checked':'')+'><span>'+esc(STATUS_OPTIONS[k])+'</span></label>'; });
   var meta=[unitLabel(it)]; if(it.model) meta.push(it.model); if(it.color) meta.push(it.color);
   html+='<div class="equip-return-row">'+
     '<label class="equip-return-pick"><input type="checkbox" name="selected[]" value="'+it.id+'">'+
       '<span class="equip-return-info"><strong>'+esc(it.equipment_type)+'</strong><span>'+esc(toFa(meta.join(' · ')))+'</span></span>'+
     '</label>'+
     '<div class="equip-return-status seg-toggle" role="radiogroup" aria-label="وضعیت">'+opts+'</div>'+
   '</div>';
  });
  box.innerHTML=html;
  syncSelection();
 }
 box.addEventListener('change',function(e){ if(e.target.matches('input[name="selected[]"]')) syncSelection(); });
 selectAll.addEventListener('change',function(){ box.querySelectorAll('input[name="selected[]"]').forEach(function(b){ b.checked=selectAll.checked; }); syncSelection(); });

 function esc(v){ return String(v==null?'':v).replace(/[<>&"]/g,function(c){return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c];}); }

 function openModal(id){
  fetch('equipment_return.php?action=person_equipment&id='+encodeURIComponent(id),{headers:{'Accept':'application/json'}})
   .then(function(r){return r.json();})
   .then(function(data){
    if(data.error) return;
    var p=data.person;
    document.getElementById('returnModalTitle').textContent=p.name;
    var meta=[]; if(p.national_id) meta.push('<span>کد ملی '+esc(toFa(p.national_id))+'</span>'); if(p.mobile) meta.push('<span>'+esc(toFa(p.mobile))+'</span>');
    document.getElementById('returnPersonMeta').innerHTML=meta.join('');
    document.getElementById('returnPersonId').value=p.id;
    var img=document.getElementById('returnPersonPhoto');
    img.onerror=function(){ img.onerror=null; img.src=img.dataset.default; };
    img.src='personnel_file.php?type=photo&id='+encodeURIComponent(p.id)+'&v='+Date.now();
    renderItems(data.items||[]);
    modal.classList.add('open'); modal.setAttribute('aria-hidden','false'); document.body.classList.add('modal-open');
   });
 }
 function closeModal(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); document.body.classList.remove('modal-open'); }
 list&&list.addEventListener('click',function(e){ var btn=e.target.closest('.op-item'); if(!btn) return; openModal(btn.dataset.id); });
 modal&&modal.querySelectorAll('[data-return-close]').forEach(function(el){ el.addEventListener('click',closeModal); });
 document.addEventListener('keydown',function(e){ if(e.key==='Escape' && modal && modal.classList.contains('open')) closeModal(); });
})();
</script>
<?php endif; ?>
<script src="<?= e(asset_url('assets/filter-bar.js')) ?>" defer></script>
<?php require __DIR__.'/../app/partials/footer.php'; ?>
