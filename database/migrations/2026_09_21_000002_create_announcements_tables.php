<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اطلاعیه‌های مدیریت برای همه‌ی کاربران.
 *
 * «خوانده شده» به‌ازای هر اطلاعیه ذخیره نمی‌شود، فقط یک زمان برای هر
 * کاربر: آخرین باری که صفحه‌ی اطلاعیه‌ها را باز کرده. هر اطلاعیه‌ای که
 * بعد از آن منتشر شده «تازه» است. رابط هم دقیقاً همین را نشان می‌دهد
 * (همه با هم خوانده می‌شوند) و جدول به‌ازای هر کاربر یک ردیف می‌ماند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('title', 200);
            $t->text('body');
            /* null یعنی پیش‌نویس: کاربر نمی‌بیند. */
            $t->timestamp('published_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->index('published_at');
        });

        Schema::create('announcement_reads', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->primary();
            $t->timestamp('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
    }
};
