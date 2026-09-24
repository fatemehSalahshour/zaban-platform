@extends('buy._layout')
@section('title', 'انتقال به درگاه')

@section('body')
  {{-- درگاه فقط POST با tokenIdentity می‌پذیرد؛ فرم خودکار فرستاده می‌شود --}}
  <h1>در حال انتقال به درگاه پرداخت…</h1>
  <p class="sub">اگر چند ثانیه‌ی دیگر منتقل نشدید، دکمه‌ی زیر را بزنید.</p>
  <form method="post" action="{{ $url }}" id="go">
    <input type="hidden" name="tokenIdentity" value="{{ $token }}">
    <button class="btn" type="submit">ورود به درگاه پرداخت</button>
  </form>
  <script>document.getElementById('go').submit();</script>
@endsection
