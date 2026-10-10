<?php

namespace App\Http\Middleware;

use App\Support\Roles;
use Closure;
use Illuminate\Http\Request;

/**
 * دسترسی به بخش مدیریت.
 *
 * دو مرحله:
 *   ۱) فقط کارکنان (مدیر کل، مدیر محتوا، ویراستار). بقیه ۴۰۴ می‌گیرند — عمداً
 *      نه ۴۰۳: کاربر عادی حتی نباید بفهمد چنین مسیری وجود دارد.
 *   ۲) هر نقش فقط حوزه‌های خودش (App\Support\Roles). این بررسی روی سرور است و
 *      برای «دیدن» و «ذخیره» هر دو اعمال می‌شود، نه فقط پنهان کردن لینک.
 *      route ناشناخته فقط برای مدیر کل باز است.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!Roles::isStaff($user)) {
            abort(404);
        }

        $area = Roles::areaOfRoute(optional($request->route())->getName());
        $ok = $area === null ? Roles::isAdmin($user) : Roles::can($user, $area);

        if (!$ok) {
            if ($request->expectsJson()) abort(403);
            return redirect()->route('zadmin.dashboard')
                ->with('denied', 'این بخش برای نقش شما («' . (Roles::LABELS[$user->type] ?? $user->type) . '») باز نیست.');
        }

        return $next($request);
    }
}
