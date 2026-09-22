/*
 * بانک موضوعات و سؤالات «گزارش وقوع» (تخلیهٔ اطلاعاتیِ امنیتی)
 * -------------------------------------------------------------
 * هر موضوع جرم/تخلف فقط در بخش‌هایی تعریف شده که با آن مرتبط است
 * (افراد، املاک، رویداد، اشیاء). یک موضوع می‌تواند در چند بخش تکرار شود،
 * اما مجموعهٔ سؤالات هر بخش متناسب با عنوان همان بخش است.
 *
 * انواع فیلد پشتیبانی‌شده در پاپ‌آپ:
 *   text     : ورودی متنی تک‌خطی
 *   textarea : ورودی متنی چندخطی
 *   choices  : انتخاب تکی از میان گزینه‌ها (دکمه‌ای)
 *   select   : فهرست کشویی قابل جست‌وجو
 * هر سؤال یک key یکتا دارد که مقدار آن ذیل نوع جرم ذخیره می‌شود.
 */
const INCIDENT_TOPIC_QUESTIONS = Object.freeze({
  افراد: [
    {
      id: 'gathering',
      label: 'تجمع و اغتشاش',
      questions: [
        { key: 'role', type: 'choices', label: 'نقش فرد در تجمع چیست؟', items: ['لیدر یا سازمان‌دهنده', 'فراخوان‌دهنده', 'شرکت‌کنندهٔ فعال', 'همراه', 'نامشخص'] },
        { key: 'reason', type: 'textarea', label: 'به چه دلیل فرد را عامل یا محرک می‌دانید؟' },
        { key: 'method', type: 'textarea', label: 'به چه شکل افراد را دعوت یا هدایت می‌کند؟ (پیام‌رسان، شعار، تحریک حضوری)' },
        { key: 'network', type: 'textarea', label: 'ارتباط فرد با گروه‌ها یا افراد دیگر چگونه است؟' }
      ]
    },
    {
      id: 'theft',
      label: 'سرقت',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل به این فرد مشکوک شدید؟' },
        { key: 'method', type: 'textarea', label: 'به چه شکل و با چه ابزاری سرقت انجام می‌شود؟' },
        { key: 'target', type: 'text', label: 'هدف یا نوع اموال موردنظر فرد چیست؟' },
        { key: 'repeat', type: 'choices', label: 'سابقهٔ تکرار چگونه است؟', items: ['بار اول', 'تکراری', 'نامشخص'] }
      ]
    },
    {
      id: 'homicide',
      label: 'قتل',
      questions: [
        { key: 'role', type: 'choices', label: 'نقش احتمالی فرد چیست؟', items: ['عامل احتمالی', 'معاون یا همدست', 'تهدیدکننده', 'نامشخص'] },
        { key: 'reason', type: 'textarea', label: 'به چه دلیل فرد را مرتبط می‌دانید؟ (اختلاف، تهدید قبلی و …)' },
        { key: 'method', type: 'textarea', label: 'به چه شکل و با چه ابزاری حادثه رخ داد؟' }
      ]
    },
    {
      id: 'group-fight',
      label: 'نزاع دسته‌جمعی',
      questions: [
        { key: 'role', type: 'choices', label: 'نقش فرد چیست؟', items: ['شروع‌کننده', 'درگیر فعال', 'تحریک‌کننده', 'نامشخص'] },
        { key: 'reason', type: 'textarea', label: 'به چه دلیل و بر سر چه موضوعی درگیری شکل گرفت؟' },
        { key: 'weapon', type: 'choices', label: 'آیا از سلاح استفاده شد؟', items: ['سلاح سرد', 'سلاح گرم', 'بدون سلاح', 'نامشخص'] },
        { key: 'affiliation', type: 'textarea', label: 'وابستگی طرفین چیست؟ (طایفه، گروه، محله و …)' }
      ]
    },
    {
      id: 'narcotics',
      label: 'مواد مخدر',
      questions: [
        { key: 'role', type: 'choices', label: 'نقش فرد چیست؟', items: ['فروشنده', 'توزیع‌کننده', 'حامل', 'ساقی', 'نامشخص'] },
        { key: 'reason', type: 'textarea', label: 'به چه دلیل به توزیع یا فروش مشکوک شدید؟' },
        { key: 'method', type: 'textarea', label: 'به چه شکل و در چه ساعاتی فعالیت می‌کند؟' },
        { key: 'minors', type: 'choices', label: 'آیا نوجوانان درگیر هستند؟', items: ['بله', 'خیر', 'نامشخص'] }
      ]
    },
    {
      id: 'human-trafficking',
      label: 'قاچاق انسان',
      questions: [
        { key: 'role', type: 'choices', label: 'نقش فرد چیست؟', items: ['گرداننده', 'واسطه', 'حامل', 'نامشخص'] },
        { key: 'reason', type: 'textarea', label: 'به چه دلیل فکر می‌کنید فرد در قاچاق انسان دخیل است؟' },
        { key: 'method', type: 'textarea', label: 'به چه شکل افراد را جابه‌جا یا کنترل می‌کند؟' },
        { key: 'victims', type: 'text', label: 'تعداد و وضعیت تقریبی قربانیان چیست؟' }
      ]
    },
    {
      id: 'arms-trafficking',
      label: 'قاچاق سلاح و مهمات',
      questions: [
        { key: 'role', type: 'choices', label: 'نقش فرد چیست؟', items: ['فروشنده', 'واسطه', 'حامل', 'نامشخص'] },
        { key: 'reason', type: 'textarea', label: 'به چه دلیل به فرد مشکوک شدید؟ (مشاهده، شنیده)' },
        { key: 'method', type: 'textarea', label: 'به چه شکل سلاح را عرضه یا جابه‌جا می‌کند؟' },
        { key: 'armed', type: 'choices', label: 'آیا فرد مسلح است؟', items: ['بله', 'خیر', 'نامشخص'] }
      ]
    },
    {
      id: 'gambling',
      label: 'شرط‌بندی و قمار',
      questions: [
        { key: 'role', type: 'choices', label: 'نقش فرد چیست؟', items: ['گرداننده', 'واسطه یا کارگزار', 'تبلیغ‌کننده', 'نامشخص'] },
        { key: 'reason', type: 'textarea', label: 'به چه دلیل فرد را گردانندهٔ قمار می‌دانید؟' },
        { key: 'method', type: 'textarea', label: 'به چه شکل افراد را جذب می‌کند؟ (سایت، پیام‌رسان، حضوری)' }
      ]
    }
  ],
  املاک: [
    {
      id: 'explosion',
      label: 'انفجار',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل انفجار در این ملک رخ داد؟ (نشتی گاز، مواد و …)' },
        { key: 'damage', type: 'textarea', label: 'به چه شکل و چه میزان ملک تخریب شده است؟' },
        { key: 'intent', type: 'choices', label: 'آیا عمدی بوده است؟', items: ['عمدی', 'حادثه', 'نامشخص'] }
      ]
    },
    {
      id: 'fire',
      label: 'آتش‌سوزی',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل و از کدام قسمت ملک آتش آغاز شد؟' },
        { key: 'spread', type: 'choices', label: 'آیا به ملک‌های مجاور گسترش یافته است؟', items: ['بله', 'خیر', 'نامشخص'] },
        { key: 'intent', type: 'choices', label: 'آیا عمدی بوده است؟', items: ['عمدی', 'حادثه', 'نامشخص'] }
      ]
    },
    {
      id: 'theft',
      label: 'سرقت',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل ملک هدف سرقت قرار گرفت؟' },
        { key: 'entry', type: 'select', label: 'محل ورود سارق کجا بوده است؟', items: ['در ورودی', 'پنجره', 'پشت‌بام', 'دیوار یا حیاط', 'شکستن قفل', 'نامشخص'] },
        { key: 'cctv', type: 'choices', label: 'آیا دوربین مداربسته وجود دارد؟', items: ['دارد', 'ندارد', 'نامشخص'] }
      ]
    },
    {
      id: 'narcotics',
      label: 'مواد مخدر',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل ملک را محل نگهداری یا توزیع مواد می‌دانید؟' },
        { key: 'usage', type: 'select', label: 'کاربری ملک چیست؟', items: ['مسکونی', 'تجاری', 'انبار', 'متروکه', 'نامشخص'] },
        { key: 'traffic', type: 'textarea', label: 'به چه شکل و در چه ساعاتی رفت‌وآمد انجام می‌شود؟' }
      ]
    },
    {
      id: 'human-trafficking',
      label: 'قاچاق انسان',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل به نگهداری افراد در ملک مشکوک شدید؟' },
        { key: 'signs', type: 'textarea', label: 'چه نشانه‌هایی دیده‌اید؟ (قفل از بیرون، پنجره‌های پوشیده، رفت‌وآمد گروهی)' },
        { key: 'count', type: 'text', label: 'تعداد تقریبی افراد نگهداری‌شده چند نفر است؟' }
      ]
    },
    {
      id: 'arms-trafficking',
      label: 'قاچاق سلاح و مهمات',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل فکر می‌کنید سلاح یا مهمات در ملک نگهداری می‌شود؟' },
        { key: 'place', type: 'select', label: 'محل نگهداری در ملک کجاست؟', items: ['انبار', 'زیرزمین', 'حیاط', 'خودرو پارک‌شده', 'نامشخص'] }
      ]
    },
    {
      id: 'hoarding',
      label: 'احتکار',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل انبار کالا را غیرعادی می‌دانید؟' },
        { key: 'goods', type: 'text', label: 'نوع کالای انبارشده چیست؟' },
        { key: 'logistics', type: 'textarea', label: 'به چه شکل بارگیری/تخلیه انجام می‌شود؟ (ساعت، نوع خودرو)' },
        { key: 'cover', type: 'select', label: 'ظاهر ملک چگونه است؟', items: ['انبار', 'مغازه', 'مسکونی', 'نامشخص'] }
      ]
    },
    {
      id: 'gambling',
      label: 'شرط‌بندی و قمار',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل ملک را محل قمار سازمان‌یافته می‌دانید؟' },
        { key: 'access', type: 'select', label: 'ورود چگونه کنترل می‌شود؟', items: ['نگهبان', 'رمز یا دعوت‌نامه', 'آزاد', 'نامشخص'] }
      ]
    }
  ],
  رویداد: [
    {
      id: 'gathering',
      label: 'تجمع و اغتشاش',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل و بر سر چه موضوعی رویداد شکل گرفت؟' },
        { key: 'planned', type: 'choices', label: 'نحوهٔ شکل‌گیری چگونه بود؟', items: ['برنامه‌ریزی‌شده', 'ناگهانی', 'نامشخص'] },
        { key: 'method', type: 'textarea', label: 'به چه شکل گسترش یافت؟ (فراخوان، شعار، تحریک)' },
        { key: 'status', type: 'choices', label: 'وضعیت فعلی چگونه است؟', items: ['ادامه دارد', 'پایان یافته', 'نامشخص'] }
      ]
    },
    {
      id: 'explosion',
      label: 'انفجار',
      questions: [
        { key: 'intent', type: 'choices', label: 'ماهیت انفجار چیست؟', items: ['عمدی', 'حادثه', 'نامشخص'] },
        { key: 'reason', type: 'textarea', label: 'به چه دلیل این نتیجه‌گیری را دارید؟' },
        { key: 'sequence', type: 'textarea', label: 'به چه شکل رخ داد؟ (دود، آتش، انفجار دوم)' }
      ]
    },
    {
      id: 'fire',
      label: 'آتش‌سوزی',
      questions: [
        { key: 'intent', type: 'choices', label: 'ماهیت آتش‌سوزی چیست؟', items: ['عمدی', 'حادثه', 'نامشخص'] },
        { key: 'reason', type: 'textarea', label: 'به چه دلیل این نتیجه‌گیری را دارید؟' },
        { key: 'spread', type: 'textarea', label: 'به چه شکل و با چه سرعتی گسترش یافت؟' }
      ]
    },
    {
      id: 'theft',
      label: 'سرقت',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل سرقت رخ داد؟ (فرصت، تهدید)' },
        { key: 'method', type: 'textarea', label: 'به چه شکل و به چه ترتیبی اتفاق افتاد؟' },
        { key: 'violence', type: 'choices', label: 'آیا همراه با خشونت یا تهدید بود؟', items: ['بله', 'خیر', 'نامشخص'] }
      ]
    },
    {
      id: 'homicide',
      label: 'قتل',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل حادثه رخ داد؟ (مشاجره، تهدید قبلی)' },
        { key: 'sequence', type: 'textarea', label: 'به چه شکل و به ترتیب رخ داد؟' },
        { key: 'escape', type: 'textarea', label: 'مسیر یا نحوهٔ ترک صحنه توسط عامل چگونه بود؟' }
      ]
    },
    {
      id: 'group-fight',
      label: 'نزاع دسته‌جمعی',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل درگیری آغاز شد؟' },
        { key: 'planned', type: 'choices', label: 'نحوهٔ شکل‌گیری چگونه بود؟', items: ['برنامه‌ریزی‌شده', 'ناگهانی', 'نامشخص'] },
        { key: 'weapon', type: 'choices', label: 'آیا از سلاح استفاده شد؟', items: ['سلاح سرد', 'سلاح گرم', 'بدون سلاح', 'نامشخص'] },
        { key: 'status', type: 'choices', label: 'وضعیت فعلی چگونه است؟', items: ['ادامه دارد', 'پایان یافته', 'نامشخص'] }
      ]
    },
    {
      id: 'human-trafficking',
      label: 'قاچاق انسان',
      questions: [
        { key: 'reason', type: 'textarea', label: 'به چه دلیل این جابه‌جایی مشکوک بود؟' },
        { key: 'method', type: 'textarea', label: 'به چه شکل و با چه وسیله‌ای جابه‌جایی انجام شد؟' }
      ]
    }
  ],
  // بخش اشیاء چهار زیربخش دارد؛ سؤالات «گزارش وقوع» هر زیربخش متناسب با موضوع خودش است.
  اشیاء: {
    'بسته مشکوک': [
      {
        id: 'explosive',
        label: 'مواد منفجره یا بمب',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل به مواد منفجره مشکوک شدید؟ (سیم، تایمر، بو، وزن غیرعادی)' },
          { key: 'shape', type: 'textarea', label: 'به چه شکل است؟ (اندازه، رنگ، نوع بسته‌بندی)' },
          { key: 'signal', type: 'choices', label: 'صدا، لرزش یا چراغ روشن دارد؟', items: ['بله', 'خیر', 'نامشخص'] }
        ]
      },
      {
        id: 'narcotics',
        label: 'مواد مخدر',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل فکر می‌کنید حاوی مواد مخدر است؟' },
          { key: 'packaging', type: 'text', label: 'نوع بسته‌بندی یا بوی محتویات چگونه است؟' }
        ]
      },
      {
        id: 'smuggling',
        label: 'قاچاق کالا',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل بسته را محمولهٔ قاچاق می‌دانید؟' },
          { key: 'goods', type: 'text', label: 'نوع کالای احتمالی داخل بسته چیست؟' }
        ]
      },
      {
        id: 'stolen',
        label: 'اموال مسروقه',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل محتویات را مسروقه می‌دانید؟' },
          { key: 'spec', type: 'textarea', label: 'مشخصات ظاهری محتویات چیست؟ (نوع، برند، نشانهٔ خاص)' }
        ]
      }
    ],
    'خودرو مشکوک': [
      {
        id: 'car-bomb',
        label: 'خودروی بمب‌گذاری‌شده',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل به بمب‌گذاری مشکوک شدید؟ (رهاشده، سیم، بار سنگین)' },
          { key: 'behavior', type: 'textarea', label: 'رفتار راننده یا نحوهٔ توقف خودرو چگونه بود؟' }
        ]
      },
      {
        id: 'narcotics-transport',
        label: 'حمل مواد مخدر',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل به حمل مواد مشکوک شدید؟' },
          { key: 'concealment', type: 'textarea', label: 'به چه شکل جاسازی یا جابه‌جا می‌شود؟' }
        ]
      },
      {
        id: 'arms-transport',
        label: 'حمل سلاح یا مهمات',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل به حمل سلاح مشکوک شدید؟' },
          { key: 'cargo', type: 'text', label: 'نوع محمولهٔ احتمالی چیست؟' }
        ]
      },
      {
        id: 'stolen-vehicle',
        label: 'خودروی سرقتی',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل خودرو را سرقتی می‌دانید؟ (پلاک مخدوش، تعویض قطعات)' },
          { key: 'plate', type: 'choices', label: 'وضعیت پلاک چگونه است؟', items: ['مخدوش', 'مغایر', 'بدون پلاک', 'سالم', 'نامشخص'] }
        ]
      },
      {
        id: 'surveillance',
        label: 'تعقیب، رصد یا رفتار مشکوک',
        questions: [
          { key: 'behavior', type: 'textarea', label: 'به چه شکل رفتار مشکوک دیده شد؟ (توقف طولانی، تردد مکرر، رصد)' },
          { key: 'target', type: 'text', label: 'مکان یا هدف احتمالی کجاست؟' }
        ]
      }
    ],
    'پرنده': [
      {
        id: 'espionage',
        label: 'تصویربرداری و جاسوسی',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل به تصویربرداری مشکوک شدید؟' },
          { key: 'path', type: 'textarea', label: 'ارتفاع، مسیر و الگوی پرواز چگونه بود؟' },
          { key: 'target', type: 'text', label: 'مکان یا هدفی که روی آن متمرکز بود کجاست؟' }
        ]
      },
      {
        id: 'payload',
        label: 'حمل محموله',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل فکر می‌کنید محموله حمل می‌کند؟' },
          { key: 'cargo', type: 'text', label: 'نوع و شکل محمولهٔ احتمالی چیست؟' }
        ]
      },
      {
        id: 'recon',
        label: 'شناسایی و رصد اماکن',
        questions: [
          { key: 'place', type: 'text', label: 'مکان مورد رصد کجاست؟' },
          { key: 'pattern', type: 'textarea', label: 'الگو و تکرار پرواز روی محل چگونه بود؟' }
        ]
      },
      {
        id: 'threat',
        label: 'تهدید اماکن حساس',
        questions: [
          { key: 'place', type: 'text', label: 'مکان حساس در معرض تهدید کجاست؟' },
          { key: 'behavior', type: 'textarea', label: 'رفتار پرنده در نزدیکی محل چگونه بود؟' }
        ]
      }
    ],
    'آنتن استارلینک': [
      {
        id: 'unauthorized',
        label: 'استفادهٔ غیرمجاز از اینترنت ماهواره‌ای',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل استفاده را غیرمجاز می‌دانید؟' },
          { key: 'usage', type: 'textarea', label: 'به چه شکل و برای چه منظوری استفاده می‌شود؟' }
        ]
      },
      {
        id: 'organized',
        label: 'استفادهٔ سازمان‌یافته یا تجاری',
        questions: [
          { key: 'reason', type: 'textarea', label: 'به چه دلیل استفاده را سازمان‌یافته می‌دانید؟' },
          { key: 'scope', type: 'text', label: 'تعداد کاربران یا گسترهٔ پوشش چقدر است؟' }
        ]
      }
    ]
  }
});

