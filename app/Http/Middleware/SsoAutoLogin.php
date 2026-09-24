<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * ورود خودکار بی‌صدا: مهمانی که در سرور مرکزی نشست دارد، یک لحظه با
 * prompt=none به آنجا می‌رود و وارد برمی‌گردد.
 *
 * کوکی sso_probe *قبل* از ریدایرکت گذاشته می‌شود، نه بعد از موفقیت؛ وگرنه
 * هر شکستی در مسیر به حلقه‌ی بی‌نهایت ریدایرکت تبدیل می‌شد.
 */
class SsoAutoLogin
{
    public const PROBE_COOKIE = 'sso_probe';

    public function handle(Request $request, Closure $next)
    {
        if (!$this->shouldProbe($request)) {
            return $next($request);
        }

        Cookie::queue(Cookie::make(self::PROBE_COOKIE, '1',
            (int) config('sso.auto_login_cooldown', 10), '/', null, $request->isSecure(), true, false, 'lax'));

        return redirect()->route('sso.redirect', ['silent' => 1, 'to' => $request->fullUrl()]);
    }

    private function shouldProbe(Request $request): bool
    {
        if (!config('sso.enabled') || !config('sso.auto_login') || Auth::check()) {
            return false;
        }
        if (!$request->isMethod('GET') || $request->expectsJson()) {
            return false;
        }
        // نبودن کوکی نشست مرکزی یعنی کاربر هیچ‌جا وارد نیست؛ پرسیدن اتلاف است.
        if (!$request->hasCookie(config('sso.central_session_cookie', 'sso'))) {
            return false;
        }
        if ($request->hasCookie(self::PROBE_COOKIE)) {
            return false;
        }
        if (str_starts_with((string) $request->route()?->getName(), 'sso.')) {
            return false;
        }
        foreach (config('sso.auto_login_except', []) as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }
        return true;
    }
}
