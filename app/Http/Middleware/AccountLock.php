<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * حساب قفل‌شده (AbuseGuard): همه‌ی مسیرهای /api جواب ۴۲۳ می‌گیرند و رابط پیام قفل را
 * نشان می‌دهد. صفحه‌ها، خروج، SSO و خرید باز می‌مانند (محتوایی از آن‌ها بیرون نمی‌رود).
 */
class AccountLock
{
    public function handle(Request $request, Closure $next)
    {
        /* فقط API: پوسته‌ی صفحه محتوایی ندارد و باید بالا بیاید تا پیام قفل را نشان دهد */
        if (!$request->is('api/*')) return $next($request);

        $user = Auth::guard('web')->user();
        if (!$user || !$user->locked_until || now()->gte($user->locked_until)) return $next($request);

        $until = \Illuminate\Support\Carbon::parse($user->locked_until);
        $hours = strtr((string) max(1, (int) ceil(now()->diffInMinutes($until, true) / 60)),
                       ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
        $msg = "حساب شما به دلیل الگوی غیرعادی استفاده موقتاً قفل شده است و حدود {$hours} ساعت دیگر خودکار باز می‌شود. "
             . 'اگر فکر می‌کنید اشتباهی رخ داده، با پشتیبانی تماس بگیرید.';

        return response()->json(['error' => 'account_locked', 'message' => $msg, 'until' => $until->toIso8601String()], 423);
    }
}
