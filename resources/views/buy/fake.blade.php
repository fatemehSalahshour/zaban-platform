@extends('buy._layout')
@section('title', 'درگاه آزمایشی')

@section('body')
  {{-- فقط local + IRANKISH_FAKE. همان فیلدهایی را که ایران کیش به revertUri می‌فرستد، شبیه‌سازی می‌کند. --}}
  <div class="dev">درگاه آزمایشی — پولی جابه‌جا نمی‌شود</div>
  <h1>پرداخت {{ number_format($o->amount_rial) }} ریال</h1>
  <p class="sub">سفارش {{ $o->id }} — {{ implode('، ', array_map(fn ($e) => $names[$e] ?? $e, explode(',', $o->exams))) }}</p>

  @foreach ([['00', 'پرداخت موفق', ''], ['55', 'رمز اشتباه', 'ghost'], ['17', 'انصراف', 'ghost']] as [$code, $label, $cls])
    <form method="post" action="{{ route('buy.return') }}" style="margin-bottom:8px">
      <input type="hidden" name="token" value="{{ $o->token }}">
      <input type="hidden" name="acceptorId" value="FAKE">
      <input type="hidden" name="responseCode" value="{{ $code }}">
      <input type="hidden" name="RequestId" value="{{ $o->request_id }}">
      <input type="hidden" name="amount" value="{{ $o->amount_rial }}">
      @if ($code === '00')
        <input type="hidden" name="retrievalReferenceNumber" value="{{ str_pad((string) random_int(1, 999999999999), 12, '0', STR_PAD_LEFT) }}">
        <input type="hidden" name="systemTraceAuditNumber" value="{{ str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT) }}">
        <input type="hidden" name="maskedPan" value="603799******1234">
      @endif
      <button class="btn {{ $cls }}" type="submit">{{ $label }}</button>
    </form>
  @endforeach
@endsection
