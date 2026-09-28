/* سقف روزانه‌ی «کارت تازه» و «مرور» — تغییر مستقیم از داشبورد.
 *
 *  ۱) پنل «سقف روزانه» بالای بخش برنامه‌ی داشبورد (#tab-rank) اضافه می‌شود.
 *  ۲) هر عنصری با data-limits-open پنجره‌ی تغییر سریع را باز می‌کند؛
 *     پیام «سقف امروز پر شد» فقط باید چنین لینکی داشته باشد.
 *     از کد هم می‌شود: window.ZabanLimits.open().
 *
 * فقط به PATCH /api/profile/limits حرف می‌زند، پس بقیه‌ی پروفایل دست نمی‌خورد.
 * سقف فعلی داخل حافظه‌ی app.js است؛ بعد از ذخیره صفحه یک بار تازه می‌شود.
 */
(function () {
  'use strict';

  var URL_ = '/api/profile/limits';

  function fa(n) { return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); }
  function en(v) {
    return String(v == null ? '' : v)
      .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
      .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); })
      .trim();
  }
  function xsrf() {
    var m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
  }
  function api(method, body) {
    var h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var t = xsrf();
    if (t) h['X-XSRF-TOKEN'] = t;
    if (body) h['Content-Type'] = 'application/json';
    return fetch(URL_, { method: method, headers: h, credentials: 'same-origin',
                         body: body ? JSON.stringify(body) : undefined })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (!r.ok) {
            var msg = j.message || 'ذخیره نشد. دوباره تلاش کنید.';
            if (j.errors) msg = Object.keys(j.errors).map(function (k) { return j.errors[k][0]; }).join(' ');
            throw new Error(msg);
          }
          return j;
        });
      });
  }

  /* یک فرم (دو ورودی + دکمه) که هم در پنل و هم در پنجره استفاده می‌شود */
  function form(onSaved) {
    var box = document.createElement('div');
    box.innerHTML =
      '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px">' +
      '<label style="display:block"><span style="display:block;font-size:13px;margin-bottom:4px">کارت تازه در روز</span>' +
      '<input class="inp en" data-k="new" inputmode="numeric" dir="ltr" style="width:100%">' +
      '<span data-h="new" style="display:block;font-size:11.5px;opacity:.65;margin-top:4px"></span></label>' +
      '<label style="display:block"><span style="display:block;font-size:13px;margin-bottom:4px">مرور در روز</span>' +
      '<input class="inp en" data-k="rev" inputmode="numeric" dir="ltr" style="width:100%">' +
      '<span data-h="rev" style="display:block;font-size:11.5px;opacity:.65;margin-top:4px"></span></label>' +
      '</div>' +
      '<div style="display:flex;align-items:center;gap:12px;margin-top:14px">' +
      '<button type="button" class="solid gold" data-save>ذخیره</button>' +
      '<span data-msg style="font-size:12.5px"></span></div>';

    var inNew = box.querySelector('[data-k="new"]'), inRev = box.querySelector('[data-k="rev"]');
    var btn = box.querySelector('[data-save]'), msg = box.querySelector('[data-msg]');
    var st = null;

    function say(t, bad) { msg.textContent = t || ''; msg.style.color = bad ? '#c0392b' : 'inherit'; }
    function fill(j) {
      st = j;
      inNew.value = j.new_per_day; inRev.value = j.rev_per_day;
      inNew.placeholder = fa(j.new_default); inRev.placeholder = fa(j.rev_default);
      box.querySelector('[data-h="new"]').textContent =
        'بین ۵ تا ۱۰۰ · امروز ' + fa(j.new_today) + ' کارت تازه دیده‌اید · خالی = پیش‌فرض (' + fa(j.new_default) + ')';
      box.querySelector('[data-h="rev"]').textContent =
        'بین ۱۰ تا ۵۰۰ · خالی = پیش‌فرض (' + fa(j.rev_default) + ')';
    }

    function num(el, min, max, label) {
      var v = en(el.value);
      if (v === '') return null;                       /* خالی = پیش‌فرض مدیر */
      if (!/^\d+$/.test(v) || +v < min || +v > max) throw new Error(label + ' باید عددی بین ' + fa(min) + ' تا ' + fa(max) + ' باشد.');
      return +v;
    }

    btn.addEventListener('click', function () {
      var body;
      try {
        body = { new_per_day: num(inNew, 5, 100, 'کارت تازه در روز'), rev_per_day: num(inRev, 10, 500, 'مرور در روز') };
      } catch (e) { return say(e.message, true); }
      btn.disabled = true; say('در حال ذخیره…');
      api('PATCH', body).then(function (j) {
        fill(j); say('ذخیره شد.');
        if (onSaved) onSaved(j);
      }).catch(function (e) { say(e.message, true); })
        .then(function () { btn.disabled = false; });
    });

    say('در حال بارگذاری…');
    api('GET').then(function (j) { fill(j); say(''); })
      .catch(function () { say('سقف‌ها بارگذاری نشد.', true); });

    return box;
  }

  function reloadSoon() { setTimeout(function () { location.reload(); }, 700); }

  /* ---------- پنجره‌ی تغییر سریع ---------- */
  function openDialog() {
    if (document.getElementById('zlDlg')) return;
    var ov = document.createElement('div');
    ov.id = 'zlDlg';
    ov.style.cssText = 'position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.55);display:flex;' +
      'align-items:center;justify-content:center;padding:16px';
    var sheet = document.createElement('div');
    sheet.style.cssText = 'background:var(--surface,var(--bg,#1b1b1b));color:var(--ink,inherit);' +
      'border:1px solid var(--line,rgba(128,128,128,.35));border-radius:14px;padding:20px;' +
      'width:100%;max-width:460px;direction:rtl;font-family:inherit';
    sheet.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">' +
      '<b>سقف روزانه</b><button type="button" class="ghost" data-x aria-label="بستن">×</button></div>' +
      '<p style="font-size:12.5px;opacity:.75;margin:0 0 14px">سقف‌ها را همین‌جا عوض کنید؛ بعد از ذخیره مرور با سقف تازه ادامه پیدا می‌کند.</p>';
    sheet.appendChild(form(reloadSoon));
    ov.appendChild(sheet);
    function close() { ov.remove(); document.removeEventListener('keydown', esc, true); }
    function esc(e) { if (e.key === 'Escape') { e.stopPropagation(); close(); } }
    ov.addEventListener('click', function (e) { if (e.target === ov) close(); });
    sheet.querySelector('[data-x]').addEventListener('click', close);
    document.addEventListener('keydown', esc, true);
    document.body.appendChild(ov);
  }

  /* ---------- پنل داشبورد ---------- */
  function mountPanel() {
    var tab = document.getElementById('tab-rank');
    if (!tab || document.getElementById('zlPanel')) return;
    var panel = document.createElement('div');
    panel.className = 'panel'; panel.id = 'zlPanel';
    panel.innerHTML = '<div class="label">سقف روزانه‌ی مرور</div>' +
      '<p style="font-size:12.5px;opacity:.75;margin:4px 0 14px">چند کارت تازه و چند مرور در هر روز؟ ' +
      'هر وقت خواستید عوضش کنید؛ خالی بگذارید تا پیش‌فرض پلتفرم اعمال شود.</p>';
    panel.appendChild(form(reloadSoon));
    var slot = document.getElementById('planSlot');
    if (slot && slot.parentNode === tab) tab.insertBefore(panel, slot);
    else tab.appendChild(panel);
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest && e.target.closest('[data-limits-open]');
    if (t) { e.preventDefault(); openDialog(); }
  });

  window.ZabanLimits = { open: openDialog };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountPanel);
  else mountPanel();
})();
