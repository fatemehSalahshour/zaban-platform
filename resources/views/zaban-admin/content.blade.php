@extends('zaban-admin.layout')
@section('title', 'محتوای بانک')

@section('body')
<div class="head">
  <h2>محتوای بانک</h2>
  <p>وضعیت کلمات و ظهورهایشان در هر سال و رشته.</p>
</div>

<div class="panel">
  <h3>نسخه‌ی محتوا</h3>
  <p>مرورگر دانشجو تا وقتی این عدد عوض نشود، محتوای کش‌شده را نگه می‌دارد.
     بعد از وارد کردن دیتای تازه یک بار بالا ببریدش.</p>

  <table style="margin-bottom:16px">
    <tbody>
      @foreach ($versions as $k => $v)
        <tr>
          <td>{{ \App\Services\Pricing::NAMES[str_replace('content_version_', '', $k)] ?? $k }}</td>
          <td class="num">{{ $v }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <form method="post" action="{{ route('zadmin.content.bump') }}">
    @csrf
    <button class="btn ghost" type="submit">بالا بردن نسخه</button>
  </form>
</div>

<div class="panel">
  <h3>ظهور کلمات در هر دفترچه</h3>
  <p>هر ردیف تعداد ظهورهای ثبت‌شده در آن سال و رشته است.</p>

  @if (count($years))
    <table>
      <thead><tr><th>سال</th><th>رشته</th><th>ظهور</th></tr></thead>
      <tbody>
        @foreach ($years as $y)
          <tr>
            <td class="num">{{ $y->year }}</td>
            <td>{{ \App\Services\Pricing::NAMES[$y->exam] ?? $y->exam }}</td>
            <td class="num">{{ number_format($y->occ) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <div class="empty">هنوز کلمه‌ای وارد نشده.</div>
  @endif
</div>
@endsection
