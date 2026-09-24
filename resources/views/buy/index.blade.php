@extends('buy._layout')
@section('title', 'خرید و پرداخت‌ها')

@push('head')
<style>
  .ex{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:12px;padding:12px 14px;
      margin-bottom:10px;cursor:pointer}
  .ex:hover{border-color:var(--gold-line)}
  .ex input{width:18px;height:18px;accent-color:var(--gold)}
  .ex.on{border-color:var(--gold);background:var(--gold-soft)}
  .ex.owned{cursor:default;opacity:.75;background:var(--canvas)}
  .ex b{flex:1;font-weight:500}
  .tag{font-size:12px;background:var(--ok-soft);color:var(--ok);border-radius:20px;padding:1px 10px}
  .sum{border-top:1px dashed var(--line);margin:18px 0 16px;padding-top:14px;font-size:14px}
  .row{display:flex;justify-content:space-between;color:var(--ink-2);margin-bottom:4px}
  .row.total{color:var(--ink);font-weight:700;font-size:17px;margin-top:8px}
  .row.disc{color:var(--ok)}
  h2{font-size:15px;font-weight:700;margin:26px 0 10px;padding-top:20px;border-top:1px solid var(--line)}
  h2:first-of-type{margin-top:0;padding-top:0;border-top:0}
  .own{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px dashed var(--line);font-size:14px}
  .own:last-child{border-bottom:0}
  .own small{color:var(--ink-3);font-size:12.5px}
  .demo-row small{color:#8f6d1f}
  .pay{display:grid;grid-template-columns:1fr auto;gap:2px 10px;padding:10px 0;border-bottom:1px dashed var(--line);
       font-size:13.5px;text-decoration:none;color:inherit}
  .pay:last-child{border-bottom:0}
  .pay:hover b{color:var(--gold)}
  .pay .meta{grid-column:1/-1;color:var(--ink-3);font-size:12px}
  .st{font-size:12px;border-radius:20px;padding:1px 10px;align-self:center}
  .st.paid{background:var(--ok-soft);color:var(--ok)}
  .st.failed,.st.expired{background:var(--danger-soft);color:var(--danger)}
  .st.pending,.st.verifying{background:var(--gold-soft);color:#7d6320}
  .empty{color:var(--ink-3);font-size:13.5px;padding:6px 0}
</style>
@endpush

@section('body')
  <h1>خرید و پرداخت‌ها</h1>
  <p class="sub">رشته‌های فعال، خرید رشته‌ی تازه و سابقه‌ی پرداخت‌های شما.</p>

  <h2>رشته‌های شما</h2>
  @forelse ($ents as $e)
    <div class="own"><span>✓ {{ $names[$e->exam] ?? $e->exam }}</span>
      <small>@if ($e->expires_at) اعتبار تا <span data-date="{{ $e->expires_at }}">{{ substr($e->expires_at, 0, 10) }}</span>
             @else بدون تاریخ انقضا @endif</small></div>
  @empty
    <div class="empty">هنوز رشته‌ای نخریده‌اید.</div>
  @endforelse
  @if ($demo && $demo['exams'])
    @foreach ($demo['exams'] as $d)
      <div class="own demo-row"><span>{{ $names[$d] ?? $d }}</span>
        <small>نسخه‌ی نمایشی — فقط کنکور {{ $demo['year'] }}</small></div>
    @endforeach
  @endif

  <h2>خرید رشته</h2>

  @if ($fake)<div class="dev">محیط توسعه: درگاه آزمایشی روشن است و پولی جابه‌جا نمی‌شود.</div>@endif
  @if (session('buy_error'))<div class="err" role="alert">{{ session('buy_error') }}</div>@endif
  @if ($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif

  <form method="post" action="{{ route('buy.start') }}" id="f">
    @csrf
    @foreach ($names as $code => $name)
      @php $isOwned = in_array($code, $owned, true); @endphp
      <label class="ex {{ $isOwned ? 'owned' : '' }}">
        <input type="checkbox" name="exams[]" value="{{ $code }}"
               @checked(in_array($code, old('exams', $pre), true)) @disabled($isOwned)>
        <b>{{ $name }}</b>
        @if ($isOwned)<span class="tag">فعال است</span>@endif
      </label>
    @endforeach

    <div class="sum" id="sum" aria-live="polite">
      <div class="row"><span>یک رشته را انتخاب کنید.</span></div>
    </div>

    @if (count($owned) >= count($names))
      <div class="ok">هر سه رشته برای شما فعال است.</div>
    @endif
    <button class="btn" id="pay" type="submit" disabled>پرداخت</button>
    <p class="note">پرداخت از درگاه امن ایران کیش (شاپرک)
      @if ($until)· دسترسی تا {{ \Illuminate\Support\Carbon::parse($until)->format('Y/m/d') }} @endif</p>
  </form>

  <h2>پرداخت‌های من</h2>
  @forelse ($orders as $o)
    <a class="pay" href="{{ route('buy.result', $o->id) }}">
      <b>{{ implode('، ', array_map(fn ($e) => $names[$e] ?? $e, array_filter(explode(',', $o->exams)))) }}</b>
      <span class="st {{ $o->status }}">{{ ['paid' => 'موفق', 'failed' => 'ناموفق', 'expired' => 'منقضی',
                                           'pending' => 'در انتظار', 'verifying' => 'در حال تأیید'][$o->status] ?? $o->status }}</span>
      <span class="meta num">
        <span data-date="{{ $o->paid_at ?? $o->created_at }}">{{ substr($o->paid_at ?? $o->created_at, 0, 16) }}</span>
        · {{ number_format($o->payable) }} تومان
        @if ($o->rrn) · مرجع {{ $o->rrn }} @endif
        · سفارش {{ $o->id }}
      </span>
    </a>
  @empty
    <div class="empty">هنوز پرداختی نداشته‌اید.</div>
  @endforelse

<script>
/* تاریخ‌ها به تقویم شمسی، با خود مرورگر */
document.querySelectorAll('[data-date]').forEach(el=>{
  const d=new Date(el.dataset.date.replace(' ','T'));
  if(!isNaN(d)) el.textContent=d.toLocaleDateString('fa-IR',{year:'numeric',month:'long',day:'numeric'})
    +(el.closest('.pay')?' — '+d.toLocaleTimeString('fa-IR',{hour:'2-digit',minute:'2-digit'}):'');
});
</script>

<script>
(function(){
  const f=document.getElementById('f'), sum=document.getElementById('sum'), pay=document.getElementById('pay');
  const toman=n=>Number(n).toLocaleString('fa-IR')+' تومان';
  const boxes=[...f.querySelectorAll('input[type=checkbox]:not([disabled])')];
  let seq=0;

  async function refresh(){
    boxes.forEach(b=>b.closest('.ex').classList.toggle('on',b.checked));
    const picked=boxes.filter(b=>b.checked).map(b=>b.value);
    if(!picked.length){
      sum.innerHTML='<div class="row"><span>یک رشته را انتخاب کنید.</span></div>'; pay.disabled=true; return;
    }
    const my=++seq; pay.disabled=true;
    try{
      /* قیمت از خود سرور — همان عددی که در سفارش گرفته می‌شود */
      const r=await fetch('/api/buy/quote?exams='+picked.join(','),
                          {credentials:'same-origin',headers:{Accept:'application/json'}});
      if(!r.ok)throw new Error(r.status);
      const q=await r.json(); if(my!==seq)return;
      sum.innerHTML=(q.lines||[]).map(l=>`<div class="row"><span>${l.name}</span><span class="num">${toman(l.price)}</span></div>`).join('')
        +(q.discount>0?`<div class="row disc"><span>تخفیف چند رشته</span><span class="num">−${toman(q.discount)}</span></div>`:'')
        +`<div class="row total"><span>مبلغ قابل پرداخت</span><span class="num">${toman(q.payable)}</span></div>`;
      pay.disabled=!(q.payable>0);
      pay.textContent=q.payable>0?'پرداخت '+toman(q.payable):'پرداخت';
    }catch(e){
      if(my!==seq)return;
      sum.innerHTML='<div class="row"><span>قیمت دریافت نشد. صفحه را دوباره باز کنید.</span></div>';
    }
  }
  boxes.forEach(b=>b.addEventListener('change',refresh));
  f.addEventListener('submit',()=>{pay.disabled=true;pay.textContent='در حال انتقال به درگاه…'});
  refresh();
})();
</script>
@endsection
