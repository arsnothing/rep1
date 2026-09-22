const state = {
  category: '',
  subtype: '',
  form: {},
  formStep: 0,
  location: {},
  time: {},
  documents: []
};

let reportMap = null;
let marker = null;
let locationRequestSequence = 0;

const forms = {};

const CATEGORY_ALIASES = Object.freeze({
  'فرد': 'افراد',
  'ملک': 'املاک',
  'شیء': 'اشیاء',
  'پدیده اجتماعی': 'رویداد'
});

function canonicalCategory(category) {
  return CATEGORY_ALIASES[category] || category;
}

const STANDARD_REPORT_STEPS = Object.freeze(['فرم', 'زمان', 'مکان', 'گزارش وقوع', 'مستندات']);
const PEOPLE_REPORT_STEPS = Object.freeze([
  'اطلاعات شناسایی',
  'زمان وقوع',
  'مکان وقوع',
  'گزارش وقوع',
  'مستندات'
]);
const PROPERTY_REPORT_STEPS = Object.freeze([
  'مکان وقوع',
  'زمان وقوع',
  'گزارش وقوع',
  'مستندات'
]);

const DRAFT_STORAGE_KEY = 'faraja-report-draft-v1';
const DRAFT_VERSION = 2;
const REPORT_CATEGORIES = new Set(['افراد', 'املاک', 'اشیاء', 'رویداد']);
const RESTORABLE_PAGE_IDS = new Set([
  'homePage',
  'locationRegistrationPage',
  'categoryPage',
  'objectTypePage',
  'phenomenonTypePage',
  'formPage',
  'timePage',
  'locationPage',
  'propertyOwnersPage',
  'propertyResidentsPage',
  'propertyVisitorsPage',
  'propertyVehiclesPage',
  'propertySecurityPage',
  'incidentReportPage',
  'documentsPage'
]);
const RETIRED_INCIDENT_FIELD_KEYS = Object.freeze(['crimeMethod', 'crimePlace', 'crimeDate']);
const RETIRED_PROPERTY_FIELD_KEYS = Object.freeze(['propertyAddress', 'owners', 'activity']);

function clearRetiredIncidentFields(form = state.form) {
  if (!isPlainRecord(form)) return;
  RETIRED_INCIDENT_FIELD_KEYS.forEach(key => delete form[key]);
}

function clearRetiredPropertyFields(form = state.form) {
  if (!isPlainRecord(form)) return;
  // The previous property release used one flat profile per role. Move any of
  // those values into the new repeatable packages before retiring the old keys.
  normalizePropertyPeople(form);
  RETIRED_PROPERTY_FIELD_KEYS.forEach(key => delete form[key]);
  // Earlier property drafts stored vehicle details as a plain text field. The new
  // property vehicle page uses the same structured collection as the people report.
  if (form.vehicles !== undefined && !Array.isArray(form.vehicles)) delete form.vehicles;
}

let timeDraft = null;
let isRestoringDraft = false;
let hasSubmittedReport = false;

const SOCIAL_PLATFORMS = Object.freeze([
  { id: 'telegram', name: 'تلگرام', prefix: 't.me/', aliases: ['t.me', 'telegram.me', 'telegram.com'], search: 'تلگرام telegram' },
  { id: 'instagram', name: 'اینستاگرام', prefix: 'instagram.com/', aliases: ['instagram.com'], search: 'اینستاگرام instagram' },
  { id: 'x', name: 'ایکس', prefix: 'x.com/', aliases: ['x.com', 'twitter.com'], search: 'ایکس x twitter توییتر' },
  { id: 'whatsapp', name: 'واتس‌اپ', prefix: 'wa.me/', aliases: ['wa.me', 'whatsapp.com'], search: 'واتس اپ whatsapp' },
  { id: 'youtube', name: 'یوتیوب', prefix: 'youtube.com/', aliases: ['youtube.com', 'youtu.be'], search: 'یوتیوب youtube' },
  { id: 'facebook', name: 'فیسبوک', prefix: 'facebook.com/', aliases: ['facebook.com', 'fb.com'], search: 'فیسبوک facebook fb' },
  { id: 'linkedin', name: 'لینکدین', prefix: 'linkedin.com/', aliases: ['linkedin.com'], search: 'لینکدین linkedin' },
  { id: 'github', name: 'گیت‌هاب', prefix: 'github.com/', aliases: ['github.com'], search: 'گیت هاب github' },
  { id: 'tiktok', name: 'تیک‌تاک', prefix: 'tiktok.com/@', aliases: ['tiktok.com'], search: 'تیک تاک tiktok' },
  { id: 'threads', name: 'تردز', prefix: 'threads.net/@', aliases: ['threads.net'], search: 'تردز threads' },
  { id: 'discord', name: 'دیسکورد', prefix: 'discord.gg/', aliases: ['discord.gg', 'discord.com'], search: 'دیسکورد discord' },
  { id: 'eitaa', name: 'ایتا', prefix: 'eitaa.com/', aliases: ['eitaa.com'], search: 'ایتا eitaa' },
  { id: 'bale', name: 'بله', prefix: 'ble.ir/', aliases: ['ble.ir', 'bale.ai'], search: 'بله bale ble' },
  { id: 'soroush', name: 'سروش‌پلاس', prefix: 'splus.ir/', aliases: ['splus.ir', 'soroushplus.ir'], search: 'سروش پلاس soroush splus' },
  { id: 'rubika', name: 'روبیکا', prefix: 'rubika.ir/', aliases: ['rubika.ir'], search: 'روبیکا rubika' },
  { id: 'igap', name: 'آی‌گپ', prefix: 'igap.net/', aliases: ['igap.net'], search: 'ای گپ آی گپ igap' },
  { id: 'gap', name: 'گپ', prefix: 'gap.im/', aliases: ['gap.im'], search: 'گپ gap' },
  { id: 'virasty', name: 'ویراستی', prefix: 'virasty.com/', aliases: ['virasty.com'], search: 'ویراستی virasty' },
  { id: 'aparat', name: 'آپارات', prefix: 'aparat.com/', aliases: ['aparat.com'], search: 'آپارات aparat' }
]);
const SOCIAL_PLATFORM_BY_ID = new Map(SOCIAL_PLATFORMS.map(platform => [platform.id, platform]));

const SOCIAL_LOGO_FILES = Object.freeze({
  telegram: 'telegram.svg',
  instagram: 'instagram.svg',
  x: 'x.svg',
  whatsapp: 'whatsapp.svg',
  youtube: 'youtube.svg',
  facebook: 'facebook.svg',
  linkedin: 'linkedin.svg',
  github: 'github.svg',
  tiktok: 'tiktok.svg',
  threads: 'threads.svg',
  discord: 'discord.svg',
  eitaa: 'eitaa.svg',
  bale: 'bale.svg',
  soroush: 'soroush.png',
  rubika: 'rubika.png',
  igap: 'igap.png',
  gap: 'gap.png',
  virasty: 'virasty.png',
  aparat: 'aparat.svg'
});

let socialLinkRowSequence = 0;
let roadmapTransitionSequence = 0;

function activateRoadmapsAfterPaint(sequence) {
  const activate = () => {
    // Ignore an animation queued for a page that the user has already left.
    if (sequence !== roadmapTransitionSequence) return;
    document.querySelectorAll('[data-report-roadmap]').forEach(stepper => stepper.classList.add('roadmap-ready'));
  };

  const prefersReducedMotion = typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) return activate();

  // Two frames give every newly visible page one rendered neutral state before the
  // ready state is applied. A synchronous remove/add only animated intermittently
  // after the first route change in mobile WebViews.
  const nextFrame = callback => typeof window.requestAnimationFrame === 'function'
    ? window.requestAnimationFrame(callback)
    : setTimeout(callback, 16);
  nextFrame(() => nextFrame(activate));
}

function renderReportRoadmaps() {
  const isPeopleReport = state.category === 'افراد';
  const isPropertyReport = state.category === 'املاک';
  // همهٔ مسیرها (افراد، املاک، رویداد، اشیاء) از انیمیشن و ترنزیشن رودمپ بخش افراد استفاده می‌کنند.
  const usesDetailedRoadmapPresentation = true;
  const steps = isPeopleReport
    ? PEOPLE_REPORT_STEPS
    : isPropertyReport
      ? PROPERTY_REPORT_STEPS
      : STANDARD_REPORT_STEPS;
  const stepAttribute = isPeopleReport
    ? 'peopleStep'
    : isPropertyReport
      ? 'propertyStep'
      : 'standardStep';
  const signature = steps.join('|');
  const sequence = ++roadmapTransitionSequence;

  document.querySelectorAll('[data-report-roadmap]').forEach(stepper => {
    const activeStep = Number(stepper.dataset[stepAttribute]);
    // Every report category reuses the exact people-roadmap component. Labels and
    // the active index remain category-specific.
    stepper.classList.toggle('report-stepper--people', usesDetailedRoadmapPresentation);
    stepper.classList.remove('report-stepper--property');
    stepper.classList.toggle('report-stepper--standard', !usesDetailedRoadmapPresentation);
    stepper.classList.remove('roadmap-ready');

    if (stepper.dataset.roadmapSteps !== signature) {
      stepper.innerHTML = steps.map((label, index) => {
        const connector = index < steps.length - 1 ? '<i aria-hidden="true"></i>' : '';
        return `<span>${label}</span>${connector}`;
      }).join('');
      stepper.dataset.roadmapSteps = signature;
    }

    stepper.querySelectorAll('span').forEach((item, index) => {
      const isDone = index < activeStep;
      const isActive = index === activeStep;
      item.classList.toggle('done', isDone);
      item.classList.toggle('active', isActive);
      if (isActive) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
    });
    stepper.querySelectorAll('i').forEach((connector, index) => {
      connector.classList.toggle('done', index < activeStep);
    });

    // Flush the neutral state before scheduling the ready state for the next paint.
    void stepper.offsetWidth;
  });

  activateRoadmapsAfterPaint(sequence);
}

function revealActiveRoadmap(page) {
  if (!page || typeof page.querySelector !== 'function') return;
  const roadmap = page.querySelector('[data-report-roadmap]');
  if (!roadmap || roadmap.scrollWidth <= roadmap.clientWidth) return;
  const activeStep = roadmap.querySelector('span.active');
  if (!activeStep || typeof activeStep.scrollIntoView !== 'function') return;

  const reduceMotion = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  activeStep.scrollIntoView({ block: 'nearest', inline: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
}

function iconMarkup(name, className = 'button-icon') {
  return `<svg xmlns="http://www.w3.org/2000/svg" class="${className}" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#icon-${name}"></use></svg>`;
}

const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
const EN_DIGITS = '0123456789';
const AR_DIGITS = '٠١٢٣٤٥٦٧٨٩';

function faDigits(value) {
  return String(value ?? '')
    .replace(/[0-9]/g, digit => FA_DIGITS[Number(digit)])
    .replace(/[٠-٩]/g, digit => FA_DIGITS[AR_DIGITS.indexOf(digit)]);
}

function enDigits(value) {
  return String(value ?? '')
    .replace(/[۰-۹]/g, digit => EN_DIGITS[FA_DIGITS.indexOf(digit)])
    .replace(/[٠-٩]/g, digit => EN_DIGITS[AR_DIGITS.indexOf(digit)]);
}

function isPlainRecord(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function readFieldValue(id) {
  const element = document.getElementById(id);
  return element && typeof element.value === 'string' ? element.value : '';
}

function captureTimeDraft() {
  timeDraft = {
    day: readFieldValue('dateDay'),
    month: readFieldValue('dateMonth'),
    year: readFieldValue('dateYear'),
    clock: readFieldValue('timeClock'),
    approximate: readFieldValue('approxText')
  };
}

const LOCATION_DETAIL_FIELD_IDS = Object.freeze({
  postalCode: 'postalCode',
  buildingPlaque: 'buildingPlaque',
  floor: 'floor',
  unit: 'unit'
});

function clearLocationDetailFields(location = state.location) {
  if (!isPlainRecord(location)) return;
  // "address" belonged to the former one-box design and must not accompany the
  // explicit postal-code / plaque / floor / unit fields in a draft or payload.
  delete location.address;
  Object.keys(LOCATION_DETAIL_FIELD_IDS).forEach(key => delete location[key]);
}

function normalizeLocationDetailFields(location = state.location) {
  if (!isPlainRecord(location)) return;
  delete location.address;
  Object.keys(LOCATION_DETAIL_FIELD_IDS).forEach(key => {
    if (typeof location[key] === 'string') {
      const value = location[key].trim();
      if (value) location[key] = value;
      else delete location[key];
    } else if (location[key] !== undefined) {
      delete location[key];
    }
  });
}

function captureLocationDraft() {
  const location = { ...state.location };
  const province = document.getElementById('province');
  const city = document.getElementById('city');

  if (province && province.value.trim()) location.province = province.value.trim();
  else delete location.province;
  if (city && city.value.trim()) location.city = city.value.trim();
  else delete location.city;

  clearLocationDetailFields(location);
  Object.entries(LOCATION_DETAIL_FIELD_IDS).forEach(([key, id]) => {
    const input = document.getElementById(id);
    const value = input && typeof input.value === 'string' ? input.value.trim() : '';
    if (value) location[key] = value;
  });
  state.location = location;
}

function getDraftStorage() {
  try {
    return typeof window !== 'undefined' && window.localStorage ? window.localStorage : null;
  } catch (error) {
    return null;
  }
}

function activePageId() {
  const activePage = document.querySelector('.page.active');
  return activePage && RESTORABLE_PAGE_IDS.has(activePage.id) ? activePage.id : 'homePage';
}

function capturePageDraft(pageId) {
  if (pageId === 'formPage') collectForm();
  if (pageId === 'incidentReportPage') collectIncidentReport();
  if (pageId === 'timePage') captureTimeDraft();
  if (pageId === 'locationPage') captureLocationDraft();
  const propertyPersonRole = propertyPersonRoleForPage(pageId);
  if (propertyPersonRole) collectPropertyPeopleDetails(propertyPersonRole);
  if (pageId === 'propertyVehiclesPage') collectPropertyVehicleDetails();
  if (pageId === 'propertySecurityPage') collectPropertySecurityDetails();
}

function persistReportDraft(pageId = activePageId()) {
  if (isRestoringDraft || hasSubmittedReport) return;
  const storage = getDraftStorage();
  if (!storage) return;

  const savedPage = RESTORABLE_PAGE_IDS.has(pageId) ? pageId : 'homePage';
  capturePageDraft(savedPage);

  const snapshot = {
    version: DRAFT_VERSION,
    page: savedPage,
    state: {
      category: state.category,
      subtype: state.subtype,
      form: state.form,
      formStep: state.formStep,
      location: state.location,
      time: state.time
    },
    timeDraft
  };

  try {
    // فایل‌های انتخاب‌شده به‌علت محدودیت ظرفیت localStorage ذخیره نمی‌شوند.
    storage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(snapshot));
  } catch (error) {
    // ذخیره‌سازی مرورگر ممکن است در حالت خصوصی یا فضای پرشده در دسترس نباشد.
  }
}

function clearReportDraft() {
  const storage = getDraftStorage();
  if (!storage) return;
  try {
    storage.removeItem(DRAFT_STORAGE_KEY);
  } catch (error) {
    // نبودن دسترسی به localStorage نباید روند گزارش را متوقف کند.
  }
}

function restoredTimeDraft(value) {
  if (!isPlainRecord(value)) return null;
  return {
    day: typeof value.day === 'string' ? value.day : '',
    month: typeof value.month === 'string' ? value.month : '',
    year: typeof value.year === 'string' ? value.year : '',
    clock: typeof value.clock === 'string' ? value.clock : '',
    approximate: typeof value.approximate === 'string' ? value.approximate : ''
  };
}

function restoreReportDraft() {
  const storage = getDraftStorage();
  if (!storage) return false;

  let snapshot;
  try {
    const raw = storage.getItem(DRAFT_STORAGE_KEY);
    if (!raw) return false;
    snapshot = JSON.parse(raw);
  } catch (error) {
    clearReportDraft();
    return false;
  }

  if (!isPlainRecord(snapshot) || snapshot.version !== DRAFT_VERSION || !isPlainRecord(snapshot.state)) {
    clearReportDraft();
    return false;
  }

  const savedState = snapshot.state;
  const category = canonicalCategory(typeof savedState.category === 'string' ? savedState.category : '');
  state.category = REPORT_CATEGORIES.has(category) ? category : '';
  state.subtype = typeof savedState.subtype === 'string' ? savedState.subtype : '';
  const savedForm = isPlainRecord(savedState.form) ? savedState.form : {};
  const savedFormStep = Number.isInteger(savedState.formStep) && savedState.formStep >= 0 ? savedState.formStep : 0;
  // The prior property flow stored its three location-related pages as form
  // sections. Detect that layout before normalizing its flat person fields.
  const restoresPreviousPropertyLayout = state.category === 'املاک'
    && (hasLegacyPropertyPersonFields(savedForm) || (!isPlainRecord(savedForm.propertyPeople) && savedFormStep > 0));
  state.form = { ...savedForm };
  clearRetiredIncidentFields(state.form);
  if (state.category === 'املاک') clearRetiredPropertyFields(state.form);
  if (state.category === 'افراد') normalizeLandlinePhoneState(state.form);
  state.formStep = state.category === 'املاک' ? 0 : savedFormStep;
  state.location = isPlainRecord(savedState.location) ? { ...savedState.location } : {};
  normalizeLocationDetailFields(state.location);
  normalizeLocationForCategory();
  state.time = isPlainRecord(savedState.time) ? { ...savedState.time } : {};
  state.documents = [];
  timeDraft = restoredTimeDraft(snapshot.timeDraft);

  let pageId = typeof snapshot.page === 'string' && RESTORABLE_PAGE_IDS.has(snapshot.page) ? snapshot.page : 'homePage';
  const needsCategory = new Set(['objectTypePage', 'phenomenonTypePage', 'formPage', 'timePage', 'locationPage', 'propertyOwnersPage', 'propertyResidentsPage', 'propertyVisitorsPage', 'propertyVehiclesPage', 'propertySecurityPage', 'incidentReportPage', 'documentsPage']);
  if (needsCategory.has(pageId) && !state.category) pageId = 'categoryPage';
  if (pageId === 'objectTypePage' && state.category !== 'اشیاء') pageId = 'categoryPage';
  if (pageId === 'phenomenonTypePage' && state.category !== 'رویداد') pageId = 'categoryPage';
  if (PROPERTY_PERSON_PAGE_IDS.has(pageId) && state.category !== 'املاک') pageId = 'categoryPage';
  if (['propertyVehiclesPage', 'propertySecurityPage'].includes(pageId) && state.category !== 'املاک') pageId = 'categoryPage';
  if (restoresPreviousPropertyLayout && pageId === 'formPage') {
    pageId = savedFormStep === 0
      ? 'propertyOwnersPage'
      : savedFormStep === 1
        ? 'propertyVehiclesPage'
        : savedFormStep === 2
          ? 'propertySecurityPage'
          : 'formPage';
  }
  if (pageId === 'incidentReportPage' && !state.category) pageId = 'categoryPage';

  isRestoringDraft = true;
  try {
    if (pageId === 'formPage') openForm();
    else if (pageId === 'timePage') openTime();
    else if (pageId === 'locationPage') openLocation();
    else if (pageId === 'propertyOwnersPage') openPropertyOwners();
    else if (pageId === 'propertyResidentsPage') openPropertyResidents();
    else if (pageId === 'propertyVisitorsPage') openPropertyVisitors();
    else if (pageId === 'propertyVehiclesPage') openPropertyVehicles();
    else if (pageId === 'propertySecurityPage') openPropertySecurity();
    else if (pageId === 'incidentReportPage') openIncidentReport();
    else if (pageId === 'documentsPage') openDocuments();
    else showPage(pageId);
  } finally {
    isRestoringDraft = false;
  }

  persistReportDraft(pageId);
  return true;
}

function normalizeFieldValue(element) {
  if (element.type === 'file' || element.dataset.socialLinkValue !== undefined) return;
  let value = element.type === 'email' || element.dataset.preserveLatin === 'true' ? String(element.value) : faDigits(element.value);

  if (element.dataset.numeric === 'true') {
    value = value.replace(/[^۰-۹]/g, '');
    const maxLength = Number(element.getAttribute('maxlength'));
    if (Number.isInteger(maxLength) && maxLength > 0) value = value.slice(0, maxLength);

    const maxValue = Number(element.dataset.maxValue);
    const numberValue = Number(enDigits(value));
    if (Number.isFinite(maxValue) && value && Number.isFinite(numberValue) && numberValue > maxValue) {
      value = faDigits(maxValue);
    }
  }

  if (element.dataset.textOnly === 'true') {
    value = value.replace(/[0-9۰-۹٠-٩]/g, '');
  }

  element.value = value;
}

function resizeTextarea(textarea) {
  if (!textarea.matches('#formBody textarea[data-auto-resize="true"], #incidentReportBody textarea[data-auto-resize="true"], #locationPage textarea[data-auto-resize="true"], #propertyOwnersBody textarea[data-auto-resize="true"], #propertyResidentsBody textarea[data-auto-resize="true"], #propertyVisitorsBody textarea[data-auto-resize="true"], #propertyVehiclesBody textarea[data-auto-resize="true"], #propertySecurityBody textarea[data-auto-resize="true"]')) return;
  const maxHeight = 280;
  textarea.style.height = 'auto';
  const height = Math.min(Math.max(textarea.scrollHeight, 52), maxHeight);
  textarea.style.height = `${height}px`;
  textarea.style.overflowY = textarea.scrollHeight > maxHeight ? 'auto' : 'hidden';
}

function normalizeVisibleNumbers() {
  document.querySelectorAll('body *').forEach(el => {
    const keepsLatin = typeof el.closest === 'function' && el.closest('[data-preserve-latin="true"]');
    if (!keepsLatin && el.children.length === 0 && el.textContent.trim()) {
      el.textContent = faDigits(el.textContent);
    }
  });
  document.querySelectorAll('input, textarea').forEach(el => {
    if (el.value) normalizeFieldValue(el);
    resizeTextarea(el);
  });
}

document.addEventListener('input', event => {
  const target = event.target;
  if (!target || typeof target.matches !== 'function') return;
  if (target.matches('.social-platform-search')) {
    filterSocialPlatforms(target);
    return;
  }
  if (target.matches('.searchable-select-search')) {
    filterSearchableSelect(target);
    return;
  }
  if (target.matches('[data-social-link-value]')) {
    persistReportDraft();
    return;
  }
  if (target.matches('[data-vehicle-plate-input]')) {
    normalizeVehiclePlateInput(target);
    syncVehiclePlatePreview(target);
    persistReportDraft();
    return;
  }
  if (target.matches('[data-landline-subscriber]')) {
    normalizeLandlineSubscriberInput(target);
    persistReportDraft();
    return;
  }
  if (target.matches('input, textarea')) {
    normalizeFieldValue(target);
    resizeTextarea(target);
    persistReportDraft();
  }
});

document.addEventListener('click', event => {
  const target = event.target;
  if (!target || typeof target.closest !== 'function') return;
  if (target.closest('[data-social-links], [data-searchable-select]')) return;
  closeSocialPlatformMenus();
  closeSearchableSelects();
});

document.addEventListener('keydown', event => {
  if (event.key === 'Escape') {
    closeSocialPlatformMenus();
    closeSearchableSelects();
  }
});

if (typeof window.addEventListener === 'function') {
  window.addEventListener('pagehide', () => persistReportDraft());
}

function showPage(id) {
  const page = document.getElementById(id);
  if (!page) return;
  persistReportDraft();
  document.querySelectorAll('.page').forEach(item => item.classList.remove('active'));
  page.classList.add('active');
  renderReportRoadmaps();
  persistReportDraft(id);
  const revealRoadmap = () => revealActiveRoadmap(page);
  if (typeof window.requestAnimationFrame === 'function') window.requestAnimationFrame(revealRoadmap);
  else setTimeout(revealRoadmap, 0);
  window.scrollTo({ top: 0, behavior: 'instant' });
  setTimeout(normalizeVisibleNumbers, 0);
}

function resetReport() {
  state.category = '';
  state.subtype = '';
  state.form = {};
  state.formStep = 0;
  state.location = {};
  state.time = {};
  state.documents = [];
  timeDraft = null;
  hasSubmittedReport = false;
  locationRequestSequence += 1;
  clearLocationMarker();
  if (reportMap && typeof reportMap.remove === 'function') reportMap.remove();
  reportMap = null;
}

function startReport() {
  resetReport();
  clearReportDraft();
  // Do not let showPage capture fields that are still mounted from a previous
  // report while the fresh category page is being opened.
  const wasRestoring = isRestoringDraft;
  isRestoringDraft = true;
  try {
    showPage('categoryPage');
  } finally {
    isRestoringDraft = wasRestoring;
  }
}

function openLocationRegistration() {
  showPage('locationRegistrationPage');
}

function chooseCategory(category) {
  category = canonicalCategory(category);
  if (!REPORT_CATEGORIES.has(category)) return showPage('categoryPage');
  // Selecting a category begins a fresh report. In particular, a map point or
  // unknown-location data from a previously abandoned category must not leak
  // into the property route, where location is its first required stage.
  // showPage normally captures the outgoing page first, so pause that capture
  // while its stale DOM belongs to the report just discarded above.
  const wasRestoring = isRestoringDraft;
  isRestoringDraft = true;
  try {
    state.category = category;
    state.subtype = '';
    state.form = {};
    state.formStep = 0;
    state.location = {};
    state.time = {};
    state.documents = [];
    timeDraft = null;
    locationRequestSequence += 1;
    clearLocationMarker();
    ['propertyOwnersBody', 'propertyResidentsBody', 'propertyVisitorsBody', 'propertyVehiclesBody', 'propertySecurityBody'].forEach(id => {
      const body = document.getElementById(id);
      if (body) body.innerHTML = '';
    });
    if (category === 'اشیاء') return showPage('objectTypePage');
    if (category === 'رویداد') return showPage('phenomenonTypePage');
    if (category === 'املاک') return openLocation();
    return openForm();
  } finally {
    isRestoringDraft = wasRestoring;
    if (!wasRestoring) persistReportDraft();
  }
}

function chooseSubtype(subtype) {
  const wasRestoring = isRestoringDraft;
  isRestoringDraft = true;
  try {
    state.subtype = subtype;
    state.form = {};
    state.formStep = 0;
    return openForm();
  } finally {
    isRestoringDraft = wasRestoring;
    if (!wasRestoring) persistReportDraft();
  }
}

function field(name, label, type = 'text', options = {}) {
  const numeric = options.numeric ? 'numeric' : '';
  const textOnly = options.textOnly ? 'text-only' : '';
  const maxLength = options.maxLength ? ` maxlength="${options.maxLength}"` : '';
  const maxValue = Number.isFinite(options.maxValue) ? ` data-max-value="${options.maxValue}"` : '';
  const placeholder = options.placeholder ? ` placeholder="${options.placeholder}"` : '';
  const validation = options.validation ? ` data-validation="${options.validation}"` : '';
  const numericRule = options.numeric ? ' data-numeric="true"' : '';
  const textRule = options.textOnly ? ' data-text-only="true"' : '';
  const direction = options.ltr ? ' dir="ltr"' : '';
  const preserveLatin = options.preserveLatin ? ' data-preserve-latin="true"' : '';
  const inputMode = options.numeric ? 'numeric' : type === 'email' ? 'email' : 'text';
  const attributes = `data-field="${name}" data-label="${label}"${numericRule}${textRule}${validation}${maxLength}${maxValue}${placeholder}${direction}${preserveLatin}`;

  if (type === 'textarea') {
    return `<div class="field-group"><label>${label}</label><textarea class="field-textarea ${numeric} ${textOnly}" ${attributes} data-auto-resize="true"></textarea></div>`;
  }
  return `<div class="field-group"><label>${label}</label><input class="field-input ${numeric} ${textOnly}" ${attributes} type="${type}" inputmode="${inputMode}"></div>`;
}

function choices(name, label, items, options = {}) {
  const columns = options.columns === 3 ? ' choice-row--three' : '';
  return `<div class="field-group"><label>${label}</label><div class="choice-row${columns}" data-choice="${name}">${items.map(item => `<button type="button" class="choice-btn" onclick="pickChoice(this,'${name}','${item.replace(/'/g, "\\'")}')">${item}</button>`).join('')}</div></div>`;
}

function yesNo(name, label) {
  return choices(name, label, ['بله', 'خیر', 'نامشخص'], { columns: 3 });
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  })[character]);
}

