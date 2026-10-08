@extends('zaban-admin.layout')
@section('title', 'کاربر ' . $u->name)

@section('body')
<div class="head">
  <h2>{{ $u->name }}</h2>
  <p>
    شناسه {{ $u->id }}@if ($u->mobile) · {{ $u->mobile }}@endif
    @if ($u->created_at) · عضویت {{ \App\Support\Jalali::formatFromGregorian($u->created_at) }}@endif
    · <a href="{{ route('zadmin.users') }}">بازگشت به فهرست کاربران</a>
  </p>
</div>

@if (session('ok'))    <div class="flash">{{ session('ok') }}</div> @endif
@if (session('error')) <div class="err">{{ session('error') }}</div> @endif

<form method="post" action="{{ route('zadmin.user.save', $u->id) }}">
  @csrf @method('put')

  <div class="panel">
    <h3>اطلاعات شخصی</h3>
    <p>موبایل از سرور احراز هویت می‌آید و اینجا عوض نمی‌شود — ورود کاربر با همان است.</p>

    <div class="field">
      <label for="name">نام و نام خانوادگی
        <i>در کارنامه و پشتیبانی دیده می‌شود</i>
      </label>
      <input type="text" id="name" name="name" maxlength="60" required
             value="{{ old('name', $u->name) }}">
    </div>

    <div class="field">
      <label for="mobile">شماره‌ی موبایل</label>
      <input type="text" id="mobile" value="{{ $u->mobile ?: '—' }}" disabled>
    </div>

    <div class="field">
      <label for="nickname">نام مستعار
        <i>در رتبه‌بندی دیده می‌شود؛ خالی یعنی «بی‌نام»</i>
      </label>
      <input type="text" id="nickname" name="nickname" maxlength="30"
             value="{{ old('nickname', $u->nickname) }}">
    </div>

    <div class="field">
      <label for="university">دانشگاه</label>
      <input type="text" id="university" name="university" maxlength="60"
             value="{{ old('university', $u->university) }}">
    </div>

    <div class="field">
      <label for="gpa">معدل <i>مثلاً ۱۷٫۵</i></label>
      <input type="number" id="gpa" name="gpa" step="0.01" min="0" max="20"
             value="{{ old('gpa', $u->gpa) }}">
    </div>

    <div class="field">
      <label for="quota">سهمیه</label>
      <input type="text" id="quota" name="quota" maxlength="30"
             value="{{ old('quota', $u->quota) }}">
    </div>
  </div>

  <div class="panel">
    <h3>رشته و نقش</h3>
    <p>رشته‌ی پیش‌فرض فقط تعیین می‌کند صفحه‌ها با کدام رشته باز شوند؛ دسترسی را پایین‌تر می‌دهید.</p>

    <div class="field">
      <label for="exam">رشته‌ی پیش‌فرض</label>
      <select id="exam" name="exam">
        @foreach (\App\Services\Pricing::NAMES as $code => $name)
          <option value="{{ $code }}" @selected(old('exam', $u->exam) === $code)>{{ $name }}</option>
        @endforeach
      </select>
    </div>

    <div class="field">
      <label for="type">سطح کاربری</label>
      <div>
        <select id="type" name="type">
          @foreach ($roles as $code => $name)
            <option value="{{ $code }}" @selected(old('type', $u->type ?: 'student') === $code)>{{ $name }}</option>
          @endforeach
        </select>
        <p class="warn">
          هر نقشی جز «دانشجو» دسترسی کامل به همین پنل می‌دهد: قیمت، تخفیف، اطلاعات کاربران و
          ورود به حساب آن‌ها. هر سه رشته هم بدون خرید برایشان باز می‌شود.
        </p>
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>دسترسی رشته‌ها</h3>
    <p>
      تیک زدن یعنی دسترسی تا روز کنکور ({{ \App\Support\Jalali::formatFromGregorian($until) }})
      باز می‌شود، بدون پرداخت. برداشتن تیک یعنی لغو — دسترسی خریداری‌شده هم از همین‌جا لغو
      می‌شود، پس قبلش مطمئن شوید پولش برگشته است.
    </p>

    <div class="ents">
      @foreach (\App\Services\Pricing::NAMES as $code => $name)
        @php $e = $ents[$code] ?? null; $on = $e && !$e->revoked_at; @endphp
        <label class="ent {{ $on ? 'on' : '' }}">
          <input type="checkbox" name="exams[]" value="{{ $code }}" @checked($on)>
          <span class="t">{{ $name }}</span>
          <span class="s">
            @if ($on)
              {{ $e->source === 'purchase' ? 'خریداری‌شده' : 'دستیِ پنل' }}@if ($e->expires_at) ·
                تا {{ \App\Support\Jalali::formatFromGregorian($e->expires_at) }}@endif
            @elseif ($e)
              قبلاً لغو شده
            @else
              ندارد
            @endif
          </span>
        </label>
      @endforeach
    </div>
  </div>

  <div class="actions">
    <button class="btn" type="submit">ذخیره‌ی تغییرات</button>
    <a class="btn ghost" href="{{ route('zadmin.users') }}">انصراف</a>
  </div>
</form>

@if (count($orders))
  <div class="panel">
    <h3>سفارش‌ها</h3>
    <p>ده سفارش آخر همین کاربر.</p>
    <table>
      <thead><tr><th>#</th><th>رشته‌ها</th><th>مبلغ</th><th>درگاه / پیگیری</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
      <tbody>
        @foreach ($orders as $o)
          <tr>
            <td class="num">{{ $o->id }}</td>
            <td>{{ $o->exams }}</td>
            <td class="num">{{ \App\Support\FaNum::format($o->payable) }}</td>
            <td>
              {{ \App\Services\Payment\Gateways::label($o->gateway) }}
              @if ($o->ref_id ?: $o->rrn)<span class="num" dir="ltr" style="display:block;font-size:11.5px;color:var(--ink-3);text-align:end">{{ $o->ref_id ?: $o->rrn }}</span>@endif
            </td>
            <td>
              <span class="tag {{ $o->status === 'paid' ? 'gold' : '' }}">{{ \App\Services\Payment\Gateways::status($o->status) }}</span>
              @if ($o->gateway_code && $o->gateway_code !== '00')<span style="display:block;font-size:11.5px;color:var(--ink-3)">کد {{ $o->gateway_code }}</span>@endif
            </td>
            <td class="num">{{ \App\Support\Jalali::formatFromGregorian($o->paid_at ?: $o->created_at) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

<style>
  /* select را هم‌شکل input های همین پنل می‌کنیم */
  .panel select{font-family:inherit;font-size:14px;padding:8px 11px;width:100%;max-width:280px;
    border:1px solid var(--line-2);border-radius:8px;background:#fff;color:var(--ink)}
  .panel select:focus{outline:2px solid var(--gold);outline-offset:1px;border-color:var(--gold)}
  .field input:disabled{background:var(--surface);color:var(--ink-3)}
  .warn{font-size:12px;color:var(--ink-3);line-height:1.95;margin:8px 0 0;max-width:52ch}

  .ents{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:10px}
  .ent{display:flex;align-items:center;gap:10px;border:1px solid var(--line-2);
    border-radius:10px;padding:12px 14px;cursor:pointer}
  .ent:hover{border-color:var(--ink-3)}
  .ent.on{border-color:#bfe0cd;background:#f3f9f5}
  .ent input{width:17px;height:17px;flex:none;accent-color:var(--green)}
  .ent .t{font-size:14px;color:var(--ink)}
  .ent .s{margin-inline-start:auto;font-size:11.5px;color:var(--ink-3);text-align:end}
</style>
@endsection
