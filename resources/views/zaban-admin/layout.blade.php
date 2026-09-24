<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title', 'مدیریت') — پلتفرم زبان</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
<style>
/* ─────────────────────────────────────────────────────────────
   پنل مدیریت.

   رنگ عمداً سرد است، برخلاف رابط دانشجو که گرم و کاغذی است. مدیر
   نباید یک لحظه شک کند کجاست. طلایی برند فقط روی دکمه‌ی ثبت
   می‌نشیند — تنها جایی که در این صفحه جسارت خرج می‌شود.
   ───────────────────────────────────────────────────────────── */
:root{
  --ink:#1b2430;          /* نوار کناری */
  --ink-2:#41505f;        /* متن اصلی */
  --ink-3:#7b8794;        /* متن کم‌اهمیت */
  --surface:#f4f6f8;      /* زمینه‌ی کار */
  --panel:#fff;
  --line:#e2e6eb;
  --line-2:#cfd6dd;
  --gold:#a8842c;         /* فقط ثبت */
  --green:#1c6b46;
  --danger:#a8461f;
  --r:10px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Vazirmatn,system-ui,sans-serif;background:var(--surface);
  color:var(--ink-2);font-size:14px;line-height:1.75;display:flex;min-height:100vh}
a{color:inherit;text-decoration:none}

/* ── نوار کناری ── */
.side{width:224px;flex-shrink:0;background:var(--ink);color:#c7d0d9;
  padding:22px 0;display:flex;flex-direction:column;position:sticky;top:0;
  min-height:100vh;align-self:stretch}