const MAX_DOCUMENTS = 10;
const MAX_DOCUMENT_TOTAL_BYTES = 100 * 1024 * 1024;
const MAX_VEHICLES = 10;
const MAX_PROPERTY_PEOPLE = 10;

// National fixed-line prefixes are province-wide after Iran's co-numbering plan.
const LANDLINE_AREA_CODES = Object.freeze({
  'آذربایجان شرقی': '041',
  'آذربایجان غربی': '044',
  'اردبیل': '045',
  'اصفهان': '031',
  'البرز': '026',
  'ایلام': '084',
  'بوشهر': '077',
  'تهران': '021',
  'چهارمحال و بختیاری': '038',
  'خراسان جنوبی': '056',
  'خراسان رضوی': '051',
  'خراسان شمالی': '058',
  'خوزستان': '061',
  'زنجان': '024',
  'سمنان': '023',
  'سیستان و بلوچستان': '054',
  'فارس': '071',
  'قزوین': '028',
  'قم': '025',
  'کردستان': '087',
  'کرمان': '034',
  'کرمانشاه': '083',
  'کهگیلویه و بویراحمد': '074',
  'گلستان': '017',
  'گیلان': '013',
  'لرستان': '066',
  'مازندران': '011',
  'مرکزی': '086',
  'هرمزگان': '076',
  'همدان': '081',
  'یزد': '035'
});
const LANDLINE_FIELD_CONFIGS = Object.freeze([
  Object.freeze({ field: 'phoneFixed', provinceField: 'phoneFixedProvince', provinceLabel: 'استان محل سکونت', label: 'شماره ثابت محل سکونت' }),
  Object.freeze({ field: 'phoneWork', provinceField: 'phoneWorkProvince', provinceLabel: 'استان محل کار', label: 'شماره ثابت محل کار' })
]);

const VEHICLE_KIND_OPTIONS = Object.freeze([
  { value: 'خودرو', label: 'خودرو', search: 'خودرو سواری وانت کامیون اتوبوس' },
  { value: 'موتورسیکلت', label: 'موتورسیکلت', search: 'موتور موتور سیکلت' }
]);

// Persian labels, colors, letters, and plate structures mirror the active Iranian formats.
// The blue country band is rendered for every format, including motorcycle and transit plates.
const VEHICLE_PLATE_TEMPLATES = Object.freeze([
  { id: 'private', label: 'پلاک شخصی / ملی', search: 'ملی شخصی سواری خودرو ب ج د س ص ط ق ل م ن و ه ی', layout: 'national', tone: 'light', vehicleKinds: ['خودرو'] },
  { id: 'disabled', label: 'معلولین و جانبازان', search: 'معلولین جانبازان ویلچر دسترسی', layout: 'disabled', tone: 'light', vehicleKinds: ['خودرو'] },
  { id: 'taxi', label: 'تاکسی', search: 'تاکسی ت عمومی زرد', layout: 'national', tone: 'yellow', fixedLetter: 'ت', vehicleKinds: ['خودرو'] },
  { id: 'public', label: 'خودروی عمومی', search: 'عمومی ع اتوبوس مینی بوس کامیون زرد', layout: 'national', tone: 'yellow', fixedLetter: 'ع', vehicleKinds: ['خودرو'] },
  { id: 'government', label: 'دولتی', search: 'دولتی اداری الف قرمز', layout: 'national', tone: 'red', fixedLetter: 'الف', vehicleKinds: ['خودرو'] },
  { id: 'police', label: 'نیروی انتظامی', search: 'پلیس انتظامی پ سبز', layout: 'national', tone: 'green', fixedLetter: 'پ', vehicleKinds: ['خودرو'] },
  { id: 'irgc', label: 'سپاه پاسداران', search: 'سپاه پاسداران ث سبز', layout: 'national', tone: 'green', fixedLetter: 'ث', vehicleKinds: ['خودرو'] },
  { id: 'army', label: 'ارتش', search: 'ارتش ش خاکی', layout: 'national', tone: 'khaki', fixedLetter: 'ش', vehicleKinds: ['خودرو'] },
  { id: 'armed-forces', label: 'ستاد کل نیروهای مسلح', search: 'نیروهای مسلح ستاد کل ف آبی', layout: 'national', tone: 'blue', fixedLetter: 'ف', vehicleKinds: ['خودرو'] },
  { id: 'defense', label: 'وزارت دفاع', search: 'دفاع ز آبی', layout: 'national', tone: 'blue', fixedLetter: 'ز', vehicleKinds: ['خودرو'] },
  { id: 'agricultural', label: 'ادوات کشاورزی', search: 'کشاورزی ک ماشین آلات زرد', layout: 'national', tone: 'yellow', fixedLetter: 'ک', vehicleKinds: ['خودرو'] },
  { id: 'temporary-persian', label: 'گذر موقت (گ)', search: 'گذر موقت گ سفید', layout: 'national', tone: 'light', fixedLetter: 'گ', vehicleKinds: ['خودرو'] },
  { id: 'temporary-latin', label: 'گذر موقت جدید (لاتین)', search: 'گذر موقت جدید انگلیسی لاتین temporary transit international', layout: 'latin', tone: 'light', vehicleKinds: ['خودرو'] },
  { id: 'diplomatic', label: 'دیپلماتیک (D)', search: 'دیپلماتیک سفارت انگلیسی D آبی', layout: 'national', tone: 'blue', fixedLetter: 'D', preserveLetter: true, vehicleKinds: ['خودرو'] },
  { id: 'service', label: 'خدمات سفارت (S)', search: 'سرویس خدمت سفارت انگلیسی S آبی', layout: 'national', tone: 'blue', fixedLetter: 'S', preserveLetter: true, vehicleKinds: ['خودرو'] },
  { id: 'protocol', label: 'تشریفات / PROTOCOL', search: 'تشریفات protocol قرمز', layout: 'protocol', tone: 'red', vehicleKinds: ['خودرو'] },
  { id: 'free-zone', label: 'پلاک مناطق آزاد تجاری ـ صنعتی', search: 'منطقه آزاد مناطق آزاد تجاری صنعتی کیش قشم چابهار ارس اروند انزلی ماکو free zone industrial', layout: 'free-zone-industrial', tone: 'light', vehicleKinds: ['خودرو'] },
  { id: 'historical', label: 'خودروی تاریخی', search: 'تاریخی کلاسیک قهوه ای', layout: 'historical', tone: 'brown', vehicleKinds: ['خودرو'] },
  { id: 'motorcycle', label: 'پلاک موتورسیکلت', search: 'موتور موتورسیکلت', layout: 'motorcycle', tone: 'light', vehicleKinds: ['موتورسیکلت'] }
]);

function searchableSelectOptionMarkup(item, selectedValue = '') {
  const value = String(item.value ?? item.id ?? '');
  const label = String(item.label ?? item.name ?? value);
  const search = String(item.search ?? `${label} ${value}`);
  const preview = item.preview ? `<span class="searchable-select-option-preview">${item.preview}</span>` : '';
  const detail = item.detail ? `<span class="searchable-select-option-detail">${escapeHtml(item.detail)}</span>` : '';
  const selected = value === String(selectedValue ?? '');
  return `<button type="button" class="searchable-select-option${item.optionClass ? ` ${item.optionClass}` : ''}" role="option" data-searchable-select-option data-value="${escapeHtml(value)}" data-search="${escapeHtml(search)}" aria-selected="${selected ? 'true' : 'false'}" onclick="selectSearchableSelectOption(this)">${preview}<span class="searchable-select-option-copy"><span class="searchable-select-option-name">${escapeHtml(label)}</span>${detail}</span></button>`;
}

function searchableSelectMarkup(name, label, items, options = {}) {
  const value = String(options.value ?? '');
  const selected = items.find(item => String(item.value ?? item.id ?? '') === value);
  const selectedLabel = selected ? String(selected.label ?? selected.name ?? selected.value ?? selected.id) : (options.placeholder || 'انتخاب کنید');
  const inputId = options.inputId ? ` id="${escapeHtml(options.inputId)}"` : '';
  const fieldName = options.fieldName ? ` data-field="${escapeHtml(options.fieldName)}" data-label="${escapeHtml(label)}"` : '';
  const isDisabled = Boolean(options.disabled);
  const disabled = isDisabled ? ' disabled' : '';
  const menuId = `searchable-select-menu-${name}`;
  const rootClass = `${options.wrap === false ? '' : 'field-group '}searchable-select-field${options.className ? ` ${options.className}` : ''}`;
  const labelMarkup = label ? `<label>${escapeHtml(label)}</label>` : '';

  return `<div class="${rootClass}" data-searchable-select data-select-name="${escapeHtml(name)}" data-placeholder="${escapeHtml(options.placeholder || 'انتخاب کنید')}">
    ${labelMarkup}
    <input type="hidden"${inputId} data-searchable-select-value${fieldName} value="${escapeHtml(value)}">
    <button type="button" class="searchable-select-trigger button-with-icon" aria-haspopup="listbox" aria-controls="${menuId}" aria-expanded="false" onclick="toggleSearchableSelect(this)"${disabled}><span class="searchable-select-trigger-copy" data-searchable-select-label>${escapeHtml(selectedLabel)}</span>${iconMarkup('chevron-down', 'searchable-select-chevron')}</button>
    <div id="${menuId}" class="searchable-select-menu" role="dialog" aria-label="${escapeHtml(label || 'انتخاب گزینه')}">
      <div class="searchable-select-search-wrap">
        ${iconMarkup('search', 'searchable-select-search-icon')}
        <input type="search" class="searchable-select-search" autocomplete="off" placeholder="جست‌وجو" aria-label="جست‌وجو در گزینه‌ها">
      </div>
      <div class="searchable-select-options" role="listbox" aria-label="${escapeHtml(label || 'گزینه‌ها')}">${items.map(item => searchableSelectOptionMarkup(item, value)).join('')}</div>
    </div>
  </div>`;
}

function searchableSelectValue(component) {
  const input = component && component.querySelector('[data-searchable-select-value]');
  return input ? input.value : '';
}

function searchableSelectOptionForValue(component, value) {
  return Array.from(component ? component.querySelectorAll('[data-searchable-select-option]') : [])
    .find(option => option.dataset.value === String(value ?? '')) || null;
}

function updateSearchableSelectPresentation(component) {
  if (!component) return;
  const input = component.querySelector('[data-searchable-select-value]');
  const trigger = component.querySelector('.searchable-select-trigger');
  const label = component.querySelector('[data-searchable-select-label]');
  const value = input ? input.value : '';
  const selected = searchableSelectOptionForValue(component, value);
  const selectedLabel = selected ? selected.querySelector('.searchable-select-option-name') : null;
  if (label) label.textContent = selectedLabel ? selectedLabel.textContent : (component.dataset.placeholder || 'انتخاب کنید');
  component.classList.toggle('has-value', Boolean(selected));
  component.querySelectorAll('[data-searchable-select-option]').forEach(option => {
    const isSelected = option === selected;
    option.classList.toggle('selected', isSelected);
    option.setAttribute('aria-selected', String(isSelected));
  });
  if (trigger && trigger.disabled) component.classList.add('is-disabled');
  else component.classList.remove('is-disabled');
}

function closeSearchableSelects(except = null) {
  document.querySelectorAll('[data-searchable-select].is-picker-open').forEach(component => {
    if (component === except) return;
    component.classList.remove('is-picker-open');
    const trigger = component.querySelector('.searchable-select-trigger');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
  });
}

function toggleSearchableSelect(trigger) {
  const component = trigger.closest('[data-searchable-select]');
  if (!component || trigger.disabled) return;
  const shouldOpen = !component.classList.contains('is-picker-open');
  closeSearchableSelects(component);
  closeSocialPlatformMenus();
  component.classList.toggle('is-picker-open', shouldOpen);
  trigger.setAttribute('aria-expanded', String(shouldOpen));
  if (!shouldOpen) return;
  const search = component.querySelector('.searchable-select-search');
  if (search) {
    search.value = '';
    filterSearchableSelect(search);
    setTimeout(() => search.focus(), 0);
  }
}

function filterSearchableSelect(searchInput) {
  const component = searchInput.closest('[data-searchable-select]');
  if (!component) return;
  const query = normalizedSocialSearch(searchInput.value);
  component.querySelectorAll('[data-searchable-select-option]').forEach(option => {
    option.hidden = Boolean(query) && !normalizedSocialSearch(option.dataset.search).includes(query);
  });
}

function setSearchableSelectItems(component, items, options = {}) {
  if (!component) return;
  const input = component.querySelector('[data-searchable-select-value]');
  const optionsBox = component.querySelector('.searchable-select-options');
  if (!input || !optionsBox) return;
  const nextValue = options.value === undefined ? input.value : String(options.value ?? '');
  input.value = nextValue;
  optionsBox.innerHTML = items.map(item => searchableSelectOptionMarkup(item, nextValue)).join('');
  const trigger = component.querySelector('.searchable-select-trigger');
  if (trigger && options.disabled !== undefined) trigger.disabled = Boolean(options.disabled);
  if (options.placeholder) component.dataset.placeholder = options.placeholder;
  updateSearchableSelectPresentation(component);
}

function selectSearchableSelectOption(option) {
  const component = option.closest('[data-searchable-select]');
  const input = component && component.querySelector('[data-searchable-select-value]');
  if (!component || !input) return;
  input.value = option.dataset.value || '';
  updateSearchableSelectPresentation(component);
  closeSearchableSelects();
  handleSearchableSelectChange(component);
  persistReportDraft();
}

function handleSearchableSelectChange(component) {
  const name = component.dataset.selectName;
  if (name === 'locationProvince') {
    const province = searchableSelectValue(component);
    state.location.province = province;
    state.location.city = '';
    updateLocationCountyPicker(province);
    return;
  }
  if (name && name.startsWith('landlineProvince-')) {
    updateLandlineProvince(component);
    return;
  }
  if (name && name.startsWith('vehicleKind-')) {
    updateVehicleKind(component);
    return;
  }
  if (name && name.startsWith('vehiclePlateTemplate-')) {
    updateVehiclePlateTemplate(component);
  }
}

function setSearchableSelectDisabled(component, disabled, placeholder = 'انتخاب کنید') {
  if (!component) return;
  const trigger = component.querySelector('.searchable-select-trigger');
  if (trigger) trigger.disabled = Boolean(disabled);
  component.dataset.placeholder = placeholder;
  if (disabled) {
    component.classList.remove('is-picker-open');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
  }
  updateSearchableSelectPresentation(component);
}

function landlineFieldConfig(fieldName) {
  return LANDLINE_FIELD_CONFIGS.find(config => config.field === fieldName) || null;
}

function landlineProvinceItems() {
  return Object.entries(LANDLINE_AREA_CODES).map(([province, prefix]) => ({
    value: province,
    label: province,
    detail: `پیش‌شماره ${faDigits(prefix)}`,
    search: `${province} ${prefix} ${faDigits(prefix)}`
  }));
}

function landlineDigits(value) {
  return faDigits(String(value ?? '')).replace(/[^۰-۹]/g, '');
}

function landlineProvinceForNumber(value) {
  const digits = enDigits(landlineDigits(value));
  return Object.keys(LANDLINE_AREA_CODES).find(province => digits.startsWith(LANDLINE_AREA_CODES[province])) || '';
}

function landlineSubscriberFromNumber(value, prefix) {
  const digits = landlineDigits(value);
  const normalizedPrefix = faDigits(prefix || '');
  if (!digits) return '';
  if (normalizedPrefix && digits.startsWith(normalizedPrefix)) return digits.slice(normalizedPrefix.length, normalizedPrefix.length + 8);
  // A pasted complete phone can carry another provincial prefix; preserve its
  // subscriber portion while the selected province supplies the new prefix.
  if (digits.length >= 11) return digits.slice(-8);
  return digits.slice(0, 8);
}

function selectedLandlineProvince(config, form = state.form) {
  if (!config || !isPlainRecord(form)) return '';
  const selected = typeof form[config.provinceField] === 'string' ? form[config.provinceField] : '';
  if (Object.prototype.hasOwnProperty.call(LANDLINE_AREA_CODES, selected)) return selected;
  const inferred = landlineProvinceForNumber(form[config.field]);
  if (inferred) form[config.provinceField] = inferred;
  return inferred;
}

