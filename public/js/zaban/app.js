
if (typeof window.ZABAN === "undefined") {
  document.addEventListener("DOMContentLoaded", function () {
    document.body.innerHTML =
      '<div style="max-width:560px;margin:12vh auto;padding:32px;text-align:center;' +
      'font-family:Vazirmatn,system-ui,sans-serif;background:#fff;border:1px solid #e6e3dd;' +
      'border-radius:16px;line-height:2"><div style="font-size:19px;font-weight:700;' +
      'margin-bottom:10px;color:#a8842c">این ساختِ سرور است، نه نسخه‌ی نمایش</div>' +
      '<div style="font-size:14px;color:#5b6167">این فایل دیتا ندارد و منتظر ' +
      '<code>/api/content</code> است.<br><br><b>برای دیدن پلتفرم</b>، فایل ' +
      '<code>vocab-platform-v69-demo.html</code> را باز کنید.</div></div>';
  });
  window.ZABAN = { boot: function () {} };
}

ZABAN.boot(function(){
/* ===================================================================
 *  اسکریپت پروتوتایپ — بدون تغییر، جز پنج نقطه‌ی اتصال که کامنت دارند.
 *  ثابت‌های دیتا (YEARS/STRUCT/WORDS/TEXTS) حذف شده‌اند؛
 *  app-bootstrap.js آن‌ها را از /api/content می‌گیرد و روی window می‌گذارد.
 * =================================================================== */

const fa=n=>String(n).replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
const $=s=>document.querySelector(s);
const LVLC={"ساده":"#5f9c4c","متوسط":"#d99521","پیشرفته":"#c05a35"};
function structFor(y){return STRUCT[y]||STRUCT[1405]||[]}
function lvlClass(l){return l==="ساده"?"b-lvl-1":l==="متوسط"?"b-lvl-2":"b-lvl-3"}

/* در نسخه‌ی demo کلمات به شکل آرایه‌ی فشرده ذخیره می‌شدند و اینجا باز
   می‌شدند. سرور آن‌ها را از قبل باز می‌فرستد، پس فقط چیزهایی که سرور
   نمی‌فرستد اینجا ساخته می‌شود. */
WORDS.forEach(w => {
  if (!w.ipa) w.ipa = "/" + w.w + "/";
  if (!w.occ) w.occ = [];
});

/* ===== مثال‌ها — کلمه‌به‌کلمه از سرور (امنیت محتوا، قدم ۱) =====
   بانک دیگر یک‌جا فرستاده نمی‌شود؛ مثال‌های هر کلمه وقتی کاربر واقعاً نگاهش
   می‌کند از /api/words/detail می‌آید، پشت دسترسی و سقف روزانه (zaban.word_daily_cap).
     EXS[id] === undefined → هنوز نگرفته‌ایم · آرایه → گرفتیم · EX_LIMIT[id] → سقف روزانه */
const EXS={}, EX_LIMIT={}, EX_WAIT={};
let EX_LIMIT_MSG="سقف روزانه‌ی دیدن مثال‌ها پر شده است؛ فردا دوباره در دسترس است.";
function wid(w){ return w.id || (window.WID||{})[w.w] || null }
function fetchEx(ids){
  const need=[...new Set(ids.filter(id=>id&&EXS[id]===undefined&&!EX_LIMIT[id]))];
  if(!need.length)return Promise.resolve();
  const jobs=[];
  for(let i=0;i<need.length;i+=40){
    const b=need.slice(i,i+40), key=b.join(",");
    if(!EX_WAIT[key]){
      EX_WAIT[key]=ZABAN.wordDetails(b).then(r=>{
        ((r&&r.items)||[]).forEach(it=>{EXS[it.id]=it.ex||[]});
        ((r&&r.limited)||[]).forEach(id=>{EX_LIMIT[id]=1});
        if(r&&r.limit_message)EX_LIMIT_MSG=r.limit_message;
        b.forEach(id=>{if(EXS[id]===undefined&&!EX_LIMIT[id])EXS[id]=[]});   /* بی‌دسترسی = بی‌مثال */
      }).finally(()=>{delete EX_WAIT[key]});
    }
    jobs.push(EX_WAIT[key]);
  }
  return Promise.all(jobs);
}
/* مثال‌های یک کلمه؛ اگر هنوز نرسیده، می‌گیرد و onReady را صدا می‌زند.
   خروجی: آرایه، یا رشته‌ی پیام (در حال گرفتن / سقف روزانه). */
function examplesOf(w,onReady){
  const id=wid(w), ex=EXS[id];
  if(ex!==undefined)return ex;
  if(EX_LIMIT[id])return EX_LIMIT_MSG;
  fetchEx([id]).then(onReady).catch(e=>console.error(e));
  return "در حال گرفتن مثال‌ها…";
}

/* ===== معنی‌ها — کلمه‌به‌کلمه از سرور (امنیت محتوا، قدم ۳) =====
   w.fa حالا getter است: اگر معنی رسیده همان را می‌دهد، وگرنه «…» و کلمه را در صف
   می‌گذارد؛ صف در یک درخواست (حداکثر ۶۰ تایی) گرفته می‌شود و صفحه دوباره کشیده می‌شود.
   پس بیشتر کد نمایشی دست نخورد. ولی هر جا که روی «همه‌ی» کلمه‌ها w.fa را بخواند کل
   بانک را درخواست می‌کند (سقف و قفل!) — جست‌وجو (meanHit)، آزمونک، allCards و فهرست‌های
   بی‌صفحه به همین دلیل عوض شدند. هرگز w.fa را در حلقه روی WORDS نخوانید. */
const MEANS={}, MEAN_LIMIT={}, MEAN_WANT=new Set(), MEAN_INFLIGHT=new Set();
let MEAN_TIMER=null, MEAN_REDRAW=null;
let MEAN_LIMIT_MSG="سقف روزانه‌ی دیدن معنی پر شده است؛ فردا دوباره در دسترس است.";
var LIST_MEANINGS = window.LIST_MEANINGS!==false;     /* var: پیش از این خط هم خوانده می‌شود */
function meanGet(w){
  const id=wid(w); if(!id)return "";
  if(MEANS[id]!==undefined)return MEANS[id];
  if(MEAN_LIMIT[id])return "—";
  if(MEAN_INFLIGHT.has(id))return "…";           /* در راه است؛ دوباره نخواه */
  MEAN_WANT.add(id);
  if(!MEAN_TIMER)MEAN_TIMER=setTimeout(flushMeans,0);
  return "…";
}
function flushMeans(){
  MEAN_TIMER=null;
  const ids=[...MEAN_WANT].filter(id=>MEANS[id]===undefined&&!MEAN_LIMIT[id]&&!MEAN_INFLIGHT.has(id)); MEAN_WANT.clear();
  for(let i=0;i<ids.length;i+=60){
    const b=ids.slice(i,i+60);
    b.forEach(id=>MEAN_INFLIGHT.add(id));
    ZABAN.wordMeanings(b).then(r=>{
      ((r&&r.items)||[]).forEach(it=>{MEANS[it.id]=it.fa||""});
      ((r&&r.limited)||[]).forEach(id=>{MEAN_LIMIT[id]=1});
      if(r&&r.limit_message)MEAN_LIMIT_MSG=r.limit_message;
      b.forEach(id=>{if(MEANS[id]===undefined&&!MEAN_LIMIT[id])MEANS[id]=""});
      if(r&&r.limited&&r.limited.length)toast(MEAN_LIMIT_MSG);
      meaningsArrived();
    }).catch(e=>console.error(e))
      .finally(()=>b.forEach(id=>MEAN_INFLIGHT.delete(id)));   /* خطای شبکه: بعداً دوباره */
  }
}
/* بعد از رسیدن معنی‌ها، هر چه روی صفحه است دوباره کشیده شود (یک بار برای چند دسته) */
function meaningsArrived(){
  clearTimeout(MEAN_REDRAW);
  MEAN_REDRAW=setTimeout(()=>{
    try{
      refreshAll();
      const R={predict:()=>renderPredict(),crowd:()=>renderCrowd(),charts:()=>renderCharts(),
               fb:()=>{renderFeedback();renderStats()},balance:()=>renderBalance()};
      if(R[navTab])R[navTab]();
      if(curView&&curView.t==="word")renderWord(curView.w);
      const rc=$("#rCard"); if(rc&&rc.offsetParent&&session[pos])paintCard();
    }catch(e){console.error(e)}
  },30);
}
/* کپی، کلیک راست و کشیدن روی متن‌های محافظت‌شده — کلیپ‌بورد پیام می‌گیرد، نه متن */
const PROTECTED=".fa-mean,.mean-lg,.rev,.ex,.ex-exp,.lfa,.pr-fa,.rrow .m,.wline span,#rOpts button";
function inProtected(node){ const el=node&&(node.nodeType===1?node:node.parentElement); return !!(el&&el.closest(PROTECTED)) }
document.addEventListener("copy",e=>{
  const s=window.getSelection();
  if(s&&s.rangeCount&&(inProtected(s.anchorNode)||inProtected(s.focusNode))){
    e.preventDefault(); if(e.clipboardData)e.clipboardData.setData("text/plain","کپی این بخش مجاز نیست.");
  }
});
document.addEventListener("contextmenu",e=>{ if(inProtected(e.target))e.preventDefault() });
document.addEventListener("dragstart",e=>{ if(inProtected(e.target))e.preventDefault() });
document.addEventListener("click",e=>{
  if(e.target.closest("[data-dmore]")){dShown+=50;renderDeck()}
  else if(e.target.closest("[data-smore]")){sShown+=50;renderStar()}
});
WORDS.forEach(w=>Object.defineProperty(w,"fa",{configurable:true,enumerable:true,
  get(){return meanGet(this)}, set(_v){}}));
/* معنی در فهرست‌ها فقط اگر پنل اجازه داده باشد (و فقط برای ردیف‌های دیده‌شده) */
function listFa(w){ return LIST_MEANINGS ? w.fa : "" }
/* نام «repLabel» از قبل برای برچسب گزارش (از کلید) گرفته شده بود — تداخل اسم = دومی جای اولی */
function wordRepLabel(w){ return LIST_MEANINGS ? w.w+" — "+w.fa : w.w }

/* جست‌وجوی متن فارسی در معنی‌ها — در سرور (فقط شناسه برمی‌گردد). انگلیسی همان w.w. */
const MSEARCH={}; let MSEARCH_TIMER=null, MSEARCH_Q="";
function meanHit(w,q){
  q=String(q||"").trim();
  if(q.length<2||!/[\u0600-\u06FF]/.test(q))return false;
  const s=MSEARCH[q];
  if(s===undefined){
    if(MSEARCH_Q!==q){
      MSEARCH_Q=q; clearTimeout(MSEARCH_TIMER);
      MSEARCH_TIMER=setTimeout(()=>{
        if(MSEARCH[q]!==undefined)return;
        MSEARCH[q]="wait";
        ZABAN.wordSearch(q).then(r=>{MSEARCH[q]=new Set((r&&r.ids)||[]);meaningsArrived()})
          .catch(e=>{console.error(e);delete MSEARCH[q]});
      },350);
    }
    return false;
  }
  return s!=="wait" && s.has(wid(w));
}
WORDS.forEach(w=>{w.years=[...new Set(w.occ.map(o=>o[0]))].sort((a,b)=>a-b);w.freq=w.years.length;w.last=Math.max(...w.years);w.first=Math.min(...w.years);});

const deck=new Set();
const star=new Set();
const deckQ=new Set();
const starQ=new Set();

/* دک و منتخب در حدود سی جای مختلف دستکاری می‌شوند. به‌جای اینکه در هر
   کدام یک فراخوانی سرور اضافه کنیم — که یکی‌اش حتماً جا می‌افتد — خودِ
   add و delete را یک بار پوشش می‌دهیم. از این به بعد هر تغییری در این
   چهار مجموعه، هر جای کد که باشد، خودکار روی سرور هم ثبت می‌شود. */
let __setsQuiet=false;
function __quietSets(f){__setsQuiet=true;try{f()}finally{__setsQuiet=false}}
(function syncSets(){
  /* کلید سؤال در صفحه «سال|رشته|شماره» است ولی سرور id می‌خواهد. */
  const qidOf=k=>{const q=(window.QBYKEY||{})[k];return q?q.id:null};
  function wrap(set,send){
    const add=set.add.bind(set), del=set.delete.bind(set);
    set.add=function(k){const had=set.has(k);const r=add(k);
      if(!__setsQuiet&&!had)try{send(k,true)}catch(e){console.error(e)}
      return r};
    set.delete=function(k){const had=set.has(k);const r=del(k);
      if(!__setsQuiet&&had)try{send(k,false)}catch(e){console.error(e)}
      return r};
  }
  wrap(deck,  (k,on)=>ZABAN.deck(k,on));
  wrap(star,  (k,on)=>ZABAN.star(k,on));
  wrap(deckQ, (k,on)=>{const id=qidOf(k); if(id)ZABAN.deckQ(id,on)});
  wrap(starQ, (k,on)=>{const id=qidOf(k); if(id)ZABAN.starQ(id,on)});
})();

/* کلید معتبر سؤال: «۱۴۰۴|مهندسی کامپیوتر|۶» با رقم لاتین. */
function isQKey(k){return typeof k==="string"&&/^\d{4}\|[^|]+\|\d+$/.test(k)}

/* دک و منتخب‌ها را سرور در SERVER_DECK و SERVER_STAR می‌گذارد، ولی
   صفحه با این چهار مجموعه کار می‌کند. بدون این پل، هر بار که صفحه باز
   شود دک خالی به نظر می‌رسد در حالی که روی سرور هست.
   داخل __quietSets است تا همان چیزی که از سرور آمده دوباره به سرور نرود. */
__quietSets(function loadServerSets(){
  const D = window.SERVER_DECK || [];
  const S = window.SERVER_STAR || [];
  /* سؤال با کلید «سال|رشته|شماره» که سرور در k می‌فرستد. قبلاً خود id عددی
     اینجا می‌نشست و هرجا صفحه روی کلید split می‌زد کل پلتفرم می‌خوابید. */
  D.forEach(x => {
    if (x.t === 'w') { const w = WBYID[x.id]; if (w) deck.add(w.w); }
    else if (isQKey(x.k)) deckQ.add(x.k);
  });
  S.forEach(x => {
    if (x.t === 'w') { const w = WBYID[x.id]; if (w) star.add(w.w); }
    else if (isQKey(x.k)) starQ.add(x.k);
  });
});

const readMark={};   // {'1405|مهندسی کامپیوتر': lastTestNumber}
const readDone={};   // {'1405|مهندسی کامپیوتر|word': true}
const miss={};
const F={q:"",lvl:new Set(),sec:new Set(),year:"",exam:"",tests:new Set(),sort:"freq",fromY:YEARS[0],toY:YEARS[YEARS.length-1]};
let shown=50;

/* ---------------- فیلتر و لیست ---------------- */
function matchOcc(o){
  return (!F.year||o[0]==+F.year)&&(!F.exam||o[1]===F.exam)&&(!F.sec.size||F.sec.has(o[2]))&&(!F.tests.size||F.tests.has(o[3]?("q"+o[3]):("t"+o[2]+"|"+(o[4]||0))))&&o[0]>=F.fromY&&o[0]<=F.toY;
}
function statTag(w){
  const m=w.occ.filter(matchOcc);
  return {y:new Set(m.map(o=>o[0])).size,t:m.length};
}
function filtered(){
  let r=WORDS.filter(w=>{
    if(F.lvl.size&&!F.lvl.has(w.lvl))return false;
    if(F.q){const q=F.q.trim();if(!w.w.includes(q)&&!meanHit(w,q))return false;}
    if(!w.occ.some(matchOcc))return false;
    return true;
  });
  r.forEach(w=>{const st=statTag(w);w._y=st.y;w._t=st.t;});
  const S={freq:(a,b)=>b._y-a._y||b._t-a._t||a.w.localeCompare(b.w),
           freq_asc:(a,b)=>a._y-b._y||a._t-b._t||a.w.localeCompare(b.w),
           recent:(a,b)=>b.last-a.last||b._y-a._y,
           oldest:(a,b)=>a.first-b.first||b._y-a._y,
           az:(a,b)=>a.w.localeCompare(b.w),
           za:(a,b)=>b.w.localeCompare(a.w)};
  r.sort(S[F.sort]||S.freq);
  return r;
}

/* نوار زمانی: ۲۵ خانه، فاصله هر ۵ سال، برچسب ابتدا و انتها */
function timeline(w){
  const cells=YEARS.map(y=>{
    const h=w.years.includes(y);
    const n=h?w.occ.filter(o=>o[0]===y).length:0;
    const out=(y<F.fromY||y>F.toY)?" out":"";
    return `<i class="${h?"hit":""}${out}" data-tip="${fa(y)}${h?` — ${fa(n)} بار`:" — نیامده"}${out?" (بیرون از بازه)":""}"></i>`;
  }).join("");
  const labels=YEARS.map(y=>`<u class="${w.years.includes(y)?"hit":""}">${fa(y)}</u>`).join("");
  /* --n = تعداد سال‌ها: خانه‌ها ردیف اول، برچسب‌ها ردیف دوم — فقط اگر ستون‌ها دقیقاً همین تعداد باشند */
  return `<div class="tl" style="--n:${YEARS.length}">${cells}${labels}</div>`;
}

function actions(w){
  const d=deck.has(w.w),st=star.has(w.w);
  return `<div class="acts">
    <button class="star ${st?"on":""}" data-star="${w.w}" data-tip="${st?"حذف از منتخب‌ها":"افزودن به منتخب‌ها"}">${st?"★":"☆"}</button>
    <button class="add ${d?"in":""}" data-toggle="${w.w}" data-tip="${d?"حذف از دک مرور":"افزودن به دک مرور"}">${d?"✓":"+"}</button>
    ${nBtn("w:"+w.w,"یادداشت — "+w.w,"star")}
    ${rBtn("w:"+w.w, wordRepLabel(w))}
  </div>`;
}
function render(){
  renderActive();
  const r=filtered();
  $("#n").textContent=fa(r.length);
  const page=r.slice(0,shown);
  $("#more").hidden=r.length<=shown;
  $("#list").innerHTML=page.length?page.map(w=>`
    <div class="row" data-i="${WORDS.indexOf(w)}">
      <div class="main">
        <div class="head">
          <span class="word en">${w.w}</span>
          <span class="pos en">${w.pos}</span>
          ${rangeChips(w)}
          <span class="badge ${lvlClass(w.lvl)}">${w.lvl}</span>
        </div>
        <div class="fa-mean">${listFa(w)}</div>
        ${timeline(w)}
      </div>
      ${actions(w)}
    </div>`).join(""):'<div class="empty">با این فیلترها کلمه‌ای پیدا نشد.</div>';
  updDeckCount();
  if(!$("#tab-tests").hidden)renderTestTab();
}

function renderActive(){
  const box=$("#activeFilters"),it=[];
  if(F.year)it.push(["year",fa(F.year)]);
  if(F.exam)it.push(["exam",F.exam]);
  F.tests.forEach(k=>it.push(["tst:"+k, k[0]==="q"?("تست "+fa(k.slice(1))):("متن "+(k.slice(1).split("|")[0]==="پسیج"?"پسیج "+fa(k.slice(1).split("|")[1]):"کلوز تست"))]));
  F.sec.forEach(s=>it.push(["sec:"+s,s]));
  F.lvl.forEach(l=>it.push(["lvl:"+l,"سطح "+l]));
  if(F.fromY!==YEARS[0]||F.toY!==YEARS[YEARS.length-1])it.push(["range",`${fa(F.fromY)} تا ${fa(F.toY)}`]);
  ["year","exam"].forEach(k=>$("#"+k).classList.toggle("on",!!F[k]));
  box.hidden=!it.length;
  if(it.length)box.innerHTML=it.map(([k,t])=>`<span class="fchip">${t}<button data-clear="${k}">×</button></span>`).join("")+'<button class="fclear" data-clear="all">پاک کردن همه</button>';
}

function renderTests(){
  const b=$("#tblock");
  if(!F.year||!F.exam){b.hidden=true;F.tests.clear();return;}
  b.hidden=false;
  const counts={};
  WORDS.forEach(w=>w.occ.forEach(o=>{if(o[0]==+F.year&&o[1]===F.exam)counts[o[3]]=(counts[o[3]]||0)+1;}));
  const groups=structFor(+F.year).filter(g=>!F.sec.size||F.sec.has(g[0]));
  $("#tlabel").textContent=`شماره تست — ${fa(F.year)} ${F.exam}`;
  const tcount={};
  WORDS.forEach(w=>w.occ.forEach(o=>{
    if(o[0]==+F.year&&o[1]===F.exam&&o[3]===0){const k=o[2]+"|"+(o[4]||0);tcount[k]=(tcount[k]||0)+1}
  }));
  $("#tests").innerHTML=groups.map(g=>{
    let chips="";
    if(g[0]!=="وکب"){
      const n=tcount[g[0]+"|"+(g[2]||0)]||0;
      const on=F.tests.has("t"+g[0]+"|"+(g[2]||0));
      chips+=`<button class="tchip txt ${on?"on":""} ${n?"":"nodata"}" data-textkey="${g[0]}|${g[2]||0}" ${n?"":"disabled"}><span>متن</span>${n?`<span class="c">${fa(n)}</span>`:""}</button>`;
    }
    for(let q=g[3];q<=g[4];q++){
      const n=counts[q]||0;
      chips+=`<button class="tchip ${F.tests.has("q"+q)?"on":""} ${n?"":"nodata"}" data-test="${q}" ${n?"":"disabled"}><span>${fa(q)}</span>${n?`<span class="c">${fa(n)}</span>`:""}</button>`;
    }
    return `<div class="tgroup" data-s="${g[0]}"><div class="gh"><b>${g[1]}</b><span>${fa(g[3])}–${fa(g[4])}</span></div><div class="tchips">${chips}</div></div>`;
  }).join("");
}

/* ---------------- رویدادهای تب کلمات ---------------- */
$("#q").addEventListener("input",e=>{F.q=e.target.value;shown=50;render()});
$("#sort").addEventListener("change",e=>{F.sort=e.target.value;render()});
$("#more").addEventListener("click",()=>{shown+=50;render()});
$("#levels").addEventListener("click",e=>{const b=e.target.closest("[data-lvl]");if(!b)return;
  const l=b.dataset.lvl;F.lvl.has(l)?F.lvl.delete(l):F.lvl.add(l);b.classList.toggle("on");shown=50;render()});
$("#sections").addEventListener("click",e=>{const b=e.target.closest("[data-sec]");if(!b)return;
  const s=b.dataset.sec;F.sec.has(s)?F.sec.delete(s):F.sec.add(s);b.classList.toggle("on");shown=50;renderTests();render()});
["year","exam"].forEach(k=>$("#"+k).addEventListener("change",e=>{F[k]=e.target.value;F.tests.clear();shown=50;renderTests();render()}));
$("#tests").addEventListener("click",e=>{
  const tx=e.target.closest("[data-textkey]");
  if(tx){
    const k=tx.dataset.textkey, sec=k.split("|")[0];
    const key="t"+k;
    F.tests.has(key)?F.tests.delete(key):F.tests.add(key);
    shown=50;renderTests();render();return;
  }
  const b=e.target.closest("[data-test]");if(!b)return;
  const key="q"+b.dataset.test;
  F.tests.has(key)?F.tests.delete(key):F.tests.add(key);
  shown=50;renderTests();render();});
function setRange(a,b){F.fromY=a;F.toY=b;$("#fromY").value=a;$("#toY").value=b;
  $("#fromY").classList.toggle("on",a!==YEARS[0]);$("#toY").classList.toggle("on",b!==YEARS[YEARS.length-1]);}
["fromY","toY"].forEach(id=>$("#"+id).addEventListener("change",e=>{
  let a=+$("#fromY").value,b=+$("#toY").value;if(a>b){[a,b]=[b,a]}
  document.querySelectorAll("[data-rng]").forEach(x=>x.classList.remove("on"));
  setRange(a,b);shown=50;renderTests();render();}));
document.querySelectorAll("[data-rng]").forEach(b=>b.addEventListener("click",()=>{
  const n=+b.dataset.rng;const last=YEARS[YEARS.length-1];
  document.querySelectorAll("[data-rng]").forEach(x=>x.classList.remove("on"));b.classList.add("on");
  setRange(n?last-n+1:YEARS[0],last);shown=50;renderTests();render();}));
$("#activeFilters").addEventListener("click",e=>{
  const b=e.target.closest("[data-clear]");if(!b)return;const k=b.dataset.clear;
  if(k==="all"){F.year=F.exam="";F.tests.clear();F.lvl.clear();F.sec.clear();setRange(YEARS[0],YEARS[YEARS.length-1]);
    $("#year").value="";$("#exam").value="";
    document.querySelectorAll("#levels button,#sections button,[data-pk],[data-rng]").forEach(x=>x.classList.remove("on"));}
  else if(k==="range"){setRange(YEARS[0],YEARS[YEARS.length-1]);document.querySelectorAll("[data-rng]").forEach(x=>x.classList.remove("on"))}
  else if(k.startsWith("lvl:")){F.lvl.delete(k.slice(4));document.querySelector(`[data-lvl="${k.slice(4)}"]`).classList.remove("on")}
  else if(k.startsWith("sec:")){F.sec.delete(k.slice(4));document.querySelector(`[data-sec="${k.slice(4)}"]`).classList.remove("on")}
  else if(k.indexOf("tst:")===0){F.tests.delete(k.slice(4))}
  else{F[k]="";$("#"+k).value="";F.tests.clear()}
  shown=50;renderTests();render();
});
$("#addResults").addEventListener("click",()=>{
  const r=filtered();
  if(!r.length)return;
  askConfirm(`${fa(r.length)} کلمه به دک مرور شما اضافه شود؟`,()=>{r.forEach(w=>deck.add(w.w));render();toast("اضافه شد.")});
});
function bindRow(sel,extra){
  $(sel).addEventListener("click",e=>{
    const t=e.target.closest("[data-toggle]");
    if(t){const k=t.dataset.toggle;deck.has(k)?deck.delete(k):deck.add(k);refreshAll();e.stopPropagation();return}
    const st=e.target.closest("[data-star]");
    if(st){const k=st.dataset.star;star.has(k)?star.delete(k):star.add(k);refreshAll();e.stopPropagation();return}
    if(extra&&extra(e))return;
    const row=e.target.closest(".row");
    if(row&&row.dataset.i!==undefined&&WORDS[+row.dataset.i])openDetail(WORDS[+row.dataset.i]);
  });
}
function refreshAll(){render();renderStar();updModeHint();if(!$("#tab-tests").hidden)renderTestTab();if(!$("#tab-deck").hidden)renderDeck();if(!$("#tab-read").hidden)renderRead()}
bindRow("#list");
bindRow("#starList");

/* ---------------- صفحه‌ی کلمه ---------------- */
function say(t){try{const u=new SpeechSynthesisUtterance(t);u.lang="en-US";u.rate=.85;speechSynthesis.speak(u)}catch(e){}}
function stem(s){return s.replace(/(ations?|ment|ness|ously|ity|ing|ed|ly|s)$/,"").slice(0,6)}
function kin(w){return WORDS.filter(x=>x!==w&&stem(x.w)===stem(w.w)).slice(0,8)}

const EXAM_SHORT={"مهندسی کامپیوتر":"کامپیوتر","آی‌تی":"آی‌تی","علوم کامپیوتر":"علوم"};
let occOpen=false;
let currentWord=null, exOpen=false;
let navStack=[], curView=null;
function viewLabel(v){
  if(v.t==="review")return "کارت مرور";
  return v.t==="word" ? ("کلمه "+v.w.w)
    : v.t==="text" ? ((v.sec==="پسیج"?"متن پسیج "+fa(v.p):"متن کلوز تست")+" — "+fa(v.y))
    : ("سؤال "+fa(v.q)+" کنکور "+fa(v.y));
}
function showView(v,push){
  if(push&&curView)navStack.push(curView);
  if(push)exOpen=false;
  curView=v;
  if(v.t==="review"){$("#detail").classList.remove("open");$("#qview").classList.remove("open");return}
  if(v.t==="word")renderWord(v.w);
  else if(v.t==="text")renderText(v.y,v.e,v.sec,v.p);
  else renderQuestion(v.y,v.e,v.q);
  syncUrl(!!push);                                 /* /zaban/word/… · /zaban/test/… */
}
const ZBASE="/zaban";
const FA2CODE={"مهندسی کامپیوتر":"ce","آی‌تی":"it","علوم کامپیوتر":"cs"};
const CODE2FA={ce:"مهندسی کامپیوتر",it:"آی‌تی",cs:"علوم کامپیوتر"};
let ROUTING=false;
function panelOpen(){ return $("#detail").classList.contains("open")||$("#qview").classList.contains("open") }
/* آدرسِ همین لحظه‌ی صفحه */
function urlFor(){
  const v=curView;
  if(v&&v.t!=="review"&&panelOpen()){
    const c=FA2CODE[v.e]||v.e;
    if(v.t==="word") return v.w.w.includes("/") ? ZBASE+"/word?w="+encodeURIComponent(v.w.w)
                                               : ZBASE+"/word/"+encodeURIComponent(v.w.w);
    if(v.t==="q")    return ZBASE+"/test/"+v.y+"/"+c+"/"+v.q;
    if(v.t==="text") return ZBASE+"/text/"+v.y+"/"+c+"/"+(v.sec==="پسیج"?"passage":"cloze")+"/"+(v.p||0);
  }
  if(isOpen("#review")) return ZBASE+"/review";                                   /* جلسه‌ی مرور */
  if(isOpen("#repM")&&navTab==="reports"&&typeof _rk!=="undefined"&&_rk)          /* گفتگوی یک گزارش */
    return ZBASE+"/reports/"+encodeURIComponent(_rk);
  if(isOpen("#cbM")&&CB.key) return ZBASE+"/"+navTab+"/cards/"+encodeURIComponent(CB.key);
  if(navTab==="exam"&&typeof EX!=="undefined"){                                    /* مراحل آزمون */
    if(EX.stage==="run") return ZBASE+"/exam/run";
    if(EX.stage==="res"&&EX.attemptId) return ZBASE+"/exam/result/"+EX.attemptId;
  }
  return navTab==="rank" ? ZBASE : ZBASE+"/"+navTab;
}
function isOpen(sel){ const el=$(sel); return !!(el&&el.classList.contains("open")) }
/* پنجره‌های تمام‌صفحه‌ای که آدرس دارند؛ رفتن به آدرس دیگر آن‌ها را می‌بندد */
function closeOverlays(){ ["#review","#cbM","#repM"].forEach(sel=>{const el=$(sel); if(el)el.classList.remove("open")}) }
/* push: ناوبری کاربر (یک قدم در تاریخچه‌ی مرورگر) · replace: هم‌سان‌سازی بی‌قدم */
function syncUrl(push){
  if(ROUTING)return;
  const u=urlFor();
  if(location.pathname+location.search===u&&!location.hash)return;
  history[push?"pushState":"replaceState"]({z:1},"",u);
}
/* آدرس ← صفحه (بار اول، و دکمه‌های بازگشت/جلوی مرورگر) */
function applyRoute(first){
  ROUTING=true; let waiting=false;           /* کارنامه منتظر تاریخچه‌ی سرور است: آدرس دست نخورد */
  try{
    let path=location.pathname.replace(/\/+$/,"");
    if(location.hash.length>1) path=ZBASE+"/"+location.hash.slice(1);     /* لینک قدیمی #tab */
    const seg=path.startsWith(ZBASE)?path.slice(ZBASE.length).split("/").filter(Boolean).map(decodeURIComponent):[];
    const tabOk=t=>!!document.getElementById("tab-"+t);
    const under=t=>{ if(first||!tabOk(navTab))openTab(t) };             /* تبِ پشت صفحه‌ی جزئیات */
    if(seg[0]==="word"){
      const name=seg.slice(1).join("/")||new URLSearchParams(location.search).get("w")||"";
      const w=WORDS.find(x=>x.w===name);
      closeModals(); under("words");
      if(w)showView({t:"word",w},true); else if(!first)openTab("words");
    }else if(seg[0]==="test"&&seg.length>=4){
      closeModals(); under("tests");
      openQuestion(+seg[1],CODE2FA[seg[2]]||seg[2],+seg[3]);
    }else if(seg[0]==="text"&&seg.length>=5){
      closeModals(); under("tests");
      openText(+seg[1],CODE2FA[seg[2]]||seg[2],seg[3]==="passage"?"پسیج":"کلوز تست",+seg[4]);
    }else if(seg[0]==="exam"){
      closeModals(); closeOverlays(); openTab("exam");
      if(seg[1]==="run"){
        if(EX.stage!=="run"&&LS.get("zban_exam"))examResume();        /* آزمون باز همین مرورگر */
      }else if(seg[1]==="result"&&seg[2]){
        const id=seg[2]; waiting=true;
        (window.HIST_READY||Promise.resolve()).then(()=>{            /* تاریخچه از سرور می‌آید */
          const h=HIST.find(x=>String(x.id)===String(id));
          if(h)histOpen(h.ts); else syncUrl(false);
        });
      }else if(EX.stage==="res"){
        renderExamHome();                                             /* /zaban/exam = صفحه‌ی شروع */
      }
    }else if(seg[0]==="reports"&&seg[1]){
      closeModals(); closeOverlays(); openTab("reports");
      const k=seg.slice(1).join("/");
      if(REPORTS[k])openReport(k,repLabel(k));
    }else if(seg[0]==="review"){
      closeModals(); closeOverlays();
      if(first||!tabOk(navTab))openTab("rank");
      const b=$("#startReview"); if(b)b.click();                      /* همان «مرور امروز» */
    }else if(seg[1]==="cards"&&seg[2]&&tabOk(seg[0])){
      closeModals(); closeOverlays(); openTab(seg[0]);                /* رندر تب، CB_SETS را پر می‌کند */
      const set=CB_SETS[seg[2]]; if(set)openCards(set.title,set.cards,seg[2]);
    }else{
      closeModals(); closeOverlays();
      openTab(seg[0]&&tabOk(seg[0])?seg[0]:"rank");
    }
  }finally{ROUTING=false}
  if(!waiting)syncUrl(false);                      /* هم‌سان‌سازی (مثلاً #words ← /zaban/words) */
}

function backBtn(){
  if(!navStack.length)return "";
  return `<button class="backbtn" data-navback="1">→ بازگشت به ${viewLabel(navStack[navStack.length-1])}</button>`;
}
function goBack(){const p=navStack.pop();if(p)showView(p,false)}
function openDetail(w){showView({t:"word",w},true)}
function openText(y,e,sec,p){showView({t:"text",y,e,sec,p},true)}
function openQuestion(y,e,q){showView({t:"q",y,e,q},true)}
function closeModals(){navStack=[];curView=null;$("#detail").classList.remove("open");$("#qview").classList.remove("open");syncUrl(true)}
document.addEventListener("click",e=>{if(e.target.closest("[data-navback]"))goBack()});

function occTimeline(w){
  const EXAMS_ORDER=["مهندسی کامپیوتر","آی‌تی","علوم کامپیوتر"];
  const byYear={};
  w.occ.forEach(o=>(byYear[o[0]]=byYear[o[0]]||[]).push(o));
  const years=Object.keys(byYear).map(Number).sort((a,b)=>b-a);
  const show=occOpen?years:years.slice(0,5);
  const html=show.map(y=>{
    const cols=EXAMS_ORDER.map(ex=>{
      const list=byYear[y].filter(o=>o[1]===ex)
        .sort((a,b)=>(a[3]||0)-(b[3]||0));
      return `<div class="oc">
        <h5 data-tip="${list.length?`این کلمه در ${fa(list.length)} جای مختلف کنکور ${fa(y)} ${ex} آمده است`:`این کلمه در کنکور ${fa(y)} ${ex} نیامده است`}">
          <span>${EXAM_SHORT[ex]}</span>${list.length?`<i>${fa(list.length)}</i>`:""}</h5>
        <div class="ocl">${list.length?list.map(o=>`
          <button class="ochip" data-q="${o[0]}|${o[1]}|${o[3]||0}|${o[2]}|${o[4]||0}">
            <span class="sc sc-${o[2]==="وکب"?"v":o[2]==="کلوز تست"?"c":"p"}">${o[2]==="پسیج"?"پسیج "+fa(o[4]||1):o[2]==="کلوز تست"?"کلوز":"وکب"}</span>
            <span class="qq">${o[3]?fa(o[3]):"متن"}</span>
          </button>`).join(""):'<span class="ocnone">—</span>'}</div>
      </div>`;
    }).join("");
    return `<div class="oy">
      <div class="oy-y">${fa(y)}<span>${fa(byYear[y].length)} مورد</span></div>
      <div class="oy-cols">${cols}</div></div>`;
  }).join("");
  const more=years.length>5?`<button class="ghost" style="width:100%;margin-top:11px" data-occmore="1">${occOpen?"نمایش کمتر":"نمایش همه‌ی "+fa(years.length)+" سال"}</button>`:"";
  return html+more;
}
function renderWord(w){
  currentWord=w;
  const k=kin(w);
  $("#detailBody").innerHTML=`
   ${backBtn()}
   <header>
     <div style="display:flex;align-items:center;gap:9px;margin-bottom:7px;flex-wrap:wrap">
       <span class="en" style="font-size:26px;font-weight:700">${w.w}</span>
       <button class="speak" data-say="${w.w}" aria-label="تلفظ">🔊</button>
     </div>
     <div class="kin" style="gap:6px">
       <span class="badge b-once en">${w.pos}</span>
       <span class="badge ${lvlClass(w.lvl)}">${w.lvl}</span>
       ${wChips([...new Set(w.occ.map(o=>o[0]))].sort((a,b)=>a-b), YEARS.length, w.occ.length)}
     </div>
     <div class="row-f" style="margin-top:12px">
       <button class="deckbtn" style="flex:1" data-toggle="${w.w}">${deck.has(w.w)?"حذف از دک مرور":"افزودن به دک مرور"}</button>
       <button class="ghost" data-star="${w.w}">${star.has(w.w)?"★ حذف از منتخب":"☆ افزودن به منتخب"}</button>
       <button class="ghost" data-note="w:${w.w}" data-nt="یادداشت — ${w.w}">${nHas("w:"+w.w)?"✎ ویرایش یادداشت":"✎ یادداشت"}</button>
     </div>
     ${nBox("w:"+w.w)}
   </header>
   <section><div class="label">معنی</div><div class="mean-lg" style="font-size:18px;font-weight:500">${w.fa}</div></section>
   ${w.forms?`<section><div class="label">فرم‌های فعل</div><div class="forms en">
     <div><div class="k">base</div><div class="v">${w.forms[0]}</div></div>
     <div><div class="k">past</div><div class="v">${w.forms[1]}</div></div>
     <div><div class="k">participle</div><div class="v">${w.forms[2]}</div></div></div></section>`:""}
   ${(()=>{ const ex=examplesOf(w,()=>{if(currentWord===w)renderWord(w)});
      if(typeof ex==="string")return `<section><div class="label">مثال</div><div class="capt">${ex}</div></section>`;
      if(!ex.length)return "";
      return `<section><div class="label">مثال — ${fa(ex.length)} نمونه</div>
     ${(exOpen?ex:ex.slice(0,2)).map(e=>`<div class="ex"><div class="s en">${e[0]}</div><div class="t">${e[1]}</div></div>`).join("")}
     ${ex.length>2?`<button class="ghost" style="width:100%;margin-top:10px" data-exmore="1">${exOpen?"نمایش کمتر":"نمایش همه‌ی "+fa(ex.length)+" مثال"}</button>`:""}</section>`;
   })()}
   ${k.length?`<section><div class="label">هم‌خانواده‌ها</div><div class="kin">${k.map(x=>`<span class="en">${x.w}</span>`).join("")}</div></section>`:""}
   <section><div class="label">کجا در کنکور آمده است — ${fa(w.freq)} سال، ${fa(w.occ.length)} مورد</div>
     ${occTimeline(w)}
   </section>
`;
  $("#qview").classList.remove("open");$("#detail").classList.add("open");
}
$("#detailBody").addEventListener("click",e=>{
  const s=e.target.closest("[data-say]");if(s){say(s.dataset.say);return}
  const om=e.target.closest("[data-occmore]");
  if(om){occOpen=!occOpen;renderWord(currentWord);return}
  const xm=e.target.closest("[data-exmore]");
  if(xm){exOpen=!exOpen;renderWord(currentWord);return}
  const g=e.target.closest("[data-q]");
  if(g){const p=g.dataset.q.split("|");
    if(+p[2])openQuestion(+p[0],p[1],+p[2]); else openText(+p[0],p[1],p[3],+p[4]);
    return}
  const t2=e.target.closest("[data-toggle]");
  if(t2){const k=t2.dataset.toggle;deck.has(k)?deck.delete(k):deck.add(k);refreshAll();openDetail(WORDS.find(w=>w.w===k));return}
  const st=e.target.closest("[data-star]");
  if(st){const k=st.dataset.star;star.has(k)?star.delete(k):star.add(k);refreshAll();openDetail(WORDS.find(w=>w.w===k));return}
});
$("#closeDetail").addEventListener("click",closeModals);
$("#detail").addEventListener("click",e=>{if(e.target.id==="detail")closeModals()});   /* آدرس هم برمی‌گردد */

/* ---------------- تب‌ها ---------------- */
/* ===== ساختار ناوبری =====
   ترتیب اینجا تعریف می‌شود، نه در HTML — پس جابه‌جا کردن یک تب
   یعنی جابه‌جا کردن یک سطر، نه دستکاری مارک‌آپ. */
const NAV=[
  {id:"dash", name:"داشبورد", icon:"◆", tabs:[
    {t:"rank",    n:"داشبورد و رتبه‌بندی"},
  ]},
  {id:"study", name:"مطالعه", icon:"▤", tabs:[
    {t:"words",   n:"کلمات"},
    {t:"tests",   n:"تست‌های زبان"},
    {t:"read",    n:"مطالعه‌ی ترتیبی"},
  ]},
  {id:"exam", name:"آزمون", icon:"✎", tabs:[
    {t:"exam",    n:"آزمون آزمایشی"},
    {t:"charts",  n:"تحلیل آزمون‌ها"},
    {t:"predict", n:"پیش‌بینی کنکور"},
  ]},
  {id:"mine", name:"مرور من", icon:"◉", tabs:[
    {t:"deck",    n:"دک من"},
    {t:"star",    n:"منتخب من"},
  ]},
  {id:"stats", name:"آمار", icon:"◫", tabs:[
    {t:"fb",      n:"فیدبک و تسلط"},
    {t:"crowd",   n:"آمار جمعی"},
    {t:"balance", n:"تعادل و پوشش"},
  ]},
];

let navGroup="dash", navTab="rank";
const navLast={};                       /* آخرین تب هر بخش، تا برگشتن طبیعی باشد */

/* داشبورد در ردیف ناوبری نیست — دکمه‌اش در نوار بالای صفحه است. */
const NAV_ROW = NAV.filter(g=>g.id!=="dash");

function dueCount(){
  return allCards().filter(c=>{
    const sc=sched[c.key]; return sc && (sc.due||0)<=dayNow && c.bucket!=="new";
  }).length;
}

function renderNav(){
  const bar=$("#navBar");
  if(bar){
    const due=dueCount();
    bar.innerHTML = NAV_ROW.map(g=>`<span class="ngrp ${navGroup===g.id?"on":""}">
        <i class="gl">${g.name}${(g.id==="mine"&&due)?`<span class="nb">${fa(due)}</span>`:""}</i>
        ${g.tabs.map(x=>`<button data-tab="${x.t}" class="${navTab===x.t?"on":""}">${x.n}</button>`).join("")}
      </span>`).join('<i class="ndiv"></i>');
    /* تب فعال باید دیده شود، حتی اگر ردیف در عرض کم اسکرول شده باشد */
    const on=bar.querySelector("button.on");
    if(on&&bar.scrollWidth>bar.clientWidth)on.scrollIntoView({block:"nearest",inline:"center"});
  }
  const db=$("#dashBtn");
  if(db)db.classList.toggle("on",navTab==="rank");
}

/* پنل فیلتر مشترک «کلمات» و «تست‌های زبان» — هر کدام باز است، پنل همان‌جاست.
   وضعیت فیلتر یکی است (F)، ولی حالا در هر دو تب دیده و عوض می‌شود. قبلاً فقط
   در «کلمات» بود و بی‌صدا روی «تست‌های زبان» هم اثر می‌گذاشت (testsHost خالی بود). */
function placeFilters(t){
  const p=$("#filterPanel"); if(!p)return;
  const host = t==="tests" ? $("#testsHost") : t==="words" ? $("#filterHost") : null;
  if(host&&p.parentNode!==host)host.appendChild(p);
  const so=$("#sort"); if(so)so.hidden=(t==="tests");   /* مرتب‌سازی کلمات؛ تب تست‌ها مرتب‌سازی خودش را دارد */
}
function openTab(t){
  /* صفحه‌هایی مثل گزارش‌ها و پروفایل عضو هیچ بخشی نیستند؛
     در آن حالت navGroup تهی می‌ماند و هیچ خوشه‌ای روشن نمی‌شود. */
  const g=NAV.find(x=>x.tabs.some(y=>y.t===t));
  navTab=t; navGroup=g?g.id:null; if(g)navLast[g.id]=t;
  document.querySelectorAll('[id^="tab-"]').forEach(x=>x.hidden=(x.id!=="tab-"+t));
  renderNav();
  /* آدرس را با تب همگام می‌کند تا رفرش، دکمه‌ی بازگشت، و اشتراک لینک کار کند (router). */
  syncUrl(true);

  placeFilters(t);
  if(t==="words")render();
  if(t==="tests"){
    renderTestTab();
    if(window.QUESTIONS&&!QUESTIONS.length&&ZABAN.questions)
      ZABAN.questions().then(()=>{if(navTab==="tests")renderTestTab()})
                       .catch(e=>console.error(e));
  }
  if(t==="deck")renderDeck();
  if(t==="star")renderStar();
  if(t==="read")renderRead();
  /* سؤال‌ها تنبل بارگذاری می‌شوند و تا وقتی نیامده‌اند QIDX خالی است،
     پس همه‌ی سال‌ها «بدون داده» نشان داده می‌شوند. اینجا ماشه را
     می‌کشیم و بعد از رسیدنشان صفحه را دوباره می‌کشیم. */
  if(t==="exam"&&!EX.stage){
    renderExamHome();
    if(window.QUESTIONS&&!QUESTIONS.length&&ZABAN.questions)
      ZABAN.questions().then(()=>{if(navTab==="exam"&&!EX.stage)renderExamHome()})
                       .catch(e=>console.error(e));
  }
  if(t==="predict")renderPredict();
  if(t==="rank")renderBoard();
  if(t==="charts")renderCharts();
  if(t==="fb"){renderFeedback();renderStats()}
  if(t==="reports")renderMyReports();
  if(t==="announce")renderAnnounce();
  if(t==="profile")renderProfile();
  if(t==="crowd")renderCrowd();
  if(t==="balance")renderBalance();
  window.scrollTo({top:0,behavior:"smooth"});
}

$("#navBar").addEventListener("click",e=>{
  const b=e.target.closest("[data-tab]"); if(!b)return;
  openTab(b.dataset.tab);
});
$("#dashBtn").addEventListener("click",()=>openTab("rank"));

/* دکمه‌های بالای صفحه هم از همین مسیر می‌روند */
window.__tabClick=(ev)=>{
  const b=ev&&ev.target&&ev.target.closest&&ev.target.closest("[data-tab]");
  if(b)openTab(b.dataset.tab);
};

/* ---------------- دک ---------------- */
const DF={q:"",sec:new Set(),year:"",exam:""};
function wSections(w){return [...new Set(w.occ.map(o=>o[2]))]}
function secBadges(list){return list.map(x=>`<span class="badge ${x==="وکب"?"b-lvl-1":x==="کلوز تست"?"b-lvl-2":"b-sec-p"}">${x}</span>`).join("")}
function dfMatchWord(w){
  if(DF.q){const q=DF.q.trim();if(!w.w.includes(q)&&!meanHit(w,q))return false}
  return w.occ.some(o=>(!DF.sec.size||DF.sec.has(o[2]))&&(!DF.year||o[0]==+DF.year)&&(!DF.exam||o[1]===DF.exam));
}
function dfMatchQ(k){
  const p=k.split("|"),y=+p[0],ex=p[1],q=+p[2];
  if(DF.year&&y!=+DF.year)return false;
  if(DF.exam&&ex!==DF.exam)return false;
  const g=structFor(y).find(x=>q>=x[3]&&q<=x[4]);
  if(DF.sec.size&&(!g||!DF.sec.has(g[0])))return false;
  if(DF.q&&!("تست "+fa(q)).includes(DF.q.trim())&&!String(q).includes(DF.q.trim()))return false;
  return true;
}
function qLabel(k){
  const p=k.split("|"),y=+p[0],q=+p[2];
  const g=structFor(y).find(x=>q>=x[3]&&q<=x[4]);
  return g?g[1]:"";
}
/* دک و منتخب‌ها هم صفحه‌به‌صفحه (مثل فهرست کلمات): با معنیِ کلمه‌به‌کلمه، کشیدن ۹۶۷
   ردیف یک‌جا یعنی درخواست معنی کل دک در یک لحظه (سقف روزانه و قفل خودکار). */
var dShown=50, sShown=50;
function moreBtn(n,attr){ return n>0?`<button class="ghost" style="width:100%;margin-top:10px" ${attr}="1">نمایش ${fa(Math.min(50,n))} کلمه‌ی دیگر</button>`:"" }
function renderDeck(){
  const d=WORDS.filter(w=>deck.has(w.w)&&dfMatchWord(w));
  $("#dwn").textContent=fa(d.length);
  $("#deckList").innerHTML=d.length?d.slice(0,dShown).map(w=>`
    <div class="row" data-i="${WORDS.indexOf(w)}">
      <div class="main"><div class="head"><span class="word en">${w.w}</span>
        <span class="badge ${lvlClass(w.lvl)}">${w.lvl}</span>
        ${secBadges(wSections(w))}
        ${miss[w.w]?`<span class="badge b-once">${fa(miss[w.w])} بار یادم نبود</span>`:""}</div>
        <div class="fa-mean">${listFa(w)}</div></div>
      ${actions(w)}</div>`).join("")+moreBtn(d.length-dShown,"data-dmore")
    :'<div class="empty">کلمه‌ای با این فیلتر در دک نیست.</div>';
  const qs=[...deckQ].filter(dfMatchQ);
  $("#dqn").textContent=fa(qs.length);
  $("#deckQList").innerHTML=qs.length?qs.map(k=>{const p=k.split("|");return `
    <div class="row" data-openq="${k}">
      <div class="main"><div class="head">
        <span class="word">تست ${fa(p[2])} — کنکور ${fa(p[0])}</span>
        <span class="badge b-once">${p[1]}</span>
        <span class="badge ${qLabel(k)==="وکب"?"b-lvl-1":qLabel(k)==="کلوز تست"?"b-lvl-2":"b-sec-p"}">${qLabel(k)}</span>
      </div></div>
      ${qActs(k)}</div>`}).join(""):'<div class="empty">تستی با این فیلتر در دک نیست.</div>';
}
$("#deckQList").addEventListener("click",e=>{
  const st=e.target.closest("[data-qstar3]");
  if(st){e.stopPropagation();const k=st.dataset.qstar3;starQ.has(k)?starQ.delete(k):starQ.add(k);refreshAll();return}
  const dq=e.target.closest("[data-qdeck2]");
  if(dq){e.stopPropagation();deckQ.delete(dq.dataset.qdeck2);refreshAll();return}
  const r=e.target.closest("[data-openq]");
  if(r){const p=r.dataset.openq.split("|");openQuestion(+p[0],p[1],+p[2])}
});
$("#dsub").addEventListener("click",e=>{const b=e.target.closest("[data-dsub]");if(!b)return;
  document.querySelectorAll("#dsub button").forEach(x=>x.classList.remove("on"));b.classList.add("on");
  $("#deckList").hidden=b.dataset.dsub!=="w";$("#deckQList").hidden=b.dataset.dsub!=="q"});
$("#ssub").addEventListener("click",e=>{const b=e.target.closest("[data-ssub]");if(!b)return;
  document.querySelectorAll("#ssub button").forEach(x=>x.classList.remove("on"));b.classList.add("on");
  $("#starList").hidden=b.dataset.ssub!=="w";$("#starQList").hidden=b.dataset.ssub!=="q"});
$("#dq").addEventListener("input",e=>{DF.q=e.target.value;renderDeck()});
$("#dsec").addEventListener("click",e=>{const b=e.target.closest("[data-dsec]");if(!b)return;
  const k=b.dataset.dsec;DF.sec.has(k)?DF.sec.delete(k):DF.sec.add(k);b.classList.toggle("on");renderDeck()});
["dyear","dexam"].forEach(id=>$("#"+id).addEventListener("change",e=>{
  DF[id==="dyear"?"year":"exam"]=e.target.value;renderDeck()}));
bindRow("#deckList");
$("#clearDeck").addEventListener("click",()=>{
  if(deck.size)askConfirm(`همه‌ی ${fa(deck.size)} کلمه از دک حذف شود؟`,()=>{deck.clear();renderDeck();render()},"حذف کن");
});

const SF={q:"",sec:new Set(),year:"",exam:""};
function sfWord(w){
  if(SF.q){const q=SF.q.trim();if(!w.w.includes(q)&&!meanHit(w,q))return false}
  return w.occ.some(o=>(!SF.sec.size||SF.sec.has(o[2]))&&(!SF.year||o[0]==+SF.year)&&(!SF.exam||o[1]===SF.exam));
}
function sfQ(k){
  const p=k.split("|"),y=+p[0],ex=p[1],q=+p[2];
  if(SF.year&&y!=+SF.year)return false;
  if(SF.exam&&ex!==SF.exam)return false;
  const g=structFor(y).find(x=>q>=x[3]&&q<=x[4]);
  if(SF.sec.size&&(!g||!SF.sec.has(g[0])))return false;
  return true;
}
function renderStar(){
  const d=WORDS.filter(w=>star.has(w.w)&&sfWord(w));
  $("#swn").textContent=fa(d.length);
  $("#starList").innerHTML=d.length?d.slice(0,sShown).map(w=>`
    <div class="row" data-i="${WORDS.indexOf(w)}">
      <div class="main"><div class="head"><span class="word en">${w.w}</span>
        <span class="badge ${lvlClass(w.lvl)}">${w.lvl}</span>${secBadges(wSections(w))}</div>
        <div class="fa-mean">${listFa(w)}</div></div>
      ${actions(w)}</div>`).join("")+moreBtn(d.length-sShown,"data-smore"):'<div class="empty">کلمه‌ای با این فیلتر در منتخب‌ها نیست.</div>';
  const qs=[...starQ].filter(sfQ);
  $("#sqn").textContent=fa(qs.length);
  $("#starQList").innerHTML=qs.length?qs.map(k=>{const p=k.split("|");return `
    <div class="row" data-openq="${k}">
      <div class="main"><div class="head">
        <span class="word">تست ${fa(p[2])} — کنکور ${fa(p[0])}</span>
        <span class="badge b-once">${p[1]}</span>
        <span class="badge ${qLabel(k)==="وکب"?"b-lvl-1":qLabel(k)==="کلوز تست"?"b-lvl-2":"b-sec-p"}">${qLabel(k)}</span>
      </div></div>
      ${qActs(k,"qdeck3")}</div>`}).join(""):'<div class="empty">تستی با این فیلتر در منتخب‌ها نیست.</div>';
}
$("#starQList").addEventListener("click",e=>{
  const st=e.target.closest("[data-qstar3]");
  if(st){e.stopPropagation();starQ.delete(st.dataset.qstar3);refreshAll();return}
  const dq=e.target.closest("[data-qdeck3]");
  if(dq){e.stopPropagation();const k=dq.dataset.qdeck3;deckQ.has(k)?deckQ.delete(k):deckQ.add(k);refreshAll();return}
  const r=e.target.closest("[data-openq]");
  if(r){const p=r.dataset.openq.split("|");openQuestion(+p[0],p[1],+p[2])}
});
$("#sq").addEventListener("input",e=>{SF.q=e.target.value;renderStar()});
$("#ssec").addEventListener("click",e=>{const b=e.target.closest("[data-ssec]");if(!b)return;
  const k=b.dataset.ssec;SF.sec.has(k)?SF.sec.delete(k):SF.sec.add(k);b.classList.toggle("on");renderStar()});
["syear","sexam"].forEach(id=>$("#"+id).addEventListener("change",e=>{
  SF[id==="syear"?"year":"exam"]=e.target.value;renderStar()}));
$("#starToDeck").addEventListener("click",()=>{
  const n=star.size+starQ.size;
  if(n)askConfirm(`${fa(n)} مورد منتخب به دک مرور اضافه شود؟`,()=>{star.forEach(k=>deck.add(k));starQ.forEach(k=>deckQ.add(k));refreshAll();toast("اضافه شد.")});
});

/* ---------------- مطالعه‌ی ترتیبی ---------------- */
function readKey(){return $("#rYear").value+"|"+$("#rExam").value}
function renderRead(){
  const y=+$("#rYear").value,ex=$("#rExam").value,key=readKey();
  const at={};
  WORDS.forEach((w,i)=>w.occ.forEach(o=>{
    if(o[0]!==y||o[1]!==ex)return;
    const k=o[3]?("q"+o[3]):("t"+o[2]+(o[4]||0));
    (at[k]=at[k]||[]).push({w,i});
  }));
  const blocks=[];
  structFor(y).forEach(g=>{
    if(g[0]!=="وکب"){
      const list=at["t"+g[0]+(g[2]||0)];
      if(list)blocks.push({kind:"text",sec:g[0],p:g[2]||0,
        title:g[0]==="پسیج"?"متن پسیج "+fa(g[2]):"متن کلوز تست",
        sub:`سؤال‌های ${fa(g[3])} تا ${fa(g[4])}`,list});
    }
    for(let q=g[3];q<=g[4];q++){
      const list=at["q"+q];
      if(list)blocks.push({kind:"q",sec:g[0],p:g[2]||0,q,
        title:"تست "+fa(q),sub:g[1],list});
    }
  });
  let order=0,total=0;
  blocks.forEach(b=>total+=b.list.length);
  const doneN=Object.keys(readDone).filter(k=>k.indexOf(key+"|")===0).length;
  const mark=readMark[key];

  $("#rBookmark").innerHTML=mark
    ? `<div class="bookmark"><span>آخرین جایی که خواندید: ${mark} · ${fa(doneN)} از ${fa(total)} کلمه خوانده شده</span><button class="ghost" id="jump">ادامه از همان‌جا</button></div>`
    : `<div class="bookmark" style="background:var(--canvas);border-color:var(--line);color:var(--ink-2)"><span>هنوز نشانه‌ای برای این آزمون ثبت نشده. کنار هر کلمه «تا اینجا خواندم» را بزنید.</span></div>`;

  $("#readList").innerHTML=blocks.length?blocks.map(b=>{
    const c=b.sec==="وکب"?"v":b.sec==="کلوز تست"?"c":"p";
    return `<div class="rblock rb-${c}">
      <div class="rb-h"><b>${b.title}</b><span>${b.sub}</span><i>${fa(b.list.length)} کلمه</i></div>
      ${b.list.map(x=>{
        const done=readDone[key+"|"+order];
        return `<div class="rrow ${done?"done":""}" data-w="${x.w.w}" data-i="${x.i}" data-data-tip="${b.title}" data-ord="${order++}">
          <span class="w en">${x.w.w}</span>
          <span class="m">${x.w.fa}</span>
          ${actions(x.w)}
          <button class="mark ${done?"on":""}" data-mark="${order-1}">${done?"✓ خوانده شد":"تا اینجا خواندم"}</button>
        </div>`;}).join("")}
    </div>`;}).join(""):'<div class="empty">برای این آزمون کلمه‌ای ثبت نشده است.</div>';
}
$("#readList").addEventListener("click",e=>{
  const m=e.target.closest("[data-mark]");
  if(m){
    const idx=+m.dataset.mark,key=readKey();
    const rows=[...$("#readList").querySelectorAll(".rrow")];
    rows.forEach(el=>{
      const k=key+"|"+el.dataset.ord;
      if(+el.dataset.ord<=idx)readDone[k]=true; else delete readDone[k];
    });
    readMark[key]=rows[idx]?rows[idx].dataset.title:"";renderRead();return;
  }
  const t=e.target.closest("[data-toggle]");
  if(t){const k=t.dataset.toggle;deck.has(k)?deck.delete(k):deck.add(k);refreshAll();return}
  const st=e.target.closest("[data-star]");
  if(st){const k=st.dataset.star;star.has(k)?star.delete(k):star.add(k);refreshAll();return}
  const row=e.target.closest(".rrow");if(row)openDetail(WORDS[+row.dataset.i]);
});
$("#rBookmark").addEventListener("click",e=>{
  if(!e.target.closest("#jump"))return;
  const key=readKey(),mark=readMark[key];
  const el=[...$("#readList").querySelectorAll(".rrow")].find(x=>!readDone[key+"|"+x.dataset.ord]);
  (el||$("#readList").lastElementChild).scrollIntoView({behavior:"smooth",block:"center"});
});
["rYear","rExam"].forEach(id=>$("#"+id).addEventListener("change",renderRead));
$("#rReset").addEventListener("click",()=>{
  const key=readKey();delete readMark[key];
  Object.keys(readDone).forEach(k=>{if(k.indexOf(key+"|")===0)delete readDone[k]});
  renderRead();
});

/* ---------------- سؤال‌ها ---------------- */
function hash(s){let h=0;s=String(s);for(let i=0;i<s.length;i++)h=(h*31+s.charCodeAt(i))|0;return Math.abs(h)}

/* ===== پاسخ سؤال‌ها =====
   پاسخ عمداً در payload سؤال نیست (وگرنه در حالت فیدبک از کنسول خوانده
   می‌شد). هر صفحه‌ای که پاسخ لازم دارد fetchAns را صدا می‌زند و بعد از
   رسیدن دوباره رندر می‌کند؛ makeQ همیشه ans را از همین کش پر می‌کند.
     ANS[qid] === undefined  → هنوز نپرسیده‌ایم
     ANS[qid] === null       → پرسیدیم، سرور نداد (دسترسی/حذف‌شده)
     ANS_LOCK[qid]           → آزمون فیدبکِ باز روی همین دفترچه
     ANS_LIMIT[qid]          → سقف روزانه‌ی دیدن پاسخ پر شده (config zaban.answer_daily_cap) */
const ANS={}, ANS_LOCK={}, ANS_WAIT={}, ANS_LIMIT={};
let ANS_LIMIT_MSG="سقف روزانه‌ی دیدن پاسخ پر شده است؛ فردا دوباره در دسترس است.";
function fetchAns(qids){
  const need=[...new Set(qids.filter(id=>id&&ANS[id]===undefined&&!ANS_LOCK[id]&&!ANS_LIMIT[id]))];
  if(!need.length)return Promise.resolve();
  const jobs=[];
  for(let i=0;i<need.length;i+=40){
    const b=need.slice(i,i+40), key=b.join(",");
    if(!ANS_WAIT[key]){
      ANS_WAIT[key]=ZABAN.answers(b).then(r=>{
        ((r&&r.answers)||[]).forEach(a=>{ANS[a.id]={ans:a.correct-1,exp:a.explanation||""}});
        ((r&&r.locked)||[]).forEach(id=>{ANS_LOCK[id]=1});
        ((r&&r.limited)||[]).forEach(id=>{ANS_LIMIT[id]=1});
        if(r&&r.limit_message)ANS_LIMIT_MSG=r.limit_message;
        b.forEach(id=>{if(ANS[id]===undefined&&!ANS_LOCK[id]&&!ANS_LIMIT[id])ANS[id]=null});
      }).finally(()=>{delete ANS_WAIT[key]});
    }
    jobs.push(ANS_WAIT[key]);
  }
  return Promise.all(jobs);
}
function applyAns(Q){ if(Q&&Q.qid&&Q.ans==null&&ANS[Q.qid])Q.ans=ANS[Q.qid].ans; return Q }
/* پیام جای پاسخ، وقتی پاسخ هنوز معلوم نیست */
function ansWaitMsg(Q){
  if(Q&&Q.qid&&ANS_LOCK[Q.qid])return "پاسخ این سؤال بعد از پایان آزمونِ باز همین دفترچه نشان داده می‌شود.";
  if(Q&&Q.qid&&ANS_LIMIT[Q.qid])return ANS_LIMIT_MSG;
  if(Q&&Q.qid&&ANS[Q.qid]===undefined)return "در حال گرفتن پاسخ…";
  return "پاسخ این سؤال در دسترس نیست.";
}

/* سؤال‌های هر رشته جدا و با تأخیر بارگذاری می‌شوند. QREADY یعنی سؤال‌های
   آن رشته رسیده‌اند؛ بعد از آن، نبودن سؤال یعنی واقعاً در بانک نیست. */
const QEXAM_CODE={"مهندسی کامپیوتر":"ce","آی‌تی":"it","علوم کامپیوتر":"cs"};
const QREADY={};
function whenQuestions(e){
  return ZABAN.questions(QEXAM_CODE[e]||"").then(r=>{QREADY[e]=1;return r});
}

/* فقط سؤال واقعیِ بانک. تا نسخه‌ی دمو، اگر سؤالی در بانک نبود از روی
   کلمه‌ها یک سؤال ساختگی با پاسخ ساختگی ساخته می‌شد — یعنی در دفترچه‌های
   ناقص، داوطلب سؤالی می‌دید که در کنکور نبوده. حالا null برمی‌گردد و
   هر نمایش‌دهنده صادقانه می‌گوید سؤال وارد نشده. */
function makeQ(y,e,q){
  const real = (window.QBYKEY||{})[y+"|"+e+"|"+q];
  if(!real)return null;
  return {
    qid: real.id, ws: (real.words.stem||[]).map(id=>WBYID[id]).filter(Boolean),
    main: WBYID[(real.words.option||[])[0]] || null,
    sec: real.sec, p: real.p, view: real.view || 3, stem: real.stem||"", stemFa: real.stemFa||"",
    opts: real.opts.map((body,i)=>{
      const w = real.optWords[i]!=null ? WBYID[real.optWords[i]] : null;
      return w || {w:body, fa:"", pos:"", lvl:"", occ:[], ex:[], years:[]};
    }),
    ans: ANS[real.id] ? ANS[real.id].ans : null, fromBank: true
  };
}

/* نمای سؤالی که در بانک نیست — یا هنوز نرسیده، یا واقعاً وارد نشده. */
function qUnavailable(y,e,q){
  $("#qBody").innerHTML=`${backBtn()}
    <header class="qsticky"><div style="font-size:19px;font-weight:700">سؤال ${fa(q)} — کنکور ${fa(y)} ${e}</div></header>
    <div class="empty" id="qMiss">در حال بارگذاری سؤال…</div>`;
  const same=()=>curView&&curView.t==="q"&&curView.y===y&&curView.e===e&&curView.q===q;
  const missing=()=>{const m=$("#qMiss"); if(m)m.innerHTML=
    `این سؤال هنوز در بانک سؤال وارد نشده است.<br><span style="font-size:12.5px">دفترچه‌ی کنکور ${fa(y)} ${e}
     ناقص است. به‌محض تکمیل، سؤال همین‌جا نمایش داده می‌شود.</span>`};
  if(QREADY[e]){missing();return}
  whenQuestions(e).then(()=>{
    if(!same())return;
    if(makeQ(y,e,q))renderQuestion(y,e,q); else missing();
  }).catch(()=>{const m=$("#qMiss"); if(m&&same())m.textContent="بارگذاری سؤال ناموفق بود. اتصال را بررسی کنید و دوباره باز کنید."});
}
function renderQuestion(y,e,q){
  const Q=makeQ(y,e,q);
  if(!Q){qUnavailable(y,e,q);return}
  const {ws,main,sec,p:pno,opts:ordered,ans}=Q;
  const exQ=exInRun(y,e,q);
  const picked=(!exQ&&curView&&curView.picked!=null)?curView.picked:null;
  const rev=exQ?!!EX.rev[q]:(picked!=null||!!(curView&&curView.revealed));
  if(rev&&ans==null&&ANS[Q.qid]===undefined&&!ANS_LOCK[Q.qid]){
    const v=curView;
    fetchAns([Q.qid]).then(()=>{if(curView===v)renderQuestion(y,e,q)}).catch(e=>console.error(e));
  }
  const qk=qKey(y,e,q),keys=ws.map(w=>w.w);
  const body=(sec==="وکب"?"":textBlock(y,e,sec,pno))
    +`<div class="qbox"><div class="en" style="text-align:left">${Q.stem.replace("____","<u>____</u>")}</div>${rev&&Q.stemFa?`<div style="font-size:13px;color:var(--ink-2);direction:rtl;text-align:right">${Q.stemFa}</div>`:""}</div>`;
  $("#qBody").innerHTML=`
   ${backBtn()}
   <header class="qsticky"><div style="font-size:19px;font-weight:700">سؤال ${fa(q)} — کنکور ${fa(y)} ${e}</div>
     <div style="font-size:13px;color:var(--ink-2)">${sec}${pno?" "+fa(pno):""}</div>
     <div class="row-f qtools" style="margin-top:11px">
       ${(exQ&&!EX.studyBtns)?"":`
       <button class="mini ${deckQ.has(qk)?"on":""}" data-qdeck="${qk}"
         data-tip="${deckQ.has(qk)?"این تست در دک مرور شماست":"افزودن این تست به دک مرور"}">${deckQ.has(qk)?"✓ در دک":"+ دک"}</button>
       <button class="mini ${starQ.has(qk)?"onstar":""}" data-qstar="${qk}"
         data-tip="${starQ.has(qk)?"این تست در منتخب شماست":"افزودن به منتخب"}">${starQ.has(qk)?"★ منتخب":"☆ منتخب"}</button>
       <button class="mini ${allWordsOf(Q).every(x=>deck.has(x.w))?"on":""}" data-tdeck="${allWordsOf(Q).map(x=>x.w).join(",")}"
         data-tip="گزینه‌ها و کلمات صورت سؤال:<br>${allWordsOf(Q).map(x=>x.w).join("، ")}">+ ${fa(allWordsOf(Q).length)} کلمه</button>`}
       ${exQ?"":`<button class="mini ${nHas("q:"+qk)?"onstar":""}" data-note="q:${qk}" data-nt="یادداشت تست ${fa(q)} — کنکور ${fa(y)} ${e}"
         data-tip="${nHas("q:"+qk)?"یادداشت شما — برای ویرایش بزنید":"یادداشت شخصی روی این تست"}">✎ یادداشت</button>
       <button class="mini ${rAnswered("q:"+qk)?"on":(rHas("q:"+qk)?"onstar":"")}" data-rep="q:${qk}"
         data-rt="سؤال ${fa(q)} — کنکور ${fa(y)} ${e}"
         data-tip="${rAnswered("q:"+qk)?"به گزارش شما پاسخ داده شده":(rHas("q:"+qk)?"گزارش شما در انتظار پاسخ است":"گزارش اشکال این تست")}">⚑ ${rAnswered("q:"+qk)?"پاسخ داده شد":(rHas("q:"+qk)?"ثبت شده":"گزارش")}</button>`}
     </div>
     ${nBox("q:"+qk)}</header>
   <section class="qcard ${rev?"rev":""}">${body}
     ${exQ?`${optsHTML({q,Q},true)}${qTools({q,Q},true)}${rev?answerBox({q,Q,y,e}):""}`:`
     <div class="ex-os ${Q.view===12?"c1":Q.view===6?"c2":"c4"} big">${ordered.map((o,i)=>{
        let c=picked===i?"sel":"";
        const struck=!rev&&!!(curView&&curView.out&&curView.out[i]);
        if(struck)c+=" out";
        if(rev&&ans!=null){if(i===ans)c+=" right"; else if(picked===i)c+=" wrong"}
        return `<div class="ex-o ${c}"><div class="box" ${rev?"":`data-pickq="${i}"`}>
          <span class="nb">${fa(i+1)}</span><span class="ck">✓</span><span class="tx en">${o.w}</span></div>
          ${rev?"":`<button class="ex-rd" data-qelim="${i}">${struck?"پاکسازی":"رد گزینه"}</button>`}</div>`}).join("")}</div>
     ${picked!=null&&ans!=null?`<div class="ex-vd ${picked===ans?"ok":"bad"}" style="margin-top:12px;display:inline-block">${
        picked===ans?"پاسخ شما درست بود":"پاسخ شما غلط بود"}</div>`:""}
     ${rev
       ? `<div class="row-f" style="margin:0 0 12px">
            <button class="ghost" data-qrev="0">← بازگشت به صورت سؤال</button>
            <button class="ghost" data-qstats="1">${curView&&curView.stats?"بستن آمار":"آمار این تست"}</button>
            ${picked!=null?`<button class="ghost" data-qretry="1" style="margin-right:auto">دوباره زدن این تست</button>`:""}
          </div>
          ${curView&&curView.stats?qStatsHTML(y,e,q,Q):""}
          ${answerBox({q,Q,y,e})}`
       : `<button class="showbtn" data-qrev="1" style="margin-top:14px">یکی از گزینه‌ها را بزنید، یا پاسخ را ببینید</button>`}`}
   </section>`;
  $("#detail").classList.remove("open");$("#qview").classList.add("open");
}
$("#qBody").addEventListener("click",e=>{
  /* هر شاخه‌ی این هندلر روی curView می‌نویسد. اگر سؤال از مسیری باز شده باشد
     که showView را صدا نزده، curView خالی است و همه‌شان می‌شکنند.
     یک محافظ اینجا، به‌جای پنج محافظ پراکنده در بدنه. */
  if(!curView) return;

  /* رد گزینه — همان رفتار سر جلسه‌ی آزمون، تا همه‌جا یکسان باشد */
  const qe=e.target.closest("[data-qelim]");
  if(qe){
    if(!curView.out)curView.out={};
    const i=+qe.dataset.qelim;
    curView.out[i]=!curView.out[i];
    renderQuestion(curView.y,curView.e,curView.q);
    return;
  }
  const qr=e.target.closest("[data-qrev]");
  if(qr){if(qr.dataset.qrev==="0"){curView.picked=null;curView.stats=false}
    const el=$("#qBody").querySelector(".qcard");
    if(el){el.classList.add("half");setTimeout(()=>{curView.revealed=qr.dataset.qrev==="1";renderQuestion(curView.y,curView.e,curView.q)},220);}
    else{curView.revealed=qr.dataset.qrev==="1";renderQuestion(curView.y,curView.e,curView.q)}
    return}
  const qs=e.target.closest("[data-qstar]");
  if(qs){const k=qs.dataset.qstar;starQ.has(k)?starQ.delete(k):starQ.add(k);refreshAll();renderQuestion(curView.y,curView.e,curView.q);return}
  const qd=e.target.closest("[data-qdeck]");
  if(qd){const k=qd.dataset.qdeck;deckQ.has(k)?deckQ.delete(k):deckQ.add(k);refreshAll();renderQuestion(curView.y,curView.e,curView.q);return}
  const td=e.target.closest("[data-tdeck]");
  if(td){toggleAll(deck,td.dataset.tdeck.split(","));refreshAll();renderQuestion(curView.y,curView.e,curView.q);return}
  const pq=e.target.closest("[data-pickq]");
  if(pq&&curView&&curView.t==="q"){
    const i=+pq.dataset.pickq, v=curView, Q=makeQ(v.y,v.e,v.q);
    const k=qKey(v.y,v.e,v.q);
    v.picked=i;v.revealed=true;
    renderQuestion(v.y,v.e,v.q);
    /* آمار شخصی فقط با پاسخ واقعی ثبت می‌شود؛ پاسخ قفل یا ناموجود = ثبت نشود */
    fetchAns([Q.qid]).then(()=>{
      const A=ANS[Q.qid]; if(!A)return;
      const a=qatt(k); a.n++; a.opt[i]++; if(i===A.ans)a.ok++; qattSave();
      ZABAN.qAttempt(Q.qid,i+1);                    /* ۱ تا ۴؛ درستی را سرور حساب می‌کند */
      if(curView===v)renderQuestion(v.y,v.e,v.q);
    }).catch(e=>console.error(e));
    return}
  if(e.target.closest("[data-qretry]")){
    curView.picked=null;curView.revealed=false;curView.stats=false;
    renderQuestion(curView.y,curView.e,curView.q);return}
  if(e.target.closest("[data-qstats]")){
    curView.stats=!curView.stats;renderQuestion(curView.y,curView.e,curView.q);return}
  const gq=e.target.closest("[data-goq]");
  if(gq){const p=gq.dataset.goq.split("|");openQuestion(+p[0],p[1],+p[2]);return}
  const b=e.target.closest("[data-back]")||e.target.closest("[data-w]");
  if(!b)return;
  const k=b.dataset.back||b.dataset.w;
  const w=WORDS.find(x=>x.w===k);
  if(w){$("#qview").classList.remove("open");openDetail(w)}
});
$("#closeQ").addEventListener("click",closeModals);
$("#qview").addEventListener("click",e=>{if(e.target.id==="qview")closeModals()});

/* ---------------- متن کلوز و پسیج ---------------- */
function textKeyOf(y,e,sec,p){return y+"|"+e+"|"+sec+"|"+(p||0)}
function textWords(y,e,sec,p){
  return WORDS.filter(w=>w.occ.some(o=>o[0]===y&&o[1]===e&&o[2]===sec&&o[3]===0&&(o[4]||0)===(p||0)));
}
function highlight(body,ws){
  let out=body;
  ws.forEach(w=>{
    out=out.replace(new RegExp("\\b("+w.w+")\\b","gi"),'<b class="kw en" data-w="'+w.w+'">$1</b>');
  });
  return out;
}
const _txtCache={};
/* در نسخه‌ی demo متن‌ها از قالب TXTTPL ساخته می‌شدند. سرور متن واقعی
   کنکور را می‌فرستد، پس فقط از TEXTS خوانده می‌شود. */
function buildText(y,e,sec,p){
  const k=textKeyOf(y,e,sec,p);
  if(_txtCache[k]!==undefined)return _txtCache[k];
  const t=(typeof TEXTS!=="undefined"&&TEXTS)?TEXTS[k]:null;
  return _txtCache[k] = t ? (typeof t==="string"?t:(t.body||"")) : "";
}
function textBlock(y,e,sec,p){
  const body=buildText(y,e,sec,p);
  if(!body)return "";
  const ws=textWords(y,e,sec,p);
  const title=sec==="پسیج"?"متن پسیج "+fa(p):"متن کلوز تست";
  return `<div class="passage">
    <div class="ph"><b>${title}</b><span>${fa(ws.length)} کلمه‌ی بانک در این متن</span></div>
    <p class="en">${highlight(body,ws)}</p></div>`;
}
function renderText(y,e,sec,p){
  const ws=textWords(y,e,sec,p);
  const g=structFor(y).find(x=>x[0]===sec&&(x[2]||0)===(p||0));
  $("#qBody").innerHTML=`
   ${backBtn()}
   <header><div style="font-size:19px;font-weight:700">${sec==="پسیج"?"پسیج "+fa(p):"کلوز تست"} — کنکور ${fa(y)} ${e}</div>
     <div style="font-size:13px;color:var(--ink-2)">${g?`سؤال‌های ${fa(g[3])} تا ${fa(g[4])}`:""} · ${fa(ws.length)} کلمه از بانک در متن</div></header>
   <section>${textBlock(y,e,sec,p)}
     <div class="capt">کلمات هایلایت‌شده در بانک ثبت شده‌اند؛ روی هرکدام بزنید تا معنی و تاریخچه‌اش باز شود.</div></section>
   ${g?`<section><div class="label">سؤال‌های این ${sec==="پسیج"?"پسیج":"کلوز"}</div>
     <div class="kin">${Array.from({length:g[4]-g[3]+1},(_,i)=>g[3]+i).map(q=>`<span data-goq="${y}|${e}|${q}" style="cursor:pointer">تست ${fa(q)}</span>`).join("")}</div></section>`:""}
   <section><div class="label">کلمات این متن</div>
     <div class="kin">${ws.map(w=>`<span class="en" data-w="${w.w}" style="cursor:pointer">${w.w}</span>`).join("")}</div></section>`;
  $("#detail").classList.remove("open");$("#qview").classList.add("open");
}

/* ---------------- تست‌های زبان ---------------- */
let tShown=3, tSort="new";
function bookletData(){
  const map={};
  WORDS.forEach(w=>{
    if(F.lvl.size&&!F.lvl.has(w.lvl))return;
    if(F.q){const q=F.q.trim();if(!w.w.includes(q)&&!meanHit(w,q))return}
    w.occ.forEach(o=>{
      if(!matchOcc(o))return;
      const ek=o[0]+"|"+o[1];
      const slot=o[3]?("q"+o[3]):("t"+o[2]+(o[4]||0));
      const E=map[ek]=map[ek]||{y:o[0],e:o[1],items:{}};
      (E.items[slot]=E.items[slot]||{q:o[3],sec:o[2],p:o[4]||0,ws:[]}).ws.push(w);
    });
  });
  const exams=Object.values(map);
  exams.sort((a,b)=>tSort==="old"?(a.y-b.y):(b.y-a.y));
  exams.forEach(E=>{
    const order=[];
    structFor(E.y).forEach(g=>{
      if(g[0]!=="وکب"){
        const t=E.items["t"+g[0]+(g[2]||0)];
        order.push({type:"text",sec:g[0],p:g[2]||0,label:g[1],from:g[3],to:g[4],ws:t?t.ws:[]});
      }
      for(let q=g[3];q<=g[4];q++){
        const it=E.items["q"+q];
        if(it)order.push({type:"q",q,sec:g[0],p:g[2]||0,label:g[1],ws:it.ws});
      }
    });
    E.order=order.filter(x=>x.type==="q"||x.ws.length);
  });
  return exams.filter(E=>E.order.length);
}
function qKey(y,e,q){return y+"|"+e+"|"+q}
function qDeckBtn(y,e,q){
  const k=qKey(y,e,q),on=deckQ.has(k);
  return `<button class="${on?"deckbtn":"ghost"}" data-qdeck="${k}">${on?"✓ این تست در دک مرور است":"افزودن خودِ این تست به دک مرور"}</button>`;
}
let _menuFns=[];
function openMenu(ev,title,items){
  const m=$("#menu");_menuFns=items.map(i=>i.fn);
  m.innerHTML=`<div class="mh">${title}</div>`+items.map((i,k)=>`<button data-mi="${k}">${i.label}</button>`).join("");
  m.hidden=false;
  const x=Math.min((ev.clientX||100),(window.innerWidth||900)-230);
  const y=Math.min((ev.clientY||100)+8,(window.innerHeight||700)-140);
  m.style.left=x+"px";m.style.top=y+"px";
}
$("#menu").addEventListener("click",e=>{
  const b=e.target.closest("[data-mi]");if(!b)return;
  $("#menu").hidden=true;const f=_menuFns[+b.dataset.mi];if(f)f();
});
document.addEventListener("click",e=>{
  if(!e.target.closest("#menu")&&!e.target.closest("[data-tmenu]"))$("#menu").hidden=true;
});
function toggleAll(set,keys){keys.every(k=>set.has(k))?keys.forEach(k=>set.delete(k)):keys.forEach(k=>set.add(k))}

function tActions(ws){
  const keys=ws.map(w=>w.w);
  const allD=keys.length&&keys.every(k=>deck.has(k));
  const allS=keys.length&&keys.every(k=>star.has(k));
  return `<span class="tacts">
    <button class="star ${allS?"on":""}" data-tstar="${keys.join(",")}" data-tip="${allS?"حذف کلمات این تست از منتخب":"افزودن کلمات این تست به منتخب"}">${allS?"★":"☆"}</button>
    <button class="add ${allD?"in":""}" data-tdeck="${keys.join(",")}" data-tip="${allD?"حذف کلمات این تست از دک":"افزودن کلمات این تست به دک"}">${allD?"✓":"+"}</button>
  </span>`;
}
function grpBtns(o){
  const ks=o.keys?String(o.keys).split(",").filter(Boolean):[];
  const qk=o.q?qKey(o.y,o.e,o.q):"";
  const wIn=ks.length&&ks.every(k=>deck.has(k)), wSt=ks.length&&ks.every(k=>star.has(k));
  const qIn=qk&&deckQ.has(qk), qSt=qk&&starQ.has(qk);
  const rngQs=[];
  if(o.rng){const a=+o.rng.split("-")[0],b=+o.rng.split("-")[1];for(let i=a;i<=b;i++)rngQs.push(qKey(o.y,o.e,i))}
  const rIn=rngQs.length&&rngQs.every(k=>deckQ.has(k)), rSt=rngQs.length&&rngQs.every(k=>starQ.has(k));
  const qws=[];
  if(rngQs.length)WORDS.forEach(w=>{if(w.occ.some(x=>x[0]===o.y&&x[1]===o.e&&x[3]&&rngQs.indexOf(qKey(o.y,o.e,x[3]))>=0))qws.push(w.w)});
  const qwIn=qws.length&&qws.every(k=>deck.has(k)), qwSt=qws.length&&qws.every(k=>star.has(k));
  if(!o.q){
    return `<span class="grp"><i>دک</i>
        <button class="${wIn?"on":""}" data-wtoggle="${ks.join(",")}" data-tip="کلمات خودِ متن">${wIn?"✓ کلمات متن":"کلمات متن"}</button>
        <button class="${rIn?"on":""}" data-rtoggle="deck~${rngQs.join(",")}" data-tip="خودِ سؤال‌های این بخش">${rIn?"✓ تست‌ها":"تست‌ها"}</button>
        <button class="${qwIn?"on":""}" data-wtoggle="${qws.join(",")}" data-tip="کلمات سؤال‌های این بخش">${qwIn?"✓ کلمات تست‌ها":"کلمات تست‌ها"}</button></span>
      <span class="grp st"><i>منتخب</i>
        <button class="${wSt?"on":""}" data-wstoggle="${ks.join(",")}">${wSt?"★ کلمات متن":"کلمات متن"}</button>
        <button class="${rSt?"on":""}" data-rtoggle="star~${rngQs.join(",")}">${rSt?"★ تست‌ها":"تست‌ها"}</button>
        <button class="${qwSt?"on":""}" data-wstoggle="${qws.join(",")}">${qwSt?"★ کلمات تست‌ها":"کلمات تست‌ها"}</button></span>`;
  }
  return `<span class="grp"><i>دک</i>
      <button class="${qIn?"on":""}" data-qtoggle="${qk}">${qIn?"✓ تست":"تست"}</button>
      <button class="${wIn?"on":""}" data-wtoggle="${ks.join(",")}">${wIn?"✓ کلمات":"کلمات"}</button></span>
    <span class="grp st"><i>منتخب</i>
      <button class="${qSt?"on":""}" data-qstoggle="${qk}">${qSt?"★ تست":"تست"}</button>
      <button class="${wSt?"on":""}" data-wstoggle="${ks.join(",")}">${wSt?"★ کلمات":"کلمات"}</button></span>`;
}
function renderTestTab(){
  const exams=bookletData();
  const total=exams.reduce((n,E)=>n+E.order.filter(x=>x.type==="q").length,0);
  $("#tn").textContent=fa(total);
  const page=exams.slice(0,tShown);
  $("#tMore").hidden=exams.length<=tShown;
  $("#tMore").textContent="نمایش آزمون‌های بیشتر";
  $("#testList").innerHTML=page.length?page.map(E=>`
    <div class="booklet">
      <div class="bk-h"><b>کنکور ${fa(E.y)}</b><span>${E.e}</span>
        <span class="bk-n">${fa(E.order.filter(x=>x.type==="q").length)} تست</span></div>
      ${E.order.map(it=>{
        const keys=it.ws.map(w=>w.w).join(",");
        if(it.type==="text")return `<div class="bk-text" data-open="text|${E.y}|${E.e}|${it.sec}|${it.p}">
             <div class="bk-r1">
               <div class="bk-tt"><b>${it.sec==="پسیج"?"متن پسیج "+fa(it.p):"متن کلوز تست"}</b>
                 <span>سؤال‌های ${fa(it.from)} تا ${fa(it.to)} · ${fa(it.ws.length)} کلمه از بانک در متن</span></div>
               <span class="go2">دیدن متن ←</span></div>
             <div class="ws">${it.ws.slice(0,8).map(w=>`<span class="en">${w.w}</span>`).join("")}${it.ws.length>8?`<span class="more">+${fa(it.ws.length-8)} کلمه‌ی دیگر</span>`:""}</div>
             <div class="bk-r3"><span class="tacts">${grpBtns({y:E.y,e:E.e,q:0,keys:keys,rng:it.from+"-"+it.to})}</span></div></div>`;
        const qk=qKey(E.y,E.e,it.q);
        return `<div class="bk-q" data-open="q|${E.y}|${E.e}|${it.q}">
             <span class="bk-no">${fa(it.q)}</span>
             <span class="bk-sec">${it.label}</span>
             <span class="bk-cnt">${fa(it.ws.length)} کلمه از بانک</span>
             <span class="tacts">${grpBtns({y:E.y,e:E.e,q:it.q,keys:keys,rng:""})}</span>
             <span class="go2">دیدن سؤال ←</span></div>`;}).join("")}
    </div>`).join(""):'<div class="empty">با این فیلترها تستی پیدا نشد.</div>';
}
$("#testList").addEventListener("click",e=>{
  const qt=e.target.closest("[data-qtoggle]");
  if(qt){e.stopPropagation();const k=qt.dataset.qtoggle;deckQ.has(k)?deckQ.delete(k):deckQ.add(k);refreshAll();return}
  const wt=e.target.closest("[data-wtoggle]");
  if(wt){e.stopPropagation();const ks=wt.dataset.wtoggle.split(",").filter(Boolean);toggleAll(deck,ks);refreshAll();return}
  const ws=e.target.closest("[data-wstoggle]");
  if(ws){e.stopPropagation();toggleAll(star,ws.dataset.wstoggle.split(",").filter(Boolean));refreshAll();return}
  const qs2=e.target.closest("[data-qstoggle]");
  if(qs2){e.stopPropagation();const k=qs2.dataset.qstoggle;starQ.has(k)?starQ.delete(k):starQ.add(k);refreshAll();return}
  const rt=e.target.closest("[data-rtoggle]");
  if(rt){e.stopPropagation();const p=rt.dataset.rtoggle.split("~");
    const set=p[0]==="deck"?deckQ:starQ,ks=(p[1]||"").split(",").filter(Boolean);
    toggleAll(set,ks);refreshAll();return}
  const tm=e.target.closest("[data-tmenu]");
  if(tm){
    e.stopPropagation();
    const p=tm.dataset.tmenu.split("|"),kind=p[0],y=+p[1],ex=p[2],q=+p[3];
    const keys=p[4]?p[4].split(",").filter(Boolean):[];
    const rng=p[5]||"";
    const qk=qKey(y,ex,q);
    const set=kind==="deck"?deck:star,setQ=kind==="deck"?deckQ:starQ;
    const nameW=kind==="deck"?"دک مرور":"منتخب‌ها";
    const items=[{label:`${keys.every(k=>set.has(k))?"حذف":"افزودن"} ${fa(keys.length)} کلمه‌ی این ${q?"تست":"متن"} ${keys.every(k=>set.has(k))?"از":"به"} ${nameW}`,
                  fn:()=>{toggleAll(set,keys);refreshAll()}}];
    if(q)items.push({label:`${setQ.has(qk)?"حذف این تست از":"افزودن این تست به"} ${nameW}`,
                     fn:()=>{setQ.has(qk)?setQ.delete(qk):setQ.add(qk);refreshAll()}});
    if(rng){
      const a=+rng.split("-")[0],b2=+rng.split("-")[1];
      const qs=[];for(let i=a;i<=b2;i++)qs.push(qKey(y,ex,i));
      const allIn=qs.every(k=>setQ.has(k));
      items.push({label:`${allIn?"حذف":"افزودن"} همه‌ی ${fa(qs.length)} تست این بخش (${fa(a)} تا ${fa(b2)}) ${allIn?"از":"به"} ${nameW}`,
                  fn:()=>{allIn?qs.forEach(k=>setQ.delete(k)):qs.forEach(k=>setQ.add(k));refreshAll()}});
    }
    openMenu(e,q?`تست ${fa(q)} — کنکور ${fa(y)}`:"متن این بخش",items);
    return;
  }
  const r=e.target.closest("[data-open]");if(!r)return;
  const p=r.dataset.open.split("|");
  if(p[0]==="q")openQuestion(+p[1],p[2],+p[3]);
  else openText(+p[1],p[2],p[3],+p[4]);
});
$("#tSort").addEventListener("change",e=>{tSort=e.target.value;tShown=3;renderTestTab()});
$("#tMore").addEventListener("click",()=>{tShown+=3;renderTestTab()});

/* ---------------- آمار ---------------- */
function renderStats(){
  /* از چند جا صدا زده می‌شود — از جمله بستن پنجره‌ی مرور — و ممکن است
     تب آمار اصلاً روی صفحه نباشد. در آن حالت کاری برای انجام نیست. */
  if(!$("#statCards"))return;
  const d=WORDS.filter(w=>deck.has(w.w));
  $("#statCards").innerHTML=`
   
    <div class="stat"><div class="k">کلمات دک شما<small>کلماتی که برای مرور انتخاب کرده‌اید</small></div><div class="v">${fa(d.length)}</div></div>
    <div class="stat"><div class="k">بدون اشتباه<small>در مرورها هیچ‌بار «یادم نبود» نزده‌اید</small></div><div class="v">${fa(d.filter(w=>!miss[w.w]).length)}</div></div>
    <div class="stat"><div class="k">نیازمند تمرین<small>بیش از دو بار فراموش شده‌اند</small></div><div class="v">${fa(Object.values(miss).filter(v=>v>2).length)}</div></div>
    <div class="stat"><div class="k">منتخب‌های شما<small>کنار گذاشته‌شده، بدون ورود به دک</small></div><div class="v">${fa(star.size)}</div></div>`;
  $("#lvlBars").innerHTML=["ساده","متوسط","پیشرفته"].map(l=>{
    const tot=WORDS.filter(w=>w.lvl===l).length,got=d.filter(w=>w.lvl===l).length;
    return `<div style="margin-bottom:11px"><div style="display:flex;justify-content:space-between;font-size:13.5px"><span>${l}</span><span style="color:var(--ink-2)">${fa(got)} کلمه از ${fa(tot)} کلمه‌ی ${l} بانک</span></div>
      <div class="bar"><i style="width:${tot?got/tot*100:0}%;background:${LVLC[l]}"></i></div></div>`;
  }).join("")+'<div class="capt">این نوارها نشان می‌دهند از کل کلمات هر سطح در بانک، چند تا را وارد دک مرور کرده‌اید — یعنی چقدر از بانک را پوشش داده‌اید، نه اینکه چند تا را یاد گرفته‌اید.</div>';
  const bad=Object.keys(miss).filter(k=>miss[k]>2);
  $("#leech").innerHTML=bad.length?`<div class="kin">${bad.map(k=>`<span class="en">${k}</span>`).join("")}</div>`
    :'<div class="hint">هنوز کلمه‌ای در این دسته نیست.</div>';
}

/* ---------------- تحلیل ---------------- */
const C={exam:"",from:YEARS[0],to:YEARS[YEARS.length-1],mode:"abs",top:20,metric:"years"};
let topList=[];
(function(){
  YEARS.slice().reverse().forEach(y=>["cFrom","cTo"].forEach(id=>{
    const o=document.createElement("option");o.value=y;o.textContent=fa(y);$("#"+id).appendChild(o)}));
  $("#cFrom").value=C.from;$("#cTo").value=C.to;
})();

function renderCharts(){
  if(C.from>C.to){[C.from,C.to]=[C.to,C.from];$("#cFrom").value=C.from;$("#cTo").value=C.to}
  const years=YEARS.filter(y=>y>=C.from&&y<=C.to);
  const H=190;

  const data=years.map(y=>{
    const d={"ساده":0,"متوسط":0,"پیشرفته":0};
    WORDS.forEach(w=>{if(w.occ.some(o=>o[0]===y&&(!C.exam||o[1]===C.exam)))d[w.lvl]++});
    d.total=d["ساده"]+d["متوسط"]+d["پیشرفته"];return{y,d};
  });
  const max=Math.max(1,...data.map(x=>x.d.total));
  $("#c1sub").innerHTML=[C.exam||"همه رشته‌ها",`${fa(C.from)} تا ${fa(C.to)}`,C.mode==="pct"?"نمایش درصدی":"نمایش تعدادی"].map(t=>`<span>${t}</span>`).join("");
  $("#chart1").innerHTML='<div class="cols">'+data.map(x=>{
    const stack=["ساده","متوسط","پیشرفته"].map(l=>{
      if(!x.d[l])return"";
      const pct=x.d.total?x.d[l]/x.d.total*100:0;
      const h=C.mode==="pct"?pct/100*H:x.d[l]/max*H;
      const lab=C.mode==="pct"?Math.round(pct)+"٪":"";
      return `<i style="height:${h}px;background:${LVLC[l]}" data-tip="${l}: ${fa(x.d[l])} (${fa(Math.round(pct))}٪)">${h>15&&lab?`<span>${fa(lab)}</span>`:""}</i>`;
    }).join("");
    return `<div class="col" data-tip="${fa(x.y)} — مجموع ${fa(x.d.total)} کلمه"><div class="tot">${C.mode==="abs"?fa(x.d.total):"۱۰۰٪"}</div>
      <div class="stack">${stack}</div><div class="yr">${fa(x.y)}</div></div>`;
  }).join("")+'</div>';

  topList=WORDS.map(w=>{
    const m=w.occ.filter(o=>o[0]>=C.from&&o[0]<=C.to&&(!C.exam||o[1]===C.exam));
    return {w,y:new Set(m.map(o=>o[0])).size,t:m.length};
  }).map(x=>({...x,n:C.metric==="times"?x.t:x.y}))
    .filter(x=>x.n>0).sort((a,b)=>b.n-a.n||b.y-a.y||a.w.w.localeCompare(b.w.w));
  const list=C.top?topList.slice(0,C.top):topList;
  const mx=Math.max(1,...list.map(x=>x.n));
  $("#c2sub").innerHTML=[C.exam||"همه رشته‌ها",`${fa(C.from)} تا ${fa(C.to)}`,`${fa(topList.length)} کلمه`,`معیار: ${C.metric==="times"?"تعداد کل تکرار":"تعداد سال‌های یکتا"}`].map(t=>`<span>${t}</span>`).join("");
  $("#chart2").innerHTML='<div class="hbars">'+list.map((x,i)=>{
    const pct=x.n/mx*100;
    const alpha=(0.35+0.65*(x.n/mx)).toFixed(2);
    return `<div class="hbar" data-i="${WORDS.indexOf(x.w)}" data-tip="${x.w.w}">
      <span class="rk">${fa(i+1)}</span>
      <span class="dot" style="background:${LVLC[x.w.lvl]}" data-tip="${x.w.lvl}"></span>
      <span class="lbl en">${x.w.w}</span><span class="hpos en">${x.w.pos}</span>
      <span class="track"><span class="fill" style="width:${Math.max(pct,2)}%;opacity:${alpha}"></span></span>
      <span class="val">${fa(x.n)} ${C.metric==="times"?"بار":"سال"}</span></div>`;
  }).join("")+'</div>';
}
["cExam","cFrom","cTo","cTop"].forEach(id=>$("#"+id).addEventListener("change",e=>{
  const k={cExam:"exam",cFrom:"from",cTo:"to",cTop:"top"}[id];
  C[k]=(id==="cExam")?e.target.value:+e.target.value;renderCharts();
}));
$("#cMetric").addEventListener("click",e=>{const b=e.target.closest("[data-m2]");if(!b)return;
  document.querySelectorAll("#cMetric button").forEach(x=>x.classList.remove("on"));b.classList.add("on");
  C.metric=b.dataset.m2;renderCharts()});
$("#cMode").addEventListener("click",e=>{const b=e.target.closest("[data-m]");if(!b)return;
  document.querySelectorAll("#cMode button").forEach(x=>x.classList.remove("on"));b.classList.add("on");C.mode=b.dataset.m;renderCharts()});
$("#chart2").addEventListener("click",e=>{const b=e.target.closest("[data-i]");if(b)openDetail(WORDS[+b.dataset.i])});
$("#addTop").addEventListener("click",()=>{
  const list=C.top?topList.slice(0,C.top):topList;
  if(list.length)askConfirm(`${fa(list.length)} کلمه به دک اضافه شود؟`,()=>{list.forEach(x=>deck.add(x.w.w));render();toast("اضافه شد.")});
});

/* ---------------- مرور ---------------- */
/* ---------------- مرور با الگوریتم SM-2 (انکی) ---------------- */
let session=[],pos=0,flipped=false,mode="en";
const sched={};      // key -> {s, d, iv, reps, lapses, due, last}  ← FSRS
/* «روز» در زمان‌بندی مرور = شماره‌ی روز تقویمی واقعی (از ۱۹۷۰، به وقت محلی).
   تا نسخه‌ی دمو این یک شمارنده بود که هیچ‌جا جلو نمی‌رفت و همیشه ۰ می‌ماند؛
   پس کارتی با بازه‌ی دو روزه هیچ‌وقت دوباره سررسید نمی‌شد. */
function todayIdx(){const d=new Date();return Math.floor((d.getTime()-d.getTimezoneOffset()*60000)/86400000)}
function dayOfDate(str){
  const m=/^(\d{4})-(\d{2})-(\d{2})/.exec(String(str||""));
  return m?Math.floor(Date.UTC(+m[1],+m[2]-1,+m[3])/86400000):null;
}
let dayNow=todayIdx();
/* اگر صفحه از نیمه‌شب رد شد، کارت‌های امروز خودشان ظاهر شوند */
setInterval(()=>{const t=todayIdx();if(t!==dayNow){dayNow=t;newToday=0;try{refreshAll()}catch(e){console.error(e)}}},60000);

/* ================= FSRS-6 =================
   جایگزین SM-2. سه کمیت به‌جای یک ضریب:
     S (پایداری) — چند روز طول می‌کشد تا احتمال یادآوری به ۹۰٪ برسد
     D (دشواری)  — بین ۱ و ۱۰، ذاتیِ خود کارت
     R (بازیابی‌پذیری) — احتمال اینکه همین حالا یادتان بیاید
   بازه طوری چیده می‌شود که R دقیقاً سر موعد به هدف برسد.
   وزن‌ها همان پیش‌فرض‌های FSRS4Anki v6.1.1 هستند.
   در نسخه‌ی سروری، محاسبه در Fsrs.php انجام می‌شود و همین‌جا فقط
   برای نمایش فوری روی چهار دکمه تکرار شده است. */
const FSRS_W=[0.212,1.2931,2.3065,8.2956,6.4133,0.8334,3.0194,0.001,1.8722,0.1666,
              0.796,1.4835,0.0614,0.2629,1.6483,0.6014,1.8729,0.5425,0.0912,0.0658,0.1542];
const FSRS_RR=0.90;                       // هدف نگهداشت
const FSRS_DECAY=-FSRS_W[20];
const FSRS_FACTOR=Math.pow(0.9,1/FSRS_DECAY)-1;
const FSRS_MAXI=3650;

function card(key){
  return sched[key]||(sched[key]={s:null,d:null,iv:0,reps:0,lapses:0,due:0,last:null});
}
const fCd=d=>Math.min(Math.max(+d.toFixed(2),1),10);
const fInitS=r=>Math.max(FSRS_W[r-1],0.1);
const fInitD=r=>fCd(FSRS_W[4]-Math.exp(FSRS_W[5]*(r-1))+1);
function fRetr(t,s){ return s>0 ? Math.pow(1+FSRS_FACTOR*t/s,FSRS_DECAY) : 0 }
/* پراکندگی — بازه را چند درصد جابه‌جا می‌کند تا کارت‌هایی که یک روز با هم
   شروع شده‌اند تا ابد با هم برنگردند. دامنه‌ها همان انکی است.
   تصادفیِ تکرارپذیر از روی کلید کارت: عددی که روی دکمه می‌بینید همان است
   که ثبت می‌شود. باید مو‌به‌مو با Fsrs::fuzz() در سرور یکی بماند. */
function fCrc32(str){
  let c, crc=0xFFFFFFFF;
  for(let i=0;i<str.length;i++){
    c=(crc^str.charCodeAt(i))&0xFF;
    for(let k=0;k<8;k++) c = c&1 ? (c>>>1)^0xEDB88320 : c>>>1;
    crc=(crc>>>8)^c;
  }
  return (crc^0xFFFFFFFF)>>>0;
}
function fFuzz(ivl,seed){
  if(ivl<3||!seed)return ivl;
  let d = ivl<7 ? ivl*0.15 : (ivl<20 ? ivl*0.10 : ivl*0.05);
  d = Math.max(1,d);
  const ratio=(fCrc32(seed+'|'+ivl)%1000)/999;
  return Math.max(1, ivl+Math.round((ratio*2-1)*d));
}
/* سقف کنکور: هیچ کارتی نباید بعد از جلسه برگردد. روز آخر کنار گذاشته
   می‌شود چون مرور در خودِ روز کنکور فایده‌ای ندارد. */
function fHorizon(){
  /* daysToExam پایین‌تر در فایل تعریف شده ولی تابع است، پس هنگام صدا زدن
     (که همیشه بعد از بارگذاری کامل است) در دسترس است. */
  if(typeof daysToExam!=='function')return null;
  const d=daysToExam()-1;
  return d>0 ? d : null;
}
function fIvl(s,seed){
  const i=s/FSRS_FACTOR*(Math.pow(FSRS_RR,1/FSRS_DECAY)-1);
  let ivl=Math.max(1,Math.min(FSRS_MAXI,Math.round(i)));
  ivl=fFuzz(ivl,seed);
  const h=fHorizon();
  if(h!==null)ivl=Math.min(ivl,h);
  return Math.max(1,ivl);
}
function fNextD(d,r){
  const dd=-FSRS_W[6]*(r-3);
  let n=d+dd*(10-d)/9;                                  // linear damping
  n=FSRS_W[7]*fInitD(4)+(1-FSRS_W[7])*n;                // mean reversion
  return fCd(n);
}
function fRecallS(d,s,r,rt){
  const hp=rt===2?FSRS_W[15]:1, eb=rt===4?FSRS_W[16]:1;
  return s*(1+Math.exp(FSRS_W[8])*(11-d)*Math.pow(s,-FSRS_W[9])
    *(Math.exp((1-r)*FSRS_W[10])-1)*hp*eb);
}
function fForgetS(d,s,r){
  const sMin=s/Math.exp(FSRS_W[17]*FSRS_W[18]);
  return Math.min(FSRS_W[11]*Math.pow(d,-FSRS_W[12])
    *(Math.pow(s+1,FSRS_W[13])-1)*Math.exp((1-r)*FSRS_W[14]), sMin);
}
function fShortS(s,rt){
  let si=Math.exp(FSRS_W[17]*(rt-3+FSRS_W[18]))*Math.pow(s,-FSRS_W[19]);
  if(rt>=3)si=Math.max(si,1);
  return s*si;
}
/** یک مرور را روی یک کپی حساب می‌کند و وضعیت تازه را برمی‌گرداند */
function fsrsNext(c,rating,elapsed,seed){
  rating=Math.max(1,Math.min(4,rating));
  let s,d;
  if(c.s==null){ s=fInitS(rating); d=fInitD(rating); }
  else{
    const R=fRetr(elapsed,c.s);
    d=fNextD(c.d,rating);
    if(elapsed<1)        s=fShortS(c.s,rating);
    else if(rating===1)  s=fForgetS(c.d,c.s,R);
    else                 s=fRecallS(c.d,c.s,R,rating);
  }
  return {s:+s.toFixed(4), d:+d.toFixed(4), iv:fIvl(s,seed)};
}
function elapsedOf(c){ return c.last==null ? 0 : Math.max(0, dayNow-c.last) }

/** بازه‌ای که هر درجه می‌سازد — برای نوشتن روی چهار دکمه */
/* کلید پراکندگی باید مو‌به‌مو همان چیزی باشد که سرور می‌سازد: «w:<id>» یا
   «q:<id>». کلید کارت («w|convenient») شناسه‌ی سرور نیست، پس با keyToItem
   تبدیلش می‌کنیم. اگر تبدیل ممکن نبود بی‌پراکندگی حساب می‌شود — بدتر از
   عددِ ناهمخوان نیست. */
function fuzzSeed(key){
  try{
    const it = ZABAN.keyToItem ? ZABAN.keyToItem(key) : null;
    return it && it.id ? it.t + ':' + it.id : '';
  }catch(e){ return '' }
}
function previewIv(c,r,key){
  const sd=fuzzSeed(key);
  const n=fsrsNext(c,r,elapsedOf(c),sd);
  if(r===4){ const g=fsrsNext(c,3,elapsedOf(c),sd).iv; return Math.max(n.iv,g+1) }
  return n.iv;
}

/* ← نقطه‌ی اتصال ۲: بازه را سرور حساب می‌کند.
   محاسبه‌ی محلی می‌ماند تا کارت فوراً حرکت کند، ولی نتیجه‌ی سرور رویش می‌نشیند.
   حالا هر دو طرف یک الگوریتم را اجرا می‌کنند، پس اختلافی نمی‌ماند. */
function applyRating(key,r){
  const c=card(key);
  const el=elapsedOf(c);
  const n=fsrsNext(c,r,el,fuzzSeed(key));

  if(r===1){ c.lapses++; c.reps=0 } else c.reps++;
  c.s=n.s; c.d=n.d; c.iv=n.iv;
  c.due=dayNow+c.iv;
  c.last=dayNow;

  ZABAN.review(key,r).then(res=>{
    if(!res)return;
    c.s=res.s; c.d=res.d; c.iv=res.iv; c.serverDue=res.due;
    const sd=dayOfDate(res.due); if(sd!=null)c.due=sd;     /* حرف آخر با سرور */
    if(res.mastered)toast("این کارت مسلط شد.");
    if(typeof drawCard==="function"&&document.querySelector("#review.open"))updCardChip(key);
  }).catch(()=>{});

  return c;
}
/* ← نقطه‌ی اتصال ۲: بازه را سرور حساب می‌کند.
   محاسبه‌ی محلی می‌ماند تا کارت فوراً حرکت کند، ولی نتیجه‌ی سرور رویش می‌نشیند.
   اگر این دو با هم فرق داشته باشند، حرف آخر با سرور است. */
function updCardChip(key){
  const el=document.querySelector("[data-cardchip='"+key+"']");
  if(el&&typeof cardChip==="function")el.outerHTML=cardChip(card(key));
}
const ivLabel=d=>d===0?"۱۰ دقیقه":d===1?"۱ روز":d<30?fa(d)+" روز":fa(Math.round(d/30))+" ماه";

/* ===== صف امروز =====
   تا نسخه‌ی دمو «مرور امروز» بیست کارت تصادفی از کل دک برمی‌داشت و به
   موعد کارت‌ها نگاه نمی‌کرد؛ عدد روی دکمه هم اندازه‌ی کل دک بود. حالا مثل
   انکی: اول سررسیدها (دیرکردترین اول)، بعد کارت تازه تا سقف روزانه، و کل
   صف تا سقف روز. دکمه‌ی بالا، کارت داشبورد و خود جلسه همه از همین. */
/* سقف کارت تازه‌ی روز: انتخاب خود دانشجو در پروفایل، وگرنه پیش‌فرض مدیر (از /me)؛
   ۲۰ فقط وقتی سرور چیزی نگفته باشد. var عمدی: بدون TDZ اگر زودتر صدا زده شود. */
var DAY_CAP=60, NEW_PER_DAY=window.NEW_PER_DAY_CAP||20;
var newToday=window.NEW_TODAY||0;     /* از سرور؛ با هر کارت تازه‌ی مرورشده یکی بالا می‌رود */
function todayQueue(kind){            /* kind: "w" | "q" | undefined = همه */
  const cards=allCards().filter(c=>!kind||c.t===kind);
  const dueAll=cards.filter(c=>c.bucket!=="new"&&sched[c.key]&&(sched[c.key].due||0)<=dayNow)
                    .sort((a,b)=>(sched[a.key].due||0)-(sched[b.key].due||0));
  const freshAll=cards.filter(c=>c.bucket==="new");
  const freshRoom=Math.max(0,NEW_PER_DAY-newToday);
  const due=dueAll.slice(0,DAY_CAP);
  const fresh=freshAll.slice(0,Math.min(freshRoom,DAY_CAP-due.length));
  return {list:due.concat(fresh), due:due.length, fresh:fresh.length,
          dueAll:dueAll.length, freshAll:freshAll.length, freshRoom};
}
function updDeckCount(){const el=$("#deckCount"); if(el)el.textContent=fa(todayQueue().list.length)}
function buildSession(){
  const kind = mode==="tests" ? "q" : mode==="all" ? undefined : "w";
  return todayQueue(kind).list.map(c=>c.t==="w"
    ? {t:"w",w:c.w,key:c.key}
    : {t:"q",y:c.y,e:c.e,q:c.q,key:c.key});
}
/* روز بعدی که کارتی سررسید می‌شود — برای پیام «امروز تمام شد» */
function nextDueIn(){
  let min=null;
  Object.values(sched).forEach(s=>{if(s&&s.s!=null&&(s.due||0)>dayNow&&(min===null||s.due<min))min=s.due});
  return min===null?null:min-dayNow;
}
function updModeHint(){
  const el=$("#modeHint");if(!el)return;
  el.textContent=mode==="tests"
    ? `${fa(deckQ.size)} کارت تست در دک شماست`
    : mode==="all"
      ? `${fa(deck.size)} کلمه و ${fa(deckQ.size)} تست، درهم و تصادفی مرور می‌شوند`
      : `${fa(deck.size)} کارت کلمه در دک شماست`+(deckQ.size?` · ${fa(deckQ.size)} کارت تست با حالت «تست‌ها» یا «همه» مرور می‌شود`:"");
}
function startReview(){
  session=buildSession();
  if(!session.length){
    const empty = mode==="tests" ? !deckQ.size : mode==="all" ? !(deck.size+deckQ.size) : !deck.size;
    if(empty){
      toast((mode==="tests"?"در حالت «تست‌ها» فقط کارت‌های تست مرور می‌شوند و الان تستی در دک نیست.":"در این حالت فقط کارت‌های کلمه مرور می‌شوند و الان کلمه‌ای در دک نیست.")
        +" (دک شما: "+fa(deck.size)+" کلمه، "+fa(deckQ.size)+" تست)");
    }else{
      const n=nextDueIn();
      toast("مرور امروز تمام شد."+(n!=null?` کارت بعدی ${n===1?"فردا":fa(n)+" روز دیگر"} سررسید می‌شود.`:"")
        +(newToday>=NEW_PER_DAY?` سقف ${fa(NEW_PER_DAY)} کارت تازه‌ی امروز هم پر شده است.`:""));
    }
    return}
  /* سررسیدها اول، تازه‌ها بعد؛ ترتیب داخل هر گروه تصادفی. هر نوبت ۲۰ کارت. */
  const isNew=c=>{const s=sched[c.key];return !s||!(s.seen>0)};
  const shuf=a=>a.sort(()=>Math.random()-.5);
  session=shuf(session.filter(c=>!isNew(c))).concat(shuf(session.filter(isNew))).slice(0,20);
  pos=0;flipped=false;$("#review").classList.add("open");drawCard();syncUrl(true);
}
$("#startReview").addEventListener("click",startReview);
$("#startReview2").addEventListener("click",startReview);
$("#modes").addEventListener("click",e=>{const b=e.target.closest("[data-mode]");if(!b)return;
  document.querySelectorAll("#modes button").forEach(x=>x.classList.remove("on"));
  b.classList.add("on");mode=b.dataset.mode;updModeHint();
  if($("#review").classList.contains("open")){session=buildSession();pos=0;flipped=false;drawCard()}});

function cardActions(c){
  if(c.t==="w"){
    const w=c.w,d=deck.has(w.w),st=star.has(w.w);
    return `<div class="cacts">
      <span class="grp"><i>دک</i><button class="${d?"on":""}" data-toggle="${w.w}">${d?"✓ کلمه":"کلمه"}</button></span>
      <span class="grp st"><i>منتخب</i><button class="${st?"on":""}" data-star="${w.w}">${st?"★ کلمه":"کلمه"}</button></span>
      <span class="grp"><i>یادداشت</i><button class="${nHas("w:"+w.w)?"on":""}" data-note="w:${w.w}" data-nt="یادداشت — ${w.w}">✎</button></span>
      <span class="grp"><i>گزارش</i><button class="${rAnswered("w:"+w.w)?"on":""}" data-rep="w:${w.w}" data-rt="${w.w} — ${w.fa}">⚑</button></span></div>`;
  }
  const k=qKey(c.y,c.e,c.q);
  return `<div class="cacts">
    <span class="grp"><i>دک</i><button class="${deckQ.has(k)?"on":""}" data-qdeck="${k}">${deckQ.has(k)?"✓ تست":"تست"}</button></span>
    <span class="grp st"><i>منتخب</i><button class="${starQ.has(k)?"on":""}" data-qstar2="${k}">${starQ.has(k)?"★ تست":"تست"}</button></span>
    <span class="grp"><i>یادداشت</i><button class="${nHas("q:"+k)?"on":""}" data-note="q:${k}" data-nt="یادداشت این تست">✎</button></span>
    <span class="grp"><i>گزارش</i><button class="${rAnswered("q:"+k)?"on":""}" data-rep="q:${k}" data-rt="سؤال ${fa(c.q)} — کنکور ${fa(c.y)} ${c.e}">⚑</button></span></div>`;
}
function faceHTML(c,back){
  if(c.t==="w"){
    const w=c.w;
    if(!back){
      return mode==="fa"
        ? `<div>${cardActions(c)}<div class="rev" style="font-size:24px;margin-top:6px">${w.fa}</div><div class="hint">معادل انگلیسی را به یاد بیاورید</div></div>`
        : `<div>${cardActions(c)}<div class="big en" style="margin-top:6px">${w.w}</div><div style="margin-top:8px"><span class="badge b-freq">${fa(w.freq)} سال در کنکور</span></div></div>`;
    }
    return `<div>${cardActions(c)}<div class="big en" style="font-size:23px;margin-top:6px">${w.w}</div>
      <div class="rev" style="margin-top:6px">${w.fa}</div>
      <div style="font-size:12px;color:var(--ink-3)">${w.pos}${w.forms?" · "+w.forms.join(" / "):""}</div>
      ${(()=>{ const ex=examplesOf(w,()=>{if(session[pos]===c)paintCard()});
        return typeof ex==="string" ? `<div class="hint" style="margin-top:10px">${ex}</div>`
          : ex.slice(0,2).map(e=>`<div class="ex" style="text-align:right;margin-top:10px"><div class="s en">${e[0]}</div><div class="t">${e[1]}</div></div>`).join(""); })()}</div>`;
  }
  const Q=makeQ(c.y,c.e,c.q);
  if(!Q){
    if(!QREADY[c.e]){
      whenQuestions(c.e).then(()=>{if(session[pos]===c)paintCard()}).catch(e=>console.error(e));
      return `<div>${cardActions(c)}<div class="hint">در حال بارگذاری سؤال…</div></div>`;
    }
    return `<div>${cardActions(c)}<div class="hint">سؤال ${fa(c.q)} کنکور ${fa(c.y)} ${c.e} هنوز در بانک سؤال وارد نشده است.</div></div>`;
  }
  const head=`<div class="qmetabox">سؤال ${fa(c.q)} · کنکور ${fa(c.y)} · ${c.e} · ${Q.sec}${Q.p?" "+fa(Q.p):""}</div>
    <div class="qstem">${Q.stem.replace("____","<u>____</u>")}</div>`;
  /* کارت تست در دک هم باید مثل بقیه‌ی پلتفرم گزینه‌زدنی و رد‌گزینه‌دار باشد.
     cardPick و cardOut روی خود کارت جلسه می‌نشینند، نه سراسری. */
  if(!back){
    return `<div>${cardActions(c)}${head}
      <div class="qopts3">${Q.opts.map((o,i)=>{
        const struck=!!(c.out&&c.out[i]), sel=c.pick===i;
        return `<div class="qopt3 ${struck?"out":""} ${sel?"sel":""}">
          <button class="pk" data-cpick="${i}"><span class="nb">${fa(i+1)}</span><span class="tx en">${o.w}</span></button>
          <button class="rd" data-cout="${i}">${struck?"پاکسازی":"رد گزینه"}</button>
        </div>`}).join("")}</div>
      ${c.pick!=null?`<div class="hint">گزینه‌ی ${fa(c.pick+1)} را زده‌اید — کارت را برگردانید تا پاسخ را ببینید</div>`
                     :`<div class="hint">یک گزینه بزنید یا کارت را برگردانید</div>`}</div>`;
  }
  if(Q.ans==null){
    if(ANS[Q.qid]===undefined&&!ANS_LOCK[Q.qid])
      fetchAns([Q.qid]).then(()=>{if(session[pos]===c)paintCard()}).catch(e=>console.error(e));
    return `<div>${cardActions(c)}${head}<div class="hint">${ansWaitMsg(Q)}</div></div>`;
  }
  return `<div>${cardActions(c)}${head}${Q.opts.map((o,i)=>{
      let k="";
      if(i===Q.ans)k="ok"; else if(c.pick===i)k="bad";
      return `<div class="qopt2 ${k}">${fa(i+1)}) ${o.w}</div>`}).join("")}
    ${c.pick!=null?`<div class="ex-vd ${c.pick===Q.ans?"ok":"bad"}" style="margin:10px 0;display:inline-block">${
        c.pick===Q.ans?"پاسخ شما درست بود":"پاسخ شما غلط بود"}</div>`:""}
    <div class="ansbox"><b>پاسخ درست: گزینه‌ی ${fa(Q.ans+1)} — ${Q.opts[Q.ans].w}</b>
      <div>${Q.opts[Q.ans].fa}</div>${Q.stemFa?`<div>${Q.stemFa}</div>`:""}</div>
    <div class="label" style="margin-top:12px;text-align:right">کلمات این سؤال</div>
    ${Q.ws.map(x=>`<div class="wline" data-w="${x.w}"><b class="en">${x.w}</b><span>${x.fa}</span></div>`).join("")}</div>`;
}
function paintCard(){
  const rate=$("#rRate"),opts=$("#rOpts"),show=$("#rShow");
  if(pos>=session.length){
    $("#rCard").innerHTML=`<div><div style="font-size:19px;font-weight:500">مرور این دسته تمام شد</div>
      <div class="hint">${fa(session.length)} کارت مرور شد.</div></div>`;
    rate.hidden=opts.hidden=show.hidden=true;
    $("#rIdx").textContent="";$("#rBar").style.width="100%";renderStats();return;
  }
  const c=session[pos];
  $("#rIdx").textContent=`کارت ${fa(pos+1)} از ${fa(session.length)} · ${c.t==="w"?(mode==="fa"?"فارسی ← انگلیسی":mode==="quiz"?"چهارگزینه‌ای":"انگلیسی ← فارسی"):"کارت تست"}`;
  $("#rBar").style.width=(pos/session.length*100)+"%";
  if(c.t==="w"&&mode==="quiz"&&!flipped){
    /* گزینه‌ها یک بار برای هر کارت انتخاب می‌شوند (رسیدن معنی‌ها کارت را دوباره می‌کشد؛
       انتخاب دوباره یعنی گزینه‌ی تازه، معنی تازه، و حلقه). معنی همه‌ی کلمات هم خوانده نمی‌شود. */
    const w=c.w;
    if(!c._quiz){
      const pool=WORDS.filter(x=>x!==w&&x.pos===w.pos);
      c._quiz=[w].concat(pool.sort(()=>Math.random()-.5).slice(0,3)).sort(()=>Math.random()-.5);
    }
    const all=c._quiz;
    $("#rCard").innerHTML=`<div>${cardActions(c)}<div class="big en" style="margin-top:6px">${w.w}</div><div class="hint">معنی درست را انتخاب کنید</div></div>`;
    show.hidden=true;rate.hidden=true;opts.hidden=false;
    opts.innerHTML=all.map(x=>`<button data-ok="${x===w?1:0}">${x.fa}</button>`).join("");
    return;
  }
  opts.hidden=true;
  $("#rCard").innerHTML=faceHTML(c,flipped)+(flipped?'<button class="flipback" data-unflip="1">↺ بازگشت به روی کارت</button>':"");
  show.hidden=flipped;rate.hidden=!flipped;
  if(flipped&&!$("#rBackBtn"))0;
  if(flipped){
    const st=card(c.key);
    document.querySelectorAll("#rRate button").forEach(b=>{
      /* کلید کارت لازم است: پراکندگی از روی آن حساب می‌شود، پس بدون آن
         عددِ دکمه با چیزی که بعد از زدنش ثبت می‌شود فرق می‌کرد. */
      b.querySelector("i").textContent=ivLabel(previewIv(st,+b.dataset.r,c.key));
    });
  }
}
function drawCard(animate){
  if(!animate)return paintCard();
  const el=$("#rCard");el.classList.add("half");
  setTimeout(()=>{paintCard();el.classList.remove("half")},220);
}
$("#rShow").addEventListener("click",()=>{flipped=true;drawCard(true)});
$("#rCard").addEventListener("click",e=>{
  const qs=e.target.closest("[data-qstar2]");
  if(qs){const k=qs.dataset.qstar2;starQ.has(k)?starQ.delete(k):starQ.add(k);refreshAll();paintCard();
    e.stopPropagation();return}
  /* این دکمه در مرور هیچ هندلری نداشت — زدنش فقط کارت را برمی‌گرداند.
     همان باگی که در دک به آن خوردید. */
  const qd=e.target.closest("[data-qdeck]");
  if(qd){const k=qd.dataset.qdeck;deckQ.has(k)?deckQ.delete(k):deckQ.add(k);refreshAll();paintCard();
    e.stopPropagation();return}
  const cp=e.target.closest("[data-cpick]");
  if(cp){const c=session[pos];c.pick=+cp.dataset.cpick;paintCard();e.stopPropagation();return}
  const co=e.target.closest("[data-cout]");
  if(co){const c=session[pos];c.out=c.out||{};const i=+co.dataset.cout;
    c.out[i]=!c.out[i];paintCard();e.stopPropagation();return}
});
$("#rCard").addEventListener("click",e=>{
  const t=e.target.closest("[data-toggle]");
  if(t){const k=t.dataset.toggle;deck.has(k)?deck.delete(k):deck.add(k);refreshAll();paintCard();return}
  const st=e.target.closest("[data-star]");
  if(st){const k=st.dataset.star;star.has(k)?star.delete(k):star.add(k);refreshAll();paintCard();return}
  if(e.target.closest("[data-unflip]")){flipped=false;drawCard(true);return}
  const wl=e.target.closest("[data-w]");
  if(wl){const w=WORDS.find(x=>x.w===wl.dataset.w);
    if(w){navStack=[];curView=null;showView({t:"review"},false);showView({t:"word",w},true)}
    return}
  if(e.target.closest("[data-qdeck],[data-qstar2],[data-toggle],[data-star],[data-cpick],[data-cout],[data-note]"))return;
  flipped=!flipped;drawCard(true);
});
$("#rRate").addEventListener("click",e=>{
  const b=e.target.closest("[data-r]");if(!b)return;
  const c=session[pos],r=+b.dataset.r;
  applyRating(c.key,r);
  if(c.t==="w"&&r===1)miss[c.w.w]=(miss[c.w.w]||0)+1;
  if(r===1)session.push(c);                     // «یادم نبود» همان جلسه دوباره می‌آید
  pos++;flipped=false;drawCard();
});
$("#rOpts").addEventListener("click",e=>{
  const b=e.target.closest("[data-ok]");if(!b||$("#rOpts").dataset.done)return;
  $("#rOpts").dataset.done="1";const c=session[pos],w=c.w;
  if(b.dataset.ok==="1"){b.classList.add("ok");applyRating(c.key,3)}
  else{b.classList.add("no");miss[w.w]=(miss[w.w]||0)+1;applyRating(c.key,1);
    [...$("#rOpts").children].forEach(x=>{if(x.textContent===w.fa)x.classList.add("ok")})}
  setTimeout(()=>{delete $("#rOpts").dataset.done;pos++;flipped=false;drawCard()},1100);
});
$("#closeReview").addEventListener("click",()=>{$("#review").classList.remove("open");renderStats();syncUrl(true)});
$("#review").addEventListener("click",e=>{if(e.target.id==="review"){$("#review").classList.remove("open");syncUrl(true)}});




/* ================= ذخیره‌ی محلی ================= */
/* ← نقطه‌ی اتصال ۱: به‌جای localStorage، حافظه‌ی همگام با سرور */
const LS = ZABAN.LS;

/* ================= گزارش‌ها =================
   هر کلمه و هر تست می‌تواند گفت‌وگوی گزارش داشته باشد.
   کلید مثل یادداشت‌ها: w:<کلمه>  یا  q:<سال>|<رشته>|<شماره>
   ساختار: { topic, state:"open"|"answered", msgs:[{by:"me"|"admin", body, ts}] }
   از GET /api/reports می‌آیند (app-bootstrap در mem می‌گذارد) و هر تغییر
   اول به سرور می‌رود، بعد جواب سرور جای نسخه‌ی محلی می‌نشیند. */
const REPORTS = ZABAN.LS.get("zban_reports") || {};
function rHas(k){return !!REPORTS[k]}
function rAnswered(k){return !!(REPORTS[k]&&REPORTS[k].state==="answered")}
function rBtn(k,title){
  const cls = rAnswered(k)?"repanswered":(rHas(k)?"hasrep":"");
  const tip = rAnswered(k)?"به گزارش شما پاسخ داده شده — برای دیدن بزنید"
            : rHas(k)?"گزارش شما ثبت شده، در انتظار پاسخ"
            : "گزارش اشکال";
  return `<button class="ib ${cls}" data-rep="${k}" data-rt="${title}" data-tip="${tip}">⚑</button>`;
}
let _rk=null,_rtopic="";
/* کاربر پاسخ مدیر را دید: نشان «تازه» خاموش، و به سرور هم خبر می‌دهیم
   وگرنه با رفرش بعدی دوباره روشن می‌شد. */
function markRepSeen(k){
  const R=REPORTS[k];
  if(!R||R.state!=="answered"||R.seen)return;
  R.seen=true;saveReports();ZABAN.reportSeen(R.id);updRepBadge();
}
function openReport(k,title){
  _rk=k;
  markRepSeen(k);
  $("#repTitle").textContent = k.charAt(0)==="q" ? "گزارش برای این سؤال" : "گزارش برای این کلمه";
  $("#repSub").textContent = title||"";
  _rtopic="";
  const R=REPORTS[k];
  if(R){
    $("#repThread").innerHTML=`
      <div class="row-f" style="margin-bottom:9px">
        <span class="rep-topic">${R.topic}</span>
        <span class="rep-state ${R.state}" style="margin-right:auto">${
          R.state==="answered"?"پاسخ داده شده":"در انتظار پاسخ"}</span>
      </div>
      <div class="rep-thread">${R.msgs.map(m=>`
        <div class="rep-msg ${m.by}">
          <div class="who"><b>${m.by==="admin"?"پشتیبانی پلتفرم":"شما"}</b><span>${jdate(m.ts)}</span></div>
          ${String(m.body).replace(/</g,"&lt;")}
        </div>`).join("")}</div>`;
    $("#repForm").hidden=true; $("#repReply").hidden=false; $("#repRt").value="";
    $("#repFootNew").hidden=true; $("#repFootReply").hidden=false;
  }else{
    $("#repThread").innerHTML="";
    $("#repForm").hidden=false; $("#repReply").hidden=true;
    $("#repFootNew").hidden=false; $("#repFootReply").hidden=true;
    $("#repTa").value=""; $("#repCount").textContent=fa(0);
    document.querySelectorAll("#repTopics button").forEach(b=>b.classList.remove("on"));
  }
  $("#repM").classList.add("open");
  setTimeout(()=>{const t=$("#repTa");if(t&&!$("#repForm").hidden)t.focus()},80);
}
function closeReport(){$("#repM").classList.remove("open");_rk=null;syncUrl(true)}
function saveReports(){ZABAN.LS.set("zban_reports",REPORTS)}

$("#repClose").addEventListener("click",closeReport);
$("#repCancel").addEventListener("click",closeReport);
$("#repM").addEventListener("click",e=>{if(e.target.id==="repM")closeReport()});

$("#repTopics").addEventListener("click",e=>{
  const b=e.target.closest("[data-topic]"); if(!b)return;
  _rtopic=b.dataset.topic;
  [...$("#repTopics").children].forEach(x=>x.classList.toggle("on",x===b));
});
$("#repTa").addEventListener("input",e=>{$("#repCount").textContent=fa(e.target.value.length)});

$("#repSend").addEventListener("click",async()=>{
  const k=_rk, topic=_rtopic, body=$("#repTa").value.trim();
  if(!topic){toast("موضوع گزارش را انتخاب کنید.");return}
  if(!body){toast("پیام گزارش خالی است.");return}
  const btn=$("#repSend"); btn.disabled=true;
  try{
    REPORTS[k]=await ZABAN.reportNew(k,topic,body);
    saveReports();closeReport();refreshAll();
    toast("گزارش شما ثبت شد. پاسخ پشتیبانی روی همین دکمه نشان داده می‌شود.");
  }catch(e){
    console.error(e);
    toast("گزارش ثبت نشد. اتصال را بررسی کنید و دوباره بفرستید.");
  }finally{btn.disabled=false}
});

$("#repReplyBtn").addEventListener("click",async()=>{
  const k=_rk, body=$("#repRt").value.trim();
  if(!body){toast("پیام خالی است.");return}
  if(!REPORTS[k]||!REPORTS[k].id){toast("این گزارش هنوز روی سرور ثبت نشده. صفحه را تازه کنید.");return}
  const btn=$("#repReplyBtn"); btn.disabled=true;
  try{
    REPORTS[k]=await ZABAN.reportReply(REPORTS[k].id,body);
    saveReports();refreshAll();
    if(_rk===k)openReport(k,$("#repSub").textContent);
    toast("پاسخ شما به همین گفت‌وگو اضافه شد.");
  }catch(e){
    console.error(e);
    toast("پیام فرستاده نشد. اتصال را بررسی کنید و دوباره بفرستید.");
  }finally{btn.disabled=false}
});

$("#repNew").addEventListener("click",()=>{
  $("#repForm").hidden=false;$("#repReply").hidden=true;
  $("#repFootNew").hidden=false;$("#repFootReply").hidden=true;
  _rtopic="";$("#repTa").value="";$("#repCount").textContent=fa(0);
  document.querySelectorAll("#repTopics button").forEach(b=>b.classList.remove("on"));
});

/* ===== آمار جمعی =====
   همان تب پلتفرم مرور، ولی واحدش اینجا «تست» است نه «نکته».
   عددها از qGlobal می‌آیند — همان چیزی که خط زیر پاسخ هم از آن می‌خواند. */
const CROWD_BAND={
  weak:  {label:"سخت برای بقیه", color:"#c05a35", test:v=>v<0.45},
  mid:   {label:"متوسط",         color:"#d99521", test:v=>v>=0.45&&v<0.7},
  strong:{label:"آسان برای بقیه",color:"#3f8f6b", test:v=>v>=0.7},
};
const CROWD={band:"weak", kind:"q", year:"", exam:"", page:0, per:20};

/* ---------- تست‌ها ---------- */
/* سؤال‌های همه‌ی رشته‌های خریده‌شده لازم است، نه فقط رشته‌ی پروفایل (که
   موقع باز شدن صفحه بارگذاری می‌شود). هر رشته یک بار؛ ZABAN.questions کش دارد. */
function ensureOwnedQuestions(){
  (window.OWNED_EXAMS||[]).forEach(c=>{try{ZABAN.questions(c).catch(()=>{})}catch(e){}});
}
document.addEventListener("zaban:questions-ready",()=>{
  const t=$("#tab-crowd"); if(t&&!t.hidden)renderCrowd();
});
function crowdQ(){
  ensureOwnedQuestions();
  const out=[];
  (window.QUESTIONS||[]).forEach(Q=>{
    const g=qGlobal(Q.y,Q.e,Q.q);
    if(!g||!g.total)return;
    out.push({kind:"q",y:Q.y,e:Q.e,q:Q.q,sec:Q.sec,
              total:g.total, rate:g.right/g.total, blankRate:g.blank/g.total});
  });
  return out;
}

/* ---------- کلمات ----------
   «بلد بودن» = آخرین امتیاز هر داوطلب روی این کلمه در مرور؛ هر نفر یک بار. */
function crowdW(){
  const out=[];
  if(!CROWD_DATA){loadCrowd().catch(()=>{});return out}
  WORDS.forEach(w=>{
    const a=CROWD_DATA.w[WID[w.w]]; if(!a)return;
    const [total,ok]=a;
    const yrs=[...new Set((w.occ||[]).map(o=>o[0]))];
    const exs=[...new Set((w.occ||[]).map(o=>o[1]))];
    out.push({kind:"w",w,total,rate:total?ok/total:0,yrs,exs,lvl:w.lvl,pos:w.pos});
  });
  return out;
}

function crowdAll(){
  return CROWD.kind==="q" ? crowdQ() : crowdW();
}

function crowdFiltered(all){
  return all.filter(r=>{
    if(CROWD.year){
      if(r.kind==="q"){ if(String(r.y)!==CROWD.year)return false }
      else { if(!r.yrs.some(y=>String(y)===CROWD.year))return false }
    }
    if(CROWD.exam){
      if(r.kind==="q"){ if(r.e!==CROWD.exam)return false }
      else { if(!r.exs.some(e=>e===CROWD.exam))return false }
    }
    return true;
  });
}

function renderCrowd(){
  const box=$("#crowdBody"); if(!box)return;
  const all=crowdFiltered(crowdAll());

  const dist={weak:0,mid:0,strong:0};
  all.forEach(r=>{ Object.keys(CROWD_BAND).forEach(b=>{ if(CROWD_BAND[b].test(r.rate))dist[b]++ }) });

  const band=CROWD_BAND[CROWD.band];
  const rows=all.filter(r=>band.test(r.rate)).sort((a,b)=>a.rate-b.rate);

  const pages=Math.max(1,Math.ceil(rows.length/CROWD.per));
  if(CROWD.page>=pages)CROWD.page=pages-1;
  const slice=rows.slice(CROWD.page*CROWD.per,(CROWD.page+1)*CROWD.per);

  const avg=all.length?Math.round(all.reduce((a,r)=>a+r.rate,0)/all.length*100):0;
  const unit=CROWD.kind==="q"?"تست":"کلمه";

  box.innerHTML=`
    <div class="row-f" style="margin-bottom:12px">
      <div class="seg" id="crowdKind">
        <button data-ck="q" class="${CROWD.kind==="q"?"on":""}">تست‌ها</button>
        <button data-ck="w" class="${CROWD.kind==="w"?"on":""}">کلمات</button>
      </div>
      <select id="crowdYear" class="inp" style="width:auto">
        <option value="">همه‌ی سال‌ها</option>
        ${YEARS.slice().reverse().map(y=>
          `<option value="${y}" ${CROWD.year===String(y)?"selected":""}>کنکور ${fa(y)}</option>`).join("")}
      </select>
      <select id="crowdExam" class="inp" style="width:auto">
        <option value="">همه‌ی رشته‌ها</option>
        ${(window.OWNED_EXAMS||[]).map(c=>EXAM_CODE_FA[c]).filter(Boolean).map(e=>
          `<option value="${e}" ${CROWD.exam===e?"selected":""}>${e}</option>`).join("")}
      </select>
      ${(CROWD.year||CROWD.exam)?`<button class="ghost" id="crowdReset">برداشتن فیلترها</button>`:""}
      <span class="capt" style="margin:0 auto 0 0">${fa(all.length)} ${unit} در این نما</span>
    </div>

    <div class="stats">
      ${Object.keys(CROWD_BAND).map(b=>{
        const B=CROWD_BAND[b], v=dist[b], pc2=all.length?Math.round(v/all.length*100):0;
        return `<div class="stat"><div class="k"><i style="background:${B.color}"></i>${B.label}</div>
          <div class="v" style="color:${B.color}">${fa(v)}</div>
          <div class="bar"><i style="width:${pc2}%;background:${B.color}"></i></div>
          <div class="capt">${fa(pc2)}٪ از ${fa(all.length)} ${unit}</div></div>`;
      }).join("")}
      <div class="stat"><div class="k"><i style="background:#4a86c8"></i>${
        CROWD.kind==="q"?"میانگین درست‌زدن":"میانگین بلد بودن"}</div>
        <div class="v">${fa(avg)}<small>٪</small></div>
        <div class="capt">بین همه‌ی داوطلب‌ها</div></div>
    </div>

    <div class="row-f" style="margin:14px 0 10px">
      <div class="label" style="margin:0">فهرست ${unit}‌ها</div>
      <div class="seg" id="crowdSeg">
        ${Object.keys(CROWD_BAND).map(b=>
          `<button class="${CROWD.band===b?"on":""}" data-cb="${b}">${CROWD_BAND[b].label} (${fa(dist[b])})</button>`).join("")}
      </div>
    </div>

    ${slice.length?`<div class="list">${slice.map((r,i)=>{
      const pv=Math.round(r.rate*100);
      const n=CROWD.page*CROWD.per+i+1;
      const head = r.kind==="q"
        ? `<b>تست ${fa(r.q)} — کنکور ${fa(r.y)} ${r.e}</b><span class="lfa">${r.sec}</span>`
        : `<b class="en">${r.w.w}</b><span class="lfa">${listFa(r.w)}</span>`;
      const meta = r.kind==="q"
        ? `<span>${fa(r.total)} پاسخ ثبت‌شده</span>
           <span class="${r.rate<0.45?"hot":""}">${fa(100-pv)}٪ غلط یا نزده</span>
           ${Math.round(r.blankRate*100)>25?`<span>${fa(Math.round(r.blankRate*100))}٪ اصلاً نزده‌اند</span>`:""}`
        : `<span>${fa(r.total)} نفر مرور کرده‌اند</span>
           <span class="${r.rate<0.45?"hot":""}">${fa(100-pv)}٪ بلد نبوده‌اند</span>
           ${r.lvl?`<span>سطح: ${r.lvl}</span>`:""}
           ${r.yrs&&r.yrs.length?`<span>${fa(r.yrs.length)} کنکور</span>`:""}`;
      const go = r.kind==="q" ? `${r.y}|${r.e}|${r.q}` : `w|${r.w.w}`;
      return `<div class="lrow crowdrow" data-crowdgo="${go}">
        <span class="lrk">${fa(n)}</span>
        <span class="lmain">
          <span class="lhead">${head}</span>
          <span class="lmeta">${meta}</span>
        </span>
        <span class="lsc"><b style="color:${band.color}">${fa(pv)}٪</b>
          <span class="ltrack"><i style="width:${pv}%;background:${band.color}"></i></span></span>
        <span class="go2">${r.kind==="q"?"دیدن تست":"دیدن کلمه"} ←</span>
      </div>`;
    }).join("")}</div>

    <div class="row-f" style="margin-top:12px">
      ${pages>1?`
        <button class="ghost" data-cpg="${CROWD.page-1}" ${CROWD.page===0?"disabled":""}>← قبلی</button>
        <span class="capt" style="margin:0">صفحه‌ی ${fa(CROWD.page+1)} از ${fa(pages)} · ${fa(rows.length)} ${unit}</span>
        <button class="ghost" data-cpg="${CROWD.page+1}" ${CROWD.page>=pages-1?"disabled":""}>بعدی →</button>`
        :`<span class="capt" style="margin:0">${fa(rows.length)} ${unit} در این دسته</span>`}
    </div>
    `:`<div class="empty">${!CROWD_DATA
        ? (CROWD_FAILED?"آمار بارگذاری نشد. صفحه را دوباره باز کنید.":"در حال گرفتن آمار…")
        : all.length ? `${unit}ی در این دسته نیست.`
        : `هنوز پاسخ کافی از داوطلب‌ها ثبت نشده است. آمار فقط برای رشته‌هایی که خریده‌اید و فقط وقتی
           دست‌کم ${fa(CROWD_DATA.min)} پاسخ ثبت شده باشد نشان داده می‌شود.`}</div>`}

    <div class="capt" style="margin-top:12px">
      این آمار از پاسخ همه‌ی داوطلب‌هاست، نه فقط شما. اگر ${unit}ی برای بیشتر آدم‌ها سخت است،
      احتمالاً <b>مشکل از خودش است نه از شما</b>. اگر با چنین موردی روبه‌رو شدید،
      دکمه‌ی ⚑ را بزنید و بگویید کجایش گنگ است.
    </div>

    ${rows.length?`
    <div class="actionbar">
      <div class="ab-l">
        <span class="ab-n">${fa(rows.length)}</span>
        <span class="ab-t">${unit} در دسته‌ی «${band.label}»
          ${CROWD.year?` · کنکور ${fa(+CROWD.year)}`:""}${CROWD.exam?` · ${CROWD.exam}`:""}</span>
      </div>
      <button class="ab-go" id="crowdAdd">${CROWD.kind==="w"
        ? "افزودن این کلمه‌ها به دک مرور"
        : "افزودن کلمات این تست‌ها به دک مرور"}
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
          stroke-width="2" stroke-linecap="round"><path d="M15 6l-6 6 6 6"/></svg></button>
    </div>`:""}`;

  box.querySelectorAll("#crowdKind button").forEach(b=>b.addEventListener("click",()=>{
    CROWD.kind=b.dataset.ck; CROWD.page=0; renderCrowd();
  }));
  box.querySelectorAll("#crowdSeg button").forEach(b=>b.addEventListener("click",()=>{
    CROWD.band=b.dataset.cb; CROWD.page=0; renderCrowd();
  }));
  const yy=$("#crowdYear"); if(yy)yy.addEventListener("change",e=>{CROWD.year=e.target.value;CROWD.page=0;renderCrowd()});
  const ee=$("#crowdExam"); if(ee)ee.addEventListener("change",e=>{CROWD.exam=e.target.value;CROWD.page=0;renderCrowd()});
  const rs=$("#crowdReset"); if(rs)rs.addEventListener("click",()=>{CROWD.year="";CROWD.exam="";CROWD.page=0;renderCrowd()});
  box.querySelectorAll("[data-cpg]").forEach(b=>b.addEventListener("click",()=>{
    if(b.disabled)return;
    CROWD.page=+b.dataset.cpg; renderCrowd();
    box.scrollIntoView({block:"start",behavior:"smooth"});
  }));
  /* کل ردیف کلیک‌شدنی است، نه فقط دکمه */
  const ca=$("#crowdAdd");
  if(ca)ca.addEventListener("click",()=>{
    let names=[];
    if(CROWD.kind==="w"){
      names=rows.map(r=>r.w.w);
    }else{
      /* برای تست‌ها، کلمات همان تست‌ها را جمع می‌کنیم */
      rows.forEach(r=>{
        const Q=makeQ(r.y,r.e,r.q);
        if(Q&&typeof allWordsOf==="function")
          allWordsOf(Q).forEach(w=>names.push(w.w));
      });
    }
    const add=[...new Set(names)].filter(n=>n&&!deck.has(n));
    if(!add.length){toast("همه‌ی این کلمات از قبل در دک شماست.");return}
    add.forEach(n=>deck.add(n));
    saveState();refreshAll();renderCrowd();
    toast(`${fa(add.length)} کلمه به دک مرور اضافه شد.`);
  });
  box.querySelectorAll("[data-crowdgo]").forEach(b=>b.addEventListener("click",()=>{
    const v=b.dataset.crowdgo;
    /* openQuestion لازم است نه renderQuestion:
       اولی curView را می‌سازد، دومی فقط رندر می‌کند. بدون curView،
       زدن «مشاهده پاسخ» روی null می‌نویسد و می‌شکند. */
    if(v.startsWith("w|")){ const w=WORDS.find(x=>x.w===v.slice(2)); if(w)openDetail(w) }
    else { const [y,e,q]=v.split("|"); openQuestion(+y,e,+q) }
  }));
}

/* ===== تعادل و پوشش =====
   در پلتفرم مرور، واحدِ سنجش «درس» بود. اینجا واحد طبیعی «سال و رشته» است:
   دفترچه‌ی کنکور ۱۴۰۳ مهندسی کامپیوتر یک واحد مستقل است.
   دو عدد متفاوت را نشان می‌دهیم و عمداً قاطی نمی‌کنیم:
     پوشش  — چند درصد کلمات آن دفترچه وارد دک شما شده
     سهم   — چند درصد از کل مرورهای شما صرف آن دفترچه شده
   «سهم منصفانه» یعنی سهمی که با توجه به حجم آن دفترچه انتظار می‌رود. */
const BAL={exam:""};

function balUnits(){
  const map={};
  WORDS.forEach(w=>{
    (w.occ||[]).forEach(o=>{
      const y=o[0], e=o[1];
      const k=y+"|"+e;
      (map[k]=map[k]||{y,e,words:new Set()}).words.add(w.w);
    });
  });
  return Object.keys(map).map(k=>{
    const u=map[k];
    const all=[...u.words];
    const inDeck=all.filter(w=>deck.has(w));
    let seen=0, mastered=0;
    inDeck.forEach(w=>{
      const st=cardStats("w|"+w);
      seen+=st.seen;
      if(st.bucket==="strong")mastered++;
    });
    return {key:k, y:u.y, e:u.e, total:all.length, deck:inDeck.length,
            seen, mastered, cover: all.length?inDeck.length/all.length:0};
  });
}

function renderBalance(){
  const box=$("#balBody"); if(!box)return;

  let rows=balUnits();
  if(BAL.exam) rows=rows.filter(r=>r.e===BAL.exam);
  if(!rows.length){ box.innerHTML='<div class="empty">هنوز کلمه‌ای در دک ندارید.</div>'; return }

  const totalSeen=rows.reduce((a,r)=>a+r.seen,0)||1;
  const totalAll =rows.reduce((a,r)=>a+r.total,0)||1;
  rows.forEach(r=>{
    r.share=r.seen/totalSeen;
    r.fair =r.total/totalAll;
    r.gap  =r.share-r.fair;
    r.state= r.gap<-0.04?"behind" : (r.gap>0.04?"over":"fairb");
  });

  const behind=rows.filter(r=>r.state==="behind").sort((a,b)=>a.gap-b.gap);
  const over  =rows.filter(r=>r.state==="over").sort((a,b)=>b.gap-a.gap);
  const balanced=rows.filter(r=>r.state==="fairb").length;
  const maxShare=Math.max(...rows.map(r=>Math.max(r.share,r.fair)))||1;
  const COL={behind:"#c05a35",over:"#d99521",fairb:"#3f8f5f"};
  const TAG={behind:"عقب افتاده",over:"بیش از سهم",fairb:"متعادل"};
  const imbalance=Math.round(rows.reduce((a,r)=>a+Math.abs(r.gap),0)/2*100);

  const nm=r=>`کنکور ${fa(r.y)} — ${r.e}`;
  const exams=[...new Set(balUnits().map(r=>r.e))];
  const avgCover=Math.round(rows.reduce((a,r)=>a+r.cover,0)/rows.length*100);

  box.innerHTML=`
  <div class="row-f" style="margin-bottom:12px">
    <div class="seg" id="balSeg">
      <button data-be="" class="${BAL.exam?"":"on"}">همه‌ی رشته‌ها</button>
      ${exams.map(e=>`<button data-be="${e}" class="${BAL.exam===e?"on":""}">${e}</button>`).join("")}
    </div>
    <span class="capt" style="margin:0 auto 0 0">${fa(rows.length)} دفترچه در این نما</span>
  </div>

  <div class="balhero">
    <div class="balscore">
      <div class="bsn" style="color:${imbalance>25?"#c05a35":imbalance>12?"#d99521":"#3f8f5f"}">${fa(imbalance)}<small>٪</small></div>
      <div class="bsl">نامتعادلی</div>
      <div class="bsd">${imbalance>25?"اختلاف زیاد است":imbalance>12?"کمی نامتعادل":"تقسیم وقت شما متعادل است"}</div>
    </div>
    <div class="baltext">
      ${behind.length
        ? `<p><b>${nm(behind[0])}</b> عقب افتاده: ${fa(Math.round(behind[0].share*100))}٪ از مرورهای شما را گرفته،
           در حالی که ${fa(Math.round(behind[0].fair*100))}٪ از کلمات این نما را تشکیل می‌دهد.
           ${over.length?`در مقابل <b>${nm(over[0])}</b> با ${fa(Math.round(over[0].share*100))}٪ جلوتر از سهمش است.`:""}</p>
           <p class="capt">دفترچه‌ای که کمتر کار می‌کنید معمولاً همانی است که سخت‌تر بوده — و همان‌جاست که نمره از دست می‌رود.</p>`
        : `<p>هیچ دفترچه‌ای رها نشده و هیچ‌کدام هم بیش از سهمش وقت نگرفته. ${fa(balanced)} دفترچه در محدوده‌ی متعادل‌اند.</p>`}
      <p class="capt">پوشش میانگین شما در این نما: <b>${fa(avgCover)}٪</b> از کلمات وارد دک شده.</p>
    </div>
  </div>

  <div class="ballegend">
    <span><em style="background:${COL.behind}"></em>عقب افتاده (${fa(behind.length)})</span>
    <span><em style="background:${COL.fairb}"></em>متعادل (${fa(balanced)})</span>
    <span><em style="background:${COL.over}"></em>بیش از سهم (${fa(over.length)})</span>
    <span class="capt" style="margin-right:auto">خط تیره = سهمی که با توجه به حجم دفترچه انتظار می‌رود</span>
  </div>

  ${rows.sort((a,b)=>a.gap-b.gap).map(r=>`
    <div class="balrow ${r.state}">
      <span class="bn">${nm(r)}
        <small>${fa(r.total)} کلمه · ${fa(Math.round(r.cover*100))}٪ در دک · ${fa(r.mastered)} مسلط</small></span>
      <span class="btrack">
        <i style="width:${r.share/maxShare*100}%;background:${COL[r.state]}"></i>
        <span class="fair" style="right:${r.fair/maxShare*100}%"
          data-tip="سهم مورد انتظار: ${fa(Math.round(r.fair*100))}٪"></span>
      </span>
      <span class="bv">
        <b style="color:${COL[r.state]}">${fa(Math.round(r.share*100))}٪</b>
        <em>${TAG[r.state]}${r.state!=="fairb"?` · ${r.gap>0?"+":"−"}${fa(Math.abs(Math.round(r.gap*100)))}`:""}</em>
      </span>
      <button class="ghost" data-balgo="${r.key}">افزودن به دک</button>
    </div>`).join("")}

  <div class="actionbar">
    <div class="ab-l">
      <span class="ab-n">${behind.length?fa(behind.length):fa(avgCover)+"٪"}</span>
      <span class="ab-t">${behind.length
        ? `دفترچه عقب افتاده · عقب‌ترینشان ${nm(behind[0])}`
        : `پوشش میانگین شما در این نما · ${fa(rows.length)} دفترچه`}</span>
    </div>
    <button class="ab-go" data-balgo="${(behind[0]||rows[0]).key}">
      ${behind.length?`جبران ${nm(behind[0])}`:`افزودن کلمات ${nm(rows[0])}`}
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round"><path d="M15 6l-6 6 6 6"/></svg></button>
  </div>

  <div class="capt" style="margin-top:12px">
    نوار رنگی سهم واقعی هر دفترچه از مرورهای شماست و خط تیره جایی است که اگر وقتتان را متناسب با
    حجم هر دفترچه تقسیم می‌کردید باید می‌رسید. این معیار درباره‌ی <b>توزیع وقت</b> است، نه سطح علمی —
    ممکن است دفترچه‌ای را کم کار کنید چون کلماتش را از جای دیگر بلدید.
  </div>`;

  $("#balSeg").querySelectorAll("[data-be]").forEach(b=>b.addEventListener("click",()=>{
    BAL.exam=b.dataset.be; renderBalance();
  }));
  box.querySelectorAll("[data-balgo]").forEach(b=>b.addEventListener("click",()=>{
    const [y,e]=b.dataset.balgo.split("|");
    const add=WORDS.filter(w=>(w.occ||[]).some(o=>String(o[0])===y&&o[1]===e)&&!deck.has(w.w));
    if(!add.length){toast("همه‌ی کلمات این دفترچه از قبل در دک شماست.");return}
    add.forEach(w=>deck.add(w.w));
    saveState();refreshAll();renderBalance();
    toast(`${fa(add.length)} کلمه‌ی کنکور ${fa(+y)} ${e} به دک اضافه شد.`);
  }));
}

/* ---------- تم و دکمه‌های نوار بالا ---------- */
const THEMES=[
  {id:"light", name:"روشن",  note:"کاغذ کرم و طلایی",
   sw:["#f5f4f2","#ffffff","#a8842c","#16191d"],
   tip:"<b>روز، اتاق روشن</b><br>بیشترین تضاد و خواناترین حالت برای متن انگلیسی و فارسی کنار هم. اگر نور اتاق کافی است، همین بهترین انتخاب است."},
  {id:"sepia", name:"کاغذی", note:"کم‌آبی، مثل کاغذ کهنه",
   sw:["#f2e8d5","#fbf4e6","#9a7526","#2e2519"],
   tip:"<b>مطالعه‌ی چند ساعته</b><br>نور آبی کمتری دارد. چیزی که چشم را در مطالعه‌ی طولانی خسته می‌کند لزوماً روشنایی نیست، نور آبی است — پس این حالت حتی وسط روز هم کمک می‌کند."},
  {id:"dim",   name:"کم‌نور", note:"خاکستری ملایم",
   sw:["#22262c","#2a2f36","#d4b062","#dfe3e8"],
   tip:"<b>غروب و اتاق نیمه‌روشن</b><br>تیره است ولی سیاه مطلق نیست. در نور کم، متن سفید روی سیاه خالص هاله می‌گیرد و تارتر دیده می‌شود؛ این حالت آن مشکل را ندارد."},
  {id:"dark",  name:"شب",    note:"تیره‌ترین حالت",
   sw:["#14171a","#1c1f24","#d0a94a","#e7e3dc"],
   tip:"<b>شب، اتاق تاریک</b><br>وقتی چراغ خاموش است و صفحه تنها منبع نور. اگر در اتاق روشن از این استفاده کنید، برعکس، چشمتان بیشتر خسته می‌شود."},
];
let curTheme = LS.get("zban_theme") || "light";

function applyTheme(id){
  curTheme=id; LS.set("zban_theme",id);
  if(id==="light")document.documentElement.removeAttribute("data-theme");
  else document.documentElement.setAttribute("data-theme",id);
  renderThemeMenu();
}
function esc(x){return String(x).replace(/"/g,"&quot;")}

function renderThemeMenu(){
  const m=$("#themeMenu"); if(!m)return;
  m.innerHTML=THEMES.map(t=>`
    <button data-theme-id="${t.id}" class="${curTheme===t.id?"on":""}" data-tip="${esc(t.tip)}">
      <span class="tprev" style="background:${t.sw[0]}">
        <span class="tbar" style="background:${t.sw[2]}"></span>
        <span class="tcard" style="background:${t.sw[1]}">
          <span class="tln" style="background:${t.sw[3]};width:80%"></span>
          <span class="tln" style="background:${t.sw[3]};width:55%;opacity:.55"></span>
        </span>
      </span>
      <span class="lbl"><b>${t.name}</b><small>${t.note}</small></span>
      <span class="tsw"></span>
    </button>`).join("");
  m.querySelectorAll("[data-theme-id]").forEach(b=>b.addEventListener("click",()=>{
    applyTheme(b.dataset.themeId);
    m.classList.remove("on");
    toast("ظاهر تغییر کرد: "+THEMES.find(x=>x.id===b.dataset.themeId).name);
  }));
}
$("#themeBtn").addEventListener("click",e=>{
  e.stopPropagation();
  $("#themeMenu").classList.toggle("on");
  renderThemeMenu();
});
document.addEventListener("click",e=>{
  if(!e.target.closest(".themebtn"))$("#themeMenu").classList.remove("on");
});
applyTheme(curTheme);

/* دکمه‌های 🔔 📋 👤 */
document.addEventListener("click",e=>{
  /* دکمه‌های شماره‌ی سؤال در آزمون هم data-go دارند (data-go="7")، ولی
    مقصدشان تب نیست. اگر عدد بود، این شنونده کاری ندارد و می‌گذارد
    شنونده‌ی خود آزمون کارش را بکند. */
  const b=e.target.closest("[data-go]"); if(!b)return;
  const where=b.dataset.go;
  if(/^\d+$/.test(where))return;
  goTab(where);
});

/* دکمه‌های بالا صفحه‌ی کامل باز می‌کنند، نه مودال —
   چون گزارش‌ها و اطلاعیه‌ها محتوای طولانی دارند. */
/* یک مسیر واحد برای عوض کردن صفحه. قبلاً این تابع نسخه‌ی دوم openTab بود
   و سراغ `#tabs` می‌رفت که از v67 دیگر وجود ندارد — برای همین نوار ناوبری
   روی تب قبلی خشک می‌ماند. */
function goTab(name){ openTab(name) }

/* ===== اطلاعیه‌ها =====
   از GET /api/announcements می‌آیند (app-bootstrap در window.ANNOUNCE می‌گذارد)
   و مدیر از /zaban-admin/announcements می‌نویسدشان. هیچ متن ثابتی اینجا نیست.
   «تازه» یعنی بعد از آخرین باری که کاربر این صفحه را باز کرده منتشر شده. */
const ANN=(window.ANNOUNCE&&window.ANNOUNCE.items)||[];
let annUnread=(window.ANNOUNCE&&window.ANNOUNCE.unread)||0;

function annEsc(t){return String(t).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")}
function updAnnBadge(){
  const c=$("#annCnt"); if(!c)return;
  c.hidden=!annUnread; c.textContent=annUnread?fa(annUnread):"";
}

function renderAnnounce(){
  const box=$("#annBody"); if(!box)return;
  $("#annCount").textContent=fa(ANN.length);
  box.innerHTML = ANN.length ? ANN.map(a=>`
    <div class="ann ${a.new?"new":""}">
      <div class="ah"><b>${annEsc(a.t)}</b>
        ${a.new?'<span class="nb">تازه</span>':""}
        <span>${jdate(a.ts)}</span></div>
      <div class="ab">${annEsc(a.b).replace(/\n/g,"<br>")}</div>
    </div>`).join("")
    : `<div class="empty">${window.ANNOUNCE&&window.ANNOUNCE.failed
        ? "اطلاعیه‌ها بارگذاری نشد. صفحه را دوباره باز کنید."
        : "هنوز اطلاعیه‌ای منتشر نشده."}</div>`;

  /* نشان «تازه» همین بار دیده می‌شود؛ از دفعه‌ی بعد خوانده‌شده است. */
  if(annUnread){ annUnread=0; updAnnBadge(); ZABAN.announcementsRead(); }
}

/* ===== پروفایل ===== */
/* پروفایل از سرور (mem.zban_prof = پاسخ /me) و ذخیره روی سرور.
   تا نسخه‌ی دمو همه‌ی مقدارها در HTML ثابت بود و «ثبت» فقط در مرورگر می‌نوشت. */
function renderProfile(){
  const P=PROF;
  $("#pfName").value=P.name||"—";
  $("#pfMobileF").hidden=!P.mobile; $("#pfMobile").value=P.mobile||"";
  $("#pfNick").value=P.nick; $("#pfUni").value=P.uni;
  $("#pfGpa").value=P.gpa!=null?fa(String(P.gpa).replace(".","٫")):"";
  $("#pfQuota").value=P.quota; $("#pfDegree").value=P.degree;
  $("#pfNew").value=P.newPerDay?fa(String(P.newPerDay)):"";
  $("#pfExam").value=P.examCode; $("#pfBoard").checked=P.board;

  const b=$("#pfSave");
  if(b._bound)return;
  b._bound=true;
  b.addEventListener("click",async()=>{
    /* رقم فارسی و «٫» را به عدد لاتین برمی‌گردانیم */
    const g=$("#pfGpa").value.trim().replace(/[۰-۹]/g,d=>"۰۱۲۳۴۵۶۷۸۹".indexOf(d)).replace(/[٫,]/g,".");
    const gpa=g===""?null:Number(g);
    if(gpa!==null&&!(gpa>=0&&gpa<=20)){toast("معدل باید عددی بین ۰ تا ۲۰ باشد.");return}
    /* کارت تازه در روز: خالی یعنی پیش‌فرض مدیر */
    const npRaw=$("#pfNew").value.trim().replace(/[۰-۹]/g,d=>"۰۱۲۳۴۵۶۷۸۹".indexOf(d));
    const np=npRaw===""?null:Number(npRaw);
    if(np!==null&&!(Number.isInteger(np)&&np>=5&&np<=100)){toast("کارت تازه در روز باید عددی بین ۵ تا ۱۰۰ باشد.");return}
    const nick=$("#pfNick").value.trim();
    if(/[<>"&]/.test(nick)){toast("نام مستعار نمی‌تواند نویسه‌های < > \" & داشته باشد.");return}
    b.disabled=true;
    try{
      const p=await ZABAN.profile({
        nickname:nick||null, exam:$("#pfExam").value, show_in_board:$("#pfBoard").checked,
        university:$("#pfUni").value.trim()||null, gpa,
        quota:$("#pfQuota").value||null, degree:$("#pfDegree").value||null,
        new_per_day:np,
      });
      const merged=Object.assign({},LS.get("zban_prof")||{},p);
      LS.set("zban_prof",merged);
      Object.assign(PROF,profFromServer(merged));
      NEW_PER_DAY=PROF.newPerDay||NEW_PER_DAY;       /* صف «مرور امروز» با عدد تازه */
      updDeckCount(); refreshAll(); renderProfile();
      toast("اطلاعات شما ثبت شد."+(p.show_in_board?" رتبه‌بندی هر ده دقیقه به‌روز می‌شود.":""));
    }catch(e){
      console.error(e);
      toast(e&&e.userMessage ? e.userMessage
            : "اطلاعات ذخیره نشد. اتصال را بررسی کنید و دوباره «ثبت اطلاعات» را بزنید.");
    }finally{b.disabled=false}
  });
}

/* یک مودال ساده‌ی فقط-خواندنی — برای اطلاعیه و پروفایل */
function askInfo(title, html){
  let el=$("#infoM");
  if(!el){
    el=document.createElement("div");
    el.className="scrim"; el.id="infoM";
    el.innerHTML='<div class="sheet repsheet" style="max-width:520px">'+
      '<div class="rephead"><button class="close" id="infoClose">×</button>'+
      '<div class="rt" id="infoTitle"></div></div>'+
      '<div class="repbody" id="infoBody"></div></div>';
    document.body.appendChild(el);
    el.addEventListener("click",ev=>{ if(ev.target.id==="infoM"||ev.target.id==="infoClose")el.classList.remove("open") });
  }
  $("#infoTitle").textContent=title;
  $("#infoBody").innerHTML=html;
  el.classList.add("open");
}

/* ---------- گزارش‌های من ----------
   تا حالا گزارش فقط از کنار خود کلمه یا تست دیده می‌شد. اگر کاربر ده گزارش
   داده باشد باید ده جا را بگردد تا ببیند به کدام پاسخ داده‌ایم.
   این صفحه همه را یکجا می‌آورد و پاسخ‌های تازه را بالا می‌گذارد. */
const RF={filter:"all",topic:"all",kind:"all",q:""};

function repUnanswered(){
  return Object.keys(REPORTS).filter(k=>REPORTS[k].state==="answered" && !REPORTS[k].seen).length;
}
function updRepBadge(){
  const n=repUnanswered();
  const b=$("#repBadge");
  if(b){ b.hidden=!n; b.textContent=n?fa(n):"" }
  const c=$("#repCnt");
  if(c){ c.hidden=!n; c.textContent=n?fa(n):"" }
}
function repLabel(k){
  if(k.charAt(0)==="w")return "کلمه‌ی "+k.slice(2);
  const p=k.slice(2).split("|");
  return "سؤال "+fa(p[2])+" — کنکور "+fa(p[0])+" "+p[1];
}

function renderMyReports(){
  const keys=Object.keys(REPORTS);
  const answered=keys.filter(k=>REPORTS[k].state==="answered");
  const open=keys.filter(k=>REPORTS[k].state==="open");

  let list = RF.filter==="answered" ? answered.slice()
           : RF.filter==="open"     ? open.slice()
           : keys.slice();

  if(RF.topic!=="all") list=list.filter(k=>REPORTS[k].topic===RF.topic);
  if(RF.kind!=="all")  list=list.filter(k=>k.charAt(0)===RF.kind);
  if(RF.q){
    const q=RF.q.trim().toLowerCase();
    list=list.filter(k=>(repLabel(k)+" "+REPORTS[k].msgs.map(m=>m.body).join(" ")).toLowerCase().indexOf(q)>=0);
  }

  list.sort((a,b)=>{
    const A=REPORTS[a],B=REPORTS[b];
    const an=(A.state==="answered"&&!A.seen)?1:0, bn=(B.state==="answered"&&!B.seen)?1:0;
    if(an!==bn)return bn-an;
    return B.msgs[B.msgs.length-1].ts - A.msgs[A.msgs.length-1].ts;
  });

  $("#repMine").innerHTML = `
    <div class="panel">
      <div class="row-f" style="margin-bottom:12px">
        <div class="label" style="margin:0">گزارش‌های من</div>
        <span class="capt" style="margin:0">${keys.length?fa(keys.length)+" گزارش":"هنوز گزارشی نداده‌اید"}</span>
      </div>

      ${list.length ? list.map(k=>{
        const R=REPORTS[k];
        const last=R.msgs[R.msgs.length-1];
        const isNew = R.state==="answered" && !R.seen;
        return `<div class="mrep ${isNew?"fresh":""}" data-mrep="${k}">
          <div class="mh">
            ${isNew?'<span class="dotnew"></span>':""}
            <span class="mk2">${repLabel(k)}</span>
            <span class="rep-topic" style="margin:0">${R.topic}</span>
            <span class="rep-state ${R.state}" style="margin-right:auto">${
              R.state==="answered"?"پاسخ داده شده":"در انتظار پاسخ"}</span>
          </div>
          <div class="mlast">
            <b>${last.by==="admin"?"پشتیبانی:":"شما:"}</b>
            ${String(last.body).replace(/</g,"&lt;").slice(0,140)}${last.body.length>140?"…":""}
          </div>
          <div class="mfoot">
            <span>${jdate(last.ts)}</span>
            <span>${fa(R.msgs.length)} پیام</span>
            <span class="go2">دیدن گفت‌وگو ←</span>
          </div>
        </div>`}).join("")
        : `<div class="empty">${keys.length
             ? "با این فیلتر گزارشی نیست."
             : "روی دکمه‌ی ⚑ کنار هر کلمه یا سؤال بزنید تا اشکالش را گزارش کنید."}</div>`}
    </div>`;
  updRepBadge();
}

$("#tab-reports").addEventListener("click",e=>{
  const st=e.target.closest("#rpStatus [data-v]");
  if(st){ RF.filter=st.dataset.v;
    $("#rpStatus").querySelectorAll("button").forEach(x=>x.classList.toggle("on",x===st));
    renderMyReports(); return }
  const tp=e.target.closest("#rpTopic [data-v]");
  if(tp){ RF.topic=tp.dataset.v;
    $("#rpTopic").querySelectorAll("button").forEach(x=>x.classList.toggle("on",x===tp));
    renderMyReports(); return }
  const f=e.target.closest("[data-rf]");
  if(f){RF.filter=f.dataset.rf;renderMyReports();return}
  const r=e.target.closest("[data-mrep]");
  if(r){
    const k=r.dataset.mrep;
    openReport(k, repLabel(k));   /* openReport خودش «دیده شد» را ثبت می‌کند */
    renderMyReports();
    syncUrl(true);                /* /zaban/reports/<کلید> — لینک پاسخ پشتیبانی */
  }
});

/* ---------- پنل مدیریت ----------
   در نسخه‌ی واقعی این یک صفحه‌ی جدا در بخش مدیریت سایت است که گزارش همه‌ی
   کاربران را می‌آورد. اینجا هست تا بتوانید چرخه‌ی کامل را خودتان تست کنید:
   گزارش بدهید → اینجا پاسخ بدهید → برگردید و پاسخ را روی همان کلمه ببینید. */
function renderAdmin(){
  const keys=Object.keys(REPORTS);
  $("#admCount").textContent = keys.length? fa(keys.length)+" گزارش" : "";
  if(!keys.length){
    $("#admBody").innerHTML=`<div class="empty">هنوز گزارشی ثبت نشده.<br>
      روی دکمه‌ی ⚑ کنار یک کلمه یا سؤال بزنید و یک گزارش بفرستید، بعد اینجا برگردید.</div>`;
    return;
  }
  const open=keys.filter(k=>REPORTS[k].state==="open");
  $("#admBody").innerHTML =
    (open.length?`<div class="pr-note" style="margin-bottom:12px">${fa(open.length)} گزارش در انتظار پاسخ است.</div>`:"")
    + keys.map(k=>{
    const R=REPORTS[k];
    const label = k.charAt(0)==="w" ? "کلمه: "+k.slice(2) : "سؤال: "+k.slice(2);
    return `<div class="admrow">
      <div class="ah">
        <span class="ak">${label}</span>
        <span class="rep-topic" style="margin:0">${R.topic}</span>
        <span class="rep-state ${R.state}" style="margin-right:auto">${
          R.state==="answered"?"پاسخ داده شده":"در انتظار پاسخ"}</span>
      </div>
      <div class="rep-thread" style="max-height:190px;margin-bottom:10px">${R.msgs.map(m=>`
        <div class="rep-msg ${m.by}">
          <div class="who"><b>${m.by==="admin"?"پشتیبانی پلتفرم":"کاربر"}</b><span>${jdate(m.ts)}</span></div>
          ${String(m.body).replace(/</g,"&lt;")}
        </div>`).join("")}</div>
      <textarea class="art" data-admta="${k}" placeholder="پاسخ شما به کاربر…"></textarea>
      <div class="row-f" style="margin-top:9px">
        <button class="admbtn" data-admsend="${k}">ارسال پاسخ به کاربر</button>
        <button class="ghost" data-admdel="${k}" style="margin-right:auto">حذف این گزارش</button>
      </div>
    </div>`}).join("");
}
function openAdmin(){renderAdmin();$("#admM").classList.add("open")}
window.openAdmin=openAdmin;
$("#cbClose").addEventListener("click",()=>{$("#cbM").classList.remove("open");syncUrl(true)});
$("#cbM").addEventListener("click",e=>{if(e.target.id==="cbM")$("#cbM").classList.remove("open")});
$("#cbFilters").addEventListener("click",e=>{
  const b=e.target.closest("[data-cbb]"); if(!b)return;
  CB.bucket=b.dataset.cbb; CB.page=0; renderCards();
});
$("#cbFilters").addEventListener("input",e=>{
  if(e.target.id!=="cbQ")return;
  CB.q=e.target.value; CB.page=0;
  const pos=e.target.selectionStart; renderCards();
  const again=$("#cbQ"); if(again){again.focus();try{again.setSelectionRange(pos,pos)}catch(_){}}
});
$("#cbPager").addEventListener("click",e=>{
  const b=e.target.closest("[data-cbp]"); if(!b||b.disabled)return;
  CB.page=+b.dataset.cbp; renderCards();
  $("#cbBody").scrollTop=0;
});
$("#cbStart").addEventListener("click",()=>{
  const L=cbFiltered().slice(0,40);
  if(!L.length)return;
  $("#cbM").classList.remove("open");
  startCards(L);
  toast(`جلسه‌ی مرور: ${fa(L.length)} کارت`);
});

$("#admClose").addEventListener("click",()=>$("#admM").classList.remove("open"));
$("#admM").addEventListener("click",e=>{if(e.target.id==="admM")$("#admM").classList.remove("open")});
$("#admClear").addEventListener("click",()=>{
  askConfirm("همه‌ی گزارش‌ها پاک شود؟",()=>{
    Object.keys(REPORTS).forEach(k=>delete REPORTS[k]);
    saveReports();renderAdmin();refreshAll();toast("گزارش‌ها پاک شد.");
  });
});
$("#admBody").addEventListener("click",e=>{
  const sd=e.target.closest("[data-admsend]");
  if(sd){
    const k=sd.dataset.admsend;
    const ta=document.querySelector(`[data-admta="${CSS.escape(k)}"]`);
    const body=ta?ta.value.trim():"";
    if(!body){toast("متن پاسخ خالی است.");return}
    adminReply(k,body);renderAdmin();
    return;
  }
  const dl=e.target.closest("[data-admdel]");
  if(dl){delete REPORTS[dl.dataset.admdel];saveReports();renderAdmin();refreshAll();return}
});

window.adminReply=function(key,body){
  if(!REPORTS[key]){console.warn("گزارشی با این کلید نیست:",key);return}
  REPORTS[key].msgs.push({by:"admin",body,ts:Date.now()});
  REPORTS[key].state="answered";
  REPORTS[key].seen=false;                 /* تا در تب گزارش‌ها نشان تازه بگیرد */
  saveReports();refreshAll();updRepBadge();
  if(!$("#tab-reports").hidden)renderMyReports();
  notifyReportAnswered(key);
  toast("پاسخ پشتیبانی ثبت شد. روی همان کلمه یا سؤال، دکمه‌ی ⚑ حالا سبز است.");
};

document.addEventListener("click",e=>{
  const b=e.target.closest("[data-rep]");
  if(b){openReport(b.dataset.rep,b.dataset.rt||"");e.stopPropagation();e.preventDefault()}
},true);

/* ================= یادداشت‌های سراسری ================= */
/* یک منبع واحد: هر کلمه یا تست یک یادداشت دارد و از هر نقطه‌ی پلتفرم
   همان یادداشت دیده و ویرایش می‌شود. کلید: w:<کلمه>  یا  q:<سال>|<رشته>|<شماره> */
const NOTES=LS.get("zban_notes")||{};
function nHas(k){return !!(NOTES[k]&&NOTES[k].trim())}
function nBtn(k,title,extra){
  return `<button class="ib ${nHas(k)?"hasnote":""} ${extra||""}" data-note="${k}" data-nt="${title}"
    data-tip="${nHas(k)?"یادداشت شما — برای ویرایش بزنید":"یادداشت شخصی"}">✎</button>`;
}
function nBox(k){
  return nHas(k)?`<div class="nt-box"><div class="h">یادداشت شما</div>${NOTES[k].replace(/</g,"&lt;")}</div>`:"";
}
let _nk=null;
function openNote(k,title){
  _nk=k;
  $("#noteTitle").textContent=title||"یادداشت";
  $("#noteSub").textContent = k.charAt(0)==="q"
    ? "این یادداشت هرجای پلتفرم که همین تست را ببینید نمایش داده می‌شود."
    : "این یادداشت هرجای پلتفرم که همین کلمه را ببینید نمایش داده می‌شود.";
  $("#noteTa").value=NOTES[k]||"";
  $("#noteDel").hidden=!nHas(k);
  $("#noteM").classList.add("open");
  setTimeout(()=>$("#noteTa").focus(),60);
}
function closeNote(){$("#noteM").classList.remove("open");_nk=null}
function afterNote(){
  LS.set("zban_notes",NOTES);
  refreshAll();
  if(curView&&curView.t==="word")renderWord(curView.w);
  else if(curView&&curView.t==="q")renderQuestion(curView.y,curView.e,curView.q);
  if(!$("#tab-exam").hidden&&EX.stage)paintBook();
}
document.addEventListener("click",e=>{
  const b=e.target.closest("[data-note]");if(!b)return;
  e.preventDefault();e.stopPropagation();
  openNote(b.dataset.note,b.dataset.nt||"یادداشت");
},true);
/* یادداشت روی سرور — تا این نسخه فقط در حافظه‌ی صفحه نوشته می‌شد و با هر
   رفرش، نسخه‌ی خالی سرور رویش می‌نشست. نسخه‌ی محلی می‌ماند تا اگر ذخیره
   نشد، متن کاربر تا رفرش بعدی از دست نرود. */
function noteToServer(k,body){
  ZABAN.note(k,body).catch(e=>{
    console.error(e);
    toast("یادداشت روی سرور ذخیره نشد. اتصال را بررسی کنید و دوباره «ذخیره» را بزنید.");
  });
}
$("#noteSave").addEventListener("click",()=>{
  const k=_nk, v=$("#noteTa").value;
  if(v.trim())NOTES[k]=v;else delete NOTES[k];
  noteToServer(k, v.trim()?v:"");
  closeNote();afterNote();
});
$("#noteDel").addEventListener("click",()=>{
  const k=_nk;
  delete NOTES[k]; noteToServer(k,"");
  closeNote();afterNote();
});
$("#noteClose").addEventListener("click",closeNote);
$("#noteM").addEventListener("click",e=>{if(e.target.id==="noteM")closeNote()});

/* ================= آزمون شبیه‌سازی‌شده ================= */
/* در نسخه‌ی demo سؤالی وجود نداشت و از روی ظهور کلمات ساخته می‌شد، پس
   QIDX از WORDS پر می‌شد. حالا سؤال‌های واقعی داریم و مرجع باید خود
   QUESTIONS باشد — وگرنه هر سؤالی که کلمه‌ای به آن نسبت داده نشده،
   از دفترچه می‌افتد (مثل کلوز ۱۴۰۳ که سه سؤالش گم می‌شد). */
const QIDX={};
function buildQIDX(){
  for(const k in QIDX) delete QIDX[k];
  (window.QUESTIONS||[]).forEach(q => { QIDX[q.y+"|"+q.e+"|"+q.q]=1 });
}
buildQIDX();
document.addEventListener("zaban:questions-ready", buildQIDX);

const EX={y:null,e:EXAM_NAMES[0],tmode:"std",mode:"fb",items:[],groups:[],
          ans:{},bm:{},out:{},rev:{},dur:0,deadline:0,started:0,timer:null,
          stage:null,res:null,studyBtns:false};

const BM_ON ='<svg width="12" height="15" viewBox="0 0 12 15" aria-hidden="true"><path d="M1 1.2h10v12.4L6 10.3l-5 3.3z" fill="currentColor"/></svg>';
const BM_OFF='<svg width="12" height="15" viewBox="0 0 12 15" aria-hidden="true"><path d="M1.4 1.6h9.2v11.3L6 9.8l-4.6 3.1z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>';

const EXAM_START={"مهندسی کامپیوتر":1381,"آی‌تی":1383,"علوم کامپیوتر":1386};
function examYears(e){return YEARS.filter(y=>y>=(EXAM_START[e]||0))}
function examCount(y,e){let n=0;structFor(y).forEach(g=>{for(let q=g[3];q<=g[4];q++)if(QIDX[y+"|"+e+"|"+q])n++});return n}
function examTotal(y){const g=structFor(y);return g.length?g[g.length-1][4]:0}
function mmss(s){s=Math.max(0,Math.round(s));
  const h=Math.floor(s/3600),m=Math.floor(s%3600/60),x=s%60,p=n=>fa(String(n).padStart(2,"0"));
  return (h?p(h)+":":"")+p(m)+":"+p(x);
}
function examTitle(){return "شبیه‌سازی کنکور ارشد "+EX.e+" "+fa(EX.y)}
function exInRun(y,e,q){return EX.stage==="run"&&EX.y===y&&EX.e===e&&EX.items.some(i=>i.q===q)}

/* ---- ذخیره و بازیابی ---- */
/* ← نقطه‌ی اتصال ۳: وضعیت آزمون روی سرور، با debounce دو ثانیه‌ای */
function saveEX(){
  if(EX.stage!=="run"){LS.del("zban_exam");return}
  const state={y:EX.y,e:EX.e,tmode:EX.tmode,mode:EX.mode,ans:EX.ans,bm:EX.bm,out:EX.out,
    rev:EX.rev,dur:EX.dur,deadline:EX.deadline,started:EX.started,studyBtns:EX.studyBtns,attemptId:EX.attemptId};
  LS.set("zban_exam",state);
  if(EX.attemptId)ZABAN.examSave(EX.attemptId,state);
}
function buildItems(y,e){
  const items=[],groups=[];
  structFor(y).forEach(g=>{
    const grp={sec:g[0],label:g[1],p:g[2]||0,qs:[]};
    for(let q=g[3];q<=g[4];q++){
      if(!QIDX[y+"|"+e+"|"+q])continue;
      const Q=makeQ(y,e,q);
      if(Q){const it={q,sec:g[0],label:g[1],p:g[2]||0,Q};items.push(it);grp.qs.push(it)}
    }
    if(grp.qs.length)groups.push(grp);
  });
  return {items,groups};
}

function renderExamHome(){
  EX.stage=null;EX.res=null;clearInterval(EX.timer);
  $("#examRun").hidden=true;$("#examRes").hidden=true;$("#examHome").hidden=false;
  syncUrl(true);                                              /* /zaban/exam */
  const y=EX.y,e=EX.e;
  const cnt=y?examCount(y,e):0, tot=y?examTotal(y):0;
  const std=Math.max(5,Math.round(cnt*1.2)), tight=Math.max(4,Math.round(cnt*0.8));
  EX.dur=(EX.mode==="wb"||EX.tmode==="none")?0:(EX.tmode==="tight"?tight:std)*60;
  const sv=LS.get("zban_exam");
  $("#examHome").innerHTML=`
  ${sv?`<div class="ex-resume">
    <div><b>آزمون نیمه‌تمام</b><div class="capt" style="margin:2px 0 0">
      شبیه‌سازی کنکور ارشد ${sv.e} ${fa(sv.y)} — ${fa(Object.keys(sv.ans||{}).length)} سؤال پاسخ‌داده
      ${sv.dur?` · ${mmss(Math.max(0,(sv.deadline-Date.now())/1000))} زمان باقی‌مانده`:" · بدون زمان‌سنج"}</div></div>
    <div class="row-f" style="margin-right:auto">
      <button class="mx-go" id="mxResume">ادامه‌ی آزمون</button>
      <button class="ghost" id="mxDrop">حذف</button></div>
  </div>`:""}
  <div class="mx-hero">
    <h2>آزمون آزمایشی درس زبان</h2>
    <p>دفترچه‌ی هر سال با همان ساختار واقعی‌اش — وکب، متن کلوز و سؤال‌هایش، سه پسیج و سؤال‌هایشان — پشت‌سرهم و بدون صفحه‌بندی.</p>

    <div class="label">حالت آزمون</div>
    <div class="seg" id="mxMode">
      <button data-md="fb" class="${EX.mode==="fb"?"on":""}">فیدبک</button>
      <button data-md="wb" class="${EX.mode==="wb"?"on":""}">واشبک</button>
    </div>
    <div class="capt" style="margin:8px 0 0;max-width:640px">
      ${EX.mode==="fb"
        ? "<b>فیدبک —</b> شرایط واقعی جلسه. زمان‌سنج روشن است و تا وقتی آزمون تمام نشود هیچ پاسخی دیده نمی‌شود؛ در پایان کارنامه و پاسخ‌برگ کامل می‌آید. برای سنجش."
        : "<b>واشبک —</b> حالت یادگیری. زمان‌سنجی در کار نیست و زیر هر تست دکمه‌ی «پاسخ» هست تا همان‌جا جواب و کلماتش را ببینید. برای دور اول و مرور، نه برای سنجش."}
    </div>

    <div class="label" style="margin-top:14px">رشته</div>
    <div class="seg" id="mxExam">${EXAM_NAMES.map(n=>`<button data-e="${n}" class="${n===e?"on":""}">${n}</button>`).join("")}</div>
    <div class="label" style="margin-top:14px">سال کنکور
      <span style="font-weight:400;color:var(--ink-3)">— ${e} از کنکور ${fa(EXAM_START[e])} برگزار شده است</span></div>
    <div class="mx-years" id="mxYears">${examYears(e).slice().reverse().map(yy=>{
      const c=examCount(yy,e);
      return `<button class="mx-y ${yy===y?"on":""} ${c?"":"no"}" data-y="${yy}" ${c?"":"disabled"}>
        <b>${fa(yy)}</b><span>${c?fa(c)+" سؤال":"بدون داده"}</span></button>`}).join("")}</div>
    ${y?`
    <div class="label" style="margin-top:14px">ساختار دفترچه‌ی ${fa(y)}</div>
    <div class="mx-plan">${structFor(y).map(g=>`<span>${g[1]}: ${fa(g[3])}–${fa(g[4])}</span>`).join("")}</div>
    ${EX.mode==="fb"?`
    <div class="label" style="margin-top:14px">زمان</div>
    <div class="seg" id="mxTime">
      <button data-t="std" class="${EX.tmode==="std"?"on":""}">استاندارد (${fa(std)} دقیقه)</button>
      <button data-t="tight" class="${EX.tmode==="tight"?"on":""}">فشرده (${fa(tight)} دقیقه)</button>
      <button data-t="none" class="${EX.tmode==="none"?"on":""}">بدون زمان</button>
    </div>`:""}
    <div class="row-f" style="margin-top:14px">
      <button class="mini ${EX.studyBtns?"on":""}" id="mxStudy">
        ${EX.studyBtns?"✓":"○"} نمایش دکمه‌های منتخب و دک مرور حین آزمون</button>
      <span class="capt" style="margin:0">در حالت فیدبک پیشنهاد می‌شود خاموش بماند.</span>
    </div>
    <div class="capt" style="margin:12px 0 0">
      ${cnt<tot?`از ${fa(tot)} سؤال دفترچه، ${fa(cnt)} سؤال در بانک ثبت شده و در آزمون می‌آید.`:`هر ${fa(cnt)} سؤال دفترچه در آزمون می‌آید.`}
      نمره با نمره‌ی منفی یک‌سوم حساب می‌شود. پاسخ‌ها روی همین مرورگر ذخیره می‌شود؛ اگر صفحه رفرش یا بسته شود آزمون از همان‌جا ادامه پیدا می‌کند.
    </div>
    <div style="margin-top:16px"><button class="mx-go" id="mxStart">شروع ${examTitle()}</button></div>
    `:`<div class="capt" style="margin-top:14px">یک سال را انتخاب کنید.</div>`}
  </div>
  ${histHTML()}`;
}

/* فیلترهای تاریخچه — چون تاریخچه با هر رندر دوباره ساخته می‌شود،
   شنونده روی خود صفحه است نه روی ورودی‌ها. */
document.addEventListener("input",e=>{
  if(e.target&&e.target.id==="hQ"){
    HF.q=e.target.value;
    const pos=e.target.selectionStart;
    refreshHist();
    const again=document.getElementById("hQ");
    if(again){again.focus();try{again.setSelectionRange(pos,pos)}catch(_){}}
  }
});
document.addEventListener("change",e=>{
  if(!e.target)return;
  if(e.target.id==="hYear"){HF.year=e.target.value;refreshHist()}
  if(e.target.id==="hMode"){HF.mode=e.target.value;refreshHist()}
});

/* فرانت‌اند با نام فارسی رشته و کد کوتاه حالت کار می‌کند («مهندسی کامپیوتر»، «fb»)
   ولی سرور کد رشته و نام کامل حالت می‌خواهد («ce»، «feedback»). */
const EXAM_CODE={"مهندسی کامپیوتر":"ce","آی‌تی":"it","علوم کامپیوتر":"cs"};

async function examStart(){
  const {items,groups}=buildItems(EX.y,EX.e);
  if(!items.length){toast("برای این سال و رشته سؤالی ساخته نشد.");return}
  EX.items=items;EX.groups=groups;EX.ans={};EX.bm={};EX.out={};EX.rev={};EX.res=null;
  EX.stage="run";EX.started=Date.now();
  syncUrl(true);                                              /* /zaban/exam/run */
  EX.deadline=EX.dur?Date.now()+EX.dur*1000:0;
  EX.attemptId=null;

  /* بدون ثبت روی سرور، ردیفی در exam_attempts نمی‌ماند، تب تحلیل خالی
     می‌ماند و examFinish کلید پاسخ نمی‌گیرد. اگر ثبت نشد، آزمون در
     مرورگر ادامه پیدا می‌کند — بهتر از گیر افتادن کاربر پشت یک خطا. */
  try{
    const res=await ZABAN.examStart(
      EX.y, EXAM_CODE[EX.e]||EX.e,
      EX.mode==="wb"?"washback":"feedback", EX.dur||0);
    EX.attemptId=(res&&res.id)||null;
    if(res&&res.resumed)toast("آزمون باز قبلی شما ادامه پیدا کرد.");
  }catch(err){
    console.error(err);
    toast("آزمون روی سرور ثبت نشد؛ نتیجه‌اش در کارنامه نمی‌آید.");
  }

  startTick();saveEX();
  $("#examHome").hidden=true;$("#examRes").hidden=true;$("#examRun").hidden=false;
  paintRun();window.scrollTo({top:0,behavior:"smooth"});
}

function examResume(){
  const sv=LS.get("zban_exam");if(!sv)return;
  Object.assign(EX,{y:sv.y,e:sv.e,tmode:sv.tmode,mode:sv.mode,ans:sv.ans||{},bm:sv.bm||{},
    out:sv.out||{},rev:sv.rev||{},dur:sv.dur,deadline:sv.deadline,started:sv.started,
    studyBtns:!!sv.studyBtns,attemptId:sv.attemptId||null,res:null,stage:"run"});
  syncUrl(false);
  const b=buildItems(EX.y,EX.e);EX.items=b.items;EX.groups=b.groups;
  $("#examHome").hidden=true;$("#examRes").hidden=true;$("#examRun").hidden=false;
  if(EX.dur&&exLeft()<=0){examFinish(true);return}
  startTick();paintRun();window.scrollTo({top:0,behavior:"smooth"});
}
function exLeft(){return EX.dur?Math.max(0,(EX.deadline-Date.now())/1000):0}
function startTick(){
  clearInterval(EX.timer);
  if(!EX.dur)return;
  EX.timer=setInterval(()=>{
    if(exLeft()<=0){clearInterval(EX.timer);examFinish(true);return}
    tickClock();
  },1000);
}

/* ---- ستون سمت راست ---- */
function sideHTML(){
  const n=EX.items.length,done=Object.keys(EX.ans).length,bm=Object.keys(EX.bm).length,R=EX.res;
  const left=exLeft(),frac=EX.dur?left/EX.dur:1;
  const C=2*Math.PI*44, off=C*(1-frac);
  const col=left<=60?"#c05a35":left<=180?"#d99521":"#5f9c4c";
  const clock=(EX.dur&&!R)?`
    <svg width="98" height="98" viewBox="0 0 104 104">
      <circle cx="52" cy="52" r="44" fill="none" stroke="var(--canvas)" stroke-width="7"/>
      <circle id="exClockArc" cx="52" cy="52" r="44" fill="none" stroke="${col}" stroke-width="7" stroke-linecap="round"
        stroke-dasharray="${C}" stroke-dashoffset="${off}"/>
      <text x="52" y="52" transform="rotate(90 52 52)" text-anchor="middle" dominant-baseline="central"
        id="exClockT" class="ex-clock ${left<=180?"warn":""}">${mmss(left)}</text>
    </svg>`:"";
  return `
  <div class="ex-card">
    <div class="ex-ring">
      ${clock}
      <div class="tt"><b>${examTitle()}</b>
        <div>${fa(n)} سؤال · حالت ${EX.mode==="wb"?"واشبک":"فیدبک"}${EX.dur?"":" · بدون زمان‌سنج"}</div></div>
    </div>
    ${R?`<button class="mx-go" style="width:100%" id="mxHome">آزمون جدید</button>`
       :`<button class="ex-fin" id="mxEnd">✔ اتمام آزمون</button>`}
    <div class="ex-cnt">
      ${R?`<span>درست <b style="color:#1c6b46">${fa(R.right)}</b></span>
           <span>غلط <b style="color:#a8461f">${fa(R.wrong)}</b></span>
           <span>نزده <b>${fa(R.blank)}</b></span>`
        :`<span>سؤال بی‌پاسخ <b>${fa(n-done)}</b></span>
          <span>نشان‌شده <i>${BM_ON}</i> <b>${fa(bm)}</b></span>`}
    </div>
    <div class="ex-grid" id="exGrid">${EX.items.map(it=>{
      let c="";
      if(R){const a=EX.ans[it.q];c=a===undefined?"":a===it.Q.ans?"ok":"bad"}
      else if(EX.ans[it.q]!==undefined)c="done";
      return `<button class="ex-g ${c} ${EX.bm[it.q]?"bm":""}" data-go="${it.q}">${fa(it.q)}</button>`}).join("")}</div>
  </div>
  ${R?"":`<div class="ex-card" style="padding:12px 14px">
    <div class="capt" style="margin:0">کلیدهای ۱ تا ۴ گزینه‌ی سؤالی را که وسط صفحه است انتخاب می‌کند. شماره‌های بالا شما را به همان سؤال می‌برند.</div>
  </div>`}`;
}
function paintSide(){const s=$("#exSide");if(s)s.innerHTML=sideHTML()}
/* فقط عقربه را تازه می‌کند. بازسازی کل ستون هر ثانیه باعث می‌شد دکمه‌ها
   بین mousedown و mouseup نابود شوند و کلیک اصلاً ثبت نشود. */
function tickClock(){
  const t=document.getElementById("exClockT"),a=document.getElementById("exClockArc");
  if(!t||!a||!EX.dur)return;
  const left=exLeft(),C=2*Math.PI*44;
  t.textContent=mmss(left);
  t.setAttribute("class","ex-clock"+(left<=180?" warn":""));
  a.setAttribute("stroke-dashoffset",C*(1-left/EX.dur));
  a.setAttribute("stroke",left<=60?"#c05a35":left<=180?"#d99521":"#5f9c4c");
}

/* ---- یک سؤال ---- */
function optsHTML(it,big){
  const Q=it.Q,R=EX.res,a=EX.ans[it.q],out=EX.out[it.q]||{},shown=R||EX.rev[it.q];
  /* view از پلتفرم آزمون می‌آید: ۳ = چهار گزینه در یک ردیف،
   ۶ = دو تایی، ۱۲ = هر گزینه یک ردیف کامل. */
  const cols = Q.view===12 ? "c1" : Q.view===6 ? "c2" : "c4";
  return `<div class="ex-os ${cols}${big?" big":""}">${Q.opts.map((o,i)=>{

    let c=a===i?"sel":"";
    if(out[i])c+=" out";
    if(shown){if(i===Q.ans)c+=" right";else if(a===i)c+=" wrong"}
    return `<div class="ex-o ${c}">
      <div class="box" ${R?"":`data-pick="${it.q}|${i}"`}>
        <span class="nb">${fa(i+1)}</span><span class="ck">✓</span><span class="tx en">${o.w}</span>
      </div>
      ${R?"":`<button class="ex-rd" data-elim="${it.q}|${i}">${out[i]?"پاکسازی":"رد گزینه"}</button>`}
    </div>`}).join("")}</div>`;
}
function cleanFa(t){return String(t||"").replace(/^[\s—–-]+/,"").replace(/^\((.*)\)$/,"$1").trim()}
/* پاسخ تشریحی فقط از ستون explanation سرور. تا نسخه‌ی دمو اینجا یک متن
   قالبی ساخته می‌شد («تنها گزینه‌ای است که با معنای جمله جور درمی‌آید»)
   که برای هر سؤالی ادعای یکسان داشت. */
function explainOf(Q){
  const a=Q&&Q.qid?ANS[Q.qid]:null;
  return a&&a.exp ? a.exp
    : '<span style="color:var(--ink-3)">پاسخ تشریحی برای این سؤال هنوز ثبت نشده است.</span>';
}
function allWordsOf(Q){
  const seen={},out=[];
  Q.opts.concat(Q.ws).forEach(w=>{if(!seen[w.w]){seen[w.w]=1;out.push(w)}});
  return out;
}
function wordChips(list,title){
  if(!list.length)return "";
  const inAll=list.every(w=>deck.has(w.w));
  return `<div class="ex-wg"><div class="th"><span class="t">${title} (${fa(list.length)})</span>
      <button class="ib ib-l ${inAll?"ondeck":""}" data-tdeck="${list.map(w=>w.w).join(",")}">${inAll?"✓ در دک":"+ این "+fa(list.length)+" کلمه به دک"}</button></div>
    <div class="ex-ws">${list.map(w=>`<button data-w="${w.w}" class="${deck.has(w.w)?"in":""}"
        data-tip="برای دیدن کارت کامل «${w.w}» بزنید">
        <bdi class="en">${w.w}</bdi>
        <span class="fa">${cleanFa(w.fa)||"—"}</span>
        ${deck.has(w.w)?'<i class="dk">در دک</i>':""}
      </button>`).join("")}</div></div>`;
}
/* یک خط آمار جمعی، همیشه دیده می‌شود.
   عدد از qGlobal می‌آید — همان چیزی که پشت دکمه‌ی «آمار این تست» بود.
   دانستن اینکه اکثریت هم غلط زده‌اند، هم اطلاعات است هم دلگرمی. */
function crowdLine(y,e,q,ans){
  if(y==null||e==null||q==null)return "";
  const g=qGlobal(y,e,q,ans);
  if(!g||!g.total)return "";
  const wrongPct=Math.round((g.wrong+g.blank)/g.total*100);
  const rightPct=100-wrongPct;
  const tone = wrongPct>=70?"hard" : wrongPct>=45?"mid" : "easy";
  const txt = wrongPct>=70 ? `این تست سخت بوده — ${fa(wrongPct)}٪ کاربران غلط زده یا نزده‌اند`
            : wrongPct>=45 ? `${fa(wrongPct)}٪ کاربران این تست را غلط زده یا نزده‌اند`
            : `${fa(rightPct)}٪ کاربران این تست را درست زده‌اند`;
  return `<div class="crowd ${tone}" data-tip="از ${fa(g.total)} نفری که این تست را دیده‌اند">
      <span class="cbar"><i style="width:${rightPct}%"></i></span>
      <span class="ct">${txt}</span>
    </div>`;
}

function answerBox(it){
  const Q=it.Q, optW=Q.opts.map(o=>o.w);
  const stemWs=Q.ws.filter(w=>optW.indexOf(w.w)<0);
  if (Q.ans == null || !Q.opts[Q.ans]) {
    return `<div class="ex-ans wait">${ansWaitMsg(Q)}</div>`;
  }

  return `<div class="ex-ans"><b>پاسخ درست: گزینه‌ی ${fa(Q.ans+1)} — <span class="en">${Q.opts[Q.ans].w}</span></b>
    <span>${Q.opts[Q.ans].fa}</span></div>
    ${crowdLine(it.y ?? EX.y, it.e ?? EX.e, it.q, Q.ans)}
    <div class="ex-exp"><span class="t">پاسخ تشریحی</span>${explainOf(Q)}</div>
    ${wordChips(Q.opts,"کلمات گزینه‌ها")}
    ${wordChips(stemWs,"کلمات صورت سؤال و متن")}`;
}
function qTools(it,big){
  const Q=it.Q,R=EX.res,a=EX.ans[it.q];
  const nk="q:"+EX.y+"|"+EX.e+"|"+it.q, qk=qKey(EX.y,EX.e,it.q);
  const verdict=R?(a===undefined?"skip":a===Q.ans?"ok":"bad"):"";
  return `<div class="ex-tools">
      ${R?`<span class="ex-vd ${verdict}">${verdict==="ok"?"درست":verdict==="bad"?"غلط":"نزده"}</span>`:""}
      ${(!R&&EX.mode==="wb")?`<button class="ib ib-l" data-rev="${it.q}">${EX.rev[it.q]?"بستن پاسخ":"پاسخ"}</button>`:""}
      <button class="ib ${EX.bm[it.q]?"on":""}" data-bm="${it.q}" data-tip="نشان کردن این تست برای بازگشت بعدی">${EX.bm[it.q]?BM_ON:BM_OFF}</button>
      ${nBtn(nk,"یادداشت تست "+fa(it.q)+" — کنکور "+fa(EX.y)+" "+EX.e)}
      ${(R||EX.studyBtns)?`
        <button class="ib ${starQ.has(qk)?"onstar2":""}" data-qstar="${qk}" data-tip="منتخب من">★</button>
        <button class="ib ${deckQ.has(qk)?"ondeck":""}" data-qdeck="${qk}" data-tip="افزودن این تست به دک مرور">▤</button>
        <button class="ib ib-l" data-tdeck="${allWordsOf(Q).map(w=>w.w).join(",")}"
          data-tip="گزینه‌ها و کلمات صورت سؤال: ${allWordsOf(Q).map(w=>w.w).join("، ")}">+ هر ${fa(allWordsOf(Q).length)} کلمه‌ی این تست به دک</button>`:""}
      ${big?"":`<button class="ib ib-l" data-goq2="${EX.y}|${EX.e}|${it.q}" style="margin-right:auto" data-tip="نمایش بزرگ همین تست">نمایش تمام‌صفحه</button>`}
    </div>`;
}
function qHTML(it){
  const Q=it.Q,R=EX.res,a=EX.ans[it.q];
  const nk="q:"+EX.y+"|"+EX.e+"|"+it.q;
  const verdict=R?(a===undefined?"skip":a===Q.ans?"ok":"bad"):"";
  const shown=R||EX.rev[it.q];
  return `<div class="ex-q ${R?verdict:(EX.bm[it.q]?"bm":"")}" id="exq-${it.q}" data-q="${it.q}">
    <div class="ex-st"><span class="num">${fa(it.q)}.</span>${Q.stem.replace("____","<u></u>")}</div>
    ${optsHTML(it)}
    ${qTools(it)}
    ${shown?answerBox(it):""}
    ${nBox(nk)}
  </div>`;
}
function paintQ(q){
  const el=document.getElementById("exq-"+q),it=EX.items.find(x=>x.q===q);
  if(el&&it)el.outerHTML=qHTML(it);
  if(curView&&curView.t==="q"&&curView.q===q&&$("#qview").classList.contains("open"))
    renderQuestion(EX.y,EX.e,q);
}

/* ---- کل دفترچه ---- */
const DIRS={
  "وکب":"Directions: Choose the word (1), (2), (3), or (4) that best completes each sentence.",
  "کلوز تست":"Directions: Read the following passage and decide which choice best fits each space.",
  "پسیج":"Directions: Read the following passage and answer the questions by choosing the best choice."
};
function bookHTML(){
  return EX.groups.map((g,gi)=>{
    const head=`<div class="ex-part">
      <h3>${g.sec==="وکب"?"بخش اول — واژگان":g.sec==="کلوز تست"?"بخش دوم — کلوز تست":"بخش سوم — درک مطلب · "+g.label}
        <small>سؤال ${fa(g.qs[0].q)} تا ${fa(g.qs[g.qs.length-1].q)}</small></h3>
      ${gi===0||g.sec!==EX.groups[gi-1].sec?`<div class="ex-dir en">${DIRS[g.sec]}</div>`:""}
      ${g.sec!=="وکب"?textBlock(EX.y,EX.e,g.sec,g.p):""}
    </div>`;
    const qs=g.qs.map(qHTML).join("");
    return g.sec==="وکب"?head+qs
      :`<div class="ex-tie">${head}${qs}<div class="ex-tiefoot">پایان سؤال‌های ${g.label}</div></div>`;
  }).join("");
}
function paintBook(){const b=$("#exBody");if(b)b.innerHTML=bookHTML()}
function paintRun(){
  $("#examRun").innerHTML=`<div class="ex-layout"><aside class="ex-side" id="exSide"></aside>
    <div class="ex-book" id="exBook"><div id="exSummary">${EX.res?summaryHTML():""}</div>
    <div id="exBody"></div></div></div>`;
  paintSide();paintBook();

  /* کارنامه‌ی باز شده از تاریخچه، یا واشبک بعد از رفرش: پاسخ‌ها در
     آیتم‌ها نیستند و یک‌جا گرفته می‌شوند. ANS بعد از پاسخ سرور دیگر
     undefined نیست، پس این فراخوانی دوباره تکرار نمی‌شود. */
  const miss=EX.items.filter(it=>(EX.res||EX.rev[it.q])&&it.Q.ans==null&&it.Q.qid
                                 &&ANS[it.Q.qid]===undefined&&!ANS_LOCK[it.Q.qid]).map(it=>it.Q.qid);
  if(miss.length)fetchAns(miss).then(()=>{EX.items.forEach(it=>applyAns(it.Q));paintRun()})
                              .catch(e=>console.error(e));
}

/* ---- پایان و کارنامه ---- */
function summaryHTML(){
  const R=EX.res,n=EX.items.length;
  const col=R.pct>=60?"#5f9c4c":R.pct>=30?"#d99521":"#c05a35";
  const sgn=p=>(p<0?"−":"")+fa(Math.abs(p));
  return `
  <div class="mx-score">
    <div><div class="mx-big" style="color:${col}">${R.pct<0?"−":""}${fa(Math.abs(R.pct).toFixed(1))}<small>٪</small></div>
      <div class="capt" style="margin:6px 0 0">درصد با نمره‌ی منفی${R.pct<0?" — غلط‌ها بیش از سه برابر درست‌ها بوده‌اند":""}</div></div>
    <div class="stats" style="flex:1;margin:0;min-width:250px">
      <div class="stat"><div class="k">درست</div><div class="v" style="color:#5f9c4c">${fa(R.right)}</div></div>
      <div class="stat"><div class="k">غلط</div><div class="v" style="color:#c05a35">${fa(R.wrong)}</div></div>
      <div class="stat"><div class="k">نزده</div><div class="v" style="color:var(--ink-3)">${fa(R.blank)}</div></div>
      <div class="stat"><div class="k">زمان مصرفی</div><div class="v" style="font-size:20px">${mmss(R.used||0)}</div>
        <div class="capt">${n?fa(Math.round((R.used||0)/n)):"—"} ثانیه برای هر سؤال</div></div>
    </div>
  </div>
  ${R.auto?`<div class="pr-note">زمان آزمون تمام شد و پاسخ‌برگ به‌طور خودکار بسته شد.</div>`:""}
  ${R.replay?`<div class="pr-note">این کارنامه از تاریخچه باز شده است.</div>`:""}
  <div class="panel">
    <div class="row-f" style="margin-bottom:10px">
      <div class="label" style="margin:0">عملکرد به تفکیک بخش</div>
      <button class="ghost" id="mxAddWrong" style="margin-right:auto" data-tip="کلمه‌ی پاسخ درستِ هر تستی که غلط زده‌اید یا نزده‌اید به دک اضافه می‌شود">افزودن کلمه گزینه درست تست‌های غلط و نزده به دک</button>
      <button class="ghost" id="mxAgain">آزمون دوباره‌ی همین سال</button>
    </div>
    ${Object.keys(R.bySec||{}).map(s=>{const S=R.bySec[s],t=S.r+S.w+S.b,p=Math.round((3*S.r-S.w)/(3*t)*100);
      return `<div class="hbar" style="cursor:default">
        <div class="lbl" style="direction:rtl;text-align:right;width:80px">${s}</div>
        <div class="track"><i class="fill" style="width:${Math.max(0,p)}%;background:${p>=60?"#5f9c4c":p>=30?"#d99521":"#c05a35"}"></i></div>
        <div class="hpos" style="width:210px;text-align:right;direction:rtl">درست ${fa(S.r)} از ${fa(t)}
          <span style="color:${p>=60?"#1c6b46":p>=30?"#a5811a":"#a8461f"}">(${sgn(p)}٪)</span></div>
      </div>`}).join("")}
    <div class="capt" style="margin:10px 0 0">پاسخ‌برگ کامل پایین آمده است: پاسخ درست هر تست، کلماتش و دکمه‌های منتخب و دک مرور.</div>
  </div>`;
}
/* ← نقطه‌ی اتصال ۴: تصحیح سمت سرور.
   کارنامه از پاسخ سرور ساخته می‌شود؛ محاسبه‌ی محلی فقط پشتیبان است
   برای وقتی که شبکه قطع باشد. */
async function examFinish(auto){
  clearInterval(EX.timer);

  if(EX.attemptId){
    try{
      const res=await ZABAN.examFinish(EX.attemptId);
      EX.key={}; (res.key||[]).forEach(k=>{EX.key[k.q]={correct:k.correct,chosen:k.chosen,
                                                       explanation:k.explanation,words:k.words}});
      EX.items.forEach(it=>{const k=EX.key[it.q];if(!k)return;
        if(it.Q.qid)delete ANS_LOCK[it.Q.qid];
        if(k.correct==null){ if(it.Q.qid)ANS_LIMIT[it.Q.qid]=1; return }   /* سقف روزانه */
        it.Q.ans=k.correct-1;
        if(it.Q.qid)ANS[it.Q.qid]={ans:k.correct-1,exp:k.explanation||""}});
      if(res.limit_message){ANS_LIMIT_MSG=res.limit_message; toast(res.limit_message)}
      EX.res={right:res.correct,wrong:res.wrong,blank:res.blank,used:res.used_sec,
              bySec:res.bySection||{},auto:res.auto_closed,pct:res.percent};
      EX.stage="res";LS.del("zban_exam");
      syncUrl(false);                                         /* اجرا ← کارنامه (جای همان قدم) */
      finishTail();
      return;
    }catch(e){ toast("تصحیح از سرور نیامد؛ نتیجه‌ی محلی نشان داده می‌شود."); }
  }

  const used=EX.dur?(EX.dur-exLeft()):(Date.now()-EX.started)/1000;
  const n=EX.items.length;let right=0,wrong=0,blank=0;const bySec={};
  EX.items.forEach(it=>{
    const a=EX.ans[it.q],S=bySec[it.sec]=bySec[it.sec]||{r:0,w:0,b:0};
    if(a===undefined){blank++;S.b++}else if(a===it.Q.ans){right++;S.r++}else{wrong++;S.w++}
  });
  EX.res={right,wrong,blank,used,bySec,auto,pct:Math.round((3*right-wrong)/(3*n)*1000)/10};
  EX.stage="res";LS.del("zban_exam");
  syncUrl(false);
  finishTail();
}

function finishTail(){
  const R=EX.res;
  histAdd({ts:Date.now(),y:EX.y,e:EX.e,mode:EX.mode,dur:EX.dur,ans:Object.assign({},EX.ans),
           right:R.right,wrong:R.wrong,blank:R.blank,used:R.used,bySec:R.bySec,pct:R.pct});
  actAdd("q",R.right+R.wrong);actAdd("qOk",R.right);
  closeModals();
  $("#examHome").hidden=true;$("#examRes").hidden=true;$("#examRun").hidden=false;
  paintRun();
  window.scrollTo({top:0,behavior:"smooth"});
}

/* ---- رویدادها ---- */
function exClick(t){
  const ex=t.closest("[data-e]");if(ex){EX.e=ex.dataset.e;
    if(ZABAN.questions)ZABAN.questions(EXAM_CODE[EX.e]||EX.e)
      .then(()=>{if(navTab==="exam"&&!EX.stage)renderExamHome()})
      .catch(e=>console.error(e));

    if(EX.y&&EX.y<(EXAM_START[EX.e]||0))EX.y=examYears(EX.e).slice(-1)[0];
    renderExamHome();return true}
  const md=t.closest("[data-md]");if(md){EX.mode=md.dataset.md;renderExamHome();return true}
  const yy=t.closest("[data-y]");if(yy&&!yy.disabled){EX.y=+yy.dataset.y;renderExamHome();return true}
  const tm=t.closest("[data-t]");if(tm){EX.tmode=tm.dataset.t;renderExamHome();return true}
  if(t.closest("#mxStudy")){EX.studyBtns=!EX.studyBtns;renderExamHome();return true}
  if(t.closest("#mxStart")){examStart();return true}
  if(t.closest("#mxResume")){examResume();return true}
  if(t.closest("#mxDrop")){LS.del("zban_exam");renderExamHome();return true}
  const hx=t.closest("[data-hex]");
  if(hx){HF.exam=hx.dataset.hex;HF.year="";refreshHist();return true}
  const hg=t.closest("[data-hagain]");
  if(hg){const p=hg.dataset.hagain.split("|");histAgain(p[0],p[1]);return true}
  if(t.closest("#hReset")){HF.exam=HF.year=HF.mode=HF.q="";refreshHist();return true}
  if(t.closest("#hClear")){askConfirm("کل تاریخچه‌ی آزمون‌ها پاک شود؟",()=>{
    HIST.length=0;LS.set("zban_hist",HIST);renderExamHome()},"پاک کن");return true}
  const hv=t.closest("[data-hist]"); if(hv){histOpen(hv.dataset.hist);return true}

  const pk=t.closest("[data-pick]");
  if(pk){const p=pk.dataset.pick.split("|"),q=+p[0],i=+p[1];
    if((EX.out[q]||{})[i])delete EX.out[q][i];
    EX.ans[q]=i;paintQ(q);paintSide();saveEX();return true}
  const el=t.closest("[data-elim]");
  if(el){const p=el.dataset.elim.split("|"),q=+p[0],i=+p[1];
    const o=EX.out[q]=EX.out[q]||{};
    if(o[i])delete o[i];else{o[i]=1;if(EX.ans[q]===i)delete EX.ans[q]}
    paintQ(q);paintSide();saveEX();return true}
  const bm=t.closest("[data-bm]");
  if(bm){const q=+bm.dataset.bm;EX.bm[q]?delete EX.bm[q]:EX.bm[q]=1;paintQ(q);paintSide();saveEX();return true}
  const rv=t.closest("[data-rev]");
  if(rv){const q=+rv.dataset.rev;EX.rev[q]?delete EX.rev[q]:EX.rev[q]=1;paintQ(q);saveEX();
    const it=EX.items.find(x=>x.q===q);
    if(EX.rev[q]&&it&&it.Q.ans==null)
      fetchAns([it.Q.qid]).then(()=>{applyAns(it.Q);paintQ(q)}).catch(e=>console.error(e));
    return true}
  const go=t.closest("[data-go]");
  if(go){const n=document.getElementById("exq-"+go.dataset.go);
    if(n)n.scrollIntoView({behavior:"smooth",block:"center"});return true}

  if(t.closest("#mxEnd")){
    const b=EX.items.length-Object.keys(EX.ans).length;
    if(b)askConfirm(fa(b)+" سؤال بی‌پاسخ مانده. آزمون تمام شود و کارنامه نمایش داده شود؟",
      ()=>examFinish(false),"تمام کن");
    else examFinish(false);
    return true}
  if(t.closest("#mxAgain")){examStart();return true}
  if(t.closest("#mxHome")){renderExamHome();return true}
  if(t.closest("#mxAddWrong")){
    let c=0,had=0,n=0;
    EX.items.forEach(it=>{if(EX.ans[it.q]===it.Q.ans)return;
      n++;const w=it.Q.opts[it.Q.ans];
      if(deck.has(w.w))had++;else{deck.add(w.w);c++}});
    refreshAll();
    toast(`از ${fa(n)} تست غلط و نزده، کلمه‌ی پاسخ درست برداشته شد: ${fa(c)} کلمه‌ی تازه به دک اضافه شد`
      +(had?` و ${fa(had)} کلمه از قبل در دک بود.`:"."));
    return true}
  return false;
}
$("#tab-exam").addEventListener("click",ev=>{
  const t=ev.target;
  if(exClick(t))return;
  const qs=t.closest("[data-qstar]");
  if(qs){const k=qs.dataset.qstar;starQ.has(k)?starQ.delete(k):starQ.add(k);paintQ(+k.split("|")[2]);refreshAll();return}
  const qd=t.closest("[data-qdeck]");
  if(qd){const k=qd.dataset.qdeck;deckQ.has(k)?deckQ.delete(k):deckQ.add(k);paintQ(+k.split("|")[2]);refreshAll();return}
  const td=t.closest("[data-tdeck]");
  if(td){toggleAll(deck,td.dataset.tdeck.split(","));refreshAll();paintBook();return}
  const gq=t.closest("[data-goq2]");
  if(gq){const p=gq.dataset.goq2.split("|");openQuestion(+p[0],p[1],+p[2]);return}
  const dw=t.closest("[data-w]");
  if(dw&&(EX.res||EX.mode==="wb")){const w=WORDS.find(x=>x.w===dw.dataset.w);if(w)openDetail(w)}
});
/* همان دکمه‌ها داخل «صفحه‌ی کامل تست» */
$("#qBody").addEventListener("click",ev=>{
  if(EX.stage!=="run")return;
  const t=ev.target;
  if(t.closest("[data-pick]")||t.closest("[data-elim]")||t.closest("[data-bm]")||t.closest("[data-rev]")){
    ev.stopPropagation();exClick(t);
  }
},true);

document.addEventListener("keydown",e=>{
  if($("#tab-exam").hidden||EX.stage!=="run")return;
  if(/^(INPUT|SELECT|TEXTAREA)$/.test(document.activeElement&&document.activeElement.tagName||""))return;
  if(e.key<"1"||e.key>"4")return;
  let q=null;
  if($("#qview").classList.contains("open")){
    if(curView&&curView.t==="q"&&exInRun(curView.y,curView.e,curView.q))q=curView.q;
  }else{
    const nodes=[...document.querySelectorAll(".ex-q")],mid=window.innerHeight/2;
    let best=null,bd=1e9;
    nodes.forEach(n=>{const r=n.getBoundingClientRect(),d=Math.abs(r.top+r.height/2-mid);if(d<bd){bd=d;best=n}});
    if(best)q=+best.dataset.q;
  }
  if(q===null)return;
  const i=+e.key-1;
  if((EX.out[q]||{})[i])delete EX.out[q][i];
  EX.ans[q]=i;paintQ(q);paintSide();saveEX();e.preventDefault();
});



/* ================= آمار مشترک هر کلمه ================= */
/* یک منبع واحد برای همه‌ی تب‌ها: تب کلمات، تب پیش‌بینی و پنجره‌ی خود کلمه. */
const LASTY = YEARS[YEARS.length - 1];

function runsOf(ys){
  const runs=[]; if(!ys.length)return runs;
  let cur=[ys[0]];
  for(let i=1;i<ys.length;i++){
    if(ys[i]===ys[i-1]+1)cur.push(ys[i]); else {runs.push(cur);cur=[ys[i]]}
  }
  runs.push(cur);
  return runs;
}
function wstats(ys){
  const n=ys.length; if(!n)return null;
  const first=ys[0], last=ys[n-1];
  const runs=runsOf(ys).filter(r=>r.length>=2).sort((a,b)=>b.length-a.length||b[0]-a[0]);
  return {
    n, first, last, runs, best: runs[0]||null,
    mean: n>1 ? Math.round((last-first)/(n-1)*10)/10 : null,
    missed: LASTY-last            // چند کنکورِ برگزارشده است که نیامده
  };
}
function yrange(a,b){return `<bdi dir="ltr">${fa(a)}–${fa(b)}</bdi>`}
/* باکس دوره‌های پیاپی: با کلیک باز می‌شود، با کلیک بیرون بسته.
   قبلاً title خام مرورگر بود — یک خط دراز و ناخوانا. */
/* ---------- راهنمای شناور سراسری ----------
   یک المان واحد که جابه‌جا می‌شود، نه یکی به ازای هر عنصر.
   موقعیتش با getBoundingClientRect حساب می‌شود تا از لبه‌ی صفحه بیرون نزند. */
(function(){
  let box=null, hideT=null;
  function el(){
    if(!box){box=document.createElement("div");box.id="tipbox";document.body.appendChild(box)}
    return box;
  }
  function show(target){
    const txt=target.getAttribute("data-tip");
    if(!txt)return;
    clearTimeout(hideT);
    const b=el();
    b.innerHTML=txt;
    b.className="";                 /* تا اندازه‌گیری با کلاس قبلی قاطی نشود */
    b.style.left="-9999px";b.style.top="0px";
    const r=target.getBoundingClientRect(), bb=b.getBoundingClientRect();
    const margin=9;

    /* بالا یا پایین؟ هرجا جا هست */
    const below = r.bottom + bb.height + margin < window.innerHeight || r.top < bb.height + margin;
    const top = below ? r.bottom + margin : r.top - bb.height - margin;

    /* افقی: وسط عنصر، ولی داخل صفحه بماند */
    let left = r.left + r.width/2 - bb.width/2;
    left = Math.max(8, Math.min(left, window.innerWidth - bb.width - 8));

    b.style.left=left+"px";
    b.style.top=Math.max(8,top)+"px";
    b.className=(below?"below":"above")+" on";

    /* نوک فلش دقیقاً زیر وسط عنصر */
    const arrow = Math.max(12, Math.min(r.left + r.width/2 - left - 4.5, bb.width - 21));
    b.style.setProperty("--ax", arrow+"px");
    const st=b.style;
    st.setProperty("--arrow-left", arrow+"px");
  }
  function hide(){ hideT=setTimeout(()=>{ if(box)box.className=""; },60); }

  document.addEventListener("mouseover",e=>{
    const t=e.target.closest("[data-tip]");
    if(t)show(t);
  });
  document.addEventListener("mouseout",e=>{
    if(e.target.closest("[data-tip]"))hide();
  });
  /* اسکرول و کلیک: راهنما باید فوراً برود، وگرنه سر جای غلط می‌ماند */
  window.addEventListener("scroll",()=>{if(box)box.className=""},true);
  document.addEventListener("click",()=>{if(box)box.className=""},true);
})();

/* باز شدن با هاور کار CSS است. این شنونده فقط برای لمسی است،
   و مهم‌تر: جلوی باز شدن پنجره‌ی کلمه با کلیک روی همین نشان را می‌گیرد.
   capture لازم است تا قبل از هندلر ردیف اجرا شود. */
document.addEventListener("click",e=>{
  const b=e.target.closest("[data-runpop]");
  if(e.target.closest(".runpop")){e.stopPropagation();return}
  document.querySelectorAll(".runpop.pinned").forEach(x=>{
    if(!b||x.previousElementSibling!==b){
      x.classList.remove("pinned");
      if(x.previousElementSibling)x.previousElementSibling.classList.remove("open");
    }});
  if(b){
    const pop=b.nextElementSibling;
    if(pop){const on=pop.classList.toggle("pinned");b.classList.toggle("open",on)}
    e.stopPropagation();e.preventDefault();
  }
},true);

/**
 * نشان‌های یک کلمه — همه‌جای پلتفرم دقیقاً همین‌ها و با همین ترتیب.
 * ys: سال‌های حضور (با فیلترهای همان تب) · span: طول بازه · tot: تعداد کل ظهور
 */
function wChips(ys, span, tot){
  const st=wstats(ys); if(!st)return "";
  const c=[];
  if(st.best){
    /* به‌جای یک خط title بی‌شکل، یک باکس با هر بازه در یک ردیف */
    const rows=st.runs.map((r,i)=>{
      const len=r.length;
      return `<div class="rr ${i===0?"best":""}">
        <span class="dot"></span>
        <span class="yy">${fa(r[0])}${len>1?"–"+fa(r[r.length-1]):""}</span>
        <span class="ln">${fa(len)} سال${i===0?" · بلندترین":""}</span>
      </div>`;}).join("");
    c.push(`<span class="pop-wrap">
      <span class="badge b-streak" data-runpop="1">${fa(st.best.length)} سال پیاپی · ${yrange(st.best[0],st.best[st.best.length-1])}</span>
      <span class="runpop"><div class="rh">دوره‌های پیاپی این کلمه</div>${rows}</span>
    </span>`);
  }
  c.push(`<span class="badge b-freq" data-tip="تعداد سال‌هایی که این کلمه در آن‌ها آمده">${fa(st.n)} سال از ${fa(span)}</span>`);
  if(st.mean!==null)
    c.push(`<span class="badge b-gap" data-tip="میانگین فاصله‌ی بین دو حضور پشت‌سرهم این کلمه">میانگین فاصله ${fa(st.mean)} سال</span>`);
  c.push(`<span class="badge b-once" data-tip="آخرین حضور: کنکور ${fa(st.last)}">${
    st.missed===0 ? `در کنکور ${fa(st.last)} آمده` : `${fa(st.missed)} کنکور است نیامده`}</span>`);
  if(tot!=null)c.push(`<span class="badge b-once">${fa(tot)} بار</span>`);
  return c.join("");
}
/* نوار قطاری مشترک — همیشه از قدیم (چپ) به جدید (راست) */
function sparkOf(ys,title){
  return `<div class="pr-spark" dir="ltr">${YEARS.map(y=>{
    const hit=ys.indexOf(y)>=0;
    return `<i class="${hit?"hit":""}" data-tip="${fa(y)}${hit?" — آمده":" — نیامده"}"></i>`}).join("")}</div>`;
}

/* ================= تمرین تست و آمار آن ================= */
/* QATT[qk] = {n, ok, opt:[c1,c2,c3,c4]}  — تلاش‌های تمرینی خودِ کاربر روی هر تست.
   از سرور (question_attempts، از راه /me در mem.zban_qatt)؛ با هر زدن یکی محلی
   بالا می‌رود و همزمان به سرور فرستاده می‌شود. */
const QATT = LS.get("zban_qatt") || {};
function qatt(k){return QATT[k] || (QATT[k]={n:0, ok:0, opt:[0,0,0,0]})}
function qattSave(){LS.set("zban_qatt",QATT)}

/* ===== آمار جمعی از سرور =====
   GET /api/crowd فقط رشته‌های خریده‌شده و فقط «نرخ»ها را می‌دهد. توزیع گزینه‌ها
   عمداً آنجا نیست — از آن می‌شد پاسخ همه‌ی سؤال‌ها را یک‌جا حدس زد؛ آن فقط
   برای یک سؤال و پشت همان قفل پاسخ از /question/{id}/stats می‌آید (QSTAT).
   تا نسخه‌ی دمو این عددها از hash متن ساخته می‌شدند و ساختگی بودند. */
let CROWD_DATA=null, CROWD_WAIT=null, CROWD_FAILED=false;
function loadCrowd(){
  if(CROWD_DATA)return Promise.resolve(CROWD_DATA);
  /* بعد از شکست، تا ۳۰ ثانیه دوباره نمی‌پرسیم (جلوی حلقه)، بعدش با باز کردن دوباره‌ی تب */
  if(CROWD_FAILED&&Date.now()-CROWD_FAILED<30000)return Promise.reject(new Error("crowd failed"));
  if(!CROWD_WAIT)CROWD_WAIT=ZABAN.crowd().then(r=>{
      CROWD_DATA={q:(r&&r.q)||{},w:(r&&r.w)||{},min:(r&&r.min)||5};
      crowdLoaded(); return CROWD_DATA;
    }).catch(e=>{CROWD_FAILED=Date.now();console.error(e);throw e}).finally(()=>{CROWD_WAIT=null});
  return CROWD_WAIT;
}
/* بعد از رسیدن آمار، هر جا که الان روی صفحه است دوباره کشیده شود */
function crowdLoaded(){
  if(!$("#tab-crowd").hidden)renderCrowd();
  if(curView&&curView.t==="q")renderQuestion(curView.y,curView.e,curView.q);
  if(typeof EX!=="undefined"&&EX.stage&&EX.res)paintRun();
}
function qGlobal(y,e,q){
  if(!CROWD_DATA){loadCrowd().catch(()=>{});return null}
  const real=(window.QBYKEY||{})[y+"|"+e+"|"+q]; if(!real)return null;
  const a=CROWD_DATA.q[real.id]; if(!a)return null;
  const [total,right,blank]=a;
  return {total,right,blank,wrong:total-right-blank};
}
/* آمار کامل یک سؤال: undefined = نپرسیده، {total:0} = کم، {locked} = قفل */
const QSTAT={};
function loadQStat(qid){
  if(!qid||QSTAT[qid]!==undefined)return;
  QSTAT[qid]=null;                                   /* در حال گرفتن */
  ZABAN.qStats(qid).then(r=>{
    QSTAT[qid]=r||{total:0};
    if(curView&&curView.t==="q")renderQuestion(curView.y,curView.e,curView.q);
  }).catch(e=>{console.error(e);delete QSTAT[qid]});
}
function bars(rows,max){
  return `<div class="qb">${rows.map(r=>`
    <div class="qb-row">
      <span class="qb-l">${r[0]}</span>
      <span class="qb-t"><i style="width:${max?Math.max(2,r[1]/max*100):0}%;background:${r[2]}"></i></span>
      <span class="qb-v">${fa(r[1])}${r[3]?` <b>${r[3]}</b>`:""}</span>
    </div>`).join("")}</div>`;
}
function qStatsHTML(y,e,q,Q){
  const k=qKey(y,e,q), me=QATT[k];
  loadQStat(Q.qid);
  const g=QSTAT[Q.qid];
  const meMax=me?Math.max(me.ok,me.n-me.ok,1):1;
  return `
  <div class="qstat">
    <div class="label" style="margin:0 0 8px">عملکرد شما در این تست</div>
    ${me&&me.n?bars([
        ["درست", me.ok, "#5f9c4c", `از ${fa(me.n)} بار`],
        ["غلط",  me.n-me.ok, "#c05a35", ""],
      ],meMax)
      +`<div class="capt" style="margin:8px 0 0">این تست را ${fa(me.n)} بار زده‌اید و ${fa(Math.round(me.ok/me.n*100))} درصد درست بوده است.</div>`
      :`<div class="capt" style="margin:0">هنوز این تست را نزده‌اید. یکی از گزینه‌ها را انتخاب کنید تا آمارتان ساخته شود.</div>`}

    <div class="label" style="margin:14px 0 8px">عملکرد همه‌ی کاربران</div>
    ${g==null?`<div class="capt" style="margin:0">در حال گرفتن آمار…</div>`
     :g.locked?`<div class="capt" style="margin:0">آمار این سؤال بعد از پایان آزمونِ باز همین دفترچه نشان داده می‌شود.</div>`
     :!g.total?`<div class="capt" style="margin:0">هنوز پاسخ کافی از داوطلب‌ها برای این سؤال ثبت نشده است.</div>`
     :`${bars([
      ["درست", g.right, "#5f9c4c", `${fa(Math.round(g.right/g.total*100))}٪`],
      ["غلط",  g.wrong, "#c05a35", `${fa(Math.round(g.wrong/g.total*100))}٪`],
      ["نزده", g.blank, "#b9bec4", `${fa(Math.round(g.blank/g.total*100))}٪`],
    ],g.total)}
    <div class="label" style="margin:14px 0 8px">کدام گزینه را بیشتر زده‌اند</div>
    ${bars(Q.opts.map((o,i)=>[
      `<span class="en">${fa(i+1)}) ${o.w}</span>`+(i===Q.ans?' <b class="ok">پاسخ درست</b>':""),
      g.opt[i], i===Q.ans?"#5f9c4c":"#d99521",
      `${fa(Math.round(g.opt[i]/g.total*100))}٪`
    ]),Math.max(1,...g.opt))}
    <div class="capt" style="margin:10px 0 0">از ${fa(g.total)} پاسخ ثبت‌شده در آزمون‌های آزمایشی داوطلب‌ها.</div>`}
  </div>`;
}

/* ================= یادآور روزانه =================
   در مرورگر با Notification API کار می‌کند و فقط وقتی تب باز است.
   برای یادآور واقعی (پیامک/پوش، حتی با مرورگر بسته) سمت سرور لازم است:
     • جدول zaban_reminders: user_id, channel, hour, minute, tz, enabled, last_sent_at
     • یک job که هر ربع ساعت کاربران سررسیدشده را پیدا کند و
       متن را از همان شمارنده‌ی «کارت‌های امروز» بسازد
     • جلوگیری از ارسال دوباره در یک روز با last_sent_at
   تنظیمات همین‌جا ذخیره می‌شود تا وقتی سرور آمد، همین شکل را پر کند. */
const REM = LS.get("zban_remind") || {on:false, hour:20, minute:0, lastFire:""};
REM.how = false;

function saveRem(){ LS.set("zban_remind", REM) }

function remindHTML(){
  const n=todayCards().length;
  const supported = typeof Notification !== "undefined";
  const perm = supported ? Notification.permission : "unsupported";

  /* اجازه‌ی اعلان سه حالت دارد و هرکدام دکمه‌ی خودش را می‌خواهد:
       default → می‌شود همین‌جا پرسید
       granted → کاری لازم نیست
       denied  → مرورگر اجازه‌ی پرسیدن دوباره را نمی‌دهد؛ فقط راهنما می‌ماند */
  const permBox =
    !supported ? `<div class="rnote">مرورگر شما از اعلان پشتیبانی نمی‌کند.</div>`
  : perm==="denied" ? `<div class="rnote warn">
        <span>اعلان‌ها برای این سایت مسدود شده‌اند.</span>
        <button class="ghost" id="remHow">چطور باز کنم؟</button>
      </div>`
  : perm==="default" ? `<div class="rnote">
        <span>برای دریافت اعلان، اجازه لازم است.</span>
        <button class="ghost brandbtn" id="remAsk">اجازه می‌دهم</button>
      </div>` : "";

  return `<div class="panel remind">
    <div class="row-f" style="margin-bottom:10px">
      <div class="label" style="margin:0">یادآور روزانه</div>
      <span class="capt" style="margin:0">تا برنگردید، مرور اتفاق نمی‌افتد</span>
    </div>

    <div class="remrow">
      <label class="rsw">
        <input type="checkbox" id="remOn" ${REM.on?"checked":""}>
        <span>یادآور روشن باشد</span>
      </label>
      <div class="rtime">
        <span>هر روز ساعت</span>
        <span class="clock" dir="ltr">
          <select id="remH" class="hin">${Array.from({length:24},(_,h)=>
            `<option value="${h}" ${h===REM.hour?"selected":""}>${fa(String(h).padStart(2,"0"))}</option>`).join("")}</select>
          <b>:</b>
          <select id="remM" class="hin">${[0,15,30,45].map(m=>
            `<option value="${m}" ${m===REM.minute?"selected":""}>${fa(String(m).padStart(2,"0"))}</option>`).join("")}</select>
        </span>
      </div>
      <button class="ghost" id="remTest" style="margin-right:auto">آزمایش همین حالا</button>
    </div>

    ${permBox}

    <div class="rwhat">
      <div class="label" style="margin:0 0 7px;font-size:12.5px">چه چیزهایی اعلان می‌دهند؟</div>
      <div class="rwrow"><span class="ri">⏰</span>
        <div><b>یادآور روزانه</b> — هر روز ساعت
          <span dir="ltr" class="mono">${fa(String(REM.hour).padStart(2,"0"))}:${fa(String(REM.minute).padStart(2,"0"))}</span>،
          با تعداد کارت‌های آماده و روزهای مانده تا کنکور.</div></div>
      <div class="rwrow"><span class="ri">↻</span>
        <div><b>رسیدن سررسید</b> — وقتی کارت‌های تازه‌ای برای مرور آماده می‌شوند.
          حداکثر هر چهار ساعت یک بار، تا مزاحمتان نشود.</div></div>
      <div class="rwrow"><span class="ri">⚑</span>
        <div><b>پاسخ به گزارش</b> — وقتی پشتیبانی به گزارشی که داده‌اید جواب می‌دهد.</div></div>
    </div>

    <div class="capt" style="margin:11px 0 0">
      اعلان‌ها جایی نشان داده می‌شوند که سیستم‌عاملتان اعلان‌ها را نشان می‌دهد —
      در ویندوز گوشه‌ی پایین راست و بعد در مرکز اعلان‌ها، در مک گوشه‌ی بالا راست،
      در اندروید در نوار اعلان. داخل خود پلتفرم چیزی نمی‌نشیند.
      برای همین هم فقط وقتی می‌آیند که پلتفرم در یکی از تب‌های مرورگرتان باز باشد.
    </div>

    ${REM.how?`<div class="fb-help" style="margin-top:12px">
      <p><b>باز کردن اعلان‌ها</b> — روی قفل یا آیکون کنار آدرس سایت بزنید،
      بخش «اعلان‌ها» یا «Notifications» را پیدا کنید و از «مسدود» به «مجاز» تغییرش بدهید.
      بعد صفحه را یک بار تازه کنید.</p>
      <p style="color:var(--ink-3)">مرورگرها اجازه نمی‌دهند سایت خودش این تنظیم را
      برگرداند — برای همین این کار دستی است.</p>
    </div>`:""}
  </div>`;
}

/* ---------- اعلان‌های موردی ----------
   سه چیز ارزش اعلان دارند و هرکدام قاعده‌ی ضد-مزاحمت خودش را دارد:
     • یادآور روزانه   — یک بار در روز، سر ساعتی که کاربر انتخاب کرده
     • رسیدن سررسید    — حداکثر یک بار در هر بازه‌ی REMIND_GAP، فقط اگر کارتی تازه سررسید شده
     • پاسخ به گزارش   — بلافاصله، چون کاربر منتظرش است
   همه‌شان از یک تابع رد می‌شوند تا اگر روزی خواستید خاموششان کنید یک جا باشد. */
const NOTIF_GAP = 4 * 3600 * 1000;      // حداقل فاصله‌ی دو اعلان سررسید
let lastDueNotif = 0;

function canNotify(){
  return REM.on && typeof Notification !== "undefined" && Notification.permission === "granted";
}
function fire(title, body, tag){
  if(!canNotify())return false;
  try{ new Notification(title, {body, tag, icon:undefined}); return true }
  catch(e){ return false }
}

/* کارت‌هایی که همین حالا سررسید شده‌اند ولی موقع آخرین بررسی نشده بودند */
let lastDueCount = null;
function checkDueNotif(){
  if(!canNotify())return;
  const n = todayCards().length;
  if(lastDueCount === null){ lastDueCount = n; return }   // اولین اجرا فقط ثبت می‌کند
  if(n > lastDueCount && Date.now() - lastDueNotif > NOTIF_GAP){
    const added = n - lastDueCount;
    lastDueNotif = Date.now();
    fire("وقت مرور رسید",
         `${fa(added)} کارت تازه سررسید شد · مجموع ${fa(n)} کارت آماده‌ی مرور`,
         "zaban-due");
  }
  lastDueCount = n;
}
setInterval(checkDueNotif, 5 * 60 * 1000);

function notifyReportAnswered(key){
  if(!canNotify())return;
  fire("پاسخ به گزارش شما",
       `پشتیبانی به گزارش «${typeof repLabel==="function"?repLabel(key):key}» پاسخ داد`,
       "zaban-report-"+key);
}

/* هر دقیقه چک می‌کند؛ در هر روز فقط یک بار شلیک می‌شود */
function remindTick(){
  if(!REM.on || typeof Notification==="undefined") return;
  if(Notification.permission!=="granted") return;
  const now=new Date();
  const key=todayKey();
  if(REM.lastFire===key) return;
  if(now.getHours()<REM.hour) return;
  if(now.getHours()===REM.hour && now.getMinutes()<REM.minute) return;
  REM.lastFire=key; saveRem();
  fireRemind();
}

function fireRemind(){
  const n=todayCards().length, d=daysToExam();
  const body = n
    ? `${fa(n)} کارت امروز آماده است · ${fa(d)} روز تا کنکور`
    : `امروز کارت سررسیدی ندارید · ${fa(d)} روز تا کنکور`;
  if(!fire("پلتفرم زبان کنکور ارشد", body, "zaban-daily")) toast(body);
}

function bindRemind(){
  const on=$("#remOn"); if(!on) return;

  on.onchange = async e=>{
    if(e.target.checked && typeof Notification!=="undefined" && Notification.permission==="default"){
      const p=await Notification.requestPermission();
      if(p!=="granted"){ e.target.checked=false; renderRankExtras(); return }
    }
    REM.on=e.target.checked; saveRem(); renderRankExtras();
  };
  $("#remH").onchange=e=>{REM.hour=+e.target.value; REM.lastFire=""; saveRem(); renderRankExtras()};
  $("#remM").onchange=e=>{REM.minute=+e.target.value; REM.lastFire=""; saveRem(); renderRankExtras()};

  const ask=$("#remAsk");
  if(ask) ask.onclick=async()=>{
    const p=await Notification.requestPermission();
    if(p==="granted"){ REM.on=true; saveRem(); toast("اعلان‌ها فعال شد."); }
    renderRankExtras();
  };

  const how=$("#remHow");
  if(how) how.onclick=()=>{ REM.how=!REM.how; renderRankExtras() };

  $("#remTest").onclick=async()=>{
    if(typeof Notification!=="undefined" && Notification.permission==="default")
      await Notification.requestPermission();
    fireRemind();
    renderRankExtras();
  };
}

/* دو پنل بالای و پایین جدول رتبه‌بندی */
document.addEventListener("click",e=>{
  if(e.target.closest("#planHelpBtn") || e.target.closest("#planHelpBtn2")){
    PLAN.help=!PLAN.help; renderRankExtras();
  }
});

/* ===== قاب سیاه و سه شاخص — عیناً همان چیدمان پلتفرم مرور ===== */
let kpiHelp=false;

function trendTag(d){
  if(d===null)return '<span class="trend flat">—</span>';
  if(d>2)  return `<span class="trend up">▲ ${fa(Math.abs(Math.round(d)))}٪</span>`;
  if(d<-2) return `<span class="trend down">▼ ${fa(Math.abs(Math.round(d)))}٪</span>`;
  return '<span class="trend flat">بدون تغییر</span>';
}

function renderHero(){
  const cards=allCards();
  /* همان صف دکمه‌ی «مرور امروز» — این دو عدد نباید هیچ‌وقت با هم فرق کنند */
  const TQ=todayQueue();
  const due=TQ.due, fresh=TQ.fresh;
  const todayTotal = TQ.list.length;
  const doneToday  = todayTotal===0;
  const later = (TQ.dueAll-TQ.due) + (TQ.freshAll-TQ.fresh);

  const learn = cards.filter(c=>c.bucket==="learn").length;
  const mastered = cards.filter(c=>c.bucket==="strong").length;
  const bank = WORDS.length;
  const prog = bank?Math.round(mastered/bank*100):0;

  /* حجم مرور این هفته در برابر هفته‌ی قبل */
  const A=actSum(7), B=actSum(14);
  const prev=Math.max(0,(B.rev||0)-(A.rev||0));
  const delta = prev>0 ? ((A.rev-prev)/prev*100) : (A.rev>0?100:null);

  const hero=$("#heroCard"); if(!hero)return;
  hero.innerHTML=`
    <div class="hero">
      <div>
        <div class="hk">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          ${doneToday?"مرور امروز":"برای مرور امروز"}
        </div>
        <div class="hv">${fa(todayTotal)}<small>کارت</small></div>
        <div class="hs">${doneToday
          ? "همه‌ی سررسیدهای امروز را زده‌اید. فردا دوباره سر بزنید."
          : `${fa(due)} کارت سررسید شده و ${fa(fresh)} کارت تازه`+
            (later>0?`<br>${fa(later)} کارت به روزهای بعد موکول شد`:"")}</div>
      </div>
      <button class="hgo ${doneToday?"done":""}" id="heroGo">
        ${doneToday?"مرور دلخواه":"شروع مرور"}
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
          stroke-width="2" stroke-linecap="round"><path d="M15 6l-6 6 6 6"/></svg>
      </button>
    </div>`;

  $("#dashKpi").innerHTML=`
    <div class="kpi" data-tip="کارت‌هایی که وارد دک شده‌اند ولی هنوز حتی یک بار مرورشان نکرده‌اید">
      <div class="kk"><i style="background:#5f9c4c"></i>کارت‌های جدید</div>
      <div class="kv">${fa(fresh)}</div>
      <div class="kf"><span class="ks">هنوز مرور نشده‌اند</span></div></div>

    <div class="kpi" data-tip="در مرحله‌ی یادگیری‌اند: بازه‌شان هنوز کوتاه است و زود برمی‌گردند">
      <div class="kk"><i style="background:#4a86c8"></i>در حال یادگیری</div>
      <div class="kv">${fa(learn)}</div>
      <div class="kf">${trendTag(delta)}<span class="ks">حجم مرور نسبت به هفته‌ی قبل</span></div></div>

    <div class="kpi" data-tip="کارت‌هایی که بازه‌ی مرورشان به ۲۱ روز رسیده — همان تعریفی که در تب فیدبک و تسلط هم به کار می‌رود">
      <div class="kk"><i style="background:var(--gold)"></i>مسلط شده</div>
      <div class="kv">${fa(prog)}<small>٪</small></div>
      <div class="bar" style="margin-top:8px"><i style="width:${prog}%;background:var(--gold)"></i></div>
      <div class="kf"><span class="ks">${fa(mastered)} از ${fa(bank)} کارت</span></div></div>`;

  const hb=$("#kpiHelp");
  hb.hidden=!kpiHelp;
  if(kpiHelp)hb.innerHTML=`<div class="panel"><div class="fb-help">
    <p>این چهار عدد از یک منبع می‌آیند: وضعیت کارت‌های دک شما. همان عددهایی که در
    <b>تب دک من</b> و <b>تب فیدبک و تسلط</b> می‌بینید، فقط اینجا با هم جمع شده‌اند.</p>
    <ul>
      <li><b>مرور امروز</b> — کارت‌های سررسیدشده به‌علاوه‌ی کارت‌های تازه، ولی هرگز بیشتر از
        ${fa(DAY_CAP)} تا در روز. اگر بیشتر باشد بقیه به روزهای بعد موکول می‌شود تا جلسه‌ی مرور
        از دست شما در نرود.</li>
      <li><b>کارت‌های جدید</b> — وارد دک شده‌اند ولی هنوز هیچ‌بار مرور نشده‌اند.</li>
      <li><b>در حال یادگیری</b> — بازه‌ی مرورشان هنوز کوتاه است. نشان کنارش حجم مرور
        این هفته را با هفته‌ی قبل مقایسه می‌کند.</li>
      <li><b>مسلط شده</b> — بازه‌شان به ۲۱ روز رسیده و دشواری‌شان پایین آمده.</li>
    </ul></div></div>`;
}

$("#kpiHelpBtn").addEventListener("click",()=>{kpiHelp=!kpiHelp;renderHero()});
document.addEventListener("click",e=>{
  if(e.target.closest("#heroGo")){
    const t=document.querySelector('#tabs [data-tab="deck"]');
    if(t)t.click();
    const b=$("#startReview"); if(b)b.click();
  }
});

function renderRankExtras(){
  renderHero();
  const ps=$("#planSlot"), rs=$("#remindSlot");
  if(ps) ps.innerHTML=planHTML();
  if(rs) rs.innerHTML=remindHTML();
  const hb=$("#planHelpBox");
  if(hb){ hb.hidden=!PLAN.help; hb.innerHTML=PLAN.help?planHelpHTML():""; }
  bindRemind();
}

setInterval(remindTick, 60000);

/* اگر تب از دیشب باز مانده باشد، «روز مانده» باید خودش کم شود
   نه اینکه تا رفرش بعدی روی عدد دیروز بماند. */
let _lastDay = todayKey();
setInterval(()=>{
  const k=todayKey();
  if(k!==_lastDay){
    _lastDay=k;
    if(!$("#tab-rank").hidden) renderRankExtras();
    if(!$("#tab-fb").hidden && typeof renderFeedback==="function") renderFeedback();
  }
}, 60000);

/* ================= شمارش معکوس و برنامه‌ی تا کنکور =================
   تاریخ از پنل مدیریت (zaban_meta.exam_date، از راه /me). تا نسخه‌ی دمو اینجا
   ثابت بود (۱۶ اردیبهشت ۱۴۰۶) و تاریخی که مدیر می‌گذاشت به داشبورد نمی‌رسید.
   اگر مدیر هنوز تاریخی نگذاشته، همان تاریخ پیش‌فرض با برچسب «تقریبی». */
/* وضعیت باز/بسته بودن باکس توضیح.
   var است نه const، چون renderBoard قبل از این خط اجرا می‌شود
   و const در ناحیه‌ی مرده گیر می‌کند. */
var PLAN = {help:false};

/* var عمدی: بعضی رندرها پیش از این خط اجرا می‌شوند (همان دلیل PLAN) */
var EXAM_DATE = (function(){
  const iso=window.EXAM_DATE_ISO, d=iso?new Date(String(iso).replace(" ","T")):null;
  return d&&!isNaN(d) ? d : new Date(2027, 4, 6);     /* پیش‌فرض: ۱۴۰۶/۰۲/۱۶ */
})();
var EXAM_DATE_FA = EXAM_DATE.toLocaleDateString("fa-IR",{year:"numeric",month:"long",day:"numeric"})
                 + (window.EXAM_DATE_ISO ? "" : " (تقریبی)");

function daysToExam(){
  /* هر دو سرِ روز — ساعتِ تاریخ کنکور (مثلاً ۲۳:۵۹) نباید یک روز اضافه کند */
  const now=new Date(); now.setHours(0,0,0,0);
  const ex=new Date(EXAM_DATE); ex.setHours(0,0,0,0);
  return Math.max(0, Math.round((ex-now)/86400000));
}

/* برنامه‌ی تا کنکور.
   «کارت سررسید در روز» تخمین واقعی است نه ضریب دلبخواه: کارتی با بازه‌ی n روز
   هر n روز یک بار برمی‌گردد، یعنی سهم روزانه‌اش ۱/n است. جمع این کسرها روی
   کارت‌هایی که دست‌کم یک بار مرور شده‌اند، تعداد کارتی است که میانگین هر روز
   سررسید می‌شود. کارت هنوز مرورنشده بازه ندارد و «برگشتن» برایش معنا ندارد؛
   تا نسخه‌ی قبل با max(1, 0) هر کدام «روزی یک کارت» حساب می‌شد و دکِ ۱۲۰۰
   کارتیِ تازه عدد ۱۲۱۲ می‌ساخت. کارت‌های تازه با سهمیه‌ی NEW_PER_DAY وارد صف می‌شوند. */
function examPlan(){
  const d=daysToExam();
  const total=WORDS.length;
  const cards=allCards();
  const wCards=cards.filter(c=>c.t==="w");
  const done=wCards.length;
  const mastered=wCards.filter(c=>c.bucket==="strong").length;
  const left=Math.max(0,total-done);
  const perDay=d>0?Math.ceil(left/d):left;
  let load=0, fresh=0;
  cards.forEach(c=>{ if(c.bucket==="new"){fresh++;return} load += 1/Math.max(1, c.iv||1) });
  return {d,total,done,mastered,left,perDay,fresh,
          load:Math.round(load),
          pct: total?Math.round(done/total*100):0,
          pctM: total?Math.round(mastered/total*100):0};
}

function planHTML(){
  const P=examPlan();
  const urgent = P.d<=60;
  return `<div class="panel plan">
    <div class="row-f" style="margin-bottom:3px">
      <div class="label" style="margin:0">مسیر شما تا کنکور</div>
      <span class="capt" style="margin:0">${EXAM_DATE_FA}</span>
      <button class="ghost brandbtn" id="planHelpBtn" style="margin-right:auto">${
        PLAN.help?"بستن توضیح":"این عددها یعنی چه؟"}</button>
    </div>

    <div class="plangrid">
      <div class="pcard ${urgent?"hot":""}">
        <div class="ptop"><span class="pi">⏳</span><span class="pl">روز مانده</span></div>
        <div class="pn">${fa(P.d)}</div>
        <div class="ps">تا ${EXAM_DATE_FA}</div>
      </div>
      <div class="pcard">
        <div class="ptop"><span class="pi">＋</span><span class="pl">کلمه‌ی تازه در روز</span></div>
        <div class="pn">${fa(P.perDay)}</div>
        <div class="ps">${P.left?`${fa(P.left)} کلمه هنوز وارد دک نشده`:"کل بانک وارد دک شده"}</div>
      </div>
      <div class="pcard">
        <div class="ptop"><span class="pi">↻</span><span class="pl">کارت سررسید در روز</span></div>
        <div class="pn">${fa(P.load)}</div>
        <div class="ps">میانگین کارت‌هایی که هر روز برمی‌گردند</div>
      </div>
      <div class="pcard">
        <div class="ptop"><span class="pi">▤</span><span class="pl">پوشش بانک</span></div>
        <div class="pn">${fa(P.pct)}<span class="pu">٪</span></div>
        <div class="ps">${fa(P.done)} از ${fa(P.total)} کلمه</div>
      </div>

      <div class="pcard wide">
        <div class="ptop"><span class="pi">Σ</span><span class="pl">بار واقعی روز شما</span></div>
        <div class="pn">${fa(P.perDay + P.load)}<span class="pu"> کارت</span></div>
        <div class="ps">
          <bdi dir="ltr" class="mono">${fa(P.perDay)} + ${fa(P.load)} = ${fa(P.perDay + P.load)}</bdi>
          &nbsp;—&nbsp; کلمه‌ی تازه به‌علاوه‌ی کارت سررسید
        </div>
      </div>
    </div>

    <div class="pbar">
      <i class="m" style="width:${P.pctM}%"></i>
      <i class="d" style="width:${Math.max(0,P.pct-P.pctM)}%"></i>
    </div>
    <div class="plegend">
      <span><i class="m"></i>مسلط شده ${fa(P.mastered)}</span>
      <span><i class="d"></i>در دک، هنوز مسلط نشده ${fa(P.done-P.mastered)}</span>
      <span><i class="r"></i>هنوز وارد دک نشده ${fa(P.left)}</span>
    </div>

    <div class="capt" style="margin:12px 0 0">${
      P.left===0
        ? "کل بانک وارد دک شما شده. از اینجا کار فقط مرور است."
        : `اگر روزی <b>${fa(P.perDay)} کلمه‌ی تازه</b> اضافه کنید، تا ${EXAM_DATE_FA}
           هر ${fa(P.total)} کلمه‌ی بانک را دست‌کم یک بار دیده‌اید.
           این عدد هر روزی که عقب بیفتید بزرگ‌تر می‌شود.`}</div>
  </div>`;
}

function planHelpHTML(){
  const P=examPlan();
  return `<div class="row-f" style="margin-bottom:8px">
      <div class="label" style="margin:0">این عددها از کجا می‌آیند؟</div>
      <button class="ghost" id="planHelpBtn2" style="margin-right:auto">بستن</button>
    </div>
    <div class="fb-help">
      <p><b>روز مانده</b> — فاصله‌ی امروز تا ${EXAM_DATE_FA}. هر بامداد یکی کم می‌شود،
      حتی اگر صفحه را باز گذاشته باشید.</p>

      <p><b>کلمه‌ی تازه در روز</b> — کلماتی که هنوز وارد دک نشده‌اند، تقسیم بر روزهای مانده:
      <bdi dir="ltr" class="mono eq">${fa(P.left)} ÷ ${fa(P.d)} = ${fa(P.perDay)}</bdi>
      اگر امروز کاری نکنید، فردا این عدد بالا می‌رود.</p>

      <p><b>کارت سررسید در روز</b> — این <em>تکلیف</em> نیست، <em>پیش‌بینی</em> است.
      کارتی که بازه‌ی مرورش ۱۰ روز است هر ۱۰ روز یک بار برمی‌گردد، یعنی روزانه
      یک‌دهم کارت. جمع این کسرها روی کارت‌هایی که دست‌کم یک بار مرور کرده‌اید می‌شود ${fa(P.load)}.
      هرچه کارت‌هایتان مسلط‌تر شوند بازه‌شان بلندتر می‌شود و این عدد پایین می‌آید،
      حتی اگر دکتان بزرگ‌تر شده باشد.
      ${P.fresh?`${fa(P.fresh)} کارتِ هنوز مرورنشده‌ی دک در این عدد نیستند؛ روزی حداکثر
      ${fa(NEW_PER_DAY)} تا از آن‌ها وارد «مرور امروز» می‌شوند.`:""}</p>

      <p><b>پوشش بانک</b> — چند درصد کلمات بانک را وارد دک کرده‌اید. این با «چقدر بلدید»
      فرق دارد؛ نوار زیرش همان تفکیک را نشان می‌دهد.</p>

      <p><b>بار واقعی روز شما:</b> جمع دو عدد وسط —
      <bdi dir="ltr" class="mono eq">${fa(P.perDay)} + ${fa(P.load)} = ${fa(P.perDay+P.load)}</bdi>
      یعنی اگر بخواهید به برنامه برسید، هر روز حدود ${fa(P.perDay+P.load)} کارت جلوی شماست:
      ${fa(P.perDay)} تای آن کلمه‌ی تازه است که تا حالا ندیده‌اید،
      و ${fa(P.load)} تای دیگر کارت‌هایی که قبلاً دیده‌اید و حالا نوبت مرورشان است.</p>
    </div>`;
}

/* ================= تاریخچه‌ی آزمون‌ها ================= */
const HIST = LS.get("zban_hist") || [];
/* تاریخچه از سرور می‌آید و جای نسخه‌ی محلی را می‌گیرد. نسخه‌ی محلی
   همچنان نوشته می‌شود تا اگر سرور در دسترس نبود چیزی از دست نرود. */
/* HIST_READY: router برای /zaban/exam/result/<شماره> منتظر همین می‌ماند */
if (ZABAN.examHistory) window.HIST_READY = ZABAN.examHistory().then(r => {
  if (r && r.hist && r.hist.length) {
    HIST.length = 0;
    r.hist.forEach(h => HIST.push(h));
    if (navTab === "exam" && !EX.stage) renderExamHome();
  }
}).catch(e => console.error(e));

function histAdd(rec){HIST.unshift(rec);if(HIST.length>200)HIST.length=200;LS.set("zban_hist",HIST)}
function jdate(ts){
  try{return new Intl.DateTimeFormat("fa-IR",{dateStyle:"medium",timeStyle:"short"}).format(new Date(ts))}
  catch(e){return new Date(ts).toLocaleString("fa-IR")}
}
const HF={exam:"",year:"",mode:"",q:""};

function histHTML(){
  if(!HIST.length)return "";

  /* تب رشته — شمارش هر رشته کنار نامش تا معلوم باشد کجا کار کرده‌اید */
  const byExam={};
  HIST.forEach(h=>{byExam[h.e]=(byExam[h.e]||0)+1});
  const exams=Object.keys(byExam);

  /* سال‌های موجود در همان رشته‌ی انتخابی */
  const yrs=[...new Set(HIST.filter(h=>!HF.exam||h.e===HF.exam).map(h=>h.y))].sort((a,b)=>b-a);

  let L=HIST.filter(h=>
      (!HF.exam||h.e===HF.exam) &&
      (!HF.year||h.y===+HF.year) &&
      (!HF.mode||h.mode===HF.mode));
  if(HF.q){
    const q=HF.q.trim();
    L=L.filter(h=>String(h.y).indexOf(q)>=0||fa(h.y).indexOf(q)>=0||h.e.indexOf(q)>=0);
  }

  const byKey={};
  L.forEach(h=>{const k=h.y+"|"+h.e;(byKey[k]=byKey[k]||[]).push(h)});
  const keys=Object.keys(byKey).sort((a,b)=>+b.split("|")[0]-+a.split("|")[0]);

  return `<div class="panel">
    <div class="row-f" style="margin-bottom:12px">
      <div class="label" style="margin:0">تاریخچه‌ی آزمون‌های شما</div>
      <span class="capt" style="margin:0">${fa(HIST.length)} آزمون ثبت شده</span>
      <button class="ghost" id="hClear" style="margin-right:auto">پاک کردن تاریخچه</button>
    </div>

    <div class="htabs">
      <button data-hex="" class="${HF.exam?"":"on"}">همه <i>${fa(HIST.length)}</i></button>
      ${exams.map(x=>`<button data-hex="${x}" class="${HF.exam===x?"on":""}">${x} <i>${fa(byExam[x])}</i></button>`).join("")}
    </div>

    <div class="hfilters">
      <input id="hQ" class="hin" placeholder="جست‌وجو: سال یا رشته" value="${HF.q}">
      <select id="hYear" class="hin">
        <option value="">همه‌ی سال‌ها</option>
        ${yrs.map(y=>`<option value="${y}" ${HF.year==String(y)?"selected":""}>کنکور ${fa(y)}</option>`).join("")}
      </select>
      <select id="hMode" class="hin">
        <option value="">هر دو حالت</option>
        <option value="fb" ${HF.mode==="fb"?"selected":""}>فیدبک</option>
        <option value="wb" ${HF.mode==="wb"?"selected":""}>واشبک</option>
      </select>
      ${(HF.exam||HF.year||HF.mode||HF.q)?`<button class="ghost" id="hReset">برداشتن فیلترها</button>`:""}
      <span class="capt" style="margin:0 auto 0 0">${fa(L.length)} آزمون در این نما</span>
    </div>

    ${keys.length?keys.map(k=>{
      const G=byKey[k], p=k.split("|");
      const best=Math.max(...G.map(x=>x.pct));
      return `<div class="hgrp">
        <div class="hh">
          <b>کنکور ${fa(+p[0])} — ${p[1]}</b>
          <span class="hstat">${fa(G.length)} بار · بهترین ${best<0?"−":""}${fa(Math.abs(best).toFixed(1))}٪</span>
          <button class="hagain" data-hagain="${p[0]}|${p[1]}">↻ شرکت مجدد</button>
        </div>
        ${G.map(h=>`<div class="hrow" data-hist="${h.ts}">
          <span class="hd">${jdate(h.ts)}</span>
          <span class="hm">${h.mode==="wb"?"واشبک":"فیدبک"}</span>
          <span class="hs">${fa(h.right)}<i>درست</i></span>
          <span class="hs">${fa(h.wrong)}<i>غلط</i></span>
          <span class="hs">${fa(h.blank)}<i>نزده</i></span>
          <span class="hp" style="color:${h.pct>=60?"#1c6b46":h.pct>=30?"#a5811a":"#a8461f"}">
            ${h.pct<0?"−":""}${fa(Math.abs(h.pct).toFixed(1))}٪</span>
          <span class="go2">دیدن کارنامه ←</span></div>`).join("")}
      </div>`}).join("")
      :`<div class="empty">با این فیلترها آزمونی پیدا نشد.</div>`}
  </div>`;
}

/* فقط تاریخچه را تازه می‌کند — تاریخچه داخل صفحه‌ی خانه‌ی آزمون رندر می‌شود */
function refreshHist(){
  if(typeof renderExamHome==="function"&&!$("#examHome").hidden){renderExamHome();return}
  const box=document.querySelector("#examHome");
  if(box)box.innerHTML=box.innerHTML;   /* پشتیبان */
}

/* شروع دوباره‌ی همان سال و رشته */
function histAgain(y,e){
  EX.y=+y; EX.e=e; EX.stage=null; syncUrl(true);
  if(typeof renderExamHome==="function")renderExamHome();
  $("#examRun").hidden=true;$("#examRes").hidden=true;$("#examHome").hidden=false;
  const t=document.querySelector('#tabs [data-tab="exam"]');
  if(t)t.click();
  window.scrollTo({top:0,behavior:"smooth"});
  toast(`آزمون کنکور ${fa(+y)} — ${e} آماده است. حالت و زمان را انتخاب کنید و شروع کنید.`);
}

/* کارنامه‌ی قدیمی: اول با همان خلاصه‌ی تاریخچه نشان داده می‌شود، بعد
   کارنامه‌ی کامل سرور (پاسخ‌ها، تشریحی‌ها، نتیجه‌ی بخش‌ها) رویش می‌نشیند.
   تاریخچه bySec و used ندارد؛ قبلاً همین دو undefined کل تب را می‌خواباند. */
async function histOpen(ts){
  const h=HIST.find(x=>String(x.ts)===String(ts)); if(!h)return;
  EX.y=h.y;EX.e=h.e;EX.mode=h.mode;EX.dur=h.dur||0;
  const b=buildItems(h.y,h.e);EX.items=b.items;EX.groups=b.groups;
  EX.ans=h.ans||{};EX.bm={};EX.out={};EX.rev={};EX.stage="res";
  EX.attemptId=h.id||null; syncUrl(true);                     /* /zaban/exam/result/<شماره> */
  EX.res={right:h.right,wrong:h.wrong,blank:h.blank,used:h.dur||0,bySec:{},
          auto:false,pct:h.pct,replay:true};
  $("#examHome").hidden=true;$("#examRes").hidden=true;$("#examRun").hidden=false;
  paintRun();window.scrollTo({top:0,behavior:"smooth"});

  if(!h.id)return;
  const mine=EX.res;
  try{
    const r=await ZABAN.examResult(h.id);
    if(EX.res!==mine)return;                    /* کاربر در این فاصله جای دیگری رفت */
    EX.key={};
    (r.key||[]).forEach(k=>{EX.key[k.q]={correct:k.correct,chosen:k.chosen,explanation:k.explanation,words:k.words}});
    EX.items.forEach(it=>{const k=EX.key[it.q];if(!k)return;
      if(k.correct==null){ if(it.Q.qid)ANS_LIMIT[it.Q.qid]=1; return }     /* سقف روزانه */
      it.Q.ans=k.correct-1;
      if(it.Q.qid)ANS[it.Q.qid]={ans:k.correct-1,exp:k.explanation||""}});
    if(r.limit_message)ANS_LIMIT_MSG=r.limit_message;
    Object.assign(EX.res,{right:r.correct,wrong:r.wrong,blank:r.blank,pct:r.percent,
      used:r.used_sec||EX.res.used,bySec:r.bySection||{},auto:!!r.auto_closed});
    paintRun();
  }catch(e){console.error(e)}
}

/* ---------- مرورگر کارت ----------
   پنل خلاصه جای فهرست کامل نیست. با ۲۰۰ کارت، هر چیدمانی — چیپ، شبکه،
   ستون — بالاخره یک دیوار می‌شود. راه درست این است که پنل فقط چند نمونه
   نشان بدهد و فهرست کامل در جای خودش باز شود: با جست‌وجو، فیلتر و صفحه‌بندی. */
const CB={list:[],title:"",page:0,per:24,q:"",bucket:"",key:""};

function openCards(title, cards, key){
  CB.list=cards; CB.title=title; CB.page=0; CB.q=""; CB.bucket=""; CB.key=key||"";
  renderCards();
  $("#cbM").classList.add("open");
  syncUrl(true);                                              /* /zaban/<تب>/cards/<کلید> */
}
function cbFiltered(){
  const q=CB.q.trim().toLowerCase();
  return CB.list.filter(c=>{
    if(CB.bucket&&c.bucket!==CB.bucket)return false;
    if(!q)return true;
    if(c.t==="w")return c.w.w.toLowerCase().indexOf(q)>=0 || meanHit(c.w,CB.q.trim());
    return c.label.toLowerCase().indexOf(q)>=0;
  });
}
function renderCards(){
  const L=cbFiltered();
  const pages=Math.max(1,Math.ceil(L.length/CB.per));
  if(CB.page>=pages)CB.page=pages-1;
  const slice=L.slice(CB.page*CB.per,(CB.page+1)*CB.per);

  const counts={};
  ["weak","learn","fam","strong","new"].forEach(b=>counts[b]=CB.list.filter(c=>c.bucket===b).length);

  $("#cbTitle").textContent=CB.title;
  $("#cbSub").textContent=`${fa(CB.list.length)} کارت`;

  $("#cbFilters").innerHTML=`
    <input id="cbQ" class="hin" placeholder="جست‌وجوی کلمه یا معنی" value="${CB.q}">
    <div class="repchips">
      <button data-cbb="" class="${CB.bucket?"":"on"}">همه <i>${fa(CB.list.length)}</i></button>
      ${["weak","learn","fam","strong","new"].filter(b=>counts[b]).map(b=>
        `<button data-cbb="${b}" class="${CB.bucket===b?"on":""}">${MAT_LABEL[b]} <i>${fa(counts[b])}</i></button>`).join("")}
    </div>`;

  $("#cbBody").innerHTML = slice.length
    ? `<div class="cchips">${slice.map(cardChip).join("")}</div>`
    : `<div class="empty">با این فیلتر کارتی پیدا نشد.</div>`;

  $("#cbPager").innerHTML = pages>1 ? `
    <button class="ghost" data-cbp="${CB.page-1}" ${CB.page===0?"disabled":""}>← قبلی</button>
    <span class="capt" style="margin:0">صفحه‌ی ${fa(CB.page+1)} از ${fa(pages)} · ${fa(L.length)} کارت</span>
    <button class="ghost" data-cbp="${CB.page+1}" ${CB.page>=pages-1?"disabled":""}>بعدی →</button>`
    : `<span class="capt" style="margin:0">${fa(L.length)} کارت</span>`;

  $("#cbStart").textContent = `شروع مرور این ${fa(Math.min(L.length,40))} کارت`;
  $("#cbStart").disabled = !L.length;
}

/* شش نمونه در پنل، بقیه در مرورگر کارت.
   عدد شش دلخواه نیست: بیشتر از این، پنل خلاصه دیگر خلاصه نیست. */
function cardPreview(cards, browseTitle, key){
  if(!cards.length)return "";
  const show=cards.slice(0,6);
  const rest=cards.length-show.length;
  return `<div class="cchips">${show.map(cardChip).join("")}</div>`
    + (rest>0
        ? `<button class="clipbtn" data-cbopen="${key}" style="margin-top:9px">
             دیدن همه‌ی ${fa(cards.length)} کارت <span class="ar">›</span></button>`
        : "");
}
const CB_SETS={};
function cardSet(key, title, cards){ CB_SETS[key]={title,cards}; return cardPreview(cards,title,key) }
document.addEventListener("click",e=>{
  const b=e.target.closest("[data-cbopen]"); if(!b)return;
  const set=CB_SETS[b.dataset.cbopen]; if(!set)return;
  openCards(set.title,set.cards,b.dataset.cbopen);
});

/* ---------- فهرست تاشو ----------
   با ۱۰ کارت هر فهرستی خوب است؛ با ۲۰۰ کارت صفحه غیرقابل استفاده می‌شود.
   این تابع همیشه چند تای اول را نشان می‌دهد و بقیه را پشت یک دکمه می‌گذارد،
   و وقتی باز شد ارتفاعش را می‌بندد تا اسکرول داخلی بگیرد نه اینکه صفحه دراز شود. */
let _clipSeq=0;
function clipList(items, limit, unitLabel, cls){
  if(!items.length)return "";
  const id="clip"+(++_clipSeq);
  const extra=items.length-limit;
  if(extra<=0)return `<div class="${cls}">${items.join("")}</div>`;
  return `<div class="cliparea" id="${id}">
    <div class="${cls} clipbox">${items.join("")}</div>
    <div class="clipfade"></div>
    <button class="clipbtn" data-clip="${id}">
      نمایش ${fa(extra)} ${unitLabel} دیگر <span class="ar">▾</span></button>
  </div>`;
}
document.addEventListener("click",e=>{
  const b=e.target.closest("[data-clip]");
  if(!b)return;
  const area=document.getElementById(b.dataset.clip);
  if(!area)return;
  const open=area.classList.toggle("open");
  b.innerHTML = open ? 'بستن فهرست <span class="ar up">▴</span>'
    : b.dataset.label || 'نمایش بیشتر <span class="ar">▾</span>';
  if(!open)area.scrollIntoView({block:"nearest",behavior:"smooth"});
});

/* ================= کارت‌های امروز ================= */
/* ده کارت از ضعیف‌ها و در حال یادگیری — همان چیزی که نوتیف روزانه باید بفرستد. */
function todayCards(){
  const all=allCards().filter(c=>c.bucket==="weak"||c.bucket==="learn");
  const weak=all.filter(c=>c.bucket==="weak").sort((a,b)=>a.m-b.m);
  const learn=all.filter(c=>c.bucket==="learn").sort((a,b)=>a.m-b.m);
  return weak.slice(0,6).concat(learn.slice(0, Math.max(0,10-Math.min(weak.length,6))));
}
/* دکمه‌های عمل یک تست — همان ترتیب و همان شکل دکمه‌های کلمه.
   قبلاً در تب دک فقط منتخب و دک بود و یادداشت و گزارش نداشت. */
function qActs(k, deckAttr){
  const p=k.split("|");
  const label="تست "+fa(p[2])+" — کنکور "+fa(p[0])+" "+p[1];
  const da=deckAttr||"qdeck2";
  return `<div class="acts">
    <button class="star ${starQ.has(k)?"on":""}" data-qstar3="${k}"
      data-tip="${starQ.has(k)?"حذف از منتخب‌ها":"افزودن به منتخب‌ها"}">${starQ.has(k)?"★":"☆"}</button>
    <button class="add ${deckQ.has(k)?"in":""}" data-${da}="${k}"
      data-tip="${deckQ.has(k)?"حذف از دک مرور":"افزودن به دک مرور"}">${deckQ.has(k)?"✓":"+"}</button>
    ${nBtn("q:"+k,"یادداشت "+label,"star")}
    ${rBtn("q:"+k,label)}
  </div>`;
}

/* چرا این کارت در این دسته است — بدون این، دیدن «ضعیف ۲۸» کنار
   «در حال یادگیری ۹» گیج‌کننده است. */
function bucketWhy(c){
  if(c.bucket==="new")    return "هنوز مرورش نکرده‌اید";
  if(c.bucket==="weak")   return `${fa(c.seen)} بار مرور شده و ${
    c.lapses>=LEECH_WARN?`${fa(c.lapses)} بار فراموش شده`:`دقتش ${fa(Math.round((c.acc||0)*100))}٪ است`}`;
  if(c.bucket==="strong") return `بازه‌ی مرور به ${fa(c.iv)} روز رسیده`;
  if(c.bucket==="fam")    return `بازه‌ی مرور ${fa(c.iv)} روز است`;
  return c.seen<MIN_CARD
    ? `فقط ${fa(c.seen)} بار مرور شده — هنوز برای قضاوت کافی نیست`
    : `بازه‌ی مرورش هنوز کوتاه است (${fa(c.iv)} روز)`;
}

function cardChip(c){
  const isW=c.t==="w";
  const head=isW?c.w.w:c.label;
  const sub =isW?listFa(c.w):"تست";
  return `<button class="ccell ${c.bucket}" data-fbw="${isW?c.w.w:""}"
    data-tip="<b>${head}</b><br>${MAT_LABEL[c.bucket]} · نمره‌ی تسلط ${fa(c.m)} از ۱۰۰<br><span style='color:var(--ink-3)'>${bucketWhy(c)}</span>">
    <span class="cm" style="width:${Math.max(3,c.m)}%"></span>
    <bdi class="ch ${isW?"en":""}">${head}</bdi>
    <span class="cs">${sub}</span>
  </button>`;
}
function startCards(list){
  if(!list.length)return;
  session=list.map(c=>c.t==="w"?{t:"w",w:c.w,key:"w|"+c.w.w}
    :{t:"q",y:c.y,e:c.e,q:c.q,key:"q|"+qKey(c.y,c.e,c.q)});
  pos=0;flipped=false;$("#review").classList.add("open");drawCard();syncUrl(true);
}

/* ================= پیش‌بینی کنکور ================= */
const TARGET=1406;
const PR={mode:"bal",exam:"",lvl:new Set(),sec:new Set(),top:100,win:3,filter:"",rows:[],help:false};

const PR_HELP={
  bal:["متوازن",
    "هر چهار عامل تقریباً هم‌وزن دیده می‌شوند: کلمه در چند سال آمده، چقدر تازه آمده، ریتم تکرارش چیست و چند سال پیاپی تکرار شده است. هیچ عاملی بر بقیه نمی‌چربد، برای همین فهرست نه فقط کلمات همیشگی را دارد و نه فقط شرط‌بندی روی امسال را. اگر مطمئن نیستید کدام مدل به کارتان می‌آید، همین را بگذارید.",
    "برای فهرست اصلی مطالعه؛ همان صد کلمه‌ای که به هر داوطلبی توصیه می‌کنید."],
  freq:["تکرارمحور",
    "بیشترین وزن روی تعداد سال‌های حضور و تعداد کل تکرار است. کلمه‌ای که در بیست سال از بیست‌وپنج سال آمده بالای فهرست می‌ماند، حتی اگر چند سالی است غایب باشد. این مدل روی امسال شرط نمی‌بندد؛ فقط می‌گوید کدام کلمات همیشه در کنکور بوده‌اند.",
    "برای کسی که از صفر شروع کرده یا وقت کمی برایش مانده و اول باید ستون فقرات واژگان کنکور را بردارد."],
  due:["سررسیدمحور",
    "وزن اصلی روی «سررسید» است: فاصله‌ی کلمه تا امسال در برابر میانگین فاصله‌هایی که خودِ آن کلمه تا حالا داشته. کلمه‌ای که تقریباً هر چهار سال یک‌بار می‌آید و چهار سال است نیامده بالا می‌رود، و کلمه‌ای که همین پارسال آمده پایین می‌افتد. پرریسک‌ترین مدل است، چون فرض می‌کند الگوی گذشته دوباره تکرار می‌شود.",
    "برای دور آخر؛ وقتی فهرست پرتکرار را بلدید و دنبال کلماتی هستید که امسال «نوبتشان» است."]
};

function prBuild(){
  const rows=[];
  WORDS.forEach(w=>{
    if(PR.lvl.size&&!PR.lvl.has(w.lvl))return;
    let occ=w.occ;
    if(PR.exam)occ=occ.filter(o=>o[1]===PR.exam);
    if(PR.sec.size)occ=occ.filter(o=>PR.sec.has(o[2]));
    if(!occ.length)return;
    const ys=[...new Set(occ.map(o=>o[0]))].sort((a,b)=>a-b);
    const n=ys.length,first=ys[0],last=ys[n-1],gap=TARGET-last;
    const mean=n>1?(last-first)/(n-1):Math.max(gap,8);
    let R=0;occ.forEach(o=>{R+=Math.pow(.86,TARGET-1-o[0])});
    let streak=0;for(let k=0;k<YEARS.length;k++){if(ys.indexOf(TARGET-1-k)>=0)streak++;else break}
    const due=Math.exp(-Math.pow((gap-mean)/(mean*.9+1),2));
    rows.push({w,ys,n,first,last,gap,mean,R,streak,due,tot:occ.length});
  });
  if(!rows.length){PR.rows=[];return}
  const mx=k=>rows.reduce((a,r)=>Math.max(a,r[k]),0)||1;
  const mN=mx("n"),mR=mx("R"),mT=mx("tot");
  const W={bal:[.34,.28,.22,.16],freq:[.50,.26,.12,.12],due:[.20,.16,.48,.16]}[PR.mode];
  rows.forEach(r=>{
    const F2=(r.n/mN)*.75+(r.tot/mT)*.25;
    r.raw=W[0]*F2+W[1]*(r.R/mR)+W[2]*r.due+W[3]*Math.min(r.streak,4)/4;
  });
  rows.sort((a,b)=>b.raw-a.raw||b.n-a.n||a.w.w.localeCompare(b.w.w));
  const top=rows[0].raw||1;
  rows.forEach(r=>r.score=Math.round(r.raw/top*970)/10);
  PR.rows=rows;
}
/* خط متادیتای ردیف پیش‌بینی.
   همه‌ی اطلاعات سر جایش است؛ فقط به‌جای پنج بلوک رنگی، یک خط آرام است
   که با نقطه جدا می‌شود. تنها «سال پیاپی» رنگ نگه می‌دارد چون
   مهم‌ترین سیگنال این تب است. */
function prWhy(r){
  const st=wstats(r.ys);
  const parts=[];

  if(st&&st.best){
    const rows=st.runs.map((x,i)=>`<div class="rr ${i===0?"best":""}">
        <span class="dot"></span>
        <span class="yy">${fa(x[0])}${x.length>1?"–"+fa(x[x.length-1]):""}</span>
        <span class="ln">${fa(x.length)} سال${i===0?" · بلندترین":""}</span>
      </div>`).join("");
    parts.push(`<span class="pop-wrap">
      <span class="mk mk-hot" data-runpop="1">${fa(st.best.length)} سال پیاپی · ${yrange(st.best[0],st.best[st.best.length-1])}</span>
      <span class="runpop"><div class="rh">دوره‌های پیاپی این کلمه</div>${rows}</span>
    </span>`);
  }

  parts.push(`<span class="mk" data-tip="در چند کنکور از ${fa(YEARS.length)} کنکور ثبت‌شده آمده است">${
    fa(r.n)} از ${fa(YEARS.length)} کنکور</span>`);

  if(st&&st.mean!==null)
    parts.push(`<span class="mk" data-tip="میانگین فاصله‌ی بین دو حضور پشت‌سرهم — هرچه کمتر، تکرارش منظم‌تر">فاصله ${fa(st.mean)} سال</span>`);

  parts.push(`<span class="mk" data-tip="${st&&st.missed===0?"در آخرین کنکور ثبت‌شده آمده است":"از کنکور "+fa(r.last)+" دیگر نیامده"}">${
    st&&st.missed===0 ? `در ${fa(r.last)} آمده` : `${fa(st?st.missed:0)} کنکور نیامده`}</span>`);

  parts.push(`<span class="mk" data-tip="مجموع دفعاتی که در گزینه‌ها یا متن‌ها آمده — یک کنکور می‌تواند چند بار باشد">${fa(r.tot)} بار</span>`);

  return `<div class="prmeta">${parts.join('<span class="sep">·</span>')}</div>`;
}
function longestRun(ys){
  let best=[],cur=[];
  ys.slice().sort((a,b)=>a-b).forEach((y,i,a)=>{
    if(i&&y===a[i-1]+1)cur.push(y); else cur=[y];
    if(cur.length>best.length)best=cur.slice();
  });
  return best.length?best:[ys[0]];
}
/* در تب پیش‌بینی، نوار بزرگ‌تر است و دو سر بازه برچسب سال دارد —
   وگرنه یک ردیف میله‌ی ریز است که معلوم نیست کدام سال کجاست. */
function prSpark(r){
  return sparkOf(r.ys)
    + `<div class="sparkyr" data-tip="بازه‌ی کنکورهای ثبت‌شده در بانک"><span>${fa(YEARS[0])}</span><span>${fa(YEARS[YEARS.length-1])}</span></div>`;
}
function renderPredict(){
  prBuild();
  let list=PR.rows.slice(0,PR.top);
  const miss=r=>LASTY-r.last;
  const fresh=list.filter(r=>miss(r)<PR.win).length;
  const absent=list.filter(r=>miss(r)>=PR.win).length;
  if(PR.filter==="fresh")list=list.filter(r=>miss(r)<PR.win);
  if(PR.filter==="absent")list=list.filter(r=>miss(r)>=PR.win);
  const base=PR.rows.slice(0,PR.top);
  const avg=base.length?Math.round(base.reduce((a,r)=>a+r.score,0)/base.length*10)/10:0;
  $("#prStats").innerHTML=`
    <div class="stat"><div class="k">کلمات واجد شرایط</div><div class="v">${fa(PR.rows.length)}</div>
      <div class="capt">با فیلترهای فعلی</div></div>
    <div class="stat"><div class="k">میانگین امتیاز فهرست</div><div class="v">${fa(avg)}</div>
      <div class="capt">${fa(base.length)} کلمه‌ی بالای فهرست</div></div>
    <div class="stat click ${PR.filter==="fresh"?"on":""}" data-f="fresh">
      <div class="k">در ${fa(PR.win)} کنکور اخیر آمده</div><div class="v">${fa(fresh)}</div>
      <div class="capt">برای دیدن فقط این‌ها بزنید</div></div>
    <div class="stat click ${PR.filter==="absent"?"on":""}" data-f="absent">
      <div class="k">${fa(PR.win)} کنکور یا بیشتر غایب</div><div class="v">${fa(absent)}</div>
      <div class="capt">کاندیدای بازگشت — برای فیلتر بزنید</div></div>`;
  $("#prList").innerHTML=list.length?list.map((r,i)=>`
    <div class="pr-row" data-w="${r.w.w}">
      <div class="pr-rk">${fa(PR.rows.indexOf(r)+1)}</div>
      <div class="pr-mid">
        <div class="pr-w"><b class="en">${r.w.w}</b>
          <span class="badge ${lvlClass(r.w.lvl)}">${r.w.lvl}</span>
          <span class="pr-fa">${listFa(r.w)}</span>
          <button class="ib ${star.has(r.w.w)?"onstar2":""}" data-star="${r.w.w}"
            data-tip="${star.has(r.w.w)?"حذف از منتخب‌ها":"افزودن به منتخب‌ها"}">${star.has(r.w.w)?"★":"☆"}</button>
          <button class="ib ${deck.has(r.w.w)?"ondeck":""}" data-toggle="${r.w.w}"
            data-tip="${deck.has(r.w.w)?"حذف از دک مرور":"افزودن به دک مرور"}">${deck.has(r.w.w)?"✓":"+"}</button>
          ${nBtn("w:"+r.w.w,"یادداشت — "+r.w.w)}
          ${rBtn("w:"+r.w.w, wordRepLabel(r.w))}</div>
        <div class="head" style="margin-top:5px">${prWhy(r)}</div>
      </div>
      <div class="pr-sc"><b data-tip="<b>امتیاز اولویت مطالعه</b><br>نسبی و از ۹۷. بالاترین کلمه‌ی فهرست ۹۷ می‌گیرد و بقیه نسبت به آن مقیاس می‌شوند.">${fa(r.score.toFixed(1))}</b>
        <div class="track" data-tip="<b>امتیاز اولویت مطالعه</b><br>نسبی و از ۹۷. بالاترین کلمه‌ی فهرست ۹۷ می‌گیرد و بقیه نسبت به آن مقیاس می‌شوند."><i style="width:${r.score}%;background:${r.score>=75?"#c05a35":r.score>=50?"#d99521":"#7a5aa8"}"></i></div>
        ${prSpark(r)}</div>
    </div>`).join(""):'<div class="empty">با این فیلترها کلمه‌ای نماند.</div>';

  const sel=$("#prWin");
  if(!sel.options.length){
    let o="";for(let i=1;i<=15;i++)o+=`<option value="${i}" ${i===PR.win?"selected":""}>پنجره‌ی تازگی: ${fa(i)} سال</option>`;
    sel.innerHTML=o;
  }
  const H=PR_HELP[PR.mode];
  $("#prHelpBox").hidden=!PR.help;
  $("#prHelpBox").innerHTML=PR.help?`
    <div class="label">سه مدل چه فرقی با هم دارند؟</div>
    ${Object.keys(PR_HELP).map(k=>`<div class="pr-hrow ${k===PR.mode?"on":""}">
      <b>${PR_HELP[k][0]}</b><div>${PR_HELP[k][1]}</div>
      <div class="u">کِی از این استفاده کنید: ${PR_HELP[k][2]}</div></div>`).join("")}
    <div class="capt" style="margin:12px 0 0">
      هر چهار عامل از یک جا می‌آیند: سال‌هایی که کلمه در آن‌ها آمده. عامل‌ها عبارت‌اند از
      <b>فراوانی</b> (چند سال از ${fa(YEARS.length)} سال و چند بار در کل)،
      <b>تازگی</b> (هر سال عقب‌تر ۱۴٪ کم‌اثرتر می‌شود)،
      <b>سررسید</b> (فاصله‌ی تا امروز در برابر میانگین فاصله‌های خود کلمه) و
      <b>پیوستگی</b> (چند سال پیاپی از آخرین کنکور به عقب).
      امتیاز نسبی است: بالاترین کلمه‌ی فهرست ۹۷ می‌گیرد و بقیه نسبت به آن مقیاس می‌شوند؛
      این عدد «احتمال» به معنای آماری نیست، شاخص اولویت مطالعه است.
    </div>`:"";
  const _unused=`${H[0]}
`;
}

/* --- رویدادها --- */
$("#prMode").addEventListener("click",e=>{const b=e.target.closest("[data-m]");if(!b)return;
  PR.mode=b.dataset.m;[...$("#prMode").children].forEach(x=>x.classList.toggle("on",x===b));renderPredict()});
$("#prLvl").addEventListener("click",e=>{const b=e.target.closest("button");if(!b)return;
  const v=b.textContent.trim();PR.lvl.has(v)?PR.lvl.delete(v):PR.lvl.add(v);b.classList.toggle("on");renderPredict()});
$("#prSec").addEventListener("click",e=>{const b=e.target.closest("button");if(!b)return;
  const v=b.textContent.trim();PR.sec.has(v)?PR.sec.delete(v):PR.sec.add(v);b.classList.toggle("on");renderPredict()});
$("#prExam").addEventListener("change",e=>{PR.exam=e.target.value;e.target.classList.toggle("on",!!PR.exam);renderPredict()});
$("#prTop").addEventListener("change",e=>{PR.top=+e.target.value;renderPredict()});
$("#prWin").addEventListener("change",e=>{PR.win=+e.target.value;renderPredict()});
$("#prHelpBtn").addEventListener("click",()=>{PR.help=!PR.help;renderPredict()});
$("#prStats").addEventListener("click",e=>{const b=e.target.closest("[data-f]");if(!b)return;
  PR.filter=PR.filter===b.dataset.f?"":b.dataset.f;renderPredict()});
$("#prList").addEventListener("click",e=>{const r=e.target.closest("[data-w]");if(!r)return;
  const w=WORDS.find(x=>x.w===r.dataset.w);if(w)openDetail(w)});
$("#prAdd").addEventListener("click",()=>{
  let c=0;PR.rows.slice(0,PR.top).forEach(r=>{if(!deck.has(r.w.w)){deck.add(r.w.w);c++}});
  refreshAll();toast(fa(c)+" کلمه به دک مرور اضافه شد.")});


/* --- همان نشان‌ها در تب کلمات، بر پایه‌ی بازه‌ی انتخابی --- */
function rangeChips(w){
  const ys=[...new Set(w.occ.filter(matchOcc).map(o=>o[0]))].sort((a,b)=>a-b);
  return wChips(ys, F.toY-F.fromY+1, w._t);
}
EX.y=YEARS[YEARS.length-1];
if(EX.y<(EXAM_START[EX.e]||0))EX.y=examYears(EX.e).slice(-1)[0];
if(LS.get("zban_exam"))EX.stage=null;


/* ================= فیدبک و تسلط ================= */
/* داده‌ی خام: کارت‌های FSRS (s, d, iv, reps, lapses) + شمارنده‌های تجمعی seen/again
   که در applyRating اضافه می‌شوند. هیچ چیز حدسی نیست؛ همه از رفتار خود کاربر می‌آید. */

const MAT_MATURE=21;   // آستانه‌ی «مسلط» بر حسب روز — همان تعریف کارت بالغ در انکی
const MAT_YOUNG=7;     // مرز «در حال یادگیری» و «آشنا»
const LEECH_WARN=5;    // هشدار: پنج بار فراموشی روی یک کارت
/* کمینه‌ی مرور برای اینکه یک کارت قابل قضاوت باشد.
   با ۲، کارتی که از دو مرور یکی را فراموش کرده (۵۰٪) «ضعیف» می‌شد — با دو
   نمونه این قضاوت خیلی پرنوسان است و یک اشتباه سهوی کافی بود. با ۳ پایدارتر
   می‌شود. همان اصلاحی که بازبینی الگوریتم پلتفرم مرور پیشنهاد داد. */
const MIN_CARD=3;
const MIN_GROUP=8;     // کمینه‌ی کارت برای اینکه درباره‌ی یک گروه حرفی بزنیم

const MAT_LABEL={new:"دیده‌نشده",weak:"ضعیف",learn:"در حال یادگیری",fam:"آشنا",strong:"مسلط"};
const MAT_COLOR={new:"var(--ink-3)",weak:"#c05a35",learn:"#d99521",fam:"#5f9c4c",strong:"#1c6b46"};

/* نمره‌ی تسلط و دسته‌بندی — حالا بر پایه‌ی کمیت‌های FSRS.
   جای «ضریب سهولت» را «دشواری» گرفته (که برعکس است: کمتر یعنی بهتر)،
   و جای شمارش تکرار را پایداری. */
function cardStats(key){
  const c=sched[key];
  if(!c)return {key,seen:0,again:0,acc:null,s:0,d:5,iv:0,reps:0,lapses:0,bucket:"new",m:0,R:null};
  const seen=c.seen||0, again=c.again||c.lapses||0;
  const acc=seen?(seen-again)/seen:null;
  const S=c.s||0, D=c.d==null?5:c.d;

  /* احتمال اینکه همین حالا یادش بیاورید — چیزی که SM-2 اصلاً نداشت */
  const R=(S>0&&c.last!=null)?fRetr(Math.max(0,dayNow-c.last),S):null;

  const m=Math.round(100*(
      0.45*Math.min(c.iv,MAT_MATURE)/MAT_MATURE     // بازه‌ی فعلی
    + 0.30*(acc===null?0.5:acc)                     // دقت
    + 0.15*(10-D)/9                                 // دشواری، معکوس‌شده
    + 0.10*Math.min(c.reps,4)/4));                  // تکرار موفق

  let bucket;
  if(seen===0)bucket="new";
  else if(seen>=MIN_CARD&&((acc!==null&&acc<0.6)||c.lapses>=LEECH_WARN))bucket="weak";
  else if(c.iv>=MAT_MATURE&&D<=6)bucket="strong";
  else if(c.iv>=MAT_YOUNG)bucket="fam";
  else bucket="learn";

  return {key,seen,again,acc,s:S,d:D,iv:c.iv,reps:c.reps,lapses:c.lapses,bucket,m,R};
}
function allCards(){
  const out=[];
  WORDS.forEach(w=>{if(deck.has(w.w))out.push(Object.assign({t:"w",w,label:w.w,lvl:w.lvl,pos:w.pos,
    secs:[...new Set(w.occ.map(o=>o[2]))]},cardStats("w|"+w.w)))});
  deckQ.forEach(k=>{const p=k.split("|"),y=+p[0],e=p[1],q=+p[2];
    const g=structFor(y).find(x=>q>=x[3]&&q<=x[4]);
    out.push(Object.assign({t:"q",y,e,q,label:"تست "+fa(q)+" — "+fa(y)+" "+e,fa:"",lvl:"",pos:"",
      secs:[g?g[0]:"وکب"]},cardStats("q|"+k)))});
  return out;
}
function share(list,b){const j=list.filter(c=>c.bucket!=="new");
  return j.length?j.filter(c=>c.bucket===b).length/j.length:0}

function groupsOf(cards){
  const G=[];
  const push=(title,name,pick)=>{
    const m={};
    cards.forEach(c=>{const k=pick(c);if(k===null||k===undefined||k==="")return;
      (Array.isArray(k)?k:[k]).forEach(kk=>{(m[kk]=m[kk]||[]).push(c)})});
    Object.keys(m).forEach(k=>G.push({dim:title,name:k,cards:m[k]}));
  };
  push("سطح کلمه","lvl",c=>c.t==="w"?c.lvl:null);
  push("بخش کنکور","sec",c=>c.secs);
  push("نوع کارت","kind",c=>c.t==="w"?"کلمه":"تست");
  push("نقش دستوری","pos",c=>c.t==="w"?c.pos:null);
  return G;
}

/* نمودار میله‌ای انباشته برای یک بُعد (سطح، بخش کنکور، نقش دستوری، نوع کارت).
   قبلاً فقط چند جمله‌ی متنی بود؛ با ۵۰۰ کارت آن متن‌ها قابل خواندن نیستند. */
function dimChart(cards, title, pick, note, dimKey){
  const m={};
  cards.forEach(c=>{
    const k=pick(c); if(k===null||k===undefined||k==="")return;
    (Array.isArray(k)?k:[k]).forEach(kk=>{(m[kk]=m[kk]||[]).push(c)});
  });
  const rows=Object.keys(m).map(k=>{
    const L=m[k], j=L.filter(c=>c.bucket!=="new");
    const cnt={weak:0,learn:0,fam:0,strong:0,new:0};
    L.forEach(c=>cnt[c.bucket]++);
    return {name:k, all:L.length, judged:j.length, cnt,
            weakR: j.length?cnt.weak/j.length:0,
            strongR: j.length?cnt.strong/j.length:0};
  }).sort((a,b)=>b.all-a.all);

  if(!rows.length)return "";
  const max=Math.max(...rows.map(r=>r.all));

  return `<div class="dimc">
    <div class="row-f" style="margin-bottom:9px">
      <div class="label" style="margin:0">${title}</div>
      ${note?`<span class="capt" style="margin:0">${note}</span>`:""}
    </div>
    ${rows.map(r=>{
      const seg=b=>r.all?(r.cnt[b]/r.all*100):0;
      /* هر تکه‌ی رنگی قابل کلیک است: جلسه‌ی مرور همان دسته از همان گروه.
         این همان چیزی است که «فقط درصد گفتن» را به کار عملی تبدیل می‌کند. */
      return `<div class="dimrow">
        <div class="dtop">
          <span class="dn">${r.name}</span>
          <span class="dnum"><b>${fa(r.all)}</b> کارت${
            r.judged?` · <span class="${r.weakR>=0.35?"bad":r.strongR>=0.5?"good":""}">${
              fa(Math.round(r.weakR*100))}٪ ضعیف</span>`:` · <span class="dim">مرور نشده</span>`}</span>
        </div>
        <div class="dtrack"><div class="dbar" style="width:${Math.max(6,r.all/max*100)}%">
          ${["weak","learn","fam","strong","new"].map(b=>
            seg(b)>0?`<i class="s-${b}" style="width:${seg(b)}%"
              data-fbdrill="${encodeURIComponent(dimKey+"|"+r.name+"|"+b)}"
              data-tip="<b>${MAT_LABEL[b]}</b> در «${r.name}»<br>${fa(r.cnt[b])} کارت — برای مرور همین‌ها بزنید"></i>`:"").join("")}
        </div></div>
        <div class="dgo">
          ${r.cnt.weak?`<button data-fbdrill="${encodeURIComponent(dimKey+"|"+r.name+"|weak")}"
             class="gw">${fa(r.cnt.weak)} ضعیف</button>`:""}
          ${r.cnt.learn?`<button data-fbdrill="${encodeURIComponent(dimKey+"|"+r.name+"|learn")}"
             class="gl">${fa(r.cnt.learn)} متوسط</button>`:""}
          ${r.cnt.new?`<button data-fbdrill="${encodeURIComponent(dimKey+"|"+r.name+"|new")}"
             class="gn">${fa(r.cnt.new)} تازه</button>`:""}
        </div>
      </div>`}).join("")}
  </div>`;
}

function fbLegend(){
  return `<div class="dimleg">${["weak","learn","fam","strong","new"].map(b=>
    `<span><i class="s-${b}"></i>${MAT_LABEL[b]}</span>`).join("")}</div>`;
}

function renderFeedback(){
  const cards=allCards();
  const nW=cards.filter(c=>c.t==="w").length, nQ=cards.length-nW;
  const judged=cards.filter(c=>c.bucket!=="new");
  const B={new:0,weak:0,learn:0,fam:0,strong:0};
  cards.forEach(c=>B[c.bucket]++);
  const wShare=share(cards,"weak"), sShare=share(cards,"strong");
  const reviews=cards.reduce((n,c)=>n+c.seen,0);

  if(!cards.length){
    $("#fbBody").innerHTML=`<div class="panel"><div class="empty">
      هنوز کارتی در دک مرور نیست. از تب کلمات یا تست‌های زبان چند کارت اضافه کنید و یک جلسه مرور کنید؛
      این صفحه بعد از آن پر می‌شود.</div></div>
      <div class="panel" id="statsSlot"></div>`;
    mountSlots();return;
  }

  const weakG=[],strongG=[];
  groupsOf(cards).forEach(g=>{
    const j=g.cards.filter(c=>c.bucket!=="new");
    if(j.length<MIN_GROUP)return;
    const wn=j.filter(c=>c.bucket==="weak").length, sn=j.filter(c=>c.bucket==="strong").length;
    const w=wn/j.length, s=sn/j.length;
    if(w>=wShare+0.10&&w>=0.20)weakG.push({...g,all:g.cards.length,n:j.length,cnt:wn,r:w,diff:w-wShare});
    if(s>=sShare+0.10&&s>=0.30)strongG.push({...g,all:g.cards.length,n:j.length,cnt:sn,r:s,diff:s-sShare});
  });
  weakG.sort((a,b)=>b.diff-a.diff);strongG.sort((a,b)=>b.diff-a.diff);
  window.FBG=weakG;

  const leeches=judged.filter(c=>c.bucket==="weak").sort((a,b)=>a.m-b.m||b.lapses-a.lapses).slice(0,15);
  const enough=judged.length>=MIN_GROUP&&reviews>=20;

  $("#fbBody").innerHTML=`
  <div class="stats">
    <div class="stat"><div class="k">کارت‌های دک شما</div><div class="v">${fa(cards.length)}</div>
      <div class="capt">${fa(nW)} کلمه و ${fa(nQ)} تست</div></div>
    <div class="stat"><div class="k">کارت‌های مرورشده</div><div class="v">${fa(judged.length)}</div>
      <div class="capt">${fa(reviews)} پاسخ ثبت شده · بقیه هنوز دیده نشده‌اند</div></div>
    <div class="stat"><div class="k">ضعیف</div><div class="v" style="color:#c05a35">${fa(Math.round(wShare*100))}٪</div>
      <div class="capt">از کارت‌های مرورشده</div></div>
    <div class="stat"><div class="k">مسلط</div><div class="v" style="color:#1c6b46">${fa(Math.round(sShare*100))}٪</div>
      <div class="capt">از کارت‌های مرورشده</div></div>
  </div>

  ${enough?"":`<div class="pr-note">هنوز داده کم است. برای اینکه این تحلیل معنی بدهد دست‌کم
    ${fa(MIN_GROUP)} کارت مرورشده و ${fa(20)} پاسخ لازم است — الان ${fa(judged.length)} کارت و ${fa(reviews)} پاسخ دارید.
    تا آن موقع عددها را جدی نگیرید.</div>`}

  <div class="panel">
    <div class="label">وضعیت تسلط شما</div>
    ${["strong","fam","learn","weak","new"].map(b=>{
      const n=B[b],p=cards.length?n/cards.length*100:0;
      return `<div class="hbar" style="cursor:default">
        <div class="lbl" style="direction:rtl;text-align:right;width:110px">${MAT_LABEL[b]}</div>
        <div class="track"><i class="fill" style="width:${p}%;background:${MAT_COLOR[b]}"></i></div>
        <div class="hpos" style="width:120px;text-align:right;direction:rtl">${fa(n)} کارت · ${fa(Math.round(p))}٪</div>
      </div>`}).join("")}
    <div class="capt" style="margin:10px 0 0">
      این نوار همه‌ی ${fa(cards.length)} کارت دک را می‌شمارد. «دیده‌نشده» یعنی هنوز مرورش نکرده‌اید،
      پس در درصدهای بالای صفحه نمی‌آید — نه به سود شما حساب می‌شود نه به زیانتان.
    </div>
  </div>

  <div class="panel">
    <div class="row-f" style="margin-bottom:10px">
      <div class="label" style="margin:0">کارت‌های امروز</div>
      <span class="capt" style="margin:0">۱۰ کارت از ضعیف‌ها و تازه‌ها — همان چیزی که یادآور روزانه می‌فرستد</span>
      ${todayCards().length?`<button class="deckbtn" id="fbToday" style="margin-right:auto">شروع مرور این ${fa(todayCards().length)} کارت</button>`:""}
    </div>
    ${todayCards().length
      ? cardSet("today","کارت‌های امروز",todayCards())
      : `<div class="capt" style="margin:0">فعلاً کارتی که نیاز فوری به تمرین داشته باشد نیست. کارت تازه اضافه کنید یا یک دور مرور بزنید.</div>`}
  </div>

  <div class="panel" id="statsSlot"></div>

  <div class="panel">
    <div class="row-f" style="margin-bottom:4px">
      <div class="label" style="margin:0">کارت‌های شما به تفکیک وضعیت</div>
      <span class="capt" style="margin:0">بدون توجه به گروه — هر دسته یکجا</span>
      <button class="ghost" id="fbScoreBtn" style="margin-right:auto">${
        FB.score?"بستن توضیح":"نمره‌دهی چطور کار می‌کند؟"}</button>
    </div>
    ${FB.score?`<div class="fb-help" style="margin-bottom:12px">
      <p><b>دو عدد متفاوت، دو کار متفاوت.</b> «وضعیت» (ضعیف، در حال یادگیری، آشنا، مسلط)
      یک <em>قضاوت</em> است و «نمره‌ی تسلط» یک <em>اندازه‌گیری</em>. این دو همیشه هم‌جهت نیستند.</p>

      <p><b>نمره‌ی تسلط</b> عددی از ۰ تا ۱۰۰ است از چهار جزء: بازه‌ی مرور فعلی (۴۵٪)،
      دقت پاسخ‌ها (۳۰٪)، ضریب سهولت (۱۵٪) و تعداد مرورهای موفق (۱۰٪).
      هر کارتی از همان اولین لحظه یک نمره دارد.</p>

      <p><b>وضعیت</b> اما محتاط است. کارتی «ضعیف» خوانده می‌شود که
      دست‌کم ${fa(MIN_CARD)} بار مرور شده باشد <em>و</em> یا دقتش زیر ۶۰ درصد باشد
      یا ${fa(LEECH_WARN)} بار فراموش شده باشد. تا وقتی این شرط جمع نشده،
      کارت «در حال یادگیری» می‌ماند — حتی اگر نمره‌اش پایین باشد.</p>

      <p><b>پس حالتی که احتمالاً دیده‌اید طبیعی است:</b> کارتی با نمره‌ی ۹ که
      «در حال یادگیری» است، کنار کارتی با نمره‌ی ۲۸ که «ضعیف» است.
      اولی تازه یک بار دیده شده و هنوز چیزی درباره‌اش نمی‌دانیم؛
      دومی چند بار مرور شده و بدش را نشان داده. نمره‌ی پایین‌ترِ اولی
      از بی‌خبری است، نه از ضعف اثبات‌شده.</p>

      <p>برای همین «ضعیف» بودن جدی‌تر از نمره‌ی پایین است. کارت‌های ضعیف
      کسانی‌اند که فرصتشان را داشته‌اند و نگرفته‌اند.</p>
    </div>`:""}
    <div class="bkgrid">
      ${["weak","learn","fam","strong","new"].map(b=>{
        const L=cards.filter(c=>c.bucket===b);
        const pct=cards.length?Math.round(L.length/cards.length*100):0;
        return `<div class="bkcard ${b} ${L.length?"":"off"}" ${L.length?`data-fbbucket="${b}"`:""}
          data-tip="${L.length?`<b>${MAT_LABEL[b]}</b><br>${fa(L.length)} کارت — برای شروع جلسه‌ی مرور با همه‌ی این‌ها بزنید`
                              :`فعلاً کارتی در این دسته نیست`}">
          <div class="bh"><i class="s-${b}"></i><span>${MAT_LABEL[b]}</span></div>
          <div class="bn">${fa(L.length)}</div>
          <div class="bp">${fa(pct)}٪ از دک</div>
          ${L.length?`<div class="bg">شروع مرور ←</div>`:`<div class="bg off">—</div>`}
        </div>`}).join("")}
    </div>
    <div class="capt" style="margin:11px 0 0">
      اینجا همه‌ی کارت‌های یک وضعیت با هم می‌آیند، فرقی نمی‌کند از کدام سطح یا بخش کنکور باشند.
      برای تمرکز روی یک گروه خاص، از نقشه‌ی پایین استفاده کنید.
    </div>
  </div>

  <div class="panel">
    <div class="row-f" style="margin-bottom:4px">
      <div class="label" style="margin:0">نقشه‌ی تسلط شما</div>
      <span class="capt" style="margin:0">هر میله یک گروه است؛ طولش تعداد کارت، رنگ‌هایش وضعیت آن‌ها</span>
    </div>
    ${fbLegend()}
    <div class="dimgrid">
      ${dimChart(cards,"سطح کلمه",c=>c.t==="w"?c.lvl:null,"ساده / متوسط / پیشرفته","lvl")}
      ${dimChart(cards,"بخش کنکور",c=>c.secs,"وکب / کلوز / پسیج","sec")}
      ${dimChart(cards,"نقش دستوری",c=>c.t==="w"?c.pos:null,"","pos")}
      ${dimChart(cards,"نوع کارت",c=>c.t==="w"?"کلمه":"تست","","kind")}
    </div>
    <div class="capt" style="margin:12px 0 0">
      <b>روی هر تکه‌ی رنگی یا هر دکمه‌ی زیرش بزنید</b> تا همان دسته از همان گروه وارد جلسه‌ی مرور شود —
      مثلاً «۳۵ ضعیف» در سطح متوسط، یا «۸۸ تازه» در پسیج. عدد سمت چپ تعداد کل کارت آن گروه است و
      درصدش نسبت ضعیف‌ها از کارت‌های مرورشده‌ی همان گروه.
    </div>
  </div>

  <div class="fb-two">
    <div class="panel">
      <div class="label">کجا ضعیف‌ترید</div>
      <div class="capt" style="margin:0 0 12px">گروه‌هایی که به‌طور معنادار از میانگین خودتان بدترند</div>
      ${weakG.length?weakG.slice(0,4).map((g,gi)=>`
        <div class="fb-line weak"><b>${g.dim}: ${g.name}</b>
          <div class="gcmp">
            <div class="gc"><span>این گروه</span>
              <div class="gt"><i style="width:${Math.round(g.r*100)}%;background:#c05a35"></i></div>
              <b>${fa(Math.round(g.r*100))}٪</b></div>
            <div class="gc"><span>کل دک شما</span>
              <div class="gt"><i style="width:${Math.round(wShare*100)}%;background:#b9bec4"></i></div>
              <b>${fa(Math.round(wShare*100))}٪</b></div>
          </div>
          <div>${fa(g.all)} کارت با این ویژگی در دک دارید و ${fa(g.n)} تای آن‌ها را به‌قدر کافی مرور کرده‌اید.
          از همان ${fa(g.n)} کارت، ${fa(g.cnt)} کارت ضعیف مانده — یعنی ${fa(Math.round(g.r*100))} درصد.
          در کل دک شما این عدد ${fa(Math.round(wShare*100))} درصد است، پس اینجا حدود
          ${fa(Math.round(g.diff*100))} واحد بدتر از حال و روز معمول خودتان هستید.</div>
          ${cardSet("wg"+gi, "ضعیف‌های «"+g.dim+": "+g.name+"»", g.cards.filter(c=>c.bucket==="weak"))}
          <button class="mini" data-fbg="${gi}" style="margin-top:9px">تمرکز روی ضعیف‌های همین گروه</button></div>`).join("")
        :`<div class="capt" style="margin:0">هیچ گروهی به‌طور معنادار از میانگین خودتان بدتر نیست
          ${enough?"— یعنی ضعفتان پراکنده است نه متمرکز روی یک دسته.":"، ولی داده هم هنوز کم است."}</div>`}
    </div>
    <div class="panel">
      <div class="label">کجا قوی‌ترید</div>
      ${strongG.length?strongG.slice(0,4).map(g=>`
        <div class="fb-line strong"><b>${g.dim}: ${g.name}</b>
          <div>از ${fa(g.n)} کارت مرورشده با این ویژگی، ${fa(g.cnt)} کارت به تسلط رسیده — ${fa(Math.round(g.r*100))} درصد،
          در برابر ${fa(Math.round(sShare*100))} درصدِ کل دک شما.</div></div>`).join("")
        :`<div class="capt" style="margin:0">هنوز گروهی به‌طور معنادار از بقیه جلو نزده است.</div>`}
    </div>
  </div>

  <div class="panel">
    <div class="row-f" style="margin-bottom:10px">
      <div class="label" style="margin:0">کارت‌های دردسرساز</div>${(CB_SETS.leech={title:"کارت‌های دردسرساز",cards:leeches})&&""}
      <span class="capt" style="margin:0">کمترین تسلط، بیشترین فراموشی</span>
      ${leeches.length?`<button class="deckbtn" id="fbFocus" style="margin-right:auto">
        جلسه‌ی تمرکز روی این ${fa(Math.min(leeches.length,15))} کارت</button>`:""}
    </div>
    ${leeches.length?(leeches.slice(0,10).map((c,i)=>`
      <div class="lrow" data-fbw="${c.t==="w"?c.w.w:""}">
        <div class="lrk">${fa(i+1)}</div>
        <div class="lmain">
          <div class="lhead">
            <b class="${c.t==="w"?"en":""}">${c.t==="w"?c.w.w:c.label}</b>
            ${c.fa?`<span class="lfa">${c.fa}</span>`:""}
            ${c.lvl?`<span class="badge ${lvlClass(c.lvl)}">${c.lvl}</span>`:""}
          </div>
          <div class="lmeta">
            <span class="hot" data-tip="تعداد دفعاتی که بعد از یاد گرفتن، دوباره فراموشش کرده‌اید">${fa(c.lapses)} بار فراموشی</span>
            <span data-tip="نسبت پاسخ‌های درست به کل مرورهای این کارت">${fa(Math.round((c.acc||0)*100))}٪ از ${fa(c.seen)} مرور</span>
            <span data-tip="فاصله‌ی تعیین‌شده تا مرور بعدی">مرور بعدی: ${ivLabel(c.iv)}</span>
          </div>
        </div>
        <div class="lsc" data-tip="نمره‌ی تسلط — هرچه کمتر، مشکل‌سازتر">
          <b>${fa(c.m)}</b>
          <div class="ltrack"><i style="width:${Math.max(3,c.m)}%;background:${MAT_COLOR[c.bucket]}"></i></div>
        </div>
      </div>`).join(""))
      + (leeches.length>10
          ? `<button class="clipbtn" data-cbopen="leech" style="margin:11px 4px 0">
               دیدن همه‌ی ${fa(leeches.length)} کارت <span class="ar">›</span></button>`
          : "")
      + `<div class="capt" style="margin:10px 4px 0">عدد سمت چپ نمره‌ی تسلط از ۱۰۰ است؛ هرچه کمتر، کارت برای شما مشکل‌سازتر.</div>`
      :`<div class="capt" style="margin:0">فعلاً کارتی به دسته‌ی ضعیف نیفتاده است.</div>`}
  </div>

  <div class="panel">
    <div class="row-f"><div class="label" style="margin:0">این صفحه از کجا می‌فهمد شما در چیزی ضعیفید؟</div>
      <button class="ghost" id="fbHelpBtn" style="margin-right:auto">${FB.help?"بستن توضیح":"توضیح کامل"}</button></div>
    ${FB.help?`<div class="fb-help">
      <p>تنها ورودی این صفحه همان چهار دکمه‌ای است که موقع مرور هر کارت می‌زنید: «یادم نبود»، «سخت بود»،
      «یادم بود»، «ساده بود». آزمون جداگانه‌ای در کار نیست و هیچ حدسی درباره‌ی شما زده نمی‌شود.</p>

      <p><b>چرا فقط شمردن «چند بار زدم یادم نبود» کافی نیست؟</b><br>
      چون سه بار «یادم نبود» روی کلمه‌ای که تازه دیده‌اید کاملاً طبیعی است، ولی همان سه بار روی کلمه‌ای که
      دو ماه است مرورش می‌کنید یعنی مشکل جدی. پس به‌جای یک عدد خام، چهار چیز کنار هم دیده می‌شود:</p>
      <ul>
        <li><b>درصد درست</b> — از کل دفعاتی که این کارت را دیده‌اید، چند بار بلد بوده‌اید.</li>
        <li><b>تعداد فراموشی</b> — چند بار کارتی که قبلاً بلد بودید دوباره از یادتان رفت. این با «تازه بلد نبودن» فرق دارد.</li>
        <li><b>فاصله‌ی مرور</b> — سامانه‌ی مرور ما بعد از هر پاسخ درست، مرور بعدی را دیرتر می‌گذارد و بعد از هر
        فراموشی زودتر. اگر کارتی به فاصله‌ی سه هفته رسیده یعنی حافظه‌تان رویش پایدار شده؛ اگر بعد از ده بار مرور
        هنوز فاصله‌اش یک روز است یعنی هر بار از صفر شروع می‌کنید.</li>
        <li><b>سختی کارت برای شما</b> — عددی که برای هر کارت نگه می‌داریم و با هر «سخت بود» پایین و با هر
        «ساده بود» بالا می‌رود. دو نفر می‌توانند یک کلمه را بلد باشند ولی یکی هر بار مکث کند؛ این عدد همان تفاوت را ثبت می‌کند.</li>
      </ul>

      <p><b>پنج دسته دقیقاً یعنی چه؟</b></p>
      <ul>
        <li><b>مسلط</b> — کارت را چند بار پشت‌سرهم درست زده‌اید و فاصله‌ی مرورش به ${fa(MAT_MATURE)} روز یا بیشتر رسیده،
        بدون اینکه بیش از دو بار فراموشش کرده باشید. یعنی می‌شود سه هفته دست به آن نزنید و باز هم یادتان باشد.</li>
        <li><b>آشنا</b> — فاصله‌ی مرورش بین ${fa(MAT_YOUNG)} تا ${fa(MAT_MATURE)} روز است. بلدید، ولی هنوز به حافظه‌ی بلندمدت نرفته.</li>
        <li><b>در حال یادگیری</b> — فاصله‌اش هنوز کمتر از ${fa(MAT_YOUNG)} روز است. تازه شروع کرده‌اید و مشکلی هم نیست.</li>
        <li><b>ضعیف</b> — دست‌کم ${fa(MIN_CARD)} بار مرورش کرده‌اید و یا کمتر از ۶۰٪ دفعات بلد بوده‌اید،
        یا ${fa(LEECH_WARN)} بار یا بیشتر فراموشش کرده‌اید. این‌ها کارت‌هایی‌اند که وقت شما را می‌خورند و پیش نمی‌روند.</li>
        <li><b>دیده‌نشده</b> — در دک هست ولی هنوز مرورش نکرده‌اید. درباره‌اش هیچ نمی‌دانیم و در هیچ درصدی شمرده نمی‌شود.</li>
      </ul>

      <p><b>چرا مرزِ فراموشی را ${fa(LEECH_WARN)} گذاشتیم؟</b><br>
      این عدد یک انتخاب است نه قانون. اگر خیلی پایین باشد، کارتی که فقط دو سه بار قاطی کرده‌اید «ضعیف» برچسب می‌خورد
      و بی‌خود نگرانتان می‌کند. اگر خیلی بالا باشد، تا وقتی هشدار بدهد ماه‌ها وقت تلف کرده‌اید.
      ${fa(LEECH_WARN)} بار فراموشی روی یک کلمه برای دک کنکور نقطه‌ی معقولی است: به‌قدری هست که تصادفی نباشد،
      و به‌قدری زود هست که هنوز فرصت جبران داشته باشید.</p>

      <p><b>این «میانگین دک شما» که مدام تکرار می‌شود یعنی چه؟</b><br>
      یعنی درصد کارت‌های ضعیف در کل کارت‌های مرورشده‌ی شما. مقایسه همیشه با خودتان است، نه با کاربران دیگر.
      اگر در کل دک ۲۵٪ کارت‌هایتان ضعیف‌اند ولی در کلمات سطح متوسط این عدد ۴۴٪ است،
      معنی‌اش این است که سطح متوسط برای شما گلوگاه است — نه اینکه از بقیه عقب‌ترید.</p>

      <p><b>کِی درباره‌ی یک گروه حرف می‌زنیم؟</b><br>
      فقط وقتی دست‌کم ${fa(MIN_GROUP)} کارت مرورشده در آن گروه داشته باشید و فاصله‌اش با میانگین خودتان
      دست‌کم ۱۰ واحد درصد باشد. با چهار پنج کارت هر عددی ممکن است شانسی باشد و ارزش حرف‌زدن ندارد.</p>
    </div>`:""}
  </div>`;
  mountSlots();
}
function mountSlots(){
  const st=window.STATS_EL,slot=$("#statsSlot");
  if(st&&slot)slot.appendChild(st);
}

const FB={help:false,score:false};

/* استخراج کارت‌های یک گروه از یک بُعد — همان نگاشتی که dimChart استفاده می‌کند */
const DIM_PICK={
  lvl:  c=>c.t==="w"?c.lvl:null,
  sec:  c=>c.secs,
  pos:  c=>c.t==="w"?c.pos:null,
  kind: c=>c.t==="w"?"کلمه":"تست",
};

/* کلیک روی یک تکه‌ی نمودار یا دکمه‌های زیرش:
   جلسه‌ی مرور فقط با همان دسته از همان گروه ساخته می‌شود.
   این همان چیزی است که «۲۰٪ ضعیفی» را به کار عملی تبدیل می‌کند. */
function fbDrill(raw){
  const [dimKey,name,bucket]=decodeURIComponent(raw).split("|");
  const pick=DIM_PICK[dimKey];
  if(!pick)return;

  const sel=allCards().filter(c=>{
    if(c.bucket!==bucket)return false;
    const k=pick(c);
    if(k===null||k===undefined||k==="")return false;
    return Array.isArray(k) ? k.indexOf(name)>=0 : k===name;
  });

  if(!sel.length){toast("در این دسته کارتی نماند.");return}

  /* ضعیف‌ترین‌ها اول — تا وقت جلسه صرف چیزی شود که واقعاً لنگ است */
  sel.sort((a,b)=>a.m-b.m||b.lapses-a.lapses);
  const take=sel.slice(0,30);

  startCards(take);
  toast(`جلسه‌ی مرور: ${fa(take.length)} کارت ${MAT_LABEL[bucket]} از «${name}»`
        + (sel.length>take.length?` — از مجموع ${fa(sel.length)} کارت، سی‌تای اول.`:""));
}

$("#tab-fb").addEventListener("click",e=>{
  /* یک دسته‌ی کامل، بدون توجه به گروه */
  const bk=e.target.closest("[data-fbbucket]");
  if(bk){
    const b=bk.dataset.fbbucket;
    const sel=allCards().filter(c=>c.bucket===b).sort((a,b2)=>a.m-b2.m||b2.lapses-a.lapses);
    if(!sel.length){toast("در این دسته کارتی نیست.");return}
    const take=sel.slice(0,40);
    startCards(take);
    toast(`جلسه‌ی مرور: ${fa(take.length)} کارت ${MAT_LABEL[b]}`
      + (sel.length>take.length?` — از مجموع ${fa(sel.length)} کارت، چهل‌تای اول.`:""));
    return;
  }
  const dr=e.target.closest("[data-fbdrill]");
  if(dr){fbDrill(dr.dataset.fbdrill);return}
  if(e.target.closest("#fbScoreBtn")){FB.score=!FB.score;renderFeedback();return}

  if(e.target.closest("#fbHelpBtn")){FB.help=!FB.help;renderFeedback();return}
  if(e.target.closest("#fbFocus")){startFocus();return}
  if(e.target.closest("#fbToday")){startCards(todayCards());return}
  const g=e.target.closest("[data-fbg]");
  if(g){const grp=(window.FBG||[])[+g.dataset.fbg];
    if(grp)startCards(grp.cards.filter(c=>c.bucket==="weak").slice(0,15));return}
  const r=e.target.closest("[data-fbw]");
  if(r&&r.dataset.fbw){const w=WORDS.find(x=>x.w===r.dataset.fbw);if(w)openDetail(w)}
});
function startFocus(){
  const weak=allCards().filter(c=>c.bucket==="weak").sort((a,b)=>a.m-b.m).slice(0,15);
  if(!weak.length)return;
  session=weak.map(c=>c.t==="w"?{t:"w",w:c.w,key:"w|"+c.w.w}
    :{t:"q",y:c.y,e:c.e,q:c.q,key:"q|"+qKey(c.y,c.e,c.q)});
  pos=0;flipped=false;$("#review").classList.add("open");drawCard();syncUrl(true);
}

/* ================= ماندگاری وضعیت ================= */
function saveState(){
  LS.set("zban_state",{deck:[...deck],star:[...star],deckQ:[...deckQ],starQ:[...starQ],
    sched,miss,dayNow});
  LS.set("zban_qatt",QATT);
}
(function mergeStats(){
  const st=$("#tab-stats");
  if(st){st.hidden=false;st.removeAttribute("hidden");if(st.parentNode)st.parentNode.removeChild(st);
    window.STATS_EL=st}
})();
/* ===== دیتای نمایشی =====
   بار اول که فایل باز می‌شود دک خالی است، پس تب‌های تعادل و فیدبک
   چیزی برای نشان دادن ندارند. این تابع یک دک واقع‌نما می‌سازد تا
   بشود کار تب‌ها را دید. فقط یک بار اجرا می‌شود و بعدش وضعیت واقعی
   کاربر جایش را می‌گیرد. در نسخه‌ی سروری اصلاً اجرا نمی‌شود. */
function seedDemo(){
  const H=(x)=>{let h=0;for(let i=0;i<x.length;i++)h=(h*31+x.charCodeAt(i))>>>0;return h};
  const pick=WORDS.filter((w,i)=>H(w.w)%100<22);          /* حدود ۲۲٪ کلمات */
  pick.forEach(w=>{
    deck.add(w.w);
    const h=H(w.w), key="w|"+w.w;
    const bucket=h%100;
    let s,d,iv,reps,lapses,seen,again,last;
    if(bucket<28){                                        /* تازه، هنوز مرور نشده */
      sched[key]={s:null,d:null,iv:0,reps:0,lapses:0,due:dayNow,last:null,seen:0,again:0};
      return;
    }
    if(bucket<48){ s=1.5+(h%30)/10; d=6.5+(h%25)/10; iv=1+h%3;  reps=1+h%2; lapses=1+h%3; seen=2+h%4 }
    else if(bucket<70){ s=6+(h%60)/10; d=4.5+(h%20)/10; iv=4+h%6; reps=2+h%3; lapses=h%2; seen=3+h%5 }
    else if(bucket<88){ s=14+(h%90)/10; d=3.5+(h%18)/10; iv=9+h%9; reps=3+h%4; lapses=h%2; seen=4+h%6 }
    else { s=30+(h%160)/10; d=2.2+(h%14)/10; iv=22+h%20; reps=5+h%5; lapses=0; seen=6+h%7 }
    again=lapses;
    last=dayNow-(h%Math.max(1,iv));
    sched[key]={s:+s.toFixed(2),d:+d.toFixed(2),iv,reps,lapses,
                due:last+iv,last,seen,again};
  });

  /* چند تست هم در دک باشد تا کارت تست دیده شود */
  YEARS.slice(-3).forEach(y=>{
    EXAM_NAMES.forEach(e=>{
      for(let q=1;q<=4;q++){
        const k=qKey(y,e,q);
        if(H(k)%3===0)deckQ.add(k);
      }
    });
  });

  /* چند روز فعالیت، تا رتبه‌بندی و نمودار هفتگی خالی نباشد.
     ساختار ACT به شکل {days:{...}} است، نه نگاشت مسطح. */
  const act=LS.get("zban_act")||{days:{}};
  act.days=act.days||{};
  for(let i=0;i<9;i++){
    const dt=new Date(); dt.setDate(dt.getDate()-i);
    const k=dt.getFullYear()+"-"+String(dt.getMonth()+1).padStart(2,"0")+"-"+String(dt.getDate()).padStart(2,"0");
    const h=H(k);
    const rec={sec:900+h%3600, rev:8+h%26, revOk:0, q:h%7, qOk:0, mast:h%3};
    rec.revOk=Math.round(rec.rev*(0.55+(h%35)/100));
    rec.qOk =Math.round(rec.q  *(0.45+(h%40)/100));
    act.days[k]=rec;
  }
  LS.set("zban_act",act);

  LS.set("zban_seeded",1);
  saveState();
}

/* از حافظه‌ی مرورگر دیگر چیزی از zban_state خوانده نمی‌شود. دک، منتخب‌ها،
   زمان‌بندی مرور و «چند بار یادم نبود» (miss = again_count سرور) همه از
   سرور می‌آیند (loadServerSets و loadServerSched). قبلاً از مرورگر خوانده
   می‌شدند و روی نسخه‌ی سرور می‌نشستند: با عوض کردن مرورگر پیشرفت صفر دیده
   می‌شد، و چیزی که در دستگاه دیگری حذف شده بود دوباره برمی‌گشت. */

/* زمان‌بندی مرور هر کارت از deck_items سرور (همان چیزی که با هر مرور ذخیره می‌شود). */
(function loadServerSched(){
  (window.SERVER_DECK||[]).forEach(x=>{
    let key=null;
    if(x.t==="w"){const w=WBYID[x.id]; if(w){key="w|"+w.w; if(x.again>0)miss[w.w]=x.again}}
    else if(isQKey(x.k))key="q|"+x.k;
    if(!key)return;
    sched[key]={
      s: x.s!=null?+x.s:null, d: x.dif!=null?+x.dif:null,
      iv: x.iv||0, reps: x.reps||0, lapses: x.lapses||0,
      due: x.s==null ? dayNow : (dayOfDate(x.due) ?? dayNow),
      last: dayOfDate(x.last), seen: x.seen||0, again: x.again||0,
    };
  });
})();
const _refresh=refreshAll;
refreshAll=function(){
  _refresh();saveState();
  /* چیپ‌های کلمه داخل دفترچه‌ی آزمون هم باید وضعیت تازه‌ی دک را نشان بدهند */
  if(EX.stage&&$("#exBody"))paintBook();
};
const _applyRating=applyRating;
applyRating=function(key,r){
  const c=card(key);
  if(!(c.seen>0))newToday++;              /* اولین مرور این کارت */
  c.seen=(c.seen||0)+1;if(r===1)c.again=(c.again||0)+1;
  const out=_applyRating(key,r);saveState();updDeckCount();return out;
};


/* ================= دیالوگ و پیام داخلی ================= */
/* confirm()/alert() مرورگر داخل iframe های sandboxed بی‌صدا لغو می‌شوند،
   برای همین همه‌جای پلتفرم از این دو استفاده می‌شود. */
let _ask=null;
function toast(msg){
  const t=$("#toast");
  t.innerHTML=`<span>${msg}</span><button class="x" id="toastX" data-tip="بستن">×</button>`;
  t.classList.add("on");
}
$("#toast").addEventListener("click",()=>$("#toast").classList.remove("on"));
function askConfirm(msg,onYes,yesLabel){
  _ask=onYes;
  $("#askMsg").textContent=msg;
  $("#askYes").textContent=yesLabel||"بله";
  $("#askM").classList.add("open");
}
$("#askYes").addEventListener("click",()=>{const f=_ask;_ask=null;$("#askM").classList.remove("open");if(f)f()});
$("#askNo").addEventListener("click",()=>{_ask=null;$("#askM").classList.remove("open")});
$("#askM").addEventListener("click",e=>{if(e.target.id==="askM"){_ask=null;$("#askM").classList.remove("open")}});

/* ================= فعالیت روزانه ================= */
const ACT=LS.get("zban_act")||{days:{}};
function todayKey(){const d=new Date();return d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0")+"-"+String(d.getDate()).padStart(2,"0")}
function actAdd(k,n){
  const t=todayKey();
  const d=ACT.days[t]=ACT.days[t]||{sec:0,rev:0,q:0};
  d[k]=(d[k]||0)+n;LS.set("zban_act",ACT);
}
/* زمان مطالعه فقط وقتی شمرده می‌شود که کاربر واقعاً کاری کرده باشد.
   باز بودن تب کافی نیست — وگرنه کسی می‌تواند صفحه را باز بگذارد و برود.
   هر حرکت (کلیک، تایپ، اسکرول، لمس) مهر زمانی را تازه می‌کند؛
   اگر IDLE_MIN دقیقه هیچ خبری نباشد، شمارش می‌ایستد.
   سمت سرور همین کار را /api/heartbeat می‌کند. */
const IDLE_MIN = 3;
let lastActive = Date.now();
["mousedown","keydown","wheel","touchstart","scroll","input"].forEach(ev=>
  window.addEventListener(ev, ()=>{ lastActive = Date.now() }, {passive:true}));

setInterval(()=>{
  if(document.hidden === true) return;
  if(Date.now() - lastActive > IDLE_MIN*60000) return;   // بی‌حرکت
  actAdd("sec",30);
},30000);
const _applyRating2=applyRating;
applyRating=function(k,r){
  const before=cardStats(k).bucket;
  actAdd("rev",1); if(r>=3)actAdd("revOk",1);
  const out=_applyRating2(k,r);
  if(cardStats(k).bucket==="strong"&&before!=="strong")actAdd("mast",1);
  return out;
};

/* ================= جدول رتبه‌بندی ================= */
/* امتیاز فعالیت — عمداً ترکیبی است تا فقط «باز نگه داشتن تب» یا
   «رد کردن سریع کارت‌ها» بالا نیاورد. جزئیاتش زیر جدول برای کاربر نوشته شده. */
const PT={min:1, rev:2, q:3, mast:25};
const DAY_MIN_CAP=240;                 // سقف دقیقه‌ی محاسبه‌شده در هر روز
/* شکل سرور: nickname و کد رشته (ce/it/cs). صفحه nick و نام فارسی می‌خواهد.
   قبلاً مستقیم خوانده می‌شد و ستون رشته‌ی رتبه‌بندی «ce» نشان می‌داد. */
const EXAM_CODE_FA={ce:"مهندسی کامپیوتر",it:"آی‌تی",cs:"علوم کامپیوتر"};
function profFromServer(p){p=p||{};return {
  nick:p.nickname||"", exam:EXAM_CODE_FA[p.exam]||"مهندسی کامپیوتر", examCode:EXAM_CODE_FA[p.exam]?p.exam:"ce",
  board:p.show_in_board!==false, name:p.name||"", mobile:p.mobile||"",
  uni:p.university||"", gpa:p.gpa!=null?+p.gpa:null, quota:p.quota||"", degree:p.degree||"",
  newPerDay:p.new_per_day||null}}
const PROF=profFromServer(LS.get("zban_prof"));
const LB={days:1};
const LB_QUICK=[[1,"امروز"],[7,"این هفته"],[30,"این ماه"]];

function actSum(days){
  const out={sec:0,rev:0,revOk:0,q:0,qOk:0,mast:0},now=new Date();
  Object.keys(ACT.days||{}).forEach(k=>{
    const diff=Math.floor((now-new Date(k+"T00:00:00"))/86400000);
    if(diff<0||diff>=days)return;
    const d=ACT.days[k];
    out.sec+=d.sec||0;   /* سقف روزانه برداشته شد؛ شمارش فقط با فعالیت واقعی است */
    out.rev+=d.rev||0;out.revOk+=d.revOk||0;
    out.q+=d.q||0;out.qOk+=d.qOk||0;out.mast+=d.mast||0;
  });
  return out;
}
function lbScore(s){
  const accR=s.rev?s.revOk/s.rev:.5, accQ=s.q?s.qOk/s.q:.5;
  return Math.round(s.sec/60*PT.min + s.rev*PT.rev*(0.5+0.5*accR)
    + s.q*PT.q*(0.5+0.5*accQ) + s.mast*PT.mast);
}
function rng(seed){let x=seed>>>0;return()=>{x=(x*1664525+1013904223)>>>0;return x/4294967296}}
/* رتبه‌بندی از سرور می‌آید — جدول board_cache که دستور zaban:board هر ده
   دقیقه می‌سازد. تا نرسیدنش آرایه خالی است و فقط خود کاربر در جدول
   دیده می‌شود؛ صادقانه‌تر از نشان دادن سی رقیب ساختگی که تا امروز
   اینجا بودند. */
const LB_ROWS = {};       /* به‌ازای هر بازه‌ی روز، کش می‌شود */
let   LB_TOTAL = 0;
const EXAM_ORDER = ["ce","it","cs"];

function demoRows(days){ return LB_ROWS[days] || [] }

function loadBoard(days){
  if(LB_ROWS[days] || !window.ZABAN || !ZABAN.board) return;
  LB_ROWS[days]=[];                       /* جلوی درخواست تکراری */
  ZABAN.board(days).then(r=>{
    /* ردیف خود کاربر از سرور کنار گذاشته می‌شود؛ ردیف «شما» با آمار زنده‌ی
       همین لحظه جایش می‌نشیند. قبلاً هر دو می‌آمدند و کاربر دوبار دیده می‌شد. */
    LB_ROWS[days]=((r&&r.rows)||[]).filter(x=>!x.me).map(x=>({
      nick: x.nick || "بی‌نام",
      exam: EXAM_NAMES[EXAM_ORDER.indexOf(x.exam)] || x.exam,
      sec:  x.study_sec,
      rev:  x.reviews,   revOk: x.reviews_ok,
      q:    x.questions, qOk:   x.questions_ok,
      mast: x.mastered,
    }));
    LB_TOTAL=(r&&r.users_total)||LB_ROWS[days].length;
    if(navTab==="rank")renderBoard();
  }).catch(e=>console.error(e));
}
function hm(sec){const h=Math.floor(sec/3600),m=Math.floor(sec%3600/60);
  return fa(String(h).padStart(2,"0"))+":"+fa(String(m).padStart(2,"0"))}
function pc(a,b){return b?fa(Math.round(a/b*100))+"٪":"—"}
/* درصد دقت به شکل قرص رنگی — سبز بالای ۸۰، طلایی ۶۰ تا ۸۰، قرمز زیر ۶۰ */
function accPill(a,b){
  if(!b)return '<span class="na">—</span>';
  const v=Math.round(a/b*100);
  const c=v>=80?"hi":v>=60?"mid":"lo";
  return `<span class="${c}">${fa(v)}٪</span>`;
}

function renderBoard(){
  renderRankExtras();
  const days=LB.days;
  loadBoard(days);
  const me=Object.assign({nick:PROF.nick||"من",exam:PROF.exam,me:true},actSum(days));
  const rows=demoRows(days).concat([me]);
  rows.forEach(x=>x.pts=lbScore(x));
  rows.sort((a,b)=>b.pts-a.pts||b.sec-a.sec);
  const myRank=rows.findIndex(x=>x.me)+1;
  const top=rows.slice(0,10), inTop=top.some(x=>x.me);
  const line=(x,i)=>`<tr class="${x.me?"me":""}">
      <td class="rk">${fa(i+1)}</td>
      <td class="nk"><span class="who">${
        x.me && (!x.nick || x.nick==="من") ? "شما" : x.nick
      }</span>${x.me && x.nick && x.nick!=="من" ? '<span class="tag">شما</span>' : ""}</td>
      <td>${x.exam}</td>
      <td class="num pts">${fa(x.pts)}</td>
      <td class="num">${hm(x.sec)}</td>
      <td class="num pair">${fa(x.rev)}</td>
      <td class="num acc">${accPill(x.revOk,x.rev)}</td>
      <td class="num pair">${fa(x.q)}</td>
      <td class="num acc">${accPill(x.qOk,x.q)}</td>
      <td class="num">${fa(x.mast)}</td></tr>`;
  const months=[];for(let m=2;m<=12;m++)months.push(m);
  $("#lbBody").innerHTML=`
    <div class="row-f" style="margin-bottom:12px">
      <div class="seg" id="lbSeg">${LB_QUICK.map(p=>
        `<button data-d="${p[0]}" class="${LB.days===p[0]?"on":""}">${p[1]}</button>`).join("")}</div>
      <select id="lbMon" class="${LB.days>30?"on":""}">
        <option value="">چند ماه اخیر…</option>
        ${months.map(m=>`<option value="${m*30}" ${LB.days===m*30?"selected":""}>${fa(m)} ماه اخیر</option>`).join("")}
      </select>
      <span class="lb-live" style="margin-right:auto">${fa(LB_TOTAL||rows.length)} کاربر ثبت‌نام‌کرده</span>
    </div>
    <div class="lb-wrap"><table class="lb">
      <thead><tr>
        <th class="c">رتبه</th><th>نام مستعار</th><th>رشته</th>
        <th class="n">امتیاز</th><th class="n">زمان مطالعه</th>
        <th class="n pair">کارت مرورشده</th><th class="n acc">دقت کارت</th>
        <th class="n pair">تست زده</th><th class="n acc">دقت تست</th>
        <th class="n">کارت مسلط‌شده</th></tr></thead>
      <tbody>${top.map(line).join("")}
        ${inTop?"":`<tr class="gap"><td colspan="10">⋯</td></tr>`+line(rows[myRank-1],myRank-1)}</tbody>
    </table></div>
    <div class="lb-note">این جدول <b>میزان و کیفیت فعالیت</b> را نشان می‌دهد، نه سطح علمی و نه رتبه‌ی کنکور.
      کسی که بالای جدول است لزوماً بهتر نمی‌خواند؛ فقط بیشتر و بهتر کار کرده است.</div>
    <div class="panel" style="margin-top:12px">
      <div class="row-f"><div class="label" style="margin:0">امتیاز چطور حساب می‌شود؟</div>
        <button class="ghost" id="lbHelpBtn" style="margin-right:auto">${LB.help?"بستن":"توضیح"}</button></div>
      ${LB.help?`<div class="fb-help">
        <p>اگر رتبه فقط بر اساس زمان بود، کسی که آرام و پراکنده کار می‌کند از کسی که
        فشرده و متمرکز می‌خواند جلو می‌افتاد — فقط چون بیشتر پای صفحه بوده.
        و اگر فقط بر اساس تعداد کارت بود، کافی بود کسی سیصد کارت را پشت‌سرهم رد کند
        بدون اینکه چیزی یاد بگیرد. برای همین امتیاز از چهار چیز ساخته می‌شود:</p>

        <div class="fx">
          <div class="fxline">امتیاز =</div>
          <div class="fxline">دقیقه‌ی مطالعه × ${fa(PT.min)}</div>
          <div class="fxline">+ کارت مرورشده × ${fa(PT.rev)} × <span class="q">ضریب کیفیت</span></div>
          <div class="fxline">+ تست پاسخ‌داده‌شده × ${fa(PT.q)} × <span class="q">ضریب کیفیت</span></div>
          <div class="fxline">+ کارت مسلط‌شده × ${fa(PT.mast)}</div>
        </div>
        <div class="qdef">
          <div class="qeq">
            <span class="q">ضریب کیفیت</span>
            <span class="op">=</span>
            <span class="num">۰٫۵</span>
            <span class="op">+</span>
            <span class="num">۰٫۵</span>
            <span class="op">×</span>
            <span class="var">دقت</span>
          </div>

          <p><b>«دقت» از کجا می‌آید؟</b> دو جور دقت داریم و هر دو یک شکل حساب می‌شوند —
          نسبت موفق‌ها به کل:</p>

          <table class="qtab" style="margin-bottom:12px">
            <tr><th>کدام دقت</th><th>چطور حساب می‌شود</th></tr>
            <tr>
              <td>دقت کارت</td>
              <td style="direction:rtl;text-align:right;color:var(--ink-2)">
                کارت‌هایی که «یادم بود» یا «ساده بود» زده‌اید ÷ کل کارت‌های مرورشده
              </td>
            </tr>
            <tr>
              <td>دقت تست</td>
              <td style="direction:rtl;text-align:right;color:var(--ink-2)">
                تست‌هایی که درست زده‌اید ÷ کل تست‌های پاسخ‌داده‌شده
              </td>
            </tr>
          </table>

          <p>پس <b>۰ یعنی هیچ‌کدام</b> و <b>۱ یعنی همه</b>، و هر عدد وسط همان کسر است.
          مثلاً اگر ۵۰ کارت مرور کرده باشید و ۴۰ تای آن‌ها را «یادم بود» زده باشید:
          <span class="mono">۴۰ ÷ ۵۰ = ۰٫۸</span> — یعنی دقت ۸۰٪.
          اگر هنوز هیچ مروری نکرده باشید دقت ۰٫۵ فرض می‌شود، نه ۰، چون هنوز چیزی برای قضاوت نیست.</p>

          <p><b>چرا به‌علاوه‌ی نیم کردیم؟</b> اگر ضریب کیفیت را فقط
          <span class="mono">۰٫۵ × دقت</span> می‌گذاشتیم، کسی که هیچ‌کدام از کارت‌هایش را
          بلد نبوده دقتش ۰ می‌شد، ضریب کیفیتش هم ۰، و کل امتیاز مرور آن کارت‌ها صفر —
          یعنی مرور کردن چیزی که بلد نیستید هیچ ارزشی نداشت.
          ولی همین کارت‌ها دقیقاً جایی‌اند که یادگیری اتفاق می‌افتد.</p>

          <p>پس نصف امتیاز را بابت <b>انجام دادنِ کار</b> می‌دهیم و نصف دیگر را
          بابت <b>کیفیتش</b>. با این کار ضریب هیچ‌وقت زیر ۰٫۵ نمی‌رود و هیچ‌وقت
          از ۱ بالاتر نمی‌رود:</p>

          <table class="qtab">
            <tr><th>دقت شما</th><th>محاسبه</th><th>ضریب</th><th>یعنی</th></tr>
            <tr><td>۰٪</td><td class="mono">۰٫۵ + ۰٫۵ × ۰</td><td class="mono b">۰٫۵</td><td>نصف امتیاز</td></tr>
            <tr><td>۵۰٪</td><td class="mono">۰٫۵ + ۰٫۵ × ۰٫۵</td><td class="mono b">۰٫۷۵</td><td>سه‌چهارم امتیاز</td></tr>
            <tr><td>۱۰۰٪</td><td class="mono">۰٫۵ + ۰٫۵ × ۱</td><td class="mono b">۱</td><td>تمام امتیاز</td></tr>
          </table>
        </div>

        <ul>
          <li><b>دقیقه‌ی مطالعه × ${fa(PT.min)}</b> — فقط دقیقه‌هایی شمرده می‌شوند که در آن‌ها
            کاری کرده باشید: کلیک، تایپ، اسکرول یا زدن کارت. اگر ${fa(IDLE_MIN)} دقیقه هیچ حرکتی نباشد
            شمارش می‌ایستد تا دوباره برگردید، پس باز گذاشتن صفحه امتیاز نمی‌سازد.</li>

          <li><b>کارت مرورشده × ${fa(PT.rev)} × ضریب کیفیت</b> — ضریب کیفیت بین ۰٫۵ و ۱ است:
            کسی که هیچ کارتی را بلد نبوده ضریب ۰٫۵ می‌گیرد و کسی که همه را بلد بوده ضریب ۱.
            پس رد کردن سریع کارت‌ها نصف امتیاز می‌دهد، نه صفر — چون دیدن کارت هم بی‌ارزش نیست.</li>

          <li><b>تست پاسخ‌داده‌شده × ${fa(PT.q)} × ضریب کیفیت</b> — همان ضریب، ولی این بار
            «دقت» یعنی چند درصد تست‌ها را درست زده‌اید.</li>

          <li><b>کارتی که به «مسلط» می‌رسد × ${fa(PT.mast)}</b> — سنگین‌ترین جزء،
            چون تنها چیزی است که واقعاً پیشرفت را نشان می‌دهد نه تلاش را.</li>
        </ul>

        <p><b>یک مثال.</b> دو نفر امروز هرکدام <b>۶۰ دقیقه</b> کار کرده‌اند،
        <b>۱۰۰ کارت</b> مرور کرده‌اند و <b>۲۰ تست</b> زده‌اند. تنها فرقشان دقت است:</p>

        <div class="fxex">
          <div>
            <div class="exh">نفر اول — دقت ۹۰٪ در کارت و ۸۰٪ در تست</div>
            <table class="extab">
              <tr><td>دقیقه</td><td class="mono">۶۰ × ۱</td><td class="mono b">۶۰</td></tr>
              <tr><td>ضریب کیفیت کارت</td><td class="mono">۰٫۵ + (۰٫۵ × ۰٫۹)</td><td class="mono">۰٫۹۵</td></tr>
              <tr><td>کارت</td><td class="mono">۱۰۰ × ۲ × ۰٫۹۵</td><td class="mono b">۱۹۰</td></tr>
              <tr><td>ضریب کیفیت تست</td><td class="mono">۰٫۵ + (۰٫۵ × ۰٫۸)</td><td class="mono">۰٫۹۰</td></tr>
              <tr><td>تست</td><td class="mono">۲۰ × ۳ × ۰٫۹۰</td><td class="mono b">۵۴</td></tr>
              <tr class="sum"><td>جمع</td><td class="mono">۶۰ + ۱۹۰ + ۵۴</td><td class="mono b">۳۰۴</td></tr>
            </table>
          </div>

          <div>
            <div class="exh">نفر دوم — دقت ۴۰٪ در کارت و ۳۰٪ در تست</div>
            <table class="extab">
              <tr><td>دقیقه</td><td class="mono">۶۰ × ۱</td><td class="mono b">۶۰</td></tr>
              <tr><td>ضریب کیفیت کارت</td><td class="mono">۰٫۵ + (۰٫۵ × ۰٫۴)</td><td class="mono">۰٫۷۰</td></tr>
              <tr><td>کارت</td><td class="mono">۱۰۰ × ۲ × ۰٫۷۰</td><td class="mono b">۱۴۰</td></tr>
              <tr><td>ضریب کیفیت تست</td><td class="mono">۰٫۵ + (۰٫۵ × ۰٫۳)</td><td class="mono">۰٫۶۵</td></tr>
              <tr><td>تست</td><td class="mono">۲۰ × ۳ × ۰٫۶۵</td><td class="mono b">۳۹</td></tr>
              <tr class="sum"><td>جمع</td><td class="mono">۶۰ + ۱۴۰ + ۳۹</td><td class="mono b">۲۳۹</td></tr>
            </table>
          </div>
        </div>

        <p>وقت و تعدادشان دقیقاً یکی بوده، ولی نفر اول ۶۵ امتیاز جلوتر است — فقط به‌خاطر کیفیت.
        و اگر نفر دوم همان روز <b>۳ کارت</b> را به «مسلط» برساند،
        <span class="mono">۳ × ۲۵ = ۷۵</span> امتیاز می‌گیرد و با ۳۱۴ از نفر اول جلو می‌زند.
        این عمدی است: مسلط شدن از سرعت و از دقتِ یک روز مهم‌تر است.</p>

        <p>دو ستون «دقت کارت» و «دقت تست» در جدول، همان دقتی است که در ضریب کیفیت به کار می‌رود،
        تا معلوم باشد امتیاز هر نفر از کجا آمده.</p>

        <div class="clrbox">
          <div class="label" style="margin:0 0 4px">رنگ درصدها یعنی چه؟</div>
          <div class="capt" style="margin:0 0 11px">
            رنگ فقط برای این است که با یک نگاه بفهمید کجا خوب بوده و کجا نه. مرزها ثابت‌اند:
          </div>
          <div class="clrrow">
            <span class="hi">۸۰٪ و بالاتر</span>
            <span class="ct">خوب — بیشترشان را درست زده‌اید</span>
          </div>
          <div class="clrrow">
            <span class="mid">۶۰٪ تا ۷۹٪</span>
            <span class="ct">متوسط — جای بهتر شدن دارد</span>
          </div>
          <div class="clrrow">
            <span class="lo">زیر ۶۰٪</span>
            <span class="ct">ضعیف — بیش از یک‌سوم را اشتباه زده‌اید</span>
          </div>
          <div class="clrrow">
            <span class="na">—</span>
            <span class="ct">هنوز کاری در این ستون انجام نشده</span>
          </div>
          <div class="capt" style="margin:12px 0 0">
            <b>همین رنگ‌ها در تب «فیدبک و تسلط» هم به کار می‌روند</b>، آنجا برای وضعیت کارت‌ها
            نه برای درصد — با یک رنگ بیشتر:
          </div>
          <div class="clrrow" style="margin-top:8px">
            <span class="lo">ضعیف</span><span class="ct">مرور شده ولی دقتش پایین بوده</span>
          </div>
          <div class="clrrow">
            <span class="mid">در حال یادگیری</span><span class="ct">تازه شروع شده، هنوز قضاوتی نمی‌شود</span>
          </div>
          <div class="clrrow">
            <span class="fam">آشنا</span><span class="ct">بازه‌ی مرورش بالای یک هفته رفته</span>
          </div>
          <div class="clrrow">
            <span class="hi">مسلط</span><span class="ct">بازه‌ی مرورش به ۲۱ روز رسیده</span>
          </div>
          <div class="capt" style="margin:10px 0 0">
            قاعده‌ی کلی همه‌جا یکی است: قرمز یعنی نقطه‌ی ضعف، طلایی یعنی در حال کار،
            آبی یعنی جا افتاده، سبز یعنی از این بابت خیالتان راحت.
          </div>
        </div>
      </div>`:""}
    </div>
`;
}
$("#tab-rank").addEventListener("click",e=>{
  const p=e.target.closest("[data-d]");
  if(p){LB.days=+p.dataset.d;renderBoard();return}
  if(e.target.closest("#lbHelpBtn")){LB.help=!LB.help;renderBoard()}
});
$("#tab-rank").addEventListener("change",e=>{
  if(e.target.id==="lbMon"&&e.target.value){LB.days=+e.target.value;renderBoard()}
});

/* ---------------- شروع ---------------- */
YEARS.slice().reverse().forEach(y=>{
  const o=document.createElement("option");o.value=y;o.textContent=fa(y);$("#year").appendChild(o);
  ["fromY","toY"].forEach(id=>{const p=document.createElement("option");p.value=y;p.textContent=fa(y);$("#"+id).appendChild(p)});
});
YEARS.slice().reverse().forEach(y=>{
  const o=document.createElement("option");o.value=y;o.textContent=fa(y);$("#rYear").appendChild(o);
  const o2=document.createElement("option");o2.value=y;o2.textContent=fa(y);$("#dyear").appendChild(o2);
  const o3=document.createElement("option");o3.value=y;o3.textContent=fa(y);$("#syear").appendChild(o3);
});
setRange(YEARS[0],YEARS[YEARS.length-1]);
renderTests();render();renderStar();updModeHint();
/* هم تب درست را باز می‌کند هم ناوبری را می‌کشد.
   navTab اول «rank» است، پس صفحه‌ی اول داشبورد است.
   اگر خواستید با کلمات باز شود، مقدار اولیه‌ی navTab را "words" کنید. */
// openTab(navTab);

/* ===== آدرس‌ها (router) =====
   پلتفرم تک‌صفحه‌ای است ولی هر صفحه آدرس خودش را دارد:
     /zaban                         داشبورد
     /zaban/<تب>                    words · deck · exam · crowd · …
     /zaban/word/<کلمه>             (کلمه‌ی دارای «/»: /zaban/word?w=…)
     /zaban/test/<سال>/<رشته>/<شماره>             رشته: ce · it · cs
     /zaban/text/<سال>/<رشته>/<cloze|passage>/<شماره‌ی پسیج>
   سرور برای همه همان فایل را می‌دهد (routes/web.php). آدرس‌های قدیمی «#words» هم باز می‌شوند. */
applyRoute(true);
window.addEventListener("popstate", () => applyRoute(false));

updAnnBadge();

/* خط رشته‌ها بالای صفحه: وضعیت واقعی هر رشته — خریده، دمو، یا لینک خرید.
   راه خرید رشته‌ی تازه همیشه جلوی چشم است، نه فقط ته پروفایل. */
(function(){
  const owned=window.OWNED_EXAMS||[], demo=((window.ME_DEMO||{}).exams)||[];
  const tag=(cls,t)=>`<span class="mjs ${cls}">${t}</span>`;
  document.querySelectorAll(".majors .mj[data-exam]").forEach(el=>{
    const c=el.dataset.exam;
    /* خود اسم دست‌نخورده می‌ماند؛ اسم و برچسب‌ها داخل یک پوشش کنار هم */
    const w=document.createElement(owned.includes(c)?"span":"a");
    w.className="mjw";
    if(!owned.includes(c)){w.href="/buy?exam="+c; w.title="خرید "+el.textContent}
    el.replaceWith(w); w.appendChild(el);
    w.insertAdjacentHTML("beforeend", owned.includes(c) ? tag("own","✓")
      : (demo.includes(c)?tag("demo","دمو"):"")+tag("buy","خرید"));
  });
})();

/* ===== خروج از هدر ===== */
(function(){
  const b=$("#logoutBtn"); if(!b)return;
  b.addEventListener("click",()=>{ if(confirm("از حساب کاربری خارج می‌شوید؟"))ZABAN.logout(); });
})();

/* ===== سوییچر «پلتفرم‌ها» — طرح پلتفرم مرور؛ آدرس‌ها از سرور (config/platforms.php) =====
   پلتفرمی که آدرس ندارد کم‌رنگ و بی‌کلیک است (بدون برچسب). همه با ورود یکپارچه‌اند. */
(function(){
  const btn=$("#pswBtn"), pop=$("#pswPop"), P=window.PLATFORMS;
  if(!btn||!pop)return;
  if(!P||!Array.isArray(P.list)||!P.list.length){ btn.closest(".psw").hidden=true; return; }

  const PIC={
    grid:'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    lang:'<path d="M4 6h10M9 4v2c0 4-2 7-5 8.5M7 10c1 2.5 3 4.2 5.5 5M13.5 20l4-9 4 9M15 17.5h5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    note:'<path d="M5 4h9l5 5v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 4v5h5M8 13h7M8 17h5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
    exam:'<path d="M6 3h12a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8.5 9.5l1.5 1.5 3-3M8.5 15.5l1.5 1.5 3-3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    chart:'<path d="M4 20V10M10 20V4M16 20v-7M22 20H2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>'
  };
  const esc=t=>String(t==null?"":t).replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));
  const icon=n=>'<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"'+(n==="grid"?' fill="currentColor"':'')+'>'+(PIC[n]||PIC.grid)+'</svg>';
  const body=(p,tag)=>`<span class="pi">${icon(p.i)}</span><span><span class="pn">${esc(p.n)}${tag}</span><span class="pd">${esc(p.d)}</span></span>`;

  const cur=P.list.find(p=>p.key===P.current), rest=P.list.filter(p=>p.key!==P.current);
  let h="";
  if(cur)h+='<div class="h">هم‌اکنون در این پلتفرم هستید</div><a class="cur" role="menuitem" aria-current="page">'+body(cur,' <span class="tg here">اینجا</span>')+'</a><div class="sep"></div>';
  h+='<div class="h">رفتن به پلتفرم دیگر</div>';
  rest.forEach(p=>{
    /* آدرس بدون پیشوند («plan.konkurcomputer.ir/dashboard») ← https:// اضافه می‌شود.
       بعدش فقط http(s) پذیرفته است — چیزی مثل javascript: هرگز در href نمی‌نشیند. */
    let u=String(p.url||"").trim();
    if(u && !/^[a-z][a-z0-9+.-]*:/i.test(u) && !u.startsWith("//")) u="https://"+u;
    const ok=/^https?:\/\/[^\s]+$/i.test(u);
    h+= ok ? `<a role="menuitem" href="${esc(u)}">${body(p,"")}</a>`
           : `<span class="na" role="menuitem" aria-disabled="true">${body(p,"")}</span>`;
  });
  pop.innerHTML=h;

  btn.addEventListener("click",e=>{e.stopPropagation(); const o=pop.hidden; pop.hidden=!o; btn.setAttribute("aria-expanded",String(o));});
  pop.addEventListener("click",e=>e.stopPropagation());
  document.addEventListener("click",()=>{pop.hidden=true; btn.setAttribute("aria-expanded","false");});
  document.addEventListener("keydown",e=>{if(e.key==="Escape"){pop.hidden=true; btn.setAttribute("aria-expanded","false");}});
})();

/* نسخه‌ی نمایشی: محتوایی که سرور فرستاده فقط یک سال است — کاربر باید بداند */
(function(){
  const y=window.DEMO_YEAR, bar=$("#demoBar"); if(!bar||!y)return;
  const code=window.CONTENT_EXAM||"";
  const name={ce:"مهندسی کامپیوتر",it:"آی‌تی",cs:"علوم کامپیوتر"}[code]||"";
  bar.innerHTML=`<span><b>نسخه‌ی نمایشی</b> — همه‌ی امکانات، فقط برای کنکور ${fa(y)} ${name}.</span>
    <a href="/buy?exam=${code}">خرید پکیج کامل</a>`;
  bar.hidden=false;
})();

/* دکمه‌ی مدیریت فقط برای admin/manager/editor. لینک عمداً در کد است
   نه در HTML ثابت — کاربر عادی حتی در Source هم نباید ردی از آن ببیند
   جز همین سه کاراکتر شرط. */
if (window.IS_ADMIN) {
  const b = document.createElement('a');
  b.href = '/zaban-admin';
  b.className = 'dashbtn';
  b.style.cssText = 'text-decoration:none;background:#1b2430;color:#fff';
  b.textContent = '⚙ مدیریت';
  $('.topacts').insertBefore(b, $('#dashBtn'));
}

});  /* ← پایان ZABAN.boot */
