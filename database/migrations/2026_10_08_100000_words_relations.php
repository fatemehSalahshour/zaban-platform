<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مترادف و متضاد هر کلمه — برای کارت مرور و پنجره‌ی کلمه.
 * هر دو فهرست ساده با ویرگول‌اند («reduce, lessen, ease») تا در اکسل هم
 * خوانا و ویرایش‌پذیر باشند؛ پر کردن و ویرایش با php artisan zaban:relations.
 * چند بار اجرا شدنش بی‌خطر است.
 *
 * اجرا: php artisan migrate --path=database/migrations/2026_10_08_100000_words_relations.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('words', function (Blueprint $t) {
            if (!Schema::hasColumn('words', 'synonyms')) $t->string('synonyms', 500)->nullable();
            if (!Schema::hasColumn('words', 'antonyms')) $t->string('antonyms', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('words', function (Blueprint $t) {
            foreach (['synonyms', 'antonyms'] as $c) {
                if (Schema::hasColumn('words', $c)) $t->dropColumn($c);
            }
        });
    }
};
