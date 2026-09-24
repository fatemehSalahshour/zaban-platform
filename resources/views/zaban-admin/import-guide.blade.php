@extends('zaban-admin.layout')
@section('title', 'راهنمای ورود اکسل')

@section('body')
<style>
  .ig h2{font-size:17px;margin:26px 0 10px;color:#16324f}
  .ig h3{font-size:15px;margin:18px 0 8px;color:#2b4a6f}
  .ig p,.ig li{line-height:2.1;color:#3a4654}
  .ig ol,.ig ul{padding-right:22px;margin:8px 0}
  .ig code{background:#eef2f7;padding:2px 6px;border-radius:5px;font-family:Consolas,monospace;
    font-size:13px;direction:ltr;display:inline-block}
  .ig pre{background:#16324f;color:#e8eef5;padding:12px 14px;border-radius:9px;direction:ltr;
    text-align:left;overflow-x:auto;font-family:Consolas,monospace;font-size:13px;line-height:1.8;
    margin:8px 0;white-space:pre}
  .ig .warn{background:#fdf3e7;border-right:4px solid #d98324;padding:12px 16px;border-radius:8px;margin:14px 0}
  .ig .ok{background:#eaf5ef;border-right:4px solid #2e8b62;padding:12px 16px;border-radius:8px;margin:14px 0}
  .ig table{width:100%;border-collapse:collapse;margin:10px 0;font-size:14px}
  .ig th,.ig td{border:1px solid #dde4ec;padding:8px 10px;text-align:right}
  .ig th{background:#f4f7fa;font-weight:600}
  .ig .step{background:#fff;border:1px solid #e3e9f0;border-radius:11px;padding:16px 18px;margin:12px 0}
  .ig .step b{color:#16324f}
</style>

<div class="ig">

<h1 style="font-size:21px;margin-bottom:6px">راهنمای ورود اکسل‌ها</h1>
<p style="color:#6b7787">بانک کلمات از فایل‌های اکسل تیم محتوا وارد می‌شود، نه از پلتفرم آزمون.
این صفحه فقط راهنماست و هیچ دکمه‌ای اینجا کاری روی دیتابیس انجام نمی‌دهد.</p>

<div class="ok">
  <b>دو راه ورود داده، دو کار جدا:</b>
  <ul style="margin-top:6px">
    <li><b>سؤال‌ها</b> از پلتفرم آزمون می‌آیند — با دکمه‌ی «همگام‌سازی سؤال‌ها» در همین پنل.</li>
    <li><b>کلمه‌ها، معنی‌ها و جایگاه هر کلمه در هر سؤال</b> از اکسل می‌آیند — با مراحل این صفحه.</li>
  </ul>
</div>

<h2>۱) فایل‌ها را کجا بگذارید</h2>
<p>با WinSCP به سرور وصل شوید و فایل‌های CSV را در این پوشه بگذارید:</p>
<pre>/var/www/language/storage/app/zaban/</pre>
<p>اگر پوشه نبود، یک بار در ترمینال سرور بسازیدش:</p>
<pre>mkdir -p /var/www/language/storage/app/zaban
chown -R www-data:www-data /var/www/language/storage/app/zaban</pre>

<div class="warn">
  <b>فایل باید CSV با کدگذاری UTF-8 باشد، نه xlsx.</b>
  در اکسل: <i>Save As</i> ← نوع فایل <i>CSV UTF-8 (Comma delimited)</i>.
  اگر معمولی ذخیره شود، فارسی‌ها به هم می‌ریزند.
</div>

<h2>۲) ستون‌های هر فایل</h2>

<h3>الف) کلمات — <code>words.csv</code></h3>
<table>
  <tr><th>ستون</th><th>توضیح</th></tr>
  <tr><td>Word</td><td>خود کلمه (انگلیسی). اگر خالی باشد ردیف رد می‌شود.</td></tr>
  <tr><td>Persian Meaning</td><td>معنی فارسی. چند معنی را با <code>/</code> جدا کنید.</td></tr>
  <tr><td>Type</td><td>نقش کلمه: noun، verb، adjective، phrase و…</td></tr>
  <tr><td>Verb Forms</td><td>سه شکل فعل با <code>/</code>: مثل <code>go / went / gone</code></td></tr>
  <tr><td>Level</td><td>سطح: ساده، متوسط، پیشرفته. خالی باشد «متوسط» می‌شود.</td></tr>
  <tr><td>Year</td><td>سال کنکوری که کلمه در آن آمده</td></tr>
  <tr><td>Exam</td><td>رشته: CE، IT یا CS</td></tr>
  <tr><td>Section</td><td>بخش: Vocab، Cloze یا Passage</td></tr>
</table>
<div class="ok">
  <b>معنی‌ها ادغام می‌شوند، بازنویسی نمی‌شوند.</b> اگر کلمه‌ای از قبل معنی داشته باشد و
  در فایل تازه معنی دیگری بیاید، هر دو کنار هم می‌مانند. مثلاً <code>that</code> که در کلوز
  موصولی است و در پسیج اشاره‌ای.
</div>

<h3>ب) بخش‌ها — <code>exam_sections.csv</code></h3>
<p>می‌گوید در هر دفترچه، هر بخش از کدام شماره سؤال تا کدام شماره است.</p>
<table>
  <tr><th>ستون</th><th>توضیح</th></tr>
  <tr><td>year</td><td>سال کنکور</td></tr>
  <tr><td>exam</td><td>رشته: ce، it یا cs</td></tr>
  <tr><td>section</td><td>vocab، cloze یا passage</td></tr>
  <tr><td>passage_number</td><td>شماره‌ی متن (برای کلوز و پسیج)</td></tr>
  <tr><td>from_question / to_question</td><td>از شماره‌ی چند تا چند</td></tr>
  <tr><td>sort_order</td><td>ترتیب نمایش بخش</td></tr>
</table>

<h3>ج) متن‌ها — <code>texts.csv</code></h3>
<table>
  <tr><th>ستون</th><th>توضیح</th></tr>
  <tr><td>year / exam / section / passage_number</td><td>مشخص می‌کند متن مال کدام بخش است</td></tr>
  <tr><td>title</td><td>عنوان متن</td></tr>
  <tr><td>body</td><td>متن انگلیسی</td></tr>
  <tr><td>body_fa</td><td>ترجمه‌ی فارسی</td></tr>
</table>

<h3>د) سؤال‌ها — <code>questions.csv</code></h3>
<div class="warn">
  این فایل معمولاً <b>لازم نیست</b>، چون سؤال‌ها از پلتفرم آزمون می‌آیند.
  فقط وقتی استفاده کنید که سؤالی در آزمون نباشد و بخواهید دستی واردش کنید.
</div>
<table>
  <tr><th>ستون</th><th>توضیح</th></tr>
  <tr><td>year / exam / question_number</td><td>کلید یکتای سؤال</td></tr>
  <tr><td>section / passage_number</td><td>بخش و شماره‌ی متن</td></tr>
  <tr><td>stem / stem_fa</td><td>صورت سؤال و ترجمه‌اش</td></tr>
  <tr><td>option_1 تا option_4</td><td>چهار گزینه</td></tr>
  <tr><td>correct_option</td><td>شماره‌ی گزینه‌ی درست: ۱ تا ۴</td></tr>
  <tr><td>explanation</td><td>تشریح</td></tr>
</table>

<h2>۳) مراحل اجرا، به ترتیب</h2>
<div class="warn">
  <b>ترتیب مهم است.</b> کلمات باید قبل از سؤال‌ها وارد شوند تا گزینه‌ها بتوانند به کلمه‌ها وصل شوند.
  و <code>finalize</code> حتماً آخر از همه.
</div>

<div class="step">
  <b>قدم ۰ — اول فقط بررسی کنید</b>
  <p>با <code>--check</code> هیچ چیزی نوشته نمی‌شود، فقط ایرادهای فایل گزارش می‌شود.
  این کار را برای هر فایل، قبل از ورود واقعی انجام بدهید.</p>
<pre>docker exec -w /var/www/konkurix language-joomla-php83-1 \
  php artisan zaban:import words storage/app/zaban/words.csv --check</pre>
</div>

<div class="step">
  <b>قدم ۱ — کلمات</b>
<pre>docker exec -w /var/www/konkurix language-joomla-php83-1 \
  php artisan zaban:import words storage/app/zaban/words.csv</pre>
</div>

<div class="step">
  <b>قدم ۲ — بخش‌ها</b>
<pre>docker exec -w /var/www/konkurix language-joomla-php83-1 \
  php artisan zaban:import sections storage/app/zaban/exam_sections.csv</pre>
</div>

<div class="step">
  <b>قدم ۳ — متن‌ها</b>
<pre>docker exec -w /var/www/konkurix language-joomla-php83-1 \
  php artisan zaban:import texts storage/app/zaban/texts.csv</pre>
</div>

<div class="step">
  <b>قدم ۴ — سؤال‌ها (فقط در صورت نیاز)</b>
  <p>اگر سؤال‌ها را از پلتفرم آزمون می‌گیرید، این قدم را رد کنید و به‌جایش
  از صفحه‌ی «همگام‌سازی سؤال‌ها» استفاده کنید.</p>
<pre>docker exec -w /var/www/konkurix language-joomla-php83-1 \
  php artisan zaban:import questions storage/app/zaban/questions.csv</pre>
</div>

<div class="step">
  <b>قدم ۵ — finalize (حتماً آخر)</b>
  <p>این مرحله شش کار می‌کند: شمارنده‌های کلمات، وصل کردن متن به سؤال‌های کلوز و پسیج،
  وصل کردن ظهورها به سؤال‌ها، وصل کردن گزینه‌ها به کلمات بانک، ساخت جدول کلمات هر سؤال،
  و در آخر بررسی سلامت داده‌ها.</p>
<pre>docker exec -w /var/www/konkurix language-joomla-php83-1 \
  php artisan zaban:import finalize</pre>
  <p>گزارش پایانی را بخوانید. اگر ایرادی گزارش شد، قبل از ادامه رفعش کنید.</p>
</div>

<div class="step">
  <b>قدم ۶ — تازه کردن پیش‌بینی‌ها و کش</b>
<pre>docker exec -w /var/www/konkurix language-joomla-php83-1 php artisan zaban:predict
docker exec -w /var/www/konkurix language-joomla-php83-1 php artisan cache:clear</pre>
</div>

<h2>۴) بعدش چه کار کنید</h2>
<ol>
  <li>به <b>«خلاصه‌ی وضعیت»</b> بروید و ببینید تعداد کلمات و سؤال‌ها بالا رفته است.</li>
  <li>جدول <b>«دفترچه‌های ناقص»</b> را نگاه کنید؛ اگر کمبودی مانده، همان‌جا دیده می‌شود.</li>
  <li>در <b>«همگام‌سازی سؤال‌ها»</b> دکمه‌ی <b>بررسی</b> را بزنید و ببینید
      شماره‌گذاری بانک کلمات با پلتفرم آزمون می‌خواند یا نه.</li>
</ol>

<h2>۵) اگر «ناهمخوان» گزارش شد</h2>
<p>یعنی اکسل و پلتفرم آزمون دو چیز متفاوت می‌گویند. مثلاً اکسل می‌گوید سؤال‌های ۱ تا ۱۰
بخش واژگان‌اند، ولی آزمون می‌گوید ۶ تا ۱۵.</p>
<div class="warn">
  <b>در این حالت آن دفترچه عمداً رد می‌شود و وارد نمی‌شود.</b>
  اگر به زور وارد شود، کلمه‌ها به سؤال‌های اشتباه وصل می‌شوند و دانشجو روی سؤال ۶
  کلمه‌ی سؤال ۱ را می‌بیند. این از نبودن آن دفترچه بدتر است.
