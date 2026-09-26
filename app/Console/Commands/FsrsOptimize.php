<?php

namespace App\Console\Commands;

use App\Services\Fsrs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * آموزش وزن‌های FSRS از روی تاریخچه‌ی واقعی مرور.
 *
 * روش کار:
 *   ۱. تاریخچه‌ی هر کارت را از review_logs می‌خوانیم: دنباله‌ی (فاصله، درجه).
 *   ۲. کارت‌ها را ۸۰ به ۲۰ تقسیم می‌کنیم. تقسیم روی «کارت» است نه روی «مرور»،
 *      چون مرورهای یک کارت به هم وابسته‌اند و اگر پخش شوند، مدل جواب را از
 *      روی مرور قبلیِ همان کارت حدس می‌زند و نتیجه‌ی آزمون دروغ درمی‌آید.
 *   ۳. روی ۸۰٪ وزن‌ها را یکی‌یکی کم و زیاد می‌کنیم و هر تغییری که خطا را کم
 *      کند نگه می‌داریم (coordinate descent).
 *   ۴. آخر سر، وزن تازه و وزن پیش‌فرض را روی همان ۲۰٪ کنارگذاشته می‌سنجیم.
 *      فقط اگر وزن تازه واقعاً بهتر بود پذیرفته می‌شود.
 *
 * معیار خطا: log-loss بین احتمال یادآوریِ پیش‌بینی‌شده و اتفاقی که واقعاً افتاد
 * (درجه‌ی ۱ یعنی فراموش، بقیه یعنی یادآوری). عدد کمتر بهتر است.
 *
 * ⚠ بدترین حالت این دستور «کمتر بهتر شدن» است، نه «بدتر شدن»: وزنی که آزمون
 * پذیرش را رد کند با accepted=0 ذخیره می‌شود و هیچ‌وقت استفاده نمی‌شود.
 *
 * استفاده:
 *   php artisan zaban:fsrs-optimize                 همه‌ی کاربرانِ واجد شرایط + عمومی
 *   php artisan zaban:fsrs-optimize --user=12
 *   php artisan zaban:fsrs-optimize --global        فقط تنظیم عمومی پلتفرم
 *   php artisan zaban:fsrs-optimize --dry           چیزی ذخیره نکن
 */
class FsrsOptimize extends Command
{
    protected $signature = 'zaban:fsrs-optimize
        {--user=        : فقط یک کاربر}
        {--global       : فقط تنظیم عمومی کل پلتفرم}
        {--min-reviews=400 : کمینه‌ی مرور برای اینکه آموزش معنی داشته باشد}
        {--min-cards=60 : کمینه‌ی کارت}
        {--passes=3     : چند دور روی همه‌ی وزن‌ها}
        {--dry          : فقط گزارش بده، ذخیره نکن}';

    protected $description = 'آموزش وزن‌های FSRS از روی مرورهای واقعی، با آزمون پذیرش';

    /** وزن‌هایی که تغییرشان بی‌خطر و مؤثر است (بقیه ساختار مدل را جابه‌جا می‌کنند) */
    private const TUNABLE = [0, 1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];

    /** کف و سقف هر وزن — بیرون از این بازه مدل بی‌معنا می‌شود */
    private const BOUNDS = [
        0 => [0.01, 30], 1 => [0.01, 30], 2 => [0.01, 30], 3 => [0.01, 30],
        4 => [1, 10],    5 => [0.001, 4], 6 => [0.001, 4],  8 => [0, 5],
        9 => [0, 1],     10 => [0.01, 6], 11 => [0.01, 6],  12 => [0.001, 1],
        13 => [0.001, 1], 14 => [0.01, 6], 15 => [0, 1],    16 => [1, 6],
        17 => [0, 2],    18 => [0, 1],    19 => [0, 0.8],
    ];

    public function handle(): int
    {
        $minReviews = (int) $this->option('min-reviews');
        $minCards   = (int) $this->option('min-cards');

        $targets = [];

        if ($this->option('user')) {
            $targets[] = ['user' => (int) $this->option('user'), 'exam' => ''];
        } elseif ($this->option('global')) {
            $targets[] = ['user' => 0, 'exam' => ''];
        } else {
            /* عمومی همیشه، به‌علاوه‌ی هر کاربری که داده‌ی کافی دارد */
            $targets[] = ['user' => 0, 'exam' => ''];
            $users = DB::table('review_logs')
                ->selectRaw('user_id, COUNT(*) AS n')
                ->groupBy('user_id')->havingRaw('COUNT(*) >= ?', [$minReviews])
                ->pluck('user_id');
            foreach ($users as $u) $targets[] = ['user' => (int) $u, 'exam' => ''];
        }

        foreach ($targets as $t) {
            $this->line('');
            $label = $t['user'] === 0 ? 'عمومی کل پلتفرم' : 'کاربر ' . $t['user'];
            $this->info("── {$label}");

            $hist = $this->histories($t['user']);
            $cards = count($hist);
            $revs  = array_sum(array_map('count', $hist));

            if ($revs < $minReviews || $cards < $minCards) {
                $this->line("  داده کافی نیست: {$revs} مرور روی {$cards} کارت"
                    . " (کمینه {$minReviews} و {$minCards}) — رد شد");
                continue;
            }

            [$train, $test] = $this->split($hist);
            $this->line('  ' . count($train) . ' کارت آموزش · ' . count($test) . ' کارت آزمون');

            $base = Fsrs::DEFAULT_W;
            $lossBefore = $this->loss($test, $base);

            $tuned = $this->optimize($train, $base, (int) $this->option('passes'));
            $lossAfter = $this->loss($test, $tuned);

            /* حاشیه‌ی یک درصد: بهبود ناچیز ارزش عوض کردن مدل را ندارد */
            $accept = $lossAfter < $lossBefore * 0.99;

            $this->line(sprintf('  خطای پیش‌فرض %.5f · خطای تازه %.5f → %s',
                $lossBefore, $lossAfter, $accept ? 'پذیرفته شد' : 'پذیرفته نشد'));

            if ($this->option('dry')) { $this->line('  (حالت آزمایشی — ذخیره نشد)'); continue; }

            DB::table('zaban_fsrs_params')->updateOrInsert(
                ['user_id' => $t['user'], 'exam' => $t['exam']],
                [
                    'w'              => json_encode(array_map(fn ($x) => round($x, 6), $tuned)),
                    'sample_reviews' => $revs,
                    'sample_cards'   => $cards,
                    'loss_default'   => round($lossBefore, 5),
                    'loss_tuned'     => round($lossAfter, 5),
                    'accepted'       => $accept,
                    'trained_at'     => now(),
                    'updated_at'     => now(),
                    'created_at'     => now(),
                ]
            );
        }

        $this->line('');
        $this->info('تمام شد.');
        return self::SUCCESS;
    }

