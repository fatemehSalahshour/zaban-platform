<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** پنل مدیریت — هشدارهای امنیتی (سقف پاسخ روزانه، اشتراک حساب). */
class ZabanAdminAlertController extends Controller
{
    public const KINDS = [
        'answer_cap'      => 'به سقف روزانه‌ی دیدن پاسخ رسید',
        'word_cap'        => 'به سقف روزانه‌ی دیدن جزئیات کلمه رسید',
        'meaning_cap'     => 'به سقف روزانه‌ی دیدن معنی رسید',
        'auto_lock'       => 'قفل خودکار حساب (الگوی شبیه اسکریپت)',
        'account_sharing' => 'جابه‌جایی مکرر بین دستگاه‌ها (احتمال اشتراک حساب)',
    ];

    public function index(): View
    {
        $alerts = DB::table('security_alerts as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.created_at', '>=', now()->subDays(30))
            ->orderByDesc('a.created_at')->limit(300)
            ->get(['a.*', 'u.name', 'u.mobile', 'u.email']);

        /* تعداد هشدار هر کاربر در این ۳۰ روز — تکرار یعنی الگو، نه اتفاق */
        $repeat = DB::table('security_alerts')->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('user_id, COUNT(*) AS n')->groupBy('user_id')->pluck('n', 'user_id');

        /* با دیدن این صفحه، هشدارها «دیده‌شده» می‌شوند (عدد سایدبار صفر) */
        DB::table('security_alerts')->whereNull('seen_at')->update(['seen_at' => now()]);

        /* حساب‌هایی که همین الان قفل‌اند — با دکمه‌ی باز کردن */
        $locked = DB::table('users')->whereNotNull('locked_until')->where('locked_until', '>', now())
            ->orderBy('locked_until')->get(['id', 'name', 'mobile', 'email', 'locked_until', 'lock_reason']);

        return view('zaban-admin.alerts', ['alerts' => $alerts, 'repeat' => $repeat, 'kinds' => self::KINDS,
                                           'locked' => $locked]);
    }

    /**
     * GET|POST /zaban-admin/leak — ردیابی متن منتشرشده: امضای نامرئی (Watermark) متن
     * چسبانده‌شده را می‌خواند و می‌گوید از حساب چه کسی برداشته شده.
     */
    public function leak(\Illuminate\Http\Request $req, \App\Services\Security\Watermark $wm)
    {
        $text = (string) $req->input('text', '');
        $hits = $text !== '' ? $wm->decode($text) : [];
        $users = $hits ? DB::table('users')->whereIn('id', array_keys($hits))
            ->get(['id', 'name', 'mobile', 'email', 'type', 'created_at'])->keyBy('id') : collect();

        return view('zaban-admin.leak', ['text' => $text, 'hits' => $hits, 'users' => $users,
                                         'checked' => $req->isMethod('post')]);
    }

    /** POST /zaban-admin/users/{id}/unlock — باز کردن دستی قفل خودکار */
    public function unlock(int $id, \App\Services\Security\AbuseGuard $guard)
    {
        $guard->unlock($id);
        return back()->with('ok', 'قفل حساب باز شد.');
    }
}
