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
