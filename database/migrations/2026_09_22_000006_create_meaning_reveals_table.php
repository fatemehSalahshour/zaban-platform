<?php

use App\Services\ContentBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * امنیت محتوا، قدم ۳: معنی‌ها هم از بسته‌ی یک‌جای /api/content بیرون آمدند و از
 * /api/words/meanings کلمه‌به‌کلمه می‌آیند؛ هر دریافت برای سقف روزانه ثبت می‌شود.
 * نسخه‌ی محتوا عوض می‌شود تا بسته‌ی کش‌شده‌ی قدیمی (با معنی) کنار برود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meaning_reveals', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('word_id');
            $t->timestamp('created_at')->nullable();
            $t->index(['user_id', 'created_at']);
        });
        if (Schema::hasTable('zaban_meta')) app(ContentBuilder::class)->bump();
    }

    public function down(): void
    {
        Schema::dropIfExists('meaning_reveals');
    }
};
