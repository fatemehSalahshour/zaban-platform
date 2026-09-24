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
    <h3>نسخه‌ی نمایشی (دمو)</h3>
    <p>کاربری که رشته‌ای را نخریده، به‌جای پیام «دسترسی فعال نیست» همه‌ی امکانات
       یک سال از آن رشته را رایگان دارد: کلمات، تست‌ها با پاسخ و تشریحی، آزمون آزمایشی،
       مرور و یادداشت. محتوای سال‌های دیگر اصلاً برایش فرستاده نمی‌شود.</p>

    <div class="field">
      <label for="demo_year">سال دمو
        <i>عددها: تعداد سؤال وارد‌شده در کامپیوتر / آی‌تی / علوم</i></label>
      <select id="demo_year" name="demo_year">
        <option value="">خاموش — بدون دمو</option>
        @foreach ($qCount as $y => $c)
          <option value="{{ $y }}" @selected((int) old('demo_year', $demoYear) === (int) $y)>
            {{ $y }} — {{ $c['ce'] ?? 0 }} / {{ $c['it'] ?? 0 }} / {{ $c['cs'] ?? 0 }}
            @if (!array_sum($c)) (هنوز سؤالی وارد نشده — فقط کلمات) @endif
          </option>
        @endforeach
      </select>
    </div>

    <div class="field">
      <label>رشته‌های دمو
        <i>برای کاربری که هنوز رشته‌ای در پروفایل انتخاب نکرده. اگر انتخاب کرده باشد،
           فقط همان رشته دمو است. خرید یک رشته، دموی بقیه را برنمی‌دارد.</i></label>
      <div class="chk">
        @foreach (\App\Services\Pricing::NAMES as $code => $name)
          <label><input type="checkbox" name="demo_exams[]" value="{{ $code }}"
                 @checked(in_array($code, old('demo_exams', $demoExams), true))> {{ $name }}</label>
        @endforeach
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>مرور</h3>
    <p>سقف کارت تازه‌ای که هر روز وارد «مرور امروز» می‌شود. این پیش‌فرض همه است؛
       هر دانشجو می‌تواند در پروفایل خودش عدد دیگری (۵ تا ۱۰۰) بگذارد.</p>
    <div class="field">
      <label for="new_per_day">کارت تازه در روز <i>پیش‌فرض ۲۰ · بین ۵ تا ۱۰۰</i></label>
      <input type="number" id="new_per_day" name="new_per_day" dir="ltr" min="5" max="100"
             value="{{ old('new_per_day', $newPerDay) }}" style="max-width:140px">
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
  select#demo_year{max-width:320px}
  .ver{margin-top:16px;padding-top:14px;border-top:1px solid var(--line);
       font-size:12.5px;color:var(--ink-3)}
</style>

<script>
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
