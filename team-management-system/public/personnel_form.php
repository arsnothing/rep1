<?php
require __DIR__.'/../app/bootstrap.php';
require_login();
if (!can_manage_personnel()) { http_response_code(403); exit('دسترسی غیرمجاز'); }

/* اگر به هر دلیل helper language_options در bootstrap نبود (مثلاً فایل قدیمی deploy شده)،
   اینجا به‌صورت محلی تعریف می‌شود تا فرم بدون خطا کار کند.
   توجه: فارسی ('fa') عمداً از این لیست حذف شده، چون زبان مادری سیستم است. */
if (!function_exists('language_options')) {
    function language_options(): array {
        return [
            // زبان‌های پرکاربرد و رایج جهان
            'en'=>'انگلیسی','fr'=>'فرانسه','de'=>'آلمانی','es'=>'اسپانیایی','it'=>'ایتالیایی',
            'pt'=>'پرتغالی','ru'=>'روسی','zh'=>'چینی (ماندارین)','ja'=>'ژاپنی','ko'=>'کره‌ای',
            'hi'=>'هندی','tr'=>'ترکی استانبولی',
            // زبان‌های خاورمیانه
            'ar'=>'عربی','ur'=>'اردو','ku'=>'کردی','ps'=>'پشتو',
            'he'=>'عبری','az'=>'ترکی آذربایجانی','tk'=>'ترکمنی',
        ];
    }
}

if (($_GET['action'] ?? '') === 'districts') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $st=$pdo->prepare('SELECT id,district_name FROM city_districts WHERE city_id=? AND is_active=1 ORDER BY district_number');
        $st->execute([(int)($_GET['city_id']??0)]);
        echo json_encode($st->fetchAll(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) { echo '[]'; }
    exit;
}

