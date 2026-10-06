<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ZabanAdminController;
use App\Http\Controllers\ZabanAdminReportController;
use App\Http\Controllers\ZabanAdminAnnouncementController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\SsoBackchannelController;
use App\Http\Controllers\ZabanPurchaseController;
use App\Http\Controllers\ZabanAdminSyncController;
use App\Http\Controllers\ZabanAdminAlertController;

/* صفحه‌ی ورودی = لندینگ پیج. کاربر واردشده مستقیم به پلتفرم می‌رود؛ مهمان لندینگ را
   می‌بیند با دکمه‌ی ورود، قیمت و تاریخ کنکور از سرور، و پیام خطای ورود SSO اگر بود. */
Route::get('/', function (\App\Services\Pricing $pricing, \App\Services\LandingFacts $facts) {
    if (auth()->check()) return redirect()->route('zaban');

    /* قیمت «دسترسی کامل به یک رشته»، با تخفیف رونمایی اگر فعال باشد — وگرنه
       عددِ لندینگ با مبلغ صفحه‌ی خرید نمی‌خواند و کاربر حس می‌کند قیمت عوض
       شده. قیمت تک‌رشته برای هر سه رشته یکی است، پس اولی را می‌گیریم. */
    $q = $pricing->quote([\App\Services\Entitlements::EXAMS[0]]);

    return view('landing', [
        /* عدد فارسی با جداکننده‌ی فارسی — بقیه‌ی صفحه هم با رقم فارسی است */
        'price'    => \App\Support\FaNum::format($q['payable']),
        'wasPrice' => $q['launch_off'] > 0 ? \App\Support\FaNum::format($q['bundle_price']) : null,
        'launchPct'=> $q['launch_pct'] ?? 0,
        /* متن تخفیف از پنل مدیریت می‌آید، نه از کد */
        'launchTitle' => $q['launch_title'] ?? \App\Services\Pricing::LAUNCH_TITLE_DEFAULT,
        'examDate' => $pricing->accessUntil(),         /* میلادی؛ شمارش معکوس صفحه */
        /* اعداد و کلمه‌های نمونه از خود بانک — قبلاً در HTML و landing.js ثابت بودند */
        'facts'    => $facts->facts(),
        'words'    => $facts->words(),
        /* دفترچه‌ی نمونه‌ی بخش آزمون: سؤال‌های واقعی وکب، بدون کلید پاسخ */
        'sample'   => $facts->sampleBooklet(),
    ]);
})->name('home');

require __DIR__ . '/../routes/api-zaban.php';

