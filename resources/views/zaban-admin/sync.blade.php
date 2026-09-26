@extends('zaban-admin.layout')
@section('title', 'همگام‌سازی سؤال‌ها')

@php $fa = ['ce' => 'مهندسی کامپیوتر', 'it' => 'آی‌تی', 'cs' => 'علوم کامپیوتر']; @endphp

@section('body')
<div class="head">
  <h2>همگام‌سازی سؤال‌ها</h2>
  <p>سؤال‌هایی که اپراتورها در پلتفرم آزمون وارد کرده‌اند، با این دکمه به سایت زبان می‌آیند —
     همراه با سال، ساختار دفترچه و متن‌های کلوز و پسیج. سال تازه خودش در همه‌ی فهرست‌ها ظاهر می‌شود.</p>
</div>

@if ($running)
  <div class="panel"><div class="flash">یک همگام‌سازی از ساعت {{ $running }} در حال اجراست. چند دقیقه‌ی دیگر صفحه را تازه کنید.</div></div>
@endif

<div class="panel">
  <h3>۱) بررسی</h3>
  <p>فقط نگاه می‌کند و چیزی را تغییر نمی‌دهد. کنار هر دفترچه ✓ یعنی آماده، ! یعنی شماره‌ی سؤال‌هایش
     با بانک کلمات نمی‌خواند (در پلتفرم آزمون سؤالی جا افتاده یا ترتیبش به‌هم خورده).</p>
  <form method="post" action="{{ route('zadmin.sync.check') }}">@csrf
    <button class="btn ghost" type="submit" @disabled($running)>بررسی دفترچه‌ها</button>
  </form>
</div>

<div class="panel">
  <h3>۲) همگام‌سازی</h3>
  <p>دفترچه‌های ✓ وارد یا به‌روز می‌شوند؛ دفترچه‌های ! وارد نمی‌شوند تا اول در پلتفرم آزمون درست شوند.
     ممکن است یکی دو دقیقه طول بکشد — اگر صفحه را ببندید هم کار تمام می‌شود.</p>
  <form method="post" action="{{ route('zadmin.sync.apply') }}"
        onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='در حال همگام‌سازی…'">@csrf
    <button class="btn" type="submit" @disabled($running)>همگام‌سازی سؤال‌ها</button>
  </form>
</div>

@if ($gaps)
<div class="panel">
  <h3>سال‌هایی که کلمه دارند ولی سؤالشان در سایت نیست</h3>
  <p>یا هنوز در پلتفرم آزمون وارد نشده‌اند، یا وارد شده‌اند و همگام‌سازی نشده‌اند.</p>
  <div class="gaps">
    @foreach ($gaps as $g)<span class="tag">{{ $g['year'] }} {{ $fa[$g['exam']] ?? $g['exam'] }}</span>@endforeach
  </div>
</div>
@endif