if (($_GET['action'] ?? '') === 'cities') {
    header('Content-Type: application/json; charset=utf-8');
    $provinceId=(int)($_GET['province_id']??0);
    $st=$pdo->prepare("SELECT c.id,c.city_name FROM cities c INNER JOIN provinces p ON p.id=c.province_id WHERE c.province_id=? AND p.province_code='TEH' AND c.is_active=1 ORDER BY c.city_name");
    $st->execute([$provinceId]);
    echo json_encode($st->fetchAll(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT); $edit=(bool)$id; $row=null;
if($edit){
    $sql=personnel_select_sql('p').' WHERE p.id=?';
    $st=$pdo->prepare($sql);$st->execute([$id]);$row=$st->fetch();
    if(!$row) exit('رکورد یافت نشد.');
    block_if_dismissed($row, 'ویرایش پرونده');
}

$categoryRows=category_rows();
$categoryLabels=[]; foreach($categoryRows as $cat){$categoryLabels[(string)$cat['category_key']]=(string)$cat['category_name'];}
$eduLabels=education_options(); $positionLabels=position_options();
$provinces=$pdo->query("SELECT id,province_name FROM provinces WHERE is_active=1 AND province_code <> 'UNKNOWN' ORDER BY province_name")->fetchAll();
$selectedProvince=(int)($row['province_id']??($_POST['province_id']??0));
$cities=[];
if($selectedProvince){
    $st=$pdo->prepare("SELECT c.id,c.city_name FROM cities c INNER JOIN provinces p ON p.id=c.province_id WHERE c.province_id=? AND p.province_code='TEH' AND c.is_active=1 ORDER BY c.city_name");
    $st->execute([$selectedProvince]);
    $cities=$st->fetchAll();
}

$values=$row ?: [
 'first_name'=>'','last_name'=>'','national_id'=>'','father_name'=>'','birth_date'=>'','marital_status'=>'','education_status'=>'','mobile'=>'','emergency_phone'=>'','residence_address'=>'','postal_code'=>'',
 'organizational_code'=>'','secondary_job'=>'','secondary_job_address'=>'','iban'=>'','profile_photo_path'=>'',
 'unit'=>'','unit_number'=>'1','group_no'=>'','team_no'=>'','position_type'=>'','personnel_status'=>'active','province_id'=>'','city_id'=>'','district_id'=>'','commander_number'=>'',
 'alias_name'=>'','religion'=>'','denomination'=>'','health_status'=>'','health_note'=>'','license_types'=>'','license_level'=>'','landline_phone'=>'','sport_skill'=>'',
 'referrer_first_name'=>'','referrer_last_name'=>'','referrer_national_id'=>'','referrer_mobile'=>'','languages'=>'',
 'criminal_record_issue_date'=>'',
];
$error='';
/** در صورت خطا: تراکنش برگردانده و فایل‌های منتقل‌شده پاک می‌شوند تا رکورد یا فایل نیمه‌کاره نماند. */
function personnel_form_rollback(PDO $pdo, array $movedFiles): void {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($movedFiles as $f) { if (is_file($f)) @unlink($f); }
}
function normalize_iban_local(?string $value): string { return strtoupper(preg_replace('/\s+/', '', trim((string)$value))); }
function valid_iban_local(?string $iban): bool { return (bool)preg_match('/^IR\d{24}$/', normalize_iban_local($iban)); }

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $values=array_merge($values,$_POST);
    // «وضعیت عنصر» از فرم حذف شده است: مقدار قبلی رکورد (یا active برای رکورد جدید) حفظ می‌شود.
    $values['personnel_status']=$row['personnel_status'] ?? 'active';
    $values['national_id']=digits_only($values['national_id']??'');
    foreach(['mobile','emergency_phone','organizational_code','unit_number','group_no','team_no','commander_number'] as $f) $values[$f]=digits_only($values[$f]??'');
    $values['iban_digits']=digits_only($_POST['iban_digits']??'');
    $values['iban']=$values['iban_digits']!=='' ? 'IR'.$values['iban_digits'] : normalize_iban_local($values['iban']??'');
    $values['province_id']=(int)($values['province_id']??0);
    $values['city_id']=(int)($values['city_id']??0);
    $values['district_id']=(int)($values['district_id']??0);
    $values['group_no']=($values['group_no']!=='')?$values['group_no']:'1';
    // ---- فیلدهای تکمیلی اطلاعات فردی و معرف ----
    $values['landline_phone']=digits_only($_POST['landline_phone']??'');
    $values['referrer_national_id']=digits_only($_POST['referrer_national_id']??'');
    $values['referrer_mobile']=digits_only($_POST['referrer_mobile']??'');
    $values['postal_code']=digits_only($_POST['postal_code']??'');
    $values['sport_skill']=trim((string)($_POST['sport_skill']??''));
    $healthStatus=(string)($_POST['health_status']??'');
    if(!isset(health_status_options()[$healthStatus])) $healthStatus='';
    $values['health_status']=$healthStatus;
    $values['health_note']=$healthStatus==='healthy' ? '' : trim((string)($_POST['health_note']??''));
    $licenseTypes=array_values(array_intersect((array)($_POST['license_types']??[]), array_keys(license_type_options())));
    $values['license_types']=implode(',', $licenseTypes);
    $licenseLevel=(string)($_POST['license_level']??'');
    if(!$licenseTypes || !isset(license_level_options()[$licenseLevel])) $licenseLevel=$licenseTypes?$licenseLevel:'';
    $values['license_level']=isset(license_level_options()[$licenseLevel])?$licenseLevel:'';
    $languages=array_values(array_intersect((array)($_POST['languages']??[]), array_keys(language_options())));
    $values['languages']=implode(',', $languages);

    $requiredFields=[
      'first_name'=>'نام','last_name'=>'نام خانوادگی','national_id'=>'کد ملی','father_name'=>'نام پدر',
      'alias_name'=>'شهرت','religion'=>'دین','denomination'=>'مذهب',
      'marital_status'=>'وضعیت تأهل','education_status'=>'تحصیلات','mobile'=>'شماره تماس همراه',
      'emergency_phone'=>'شماره تماس اضطراری','residence_address'=>'آدرس محل سکونت',
      'postal_code'=>'کد پستی محل سکونت',
      'referrer_first_name'=>'نام معرف','referrer_last_name'=>'نام خانوادگی معرف',
      'referrer_national_id'=>'کد ملی معرف','referrer_mobile'=>'شماره تماس همراه معرف',
      'secondary_job_address'=>'آدرس محل کار',
      'organizational_code'=>'کد سازمانی','commander_number'=>'شماره قائد',
      'position_type'=>'سمت','unit'=>'رسته','unit_number'=>'شماره دسته',
    ];
    foreach($requiredFields as $field=>$label){
      if(trim((string)($values[$field]??''))===''){ $error='«'.$label.'» را تکمیل کنید.'; break; }
    }
    if(!$error && trim((string)($_POST['birth_date_jalali']??''))==='') $error='«تاریخ تولد» را تکمیل کنید.';
    if(!$error && trim((string)($values['iban']??''))==='') $error='«شماره شبا» را تکمیل کنید.';
    if(!$error && !valid_name_text($values['first_name'])) $error='نام نباید شامل عدد باشد.';
    if(!$error && !valid_name_text($values['last_name'])) $error='نام خانوادگی نباید شامل عدد باشد.';
    if(!$error && trim((string)$values['father_name'])!=='' && !valid_name_text($values['father_name'])) $error='نام پدر نباید شامل عدد باشد.';
    if(!$error && !valid_digits($values['national_id'],10,10)) $error='کد ملی باید دقیقاً ۱۰ رقم باشد.';
    if(!$error && !valid_digits($values['mobile'],10,15)) $error='شماره اصلی فقط باید شامل اعداد باشد.';
    if(!$error && $values['emergency_phone']!=='' && !valid_digits($values['emergency_phone'],10,15)) $error='شماره تماس اضطراری نامعتبر است.';
    if(!$error && $values['landline_phone']!=='' && !valid_digits($values['landline_phone'],8,15)) $error='شماره تماس ثابت نامعتبر است.';
    if(!$error && !valid_digits($values['postal_code']??'',5,10)) $error='کد پستی باید ۵ تا ۱۰ رقم باشد.';
    if(!$error && trim((string)$values['alias_name'])!=='' && !valid_name_text($values['alias_name'])) $error='شهرت نباید شامل عدد باشد.';
    if(!$error && $values['referrer_national_id']!=='' && !valid_digits($values['referrer_national_id'],10,10)) $error='کد ملی معرف باید ۱۰ رقم باشد.';
    if(!$error && $values['referrer_mobile']!=='' && !valid_digits($values['referrer_mobile'],10,15)) $error='شماره تماس معرف نامعتبر است.';
    if(!$error && $values['health_status']==='') $error='«وضعیت سلامت» را انتخاب کنید.';
    if(!$error && in_array($values['health_status'],['mental','physical'],true) && $values['health_note']==='') $error='توضیح وضعیت سلامت را وارد کنید.';
    if(!$error && $values['license_types']!=='' && $values['license_level']==='') $error='سطح تسلط گواهینامه را انتخاب کنید.';
    if(!$error && $values['license_types']==='' && $values['license_level']!=='') $error='نوع گواهینامه را انتخاب کنید.';
    if(!$error && $values['organizational_code']!=='' && !valid_digits($values['organizational_code'],1,30)) $error='کد سازمانی نامعتبر است.';
    if(!$error && trim((string)$values['secondary_job'])!=='' && !valid_name_text($values['secondary_job'])) $error='عنوان شغل نباید شامل عدد باشد.';
    if(!$error && trim((string)($values['sport_skill']??''))!=='' && digits_only($values['sport_skill'])===$values['sport_skill'] && $values['sport_skill']!=='') $error='مهارت ورزشی نباید فقط شامل عدد باشد.';
    if(!$error && $values['iban']!=='' && !valid_iban_local($values['iban'])) $error='شماره شبا باید شامل IR و ۲۴ رقم باشد.';
    if(!$error && !array_key_exists((string)$values['unit'],$categoryLabels)) $error='رسته نامعتبر است.';
    if(!$error && !array_key_exists((string)$values['position_type'],$positionLabels)) $error='سمت نامعتبر است.';
    if(!$error && !array_key_exists((string)($values['personnel_status']??'active'),personnel_status_options())) $error='وضعیت عنصر نامعتبر است.';
    if(!$error && !valid_digits($values['unit_number'],1,null)) $error='شماره دسته را وارد کنید.';
    if(!$error && trim((string)($values['commander_number']??''))!=='' && !valid_digits($values['commander_number'],1,30)) $error='شماره قائد نامعتبر است.';
    if(!$error && (!valid_digits($values['group_no'],1,1) || !in_array((int)$values['group_no'],[1,2,3],true))) $error='گروه باید یکی از ۱ تا ۳ باشد.';

    $birthGregorian=parse_jalali_input($_POST['birth_date_jalali']??'');
    if(!$error && trim((string)($_POST['birth_date_jalali']??''))!=='' && $birthGregorian===null) $error='تاریخ تولد صحیح نیست.';

    // تاریخ صدور گواهی سوء پیشینه (اختیاری) - اگر پر شده باشد باید معتبر باشد.
    $criminalIssueGregorian = null;
    $criminalIssueJalaliIn = trim((string)($_POST['criminal_record_issue_date'] ?? ''));
    if ($criminalIssueJalaliIn !== '') {
        $criminalIssueGregorian = parse_jalali_input($criminalIssueJalaliIn);
        if ($criminalIssueGregorian === null) $error = 'تاریخ صدور گواهی سوء پیشینه صحیح نیست.';
    }
    $values['criminal_record_issue_date'] = $criminalIssueGregorian;

    $categoryTypeId=$category_number_id=$positionId=$groupId=$teamId=$cityProvinceId=null;
    if(!$error){
        $categoryTypeId=category_type_id((string)$values['unit']);
        $category_number_id=ensure_category_number_id((string)$values['unit'],(string)$values['unit_number']);
        $positionId=position_id((string)$values['position_type']);
        $groupId=group_id((int)$values['group_no']);
        // حوزه استحفاظی خودکار: اگر شهرستانی انتخاب نشده، شهرستان پیش‌فرض استان انتخاب می‌شود.
        if(!$values['city_id'] && $values['province_id']){
            $cq=$pdo->prepare("SELECT id FROM cities WHERE province_id=? AND is_active=1 ORDER BY (city_name='شمیرانات') DESC, city_name LIMIT 1");
            $cq->execute([(int)$values['province_id']]);
            $values['city_id']=(int)($cq->fetchColumn() ?: 0);
        }
        $cityProvinceId=null;
        $st=$pdo->prepare('SELECT province_id FROM cities WHERE id=? AND is_active=1 LIMIT 1'); $st->execute([$values['city_id']]); $cityProvinceId=$st->fetchColumn();
        if(!$categoryTypeId||!$category_number_id||!$positionId) $error='اطلاعات سازمانی انتخاب‌شده معتبر نیست.';
        elseif(!$values['province_id']||!$values['city_id']||$cityProvinceId===false||((int)$cityProvinceId!==(int)$values['province_id'])) $error='استان و شهرستان معتبر را انتخاب کنید.';
        if(!$error && (int)$cityProvinceId!==(int)$values['province_id']) $error='شهرستان انتخاب‌شده متعلق به استان انتخابی نیست.';

        if(!$error && $values['district_id']){
            try { $dchk=$pdo->prepare('SELECT city_id FROM city_districts WHERE id=? LIMIT 1'); $dchk->execute([$values['district_id']]);
                  if((int)$dchk->fetchColumn() !== (int)$values['city_id']) $error='منطقه انتخاب‌شده متعلق به شهرستان انتخابی نیست.'; }
            catch (Throwable $e) { $values['district_id']=0; }
        }

        if(!$error && !in_array((string)$values['position_type'],['unit_commander'],true) && trim((string)($_POST['group_no']??''))==='') $error='«گروه» را انتخاب کنید.';
        if(!$error && !in_array((string)$values['position_type'],['unit_commander','group_commander'],true) && trim((string)($values['team_no']??''))==='') $error='«تیم» را انتخاب کنید.';
        if(!$error && in_array((string)$values['position_type'],['unit_commander','group_commander'],true)) $teamId=null; else $teamId=team_id((string)$values['unit'],(int)$values['team_no']);
        if(!$error && $values['position_type']==='unit_commander'){ $groupId=null; $teamId=null; }
        elseif(!$error && $values['position_type']==='group_commander'){ $teamId=null; }
        elseif(!$error && !$teamId) $error='تیم انتخاب‌شده با نوع رسته سازگار نیست.';
    }

    $hasDistrictColumn=false;
    if(!$error){ try { $hasDistrictColumn=(bool)$pdo->query("SHOW COLUMNS FROM personnel LIKE 'district_id'")->fetch(); } catch (Throwable $e) { $hasDistrictColumn=false; } }
    $hasCommanderColumn=false;
    if(!$error){ try { $hasCommanderColumn=(bool)$pdo->query("SHOW COLUMNS FROM personnel LIKE 'commander_number'")->fetch(); } catch (Throwable $e) { $hasCommanderColumn=false; } }
    $extraCols = personnel_extra_columns();
    $extraValues = [];
    foreach ($extraCols as $col) {
        $v = trim((string)($values[$col] ?? ''));
        $extraValues[] = $v === '' ? null : $v;
    }
    if(!$error){
        $movedFiles=[];
        try{
            
            $pdo->beginTransaction();
            if($edit){
                $extraSet = $extraCols ? ',' . implode(',', array_map(fn($c)=>"$c=?", $extraCols)) : '';
                $sql='UPDATE personnel SET first_name=?,last_name=?,source_full_name=?,national_id=?,position_id=?,category_type_id=?,category_number_id=?,province_id=?,city_id=?'.($hasDistrictColumn?',district_id=?':'').($hasCommanderColumn?',commander_number=?':'').',group_id=?,team_id=?,birth_date=?,father_name=?,marital_status=?,education_status=?,residence_address=?,mobile=?,emergency_phone=?,organizational_code=?,secondary_job=?,secondary_job_address=?,iban=?,personnel_status=?'.$extraSet.',updated_by=? WHERE id=?';
                $st=$pdo->prepare($sql);
                $st->execute([trim($values['first_name']),trim($values['last_name']),trim($values['first_name'].' '.$values['last_name']),$values['national_id'],$positionId,$categoryTypeId,$category_number_id,$values['province_id'],$values['city_id'],...($hasDistrictColumn?[$values['district_id']?:null]:[]),...($hasCommanderColumn?[trim((string)($values['commander_number']??''))!==''?(string)$values['commander_number']:null]:[]),$groupId,$teamId,$birthGregorian,trim($values['father_name']??''),$values['marital_status']!==''?$values['marital_status']:null,$values['education_status']!==''?$values['education_status']:null,trim($values['residence_address']??''),$values['mobile'],trim($values['emergency_phone']??''),$values['organizational_code'],trim($values['secondary_job']??''),trim($values['secondary_job_address']??''),$values['iban']!==''?$values['iban']:null,$values['personnel_status'],...$extraValues,user()['id'],$id]);
                $personId=$id;
            }else{
                $extraInsert = $extraCols ? ',' . implode(',', $extraCols) : '';
                $extraMarks = str_repeat(',?', count($extraCols));
                $sql='INSERT INTO personnel(first_name,last_name,source_full_name,national_id,position_id,category_type_id,category_number_id,province_id,city_id'.($hasDistrictColumn?',district_id':'').($hasCommanderColumn?',commander_number':'').',group_id,team_id,birth_date,father_name,marital_status,education_status,residence_address,mobile,emergency_phone,organizational_code,secondary_job,secondary_job_address,iban,personnel_status'.$extraInsert.',created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?'.($hasDistrictColumn?',?':'').($hasCommanderColumn?',?':'').',?,?,?,?,?,?,?,?,?,?,?,?,?,?'.$extraMarks.',?,?)';
                $st=$pdo->prepare($sql);
                $st->execute([trim($values['first_name']),trim($values['last_name']),trim($values['first_name'].' '.$values['last_name']),$values['national_id'],$positionId,$categoryTypeId,$category_number_id,$values['province_id'],$values['city_id'],...($hasDistrictColumn?[$values['district_id']?:null]:[]),...($hasCommanderColumn?[trim((string)($values['commander_number']??''))!==''?(string)$values['commander_number']:null]:[]),$groupId,$teamId,$birthGregorian,trim($values['father_name']??''),$values['marital_status']!==''?$values['marital_status']:null,$values['education_status']!==''?$values['education_status']:null,trim($values['residence_address']??''),$values['mobile'],trim($values['emergency_phone']??''),$values['organizational_code'],trim($values['secondary_job']??''),trim($values['secondary_job_address']??''),$values['iban']!==''?$values['iban']:null,$values['personnel_status'],...$extraValues,user()['id'],user()['id']]);
                $personId=(int)$pdo->lastInsertId();
            }
            if(!empty($_FILES['profile_photo']['name'])){
                $file=$_FILES['profile_photo']; if($file['error']!==UPLOAD_ERR_OK||$file['size']>3*1024*1024) throw new RuntimeException('عکس پرونده باید حداکثر ۳ مگابایت باشد.');
                $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($allowed[$mime]))throw new RuntimeException('فرمت عکس پرونده مجاز نیست.');
                $stored='profile_'.$personId.'_'.bin2hex(random_bytes(8)).'.'.$allowed[$mime];
                $photoPath=storage_path('profile_photos').$stored;
                if(!move_uploaded_file($file['tmp_name'],$photoPath)) throw new RuntimeException('ذخیره عکس پرونده انجام نشد.');
                $movedFiles[]=$photoPath;
                $pdo->prepare('UPDATE personnel SET profile_photo_path=? WHERE id=?')->execute([$stored,$personId]);
            }
            /* ---- ثبت دوره‌های گذرانده ارسال‌شده از فرم (پس از اینکه personnel_id مشخص شد) ---- */
            $courseKeyCounter = 0;
            // دوره‌ها از طریق courses_json به فرم ارسال می‌شوند (آرایه‌ای از {name, date, description})
            $submittedCourses = [];
            if (!empty($_POST['courses_json'])) {
                $decoded = json_decode((string)$_POST['courses_json'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $row) {
                        if (!is_array($row)) continue;
                        $submittedCourses[] = [
                            'name' => (string)($row['name'] ?? ''),
                            'date' => (string)($row['date'] ?? ''),
                            'description' => (string)($row['description'] ?? ''),
                        ];
                    }
                }
            }
            if ($submittedCourses) {
                $hasDateCol = false;
                try { $hasDateCol = (bool)$pdo->query("SHOW COLUMNS FROM training_records LIKE 'training_date'")->fetch(); } catch (Throwable $e) { $hasDateCol = false; }
                $hasDescCol = false;
                try { $hasDescCol = (bool)$pdo->query("SHOW COLUMNS FROM training_records LIKE 'description'")->fetch(); } catch (Throwable $e) { $hasDescCol = false; }

                foreach ($submittedCourses as $idx => $course) {
                    if (!is_array($course)) continue;
                    $name = trim((string)($course['name'] ?? ''));
                    if ($name === '') continue; // عنوان دوره اجباری است
                    $dateJalali = trim((string)($course['date'] ?? ''));
                    $trainingDate = null;
                    if ($dateJalali !== '') {
                        $trainingDate = parse_jalali_input($dateJalali);
                        // تاریخ نامعتبر = نادیده گرفتن دوره (نه شکست کل فرم)
                    }
                    $description = trim((string)($course['description'] ?? ''));
                    $courseKeyCounter++;
                    $courseKey = 'custom_'.bin2hex(random_bytes(6));

                    if ($hasDateCol && $hasDescCol) {
                        $insCourse = $pdo->prepare('INSERT INTO training_records(personnel_id, course_key, course_name, status, training_date, description, created_by, updated_by) VALUES(?,?,?,?,?,?,?,?)');
                        $insCourse->execute([$personId, $courseKey, $name, 'completed', $trainingDate, $description !== '' ? $description : null, user()['id'], user()['id']]);
                    } elseif ($hasDateCol) {
                        $insCourse = $pdo->prepare('INSERT INTO training_records(personnel_id, course_key, course_name, status, training_date, created_by, updated_by) VALUES(?,?,?,?,?,?,?)');
                        $insCourse->execute([$personId, $courseKey, $name, 'completed', $trainingDate, user()['id'], user()['id']]);
                    } else {
                        $insCourse = $pdo->prepare('INSERT INTO training_records(personnel_id, course_key, course_name, status, created_by, updated_by) VALUES(?,?,?,?,?,?)');
                        $insCourse->execute([$personId, $courseKey, $name, 'completed', user()['id'], user()['id']]);
                    }
                    $newCourseId = (int)$pdo->lastInsertId();

                    // عکس دوره (در صورت ارسال) - فیلد course_photo_{idx}
                    $photoField = 'course_photo_'.$idx;
                    if (!empty($_FILES['courses_files']['name'][$idx]['photo']) && is_array($_FILES['courses_files']['name'][$idx])) {
                        // ساختار پیچیده‌ای که در JS می‌سازیم: courses_files[idx][photo]
                        $fName = $_FILES['courses_files']['name'][$idx]['photo'] ?? '';
                        $fTmp = $_FILES['courses_files']['tmp_name'][$idx]['photo'] ?? '';
                        $fErr = $_FILES['courses_files']['error'][$idx]['photo'] ?? UPLOAD_ERR_NO_FILE;
                        $fSize = $_FILES['courses_files']['size'][$idx]['photo'] ?? 0;
                        if ($fName !== '' && $fErr === UPLOAD_ERR_OK && (int)$fSize > 0 && (int)$fSize <= 8*1024*1024 && is_uploaded_file($fTmp)) {
                            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($fTmp);
                            $allowedDoc = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
                            if (isset($allowedDoc[$mime])) {
                                $stored = 'training_'.$personId.'_'.bin2hex(random_bytes(10)).'.'.$allowedDoc[$mime];
                                $docPath = storage_path('documents').$stored;
                                if (move_uploaded_file($fTmp, $docPath)) {
                                    $movedFiles[] = $docPath;
                                    $pdo->prepare('INSERT INTO training_documents(training_record_id, original_name, stored_name, mime_type, file_size, created_by) VALUES(?,?,?,?,?,?)')
                                        ->execute([$newCourseId, $fName, $stored, $mime, (int)$fSize, user()['id']]);
                                }
                            }
                        }
                    } elseif (!empty($_FILES[$photoField]['name'])) {
                        // ساختار ساده‌تر: course_photo_{idx}
                        $file = $_FILES[$photoField];
                        if ($file['error']===UPLOAD_ERR_OK && $file['size']<=8*1024*1024) {
                            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                            $allowedDoc=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
                            if (isset($allowedDoc[$mime])) {
                                $stored='training_'.$personId.'_'.bin2hex(random_bytes(10)).'.'.$allowedDoc[$mime];
                                $docPath=storage_path('documents').$stored;
                                if (move_uploaded_file($file['tmp_name'],$docPath)) {
                                    $movedFiles[]=$docPath;
                                    $pdo->prepare('INSERT INTO training_documents(training_record_id, original_name, stored_name, mime_type, file_size, created_by) VALUES(?,?,?,?,?,?)')
                                        ->execute([$newCourseId, $file['name'], $stored, $mime, (int)$file['size'], user()['id']]);
                                }
                            }
                        }
                    }
                }
            }
            /* ---- حذف دوره‌هایی که کاربر از فهرست «موجود» حذف کرده است ---- */
            if ($edit && !empty($_POST['courses_removed'])) {
                $removedIds = json_decode((string)$_POST['courses_removed'], true);
                if (is_array($removedIds) && $removedIds) {
                    $removedIds = array_values(array_filter(array_map('intval', $removedIds), fn($v) => $v > 0));
                    if ($removedIds) {
                        $placeholders = implode(',', array_fill(0, count($removedIds), '?'));
                        // ابتدا فایل‌های سند دوره‌های حذف‌شده را از روی دیسک پاک کن
                        $dq = $pdo->prepare("SELECT stored_name FROM training_documents td JOIN training_records tr ON tr.id=td.training_record_id WHERE tr.personnel_id=? AND tr.id IN ($placeholders)");
                        $dq->execute(array_merge([$personId], $removedIds));
                        foreach ($dq->fetchAll() as $dr) {
                            $path = storage_path('documents') . $dr['stored_name'];
                            if (is_file($path)) { @unlink($path); }
                        }
                        $pdo->prepare("DELETE FROM training_records WHERE personnel_id=? AND id IN ($placeholders)")
                            ->execute(array_merge([$personId], $removedIds));
                    }
                }
            }
            foreach(['birth_certificate'=>'document_birth_certificate','national_card'=>'document_national_card','criminal_record'=>'document_criminal_record','education_certificate'=>'document_education_certificate','driving_license'=>'document_driving_license','personal_form'=>'document_personal_form'] as $documentType=>$input){
                if(empty($_FILES[$input]['name'])) continue; $file=$_FILES[$input]; if($file['error']!==UPLOAD_ERR_OK||$file['size']>8*1024*1024) throw new RuntimeException('حجم یکی از مدارک بیش از حد مجاز است.');
                $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($allowed[$mime]))throw new RuntimeException('فرمت یکی از مدارک مجاز نیست.');
                $stored='doc_'.$personId.'_'.bin2hex(random_bytes(10)).'.'.$allowed[$mime]; $docPath=storage_path('documents').$stored; if(!move_uploaded_file($file['tmp_name'],$docPath))throw new RuntimeException('ذخیره یکی از مدارک انجام نشد.'); $movedFiles[]=$docPath;
                $pdo->prepare('INSERT INTO personnel_documents(personnel_id,document_type,original_name,stored_name,mime_type,file_size,created_by) VALUES(?,?,?,?,?,?,?)')->execute([$personId,$documentType,$file['name'],$stored,$mime,(int)$file['size'],user()['id']]);
            }
            $pdo->commit();
            redirect('personnel_view.php?id='.$personId);
        }catch(RuntimeException $e){ personnel_form_rollback($pdo,$movedFiles); $error=$e->getMessage(); }
        catch(PDOException $e){ personnel_form_rollback($pdo,$movedFiles);$code=(int)($e->errorInfo[1]??0);$msg=(string)($e->errorInfo[2]??'');
            if($code===1062 && str_contains($msg,'uq_personnel_identity')) $error='رکوردی با ترکیب همین نام، نام خانوادگی، سمت، نوع رسته، شماره دسته، استان و شهرستان قبلاً ثبت شده است.';
            elseif($code===1062 && str_contains($msg,'uq_personnel_national_id')) $error='کد ملی قبلاً ثبت شده است.';
            elseif($code===1062 && str_contains($msg,'uq_personnel_command_scope')) $error='برای این جایگاه (رسته، شماره دسته، استان و شهرستان)، فرمانده/مسئول متناظر قبلاً ثبت شده است.';
            elseif(str_contains($msg,'تیم انتخاب‌شده')) $error='تیم انتخاب‌شده با نوع رسته سازگار نیست.';
            else $error='ذخیره اطلاعات انجام نشد.';
        }
    }
}

