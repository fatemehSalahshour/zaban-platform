<!DOCTYPE html>
<html lang="fa" dir="rtl" >
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پلتفرم زبان کنکور ارشد — v69 (وصل به سرور)</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
<!-- پلتفرم زبان — نسخه v69 · 1405/06/01 · ساخت: نمایش بدون سرور -->
{{-- ظاهر پلتفرم در public/css/zaban.css. نسخه (?v=) خودکار از زمان تغییر فایل ساخته
     می‌شود؛ دیگر لازم نیست دستی عوض شود. --}}
<link rel="stylesheet" href="/css/zaban.css?v={{ filemtime(public_path('css/zaban.css')) }}">
</head>
<body>
<div id="errbar" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#7d1d1d;color:#fff;
  font-family:Vazirmatn,monospace;font-size:12.5px;padding:10px 14px;line-height:1.9;direction:ltr;text-align:left;max-height:40vh;overflow:auto"></div>
<script>
(function(){
  function show(msg){
    var b=document.getElementById("errbar");
    if(!b)return;
    b.style.display="block";
    b.textContent=(b.textContent?b.textContent+"\n":"")+msg;
  }
  window.addEventListener("error",function(e){
    show("JS ERROR: "+(e.message||"")+"  @ line "+(e.lineno||"?")+":"+(e.colno||"?")+
         (e.error&&e.error.stack?"\n"+e.error.stack.split("\n").slice(0,4).join("\n"):""));
  });
  window.addEventListener("unhandledrejection",function(e){
    show("PROMISE: "+((e.reason&&(e.reason.stack||e.reason.message))||e.reason));
  });
  /* دکمه‌ی کپی — تا بشود متن خطا را عیناً فرستاد به‌جای توصیف کردنش */
  document.addEventListener("DOMContentLoaded",function(){
    var b=document.getElementById("errbar");
    if(!b)return;
    b.addEventListener("click",function(){
      try{
        var t=document.createElement("textarea");
        t.value=b.textContent; document.body.appendChild(t);
        t.select(); document.execCommand("copy"); document.body.removeChild(t);
        var o=b.textContent; b.textContent="متن خطا کپی شد."; 
        setTimeout(function(){b.textContent=o},1200);
      }catch(_){}
    });
  });
  window.addEventListener("unhandledrejection",function(e){
    show("PROMISE ERROR: "+(e.reason&&e.reason.message?e.reason.message:e.reason));
  });
  window.__err=show;
})();
</script>
<div class="wrap">

<div id="demoBar" class="demobar" hidden></div>

<div class="topbar">
  <div class="brandrow">
    <div>
      <h1 class="brand">پلتفرم زبان کنکور ارشد</h1>
      <div class="majors">
        <span class="mj" data-exam="ce">مهندسی کامپیوتر</span><b>·</b>
        <span class="mj" data-exam="it">آی‌تی</span><b>·</b>
        <span class="mj" data-exam="cs">علوم کامپیوتر</span>
        <span class="mjy">۲۵ سال کنکور</span>
      </div>
    </div>
  </div>
  <div class="topacts">  
    <div class="psw">
      <button class="b" id="pswBtn" aria-expanded="false" aria-haspopup="true">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true">
          <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/>
          <rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
        </svg><span>پلتفرم‌ها</span>
      </button>
      <div class="pop" id="pswPop" role="menu" hidden></div>
    </div>
    <button class="dashbtn" id="dashBtn" data-tip="داشبورد، وضعیت امروز و رتبه‌بندی">
      <span class="gi">◆</span>داشبورد</button>
    <span class="themebtn">
      <button class="iconbtn" id="themeBtn" data-tip="تغییر ظاهر (تم)">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
          stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="4.2"/>
          <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
      </button>
      <div class="thememenu" id="themeMenu"></div>
    </span>
    <button class="iconbtn" data-go="announce" data-tip="اطلاعیه">🔔<span class="cnt" id="annCnt" hidden></span></button>
    <button class="iconbtn" data-go="reports" data-tip="گزارش‌های من">📋<span class="cnt" id="repCnt" hidden></span></button>
    <button class="iconbtn" data-go="profile" data-tip="پروفایل">👤</button>
    <button class="iconbtn" id="logoutBtn" data-tip="خروج از حساب" aria-label="خروج از حساب">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7"
        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M14 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 16l-4-4 4-4M6 12h10"/></svg>
    </button>
    <button class="deckbtn" id="startReview">مرور امروز (<span id="deckCount">۰</span>)</button>
  </div>
