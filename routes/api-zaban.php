<?php

use App\Http\Controllers\ZabanController;
use App\Http\Controllers\ZabanPurchaseController;
use App\Http\Controllers\ZabanReportController;
use App\Http\Controllers\ZabanAnnouncementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| پلتفرم زبان — نسخه‌ی ۲
|   این فایل از routes/web.php با require بارگذاری می‌شود.
|
| ثبت میدل‌ور در bootstrap/app.php (لاراول ۱۱+):
|   ->withMiddleware(function (Middleware $m) {
|       $m->alias(['zaban.exam' => \App\Http\Middleware\EnsureExamEntitlement::class]);
|   })
|
| یا در app/Http/Kernel.php (لاراول ۱۰):
|   protected $middlewareAliases = [ 'zaban.exam' => EnsureExamEntitlement::class, ];
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'throttle:zaban-api'])->prefix('api')->group(function () {

    /* ---------- محتوا — پشت چک حق دسترسی ---------- */
    Route::middleware('zaban.exam')->group(function () {
        Route::get('content',           [ZabanController::class, 'content']);
        Route::get('content/questions', [ZabanController::class, 'contentQuestions']);
    });

    // حق دسترسی داخل خود اکشن چک می‌شود، چون رشته از روی شناسه‌ی سؤال درمی‌آید.
    Route::get('question/{id}/answer', [ZabanController::class, 'answer'])
         ->whereNumber('id')->middleware('throttle:zaban-answer');
    Route::get('question/{id}/stats', [ZabanController::class, 'questionStats'])
         ->whereNumber('id')->middleware('throttle:zaban-qstats');
    Route::post('question/{id}/attempt', [ZabanController::class, 'questionAttempt'])
         ->whereNumber('id')->middleware('throttle:zaban-qattempt');
    Route::get('crowd', [ZabanController::class, 'crowd'])->middleware('throttle:zaban-crowd');
    /* جزئیات کلمه (مثال‌ها) — بانک دیگر یک‌جا فرستاده نمی‌شود */
    Route::get('words/detail', [ZabanController::class, 'wordDetails'])->middleware('throttle:zaban-words');
    Route::get('words/meanings', [ZabanController::class, 'wordMeanings'])->middleware('throttle:zaban-meanings');
    Route::get('words/search', [ZabanController::class, 'wordSearch'])->middleware('throttle:zaban-search');
    // چند پاسخ با یک درخواست — کارنامه‌ی قدیمی، واشبک بعد از رفرش.
    Route::get('answers', [ZabanController::class, 'answers'])->middleware('throttle:zaban-answers');

    /* ---------- وضعیت کاربر ---------- */
    Route::get('me',      [ZabanController::class, 'me']);
    Route::put('profile', [ZabanController::class, 'profile'])->middleware('throttle:zaban-profile');
    Route::post('deck',   [ZabanController::class, 'deck']);
    Route::post('star',   [ZabanController::class, 'star']);
    Route::put('note',    [ZabanController::class, 'note']);
    Route::put('reading', [ZabanController::class, 'reading']);
    Route::post('review', [ZabanController::class, 'review']);

    /* ---------- آزمون ---------- */
    Route::post('exam/start',       [ZabanController::class, 'examStart']);
    Route::patch('exam/{id}',       [ZabanController::class, 'examSave'])->whereNumber('id');
    Route::post('exam/{id}/finish', [ZabanController::class, 'examFinish'])->whereNumber('id');
    Route::delete('exam/{id}',      [ZabanController::class, 'examDrop'])->whereNumber('id');
    Route::get('exam/history',      [ZabanController::class, 'examHistory']);
    Route::get('exam/{id}/result',  [ZabanController::class, 'examResult'])->whereNumber('id');

    /* ---------- گزارش اشکال ---------- */
    Route::get('reports',            [ZabanReportController::class, 'index']);
    Route::post('report',            [ZabanReportController::class, 'store'])->middleware('throttle:zaban-report');
    Route::post('report/{id}/reply', [ZabanReportController::class, 'reply'])
         ->whereNumber('id')->middleware('throttle:zaban-report-reply');
    Route::post('report/{id}/seen',  [ZabanReportController::class, 'seen'])->whereNumber('id');

    /* ---------- اطلاعیه‌ها ---------- */
    Route::get('announcements',       [ZabanAnnouncementController::class, 'index']);
    Route::post('announcements/read', [ZabanAnnouncementController::class, 'read']);

    /* ---------- فعالیت و رتبه‌بندی ---------- */
    Route::post('heartbeat', [ZabanController::class, 'heartbeat'])->middleware('throttle:zaban-heartbeat');
    Route::get('board',      [ZabanController::class, 'board']);

    /* ---------- خرید ---------- */
    Route::get('buy/quote',  [ZabanPurchaseController::class, 'quote']);
});

/*
| پرداخت در مسیرهای وب /buy است (ZabanPurchaseController + Services/Payment/Checkout).
| مسیر قدیمی api/buy/callback با «$verified = true» حذف شد.
*/

/*
|--------------------------------------------------------------------------
| زمان‌بندی — routes/console.php
|--------------------------------------------------------------------------
| Schedule::command('zaban:board')->everyTenMinutes();
| Schedule::command('zaban:predict')->dailyAt('03:20');
| Schedule::command('zaban:close-stale-attempts')->everyFifteenMinutes();
*/