<?php

namespace App\Console\Commands;

use App\Support\Meanings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan zaban:clean-meanings [--check]
 *
 * معنی‌های تکراری را از ستون meaning_fa پاک می‌کند — نتیجه‌ی ادغامِ چندباره‌ی
 * ایمپورت (توضیح کامل در App\Support\Meanings).
 *
 * با --check چیزی نوشته نمی‌شود و فقط نمونه‌ها را نشان می‌دهد. اول همین را
 * بزنید، خروجی را نگاه کنید، بعد بدون --check اجرا کنید.
 *
 * بی‌خطر است و چند بار اجرا شدنش اشکالی ندارد: خروجی‌اش با خودش ثابت می‌ماند.
 */
class CleanMeanings extends Command
{
    protected $signature   = 'zaban:clean-meanings {--check : فقط گزارش، بدون تغییر}';
    protected $description = 'یکتا کردن معنی‌های فارسی کلمه‌ها (حذف تکرارهای ایمپورت)';

    public function handle(): int
    {
        $check   = (bool) $this->option('check');
        $changed = 0;
        $seen    = 0;
        $samples = [];

        DB::table('words')->whereNotNull('meaning_fa')->where('meaning_fa', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$changed, &$seen, &$samples, $check) {
                $updates = [];
                foreach ($rows as $w) {
                    $seen++;
                    $new = Meanings::normalize($w->meaning_fa);
                    if ($new === '' || $new === $w->meaning_fa) continue;

                    $changed++;
                    if (count($samples) < 10) $samples[] = [$w->word, $w->meaning_fa, $new];
                    $updates[$w->id] = $new;
                }

                if (!$check && $updates) {
                    foreach ($updates as $id => $fa) {
                        DB::table('words')->where('id', $id)->update(['meaning_fa' => $fa]);
                    }
                }
            });

        foreach ($samples as [$word, $old, $new]) {
            $this->line("  <fg=yellow>{$word}</>");
            $this->line("    از: {$old}");
            $this->line("    به: {$new}");
        }

        $this->line('');
        $this->info("  {$seen} کلمه بررسی شد؛ {$changed} کلمه معنی تکراری داشت"
            . ($check ? ' (چیزی نوشته نشد — برای اعمال، بدون --check اجرا کنید).' : ' و اصلاح شد.'));

        if (!$check && $changed) {
            $this->line('  حالا کش محتوا را تازه کنید: php artisan optimize:clear');
        }

        return self::SUCCESS;
    }
}
