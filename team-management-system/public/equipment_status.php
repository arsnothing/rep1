<?php
require __DIR__.'/../app/bootstrap.php'; require_login();
$equipmentTypes = equipment_type_options();
$statusOptions  = equipment_status_options();

$hasStockTable=false; $hasPersonStatusCol=false;
try { $hasStockTable=(bool)$pdo->query("SHOW TABLES LIKE 'equipment_stock'")->fetch(); } catch (Throwable $e) { $hasStockTable=false; }
try { $hasPersonStatusCol=(bool)$pdo->query("SHOW COLUMNS FROM personnel_equipment LIKE 'status'")->fetch(); } catch (Throwable $e) { $hasPersonStatusCol=false; }

/* نوع آماد: چندانتخابی (type[]). وضعیت در این صفحه فقط نمایش داده می‌شود؛
   تغییر وضعیت فقط از «صف تعمیرات» در تب ثبت بازتحویل انجام می‌شود. */
$rawTypes = $_GET['type'] ?? [];
if (!is_array($rawTypes)) $rawTypes = ($rawTypes === '' ? [] : [$rawTypes]);
$types = [];
foreach ($rawTypes as $t) { $t=(string)$t; if (isset($equipmentTypes[$t]) && !in_array($t,$types,true)) $types[]=$t; }
$allSelected = count($types) === count($equipmentTypes);

$rows=[];
if($types){
 $standardLabels = array_values(array_diff_key($equipmentTypes, ['other'=>'']));
 $conds=[]; $paramsType=[];
 $labels=[]; foreach($types as $t) if($t!=='other') $labels[]=$equipmentTypes[$t];
 if($labels){ $conds[]='equipment_type IN ('.implode(',',array_fill(0,count($labels),'?')).')'; $paramsType=array_merge($paramsType,$labels); }
 if(in_array('other',$types,true)){ $conds[]='equipment_type NOT IN ('.implode(',',array_fill(0,count($standardLabels),'?')).')'; $paramsType=array_merge($paramsType,$standardLabels); }
 $whereType='('.implode(' OR ',$conds).')';

 if($hasStockTable){
  $st=$pdo->prepare("SELECT * FROM equipment_stock WHERE $whereType");
  $st->execute($paramsType);
  foreach($st->fetchAll() as $r){
   $rows[]=['label'=>$r['equipment_type'],'model'=>$r['model'],'unit_no'=>$r['plate']?:$r['serial_number'],'unit_kind'=>$r['plate']?'پلاک':'سریال',
            'status'=>$r['status']?:'healthy','assigned_to'=>null,'created_at'=>$r['created_at']];
  }
 }
 if($hasPersonStatusCol){
  $st=$pdo->prepare("SELECT pe.*, p.full_name FROM personnel_equipment pe JOIN personnel p ON p.id=pe.personnel_id WHERE ".str_replace('equipment_type','pe.equipment_type',$whereType));
  $st->execute($paramsType);
  foreach($st->fetchAll() as $r){
   $rows[]=['label'=>$r['equipment_type'],'model'=>$r['model'],'unit_no'=>$r['plate']?:$r['serial_number'],'unit_kind'=>$r['plate']?'پلاک':'سریال',
            'status'=>$r['status']?:'healthy','assigned_to'=>$r['full_name'],'created_at'=>$r['created_at']];
  }
 }
 usort($rows, fn($a,$b)=>[$a['label'],(string)$b['created_at']] <=> [$b['label'],(string)$a['created_at']]);
}

$typeLabel = !$types ? 'انتخاب نوع آماد'
  : ($allSelected ? 'همه انواع آماد' : (count($types)===1 ? $equipmentTypes[$types[0]] : fa_digits((string)count($types)).' نوع آماد'));

require __DIR__.'/../app/partials/header.php'; ?>
<section class="page-head equipment-head"><div><h1>آماد</h1></div></section>
<div class="training-tabs training-tabs-equal">
  <a class="training-tab" href="equipment.php">ثبت آماد</a>
  <a class="training-tab active" href="equipment_status.php">گزارش وضعیت آمادی</a>
  <a class="training-tab" href="equipment_return.php">ثبت بازتحویل</a>
