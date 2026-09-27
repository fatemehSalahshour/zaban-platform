@extends('zaban-admin.layout')
@section('title', 'کلمه‌ها و ظهورها')

@section('body')
<div class="head">
  <h2>کلمه‌ها و ظهورها</h2>
  <p>هر کلمه با فهرست سؤال‌هایی که در آن‌ها آمده. اگر کلمه‌ای اشتباهی به سؤالی
     وصل شده، با ✕ همان ردیف را بردارید.</p>
</div>

{{-- پیام موفقیت/خطا را خود layout نشان می‌دهد؛ تکرارش اینجا دوباره‌کاری بود --}}
<div class="panel warn">
  <b>بعد از هر تغییر، دو کار لازم است.</b>
  <ol class="todo">
    <li><b>اتصال به سؤال‌ها را تازه کنید.</b> ردیفی که اینجا اضافه یا ویرایش می‌کنید
        تا وقتی این دستور اجرا نشود به خودِ سؤال وصل نیست و در پلتفرم دیده نمی‌شود.
      <div class="cmd">
        <div class="ch">روی سرور</div>
        <code>docker exec -w /var/www/konkurix language-joomla-php83-1 php artisan zaban:import finalize</code>
      </div>
      <div class="cmd">
        <div class="ch">روی سیستم خودتان (لوکال)</div>
        <code>"C:\wamp64\bin\php\php8.3.14\php.exe" artisan zaban:import finalize</code>
      </div>
    </li>
    <li><b>همین اصلاح را در فایل اکسل هم انجام بدهید.</b> ظهورها از اکسل تیم محتوا
        ساخته می‌شوند؛ اگر فقط اینجا تغییر بدهید، با ورود دوباره‌ی همان فایل
        به حالت قبل برمی‌گردد.</li>
  </ol>
</div>

<form method="get" class="panel srch">
  <input type="search" name="q" value="{{ $q }}" placeholder="کلمه را بنویسید…" autofocus>
  <button class="btn" type="submit">جست‌وجو</button>
  @if ($q !== '')<a class="lnk" href="{{ route('zadmin.words') }}">برداشتن فیلتر</a>@endif
</form>

@forelse ($words as $w)
  <div class="panel wrow">
    <div class="wh">
      <b class="en">{{ $w->word }}</b>
      <span class="fa">{{ $w->meaning_fa }}</span>
      <span class="tag">{{ $w->occurrence_count }} ظهور در {{ $w->year_count }} سال</span>
    </div>

    @php $list = $occ[$w->id] ?? collect(); @endphp
    @if ($list->isEmpty())
      <div class="empty">هیچ ظهوری ثبت نشده.</div>
    @else
      <table class="occ">
        <tr><th>سال</th><th>رشته</th><th>بخش</th><th>سؤال</th><th>متن</th><th></th></tr>
        @foreach ($list as $o)
          <tr>
            <form method="post" action="{{ route('zadmin.words.occ.save', $w->id) }}" id="f{{ $o->id }}">@csrf
              <input type="hidden" name="id" value="{{ $o->id }}"></form>
            <td class="num"><input form="f{{ $o->id }}" name="year" type="number" value="{{ $o->year }}"></td>
            <td>
              <select form="f{{ $o->id }}" name="exam">
                @foreach ($examFa as $c => $n)
                  <option value="{{ $c }}" @selected($o->exam === $c)>{{ $n }}</option>
                @endforeach
              </select>
            </td>
            <td>
              <select form="f{{ $o->id }}" name="section">
                @foreach ($secFa as $c => $n)
                  <option value="{{ $c }}" @selected($o->section === $c)>{{ $n }}</option>
                @endforeach
              </select>
              <input form="f{{ $o->id }}" name="passage_number" type="number" class="sm"
                     value="{{ $o->passage_number }}" placeholder="متن">
            </td>
            <td class="num"><input form="f{{ $o->id }}" name="test_number" type="number"
                   value="{{ $o->test_number }}" placeholder="—"></td>
            <td class="hint">{{ $o->source === 'text' ? 'در خود متن' : 'در سؤال' }}</td>
            <td class="act">
              <button form="f{{ $o->id }}" class="ok" type="submit" title="ذخیره">✓</button>
              <form method="post" action="{{ route('zadmin.words.occ.delete', $o->id) }}"
                    onsubmit="return confirm('این ردیف برداشته شود؟')" style="display:inline">
                @csrf
                <button class="x" type="submit" title="این کلمه در این سؤال نیست">✕</button>
              </form>
            </td>
          </tr>
        @endforeach
      </table>
    @endif

    <form method="post" action="{{ route('zadmin.words.occ.save', $w->id) }}" class="addocc">
      @csrf
      <span>افزودن ظهور تازه:</span>
      <input name="year" type="number" placeholder="سال" required>
      <select name="exam">@foreach ($examFa as $c => $n)<option value="{{ $c }}">{{ $n }}</option>@endforeach</select>
      <select name="section">@foreach ($secFa as $c => $n)<option value="{{ $c }}">{{ $n }}</option>@endforeach</select>
      <input name="passage_number" type="number" class="sm" placeholder="متن">
      <input name="test_number" type="number" class="sm" placeholder="سؤال">
      <button class="btn sm" type="submit">افزودن</button>
    </form>
  </div>
