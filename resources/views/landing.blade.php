<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>پلتفرم زبان کنکور ارشد کامپیوتر — ۲۵ سال کنکور زبان، در یک دک مرور</title>
<meta name="description" content="همه‌ی کلمات و تست‌های زبان کنکور ارشد مهندسی کامپیوتر، آی‌تی و علوم کامپیوتر از ۱۳۸۱ تا ۱۴۰۵؛ سطح‌بندی‌شده، با پیش‌بینی آماری و مرور فاصله‌دار تا روز کنکور.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&family=Markazi+Text:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,500;1,400;1,500;1,600&display=swap" rel="stylesheet">
{{-- ظاهر این صفحه در public/css/landing.css (نسخه خودکار از زمان تغییر فایل) --}}
<link rel="stylesheet" href="/css/landing.css?v={{ filemtime(public_path('css/landing.css')) }}">
</head>
<body>

<!-- ============ ناوبری ============ -->
<header class="nav" id="nav">
  <div class="wrap">
    <a class="brand" href="#top" aria-label="پلتفرم زبان کنکور ارشد">
      <span class="seal" aria-hidden="true">ز</span>
      <span><b>پلتفرم زبان</b><small>کنکور ارشد کامپیوتر</small></span>
    </a>
    <nav class="nav-links" aria-label="بخش‌های صفحه">
      <a href="#pattern">چرا این پلتفرم</a>
      <a href="#method">روش کار</a>
      <a href="#predict">پیش‌بینی کنکور</a>
      <a href="#features">امکانات</a>
      <a href="#offer">قیمت</a>
      <a href="#faq">سؤال‌ها</a>
    </nav>
    <a class="btn btn-foil" href="{{ route('login') }}">شروع مرور کلمات</a>
  </div>
</header>

@if (session('sso_error'))
  {{-- پیام ورود ناموفق SSO (پیش از این در home.blade.php بود) --}}
  <div class="flash-error" role="alert">{{ session('sso_error') }}</div>
@endif

<!-- ============ HERO ============ -->
<section class="hero night" id="top">
  <div class="wrap">
    <div>
      <h1>زبان کنکور را از ۲۵ سالِ خودِ کنکور یاد بگیر.</h1>
      <p class="hero-lead">همه‌ی کلمات و تست‌های زبان کنکور ارشد مهندسی کامپیوتر، آی‌تی و علوم کامپیوتر، از ۱۳۸۱ تا ۱۴۰۵، شمرده و سطح‌بندی و به ترتیب احتمال برگشت چیده شده‌اند. تو هر روز چند دقیقه مرور می‌کنی؛ پلتفرم یادش می‌ماند کدام کلمه دارد از ذهنت می‌رود.</p>
      <div class="hero-cta">
        <a class="btn btn-foil" href="{{ route('login') }}">شروع مرور کلمات</a>
        <a class="btn btn-line" href="#method">امتحانش کن، همین‌جا</a>
      </div>
      <div class="hero-trust">
        <div><b>۲۵ سال</b>دفترچه‌ی کنکور، کامل</div>
        <div><b>۳ رشته</b>مهندسی کامپیوتر، آی‌تی، علوم کامپیوتر</div>
        <div><b id="heroDays">—</b>روز تا کنکور ۱۴۰۶</div>
      </div>
    </div>

    <figure class="plate" id="plate" aria-live="polite">
      <i class="orn o1"></i><i class="orn o2"></i>
      <div class="plate-top">
        <span>شناسنامه‌ی یک کلمه‌ی کنکور</span>
        <span class="lvl"><i id="lvlDot"></i><span id="lvlTxt">پیشرفته</span></span>
      </div>
      <div class="word-stage" id="stage"></div>
      <div class="mean" id="mean"></div>
      <div class="ribbon">
        <div class="ribbon-cells" id="ribbon" aria-hidden="true"></div>
        <div class="ribbon-axis"><span>۱۳۸۱</span><span>۱۳۹۳</span><span>۱۴۰۵</span></div>
      </div>
      <div class="plate-foot">
        <span>در <b id="yrs">۲۵</b> کنکور از ۲۵ آمده</span>
        <span class="plate-dots" id="dots" role="tablist" aria-label="کلمه‌ها"></span>
      </div>
      <figcaption class="plate-cap">هر خانه‌ی طلایی یعنی این کلمه در کنکور آن سال پرسیده شده.</figcaption>
    </figure>
  </div>
