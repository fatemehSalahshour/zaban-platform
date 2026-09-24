<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * پر کردن board_cache.
 *
 * بدون این، هر بار باز شدن تب رتبه‌بندی یک GROUP BY روی کل daily_activity است.
 *
 *   php artisan zaban:board
 */
class BuildBoard extends Command
{
    protected $signature = 'zaban:board {--period= : فقط یک بازه، مثلاً d7}';
    protected $description = 'ساخت جدول تجمیعی رتبه‌بندی';

    private const PERIODS = ['d1' => 1, 'd7' => 7, 'd30' => 30, 'd60' => 60,
                             'd90' => 90, 'd180' => 180, 'd365' => 365];

    public function handle(): int
    {
        $only    = $this->option('period');
        $startedAt = now();

        foreach (self::PERIODS as $period => $days) {
            if ($only && $only !== $period) continue;

            DB::statement("
                REPLACE INTO board_cache
                  (period,user_id,points,study_sec,reviews,reviews_ok,
                   questions,questions_ok,mastered,built_at)
                SELECT ?, a.user_id,
                  ROUND( SUM(a.study_sec)/60
                       + SUM(a.reviews)   * 2 * (0.5 + 0.5*IFNULL(SUM(a.reviews_ok)/NULLIF(SUM(a.reviews),0),0.5))
                       + SUM(a.questions) * 3 * (0.5 + 0.5*IFNULL(SUM(a.questions_ok)/NULLIF(SUM(a.questions),0),0.5))
                       + SUM(a.mastered)  * 25 ),
                  SUM(a.study_sec), SUM(a.reviews), SUM(a.reviews_ok),
                  SUM(a.questions), SUM(a.questions_ok), SUM(a.mastered), NOW()
                FROM daily_activity a
                LEFT JOIN zaban_profiles p ON p.user_id = a.user_id
                WHERE a.day >= CURDATE() - INTERVAL $days DAY
                  AND COALESCE(p.show_in_board, 1) = 1
                GROUP BY a.user_id
            ", [$period]);

            // رتبه‌ها
            DB::statement('SET @r := 0');
            DB::statement(
                'UPDATE board_cache SET rank_no = (@r := @r + 1)
                  WHERE period = ? ORDER BY points DESC', [$period]
            );

            // ردیف‌های کاربرانی که در این بازه دیگر فعالیتی ندارند
            DB::table('board_cache')->where('period', $period)
                ->where('built_at', '<', $startedAt)->delete();

            $this->line("  $period ✓");
        }

        $this->info('رتبه‌بندی ساخته شد.');
        return 0;
    }
}
