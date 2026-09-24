<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * دسترسی به بخش مدیریت.
 *
 * نقش‌ها همان چیزی هستند که پلتفرم آزمون دارد. اینجا فقط سه نقش بالا
 * اجازه دارند؛ اگر بعداً «اپراتور» هم لازم شد، به همین آرایه اضافه شود.
 *
 * عمداً ۴۰۴ برمی‌گرداند نه ۴۰۳: کاربر عادی حتی نباید بفهمد چنین
 * مسیری وجود دارد.
 */
class EnsureAdmin
{
    private const ALLOWED = ['admin', 'manager', 'editor'];

    public function handle(Request $request, Closure $next)
    {
        $type = $request->user()->type ?? 'student';

        if (!in_array($type, self::ALLOWED, true)) {
            abort(404);
        }
        return $next($request);
    }
}