</section>

<!-- ============ دفتر حقایق ============ -->
<div class="ledger">
  <div class="wrap">
    <div><b>۷۵</b><span>دفترچه‌ی کنکور زبان، از سه رشته</span></div>
    <div><b>۲۵</b><span>سال پیاپی، از ۱۳۸۱ تا ۱۴۰۵</span></div>
    <div><b>۳</b><span>بخش آزمون: وکب، کلوز تست، پسیج</span></div>
    <div><b>۴</b><span>حالت مرور: دو جهت کلمه، تست‌ها، همه با هم</span></div>
  </div>
</div>

<!-- ============ الگو ============ -->
<section class="sec pattern" id="pattern">
  <div class="wrap">
    <div class="sec-head">
      <div class="rule-orn">◆</div>
      <h2>کنکور، کلمه‌هایش را دوباره می‌پرسد.</h2>
      <p>کتاب‌های لغت عمومی هزاران کلمه جلویت می‌گذارند و هیچ‌کدام نمی‌گوید کدامش در کنکور ارشد کامپیوتر آمده. نتیجه‌اش ماه‌ها حفظ کردنِ کلماتی است که هرگز سر جلسه نمی‌بینی.</p>
      <p>این جدول را ببین: هر ردیف یک کلمه، هر خانه یک سال کنکور. بعضی کلمه‌ها تقریباً هر سال برگشته‌اند. پلتفرم زبان از همین الگو شروع می‌کند؛ اول کلماتی را می‌خوانی که کنکور بیشتر از همه دوستشان دارد.</p>
    </div>
    <div class="loom-box">
      <div class="loom-scroll">
        <div class="loom" id="loom" role="img" aria-label="حضور چند کلمه‌ی پرتکرار در ۲۵ کنکور اخیر"></div>
        <div class="loom-axis"><span></span><div><span>۱۳۸۱</span><span>۱۳۸۷</span><span>۱۳۹۳</span><span>۱۳۹۹</span><span>۱۴۰۵</span></div></div>
      </div>
      <div class="loom-note">
        <span class="key"><i></i>در کنکور آن سال آمده</span>
        <span class="key"><i class="off"></i>نیامده</span>
        <span>نمونه از <em>تحلیل آزمون‌ها ← پرتکرارترین کلمات</em></span>
      </div>
    </div>
  </div>
</section>

