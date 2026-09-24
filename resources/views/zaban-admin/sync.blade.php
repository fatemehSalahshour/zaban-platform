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
<div class="panel">
  <h3>گزارش آخرین اجرا</h3>
  <p>{{ ['check' => 'بررسی', 'done' => 'همگام‌سازی موفق', 'failed' => 'ناموفق'][$last['status']] ?? $last['status'] }}
     — {{ $last['at'] }} — {{ $last['by'] }} — {{ $last['seconds'] }} ثانیه</p>
  <pre class="log" dir="auto">{{ $last['log'] }}</pre>
</div>
@endif

<style>
  .gaps{display:flex;flex-wrap:wrap;gap:6px}
  .log{background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:12px 14px;
       font-size:12.5px;line-height:1.9;max-height:420px;overflow:auto;white-space:pre-wrap;
       font-family:Vazirmatn,ui-monospace,monospace}
</style>
@endsection
