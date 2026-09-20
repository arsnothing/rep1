<?php
require __DIR__.'/../app/bootstrap.php'; require_login();
$equipmentTypes = equipment_type_options();
$vehicleTypes   = equipment_vehicle_types();
$error=''; $success='';

/** آیا جدول انبار آماد روی این دیتابیس ساخته شده؟ تا وقتی database/upgrade_v5.3.sql اجرا نشده، فرم غیرفعال می‌ماند. */
$hasStockTable=false;
try { $hasStockTable=(bool)$pdo->query("SHOW TABLES LIKE 'equipment_stock'")->fetch(); } catch (Throwable $e) { $hasStockTable=false; }


if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_stock'){
 verify_csrf();
 if(!can_manage_personnel()){http_response_code(403);exit('دسترسی غیرمجاز');}
 if(!$hasStockTable){ $error='ساختار دیتابیس نیاز به به‌روزرسانی دارد. فایل database/team_management.sql را اجرا کنید.'; }
 else{
  try{
   $type=(string)($_POST['equipment_type']??'');
   if(!isset($equipmentTypes[$type])) throw new RuntimeException('نوع آماد را انتخاب کنید.');
   $isVehicle=in_array($type,$vehicleTypes,true);
   $custom=trim((string)($_POST['custom']??''));
   if($type==='other' && $custom==='') throw new RuntimeException('عنوان آماد «سایر» را وارد کنید.');
   $label=$type==='other'?$custom:$equipmentTypes[$type];
   $model=trim((string)($_POST['model']??'')); $color=trim((string)($_POST['color']??'')); 
   $quantity=(int)fa_to_en_digits(trim((string)($_POST['quantity']??'0')));
   if($quantity<1 || $quantity>200) throw new RuntimeException('تعداد باید بین ۱ تا ۲۰۰ باشد.');

   $units=[];
   if($isVehicle){
    $plates=$_POST['plates']??[];
    if(!is_array($plates) || count($plates)!==$quantity) throw new RuntimeException('برای هر واحد باید شماره پلاک وارد شود.');
    foreach($plates as $p){
     if(trim((string)$p)==='') throw new RuntimeException('شماره پلاک همه واحدها را وارد کنید.');
     $raw=$p; $p=normalize_vehicle_plate((string)$raw,$type);
     if($p===null) throw new RuntimeException($type==='motorcycle'
        ? 'پلاک موتورسیکلت «'.$raw.'» معتبر نیست؛ قالب درست: ۳ رقم کد شهر و ۵ رقم شماره، مثل ۱۲۳-۴۵۶۷۸.'
        : 'پلاک خودرو «'.$raw.'» معتبر نیست؛ قالب درست: ۲ رقم، ۱ حرف، ۳ رقم و ۲ رقم کد ایران، مثل ۱۲ب۳۴۵-۶۷.');
     $units[]=['serial'=>null,'plate'=>$p];
    }
    if(count(array_unique(array_column($units,'plate')))!==count($units)) throw new RuntimeException('پلاک‌های واردشده باید یکتا باشند.');
   }else{
    $serials=$_POST['serials']??[];
    if(!is_array($serials) || count($serials)!==$quantity) throw new RuntimeException('برای هر واحد باید شماره سریال وارد شود.');
    foreach($serials as $s){
     $s=fa_to_en_digits(trim((string)$s));
     if($s==='') throw new RuntimeException('شماره سریال همه واحدها را وارد کنید.');
     $units[]=['serial'=>$s,'plate'=>null];
    }
    if(count(array_unique(array_column($units,'serial')))!==count($units)) throw new RuntimeException('شماره‌های سریال واردشده باید یکتا باشند.');
   }

   $pdo->beginTransaction();
   $ins=$pdo->prepare('INSERT INTO equipment_stock(equipment_type,serial_number,plate,model,color,status,notes,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?)');
   foreach($units as $u){
    $ins->execute([$label,$u['serial'],$u['plate'],$model!==''?$model:null,$color!==''?$color:null,'healthy',null,user()['id'],user()['id']]);
   }
   $pdo->commit();
   $success=$quantity>1 ? fa_digits((string)$quantity).' واحد «'.$label.'» با موفقیت به انبار آماد اضافه شد.' : '«'.$label.'» با موفقیت به انبار آماد اضافه شد.';
  }catch(RuntimeException $e){
   if($pdo->inTransaction()) $pdo->rollBack();
   $error=$e->getMessage();
  }catch(PDOException $e){
   if($pdo->inTransaction()) $pdo->rollBack();
   $code=(int)($e->errorInfo[1]??0); $msg=(string)($e->errorInfo[2]??'');
   if($code===1062 && str_contains($msg,'uq_equipment_stock_serial')) $error='یکی از شماره‌های سریال قبلاً در انبار آماد ثبت شده است.';
   elseif($code===1062 && str_contains($msg,'uq_equipment_stock_plate')) $error='یکی از شماره‌های پلاک قبلاً در انبار آماد ثبت شده است.';
   else $error='ذخیره اطلاعات انجام نشد.';
  }
 }
}
require __DIR__.'/../app/partials/header.php'; ?>
<section class="page-head equipment-head"><div><h1>آماد</h1></div></section>
<div class="training-tabs training-tabs-equal">
  <a class="training-tab active" href="equipment.php">ثبت آماد</a>
  <a class="training-tab" href="equipment_status.php">گزارش وضعیت آمادی</a>
  <a class="training-tab" href="equipment_return.php">ثبت بازتحویل</a>