$districtsList=[]; $currentDistrict=(int)($values['district_id']??0);
// شهرستانی که فقط یک منطقه دارد (مثل شمیرانات) خودش همان منطقه است: کادر قفل و مقدار خودکار.

if(!empty($values['city_id'])){ try { $dl=$pdo->prepare('SELECT id,district_name FROM city_districts WHERE city_id=? AND is_active=1 ORDER BY district_number'); $dl->execute([(int)$values['city_id']]); $districtsList=$dl->fetchAll(); } catch (Throwable $e) { $districtsList=[]; } }
// «منطقه» فقط برای شهرستانی که منطقه‌بندی واقعی دارد (شهرستان تهران با ۲۲ منطقه) باز می‌شود.
$districtSelectable = count($districtsList) > 1;
$birthJalali=jalali_to_input($values['birth_date']??'');
$currentProvince=(int)($values['province_id']??0); if($currentProvince && !$cities){$st=$pdo->prepare("SELECT c.id,c.city_name FROM cities c INNER JOIN provinces p ON p.id=c.province_id WHERE c.province_id=? AND p.province_code='TEH' AND c.is_active=1 ORDER BY c.city_name");$st->execute([$currentProvince]);$cities=$st->fetchAll();}

/* ---- دوره‌های گذرانده شده (برای حالت ویرایش از دیتابیس لود می‌شود) ---- */
$existingCourses = [];
if ($edit) {
    try {
        $cq = $pdo->prepare('SELECT id, course_name, training_date, description, certificate_serial, created_at FROM training_records WHERE personnel_id=? ORDER BY COALESCE(training_date, created_at) DESC, id DESC');
        $cq->execute([$id]);
        foreach ($cq->fetchAll() as $cr) {
            $existingCourses[] = [
                'id' => (int)$cr['id'],
                'name' => (string)$cr['course_name'],
                'date' => $cr['training_date'] ? jalali_to_input($cr['training_date']) : '',
                'description' => (string)($cr['description'] ?? ''),
                'cert' => (string)($cr['certificate_serial'] ?? ''),
                'created_at' => $cr['created_at'],
            ];
        }
    } catch (Throwable $e) { $existingCourses = []; }
}
require __DIR__.'/../app/partials/header.php'; ?>
<section class="page-head"><div><h1><?=$edit?'ویرایش پرونده عنصر':'افزودن عنصر جدید'?></h1></div></section>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<form method="post" enctype="multipart/form-data" class="panel form-grid" id="personnelForm">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

