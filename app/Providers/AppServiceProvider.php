<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         | محدودیت درخواست — هر مسیر شمارنده‌ی جدای خودش.
         |
         | قبلاً مسیرها «throttle:10,1» ساده داشتند. در لاراول کلید آن فقط از
         | شناسه‌ی کاربر ساخته می‌شود، نه از مسیر؛ پس همه‌ی مسیرها یک شمارنده‌ی
         | مشترک داشتند و هر درخواست دوبار شمرده می‌شد (گروه + مسیر). نتیجه:
         | heartbeat با سقف ۶ تقریباً همیشه ۴۲۹ می‌گرفت (زمان مطالعه ۰۰:۰۰)،
         | و /crowd با سقف ۱۰ بعد از خود بارگذاری صفحه رد می‌شد.
         */
        $per = fn (string $name, int $n) => RateLimiter::for($name,
            fn (Request $r) => Limit::perMinute($n)->by($name . ':' . ($r->user()?->id ?: $r->ip())));

        $per('zaban-api',           300);   // سقف کلی همه‌ی /api — مشترک بین همه‌ی تب‌های کاربر
        $per('zaban-answer',         40);
        $per('zaban-answers',        30);
        $per('zaban-qstats',         40);
        $per('zaban-qattempt',       60);
        $per('zaban-crowd',          10);
        $per('zaban-words',          60);
        $per('zaban-meanings',      120);
        $per('zaban-search',         40);
        $per('zaban-profile',        20);
        $per('zaban-report',         10);
        $per('zaban-report-reply',   20);
        $per('zaban-heartbeat',       6);
        $per('zaban-buy',            10);

        /* خروج مرکزی: درخواست از سرور auth می‌آید، نه کاربر — با آی‌پی */
        RateLimiter::for('sso-backchannel', fn (Request $r) => Limit::perMinute(60)->by('bc:' . $r->ip()));

        /* برگشت از درگاه: مرورگر کاربر بدون نشست می‌آید — با آی‌پی */
        RateLimiter::for('zaban-buy-return', fn (Request $r) => Limit::perMinute(30)->by('br:' . $r->ip()));

        /* برگشت از درگاه با POST از دامنه‌ی shaparak می‌آید و توکن CSRF ندارد.
           buy/fake فقط در local و با IRANKISH_FAKE کار می‌کند (کنترلر بررسی می‌کند). */
        ValidateCsrfToken::except(['buy/return', 'buy/fake']);

        /* ---------- یک حساب، یک دستگاه (config zaban.single_session) ----------
           هر ورود (SSO، dev-login، به‌یادسپاری) نشانه‌ی تازه می‌گیرد؛ SingleSession
           روی همه‌ی مسیرهای وب و API نشستِ با نشانه‌ی کهنه را خارج می‌کند. */
        Event::listen(Login::class, function (Login $e) {
            if (!config('zaban.single_session') || $e->guard !== 'web' || !request()->hasSession()) return;
            $t = Str::random(40);
            request()->session()->put('device_token', $t);
            DB::table('users')->where('id', $e->user->getAuthIdentifier())->update(['session_token' => $t]);
            \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('zaban_replaced'));
        });
        $this->app['router']->pushMiddlewareToGroup('web', \App\Http\Middleware\SingleSession::class);
        /* حساب قفل‌شده (AbuseGuard) — همه‌ی /api جواب ۴۲۳ */
        $this->app['router']->pushMiddlewareToGroup('web', \App\Http\Middleware\AccountLock::class);
        /* نشانگر «دستگاه دیگر» را جاوااسکریپت رابط می‌خواند؛ رمزنگاری کوکی آن را ناخوانا می‌کرد.
           محتوایش فقط «1» است. */
        \Illuminate\Cookie\Middleware\EncryptCookies::except(['zaban_replaced']);
    }
}
