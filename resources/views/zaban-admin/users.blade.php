@extends('zaban-admin.layout')
@section('title', 'کاربران')

@section('body')
<div class="head">
  <h2>کاربران و دسترسی‌ها</h2>
  <p>صد کاربر آخر. برای پیدا کردن یک نفر، نام یا ایمیلش را بنویسید.</p>
</div>

<div class="panel">
  <form method="get" class="search">
    <input type="search" name="q" value="{{ $q }}" placeholder="نام، ایمیل یا نام مستعار">
    <button class="btn ghost" type="submit">جست‌وجو</button>
  </form>

  @if (count($users))
    <table>
      <thead>
        <tr><th>#</th><th>نام</th><th>ایمیل</th><th>نقش</th>
            <th>نام مستعار</th><th>رشته‌های فعال</th></tr>
      </thead>
      <tbody>
        @foreach ($users as $u)
          <tr>
            <td class="num">{{ $u->id }}</td>
            <td>{{ $u->name }}</td>
            <td class="num">{{ $u->email }}</td>
            <td>
              @if (in_array($u->type ?? 'student', ['admin','manager','editor']))
                <span class="tag gold">{{ $u->type }}</span>
              @else
                <span class="tag">{{ $u->type ?? 'student' }}</span>
              @endif
            </td>
            <td>{{ $u->nickname ?: '—' }}</td>
            <td>
              @forelse ($ent[$u->id] ?? [] as $e)
                <span class="tag">{{ \App\Services\Pricing::NAMES[$e] ?? $e }}</span>
              @empty
                <span style="color:var(--ink-3)">—</span>
              @endforelse
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <div class="empty">کاربری با این جست‌وجو پیدا نشد.</div>
  @endif
</div>

<style>
  .search{display:flex;gap:10px;margin-bottom:18px}
  .search input{max-width:320px}
</style>
@endsection