</div>

<div class="stickytop">
  <div class="nav1" id="navBar"></div>
</div>

<div id="tab-words">
  <div id="filterHost"></div>
  <div class="panel" id="filterPanel">
    <div class="row-f">
      <div class="search"><span style="color:var(--ink-3)">⌕</span><input id="q" placeholder="جستجوی کلمه یا معنی"></div>
      <select id="sort">
        <option value="freq">بیشترین تکرار</option>
        <option value="freq_asc">کمترین تکرار</option>
        <option value="recent">جدیدترین سال</option>
        <option value="oldest">قدیمی‌ترین سال</option>
        <option value="az">الفبا A→Z</option>
        <option value="za">الفبا Z→A</option>
      </select>
    </div>

    <div class="row-f" style="margin-top:13px;align-items:flex-end;gap:22px">
      <div>
        <div class="label">سطح <span style="font-weight:400">(چندتایی)</span></div>
        <div class="seg" id="levels">
          <button data-lvl="ساده">ساده</button><button data-lvl="متوسط">متوسط</button><button data-lvl="پیشرفته">پیشرفته</button>
        </div>
      </div>
      <div>
        <div class="label">بخش آزمون <span style="font-weight:400">(چندتایی)</span></div>
        <div class="seg" id="sections">
          <button data-sec="وکب">وکب</button><button data-sec="کلوز تست">کلوز تست</button><button data-sec="پسیج">پسیج</button>
        </div>
      </div>
    </div>

    <div class="label" style="margin-top:13px">آزمون</div>
    <div class="row-f">
      <select id="year"><option value="">همه سال‌ها</option></select>
      <select id="exam">
        <option value="">همه رشته‌ها</option>
        <option value="مهندسی کامپیوتر">مهندسی کامپیوتر</option>
        <option value="آی‌تی">آی‌تی</option>
        <option value="علوم کامپیوتر">علوم کامپیوتر</option>
      </select>
    </div>

    <div class="label" style="margin-top:13px">بازه‌ی سال</div>
    <div class="row-f">
      <select id="fromY"></select>
      <span style="font-size:13px;color:var(--ink-2)">تا</span>
      <select id="toY"></select>
      <button class="pk" data-rng="5">۵ سال اخیر</button>
      <button class="pk" data-rng="10">۱۰ سال اخیر</button>
      <button class="pk" data-rng="0">کل سال‌ها</button>
    </div>

    <div class="active-row" id="activeFilters" hidden></div>

    <div class="tblock" id="tblock">
      <div class="label" id="tlabel">شماره تست</div>
      <div class="tgroups" id="tests"></div>
    </div>
  </div>

  <div class="resbar">
    <div class="count"><b id="n">۰</b> کلمه با این فیلتر</div>
    <button class="ghost" id="addResults">افزودن نتایج به دک…</button>
  </div>
  <div class="list" id="list"></div>
  <div style="text-align:center;padding:14px"><button class="ghost" id="more" hidden>نمایش بیشتر</button></div>
</div>

<div id="tab-tests" hidden>
  <div id="testsHost"></div>
  <div class="resbar">
    <div class="count"><b id="tn">۰</b> تست با این فیلتر · روی هر تست بزنید تا سؤال و کلماتش باز شود</div>
    <select id="tSort">
      <option value="new">جدیدترین سال</option>
      <option value="old">قدیمی‌ترین سال</option>
    </select>
  </div>
  <div class="list" id="testList"></div>
  <div style="text-align:center;padding:14px"><button class="ghost" id="tMore" hidden>نمایش بیشتر</button></div>
</div>

