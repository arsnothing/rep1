<?php
declare(strict_types=1);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/app/report-handler.php';
handleReportRequest();
require __DIR__ . '/includes/header.php';
?>

<section id="homePage" class="page active">
  <main class="home-content">
    <div class="home-brand">
      <img class="home-logo" src="src/NAJA.webp" alt="لوگو">
    </div>
    <p style="font-size:14px;font-weight:600;">كُونُوا قَوَّامِينَ لِلَّهِ شُهَدَاءَ بِالْقِسْطِ</p>
    <section class="home-quote" aria-label="گزیده‌ای از بیانات درباره امنیت">
      <p>امنیت پایدار، بدون مشارکت و همراهی مردم امکان پذیر نیست.</p>
      <span>قائد شهید امت آیت‌الله سیدعلی خامنه‌ای</span>
    </section>
    <section class="welcome">
      <p>
       هر گزارش مسئولانه شما، گامی مؤثر در مقابله با تهدیدات نوین و پاسداری از امنیت پایدار ایران عزیز اسلامی است؛<br> از این همراهی ارزشمند و مسئولیت پذیری شما سپاسگزاریم.
      </p>
      <div class="signature"> فرماندهی انتظامی جمهوری اسلامی ایران</div>
    </section>

    <div class="home-buttons">
      <button class="main-card" onclick="startReport()" type="button">
        <strong>ثبت گزارش</strong>
      </button>
      <button class="main-card secondary-main-card" onclick="openLocationRegistration()" type="button">
        <strong>ثبت موقعیت مکانی</strong>
      </button>
    </div>
  </main>
</section>

<section id="locationRegistrationPage" class="page construction-page">
  <main class="construction-shell">
    <button class="back-button button-with-icon" onclick="showPage('homePage')" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <section class="construction-card" aria-labelledby="constructionTitle">
      <div class="construction-visual" aria-hidden="true">
        <div class="construction-glow"></div>
        <div class="construction-ground"></div>
        <div class="construction-building">
          <span></span><span></span><span></span><span></span><span></span><span></span>
        </div>
        <?= buttonIcon('gear', 'construction-gear construction-gear-primary') ?>
        <?= buttonIcon('gear', 'construction-gear construction-gear-secondary') ?>
      </div>
      <p id="constructionStatus" class="construction-status" aria-live="polite"><span></span>در حال آماده‌سازی</p>
      <h1 id="constructionTitle">ثبت موقعیت مکانی<br>در حال ساخت است</h1>
      <p class="construction-copy">این بخش به‌زودی در دسترس قرار می‌گیرد. در حال آماده‌سازی تجربه‌ای دقیق‌تر و بهتر برای ثبت موقعیت هستیم.</p>
      <div class="construction-progress" aria-hidden="true"><span></span></div>
    </section>
  </main>
</section>

<section id="categoryPage" class="page">
  <main class="shell">
    <button class="back-button button-with-icon" onclick="showPage('homePage')" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="page-head"><h1>موضوع گزارش</h1></div>
    <div class="category-grid">
      <button class="category-card" onclick="chooseCategory('افراد')" type="button">
        <?= buttonIcon('community', 'category-icon') ?>
        <strong>افراد</strong>
      </button>
      <button class="category-card" onclick="chooseCategory('رویداد')" type="button">
        <?= buttonIcon('event', 'category-icon') ?>
        <strong>رویداد</strong>
      </button>
      <button class="category-card" onclick="chooseCategory('املاک')" type="button">
        <?= buttonIcon('properties', 'category-icon') ?>
        <strong>املاک</strong>
      </button>
      <button class="category-card" onclick="chooseCategory('اشیاء')" type="button">
        <?= buttonIcon('objects', 'category-icon') ?>
        <strong>اشیاء</strong>
      </button>
    </div>
  </main>
</section>

<section id="objectTypePage" class="page">
  <main class="shell">
    <button class="back-button button-with-icon" onclick="showPage('categoryPage')" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="page-head"><h1>نوع اشیاء</h1></div>
    <div class="option-grid">
      <button class="option-card" onclick="chooseSubtype('بسته مشکوک')" type="button"><?= buttonIcon('package', 'subtype-svg') ?><strong>بسته مشکوک</strong></button>
      <button class="option-card" onclick="chooseSubtype('خودرو مشکوک')" type="button"><?= buttonIcon('car', 'subtype-svg') ?><strong>خودرو مشکوک</strong></button>
      <button class="option-card" onclick="chooseSubtype('پرنده')" type="button"><?= buttonIcon('drone', 'subtype-svg') ?><strong>انواع پرنده</strong></button>
      <button class="option-card" onclick="chooseSubtype('آنتن استارلینک')" type="button"><?= buttonIcon('satellite', 'subtype-svg') ?><strong>آنتن استارلینک</strong></button>
    </div>
  </main>
