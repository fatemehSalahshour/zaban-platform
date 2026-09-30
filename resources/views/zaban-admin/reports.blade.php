@extends('zaban-admin.layout')
@section('title', 'گزارش‌ها')

@php
  $label = function (string $k): string {
      if (str_starts_with($k, 'w:')) return 'کلمه‌ی ' . substr($k, 2);
      $p = explode('|', substr($k, 2)) + ['', '', ''];
      return "سؤال {$p[2]} — کنکور {$p[0]} {$p[1]}";
  };

  /* نشانی همان کلمه یا تست در خود پلتفرم — مدیر باید بتواند در یک کلیک
     ببیند کاربر دقیقاً از چه چیزی حرف می‌زند، نه اینکه دستی دنبالش بگردد. */
  $link = function (string $k) use ($examCode): ?string {
      if (str_starts_with($k, 'w:')) return url('/zaban/word/' . rawurlencode(substr($k, 2)));
      $p = explode('|', substr($k, 2)) + ['', '', ''];
      $code = $examCode[$p[1]] ?? null;
      return ($code && $p[0] && $p[2]) ? url("/zaban/test/{$p[0]}/{$code}/{$p[2]}") : null;
  };

  /* راه رفع هر موضوع. بدون این، مدیر گزارش را می‌خواند و نمی‌داند از کجا
     شروع کند؛ مسیر اصلاح هر کدام واقعاً فرق می‌کند. */
  $howto = [
    'معنی نادرست' => 'معنی‌ها از ستون Persian Meaning همان ردیف در sheet1.csv می‌آیند. ردیف را اصلاح کنید و ایمپورت را دوباره بزنید. چون ایمپورت معنی‌ها را ادغام می‌کند، معنی غلط قبلی خودش پاک نمی‌شود و باید دستی حذفش کنید.',
    'اشتباه نگارشی' => 'اگر در معنی فارسی است، همان ردیف sheet1.csv؛ اگر در متن سؤال یا گزینه است، داده از پلتفرم آزمون می‌آید و با zaban:sync-questions به‌روز می‌شود.',
    'این کلمه در این تست نیست' => 'پیوند کلمه به تست، یک «ظهور» است. در «کلمه‌ها و ظهورها» همان کلمه را باز کنید و ظهور اشتباه را حذف کنید.',
    'گزینه اشتباه' => 'گزینه‌ها و کلید پاسخ از پلتفرم آزمون می‌آیند. اول همان‌جا اصلاح کنید، بعد zaban:sync-questions بزنید.',
    'فاقد جواب تشریحی' => 'پاسخ تشریحی از ستون explanation پلتفرم آزمون می‌آید. آنجا بنویسید و zaban:sync-questions بزنید.',
    'اشتباه علمی' => 'بسته به اینکه ایراد در معنی است یا در خود سؤال: معنی از sheet1.csv و سؤال از پلتفرم آزمون.',
  ];
  $tabs = ['open' => 'در انتظار پاسخ', 'answered' => 'پاسخ داده شده', 'all' => 'همه'];
@endphp

@section('body')
<div class="head">
  <h2>گزارش‌های کاربران</h2>
  <p>اشکال‌هایی که کاربران روی کلمه‌ها و سؤال‌ها گزارش داده‌اند. پاسخ شما
     روی همان دکمه‌ی ⚑ و در «گزارش‌های من» به کاربر نشان داده می‌شود.</p>
</div>

@php
  /* فیلترها روی هم جمع می‌شوند؛ هر لینک بقیه را نگه می‌دارد */
  $url = fn (array $over) => '?' . http_build_query(array_filter(
      array_merge(['state' => $state, 'topic' => $topic, 'kind' => $kind], $over)));
@endphp

<nav class="tabs">
  @foreach ($tabs as $k => $t)
    <a href="{{ $url(['state' => $k]) }}" class="{{ $state === $k ? 'on' : '' }}">
      {{ $t }}@if ($k !== 'all') <span class="n">{{ $counts[$k] }}</span>@endif
    </a>
  @endforeach
