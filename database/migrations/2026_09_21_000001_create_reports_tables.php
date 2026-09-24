<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * گزارش اشکال کاربران روی کلمه‌ها و سؤال‌ها.
 *
 * عمداً migration است نه اضافه به schema.sql: تست‌های امنیتی (کار ۶) دقیقاً
 * به خاطر جدول‌هایی شکست خوردند که فقط در schema.sql بودند.
 *
 * یک کاربر برای هر آیتم یک گفت‌وگو دارد (unique روی user_id + item_key)،
 * همان مدلی که رابط کاربری دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            /* بدون کلید خارجی: users با schema.sql ساخته شده و نوع دقیق id اش
               (int یا bigint) قطعی نیست. کلید خارجی با نوع ناجور، migration را
               روی MySQL می‌شکند. */
            $t->unsignedBigInteger('user_id');
            $t->enum('item_type', ['word', 'question']);
            $t->unsignedBigInteger('item_id');
            /* کلید رابط: «w:<کلمه>» یا «q:<سال>|<رشته>|<شماره>».
               ذخیره‌اش می‌کنیم تا رابط بدون دانستن ساختار جدول questions
               بتواند گزارش را سر جای خودش بنشاند. */
            $t->string('item_key', 191);
            $t->string('topic', 40);
            $t->enum('state', ['open', 'answered'])->default('open');
            /* کاربر پاسخ آخر را دیده؟ با هر پاسخ مدیر دوباره null می‌شود. */
            $t->timestamp('seen_at')->nullable();
            $t->timestamp('last_msg_at')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'item_key']);
            $t->index(['state', 'last_msg_at']);
            $t->index(['item_type', 'item_id']);
        });

        Schema::create('report_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $t->enum('by', ['user', 'admin']);
            $t->unsignedBigInteger('admin_id')->nullable();
            $t->text('body');
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_messages');
        Schema::dropIfExists('reports');
    }
};
