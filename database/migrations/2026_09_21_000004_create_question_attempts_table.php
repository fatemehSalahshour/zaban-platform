<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تلاش‌های تمرینی کاربر روی سؤال (بیرون از آزمون) — «این تست را n بار زده‌اید».
 * تا این نسخه فقط در حافظه‌ی مرورگر بود (zban_qatt).
 * درست/غلط را سرور حساب می‌کند، نه مرورگر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_attempts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('question_id');
            $t->unsignedTinyInteger('chosen');          // ۱ تا ۴
            $t->boolean('is_correct');
            $t->timestamp('created_at')->nullable();
            $t->index(['user_id', 'question_id']);
            $t->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_attempts');
    }
};
