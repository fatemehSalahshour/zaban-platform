<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/* رتبه‌بندی از پیش حساب می‌شود؛ بدون این، هر بار باز شدن تب یک
   GROUP BY روی کل daily_activity است. */
Schedule::command('zaban:board')->everyTenMinutes();

/* آزمونی که کاربر رهایش کرده و مهلتش گذشته، بسته می‌شود. */
Schedule::command('zaban:close-stale-attempts')->everyFifteenMinutes();

/* پرداخت‌های نیمه‌کاره — تاییدیه‌ی دوباره یا استعلام (مهلت تاییدیه‌ی ایران کیش ۲۰ دقیقه است) */
Schedule::command('zaban:reconcile-payments')->everyFiveMinutes()->withoutOverlapping();

/* نبض زمان‌بندی — zaban:preflight از روی این می‌فهمد cron واقعاً اجرا می‌شود یا نه */
Schedule::call(fn () => \Illuminate\Support\Facades\DB::table('zaban_meta')->updateOrInsert(
    ['k' => 'schedule_heartbeat'], ['v' => now()->toDateTimeString(), 'updated_at' => now()]
))->everyMinute()->name('zaban-schedule-heartbeat')->withoutOverlapping();
