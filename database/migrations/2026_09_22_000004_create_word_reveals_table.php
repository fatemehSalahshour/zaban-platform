<?php

use App\Services\ContentBuilder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * امنیت محتوا، قدم ۱: مثال‌های کلمات از بسته‌ی یک‌جای /api/content بیرون آمدند
 * و کلمه‌به‌کلمه از /api/words/detail می‌آیند. هر دریافت اینجا ثبت می‌شود تا
 * سقف روزانه (config zaban.word_daily_cap) شمرده شود.
 *
 * نسخه‌ی محتوای همه‌ی رشته‌ها هم عوض می‌شود تا مرورگری که بسته‌ی قدیمی (با
 * مثال‌ها) را کش کرده، با ETag تازه نسخه‌ی بی‌مثال را بگیرد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_reveals', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('word_id');
            $t->timestamp('created_at')->nullable();
            $t->index(['user_id', 'created_at']);
        });

        if (Schema::hasTable('zaban_meta')) {
            app(ContentBuilder::class)->bump();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('word_reveals');
    }
};
