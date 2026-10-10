@extends('zaban-admin.layout')
@section('title', 'گزارش امنیتی شبانه')

@php
  $fa = fn ($n) => \App\Support\FaNum::format((int) $n);
  $kinds = \App\Http\Controllers\ZabanAdminAlertController::KINDS + ['trial_cap' => 'به سقف نسخه‌ی آزمایشی رسید'];
@endphp

@section('body')
<div class="head">
  <h2>گزارش امنیتی شبانه</h2>
  <p>هر روز ساعت ۵ صبح خودکار ساخته می‌شود و ۲۴ ساعت گذشته را نشان می‌دهد. فقط گزارش است؛
     کسی را قفل نمی‌کند. قفل خودکار و باز کردن قفل در «هشدارهای امنیتی» است.</p>
</div>

<div class="panel">
  <form method="post" action="{{ route('zadmin.secreport.run') }}" class="rerun">
    @csrf
    <span>
      @if ($r)
        آخرین گزارش: <b class="num" dir="ltr">{{ $r['built_at'] }}</b>
      @else
        هنوز گزارشی ساخته نشده است.
      @endif
    </span>
    <button class="btn ghost" type="submit">ساختن گزارش همین حالا</button>
  </form>
</div>

@if ($r)
  <div class="panel">
    <h3>پرداخت‌ها در ۲۴ ساعت گذشته</h3>
    <div class="kpis">
      <div><b>{{ $fa($r['pay']['paid']) }}</b><span>پرداخت موفق</span></div>
      <div><b>{{ $fa($r['pay']['paid_sum']) }}</b><span>مجموع (تومان)</span></div>
      <div><b>{{ $fa($r['pay']['failed']) }}</b><span>ناموفق یا منقضی</span></div>
      <div class="{{ $r['pay']['stuck'] ? 'warnk' : '' }}"><b>{{ $fa($r['pay']['stuck']) }}</b><span>گیرکرده (بیش از ۳۰ دقیقه)</span></div>
    </div>
  </div>

  <div class="panel">
    <h3>پرمصرف‌ترین دانشجوها</h3>
    <p>محتوای تازه‌ای که هر کاربر در ۲۴ ساعت باز کرده. دانشجوی پرکار واقعی هم ممکن است این‌جا باشد؛
       نکته‌ی مشکوک، عدد خیلی بزرگ در زمان کوتاه یا همراهی با رویدادهای محافظ است.</p>
    @if (count($r['heavy']))
      <table class="tbl">
        <thead><tr><th>کاربر</th><th>کلمه</th><th>معنی</th><th>پاسخ</th><th>مجموع</th></tr></thead>
        <tbody>
          @foreach ($r['heavy'] as $x)
            <tr>
              <td><a href="{{ route('zadmin.user', $x['user']) }}">{{ $x['name'] ?: $x['mobile'] }}</a>
                  <small class="num">{{ $x['mobile'] }} · شماره‌ی {{ $x['user'] }}</small></td>
              <td class="num">{{ $fa($x['words']) }}</td>
              <td class="num">{{ $fa($x['meanings']) }}</td>
              <td class="num">{{ $fa($x['answers']) }}</td>
              <td class="num"><b>{{ $fa($x['total']) }}</b></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="empty">موردی نیست.</p>
    @endif
  </div>

  <div class="panel">
    <h3>رویدادهای محافظ مرورگر</h3>
    <p>«پنهان شدن» معمولاً یعنی رفتن به برنامه‌ی دیگر و عادی است. ابزار توسعه (F12)، PrintScreen و تلاش‌های
       مکرر کپی جدی‌ترند و بالاتر نشان داده می‌شوند.</p>
    @if (count($r['events']))
      <table class="tbl">
        <thead><tr><th>کاربر</th><th>ابزار توسعه</th><th>اسکرین‌شات / چاپ</th><th>کپی</th><th>کلیک راست</th><th>میان‌بر</th><th>پنهان شدن</th></tr></thead>
        <tbody>
          @foreach ($r['events'] as $x)
            <tr>
              <td><a href="{{ route('zadmin.user', $x['user']) }}" class="num">{{ $x['mobile'] ?: $x['user'] }}</a></td>
              <td class="num {{ $x['dev'] ? 'hot' : '' }}">{{ $fa($x['dev']) }}</td>
              <td class="num {{ $x['shot'] ? 'hot' : '' }}">{{ $fa($x['shot']) }}</td>
              <td class="num">{{ $fa($x['copy']) }}</td>
              <td class="num">{{ $fa($x['ctx']) }}</td>
              <td class="num">{{ $fa($x['key']) }}</td>
              <td class="num">{{ $fa($x['hide']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="empty">موردی نیست.</p>
    @endif
  </div>

  <div class="panel">
    <h3>هشدارهای امنیتی ۲۴ ساعت گذشته</h3>
    @if (count($r['alerts']))
      <table class="tbl">
        <thead><tr><th>نوع</th><th>تعداد</th><th>کاربر</th></tr></thead>
        <tbody>
          @foreach ($r['alerts'] as $x)
            <tr><td>{{ $kinds[$x['kind']] ?? $x['kind'] }}</td><td class="num">{{ $fa($x['n']) }}</td><td class="num">{{ $fa($x['users']) }}</td></tr>
          @endforeach
        </tbody>
      </table>
      <p style="margin-top:10px"><a href="{{ route('zadmin.alerts') }}">جزئیات در «هشدارهای امنیتی» ←</a></p>
    @else
      <p class="empty">موردی نیست.</p>
    @endif
  </div>

  <div class="panel">
    <h3>حساب‌های قفل‌شده</h3>
    @if (count($r['locked']))
      <table class="tbl">
        <thead><tr><th>کاربر</th><th>علت</th><th>قفل تا</th></tr></thead>
        <tbody>
          @foreach ($r['locked'] as $x)
            <tr><td><a href="{{ route('zadmin.user', $x['user']) }}" class="num">{{ $x['mobile'] ?: $x['user'] }}</a></td>
                <td>{{ $x['reason'] }}</td><td class="num" dir="ltr">{{ $x['until'] }}</td></tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="empty">هیچ حسابی قفل نیست.</p>
    @endif
  </div>
@endif

<style>
  .rerun{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:13.5px;color:var(--ink-2)}
  .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}
  .kpis div{border:1px solid var(--line);border-radius:var(--r);padding:12px 14px}
  .kpis b{display:block;font-size:20px;color:var(--ink);font-variant-numeric:tabular-nums}
  .kpis span{font-size:12px;color:var(--ink-3)}
  .kpis .warnk{border-color:#f0cdbe;background:#fdf0eb}
  .tbl{width:100%;border-collapse:collapse;font-size:13.5px}
  .tbl th{font-weight:500;color:var(--ink-3);font-size:12.5px;text-align:right;padding:6px 8px;border-bottom:1px solid var(--line)}
  .tbl td{padding:8px;border-bottom:1px solid var(--line);vertical-align:top}
  .tbl small{display:block;font-size:11.5px;color:var(--ink-3)}
  .tbl td.hot{color:var(--danger);font-weight:700}
  .empty{font-size:13px;color:var(--ink-3)}
</style>
@endsection