<!-- ============ روش ============ -->
<section class="sec method night" id="method">
  <div class="wrap">
    <div class="sec-head">
      <div class="rule-orn">◆</div>
      <h2>روزی چند دقیقه، تا صبح کنکور.</h2>
      <p>نه دفتر لغت، نه لایتنر کاغذی، نه حدس زدن که امروز چه بخوانی. چهار قدم، هر روز همین.</p>
    </div>
    <div class="steps">
      <div class="step"><div class="n">۱</div><h3>کلمه‌ها را انتخاب کن</h3><p>از پرتکرارترین‌ها، از فهرست پیش‌بینی، از یک سال و رشته‌ی خاص، یا کلمه به کلمه از دل تست‌ها. با یک دکمه وارد دک مرورت می‌شوند.</p></div>
      <div class="step"><div class="n">۲</div><h3>هر روز مرور کن</h3><p>کارت را می‌بینی، معنی را به خاطر می‌آوری و صادقانه جواب می‌دهی. پلتفرم از روی همین جواب، روز برگشت آن کلمه را می‌چیند.</p></div>
      <div class="step"><div class="n">۳</div><h3>با دفترچه‌ی واقعی بسنج</h3><p>دفترچه‌ی هر سال را با زمان‌سنج و نمره‌ی منفی بزن. کلمه‌هایی که سر آزمون لنگیدند، مستقیم به دک می‌روند.</p></div>
      <div class="step"><div class="n">۴</div><h3>سر جلسه، آشنا ببین</h3><p>کلمه‌ای که بیست بار در کنکورهای قبل آمده و ده بار در دک تو مرور شده، دیگر غریبه نیست.</p></div>
    </div>

    <div class="try">
      <div class="try-copy">
        <h3>یک کارت از دک مرور. امتحانش کن.</h3>
        <p>این همان کارتی است که هر روز داخل پلتفرم می‌بینی. معنی را در ذهنت بگو، بعد پاسخ را ببین و یکی از چهار دکمه را بزن.</p>
        <ul>
          <li>دو جهت مرور: انگلیسی به فارسی، و فارسی به انگلیسی برای وقتی که باید کلمه را خودت پیدا کنی.</li>
          <li>زیر هر دکمه نوشته این کلمه کِی برمی‌گردد. کلمه‌ای که بلدی کمتر وقتت را می‌گیرد، کلمه‌ای که نبلدی زودتر برمی‌گردد.</li>
          <li>کلمه‌ای که بیش از دو بار «یادم نبود» خورده، در فیدبک و تسلط جدا نشانت داده می‌شود.</li>
        </ul>
      </div>
      <div class="card" id="card">
        <div class="card-top">
          <span id="cardCount">کارت ۱ از ۶</span>
          <div class="seg" role="group" aria-label="جهت مرور">
            <button type="button" aria-pressed="true" data-dir="en">انگلیسی ← فارسی</button>
            <button type="button" aria-pressed="false" data-dir="fa">فارسی ← انگلیسی</button>
          </div>
        </div>
        <div class="card-face">
          <div class="q en" id="cq"></div>
          <div class="card-meta" id="cmeta"></div>
          <div class="a" id="ca"></div>
        </div>
        <button class="reveal" type="button" id="reveal">مشاهده‌ی پاسخ</button>
        <div class="grades" role="group" aria-label="چقدر یادت بود؟">
          <button type="button" class="g1" data-g="1">یادم نبود<small>بازگشت: فردا</small></button>
          <button type="button" class="g2" data-g="2">سخت بود<small>بازگشت: ۳ روز بعد</small></button>
          <button type="button" class="g3" data-g="3">یادم بود<small>بازگشت: ۸ روز بعد</small></button>
          <button type="button" class="g4" data-g="4">ساده بود<small>بازگشت: ۱۹ روز بعد</small></button>
        </div>
        <div class="card-foot"><span>مرور امروز: <b id="done">۰</b> کارت</span><span>فاصله‌ها نمونه‌اند؛ برای هر کلمه‌ی تو جدا حساب می‌شوند</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ============ پیش‌بینی ============ -->
<section class="sec predict" id="predict">
  <div class="wrap">
    <div class="sec-head">
      <div class="rule-orn">◆</div>
      <h2>اگر فقط صد کلمه وقت داشتی، کدام صد تا؟</h2>
      <p>پیش‌بینی کنکور به هر کلمه امتیاز می‌دهد: چند سال آمده، آخرین بار کِی آمده و با چه ریتمی برمی‌گردد. سه مدل امتیازدهی داری؛ فهرست را بر اساس رشته، سطح، بخش آزمون و پنجره‌ی تازگی تنظیم می‌کنی و با یک دکمه کل فهرست را به دک می‌فرستی.</p>
      <p class="honest"><b>صادقانه:</b> هیچ‌کس سؤال‌های سال بعد را نمی‌داند. این فهرست برآوردی آماری است از الگوی ۲۵ سال گذشته، نه وعده‌ی قطعی. ولی اگر قرار است از جایی شروع کنی، از جایی شروع کن که احتمالش بیشتر است.</p>
    </div>
    <div class="board">
      <div class="board-top">
        <div class="seg" role="group" aria-label="مدل امتیازدهی">
          <button type="button" aria-pressed="true" data-m="0">متوازن</button>
          <button type="button" aria-pressed="false" data-m="1">تکرارمحور</button>
          <button type="button" aria-pressed="false" data-m="2">سررسیدمحور</button>
        </div>
        <span>۸ کلمه‌ی بالای فهرست</span>
      </div>
      <ol class="plist" id="plist"></ol>
      <p class="board-cap">نمایش نمونه. در پلتفرم، فهرست ۵۰ تا ۲۰۰ کلمه‌ای است و کاندیداهای بازگشت را هم جدا نشان می‌دهد.</p>
    </div>
  </div>
</section>

