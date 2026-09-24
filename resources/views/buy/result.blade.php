@extends('buy._layout')
@section('title', 'نتیجه‌ی پرداخت')

@php
  $exams = array_map(fn ($e) => $names[$e] ?? $e, array_filter(explode(',', $o->exams)));
@endphp

@if ($o->status === 'verifying' || $o->status === 'pending')
  @push('head')<meta http-equiv="refresh" content="8">@endpush
@endif

@section('body')
  @if ($o->status === 'paid')
    <h1>پرداخت موفق بود</h1>
    <div class="ok">دسترسی {{ implode('، ', $exams) }} فعال شد.</div>
    <p class="sub num">
      مبلغ: {{ number_format($o->payable) }} تومان
      @if ($o->rrn)<br>شماره‌ی مرجع: {{ $o->rrn }}@endif
      @if ($o->masked_pan)<br>کارت: <span dir="ltr">{{ $o->masked_pan }}</span>@endif
    </p>
    <a class="btn" href="/zaban">ورود به پلتفرم</a>

  @elseif ($o->status === 'verifying' || $o->status === 'pending')
    <h1>در حال تأیید پرداخت…</h1>
    <p class="sub">این صفحه خودش به‌روز می‌شود. اگر مبلغی از حسابتان کسر شده و تا ۲۵ دقیقه
      دسترسی فعال نشد، خودکار به حسابتان برمی‌گردد.</p>
    <a class="btn ghost" href="{{ route('buy.result', $o->id) }}">به‌روزرسانی</a>

  @else
    <h1>پرداخت انجام نشد</h1>
    <div class="err">{{ $msg ?? 'پرداخت ناموفق بود.' }}
      @if ($o->gateway_code && !$msg)<span class="num">(کد {{ $o->gateway_code }})</span>@endif</div>
    <p class="sub">اگر مبلغی از حسابتان کسر شده، حداکثر ظرف ۷۲ ساعت به حسابتان برمی‌گردد.</p>
    <a class="btn" href="{{ route('buy', ['exam' => $o->exams]) }}">تلاش دوباره</a>
  @endif

  <p class="note">شماره‌ی سفارش: <span class="num">{{ $o->id }}</span></p>
@endsection
