@extends('zaban-admin.layout')
@section('title', 'خلاصه‌ی وضعیت')

@section('body')
<div class="head">
  <h2>خلاصه‌ی وضعیت</h2>
  <p>یک نگاه به اینکه پلتفرم الان کجاست.</p>
</div>

<div class="grid">
  @foreach ($stats as $label => $value)
    <div class="cell">
      <div class="v">{{ number_format($value) }}</div>
      <div class="k">{{ $label }}</div>
    </div>
  @endforeach
</div>

<div class="panel">
  <h3>دفترچه‌های ناقص</h3>
  <p>در این سال‌ها بعضی سؤال‌ها هنوز در پلتفرم آزمون وارد نشده‌اند و
     دانشجو دفترچه‌ی کم می‌بیند. بعد از تکمیل، دستور
     <code>zaban:sync-questions</code> را یک بار بزنید.</p>

  @if (isset($gaps['error']))
    <div class="empty">{{ $gaps['error'] }}<br>
      <span style="font-size:12.5px">تا وقتی این اتصال برقرار نشود،
        وضعیت دفترچه‌ها معلوم نیست.</span></div>
  @elseif (count($gaps))
    <table>
      <thead>
        <tr><th>سال</th><th>رشته</th><th>سؤال کم</th><th>وارد شده</th><th>باید باشد</th></tr>
      </thead>
      <tbody>
        @foreach ($gaps as $g)
          <tr>
            <td class="num">{{ $g['year'] }}</td>
            <td>{{ $g['exam'] }}</td>
            <td class="num"><span class="tag gold">{{ $g['missing'] }}</span></td>
            <td class="num">{{ $g['actual'] }}</td>
            <td class="num">{{ $g['expected'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <div class="foot">{{ count($gaps) }} دفترچه ناقص است.</div>
  @else
    <div class="empty">همه‌ی دفترچه‌ها کامل‌اند.</div>
  @endif
</div>

<style>
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(178px,1fr));
        gap:12px;margin-bottom:18px}
  .cell{background:var(--panel);border:1px solid var(--line);border-radius:var(--r);
        padding:16px 18px}
  .cell .v{font-size:26px;font-weight:700;color:var(--ink);
           font-variant-numeric:tabular-nums;letter-spacing:-.02em}
  .cell .k{font-size:12.5px;color:var(--ink-3);margin-top:2px}
  code{background:var(--surface);border:1px solid var(--line);border-radius:5px;
       padding:1px 6px;font-size:12px;direction:ltr;display:inline-block}
  .foot{margin-top:14px;padding-top:12px;border-top:1px solid var(--line);
        font-size:12.5px;color:var(--ink-3)}
</style>
@endsection