</section>

<section id="phenomenonTypePage" class="page">
  <main class="shell">
    <button class="back-button button-with-icon" onclick="showPage('categoryPage')" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="page-head"><h1>نوع رویداد</h1></div>
    <div class="option-grid">
      <button class="option-card" onclick="chooseSubtype('تجمع، تحصن یا اغتشاش')" type="button"><?= buttonIcon('crowd', 'subtype-svg') ?><strong>تجمع، تحصن یا اغتشاش</strong></button>
      <button class="option-card" onclick="chooseSubtype('انفجار یا آتش‌سوزی')" type="button"><?= buttonIcon('fire', 'subtype-svg') ?><strong>انفجار یا آتش‌سوزی</strong></button>
    </div>
  </main>
</section>

<section id="formPage" class="page">
  <main class="shell form-shell">
    <button class="back-button button-with-icon" onclick="backFromForm()" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="stepper report-stepper" data-report-roadmap data-standard-step="0" data-people-step="0" data-property-step="2" aria-label="مراحل ثبت گزارش"></div>
    <div class="page-head"><h1 id="formTitle">ثبت گزارش</h1></div>
    <div id="formBody"></div>
    <div id="formActions" class="form-actions"></div>
  </main>
</section>

<section id="timePage" class="page">
  <main class="shell">
    <button class="back-button button-with-icon" onclick="backFromTime()" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="stepper report-stepper" data-report-roadmap data-standard-step="1" data-people-step="1" data-property-step="1" aria-label="مراحل ثبت گزارش"></div>
    <div class="page-head"><h1>تعیین زمان وقوع</h1></div>
    <div class="choice-row time-choices">
      <button class="choice-btn button-with-icon" data-time="اکنون" onclick="setTimeMode(this,'اکنون')" type="button"><?= buttonIcon('clock-now') ?><span>اکنون</span></button>
      <button class="choice-btn button-with-icon" data-time="دقیق" onclick="setTimeMode(this,'دقیق')" type="button"><?= buttonIcon('calendar-clock') ?><span>تعیین زمان دقیق</span></button>
      <button class="choice-btn button-with-icon" data-time="تقریبی" onclick="setTimeMode(this,'تقریبی')" type="button"><?= buttonIcon('clock') ?><span>تعیین زمان تقریبی</span></button>
      <button class="choice-btn button-with-icon" data-time="نامشخص" onclick="setTimeMode(this,'نامشخص')" type="button"><?= buttonIcon('clock-off') ?><span>زمان را نمی‌دانم</span></button>
    </div>
    <div id="exactTime" class="conditional-fields" hidden>
      <div class="date-row">
        <div><label>روز</label><input id="dateDay" class="field-input numeric" inputmode="numeric" maxlength="۲" autocomplete="off"></div>
        <div><label>ماه</label><input id="dateMonth" class="field-input numeric" inputmode="numeric" maxlength="۲" autocomplete="off"></div>
        <div><label>سال</label><input id="dateYear" class="field-input numeric" inputmode="numeric" maxlength="۴" autocomplete="off"></div>
      </div>
      <div class="field-group"><label>ساعت</label><input id="timeClock" class="field-input numeric" inputmode="numeric" maxlength="۵" autocomplete="off"></div>
    </div>
    <div id="approxTime" class="conditional-fields" hidden>
      <div class="field-group"><label>تعیین زمان تقریبی</label><input id="approxText" class="field-input" type="text"></div>
    </div>
    <button class="primary-button page-action" onclick="continueTime()" type="button">تایید زمان وقوع</button>
  </main>
</section>

<section id="locationPage" class="page">
  <main class="shell">
    <button class="back-button button-with-icon" onclick="backFromLocation()" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="stepper report-stepper" data-report-roadmap data-standard-step="2" data-people-step="2" data-property-step="0" aria-label="مراحل ثبت گزارش"></div>
    <div class="page-head"><h1>مکان وقوع</h1></div>
    <div class="location-actions" role="group" aria-label="روش تعیین مکان وقوع">
      <button class="choice-btn location-choice" data-location-mode="current" aria-pressed="false" onclick="selectLocationMode('current',this)" type="button"><?= buttonIcon('target', 'mini-svg') ?><span>موقعیت فعلی</span></button>
      <button class="choice-btn location-choice" data-location-mode="map" aria-pressed="false" onclick="selectLocationMode('map',this)" type="button"><?= buttonIcon('map', 'mini-svg') ?><span>انتخاب روی نقشه</span></button>
      <button class="choice-btn location-choice" data-location-mode="unknown" aria-pressed="false" onclick="selectLocationMode('unknown',this)" type="button"><?= buttonIcon('pin-off', 'mini-svg') ?><span>مکان را نمی‌دانم</span></button>
    </div>
    <div id="mapWrap" class="map-wrap"><div id="reportMap"></div></div>
    <div id="locationFields" class="location-fields"></div>
    <div id="locationStatus" class="status" aria-live="polite"></div>
    <button id="locationContinueButton" class="primary-button page-action" onclick="continueLocation()" type="button">تایید مکان وقوع</button>
  </main>