@empty
  <div class="panel"><div class="empty">
    @if ($q !== '') کلمه‌ای با این جست‌وجو پیدا نشد. @else بانک کلمات خالی است. @endif
  </div></div>
@endforelse

@if ($words->hasPages())
  <div class="pager">
    <span>@if ($words->previousPageUrl())<a href="{{ $words->previousPageUrl() }}">صفحه‌ی قبل</a>@endif</span>
    <span>صفحه‌ی {{ $words->currentPage() }} از {{ $words->lastPage() }}</span>
    <span>@if ($words->nextPageUrl())<a href="{{ $words->nextPageUrl() }}">صفحه‌ی بعد</a>@endif</span>
  </div>
@endif

<style>
  .panel.warn{background:#fdf6e4;border-color:#e6d3a0;font-size:13px;line-height:2}
  .todo{margin:8px 0 0;padding-right:20px}
  .todo li{margin-bottom:10px}
  .cmd{margin:6px 0}
  .cmd .ch{font-size:11.5px;color:#8a6d1f;margin-bottom:2px}
  .cmd code{display:block;background:#16324f;color:#e8eef5;padding:8px 11px;border-radius:7px;
    direction:ltr;text-align:left;font-family:Consolas,monospace;font-size:12px;
    line-height:1.7;overflow-x:auto;white-space:pre}
  .srch{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .srch input{flex:1;min-width:200px;font-family:inherit;font-size:14px;padding:8px 11px;
    border:1px solid var(--line-2);border-radius:8px;background:#fff;color:var(--ink)}
  .srch input:focus{outline:2px solid var(--gold);outline-offset:1px}
  .srch .lnk{font-size:12.5px;color:var(--ink-3)}
  .wrow{padding-bottom:8px}
  .wh{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:10px}
  .wh .en{font-size:16px;direction:ltr}
  .wh .fa{color:var(--ink-2);font-size:13.5px}
  table.occ{width:100%;border-collapse:collapse;font-size:13px}
  table.occ th{background:var(--surface);text-align:right;padding:6px 9px;font-weight:600;
    border-bottom:1px solid var(--line);white-space:nowrap}
  table.occ td{padding:6px 9px;border-bottom:1px solid var(--line)}
  table.occ td.num{direction:ltr;text-align:center;font-variant-numeric:tabular-nums;width:60px}
  table.occ td.act{width:44px;text-align:center}
  /* فلش‌های عدد جا را می‌خوردند و «۱۴۰۴» را به «۱۴۰» می‌بریدند */
  table.occ input[type=number],.addocc input[type=number]{-moz-appearance:textfield}
  table.occ input[type=number]::-webkit-outer-spin-button,
  table.occ input[type=number]::-webkit-inner-spin-button,
  .addocc input[type=number]::-webkit-outer-spin-button,
  .addocc input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
  table.occ input,table.occ select{font-family:inherit;font-size:13px;padding:5px 8px;width:100%;
    border:1px solid var(--line-2);border-radius:6px;background:#fff;color:var(--ink);
    text-align:center;font-variant-numeric:tabular-nums}
  table.occ select{text-align:right}
  table.occ input.sm{width:64px;display:inline-block;margin-right:6px}
  table.occ td.num{width:92px}
  table.occ td{vertical-align:middle}
  table.occ td.hint{color:var(--ink-3);font-size:12px;white-space:nowrap}
  table.occ td.act{width:74px;white-space:nowrap}
  .ok{background:none;border:1px solid #c9e4d6;border-radius:7px;width:28px;height:28px;
    cursor:pointer;color:#1d6b58;font-size:13px;line-height:1;margin-left:3px}
  .ok:hover{background:#eaf5ef}
  .addocc{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:10px;
    padding-top:10px;border-top:1px dashed var(--line);font-size:12.5px;color:var(--ink-3)}
  .addocc input,.addocc select{font-family:inherit;font-size:13px;padding:6px 9px;
    border:1px solid var(--line-2);border-radius:6px;background:#fff;color:var(--ink);width:104px;
    text-align:center;font-variant-numeric:tabular-nums}
  .addocc select{text-align:right;width:130px}
  .addocc input.sm{width:74px}
  .btn.sm{font-size:12.5px;padding:5px 12px}
  .x{background:none;border:1px solid var(--line-2);border-radius:7px;width:28px;height:28px;
    cursor:pointer;color:var(--danger);font-size:13px;line-height:1}
  .x:hover{background:#fdf0eb;border-color:#f0cdbe}
  .pager{display:flex;justify-content:space-between;font-size:13.5px;color:var(--ink-3)}
  .pager a{color:var(--gold)}
</style>
@endsection