<div class="section-title wide">اطلاعات فردی</div>
<?php
  $languageOptions=language_options();
  $currentLanguages=array_values(array_filter(explode(',', (string)($values['languages']??'')), fn($x)=>isset($languageOptions[$x])));
  $languagesLabel = $currentLanguages
      ? implode('، ', array_map(fn($k)=>$languageOptions[$k]??$k, $currentLanguages))
      : 'انتخاب زبان‌های خارجی';

  $healthOptions=health_status_options(); $licenseTypeOptions=license_type_options(); $licenseLevelOptions=license_level_options();
  $currentHealth=(string)($values['health_status']??'');
  $currentLicenseTypes=array_filter(explode(',', (string)($values['license_types']??'')));
  $currentLicenseLevel=(string)($values['license_level']??'');
  $healthLabel = $currentHealth!=='' ? ($healthOptions[$currentHealth].($values['health_note']!==''?' — '.$values['health_note']:'')) : 'انتخاب وضعیت سلامت';
  $licenseLabel = $currentLicenseTypes
      ? implode('، ', array_map(fn($k)=>$licenseTypeOptions[$k]??$k, $currentLicenseTypes)).($currentLicenseLevel?' — تسلط '.$licenseLevelOptions[$currentLicenseLevel]:'')
      : 'انتخاب وضعیت گواهینامه';