</nav>

<nav class="tabs sub">
  <a href="{{ $url(['kind' => null]) }}" class="{{ $kind === '' ? 'on' : '' }}">کلمه و سؤال</a>
  <a href="{{ $url(['kind' => 'w']) }}" class="{{ $kind === 'w' ? 'on' : '' }}">فقط کلمه‌ها</a>
  <a href="{{ $url(['kind' => 'q']) }}" class="{{ $kind === 'q' ? 'on' : '' }}">فقط سؤال‌ها</a>
</nav>

<nav class="tabs sub">
  <a href="{{ $url(['topic' => null]) }}" class="{{ $topic === '' ? 'on' : '' }}">همه‌ی موضوع‌ها</a>
  @foreach ($topics as $t)
    <a href="{{ $url(['topic' => $t]) }}" class="{{ $topic === $t ? 'on' : '' }}">{{ $t }}</a>
  @endforeach
</nav>

@forelse ($reports as $r)
  @php $u = $who[$r->user_id] ?? null; @endphp
  <div class="panel rep {{ $r->state === 'open' ? 'is-open' : '' }}">
    @php $href = $link($r->item_key); @endphp
    <div class="rep-h">
      @if ($href)
        <b><a href="{{ $href }}" target="_blank" rel="noopener">{{ $label($r->item_key) }} ↗</a></b>
      @else
        <b>{{ $label($r->item_key) }}</b>
      @endif
      <span class="tag">{{ $r->topic }}</span>
      <span class="tag {{ $r->state === 'open' ? 'wait' : 'gold' }}">
        {{ $r->state === 'open' ? 'در انتظار پاسخ' : 'پاسخ داده شده' }}</span>
      <span class="who">
        @if ($u)
          <a href="{{ route('zadmin.users', ['q' => $u->name]) }}">{{ $u->name }}</a>@if ($u->nickname) ({{ $u->nickname }})@endif
          @if ($u->mobile && $u->mobile !== $u->name) · {{ $u->mobile }}@endif
          · کاربر #{{ $r->user_id }}
        @else
          کاربر حذف‌شده · #{{ $r->user_id }}
        @endif
      </span>
    </div>

    <div class="rep-fix">
      @if (str_starts_with($r->item_key, 'w:'))
        <a href="{{ route('zadmin.words', ['q' => substr($r->item_key, 2)]) }}">کلمه و ظهورهایش در پنل</a>
      @endif
      @if (!empty($howto[$r->topic]))
        <span>{{ $howto[$r->topic] }}</span>
      @endif
    </div>

    <div class="thread">
      @foreach ($msgs[$r->id] ?? [] as $m)
        <div class="msg {{ $m->by }}">
          <div class="mh"><b>{{ $m->by === 'admin' ? 'پشتیبانی' : 'کاربر' }}</b>
            <span>{{ \Illuminate\Support\Carbon::parse($m->created_at)->format('Y-m-d H:i') }}</span></div>
          <div class="mb">{{ $m->body }}</div>
        </div>
      @endforeach
    </div>

    <form method="post" action="{{ route('zadmin.reports.reply', $r->id) }}">
      @csrf
      <textarea name="body" rows="3" required maxlength="4000" placeholder="پاسخ به کاربر…"></textarea>
      <div class="rep-a">
        <button class="btn" type="submit">فرستادن پاسخ</button>
        <button class="lnk del" type="submit" formaction="{{ route('zadmin.reports.delete', $r->id) }}"
                formnovalidate onclick="return confirm('این گزارش و همه‌ی پیام‌هایش پاک شود؟')">حذف گزارش</button>
      </div>
    </form>
  </div>
