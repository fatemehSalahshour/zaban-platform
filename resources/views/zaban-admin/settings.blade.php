@extends('zaban-admin.layout')
@section('title', 'قیمت و تاریخ کنکور')

@section('body')
<div class="head">
  <h2>قیمت و تاریخ کنکور</h2>
  <p>قیمت‌ها همین‌جا عوض می‌شوند و بلافاصله در صفحه‌ی خرید اثر می‌کنند.
     مبلغ نهایی هر سفارش روی سرور حساب می‌شود، پس تغییر اینجا امن است.</p>
</div>

<form method="post" action="{{ route('zadmin.settings.save') }}">
  @csrf

  <div class="panel">
    <h3>پکیج‌ها</h3>
    <p>دانشجو یک، دو یا هر سه رشته را می‌خرد. هرچه بیشتر، ارزان‌تر —
       عدد سمت چپ نشان می‌دهد هر رشته در آن پله چند درمی‌آید.</p>

    @foreach ([1 => 'یک رشته', 2 => 'دو رشته', 3 => 'هر سه رشته'] as $n => $label)
      <div class="field">
        <label for="p{{ $n }}">{{ $label }}
          <i>{{ ['یک','دو','سه'][$n-1] }} رشته از میان کامپیوتر، آی‌تی و علوم کامپیوتر</i>
        </label>
        <div class="tier">
          <input type="number" id="p{{ $n }}" name="p{{ $n }}" min="0" step="10000"
                 value="{{ old('p'.$n, $bundles[$n]) }}" data-n="{{ $n }}"
                 aria-describedby="per{{ $n }}">
          <span class="unit">تومان</span>
          <span class="per" id="per{{ $n }}"></span>
        </div>
      </div>
    @endforeach

    <div class="ver">نسخه‌ی قیمت فعلی: <b>{{ $version }}</b> — با هر ذخیره یک شماره جلو می‌رود
      و روی سفارش‌های تازه ثبت می‌شود.</div>
  </div>

  <div class="panel" id="gateways">
    <h3>درگاه پرداخت</h3>
    <p>کدام درگاه‌ها در صفحه‌ی خرید به دانشجو نشان داده شوند. اگر یکی مشکل پیدا کرد،
       تیکش را بردارید و ذخیره کنید — بلافاصله از صفحه‌ی خرید حذف می‌شود.</p>

    <input type="hidden" name="gateways_form" value="1">
    @php
      $gwOn  = collect(old('gateways_form') ? old('gateways', []) : $gwPrefs['on']);
      $gwDef = old('gateway_default', $gwPrefs['default'] ?? $gwOn->first());
    @endphp

    <div class="gwgrid">
      @foreach ($gwReady as $key => $g)
        <label class="gwc {{ $gwOn->contains($key) ? 'on' : '' }} {{ $g['ready'] ? '' : 'off' }}">
          <input type="checkbox" name="gateways[]" value="{{ $key }}" @checked($gwOn->contains($key))>
          <span class="box" aria-hidden="true">✓</span>
          <span class="nm">{{ $g['label'] }}</span>
          <span class="st {{ $g['ready'] ? 'ready' : '' }}">{{ $g['ready'] ? 'آماده' : $g['why'] }}</span>
        </label>
      @endforeach
    </div>

    <div class="gwdef">
      <label for="gateway_default">درگاه پیش‌فرض <i>وقتی هر دو روشن‌اند، این از قبل انتخاب شده و اول نشان داده می‌شود</i></label>
      <select id="gateway_default" name="gateway_default">
        @foreach ($gwReady as $key => $g)
          <option value="{{ $key }}" @selected($gwDef === $key)>{{ $g['label'] }}</option>
        @endforeach
      </select>
    </div>

    <p class="gwhelp">حداقل یک درگاه باید روشن باشد. اگر فقط یکی روشن باشد، دانشجو انتخابی نمی‌بیند
       و مستقیم به همان درگاه می‌رود. درگاهی که «تنظیم نشده» یا «خاموش در <bdi>.env</bdi>» است، حتی اگر
       تیک بخورد نشان داده نمی‌شود. سفارش‌های قبلی هر درگاه همچنان پیگیری و تأیید می‌شوند.</p>
  </div>

  <div class="panel">
    <h3>تخفیف رونمایی</h3>
    <p>یک تخفیف درصدی مدت‌دار که <b>روی مبلغ نهایی</b> می‌نشیند — یعنی روی تخفیف
       پلکانی سوار می‌شود، نه جایگزین آن. بیرون از بازه خودش خاموش می‌شود و
       لازم نیست چیزی را دستی برگردانید.</p>

    @php
      $jToday = \App\Support\Jalali::formatFromGregorian(now()->format('Y-m-d'));
      $jTwoWk = \App\Support\Jalali::formatFromGregorian(now()->addDays(14)->format('Y-m-d'));
    @endphp

    @if ($launch['active'])
      <div class="ok" style="margin-bottom:14px">
        هم‌اکنون فعال است: <b>{{ $launch['percent'] }}٪</b>
        @if ($launch['days_left'] !== null) — {{ $launch['days_left'] }} روز مانده @endif
      </div>
    @elseif ($launch['percent'] > 0)
      <div class="dev" style="margin-bottom:14px">درصد تنظیم شده ولی بیرون از بازه است، پس روی هیچ سفارشی اثر ندارد.</div>
    @endif

    <div class="field">
      <label for="launch_off">درصد تخفیف
        <i>۰ یعنی خاموش. سقف ۹۰٪ است تا مبلغ سهواً صفر نشود.</i>
      </label>
      <div class="tier">
        <input type="number" id="launch_off" name="launch_off" min="0" max="90" step="1"
               value="{{ old('launch_off', $launch['percent']) }}">
        <span class="unit">٪</span>
      </div>
    </div>

    <div class="field">
      <label for="launch_off_title">متن تخفیف
        <i>همین نوشته کنار درصد، روی صفحه‌ی خرید و لندینگ می‌آید. خالی = «تخفیف رونمایی»</i>
      </label>
      <input type="text" id="launch_off_title" name="launch_off_title" maxlength="40"
             placeholder="{{ \App\Services\Pricing::LAUNCH_TITLE_DEFAULT }}"
             value="{{ old('launch_off_title', $launch['title_raw'] ?? '') }}">
    </div>

    <div class="field">
      <label for="launch_off_note">توضیح کوتاه
        <i>اختیاری — یک خط زیر بنر صفحه‌ی خرید، مثلاً «به مناسبت شروع سال تحصیلی»</i>
      </label>
      <input type="text" id="launch_off_note" name="launch_off_note" maxlength="120"
             value="{{ old('launch_off_note', $launch['note'] ?? '') }}">
    </div>

    <div class="field">
      <label for="launch_off_from">از تاریخ
        <i>شمسی — خالی یعنی از همین حالا. امروز: {{ $jToday }}</i>
      </label>
      <input type="text" id="launch_off_from" name="launch_off_from" dir="ltr"
             placeholder="{{ str_replace('-', '/', $jToday) }}"
             value="{{ old('launch_off_from', $launch['from'] ? str_replace('-', '/', \App\Support\Jalali::formatFromGregorian(substr($launch['from'], 0, 10))) : '') }}">
    </div>

    <div class="field">
      <label for="launch_off_to">تا تاریخ
        <i>شمسی — تا پایان همان روز. برای دو هفته: {{ $jTwoWk }}</i>
      </label>
      <input type="text" id="launch_off_to" name="launch_off_to" dir="ltr"
             placeholder="{{ str_replace('-', '/', $jTwoWk) }}"
             value="{{ old('launch_off_to', $launch['to'] ? str_replace('-', '/', \App\Support\Jalali::formatFromGregorian(substr($launch['to'], 0, 10))) : '') }}">
    </div>
  </div>

  <div class="panel">
    <h3>تاریخ کنکور</h3>
    <p>دسترسی خریداری‌شده تا این تاریخ معتبر است و شمارش معکوس داشبورد
       دانشجو هم از همین می‌آید.</p>

    <div class="field">
      <label for="exam_date">تاریخ و ساعت
        <i>شمسی — مثلاً ۱۴۰۶/۰۲/۱۶ ، یا با ساعت: 1406-02-16 08:00 (بدون ساعت یعنی پایان همان روز)</i>
      </label>
      <input type="text" id="exam_date" name="exam_date" dir="ltr"
             placeholder="1406-02-16"
             value="{{ old('exam_date', $examDate) }}">
    </div>
  </div>

  <div class="panel">
    <h3>نسخه‌ی آزمایشی</h3>
    <p>کاربری که رشته‌ای را نخریده <b>همه‌ی سال‌ها و رشته‌ها را می‌بیند</b>: فهرست کلمه‌ها،
       نوار سال‌ها، جدول «کجا در کنکور آمده»، پوشش بانک و پیش‌بینی. یعنی قابلیت‌های
       پلتفرم را کامل لمس می‌کند.</p>
    <p>آنچه محدود است «عمق» است: معنی و مثال تا سقف مشخصی <b>کلمه‌ی متمایز</b>، و
       پاسخ و آزمون فقط روی دفترچه‌هایی که پایین انتخاب می‌کنید.</p>
    <p><i>سقف روی کلمه‌ی متمایز است نه کلیک — کلمه‌ای که یک بار باز شده، هر بار
       دیگر رایگان است. وگرنه کاربر از کلیک کردن می‌ترسد.</i></p>

    <div class="field">
      <label><input type="checkbox" name="trial_on" value="1" @checked(old('trial_on', $trialOn))>
        نسخه‌ی آزمایشی روشن باشد</label>
    </div>

    <div class="field">
      <label for="trial_cap">سقف کلمه <i>پیش‌فرض ۲۰۰ · مجموع، نه روزانه</i></label>
      <input type="number" id="trial_cap" name="trial_cap" dir="ltr" min="0" max="5000"
             value="{{ old('trial_cap', $trialCap) }}">
    </div>

    <div class="field">
      <label>دفترچه‌های آزمایشی
        <i>روی این‌ها آزمون کامل و پاسخ و تشریحی باز است. دو تا کافی است.
           عددها: تعداد سؤال وارد‌شده.</i></label>
      <div class="chk">
        @php $picked = collect(old('trial_books', array_map(fn ($b) => $b['year'] . ':' . $b['exam'], $trialBooks))); @endphp
        @foreach ($qCount as $y => $c)
          @foreach (\App\Services\Pricing::NAMES as $code => $name)
            @if (($c[$code] ?? 0) > 0)
              <label><input type="checkbox" name="trial_books[]" value="{{ $y }}:{{ $code }}"
                     @checked($picked->contains($y . ':' . $code))>
                {{ $y }} {{ $name }} <small>({{ $c[$code] }} سؤال)</small></label>
            @endif
          @endforeach
        @endforeach
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>مرور</h3>
    <p>دو سقف جدا: کارت‌های <b>تازه</b>ای که هر روز وارد «مرور امروز» می‌شوند، و کل
       کارت‌های <b>سررسیدشده</b>ای که در یک روز پرسیده می‌شوند. این‌ها پیش‌فرض همه‌اند؛
       هر دانشجو می‌تواند در پروفایل خودش عدد دیگری بگذارد — مثل انکی.</p>
    <div class="field">
      <label for="new_per_day">کارت تازه در روز <i>پیش‌فرض ۲۰ · بین ۵ تا ۱۰۰</i></label>
      <input type="number" id="new_per_day" name="new_per_day" dir="ltr" min="5" max="100"
             value="{{ old('new_per_day', $newPerDay) }}" style="max-width:140px">
    </div>
    <div class="field">
      <label for="rev_per_day">مرور در روز <i>پیش‌فرض ۶۰ · بین ۱۰ تا ۵۰۰ · طول هر نوبت مرور هم همین است</i></label>
      <input type="number" id="rev_per_day" name="rev_per_day" dir="ltr" min="10" max="500"
             value="{{ old('rev_per_day', $revPerDay) }}" style="max-width:140px">
    </div>
  </div>

  <div class="panel">
    <h3>امنیت محتوا</h3>
    <p>بانک لغات و پاسخ‌نامه یک‌جا به مرورگر فرستاده نمی‌شود؛ هر کاربر روزانه تا این سقف‌ها
       می‌گیرد و رفتار شبیه اسکریپت حساب را خودکار قفل می‌کند (هشدارها و باز کردن قفل:
       «هشدارهای امنیتی»). «مطالعه‌ی ترتیبی» هر بار معنی ۲۰۰ تا ۳۰۰ کلمه را نشان می‌دهد —
       سقف و آستانه‌ی معنی را خیلی پایین نگذارید تا داوطلب واقعی قفل نشود.</p>

    <div class="secgrid">
      @foreach ($secFields as $name => [$label, $min, $max])
        <div class="field">
          <label for="sec_{{ $name }}">{{ $label }}</label>
          <input type="number" id="sec_{{ $name }}" name="sec[{{ $name }}]" dir="ltr"
                 min="{{ $min }}" max="{{ $max }}" value="{{ old('sec.'.$name, $sec[$name]) }}">
        </div>
      @endforeach
    </div>

    <div class="field">
      <label class="chk-one"><input type="checkbox" name="sec_list_meanings" value="1"
             @checked(old('sec_list_meanings', $sec['list_meanings']))>
        فهرست کلمات، دک و منتخب‌ها با معنی نشان داده شوند
        <i>خاموش: معنی فقط با باز کردن کلمه (مصرف سقف روزانه خیلی کمتر)</i></label>
    </div>
  </div>

  <div class="actions">
    <button class="btn" type="submit">ذخیره‌ی تغییرات</button>
    <a class="btn ghost" href="{{ route('zadmin.settings') }}">انصراف</a>
  </div>
