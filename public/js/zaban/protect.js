/* ============================================================
   protect.js — محافظ محتوا برای دانشجو (کیت امنیت، بخش ۲-۸، ۲-۹ و ۲-۱۰)

   فقط برای دانشجو بار می‌شود (شرط در zaban.blade.php)؛ کارکنان آن را ندارند.
   تنظیم از window.ZPROTECT = {uid, mobile, hide, endpoint, csrf}

   ۱) نشانه‌ی دیدنی: شناسه و موبایل خودِ کاربر، بسیار کم‌رنگ و کاشی‌شده روی صفحه،
      غیرقابل انتخاب و بدون اثر روی کلیک. در هر اسکرین‌شات و عکس از صفحه هست،
      حتی اگر بخشی از صفحه بریده شود.
   ۲) کپی، برش، کلیک راست، کشیدن و میان‌برهای ذخیره/چاپ/سورس/ابزار توسعه بسته‌اند.
      داخل فیلدهای ورودی (جست‌وجو، یادداشت، گزارش) همه‌چیز آزاد است.
      انتخاب متن فقط روی معنی، مثال و پاسخ تشریحی بسته است — نه کل صفحه.
   ۳) پنهان شدن صفحه وقتی پنجره فوکوس را از دست می‌دهد (ابزار برش، PrintScreen،
      رفتن به برنامه‌ی دیگر) یا ابزار توسعه باز می‌شود (فقط کامپیوتر). با برگشتن
      به صفحه خودکار برمی‌گردد. از پنل مدیر قابل خاموش کردن است.
   ۴) چاپ: صفحه‌ی خالی.
   ۵) گزارش رویدادها به سرور، هر نوع حداکثر یک بار در ۳۰ ثانیه.

   محدودیت صادقانه: اسکرین‌شات سیستم‌عامل و عکس با گوشی را نمی‌شود کاملاً بست؛
   برای همین نشانه‌ی دیدنی هست — تا معلوم باشد از حساب چه کسی گرفته شده.
   ============================================================ */