    /* ---------------------------------------------------------------- */

    /**
     * تاریخچه‌ی هر کارت: [[فاصله، درجه], …] به ترتیب زمان.
     *
     * @return array<string, array<int, array{0:int,1:int}>>
     */
    private function histories(int $userId): array
    {
        $q = DB::table('review_logs')
            ->select('user_id', 'item_type', 'item_id', 'rating', 'elapsed_days', 'created_at')
            ->orderBy('user_id')->orderBy('item_type')->orderBy('item_id')->orderBy('created_at');

        if ($userId > 0) $q->where('user_id', $userId);

        $out = [];
        foreach ($q->cursor() as $r) {
            $key = $r->user_id . ':' . $r->item_type . ':' . $r->item_id;
            $out[$key][] = [(int) $r->elapsed_days, (int) $r->rating];
        }

        /* کارتی با یک مرور چیزی برای پیش‌بینی ندارد */
        return array_filter($out, fn ($h) => count($h) >= 2);
    }

    /**
     * تقسیم ۸۰ به ۲۰ روی کارت‌ها — تکرارپذیر، تا دو اجرای پیاپی قابل مقایسه باشند.
     */
    private function split(array $hist): array
    {
        $train = $test = [];
        foreach ($hist as $key => $h) {
            (crc32($key) % 5 === 0) ? $test[$key] = $h : $train[$key] = $h;
        }
        /* اگر آزمون خالی ماند (داده‌ی خیلی کم)، همان آموزش را برمی‌گردانیم تا
           آزمون پذیرش بی‌معنی نشود؛ در عمل با حداقل‌های بالا پیش نمی‌آید. */
        return $test ? [$train, $test] : [$hist, $hist];
    }

    /**
     * log-loss روی مجموعه‌ای از تاریخچه‌ها با وزن‌های داده‌شده.
     *
     * برای هر مرور (به‌جز اولی)، احتمال یادآوری را پیش‌بینی می‌کنیم و با اتفاق
     * واقعی می‌سنجیم. مرور همان‌روز کنار گذاشته می‌شود؛ آنجا R تقریباً یک است
     * و چیزی به سنجش اضافه نمی‌کند.
     */
    private function loss(array $hist, array $w): float
    {
        $f = new Fsrs($w);
        $sum = 0.0; $n = 0;

        foreach ($hist as $h) {
            $state = null;
            foreach ($h as [$elapsed, $rating]) {
                if ($state !== null && $elapsed >= 1) {
                    $p = $f->retrievability($elapsed, $state['stability']);
                    $p = min(max($p, 1e-6), 1 - 1e-6);
                    $y = $rating > 1 ? 1 : 0;
                    $sum += -($y * log($p) + (1 - $y) * log(1 - $p));
                    $n++;
                }
                $next  = $f->review($state, $rating, $elapsed);
                $state = ['stability' => $next['stability'], 'difficulty' => $next['difficulty']];
            }
        }

        return $n ? $sum / $n : INF;
    }

    /**
     * جست‌وجوی مختصاتی: هر وزن را جدا کم و زیاد می‌کنیم و بهترین را نگه می‌داریم.
     *
     * ساده‌تر از بهینه‌ساز رسمی است و به جواب بهینه نمی‌رسد، ولی چون نتیجه‌اش
     * با آزمون پذیرش سنجیده می‌شود، بدترین پیامدش «بهبود کمتر» است نه «بدتر شدن».
     */
    private function optimize(array $train, array $w, int $passes): array
    {
        $best     = $w;
        $bestLoss = $this->loss($train, $best);

        foreach (range(1, max(1, $passes)) as $pass) {
            $step = 0.20 / $pass;                    // هر دور ریزتر
            $improved = false;

            foreach (self::TUNABLE as $i) {
                [$lo, $hi] = self::BOUNDS[$i] ?? [0.0, 10.0];

                foreach ([1 + $step, 1 - $step] as $mul) {
                    $cand = $best;
                    $cand[$i] = min($hi, max($lo, $best[$i] * $mul));
                    if (abs($cand[$i] - $best[$i]) < 1e-9) continue;

                    $l = $this->loss($train, $cand);
                    if ($l < $bestLoss - 1e-7) {
                        $best = $cand; $bestLoss = $l; $improved = true;
                    }
                }
            }

            $this->line(sprintf('    دور %d — خطای آموزش %.5f', $pass, $bestLoss));
            if (!$improved) break;
        }

        return $best;
    }
}