<div id="tab-star" hidden>
  <div class="resbar"><div class="count">کلمات و تست‌هایی که کنار گذاشته‌اید — جدا از دک مرور</div>
    <button class="ghost" id="starToDeck">فرستادن همه به دک مرور…</button></div>
  <div class="panel">
    <div class="row-f">
      <div class="search"><span style="color:var(--ink-3)">⌕</span><input id="sq" placeholder="جستجو در منتخب‌ها"></div>
      <div class="seg" id="ssec">
        <button data-ssec="وکب">وکب</button><button data-ssec="کلوز تست">کلوز تست</button><button data-ssec="پسیج">پسیج</button>
      </div>
      <select id="syear"><option value="">همه سال‌ها</option></select>
      <select id="sexam"><option value="">همه رشته‌ها</option>
        <option value="مهندسی کامپیوتر">مهندسی کامپیوتر</option>
        <option value="آی‌تی">آی‌تی</option><option value="علوم کامپیوتر">علوم کامپیوتر</option></select>
    </div>
  </div>
  <div class="subtabs" id="ssub">
    <button data-ssub="w" class="on">کلمات (<b id="swn">۰</b>)</button>
    <button data-ssub="q">تست‌ها (<b id="sqn">۰</b>)</button>
  </div>
  <div class="list" id="starList"></div>
  <div class="list" id="starQList" hidden></div>
</div>

<div id="tab-read" hidden>
  <div class="panel">
    <div class="label">مطالعه‌ی ترتیبی کلمات کنکور — به ترتیب دفترچه</div>
    <div class="row-f">
      <select id="rYear"></select>
      <select id="rExam">
        <option value="مهندسی کامپیوتر">مهندسی کامپیوتر</option>
        <option value="آی‌تی">آی‌تی</option>
        <option value="علوم کامپیوتر">علوم کامپیوتر</option>
      </select>
      <button class="ghost" id="rReset">پاک کردن نشانه‌ی این آزمون</button>
    </div>
  </div>
  <div id="rBookmark"></div>
  <div class="list" id="readList"></div>
</div>

<div id="tab-deck" hidden>
  <div class="panel">
    <div class="label">حالت مرور</div>
    <div class="modes" id="modes">
      <button data-mode="en" class="on">انگلیسی ← فارسی</button>
      <button data-mode="fa">فارسی ← انگلیسی</button>
      <button data-mode="tests">تست‌ها</button>
      <button data-mode="all">همه — کلمه و تست</button>
    </div>
    <div class="capt" id="modeHint" style="margin:0 0 10px"></div>
    <button class="deckbtn" id="startReview2" style="width:100%">شروع مرور</button>
  </div>
  <div class="panel">
    <div class="row-f">
      <div class="search"><span style="color:var(--ink-3)">⌕</span><input id="dq" placeholder="جستجو در دک"></div>
      <div class="seg" id="dsec">
        <button data-dsec="وکب">وکب</button><button data-dsec="کلوز تست">کلوز تست</button><button data-dsec="پسیج">پسیج</button>
      </div>
      <select id="dyear"><option value="">همه سال‌ها</option></select>
      <select id="dexam"><option value="">همه رشته‌ها</option>
        <option value="مهندسی کامپیوتر">مهندسی کامپیوتر</option>
        <option value="آی‌تی">آی‌تی</option><option value="علوم کامپیوتر">علوم کامپیوتر</option></select>
      <button class="ghost" id="clearDeck" style="margin-right:auto">خالی کردن دک</button>
    </div>
  </div>
  <div class="subtabs" id="dsub">
    <button data-dsub="w" class="on">کلمات (<b id="dwn">۰</b>)</button>
    <button data-dsub="q">تست‌ها (<b id="dqn">۰</b>)</button>
  </div>
  <div class="list" id="deckList"></div>
  <div class="list" id="deckQList" hidden></div>
</div>

<div id="tab-stats" hidden>
  <div class="stats" id="statCards"></div>
  <div class="panel"><div class="label">پیشرفت بر اساس سطح</div><div id="lvlBars"></div></div>
  <div class="panel"><div class="label">کلمات مشکل‌دار — بیش از دو بار «یادم نبود»</div><div id="leech"></div></div>
</div>

