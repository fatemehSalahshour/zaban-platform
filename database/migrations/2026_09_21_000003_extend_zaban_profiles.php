<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فیلدهای پروفایل که صفحه داشت ولی سرور نه: دانشگاه، معدل، سهمیه، نوع کنکور.
 *
 * zaban_profiles با schema.sql ساخته شده و ستون‌های دقیقش در migration
 * نیست؛ پس فقط ستونی اضافه می‌شود که نباشد. اگر خود جدول نباشد (مثلاً
 * دیتابیس تست که از صفر با migration ساخته می‌شود)، کاملش ساخته می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('zaban_profiles')) {
            Schema::create('zaban_profiles', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id')->primary();
                $t->string('nickname', 40)->nullable();
                $t->string('exam', 4)->nullable();
                $t->boolean('show_in_board')->default(true);
                $t->string('university', 150)->nullable();
                $t->decimal('gpa', 4, 2)->nullable();
                $t->string('quota', 20)->nullable();
                $t->string('degree', 10)->nullable();
                $t->timestamps();
            });
            return;
        }

        $add = [
            'university' => fn (Blueprint $t) => $t->string('university', 150)->nullable(),
            'gpa'        => fn (Blueprint $t) => $t->decimal('gpa', 4, 2)->nullable(),
            'quota'      => fn (Blueprint $t) => $t->string('quota', 20)->nullable(),
            'degree'     => fn (Blueprint $t) => $t->string('degree', 10)->nullable(),
            'created_at' => fn (Blueprint $t) => $t->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ];
        foreach ($add as $col => $def) {
            if (!Schema::hasColumn('zaban_profiles', $col)) {
                Schema::table('zaban_profiles', fn (Blueprint $t) => $def($t));
            }
        }
    }

    public function down(): void
    {
        foreach (['university', 'gpa', 'quota', 'degree'] as $col) {
            if (Schema::hasColumn('zaban_profiles', $col)) {
                Schema::table('zaban_profiles', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