@empty
  <div class="panel"><div class="empty">
    @if ($state === 'open') گزارشی در انتظار پاسخ نیست.
    @elseif ($state === 'answered') هنوز به گزارشی پاسخ نداده‌اید.
    @else هنوز هیچ کاربری گزارشی نفرستاده. @endif
    @if ($topic !== '' || $kind !== '') <br><small>فیلتر فعال است؛ با برداشتن آن ممکن است گزارش‌های دیگری باشد.</small>@endif
  </div></div>
@endforelse

@if ($reports->hasPages())
  <div class="pager">
    <span>@if ($reports->previousPageUrl())<a href="{{ $reports->previousPageUrl() }}">صفحه‌ی قبل</a>@endif</span>
    <span>صفحه‌ی {{ $reports->currentPage() }} از {{ $reports->lastPage() }}</span>
    <span>@if ($reports->nextPageUrl())<a href="{{ $reports->nextPageUrl() }}">صفحه‌ی بعد</a>@endif</span>
  </div>
@endif

<style>
  .tabs{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap}
  .tabs a{padding:6px 14px;border:1px solid var(--line-2);border-radius:20px;background:var(--panel);
          font-size:13px;color:var(--ink-2)}
  .tabs a.on{border-color:var(--ink);background:var(--ink);color:#fff}
  .tabs.sub{margin-top:-8px}
  .tabs.sub a{font-size:12.5px;padding:4px 11px}
  .tabs.sub a.on{background:var(--gold,#b8892b);border-color:var(--gold,#b8892b)}
  .tabs .n{font-weight:700;font-variant-numeric:tabular-nums;margin-right:2px}
  .rep.is-open{border-right:3px solid var(--danger)}
  .rep-h{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px}
  .rep-h b{color:var(--ink);font-size:14.5px}
  .tag.wait{background:#fdf0eb;border-color:#f0cdbe;color:var(--danger)}
  .who{width:100%;font-size:12px;color:var(--ink-3)}
  .who a{color:inherit;text-decoration:underline;text-underline-offset:3px}
  .rep-h b a{color:inherit;text-decoration:none}
  .rep-h b a:hover{text-decoration:underline;text-underline-offset:3px}
  /* راه رفع — جعبه‌ی آرام بالای گفتگو، برای مدیر نه کاربر */
  .rep-fix{background:var(--surface);border:1px solid var(--line);border-radius:9px;
    padding:9px 12px;margin:0 0 12px;font-size:12px;line-height:2;color:var(--ink-2)}
  .rep-fix a{color:var(--gold);font-weight:500;margin-inline-end:8px;white-space:nowrap}
  .rep-fix:empty{display:none}
  .thread{display:flex;flex-direction:column;gap:8px;margin-bottom:12px}
  .msg{padding:8px 12px;border-radius:9px;max-width:88%}
  .msg.user{background:var(--surface);align-self:flex-start}
  .msg.admin{background:#fdf6e4;border:1px solid #e6d3a0;align-self:flex-end}
  .mh{display:flex;gap:10px;font-size:11.5px;color:var(--ink-3)}
  .mh b{color:var(--ink-2);font-weight:600}
  .mh span{direction:ltr;font-variant-numeric:tabular-nums}
  .mb{white-space:pre-wrap;word-wrap:break-word;color:var(--ink)}
  textarea{font-family:inherit;font-size:14px;line-height:1.8;padding:9px 11px;width:100%;
           border:1px solid var(--line-2);border-radius:8px;background:#fff;color:var(--ink);resize:vertical}
  textarea:focus{outline:2px solid var(--gold);outline-offset:1px;border-color:var(--gold)}
  .rep-a{display:flex;align-items:center;gap:10px;margin-top:10px}
  .lnk{font-family:inherit;font-size:12.5px;background:none;border:0;padding:0;cursor:pointer;
       color:var(--danger);text-decoration:underline;text-underline-offset:3px;margin-right:auto}
  .lnk:focus-visible{outline:2px solid var(--gold);outline-offset:2px}
  .pager{display:flex;justify-content:space-between;font-size:13.5px;color:var(--ink-3)}
  .pager a{color:var(--gold)}
</style>
@endsection
