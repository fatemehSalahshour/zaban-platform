
(function(){
  /* تاریخ کنکور ارشد ۱۴۰۶ — ۱۶ اردیبهشت ۱۴۰۶ (در صورت تغییر، فقط همین خط) */
  /* تاریخ کنکور از پنل مدیریت می‌آید (window.LANDING_EXAM)؛ عدد ثابت فقط پشتیبان است */
  var EXAM = window.LANDING_EXAM ? new Date(String(window.LANDING_EXAM).replace(' ','T'))
                                 : new Date(2027, 4, 6);
  var fa = function(n){ return Number(n).toLocaleString('fa-IR'); };
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var today = new Date(); today.setHours(0,0,0,0);
  var DAYS = Math.max(0, Math.round((EXAM - today) / 864e5));
  ['heroDays','finalDays','mbarDays'].forEach(function(id){ var e=document.getElementById(id); if(e) e.textContent = fa(DAYS); });

  /* داده‌ی نمونه از پلتفرم: [کلمه، معنی، نقش، سطح، نوار ۲۵ سال از ۱۳۸۱] */
  var W = [["convenient", "مناسب، راحت", "adjective", 0, "1111111111111111111111111"], ["paramount", "بسیار مهم، در اولویت اول", "adjective", 2, "1111111111111111111111111"], ["haphazard", "بی‌نظم، تصادفی", "adjective", 2, "1111111111111111111111111"], ["serendipity", "کشف اتفاقی و خوشایند", "noun", 2, "1110111111111111011111111"], ["reckless", "بی‌احتیاط، بی‌پروا", "adjective", 1, "1111111111111111111111111"], ["vigorous", "پرانرژی، قوی", "adjective", 1, "1111111111111111111111111"], ["tranquility", "آرامش", "noun", 2, "1101111111110101110111111"], ["invulnerable", "آسیب‌ناپذیر", "adjective", 2, "1011111101001111111111111"], ["euphoria", "سرخوشی شدید", "noun", 2, "1110110111111110111111101"], ["ambiguous", "مبهم، دوپهلو", "adjective", 2, "0101111111111110011111001"], ["confront", "روبه‌رو شدن با، مواجه شدن", "verb", 2, "1111111111111111111111111"], ["irrelevant", "نامرتبط، بی‌ربط", "adjective", 1, "1111111111111111111111111"], ["fascinating", "جذاب، مسحورکننده", "adjective", 1, "1111111111111111111111111"], ["permanent", "دائمی، همیشگی", "adjective", 0, "1111111111111111111111101"], ["demonstrate", "نشان دادن، اثبات کردن", "verb", 1, "1111111111111110111101111"], ["guise", "ظاهر، پوشش", "noun", 2, "1111011111011111110111111"], ["abundant", "فراوان، وفور", "adjective", 1, "1111111110001111111111111"], ["adjacent", "مجاور، همسایه", "adjective", 1, "1011111111101111010111111"], ["contemplate", "تعمق کردن، در نظر گرفتن", "verb", 2, "1111111111011101101111011"], ["arbitrary", "دلبخواهی، اختیاری", "adjective", 2, "0111101101110110111101010"], ["consecutive", "پیاپی، متوالی", "adjective", 2, "1101110010111100111110100"], ["controversial", "بحث‌برانگیز", "adjective", 1, "0010011001010101001110001"], ["conceal", "پنهان کردن", "verb", 1, "1111101010111111001100110"], ["anticipate", "پیش‌بینی کردن، انتظار داشتن", "verb", 1, "1011111110011110101110100"]];
  var LV = ['ساده','متوسط','پیشرفته'], LVC = ['#3f9c7f','#d0a94a','#d4785a'];

  /* ---------- لوحه‌ی hero ---------- */
  var heroSet = ['paramount','serendipity','haphazard','convenient','tranquility','invulnerable','reckless','euphoria'];
  var H = heroSet.map(function(k){ return W.filter(function(w){return w[0]===k;})[0]; }).filter(Boolean);
  var stage = document.getElementById('stage'), ribbon = document.getElementById('ribbon'), dots = document.getElementById('dots');
  for (var i=0;i<25;i++){ ribbon.appendChild(document.createElement('span')); }
  var cells = ribbon.children, cur = -1, timer = null;
  H.forEach(function(w,i){
    var b = document.createElement('button'); b.type='button'; b.setAttribute('aria-label', w[0]);
    b.onclick = function(){ show(i); restart(); }; dots.appendChild(b);
  });
  function show(i){
    if (i===cur) return;
    var w = H[i], old = stage.querySelector('.word');
    var el = document.createElement('div'); el.className='word out';
    el.innerHTML = '<span class="w" lang="en">'+w[0]+'</span><span class="pos" lang="en">'+w[2]+'</span>';
    stage.appendChild(el);
    requestAnimationFrame(function(){ requestAnimationFrame(function(){ el.classList.remove('out'); }); });
    if (old){ old.classList.add('out'); setTimeout(function(){ old.remove(); }, 700); }
    var m = document.getElementById('mean'); m.style.opacity=0;
    setTimeout(function(){ m.textContent = w[1]; m.style.opacity=1; }, reduce?0:260);
    document.getElementById('lvlTxt').textContent = LV[w[3]];
    document.getElementById('lvlDot').style.background = LVC[w[3]];
    var s = w[4], n = 0;
    for (var k=0;k<25;k++){ (function(k){
      var on = s[k]==='1'; if(on) n++;
      if (reduce) cells[k].className = on?'on':'';
      else { cells[k].className=''; setTimeout(function(){ cells[k].className = on?'on':''; }, 120 + k*28); }
    })(k); }
    document.getElementById('yrs').textContent = fa(n);
    [].forEach.call(dots.children, function(d,j){ d.classList.toggle('on', j===i); });
    cur = i;
  }
  function restart(){ if (reduce) return; clearInterval(timer); timer = setInterval(function(){ show((cur+1)%H.length); }, 4600); }
  var plate = document.getElementById('plate');
  plate.addEventListener('mouseenter', function(){ clearInterval(timer); });
  plate.addEventListener('mouseleave', restart);
  show(0); restart();

  /* ---------- بافت تکرار ---------- */
  var loomSet = ['convenient','confront','paramount','vigorous','permanent','ease','serendipity','demonstrate','abundant','guise','euphoria','contemplate','ambiguous','allocate'];
  var loom = document.getElementById('loom');
  loomSet.forEach(function(k){
    var w = W.filter(function(x){return x[0]===k;})[0]; if(!w) return;
    var lw = document.createElement('div'); lw.className='lw'; lw.textContent=w[0]; lw.title=w[1]; lw.lang='en';
    var lr = document.createElement('div'); lr.className='lr';
    for (var j=0;j<25;j++){ var c=document.createElement('i'); if(w[4][j]==='1') c.className='on'; lr.appendChild(c); }
    loom.appendChild(lw); loom.appendChild(lr);
  });

  /* ---------- کارت مرور ---------- */
  var deck = ['paramount','reckless','ambiguous','adequate','conceal','anticipate'].map(function(k){ return W.filter(function(x){return x[0]===k;})[0]; }).filter(Boolean);
  var ci = 0, dir = 'en', done = 0, card = document.getElementById('card');
  function paint(){
    var w = deck[ci % deck.length], q = document.getElementById('cq'), a = document.getElementById('ca');
    card.classList.remove('shown');
    if (dir==='en'){ q.textContent=w[0]; q.className='q en'; q.lang='en'; a.textContent=w[1]; a.className='a fa'; a.lang='fa'; }
    else { q.textContent=w[1]; q.className='q fa'; q.lang='fa'; a.textContent=w[0]; a.className='a en'; a.lang='en'; }
    document.getElementById('cmeta').textContent = LV[w[3]] + ' · در ' + fa(w[4].split('1').length-1) + ' کنکور از ۲۵';
    document.getElementById('cardCount').textContent = 'کارت ' + fa(ci % deck.length + 1) + ' از ' + fa(deck.length);
  }
  document.getElementById('reveal').onclick = function(){ card.classList.add('shown'); };
  [].forEach.call(card.querySelectorAll('.grades button'), function(b){ b.onclick = function(){ done++; document.getElementById('done').textContent = fa(done); ci++; paint(); document.getElementById('reveal').focus(); }; });
  [].forEach.call(card.querySelectorAll('.seg button'), function(b){ b.onclick = function(){
    dir = b.dataset.dir; [].forEach.call(card.querySelectorAll('.seg button'), function(x){ x.setAttribute('aria-pressed', x===b); }); paint(); }; });
  paint();

  /* ---------- پیش‌بینی ---------- */
  var P = [
    [['convenient',97],['confront',95],['paramount',91],['irrelevant',89],['reckless',86],['vigorous',84],['haphazard',82],['expose',79]],
    [['convenient',99],['confront',94],['reckless',90],['irrelevant',88],['paramount',85],['expose',84],['haphazard',80],['vigorous',79]],
    [['serendipity',93],['guise',90],['tranquility',87],['permanent',85],['euphoria',83],['invulnerable',80],['demonstrate',78],['ease',76]]
  ];
  var plist = document.getElementById('plist');
  function mean(k){ var w=W.filter(function(x){return x[0]===k;})[0]; return w?w[1]:''; }
  function drawP(m){
    plist.innerHTML = P[m].map(function(r,i){
      return '<li><span class="r">'+fa(i+1)+'</span><span class="pw"><span class="en" lang="en">'+r[0]+'</span><small>'+mean(r[0])+'</small></span><span class="bar"><i style="width:0"></i></span><span class="s">'+fa(r[1])+'</span></li>';
    }).join('');
    requestAnimationFrame(function(){ [].forEach.call(plist.querySelectorAll('.bar i'), function(b,i){ b.style.width = P[m][i][1]+'%'; }); });
  }
  [].forEach.call(document.querySelectorAll('.board .seg button'), function(b){ b.onclick = function(){
    [].forEach.call(document.querySelectorAll('.board .seg button'), function(x){ x.setAttribute('aria-pressed', x===b); }); drawP(+b.dataset.m); }; });
  drawP(0);

  /* ---------- پاسخ‌برگ ---------- */
  var bub = document.getElementById('bubbles'), tel = document.getElementById('timer'), msg = document.getElementById('sheetMsg');
  var T0 = 30*60, t = T0, tick = null, ans = {};
  function clock(){ var m=Math.floor(t/60), s=t%60; tel.textContent = fa(m)+':'+(s<10?'۰':'')+fa(s); }
  function status(){ var n = Object.keys(ans).length;
    msg.innerHTML = n ? 'پاسخ‌داده: <b>'+fa(n)+'</b> از ۱۵ سؤال نمونه. در پلتفرم، کل ۲۵ سؤال با متن کامل می‌آید.' : 'روی گزینه‌ها بزن؛ زمان‌سنج با اولین پاسخ راه می‌افتد.'; }
  for (var q=1;q<=15;q++){
    var h = '<div><span>'+fa(q)+'</span>';
    for (var o=1;o<=4;o++) h += '<button type="button" data-q="'+q+'" data-o="'+o+'" aria-label="سؤال '+fa(q)+'، گزینه‌ی '+fa(o)+'" aria-pressed="false">'+fa(o)+'</button>';
    bub.insertAdjacentHTML('beforeend', h+'</div>');
  }
  bub.addEventListener('click', function(e){
    var b = e.target.closest('button'); if(!b) return;
    var q = b.dataset.q, row = b.parentNode.querySelectorAll('button'), was = b.classList.contains('f');
    [].forEach.call(row, function(x){ x.classList.remove('f'); x.setAttribute('aria-pressed','false'); });
    if (was) delete ans[q]; else { b.classList.add('f'); b.setAttribute('aria-pressed','true'); ans[q]=+b.dataset.o; }
    if (!tick && !reduce) tick = setInterval(function(){ if (t>0){ t--; clock(); } else { clearInterval(tick); msg.textContent='وقت تمام شد. در پلتفرم اینجا کارنامه و پاسخ‌برگ کامل می‌آید.'; } }, 1000);
    status();
  });
  document.getElementById('sheetReset').onclick = function(){ clearInterval(tick); tick=null; t=T0; ans={}; clock();
    [].forEach.call(bub.querySelectorAll('button'), function(x){ x.classList.remove('f'); x.setAttribute('aria-pressed','false'); }); status(); };
  clock();

  /* ---------- ماشین‌حساب ---------- */
  var wn = document.getElementById('wn'), loads = document.getElementById('loads');
  var offs = [0,30,60,90,120];
  loads.innerHTML = offs.map(function(o,i){ return '<div class="load'+(i===0?' now':'')+'"><span class="v"></span><span class="bar"></span></div>'; }).join('');
  function calc(){
    var n = +wn.value; document.getElementById('wnv').textContent = fa(n)+' کلمه';
    /* ده روز آخر برای جمع‌بندی کنار گذاشته می‌شود؛ عدد = کلمه‌ی تازه در هفته */
    var per = offs.map(function(o){ var d = Math.max(7, DAYS - o - 10); return Math.ceil(n/d*7); });
    var max = Math.max.apply(null, per);
    [].forEach.call(loads.children, function(el,i){ el.querySelector('.v').textContent = fa(per[i]); el.querySelector('.bar').style.height = Math.max(6, per[i]/max*150)+'px'; });
    var k = per[4]/per[0];
    document.getElementById('calcOut').innerHTML = 'اگر امروز شروع کنی، هفته‌ای <b>'+fa(per[0])+' کلمه‌ی تازه</b> کافی است. چهار ماه دیگر، همین کار هفته‌ای <b>'+fa(per[4])+' کلمه</b> می‌خواهد؛ '+fa(Math.round(k*10)/10)+' برابر، آن هم در شلوغ‌ترین ماه‌های درس‌های تخصصی. ده روز آخر برای جمع‌بندی کنار گذاشته شده.';
  }
  wn.addEventListener('input', calc); calc();

  /* ---------- اسکرول لینک‌های داخلی (در iframe و پیش‌نمایش‌ها هم کار کند) ---------- */
  document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('a[href^="#"]'); if (!a) return;
    var id = a.getAttribute('href').slice(1); if (!id) return;
    var el = document.getElementById(id); if (!el) return;
    e.preventDefault();
    var y = el.getBoundingClientRect().top + window.pageYOffset - (id==='top' ? el.getBoundingClientRect().top + window.pageYOffset : document.getElementById('nav').offsetHeight + 8);
    window.scrollTo({ top: Math.max(0,y), behavior: reduce ? 'auto' : 'smooth' });
    try { history.replaceState(null, '', '#'+id); } catch(err){}
  });

  /* ---------- ناوبری و نوار موبایل ---------- */
  var nav = document.getElementById('nav'), mbar = document.getElementById('mbar'), offer = document.getElementById('offer');
  function onScroll(){
    var y = window.scrollY; nav.classList.toggle('solid', y > 40);
    var r = offer.getBoundingClientRect();
    mbar.classList.toggle('show', y > window.innerHeight*0.9 && !(r.top < window.innerHeight && r.bottom > 0));
  }
  window.addEventListener('scroll', onScroll, {passive:true}); onScroll();
})();