.side h1{font-size:15px;font-weight:700;color:#fff;padding:0 20px 18px;
  border-bottom:1px solid #2b3746;margin-bottom:14px;letter-spacing:-.01em}
.side h1 small{display:block;font-size:11px;font-weight:400;color:#7b8794;margin-top:3px}
.side nav a{display:block;padding:9px 20px;font-size:13.5px;
  border-right:2.5px solid transparent;transition:.12s}
.side nav a:hover{background:#222d3b;color:#fff}
.side nav a.on{background:#222d3b;color:#fff;border-right-color:var(--gold)}
.side nav .nb{float:left;min-width:20px;padding:0 6px;border-radius:20px;background:var(--danger);
  color:#fff;font-size:11px;line-height:19px;text-align:center;font-variant-numeric:tabular-nums}
.side .out{margin-top:auto;padding:14px 20px 0;border-top:1px solid #2b3746;
  font-size:12.5px;color:#7b8794}

/* ── ستون کار ── */
main{flex:1;padding:34px 40px 60px;max-width:940px}
.head{margin-bottom:26px}
.head h2{font-size:21px;font-weight:700;color:var(--ink);letter-spacing:-.02em}
.head p{font-size:13px;color:var(--ink-3);margin-top:5px;max-width:62ch}

.panel{background:var(--panel);border:1px solid var(--line);
  border-radius:var(--r);padding:22px 24px;margin-bottom:18px}
.panel > h3{font-size:15px;font-weight:700;color:var(--ink);margin-bottom:4px}
.panel > h3 + p{font-size:12.5px;color:var(--ink-3);margin-bottom:18px;max-width:60ch}

/* ── فرم ── */
.field{display:grid;grid-template-columns:180px 1fr;gap:14px;
  align-items:center;padding:11px 0;border-top:1px solid var(--line)}
.field:first-of-type{border-top:0}
.field label{font-size:13.5px;color:var(--ink-2)}
.field label i{display:block;font-style:normal;font-size:11.5px;color:var(--ink-3)}
input[type=text],input[type=number],input[type=search]{
  font-family:inherit;font-size:14px;padding:8px 11px;width:100%;max-width:280px;
  border:1px solid var(--line-2);border-radius:8px;background:#fff;color:var(--ink);
  font-variant-numeric:tabular-nums}
input:focus{outline:2px solid var(--gold);outline-offset:1px;border-color:var(--gold)}

.btn{font-family:inherit;font-size:14px;font-weight:600;padding:9px 20px;
  border:0;border-radius:9px;cursor:pointer;background:var(--gold);color:#fff;
  transition:.12s}
.btn:hover{background:#8f6d1f}
.btn:focus-visible{outline:2px solid var(--ink);outline-offset:2px}
.btn.ghost{background:#fff;color:var(--ink-2);border:1px solid var(--line-2)}
.btn.ghost:hover{background:var(--surface)}
.actions{margin-top:18px;padding-top:16px;border-top:1px solid var(--line);
  display:flex;gap:10px;align-items:center}

/* ── پیام ── */
.flash{background:#eaf5ef;border:1px solid #bfe0cd;color:var(--green);
  padding:11px 16px;border-radius:9px;margin-bottom:18px;font-size:13.5px}
.err{background:#fdf0eb;border:1px solid #f0cdbe;color:var(--danger);
  padding:11px 16px;border-radius:9px;margin-bottom:18px;font-size:13.5px}
.err ul{margin:4px 18px 0}

/* ── جدول ── */
table{width:100%;border-collapse:collapse;font-size:13.5px}
th{text-align:right;font-weight:600;color:var(--ink-3);font-size:12px;
  padding:0 10px 9px;border-bottom:1px solid var(--line)}
td{padding:10px;border-bottom:1px solid var(--line)}
tr:last-child td{border-bottom:0}
td.num{font-variant-numeric:tabular-nums;text-align:left;direction:ltr}
.tag{display:inline-block;font-size:11.5px;padding:1px 7px;border-radius:20px;
  background:var(--surface);border:1px solid var(--line-2);color:var(--ink-3)}
.tag.gold{background:#fdf6e4;border-color:#e6d3a0;color:#8f6d1f}
.empty{padding:26px;text-align:center;color:var(--ink-3);font-size:13.5px}

@media(max-width:820px){
  body{flex-direction:column}
  .side{width:100%;height:auto;position:static;padding:16px 0}
  .side nav{display:flex;flex-wrap:wrap;gap:2px;padding:0 12px}
  .side nav a{border-right:0;border-bottom:2.5px solid transparent;border-radius:6px}
  .side nav a.on{border-right:0;border-bottom-color:var(--gold)}
  .side .out{margin-top:12px}
  main{padding:22px 16px 40px}
  .field{grid-template-columns:1fr;gap:6px}
}
@media(prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body>

<aside class="side">
  <h1>پلتفرم زبان<small>بخش مدیریت</small></h1>
  <nav>
    <a href="{{ route('zadmin.dashboard') }}" class="{{ request()->routeIs('zadmin.dashboard') ? 'on' : '' }}">خلاصه‌ی وضعیت</a>
    <a href="{{ route('zadmin.settings') }}"  class="{{ request()->routeIs('zadmin.settings')  ? 'on' : '' }}">قیمت و تاریخ کنکور</a>
    <a href="{{ route('zadmin.users') }}"     class="{{ request()->routeIs('zadmin.users')     ? 'on' : '' }}">کاربران و دسترسی‌ها</a>
    <a href="{{ route('zadmin.content') }}"   class="{{ request()->routeIs('zadmin.content')   ? 'on' : '' }}">محتوای بانک</a>
    @php
      /* تنها عددی که در نوار کناری می‌آید: کاری که منتظر مدیر است. */
      $openReports = \Illuminate\Support\Facades\DB::table('reports')->where('state', 'open')->count();
    @endphp
    <a href="{{ route('zadmin.reports') }}" class="{{ request()->routeIs('zadmin.reports*') ? 'on' : '' }}">گزارش‌های کاربران
      @if ($openReports)<span class="nb">{{ $openReports }}</span>@endif</a>
    <a href="{{ route('zadmin.announcements') }}" class="{{ request()->routeIs('zadmin.announcements*') ? 'on' : '' }}">اطلاعیه‌ها</a>
    <a href="{{ route('zadmin.sync') }}" class="{{ request()->routeIs('zadmin.sync*') ? 'on' : '' }}">همگام‌سازی سؤال‌ها</a>
    @php $newAlerts = \Illuminate\Support\Facades\DB::table('security_alerts')->whereNull('seen_at')->count(); @endphp
    <a href="{{ route('zadmin.alerts') }}" class="{{ request()->routeIs('zadmin.alerts') ? 'on' : '' }}">هشدارهای امنیتی
      @if ($newAlerts)<span class="nb">{{ $newAlerts }}</span>@endif</a>
    <a href="{{ route('zadmin.leak') }}" class="{{ request()->routeIs('zadmin.leak') ? 'on' : '' }}">ردیابی متن منتشرشده</a>
  </nav>
  <div class="out">
    {{ auth()->user()->name }}
    <div><a href="/zaban" style="color:#a8b4c0">بازگشت به پلتفرم ←</a></div>
    <div style="margin-top:6px"><a href="{{ route('logout') }}" style="color:#a8b4c0">خروج از حساب</a></div>
  </div>
</aside>

<main>
  @if (session('ok'))
    <div class="flash">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="err">ذخیره نشد:
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  @yield('body')
</main>

</body>
</html>
