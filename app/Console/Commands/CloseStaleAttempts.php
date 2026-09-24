<?php

namespace App\Console\Commands;

use App\Http\Controllers\ZabanController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * بستن آزمون‌هایی که مهلتشان گذشته و کاربر برنگشته.
 *
 * بدون این، کارت زرد «آزمون نیمه‌تمام» تا ابد در داشبورد می‌ماند و
 * examStart هم اجازه‌ی شروع آزمون تازه نمی‌دهد.
 *
 *   php artisan zaban:close-stale-attempts
 */
class CloseStaleAttempts extends Command
{
    protected $signature = 'zaban:close-stale-attempts {--grace=0 : ثانیه‌ی مهلت اضافه}';
    protected $description = 'بستن و تصحیح آزمون‌های رهاشده';

    public function handle(ZabanController $ctrl): int
    {
        $grace = (int) $this->option('grace');

        $ids = DB::table('exam_attempts')
            ->whereNull('finished_at')
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', now()->subSeconds($grace))
            ->pluck('id');

        // آزمون بدون زمان‌سنج که سه روز دست‌نخورده مانده هم رها شده حساب می‌شود.
        $abandoned = DB::table('exam_attempts')
            ->whereNull('finished_at')->whereNull('deadline_at')
            ->where('started_at', '<', now()->subDays(3))
            ->pluck('id');

        $n = 0;
        foreach ($ids->merge($abandoned) as $id) {
            try {
                $ctrl->grade((int) $id, true);
                $n++;
            } catch (\Throwable $e) {
                $this->error("آزمون $id: " . $e->getMessage());
            }
        }

        $this->info("$n آزمون بسته و تصحیح شد.");
        return 0;
    }
}