// «همکاران و افراد مرتبط» و «نحوهٔ اطلاع» برای همهٔ بخش‌ها مشترک‌اند.
const INCIDENT_RELATED_QUESTIONS = Object.freeze([
  { key: 'hasAccomplices', type: 'choices', label: 'آیا فرد یا افراد دیگری همکاری داشتند؟', items: ['بله', 'خیر', 'نامشخص'] },
  { key: 'accompliceCount', type: 'text', label: 'تعداد تقریبی افراد مرتبط چند نفر است؟', numeric: true },
  { key: 'accompliceRoles', type: 'textarea', label: 'نقش و مشخصات افراد مرتبط چیست؟' },
  { key: 'connection', type: 'textarea', label: 'نحوهٔ ارتباط افراد با یکدیگر چگونه است؟ (خانوادگی، شغلی، گروهی)' }
]);

const INCIDENT_SOURCE_QUESTIONS = Object.freeze([
  { key: 'sourceType', type: 'choices', label: 'به چه شکل از موضوع مطلع شدید؟', items: ['مشاهدهٔ مستقیم', 'شنیده از دیگران', 'اطلاع قبلی', 'سایر'], columns: 2 },
  { key: 'sourceRelation', type: 'textarea', label: 'زمان و نحوهٔ آشنایی شما با موضوع چگونه بود؟' },
  { key: 'accessLevel', type: 'choices', label: 'میزان دسترسی شما به اطلاعات موضوع چقدر است؟', items: ['زیاد', 'متوسط', 'کم'], columns: 3 },
  { key: 'sourceNote', type: 'textarea', label: 'چه توضیحات تکمیلی دربارهٔ نحوهٔ اطلاع دارید؟' }
]);

if (typeof window !== 'undefined') {
  window.INCIDENT_TOPIC_QUESTIONS = INCIDENT_TOPIC_QUESTIONS;
  window.INCIDENT_RELATED_QUESTIONS = INCIDENT_RELATED_QUESTIONS;
  window.INCIDENT_SOURCE_QUESTIONS = INCIDENT_SOURCE_QUESTIONS;
}