function landlineContactFieldMarkup(fieldName) {
  const config = landlineFieldConfig(fieldName);
  if (!config) return '';
  const province = selectedLandlineProvince(config);
  const prefix = LANDLINE_AREA_CODES[province] || '';
  const subscriber = prefix ? landlineSubscriberFromNumber(state.form[fieldName], prefix) : '';
  const inputId = `landline-${fieldName}`;
  const helpId = `${inputId}-help`;
  return `<section class="landline-contact-field" data-landline-contact data-landline-field="${fieldName}" data-landline-province-field="${config.provinceField}" data-landline-prefix="${prefix}">
    ${searchableSelectMarkup(`landlineProvince-${fieldName}`, config.provinceLabel, landlineProvinceItems(), { value: province, placeholder: 'استان را انتخاب کنید' })}
    <div class="field-group">
      <label for="${inputId}">${config.label}</label>
      <div class="landline-phone-entry">
        <span class="landline-prefix" data-landline-prefix-display aria-label="پیش‌شماره استان">${prefix ? faDigits(prefix) : '—'}</span>
        <input id="${inputId}" class="field-input numeric landline-subscriber-input" type="text" data-landline-subscriber data-label="${config.label}" data-validation="landline" data-numeric="true" maxlength="8" inputmode="numeric" autocomplete="tel-national" aria-describedby="${helpId}" value="${escapeHtml(subscriber)}"${prefix ? '' : ' disabled'}>
      </div>
      <p id="${helpId}" class="landline-help">۸ رقم شماره ثابت را پس از پیش‌شماره وارد کنید.</p>
    </div>
  </section>`;
}

function normalizeLandlineSubscriberInput(input) {
  const container = input && typeof input.closest === 'function' ? input.closest('[data-landline-contact]') : null;
  input.value = landlineSubscriberFromNumber(input.value, container ? container.dataset.landlinePrefix : '');
}

function updateLandlineProvince(component) {
  const container = component && typeof component.closest === 'function' ? component.closest('[data-landline-contact]') : null;
  if (!container) return;
  const config = landlineFieldConfig(container.dataset.landlineField);
  if (!config) return;
  const province = searchableSelectValue(component);
  const prefix = LANDLINE_AREA_CODES[province] || '';
  const input = container.querySelector('[data-landline-subscriber]');
  const prefixDisplay = container.querySelector('[data-landline-prefix-display]');
  container.dataset.landlinePrefix = prefix;
  if (prefixDisplay) prefixDisplay.textContent = prefix ? faDigits(prefix) : '—';
  if (input) {
    input.disabled = !prefix;
    input.value = prefix ? landlineSubscriberFromNumber(input.value, prefix) : '';
  }
  if (prefix) state.form[config.provinceField] = province;
  else delete state.form[config.provinceField];
  if (prefix && input && input.value) state.form[config.field] = `${faDigits(prefix)}${input.value}`;
  else delete state.form[config.field];
}

function collectLandlineContactFields(data, scope = document) {
  if (!scope || typeof scope.querySelectorAll !== 'function') return;
  scope.querySelectorAll('[data-landline-contact]').forEach(container => {
    const config = landlineFieldConfig(container.dataset.landlineField);
    if (!config) return;
    const picker = container.querySelector('[data-searchable-select]');
    const province = searchableSelectValue(picker);
    const prefix = LANDLINE_AREA_CODES[province] || '';
    const input = container.querySelector('[data-landline-subscriber]');
    const subscriber = input && prefix ? landlineSubscriberFromNumber(input.value, prefix) : '';
    if (!prefix) {
      delete data[config.provinceField];
      delete data[config.field];
      return;
    }
    data[config.provinceField] = province;
    if (subscriber) data[config.field] = `${faDigits(prefix)}${subscriber}`;
    else delete data[config.field];
  });
}

function normalizeLandlinePhoneState(form = state.form) {
  if (!isPlainRecord(form)) return;
  LANDLINE_FIELD_CONFIGS.forEach(config => {
    const rawPhone = typeof form[config.field] === 'string' ? form[config.field] : '';
    const phone = landlineDigits(rawPhone);
    if (phone) form[config.field] = phone;
    else delete form[config.field];
    const province = selectedLandlineProvince(config, form);
    if (province) form[config.provinceField] = province;
    else delete form[config.provinceField];
  });
}

function vehiclePlateTemplateFor(templateId) {
  return VEHICLE_PLATE_TEMPLATES.find(template => template.id === templateId) || null;
}

function vehiclePlateTemplatesForKind(kind) {
  if (!VEHICLE_KIND_OPTIONS.some(item => item.value === kind)) return [];
  return VEHICLE_PLATE_TEMPLATES.filter(template => Array.isArray(template.vehicleKinds) && template.vehicleKinds.includes(kind));
}

function iranFlagMarkup() {
  return '<img class="iran-flag" src="assets/iran-flag.png" alt="" aria-hidden="true" draggable="false" decoding="async">';
}

function wheelchairSymbolMarkup() {
  return `<svg class="vehicle-plate-wheelchair-icon" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
    <circle cx="15.2" cy="5.4" r="2.8" fill="currentColor" stroke="none"/>
    <path d="M13.9 10h3l1.9 7.1h5.2M14 10l-3.2 6.1M13.9 14.2h5.5l2.6 6.1"/>
    <path d="M12.9 15.4a7.2 7.2 0 1 0 7.1 8.8"/>
  </svg>`;
}

function iranPlateCountryBandMarkup(options = {}) {
  const freeZoneMark = options.freeZone
    ? '<span class="iran-plate-free-zone-badge">FZ</span><small>FREE ZONE</small>'
    : '';
  return `<span class="iran-plate-country-band${options.freeZone ? ' iran-plate-country-band--free-zone' : ''}" data-preserve-latin="true" aria-hidden="true">${iranFlagMarkup()}<b>I.R. IRAN</b>${freeZoneMark}<em>ایران</em></span>`;
}

function vehiclePlatePreviewMarkup(template, parts = {}, isOption = false) {
  if (!template) return '';
  const values = isPlainRecord(parts) ? parts : {};
  const slot = (name, fallback, options = {}) => {
    const value = typeof values[name] === 'string' && values[name] ? values[name] : fallback;
    const className = options.className ? ` ${options.className}` : '';
    const preserveLatin = options.preserveLatin ? ' data-preserve-latin="true" dir="ltr"' : '';
    return `<span class="vehicle-plate-value${className}" data-plate-preview-part="${name}" data-default-value="${escapeHtml(fallback)}"${preserveLatin}>${escapeHtml(value)}</span>`;
  };
  const className = `vehicle-plate-preview vehicle-plate-preview--${template.tone}${isOption ? ' vehicle-plate-preview--sample' : ''}`;
  const countryBand = iranPlateCountryBandMarkup();

  if (template.layout === 'latin') {
    return `<span class="${className} vehicle-plate-preview--latin" data-preserve-latin="true" dir="ltr">${countryBand}<span class="vehicle-plate-latin-copy"><small>TEMPORARY</small>${slot('latin', '12A-34567', { preserveLatin: true })}</span></span>`;
  }
  if (template.layout === 'motorcycle') {
    return `<span class="${className} vehicle-plate-preview--motorcycle">${countryBand}<span class="vehicle-plate-motorcycle-copy">${slot('first', '۱۲۳')}${slot('second', '۴۵۶')}<small>موتورسیکلت</small></span></span>`;
  }
  if (template.layout === 'protocol') {
    return `<span class="${className} vehicle-plate-preview--protocol" data-preserve-latin="true" dir="ltr">${countryBand}<span class="vehicle-plate-protocol-copy"><small>PROTOCOL</small>${slot('number', '1234', { preserveLatin: true })}</span></span>`;
  }
  if (template.layout === 'free-zone-industrial') {
    const freeZoneCountryBand = iranPlateCountryBandMarkup({ freeZone: true });
    const freeZoneNumber = typeof values.number === 'string' && values.number ? values.number : '۱۲۳۴۵';
    const freeZoneLatinNumber = enDigits(freeZoneNumber);
    return `<span class="${className} vehicle-plate-preview--free-zone">${freeZoneCountryBand}<span class="vehicle-plate-free-zone-copy"><span class="vehicle-plate-free-zone-numbers">${slot('number', '۱۲۳۴۵')}<span class="vehicle-plate-free-zone-latin" data-plate-preview-mirror="number" data-default-value="12345" dir="ltr">${escapeHtml(freeZoneLatinNumber)}</span></span><span class="vehicle-plate-free-zone-region">${slot('region', '۲۲')}<small data-plate-preview-part="zone" data-default-value="کیش">${escapeHtml(typeof values.zone === 'string' && values.zone ? values.zone : 'کیش')}</small></span></span></span>`;
  }
  if (template.layout === 'historical') {
    return `<span class="${className} vehicle-plate-preview--historical">${countryBand}<span class="vehicle-plate-historical-copy"><small>تاریخی</small>${slot('number', '۱۲۳۴۵')}</span></span>`;
  }
  if (template.layout === 'disabled') {
    return `<span class="${className} vehicle-plate-preview--disabled">${countryBand}${slot('right', '۱۲')}<span class="vehicle-plate-wheelchair" aria-label="نشان ویلچر">${wheelchairSymbolMarkup()}</span>${slot('left', '۳۴۵')}<span class="vehicle-plate-region">${slot('region', '۶۷')}</span></span>`;
  }

  const letter = template.fixedLetter
    ? `<span class="vehicle-plate-letter"${template.preserveLetter ? ' data-preserve-latin="true" dir="ltr"' : ''}>${escapeHtml(template.fixedLetter)}</span>`
    : slot('letter', 'ب', { className: 'vehicle-plate-letter' });
  return `<span class="${className}">${countryBand}${slot('right', '۱۲')}${letter}${slot('left', '۳۴۵')}<span class="vehicle-plate-region">${slot('region', '۶۷')}</span></span>`;
}

function vehiclePlateInputMarkup(part, label, options = {}, value = '') {
  const numeric = options.numeric ? ' data-numeric="true"' : '';
  const textOnly = options.textOnly ? ' data-text-only="true"' : '';
  const latin = options.latin ? ' data-preserve-latin="true" dir="ltr" autocapitalize="characters" spellcheck="false"' : '';
  const maxLength = options.maxLength ? ` maxlength="${options.maxLength}"` : '';
  const inputMode = options.numeric ? 'numeric' : 'text';
  return `<label class="vehicle-plate-input-wrap"><span>${label}</span><input type="text" class="field-input vehicle-plate-input${options.numeric ? ' numeric' : ''}" data-vehicle-plate-input data-plate-part="${part}"${numeric}${textOnly}${latin}${maxLength} inputmode="${inputMode}" autocomplete="off" value="${escapeHtml(value)}"></label>`;
}

function vehiclePlateEditorMarkup(templateId, savedParts = {}) {
  const template = vehiclePlateTemplateFor(templateId);
  if (!template) {
    return '<div class="vehicle-plate-editor vehicle-plate-editor--empty" data-vehicle-plate-editor><p>قالب پلاک این وسیله نقلیه را انتخاب کنید.</p></div>';
  }

  let inputs = '';
  if (template.layout === 'national' || template.layout === 'disabled') {
    const letterInput = template.layout === 'national' && !template.fixedLetter
      ? vehiclePlateInputMarkup('letter', 'حرف پلاک', { textOnly: true, maxLength: 1 }, savedParts.letter || '')
      : '';
    inputs = `${vehiclePlateInputMarkup('right', 'دو رقم سمت راست', { numeric: true, maxLength: 2 }, savedParts.right || '')}${letterInput}${vehiclePlateInputMarkup('left', 'سه رقم', { numeric: true, maxLength: 3 }, savedParts.left || '')}${vehiclePlateInputMarkup('region', 'کد دو رقمی', { numeric: true, maxLength: 2 }, savedParts.region || '')}`;
  } else if (template.layout === 'motorcycle') {
    inputs = `${vehiclePlateInputMarkup('first', 'سه رقم اول', { numeric: true, maxLength: 3 }, savedParts.first || '')}${vehiclePlateInputMarkup('second', 'سه رقم دوم', { numeric: true, maxLength: 3 }, savedParts.second || '')}`;
  } else if (template.layout === 'latin') {
    inputs = vehiclePlateInputMarkup('latin', 'شماره لاتین پلاک', { latin: true, maxLength: 16 }, savedParts.latin || '');
  } else if (template.layout === 'free-zone-industrial') {
    inputs = `${vehiclePlateInputMarkup('number', 'شماره پنج رقمی', { numeric: true, maxLength: 5 }, savedParts.number || '')}${vehiclePlateInputMarkup('region', 'کد منطقه آزاد', { numeric: true, maxLength: 2 }, savedParts.region || '')}${vehiclePlateInputMarkup('zone', 'نام منطقه آزاد', { textOnly: true, maxLength: 20 }, savedParts.zone || '')}`;
  } else {
    inputs = vehiclePlateInputMarkup('number', 'شماره پلاک', { numeric: true, maxLength: 5 }, savedParts.number || '');
  }

  return `<div class="vehicle-plate-editor" data-vehicle-plate-editor data-plate-template="${template.id}">
    <div class="vehicle-plate-preview-wrap" aria-label="نمایش قالب انتخاب‌شده">${vehiclePlatePreviewMarkup(template, savedParts)}</div>
    <div class="vehicle-plate-inputs vehicle-plate-inputs--${template.layout}">${inputs}</div>
  </div>`;
}

function normalizedVehiclePlate(value, kind = '') {
  if (!isPlainRecord(value)) return null;
  const template = vehiclePlateTemplateFor(value.template);
  if (!template || (kind && !vehiclePlateTemplatesForKind(kind).some(item => item.id === template.id))) return null;
  const parts = {};
  if (isPlainRecord(value.parts)) {
    Object.entries(value.parts).forEach(([part, partValue]) => {
      if (typeof partValue === 'string' && partValue.trim()) parts[part] = partValue.trim();
    });
  }
  if (template.fixedLetter) parts.letter = template.fixedLetter;
  if (template.layout === 'disabled') delete parts.letter;
  return { template: template.id, label: template.label, parts };
}

function normalizedVehicleRecord(value) {
  const source = isPlainRecord(value) ? value : {};
  const rawPlate = isPlainRecord(source.plate) ? source.plate : (isPlainRecord(source.vehiclePlate) ? source.vehiclePlate : null);
  let kind = VEHICLE_KIND_OPTIONS.some(item => item.value === source.kind) ? source.kind : '';
  if (!kind && rawPlate && vehiclePlateTemplateFor(rawPlate.template)) {
    kind = rawPlate.template === 'motorcycle' ? 'موتورسیکلت' : 'خودرو';
  }
  const record = {};
  if (kind) record.kind = kind;
  ['type', 'color', 'specialFeature'].forEach(key => {
    if (typeof source[key] === 'string' && source[key].trim()) record[key] = source[key].trim();
  });
  const noPlate = source.noPlate === true || source.noPlate === 'بله' || source.vehicleNoPlate === 'بله';
  if (noPlate) record.noPlate = true;
  else {
    const plate = normalizedVehiclePlate(rawPlate, kind);
    if (plate) record.plate = plate;
  }
  return record;
}

function vehicleRecordHasMeaningfulValue(vehicle) {
  if (!isPlainRecord(vehicle)) return false;
  return Boolean(
    vehicle.kind || vehicle.type || vehicle.color || vehicle.specialFeature || vehicle.noPlate ||
    (isPlainRecord(vehicle.plate) && vehicle.plate.template)
  );
}

function clearLegacyPersonVehicleFields(data) {
  ['vehicleKind', 'vehicleType', 'vehicleColor', 'vehiclePlate', 'vehicleNoPlate', 'vehicleSpecialFeature'].forEach(key => delete data[key]);
}

function ensurePersonVehicles() {
  const existing = state.form.vehicles;
  let vehicles;
  if (Array.isArray(existing)) {
    vehicles = existing.slice(0, MAX_VEHICLES).map(normalizedVehicleRecord);
  } else {
    const legacy = normalizedVehicleRecord({
      kind: state.form.vehicleKind,
      type: state.form.vehicleType,
      color: state.form.vehicleColor,
      plate: state.form.vehiclePlate,
      noPlate: state.form.vehicleNoPlate,
      specialFeature: state.form.vehicleSpecialFeature
    });
    vehicles = vehicleRecordHasMeaningfulValue(legacy) ? [legacy] : [];
  }
  if (!vehicles.length) vehicles = [{}];
  state.form.vehicles = vehicles;
  clearLegacyPersonVehicleFields(state.form);
  return vehicles;
}

function vehicleTextFieldMarkup(vehicle, key, label, options = {}) {
  const value = typeof vehicle[key] === 'string' ? vehicle[key] : '';
  const placeholder = options.placeholder ? ` placeholder="${escapeHtml(options.placeholder)}"` : '';
  if (options.textarea) {
    return `<div class="field-group"><label>${label}</label><textarea class="field-textarea" data-vehicle-field="${key}" data-auto-resize="true"${placeholder}>${escapeHtml(value)}</textarea></div>`;
  }
  return `<div class="field-group"><label>${label}</label><input class="field-input" type="text" data-vehicle-field="${key}"${placeholder} value="${escapeHtml(value)}"></div>`;
}

function vehiclePlateField(vehicle, index) {
  const kind = vehicle.kind || '';
  const availableTemplates = vehiclePlateTemplatesForKind(kind);
  const storedPlate = normalizedVehiclePlate(vehicle.plate, kind);
  const template = storedPlate && availableTemplates.some(item => item.id === storedPlate.template) ? vehiclePlateTemplateFor(storedPlate.template) : null;
  const noPlate = vehicle.noPlate === true;
  const options = availableTemplates.map(item => ({
    ...item,
    value: item.id,
    preview: vehiclePlatePreviewMarkup(item, {}, true)
  }));
  const canChooseTemplate = Boolean(kind);
  const placeholder = canChooseTemplate ? 'قالب پلاک را انتخاب کنید' : 'ابتدا وسیله نقلیه را انتخاب کنید';
  return `<div class="field-group vehicle-plate-field${noPlate ? ' is-no-plate' : ''}" data-vehicle-plate-field data-vehicle-index="${index}">
    <label>پلاک</label>
    <p class="vehicle-plate-description">اطلاعات پلاک مشاهده شده را وارد کنید</p>
    ${searchableSelectMarkup(`vehiclePlateTemplate-${index}`, '', options, { value: template ? template.id : '', placeholder, wrap: false, className: 'vehicle-plate-template-select', disabled: noPlate || !canChooseTemplate })}
    <label class="vehicle-no-plate"><input type="checkbox" data-vehicle-no-plate onchange="toggleVehicleNoPlate(this)"${noPlate ? ' checked' : ''}><span>فاقد پلاک</span></label>
    <div data-vehicle-plate-editor-container>${noPlate ? '<div class="vehicle-plate-editor vehicle-plate-editor--empty" data-vehicle-plate-editor><p>برای این وسیله نقلیه، پلاکی مشاهده نشده است.</p></div>' : vehiclePlateEditorMarkup(template ? template.id : '', storedPlate ? storedPlate.parts : {})}</div>
  </div>`;
}

function vehicleCardMarkup(vehicle, index, total) {
  const title = `وسیله نقلیه ${faDigits(index + 1)}`;
  const removeButton = total > 1 ? `<button class="vehicle-remove-button" type="button" onclick="removeVehicle(this)">حذف این وسیله</button>` : '';
  return `<article class="vehicle-card" data-vehicle-card data-vehicle-index="${index}">
    <header class="vehicle-card-heading"><h3>${title}</h3>${removeButton}</header>
    <div class="vehicle-card-fields">
      ${searchableSelectMarkup(`vehicleKind-${index}`, 'وسیله نقلیه', VEHICLE_KIND_OPTIONS, { value: vehicle.kind || '', placeholder: 'خودرو یا موتورسیکلت را انتخاب کنید', className: 'vehicle-kind-select' })}
      ${vehicleTextFieldMarkup(vehicle, 'type', 'عنوان وسیله نقلیه')}
      ${vehicleTextFieldMarkup(vehicle, 'color', 'رنگ')}
      ${vehiclePlateField(vehicle, index)}
      ${vehicleTextFieldMarkup(vehicle, 'specialFeature', 'ویژگی خاص', { textarea: true, placeholder: 'تصادف، خوردگی رنگ و موارد بارز دیگر' })}
    </div>
  </article>`;
}

function vehicleCollectionInnerMarkup() {
  const vehicles = ensurePersonVehicles();
  const atLimit = vehicles.length >= MAX_VEHICLES;
  return `<div class="vehicle-card-list">${vehicles.map((vehicle, index) => vehicleCardMarkup(vehicle, index, vehicles.length)).join('')}</div>
    <button class="vehicle-add-button" type="button" onclick="addVehicle()"${atLimit ? ' disabled' : ''}>افزودن وسیله نقلیه دیگر${atLimit ? ` (حداکثر ${faDigits(MAX_VEHICLES)})` : ''}</button>`;
}

function vehicleCollectionMarkup() {
  return `<div class="vehicle-collection" data-vehicle-collection aria-label="فهرست وسایل نقلیه">${vehicleCollectionInnerMarkup()}</div>`;
}

function activeVehicleCollection() {
  // A property report renders the same reusable package on its own physical page.
  // Prefer the visible collection so an interaction can never redraw a hidden form.
  return document.querySelector('#propertyVehiclesPage.active [data-vehicle-collection], #formPage.active [data-vehicle-collection]')
    || document.querySelector('[data-vehicle-collection]');
}

function collectVehicleDetailsForInteraction(element = null) {
  const inPropertyVehiclePage = element && typeof element.closest === 'function'
    && element.closest('#propertyVehiclesPage');
  const propertyVehiclePage = document.getElementById('propertyVehiclesPage');
  const propertyVehiclePageIsActive = propertyVehiclePage && propertyVehiclePage.classList.contains('active');
  if (isPropertyReport() && (inPropertyVehiclePage || propertyVehiclePageIsActive)) {
    collectPropertyVehicleDetails();
    return;
  }
  collectForm();
}

function renderVehiclePackages() {
  const collection = activeVehicleCollection();
  if (!collection) return;
  collection.innerHTML = vehicleCollectionInnerMarkup();
  collection.querySelectorAll('[data-searchable-select]').forEach(updateSearchableSelectPresentation);
  collection.querySelectorAll('textarea[data-auto-resize="true"]').forEach(resizeTextarea);
  setTimeout(normalizeVisibleNumbers, 0);
}