</div>
<form method="get" id="equipStatusFilters" class="panel equip-status-filter">
  <div class="section-title">گزارش وضعیت آمادی</div>
  <div class="filter-bar">
    <button type="button" class="filter-modal-trigger equip-type-trigger<?= $types?' has-value':'' ?>" data-pv-open="equipTypeModal">
      <span class="filter-modal-value"><?= e($typeLabel) ?></span><span class="filter-modal-arrow">⌄</span>
    </button>
  </div>
</form>

<div class="pv-modal" id="equipTypeModal" aria-hidden="true">
  <div class="pv-modal-backdrop" data-pv-close></div>
  <section class="pv-modal-card" role="dialog" aria-modal="true" aria-labelledby="equipTypeModalTitle">
    <header class="pv-modal-head">
      <div><h2 id="equipTypeModalTitle">انتخاب نوع آماد</h2></div>
      <button type="button" class="pv-modal-close" data-pv-close data-filter-cancel aria-label="بستن">×</button>
    </header>
    <div class="pick-list" data-pick-group>
      <label class="pick-item pick-all"><input type="checkbox" data-pick-all><span>همه انواع آماد</span></label>
      <div class="pick-scroll">
        <?php foreach($equipmentTypes as $k=>$v): ?>
        <label class="pick-item"><input type="checkbox" name="type[]" value="<?=e($k)?>" form="equipStatusFilters" <?= in_array($k,$types,true)?'checked':'' ?>><span><?=e($v)?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="pv-modal-actions">
      <button type="button" class="btn pv-btn-success" data-filter-apply>تایید</button>
      <button type="button" class="btn secondary" data-pv-close data-filter-cancel>انصراف</button>
    </div>
  </section>
</div>

<div class="panel equip-status-panel">
<?php if(!$types): ?>
  <p class="empty">نوع آماد انتخاب نشده است.</p>
<?php elseif(!$rows): ?>
  <p class="empty">آمادی از نوع انتخاب‌شده ثبت نشده است.</p>
<?php else: ?>
  <div class="equip-line-head"><span>ردیف</span><span>نوع آماد</span><span>مدل</span><span>سریال / پلاک</span><span>محل</span><span>وضعیت</span></div>
  <div class="equip-line-list">
  <?php foreach($rows as $i=>$row): ?>
    <div class="equip-line">
      <span class="equip-line-no"><?=fa_digits((string)($i+1))?></span>
      <strong class="equip-line-type"><?=e($row['label'])?></strong>
      <span class="equip-line-model"><?=e($row['model']?:'—')?></span>
      <span class="equip-line-unit"><?php if($row['unit_no']):?><small><?=e($row['unit_kind'])?></small> <?=e(fa_digits((string)$row['unit_no']))?><?php else:?>—<?php endif;?></span>
      <span class="equip-line-place"><?php if($row['assigned_to']):?><span class="chip-assigned"><?=e($row['assigned_to'])?></span><?php else:?><span class="chip-stock">در انبار</span><?php endif;?></span>
      <span class="equip-line-status"><span class="status-chip <?=equipment_status_chip_class($row['status'])?>"><?=e($statusOptions[$row['status']]??$row['status'])?></span></span>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<script src="<?= e(asset_url('assets/pv-modal.js')) ?>" defer></script>
<script>
(function(){
 var form=document.getElementById('equipStatusFilters');
 var group=document.querySelector('#equipTypeModal [data-pick-group]');
 if(!form||!group) return;
 var all=group.querySelector('[data-pick-all]');
 var boxes=group.querySelectorAll('.pick-scroll input[type=checkbox]');
 var snapshot=new Map(); boxes.forEach(function(b){ snapshot.set(b,b.checked); });
 function sync(){ var n=0; boxes.forEach(function(b){ if(b.checked) n++; }); all.checked=n===boxes.length; all.indeterminate=n>0&&n<boxes.length; }
 all.addEventListener('change',function(){ boxes.forEach(function(b){ b.checked=all.checked; }); all.indeterminate=false; });
 boxes.forEach(function(b){ b.addEventListener('change',sync); });
 sync();
 document.querySelector('#equipTypeModal [data-filter-apply]').addEventListener('click',function(){ form.requestSubmit?form.requestSubmit():form.submit(); });
 document.querySelectorAll('#equipTypeModal [data-filter-cancel]').forEach(function(btn){
  btn.addEventListener('click',function(){ snapshot.forEach(function(v,b){ b.checked=v; }); sync(); });
 });
})();
</script>
<?php require __DIR__.'/../app/partials/footer.php'; ?>
