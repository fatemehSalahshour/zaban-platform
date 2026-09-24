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
            <th>نام مستعار</th><th>رشته‌های فعال</th><th></th></tr>
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
            <td>
              @if ($u->id !== auth()->id())
                <form method="post" action="{{ route('impersonate.start', $u->id) }}" class="inline-form">
                  @csrf
                  <button class="btn ghost" type="submit">ورود به حساب</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <div class="empty">کاربری با این جست‌وجو پیدا نشد.</div>
  @endif
</div>

@if (session('error'))
  <div class="panel" style="border-color:#b91c1c;color:#b91c1c">{{ session('error') }}</div>
@endif

<style>
  .inline-form{margin:0}
  .search{display:flex;gap:10px;margin-bottom:18px}
  .search input{max-width:320px}
</style>
@endsection
