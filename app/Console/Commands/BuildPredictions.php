<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * محاسبه‌ی شبانه‌ی امتیاز پیش‌بینی — همان فرمولی که در پروتوتایپ اجرا می‌شود.
 * چهار مؤلفه: فراوانی، تازگی، سررسید، پیوستگی.
 * خروجی در ستون‌های words.pred_* می‌نشیند تا مرورگر چیزی حساب نکند.
 *
 *   php artisan zaban:predict --target=1406
 */
class BuildPredictions extends Command
{
    protected $signature = 'zaban:predict {--target=} {--exam=}';
    protected $description = 'ساخت امتیاز پیش‌بینی کلمات';

    public function handle(): int
    {
        $target = (int) ($this->option('target') ?: (DB::table('word_occurrences')->max('year') + 1));
        $this->info("هدف: کنکور $target");

        $rows = DB::table('word_occurrences')
            ->when($this->option('exam'), fn ($q, $e) => $q->where('exam', $e))
            ->selectRaw('word_id, GROUP_CONCAT(DISTINCT year ORDER BY year) years, COUNT(*) tot')
            ->groupBy('word_id')->get();

        if ($rows->isEmpty()) { $this->warn('ظهوری پیدا نشد.'); return 1; }

        $calc = [];
        foreach ($rows as $r) {
            $ys    = array_map('intval', explode(',', $r->years));
            $n     = count($ys);
            $first = $ys[0]; $last = $ys[$n - 1];
            $gap   = $target - $last;
            $mean  = $n > 1 ? ($last - $first) / ($n - 1) : max($gap, 8);

            $recency = 0.0;
            foreach ($ys as $y) $recency += pow(0.86, $target - 1 - $y);

            $streak = 0;
            for ($k = 0; ; $k++) { if (in_array($target - 1 - $k, $ys, true)) $streak++; else break; }

            [$runLen, $runFrom] = $this->longestRun($ys);
            $due = exp(-pow(($gap - $mean) / ($mean * 0.9 + 1), 2));

            $calc[$r->word_id] = compact('n', 'mean', 'recency', 'streak', 'due', 'runLen', 'runFrom')
                               + ['tot' => (int) $r->tot];
        }

        $maxN = max(array_column($calc, 'n'));
        $maxR = max(array_column($calc, 'recency'));
        $maxT = max(array_column($calc, 'tot'));

        $raw = [];
        foreach ($calc as $id => $c) {
            $F = ($c['n'] / $maxN) * 0.75 + ($c['tot'] / $maxT) * 0.25;
            $raw[$id] = 0.34 * $F + 0.28 * ($c['recency'] / $maxR)
                      + 0.22 * $c['due'] + 0.16 * min($c['streak'], 4) / 4;
        }
        arsort($raw);
        $top = max($raw) ?: 1;

        $rank = 0;
        DB::transaction(function () use ($raw, $calc, $top, &$rank) {
            foreach ($raw as $id => $v) {
                $c = $calc[$id];
                DB::table('words')->where('id', $id)->update([
                    'pred_score'    => round($v / $top * 97, 2),
                    'pred_rank'     => ++$rank,
                    'mean_gap'      => round($c['mean'], 2),
                    'streak_recent' => min($c['streak'], 255),
                    'run_longest'   => min($c['runLen'], 255),
                    'run_from'      => $c['runFrom'] ?: null,
                    'run_to'        => $c['runFrom'] ? $c['runFrom'] + $c['runLen'] - 1 : null,
                ]);
            }
        });

        \Illuminate\Support\Facades\Cache::forget('zaban.content.ce');
        \Illuminate\Support\Facades\Cache::forget('zaban.content.it');
        \Illuminate\Support\Facades\Cache::forget('zaban.content.cs');

        $this->info("$rank کلمه امتیازدهی شد.");
        return 0;
    }

    /** بلندترین دوره‌ی پیاپی — همان چیزی که در تب کلمات نشان داده می‌شود */
    private function longestRun(array $ys): array
    {
        $best = 0; $bestFrom = 0; $cur = 0; $curFrom = 0;
        foreach ($ys as $i => $y) {
            if ($i && $y === $ys[$i - 1] + 1) $cur++;
            else { $cur = 1; $curFrom = $y; }
            if ($cur > $best) { $best = $cur; $bestFrom = $curFrom; }
        }
        return [$best, $bestFrom];
    }
}
