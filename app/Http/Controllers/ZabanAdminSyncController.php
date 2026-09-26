<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * پنل مدیریت — آوردن سؤال‌های تازه از پلتفرم آزمون (دیتابیس azmoon).
 *
 * اپراتورها سؤال‌ها را در پلتفرم آزمون وارد می‌کنند؛ مدیر اینجا با یک دکمه
 * همان کار دستورهای خط فرمان را انجام می‌دهد:
 *   php artisan zaban:sync-questions        ← سال، ساختار دفترچه، متن‌ها، سؤال‌ها، گزینه‌ها
 *   php artisan zaban:import finalize       ← اتصال سؤال‌ها به کلمات و متن‌ها
 * دفترچه‌ای که با بانک کلمات نخواند وارد نمی‌شود (همان رفتار پیش‌فرض دستور).
 */
class ZabanAdminSyncController extends Controller
{
    private const LOCK = 'zaban.sync.lock';
    private const LAST = 'zaban.sync.last';
    private const RUNNING = 'zaban.sync.running';   /* فقط برای نمایش؛ جلوگیری از هم‌زمانی با LOCK است */

    public function index(): View
    {
        return view('zaban-admin.sync', [
            'last'    => Cache::get(self::LAST),
            'running' => Cache::get(self::RUNNING),
            'gaps'    => $this->gaps(),
        ]);
    }

    /** فقط بررسی — چیزی نوشته نمی‌شود */
    public function check(): RedirectResponse
    {
        return $this->run(true);
    }

    /** همگام‌سازی واقعی */
    public function apply(): RedirectResponse
    {
        return $this->run(false);
    }

    private function run(bool $checkOnly): RedirectResponse
    {
        $lock = Cache::lock(self::LOCK, 900);
        if (!$lock->get()) {
            return back()->with('ok', 'یک همگام‌سازی دیگر در حال اجراست. چند دقیقه‌ی دیگر دوباره نگاه کنید.');
        }

        /* اگر مدیر صفحه را ببندد، کار نیمه‌کاره نمی‌ماند */
        ignore_user_abort(true);
        @set_time_limit(900);

        $started = now();
        Cache::put(self::RUNNING, $started->format('H:i'), 900);
        $log = '';
        try {
            $code = Artisan::call('zaban:sync-questions', $checkOnly ? ['--check' => true] : []);
            $log .= Artisan::output();

            if (!$checkOnly && $code === 0) {
                $log .= "\n────────── اتصال سؤال‌ها به کلمات و متن‌ها (finalize) ──────────\n";
                Artisan::call('zaban:import', ['what' => 'finalize']);
                $log .= Artisan::output();
            }
            $status = $checkOnly ? 'check' : ($code === 0 ? 'done' : 'failed');
        } catch (\Throwable $e) {
            $log .= "\nخطا: " . $e->getMessage();
            $status = 'failed';
        } finally {
            Cache::forget(self::RUNNING);
            $lock->release();
        }

        Cache::forever(self::LAST, [
            'status'  => $status,
            'by'      => auth()->user()->name ?? auth()->id(),
            'at'      => $started->format('Y-m-d H:i'),
            'seconds' => $started->diffInSeconds(now(), true),
            'log'     => $log,
        ]);

        return redirect()->route('zadmin.sync')->with('ok', match ($status) {
            'check'  => 'بررسی انجام شد — چیزی تغییر نکرد. گزارش پایین صفحه است.',
            'done'   => 'سؤال‌های تازه به سایت زبان اضافه شد.',
            default  => 'همگام‌سازی با خطا تمام شد. گزارش پایین صفحه را ببینید.',
        });
    }

    /**
     * خروجی خام دستور را به جدول قابل خواندن تبدیل می‌کند.
     *
     * متن خام در یک <pre> به‌هم می‌ریخت: هر خط ترکیب فارسی و لاتین است
     * («✓ 1404 ce — 25 سؤال») و مرورگر جهت را برای هر خط جدا حدس می‌زد،
     * پس سال و رشته گاهی آخر خط می‌افتاد. حالا هر خط جدا می‌شود و هر تکه
     * در خانه‌ی خودش با جهت درست نمایش داده می‌شود.
     *
     * خروجی: ['rows' => [...], 'ok' => n, 'bad' => n, 'skip' => n, 'rest' => "..."]
     */
    public static function parseLog(string $log): array
    {
        $fa   = ['ce' => 'مهندسی کامپیوتر', 'it' => 'آی‌تی', 'cs' => 'علوم کامپیوتر'];
        $rows = [];
        $rest = [];
        $seen = [];   /* برای اینکه فهرست تکراری انتهای گزارش دوباره نیاید */

        foreach (preg_split('/\R/u', $log) as $line) {
            $t = trim($line);
            if ($t === '') continue;

            /* «✓ 1404 ce — 25 سؤال» یا «! 1403 cs — …» یا «- 1402 cs — خالی، رد شد» */
            if (preg_match('/^([✓!\-])\s+(\d{4})\s+(ce|it|cs)\s+—\s+(.+)$/u', $t, $m)) {
                $key = $m[2] . $m[3];
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $rows[] = [
                    'state' => $m[1] === '✓' ? 'ok' : ($m[1] === '!' ? 'bad' : 'skip'),
                    'year'  => $m[2],
                    'exam'  => $fa[$m[3]] ?? $m[3],
                    'code'  => $m[3],
                    'note'  => trim($m[4]),
                ];
                continue;
            }

            /* فهرست تکراری انتهای گزارش: «1403 cs: بخش …» */
            if (preg_match('/^(\d{4})\s+(ce|it|cs):/u', $t)) continue;

            /* سرشماری‌ها و خطوط توضیحی را جدا نگه می‌داریم */
            if (preg_match('/^(همخوان|دفترچه‌های ناهمخوان)/u', $t)) continue;
            if (preg_match('/^\d+ دفترچه پیدا شد/u', $t)) continue;

            $rest[] = $t;
        }

        return [
            'rows' => $rows,
            'ok'   => count(array_filter($rows, fn ($r) => $r['state'] === 'ok')),
            'bad'  => count(array_filter($rows, fn ($r) => $r['state'] === 'bad')),
            'skip' => count(array_filter($rows, fn ($r) => $r['state'] === 'skip')),
            'rest' => implode("\n", $rest),
        ];
    }

    /**
     * سال‌هایی که در بانک کلمات هستند ولی سؤالشان در سایت نیست — دفترچه‌ای که
     * اصلاً در پلتفرم آزمون ساخته نشده در گزارش «دفترچه‌های ناقص» داشبورد نمی‌آمد.
     */
    private function gaps(): array
    {
        $words = DB::table('word_occurrences')->selectRaw('year, exam')->distinct()->get();
        $qs    = DB::table('questions')->select('year', 'exam')->distinct()->get()
            ->mapWithKeys(fn ($q) => [$q->year . '|' . $q->exam => true]);

        $out = [];
        foreach ($words as $w) {
            if (!isset($qs[$w->year . '|' . $w->exam])) $out[] = ['year' => (int) $w->year, 'exam' => $w->exam];
        }
        usort($out, fn ($a, $b) => [$b['year'], $a['exam']] <=> [$a['year'], $b['exam']]);
        return $out;
    }
}