<!-- ============ آزمون آزمایشی ============ -->
<section class="sec exam night" id="exam">
  <div class="wrap">
    <div>
      <div class="sec-head">
        <div class="rule-orn">◆</div>
        <h2>قبل از کنکور، کنکور را بزن. ۲۵ بار.</h2>
        <p>دفترچه‌ی زبان هر سال و هر رشته، با همان ساختار واقعی‌اش: وکب، متن کلوز و سؤال‌هایش، سه پسیج. پشت‌سرهم، بدون صفحه‌بندی، با زمان‌سنج.</p>
      </div>
      <div class="modes">
        <div class="mode"><h3>حالت آزمون</h3><p>شرایط واقعی جلسه. تا آزمون تمام نشود هیچ پاسخی دیده نمی‌شود؛ در پایان کارنامه و پاسخ‌برگ کامل.</p></div>
        <div class="mode"><h3>حالت فیدبک</h3><p>برای یادگیری. بعد از هر سؤال جواب درست را می‌بینی و کلمه‌های ناآشنا را همان‌جا به دک می‌فرستی.</p></div>
      </div>
      <div class="exam-facts">
        <span>زمان استاندارد، فشرده یا بدون زمان</span>
        <span>نمره با منفیِ یک‌سوم</span>
        <span>رفرش شد؟ از همان‌جا ادامه می‌دهی</span>
      </div>
    </div>
    <div class="booklet">
      <div class="booklet-h"><b>کنکور ۱۴۰۵ · مهندسی کامپیوتر</b><span class="timer" id="timer">۳۰:۰۰</span></div>
      <div class="struct">
        <div class="v">وکب<small>۱ تا ۷</small></div>
        <div class="c">کلوز<small>۸ تا ۱۰</small></div>
        <div class="p">پسیج ۱<small>۱۱ تا ۱۵</small></div>
        <div class="p">پسیج ۲<small>۱۶ تا ۲۰</small></div>
        <div class="p">پسیج ۳<small>۲۱ تا ۲۵</small></div>
      </div>
      <div class="bubbles" id="bubbles" role="group" aria-label="پاسخ‌برگ نمونه"></div>
      <div class="sheet-foot"><span id="sheetMsg">روی گزینه‌ها بزن؛ زمان‌سنج با اولین پاسخ راه می‌افتد.</span><button type="button" id="sheetReset">از نو</button></div>
    </div>
  </div>
</section>