<div id="tab-charts" hidden>
  <div class="panel">
    <div class="row-f">
      <select id="cExam">
        <option value="">همه رشته‌ها</option>
        <option value="مهندسی کامپیوتر">مهندسی کامپیوتر</option>
        <option value="آی‌تی">آی‌تی</option>
        <option value="علوم کامپیوتر">علوم کامپیوتر</option>
      </select>
      <select id="cFrom"></select>
      <span style="font-size:13px;color:var(--ink-2)">تا</span>
      <select id="cTo"></select>
      <div class="seg" id="cMode" style="margin-right:auto">
        <button data-m="abs" class="on">تعداد</button><button data-m="pct">درصد</button>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="ch-h">
      <div><div class="ch-t">ترکیب سطح کلمات در هر سال</div><div class="ch-s" id="c1sub"></div></div>
      <div class="legend"><span style="--c:#5f9c4c">ساده</span><span style="--c:#d99521">متوسط</span><span style="--c:#c05a35">پیشرفته</span></div>
    </div>
    <div id="chart1"></div>
  </div>

  <div class="panel">
    <div class="ch-h">
      <div><div class="ch-t">پرتکرارترین کلمات</div><div class="ch-s" id="c2sub"></div></div>
      <div class="row-f">
        <div class="seg" id="cMetric">
          <button data-m2="years" class="on">تعداد سال</button>
          <button data-m2="times">تعداد کل تکرار</button>
        </div>
        <select id="cTop">
          <option value="20">۲۰ کلمه اول</option>
          <option value="50">۵۰ کلمه اول</option>
          <option value="100">۱۰۰ کلمه اول</option>
          <option value="0">همه‌ی کلمات</option>
        </select>
        <button class="ghost" id="addTop">افزودن نمایش‌داده‌شده‌ها به دک…</button>
      </div>
    </div>
    <div id="chart2"></div>
  </div>
</div>

<div id="tab-crowd" hidden>
  <div class="note blue"><div>اینجا می‌بینید <b>بقیه‌ی داوطلب‌ها</b> روی کدام کلمات و تست‌ها گیر می‌کنند.
    اگر چیزی برای اکثریت سخت است، اشکال از شما نیست.</div></div>
  <div id="crowdBody"></div>
</div>

<div id="tab-balance" hidden>
  <div class="sechead"><h2>تعادل و پوشش<span class="sub">هر سال و رشته را چقدر کار کرده‌اید</span></h2></div>
  <div id="balBody"></div>
</div>

<div id="tab-reports" hidden>
  <div class="sechead"><h2>گزارش‌های من<span class="sub">اشکال‌هایی که گزارش کرده‌اید و پاسخ ما</span></h2></div>
  <div class="panel">
    <div class="row-f">
      <div class="search"><span style="color:var(--ink-3)">⌕</span>
        <input id="rpQ" placeholder="جستجو در متن گزارش‌ها"></div>
      <select id="rpKind" class="inp" style="width:auto">
        <option value="all">کلمه و تست</option>
        <option value="w">فقط کلمات</option>
        <option value="q">فقط تست‌ها</option>
      </select>
    </div>
    <div class="row-f" style="margin-top:13px;align-items:flex-end;gap:22px">
      <div><div class="label">وضعیت</div><div class="seg" id="rpStatus">
        <button class="on" data-v="all">همه</button>
        <button data-v="open">در انتظار بررسی</button>
        <button data-v="answered">پاسخ داده شده</button></div></div>
      <div><div class="label">موضوع</div><div class="seg amber" id="rpTopic">
        <button class="on" data-v="all">همه</button>
        <button data-v="اشتباه نگارشی">نگارشی</button>
        <button data-v="اشتباه علمی">علمی</button>
        <button data-v="گزینه اشتباه">گزینه</button>
        <button data-v="معنی نادرست">معنی</button>
        <button data-v="سایر">سایر</button></div></div>
    </div>
  </div>
  <div id="repMine"></div>
</div>

<div id="tab-announce" hidden>
  <div class="sechead"><h2>اطلاعیه‌ها<span class="sub">پیام‌های مدیریت برای همه‌ی داوطلب‌ها</span></h2></div>
  <div class="resbar"><div class="count"><b id="annCount">۰</b> اطلاعیه</div></div>
  <div id="annBody"></div>
</div>

