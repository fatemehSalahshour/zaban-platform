<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * درگاه دوم (زرین‌پال) روی zaban_orders.
 *
 * ستون‌های gateway / ref_id / token / authority / masked_pan از قبل هستند؛ این فایل فقط
 * مطمئن می‌شود جا دارند (authority زرین‌پال ۳۶ حرف است) و اگر ستونی نبود اضافه‌اش می‌کند.
 * ستونی که از قبل به اندازه‌ی کافی بلند است دست نمی‌خورد؛ nullable و ایندکس‌ها حفظ می‌شوند.
 * چند بار اجرا شدنش بی‌خطر است.
 *
 * اجرا: php artisan migrate --path=database/migrations/2026_10_08_000000_zaban_orders_zarinpal.php --force
 */
return new class extends Migration
{
    private const WANT = [
        'gateway'    => 20,
        'token'      => 64,
        'authority'  => 64,
        'ref_id'     => 64,
        'masked_pan' => 32,
    ];

    public function up(): void
    {
        foreach (self::WANT as $col => $len) {
            $info = DB::selectOne(
                "SELECT DATA_TYPE t, CHARACTER_MAXIMUM_LENGTH n, IS_NULLABLE nul, COLUMN_DEFAULT d
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zaban_orders' AND COLUMN_NAME = ?",
                [$col]
            );

            if (!$info) {
                DB::statement("ALTER TABLE zaban_orders ADD COLUMN `{$col}` VARCHAR({$len}) NULL");
                continue;
            }

            $type = strtolower($info->t);
            /* enum (مثلاً gateway فقط با 'irankish') مقدار 'zarinpal' را نمی‌پذیرد → varchar.
               متن بلند (text و …) یا varchar به اندازه‌ی کافی: دست نمی‌زنیم. */
            if ($type !== 'enum'
                && (!in_array($type, ['varchar', 'char'], true) || (int) $info->n >= $len)) {
                continue;
            }

            $null = $info->nul === 'YES' ? 'NULL' : 'NOT NULL';
            /* مقدار پیش‌فرض قبلی (اگر بود) حفظ می‌شود */
            $def = ($info->d !== null && strtoupper((string) $info->d) !== 'NULL')
                ? ' DEFAULT ' . DB::getPdo()->quote(trim((string) $info->d, "'")) : '';
            DB::statement("ALTER TABLE zaban_orders MODIFY `{$col}` VARCHAR({$len}) {$null}{$def}");
        }

        /* برگشت زرین‌پال سفارش را با token پیدا می‌کند */
        $hasIndex = collect(DB::select("SHOW INDEX FROM zaban_orders WHERE Column_name = 'token'"))->isNotEmpty();
        if (!$hasIndex) {
            DB::statement('ALTER TABLE zaban_orders ADD INDEX zaban_orders_token_idx (token)');
        }
    }

    public function down(): void
    {
        /* بلندتر کردن ستون برگشت ندارد — کوتاه کردن ممکن است داده را ببُرد */
    }
};
