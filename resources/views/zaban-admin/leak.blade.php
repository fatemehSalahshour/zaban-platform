@extends('zaban-admin.layout')
@section('title', 'ردیابی متن منتشرشده')

@section('body')
<div class="head">
  <h2>ردیابی متن منتشرشده</h2>
  <p>هر معنی، مثال و پاسخ تشریحی که به کاربر فرستاده می‌شود، شماره‌ی همان کاربر را با نویسه‌های
     نامرئی در خود دارد. اگر بخشی از بانک را جایی (کانال، سایت، فایل) دیدید، متنش را کپی کنید و
     اینجا بچسبانید. چند معنی یا یک پاراگراف کافی است.</p>
</div>

<div class="panel">
  <form method="post" action="{{ route('zadmin.leak') }}">@csrf
    <div class="field">
      <label for="text">متن منتشرشده</label>
      <textarea id="text" name="text" rows="8" style="width:100%;font:inherit;padding:10px;border:1px solid var(--line);border-radius:8px">{{ $text }}</textarea>
    </div>
    <div class="actions"><button class="btn" type="submit">ردیابی</button></div>
  </form>
</div>

@if ($checked)
<div class="panel">
  @if (!$hits)
    <div class="empty">امضایی در این متن پیدا نشد. یا متن از این پلتفرم نیست، یا هنگام انتشار
      نویسه‌های نامرئی پاک شده‌اند (مثلاً بازتایپ یا عکس از صفحه). متن را از منبع اصلی و با
      کپی‌وچسباندن مستقیم بیاورید.</div>
  @else
    <h3>منبع احتمالی</h3>
    <table class="tbl">
      <thead><tr><th>کاربر</th><th>نقش</th><th>ثبت‌نام</th><th>تعداد امضا در متن</th></tr></thead>
      <tbody>
      @foreach ($hits as $uid => $n)
        @php $u = $users[$uid] ?? null; @endphp
        <tr>
          <td>{{ $u->name ?? 'کاربر ناشناس' }}<small>{{ $u ? ($u->mobile ?? $u->email) : '' }} · شماره‌ی {{ $uid }}</small></td>
          <td>{{ $u->type ?? '—' }}</td>
          <td class="num" dir="ltr">{{ $u ? \Illuminate\Support\Carbon::parse($u->created_at)->format('Y-m-d') : '—' }}</td>
          <td class="num">{{ $n }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
    <p class="note">اگر چند کاربر پیدا شد، متن از چند حساب جمع شده است. تعداد امضای بیشتر = سهم بیشتر.</p>
  @endif
</div>
@endif

<style>
  .tbl{width:100%;border-collapse:collapse;font-size:13.5px}
  .tbl th{text-align:right;color:var(--ink-3);font-weight:500;padding:8px 10px;border-bottom:1px solid var(--line)}
  .tbl td{padding:10px;border-bottom:1px solid var(--line)}
  .tbl td small{display:block;color:var(--ink-3);font-size:12px}
  .tbl .num{font-variant-numeric:tabular-nums}
  .note{color:var(--ink-3);font-size:12.5px;margin-top:10px}
</style>
@endsection
