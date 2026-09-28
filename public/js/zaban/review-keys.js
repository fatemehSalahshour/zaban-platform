/* میانبرهای صفحه‌کلید برای مرور کارت‌ها — مثل Anki.
 *
 *   Space / Enter → نمایش پاسخ
 *   1 → ساده بود     2 → یادم بود     3 → سخت بود     4 → یادم نبود
 *
 * این فایل مستقل است و فقط با کلیک روی همان دکمه‌های موجود کار می‌کند
 * (#rShow و دکمه‌های data-r داخل #rRate)؛ پس منطق ثبت مرور، FSRS و
 * ذخیره‌سازی دقیقاً همان مسیر همیشگی را می‌رود.
 */
(function () {
  'use strict';

  /* کلید → مقدار data-r دکمه (۱=یادم نبود … ۴=ساده بود).
     ترتیب کلیدها: ۱ ساده، ۲ یادم بود، ۳ سخت، ۴ یادم نبود.
     اگر ترتیب Anki را خواستی (۱ = یادم نبود) این چهار عدد را برعکس کن. */
  var KEY_TO_RATING = { 1: 4, 2: 3, 3: 2, 4: 1 };

  var $ = function (id) { return document.getElementById(id); };

  /* برچسب کلید روی دکمه‌ها — فقط روی دستگاه‌هایی که ماوس/کیبورد دارند.
     با CSS اضافه می‌شود تا اگر app.js متن دکمه را دوباره نوشت، از بین نرود. */
  var css = document.createElement('style');
  var rateSel = Object.keys(KEY_TO_RATING).map(function (k) {
    return '#rRate button[data-r="' + KEY_TO_RATING[k] + '"]::before{content:"' + k + '";}';
  }).join('');
  css.textContent =
    '@media (hover:hover) and (pointer:fine){' +
    '#rRate button::before{display:inline-block;min-width:1.5em;margin-inline-end:.4em;padding:0 .3em;' +
    'border:1px solid currentColor;border-radius:4px;font-size:.75em;line-height:1.5;opacity:.6;text-align:center}' +
    rateSel +
    '#rShow::after{content:"  Space";font-size:.75em;opacity:.55;letter-spacing:.02em}' +
    '}';
  document.head.appendChild(css);

  /* آیا این عنصر واقعاً دیده می‌شود؟ (hidden، display، visibility، opacity) */
  function visible(el) {
    if (!el || el.hidden || el.getClientRects().length === 0) return false;
    var cs = getComputedStyle(el);
    return cs.display !== 'none' && cs.visibility !== 'hidden' &&
           parseFloat(cs.opacity) > 0.01 && cs.pointerEvents !== 'none';
  }

  /* پنجره‌ی مرور باز است و پنجره‌ی دیگری (گزارش خطا، یادداشت، …) روی آن نیست */
  function reviewActive() {
    var r = $('review');
    if (!visible(r)) return false;
    var others = document.querySelectorAll('.scrim');
    for (var i = 0; i < others.length; i++) {
      if (others[i] !== r && visible(others[i])) return false;
    }
    return true;
  }

  function typing(t) {
    if (!t || !t.tagName) return false;
    var n = t.tagName;
    return n === 'INPUT' || n === 'TEXTAREA' || n === 'SELECT' || t.isContentEditable;
  }

  document.addEventListener('keydown', function (e) {
    if (e.repeat || e.ctrlKey || e.metaKey || e.altKey) return;
    if (typing(e.target) || !reviewActive()) return;

    var show = $('rShow');
    var rate = $('rRate');

    /* دکمه‌ی دیگری (مثلاً بستن یا گزارش) فوکوس دارد: Enter/Space کار خودش را بکند.
       دکمه‌های خودِ مرور مستثنی‌اند تا کلیک دوباره نخورد. */
    var t = e.target;
    var own = t === show || (rate && rate.contains(t));
    if (t && (t.tagName === 'BUTTON' || t.tagName === 'A') && !own) return;

    /* نمایش پاسخ */
    if (e.code === 'Space' || e.code === 'Enter' || e.code === 'NumpadEnter') {
      if (visible(show)) {
        e.preventDefault();
        show.click();
      } else if (own) {
        /* فوکوس روی دکمه‌ای است که الان فعال نیست؛ کلیک بومی نزند */
        e.preventDefault();
      }
      return;
    }

    /* امتیاز ۱–۴ (کدِ فیزیکی کلید، پس با کیبورد فارسی هم کار می‌کند) */
    var m = /^(?:Digit|Numpad)([1-4])$/.exec(e.code);
    if (m && visible(rate)) {
      var btn = rate.querySelector('button[data-r="' + KEY_TO_RATING[m[1]] + '"]');
      if (btn && !btn.disabled) {
        e.preventDefault();
        btn.click();
      }
    }
  });
})();