function vehicleCardIndexFor(element) {
  const card = element && element.closest ? element.closest('[data-vehicle-card]') : null;
  const index = card ? Number(card.dataset.vehicleIndex) : NaN;
  return Number.isInteger(index) && index >= 0 ? index : -1;
}

function updateVehicleKind(component) {
  const index = vehicleCardIndexFor(component);
  if (index < 0) return;
  collectVehicleDetailsForInteraction(component);
  const vehicles = ensurePersonVehicles();
  const vehicle = vehicles[index];
  if (!vehicle) return;
  vehicle.kind = searchableSelectValue(component);
  if (vehicle.plate && !vehiclePlateTemplatesForKind(vehicle.kind).some(item => item.id === vehicle.plate.template)) delete vehicle.plate;
  renderVehiclePackages();
}

function updateVehiclePlateTemplate(component) {
  const index = vehicleCardIndexFor(component);
  if (index < 0) return;
  const selectedTemplate = searchableSelectValue(component);
  const previousTemplate = Array.isArray(state.form.vehicles) && isPlainRecord(state.form.vehicles[index]) && isPlainRecord(state.form.vehicles[index].plate)
    ? state.form.vehicles[index].plate.template
    : '';
  collectVehicleDetailsForInteraction(component);
  const vehicles = ensurePersonVehicles();
  const vehicle = vehicles[index];
  const template = vehicle && vehiclePlateTemplateFor(selectedTemplate);
  if (!vehicle || !template || !vehiclePlateTemplatesForKind(vehicle.kind).some(item => item.id === template.id)) return;
  if (previousTemplate !== template.id) vehicle.plate = { template: template.id, label: template.label, parts: template.fixedLetter ? { letter: template.fixedLetter } : {} };
  renderVehiclePackages();
}

function toggleVehicleNoPlate(checkbox) {
  const index = vehicleCardIndexFor(checkbox);
  if (index < 0) return;
  collectVehicleDetailsForInteraction(checkbox);
  const vehicle = ensurePersonVehicles()[index];
  if (!vehicle) return;
  if (checkbox.checked) {
    vehicle.noPlate = true;
    delete vehicle.plate;
  } else {
    delete vehicle.noPlate;
  }
  renderVehiclePackages();
  persistReportDraft();
}

function addVehicle() {
  collectVehicleDetailsForInteraction();
  const vehicles = ensurePersonVehicles();
  if (vehicles.length >= MAX_VEHICLES) return;
  vehicles.push({});
  renderVehiclePackages();
  persistReportDraft();
}

function removeVehicle(button) {
  const index = vehicleCardIndexFor(button);
  if (index < 0) return;
  collectVehicleDetailsForInteraction(button);
  const vehicles = ensurePersonVehicles();
  vehicles.splice(index, 1);
  if (!vehicles.length) vehicles.push({});
  renderVehiclePackages();
  persistReportDraft();
}

function normalizeVehiclePlateInput(input) {
  normalizeFieldValue(input);
  if (input.dataset.preserveLatin === 'true') {
    input.value = enDigits(input.value).toUpperCase().replace(/[^A-Z0-9 -]/g, '');
  }
  if (input.dataset.platePart === 'letter') {
    input.value = input.value.replace(/[^آ-یءئ]/g, '').slice(0, 1);
  }
}

function syncVehiclePlatePreview(input) {
  const editor = input.closest('[data-vehicle-plate-editor]');
  if (!editor) return;
  const preview = editor.querySelector(`[data-plate-preview-part="${input.dataset.platePart}"]`);
  if (preview) {
    const fallback = preview.dataset.defaultValue || preview.textContent;
    preview.textContent = input.value || fallback;
  }
  const mirror = editor.querySelector(`[data-plate-preview-mirror="${input.dataset.platePart}"]`);
  if (mirror) {
    const fallback = mirror.dataset.defaultValue || mirror.textContent;
    mirror.textContent = input.value ? enDigits(input.value) : fallback;
  }
}

function collectVehiclePackages(data, scope = document) {
  const collection = scope && typeof scope.querySelector === 'function' ? scope.querySelector('[data-vehicle-collection]') : null;
  // Only the active sequential form section is in the DOM. Keep package records
  // intact while the reporter is on earlier steps, time, location, or documents.
  if (!collection) return;

  const vehicles = [];
  collection.querySelectorAll('[data-vehicle-card]').forEach(card => {
    const kindPicker = card.querySelector('[data-select-name^="vehicleKind-"]');
    const platePicker = card.querySelector('[data-select-name^="vehiclePlateTemplate-"]');
    const kind = searchableSelectValue(kindPicker);
    const record = {
      kind,
      type: (card.querySelector('[data-vehicle-field="type"]')?.value || '').trim(),
      color: (card.querySelector('[data-vehicle-field="color"]')?.value || '').trim(),
      specialFeature: (card.querySelector('[data-vehicle-field="specialFeature"]')?.value || '').trim()
    };
    const noPlate = card.querySelector('[data-vehicle-no-plate]');
    if (noPlate && noPlate.checked) {
      record.noPlate = true;
    } else {
      const template = vehiclePlateTemplateFor(searchableSelectValue(platePicker));
      if (template && vehiclePlateTemplatesForKind(kind).some(item => item.id === template.id)) {
        const parts = {};
        card.querySelectorAll('[data-vehicle-plate-input]').forEach(input => {
          const value = input.value.trim();
          if (value) parts[input.dataset.platePart] = value;
        });
        if (template.fixedLetter) parts.letter = template.fixedLetter;
        record.plate = { template: template.id, label: template.label, parts };
      }
    }
    vehicles.push(normalizedVehicleRecord(record));
  });
  data.vehicles = vehicles.slice(0, MAX_VEHICLES);
  clearLegacyPersonVehicleFields(data);
}

function socialPlatformFor(platformId) {
  return typeof platformId === 'string' ? SOCIAL_PLATFORM_BY_ID.get(platformId) || null : null;
}

function socialPlatformLogoMarkup(platform, className = 'social-platform-logo') {
  if (!platform) return '';
  const logoFile = SOCIAL_LOGO_FILES[platform.id];
  if (!logoFile) return '';
  return `<img class="${className} social-platform-logo--${platform.id}" src="assets/social-icons/${logoFile}" alt="" aria-hidden="true" draggable="false" decoding="async">`;
}