?>
<label><span class="lbl">نام</span><input name="first_name" required value="<?=e($values['first_name']??'')?>"></label>
<label><span class="lbl">نام خانوادگی</span><input name="last_name" required value="<?=e($values['last_name']??'')?>"></label>
<label><span class="lbl">کد ملی</span><input name="national_id" required inputmode="numeric" maxlength="10" value="<?=e($values['national_id']??'')?>"></label>
<label><span class="lbl">نام پدر</span><input name="father_name" required value="<?=e($values['father_name']??'')?>"></label>
<label><span class="lbl">شهرت</span><input name="alias_name" required value="<?=e($values['alias_name']??'')?>"></label>
<label><span class="lbl">تاریخ تولد</span><input name="birth_date_jalali" required class="jalali" maxlength="10" inputmode="numeric" autocomplete="off" value="<?=e($birthJalali)?>"></label>
<label><span class="lbl">دین</span><input name="religion" required value="<?=e($values['religion']??'')?>"></label>
<label><span class="lbl">مذهب</span><input name="denomination" required value="<?=e($values['denomination']??'')?>"></label>
<label><span class="lbl">تحصیلات</span><select name="education_status" required><option value="" disabled hidden <?=($values['education_status']??'')===''?'selected':''?>>انتخاب تحصیلات</option><?php foreach($eduLabels as $k=>$v):?><option value="<?=$k?>" <?=($values['education_status']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
<label><span class="lbl">وضعیت تأهل</span><select name="marital_status" required><option value="" disabled hidden <?=($values['marital_status']??'')===''?'selected':''?>>انتخاب وضعیت تأهل</option><option value="single" <?=($values['marital_status']??'')==='single'?'selected':''?>>مجرد</option><option value="married" <?=($values['marital_status']??'')==='married'?'selected':''?>>متأهل</option><option value="separated" <?=($values['marital_status']??'')==='separated'?'selected':''?>>متارکه</option></select></label>
<label class="picker-field"><span class="lbl">وضعیت سلامت</span>
  <button type="button" class="picker-trigger<?= $currentHealth!==''?' has-value':'' ?>" data-pv-open="healthModal" id="healthTrigger"><span data-health-label><?=e($healthLabel)?></span><span class="picker-arrow">⌄</span></button>
  <input type="hidden" name="health_status" id="healthStatusInput" value="<?=e($currentHealth)?>" required>
  <input type="hidden" name="health_note" id="healthNoteInput" value="<?=e($values['health_note']??'')?>">
</label>
<label class="picker-field"><span class="lbl">وضعیت گواهینامه</span>
  <button type="button" class="picker-trigger<?= $currentLicenseTypes?' has-value':'' ?>" data-pv-open="licenseModal" id="licenseTrigger"><span data-license-label><?=e($licenseLabel)?></span><span class="picker-arrow">⌄</span></button>
  <input type="hidden" name="license_level" id="licenseLevelInput" value="<?=e($currentLicenseLevel)?>" required>
  <span id="licenseTypesHolder"><?php foreach($currentLicenseTypes as $lt): ?><input type="hidden" name="license_types[]" value="<?=e($lt)?>"><?php endforeach; ?></span>
</label>
<label class="picker-field"><span class="lbl">زبان خارجی</span>
  <button type="button" class="picker-trigger<?= $currentLanguages?' has-value':'' ?>" data-pv-open="languagesModal" id="languagesTrigger"><span data-languages-label><?=e($languagesLabel)?></span><span class="picker-arrow">⌄</span></button>
  <span id="languagesHolder"><?php foreach($currentLanguages as $lng): ?><input type="hidden" name="languages[]" value="<?=e($lng)?>"><?php endforeach; ?></span>
</label>
<label><span class="lbl">مهارت ورزشی</span><input name="sport_skill" maxlength="150" value="<?=e($values['sport_skill']??'')?>" placeholder="مثلاً شنا، فوتبال، کشتی"></label>
<label><span class="lbl">حرفه و تخصص</span><input name="secondary_job" value="<?=e($values['secondary_job']??'')?>"></label>
<label><span class="lbl">شماره تماس ثابت</span><input name="landline_phone" inputmode="numeric" value="<?=e($values['landline_phone']??'')?>"></label>
<label><span class="lbl">شماره تماس همراه</span><input name="mobile" required inputmode="numeric" value="<?=e($values['mobile']??'')?>"></label>
<label><span class="lbl">شماره تماس اضطراری</span><input name="emergency_phone" required inputmode="numeric" value="<?=e($values['emergency_phone']??'')?>"></label>
<label><span class="lbl">کد پستی محل سکونت</span><input name="postal_code" required inputmode="numeric" maxlength="10" value="<?=e($values['postal_code']??'')?>"></label>
<label class="wide"><span class="lbl">آدرس محل سکونت</span><textarea name="residence_address" required rows="3"><?=e($values['residence_address']??'')?></textarea></label>

<div class="section-title wide">اطلاعات معرف</div>
<label><span class="lbl">نام</span><input name="referrer_first_name" required value="<?=e($values['referrer_first_name']??'')?>"></label>
<label><span class="lbl">نام خانوادگی</span><input name="referrer_last_name" required value="<?=e($values['referrer_last_name']??'')?>"></label>
<label><span class="lbl">کد ملی</span><input name="referrer_national_id" required inputmode="numeric" maxlength="10" value="<?=e($values['referrer_national_id']??'')?>"></label>
<label><span class="lbl">شماره تماس همراه</span><input name="referrer_mobile" required inputmode="numeric" value="<?=e($values['referrer_mobile']??'')?>"></label>

<div class="section-title wide">اطلاعات شغلی</div>
<p class="zone-auto-note wide">«حرفه و تخصص» در بخش اطلاعات فردی قرار دارد. در اینجا فقط آدرس محل کار (اگر حرفه‌ای دوم دارد) را وارد کنید.</p>
<label class="wide"><span class="lbl">آدرس محل کار</span><textarea name="secondary_job_address" required rows="3"><?=e($values['secondary_job_address']??'')?></textarea></label>

<div class="section-title wide">اطلاعات بانکی</div>
<label><span class="lbl">شماره شبا</span><div class="iban-input"><span class="iban-prefix">IR</span><input name="iban_digits" required placeholder="حداکثر ۲۴ رقم وارد شود" maxlength="24" inputmode="numeric" value="<?=e(substr((string)($values['iban']??''),2,24))?>"><input type="hidden"  name="iban" value="<?=e($values['iban']??'')?>"></div></label>

<div class="section-title wide">سابقه و گواهی‌ها</div>
<?php $criminalIssueJalali = $values['criminal_record_issue_date'] ? jalali_to_input($values['criminal_record_issue_date']) : ''; ?>
<label><span class="lbl">تاریخ صدور گواهی سوء پیشینه</span><input type="text" name="criminal_record_issue_date" class="jalali" maxlength="10" inputmode="numeric" autocomplete="off" placeholder="۱۴۰۳/۰۱/۰۱" value="<?=e($criminalIssueJalali)?>"></label>

<div class="section-title wide">جایگاه سازمانی</div>
<div class="organization-fields wide">
 <label class="org-solo"><span class="lbl">کد سازمانی</span><input name="organizational_code" required inputmode="numeric" value="<?=e($values['organizational_code']??'')?>"></label>
 <span class="org-line-break" aria-hidden="true"></span>
 <label class="location-field"><span class="lbl">استان محل خدمت</span><div class="smart-select" id="provinceSmart"><button type="button" class="smart-select-trigger" aria-haspopup="listbox" aria-expanded="false"><span class="smart-select-value">انتخاب استان</span><span class="smart-select-arrow">⌄</span></button><div class="smart-select-menu" role="listbox"><div class="smart-select-search-wrap"><input type="search" class="smart-select-search" placeholder="جست‌وجوی استان" autocomplete="off"></div><div class="smart-select-options"></div></div><select name="province_id" id="provinceSelect" class="smart-select-native" required><option value="" disabled hidden <?=empty($values['province_id'])?'selected':''?>>انتخاب استان</option><?php foreach($provinces as $p):?><option value="<?=$p['id']?>" <?=((int)$values['province_id']===(int)$p['id'])?'selected':''?>><?=e($p['province_name'])?></option><?php endforeach;?></select></div></label>
 <label><span class="lbl">شماره قائد</span><input name="commander_number" required inputmode="numeric" maxlength="30" value="<?=e($values['commander_number']??'')?>"></label>
 <label class=""><span class="lbl">سمت</span><select name="position_type" id="positionSelect" required><option value="" disabled hidden <?=($values['position_type']??'')===''?'selected':''?>>انتخاب سمت</option><?php foreach($positionLabels as $k=>$v):?><option value="<?=$k?>" <?=($values['position_type']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach;?></select></label>
 <label><span class="lbl">رسته</span><select name="unit" id="unitSelect" required><option value="" disabled hidden <?=((string)($values['unit']??''))===''?'selected':''?>>انتخاب رسته</option><?php foreach($categoryLabels as $k=>$v):?><option value="<?=e($k)?>" <?=((string)($values['unit']??'')===(string)$k?'selected':'')?>><?=e($v)?></option><?php endforeach;?></select></label>
 <label><span class="lbl">شماره دسته</span><input name="unit_number" inputmode="numeric" value="<?=e($values['unit_number']??'1')?>" required></label>
 <label id="groupField"><span class="lbl">گروه</span><select name="group_no" id="groupSelect" required><option value="" disabled hidden <?=($values['group_no']??'')===''?'selected':''?>>انتخاب گروه</option><option value="1" <?=((int)($values['group_no']??0)===1?'selected':'')?>>گروه ۱</option><option value="2" <?=((int)($values['group_no']??0)===2?'selected':'')?>>گروه ۲</option><option value="3" <?=((int)($values['group_no']??0)===3?'selected':'')?>>گروه ۳</option></select></label>
 <label><span class="lbl">تیم</span><select name="team_no" id="teamSelect" required></select></label>
</div>

<div class="section-title wide">دوره‌های گذرانده</div>
<div class="wide training-section-block">
  <div class="training-section-head">
    <button type="button" class="btn primary" data-pv-open="courseModal" id="addCourseBtn">افزودن دوره</button>
  </div>
  <div id="coursesList" class="courses-list">
    <?php if (!$edit): ?>
      <div class="empty courses-empty">هنوز دوره‌ای اضافه نشده است.</div>
    <?php endif; ?>
    <?php foreach ($existingCourses as $i => $ec): ?>
      <div class="course-row" data-existing-id="<?= (int)$ec['id'] ?>">
        <div class="course-row-icon">✓</div>
        <div class="course-row-main">
          <strong><?= e($ec['name']) ?></strong>
          <?php if ($ec['date'] !== ''): ?><small>تاریخ: <?= e($ec['date']) ?></small><?php endif; ?>
          <?php if ($ec['description'] !== ''): ?><small>توضیح: <?= e(mb_strimwidth($ec['description'], 0, 80, '…')) ?></small><?php endif; ?>
        </div>
        <button type="button" class="course-row-remove" title="حذف" data-remove-existing="<?= (int)$ec['id'] ?>">×</button>
      </div>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="courses_json" id="coursesJson" value="">
</div>

<div class="pv-modal" id="courseModal" aria-hidden="true">
  <div class="pv-modal-backdrop" data-pv-close></div>
  <section class="pv-modal-card" role="dialog" aria-modal="true" aria-labelledby="courseModalTitle">
    <header class="pv-modal-head">
      <div><h2 id="courseModalTitle">افزودن دوره گذرانده</h2></div>
      <button type="button" class="pv-modal-close" data-pv-close aria-label="بستن">×</button>
    </header>
    <div class="pick-list">
      <div class="pick-scroll">
        <label class="pv-field pv-field-wide"><span class="lbl">عنوان دوره</span><input type="text" id="courseName" maxlength="150" autocomplete="off"></label>
        <label class="pv-field pv-field-wide"><span class="lbl">تاریخ</span><input type="text" id="courseDate" class="jalali" maxlength="10" inputmode="numeric" autocomplete="off"></label>
        <div class="pv-field pv-field-wide">
          <span class="lbl">توضیحات</span>
          <textarea id="courseDescription" rows="3" maxlength="2000" class="course-desc-textarea"></textarea>
        </div>
        <div class="pv-field pv-field-wide">
          <span class="lbl">آپلود عکس</span>
          <div class="course-photo-card pv-upload-card">
            <div class="course-photo-icon">▣</div>
            <div class="course-photo-info"><strong>عکس دوره</strong><small>یک فایل، حداکثر ۸MB — JPG/PNG/WEBP/PDF</small></div>
            <label class="file-picker">
              <span data-file-label="انتخاب فایل">انتخاب فایل</span>
              <input type="file" id="coursePhotoFile" accept="image/jpeg,image/png,image/webp,application/pdf">
            </label>
          </div>
        </div>
        <div id="courseFormError" class="course-form-error" hidden></div>
      </div>
    </div>
    <div class="pv-modal-actions course-modal-actions">
      <button type="button" class="btn primary" id="courseApply">ثبت</button>
      <button type="button" class="btn secondary" data-pv-close>انصراف</button>
    </div>
  </section>
</div>

<div class="section-title wide">حوزه استحفاظی</div>
<p class="zone-auto-note wide">حوزه استحفاظی به‌صورت خودکار با توجه به اطلاعات دسته عنصر انتخاب می‌شود.</p>
<input type="hidden" name="city_id" id="citySelect" value="<?=e((string)($values['city_id']??''))?>">
<input type="hidden" name="district_id" id="districtSelect" value="<?=e((string)($values['district_id']??''))?>">

<div class="section-title wide">عکس و مدارک</div>
<div class="document-upload-card profile-photo-upload-card" id="profilePhoto"><div class="document-upload-icon">▣</div><div><strong>عکس پرونده</strong><small>یک فایل، حداکثر ۳MB</small></div><label class="file-picker"><span>انتخاب فایل</span><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"></label></div>
<div class="documents-grid wide">
 <div class="document-upload-card"><div class="document-upload-icon">▣</div><div><strong>شناسنامه</strong><small>یک فایل، حداکثر 8MB</small></div><label class="file-picker"><span>انتخاب فایل</span><input type="file" name="document_birth_certificate" accept="application/pdf,image/jpeg,image/png,image/webp"></label></div>
 <div class="document-upload-card"><div class="document-upload-icon">▤</div><div><strong>کارت ملی</strong><small>یک فایل، حداکثر 8MB</small></div><label class="file-picker"><span>انتخاب فایل</span><input type="file" name="document_national_card" accept="application/pdf,image/jpeg,image/png,image/webp"></label></div>
 <div class="document-upload-card"><div class="document-upload-icon">▧</div><div><strong>گواهی عدم سوء پیشینه</strong><small>یک فایل، حداکثر 8MB</small></div><label class="file-picker"><span>انتخاب فایل</span><input type="file" name="document_criminal_record" accept="application/pdf,image/jpeg,image/png,image/webp"></label></div>
 <div class="document-upload-card"><div class="document-upload-icon">▥</div><div><strong>مدرک تحصیلی</strong><small>یک فایل، حداکثر 8MB</small></div><label class="file-picker"><span>انتخاب فایل</span><input type="file" name="document_education_certificate" accept="application/pdf,image/jpeg,image/png,image/webp"></label></div>
 <div class="document-upload-card"><div class="document-upload-icon">▨</div><div><strong>گواهینامه</strong><small>یک فایل، حداکثر 8MB</small></div><label class="file-picker"><span>انتخاب فایل</span><input type="file" name="document_driving_license" accept="application/pdf,image/jpeg,image/png,image/webp"></label></div>
 <div class="document-upload-card"><div class="document-upload-icon">▩</div><div><strong>فرم اطلاعات فردی</strong><small>یک فایل، حداکثر 8MB</small></div><label class="file-picker"><span>انتخاب فایل</span><input type="file" name="document_personal_form" accept="application/pdf,image/jpeg,image/png,image/webp"></label></div>
</div>

<div class="pv-modal" id="healthModal" aria-hidden="true">
  <div class="pv-modal-backdrop" data-pv-close></div>
  <section class="pv-modal-card" role="dialog" aria-modal="true" aria-labelledby="healthModalTitle">
    <header class="pv-modal-head"><div><h2 id="healthModalTitle">وضعیت سلامت</h2></div><button type="button" class="pv-modal-close" data-pv-close aria-label="بستن">×</button></header>
    <div class="pick-list">
      <div class="pick-scroll">
        <?php foreach(health_status_options() as $k=>$v): ?>
        <label class="pick-item"><input type="radio" name="health_pick" value="<?=e($k)?>" <?= $currentHealth===$k?'checked':'' ?>><span><?=e($v)?></span></label>
        <?php endforeach; ?>
      </div>
      <label class="pv-field pv-field-wide" id="healthNoteField" hidden><span>توضیح</span><input type="text" id="healthNoteText" maxlength="255" placeholder="نوع بیماری یا توضیح لازم" value="<?=e($values['health_note']??'')?>"></label>
    </div>
    <div class="pv-modal-actions"><button type="button" class="btn pv-btn-success" id="healthApply">تایید</button><button type="button" class="btn secondary" data-pv-close>انصراف</button></div>
  </section>
</div>

<div class="pv-modal" id="licenseModal" aria-hidden="true">
  <div class="pv-modal-backdrop" data-pv-close></div>
  <section class="pv-modal-card" role="dialog" aria-modal="true" aria-labelledby="licenseModalTitle">
    <header class="pv-modal-head"><div><h2 id="licenseModalTitle">وضعیت گواهینامه</h2></div><button type="button" class="pv-modal-close" data-pv-close aria-label="بستن">×</button></header>
    <div class="pick-list">
      <div class="pick-scroll">
        <?php foreach(license_type_options() as $k=>$v): ?>
        <label class="pick-item"><input type="checkbox" name="license_pick" value="<?=e($k)?>" <?= in_array($k,$currentLicenseTypes,true)?'checked':'' ?>><span><?=e($v)?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="level-group" id="licenseLevelGroup">
        <span class="level-title">سطح تسلط</span>
        <div class="level-buttons">
          <?php foreach(license_level_options() as $k=>$v): ?>
          <button type="button" class="level-btn<?= $currentLicenseLevel===$k?' active':'' ?>" data-level="<?=e($k)?>"><?=e($v)?></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="pv-modal-actions"><button type="button" class="btn pv-btn-success" id="licenseApply">تایید</button><button type="button" class="btn secondary" data-pv-close>انصراف</button></div>
  </section>
</div>

<div class="pv-modal" id="languagesModal" aria-hidden="true">
  <div class="pv-modal-backdrop" data-pv-close></div>
  <section class="pv-modal-card" role="dialog" aria-modal="true" aria-labelledby="languagesModalTitle">
    <header class="pv-modal-head"><div><h2 id="languagesModalTitle">زبان‌های خارجی</h2></div><button type="button" class="pv-modal-close" data-pv-close aria-label="بستن">×</button></header>
    <div class="pick-list">
      <div class="pick-scroll">
        <?php foreach($languageOptions as $k=>$v): ?>
        <label class="pick-item"><input type="checkbox" name="languages_pick" value="<?=e($k)?>" <?= in_array($k,$currentLanguages,true)?'checked':'' ?>><span><?=e($v)?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="pv-modal-actions"><button type="button" class="btn pv-btn-success" id="languagesApply">تایید</button><button type="button" class="btn secondary" data-pv-close>انصراف</button></div>
  </section>
</div>

<div class="actions wide"><button class="btn primary " type="submit">ثبت</button><a class="btn secondary" href="personnel.php">انصراف</a></div>
</form>
<style>
/* چیدمان مخصوص این صفحه؛ ظاهر دراپ‌داون‌ها از استایل سراسری (بلوک V4.55) می‌آید. */
.location-field{gap:7px!important}
.smart-select{position:relative;width:100%}

/* ----- بخش دوره‌های گذرانده ----- */
.training-section-block{display:flex;flex-direction:column;gap:12px}
/* در حالت RTL، flex-start به معنای سمت راست است */
.training-section-head{display:flex;justify-content:flex-start;align-items:center;gap:14px;flex-wrap:wrap}
.courses-list{display:flex;flex-direction:column;gap:9px}
.course-row{display:grid;grid-template-columns:36px 1fr 32px;align-items:center;gap:12px;padding:11px 13px;border:1px solid #dfe9e4;border-radius:13px;background:#f4f8f5}
.course-row-icon{width:32px;height:32px;display:grid;place-items:center;border-radius:9px;background:#244b3b;color:#fff;font-weight:900}
.course-row-main{display:flex;flex-direction:column;min-width:0}
.course-row-main strong{color:#244b3b;font-size:14px}
.course-row-main small{color:#6b7872;font-size:11px;margin-top:3px}
.course-row-remove{background:#fbecec;color:#a93b3b;border:1px solid #f0c5c5;border-radius:9px;width:30px;height:30px;font-size:18px;cursor:pointer;font-family:inherit;line-height:1}
.course-row-remove:hover{background:#f6dada}
.courses-empty{padding:18px !important;border:1px dashed #d8e4dc;border-radius:13px;background:#fafdfb;color:#7a8780;font-size:13px}
.course-modal-actions{justify-content:flex-start !important;gap:10px}
.course-form-error{background:#fff0f0;color:#a12525;border:1px solid #f3c8c8;border-radius:11px;padding:10px 12px;font-size:12px;margin-top:8px}
.course-desc-textarea{width:100%;border:1px solid #dbe1ea;border-radius:11px;padding:11px 12px;font:inherit;color:#172235;background:#fff;resize:vertical;min-height:80px}
.course-desc-textarea:focus{outline:none;border-color:#91a8c4;box-shadow:0 0 0 3px rgba(36,77,125,.08)}
.course-photo-card{display:grid;grid-template-columns:42px 1fr;gap:12px;align-items:center;padding:14px;border:1px solid #dfe9e4;border-radius:16px;background:linear-gradient(180deg,#ffffff,#f8fbf9);box-shadow:0 7px 20px rgba(36,75,59,.06)}
.course-photo-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#edf5f0;color:#244b3b;font-weight:900;font-size:20px}
.course-photo-info strong{display:block;color:#244b3b;font-size:13px}
.course-photo-info small{display:block;margin-top:3px;color:#87938c;font-size:10px}
.course-photo-card .file-picker{grid-column:1 / -1;position:relative;display:block}
.course-photo-card .file-picker span{display:flex;align-items:center;justify-content:center;height:40px;border:1px dashed #b8ccbf;border-radius:11px;background:#f7fbf8;color:#244b3b;font-size:11px;font-weight:800;cursor:pointer}
.course-photo-card .file-picker input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
.pv-modal .pick-scroll{padding:0 4px}
.pv-modal .pv-field{display:flex;flex-direction:column;gap:7px;font-size:13px;color:#5d6879;margin-bottom:12px}
.pv-modal .pv-field input,.pv-modal .pv-field select,.pv-modal .pv-field textarea{border:1px solid #dbe1ea;border-radius:11px;padding:11px 12px;font:inherit;color:#172235;background:#fff;width:100%}
.pv-modal .pv-field input:focus,.pv-modal .pv-field textarea:focus{outline:none;border-color:#91a8c4;box-shadow:0 0 0 3px rgba(36,77,125,.08)}
.pv-modal .pv-field>span{color:#5d6879}
.pv-modal .pv-field>span .req{color:#c1393e;margin-right:3px}
</style>
<script>
(function(){
 const unitSelect=document.getElementById('unitSelect'),teamSelect=document.getElementById('teamSelect'),positionSelect=document.getElementById('positionSelect'),groupSelect=document.getElementById('groupSelect'),groupField=document.getElementById('groupField');
 const provinceSelect=document.getElementById('provinceSelect');
 const currentTeam=<?=json_encode((string)($values['team_no']??''),JSON_UNESCAPED_UNICODE)?>;
 const currentGroup=<?=json_encode((string)($values['group_no']??''),JSON_UNESCAPED_UNICODE)?>;
 
 function refreshTeams(reset=false){
  const options=unitSelect.value==='information'?[['1','پیاده'],['2','موتوری'],['3','خودرویی']]:[['1','تیم ۱'],['2','تیم ۲'],['3','تیم ۳']];
  const keep=reset?'':(teamSelect.value||currentTeam||'');
  teamSelect.innerHTML='<option value="" disabled hidden selected>انتخاب تیم</option>'+options.map(([v,t])=>`<option value="${v}">${t}</option>`).join('');
  // با عوض‌شدن رسته، گزینه‌ها بازسازی می‌شوند؛ اگر انتخاب قبلی نبود، متن راهنما بماند.
  teamSelect.value = keep || '';
  if(!teamSelect.value) teamSelect.selectedIndex = 0;
 }
 function syncOrganizationFields(){
  const commander=positionSelect.value,unitCommander=commander==='unit_commander',groupCommander=commander==='group_commander';
  groupField.style.display='flex'; groupSelect.disabled=unitCommander; teamSelect.disabled=unitCommander||groupCommander;
  if(unitCommander){groupSelect.value='';teamSelect.value='';} else if(!groupSelect.value && currentGroup) groupSelect.value=currentGroup;
  if(!unitCommander&&!groupCommander&&!teamSelect.value) teamSelect.value=currentTeam||'';
 }
 unitSelect.addEventListener('change',()=>{refreshTeams(true);syncOrganizationFields();}); positionSelect.addEventListener('change',syncOrganizationFields); refreshTeams(false);syncOrganizationFields();

 const faFold=(v)=>String(v??'').replace(/[۰-۹٠-٩]/g,d=>{const i='۰۱۲۳۴۵۶۷۸۹'.indexOf(d);return i>-1?String(i):String('٠١٢٣٤٥٦٧٨٩'.indexOf(d));}).toLocaleLowerCase('fa-IR');
 function setupSmartSelect(root, select, placeholder){
  if(!root||!select)return {setOptions(){},reset(){}};
  const trigger=root.querySelector('.smart-select-trigger'),valueEl=root.querySelector('.smart-select-value'),menu=root.querySelector('.smart-select-menu'),search=root.querySelector('.smart-select-search'),optionsEl=root.querySelector('.smart-select-options');
  function render(){
    const q=faFold((search.value||'').trim());
    const opts=[...select.options].filter(o=>o.value!=='' && !o.disabled);
    const filtered=opts.filter(o=>faFold(o.textContent).includes(q));
    optionsEl.innerHTML=filtered.length?filtered.map(o=>`<div class="smart-select-option ${o.selected?'active':''}" role="option" data-value="${o.value}">${o.textContent}</div>`).join(''):'<div class="smart-select-empty">موردی پیدا نشد</div>';
    // preventDefault لازم است: کل کنترل داخل یک <label> است و کلیک روی گزینه،
    // به‌صورت خودکار دوباره روی دکمهٔ trigger شلیک می‌شد و منو باز می‌ماند.
    optionsEl.querySelectorAll('.smart-select-option').forEach(el=>el.addEventListener('click',ev=>{ev.preventDefault();ev.stopPropagation();select.value=el.dataset.value;select.dispatchEvent(new Event('change',{bubbles:true})); close();}));
  }
  function sync(){const selected=select.selectedOptions[0]; valueEl.textContent=(selected && selected.value) ? selected.textContent.trim() : placeholder; root.classList.toggle('has-value', !!(selected && selected.value));}
  function open(){if(trigger.disabled)return;document.querySelectorAll('.smart-select.open').forEach(x=>x!==root&&x.classList.remove('open'));root.classList.add('open');trigger.setAttribute('aria-expanded','true');search.disabled=false;search.value='';render();setTimeout(()=>search.focus(),0);}
  function close(){root.classList.remove('open');trigger.setAttribute('aria-expanded','false');sync();}
  trigger.addEventListener('click',e=>{e.preventDefault();root.classList.contains('open')?close():open();});
  // هر کلیکی داخل منو نباید به فعال‌سازی <label> و باز/بسته‌شدن دوبارهٔ منو منجر شود.
  menu.addEventListener('click',e=>{e.preventDefault();});
  search.addEventListener('input',render); search.addEventListener('click',e=>e.stopPropagation());
  select.addEventListener('change',()=>{sync(); close();});
  sync(); render();
  return {setOptions(){sync();render();},reset(){select.value='';search.value='';sync();render();},close};
 }
 const provinceUI=setupSmartSelect(document.getElementById('provinceSmart'),provinceSelect,'انتخاب استان');
 document.addEventListener('click',e=>{document.querySelectorAll('.smart-select.open').forEach(root=>{if(!root.contains(e.target)){const trigger=root.querySelector('.smart-select-trigger');root.classList.remove('open');trigger?.setAttribute('aria-expanded','false');}});});

 const toEnglishDigits=s=>(s||'').replace(/[۰-۹]/g,d=>'۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/\D/g,'');
 function maskJalali(el){let v=toEnglishDigits(el.value).slice(0,8),out=v.slice(0,4);if(v.length>4)out+='/'+v.slice(4,6);if(v.length>6)out+='/'+v.slice(6,8);el.value=out.replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);}
 // فقط شماره شبا انگلیسی می‌ماند؛ بقیه ارقام را اسکریپت مشترک فوتر فارسی می‌کند.
 document.querySelectorAll('input.jalali').forEach(el=>{el.addEventListener('input',()=>maskJalali(el));el.addEventListener('paste',()=>setTimeout(()=>maskJalali(el),0));maskJalali(el);});
 // شبا: کادر ورودی با ارقام فارسی دیده می‌شود، ولی مقدار ارسالی به سرور لاتین است.
const ibanDigits=document.querySelector('input[name="iban_digits"]'),ibanHidden=document.querySelector('input[name="iban"]');
const syncIban=()=>{ if(!ibanDigits||!ibanHidden) return;
  const latin=toEnglishDigits(ibanDigits.value).replace(/\D/g,'').slice(0,24);
  ibanDigits.value=latin.replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[+d]);
  ibanHidden.value=latin?'IR'+latin:''; };
ibanDigits?.addEventListener('input',syncIban); syncIban();
 /* ---------- پاپ‌آپ وضعیت سلامت ---------- */
 const healthLabels=<?= json_encode(health_status_options(), JSON_UNESCAPED_UNICODE) ?>;
 const healthStatusInput=document.getElementById('healthStatusInput'), healthNoteInput=document.getElementById('healthNoteInput');
 const healthNoteField=document.getElementById('healthNoteField'), healthNoteText=document.getElementById('healthNoteText');
 const healthLabelEl=document.querySelector('[data-health-label]'), healthTrigger=document.getElementById('healthTrigger');
 function syncHealthNote(){
  const picked=document.querySelector('input[name="health_pick"]:checked');
  const needsNote=!!picked && picked.value!=='healthy';
  if(healthNoteField) healthNoteField.hidden=!needsNote;
 }
 document.querySelectorAll('input[name="health_pick"]').forEach(el=>el.addEventListener('change',syncHealthNote));
 syncHealthNote();
 document.getElementById('healthApply')?.addEventListener('click',()=>{
  const picked=document.querySelector('input[name="health_pick"]:checked');
  if(!picked) return;
  const note=(picked.value==='healthy')?'':(healthNoteText?.value||'').trim();
  if(picked.value!=='healthy' && !note){ healthNoteText?.focus(); return; }
  healthStatusInput.value=picked.value; healthNoteInput.value=note;
  healthLabelEl.textContent=healthLabels[picked.value]+(note?' — '+note:'');
  healthTrigger.classList.add('has-value');
  document.querySelectorAll('#healthModal [data-pv-close]')[0]?.click();
 });

 /* ---------- پاپ‌آپ وضعیت گواهینامه ---------- */
 const licenseTypeLabels=<?= json_encode(license_type_options(), JSON_UNESCAPED_UNICODE) ?>;
 const licenseLevelLabels=<?= json_encode(license_level_options(), JSON_UNESCAPED_UNICODE) ?>;
 const licenseLevelInput=document.getElementById('licenseLevelInput'), licenseHolder=document.getElementById('licenseTypesHolder');
 const licenseLabelEl=document.querySelector('[data-license-label]'), licenseTrigger=document.getElementById('licenseTrigger');
 let licenseLevel=licenseLevelInput.value||'';
 document.querySelectorAll('#licenseModal .level-btn').forEach(btn=>btn.addEventListener('click',()=>{
  document.querySelectorAll('#licenseModal .level-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active'); licenseLevel=btn.dataset.level;
  document.getElementById('licenseLevelGroup')?.classList.remove('needs-pick');
 }));
 document.getElementById('licenseApply')?.addEventListener('click',()=>{
  const picked=[...document.querySelectorAll('input[name="license_pick"]:checked')].map(i=>i.value);
  if(picked.length && !licenseLevel){ document.getElementById('licenseLevelGroup')?.classList.add('needs-pick'); return; }
  licenseHolder.innerHTML=picked.map(v=>`<input type="hidden" name="license_types[]" value="${v}">`).join('');
  licenseLevelInput.value=picked.length?licenseLevel:'';
  licenseLabelEl.textContent=picked.length
    ? picked.map(v=>licenseTypeLabels[v]).join('، ')+(licenseLevel?' — تسلط '+licenseLevelLabels[licenseLevel]:'')
    : 'انتخاب وضعیت گواهینامه';
  licenseTrigger.classList.toggle('has-value',picked.length>0);
  document.querySelectorAll('#licenseModal [data-pv-close]')[0]?.click();
 });

 /* ---------- پاپ‌آپ زبان‌های خارجی ---------- */
 const languageLabels=<?= json_encode(language_options(), JSON_UNESCAPED_UNICODE) ?>;
 const languagesHolder=document.getElementById('languagesHolder');
 const languagesLabelEl=document.querySelector('[data-languages-label]');
 const languagesTrigger=document.getElementById('languagesTrigger');
 document.getElementById('languagesApply')?.addEventListener('click',()=>{
  const picked=[...document.querySelectorAll('input[name="languages_pick"]:checked')].map(i=>i.value);
  languagesHolder.innerHTML=picked.map(v=>`<input type="hidden" name="languages[]" value="${v}">`).join('');
  languagesLabelEl.textContent=picked.length
    ? picked.map(v=>languageLabels[v]).join('، ')
    : 'انتخاب زبان‌های خارجی';
  languagesTrigger.classList.toggle('has-value',picked.length>0);
  document.querySelectorAll('#languagesModal [data-pv-close]')[0]?.click();
 });

 document.querySelectorAll('input[name="first_name"],input[name="last_name"],input[name="father_name"],input[name="secondary_job"]').forEach(el=>el.addEventListener('input',()=>{el.value=el.value.replace(/[0-9۰-۹]/g,'');}));

 /* ---------- دوره‌های گذرانده (افزودن در پاپ‌آپ + نمایش در فهرست) ---------- */
 (function(){
  const list = document.getElementById('coursesList');
  const jsonInput = document.getElementById('coursesJson');
  const formEl = document.getElementById('personnelForm');
  if (!list || !jsonInput || !formEl) return;

  // آرایه‌ای از دوره‌های جدید که در همین فرم اضافه شده‌اند (هنوز در DB نیستند)
  const pending = [];
  // شناسهٔ دوره‌هایی که در حالت ویرایش حذف شده‌اند (تا در سمت سرور از DB پاک شوند)
  const removedExisting = new Set();
  let editingIdx = null; // اندیس دوره‌ای که در حال ویرایش آن هستیم (null = افزودن جدید)

  // رندر دوره‌های pending + دوره‌های موجود
  function render() {
    // دوره‌های موجودی که حذف نشده‌اند
    const existingRows = [...list.querySelectorAll('.course-row[data-existing-id]')]
      .filter(row => !removedExisting.has(row.dataset.existingId));
    list.innerHTML = '';
    existingRows.forEach(row => list.appendChild(row));
    pending.forEach((c, i) => {
      const row = document.createElement('div');
      row.className = 'course-row';
      row.dataset.idx = String(i);
      const dateText = c.date ? `تاریخ: ${escapeHtml(c.date)}` : '';
      const descText = c.description ? `توضیح: ${escapeHtml(truncate(c.description, 80))}` : '';
      const photoText = c._hasPhoto ? 'عکس: ضمیمه شد' : '';
      row.innerHTML = `
        <div class="course-row-icon">✓</div>
        <div class="course-row-main">
          <strong>${escapeHtml(c.name)}</strong>
          ${dateText ? `<small>${dateText}</small>` : ''}
          ${descText ? `<small>${descText}</small>` : ''}
          ${photoText ? `<small>${photoText}</small>` : ''}
        </div>
        <button type="button" class="course-row-remove" title="حذف" data-remove-pending="${i}">×</button>
      `;
      list.appendChild(row);
    });
    // دکمه‌های حذف را فعال کن
    list.querySelectorAll('[data-remove-pending]').forEach(btn => {
      btn.addEventListener('click', () => {
        const i = parseInt(btn.dataset.removePending, 10);
        pending.splice(i, 1);
        render();
        syncJson();
      });
    });
    list.querySelectorAll('[data-remove-existing]').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.removeExisting;
        removedExisting.add(id);
        render();
        syncRemovedInput();
      });
    });

    // نمایش/پنهان کردن پیام خالی
    const existingVisible = list.querySelectorAll('.course-row').length;
    let empty = list.querySelector('.courses-empty');
    if (existingVisible === 0 && !empty) {
      empty = document.createElement('div');
      empty.className = 'empty courses-empty';
      empty.textContent = 'هنوز دوره‌ای اضافه نشده است.';
      list.appendChild(empty);
    } else if (existingVisible > 0 && empty) {
      empty.remove();
    }
  }

  function escapeHtml(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch])); }
  function truncate(s, n) { s = String(s); return s.length > n ? s.slice(0, n) + '…' : s; }

  function syncJson() {
    // فقط داده‌های متنی به فرم ارسال می‌شود؛ فایل‌ها به‌صورت داینامیک به فرم اضافه می‌شوند
    const payload = pending.map(c => ({ name: c.name, date: c.date, description: c.description }));
    jsonInput.value = JSON.stringify(payload);
  }

  function syncRemovedInput() {
    // فیلد courses_removed را به‌روز کن
    let removedInput = formEl.querySelector('input[name="courses_removed"]');
    if (!removedInput) {
      removedInput = document.createElement('input');
      removedInput.type = 'hidden';
      removedInput.name = 'courses_removed';
      formEl.appendChild(removedInput);
    }
    removedInput.value = JSON.stringify([...removedExisting]);
  }

  // ---- ثبت دوره در پاپ‌آپ ----
  const applyBtn = document.getElementById('courseApply');
  const nameInput = document.getElementById('courseName');
  const dateInput = document.getElementById('courseDate');
  const descInput = document.getElementById('courseDescription');
  const photoInput = document.getElementById('coursePhotoFile');
  const errBox = document.getElementById('courseFormError');

  function showError(msg) {
    if (!errBox) return;
    errBox.textContent = msg;
    errBox.hidden = false;
  }
  function clearError() {
    if (!errBox) return;
    errBox.textContent = '';
    errBox.hidden = true;
  }

  // تابع کمکی برای ریست کامل همه چیز (شامل برچسب نام فایل)
  function resetCourseModal() {
    if (nameInput) nameInput.value = '';
    if (dateInput) dateInput.value = '';
    if (descInput) descInput.value = '';
    if (photoInput) photoInput.value = '';
    // بازنشانی برچسب نام فایل (اگر pv-modal.js آن را تغییر داده باشد)
    const fileLabel = document.querySelector('#courseModal [data-file-label]');
    if (fileLabel) fileLabel.textContent = 'انتخاب فایل';
    clearError();
  }

  if (applyBtn) {
    applyBtn.addEventListener('click', () => {
      clearError();
      const name = (nameInput.value || '').trim();
      if (!name) { showError('«عنوان دوره» را وارد کنید.'); nameInput.focus(); return; }
      const date = (dateInput.value || '').trim();
      const description = (descInput.value || '').trim();
      const file = photoInput.files && photoInput.files[0];
      let hasPhoto = false;
      if (file) {
        if (file.size > 8 * 1024 * 1024) { showError('حجم عکس دوره باید حداکثر ۸ مگابایت باشد.'); return; }
        const okTypes = ['image/jpeg','image/png','image/webp','application/pdf'];
        if (!okTypes.includes(file.type)) { showError('فرمت فایل مجاز نیست.'); return; }
        hasPhoto = true;
      }
      pending.push({ name, date, description, _hasPhoto: hasPhoto, _photoFile: file || null });
      // ریست کردن فیلدهای پاپ‌آپ و بستن
      resetCourseModal();
      render();
      syncJson();
      // بستن پاپ‌آپ
      document.querySelectorAll('#courseModal [data-pv-close]')[0]?.click();
    });
  }

  // پاک کردن خطا و ریست فیلدها هنگام باز شدن پاپ‌آپ
  document.querySelectorAll('[data-pv-open="courseModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
      // اندکی صبر تا reset انجام شود
      setTimeout(resetCourseModal, 50);
    });
  });

  // ---- هنگام submit فرم اصلی: فایل‌های دوره‌ها را به فرم اضافه کن ----
  formEl.addEventListener('submit', (ev) => {
    // ابتدا فیلدهای فایل قبلی را پاک کن
    formEl.querySelectorAll('input[type=file][data-course-photo]').forEach(el => el.remove());

    // فیلد files دوره‌ها به فرم اصلی اضافه می‌شود
    let hasFiles = false;
    pending.forEach((c, i) => {
      if (c._photoFile) {
        try {
          const dt = new DataTransfer();
          dt.items.add(c._photoFile);
          const inp = document.createElement('input');
          inp.type = 'file';
          inp.name = `course_photo_${i}`;
          inp.style.display = 'none';
          inp.setAttribute('data-course-photo', '1');
          inp.files = dt.files;
          formEl.appendChild(inp);
          hasFiles = true;
        } catch (err) {
          // اگر DataTransfer پشتیبانی نشد، فایل این دوره ثبت نمی‌شود
          console.warn('course photo upload failed', err);
        }
      }
    });
    syncJson();
    syncRemovedInput();
  });

  render();
 })();
})();
</script>
<script src="<?= e(asset_url('assets/pv-modal.js')) ?>" defer></script>
<?php require __DIR__.'/../app/partials/footer.php'; ?>