<div id="tab-profile" hidden>
  <div class="sechead"><h2>پروفایل<span class="sub">اطلاعات شما برای صدور کارنامه و پشتیبانی استفاده می‌شود</span></h2></div>
  <div class="panel"><div class="fg">
    <div class="f"><label>نام و نام خانوادگی</label><input class="inp" id="pfName" disabled></div>
    <div class="f" id="pfMobileF" hidden><label>شماره موبایل</label><input class="inp en" id="pfMobile" disabled></div>
    <div class="f"><label>نام مستعار در رتبه‌بندی</label><input class="inp" id="pfNick" maxlength="30" placeholder="اگر خالی بماند، «بی‌نام» دیده می‌شوید"></div>
    <div class="f"><label>دانشگاه</label><input class="inp" id="pfUni" maxlength="150"></div>
    <div class="f"><label>معدل</label><input class="inp" id="pfGpa" inputmode="decimal" placeholder="مثلاً ۱۷٫۵"></div>
    <div class="f"><label>کارت تازه در روز
        <span class="capt" style="display:block;margin:0">بین ۵ تا ۱۰۰ · خالی = پیش‌فرض پلتفرم</span></label>
      <input class="inp en" id="pfNew" inputmode="numeric" placeholder="۲۰"></div>
    <div class="f"><label>سهمیه</label><select class="inp" id="pfQuota">
      <option value="">انتخاب نشده</option><option value="free">آزاد</option><option value="veteran">ایثارگر</option></select></div>
    <div class="f"><label>متقاضی چه کنکوری هستید؟</label><select class="inp" id="pfDegree">
      <option value="">انتخاب نشده</option><option value="msc">ارشد</option><option value="phd">دکتری</option></select></div>
    <div class="f"><label>متقاضی چه رشته‌ای هستید؟</label>
      <select class="inp" id="pfExam"><option value="ce">مهندسی کامپیوتر</option><option value="it">آی‌تی</option><option value="cs">علوم کامپیوتر</option></select></div>
    <div class="f" style="grid-column:1/-1">
      <label>نمایش در جدول رتبه‌بندی</label>
      <label class="rsw" style="margin-top:4px"><input type="checkbox" id="pfBoard">
        <span>نام مستعار من در رتبه‌بندی دیده شود</span></label></div>
  </div><div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px">
    <div style="display:flex;gap:8px">
      <button class="ghost" id="pfLogout" onclick="ZABAN.logout()">خروج از حساب</button>
      <a class="ghost" href="/buy" style="text-decoration:none">خرید و پرداخت‌ها</a>
    </div>
    <button class="solid gold" id="pfSave">ثبت اطلاعات</button></div></div>
</div>

<div id="tab-rank" hidden>
  <div class="dsec" style="margin-top:0"><h2>وضعیت امروز</h2>
    <span class="ds">آنچه همین حالا منتظر شماست</span>
    <button class="ghost act" id="kpiHelpBtn">این عددها یعنی چه؟</button></div>
  <div class="herorow">
    <div id="heroCard"></div>
    <div class="kpigrid" id="dashKpi"></div>
  </div>
  <div id="kpiHelp" hidden></div>

  <div id="planSlot"></div>
  <div class="panel" id="planHelpBox" hidden></div>
  <div class="panel" id="lbBody"></div>
  <div id="remindSlot"></div>
</div>

<div id="tab-fb" hidden><div id="fbBody"></div></div>

<div id="tab-exam" hidden>
  <div id="examHome"></div>
  <div id="examRun" hidden></div>
  <div id="examRes" hidden></div>
</div>