<!-- ============ فرهنگ امکانات ============ -->
<section class="sec lexicon" id="features">
  <div class="wrap">
    <div class="sec-head">
      <div class="rule-orn">◆</div>
      <h2>فهرست کامل امکانات، به سبک یک فرهنگ لغت.</h2>
      <p>هر چیزی که برای بستن درس زبان لازم داری، در یک جا. کنار هر مدخل نوشته‌ایم در کدام بخش پلتفرم پیدایش می‌کنی.</p>
    </div>
    <div class="cols">
      <div class="entry"><h3>بانک کلمات ۲۵ ساله <small>مطالعه</small></h3><p>هر کلمه با معنی، نقش دستوری، سطح، بخشی که در آن آمده و نوار سال‌هایش. مرتب‌سازی بر اساس بیشترین تکرار، جدیدترین سال یا الفبا؛ فیلتر بر اساس سال، رشته، سطح و بخش آزمون.</p></div>
      <div class="entry"><h3>تست‌های زبان <small>مطالعه</small></h3><p>همه‌ی تست‌های ۲۵ سال. روی هر تست بزنی، سؤال و کلماتش باز می‌شود؛ متن‌های کلوز و پسیج هم با کلمات مهمشان. کل تست را هم می‌توانی به دک ببری.</p></div>
      <div class="entry"><h3>مطالعه‌ی ترتیبی <small>مطالعه</small></h3><p>کلمات هر دفترچه به ترتیب خود دفترچه، سؤال به سؤال. با «تا اینجا خواندم» جایت را نشانه می‌گذاری و فردا از همان‌جا ادامه می‌دهی.</p></div>
      <div class="entry"><h3>دک من <small>مرور</small></h3><p>قلب پلتفرم. مرور فاصله‌دار در چهار حالت: انگلیسی به فارسی، فارسی به انگلیسی، تست‌ها، یا همه با هم.</p></div>
      <div class="entry"><h3>منتخب من <small>مرور</small></h3><p>کلمه‌ای که فعلاً نمی‌خواهی مرورش کنی ولی نمی‌خواهی گمش کنی، با ☆ کنار می‌رود. هر وقت خواستی، همه را یک‌جا به دک می‌فرستی.</p></div>
      <div class="entry"><h3>یادداشت شخصی <small>روی هر کلمه</small></h3><p>ریشه، مثال، ترفند حفظ کردن، هرچه خودت لازم داری. کنار همان کلمه ذخیره می‌شود و سر مرور جلویت است.</p></div>
      <div class="entry"><h3>آزمون آزمایشی <small>آزمون</small></h3><p>دفترچه‌ی کامل هر سال، در حالت آزمون یا فیدبک، با زمان‌سنج و منفی یک‌سوم.</p></div>
      <div class="entry"><h3>تحلیل آزمون‌ها <small>آزمون</small></h3><p>ترکیب کلمات ساده، متوسط و پیشرفته در هر سال، و فهرست پرتکرارترین کلمات بر اساس تعداد سال یا تعداد کل تکرار.</p></div>
      <div class="entry"><h3>پیش‌بینی کنکور <small>آزمون</small></h3><p>سه مدل امتیازدهی، پنجره‌ی تازگی قابل تنظیم و کاندیداهای بازگشت: کلماتی که چند کنکور غایب بوده‌اند و وقت برگشتشان است.</p></div>
      <div class="entry"><h3>فیدبک و تسلط <small>آمار</small></h3><p>چند کلمه در دکت است، چندتا را بی‌اشتباه مرور کرده‌ای و کدام‌ها بیش از دو بار از ذهنت رفته‌اند. پیشرفتت در هر سطح هم جدا.</p></div>
      <div class="entry"><h3>آمار جمعی <small>آمار</small></h3><p>روی هر تست و کلمه می‌بینی بقیه‌ی داوطلب‌ها چقدر درست زده‌اند. اگر چیزی برای اکثریت سخت است، اشکال از تو نیست.</p></div>
      <div class="entry"><h3>تعادل و پوشش <small>آمار</small></h3><p>هر دفترچه را چقدر کار کرده‌ای، در برابر سهمی که با حجمش انتظار می‌رود. هیچ سالی رها نمی‌شود و هیچ سالی بیش از سهمش وقت نمی‌گیرد.</p></div>
      <div class="entry"><h3>وضعیت امروز <small>داشبورد</small></h3><p>مرور امروز، کلمه‌های تازه، روزهای مانده تا کنکور و «بار واقعی روز»: اینکه با این سرعت، روزی چند کارت لازم داری تا همه‌چیز به کنکور برسد.</p></div>
      <div class="entry"><h3>رتبه‌بندی <small>داشبورد</small></h3><p>امتیاز، زمان مطالعه، کارت‌های مرورشده و دقتت کنار بقیه، با نام مستعار. رقابت سالم، هر روز.</p></div>
      <div class="entry"><h3>یادآور روزانه <small>داشبورد</small></h3><p>ساعتش را خودت انتخاب می‌کنی. هر روز یادت می‌اندازد که مرور امروز منتظر توست.</p></div>
      <div class="entry"><h3>گزارش خطا <small>روی هر کلمه و تست</small></h3><p>معنی دقیق نیست یا گزینه‌ای اشتباه است؟ با ⚑ همان‌جا گزارش می‌دهی و پاسخ را در «گزارش‌های من» می‌گیری.</p></div>
    </div>
  </div>
</section>

<!-- ============ مقایسه ============ -->
<section class="sec compare night">
  <div class="wrap">
    <div class="sec-head">
      <div class="rule-orn">◆</div>
      <h2>کتاب لغت یا پلتفرم زبان؟</h2>
      <p>کتاب لغت بد نیست؛ فقط برای کنکور ارشد کامپیوتر نوشته نشده.</p>
    </div>
    <div class="compare-box">
      <table>
        <thead><tr><th scope="col"><span class="sr">موضوع</span></th><th scope="col">کتاب لغت عمومی</th><th scope="col">پلتفرم زبان</th></tr></thead>
        <tbody>
          <tr><th scope="row">کدام کلمات</th><td>هزاران کلمه‌ی عمومی، بی‌ربط به کنکور تو</td><td>کلماتی که در ۲۵ سال کنکور ارشد کامپیوتر، آی‌تی و علوم کامپیوتر آمده‌اند</td></tr>
          <tr><th scope="row">اولویت</th><td>به ترتیب الفبا یا درس‌های کتاب</td><td>به ترتیب تکرار، تازگی و احتمال برگشت</td></tr>
          <tr><th scope="row">زمان مرور</th><td>هر وقت یادت بیفتد، یا هیچ‌وقت</td><td>همان روزی که داری فراموشش می‌کنی</td></tr>
          <tr><th scope="row">کلمه در بافت</th><td>یک جمله‌ی مثال ساختگی</td><td>همان تستی که کلمه در آن پرسیده شده</td></tr>
          <tr><th scope="row">سنجش</th><td>باید آزمون جدا پیدا کنی</td><td>۲۵ دفترچه‌ی واقعی با زمان و نمره‌ی منفی</td></tr>
          <tr><th scope="row">جایگاه تو</th><td>نمی‌دانی کجا ایستاده‌ای</td><td>آمار جمعی، رتبه‌بندی و فیدبک تسلط</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- ============ ماشین‌حساب ============ -->
