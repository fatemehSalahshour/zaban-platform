<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ورود مدیر به حساب کاربر برای بررسی مشکل، و برگشت به حساب خودش.
 *
 * -------------------------------------------------------------------------
 * سه نکته که این پروژه را از بقیه متفاوت می‌کند
 * -------------------------------------------------------------------------
 * ۱) SingleSession: هر حساب فقط روی یک دستگاه. اگر این میدل‌ور در حالت
 *    بررسی فعال بماند، نشانه‌ی دستگاهِ کاربر را با نشست مدیر بازنویسی
 *    می‌کند و کاربر واقعی همان لحظه از گوشی خودش بیرون می‌افتد — بدون
 *    اینکه بفهمد چرا. پس نشست بررسی از آن قاعده معاف است.
 *
 * ۲) نشانه‌ی دستگاهِ خودِ مدیر هم باید حفظ شود. اگر بعد از برگشت
 *    device_token نداشته باشد، SingleSession او را هم بیرون می‌اندازد.
 *
 * ۳) نشست‌های SSO مدیر (sso.login_at) نگه داشته و برگردانده می‌شوند، تا
 *    بعد از برگشت، خروج مرکزی دوباره روی نشست مدیر اثر کند.
 * -------------------------------------------------------------------------
 */
class ImpersonateController extends Controller
{
    public const KEY = 'impersonator';

    /** ورود به حساب کاربر — روت پشت zaban.admin است. */
    public function start(Request $request, int $id): RedirectResponse
    {
        if ($request->session()->has(self::KEY)) {
            return back()->with('error', 'اول از حساب کاربر فعلی به حساب خودتان برگردید.');
        }

        $target = User::find($id);

        if (!$target) {
            return back()->with('error', 'کاربر یافت نشد.');
        }
        if ($target->id === Auth::id()) {
            return back()->with('error', 'این حساب خودتان است.');
        }

        $stash = [
            'admin_id'     => Auth::id(),
            'device_token' => $request->session()->get('device_token'),
            'sso_login_at' => $request->session()->get('sso.login_at'),
            'sso_id_token' => $request->session()->get('sso.id_token'),
            'started_at'   => time(),
        ];

        Log::warning('[IMPERSONATE] start', [
            'admin_id'  => $stash['admin_id'],
            'target_id' => $target->id,
            'ip'        => $request->ip(),
        ]);

        $request->session()->flush();
        $request->session()->regenerate();

        Auth::login($target);          // remember=false عمدی
        $request->session()->put(self::KEY, $stash);

        return redirect()->route('zaban');
    }

    /** برگشت به حساب مدیر. عمداً zaban.admin ندارد: کاربر فعلی دانشجوست. */
    public function stop(Request $request): RedirectResponse
    {
        $stash = $request->session()->get(self::KEY);

        if (!$stash || empty($stash['admin_id'])) {
            return redirect()->route('zaban');
        }

        $targetId = Auth::id();
        $admin    = User::find($stash['admin_id']);
        $isAdmin  = $admin && in_array($admin->type, ['admin', 'manager', 'editor'], true);

        $request->session()->flush();
        $request->session()->regenerate();

        if (!$isAdmin) {
            Auth::logout();
            Log::warning('[IMPERSONATE] stop refused: not admin anymore', ['admin_id' => $stash['admin_id']]);
            return redirect()->route('home');
        }

        Auth::login($admin);

        /* نشانه‌ی دستگاه مدیر برمی‌گردد، وگرنه SingleSession در درخواست
           بعدی او را هم بیرون می‌اندازد. اگر نشانه‌ای نبود (نشست قدیمی)،
           همان‌جا یکی تازه ساخته و روی حساب ثبت می‌شود. */
        $token = $stash['device_token'] ?: \Illuminate\Support\Str::random(40);
        $request->session()->put('device_token', $token);
        DB::table('users')->where('id', $admin->id)->update(['session_token' => $token]);

        foreach (['sso.login_at' => 'sso_login_at', 'sso.id_token' => 'sso_id_token'] as $key => $from) {
            if (!empty($stash[$from])) {
                $request->session()->put($key, $stash[$from]);
            }
        }

        Log::info('[IMPERSONATE] end', [
            'admin_id'  => $admin->id,
            'target_id' => $targetId,
            'seconds'   => time() - (int) ($stash['started_at'] ?? time()),
        ]);

        return redirect()->route('zaban.admin.users');
    }

    /**
     * نوار هشدار که در پوسته‌ی رابط تزریق می‌شود.
     *
     * رابط یک فایل HTML ثابت است، پس نمی‌شود مثل بقیه‌ی سایت‌ها در قالب
     * Blade نوار گذاشت. به‌جایش همان فایل خوانده و درست پیش از </body>
     * این تکه به آن اضافه می‌شود — بدون دست زدن به خود فایل رابط.
     */
    public static function banner(): string
    {
        $user = Auth::user();
        $name = e($user?->name ?: $user?->mobile ?: ('#' . Auth::id()));
        $back = e(route('impersonate.stop'));

        return <<<HTML
<div id="impersonate-bar" style="position:fixed;top:0;left:0;right:0;z-index:2147483647;
     background:#b91c1c;color:#fff;padding:9px 14px;text-align:center;
     font:14px/1.9 inherit;box-shadow:0 2px 8px rgba(0,0,0,.3)">
  شما به‌عنوان مدیر در حساب <b>{$name}</b> (شناسه {$user?->id}) هستید.
  <a href="{$back}" style="color:#fff;text-decoration:underline;font-weight:700;margin-right:8px">بازگشت به حساب خودم</a>
</div>
<script>document.documentElement.style.scrollPaddingTop='48px';document.body.style.paddingTop='42px';</script>
HTML;
    }
}