<div id="tab-predict" hidden>
  <div class="pr-note">
    این فهرست یک <b>برآورد آماری</b> بر اساس الگوی تکرار کلمات در ۲۵ سال گذشته است، نه فهرست قطعی کنکور پیش‌ رو.
    هیچ‌کس نمی‌تواند سؤال‌های سال آینده را از پیش بداند؛ آنچه اینجا می‌بینید کلماتی است که
    <b>بیشترین احتمال آماری تکرار</b> را بر پایه‌ی فراوانی، تازگی و ریتم تکرارشان دارند.
  </div>
  <div class="panel">
    <div class="row-f" style="margin-bottom:10px">
      <div class="label" style="margin:0 0 0 4px">مدل امتیازدهی</div>
      <div class="seg" id="prMode">
        <button data-m="bal" class="on">متوازن</button>
        <button data-m="freq">تکرارمحور</button>
        <button data-m="due">سررسیدمحور</button>
      </div>
      <select id="prExam" style="margin-right:auto">
        <option value="">همه‌ی رشته‌ها</option>
        <option>مهندسی کامپیوتر</option><option>آی‌تی</option><option>علوم کامپیوتر</option>
      </select>
      <select id="prTop"><option value="50">۵۰ کلمه</option><option value="100" selected>۱۰۰ کلمه</option><option value="200">۲۰۰ کلمه</option></select>
      <select id="prWin" data-tip="فقط دو شمارنده‌ی زیر و فیلترشان را عوض می‌کند؛ روی امتیاز و ترتیب فهرست هیچ اثری ندارد."></select>
      <button class="ghost brandbtn" id="prHelpBtn">این مدل‌ها یعنی چه؟</button>
    </div>
    <div class="row-f">
      <div class="label" style="margin:0 0 0 4px">سطح</div>
      <div class="seg" id="prLvl"><button>ساده</button><button>متوسط</button><button>پیشرفته</button></div>
      <div class="label" style="margin:0 0 0 4px">بخش</div>
      <div class="seg" id="prSec"><button>وکب</button><button>کلوز تست</button><button>پسیج</button></div>
      <button class="ghost" id="prAdd" style="margin-right:auto">افزودن این فهرست به دک مرور</button>
    </div>
  </div>
  <div class="panel" id="prHelpBox" hidden></div>
  <div class="stats" id="prStats"></div>
  <div id="prWrap"><div class="list" id="prList"></div></div>

</div>

<div class="scrim" id="noteM"><div class="sheet" style="max-width:520px;padding:20px">
  <button class="close" id="noteClose">×</button>
  <div style="font-size:16px;font-weight:700;padding-left:36px;margin-bottom:4px" id="noteTitle"></div>
  <div class="capt" style="margin:0 0 12px" id="noteSub"></div>
  <div id="noteBody"><textarea id="noteTa" placeholder="هرچه می‌خواهی برای خودت اینجا بنویس…"></textarea></div>
  <div class="row-f" style="margin-top:12px">
    <button class="deckbtn" id="noteSave" style="flex:1">ذخیره‌ی یادداشت</button>
    <button class="ghost" id="noteDel">حذف یادداشت</button>
  </div>
</div></div>
<div class="scrim" id="repM"><div class="sheet repsheet">
  <div class="rephead">
    <button class="close" id="repClose">×</button>
    <div class="rt" id="repTitle">ثبت گزارش</div>
    <div class="rs" id="repSub"></div>
  </div>

  <div class="repbody">
    <div id="repThread"></div>

    <div id="repForm">
      <div class="lbl3">موضوع گزارش <b>*</b></div>
      <div class="repchips" id="repTopics">
        <button data-topic="اشتباه نگارشی">اشتباه نگارشی</button>
        <button data-topic="اشتباه علمی">اشتباه علمی</button>
        <button data-topic="گزینه اشتباه">گزینه اشتباه</button>
        <button data-topic="معنی نادرست">معنی نادرست</button>
        <button data-topic="فاقد جواب تشریحی">فاقد جواب تشریحی</button>
        <button data-topic="سایر">سایر</button>
      </div>

      <div class="lbl3">پیام شما <b>*</b></div>
      <textarea id="repTa" class="repta"
        placeholder="دقیقاً بگویید کجا و چه اشکالی هست. مثلاً: «معنی گزینه‌ی ۳ اشتباه است، باید فلان باشد.»"></textarea>
      <div class="repcount"><span id="repCount">۰</span> نویسه</div>
    </div>

    <div id="repReply" hidden>
      <div class="lbl3">پاسخ شما به این گفت‌وگو</div>
      <textarea id="repRt" class="repta" placeholder="اگر پاسخ ما مشکل را حل نکرد، اینجا ادامه بدهید"></textarea>
    </div>
  </div>

  <div class="repfoot">
    <div id="repFootNew">
      <button class="ghost" id="repCancel">بیخیال</button>
      <button class="deckbtn" id="repSend">✓ ثبت گزارش</button>
    </div>
    <div id="repFootReply" hidden>
      <button class="ghost" id="repNew">گزارش تازه</button>
      <button class="deckbtn" id="repReplyBtn">↩ ارسال پاسخ</button>
    </div>
  </div>