<section class="sec calc" id="calc">
  <div class="wrap">
    <div class="sec-head">
      <div class="rule-orn">◆</div>
      <h2>هر روزی که صبر کنی، روزهای بعد سنگین‌تر می‌شوند.</h2>
      <p>ساده‌ترین حساب دنیاست: کلمه‌ها ثابت‌اند، روزها کم می‌شوند. عدد را تغییر بده و ببین اگر امروز شروع کنی هر هفته چند کلمه‌ی تازه لازم است، و اگر چند ماه دیگر شروع کنی چقدر.</p>
    </div>
    <div class="calc-box">
      <label for="wn">چند کلمه می‌خواهی تا کنکور ببندی؟ <b id="wnv">۸۰۰ کلمه</b></label>
      <input type="range" id="wn" min="200" max="1500" step="50" value="800">
      <div class="loads" id="loads" aria-hidden="true"></div>
      <div class="load-x"><span>امروز</span><span>۱ ماه بعد</span><span>۲ ماه بعد</span><span>۳ ماه بعد</span><span>۴ ماه بعد</span></div>
      <p class="calc-out" id="calcOut"></p>
    </div>
  </div>
</section>

<!-- ============ قیمت ============ -->
<section class="sec offer night" id="offer">
  <div class="wrap">
    <div class="sec-head">
      <div class="rule-orn">◆</div>
      <h2>همه‌ی زبان کنکور، در یک اشتراک.</h2>
      <p>بدون کتاب اضافه، بدون دفتر لغت، بدون حدس. از امروز تا صبح کنکور.</p>
    </div>
    <div class="ticket">
      <div>
        <ul>
          <li>بانک کامل کلمات و تست‌های ۲۵ سال، هر سه رشته</li>
          <li>دک مرور فاصله‌دار در چهار حالت، با یادآور روزانه</li>
          <li>پیش‌بینی کنکور با سه مدل امتیازدهی</li>
          <li>۲۵ دفترچه‌ی آزمون آزمایشی، حالت آزمون و فیدبک</li>
          <li>مطالعه‌ی ترتیبی، منتخب، یادداشت شخصی</li>
          <li>فیدبک و تسلط، آمار جمعی، تعادل و پوشش</li>
          <li>رتبه‌بندی و تحلیل آزمون‌ها</li>
          <li>گزارش خطا و پاسخ‌گویی روی هر کلمه و تست</li>
        </ul>
      </div>
      <div class="buy">
        <h3>دسترسی کامل</h3>
        {{-- قیمت و لینک خرید از سرور: جدول قیمت پنل مدیریت (Pricing) --}}
        <div class="price">{{ $price }}</div>
        <div class="price-sub">تومان — هر سه رشته، تا روز کنکور</div>
        <a class="btn btn-foil" href="{{ route('buy') }}">خرید و شروع مرور</a>
        <p class="help">پرداخت ناموفق بود یا سؤالی داری؟ ۰۹۳۷۸۵۵۵۲۰۰</p>
      </div>
    </div>
  </div>
</section>

