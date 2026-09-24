<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $m) {
        $m->alias([
            'zaban.exam'  => \App\Http\Middleware\EnsureExamEntitlement::class,
            'zaban.admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);

        /* ورود یکپارچه — ترتیب مهم است: اول خروج مرکزی اعمال شود،
           بعد ورود خودکار. هر دو به گروه web می‌روند؛ مسیرهای /api/* هم
           چون از web.php بارگذاری می‌شوند، از همین گروه رد می‌شوند. */
        $m->web(append: [
            \App\Http\Middleware\SsoSessionGuard::class,
            \App\Http\Middleware\SsoAutoLogin::class,
        ]);

        /* درخواست‌هایی که از سرور دیگری می‌آیند و توکن CSRF ندارند:
           - backchannel: auth-server، محافظت با امضای HMAC
           - callback درگاه: سرور درگاه، محافظت با تأیید تراکنش نزد درگاه */
        $m->validateCsrfTokens(except: [
            'sso/backchannel-logout',
            'api/buy/callback',
        ]);

        /* کوکی نشست مرکزی را auth روی دامنه‌ی مادر می‌گذارد و با کلید این
           سایت رمز نشده. بدون این استثنا لاراول بی‌صدا دورش می‌ریزد و ورود
           خودکار هیچ‌وقت فعال نمی‌شود. */
        $m->encryptCookies(except: ['sso']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();