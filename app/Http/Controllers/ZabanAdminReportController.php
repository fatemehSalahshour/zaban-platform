<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * پنل مدیریت — پاسخ به گزارش‌های کاربران.
 * دسترسی را میدل‌ور EnsureAdmin روی گروه مسیر کنترل می‌کند، نه این کلاس.
 */
class ZabanAdminReportController extends Controller
{
    public function index(Request $req)
    {
        $state = $req->query('state', 'open');
        if (!in_array($state, ['open', 'answered', 'all'], true)) {
            $state = 'open';
        }

        $q = DB::table('reports')->orderByDesc('last_msg_at')->orderByDesc('id');
        if ($state !== 'all') {
            $q->where('state', $state);
        }
        $reports = $q->paginate(20)->withQueryString();

        $ids  = $reports->getCollection()->pluck('id');
        $msgs = $ids->isEmpty() ? collect() : DB::table('report_messages')
            ->whereIn('report_id', $ids)->orderBy('id')->get()->groupBy('report_id');

        /* نام کاربر و نام مستعار — ردیف پروفایل ممکن است هنوز ساخته نشده باشد. */
        $who = DB::table('users as u')
            ->leftJoin('zaban_profiles as p', 'p.user_id', '=', 'u.id')
            ->whereIn('u.id', $reports->getCollection()->pluck('user_id')->unique())
            ->get(['u.id', 'u.name', 'p.nickname'])->keyBy('id');

        $counts = DB::table('reports')
            ->selectRaw('state, count(*) as c')->groupBy('state')->pluck('c', 'state');

        return view('zaban-admin.reports', [
            'reports' => $reports,
            'msgs'    => $msgs,
            'state'   => $state,
            'who'     => $who,
            'counts'  => [
                'open'     => (int) ($counts['open'] ?? 0),
                'answered' => (int) ($counts['answered'] ?? 0),
            ],
        ]);
    }

    public function reply(Request $req, int $id)
    {
        $d = $req->validate(['body' => ['required', 'string', 'max:4000']]);

        abort_unless(DB::table('reports')->where('id', $id)->exists(), 404);

        $now = now();
        DB::transaction(function () use ($id, $d, $req, $now) {
            DB::table('report_messages')->insert([
                'report_id' => $id, 'by' => 'admin', 'admin_id' => $req->user()->id,
                'body' => $d['body'], 'created_at' => $now,
            ]);
            DB::table('reports')->where('id', $id)->update([
                'state' => 'answered', 'seen_at' => null,
                'last_msg_at' => $now, 'updated_at' => $now,
            ]);
        });

        return back()->with('ok', 'پاسخ برای کاربر فرستاده شد.');
    }

    public function destroy(int $id)
    {
        /* پیام‌ها را صریح پاک می‌کنیم؛ روی SQLite (محیط تست) کلید خارجی
           به‌طور پیش‌فرض خاموش است و cascade اجرا نمی‌شود. */
        DB::transaction(function () use ($id) {
            DB::table('report_messages')->where('report_id', $id)->delete();
            DB::table('reports')->where('id', $id)->delete();
        });

        return back()->with('ok', 'گزارش حذف شد.');
    }
}