function normalizedSocialSearch(value) {
  return String(value ?? '')
    .toLocaleLowerCase('fa')
    .replace(/ي/g, 'ی')
    .replace(/ك/g, 'ک')
    .replace(/\u200c/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function normalizeSocialLinkValue(platform, value) {
  const rawValue = String(value ?? '').trim();
  if (!platform || !rawValue) return '';

  const withoutProtocol = rawValue.replace(/^(?:https?:)?\/\//i, '').replace(/^www\./i, '');
  const loweredValue = withoutProtocol.toLowerCase();
  const prefix = platform.prefix.toLowerCase();
  if (loweredValue.startsWith(prefix)) return withoutProtocol.slice(platform.prefix.length).replace(/^\/+/, '');

  for (const alias of platform.aliases) {
    const loweredAlias = alias.toLowerCase().replace(/\/+$/, '');
    if (loweredValue === loweredAlias) return '';
    if (loweredValue.startsWith(`${loweredAlias}/`)) return withoutProtocol.slice(alias.length).replace(/^\/+/, '');
  }

  return rawValue.replace(/^\/+/, '');
}

function socialLinkUrl(platform, value) {
  const normalizedValue = normalizeSocialLinkValue(platform, value);
  return normalizedValue ? `https://${platform.prefix}${normalizedValue}` : '';
}

function savedSocialLinks() {
  if (!Array.isArray(state.form.socialLinks)) return [];
  return state.form.socialLinks.reduce((links, item) => {
    if (!isPlainRecord(item)) return links;
    const platform = socialPlatformFor(item.platform);
    if (!platform) return links;
    const rawValue = typeof item.value === 'string' ? item.value : typeof item.url === 'string' ? item.url : '';
    links.push({ platform: platform.id, value: normalizeSocialLinkValue(platform, rawValue) });
    return links;
  }, []);
}

function socialPlatformOptionMarkup(platform) {
  return `<button type="button" class="social-platform-option" role="option" data-social-platform-option data-social-search="${escapeHtml(platform.search)}" onclick="selectSocialPlatform(this,'${platform.id}')">${socialPlatformLogoMarkup(platform)}<span class="social-platform-option-name">${platform.name}</span><span class="social-platform-option-prefix" dir="ltr">${platform.prefix}</span></button>`;
}

function socialLinkRowMarkup(savedLink = {}) {
  const platform = socialPlatformFor(savedLink.platform);
  const rawValue = typeof savedLink.value === 'string' ? savedLink.value : typeof savedLink.url === 'string' ? savedLink.url : '';
  const value = platform ? normalizeSocialLinkValue(platform, rawValue) : '';
  const menuId = `social-platform-menu-${++socialLinkRowSequence}`;
  const selectedMarkup = platform
    ? `${socialPlatformLogoMarkup(platform)}<span class="sr-only">${platform.name}</span>`
    : `${iconMarkup('link', 'social-platform-empty-icon')}<span class="social-platform-trigger-copy">انتخاب شبکه</span>`;
  const prefixMarkup = platform
    ? `<span class="social-link-prefix" dir="ltr">${platform.prefix}</span>`
    : '<span class="social-link-prefix social-link-prefix--empty">نشانی</span>';
  const inputState = platform ? '' : ' disabled';
  const helper = platform
    ? 'شناسه، مسیر پیام یا پست، یا لینک کامل را وارد کنید.'
    : 'ابتدا پلتفرم موردنظر را انتخاب کنید.';

  return `<div class="social-link-row${platform ? ' is-selected' : ''}" data-social-link-row data-platform="${platform ? platform.id : ''}">
    <div class="social-link-control">
      <button type="button" class="social-platform-trigger button-with-icon" aria-haspopup="dialog" aria-controls="${menuId}" aria-expanded="false" aria-label="${platform ? `تغییر پلتفرم؛ ${platform.name}` : 'انتخاب پلتفرم'}" onclick="toggleSocialPlatformMenu(this)">${selectedMarkup}${iconMarkup('chevron-down', 'social-platform-chevron')}</button>
      <div class="social-link-entry">
        ${prefixMarkup}
        <input type="text" class="social-link-value" data-social-link-value dir="ltr" value="${escapeHtml(value)}"${inputState} maxlength="500" autocomplete="off" autocapitalize="none" spellcheck="false" aria-label="شناسه یا مسیر نشانی فضای مجازی" onblur="normalizeSocialLinkEntry(this)">
      </div>
      <button type="button" class="social-link-remove" aria-label="حذف این نشانی" onclick="removeSocialLink(this)">${iconMarkup('trash', 'social-link-remove-icon')}<span class="sr-only">حذف</span></button>
    </div>
    <div id="${menuId}" class="social-platform-menu" role="dialog" aria-label="انتخاب پلتفرم">
      <div class="social-platform-search-wrap">
        ${iconMarkup('search', 'social-platform-search-icon')}
        <input type="search" class="social-platform-search" autocomplete="off" placeholder="جست‌وجوی پلتفرم" aria-label="جست‌وجوی پلتفرم">
      </div>
      <div class="social-platform-options" role="listbox" aria-label="فهرست پلتفرم‌ها">${SOCIAL_PLATFORMS.map(socialPlatformOptionMarkup).join('')}</div>
    </div>
    <p class="social-link-helper">${helper}</p>
  </div>`;
}

function socialLinksField() {
  const savedLinks = savedSocialLinks();
  const rows = savedLinks.length ? savedLinks : [{}];
  return `<div class="field-group social-links-field" data-social-links data-person-contact-fields>
    <label>نشانی‌های فضای مجازی</label>
    <p class="social-links-description">پلتفرم را انتخاب کنید و سپس شناسه، مسیر پیام یا پست، یا لینک آن را وارد کنید.</p>
    <div class="social-link-list" data-social-link-list>${rows.map(socialLinkRowMarkup).join('')}</div>
    <button type="button" class="social-add-button button-with-icon" onclick="addSocialLink()">${iconMarkup('plus', 'social-add-icon')}<span>افزودن نشانی دیگر</span></button>
  </div>`;
}

function closeSocialPlatformMenus(except = null) {
  document.querySelectorAll('.social-link-row.is-picker-open').forEach(row => {
    if (row === except) return;
    row.classList.remove('is-picker-open');
    const trigger = row.querySelector('.social-platform-trigger');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
  });
}

function toggleSocialPlatformMenu(trigger) {
  const row = trigger.closest('[data-social-link-row]');
  if (!row) return;
  const shouldOpen = !row.classList.contains('is-picker-open');
  closeSocialPlatformMenus(row);
  row.classList.toggle('is-picker-open', shouldOpen);
  trigger.setAttribute('aria-expanded', String(shouldOpen));

  if (shouldOpen) {
    const search = row.querySelector('.social-platform-search');
    if (search) {
      search.value = '';
      filterSocialPlatforms(search);
      setTimeout(() => search.focus(), 0);
    }
  }
}

function filterSocialPlatforms(searchInput) {
  const row = searchInput.closest('[data-social-link-row]');
  if (!row) return;
  const query = normalizedSocialSearch(searchInput.value);
  row.querySelectorAll('[data-social-platform-option]').forEach(option => {
    option.hidden = Boolean(query) && !normalizedSocialSearch(option.dataset.socialSearch).includes(query);
  });
}

function selectSocialPlatform(option, platformId) {
  const platform = socialPlatformFor(platformId);
  const row = option.closest('[data-social-link-row]');
  if (!platform || !row) return;
  const currentInput = row.querySelector('[data-social-link-value]');
  const value = currentInput ? currentInput.value : '';
  row.outerHTML = socialLinkRowMarkup({ platform: platform.id, value: normalizeSocialLinkValue(platform, value) });
  persistReportDraft();
}

function normalizeSocialLinkEntry(input) {
  const row = input.closest('[data-social-link-row]');
  const platform = row ? socialPlatformFor(row.dataset.platform) : null;
  if (!platform) return;
  input.value = normalizeSocialLinkValue(platform, input.value);
  persistReportDraft();
}

function addSocialLink() {
  const list = document.querySelector('[data-social-link-list]');
  if (!list) return;
  collectForm();
  list.insertAdjacentHTML('beforeend', socialLinkRowMarkup());
  const trigger = list.lastElementChild && list.lastElementChild.querySelector('.social-platform-trigger');
  if (trigger) toggleSocialPlatformMenu(trigger);
  persistReportDraft();
}

function removeSocialLink(button) {
  const row = button.closest('[data-social-link-row]');
  const list = row && row.parentElement;
  if (!row || !list) return;
  if (list.children.length === 1) row.outerHTML = socialLinkRowMarkup();
  else row.remove();
  collectForm();
  persistReportDraft();
}

function collectSocialLinks(data) {
  const group = document.querySelector('[data-social-links]');
  if (!group) return;

  delete data.social;
  delete data.socialLinks;
  const links = [];
  group.querySelectorAll('[data-social-link-row]').forEach(row => {
    const platform = socialPlatformFor(row.dataset.platform);
    if (!platform) return;
    const input = row.querySelector('[data-social-link-value]');
    const value = normalizeSocialLinkValue(platform, input ? input.value : '');
    links.push({ platform: platform.id, value, url: socialLinkUrl(platform, value) });
  });
  if (links.length) data.socialLinks = links;
}

function hasMeaningfulFormValue() {
  return Object.entries(state.form).some(([name, value]) => {
    if (name === 'socialLinks' && Array.isArray(value)) {
      return value.some(link => isPlainRecord(link) && typeof link.value === 'string' && link.value.trim());
    }
    if (name === 'vehiclePlate' && isPlainRecord(value)) return Boolean(value.template);
    if (name === 'vehicles' && Array.isArray(value)) return value.some(vehicleRecordHasMeaningfulValue);
    if (name === 'phoneFixedProvince' || name === 'phoneWorkProvince') return false;
    if (name === 'propertyPeople' && isPlainRecord(value)) {
      return Object.values(value).some(entries => Array.isArray(entries) && entries.some(propertyPersonRecordHasMeaningfulValue));
    }
    return typeof value === 'string' && value.trim();
  });
}

function isValidNationalId(value) {
  const digits = enDigits(value);
  if (!/^\d{10}$/.test(digits) || /^(\d)\1{9}$/.test(digits)) return false;
  const total = digits.slice(0, 9).split('').reduce((sum, digit, index) => sum + Number(digit) * (10 - index), 0);
  const remainder = total % 11;
  const checkDigit = Number(digits[9]);
  return checkDigit === (remainder < 2 ? remainder : 11 - remainder);
}

function fieldValidationMessage(element) {
  const value = element.value.trim();
  if (!value || !element.dataset.validation) return '';

  const digits = enDigits(value);
  const label = element.dataset.label || 'این فیلد';
  if (element.dataset.validation === 'national-id' && !isValidNationalId(digits)) {
    return `${label} باید ۱۰ رقم معتبر باشد.`;
  }
  if (element.dataset.validation === 'phone' && !/^\d{11}$/.test(digits)) {
    return `${label} باید دقیقاً ۱۱ رقم باشد.`;
  }
  if (element.dataset.validation === 'landline' && !/^\d{8}$/.test(digits)) {
    return `${label} باید ۸ رقم باشد؛ پیش‌شماره استان خودکار افزوده می‌شود.`;
  }
  if (element.dataset.validation === 'age' && (!/^\d{1,3}$/.test(digits) || Number(digits) < 1 || Number(digits) > 120)) {
    return `${label} باید عددی بین ۱ تا ۱۲۰ باشد.`;
  }
  if (element.dataset.validation === 'height' && (!/^\d{1,3}$/.test(digits) || Number(digits) < 1 || Number(digits) > 250)) {
    return `${label} باید عددی تا ۳ رقم و حداکثر ۲۵۰ باشد.`;
  }
  if (element.dataset.validation === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
    return `${label} را به شکل یک ایمیل معتبر وارد کنید.`;
  }
  return '';
}

function validateVisibleFormFields(scope = null) {
  const activeScope = scope && typeof scope.querySelectorAll === 'function'
    ? scope
    : document.querySelector('.page.active');
  const validationFields = activeScope
    ? Array.from(activeScope.querySelectorAll('[data-validation]'))
    : Array.from(document.querySelectorAll('[data-validation]'));
  const invalid = validationFields
    .map(element => ({ element, message: fieldValidationMessage(element) }))
    .find(item => item.message);

  if (!invalid) return true;
  if (typeof invalid.element.focus === 'function') invalid.element.focus();
  alert(invalid.message);
  return false;
}

function formSection(title, fields, description) {
  return { title, fields, description };
}

function openForm() {
  if (state.category === 'املاک') clearRetiredPropertyFields();
  const title = state.category === 'املاک'
    ? 'گزارش وقوع'
    : state.subtype ? `${state.category} — ${state.subtype}` : state.category;
  const sections = buildForm(state.category, state.subtype);
  if (!sections.length) return showPage('categoryPage');
  state.formStep = Math.min(Math.max(Number(state.formStep) || 0, 0), sections.length - 1);
  document.getElementById('formTitle').textContent = title;
  renderFormSection(sections);
  showPage('formPage');
}

function renderFormSection(sections = buildForm(state.category, state.subtype)) {
  const total = sections.length;
  const current = sections[state.formStep];
  const isLast = state.formStep === total - 1;
  const currentNumber = faDigits(state.formStep + 1);
  const totalNumber = faDigits(total);
  const progress = ((state.formStep + 1) / total) * 100;

  document.getElementById('formBody').innerHTML = `
    <div class="form-section-progress" aria-label="بخش ${currentNumber} از ${totalNumber}">
      <span>بخش ${currentNumber} از ${totalNumber}</span>
      <div class="form-section-progress-track" aria-hidden="true"><span style="--form-progress:${progress}%"></span></div>
    </div>
    <section class="form-section-card" aria-labelledby="formSectionTitle">
      <header class="form-section-heading">
        <h2 id="formSectionTitle">${current.title}</h2>
        <p>${current.description}</p>
      </header>
      <div class="form-section-fields">${current.fields}</div>
    </section>`;

  const actions = document.getElementById('formActions');
  const primaryLabel = isLast
    ? state.category === 'املاک' ? 'تایید گزارش وقوع' : 'تایید فرم شناسایی'
    : 'مرحله بعد';
  actions.className = `form-actions${state.formStep === 0 ? ' first-step' : ''}`;
  actions.innerHTML = `
    ${state.formStep > 0 ? `<button class="secondary-button" onclick="previousFormSection()" type="button">مرحله قبل</button>` : ''}
    <button class="primary-button" onclick="${isLast ? 'continueForm()' : 'nextFormSection()'}" type="button">${primaryLabel}</button>`;

  restoreFormValues();
  setTimeout(normalizeVisibleNumbers, 0);
}

function buildForm(category, subtype) {
  category = canonicalCategory(category);
  if (category === 'افراد') return personForm();
  if (category === 'املاک') return propertyForm();
  if (category === 'اشیاء') return objectForm(subtype);
  if (category === 'رویداد') return phenomenonForm(subtype);
  return [];
}

function personForm() {
  return [
    formSection('مشخصات فردی',
      `${field('firstName','نام','text',{textOnly:true})}${field('lastName','نام خانوادگی','text',{textOnly:true})}${field('nickname','شهرت','text',{textOnly:true})}${field('nationalId','کد ملی','text',{numeric:true,maxLength:10,validation:'national-id'})}${field('age','سن','text',{numeric:true,maxLength:3,validation:'age'})}${choices('gender','جنسیت',['مرد','زن','نامشخص'],{columns:3})}`,
      'اطلاعات پایه برای شناسایی فرد را وارد کنید.'),
    formSection('مشخصات ظاهری',
      `${field('height','قد','text',{numeric:true,maxLength:3,maxValue:250,validation:'height'})}${choices('bodyBuild','اندام',['لاغر','معمولی','چاق'],{columns:3})}${field('face','رنگ پوست','text',{textOnly:true})}${field('hairColor','رنگ مو','text',{textOnly:true})}${field('hairStatus','وضعیت موی سر','text',{textOnly:true})}${field('beard','محاسن','text',{textOnly:true})}${field('appearance','ویژگی خاص','textarea',{placeholder:'شامل زخم، تتو، معلولیت و موارد بارز دیگر'})}`,
      'ویژگی‌های ظاهری قابل مشاهده را ثبت کنید.'),
    formSection('پل‌های ارتباطی',
      `${field('phoneMobile','شماره همراه','text',{numeric:true,maxLength:11,validation:'phone'})}${landlineContactFieldMarkup('phoneFixed')}${field('homeAddress','نشانی محل سکونت','textarea')}${landlineContactFieldMarkup('phoneWork')}${field('workAddress','نشانی محل کار','textarea')}${field('email','نشانی پست الکترونیک','email',{validation:'email',placeholder:'example@gmail.com',ltr:true})}${socialLinksField()}`,
      'شماره‌های تماس و نشانی‌های مرتبط را وارد کنید.'),
    formSection('وسایل نقلیه',
      vehicleCollectionMarkup(),
      'برای هر وسیله نقلیه، نوع، رنگ، پلاک و ویژگی‌های قابل مشاهده را ثبت کنید.')
  ];
}

/* ---------------------------------------------------------------------------
 * گزارش وقوع: ساختار سه‌بخشی با پاپ‌آپ سؤالات
 * سه دستهٔ «نوع جرم یا تخلف»، «همکاران و افراد مرتبط» و «نحوهٔ اطلاع» برای همهٔ
 * بخش‌ها (افراد، املاک، رویداد، اشیاء) مشترک است. با انتخاب هر مورد، پاپ‌آپی با
 * سؤالات متناسب همان مورد باز می‌شود. داده‌ها در state.form.incident ذخیره می‌شوند.
 * ------------------------------------------------------------------------- */
const INCIDENT_GROUP_ORDER = Object.freeze(['crimeTypes', 'related', 'source']);
let incidentPopupContext = null;
// وضعیت دستیار صوتی و کراس‌چک خودکار (فقط در جریان همین جلسه، در داده ذخیره نمی‌شود)
let incidentRecognition = null;
let incidentDictating = false;
let incidentAutofill = { topics: {}, related: false, source: false };
let incidentReviewQueue = [];
let incidentReviewActive = false;
let incidentDictationStop = false;

// سناریوهای نمونه برای تست سریع بدون میکروفون (متن را در کادر می‌گذارد تا «تحلیل» بزنید)
const INCIDENT_SAMPLES = {
  'افراد': 'دیروز حدود ساعت ۹ شب خودم دیدم که یک نفر با شکستن قفل از خانهٔ همسایه سرقت کرد چون در قفل بود. سه نفر با هم بودند و به نظر می‌رسید مواد مخدر هم می‌فروختند. یکی‌شان اسلحه داشت.',
  'املاک': 'امروز صبح در این ملک انفجار رخ داد چون نشتی گاز بود و به نظرم حادثه بود. دیوار و شیشه‌ها تخریب شد. قبلاً هم بوی گاز می‌آمد و خودم شنیدم که همسایه‌ها گفتند.',
  'رویداد': 'یک نزاع دسته‌جمعی به‌صورت ناگهانی رخ داد و هنوز ادامه دارد. از چاقو استفاده کردند و چند نفر با هم درگیر بودند. خودم دیدم که چطور شروع شد.',
  'اشیاء': {
    'بسته مشکوک': 'یک بستهٔ بی‌صاحب با سیم و تایمر جلوی در دیدم که خیلی مشکوک بود؛ رنگش مشکی و اندازه‌اش متوسط بود.',
    'خودرو مشکوک': 'یک خودروی رهاشده با پلاک مخدوش جلوی ساختمان بود که به بمب‌گذاری مشکوکم کرد؛ رفتار راننده هم عجیب بود.',
    'پرنده': 'یک پهپاد در حال تصویربرداری و جاسوسی از اماکن بود؛ ارتفاع کمی داشت و روی ساختمان می‌چرخید.',
    'آنتن استارلینک': 'یک آنتن استارلینک برای استفادهٔ غیرمجاز و سازمان‌یافته نصب شده بود و چند نفر از آن استفاده می‌کردند.'
  }
};

function incidentSampleText() {
  const sample = INCIDENT_SAMPLES[state.category];
  if (typeof sample === 'string') return sample;
  if (sample && typeof sample === 'object') return sample[state.subtype] || Object.values(sample)[0] || '';
  return '';
}

function incidentSpeechSupported() {
  return typeof window !== 'undefined' && (window.SpeechRecognition || window.webkitSpeechRecognition);
}

function incidentNarrative() {
  const incident = ensureIncidentState();
  return typeof incident.narrative === 'string' ? incident.narrative : '';
}

function setIncidentNarrative(text) {
  const incident = ensureIncidentState();
  incident.narrative = text;
}

function incidentAutofillPending() {
  return Object.keys(incidentAutofill.topics).length > 0 || incidentAutofill.related || incidentAutofill.source;
}

function incidentTopicsForCategory(category) {
  const bank = (typeof INCIDENT_TOPIC_QUESTIONS !== 'undefined' && INCIDENT_TOPIC_QUESTIONS) || {};
  const entry = bank[canonicalCategory(category)];
  if (Array.isArray(entry)) return entry;
  // بخش اشیاء موضوعاتش به تفکیک زیربخش (subtype) تعریف شده است.
  if (isPlainRecord(entry)) {
    const subtype = state.subtype;
    return Array.isArray(entry[subtype]) ? entry[subtype] : [];
  }
  return [];
}

function ensureIncidentState(form = state.form) {
  if (!isPlainRecord(form.incident)) form.incident = {};
  const incident = form.incident;
  if (!isPlainRecord(incident.crimeTypes)) incident.crimeTypes = {};
  if (!isPlainRecord(incident.related)) incident.related = {};
  if (!isPlainRecord(incident.source)) incident.source = {};
  return incident;
}

function incidentSelectedTopicIds() {
  const incident = ensureIncidentState();
  const valid = new Set(incidentTopicsForCategory(state.category).map(topic => topic.id));
  return Object.keys(incident.crimeTypes).filter(id => valid.has(id));
}

function incidentTopicById(id) {
  return incidentTopicsForCategory(state.category).find(topic => topic.id === id) || null;
}

function incidentAnswerSummary(answers, questions) {
  if (!isPlainRecord(answers)) return '';
  const parts = questions
    .map(question => {
      const value = answers[question.key];
      return typeof value === 'string' && value.trim() ? value.trim() : '';
    })
    .filter(Boolean);
  return parts.join(' • ');
}

function incidentQuestionFieldMarkup(question, value = '') {
  const name = `incident-field-${question.key}`;
  if (question.type === 'choices') {
    const columns = question.columns === 3 ? ' choice-row--three' : question.columns === 2 ? ' choice-row--two' : '';
    const buttons = question.items.map(item => {
      const selected = item === value ? ' selected' : '';
      const safe = item.replace(/'/g, "\\'");
      return `<button type="button" class="choice-btn${selected}" data-incident-choice="${escapeHtml(question.key)}" data-value="${escapeHtml(item)}" onclick="pickIncidentChoice(this,'${safe}')">${escapeHtml(item)}</button>`;
    }).join('');
    return `<div class="field-group"><label>${escapeHtml(question.label)}</label><div class="choice-row${columns}">${buttons}</div></div>`;
  }
  if (question.type === 'select') {
    return searchableSelectMarkup(name, question.label, question.items.map(item => ({ value: item, label: item })), {
      value,
      placeholder: 'انتخاب کنید',
      className: 'incident-select',
      fieldName: `incident-select-${question.key}`
    });
  }
  const numeric = question.numeric ? ' numeric' : '';
  const numericRule = question.numeric ? ' data-numeric="true" inputmode="numeric"' : '';
  if (question.type === 'textarea') {
    return `<div class="field-group"><label>${escapeHtml(question.label)}</label><textarea class="field-textarea${numeric}" data-incident-input="${escapeHtml(question.key)}" data-auto-resize="true"${numericRule}>${escapeHtml(value)}</textarea></div>`;
  }
  return `<div class="field-group"><label>${escapeHtml(question.label)}</label><input class="field-input${numeric}" data-incident-input="${escapeHtml(question.key)}" type="text"${numericRule} value="${escapeHtml(value)}"></div>`;
}

function incidentGroupCardMarkup(group) {
  if (group === 'crimeTypes') {
    const topics = incidentTopicsForCategory(state.category);
    const incident = ensureIncidentState();
    const chips = topics.map(topic => {
      const answers = incident.crimeTypes[topic.id];
      const isSelected = isPlainRecord(answers);
      const summary = isSelected ? incidentAnswerSummary(answers, topic.questions) : '';
      const needsReview = Boolean(incidentAutofill.topics[topic.id]);
      return `<button type="button" class="incident-topic-chip${isSelected ? ' is-selected' : ''}${needsReview ? ' is-review' : ''}" data-incident-topic="${escapeHtml(topic.id)}" onclick="openIncidentTopicPopup('${topic.id.replace(/'/g, "\\'")}')" aria-pressed="${isSelected}">
        ${needsReview ? '<span class="incident-review-badge">نیازمند بازبینی</span>' : ''}
        <span class="incident-topic-chip-title">${escapeHtml(topic.label)}</span>
        ${summary ? `<span class="incident-topic-chip-summary">${escapeHtml(summary)}</span>` : `<span class="incident-topic-chip-hint">برای ثبت جزئیات انتخاب کنید</span>`}
      </button>`;
    }).join('');
    return `<section class="form-section-card incident-report-card incident-group-card" aria-labelledby="incidentCrimeTitle">
      <header class="form-section-heading">
        <h2 id="incidentCrimeTitle">نوع جرم یا تخلف</h2>
        <p>${state.category === 'اشیاء' && state.subtype ? `متناسب با «${escapeHtml(state.subtype)}»، ` : ''}موضوع یا موضوعات مرتبط را انتخاب کنید تا سؤالات هر مورد باز شود. می‌توانید چند مورد را ثبت کنید.</p>
      </header>
      <div class="incident-topic-grid">${chips || '<p class="incident-empty-note">برای این بخش موضوعی تعریف نشده است.</p>'}</div>
    </section>`;
  }
  const isRelated = group === 'related';
  const title = isRelated ? 'همکاران و افراد مرتبط' : 'نحوه اطلاع';
  const description = isRelated
    ? 'اطلاعات افراد همدست یا مرتبط با موضوع را ثبت کنید.'
    : 'به چه شکل و از چه طریق از موضوع مطلع شده‌اید؟';
  const questions = isRelated ? INCIDENT_RELATED_QUESTIONS : INCIDENT_SOURCE_QUESTIONS;
  const answers = ensureIncidentState()[group];
  const summary = incidentAnswerSummary(answers, questions);
  const hasValue = Boolean(summary);
  const needsReview = Boolean(incidentAutofill[group]);
  return `<section class="form-section-card incident-report-card incident-group-card" aria-labelledby="incidentGroup-${group}">
    <header class="form-section-heading">
      <h2 id="incidentGroup-${group}">${escapeHtml(title)}</h2>
      <p>${escapeHtml(description)}</p>
    </header>
    <button type="button" class="incident-group-open${hasValue ? ' is-filled' : ''}${needsReview ? ' is-review' : ''}" data-incident-group="${group}" onclick="openIncidentGroupPopup('${group}')">
      ${needsReview ? '<span class="incident-review-badge">نیازمند بازبینی</span>' : ''}
      <span class="incident-group-open-copy">${hasValue ? escapeHtml(summary) : 'برای پاسخ به سؤالات این بخش ضربه بزنید'}</span>
      ${iconMarkup('chevron-down', 'incident-group-open-chevron')}
    </button>
  </section>`;
}

function incidentVoiceCardMarkup() {
  const supported = incidentSpeechSupported();
  const narrative = incidentNarrative();
  const pending = incidentAutofillPending();
  const micLabel = incidentDictating ? 'در حال شنیدن… (برای توقف بزنید)' : 'شرح ماجرا را بگویید';
  const micSvg = incidentDictating
    ? '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>'
    : '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0"/><line x1="12" y1="18" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/></svg>';
  const micBlock = supported
    ? `<button type="button" class="incident-mic-btn${incidentDictating ? ' is-recording' : ''}" onclick="toggleIncidentDictation()" aria-pressed="${incidentDictating}">
        ${micSvg}<span>${micLabel}</span>
      </button>
      <p id="incidentVoiceStatus" class="incident-voice-status" role="status" aria-live="polite"></p>`
    : `<p class="incident-voice-note">مرورگر شما تبدیل گفتار به نوشتار را پشتیبانی نمی‌کند. می‌توانید متن را تایپ کنید (یا از دکمهٔ میکروفون کیبورد گوشی استفاده کنید) و سپس «تحلیل و پرکردن خودکار» را بزنید. برای قابلیت صوتی از مرورگر Chrome استفاده کنید.</p>`;
  return `<section class="form-section-card incident-voice-card" aria-labelledby="incidentVoiceTitle">
    <header class="form-section-heading">
      <h2 id="incidentVoiceTitle">دستیار صوتی گزارش</h2>
      <p>ماجرا را با صدای خود تعریف کنید؛ سامانه آن را به متن تبدیل می‌کند، فیلدها را خودکار پر می‌کند و در پایان یک دور با شما بازبینی می‌کند.</p>
    </header>
    ${micBlock}
    <textarea id="incidentNarrative" class="field-textarea incident-narrative" data-auto-resize="true" placeholder="متن گفتار شما اینجا نوشته می‌شود؛ می‌توانید ویرایش کنید.">${escapeHtml(narrative)}</textarea>
    <div class="incident-voice-actions">
      <button type="button" class="primary-button incident-analyze-btn" onclick="runIncidentAutofill()">تحلیل و پرکردن خودکار</button>
      <button type="button" class="secondary-button incident-sample-btn" onclick="insertIncidentSample()">درج متن نمونه (تست)</button>
      ${narrative.trim() ? `<button type="button" class="secondary-button incident-clear-voice" onclick="clearIncidentNarrative()">پاک کردن متن</button>` : ''}
    </div>
    ${pending ? `<div class="incident-review-banner">
      <span>موارد زیر به‌صورت خودکار استخراج شد و «نیازمند بازبینی» است. لطفاً هرکدام را تأیید یا اصلاح کنید.</span>
      <button type="button" class="incident-review-start" onclick="startIncidentReview()">شروع بازبینی گام‌به‌گام</button>
    </div>` : ''}
  </section>`;
}

function renderIncidentReport() {
  clearRetiredIncidentFields();
  ensureIncidentState();
  const body = document.getElementById('incidentReportBody');
  if (!body) return;
  body.innerHTML = `<div class="incident-report-groups">
    ${incidentVoiceCardMarkup()}
    ${incidentGroupCardMarkup('crimeTypes')}
    ${incidentGroupCardMarkup('related')}
    ${incidentGroupCardMarkup('source')}
  </div>`;
  const narrativeField = document.getElementById('incidentNarrative');
  if (narrativeField) resizeTextarea(narrativeField);
  setTimeout(normalizeVisibleNumbers, 0);
}

/* -------- پاپ‌آپ سؤالات -------- */
function incidentPopupHostMarkup(title, description, fieldsMarkup) {
  const reviewing = incidentReviewActive;
  const remaining = reviewing ? incidentReviewQueue.length : 0;
  const reviewHint = reviewing
    ? `<div class="incident-popup-review">این پاسخ‌ها به‌صورت خودکار از گفتار شما استخراج شده‌اند. لطفاً بررسی و در صورت نیاز اصلاح کنید.</div>`
    : '';
  const saveLabel = reviewing ? (remaining > 0 ? 'تأیید و ادامه' : 'تأیید و پایان') : 'ثبت پاسخ‌ها';
  return `<div class="incident-popup-overlay" data-incident-popup role="dialog" aria-modal="true" aria-label="${escapeHtml(title)}" onclick="handleIncidentPopupBackdrop(event)">
    <div class="incident-popup" role="document">
      <header class="incident-popup-head">
        <div>
          <h2>${escapeHtml(title)}</h2>
          ${description ? `<p>${escapeHtml(description)}</p>` : ''}
        </div>
        <button type="button" class="incident-popup-close" aria-label="بستن" onclick="closeIncidentPopup(false)"><span aria-hidden="true">×</span></button>
      </header>
      <div class="incident-popup-body">${reviewHint}${fieldsMarkup}</div>
      <footer class="incident-popup-foot">
        <button type="button" class="secondary-button incident-popup-cancel" onclick="closeIncidentPopup(false)">${reviewing ? 'توقف بازبینی' : 'انصراف'}</button>
        <button type="button" class="primary-button incident-popup-save" onclick="saveIncidentPopup()">${saveLabel}</button>
      </footer>
    </div>
  </div>`;
}

function mountIncidentPopup(markup) {
  removeIncidentOverlay();
  const wrapper = document.createElement('div');
  wrapper.innerHTML = markup;
  const overlay = wrapper.firstElementChild;
  document.body.appendChild(overlay);
  document.body.classList.add('incident-popup-open');
  requestAnimationFrame(() => overlay.classList.add('is-visible'));
  overlay.querySelectorAll('[data-searchable-select]').forEach(updateSearchableSelectPresentation);
  overlay.querySelectorAll('textarea[data-auto-resize="true"]').forEach(resizeTextarea);
  setTimeout(normalizeVisibleNumbers, 0);
  return overlay;
}

/* -------- دستیار صوتی: تبدیل گفتار به نوشتار -------- */
function incidentMicIconSvg(recording) {
  return recording
    ? '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>'
    : '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0"/><line x1="12" y1="18" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/></svg>';
}

function updateIncidentMicButton(recording) {
  const btn = document.querySelector('.incident-mic-btn');
  if (!btn) return;
  btn.classList.toggle('is-recording', !!recording);
  btn.setAttribute('aria-pressed', String(!!recording));
  btn.innerHTML = `${incidentMicIconSvg(recording)}<span>${recording ? 'در حال شنیدن… (برای توقف و تحلیل بزنید)' : 'شرح ماجرا را بگویید'}</span>`;
}

function setIncidentVoiceStatus(message) {
  const status = document.getElementById('incidentVoiceStatus');
  if (status) status.textContent = message || '';
}

function toggleIncidentDictation() {
  if (incidentDictating) return stopIncidentDictation(true);
  startIncidentDictation();
}

function startIncidentDictation() {
  const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!Recognition) {
    setIncidentVoiceStatus('مرورگر شما تبدیل گفتار به نوشتار را پشتیبانی نمی‌کند؛ از Chrome استفاده کنید.');
    return;
  }
  if (typeof window.isSecureContext !== 'undefined' && !window.isSecureContext) {
    setIncidentVoiceStatus('برای استفاده از میکروفون، سایت باید روی https یا localhost باز شود.');
    return;
  }

  const field = document.getElementById('incidentNarrative');
  const baseText = field ? field.value.trim() : '';
  let finalText = baseText ? baseText + ' ' : '';

  const recognition = new Recognition();
  incidentRecognition = recognition;
  incidentDictationStop = false;
  recognition.lang = 'fa-IR';
  recognition.continuous = true;
  recognition.interimResults = true;
  recognition.maxAlternatives = 1;

  recognition.onstart = () => {
    incidentDictating = true;
    updateIncidentMicButton(true);
    setIncidentVoiceStatus('در حال شنیدن… واضح صحبت کنید.');
  };
  recognition.onaudiostart = () => setIncidentVoiceStatus('میکروفون فعال است، بفرمایید…');
  recognition.onspeechstart = () => setIncidentVoiceStatus('در حال دریافت گفتار…');
  recognition.onresult = event => {
    let interim = '';
    for (let i = event.resultIndex; i < event.results.length; i++) {
      const chunk = event.results[i][0].transcript;
      if (event.results[i].isFinal) finalText += chunk + ' ';
      else interim += chunk;
    }
    const target = document.getElementById('incidentNarrative');
    if (target) {
      target.value = (finalText + interim).replace(/\s+/g, ' ').trimStart();
      resizeTextarea(target);
      setIncidentNarrative(target.value);
    }
  };
  recognition.onerror = event => {
    // «no-speech» و «aborted» کشنده نیستند؛ onend دوباره گوش دادن را ادامه می‌دهد.
    if (event.error === 'no-speech') { setIncidentVoiceStatus('هنوز صدایی نشنیدم… بلندتر صحبت کنید.'); return; }
    if (event.error === 'aborted') return;
    const messages = {
      'not-allowed': 'دسترسی میکروفون رد شد. روی قفل کنار آدرس، میکروفون را Allow کنید و صفحه را تازه کنید.',
      'service-not-allowed': 'دسترسی میکروفون رد شد. از تنظیمات مرورگر اجازه دهید.',
      'audio-capture': 'میکروفونی پیدا نشد. اتصال میکروفون و ورودی صحیح را بررسی کنید.',
      'network': 'خطای شبکه در تشخیص گفتار؛ اتصال اینترنت را بررسی کنید (این قابلیت آنلاین است).'
    };
    setIncidentVoiceStatus(messages[event.error] || ('خطا: ' + event.error));
    incidentDictationStop = true;
    incidentDictating = false;
    updateIncidentMicButton(false);
  };
  recognition.onend = () => {
    // اگر کاربر عمداً متوقف نکرده، برای عبور از مکث‌ها دوباره شروع می‌کنیم (شنیدن پیوسته).
    if (!incidentDictationStop) {
      try { recognition.start(); return; } catch (error) { /* ادامه به توقف */ }
    }
    incidentDictating = false;
    updateIncidentMicButton(false);
    const current = document.getElementById('incidentNarrative');
    if (current) setIncidentNarrative(current.value);
    persistReportDraft();
  };

  try {
    recognition.start();
    incidentDictating = true;
    updateIncidentMicButton(true);
    setIncidentVoiceStatus('در حال آماده‌سازی میکروفون… (اگر پیام مجوز آمد، Allow را بزنید)');
  } catch (error) {
    incidentDictating = false;
    updateIncidentMicButton(false);
    setIncidentVoiceStatus('شروع تشخیص گفتار ممکن نشد؛ چند لحظه بعد دوباره تلاش کنید.');
  }
}

function stopIncidentDictation(runAnalysis) {
  incidentDictationStop = true;
  incidentDictating = false;
  if (incidentRecognition) {
    try { incidentRecognition.stop(); } catch (error) { /* noop */ }
  }
  const field = document.getElementById('incidentNarrative');
  if (field) setIncidentNarrative(field.value.trim());
  updateIncidentMicButton(false);
  setIncidentVoiceStatus('');
  persistReportDraft();
  // به‌محض توقف، به‌صورت خودکار تحلیل و پرکردن انجام می‌شود تا مدل «فعال» حس شود.
  if (runAnalysis && field && field.value.trim()) {
    setTimeout(runIncidentAutofill, 150);
  }
}

function insertIncidentSample() {
  const text = incidentSampleText();
  if (!text) return alert('برای این بخش متن نمونه‌ای تعریف نشده است.');
  setIncidentNarrative(text);
  const field = document.getElementById('incidentNarrative');
  if (field) { field.value = text; resizeTextarea(field); }
  setIncidentVoiceStatus('متن نمونه درج شد. حالا «تحلیل و پرکردن خودکار» را بزنید.');
  persistReportDraft();
}

function refreshIncidentVoiceUI() {
  const field = document.getElementById('incidentNarrative');
  if (field) setIncidentNarrative(field.value);
  renderIncidentReport();
}

function clearIncidentNarrative() {
  setIncidentNarrative('');
  const field = document.getElementById('incidentNarrative');
  if (field) field.value = '';
  renderIncidentReport();
  persistReportDraft();
}

/* -------- تحلیل متن و پرکردن خودکار فیلدها -------- */
function runIncidentAutofill() {
  const field = document.getElementById('incidentNarrative');
  const text = field ? field.value.trim() : incidentNarrative();
  if (!text) return alert('ابتدا ماجرا را با صدا بگویید یا متن آن را وارد کنید.');
  setIncidentNarrative(text);
  if (typeof extractIncidentFromText !== 'function') return alert('موتور تحلیل بارگذاری نشده است.');
  const extraction = extractIncidentFromText({
    text,
    topics: incidentTopicsForCategory(state.category),
    relatedQuestions: INCIDENT_RELATED_QUESTIONS,
    sourceQuestions: INCIDENT_SOURCE_QUESTIONS
  });
  const filled = mergeIncidentExtraction(extraction);
  persistReportDraft();
  renderIncidentReport();
  if (!filled) {
    alert('از متن، موردی برای پرکردن خودکار شناسایی نشد. می‌توانید موضوع‌ها را دستی انتخاب کنید.');
    return;
  }
  setTimeout(startIncidentReview, 250);
}

function mergeIncidentExtraction(extraction) {
  const incident = ensureIncidentState();
  incidentAutofill = { topics: {}, related: false, source: false };
  let filled = false;
  Object.entries(extraction.crimeTypes || {}).forEach(([id, answers]) => {
    if (!isPlainRecord(answers) || !Object.keys(answers).length) return;
    incident.crimeTypes[id] = { ...(incident.crimeTypes[id] || {}), ...answers };
    incidentAutofill.topics[id] = true;
    filled = true;
  });
  if (isPlainRecord(extraction.related) && Object.keys(extraction.related).length) {
    incident.related = { ...incident.related, ...extraction.related };
    incidentAutofill.related = true;
    filled = true;
  }
  if (isPlainRecord(extraction.source) && Object.keys(extraction.source).length) {
    incident.source = { ...incident.source, ...extraction.source };
    incidentAutofill.source = true;
    filled = true;
  }
  return filled;
}

/* -------- کراس‌چک گام‌به‌گام با کاربر -------- */
function startIncidentReview() {
  incidentReviewQueue = [];
  Object.keys(incidentAutofill.topics).forEach(id => incidentReviewQueue.push({ kind: 'topic', id }));
  if (incidentAutofill.related) incidentReviewQueue.push({ kind: 'group', group: 'related' });
  if (incidentAutofill.source) incidentReviewQueue.push({ kind: 'group', group: 'source' });
  if (!incidentReviewQueue.length) return;
  incidentReviewActive = true;
  incidentReviewNext();
}

function incidentReviewNext() {
  const next = incidentReviewQueue.shift();
  if (!next) {
    incidentReviewActive = false;
    renderIncidentReport();
    return;
  }
  if (next.kind === 'topic') {
    if (!incidentAutofill.topics[next.id]) return incidentReviewNext();
    openIncidentTopicPopup(next.id);
  } else {
    if (!incidentAutofill[next.group]) return incidentReviewNext();
    openIncidentGroupPopup(next.group);
  }
}

function openIncidentTopicPopup(topicId) {
  const topic = incidentTopicById(topicId);
  if (!topic) return;
  const incident = ensureIncidentState();
  const answers = isPlainRecord(incident.crimeTypes[topicId]) ? incident.crimeTypes[topicId] : {};
  const fields = topic.questions.map(question => incidentQuestionFieldMarkup(question, answers[question.key] || '')).join('');
  // بستنِ پاپ‌آپ قبلی (داخل mount) context را صفر می‌کند؛ پس context را پس از mount تنظیم می‌کنیم.
  mountIncidentPopup(incidentPopupHostMarkup(topic.label, 'به سؤالات زیر تا حد امکان پاسخ دهید.', fields));
  incidentPopupContext = { kind: 'topic', topicId, questions: topic.questions };
}

function openIncidentGroupPopup(group) {
  if (group !== 'related' && group !== 'source') return;
  const questions = group === 'related' ? INCIDENT_RELATED_QUESTIONS : INCIDENT_SOURCE_QUESTIONS;
  const answers = ensureIncidentState()[group];
  const title = group === 'related' ? 'همکاران و افراد مرتبط' : 'نحوه اطلاع';
  const fields = questions.map(question => incidentQuestionFieldMarkup(question, answers[question.key] || '')).join('');
  mountIncidentPopup(incidentPopupHostMarkup(title, '', fields));
  incidentPopupContext = { kind: 'group', group, questions };
}

function pickIncidentChoice(button, value) {
  const row = button.parentElement;
  const already = button.classList.contains('selected');
  row.querySelectorAll('.choice-btn').forEach(item => item.classList.remove('selected'));
  if (!already) {
    button.classList.add('selected');
    button.dataset.value = value;
  }
  // ثبت زندهٔ انتخاب تا حتی بدون زدن دکمهٔ «ثبت»، پاسخ‌ها از دست نرود.
  persistReportDraft();
}

function collectIncidentPopupAnswers(overlay, questions) {
  const answers = {};
  questions.forEach(question => {
    if (question.type === 'choices') {
      const selected = overlay.querySelector(`[data-incident-choice="${CSS.escape(question.key)}"].selected`);
      if (selected) answers[question.key] = selected.dataset.value || selected.textContent.trim();
      return;
    }
    if (question.type === 'select') {
      const component = overlay.querySelector(`[data-select-name="${CSS.escape('incident-field-' + question.key)}"]`);
      const value = component ? searchableSelectValue(component).trim() : '';
      if (value) answers[question.key] = value;
      return;
    }
    const input = overlay.querySelector(`[data-incident-input="${CSS.escape(question.key)}"]`);
    const value = input ? input.value.trim() : '';
    if (value) answers[question.key] = value;
  });
  return answers;
}

// پاسخ‌های پاپ‌آپِ باز را در ساختار incident می‌نویسد (چه هنگام تایپ، چه هنگام ثبت).
function captureOpenIncidentPopup(incident) {
  const overlay = document.querySelector('[data-incident-popup]');
  if (!overlay || !incidentPopupContext) return false;
  const answers = collectIncidentPopupAnswers(overlay, incidentPopupContext.questions);
  if (incidentPopupContext.kind === 'topic') {
    if (Object.keys(answers).length) {
      const topic = incidentTopicById(incidentPopupContext.topicId);
      if (topic) answers.__label = topic.label;
      incident.crimeTypes[incidentPopupContext.topicId] = answers;
    } else {
      delete incident.crimeTypes[incidentPopupContext.topicId];
    }
  } else {
    incident[incidentPopupContext.group] = answers;
  }
  return true;
}

function saveIncidentPopup() {
  const incident = ensureIncidentState();
  captureOpenIncidentPopup(incident);
  // این مورد بازبینی و تأیید شد؛ نشان «نیازمند بازبینی» برداشته می‌شود.
  if (incidentPopupContext) {
    if (incidentPopupContext.kind === 'topic') delete incidentAutofill.topics[incidentPopupContext.topicId];
    else if (incidentPopupContext.kind === 'group') incidentAutofill[incidentPopupContext.group] = false;
  }
  closeIncidentPopup(true);
  renderIncidentReport();
  persistReportDraft();
  if (incidentReviewActive) setTimeout(incidentReviewNext, 220);
}

function handleIncidentPopupBackdrop(event) {
  if (event.target === event.currentTarget) closeIncidentPopup(false);
}

function removeIncidentOverlay() {
  const overlay = document.querySelector('[data-incident-popup]');
  incidentPopupContext = null;
  document.body.classList.remove('incident-popup-open');
  if (overlay) overlay.remove();
}

function closeIncidentPopup(saved) {
  const overlay = document.querySelector('[data-incident-popup]');
  incidentPopupContext = null;
  document.body.classList.remove('incident-popup-open');
  // اگر کاربر وسط بازبینی «توقف بازبینی/انصراف» بزند، بازبینی متوقف می‌شود (نشان‌ها باقی می‌مانند).
  if (saved !== true && incidentReviewActive) {
    incidentReviewActive = false;
    incidentReviewQueue = [];
  }
  if (!overlay) return;
  overlay.classList.remove('is-visible');
  setTimeout(() => overlay.remove(), 180);
}

function collectIncidentReport() {
  // مقادیر پاپ‌آپِ باز (در حال تایپ) نیز پیش از پاک‌سازی گروه‌های خالی ثبت می‌شوند.
  const data = { ...state.form };
  const incident = ensureIncidentState(data);
  captureOpenIncidentPopup(incident);
  const narrativeField = document.getElementById('incidentNarrative');
  if (narrativeField) {
    const value = narrativeField.value.trim();
    if (value) incident.narrative = value; else delete incident.narrative;
  }
  if (!Object.keys(incident.crimeTypes).length) delete incident.crimeTypes;
  if (!Object.keys(incident.related).length) delete incident.related;
  if (!Object.keys(incident.source).length) delete incident.source;
  if (!isPlainRecord(data.incident) || !Object.keys(data.incident).length) delete data.incident;
  state.form = data;
}

function incidentReportHasValue() {
  const incident = ensureIncidentState();
  return incidentSelectedTopicIds().length > 0
    || Object.keys(incident.related).length > 0
    || Object.keys(incident.source).length > 0;
}

const PROPERTY_PERSON_ROLES = Object.freeze({
  owner: Object.freeze({ title: 'مشخصات مالکین', singular: 'مالک', hasPropertyRoles: true, pageId: 'propertyOwnersPage', bodyId: 'propertyOwnersBody' }),
  resident: Object.freeze({ title: 'مشخصات ساکنین', singular: 'ساکن', hasPropertyRoles: false, pageId: 'propertyResidentsPage', bodyId: 'propertyResidentsBody' }),
  visitor: Object.freeze({ title: 'مشخصات ترددکنندگان', singular: 'ترددکننده', hasPropertyRoles: false, pageId: 'propertyVisitorsPage', bodyId: 'propertyVisitorsBody' })
});
const PROPERTY_PERSON_STAGE_ORDER = Object.freeze(['owner', 'resident', 'visitor']);
const PROPERTY_PERSON_PAGE_IDS = new Set(PROPERTY_PERSON_STAGE_ORDER.map(role => PROPERTY_PERSON_ROLES[role].pageId));
const PROPERTY_PERSON_FIELD_KEYS = Object.freeze([
  'firstName', 'lastName', 'nickname', 'phone', 'gender', 'height', 'bodyBuild',
  'face', 'hairColor', 'hairStatus', 'beard', 'appearance'
]);
const PROPERTY_PERSON_LEGACY_SUFFIXES = Object.freeze({
  firstName: 'FirstName',
  lastName: 'LastName',
  nickname: 'Nickname',
  phone: 'Phone',
  gender: 'Gender',
  height: 'Height',
  bodyBuild: 'BodyBuild',
  face: 'Face',
  hairColor: 'HairColor',
  hairStatus: 'HairStatus',
  beard: 'Beard',
  appearance: 'Appearance'
});

function propertyPersonRecordHasMeaningfulValue(person) {
  if (!isPlainRecord(person)) return false;
  return Object.values(person).some(value => value === true || (typeof value === 'string' && value.trim()));
}

function normalizedPropertyPersonRecord(value, role) {
  const source = isPlainRecord(value) ? value : {};
  const record = {};
  PROPERTY_PERSON_FIELD_KEYS.forEach(key => {
    if (typeof source[key] === 'string' && source[key].trim()) record[key] = source[key].trim();
  });
  if (role === 'owner') {
    if (source.isResident === true || source.isResident === 'بله') record.isResident = true;
    if (source.isVisitor === true || source.isVisitor === 'بله') record.isVisitor = true;
  }
  return record;
}

function legacyPropertyPersonRecord(form, role) {
  const record = {};
  Object.entries(PROPERTY_PERSON_LEGACY_SUFFIXES).forEach(([key, suffix]) => {
    const value = form[`${role}${suffix}`];
    if (typeof value === 'string' && value.trim()) record[key] = value.trim();
  });
  if (role === 'owner') {
    if (form.ownerIsResident === 'بله' || form.ownerIsResident === true) record.isResident = true;
    if (form.ownerIsVisitor === 'بله' || form.ownerIsVisitor === true) record.isVisitor = true;
  }
  return record;
}

function legacyPropertyPersonFieldNames() {
  return Object.keys(PROPERTY_PERSON_ROLES).flatMap(role => [
    ...Object.values(PROPERTY_PERSON_LEGACY_SUFFIXES).map(suffix => `${role}${suffix}`),
    ...(role === 'owner' ? ['ownerIsResident', 'ownerIsVisitor'] : [])
  ]);
}

function hasLegacyPropertyPersonFields(form) {
  return isPlainRecord(form) && legacyPropertyPersonFieldNames().some(key => form[key] !== undefined);
}

function normalizePropertyPeople(form = state.form) {
  if (!isPlainRecord(form)) return {};
  const source = isPlainRecord(form.propertyPeople) ? form.propertyPeople : {};
  const hadStructuredPeople = isPlainRecord(form.propertyPeople);
  const people = {};
  let hasEntries = false;

  Object.keys(PROPERTY_PERSON_ROLES).forEach(role => {
    let entries = Array.isArray(source[role])
      ? source[role].filter(isPlainRecord).slice(0, MAX_PROPERTY_PEOPLE).map(person => normalizedPropertyPersonRecord(person, role))
      : [];
    if (!entries.length && !Array.isArray(source[role])) {
      const legacy = legacyPropertyPersonRecord(form, role);
      if (propertyPersonRecordHasMeaningfulValue(legacy)) entries = [legacy];
    }
    people[role] = entries;
    if (entries.length) hasEntries = true;
  });

  legacyPropertyPersonFieldNames().forEach(key => delete form[key]);
  if (hadStructuredPeople || hasEntries) form.propertyPeople = people;
  else delete form.propertyPeople;
  return people;
}

function ensurePropertyPeople() {
  const people = normalizePropertyPeople();
  Object.keys(PROPERTY_PERSON_ROLES).forEach(role => {
    if (!Array.isArray(people[role]) || !people[role].length) people[role] = [{}];
  });
  state.form.propertyPeople = people;
  return people;
}

function propertyPersonInputMarkup(person, key, label, type = 'text', options = {}) {
  const value = typeof person[key] === 'string' ? escapeHtml(person[key]) : '';
  const numeric = options.numeric ? ' numeric' : '';
  const textOnly = options.textOnly ? ' text-only' : '';
  const maxLength = options.maxLength ? ` maxlength="${options.maxLength}"` : '';
  const maxValue = Number.isFinite(options.maxValue) ? ` data-max-value="${options.maxValue}"` : '';
  const validation = options.validation ? ` data-validation="${options.validation}"` : '';
  const numericRule = options.numeric ? ' data-numeric="true"' : '';
  const textRule = options.textOnly ? ' data-text-only="true"' : '';
  const placeholder = options.placeholder ? ` placeholder="${escapeHtml(options.placeholder)}"` : '';
  const attributes = `data-property-person-field="${key}" data-label="${label}"${numericRule}${textRule}${validation}${maxLength}${maxValue}`;
  if (type === 'textarea') {
    return `<div class="field-group"><label>${label}</label><textarea class="field-textarea${numeric}${textOnly}" ${attributes}${placeholder} data-auto-resize="true">${value}</textarea></div>`;
  }
  return `<div class="field-group"><label>${label}</label><input class="field-input${numeric}${textOnly}" ${attributes}${placeholder} type="${type}" inputmode="${options.numeric ? 'numeric' : 'text'}" value="${value}"></div>`;
}

function propertyPersonChoicesMarkup(role, index, person, key, label, items) {
  return `<div class="field-group"><label>${label}</label><div class="choice-row choice-row--three" data-property-person-choice data-property-person-choice-key="${key}">${items.map(item => {
    const selected = person[key] === item ? ' selected' : '';
    return `<button type="button" class="choice-btn${selected}" data-value="${item}" onclick="pickPropertyPersonChoice(this,'${role}',${index},'${key}','${item}')">${item}</button>`;
  }).join('')}</div></div>`;
}

function propertyPersonPackageMarkup(role, person, index, total) {
  const config = PROPERTY_PERSON_ROLES[role];
  const removeButton = total > 1
    ? `<button class="property-person-remove-button" type="button" onclick="removePropertyPerson('${role}',${index})">حذف این فرد</button>`
    : '';
  const ownerToggles = config.hasPropertyRoles
    ? `<div class="property-person-role-toggles" role="group" aria-label="وضعیت مالک نسبت به ملک">
        <label class="property-person-role-toggle"><input type="checkbox" data-property-person-checkbox="isResident" onchange="persistReportDraft()"${person.isResident ? ' checked' : ''}><span>ساکن هست</span></label>
        <label class="property-person-role-toggle"><input type="checkbox" data-property-person-checkbox="isVisitor" onchange="persistReportDraft()"${person.isVisitor ? ' checked' : ''}><span>تردد میکند</span></label>
      </div>`
    : '';

  return `<article class="property-person-package" data-property-person-package data-property-person-role="${role}" data-property-person-index="${index}">
    <header class="property-person-package-heading"><h4>${config.singular} ${faDigits(index + 1)}</h4>${removeButton}</header>
    <div class="property-person-basic-fields">
      ${propertyPersonInputMarkup(person, 'firstName', 'نام', 'text', { textOnly: true })}
      ${propertyPersonInputMarkup(person, 'lastName', 'نام خانوادگی', 'text', { textOnly: true })}
      ${propertyPersonInputMarkup(person, 'nickname', 'شهرت', 'text', { textOnly: true })}
      ${propertyPersonInputMarkup(person, 'phone', 'شماره تماس', 'text', { numeric: true, maxLength: 11, validation: 'phone' })}
    </div>
    ${propertyPersonChoicesMarkup(role, index, person, 'gender', 'جنسیت', ['مرد', 'زن', 'نامشخص'])}
    ${ownerToggles}
    <h5 class="property-person-appearance-title">مشخصات ظاهری</h5>
    <div class="property-person-appearance-fields">
      ${propertyPersonInputMarkup(person, 'height', 'قد', 'text', { numeric: true, maxLength: 3, maxValue: 250, validation: 'height' })}
      ${propertyPersonChoicesMarkup(role, index, person, 'bodyBuild', 'اندام', ['لاغر', 'معمولی', 'چاق'])}
      ${propertyPersonInputMarkup(person, 'face', 'رنگ پوست', 'text', { textOnly: true })}
      ${propertyPersonInputMarkup(person, 'hairColor', 'رنگ مو', 'text', { textOnly: true })}
      ${propertyPersonInputMarkup(person, 'hairStatus', 'وضعیت موی سر', 'text', { textOnly: true })}
      ${propertyPersonInputMarkup(person, 'beard', 'محاسن', 'text', { textOnly: true })}
      ${propertyPersonInputMarkup(person, 'appearance', 'ویژگی خاص', 'textarea', { placeholder: 'شامل زخم، تتو، معلولیت و موارد بارز دیگر' })}
    </div>
  </article>`;
}

function propertyPersonRoleSectionMarkup(role, people) {
  const config = PROPERTY_PERSON_ROLES[role];
  const entries = Array.isArray(people[role]) ? people[role] : [{}];
  const atLimit = entries.length >= MAX_PROPERTY_PEOPLE;
  return `<section class="property-person-role-section" aria-labelledby="propertyPeople${role}Title">
    <h3 id="propertyPeople${role}Title" class="property-person-group-title">${config.title}:</h3>
    <div class="property-person-package-list">${entries.map((person, index) => propertyPersonPackageMarkup(role, person, index, entries.length)).join('')}</div>
    <button class="property-person-add-button button-with-icon" type="button" onclick="addPropertyPerson('${role}')"${atLimit ? ' disabled' : ''}>${iconMarkup('plus', 'property-person-add-icon')}<span>افزودن ${config.singular} دیگر${atLimit ? ` (حداکثر ${faDigits(MAX_PROPERTY_PEOPLE)})` : ''}</span></button>
  </section>`;
}

function propertyPersonRoleForPage(pageId) {
  return PROPERTY_PERSON_STAGE_ORDER.find(role => PROPERTY_PERSON_ROLES[role].pageId === pageId) || '';
}

function propertyPersonBody(role) {
  const config = PROPERTY_PERSON_ROLES[role];
  return config ? document.getElementById(config.bodyId) : null;
}

function renderPropertyPersonPage(role) {
  const body = propertyPersonBody(role);
  if (!body || !isPropertyReport() || !PROPERTY_PERSON_ROLES[role]) return;
  const people = ensurePropertyPeople();
  body.innerHTML = propertyPersonRoleSectionMarkup(role, people);
  body.querySelectorAll('textarea[data-auto-resize="true"]').forEach(resizeTextarea);
}

function collectPropertyPeopleDetails(role = propertyPersonRoleForPage(activePageId())) {
  if (!isPropertyReport() || !PROPERTY_PERSON_ROLES[role]) return;
  const body = propertyPersonBody(role);
  if (!body) return;
  const people = ensurePropertyPeople();
  body.querySelectorAll('[data-property-person-package]').forEach(card => {
    const index = Number(card.dataset.propertyPersonIndex);
    if (!Number.isInteger(index) || index < 0) return;
    const person = {};
    card.querySelectorAll('[data-property-person-field]').forEach(input => {
      const key = input.dataset.propertyPersonField;
      const value = typeof input.value === 'string' ? input.value.trim() : '';
      if (PROPERTY_PERSON_FIELD_KEYS.includes(key) && value) person[key] = value;
    });
    card.querySelectorAll('[data-property-person-choice]').forEach(choiceRow => {
      const key = choiceRow.dataset.propertyPersonChoiceKey;
      const selected = choiceRow.querySelector('.selected');
      if (PROPERTY_PERSON_FIELD_KEYS.includes(key) && selected) person[key] = selected.dataset.value || selected.textContent.trim();
    });
    if (role === 'owner') {
      card.querySelectorAll('[data-property-person-checkbox]').forEach(checkbox => {
        const key = checkbox.dataset.propertyPersonCheckbox;
        if ((key === 'isResident' || key === 'isVisitor') && checkbox.checked) person[key] = true;
      });
    }
    if (!Array.isArray(people[role])) people[role] = [];
    people[role][index] = normalizedPropertyPersonRecord(person, role);
  });
  state.form.propertyPeople = people;
}

function pickPropertyPersonChoice(button, role, index, key, value) {
  if (!PROPERTY_PERSON_ROLES[role] || !PROPERTY_PERSON_FIELD_KEYS.includes(key)) return;
  collectPropertyPeopleDetails(role);
  const people = ensurePropertyPeople();
  const person = people[role][index];
  if (!person) return;
  person[key] = value;
  const row = button.parentElement;
  row.querySelectorAll('.choice-btn').forEach(item => item.classList.remove('selected'));
  button.classList.add('selected');
  persistReportDraft();
}

function addPropertyPerson(role) {
  if (!PROPERTY_PERSON_ROLES[role]) return;
  collectPropertyPeopleDetails(role);
  const people = ensurePropertyPeople();
  if (people[role].length >= MAX_PROPERTY_PEOPLE) return;
  people[role].push({});
  renderPropertyPersonPage(role);
  persistReportDraft();
}

function removePropertyPerson(role, index) {
  if (!PROPERTY_PERSON_ROLES[role]) return;
  collectPropertyPeopleDetails(role);
  const people = ensurePropertyPeople();
  if (!Number.isInteger(index) || index < 0 || index >= people[role].length) return;
  people[role].splice(index, 1);
  if (!people[role].length) people[role].push({});
  renderPropertyPersonPage(role);
  persistReportDraft();
}

function openPropertyPeoplePage(role) {
  const config = PROPERTY_PERSON_ROLES[role];
  if (!isPropertyReport() || !config) return openLocation();
  renderPropertyPersonPage(role);
  showPage(config.pageId);
}

function continuePropertyPeoplePage(role) {
  const body = propertyPersonBody(role);
  collectPropertyPeopleDetails(role);
  if (!validateVisibleFormFields(body)) return;
  const currentIndex = PROPERTY_PERSON_STAGE_ORDER.indexOf(role);
  const nextRole = PROPERTY_PERSON_STAGE_ORDER[currentIndex + 1];
  if (nextRole) return openPropertyPeoplePage(nextRole);
  openPropertyVehicles();
}

function backFromPropertyPeoplePage(role) {
  collectPropertyPeopleDetails(role);
  const currentIndex = PROPERTY_PERSON_STAGE_ORDER.indexOf(role);
  const previousRole = PROPERTY_PERSON_STAGE_ORDER[currentIndex - 1];
  if (previousRole) return openPropertyPeoplePage(previousRole);
  openLocation();
}

function openPropertyOwners() { return openPropertyPeoplePage('owner'); }
function continuePropertyOwners() { return continuePropertyPeoplePage('owner'); }
function backFromPropertyOwners() { return backFromPropertyPeoplePage('owner'); }
function openPropertyResidents() { return openPropertyPeoplePage('resident'); }
function continuePropertyResidents() { return continuePropertyPeoplePage('resident'); }
function backFromPropertyResidents() { return backFromPropertyPeoplePage('resident'); }
function openPropertyVisitors() { return openPropertyPeoplePage('visitor'); }
function continuePropertyVisitors() { return continuePropertyPeoplePage('visitor'); }
function backFromPropertyVisitors() { return backFromPropertyPeoplePage('visitor'); }

function renderPropertyVehicles() {
  const body = document.getElementById('propertyVehiclesBody');
  if (!body || !isPropertyReport()) return;
  body.innerHTML = vehicleCollectionMarkup();
  body.querySelectorAll('[data-searchable-select]').forEach(updateSearchableSelectPresentation);
  body.querySelectorAll('textarea[data-auto-resize="true"]').forEach(resizeTextarea);
}

function collectPropertyVehicleDetails() {
  if (!isPropertyReport()) return;
  const data = { ...state.form };
  clearRetiredPropertyFields(data);
  const body = document.getElementById('propertyVehiclesBody');
  collectVehiclePackages(data, body || undefined);
  state.form = data;
}

function openPropertyVehicles() {
  if (!isPropertyReport()) return openLocation();
  renderPropertyVehicles();
  showPage('propertyVehiclesPage');
}

function continuePropertyVehicles() {
  const body = document.getElementById('propertyVehiclesBody');
  collectPropertyVehicleDetails();
  if (!validateVisibleFormFields(body)) return;
  openPropertySecurity();
}

function backFromPropertyVehicles() {
  collectPropertyVehicleDetails();
  openPropertyVisitors();
}

function renderPropertySecurity() {
  const body = document.getElementById('propertySecurityBody');
  if (!body || !isPropertyReport()) return;
  body.innerHTML = `${field('security', 'سیستم حفاظت و کنترل', 'textarea')}${field('specialSecurity', 'اقدامات حفاظتی و کنترل خاص', 'textarea')}`;
  restoreFormValuesIn(body);
  body.querySelectorAll('textarea[data-auto-resize="true"]').forEach(resizeTextarea);
}

function collectPropertySecurityDetails() {
  if (!isPropertyReport()) return;
  const body = document.getElementById('propertySecurityBody');
  if (!body) return;
  const data = { ...state.form };
  body.querySelectorAll('[data-field]').forEach(element => {
    const value = element.value.trim();
    if (value) data[element.dataset.field] = value;
    else delete data[element.dataset.field];
  });
  state.form = data;
}

function openPropertySecurity() {
  if (!isPropertyReport()) return openLocation();
  renderPropertySecurity();
  showPage('propertySecurityPage');
}

function continuePropertySecurity() {
  const body = document.getElementById('propertySecurityBody');
  collectPropertySecurityDetails();
  if (!validateVisibleFormFields(body)) return;
  openTime();
}

function backFromPropertySecurity() {
  collectPropertySecurityDetails();
  openPropertyVehicles();
}

function propertyForm() {
  return [
    formSection('شرح و جزئیات وقوع',
      `${field('suspicionReason','دلایل مشکوک بودن ملک','textarea')}${field('source','نحوه اطلاع','textarea')}`,
      'دلیل گزارش و نحوه اطلاع خود را ثبت کنید.')
  ];
}

function objectForm(subtype) {
  if (subtype === 'بسته مشکوک') {
    return [
      formSection('مشخصات بسته',
        `${field('objectType','نوع شیء مشکوک')}${choices('packageType','نوع بسته‌بندی',['پلمپ','چسب','عادی','نامشخص'])}${field('specialSigns','علائم خاص و ویژه','textarea')}`,
        'مشخصات قابل مشاهده بسته یا شیء را وارد کنید.'),
      formSection('محل و علت گزارش',
        `${field('packageAddress','آدرس محل قرارگیری','textarea')}${field('suspicionReason','علت مشکوک بودن بسته','textarea')}${field('source','نحوه اطلاع','textarea')}`,
        'محل قرارگیری، علت گزارش و نحوه اطلاع خود را ثبت کنید.')
    ];
  }
  if (subtype === 'خودرو مشکوک') {
    return [
      formSection('مشخصات خودرو',
        `${field('vehicleType','عنوان وسیله نقلیه')}${field('vehicleColor','رنگ')}${field('vehiclePlate','پلاک')}`,
        'مشخصات ظاهری و پلاک خودرو را وارد کنید.'),
      formSection('مشاهده و گزارش',
        `${field('vehicleAddress','محل مشاهده','textarea')}${field('vehicleReason','علت مشکوک بودن خودرو','textarea')}${field('riderAppearance','مشخصات ظاهری راکب','textarea')}${field('vehicleTime','ساعت مشاهده، توقف یا تردد','text')}${field('source','نحوه اطلاع','textarea')}`,
        'جزئیات مشاهده خودرو و نحوه اطلاع خود را ثبت کنید.')
    ];
  }
  if (subtype === 'پرنده') {
    return [
      formSection('مشخصات و حرکت پرنده',
        `${field('birdType','نوع پرنده')}${field('birdVisible','مشخصات قابل رؤیت','textarea')}${field('birdSound','صدا','textarea')}${field('birdDirection','مسیر حرکت')}${field('birdSpeed','سرعت حرکت')}${choices('birdMotion','متحرک یا ثابت',['متحرک','ثابت','نامشخص'])}`,
        'ویژگی‌های قابل مشاهده و نحوه حرکت پرنده را وارد کنید.'),
      formSection('سابقه و مستندات',
        `${yesNo('birdRepeat','تکرار رؤیت در گذشته')}${field('birdObservation','نحوه مشاهده منبع','textarea')}${field('birdSourceRelation','آشنایی منبع با موضوع','textarea')}${field('birdEvidence','مستندات احتمالی','textarea')}`,
        'سابقه مشاهده، ارتباط منبع و مستندات احتمالی را ثبت کنید.')
    ];
  }
  if (subtype === 'کالا') {
    return [
      formSection('مشخصات کالا',
        `${field('goodsType','نوع کالا')}${field('goodsBrand','برند یا سازنده')}${field('goodsModel','مدل یا مشخصات')}${field('goodsQuantity','تعداد','text',{numeric:true})}${field('goodsPackaging','نوع بسته‌بندی')}`,
        'مشخصات اصلی کالا را وارد کنید.'),
      formSection('مبدأ، مقصد و گزارش',
        `${field('goodsOrigin','مبدأ یا محل تهیه')}${field('goodsDestination','مقصد یا محل نگهداری','textarea')}${field('goodsReason','علت اهمیت یا مشکوک بودن','textarea')}${field('source','نحوه اطلاع','textarea')}`,
        'مسیر کالا، علت گزارش و نحوه اطلاع خود را ثبت کنید.')
    ];
  }
  return [
    formSection('محل و مشخصات آنتن',
      `${field('starlinkAddress','آدرس محل نصب آنتن','textarea')}${field('starlinkOwners','مشخصات صاحبان و استفاده‌کنندگان','textarea')}${field('starlinkAppearance','مشخصات ظاهری آنتن','textarea')}`,
      'محل نصب و مشخصات قابل مشاهده آنتن را وارد کنید.'),
    formSection('علت استفاده و منبع',
      `${field('starlinkReason','علت استفاده','textarea')}${field('source','نحوه اطلاع','textarea')}${field('sourceRelation','زمان و نحوه آشنایی منبع با موضوع','textarea')}`,
      'علت استفاده و نحوه اطلاع یا آشنایی خود با موضوع را ثبت کنید.')
  ];
}

function phenomenonForm(subtype) {
  if (subtype === 'تجمع، تحصن یا اغتشاش') {
    return [
      formSection('محل و حاضران',
        `${field('phenomenonAddress','آدرس و محل وقوع','textarea')}${field('participantCount','تعداد افراد حاضر و شرکت‌کننده','text',{numeric:true})}`,
        'محل رخداد و تعداد تقریبی افراد حاضر را وارد کنید.'),
      formSection('وضعیت تجمع',
        `${field('participantActions','اقدامات شرکت‌کنندگان','textarea')}${field('signsSlogans','دستنوشته‌ها، شعارها و خواسته‌ها','textarea')}${field('leaders','مشخصات لیدرها','textarea')}${field('futureActions','اقدامات احتمالی آینده','textarea')}${field('formation','نحوه شکل‌گیری پدیده','textarea')}${yesNo('history','سابقه قبلی پدیده')}`,
        'روند شکل‌گیری، وضعیت فعلی و اقدامات احتمالی را شرح دهید.'),
      formSection('اطلاع‌رسانی و منبع',
        `${field('callMethod','نحوه فراخوان و اطلاع‌رسانی','textarea')}${field('source','نحوه اطلاع','textarea')}`,
        'روش اطلاع‌رسانی و نحوه اطلاع خود را ثبت کنید.')
    ];
  }
  return [
    formSection('محل و خسارت',
      `${field('eventAddress','آدرس و محل رخداد','textarea')}${field('importance','اهمیت مکان مورد تهدید','textarea')}${field('damage','خسارت‌های جانی و مالی و تخریب','textarea')}`,
      'محل رخداد و خسارت‌های واردشده را ثبت کنید.'),
    formSection('عوامل و نحوه وقوع',
      `${field('suspects','مشخصات مظنونین احتمالی','textarea')}${field('responders','حضور یا عدم حضور نیروهای خدماتی و مأمورین','textarea')}${field('eventCause','نحوه وقوع و چگونگی آغاز و گسترش','textarea')}${field('intent','انگیزه یا عامل احتمالی در صورت عمدی بودن','textarea')}${field('source','نحوه اطلاع','textarea')}`,
      'عوامل احتمالی، نحوه رخداد و منبع اطلاع را وارد کنید.')
  ];
}

function nextFormSection() {
  const sections = buildForm(state.category, state.subtype);
  if (state.formStep >= sections.length - 1) return continueForm();
  collectForm();
  if (!validateVisibleFormFields()) return;
  state.formStep += 1;
  renderFormSection(sections);
  persistReportDraft();
  window.scrollTo({ top: 0, behavior: 'instant' });
}

function previousFormSection() {
  if (state.formStep === 0) return;
  collectForm();
  state.formStep -= 1;
  renderFormSection();
  persistReportDraft();
  window.scrollTo({ top: 0, behavior: 'instant' });
}

function pickChoice(button, name, value) {
  const row = button.parentElement;
  row.querySelectorAll('.choice-btn').forEach(item => item.classList.remove('selected'));
  button.classList.add('selected');
  button.dataset.value = value;
  persistReportDraft();
}

function collectForm() {
  const data = { ...state.form };
  delete data.priority;
  delete data.weight;
  clearRetiredIncidentFields(data);
  if (state.category === 'املاک') clearRetiredPropertyFields(data);
  if (document.querySelector('[data-person-contact-fields]')) delete data.phoneHome;
  document.querySelectorAll('#formBody [data-field], #incidentReportBody [data-field]').forEach(element => {
    const value = element.value.trim();
    if (value) data[element.dataset.field] = value;
    else delete data[element.dataset.field];
  });
  document.querySelectorAll('#formBody [data-choice], #incidentReportBody [data-choice]').forEach(row => {
    const selected = row.querySelector('.selected');
    if (selected) data[row.dataset.choice] = selected.dataset.value || selected.textContent.trim();
    else delete data[row.dataset.choice];
  });
  collectLandlineContactFields(data, document.getElementById('formBody'));
  collectSocialLinks(data);
  const vehicleScope = isPropertyReport()
    ? document.getElementById('propertyVehiclesBody')
    : document.getElementById('formBody');
  collectVehiclePackages(data, vehicleScope || undefined);
  state.form = data;
}

function restoreFormValuesIn(scope) {
  if (!scope) return;
  Object.entries(state.form).forEach(([name, value]) => {
    if (typeof value !== 'string') return;
    const fieldElement = scope.querySelector(`[data-field="${CSS.escape(name)}"]`);
    if (fieldElement) fieldElement.value = value;
    const row = scope.querySelector(`[data-choice="${CSS.escape(name)}"]`);
    if (row) {
      row.querySelectorAll('.choice-btn').forEach(button => {
        if (button.textContent.trim() === value) {
          button.classList.add('selected');
          button.dataset.value = value;
        }
      });
    }
  });
  scope.querySelectorAll('[data-searchable-select]').forEach(updateSearchableSelectPresentation);
  scope.querySelectorAll('textarea[data-auto-resize="true"]').forEach(resizeTextarea);
}

function restoreFormValues() {
  restoreFormValuesIn(document.getElementById('formBody'));
  restoreFormValuesIn(document.getElementById('incidentReportBody'));
}

function continueForm() {
  collectForm();
  if (!validateVisibleFormFields()) return;
  if (!hasMeaningfulFormValue()) return alert('حداقل یکی از اطلاعات گزارش را وارد کنید.');
  if (state.category === 'املاک') return openDocuments();
  openTime();
}

function backFromForm() {
  if (state.formStep > 0) return previousFormSection();
  collectForm();
  if (state.category === 'املاک') return openTime();
  if (state.category === 'اشیاء') return showPage('objectTypePage');
  if (state.category === 'رویداد') return showPage('phenomenonTypePage');
  showPage('categoryPage');
}

function backFromTime() {
  captureTimeDraft();
  if (state.category === 'املاک') return openPropertySecurity();
  showPage('formPage');
}

function openTime() {
  restoreTimeFields();
  showPage('timePage');
}

function restoreTimeFields() {
  document.querySelectorAll('#timePage .choice-btn').forEach(button => button.classList.remove('selected'));
  const mode = state.time.mode === 'الان' ? 'اکنون' : state.time.mode;
  if (state.time.mode === 'الان') state.time.mode = mode;
  if (mode) {
    const selected = document.querySelector(`#timePage .choice-btn[data-time="${CSS.escape(mode)}"]`);
    if (selected) selected.classList.add('selected');
  }
  document.getElementById('exactTime').hidden = mode !== 'دقیق';
  document.getElementById('approxTime').hidden = mode !== 'تقریبی';

  const savedTimeDraft = timeDraft;
  const [year, month, day] = !savedTimeDraft && state.time.date ? state.time.date.split('/') : [];
  document.getElementById('dateYear').value = savedTimeDraft ? savedTimeDraft.year : faDigits(year || '');
  document.getElementById('dateMonth').value = savedTimeDraft ? savedTimeDraft.month : faDigits(month || '');
  document.getElementById('dateDay').value = savedTimeDraft ? savedTimeDraft.day : faDigits(day || '');
  document.getElementById('timeClock').value = savedTimeDraft ? savedTimeDraft.clock : state.time.clock || '';
  document.getElementById('approxText').value = savedTimeDraft ? savedTimeDraft.approximate : state.time.approximate || '';
}

function setTimeMode(button, mode) {
  document.querySelectorAll('#timePage .choice-btn').forEach(item => item.classList.remove('selected'));
  button.classList.add('selected');
  state.time = { mode };
  if (mode === 'اکنون') state.time.selectedAt = new Date().toISOString();
  document.getElementById('exactTime').hidden = mode !== 'دقیق';
  document.getElementById('approxTime').hidden = mode !== 'تقریبی';
  persistReportDraft();
}

function continueTime() {
  if (!state.time.mode) return alert('زمان را مشخص کنید.');
  if (state.time.mode === 'دقیق') {
    const day = enDigits(document.getElementById('dateDay').value);
    const month = enDigits(document.getElementById('dateMonth').value);
    const year = enDigits(document.getElementById('dateYear').value);
    const clock = document.getElementById('timeClock').value.trim();
    const dayNumber = Number(day), monthNumber = Number(month), yearNumber = Number(year);
    const clockPattern = /^(?:[01]?[0-9]|2[0-3]):[0-5][0-9]$/;
    if (!day || !month || !year || !clock) return alert('تاریخ و ساعت را کامل کنید.');
    if (dayNumber < 1 || dayNumber > 31 || monthNumber < 1 || monthNumber > 12 || yearNumber < 1300 || yearNumber > 1500) return alert('تاریخ واردشده معتبر نیست.');
    if (!clockPattern.test(enDigits(clock))) return alert('ساعت را به شکل ساعت:دقیقه وارد کنید.');
    state.time.date = `${year}/${month}/${day}`;
    state.time.clock = clock;
  }
  if (state.time.mode === 'تقریبی') {
    const approximate = document.getElementById('approxText').value.trim();
    if (!approximate) return alert('زمان تقریبی را وارد کنید.');
    state.time.approximate = approximate;
  }
  if (state.category === 'املاک') return openIncidentReport();
  openLocation();
}

function isPropertyReport() {
  return state.category === 'املاک';
}

function allowedLocationModes() {
  return isPropertyReport() ? ['current', 'map'] : ['current', 'map', 'unknown'];
}

function normalizeLocationForCategory() {
  if (!isPropertyReport()) return;
  if (state.location.mode === 'unknown') {
    state.location = {};
    return;
  }
  delete state.location.province;
  delete state.location.city;
}

function setLocationModeAvailability() {
  const hasUnknownMode = !isPropertyReport();
  document.querySelectorAll('#locationPage [data-location-mode="unknown"]').forEach(button => {
    button.hidden = !hasUnknownMode;
    button.disabled = !hasUnknownMode;
    button.style.display = hasUnknownMode ? '' : 'none';
    button.setAttribute('aria-hidden', String(!hasUnknownMode));
  });
}

function locationData() {
  const data = typeof window !== 'undefined' ? window.IRAN_COUNTIES_BY_PROVINCE : null;
  return isPlainRecord(data) ? data : {};
}

function locationProvinceItems() {
  return Object.keys(locationData()).map(name => ({ value: name, label: name, search: name }));
}

function locationCountyItems(province) {
  const counties = locationData()[province];
  return Array.isArray(counties) ? counties.map(name => ({ value: name, label: name, search: name })) : [];
}

function setLocationModeVisual(mode) {
  document.querySelectorAll('#locationPage [data-location-mode]').forEach(button => {
    const selected = button.dataset.locationMode === mode;
    button.classList.toggle('selected', selected);
    button.setAttribute('aria-pressed', String(selected));
  });
}

function locationDetailsMarkup() {
  const value = key => escapeHtml(state.location[key] || '');
  return `<section class="location-details-section" aria-labelledby="locationDetailsTitle">
    <h2 id="locationDetailsTitle" class="location-details-title">جزئیات مکان وقوع</h2>
    <div class="location-details-grid">
      <div class="field-group"><label for="postalCode">کدپستی</label><input id="postalCode" class="field-input numeric" type="text" inputmode="numeric" maxlength="10" data-numeric="true" autocomplete="postal-code" value="${value('postalCode')}"></div>
      <div class="field-group"><label for="buildingPlaque">پلاک</label><input id="buildingPlaque" class="field-input" type="text" autocomplete="off" value="${value('buildingPlaque')}"></div>
      <div class="field-group"><label for="floor">طبقه</label><input id="floor" class="field-input" type="text" autocomplete="off" value="${value('floor')}"></div>
      <div class="field-group"><label for="unit">واحد</label><input id="unit" class="field-input" type="text" autocomplete="off" value="${value('unit')}"></div>
    </div>
  </section>`;
}

function renderLocationFields() {
  const box = document.getElementById('locationFields');
  if (!box) return;
  const mode = state.location.mode;
  if (!allowedLocationModes().includes(mode)) {
    box.innerHTML = '<p class="location-mode-guidance">ابتدا یکی از روش‌های تعیین مکان وقوع را انتخاب کنید.</p>';
    return;
  }

  const unknown = mode === 'unknown';
  const province = unknown ? state.location.province || '' : '';
  const city = unknown ? state.location.city || '' : '';
  const counties = locationCountyItems(province);
  if (unknown) clearLocationDetailFields();
  else normalizeLocationDetailFields();
  const regionFields = unknown ? `<div class="location-region-fields">
    ${searchableSelectMarkup('locationProvince', 'استان مکان وقوع', locationProvinceItems(), { inputId: 'province', value: province, placeholder: 'استان را انتخاب کنید' })}
    ${searchableSelectMarkup('locationCounty', 'شهرستان مکان وقوع', counties, { inputId: 'city', value: city, placeholder: province ? 'شهرستان را انتخاب کنید' : 'ابتدا استان را انتخاب کنید', disabled: !province })}
  </div>` : '';
  const detailsFields = unknown ? '' : locationDetailsMarkup();
  const peopleUnknownGuidance = unknown && state.category === 'افراد'
    ? '<p class="location-unknown-guidance">حداقل استان و شهرستان محل وقوع را وارد کنید.</p>'
    : '';

  box.innerHTML = `${peopleUnknownGuidance}${regionFields}${detailsFields}`;
  box.querySelectorAll('[data-searchable-select]').forEach(updateSearchableSelectPresentation);
  box.querySelectorAll('input, textarea').forEach(normalizeFieldValue);
}

function updateLocationCountyPicker(province) {
  const component = document.querySelector('#locationFields [data-select-name="locationCounty"]');
  if (!component) return;
  const counties = locationCountyItems(province);
  setSearchableSelectItems(component, counties, {
    value: '',
    disabled: !province,
    placeholder: province ? 'شهرستان را انتخاب کنید' : 'ابتدا استان را انتخاب کنید'
  });
}

function clearLocationMarker() {
  if (marker && reportMap && typeof reportMap.removeLayer === 'function') reportMap.removeLayer(marker);
  marker = null;
}

function hideReportMap() {
  const wrap = document.getElementById('mapWrap');
  if (wrap) wrap.classList.remove('visible');
}

function initMap() {
  if (reportMap) return true;
  if (typeof L === 'undefined') return false;
  const mapElement = document.getElementById('reportMap');
  if (!mapElement) return false;
  reportMap = L.map(mapElement, { zoomControl: true, attributionControl: true }).setView([35.6892, 51.3890], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(reportMap);
  reportMap.on('click', event => setMapPoint(event.latlng.lat, event.latlng.lng));
  return true;
}

function showReportMap() {
  const wrap = document.getElementById('mapWrap');
  const status = document.getElementById('locationStatus');
  if (!initMap()) {
    if (status) status.textContent = 'نمایش نقشه در این مرورگر در دسترس نیست.';
    return false;
  }
  if (wrap) wrap.classList.add('visible');
  setTimeout(() => {
    if (reportMap && typeof reportMap.invalidateSize === 'function') reportMap.invalidateSize();
  }, 100);
  return true;
}

function restoreLocationMarker() {
  const latitude = Number(state.location.latitude);
  const longitude = Number(state.location.longitude);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || !reportMap || typeof L === 'undefined') return;
  if (marker) marker.setLatLng([latitude, longitude]);
  else {
    marker = L.marker([latitude, longitude], { draggable: true }).addTo(reportMap);
    marker.on('dragend', event => {
      if (!['current', 'map'].includes(state.location.mode)) return;
      const point = event.target.getLatLng();
      state.location.latitude = point.lat;
      state.location.longitude = point.lng;
      persistReportDraft();
    });
  }
  reportMap.setView([latitude, longitude], Math.max(reportMap.getZoom ? reportMap.getZoom() : 6, 13));
}

function setMapPoint(lat, lng) {
  if (!['current', 'map'].includes(state.location.mode) || !initMap()) return;
  const latitude = Number(lat);
  const longitude = Number(lng);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
  if (marker) marker.setLatLng([latitude, longitude]);
  else {
    marker = L.marker([latitude, longitude], { draggable: true }).addTo(reportMap);
    marker.on('dragend', event => {
      if (!['current', 'map'].includes(state.location.mode)) return;
      const point = event.target.getLatLng();
      state.location.latitude = point.lat;
      state.location.longitude = point.lng;
      persistReportDraft();
    });
  }
  state.location.latitude = latitude;
  state.location.longitude = longitude;
  state.location.known = true;
  const status = document.getElementById('locationStatus');
  if (status) status.textContent = state.location.mode === 'current' ? 'موقعیت فعلی ثبت شد.' : 'موقعیت انتخاب شد.';
  persistReportDraft();
}

function requestCurrentLocation() {
  const status = document.getElementById('locationStatus');
  if (typeof navigator === 'undefined' || !navigator.geolocation) {
    if (status) status.textContent = 'دسترسی به موقعیت فعلی در این مرورگر ممکن نیست؛ می‌توانید «انتخاب روی نقشه» را انتخاب کنید.';
    return;
  }
  const requestId = ++locationRequestSequence;
  if (status) status.textContent = 'در حال دریافت موقعیت فعلی؛ در صورت درخواست مرورگر، دسترسی GPS را تأیید کنید.';
  navigator.geolocation.getCurrentPosition(
    position => {
      if (state.location.mode !== 'current' || requestId !== locationRequestSequence) return;
      setMapPoint(position.coords.latitude, position.coords.longitude);
      if (reportMap && typeof reportMap.setView === 'function') reportMap.setView([position.coords.latitude, position.coords.longitude], 15);
    },
    () => {
      if (state.location.mode !== 'current' || requestId !== locationRequestSequence) return;
      if (status) status.textContent = 'دسترسی به موقعیت فعلی ممکن نشد؛ می‌توانید «انتخاب روی نقشه» را انتخاب کنید.';
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
}

function selectLocationMode(mode) {
  if (!allowedLocationModes().includes(mode)) return;
  if (state.location.mode !== mode) {
    locationRequestSequence += 1;
    clearLocationMarker();
    state.location = { mode };
  }

  setLocationModeVisual(mode);
  renderLocationFields();
  const status = document.getElementById('locationStatus');
  if (mode === 'unknown') {
    hideReportMap();
    if (status) status.textContent = '';
  } else {
    showReportMap();
    restoreLocationMarker();
    if (mode === 'map' && status && !Number.isFinite(Number(state.location.latitude))) {
      status.textContent = 'نقطه مکان وقوع را روی نقشه انتخاب کنید.';
    }
    if (mode === 'current' && !Number.isFinite(Number(state.location.latitude))) requestCurrentLocation();
  }
  persistReportDraft();
}

function openLocation() {
  normalizeLocationDetailFields();
  normalizeLocationForCategory();
  setLocationModeAvailability();
  const mode = allowedLocationModes().includes(state.location.mode) ? state.location.mode : '';
  setLocationModeVisual(mode);
  renderLocationFields();
  const continueButton = document.getElementById('locationContinueButton');
  if (continueButton) continueButton.textContent = isPropertyReport() ? 'مرحله بعد' : 'تایید مکان وقوع';
  const status = document.getElementById('locationStatus');
  if (mode === 'current' || mode === 'map') {
    showReportMap();
    restoreLocationMarker();
    if (status) {
      if (Number.isFinite(Number(state.location.latitude))) status.textContent = mode === 'current' ? 'موقعیت فعلی ثبت شده است.' : 'موقعیت انتخاب‌شده روی نقشه ثبت شده است.';
      else status.textContent = mode === 'map' ? 'نقطه مکان وقوع را روی نقشه انتخاب کنید.' : 'برای دریافت موقعیت فعلی، گزینه را دوباره انتخاب کنید.';
    }
  } else {
    hideReportMap();
    if (status) status.textContent = '';
  }
  showPage('locationPage');
}

function continueLocation() {
  captureLocationDraft();
  normalizeLocationForCategory();
  const mode = state.location.mode;
  if (!allowedLocationModes().includes(mode)) return alert('روش تعیین مکان وقوع را انتخاب کنید.');
  if (mode === 'unknown') {
    if (state.category === 'افراد' && (!state.location.province || !state.location.city)) {
      return alert('حداقل استان و شهرستان محل وقوع را وارد کنید.');
    }
    if (!state.location.province) return alert('استان مکان وقوع را انتخاب کنید.');
    if (!state.location.city) return alert('شهرستان مکان وقوع را انتخاب کنید.');
    state.location.known = false;
    delete state.location.latitude;
    delete state.location.longitude;
  } else {
    if (!Number.isFinite(Number(state.location.latitude)) || !Number.isFinite(Number(state.location.longitude))) {
      return alert(mode === 'current' ? 'دریافت موقعیت فعلی کامل نشده است.' : 'نقطه مکان وقوع را روی نقشه انتخاب کنید.');
    }
    state.location.known = true;
    delete state.location.province;
    delete state.location.city;
  }
  if (state.category === 'افراد') return openIncidentReport();
  if (state.category === 'املاک') return openPropertyOwners();
  openIncidentReport();
}

function backFromLocation() {
  captureLocationDraft();
  if (state.category === 'املاک') return showPage('categoryPage');
  openTime();
}

function openIncidentReport() {
  renderIncidentReport();
  showPage('incidentReportPage');
}

function continueIncidentReport() {
  closeIncidentPopup(false);
  collectIncidentReport();
  if (!incidentReportHasValue()) return alert('حداقل یک مورد از «نوع جرم یا تخلف» یا سایر بخش‌ها را تکمیل کنید.');
  openDocuments();
}

function backFromIncidentReport() {
  closeIncidentPopup(false);
  collectIncidentReport();
  // مسیر بازگشت هر بخش به مرحلهٔ پیش از «گزارش وقوع».
  if (state.category === 'املاک') return openTime();
  return openLocation();
}

function backFromDocuments() {
  return openIncidentReport();
}

function openDocuments() {
  const title = document.getElementById('documentsPageTitle');
  if (title) title.textContent = 'مستندات گزارش';
  renderDocuments();
  showPage('documentsPage');
}

function isImageDocument(document) {
  return Boolean(document) && typeof document.mime === 'string' && document.mime.startsWith('image/');
}

let documentSequence = 0;

function documentTypeLabel(document) {
  if (isImageDocument(document)) return 'تصویر';
  const extension = typeof document.name === 'string' && document.name.includes('.')
    ? document.name.split('.').pop().slice(0, 8).toUpperCase()
    : '';
  return extension || 'فایل';
}

function formatDocumentSize(bytes) {
  const size = Number(bytes);
  if (!Number.isFinite(size) || size < 0) return '';
  if (size === 0) return `${faDigits(0)} کیلوبایت`;
  if (size < 1024 * 1024) return `${faDigits(Math.max(1, Math.round(size / 1024)))} کیلوبایت`;
  const megabytes = Math.round((size / (1024 * 1024)) * 10) / 10;
  return `${faDigits(megabytes)} مگابایت`;
}

function documentTotalBytes(documents = state.documents) {
  if (!Array.isArray(documents)) return 0;
  return documents.reduce((total, document) => {
    const size = Number(document && document.size);
    return total + (Number.isFinite(size) && size > 0 ? size : 0);
  }, 0);
}

function documentIsReading(document) {
  return Boolean(document) && document.status === 'reading';
}

function documentStatusText(document) {
  if (documentIsReading(document)) return `در حال آماده‌سازی ${faDigits(Math.round(Number(document.progress) || 0))}٪`;
  if (document && document.status === 'error') return document.error || 'خواندن فایل انجام نشد';
  const type = documentTypeLabel(document);
  const size = formatDocumentSize(document && document.size);
  return size ? `${type} · ${size}` : type;
}

function documentUploadSummaryText() {
  const count = state.documents.length;
  const total = documentTotalBytes();
  return `${faDigits(count)} از ${faDigits(MAX_DOCUMENTS)} فایل · ${formatDocumentSize(total)} از ${faDigits(100)} مگابایت`;
}

function updateDocumentUploadCard() {
  const field = document.querySelector('[data-document-upload-field]');
  const input = document.getElementById('documentInput');
  const trigger = document.getElementById('documentUploadTrigger');
  const addButton = document.getElementById('documentAddButton');
  const summary = document.getElementById('documentUploadSummary');
  const total = documentTotalBytes();
  const atCountLimit = state.documents.length >= MAX_DOCUMENTS;
  const atSizeLimit = total >= MAX_DOCUMENT_TOTAL_BYTES;
  const atLimit = atCountLimit || atSizeLimit;
  const isReading = state.documents.some(documentIsReading);

  if (field) {
    field.classList.toggle('is-full', atLimit);
    field.classList.toggle('is-size-full', atSizeLimit);
    field.classList.toggle('is-reading', isReading);
    field.setAttribute('aria-busy', String(isReading));
  }
  if (summary) summary.textContent = documentUploadSummaryText();
  if (trigger) {
    trigger.disabled = atLimit;
    trigger.setAttribute('aria-label', atLimit ? 'سقف بارگذاری مستندات تکمیل شده است' : 'انتخاب یک یا چند فایل مستند');
  }
  if (addButton) addButton.disabled = atLimit;
  if (input) input.disabled = atLimit;
}

function openDocumentPicker() {
  const input = document.getElementById('documentInput');
  if (!input || input.disabled || typeof input.click !== 'function') return;
  input.click();
}

function addDocuments(input) {
  setDocumentTransferStatus();
  const selectedFiles = Array.from(input.files || []);
  const slots = Math.max(0, MAX_DOCUMENTS - state.documents.length);
  let reservedBytes = documentTotalBytes();
  let invalidSize = 0;
  let overTotal = 0;
  let overCount = 0;
  const accepted = [];

  selectedFiles.forEach(file => {
    const size = Number(file && file.size);
    if (!Number.isFinite(size) || size < 0) {
      invalidSize += 1;
      return;
    }
    if (accepted.length >= slots) {
      overCount += 1;
      return;
    }
    if (reservedBytes + size > MAX_DOCUMENT_TOTAL_BYTES) {
      overTotal += 1;
      return;
    }
    accepted.push(file);
    reservedBytes += size;
  });

  if (invalidSize || overTotal || overCount) {
    const notices = [];
    if (overTotal) notices.push(`مجموع حجم فایل‌های این بخش نباید بیشتر از ${faDigits(100)} مگابایت باشد.`);
    if (overCount) notices.push(`حداکثر ${faDigits(MAX_DOCUMENTS)} فایل قابل بارگذاری است.`);
    if (invalidSize) notices.push('اندازه یکی از فایل‌ها قابل تشخیص نیست.');
    alert(notices.join(' '));
  }
  if (typeof FileReader === 'undefined') {
    if (accepted.length) alert('خواندن فایل در این مرورگر در دسترس نیست.');
    input.value = '';
    return;
  }

  accepted.forEach(file => {
    const entry = {
      id: `document-${++documentSequence}`,
      type: String(file.type || '').startsWith('image/') ? 'image' : 'file',
      name: file.name || 'فایل بدون نام',
      mime: file.type || 'application/octet-stream',
      size: file.size,
      status: 'reading',
      progress: 0,
      data: ''
    };
    state.documents.push(entry);
    const reader = new FileReader();
    reader.onprogress = event => {
      if (!state.documents.includes(entry)) return;
      if (event.lengthComputable && event.total > 0) entry.progress = Math.min(100, (event.loaded / event.total) * 100);
      renderDocuments();
    };
    reader.onload = () => {
      if (!state.documents.includes(entry)) return;
      if (typeof reader.result !== 'string') {
        entry.status = 'error';
        entry.error = 'خواندن فایل انجام نشد';
      } else {
        entry.data = reader.result;
        entry.status = 'ready';
        entry.progress = 100;
      }
      renderDocuments();
    };
    reader.onerror = reader.onabort = () => {
      if (!state.documents.includes(entry)) return;
      entry.status = 'error';
      entry.error = 'خواندن فایل انجام نشد';
      renderDocuments();
    };
    try {
      reader.readAsDataURL(file);
    } catch (error) {
      entry.status = 'error';
      entry.error = 'خواندن فایل انجام نشد';
      renderDocuments();
    }
  });
  input.value = '';
  renderDocuments();
}

function documentProgressMarkup(document) {
  if (!documentIsReading(document)) return '';
  const progress = Math.max(0, Math.min(100, Math.round(Number(document.progress) || 0)));
  return `<span class="document-item-progress"><span>در حال آماده‌سازی</span><progress max="100" value="${progress}">${faDigits(progress)}٪</progress><b>${faDigits(progress)}٪</b></span>`;
}

function renderDocuments() {
  const box = document.getElementById('documentList');
  if (!box) return;
  updateDocumentUploadCard();
  if (!state.documents.length) {
    box.innerHTML = '<div class="document-empty-state"><strong>امکان انتخاب چند فایل وجود دارد</strong><span>تصویر، PDF و سایر مستندات را می‌توانید با هم انتخاب کنید؛ مجموع حجم این بخش حداکثر ۱۰۰ مگابایت است.</span></div>';
    return;
  }
  box.innerHTML = state.documents.map((document, index) => {
    const imagePreview = isImageDocument(document) && typeof document.data === 'string' && document.data
      ? `<img src="${escapeHtml(document.data)}" alt="">`
      : `<span class="document-file-preview" aria-hidden="true">${iconMarkup('report', 'document-file-icon')}</span>`;
    const errorClass = document.status === 'error' ? ' is-error' : '';
    return `<div class="document-item${errorClass}">
      ${imagePreview}
      <span class="document-item-copy"><strong>${escapeHtml(document.name)}</strong><small>${escapeHtml(documentStatusText(document))}</small>${documentProgressMarkup(document)}</span>
      <button class="document-delete-button" type="button" aria-label="حذف فایل ${faDigits(index + 1)}" onclick="removeDocument(${index})">${iconMarkup('trash', 'button-icon delete-icon')}<span>حذف</span></button>
    </div>`;
  }).join('');
}

function removeDocument(index) {
  state.documents.splice(index, 1);
  setDocumentTransferStatus();
  renderDocuments();
}

function setDocumentTransferStatus(message = '', progress = null, isError = false) {
  const status = document.getElementById('documentTransferStatus');
  if (!status) return;
  if (!message) {
    status.hidden = true;
    status.classList.remove('is-error');
    status.textContent = '';
    return;
  }
  status.hidden = false;
  status.classList.toggle('is-error', isError);
  const validProgress = Number.isFinite(progress);
  const normalizedProgress = validProgress ? Math.max(0, Math.min(100, Math.round(progress))) : 0;
  status.innerHTML = `<span>${escapeHtml(message)}${validProgress ? ` ${faDigits(normalizedProgress)}٪` : ''}</span><span class="document-transfer-track${validProgress ? '' : ' is-indeterminate'}" aria-hidden="true"><i style="--document-transfer-progress:${normalizedProgress}%"></i></span>`;
}

function submitReportPayload(payload, onProgress) {
  if (typeof XMLHttpRequest === 'undefined') {
    return fetch('app/report-handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(async response => {
      let output = {};
      try { output = await response.json(); } catch (error) { /* handled below */ }
      if (!response.ok || !output.ok) throw new Error(output.message || 'ثبت گزارش انجام نشد.');
      return output;
    });
  }

  return new Promise((resolve, reject) => {
    const request = new XMLHttpRequest();
    request.open('POST', 'app/report-handler.php', true);
    request.setRequestHeader('Content-Type', 'application/json');
    if (request.upload) {
      request.upload.onprogress = event => {
        if (event.lengthComputable && event.total > 0) onProgress((event.loaded / event.total) * 100);
        else onProgress(null);
      };
    }
    request.onload = () => {
      let output = {};
      try { output = JSON.parse(request.responseText || '{}'); } catch (error) { /* handled below */ }
      if (request.status >= 200 && request.status < 300 && output.ok) resolve(output);
      else reject(new Error(output.message || 'ثبت گزارش انجام نشد.'));
    };
    request.onerror = () => reject(new Error('ارتباط با سرور برقرار نشد.'));
    request.onabort = () => reject(new Error('ارسال گزارش متوقف شد.'));
    request.send(JSON.stringify(payload));
  });
}

async function sendReport() {
  collectForm();
  if (documentTotalBytes() > MAX_DOCUMENT_TOTAL_BYTES) {
    setDocumentTransferStatus('مجموع حجم مستندات از سقف ۱۰۰ مگابایت بیشتر است.', null, true);
    return;
  }
  const pendingDocument = state.documents.find(document => documentIsReading(document));
  if (pendingDocument) {
    setDocumentTransferStatus('بارگذاری فایل‌ها هنوز کامل نشده است؛ لطفاً صبر کنید.');
    return;
  }
  const failedDocument = state.documents.find(document => document.status === 'error' || !document.data);
  if (failedDocument) {
    setDocumentTransferStatus('یک فایل آماده نشده است؛ آن را حذف و دوباره انتخاب کنید.', null, true);
    return;
  }

  const payload = {
    reportType: 'گزارش',
    category: state.category,
    subtype: state.subtype,
    form: state.form,
    location: state.location,
    time: state.time,
    documents: state.documents.map(document => ({
      type: document.type,
      name: document.name,
      mime: document.mime,
      size: document.size,
      data: document.data
    }))
  };

  const button = document.querySelector('#documentsPage .primary-button');
  const success = document.getElementById('successBox');
  if (!button) return;
  button.disabled = true;
  button.setAttribute('aria-busy', 'true');
  success.textContent = '';
  setDocumentTransferStatus(state.documents.length ? 'در حال ارسال گزارش و مستندات' : 'در حال ارسال گزارش', 0);

  try {
    await submitReportPayload(payload, progress => {
      setDocumentTransferStatus(state.documents.length ? 'در حال ارسال گزارش و مستندات' : 'در حال ارسال گزارش', progress);
    });
    hasSubmittedReport = true;
    clearReportDraft();
    setDocumentTransferStatus('ارسال گزارش با موفقیت کامل شد.', 100);
    success.textContent = 'گزارش با موفقیت ثبت شد.';
  } catch (error) {
    setDocumentTransferStatus(error.message || 'ثبت گزارش انجام نشد.', null, true);
    success.textContent = '';
  } finally {
    button.disabled = false;
    button.removeAttribute('aria-busy');
  }
}
if (!restoreReportDraft()) {
  renderReportRoadmaps();
  normalizeVisibleNumbers();
}