@if ($last)
@php $rep = \App\Http\Controllers\ZabanAdminSyncController::parseLog($last['log']); @endphp
<div class="panel">
  <h3>گزارش آخرین اجرا</h3>
  <p class="runmeta">{{ ['check' => 'بررسی', 'done' => 'همگام‌سازی موفق', 'failed' => 'ناموفق'][$last['status']] ?? $last['status'] }}
     — {{ $last['at'] }} — {{ $last['by'] }} — {{ round($last['seconds']) }} ثانیه</p>

  @if ($rep['rows'])
    <div class="sum">
      <span class="chip all">{{ count($rep['rows']) }} دفترچه</span>
      <span class="chip ok">{{ $rep['ok'] }} سالم</span>
      @if ($rep['bad'])<span class="chip bad">{{ $rep['bad'] }} ناهمخوان</span>@endif
      @if ($rep['skip'])<span class="chip skip">{{ $rep['skip'] }} خالی</span>@endif
    </div>

    {{-- ناهمخوان‌ها اول می‌آیند: همان‌هایی که کار لازم دارند --}}
    @php $bad = array_filter($rep['rows'], fn ($r) => $r['state'] !== 'ok'); @endphp
    @if ($bad)
      <h4 class="sec bad">نیاز به رسیدگی</h4>
      <table class="rep">
        <tr><th>سال</th><th>رشته</th><th>مشکل</th></tr>
        @foreach ($bad as $r)
          <tr class="{{ $r['state'] }}">
            <td class="num">{{ $r['year'] }}</td>
            <td>{{ $r['exam'] }}</td>
            <td class="note">{{ $r['note'] }}</td>
          </tr>
        @endforeach
      </table>
    @endif

    @php $good = array_filter($rep['rows'], fn ($r) => $r['state'] === 'ok'); @endphp
    @if ($good)
      <details class="fold">
        <summary>{{ count($good) }} دفترچه‌ی سالم</summary>
        <table class="rep">
          <tr><th>سال</th><th>رشته</th><th>نتیجه</th></tr>
          @foreach ($good as $r)
            <tr class="ok"><td class="num">{{ $r['year'] }}</td><td>{{ $r['exam'] }}</td>
                <td class="note">{{ $r['note'] }}</td></tr>
          @endforeach
        </table>
      </details>
    @endif
  @endif

  @if (trim($rep['rest']) !== '')
    <div class="rest">{{ $rep['rest'] }}</div>
  @endif

  <details class="fold">
    <summary>متن کامل گزارش</summary>
    <pre class="log" dir="auto">{{ $last['log'] }}</pre>
  </details>
</div>
@endif

<style>
  .gaps{display:flex;flex-wrap:wrap;gap:6px}
  .log{background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:12px 14px;
       font-size:12.5px;line-height:1.9;max-height:420px;overflow:auto;white-space:pre-wrap;
       font-family:Vazirmatn,ui-monospace,monospace}

  .runmeta{color:var(--ink-2,#6b7787);font-size:13px;margin:2px 0 12px}

  /* خلاصه‌ی بالای گزارش */
  .sum{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
  .chip{padding:5px 12px;border-radius:999px;font-size:13px;font-weight:600;border:1px solid transparent}
  .chip.all {background:#eef2f7;color:#41505f;border-color:#dde4ec}
  .chip.ok  {background:#eaf5ef;color:#1d6b58;border-color:#c9e4d6}
  .chip.bad {background:#fdf0e8;color:#a8461f;border-color:#f0d3c2}
  .chip.skip{background:#f5f2e8;color:#7a6a3e;border-color:#e6dfc9}

  .sec{font-size:14px;margin:16px 0 8px}
  .sec.bad{color:#a8461f}

  /* جدول گزارش — هر تکه در خانه‌ی خودش، پس جهت به هم نمی‌ریزد */
  table.rep{width:100%;border-collapse:collapse;font-size:13.5px}
  table.rep th{background:#f4f7fa;text-align:right;padding:7px 10px;font-weight:600;
       border-bottom:1px solid var(--line);white-space:nowrap}
  table.rep td{padding:7px 10px;border-bottom:1px solid var(--line);vertical-align:top}
  table.rep td.num{direction:ltr;text-align:center;font-variant-numeric:tabular-nums;
       width:62px;white-space:nowrap}
  table.rep td.note{line-height:1.9}
  table.rep tr.bad  td.num{color:#a8461f;font-weight:600}
  table.rep tr.skip td{color:#7a6a3e}
  table.rep tr.ok   td.note{color:var(--ink-2,#6b7787)}

  .fold{margin-top:12px}
  .fold>summary{cursor:pointer;font-size:13.5px;color:#41505f;padding:6px 0;user-select:none}
  .fold>summary:hover{color:#16324f}

  .rest{background:#fbfcfd;border:1px solid var(--line);border-radius:8px;padding:10px 14px;
       margin-top:14px;font-size:13px;line-height:2;white-space:pre-wrap;color:#4a5866}
</style>
@endsection
