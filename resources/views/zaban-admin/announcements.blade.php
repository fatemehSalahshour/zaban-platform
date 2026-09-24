@extends('zaban-admin.layout')
@section('title', 'اطلاعیه‌ها')

@section('body')
<div class="head">
  <h2>اطلاعیه‌ها</h2>
  <p>پیامی که اینجا منتشر شود در تب «اطلاعیه‌ها»ی همه‌ی کاربران می‌آید و
     روی زنگ بالای صفحه‌شان عدد می‌نشیند، تا وقتی بازش کنند.</p>
</div>

<form method="post" class="panel"
      action="{{ $editing ? route('zadmin.announcements.update', $editing->id) : route('zadmin.announcements.store') }}">
  @csrf
  <h3>{{ $editing ? 'ویرایش اطلاعیه' : 'اطلاعیه‌ی تازه' }}</h3>
  <p>@if ($editing && $editing->published_at)
       این اطلاعیه منتشر شده. ویرایش متن آن را دوباره «تازه» نمی‌کند.
     @else
       متن ساده بنویسید. هر خط جدید در صفحه‌ی کاربر هم خط جدید می‌شود.
     @endif</p>

  <div class="stack">
    <label for="title">عنوان</label>
    <input type="text" id="title" name="title" maxlength="200" required
           value="{{ old('title', $editing->title ?? '') }}">

    <label for="body">متن</label>
    <textarea id="body" name="body" rows="7" maxlength="5000" required>{{ old('body', $editing->body ?? '') }}</textarea>
  </div>

  <div class="actions">
    @if ($editing)
      <button class="btn" type="submit">ذخیره‌ی تغییرات</button>
      <a class="btn ghost" href="{{ route('zadmin.announcements') }}">انصراف</a>
    @else
      <button class="btn" type="submit" name="publish" value="1">انتشار برای همه</button>
      <button class="btn ghost" type="submit" name="publish" value="0">ذخیره به‌عنوان پیش‌نویس</button>
    @endif
  </div>
</form>

<div class="panel">
  <h3>همه‌ی اطلاعیه‌ها</h3>
  <p>پیش‌نویس‌ها بالای فهرست‌اند. برداشتن انتشار، اطلاعیه را از دید کاربران
     پنهان می‌کند بی‌آنکه پاکش کند.</p>

  @forelse ($items as $a)
    <div class="ann {{ $editing && $editing->id === $a->id ? 'is-editing' : '' }}">
      <div class="ann-h">
        <b>{{ $a->title }}</b>
        @if ($a->published_at)
          <span class="tag gold">منتشر شده</span>
          <span class="when">{{ \Illuminate\Support\Carbon::parse($a->published_at)->format('Y-m-d H:i') }}</span>
        @else
          <span class="tag">پیش‌نویس</span>
        @endif
      </div>
      <div class="ann-b">{{ \Illuminate\Support\Str::limit($a->body, 260) }}</div>
      <div class="ann-a">
        <a class="lnk" href="{{ route('zadmin.announcements.edit', $a->id) }}">ویرایش</a>
        <form method="post" action="{{ route('zadmin.announcements.toggle', $a->id) }}">
          @csrf
          <button class="lnk" type="submit">{{ $a->published_at ? 'برداشتن انتشار' : 'انتشار' }}</button>
        </form>
        <form method="post" action="{{ route('zadmin.announcements.delete', $a->id) }}"
              onsubmit="return confirm('این اطلاعیه برای همیشه پاک شود؟')">
          @csrf
          <button class="lnk del" type="submit">حذف</button>
        </form>
      </div>
    </div>
  @empty
    <div class="empty">هنوز اطلاعیه‌ای نوشته نشده. اولین اطلاعیه را از فرم بالا بنویسید.</div>
  @endforelse
</div>

<style>
  .stack{display:grid;gap:6px}
  .stack label{font-size:13px;color:var(--ink-2);margin-top:8px}
  .stack input[type=text]{max-width:none}
  textarea{font-family:inherit;font-size:14px;line-height:1.9;padding:9px 11px;width:100%;
           border:1px solid var(--line-2);border-radius:8px;background:#fff;color:var(--ink);resize:vertical}
  textarea:focus{outline:2px solid var(--gold);outline-offset:1px;border-color:var(--gold)}
  .ann{padding:14px 0;border-top:1px solid var(--line)}
  .ann:first-of-type{border-top:0}
  .ann.is-editing{background:#fdf6e4;margin:0 -24px;padding:14px 24px}
  .ann-h{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .ann-h b{color:var(--ink);font-size:14.5px}
  .when{font-size:12px;color:var(--ink-3);direction:ltr;font-variant-numeric:tabular-nums}
  .ann-b{font-size:13px;color:var(--ink-3);margin-top:4px;white-space:pre-line}
  .ann-a{display:flex;gap:16px;margin-top:8px}
  .ann-a form{display:inline}
  .lnk{font-family:inherit;font-size:12.5px;background:none;border:0;padding:0;cursor:pointer;
       color:var(--ink-2);text-decoration:underline;text-underline-offset:3px}
  .lnk:hover{color:var(--ink)}
  .lnk.del{color:var(--danger)}
  .lnk:focus-visible{outline:2px solid var(--gold);outline-offset:2px}
</style>
@endsection
