<?php

namespace App\Http\Middleware;

use App\Services\Security\Alerts;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * هر حساب فقط روی یک دستگاه (config zaban.single_session).
 *
 * هنگام ورود (رویداد Login در AppServiceProvider) یک نشانه‌ی تازه هم در نشست
 * و هم در users.session_token نوشته می‌شود؛ نشستی که نشانه‌اش با حساب نخواند،
 * یعنی کسی روی دستگاه دیگری وارد شده ← این نشست خارج می‌شود.
 *
 * بعد از خروج، کوکی sso_probe گذاشته می‌شود تا ورود بی‌صدای SSO این دستگاه را
 * خودکار برنگرداند؛ وگرنه دو دستگاه بی‌انتها همدیگر را بیرون می‌انداختند.
 * برگشتن فقط با کلیک عمدی روی «ورود» است — که دستگاه دیگر را خارج می‌کند.
 */
class SingleSession
{
    public function __construct(private Alerts $alerts) {}

    public function handle(Request $request, Closure $next)
    {
        if (!config('zaban.single_session') || !$request->hasSession() || !Auth::guard('web')->check()) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();
        $mine = (string) $request->session()->get('device_token', '');
        $live = (string) ($user->session_token ?? '');

        /* نشستِ پیش از راه‌افتادن این قاعده، یا اولین درخواست: همین دستگاه صاحب حساب می‌شود */
        if ($live === '') {
            $t = $mine !== '' ? $mine : Str::random(40);
            $request->session()->put('device_token', $t);
            DB::table('users')->where('id', $user->id)->update(['session_token' => $t]);
            $user->session_token = $t;
            return $next($request);
        }

        if ($mine !== '' && hash_equals($live, $mine)) {
            return $next($request);
        }

        /* دستگاه دیگری وارد شده است */
        $this->noteKick((int) $user->id);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::make(SsoAutoLogin::PROBE_COOKIE, '1', 720));
        /* نشانگر قابل‌خواندن برای رابط (httpOnly=false): درخواست‌های هم‌زمانی که بعد از
           بسته شدن نشست می‌رسند ۴۰۱ ساده می‌گیرند؛ رابط با این نشانگر آن‌ها را هم
           «دستگاه دیگر» می‌فهمد و خودکار به /login نمی‌رود. ۱۰ دقیقه. */
        Cookie::queue(Cookie::make('zaban_replaced', '1', 10, null, null, null, false));

        $msg = 'این حساب روی دستگاه دیگری باز شد و این دستگاه خارج شد. برای ادامه، دوباره وارد شوید (دستگاه دیگر خارج می‌شود).';
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'session_replaced', 'message' => $msg], 401);
        }
        return redirect()->route('home')->with('sso_error', $msg);
    }

    /** دستگاه‌عوض‌کردن‌های پیاپی در یک روز = احتمال اشتراک حساب ← هشدار به مدیر */
    private function noteKick(int $uid): void
    {
        $key = 'zaban.kicks.' . $uid . '.' . now()->toDateString();
        Cache::add($key, 0, now()->endOfDay());
        $n = Cache::increment($key);
        if ($n >= (int) config('zaban.sharing_alert_kicks', 5)) {
            $this->alerts->raise($uid, 'account_sharing', "امروز {$n} بار بین دستگاه‌ها جابه‌جا شد.");
        }
    }
}