</div>
<p>راه درست: علتش را پیدا کنید. معمولاً یکی از این دوتاست:</p>
<ul>
  <li>سؤالی در پلتفرم آزمون وارد نشده یا حذف شده است.</li>
  <li>ترتیب سؤال‌ها در آزمون با دفترچه‌ی واقعی فرق دارد.</li>
</ul>
<p>بعد از رفع، دوباره «بررسی» را بزنید.</p>

<h2>۶) نکته‌های ایمنی</h2>
<ul>
  <li><b>قبل از هر ورود بزرگ، از دیتابیس پشتیبان بگیرید.</b> در phpMyAdmin،
      دیتابیس <code>language</code> ← تب Export.</li>
  <li>اجرای دوباره‌ی یک فایل ردیف تکراری نمی‌سازد؛ دستورها روی کلید یکتا کار می‌کنند.</li>
  <li>بانک کلمات فقط از همین اکسل‌ها می‌آید و در پلتفرم آزمون نسخه‌ای ندارد.
      پس هیچ‌وقت جدول‌های <code>words</code>، <code>word_examples</code> و
      <code>word_occurrences</code> را پاک نکنید.</li>
  <li>بعد از باز شدن سایت، دک و تاریخچه‌ی کاربران به شناسه‌ی کلمه‌ها وصل است.
      ورود افزایشی بی‌خطر است، ولی پاک کردن و از نو ساختن نه.</li>
</ul>

</div>
@endsection