</section>

<section id="propertyOwnersPage" class="page">
  <main class="shell form-shell">
    <button class="back-button button-with-icon" onclick="backFromPropertyOwners()" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="stepper report-stepper" data-report-roadmap data-standard-step="2" data-people-step="2" data-property-step="0" aria-label="مراحل ثبت گزارش"></div>
    <div class="form-section-progress property-section-progress" aria-label="بخش ۲ از ۶">
      <span>بخش ۲ از ۶</span>
      <div class="form-section-progress-track" aria-hidden="true"><span style="--form-progress:33.3333%"></span></div>
    </div>
    <section class="property-location-stage" aria-label="مشخصات مالکین">
      <header class="property-location-stage-heading">
        <h2 class="property-location-stage-title">مشخصات و محل ملک</h2>
        <p>مشخصات افراد مرتبط با ملک را در بسته‌های جداگانه وارد کنید.</p>
      </header>
      <div id="propertyOwnersBody" class="property-location-stage-body"></div>
    </section>
    <button class="primary-button page-action" onclick="continuePropertyOwners()" type="button">مرحله بعد</button>
  </main>
</section>

<section id="propertyResidentsPage" class="page">
  <main class="shell form-shell">
    <button class="back-button button-with-icon" onclick="backFromPropertyResidents()" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="stepper report-stepper" data-report-roadmap data-standard-step="2" data-people-step="2" data-property-step="0" aria-label="مراحل ثبت گزارش"></div>
    <div class="form-section-progress property-section-progress" aria-label="بخش ۳ از ۶">
      <span>بخش ۳ از ۶</span>
      <div class="form-section-progress-track" aria-hidden="true"><span style="--form-progress:50%"></span></div>
    </div>
    <section class="property-location-stage" aria-label="مشخصات ساکنین">
      <header class="property-location-stage-heading">
        <h2 class="property-location-stage-title">مشخصات و محل ملک</h2>
        <p>مشخصات افراد مرتبط با ملک را در بسته‌های جداگانه وارد کنید.</p>
      </header>
      <div id="propertyResidentsBody" class="property-location-stage-body"></div>
    </section>
    <button class="primary-button page-action" onclick="continuePropertyResidents()" type="button">مرحله بعد</button>
  </main>
</section>

<section id="propertyVisitorsPage" class="page">
  <main class="shell form-shell">
    <button class="back-button button-with-icon" onclick="backFromPropertyVisitors()" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="stepper report-stepper" data-report-roadmap data-standard-step="2" data-people-step="2" data-property-step="0" aria-label="مراحل ثبت گزارش"></div>
    <div class="form-section-progress property-section-progress" aria-label="بخش ۴ از ۶">
      <span>بخش ۴ از ۶</span>
      <div class="form-section-progress-track" aria-hidden="true"><span style="--form-progress:66.6667%"></span></div>
    </div>
    <section class="property-location-stage" aria-label="مشخصات ترددکنندگان">
      <header class="property-location-stage-heading">
        <h2 class="property-location-stage-title">مشخصات و محل ملک</h2>
        <p>مشخصات افراد مرتبط با ملک را در بسته‌های جداگانه وارد کنید.</p>
      </header>
      <div id="propertyVisitorsBody" class="property-location-stage-body"></div>
    </section>
    <button class="primary-button page-action" onclick="continuePropertyVisitors()" type="button">مرحله بعد</button>
  </main>
</section>

<section id="propertyVehiclesPage" class="page">
  <main class="shell form-shell">
    <button class="back-button button-with-icon" onclick="backFromPropertyVehicles()" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="stepper report-stepper" data-report-roadmap data-standard-step="2" data-people-step="2" data-property-step="0" aria-label="مراحل ثبت گزارش"></div>
    <div class="form-section-progress property-section-progress" aria-label="بخش ۵ از ۶">
      <span>بخش ۵ از ۶</span>
      <div class="form-section-progress-track" aria-hidden="true"><span style="--form-progress:83.3333%"></span></div>
    </div>
    <section class="property-location-stage" aria-labelledby="propertyVehiclesStageTitle">
      <header class="property-location-stage-heading">
        <span class="property-location-stage-title property-location-stage-title--eyebrow">مشخصات و محل ملک</span>
        <h2 id="propertyVehiclesStageTitle">اطلاعات خودرو یا موتورسیکلت</h2>
        <p>برای هر خودرو یا موتورسیکلت، نوع، رنگ، پلاک و ویژگی‌های قابل مشاهده را ثبت کنید.</p>
      </header>
      <div id="propertyVehiclesBody" class="property-location-stage-body"></div>
    </section>
    <button class="primary-button page-action" onclick="continuePropertyVehicles()" type="button">مرحله بعد</button>
  </main>
