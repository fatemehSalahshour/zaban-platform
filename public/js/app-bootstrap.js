/* =====================================================================
 *  لایه‌ی اتصال پروتوتایپ به سرور — نسخه‌ی ۲
 *
 *  تفاوت‌ها با نسخه‌ی اول:
 *   • سؤال‌ها جدا و با تأخیر بارگذاری می‌شوند (payload اول سبک‌تر)
 *   • ۴۰۳ ساختاریافته است، پس پی‌وال نام رشته و لینک خرید درست را نشان می‌دهد
 *   • صف نوشتن دیگر با یک درخواستِ همیشه‌شکست‌خورده قفل نمی‌شود
 *   • sendBeacon فقط برای مسیرهای POST استفاده می‌شود (PATCH/PUT با beacon کار نمی‌کند)
 *
 *  اتصال:
 *    <script src="/js/app-bootstrap.js"></script>
 *    <script>ZABAN.boot(function(){  ... کل اسکریپت فعلی ...  });</script>
 * ===================================================================== */

window.ZABAN = (function () {
  const API = '/api';
  let CONTENT = null, ME = null, QUESTIONS_READY = {};   /* به‌ازای هر رشته */

  /* ---------- ارتباط ----------
     نکته‌ای که در نسخه‌ی قبل نبود و روی سرور همه‌ی نوشتن‌ها را می‌شکست:
     Sanctum در حالت stateful (احراز هویت با کوکی سشن، نه توکن) میدل‌ور
     CSRF لاراول را روی مسیرهای api هم اجرا می‌کند. هر POST/PUT/PATCH
     بدون هدر X-XSRF-TOKEN جواب ۴۱۹ می‌گیرد — یعنی دک، یادداشت، مرور و
     وضعیت آزمون هیچ‌کدام ذخیره نمی‌شدند، و چون صف نوشتن ۴xx را
     غیرقابل‌تلاش‌مجدد می‌داند، بی‌صدا دور ریخته می‌شدند.
     توکن از کوکی XSRF-TOKEN خوانده می‌شود که خود لاراول می‌گذارد. */

  function xsrf() {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return m ? decodeURIComponent(m[1]) : '';
  }

  /** اگر کوکی نبود یا منقضی شده بود، یک بار تازه‌اش می‌کنیم. */
  async function freshXsrf() {
    try { await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }); } catch (_) {}
    return xsrf();
  }

  /* تشخیص حلقه: اگر یک مسیر بیش از ۱۰ بار در ۱۰ ثانیه صدا زده شود، در Console
     هشدار می‌دهد — سقف کلی ۱۲۰ درخواست در دقیقه بین همه‌ی تب‌ها مشترک است و
     یک تب گیرکرده همه را از کار می‌اندازد. */
  const RECENT = {};
  function watchLoop(method, path) {
    const k = method + ' ' + path.split('?')[0].replace(/\/\d+/g, '/{id}');
    const now = Date.now();
    const arr = (RECENT[k] = (RECENT[k] || []).filter(t => now - t < 10000));
    arr.push(now);
    if (arr.length === 11) console.warn('⚠ درخواست تکراری (احتمال حلقه):', k, '— ۱۱ بار در ۱۰ ثانیه');
  }

  /* پاسخ‌های درهم‌شده (معنی، مثال، پاسخ‌ها) — کلید نشست از /me. قبل از رسیدن کلید
     سرآیند فرستاده نمی‌شود تا سرور پاسخ ساده بدهد. (امنیت محتوا، قدم ۴) */
  let OBF_KEY = null;
  function deobf(b64) {
    const bin = atob(b64), k = OBF_KEY.match(/../g).map(h => parseInt(h, 16));
    const out = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i) ^ k[i % k.length];
    return JSON.parse(new TextDecoder('utf-8').decode(out));
  }

  async function req(method, path, body, retried) {
    watchLoop(method, path);
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json',
                      'X-Requested-With': 'XMLHttpRequest' };
    if (OBF_KEY) headers['X-Zaban-Obf'] = '1';
    if (method !== 'GET') {
      const t = xsrf() || await freshXsrf();
      if (t) headers['X-XSRF-TOKEN'] = t;
    }

    const res = await fetch(API + path, {
      method,
      headers,
      credentials: 'same-origin',
      body: body ? JSON.stringify(body) : undefined,
    });

    if (res.status === 401) {
      /* حساب روی دستگاه دیگری باز شده: رفتن خودکار به /login این دستگاه را با ورود
         یکپارچه برمی‌گرداند و دستگاه دیگر را بیرون می‌اندازد — بی‌انتها. پس پیام،
         و برگشت فقط با کلیک عمدی. */
      let j = null; try { j = await res.clone().json(); } catch (_) {}
      /* درخواست هم‌زمانی که بعد از بسته شدن نشست رسید، ۴۰۱ ساده دارد؛ نشانگر
         zaban_replaced می‌گوید علتش همان دستگاه دیگر است */
      const replaced = (j && j.error === 'session_replaced') || /(?:^|;\s*)zaban_replaced=1/.test(document.cookie);
      if (replaced) {
        fatal((j.message || 'این حساب روی دستگاه دیگری باز شد.') +
              '<br><br><a href="/login" style="display:inline-block;background:#16191d;color:#fff;' +
              'text-decoration:none;border-radius:10px;padding:10px 22px">ورود دوباره روی این دستگاه</a>');
        throw halt('session_replaced');
      }
      location.href = '/login'; throw halt('unauthenticated');
    }

    /* ۴۱۹ = توکن CSRF منقضی شده (سشن طولانی، تب باز مانده).
       یک بار توکن را تازه می‌کنیم و همان درخواست را می‌فرستیم. */
    if (res.status === 419 && !retried) return req(method, path, body, true);

    if (res.status === 423) {
      /* حساب به‌خاطر الگوی غیرعادی استفاده موقتاً قفل است (AbuseGuard) */
      let j = null; try { j = await res.clone().json(); } catch (_) {}
      fatal((j && j.message) || 'حساب شما موقتاً قفل شده است.');
      throw halt('account_locked');
    }

    if (res.status === 429) {
      const err = new Error(method + ' ' + path + ' → 429');
      err.retryable = false; err.tooMany = true;
      throw err;
    }

    if (res.status === 403) {
      const info = await res.json().catch(() => ({}));
      showPaywall(info);
      throw halt('forbidden');
    }

    if (res.status === 304) return null;
    if (res.status === 204) return null;

    if (!res.ok) {
      const err = new Error(method + ' ' + path + ' → ' + res.status);
      /* ۴xx یعنی درخواست خودش غلط است؛ تکرارش هم همان جواب را می‌دهد.
         فقط ۵xx و قطعی شبکه ارزش تلاش دوباره دارند. */
      err.retryable = res.status >= 500;
      err.status = res.status;
      /* ۴۲۲: پیام فارسی اعتبارسنجی لاراول را همراه خطا می‌بریم تا صفحه نشانش بدهد */
      if (res.status === 422) {
        try { const j = await res.json(); err.userMessage = j.message || null; } catch (_) {}
      }
      throw err;
    }
    const j = await res.json();
    return (j && typeof j.o === 'string' && OBF_KEY) ? deobf(j.o) : j;
  }

  function halt(msg) { const e = new Error(msg); e.retryable = false; e.halt = true; return e; }

  /* ---------- صف نوشتن ---------- */
  const queue = [];
  let flushing = false;

  function push(method, path, body) {
    queue.push({ method, path, body, tries: 0 });
    flush();
  }

  async function flush() {
    if (flushing || !queue.length) return;
    flushing = true;
    while (queue.length) {
      const job = queue[0];
      try {
        await req(job.method, job.path, job.body);
        queue.shift();
      } catch (e) {
        if (e.halt || e.retryable === false) { queue.shift(); continue; }
        job.tries++;
        if (job.tries > 4) { queue.shift(); console.warn('رها شد:', job, e); continue; }
        await new Promise(r => setTimeout(r, 1000 * job.tries));
      }
    }
    flushing = false;
  }

  window.addEventListener('online', flush);
  window.addEventListener('beforeunload', () => {
    /* sendBeacon فقط POST می‌فرستد. PATCH و PUT را با beacon نمی‌شود فرستاد،
       پس برای وضعیت آزمون یک مسیر POST جداگانه داریم. */
    /* sendBeacon هدر سفارشی قبول نمی‌کند، پس توکن CSRF را داخل بدنه
       می‌گذاریم — لاراول _token را هم مثل هدر می‌پذیرد. */
    queue.filter(j => j.method === 'POST').forEach(j =>
      navigator.sendBeacon && navigator.sendBeacon(
        API + j.path, new Blob([JSON.stringify(Object.assign({ _token: xsrf() }, j.body || {}))],
                               { type: 'application/json' })));
  });

  /* ---------- جایگزین localStorage ----------
     قبلاً این فقط یک شیء در حافظه بود، پس هر چیزی که سرور برایش مسیر
     نداشت با رفرش گم می‌شد: تاریخچه‌ی آزمون‌ها، تم، یادآور، گزارش‌ها.
     حالا زیرش localStorage واقعی نشسته.

     ترتیب اولویت عمدی است: اول mem، بعد localStorage. سه کلیدی که
     install() از سرور پر می‌کند (zban_notes، zban_prof، zban_exam)
     در mem هستند و همیشه بر نسخه‌ی محلی مقدم‌اند — وگرنه نسخه‌ی
     کهنه‌ی مرورگر روی دیتای تازه‌ی سرور می‌نشست.

     پیشوند zaban: برای این است که با کلیدهای سایت‌های دیگر روی همان
     دامنه قاطی نشود. */
  const LS_PREFIX = 'zaban:';
  const mem = {};
  const LS = {
    get(k) {
      if (k in mem) return mem[k];
      try {
        const v = localStorage.getItem(LS_PREFIX + k);
        return v === null ? null : JSON.parse(v);
      } catch (_) { return null; }
    },
    set(k, v) {
      mem[k] = v;
      try { localStorage.setItem(LS_PREFIX + k, JSON.stringify(v)); } catch (_) {}
    },
    del(k) {
      delete mem[k];
      try { localStorage.removeItem(LS_PREFIX + k); } catch (_) {}
    },
  };

  /* ---------- نصب داده ----------
     نکته‌ی مهم: پروتوتایپ کلمه را با «متن انگلیسی» صدا می‌زند (deck و star
     مجموعه‌ای از رشته‌اند)، ولی سرور با id کار می‌کند. دو نگاشت می‌سازیم و
     همه‌ی تبدیل‌ها فقط از همین‌جا رد می‌شوند. */
  let WID = {}, WBYID = {};

  /* رایانه‌ی مشترک: اگر داده‌ی مرورگر مال کاربر دیگری است (یا معلوم نیست مال
     کیست)، پیش از هر چیز پاک می‌شود. یادداشت، پروفایل و معدل نفر قبلی نباید
     برای نفر بعدی بماند. */
  function clearLocal() {
    try {
      Object.keys(localStorage).filter(k => k.startsWith(LS_PREFIX))
        .forEach(k => localStorage.removeItem(k));
    } catch (_) {}
  }
  function claimLocal(uid) {
    try {
      const owner = localStorage.getItem(LS_PREFIX + '__uid');
      if (owner !== String(uid)) {
        clearLocal();
        localStorage.setItem(LS_PREFIX + '__uid', String(uid));
      }
    } catch (_) {}
  }

  function install(content, me, reps, anns) {
    if (me.uid != null) claimLocal(me.uid);
    /* اسم‌های ثابت رابط. در نسخه‌ی demo داخل بلوک دیتا بودند و موقع
       ساختن نسخه‌ی سروری با آن بلوک حذف شدند — ولی صفحه در چند جا
       به آن‌ها تکیه می‌کند و بدونشان اصلاً بالا نمی‌آید. */
    window.EXAM_NAMES = ['مهندسی کامپیوتر', 'آی‌تی', 'علوم کامپیوتر'];
    window.SEC_NAMES  = ['وکب', 'کلوز تست', 'پسیج'];
    window.LVL_NAMES  = ['ساده', 'متوسط', 'پیشرفته'];

    window.YEARS  = content.years;
    window.STRUCT = content.struct;
    window.TEXTS  = content.texts;

    /* سرور دقیقاً همان شکلی را می‌فرستد که decode() در پروتوتایپ می‌ساخت،
       پس اینجا decode لازم نیست — فقط id را نگه می‌داریم. */
    window.WORDS = content.words;

    WID = {}; WBYID = {};
    content.words.forEach(w => { WID[w.w] = w.id; WBYID[w.id] = w; });
    window.WID = WID; window.WBYID = WBYID;

    /* سؤال‌ها هنوز نرسیده‌اند */
    window.QUESTIONS = []; window.QBYID = {}; window.QBYKEY = {};

    /* کلیدهای localStorage در پروتوتایپ zban_* هستند (بدون «a»)، نه zaban_*.
       اگر این را zaban_ بنویسید، یادداشت‌ها و آزمون نیمه‌تمام بی‌صدا گم می‌شوند. */
    mem.zban_notes = {};
    me.notes.forEach(n => {
      /* سؤال با کلید «سال|رشته|شماره» که سرور در k می‌فرستد؛ قبلاً 'q:' + id
         می‌شد و صفحه هیچ‌وقت یادداشت سؤال را پیدا نمی‌کرد. */
      const key = n.t === 'w' ? (WBYID[n.id] ? 'w:' + WBYID[n.id].w : null)
                              : (n.k ? 'q:' + n.k : null);
      if (key) mem.zban_notes[key] = n.body;
    });
    mem.zban_prof = me.profile;
    mem.zban_exam = me.openAttempt ? me.openAttempt.state : null;

    window.SERVER_DECK = me.deck;
    window.SERVER_STAR = me.star;
    window.OPEN_ATTEMPT = me.openAttempt;
    window.OWNED_EXAMS = me.profile.owned || [];
    window.ME_DEMO     = me.profile.demo || null;       /* {year, exams} یا null */
    window.EXAM_DATE_ISO = me.exam_date || null;       /* تاریخ کنکور از پنل مدیریت */
    window.PLATFORMS     = me.platforms || null;
    window.LIST_MEANINGS = me.list_meanings !== false;   /* فهرست‌ها با معنی؟ (پنل مدیریت) */
    window.NEW_PER_DAY_CAP = me.new_per_day || null;     /* سقف کارت تازه: پروفایل یا پیش‌فرض مدیر */
    if (me.ok && /^[0-9a-f]{32}$/.test(me.ok)) OBF_KEY = me.ok;       /* {current, list:[{key,n,d,i,url}]} */
    /* نسخه‌ی نمایشی: اگر محتوای بارگذاری‌شده فقط یک سال است، سرور demo_year را می‌گوید */
    window.DEMO_YEAR    = content.demo_year || null;
    window.CONTENT_EXAM = content.exam || null;
    window.NEW_TODAY = me.new_today || 0;

    /* آمار تمرینی خود کاربر روی هر سؤال — قبلاً فقط در مرورگر (zban_qatt) */
    mem.zban_qatt = {};
    (me.qatt || []).forEach(a => { mem.zban_qatt[a.k] = { n: a.n, ok: a.ok, opt: a.opt }; });

    /* فعالیت روزانه از سرور (daily_activity)، به همان شکلی که صفحه در
       zban_act نگه می‌دارد. در mem، پس بر نسخه‌ی مرورگر مقدم است. */
    mem.zban_act = { days: {} };
    (me.activity || []).forEach(a => {
      mem.zban_act.days[a.d] = { sec: a.sec, rev: a.rev, revOk: a.revOk, q: a.q, qOk: a.qOk, mast: a.mast };
    });
    window.IS_ADMIN = !!(me.profile && me.profile.is_admin);

    /* گزارش‌ها در mem می‌نشینند تا بر نسخه‌ی کهنه‌ی localStorage مقدم باشند.
       اگر سرور جواب نداد، نسخه‌ی محلی را نگه می‌داریم تا دست‌کم گزارش‌های
       قبلی دیده شوند. آرایه یعنی شکل اشتباه — شیء خالی جایش. */
    if (reps && reps.reports && !Array.isArray(reps.reports)) mem.zban_reports = reps.reports;
    else if (reps) mem.zban_reports = {};

    /* اطلاعیه‌ها فقط از سرور. اگر نیامد، فهرست خالی — نه متن ثابت. */
    window.ANNOUNCE = {
      items: (anns && Array.isArray(anns.items)) ? anns.items : [],
      unread: (anns && anns.unread) || 0,
      failed: !anns,
    };
  }

  /**
   * سؤال‌های یک رشته را به آنچه از قبل هست اضافه می‌کند، نه جایگزین.
   *
   * قبلاً جایگزین می‌کرد و چون هر رشته جدا بارگذاری می‌شود، با هر بار
   * عوض کردن رشته در تب آزمون، دفترچه‌ی رشته‌ی قبلی از دست می‌رفت.
   */
  function installQuestions(payload) {
    const fresh = payload.questions || [];
    if (!window.QUESTIONS) window.QUESTIONS = [];

    const seen = new Set(window.QUESTIONS.map(q => q.id));
    fresh.forEach(q => { if (!seen.has(q.id)) window.QUESTIONS.push(q); });

    window.QBYID = {}; window.QBYKEY = {};
    window.QUESTIONS.forEach(q => {
      QBYID[q.id] = q;
      QBYKEY[q.y + '|' + q.e + '|' + q.q] = q;   // ← makeQ با همین کلید می‌خواند
    });
    document.dispatchEvent(new CustomEvent('zaban:questions-ready'));
    return window.QUESTIONS;
  }

  /* کلید کارت در پروتوتایپ: "w|<متن کلمه>"  یا  "q|<سال>|<رشته>|<شماره>" */
  function keyToItem(key) {
    const p = String(key).split('|');
    if (p[0] === 'w') return { t: 'w', id: WID[p.slice(1).join('|')] };
    if (p[0] === 'q') {
      const q = window.QBYKEY[p[1] + '|' + p[2] + '|' + p[3]];
      return { t: 'q', id: q ? q.id : null };
    }
    return { t: null, id: null };
  }

  /* کلید گزارش در رابط «w:<کلمه>» یا «q:<سال>|<رشته>|<شماره>» است
     (با دو‌نقطه، برخلاف کلید کارت مرور که با | شروع می‌شود). */
  function reportItem(key) {
    const k = String(key);
    if (k.startsWith('w:')) return { t: 'w', id: WID[k.slice(2)] || null };
    if (k.startsWith('q:')) { const q = (window.QBYKEY || {})[k.slice(2)]; return { t: 'q', id: q ? q.id : null }; }
    return { t: null, id: null };
  }

  /* ---------- عملیات ---------- */
  const api = {
    LS,

    async boot(main) {
      try {
        const exam = document.documentElement.dataset.exam || '';
        const qs = exam ? '?exam=' + exam : '';

        /* گزارش‌ها جدا می‌آیند و شکستشان نباید کل پلتفرم را بخواباند. */
        const [content, me, reps, anns] = await Promise.all([
          req('GET', '/content' + qs),
          req('GET', '/me'),
          req('GET', '/reports').catch(e => { console.error('گزارش‌ها نیامد:', e); return null; }),
          req('GET', '/announcements').catch(e => { console.error('اطلاعیه‌ها نیامد:', e); return null; }),
        ]);

        CONTENT = content; ME = me;
        install(content, me, reps, anns);
        main();
        startHeartbeat();

        /* سؤال‌ها بعد از رندر شدن رابط، در پس‌زمینه */
        api.questions();
      } catch (e) {
        if (e.tooMany) fatal('تعداد درخواست‌ها در این دقیقه زیاد بوده است. یک دقیقه صبر کنید و صفحه را دوباره باز کنید. اگر پلتفرم در چند تب باز است، بقیه را ببندید.');
        else if (!e.halt) fatal();
        console.error(e);
      }
    },

    /**
     * سؤال‌ها — تنبل، و برای هر رشته جداگانه کش می‌شود.
     *
     * قبلاً فقط رشته‌ی پروفایل بارگذاری می‌شد. وقتی کاربر در تب آزمون
     * رشته را عوض می‌کرد، QIDX چیزی برای رشته‌ی تازه نداشت و همه‌ی
     * سال‌ها «بدون داده» نشان داده می‌شدند.
     *
     * ورودی، کد رشته است (ce/it/cs) نه نام فارسی.
     */
    questions(exam) {
      const e = exam || (ME && ME.profile.exam) ||
                document.documentElement.dataset.exam || '';
      if (!QUESTIONS_READY[e]) {
        QUESTIONS_READY[e] = req('GET', '/content/questions' + (e ? '?exam=' + e : ''))
          .then(installQuestions)
          .catch(err => { delete QUESTIONS_READY[e]; throw err; });
      }
      return QUESTIONS_READY[e];
    },

    /* هر چهار تا «متن کلمه» می‌گیرند (همان چیزی که پروتوتایپ دارد) و
       خودشان به id تبدیل می‌کنند. */
    deck(word, on)     { const id = WID[word]; if (id) push('POST', '/deck', { t: 'w', id, on }); },
    deckQ(qid, on)     { push('POST', '/deck', { t: 'q', id: qid, on }); },
    deckBulk(words, on){
      const ids = words.map(w => WID[w]).filter(Boolean);
      if (ids.length) push('POST', '/deck', { t: 'w', ids, on });
    },
    star(word, on)     { const id = WID[word]; if (id) push('POST', '/star', { t: 'w', id, on }); },
    /* ستاره‌دار کردن سؤال. مسیر /star از قبل t:'q' را می‌پذیرد. */
    starQ(qid, on)     { if (qid) push('POST', '/star', { t: 'q', id: qid, on }); },
    /** یادداشت — کلید «w:<کلمه>» یا «q:<سال>|<رشته>|<شماره>»؛ body خالی = حذف.
        منتظر جواب می‌ماند تا اگر ذخیره نشد، کاربر بفهمد. */
    note(noteKey, body){
      const k = String(noteKey);
      let t = null, id = null;
      if (k.startsWith('w:'))      { t = 'w'; id = WID[k.slice(2)] || null; }
      else if (k.startsWith('q:')) { t = 'q'; const q = (window.QBYKEY || {})[k.slice(2)]; id = q ? q.id : null; }
      if (!id) return Promise.reject(new Error('آیتم یادداشت پیدا نشد: ' + k));
      return req('PUT', '/note', { t, id, body });
    },
    reading(y, e, pos)   { push('PUT',  '/reading', { year: y, exam: e, position: pos }); },

    /** ثبت مرور — ورودی همان key پروتوتایپ است ("w|convenient") */
    review(key, rating, mode) {
      const it = keyToItem(key);
      if (!it.id) return Promise.resolve(null);
      return req('POST', '/review', { t: it.t, id: it.id, rating, mode: mode || 'deck' });
    },

    /** پاسخ یک سؤال — ۴۰۹ یعنی «در حالت فیدبک هستی، صبر کن» */
    async answer(qid) {
      try {
        return await req('GET', '/question/' + qid + '/answer');
      } catch (e) {
        if (String(e.message).includes('409')) return { locked: true };
        throw e;
      }
    },

    /** پاسخ چند سؤال: { answers:[{id, correct, explanation}], locked:[id] } */
    answers(qids) { return req('GET', '/answers?ids=' + qids.join(',')); },
    /** مثال‌های چند کلمه: { items:[{id, ex}], limited:[id], limit_message } */
    wordDetails(ids) { return req('GET', '/words/detail?ids=' + ids.join(',')); },
    /** معنی چند کلمه: { items:[{id, fa}], limited:[id], limit_message } */
    wordMeanings(ids) { return req('GET', '/words/meanings?ids=' + ids.join(',')); },
    /** جست‌وجوی متن فارسی در معنی‌ها: { ids:[…] } */
    wordSearch(q) { return req('GET', '/words/search?q=' + encodeURIComponent(q)); },

    examStart(year, exam, mode, dur) {
      return req('POST', '/exam/start', { year, exam, mode, duration_sec: dur });
    },
    examSave: debounce(function (id, state) {
      push('PATCH', '/exam/' + id, { state });
    }, 2000),
    examFinish(id) { return req('POST', '/exam/' + id + '/finish'); },
    /* تاریخچه از exam_attempts سرور، نه از حافظه‌ی مرورگر. */
    examHistory()  { return req('GET', '/exam/history'); },
    /** کارنامه‌ی کامل یک آزمون تمام‌شده: پاسخ‌ها، تشریحی‌ها، نتیجه‌ی بخش‌ها */
    examResult(id) { return req('GET', '/exam/' + id + '/result'); },
    examDrop(id)   { return req('DELETE', '/exam/' + id); },

    board(days, exam) {
      return req('GET', '/board?days=' + days + (exam ? '&exam=' + exam : ''));
    },

    /* گزارش‌ها منتظر جواب سرور می‌مانند (از صف نوشتن رد نمی‌شوند):
       کاربر باید فقط وقتی «ثبت شد» ببیند که واقعاً ثبت شده باشد. */
    reportNew(key, topic, body) {
      const it = reportItem(key);
      if (!it.id) return Promise.reject(new Error('آیتم گزارش پیدا نشد: ' + key));
      return req('POST', '/report', { key, t: it.t, id: it.id, topic, body }).then(r => r.report);
    },
    reportReply(id, body) {
      return req('POST', '/report/' + id + '/reply', { body }).then(r => r.report);
    },
    reportSeen(id) { if (id) push('POST', '/report/' + id + '/seen', {}); },

    /** کاربر صفحه‌ی اطلاعیه‌ها را دید. */
    announcementsRead() { push('POST', '/announcements/read', {}); },

    /** خروج: داده‌ی این کاربر از مرورگر پاک و بعد خروج محلی و مرکزی (SsoController@logout) */
    logout() { clearLocal(); location.href = '/logout'; },

    /** ذخیره‌ی پروفایل؛ پروفایل تازه‌ی سرور را برمی‌گرداند */
    profile(data) { return req('PUT', '/profile', data).then(r => r.profile); },

    /** آمار جمعی رشته‌های خریده‌شده: { q:{qid:[n,right,blank]}, w:{wid:[n,ok]}, min } */
    crowd()        { return req('GET', '/crowd'); },
    /** آمار کامل یک سؤال با توزیع گزینه‌ها؛ ۴۰۹ = قفل آزمون فیدبک */
    async qStats(qid) {
      try { return await req('GET', '/question/' + qid + '/stats'); }
      catch (e) { if (String(e.message).includes('409')) return { locked: true }; throw e; }
    },
    /** یک بار زدن سؤال در تمرین؛ chosen از ۱ تا ۴ */
    qAttempt(qid, chosen) { if (qid) push('POST', '/question/' + qid + '/attempt', { chosen }); },

    buyQuote(exams) { return req('GET', '/buy/quote?exams=' + exams.join(',')); },
    buyOrder(exams) { return req('POST', '/buy/order', { exams }); },
  };

  function debounce(fn, ms) {
    let t; return function (...a) { clearTimeout(t); t = setTimeout(() => fn.apply(null, a), ms); };
  }

  /* ---------- ضربان فعالیت ---------- */
  function startHeartbeat() {
    setInterval(() => {
      if (document.hidden) return;
      if (Date.now() - lastMove > 120000) return;
      push('POST', '/heartbeat', {});
    }, 30000);
  }
  let lastMove = Date.now();
  ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(ev =>
    window.addEventListener(ev, () => { lastMove = Date.now(); }, { passive: true }));

  /* ---------- پی‌وال ---------- */
  const NAMES = { ce: 'مهندسی کامپیوتر', it: 'آی‌تی', cs: 'علوم کامپیوتر' };
  let paywallOpen = false;

  function showPaywall(info) {
    if (paywallOpen) return;
    paywallOpen = true;
    const name = NAMES[info && info.exam] || 'این رشته';
    const url  = (info && info.buy_url) || '/buy';
    const el = document.createElement('div');
    el.className = 'scrim open';
    el.innerHTML =
      '<div class="sheet" style="padding:28px;max-width:420px;text-align:center">' +
      '<div style="font-size:17px;font-weight:700;margin-bottom:8px">دسترسی «' + name + '» فعال نیست</div>' +
      '<div style="font-size:14px;color:#5b6167;line-height:2;margin-bottom:16px">' +
      'برای استفاده از کلمات و آزمون‌های این رشته، پکیجش را تهیه کنید.</div>' +
      '<a class="deckbtn" href="' + url + '" style="display:inline-block;text-decoration:none">مشاهده‌ی پکیج‌ها</a></div>';
    document.body.appendChild(el);
  }

  function fatal(msg) {
    document.body.innerHTML =
      '<div style="padding:60px;text-align:center;font-family:Vazirmatn,sans-serif;line-height:2">' +
      (msg || 'بارگذاری پلتفرم ناموفق بود. لطفاً صفحه را دوباره باز کنید.') + '</div>';
  }

  return api;
})();