<!-- ============ سؤال‌ها ============ -->
<section class="sec faq" id="faq">
  <div class="wrap">
    <div class="sec-head">
      <div class="rule-orn">◆</div>
      <h2>پیش از تصمیم.</h2>
      <p>اگر جواب سؤالت اینجا نیست، به پشتیبانی پیام بده.</p>
    </div>
    <div>
      <details open><summary>من کتاب لغت دارم. این چه چیزی اضافه می‌کند؟</summary><p>کتاب می‌گوید چه کلمه‌ای یاد بگیری؛ پلتفرم می‌گوید کدام کلمه برای کنکور تو مهم‌تر است و کِی باید دوباره ببینی‌اش. بیشتر کلمات کتاب‌های عمومی هیچ‌وقت در کنکور ارشد کامپیوتر نیامده‌اند؛ اینجا از همان کلماتی شروع می‌کنی که آمده‌اند.</p></details>
      <details><summary>پیش‌بینی کنکور یعنی کلمات سال بعد را می‌دانید؟</summary><p>نه، و هیچ‌کس نمی‌داند. پیش‌بینی یک برآورد آماری است از فراوانی، تازگی و ریتم تکرار کلمات در ۲۵ سال گذشته. کمکش این است که اگر وقتت محدود است، اول سراغ کلماتی بروی که احتمال برگشتشان بیشتر است.</p></details>
      <details><summary>روزی چقدر وقت می‌گیرد؟</summary><p>بسته به اینکه چند کلمه را هدف گرفته‌ای و چقدر تا کنکور مانده. در داشبورد «بار واقعی روز» را می‌بینی: روزی چند کارت لازم است تا همه‌چیز به کنکور برسد. ماشین‌حساب همین صفحه هم تقریبش را نشان می‌دهد.</p></details>
      <details><summary>برای کدام رشته‌هاست؟</summary><p>کنکور ارشد مهندسی کامپیوتر، آی‌تی و علوم کامپیوتر. همه‌جا فیلتر رشته داری و می‌توانی فقط روی دفترچه‌های رشته‌ی خودت کار کنی، یا از هر سه استفاده کنی.</p></details>
      <details><summary>فقط کلمه است یا تست هم دارد؟</summary><p>هر دو. همه‌ی تست‌های وکب، کلوز و پسیج ۲۵ سال، با کلماتی که از هر تست در بانک هست. تست‌ها هم می‌توانند وارد دک مرور شوند و در حالت «تست‌ها» مرور شوند.</p></details>
      <details><summary>آزمون آزمایشی چطور نمره می‌دهد؟</summary><p>مثل کنکور: هر پاسخ غلط یک‌سوم نمره‌ی منفی دارد. در حالت آزمون تا پایان هیچ پاسخی دیده نمی‌شود و کارنامه و پاسخ‌برگ کامل می‌گیری. پاسخ‌ها ذخیره می‌شوند؛ اگر صفحه بسته شود، از همان‌جا ادامه می‌دهی.</p></details>
      <details><summary>روی گوشی هم کار می‌کند؟</summary><p>بله، در مرورگر گوشی. مرور برای همان چند دقیقه‌های خالی روز ساخته شده: در مترو، در صف، قبل از خواب.</p></details>
      <details><summary>اگر در معنی یا تستی اشتباه دیدم؟</summary><p>روی همان کلمه یا تست ⚑ را بزن و موضوع را انتخاب کن. پاسخ ما را در «گزارش‌های من» می‌بینی. محتوا با کمک خود داوطلب‌ها هر روز دقیق‌تر می‌شود.</p></details>
    </div>
  </div>
</section>

<!-- ============ پایان ============ -->
<section class="final night">
  <div class="wrap">
    <h2>صبح کنکور، دفترچه را باز می‌کنی و کلمه‌ها را می‌شناسی.</h2>
    <p>این حس اتفاقی نیست. نتیجه‌ی چند دقیقه در روز است، از همین امروز تا آن صبح.</p>
    <div class="countdown"><b id="finalDays">—</b><span>روز تا کنکور ارشد ۱۴۰۶</span></div>
    <div><a class="btn btn-foil" href="{{ route('login') }}">شروع مرور کلمات</a></div>
  </div>
</section>

<footer>
  <div class="wrap">
    <span>پلتفرم زبان کنکور ارشد کامپیوتر · از تیم کنکور کامپیوتر</span>
    <span>پشتیبانی: ۰۹۳۷۸۵۵۵۲۰۰</span>
  </div>
</footer>

<div class="mbar" id="mbar"><span><b id="mbarDays">—</b> روز تا کنکور</span><a class="btn btn-foil" href="{{ route('login') }}">شروع مرور</a></div>

<script>window.LANDING_EXAM = @json($examDate);</script>
<script src="/js/landing.js?v={{ filemtime(public_path('js/landing.js')) }}"></script>
</body>
</html>