(function () {
  'use strict';
  var C = window.ZPROTECT;
  if (!C || !C.uid) return;

  var html = document.documentElement;
  var touch = ('ontouchstart' in window) || (window.matchMedia && matchMedia('(pointer:coarse)').matches);

  /* ---------- گزارش رویداد (دسته‌ای و کم) ---------- */
  var lastSent = {}, pending = {}, timer = null;
  function report(kind) {
    var now = Date.now();
    if (lastSent[kind] && now - lastSent[kind] < 30000) return;
    lastSent[kind] = now;
    pending[kind] = 1;
    if (!timer) timer = setTimeout(flush, 2000);
  }
  function flush() {
    timer = null;
    var kinds = Object.keys(pending); pending = {};
    if (!kinds.length || !C.endpoint) return;
    try {
      fetch(C.endpoint, {
        method: 'POST', credentials: 'same-origin', keepalive: true,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                   'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': C.csrf || '' },
        body: JSON.stringify({ kinds: kinds })
      }).catch(function () {});
    } catch (e) {}
  }

  /* ---------- ۱) نشانه‌ی دیدنی ---------- */
  function faDigits(s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); }
  function watermark() {
    var label = 'کاربر ' + faDigits(C.uid) + (C.mobile ? ' · ' + faDigits(C.mobile) : '');
    var esc = label.replace(/&/g, '&amp;').replace(/</g, '&lt;');
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="170">' +
      '<text x="150" y="95" text-anchor="middle" transform="rotate(-24 150 85)" ' +
      'font-family="Vazirmatn,Tahoma,sans-serif" font-size="14" fill="#000">' + esc + '</text></svg>';
    var d = document.createElement('div');
    d.id = 'zwm';
    d.setAttribute('aria-hidden', 'true');
    d.style.backgroundImage = 'url("data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg) + '")';
    document.body.appendChild(d);
  }

  /* ---------- CSS ---------- */
  var css = document.createElement('style');
  css.id = 'zprotectCss';
  css.textContent =
    /* نشانه: بالای همه‌چیز (حتی پنجره‌ها)، ولی کلیک از رویش رد می‌شود */
    '#zwm{position:fixed;inset:0;z-index:2147483000;pointer-events:none;opacity:.05;' +
    'user-select:none;-webkit-user-select:none}' +
    'html[data-theme="dark"] #zwm,html[data-theme="dim"] #zwm{filter:invert(1);opacity:.07}' +
    /* انتخاب متن فقط روی محتوای ارزشمند بسته است */
    '.fa-mean,.mean-lg,.rev,.ex,.ex-exp,.lfa,.pr-fa,.rrow .m,.wline span,.relbox,.qans,.ex-ans' +
    '{-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}' +
    'img{-webkit-user-drag:none}' +
    /* پوشش پنهان‌کننده */
    '#zcover{position:fixed;inset:0;z-index:2147483600;background:#14171a;color:#fff;display:none;' +
    'align-items:center;justify-content:center;text-align:center;padding:24px;font-size:15px;' +
    'line-height:1.9;cursor:pointer;user-select:none;-webkit-user-select:none}' +
    'html.zhidden #zcover{display:flex}' +
    'html.zhidden body>*:not(#zcover):not(#zwm){visibility:hidden !important}' +
    /* چاپ: خالی */
    '@media print{body *{visibility:hidden !important}' +
    'body::after{content:"چاپ این صفحه مجاز نیست.";visibility:visible;display:block;padding:40px;font-size:18px}}';
  document.head.appendChild(css);

  /* ---------- ۲) کپی، کلیک راست، میان‌برها ---------- */
  function editable(el) {
    if (!el || el.nodeType !== 1) el = el && el.parentElement;
    if (!el) return false;
    var t = (el.tagName || '').toLowerCase();
    return t === 'input' || t === 'textarea' || el.isContentEditable;
  }
  document.addEventListener('copy', function (e) {
    if (editable(e.target)) return;
    e.preventDefault();
    if (e.clipboardData) e.clipboardData.setData('text/plain', 'کپی محتوای پلتفرم مجاز نیست.');
    report('copy');
  }, true);
  document.addEventListener('cut', function (e) {
    if (editable(e.target)) return;
    e.preventDefault(); report('copy');
  }, true);
  document.addEventListener('contextmenu', function (e) {
    if (editable(e.target)) return;
    e.preventDefault(); report('contextmenu');
  }, true);
  document.addEventListener('dragstart', function (e) {
    if (editable(e.target)) return;
    e.preventDefault();
  }, true);
  document.addEventListener('keydown', function (e) {
    var k = e.key || '', mod = e.ctrlKey || e.metaKey;
    if (k === 'PrintScreen') {
      try { navigator.clipboard && navigator.clipboard.writeText(''); } catch (err) {}
      hide(); report('printscreen');
      return;
    }
    var block = k === 'F12' ||
      (mod && /^[sSpPuU]$/.test(k)) ||                       /* ذخیره، چاپ، سورس */
      (mod && e.shiftKey && /^[iIjJcCkK]$/.test(k));         /* ابزار توسعه */
    if (block) { e.preventDefault(); e.stopPropagation(); report(k === 'F12' || e.shiftKey ? 'devtools' : 'key'); }
  }, true);
  window.addEventListener('beforeprint', function () { report('print'); });

  /* ---------- ۳) پنهان شدن ---------- */
  var cover = null, devOpen = false;
  function hide() {
    if (!C.hide) return;
    if (!cover) {
      cover = document.createElement('div');
      cover.id = 'zcover';
      cover.setAttribute('role', 'alert');
      cover.innerHTML = '<div><div style="font-size:30px;margin-bottom:8px">🔒</div>' +
        'برای حفاظت از محتوا، صفحه موقتاً پنهان شد.<br>برای ادامه روی صفحه بزنید.</div>';
      cover.addEventListener('click', show);
      document.body.appendChild(cover);
    }
    if (!html.classList.contains('zhidden')) { html.classList.add('zhidden'); report('hide'); }
  }
  function show() {
    if (devOpen) return;
    html.classList.remove('zhidden');
  }
  if (C.hide) {
    document.addEventListener('visibilitychange', function () { document.hidden ? hide() : show(); });
    window.addEventListener('blur', hide);
    window.addEventListener('focus', show);
  }

  /* ابزار توسعه‌ی چسبیده به پنجره — فقط کامپیوتر. روی گوشی باز شدن صفحه‌کلید
     ارتفاع را عوض می‌کند و این روش اشتباه می‌گرفت. مبنا اختلاف لحظه‌ی بارگذاری است؛
     فقط افزایش ناگهانیِ یک بُعد (بیش از ۱۶۰px) یعنی ابزار توسعه. */
  if (C.hide && !touch) {
    var base = null, baseDpr = null;
    var check = function () {
      var d = { w: window.outerWidth - window.innerWidth, h: window.outerHeight - window.innerHeight };
      var dpr = window.devicePixelRatio || 1;
      if (!base || baseDpr !== dpr) { base = d; baseDpr = dpr; return; }
      var gw = d.w - base.w > 160, gh = d.h - base.h > 160;
      var open = (gw && !gh) || (gh && !gw);
      if (open && !devOpen) { devOpen = true; hide(); report('devtools'); }
      else if (!open && devOpen) { devOpen = false; if (document.hasFocus()) show(); }
      else if (!open) { base = d; }
    };
    window.addEventListener('resize', check);
    setInterval(check, 1500);
    check();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', watermark);
  else watermark();
})();