</form>

<style>
  .tier{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
  .tier input{max-width:190px}
  .unit{font-size:12.5px;color:var(--ink-3)}
  .per{font-size:12.5px;color:var(--ink-3);padding:2px 10px;border-radius:20px;
       background:var(--surface);border:1px solid var(--line);
       font-variant-numeric:tabular-nums}
  .per.save{background:#fdf6e4;border-color:#e6d3a0;color:#8f6d1f}
  .chk{display:flex;gap:18px;flex-wrap:wrap;font-size:14px}
  .secgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:4px 18px}
  .secgrid input[type=number]{max-width:140px}
  .chk-one{display:flex;align-items:center;gap:8px;flex-wrap:wrap;cursor:pointer;font-size:14px}
  .chk-one input{accent-color:var(--gold);width:16px;height:16px}
  .chk label{display:flex;align-items:center;gap:6px;cursor:pointer}
  .chk input{accent-color:var(--gold);width:16px;height:16px}
  #trial_cap{max-width:160px}
  /* درگاه پرداخت */
  .gwgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,250px));gap:12px;margin-bottom:18px}
  .gwc{position:relative;display:flex;align-items:center;gap:10px;border:1px solid var(--line-2);
       border-radius:var(--r);padding:13px 14px;cursor:pointer;background:#fff;transition:.15s}
  .gwc:hover{border-color:var(--ink-3)}
  .gwc input{position:absolute;opacity:0;pointer-events:none}
  .gwc .box{width:20px;height:20px;border:1.5px solid var(--line-2);border-radius:5px;flex:none;
       display:flex;align-items:center;justify-content:center;font-size:12px;color:transparent;background:#fff}
  .gwc .nm{font-size:15px;font-weight:700;color:var(--ink)}
  .gwc .st{margin-inline-start:auto;font-size:11px;padding:2px 9px;border-radius:20px;
       background:var(--surface);color:var(--ink-3);white-space:nowrap}
  .gwc .st.ready{background:#e6f2ec;color:var(--green)}
  .gwc.on{border-color:var(--gold);background:#fdf8ec}
  .gwc.on .box{border-color:var(--gold);color:var(--gold)}
  .gwc.off .nm{color:var(--ink-3)}
  .gwc:focus-within{outline:2px solid var(--gold);outline-offset:2px}
  .gwdef label{display:block;font-size:13px;color:var(--ink-2);margin-bottom:7px}
  .gwdef label i{font-style:normal;font-size:11.5px;color:var(--ink-3)}
  .gwdef select{font-family:inherit;font-size:14px;padding:9px 11px;width:100%;max-width:360px;
       border:1px solid var(--line-2);border-radius:8px;background:#fff;color:var(--ink)}
  .gwdef select:focus{outline:2px solid var(--gold);outline-offset:1px}
  .gwhelp{font-size:12px;color:var(--ink-3);line-height:1.95;margin-top:14px;max-width:none}
  .ver{margin-top:16px;padding-top:14px;border-top:1px solid var(--line);
       font-size:12.5px;color:var(--ink-3)}
</style>

<script>
/* کارت درگاه: حالت انتخاب، و درگاه پیش‌فرض فقط از میان روشن‌ها */
(function () {
  const cards = [...document.querySelectorAll('.gwc')];
  const sel = document.getElementById('gateway_default');
  if (!cards.length || !sel) return;
  function sync() {
    const on = cards.filter(c => c.querySelector('input').checked).map(c => c.querySelector('input').value);
    cards.forEach(c => c.classList.toggle('on', c.querySelector('input').checked));
    [...sel.options].forEach(o => { o.disabled = !on.includes(o.value); });
    if (on.length && !on.includes(sel.value)) sel.value = on[0];
    sel.disabled = on.length < 2;          /* فقط یکی روشن → پیش‌فرض معنی ندارد */
  }
  cards.forEach(c => c.querySelector('input').addEventListener('change', sync));
  /* select غیرفعال فرستاده نمی‌شود؛ پیش از ارسال باز می‌شود تا مقدارش ذخیره شود */
  sel.form.addEventListener('submit', () => { sel.disabled = false; });
  sync();
})();

/* قیمت سرانه‌ی هر پله زنده حساب می‌شود. تصمیم واقعی مدیر همین است:
   «دو رشته‌ای شد نفری ششصد» — نه اینکه عدد کل چقدر باشد. */
(function () {
  const fa = n => n.toLocaleString('fa-IR');
  const inputs = [...document.querySelectorAll('.tier input')];

  function paint() {
    const one = +inputs[0].value || 0;
    inputs.forEach(inp => {
      const n = +inp.dataset.n;
      const total = +inp.value || 0;
      const per = n ? Math.round(total / n) : 0;
      const box = document.getElementById('per' + n);

      if (n === 1) { box.textContent = 'پایه‌ی محاسبه'; box.className = 'per'; return; }

      const off = one * n - total;
      box.textContent = 'هر رشته ' + fa(per) + ' تومان'
                      + (off > 0 ? ' · ' + fa(off) + ' تومان تخفیف' : '');
      box.className = off > 0 ? 'per save' : 'per';
    });
  }

  inputs.forEach(i => i.addEventListener('input', paint));
  paint();
})();
</script>
@endsection