/* ---------- ورود یکپارچه ---------- */
Route::get('/sso/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
Route::get('/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');
Route::post('/sso/backchannel-logout', [SsoBackchannelController::class, 'logout'])
    ->middleware('throttle:sso-backchannel')->name('sso.backchannel');

// میدل‌ور auth مهمان را به این مسیر می‌فرستد.
Route::get('/login', fn () => config('sso.enabled') || !app()->environment('local')
    ? redirect()->route('sso.redirect')
    : redirect('/dev-login'))->name('login');

/* GET هم پذیرفته می‌شود تا دکمه‌ی خروج رابط بتواند یک لینک ساده باشد.
   POST از فرم یا fetch با توکن CSRF هم کار می‌کند. */
Route::match(['get', 'post'], '/logout', [SsoController::class, 'logout'])->name('logout');

/* پلتفرم تک‌صفحه‌ای است، ولی هر صفحه آدرس خودش را دارد (/zaban/words، /zaban/word/ability،
   /zaban/test/1404/ce/6، …). سرور برای همه‌ی این‌ها همان فایل را می‌دهد و خود صفحه از روی
   آدرس می‌فهمد چه چیزی را نشان دهد (router در HTML). رفرش و لینک مستقیم هم کار می‌کند. */
Route::get('/zaban/{path?}', fn () => view('zaban'))
    ->where('path', '.*')->middleware('auth')->name('zaban');

Route::get('/admin/ping', fn () => 'ok')->middleware(['auth', 'zaban.admin']);

/* ---------- خرید (درگاه ایران کیش) ----------
| /buy/return بدون auth و بدون CSRF: مرورگر با POST از دامنه‌ی درگاه برمی‌گردد و
| کوکی نشست (SameSite=Lax) همراهش نیست. اعتبار با نشانه و تاییدیه‌ی سرور‌به‌سرور است. */
Route::middleware('auth')->group(function () {
    Route::get('/buy', [ZabanPurchaseController::class, 'page'])->name('buy');
    Route::post('/buy', [ZabanPurchaseController::class, 'start'])
        ->middleware('throttle:zaban-buy')->name('buy.start');
    Route::get('/buy/result/{order}', [ZabanPurchaseController::class, 'result'])
        ->whereNumber('order')->name('buy.result');
});
Route::post('/buy/return', [ZabanPurchaseController::class, 'back'])
    ->middleware('throttle:zaban-buy-return')->name('buy.return');
Route::match(['get', 'post'], '/buy/fake', [ZabanPurchaseController::class, 'fake'])->name('buy.fake');

if (app()->environment('local')) {
    Route::get('/dev-login/{id?}', function ($id = 1) {
        Auth::loginUsingId((int) $id);
        return redirect('/zaban');
    });
}

/*
| پنل مدیریت — همه‌ی مسیرها زیر یک گروه، با یک prefix و یک middleware.
| گروه تو در تو با prefix دوباره نسازید: آدرس «/zaban-admin/zaban-admin/...» می‌شود.
*/
/*
| توکن تازه‌ی CSRF.
|
| وقتی نشست عوض می‌شود (ورود به حساب کاربر، خروج، یا انقضای دو ساعته)، تبی که
| باز مانده توکن باطل دارد و هر درخواستش ۴۱۹ می‌گیرد. رابط با گرفتن این آدرس
| توکن تازه می‌گیرد و همان درخواست را دوباره می‌فرستد، بدون اینکه کاربر چیزی
| ببیند. پاسخ چیزی جز خود توکن ندارد، پس افشای اطلاعاتی در کار نیست.
*/
Route::get('/csrf', fn () => response()->json(['token' => csrf_token()]))->name('csrf');

/*
| ورود به حساب کاربر (پشتیبانی).
|
| بیرون از گروه پنل، چون نامشان نباید «zadmin.» بگیرد؛ ویو با نام
| impersonate.start صدایشان می‌زند.
|
| ⚠ «خروج» فقط auth دارد، نه zaban.admin: در آن لحظه مدیر به‌شکل دانشجو وارد
| است و اگر zaban.admin بگذاریم، راه برگشت خودش بسته می‌شود. کنترلر خودش
| بررسی می‌کند که نشستِ ذخیره‌شده‌ی مدیر وجود دارد.
*/
Route::post('/impersonate/{id}', [\App\Http\Controllers\ImpersonateController::class, 'start'])
    ->middleware(['auth', 'zaban.admin'])->whereNumber('id')->name('impersonate.start');

Route::post('/impersonate-stop', [\App\Http\Controllers\ImpersonateController::class, 'stop'])
    ->middleware('auth')->name('impersonate.stop');

Route::middleware(['auth', 'zaban.admin'])
    ->prefix('zaban-admin')
    ->name('zadmin.')
    ->group(function () {

        Route::get('/', [ZabanAdminController::class, 'dashboard'])->name('dashboard');

        Route::get('/settings',  [ZabanAdminController::class, 'settings'])->name('settings');
        Route::post('/settings', [ZabanAdminController::class, 'saveSettings'])->name('settings.save');

        Route::get('/users', [ZabanAdminController::class, 'users'])->name('users');
        /* پرونده‌ی یک کاربر: اطلاعات، نقش و دسترسی رشته‌ها */
        Route::get('/users/{id}', [ZabanAdminController::class, 'user'])
            ->whereNumber('id')->name('user');
        Route::put('/users/{id}', [ZabanAdminController::class, 'userSave'])
            ->whereNumber('id')->name('user.save');

        /* سفارش‌ها: استعلام دوباره از درگاه و فعال‌سازی دستی کارت به کارت */
        Route::get('/orders', [ZabanAdminController::class, 'orders'])->name('orders');
        Route::post('/orders/{id}/recheck', [ZabanAdminController::class, 'orderRecheck'])
            ->whereNumber('id')->name('order.recheck');
        Route::post('/orders/{id}/activate', [ZabanAdminController::class, 'orderActivate'])
            ->whereNumber('id')->name('order.activate');

        /* اصلاح پیوند کلمه به سؤال — ظهور اشتباهی که از اکسل آمده */
        Route::get('/words', [\App\Http\Controllers\ZabanAdminWordsController::class, 'index'])
            ->name('words');
        Route::post('/words/{word}/occurrence', [\App\Http\Controllers\ZabanAdminWordsController::class, 'saveOccurrence'])
            ->whereNumber('word')->name('words.occ.save');
        Route::post('/words/occurrence/{id}', [\App\Http\Controllers\ZabanAdminWordsController::class, 'destroyOccurrence'])
            ->whereNumber('id')->name('words.occ.delete');

        Route::get('/content',       [ZabanAdminController::class, 'content'])->name('content');
        Route::post('/content/bump', [ZabanAdminController::class, 'bump'])->name('content.bump');

        /* ---------- گزارش‌های کاربران ---------- */
        Route::get('/reports', [ZabanAdminReportController::class, 'index'])->name('reports');
        Route::post('/reports/{id}/reply', [ZabanAdminReportController::class, 'reply'])
            ->whereNumber('id')->name('reports.reply');
        Route::post('/reports/{id}/delete', [ZabanAdminReportController::class, 'destroy'])
            ->whereNumber('id')->name('reports.delete');

        /* ---------- هشدارهای امنیتی ---------- */
        Route::get('/alerts', [ZabanAdminAlertController::class, 'index'])->name('alerts');
        Route::match(['get', 'post'], '/leak', [ZabanAdminAlertController::class, 'leak'])->name('leak');
        Route::post('/users/{id}/unlock', [ZabanAdminAlertController::class, 'unlock'])
            ->whereNumber('id')->name('users.unlock');

        /* ---------- همگام‌سازی سؤال‌ها از پلتفرم آزمون ---------- */
        Route::get('/sync', [ZabanAdminSyncController::class, 'index'])->name('sync');
        Route::post('/sync/check', [ZabanAdminSyncController::class, 'check'])->name('sync.check');
        Route::post('/sync/apply', [ZabanAdminSyncController::class, 'apply'])->name('sync.apply');

        /* راهنمای ورود اکسل‌ها — فقط متن است و هیچ کاری روی دیتابیس نمی‌کند */
        Route::view('/import-guide', 'zaban-admin.import-guide')->name('import.guide');

        /* ---------- اطلاعیه‌ها ---------- */
        Route::get('/announcements', [ZabanAdminAnnouncementController::class, 'index'])->name('announcements');
        Route::post('/announcements', [ZabanAdminAnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/announcements/{id}/edit', [ZabanAdminAnnouncementController::class, 'edit'])
            ->whereNumber('id')->name('announcements.edit');
        Route::post('/announcements/{id}', [ZabanAdminAnnouncementController::class, 'update'])
            ->whereNumber('id')->name('announcements.update');
        Route::post('/announcements/{id}/toggle', [ZabanAdminAnnouncementController::class, 'toggle'])
            ->whereNumber('id')->name('announcements.toggle');
        Route::post('/announcements/{id}/delete', [ZabanAdminAnnouncementController::class, 'destroy'])
            ->whereNumber('id')->name('announcements.delete');
    });
