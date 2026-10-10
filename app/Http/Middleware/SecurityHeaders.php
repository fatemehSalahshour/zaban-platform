<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * هدرهای امنیتی پایه روی همه‌ی پاسخ‌های وب و API (کیت امنیت، بخش ۲-۳).
 *
 *   X-Frame-Options: SAMEORIGIN       صفحه داخل iframe سایت دیگری باز نشود (کلیک‌دزدی)
 *   X-Content-Type-Options: nosniff   مرورگر نوع فایل را حدس نزند
 *   Referrer-Policy                   آدرس کامل صفحه (با پارامترها) به سایت‌های بیرونی نرود
 *   Permissions-Policy                دوربین، میکروفون، موقعیت و پرداخت مرورگر بسته
 *   Strict-Transport-Security         فقط روی https؛ مرورگر دیگر سراغ http نمی‌رود
 *
 * CSP عمداً گذاشته نشده: صفحه‌ها اسکریپت درون‌خطی دارند و یک CSP نادرست کل
 * پلتفرم را از کار می‌اندازد. هدری که پاسخ از قبل دارد بازنویسی نمی‌شود.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Frame-Options'        => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=()',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        foreach (self::HEADERS as $k => $v) {
            if (!$response->headers->has($k)) $response->headers->set($k, $v);
        }
        if ($request->isSecure() && !$response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }
        return $response;
    }
}
