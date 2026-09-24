@extends('zaban-admin.layout')
@section('title', 'هشدارهای امنیتی')

@section('body')
<div class="head">
  <h2>هشدارهای امنیتی</h2>
  <p>کاربرانی که رفتارشان به برداشتن انبوه محتوا یا اشتراک حساب شبیه است. هر نوع هشدار
     برای هر کاربر روزی یک بار ثبت می‌شود؛ ستون «تکرار» تعداد روزهای دارای هشدار در ۳۰ روز اخیر است.</p>
</div>

@if ($locked->isNotEmpty())
<div class="panel">
  <h3>حساب‌های قفل‌شده</h3>
  <p>با رفتار شبیه اسکریپت خودکار قفل شده‌اند و سر وقت خودشان باز می‌شوند. اگر اشتباه است، همین‌جا باز کنید.</p>
  <table class="tbl">
    <thead><tr><th>کاربر</th><th>علت</th><th>قفل تا</th><th></th></tr></thead>
    <tbody>
    @foreach ($locked as $u)
      <tr>
        <td>{{ $u->name }}<small>{{ $u->mobile ?? $u->email }} · شماره‌ی {{ $u->id }}</small></td>
        <td>{{ $u->lock_reason }}</td>
        <td class="num" dir="ltr">{{ \Illuminate\Support\Carbon::parse($u->locked_until)->format('Y-m-d H:i') }}</td>
        <td><form method="post" action="{{ route('zadmin.users.unlock', $u->id) }}">@csrf
          <button class="btn ghost" type="submit">باز کردن قفل</button></form></td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif

<div class="panel">
  @if ($alerts->isEmpty())
    <div class="empty">در ۳۰ روز اخیر هشداری ثبت نشده است.</div>
  @else
    <table class="tbl">
      <thead><tr><th>زمان</th><th>کاربر</th><th>هشدار</th><th>جزئیات</th><th>تکرار</th></tr></thead>
      <tbody>
      @foreach ($alerts as $a)
        <tr class="{{ $a->seen_at ? '' : 'new' }}">
          <td class="num" dir="ltr">{{ \Illuminate\Support\Carbon::parse($a->created_at)->format('Y-m-d H:i') }}</td>
          <td>{{ $a->name ?? 'کاربر حذف‌شده' }}
              <small>{{ $a->mobile ?? $a->email }} · شماره‌ی {{ $a->user_id }}</small></td>
          <td>{{ $kinds[$a->kind] ?? $a->kind }}</td>
          <td>{{ $a->detail }}</td>
          <td class="num">{{ $repeat[$a->user_id] ?? 1 }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  @endif
</div>

<style>
  .tbl{width:100%;border-collapse:collapse;font-size:13.5px}
  .tbl th{text-align:right;color:var(--ink-3);font-weight:500;padding:8px 10px;border-bottom:1px solid var(--line)}
  .tbl td{padding:10px;border-bottom:1px solid var(--line);vertical-align:top}
  .tbl td small{display:block;color:var(--ink-3);font-size:12px}
  .tbl tr.new td{background:#fdf6e4}
  .tbl .num{font-variant-numeric:tabular-nums}
</style>
@endsection
