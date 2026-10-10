<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * php artisan zaban:secure-report — گزارش شبانه‌ی رفتار مشکوک (کیت امنیت، بخش ۲-۱۰).
 *
 * هر روز ساعت ۵ صبح با cron (routes/console.php). نتیجه در فایل
 * storage/app/secure-report.json ذخیره می‌شود (ستون v در zaban_meta برای این
 * حجم کوتاه است) و در پنل، «گزارش امنیتی شبانه» نشان داده می‌شود.
 * فقط می‌خواند و گزارش می‌سازد؛ کسی را قفل نمی‌کند (قفل خودکار کار AbuseGuard است).
 *
 * بازه: ۲۴ ساعت گذشته.
 *   ۱) پرمصرف‌ها: کلمه، معنی و پاسخِ تازه‌ای که هر کاربر باز کرده
 *   ۲) هشدارهای امنیتی ۲۴ ساعت گذشته، به تفکیک نوع
 *   ۳) رویدادهای محافظ مرورگر: کپی، کلیک راست، پنهان شدن صفحه، ابزار توسعه، PrintScreen
 *   ۴) حساب‌هایی که الان قفل‌اند
 *   ۵) خلاصه‌ی پرداخت‌ها
 */
class SecureReport extends Command
{
    protected $signature = 'zaban:secure-report {--quiet-output : فقط ذخیره، بدون چاپ}';
    protected $description = 'گزارش شبانه‌ی رفتار مشکوک (فقط گزارش)';

    public function handle(): int
    {
        $since = now()->subDay();
        $r = [
            'built_at' => now()->toDateTimeString(),
            'since'    => $since->toDateTimeString(),
            'heavy'    => $this->heavy($since),
            'alerts'   => $this->alerts($since),
            'events'   => $this->events($since),
            'locked'   => $this->locked(),
            'pay'      => $this->payments($since),
        ];

        file_put_contents(self::path(), json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);

        if (!$this->option('quiet-output')) {
            $this->info('گزارش ساخته شد (' . $r['built_at'] . ').');
            $this->line('پرمصرف‌ها: ' . count($r['heavy']) . ' · هشدارها: ' . array_sum(array_column($r['alerts'], 'n'))
                . ' · رویدادهای محافظ: ' . count($r['events']) . ' · قفل‌شده: ' . count($r['locked']));
        }
        return self::SUCCESS;
    }

    public static function path(): string
    {
        return storage_path('app/secure-report.json');
    }

    /** ۱۰ کاربرِ پرمصرف — مجموع کلمه + معنی + پاسخِ تازه‌ی باز شده */
    private function heavy($since): array
    {
        $count = function (string $table) use ($since) {
            if (!Schema::hasTable($table)) return collect();
            return DB::table($table)->where('created_at', '>=', $since)
                ->groupBy('user_id')->selectRaw('user_id, COUNT(*) AS n')->pluck('n', 'user_id');
        };
        $w = $count('word_reveals'); $m = $count('meaning_reveals'); $a = $count('answer_reveals');

        $tot = [];
        foreach ([$w, $m, $a] as $set) foreach ($set as $uid => $n) $tot[$uid] = ($tot[$uid] ?? 0) + (int) $n;
        arsort($tot);
        $top = array_slice($tot, 0, 10, true);
        if (!$top) return [];

        $users = DB::table('users')->whereIn('id', array_keys($top))->get(['id', 'mobile', 'name', 'type'])->keyBy('id');
        $out = [];
        foreach ($top as $uid => $n) {
            $u = $users[$uid] ?? null;
            if ($u && in_array($u->type, \App\Support\Roles::STAFF, true)) continue;   /* کارکنان نه */
            $out[] = ['user' => (int) $uid, 'mobile' => $u->mobile ?? '', 'name' => $u->name ?? '',
                      'words' => (int) ($w[$uid] ?? 0), 'meanings' => (int) ($m[$uid] ?? 0),
                      'answers' => (int) ($a[$uid] ?? 0), 'total' => (int) $n];
        }
        return $out;
    }

    private function alerts($since): array
    {
        return DB::table('security_alerts as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.created_at', '>=', $since)
            ->groupBy('a.kind')
            ->selectRaw('a.kind, COUNT(*) AS n, COUNT(DISTINCT a.user_id) AS users')
            ->orderByDesc('n')->get()
            ->map(fn ($x) => ['kind' => $x->kind, 'n' => (int) $x->n, 'users' => (int) $x->users])->all();
    }

    private function events($since): array
    {
        if (!Schema::hasTable('zaban_client_events')) return [];
        $rows = DB::table('zaban_client_events as e')->join('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.day', '>=', $since->toDateString())
            ->groupBy('e.user_id', 'u.mobile')
            ->selectRaw("e.user_id, u.mobile,
                SUM(CASE WHEN e.kind = 'copy' THEN e.n ELSE 0 END) AS copy,
                SUM(CASE WHEN e.kind = 'contextmenu' THEN e.n ELSE 0 END) AS ctx,
                SUM(CASE WHEN e.kind = 'hide' THEN e.n ELSE 0 END) AS hide,
                SUM(CASE WHEN e.kind = 'devtools' THEN e.n ELSE 0 END) AS dev,
                SUM(CASE WHEN e.kind IN ('printscreen', 'print') THEN e.n ELSE 0 END) AS shot,
                SUM(CASE WHEN e.kind = 'key' THEN e.n ELSE 0 END) AS keyn")
            ->get();
        /* مرتب‌سازی: ابزار توسعه و اسکرین‌شات مهم‌تر از پنهان شدن معمولی صفحه‌اند */
        return $rows->map(fn ($x) => ['user' => (int) $x->user_id, 'mobile' => $x->mobile,
                'copy' => (int) $x->copy, 'ctx' => (int) $x->ctx, 'hide' => (int) $x->hide,
                'dev' => (int) $x->dev, 'shot' => (int) $x->shot, 'key' => (int) $x->keyn,
                'score' => 5 * ((int) $x->dev + (int) $x->shot) + 2 * (int) $x->copy + (int) $x->ctx + (int) $x->keyn])
            ->filter(fn ($x) => $x['score'] > 0)
            ->sortByDesc('score')->take(15)->values()->all();
    }

    private function locked(): array
    {
        return DB::table('users')->whereNotNull('locked_until')->where('locked_until', '>', now())
            ->orderByDesc('locked_until')->limit(30)
            ->get(['id', 'mobile', 'locked_until', 'lock_reason'])
            ->map(fn ($u) => ['user' => (int) $u->id, 'mobile' => $u->mobile,
                              'until' => (string) $u->locked_until, 'reason' => (string) $u->lock_reason])->all();
    }

    private function payments($since): array
    {
        $q = fn () => DB::table('zaban_orders')->where('updated_at', '>=', $since);
        return [
            'paid'     => (int) $q()->where('status', 'paid')->count(),
            'paid_sum' => (int) $q()->where('status', 'paid')->sum('payable'),
            'failed'   => (int) $q()->whereIn('status', ['failed', 'expired'])->count(),
            'stuck'    => (int) DB::table('zaban_orders')->whereIn('status', ['pending', 'verifying'])
                              ->whereNotNull('token')->where('created_at', '<', now()->subMinutes(30))->count(),
        ];
    }
}
