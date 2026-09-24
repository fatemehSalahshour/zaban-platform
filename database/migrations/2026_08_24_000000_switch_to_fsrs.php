<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * گذار از SM-2 به FSRS-6.
 *
 * ستون‌های SM-2 پاک نمی‌شوند. دو دلیل:
 *   ۱) اگر FSRS جواب نداد، باید بشود برگشت
 *   ۲) ease_factor برای بازسازی دشواری کارت‌های موجود لازم است
 *
 * تبدیل در همین migration انجام می‌شود: بازه‌ی فعلی می‌شود پایداری،
 * و از ease_factor دشواری بازسازی می‌شود. دقیق نیست ولی از صفر
 * شروع کردن خیلی بهتر است — کاربر تاریخچه‌اش را از دست نمی‌دهد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deck_items', function (Blueprint $t) {
            $t->decimal('stability', 10, 4)->nullable()->comment('S — روز تا افت احتمال یادآوری به ۹۰٪');
            $t->decimal('difficulty', 5, 2)->nullable()->comment('D — بین ۱ و ۱۰');
            $t->unsignedSmallInteger('elapsed_days')->default(0)->comment('فاصله‌ی مرور قبلی، برای محاسبه‌ی R');
            $t->string('scheduler', 8)->default('fsrs6')->comment('sm2 | fsrs6 — تا بشود تدریجی مهاجرت داد');
            $t->index(['user_id', 'due_date'], 'deck_due');
        });

        // تبدیل کارت‌های موجود
        $w = \App\Services\Fsrs::DEFAULT_W;
        $expW8  = exp($w[8]);
        $expW10 = exp(0.1 * $w[10]);

        DB::table('deck_items')->whereNull('stability')->orderBy('id')
            ->chunkById(500, function ($rows) use ($w, $expW8, $expW10) {
                foreach ($rows as $r) {
                    $s = max((float) $r->interval_days, 0.1);
                    $d = 11 - ((float) $r->ease_factor - 1)
                       / ($expW8 * pow($s, -$w[9]) * ($expW10 - 1));
                    $d = min(max(round($d, 2), 1.0), 10.0);

                    DB::table('deck_items')->where('id', $r->id)->update([
                        'stability'  => round($s, 4),
                        'difficulty' => $d,
                        'scheduler'  => 'fsrs6',
                    ]);
                }
            });

        Schema::table('review_logs', function (Blueprint $t) {
            $t->decimal('stability', 10, 4)->nullable();
            $t->decimal('difficulty', 5, 2)->nullable();
            $t->decimal('retrievability', 5, 4)->nullable()->comment('R در لحظه‌ی مرور — برای optimizer');
            $t->unsignedSmallInteger('elapsed_days')->default(0);
        });

        // هدف نگهداشت، قابل تغییر بدون دیپلوی
        DB::table('zaban_meta')->insertOrIgnore([
            'k' => 'fsrs_request_retention', 'v' => '0.90', 'updated_at' => now(),
        ]);
        DB::table('zaban_meta')->insertOrIgnore([
            'k' => 'fsrs_weights', 'v' => json_encode(\App\Services\Fsrs::DEFAULT_W), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('deck_items', function (Blueprint $t) {
            $t->dropIndex('deck_due');
            $t->dropColumn(['stability', 'difficulty', 'elapsed_days', 'scheduler']);
        });
        Schema::table('review_logs', function (Blueprint $t) {
            $t->dropColumn(['stability', 'difficulty', 'retrievability', 'elapsed_days']);
        });
    }
};
