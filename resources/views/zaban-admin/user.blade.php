@extends('zaban-admin.layout')
@section('title', 'کاربر ' . $u->name)

@section('body')
<div class="head">
  <h2>{{ $u->name }}</h2>
  <p>
    شناسه {{ $u->id }}
    @if ($u->mobile) · {{ $u->mobile }} @endif
    @if ($u->created_at) · عضویت {{ \App\Support\Jalali::formatFromGregorian($u->created_at) }} @endif
    · <a href="{{ route('zadmin.users') }}">بازگشت به فهرست</a>
  </p>
</div>

@if (session('ok'))   <div class="flash ok">{{ session('ok') }}</div> @endif
@if (session('error'))<div class="flash err">{{ session('error') }}</div> @endif

<form method="post" action="{{ route('zadmin.user.save', $u->id) }}">
  @csrf @method('put')

  <div class="panel">
    <h3>اطلاعات شخصی</h3>
    <p class="hint">
      موبایل از سرور احراز هویت می‌آید و اینجا عوض نمی‌شود — ورود کاربر با همان است.
    </p>

    <div class="grid">
      <div class="field">
        <label for="name">نام و نام خانوادگی</label>
        <input type="text" id="name" name="name" maxlength="60"
               value="{{ old('name', $u->name) }}" required>
      </div>
      <div class="field">
        <label for="mobile">شماره‌ی موبایل</label>
        <input type="text" id="mobile" value="{{ $u->mobile ?: '—' }}" disabled>
      </div>
      <div class="field">
        <label for="nickname">نام مستعار <i>در رتبه‌بندی دیده می‌شود</i></label>
        <input type="text" id="nickname" name="nickname" maxlength="30"
               value="{{ old('nickname', $u->nickname) }}">
      </div>
      <div class="field">
        <label for="university">دانشگاه</label>
        <input type="text" id="university" name="university" maxlength="60"
               value="{{ old('university', $u->university) }}">
      </div>
      <div class="field">
        <label for="gpa">معدل</label>
        <input type="number" id="gpa" name="gpa" step="0.01" min="0" max="20"
               value="{{ old('gpa', $u->gpa) }}">
      </div>
      <div class="field">
        <label for="quota">سهمیه</label>
        <input type="text" id="quota" name="quota" maxlength="30"
               value="{{ old('quota', $u->quota) }}">
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>رشته و نقش</h3>
    <div class="grid">
      <div class="field">
        <label for="exam">رشته‌ی پیش‌فرض <i>صفحه‌ها با همین باز می‌شوند</i></label>
        <select id="exam" name="exam">
          @foreach (\App\Services\Pricing::NAMES as $code => $name)
            <option value="{{ $code }}" @selected(old('exam', $u->exam) === $code)>{{ $name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label for="type">سطح کاربری
          <i>ویراستار، مدیرمحتوا و مدیرکل بدون خرید به هر سه رشته دسترسی دارند</i>
        </label>
        <select id="type" name="type">
          @foreach ($roles as $code => $name)
            <option value="{{ $code }}" @selected(old('type', $u->type ?: 'student') === $code)>{{ $name }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>دسترسی رشته‌ها</h3>
    <p class="hint">
      تیک زدن یعنی دسترسی تا روز کنکور ({{ \App\Support\Jalali::formatFromGregorian($until) }}) باز می‌شود،
      بدون پرداخت. برداشتن تیک یعنی لغو. دسترسی خریداری‌شده هم از همین‌جا لغو می‌شود، پس
      قبل از برداشتن تیک مطمئن شوید پولش برگشته است.
    </p>

    <div class="ent-list">
      @foreach (\App\Services\Pricing::NAMES as $code => $name)
        @php $e = $ents[$code] ?? null; $on = $e && !$e->revoked_at; @endphp
        <label class="ent {{ $on ? 'on' : '' }}">
          <input type="checkbox" name="exams[]" value="{{ $code }}" @checked($on)>
          <span class="t">{{ $name }}</span>
          <span class="s">
            @if ($on)
              {{ $e->source === 'purchase' ? 'خریداری‌شده' : 'دستیِ پنل' }}
              @if ($e->expires_at) · تا {{ \App\Support\Jalali::formatFromGregorian($e->expires_at) }} @endif
            @elseif ($e)
              لغو شده
            @else
              ندارد
            @endif
          </span>
        </label>
      @endforeach
    </div>
  </div>

  <div class="actions">
    <button class="btn gold" type="submit">ذخیره‌ی تغییرات</button>
    <a class="btn ghost" href="{{ route('zadmin.users') }}">انصراف</a>
  </div>
</form>

@if (count($orders))
  <div class="panel">
    <h3>سفارش‌ها</h3>
    <table>
      <thead><tr><th>#</th><th>رشته‌ها</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
      <tbody>
        @foreach ($orders as $o)
          <tr>
            <td class="num">{{ $o->id }}</td>
            <td>{{ $o->exams }}</td>
            <td class="num">{{ \App\Support\FaNum::format($o->payable) }}</td>
            <td>
              <span class="tag {{ $o->status === 'paid' ? 'gold' : '' }}">{{ $o->status }}</span>
            </td>
            <td class="num">{{ \App\Support\Jalali::formatFromGregorian($o->paid_at ?: $o->created_at) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

<style>
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
  .field label{display:block;font-size:13px;margin-bottom:5px}
  .field label i{display:block;font-style:normal;font-size:11.5px;color:var(--ink-3);line-height:1.8}
  .field input,.field select{width:100%}
  .field input:disabled{opacity:.6}
  .hint{font-size:12.5px;color:var(--ink-3);line-height:2;margin:0 0 14px}
  .ent-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px}
  .ent{display:flex;align-items:center;gap:9px;border:1px solid var(--line);border-radius:10px;
       padding:11px 13px;cursor:pointer}
  .ent.on{border-color:#bcdcd2;background:#f2f8f5}
  .ent .t{font-size:14px}
  .ent .s{margin-inline-start:auto;font-size:11.5px;color:var(--ink-3)}
  .actions{display:flex;gap:10px;margin:18px 0}
  .flash{padding:10px 14px;border-radius:9px;margin-bottom:14px;font-size:13px}
  .flash.ok{background:#f2f8f5;border:1px solid #bcdcd2}
  .flash.err{background:#fdf0eb;border:1px solid #f0cdbe}
</style>
@endsection
