/*
 * موتور استخراج «گزارش وقوع» از متنِ گفتار
 * ----------------------------------------
 * متن حاصل از تبدیل صدا به نوشتار را می‌گیرد و تلاش می‌کند موضوع/موضوعات جرم و
 * پاسخ سؤالات را حدس بزند تا بار اولیهٔ پرکردن فرم روی سامانه باشد و کاربر فقط
 * تأیید یا اصلاح کند. خروجی: { crimeTypes:{id:{...}}, related:{...}, source:{...} }
 * این یک استخراج مبتنی بر کلیدواژه است (سبک، بدون سرور)، و همیشه با «کراس‌چک»
 * کاربر نهایی می‌شود.
 */
(function () {
  const TOPIC_KEYWORDS = {
    gathering: ['تجمع', 'اجتماع', 'تظاهرات', 'اغتشاش', 'شلوغی', 'جمعیت', 'آشوب', 'اعتراض', 'راهپیمایی'],
    theft: ['سرقت', 'دزدی', 'دزد', 'سارق', 'ربود', 'زدند به', 'به سرقت'],
    homicide: ['قتل', 'کشت', 'مقتول', 'جنازه', 'کشته', 'به قتل', 'جسد'],
    explosion: ['انفجار', 'منفجر', 'ترکید', 'بمب', 'انفجاری'],
    fire: ['آتش', 'آتش‌سوزی', 'آتش سوزی', 'حریق', 'سوخت', 'شعله', 'دود', 'سوختن'],
    narcotics: ['مواد مخدر', 'مخدر', 'هروئین', 'تریاک', 'گراس', 'حشیش', 'ساقی', 'کراک', 'فروش مواد'],
    'group-fight': ['نزاع', 'دعوا', 'درگیری', 'زدوخورد', 'زد و خورد', 'دسته‌جمعی', 'دسته جمعی', 'چاقوکشی', 'کتک‌کاری'],
    'human-trafficking': ['قاچاق انسان', 'قاچاق آدم', 'بردگی', 'بهره‌کشی', 'بهره کشی', 'آدم‌ربایی'],
    'arms-trafficking': ['قاچاق سلاح', 'اسلحه', 'سلاح', 'مهمات', 'فشنگ', 'کلت', 'تفنگ', 'قاچاق اسلحه'],
    hoarding: ['احتکار', 'انبار', 'دپو', 'انبار کالا', 'انبار احتکار'],
    gambling: ['قمار', 'شرط‌بندی', 'شرط بندی', 'قماربازی', 'پاسور', 'شرطبندی'],
    surveillance: ['تعقیب', 'رصد', 'مراقبت', 'زیر نظر', 'مشکوک', 'پرسه'],
    'car-bomb': ['خودرو بمب', 'ماشین بمب', 'خودروی رهاشده', 'ماشین رهاشده', 'خودرو مشکوک', 'ماشین مشکوک'],
    explosive: ['بسته مشکوک', 'جسم مشکوک', 'شیء مشکوک', 'سیم', 'تایمر', 'بمب', 'ساک مشکوک'],
    stolen: ['مسروقه', 'مال دزدی', 'اموال سرقتی', 'اموال مسروقه', 'دزدی'],
    'stolen-vehicle': ['خودروی سرقتی', 'ماشین دزدی', 'پلاک مخدوش', 'ماشین سرقتی', 'خودرو سرقتی'],
    'narcotics-transport': ['حمل مواد', 'جاسازی مواد', 'بار مواد'],
    'arms-transport': ['حمل سلاح', 'حمل اسلحه', 'حمل مهمات'],
    smuggling: ['قاچاق کالا', 'قاچاق', 'کالای قاچاق'],
    espionage: ['جاسوسی', 'تصویربرداری', 'فیلم‌برداری', 'فیلم برداری', 'عکس', 'دوربین'],
    payload: ['محموله', 'بار', 'حمل بار'],
    recon: ['شناسایی', 'رصد', 'پایش'],
    threat: ['تهدید', 'حمله', 'اماکن حساس'],
    unauthorized: ['استارلینک', 'اینترنت ماهواره‌ای', 'اینترنت ماهواره ای', 'غیرمجاز', 'ماهواره‌ای'],
    organized: ['سازمان‌یافته', 'سازمان یافته', 'تجاری', 'باند', 'شبکه']
  };

  // مترادف‌های گزینه‌ها برای پرکردن سؤالات انتخابی/کشویی
  const CHOICE_SYNONYMS = {
    'عمدی': ['عمدی', 'عمداً', 'عمدا', 'خواسته', 'تعمد'],
    'حادثه': ['حادثه', 'اتفاقی', 'سهوی', 'ناخواسته'],
    'سلاح سرد': ['چاقو', 'قمه', 'سلاح سرد', 'ساطور', 'شمشیر'],
    'سلاح گرم': ['اسلحه', 'کلت', 'تفنگ', 'سلاح گرم', 'شلیک', 'گلوله'],
    'بدون سلاح': ['بدون سلاح', 'بی‌سلاح', 'دست خالی'],
    'مسکونی': ['مسکونی', 'خانه', 'منزل', 'آپارتمان'],
    'تجاری': ['تجاری', 'مغازه', 'فروشگاه', 'دفتر'],
    'انبار': ['انبار', 'سوله', 'دپو'],
    'متروکه': ['متروکه', 'خرابه', 'رهاشده'],
    'ادامه دارد': ['ادامه دارد', 'همچنان', 'در حال انجام', 'هنوز'],
    'پایان یافته': ['تمام شد', 'پایان یافت', 'تمام شده', 'خاتمه'],
    'برنامه‌ریزی‌شده': ['برنامه‌ریزی', 'از قبل', 'هماهنگ', 'برنامه ریزی'],
    'ناگهانی': ['ناگهانی', 'یهو', 'یک‌دفعه', 'یکدفعه', 'ناگهان'],
    'مخدوش': ['مخدوش', 'دستکاری', 'پاک شده'],
    'بدون پلاک': ['بدون پلاک', 'پلاک ندارد', 'بی‌پلاک'],
    'بله': ['بله', 'آره', 'وجود داشت', 'داشت'],
    'خیر': ['خیر', 'نه', 'نبود', 'نداشت']
  };

  const CAUSE_CUES = ['چون', 'چونکه', 'به دلیل', 'بخاطر', 'به‌خاطر', 'به خاطر', 'برای اینکه', 'علت', 'دلیلش'];
  const MANNER_CUES = ['با ', 'از طریق', 'به شکل', 'طوری', 'نحوه', 'توسط', 'به‌وسیله', 'به وسیله'];
  const DESC_CUES = ['رنگ', 'اندازه', 'نوع', 'برند', 'مدل', 'بسته', 'شکل', 'مارک', 'جنس'];
  const PLACE_CUES = ['در ', 'جلوی', 'نزدیک', 'کوچه', 'خیابان', 'پلاک', 'طبقه', 'محل', 'میدان', 'بلوار'];

  const DESC_KEYS = ['shape', 'spec', 'kind', 'goods', 'cargo', 'packaging'];
  const PLACE_KEYS = ['place', 'target', 'entry', 'access'];
  const MANNER_KEYS = ['method', 'sequence', 'behavior', 'concealment', 'logistics', 'spread', 'usage', 'path', 'pattern', 'escape', 'offer', 'traffic', 'signs'];

  function splitSentences(text) {
    return String(text || '')
      .split(/[.!؟\n]+/)
      .map(s => s.trim())
      .filter(Boolean);
  }

  function firstSentenceWith(sentences, cues) {
    return sentences.find(s => cues.some(cue => s.includes(cue))) || '';
  }

  function isNegated(text, term) {
    const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return new RegExp(escaped + '\\s*(?:نبود|نیست|نبوده|نیستند|نداشت|ندارد|نمی)').test(text);
  }

  function matchChoice(items, text) {
    for (const item of items) {
      if (item === 'نامشخص') continue;
      if (text.includes(item) && !isNegated(text, item)) return item;
      const syn = CHOICE_SYNONYMS[item];
      if (syn && syn.some(word => text.includes(word) && !isNegated(text, word))) return item;
    }
    return '';
  }

  function extractPersianNumber(text) {
    const map = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9' };
    const near = text.match(/([۰-۹0-9]{1,3})\s*(?:نفر|تا|دستگاه|عدد)/);
    if (!near) return '';
    return near[1].replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[+d]).replace(/[۰-۹]/g, ch => ch);
  }

  function pickSentence(sentences, cues, used) {
    const found = sentences.find(s => !used.has(s) && cues.some(cue => s.includes(cue)));
    return found || '';
  }

  function fillTextQuestion(key, sentences, used, isPrimary) {
    let cues = null;
    if (key === 'reason') cues = CAUSE_CUES;
    else if (DESC_KEYS.includes(key)) cues = DESC_CUES;
    else if (PLACE_KEYS.includes(key)) cues = PLACE_CUES;
    else if (MANNER_KEYS.includes(key)) cues = MANNER_CUES;

    let sentence = cues ? pickSentence(sentences, cues, used) : '';
    if (!sentence && isPrimary) sentence = sentences.find(s => !used.has(s)) || '';
    if (sentence) used.add(sentence);
    return sentence;
  }

  function extractRelated(questions, text) {
    const answers = {};
    const has = /همدست|با هم|چند نفر|دستیار|کمک|گروهی|باند|نفر دیگر/.test(text);
    const solo = /تنها|به‌تنهایی|به تنهایی|یک نفر بود/.test(text);
    questions.forEach(q => {
      if (q.key === 'hasAccomplices') {
        if (has) answers.hasAccomplices = 'بله';
        else if (solo) answers.hasAccomplices = 'خیر';
      } else if (q.key === 'accompliceCount') {
        const n = extractPersianNumber(text);
        if (n && has) answers.accompliceCount = n;
      }
    });
    return answers;
  }

  function extractSource(questions, text) {
    const answers = {};
    let type = '';
    if (/خودم دیدم|شاهد بودم|با چشم خودم|دیدم که|جلوی چشمم/.test(text)) type = 'مشاهدهٔ مستقیم';
    else if (/شنیدم|گفتند|شنیده‌ام|شنیده ام|به من گفتند/.test(text)) type = 'شنیده از دیگران';
    else if (/می‌دانستم|میدانستم|از قبل|قبلاً|قبلا خبر داشتم/.test(text)) type = 'اطلاع قبلی';
    questions.forEach(q => {
      if (q.key === 'sourceType' && type) answers.sourceType = type;
    });
    return answers;
  }

  function extractIncident(options) {
    const text = String(options.text || '');
    const topics = Array.isArray(options.topics) ? options.topics : [];
    const relatedQuestions = options.relatedQuestions || [];
    const sourceQuestions = options.sourceQuestions || [];
    const sentences = splitSentences(text);
    const result = { crimeTypes: {}, related: {}, source: {} };
    if (!text.trim()) return result;

    topics.forEach(topic => {
      const keywords = TOPIC_KEYWORDS[topic.id] || [topic.label];
      const hit = keywords.some(k => text.includes(k)) || text.includes(topic.label);
      if (!hit) return;
      const topicSentences = sentences.filter(s => keywords.some(k => s.includes(k)) || s.includes(topic.label));
      const source = topicSentences.length ? topicSentences : sentences;
      const used = new Set();
      const answers = {};
      let primaryUsed = false;
      topic.questions.forEach(question => {
        if (question.type === 'choices' || question.type === 'select') {
          const value = matchChoice(question.items || [], text);
          if (value) answers[question.key] = value;
          return;
        }
        const isPrimary = !primaryUsed;
        const value = fillTextQuestion(question.key, source, used, isPrimary);
        if (value) { answers[question.key] = value; primaryUsed = true; }
      });
      if (Object.keys(answers).length) {
        answers.__label = topic.label;
        result.crimeTypes[topic.id] = answers;
      }
    });

    result.related = extractRelated(relatedQuestions, text);
    result.source = extractSource(sourceQuestions, text);
    return result;
  }

  window.extractIncidentFromText = extractIncident;
})();
