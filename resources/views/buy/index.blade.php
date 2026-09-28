@extends('buy._layout')
@section('title', 'خرید و پرداخت‌ها')
{{-- محتوا مستقیم روی زمینه‌ی پلتفرم می‌نشیند، نه توی کارت سفید _layout --}}
@section('bare', true)

@push('head')
<style>
  /* --ok/--danger در zaban.css نیست (آن‌جا --accent است)؛ این‌جا تعریفشان می‌کنیم. */
  :root{--ok:#1d6b58;--ok-soft:#e6f2ee;--danger:#a8461f;--danger-soft:#fbe4dc}

  .buyhead{font-size:24px;font-weight:800;letter-spacing:-.02em;margin-bottom:6px}
  .launch{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#fdeed3;
       border:1px solid var(--gold-line);border-radius:12px;padding:11px 15px;margin-bottom:16px}
  .launch b{font-size:15px;color:#8a5206}
  .launch span{font-size:13px;color:#8a5206;opacity:.85;margin-right:auto}
  .launch span.note{margin-right:0;opacity:1}
  .buylead{color:var(--ink-2);margin-bottom:22px}

  .buygrid{display:grid;grid-template-columns:1.6fr 1fr;gap:16px;align-items:start}
  @media(max-width:820px){.buygrid{grid-template-columns:1fr}}

  .pkg{position:relative;display:flex;gap:14px;background:var(--paper);border:2px solid var(--line);
       border-radius:14px;padding:16px;cursor:pointer;margin-bottom:11px;transition:border-color .15s,background .15s}
  .pkg:hover{border-color:var(--line-2)}
  .pkg.on{border-color:var(--ok);background:var(--ok-soft)}
  .pkg.owned{cursor:default;border-color:var(--ok);background:var(--ok-soft)}
  .pkg input{position:absolute;opacity:0;pointer-events:none}
  .pkg .box{width:22px;height:22px;border:2px solid var(--line-2);border-radius:6px;flex-shrink:0;margin-top:3px;
       display:flex;align-items:center;justify-content:center;font-size:13px;color:#fff;background:var(--paper);transition:.15s}
  .pkg.on .box,.pkg.owned .box{background:var(--ok);border-color:var(--ok)}
  .pkg h4{font-size:16px;font-weight:700}
  .pkg .d{font-size:13.5px;color:var(--ink-2);margin:2px 0 8px}
  .pkg .tags{display:flex;gap:6px;flex-wrap:wrap}
  .pkg .tag{font-size:12px;background:var(--canvas);border-radius:6px;padding:2px 9px;color:var(--ink-2)}
  .pkg.on .tag,.pkg.owned .tag{background:#fff}
  .pkg .pr{margin-right:auto;text-align:left;font-weight:700;white-space:nowrap;font-variant-numeric:tabular-nums}
  .pkg .pr small{display:block;font-size:12px;font-weight:400;color:var(--ink-3)}
  .pkg .own-tag{margin-right:auto;align-self:center;font-size:12px;background:#fff;color:var(--ok);
       border-radius:20px;padding:3px 11px;font-weight:500;white-space:nowrap}

  .ladder{display:flex;gap:8px;margin-top:14px}
  .ladder div{flex:1;border:1px solid var(--line);border-radius:10px;padding:10px 8px;text-align:center;
       font-size:12.5px;color:var(--ink-2);transition:.15s}
  .ladder div.on{border-color:var(--ok);background:var(--ok-soft);color:var(--ok);font-weight:700}
  .ladder b{display:block;font-size:15px;color:var(--ink);font-weight:800;font-variant-numeric:tabular-nums}
  .ladder div.on b{color:var(--ok)}

  .hintbox{background:var(--canvas);border-radius:10px;padding:11px 13px;font-size:13px;color:var(--ink-2);margin-top:11px}

  .sum{background:var(--paper);border:1px solid var(--line);border-radius:14px;padding:18px;position:sticky;top:78px}
  .sum h3{font-size:16px;font-weight:700;margin-bottom:12px}
  .sum .line{display:flex;justify-content:space-between;font-size:14px;padding:6px 0;color:var(--ink-2)}
  .sum .line b{color:var(--ink);font-weight:500;font-variant-numeric:tabular-nums}
  .sum .line.off{color:#8d3617}
  .sum .line.off b{color:#8d3617}
  .sum .total{display:flex;justify-content:space-between;align-items:baseline;
       border-top:1px solid var(--line);margin-top:10px;padding-top:12px}
  .sum .total .v{font-size:24px;font-weight:800;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
  .sum .was{font-size:12.5px;color:var(--ink-3);text-decoration:line-through;font-variant-numeric:tabular-nums}
  .sum .savebox{background:#fdeed3;color:#8a5206;border-radius:10px;padding:9px 12px;font-size:13px;
       margin-top:10px;font-weight:500}
  .cta{display:block;width:100%;text-align:center;background:var(--ok);color:#fff;border:0;border-radius:12px;
       padding:13px 26px;font:inherit;font-weight:500;font-size:16px;cursor:pointer;margin-top:14px}
  .cta:hover{background:#14503f}
  .cta[disabled]{background:#b9bec4;cursor:not-allowed}
</style>
@endpush

@section('body')
  <h2 class="buyhead">پکیج خود را انتخاب کنید</h2>
  <p class="buylead">هر پکیج شامل کل کلمات {{ \App\Support\FaNum::format(max(array_column($stats, 'years')) ?: 0) }} سال همان رشته است. با انتخاب چند رشته، تخفیف پلکانی خودکار اعمال می‌شود.</p>

  @if (!empty($launch['active']))
    <div class="launch">
      <b>{{ \App\Support\FaNum::format($launch['percent']) }}٪ {{ $launch['title'] ?? \App\Services\Pricing::LAUNCH_TITLE_DEFAULT }}</b>
      @if (!empty($launch['note']))<span class="note">{{ $launch['note'] }}</span>@endif
      @if ($launch['days_left'] !== null)
        <span>{{ $launch['days_left'] > 0
          ? \App\Support\FaNum::format($launch['days_left']) . ' روز تا پایان'
          : 'امروز آخرین روز است' }}</span>
      @endif
    </div>
  @endif

  @if ($fake)<div class="dev">محیط توسعه: درگاه آزمایشی روشن است و پولی جابه‌جا نمی‌شود.</div>@endif
  @if (session('buy_error'))<div class="err" role="alert">{{ session('buy_error') }}</div>@endif
  @if ($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif

  @php
    $unit = $bundles[1] ?? 0;
    $remaining = count($names) - count($owned);
  @endphp

  <form method="post" action="{{ route('buy.start') }}" id="f">
    @csrf
    <div class="buygrid">
      <div>
        @foreach ($names as $code => $name)
          @php $isOwned = in_array($code, $owned, true); $st = $stats[$code] ?? ['years'=>0,'words'=>0,'occ'=>0,'y1'=>null,'y2'=>null]; @endphp
          <label class="pkg {{ $isOwned ? 'owned' : '' }}">
            @unless ($isOwned)
              <input type="checkbox" name="exams[]" value="{{ $code }}"
                     @checked(in_array($code, old('exams', $pre), true))>
            @endunless
            <div class="box">✓</div>
            <div style="flex:1">
              <h4>کلمات ارشد {{ $name }}</h4>
              <div class="d">تمام کلمات وکب، کلوز تست و پسیج آزمون {{ $name }}@if ($st['y1'] && $st['y2']) از {{ \App\Support\FaNum::format($st['y1']) }} تا {{ \App\Support\FaNum::format($st['y2']) }}@endif</div>
              <div class="tags">
                <span class="tag">{{ \App\Support\FaNum::format($st['years']) }} سال</span>
                <span class="tag">{{ \App\Support\FaNum::format($st['words']) }} کلمه‌ی یکتا</span>
                <span class="tag">{{ \App\Support\FaNum::format($st['occ']) }} ظهور</span>
              </div>
            </div>
            @if ($isOwned)
              <span class="own-tag">فعال است</span>
            @else
              <div class="pr">{{ \App\Support\FaNum::format($unit) }}<small>تومان</small></div>
            @endif
          </label>
        @endforeach

        @if ($remaining > 1)
          <div class="ladder" id="ladder">
            @foreach (range(1, min(3, $remaining)) as $n)
              @php
                $list = $n * $unit;
                $pay  = $bundles[$n] ?? $list;
                $off  = $list > 0 ? (int) round((($list - $pay) / $list) * 100) : 0;
              @endphp
              <div data-n="{{ $n }}"><b>{{ \App\Support\FaNum::format($pay) }}</b>{{ $n === 1 ? 'یک رشته' : ($n === 2 ? 'دو رشته' : 'هر سه رشته') }}@if ($off > 0) · {{ \App\Support\FaNum::format($off) }}٪ تخفیف @endif</div>
            @endforeach
          </div>
        @endif

        <div class="hintbox">کلمات مشترک بین رشته‌ها در پکیج‌های ترکیبی یک بار حساب می‌شوند و تکراری نمی‌بینید؛ ولی فراوانی هر کلمه به تفکیک رشته باقی می‌ماند.</div>
      </div>

      <div class="sum" aria-live="polite">
        <h3>خلاصه‌ی سفارش</h3>
        <div id="lines"></div>
        <div class="total">
          <div><div style="font-size:13px;color:var(--ink-2)">مبلغ قابل پرداخت</div>
            <div class="was" id="was" hidden></div></div>
          <div class="v" id="total">۰ <span style="font-size:13px;font-weight:400">تومان</span></div>
        </div>
        <div class="savebox" id="save" hidden></div>
        <button class="cta" id="pay" type="submit" disabled>پرداخت و فعال‌سازی</button>
        <div class="hintbox" style="text-align:center">دسترسی تا روز برگزاری کنکور ارشد است.@if ($until)
          <br><span class="num">{{ str_replace('-', '/', \App\Support\Jalali::formatFromGregorian(
              \Illuminate\Support\Carbon::parse($until)->format('Y-m-d'))) }}</span>@endif</div>
      </div>
    </div>
  </form>

<script>
(function(){
  const f=document.getElementById('f'), lines=document.getElementById('lines'),
        pay=document.getElementById('pay'), ladder=document.getElementById('ladder'),
        totalEl=document.getElementById('total'), wasEl=document.getElementById('was'),
        saveEl=document.getElementById('save');
  const money=n=>Number(n).toLocaleString('fa-IR');
  const toman=n=>money(n)+' تومان';
  const boxes=[...f.querySelectorAll('input[type=checkbox]')];

  /* پلکان تخفیف مستقیم از سرور (نه از روی متن فارسیِ صفحه) — برای پیام
     «با یک رشته‌ی دیگر چقدر بیشتر می‌پردازید». */
  const BUNDLE=@json($bundles);

  if(!boxes.length){                       /* هر سه رشته فعال است */
    lines.innerHTML='<div class="line"><span>همه‌ی رشته‌ها برای شما فعال است.</span></div>';
    pay.hidden=true; saveEl.hidden=true;
    return;
  }

  let seq=0;
  async function refresh(){
    boxes.forEach(b=>b.closest('.pkg').classList.toggle('on',b.checked));
    const picked=boxes.filter(b=>b.checked).map(b=>b.value);
    if(ladder)ladder.querySelectorAll('[data-n]').forEach(el=>el.classList.toggle('on',+el.dataset.n===picked.length));

    if(!picked.length){
      lines.innerHTML='<div class="line"><span>هنوز پکیجی انتخاب نشده</span></div>';
      totalEl.innerHTML='۰ <span style="font-size:13px;font-weight:400">تومان</span>';
      wasEl.hidden=true; saveEl.hidden=true;
      pay.disabled=true; pay.textContent='پرداخت و فعال‌سازی';
      return;
    }

    const my=++seq; pay.disabled=true;
    try{
      /* قیمت از خود سرور — همان عددی که در سفارش گرفته می‌شود */
      const r=await fetch('/api/buy/quote?exams='+picked.join(','),
                          {credentials:'same-origin',headers:{Accept:'application/json'}});
      if(!r.ok)throw new Error(r.status);
      const q=await r.json(); if(my!==seq)return;

      lines.innerHTML=(q.lines||[]).map(l=>`<div class="line"><span>${l.name}</span><b>${money(l.price)}</b></div>`).join('')
        +(q.launch_off>0
            ? `<div class="line off"><span>تخفیف پلکانی</span><b>− ${money(q.list_price-q.bundle_price)}</b></div>`
              +`<div class="line off"><span>${q.launch_title||'تخفیف'} (${Number(q.launch_pct).toLocaleString('fa-IR')}٪)</span><b>− ${money(q.launch_off)}</b></div>`
            : (q.discount>0?`<div class="line off"><span>تخفیف پلکانی</span><b>− ${money(q.discount)}</b></div>`
                           :`<div class="line off"><span>تخفیف پلکانی</span><b>− ۰</b></div>`));

      totalEl.innerHTML=money(q.payable)+' <span style="font-size:13px;font-weight:400">تومان</span>';
      if(q.discount>0){ wasEl.textContent=money(q.list_price)+' تومان'; wasEl.hidden=false; }
      else wasEl.hidden=true;

      /* «با یک رشته‌ی دیگر فقط چقدر بیشتر» — همان پیام نمونه‌ی طراحی */
      const n=picked.length, next=BUNDLE[n+1];
      if(next&&BUNDLE[n]){
        saveEl.textContent=`با افزودن یک رشته‌ی دیگر، فقط ${toman(next-BUNDLE[n])} بیشتر می‌پردازید.`;
        saveEl.hidden=false;
      } else saveEl.hidden=true;

      pay.disabled=!(q.payable>0);
    }catch(e){
      if(my!==seq)return;
      lines.innerHTML='<div class="line"><span>قیمت دریافت نشد. صفحه را دوباره باز کنید.</span></div>';
      saveEl.hidden=true;
    }
  }
  boxes.forEach(b=>b.addEventListener('change',refresh));
  f.addEventListener('submit',()=>{pay.disabled=true;pay.textContent='در حال انتقال به درگاه…'});
  refresh();
})();
</script>
@endsection
