<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شمارنده‌ی روزانه‌ی رویدادهای محافظ محتوا (کپی، پنهان شدن صفحه، ابزار توسعه…).
 * یک ردیف برای هر کاربر، روز و نوع — جدول کوچک می‌ماند.
 * فقط اضافه می‌کند؛ چند بار اجرا شدنش بی‌خطر است.
 *
 * اجرا: php artisan migrate --path=database/migrations/2026_10_10_000000_zaban_client_events.php --force
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zaban_client_events')) return;
        Schema::create('zaban_client_events', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id');
            $t->date('day');
            $t->string('kind', 20);
            $t->unsignedInteger('n')->default(0);
            $t->timestamp('updated_at')->nullable();
            $t->primary(['user_id', 'day', 'kind']);
            $t->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zaban_client_events');
    }
};