</div>
<?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<?php if(can_manage_personnel()): ?>
<?php if(!$hasStockTable): ?>
<div class="alert danger">ساختار دیتابیس نیاز به به‌روزرسانی دارد. فایل database/team_management.sql را روی پایگاه داده اجرا کنید تا ثبت آماد فعال شود.</div>
<?php else: ?>
<div class="panel equipment-panel">
  <div class="section-title">ثبت آماد جدید</div>
  <div class="form-grid">
    <label class="wide equipment-type-field">نوع آماد
      <select id="stockTypeSelect" data-placeholder="عنوان">
        <option value="" disabled selected hidden>عنوان</option>
        <?php foreach($equipmentTypes as $k=>$v):?><option value="<?=e($k)?>"><?=e($v)?></option><?php endforeach;?>
      </select>
    </label>
  </div>
</div>
<div class="pv-modal stock-modal" id="stockModal" aria-hidden="true">
  <div class="pv-modal-backdrop" data-stock-close></div>
  <section class="pv-modal-card pv-modal-scroll" role="dialog" aria-modal="true" aria-labelledby="stockModalTitle">
    <header class="pv-modal-head">
      <div><span class="pv-modal-eyebrow">ثبت آماد جدید</span><h2 id="stockModalTitle">—</h2></div>
      <button type="button" class="pv-modal-close" data-stock-close aria-label="بستن">×</button>
    </header>
    <form method="post" class="pv-modal-form stock-form" id="stockForm">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="add_stock">
      <input type="hidden" name="equipment_type" id="stockTypeInput">
      <div class="pv-modal-body">
        <label id="stockCustomField" class="pv-field pv-field-wide" hidden><span>عنوان آماد</span><input name="custom" placeholder="عنوان آماد را وارد کنید"></label>
        <label class="pv-field"><span>مدل</span><input name="model" placeholder="اختیاری"></label>
        <label id="stockColorField" class="pv-field" hidden><span>رنگ</span><input name="color" placeholder="اختیاری"></label>
        <div class="pv-field stock-qty-field">
          <span>تعداد</span>
          <div class="qty-stepper">
            <button type="button" class="qty-btn" data-qty-step="1" aria-label="افزایش">+</button>
            <input name="quantity" id="stockQuantity" type="text" value="1" inputmode="numeric" maxlength="3" required aria-label="تعداد">
            <button type="button" class="qty-btn" data-qty-step="-1" aria-label="کاهش">−</button>
          </div>
        </div>
        <div class="pv-field pv-field-wide">
          <span id="stockUnitsLabel">شماره سریال</span>
          <div class="stock-units" id="stockUnits"></div>
        </div>
      </div>
      <div class="pv-modal-actions"><button class="btn pv-btn-success" type="submit">ثبت</button><button class="btn secondary" type="button" data-stock-close>انصراف</button></div>
    </form>
  </section>
