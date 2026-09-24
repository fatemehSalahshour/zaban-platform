<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * zaban_profiles.nickname در schema.sql «NOT NULL، ۲۴ نویسه، یکتا» بود. صفحه‌ی
 * پروفایل نام مستعار خالی (= «بی‌نام» در رتبه‌بندی) را هم می‌پذیرد و تا ۳۰ نویسه؛
 * هر دو خطای ۵۰۰ می‌دادند. حالا NULL‌پذیر و ۳۰ نویسه؛ یکتایی می‌ماند (در MySQL
 * چند NULL با قید یکتا مشکلی ندارند) و تکراری بودن با پیام فارسی رد می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('zaban_profiles')) return;
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE zaban_profiles MODIFY nickname VARCHAR(30) NULL');
        }
    }

    public function down(): void
    {
        /* برگرداندن به NOT NULL ردیف‌های بی‌نام را خراب می‌کند؛ عمداً خالی. */
    }
};
