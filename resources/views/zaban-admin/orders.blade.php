@extends('zaban-admin.layout')
@section('title', 'سفارش‌ها')

@section('body')
<div class="head">
  <h2>سفارش‌ها</h2>
  <p>
    سفارش گیرکرده یعنی کاربر به درگاه رفته و نتیجه برنگشته. «بررسی دوباره» از همان درگاهی
    که کاربر با آن پرداخت کرده (ایران کیش یا زرین‌پال) می‌پرسد و اگر پرداخت واقعاً انجام شده
    باشد، خودش دسترسی را باز می‌کند. سفارش تازه (کمتر از ۲۵ دقیقه) را دست نمی‌زند، چون ممکن
    است کاربر هنوز در صفحه‌ی درگاه باشد. مهلت استعلام ایران کیش هفت روز است و زرین‌پال
    تراکنش تأییدنشده را خودش برمی‌گرداند؛ موارد قدیمی‌تر فقط با رسید و فعال‌سازی دستی حل می‌شود.
  </p>
</div>

@if (session('ok'))    <div class="flash">{{ session('ok') }}</div> @endif
@if (session('error')) <div class="err">{{ session('error') }}</div> @endif

<div class="panel">
  <div class="tabs">
    @foreach ([
      'stuck'  => ['گیرکرده', $counts->stuck],
      'paid'   => ['پرداخت‌شده', $counts->paid],
      'failed' => ['ناموفق', $counts->failed],
      'all'    => ['همه', null],
    ] as $key => [$label, $n])
      <a href="{{ route('zadmin.orders', ['state' => $key, 'q' => $q]) }}"
         class="tab {{ $state === $key ? 'on' : '' }}">
        {{ $label }}@if ($n !== null) <b>{{ \App\Support\FaNum::format($n) }}</b>@endif
      </a>
    @endforeach
  </div>

  <form method="get" class="search">
    <input type="hidden" name="state" value="{{ $state }}">
    <input type="search" name="q" value="{{ $q }}" placeholder="موبایل، نام، شماره‌ی سفارش یا کد پیگیری">
    <button class="btn ghost" type="submit">جست‌وجو</button>
  </form>

  @if (!count($orders))
    <p class="empty">سفارشی با این فیلتر نیست.</p>
  @else
    <table>
      <thead>
        <tr><th>#</th><th>کاربر</th><th>رشته‌ها</th><th>مبلغ</th>
            <th>درگاه / پیگیری</th><th>وضعیت</th><th>تاریخ</th><th></th></tr>
      </thead>
      <tbody>
        @foreach ($orders as $o)
          <tr>
            <td class="num">{{ $o->id }}</td>
            <td>
              <a href="{{ route('zadmin.user', $o->user_id) }}">{{ $o->name }}</a>
              <span class="sub">{{ $o->mobile }}</span>
            </td>
            <td>{{ $o->exams }}</td>
            <td class="num">
              {{ \App\Support\FaNum::format($o->payable) }}
              @if ($o->amount_rial) <span class="sub">{{ \App\Support\FaNum::format($o->amount_rial) }} ریال</span> @endif
            </td>
            <td>
              {{ \App\Services\Payment\Gateways::label($o->gateway) }}
              @if ($o->ref_id ?: $o->rrn)
                <span class="sub num" dir="ltr" style="text-align:end">{{ $o->ref_id ?: $o->rrn }}</span>
              @elseif ($o->token && $o->status !== 'paid')
                <span class="sub num" dir="ltr" style="text-align:end" title="شناسه‌ی تراکنش">{{ \Illuminate\Support\Str::limit($o->token, 18, '…') }}</span>
              @endif
            </td>
            <td>
              <span class="tag {{ $o->status === 'paid' ? 'gold' : '' }}">{{ \App\Services\Payment\Gateways::status($o->status) }}</span>
              @if ($o->gateway_code && $o->gateway_code !== '00') <span class="sub">کد {{ $o->gateway_code }}</span> @endif
              @if (!$o->token && $o->status !== 'paid') <span class="sub">به درگاه نرسیده</span> @endif
            </td>
            <td class="num">
              {{ \App\Support\Jalali::formatFromGregorian($o->paid_at ?: $o->created_at) }}
            </td>
            <td class="acts">
              @if ($o->status !== 'paid')
                <form method="post" action="{{ route('zadmin.order.recheck', $o->id) }}" class="inline">
                  @csrf
                  <button class="btn ghost sm" type="submit">بررسی دوباره</button>
                </form>
                <button class="btn ghost sm" type="button" data-open="man{{ $o->id }}">فعال‌سازی دستی</button>
              @endif
            </td>
          </tr>

          @if ($o->status !== 'paid')
            <tr class="manrow" id="man{{ $o->id }}" hidden>
              <td colspan="8">
                <form method="post" action="{{ route('zadmin.order.activate', $o->id) }}" class="manform">
                  @csrf
                  <label for="note{{ $o->id }}">
                    توضیح پرداخت — مبلغ، تاریخ و شماره‌ی پیگیری کارت به کارت یا رسید درگاه
                  </label>
                  <div class="row">
                    <input type="text" id="note{{ $o->id }}" name="note" maxlength="200" required
                           placeholder="مثلاً: کارت به کارت ۹۶۰٬۰۰۰ در ۱۴۰۵/۰۷/۱۱، پیگیری ۱۲۳۴۵۶">
                    <button class="btn" type="submit">فعال کن</button>
                  </div>
                  <p class="warn">
                    دسترسی فوراً باز می‌شود و سفارش «پرداخت‌شده» ثبت می‌شود. قبل از زدن، از
                    رسید مطمئن شوید — این کار از پنل برگشت‌پذیر نیست.
                  </p>
                </form>
              </td>
            </tr>
          @endif
        @endforeach
      </tbody>
    </table>
  @endif
</div>

<script>
  /* ردیف فعال‌سازی دستی بسته می‌ماند تا کسی اشتباهی رویش نزند */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-open]');
    if (!b) return;
    var row = document.getElementById(b.dataset.open);
    if (!row) return;
    row.hidden = !row.hidden;
    if (!row.hidden) row.querySelector('input[name=note]').focus();
  });
</script>

<style>
  .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
  .tab{font-size:13px;padding:7px 13px;border:1px solid var(--line-2);border-radius:20px;
    color:var(--ink-2);text-decoration:none}
  .tab.on{background:var(--ink);border-color:var(--ink);color:#fff}
  .tab b{font-weight:600;opacity:.75;margin-inline-start:4px}
  .search{display:flex;gap:8px;margin-bottom:16px}
  .search input{max-width:320px}
  .sub{display:block;font-size:11.5px;color:var(--ink-3)}
  .acts{white-space:nowrap}
  .acts .inline{display:inline;margin:0}
  .btn.sm{font-size:12.5px;padding:6px 12px}
  .manrow td{background:var(--surface)}
  .manform label{display:block;font-size:12.5px;color:var(--ink-2);margin-bottom:7px}
  .manform .row{display:flex;gap:8px;align-items:center}
  .manform input{max-width:none;flex:1}
  .manform .warn{font-size:11.5px;color:var(--ink-3);margin:8px 0 0;line-height:1.9}
  .empty{font-size:13px;color:var(--ink-3)}
</style>
@endsection
