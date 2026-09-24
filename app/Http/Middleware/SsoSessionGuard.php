<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * اعمال خروج مرکزی. auth-server نمی‌تواند مستقیم نشست این سایت را ببندد؛
 * فقط sso_logout_at را روی کاربر می‌گذارد و این میدل‌ور در اولین درخواست
 * بعدی نشست را می‌بندد.
 *
 * فقط نشست‌هایی که از SSO ساخته شده‌اند (sso.login_at دارند) مشمول‌اند؛
 * ورود dev-login در لوکال دست‌نخورده می‌ماند.
 */
class SsoSessionGuard
{
    public function handle(Request $request, Closure $next)
    {
        $user    = Auth::user();
        $loginAt = $request->session()->get('sso.login_at');

        if (!$user || !$loginAt || !$user->sso_logout_at) {
            return $next($request);
        }

        if ($user->sso_logout_at->getTimestamp() < $loginAt) {
            return $next($request);
        }

        Log::info('[SSO] session closed by central logout', ['user_id' => $user->id]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        /* درخواست‌های API رابط (fetch) نباید ریدایرکت HTML بگیرند؛ ۴۰۱ یعنی
           «دوباره وارد شو» و رابط کاربر را به /login می‌فرستد. همان قاعده‌ی
           shouldRenderJsonWhen در bootstrap/app.php: api/* حتی بدون هدر Accept. */
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->route('home');
    }
}