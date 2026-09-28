<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{{-- تم قبل از رسیدن CSS اعمال می‌شود تا صفحه یک لحظه با تم غلط چشمک نزند —
     همان کلید و همان فرمتی که app-bootstrap.js با LS.set('zban_theme', …) می‌سازد. --}}
<script>try{var t=JSON.parse(localStorage.getItem('zaban:zban_theme'));if(t&&t!=='light')document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
<title>@yield('title', 'خرید پکیج') — پلتفرم زبان کنکور ارشد</title>
<link rel="icon" href="/favicon/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
{{-- همان فایل استایل واقعی پلتفرم — یک کپی جدا نیست، پس هر تغییری در ظاهر
     سایت خودش این‌جا هم اثر می‌کند و هیچ‌وقت این صفحه از بقیه عقب نمی‌ماند. --}}
<link rel="stylesheet" href="/css/zaban.css?v={{ filemtime(public_path('css/zaban.css')) }}">
<style>
  /* فقط چیزهایی که مخصوص صفحه‌های /buy است و در zaban.css نیست. نام‌ها عمداً
     با پیشوند buyp- هستند تا با کلاس‌های خودِ پلتفرم (مثلاً .card، .note که
     آن‌جا برای چیز دیگری استفاده شده‌اند) قاطی نشوند. */
  .buyp-card{background:var(--paper);border:1px solid var(--line);border-radius:16px;
       padding:28px 26px;max-width:480px;margin:24px auto}
  .buyp-card h1{font-size:20px;font-weight:700;margin-bottom:4px}
  .sub{color:var(--ink-2);font-size:13.5px;margin-bottom:20px}
  .err{background:#fbeee8;color:#b5451d;border-radius:10px;padding:10px 14px;font-size:14px;margin-bottom:16px}
  .ok{background:#e6f2ee;color:#1d6b58;border-radius:10px;padding:10px 14px;font-size:14px;margin-bottom:16px}
  .btn{display:block;width:100%;text-align:center;background:var(--ink);color:#fff;border:0;border-radius:10px;
       padding:12px 16px;font:inherit;font-weight:500;font-size:15px;cursor:pointer;text-decoration:none}
  .btn:hover{background:#000}
  .btn[disabled]{background:#b9bec4;cursor:not-allowed}
  .btn.ghost{background:transparent;color:var(--ink);border:1px solid var(--line)}
  .btn:focus-visible{outline:2px solid var(--gold);outline-offset:3px}
  .buyp-note{color:var(--ink-3);font-size:12.5px;margin-top:12px;text-align:center}
  .dev{background:#fff4d6;border:1px dashed var(--gold-line);border-radius:10px;padding:8px 12px;font-size:12.5px;
       color:#7d6320;margin-bottom:16px}
  .num{font-variant-numeric:tabular-nums}
  /* دکمه‌های بالا این‌جا لینک‌اند نه button؛ .iconbtn/.dashbtn در zaban.css
     برای button نوشته شده‌اند و روی <a> زیرخط و رنگ لینک می‌گیرند. */
  .topacts a.iconbtn,.topacts a.dashbtn{text-decoration:none}
</style>
@stack('head')
</head>
<body>

@php
  /* نوار بالای این صفحه عمداً همان مارک‌آپ و همان کلاس‌های /zaban است (و همان
     zaban.css را می‌خواند)، چون /buy یک صفحه‌ی مستقل است و اسکریپت SPA را ندارد
     — دلیلش بالای ZabanPurchaseController توضیح داده شده.

     چیزهایی که این‌جا داده‌ی زنده لازم دارند، به‌جای حذف شدن، به شکل لینک
     ساخته شده‌اند تا هم دیده شوند هم کار کنند: زنگ اعلان و گزارش‌ها بدون عدد
     (شمارنده‌شان از /api/me می‌آید که این‌جا صدا زده نمی‌شود) و «مرور امروز»
     به /zaban/review می‌رود که خود پلتفرم جلسه‌ی مرور را باز می‌کند. */
  $ent     = app(\App\Services\Entitlements::class);
  $uid     = auth()->id();
  $owned   = $uid ? $ent->for($uid) : [];
  $isStaff = $uid && in_array(optional(auth()->user())->type, ['admin', 'manager', 'editor'], true);
  $remaining = max(0, count(\App\Services\Pricing::NAMES) - count($owned));

  /* سوییچر پلتفرم‌ها — دقیقاً همان منبعی که ZabanController به رابط می‌دهد */
  $pfCurrent = config('platforms.current');
  $pfList = array_map(function ($p) {
      $u = trim((string) ($p['url'] ?? ''), " \t\n\r\0\x0B\"'");
      if ($u !== '' && !preg_match('~^https?://~i', $u)) $u = 'https://' . ltrim($u, '/');
      $p['url'] = $u ?: null;
      return $p;
  }, config('platforms.list', []));

  $navGroups = [
    ['name' => 'مطالعه', 'tabs' => [['t' => 'words', 'n' => 'کلمات'], ['t' => 'tests', 'n' => 'تست‌های زبان'], ['t' => 'read', 'n' => 'مطالعه‌ی ترتیبی']]],
    ['name' => 'آزمون', 'tabs' => [['t' => 'exam', 'n' => 'آزمون آزمایشی'], ['t' => 'charts', 'n' => 'تحلیل آزمون‌ها'], ['t' => 'predict', 'n' => 'پیش‌بینی کنکور']]],
    ['name' => 'مرور من', 'tabs' => [['t' => 'deck', 'n' => 'دک من'], ['t' => 'star', 'n' => 'منتخب من']]],
    ['name' => 'آمار', 'tabs' => [['t' => 'fb', 'n' => 'فیدبک و تسلط'], ['t' => 'crowd', 'n' => 'آمار جمعی'], ['t' => 'balance', 'n' => 'تعادل و پوشش']]],
  ];
@endphp

<div class="wrap">
  <div class="topbar">
    <div class="brandrow">
      <div>
        <h1 class="brand">پلتفرم زبان کنکور ارشد</h1>
        <div class="majors">
          @foreach (\App\Services\Pricing::NAMES as $code => $name)
            @php $isOwned = in_array($code, $owned, true); @endphp
            @if ($isOwned)
              <span class="mjw"><span class="mj">{{ $name }}</span><span class="mjs own">✓</span></span>
            @else
              <a class="mjw" href="{{ route('buy', ['exam' => $code]) }}" title="خرید {{ $name }}"><span class="mj">{{ $name }}</span><span class="mjs buy">خرید</span></a>
            @endif
            @if (!$loop->last)<b>·</b>@endif
          @endforeach
          <span class="mjy">۲۵ سال کنکور</span>
        </div>
      </div>
    </div>

    {{-- ترتیب دقیقاً همان ترتیب zaban.blade.php است تا این صفحه با بقیه یکی دیده شود --}}
    <div class="topacts">
      @if ($uid)
        @if ($pfList)
          <div class="psw">
            <button class="b" id="buypPswBtn" type="button" aria-expanded="false" aria-haspopup="true">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true">
                <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/>
                <rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
              </svg><span>پلتفرم‌ها</span>
            </button>
            <div class="pop" id="buypPswPop" role="menu" hidden>
              @php $pfCur = collect($pfList)->firstWhere('key', $pfCurrent); @endphp
              @if ($pfCur)
                <div class="h">هم‌اکنون در این پلتفرم هستید</div>
                <a class="cur" role="menuitem" aria-current="page">
                  <span class="pi"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
                  <span><span class="pn">{{ $pfCur['n'] ?? '' }} <span class="tg here">اینجا</span></span><span class="pd">{{ $pfCur['d'] ?? '' }}</span></span>
                </a>
                <div class="sep"></div>
              @endif
              <div class="h">رفتن به پلتفرم دیگر</div>
              @foreach ($pfList as $p)
                @continue(($p['key'] ?? null) === $pfCurrent)
                @php $ok = $p['url'] && preg_match('~^https?://[^\s]+$~i', $p['url']); @endphp
                @if ($ok)
                  <a role="menuitem" href="{{ $p['url'] }}">
                    <span class="pi"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
                    <span><span class="pn">{{ $p['n'] ?? '' }}</span><span class="pd">{{ $p['d'] ?? '' }}</span></span>
                  </a>
                @else
                  <span class="na" role="menuitem" aria-disabled="true">
                    <span class="pi"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
                    <span><span class="pn">{{ $p['n'] ?? '' }}</span><span class="pd">{{ $p['d'] ?? '' }}</span></span>
                  </span>
                @endif
              @endforeach
            </div>
          </div>
        @endif

        @if ($isStaff)
          <a class="dashbtn" style="background:#1b2430;color:#fff;border-color:#1b2430" href="{{ route('zadmin.dashboard') }}">⚙ مدیریت</a>
        @endif

        <a class="dashbtn" href="{{ route('zaban') }}"><span class="gi">◆</span>داشبورد</a>

        <span class="themebtn">
          <button class="iconbtn" id="buypThemeBtn" data-tip="تغییر ظاهر (تم)" type="button">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
              stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="4.2"/>
              <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
          </button>
        </span>

        <a class="iconbtn" href="{{ route('zaban', ['path' => 'announce']) }}" data-tip="اطلاعیه">🔔</a>
        <a class="iconbtn" href="{{ route('zaban', ['path' => 'reports']) }}" data-tip="گزارش‌های من">📋</a>
        <a class="iconbtn" href="{{ route('zaban', ['path' => 'profile']) }}" data-tip="پروفایل">👤</a>
        <a class="iconbtn" href="{{ route('logout') }}" data-tip="خروج از حساب" aria-label="خروج از حساب"
           onclick="return confirm('از حساب کاربری خارج می‌شوید؟')">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7"
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 16l-4-4 4-4M6 12h10"/></svg>
        </a>
        <a class="deckbtn" href="{{ route('zaban', ['path' => 'review']) }}" style="text-decoration:none">مرور امروز</a>
      @endif
    </div>
  </div>

  <div class="stickytop"><div class="nav1">
    @foreach ($navGroups as $g)
      <span class="ngrp">
        <i class="gl">{{ $g['name'] }}</i>
        @foreach ($g['tabs'] as $t)
          <a href="{{ route('zaban', ['path' => $t['t']]) }}">{{ $t['n'] }}</a>
        @endforeach
      </span>
      <i class="ndiv"></i>
    @endforeach
    <span class="ngrp buygrp on">
      <a href="{{ route('buy') }}" class="on">خرید و پرداخت‌ها@if ($remaining)<span class="nb">{{ \App\Support\FaNum::format($remaining) }}</span>@endif</a>
    </span>
  </div></div>

  @hasSection('bare')
    @yield('body')
  @else
    <div class="buyp-card">@yield('body')</div>
  @endif
</div>

<script>
/* چرخه‌ی ساده‌ی تم — همان چهار حالت پلتفرم، همان کلید localStorage، بدون
   منوی پیش‌نمایش (آن بخش نیاز به اسکریپت کامل SPA دارد که این‌جا نیست). */
(function(){
  var b=document.getElementById('buypThemeBtn');
  if(b){
    var ORDER=['light','sepia','dim','dark'], KEY='zaban:zban_theme';
    var cur=function(){ try{ return JSON.parse(localStorage.getItem(KEY))||'light'; }catch(e){ return 'light'; } };
    b.addEventListener('click', function(){
      var id=ORDER[(ORDER.indexOf(cur())+1)%ORDER.length];
      if(id==='light') document.documentElement.removeAttribute('data-theme');
      else document.documentElement.setAttribute('data-theme', id);
      try{ localStorage.setItem(KEY, JSON.stringify(id)); }catch(e){}
    });
  }
  var pb=document.getElementById('buypPswBtn'), pp=document.getElementById('buypPswPop');
  if(pb&&pp){
    pb.addEventListener('click', function(e){
      e.stopPropagation();
      var open=pp.hidden; pp.hidden=!open; pb.setAttribute('aria-expanded', String(open));
    });
    pp.addEventListener('click', function(e){ e.stopPropagation(); });
    document.addEventListener('click', function(){ pp.hidden=true; pb.setAttribute('aria-expanded','false'); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ pp.hidden=true; pb.setAttribute('aria-expanded','false'); } });
  }
})();
</script>
</body>
</html>