</div>
<script>
(function(){
 var TYPES=<?=json_encode($equipmentTypes,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
 var VEHICLES=<?=json_encode($vehicleTypes,JSON_UNESCAPED_UNICODE)?>;
 var PLATE_HINTS={car:<?=json_encode(vehicle_plate_hint('car'),JSON_UNESCAPED_UNICODE)?>,motorcycle:<?=json_encode(vehicle_plate_hint('motorcycle'),JSON_UNESCAPED_UNICODE)?>};
 var select=document.getElementById('stockTypeSelect');
 var modal=document.getElementById('stockModal');
 if(!select||!modal) return;
 if(modal.parentElement!==document.body) document.body.appendChild(modal);
 var typeInput=document.getElementById('stockTypeInput');
 var titleEl=document.getElementById('stockModalTitle');
 var customField=document.getElementById('stockCustomField');
 var colorField=document.getElementById('stockColorField');
 var qty=document.getElementById('stockQuantity');
 var units=document.getElementById('stockUnits');
 var form=document.getElementById('stockForm');
 var currentType='';

 var fa='۰۱۲۳۴۵۶۷۸۹';
 function toFa(v){ return String(v).replace(/[0-9]/g,function(d){return fa[+d];}); }
 function toEn(v){ return String(v||'').replace(/[۰-۹]/g,function(d){return String(fa.indexOf(d));}).replace(/\D/g,''); }
 function qtyValue(){ return Math.max(1,Math.min(200,parseInt(toEn(qty.value),10)||1)); }
 function renderUnits(){
  var n=qtyValue();
  var isVehicle=VEHICLES.indexOf(currentType)!==-1;
  var name=isVehicle?'plates':'serials';
  var placeholder=isVehicle?PLATE_HINTS[currentType]:'شماره سریال';
  document.getElementById('stockUnitsLabel').textContent=currentType==='motorcycle'?'شماره پلاک موتورسیکلت (۳ رقم کد شهر، ۵ رقم شماره)':(currentType==='car'?'شماره پلاک خودرو (۲ رقم، حرف، ۳ رقم، ۲ رقم کد ایران)':'شماره سریال');
  // مقادیر واردشده با تغییر تعداد پاک نشوند.
  var prev=[].map.call(units.querySelectorAll('input'),function(i){return i.value;});
  var html='';
  for(var i=1;i<=n;i++){
   html+='<div class="stock-unit"><span class="stock-unit-no">'+toFa(i)+'</span><input name="'+name+'[]" required placeholder="'+placeholder+'" aria-label="واحد '+toFa(i)+'"'+(isVehicle?' data-plate="'+currentType+'" autocomplete="off" maxlength="12"':'')+'></div>';
  }
  units.innerHTML=html;
  units.querySelectorAll('input').forEach(function(inp,idx){ if(prev[idx]!==undefined) inp.value=prev[idx]; });
 }
 /* ماسک پلاک: خودرو ۱۲ب۳۴۵-۶۷ ، موتور ۱۲۳-۴۵۶۷۸ */
 function maskPlate(inp){
  var kind=inp.dataset.plate;
  var v=String(inp.value||'').replace(/[۰-۹]/g,function(d){return String(fa.indexOf(d));}).replace(/[\s\-_\/.\u200c]/g,'');
  var out;
  if(kind==='motorcycle'){
   v=v.replace(/\D/g,'').slice(0,8);
   out=v.length>3 ? v.slice(0,3)+'-'+v.slice(3) : v;
  }else{
   var a=v.replace(/[^\d]/g,'');
   var letter=(v.match(/[^\d]/)||[''])[0];
   var d1=a.slice(0,2), d2=a.slice(2,5), d3=a.slice(5,7);
   out=d1+(d1.length===2?letter:'')+(letter?d2:'')+(letter&&d2.length===3&&d3?'-'+d3:'');
   if(!letter) out=d1;
  }
  inp.value=toFa(out);
 }
 units.addEventListener('input',function(e){ if(e.target.dataset && e.target.dataset.plate) maskPlate(e.target); });

 modal.querySelectorAll('[data-qty-step]').forEach(function(b){
  b.addEventListener('click',function(){ qty.value=toFa(Math.max(1,Math.min(200,qtyValue()+parseInt(b.dataset.qtyStep,10)))); renderUnits(); });
 });
 qty.addEventListener('blur',function(){ qty.value=toFa(qtyValue()); renderUnits(); });

 function open(type){
  currentType=type;
  var isVehicle=VEHICLES.indexOf(type)!==-1;
  typeInput.value=type;
  titleEl.textContent=TYPES[type]||'—';
  customField.hidden=(type!=='other');
  customField.querySelector('input').required=(type==='other');
  colorField.hidden=!isVehicle;
  qty.value='۱';
  units.innerHTML='';
  renderUnits();
  modal.classList.add('open'); modal.setAttribute('aria-hidden','false'); document.body.classList.add('modal-open');
 }
 function close(){
  modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); document.body.classList.remove('modal-open');
  select.value=''; select.dispatchEvent(new Event('change',{bubbles:true}));
  form.reset();
 }
 select.addEventListener('change',function(){ if(select.value) open(select.value); });
 qty.addEventListener('input',renderUnits);
 modal.querySelectorAll('[data-stock-close]').forEach(function(el){ el.addEventListener('click',close); });
 document.addEventListener('keydown',function(e){ if(e.key==='Escape' && modal.classList.contains('open')) close(); });
})();
</script>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__.'/../app/partials/footer.php'; ?>
