@extends('buy._layout')
@section('title', 'نتیجه‌ی پرداخت')

@php
  $exams = array_map(fn ($e) => $names[$e] ?? $e, array_filter(explode(',', $o->exams)));
  /* کد پیگیری: زرین‌پال ref_id، ایران کیش شماره‌ی مرجع (rrn) */
  $ref = $o->ref_id ?: $o->rrn;
  $guest = $guest ?? false;
@endphp

@if ($o->status === 'verifying' || $o->status === 'pending')
  {{-- بدون آدرس: همین صفحه دوباره باز می‌شود (برای برگشت زرین‌پال یعنی verify دوباره) --}}
  @push('head')<meta http-equiv="refresh" content="8">@endpush
@endif

@section('body')
  @if ($o->status === 'paid')
    <h1>پرداخت موفق بود</h1>
    <div class="ok">دسترسی {{ implode('، ', $exams) }} فعال شد.</div>
    <p class="sub num">
      مبلغ: {{ number_format($o->payable) }} تومان
      <br>درگاه: {{ $gwLabel ?? '' }}
      @if ($ref)<br>کد پیگیری: <span dir="ltr">{{ $ref }}</span>@endif
      @if ($o->masked_pan)<br>کارت: <span dir="ltr">{{ $o->masked_pan }}</span>@endif
    </p>
    <a class="btn" href="/zaban">ورود به پلتفرم</a>

  @elseif ($o->status === 'verifying' || $o->status === 'pending')
    <h1>در حال تأیید پرداخت…</h1>
    <p class="sub">این صفحه خودش به‌روز می‌شود. اگر مبلغی از حسابتان کسر شده و تا ۲۵ دقیقه
      دسترسی فعال نشد، خودکار به حسابتان برمی‌گردد.</p>
    <a class="btn ghost" href="{{ $guest ? request()->fullUrl() : route('buy.result', $o->id) }}">به‌روزرسانی</a>

  @else
    <h1>پرداخت انجام نشد</h1>
    <div class="err">{{ $msg ?? 'پرداخت ناموفق بود.' }}
      @if ($o->gateway_code && !$msg)<span class="num">(کد {{ $o->gateway_code }})</span>@endif</div>
    <p class="sub">اگر مبلغی از حسابتان کسر شده، حداکثر ظرف ۷۲ ساعت به حسابتان برمی‌گردد.
      @if (!empty($other))<br>اگر پرداخت با یک درگاه انجام نشد، درگاه دیگر را امتحان کنید.@endif</p>
    @if (!empty($other))
      <a class="btn" href="{{ route('buy', ['exam' => $o->exams, 'gw' => $other]) }}">پرداخت با درگاه {{ $otherLabel }}</a>
      <a class="btn ghost" style="margin-top:8px" href="{{ route('buy', ['exam' => $o->exams, 'gw' => $o->gateway]) }}">تلاش دوباره با {{ $gwLabel }}</a>
    @else
      <a class="btn" href="{{ route('buy', ['exam' => $o->exams]) }}">تلاش دوباره</a>
    @endif
  @endif

  <p class="buyp-note">شماره‌ی سفارش: <span class="num">{{ $o->id }}</span></p>
@endsection