</div></div>
<div class="scrim" id="cbM"><div class="sheet repsheet" style="max-width:860px">
  <div class="rephead">
    <button class="close" id="cbClose">×</button>
    <div class="rt" id="cbTitle">کارت‌ها</div>
    <div class="rs" style="direction:rtl" id="cbSub"></div>
    <div id="cbFilters" style="margin-top:12px;display:flex;flex-direction:column;gap:9px"></div>
  </div>
  <div class="repbody" id="cbBody"></div>
  <div class="repfoot"><div>
    <button class="deckbtn" id="cbStart">شروع مرور</button>
    <div id="cbPager" style="display:flex;gap:9px;align-items:center;margin-right:auto"></div>
  </div></div>
</div></div>

<div class="scrim" id="admM"><div class="sheet repsheet" style="max-width:720px">
  <div class="rephead" style="background:linear-gradient(180deg,#e9eef5,#fff)">
    <button class="close" id="admClose">×</button>
    <div class="rt">پنل مدیریت — گزارش‌های کاربران</div>
    <div class="rs" style="direction:rtl">اینجا جای بخش مدیریت سایت است. در نسخه‌ی واقعی
      گزارش همه‌ی کاربران می‌آید؛ در حالت نمایش فقط گزارش‌های خودتان.</div>
  </div>
  <div class="repbody" id="admBody"></div>
  <div class="repfoot"><div>
    <button class="ghost" id="admClear">پاک کردن همه‌ی گزارش‌ها</button>
    <span class="capt" style="margin:0 auto 0 0" id="admCount"></span>
  </div></div>
</div></div>
<div class="toast" id="toast"></div>
<div class="scrim" id="askM"><div class="sheet" style="max-width:430px;padding:22px">
  <div id="askMsg" style="font-size:15.5px;line-height:2;margin-bottom:16px"></div>
  <div class="row-f"><button class="deckbtn" id="askYes" style="flex:1">بله</button>
    <button class="ghost" id="askNo">انصراف</button></div>
</div></div>
<div class="menu" id="menu" hidden></div>
<div class="scrim" id="detail"><div class="sheet"><button class="close" id="closeDetail">×</button><div id="detailBody"></div></div></div>

<div class="scrim" id="qview"><div class="sheet"><button class="close" id="closeQ">×</button><div id="qBody"></div></div></div>

<div class="scrim" id="review"><div class="sheet" style="max-width:480px;padding:20px">
  <button class="close" id="closeReview">×</button>
  <div class="meta" style="padding-left:36px"><span id="rIdx"></span><span style="color:var(--ink-3)">دک زبان</span></div>
  <div class="flipwrap"><div class="card" id="rCard"></div></div>
  <button class="showbtn" id="rShow">مشاهده‌ی پاسخ</button>
  <div class="opts" id="rOpts" hidden></div>
  <div class="rate" id="rRate" hidden>
    <button class="r1" data-r="1">یادم نبود<i></i></button><button class="r2" data-r="2">سخت بود<i></i></button>
    <button class="r3" data-r="3">یادم بود<i></i></button><button class="r4" data-r="4">ساده بود<i></i></button>
  </div>
  <div class="progress"><i id="rBar" style="width:0%"></i></div>
</div></div>

</div>

<script src="/js/app-bootstrap.js?v={{ filemtime(public_path('js/app-bootstrap.js')) }}"></script>
{{-- کد صفحه در public/js/zaban/app.js (جدا کردن کد، قدم ۳). نسخه خودکار از زمان
     تغییر فایل؛ مرورگر آن را جدا از HTML کش می‌کند. --}}
<script src="/js/zaban/app.js?v={{ filemtime(public_path('js/zaban/app.js')) }}"></script>
</body>
</html>