</section>

<section id="propertySecurityPage" class="page">
  <main class="shell form-shell">
    <button class="back-button button-with-icon" onclick="backFromPropertySecurity()" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="stepper report-stepper" data-report-roadmap data-standard-step="2" data-people-step="2" data-property-step="0" aria-label="مراحل ثبت گزارش"></div>
    <div class="form-section-progress property-section-progress" aria-label="بخش ۶ از ۶">
      <span>بخش ۶ از ۶</span>
      <div class="form-section-progress-track" aria-hidden="true"><span style="--form-progress:100%"></span></div>
    </div>
    <section class="property-location-stage" aria-labelledby="propertySecurityStageTitle">
      <header class="property-location-stage-heading">
        <span class="property-location-stage-title property-location-stage-title--eyebrow">مشخصات و محل ملک</span>
        <h2 id="propertySecurityStageTitle">اقدامات حفاظتی ملک</h2>
        <p>وضعیت حفاظت و مراقبتی ملک را شرح دهید.</p>
      </header>
      <div id="propertySecurityBody" class="property-location-stage-body"></div>
    </section>
    <button class="primary-button page-action" onclick="continuePropertySecurity()" type="button">تایید مکان وقوع</button>
  </main>
</section>

<section id="incidentReportPage" class="page">
  <main class="shell incident-report-shell">
    <button class="back-button button-with-icon" onclick="backFromIncidentReport()" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="stepper report-stepper" data-report-roadmap data-standard-step="3" data-people-step="3" data-property-step="2" aria-label="مراحل ثبت گزارش"></div>
    <div class="page-head"><h1>گزارش وقوع</h1></div>
    <div id="incidentReportBody"></div>
    <button class="primary-button page-action" onclick="continueIncidentReport()" type="button">تایید گزارش وقوع</button>
  </main>
</section>

<section id="documentsPage" class="page">
  <main class="shell">
    <button class="back-button button-with-icon" onclick="backFromDocuments()" type="button"><?= buttonIcon('arrow-previous', 'button-icon back-icon') ?><span>بازگشت</span></button>
    <div class="stepper report-stepper" data-report-roadmap data-standard-step="4" data-people-step="4" data-property-step="3" aria-label="مراحل ثبت گزارش"></div>
    <div class="page-head"><h1 id="documentsPageTitle">مستندات گزارش</h1></div>
    <section class="document-upload-field" data-document-upload-field aria-labelledby="documentUploadTitle">
      <h2 id="documentUploadTitle" class="document-upload-label">بارگذاری مستندات</h2>
      <p class="document-upload-description">یک یا چند تصویر، PDF یا فایل دیگر را هم‌زمان انتخاب کنید.</p>
      <div class="document-upload-control">
        <button id="documentUploadTrigger" class="document-upload-trigger button-with-icon" type="button" onclick="openDocumentPicker()" aria-controls="documentInput" aria-label="انتخاب یک یا چند فایل مستند">
          <?= buttonIcon('document-upload', 'document-upload-trigger-icon') ?><span>بارگذاری مستندات</span>
        </button>
        <div class="document-upload-entry" aria-live="polite">
          <span class="document-upload-prefix">مستندات</span>
          <span id="documentUploadSummary" class="document-upload-summary">۰ از ۱۰ فایل · ۰ کیلوبایت از ۱۰۰ مگابایت</span>
        </div>
      </div>
      <p id="documentUploadHelp" class="document-upload-helper">مجموع حجم همه فایل‌های این بخش حداکثر ۱۰۰ مگابایت است.</p>
      <button id="documentAddButton" class="document-add-button button-with-icon" type="button" onclick="openDocumentPicker()"><?= buttonIcon('plus', 'document-add-icon') ?><span>افزودن مستندات دیگر</span></button>
    </section>
    <input id="documentInput" type="file" multiple hidden aria-describedby="documentUploadHelp documentUploadSummary" onchange="addDocuments(this)">
    <div id="documentList" class="document-list" aria-live="polite"></div>
    <div id="documentTransferStatus" class="document-transfer-status" hidden aria-live="polite"></div>
    <button class="primary-button" onclick="sendReport()" type="button">تایید و ثبت نهایی</button>
    <div id="successBox" class="success-box" aria-live="polite"></div>
  </main>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
