<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * پارامترهای شخصی‌شده‌ی FSRS.
 *
 * وزن‌های پیش‌فرض روی میلیون‌ها مرور واقعی آموزش دیده‌اند، ولی میانگین همه‌ی
 * آدم‌ها هستند. حافظه‌ی هر نفر فرق دارد و برای هر رشته هم می‌تواند فرق کند.
 * این جدول اجازه می‌دهد وزن‌ها در چهار سطح نگه داشته شوند و هنگام محاسبه،
 * نزدیک‌ترین سطحِ موجود انتخاب شود:
 *
 *   user_id=U exam=ce  →  تنظیم این کاربر برای همین رشته
 *   user_id=U exam=''  →  تنظیم کلی همین کاربر
 *   user_id=0 exam=ce  →  تنظیم عمومی رشته روی کل پلتفرم
 *   user_id=0 exam=''  →  تنظیم عمومی کل پلتفرم
 *
 * به‌جای NULL از صفر و رشته‌ی خالی استفاده می‌کنیم، چون MySQL روی ستون NULL
 * یکتایی را اعمال نمی‌کند و آن وقت می‌شد چند ردیف تکراری برای «عمومی» داشت.
 *
 * ستون accepted: وزنی که بهینه‌ساز ساخته تا وقتی روی کارت‌های کنارگذاشته بهتر
 * از پیش‌فرض نبوده استفاده نمی‌شود. یعنی ردیف ساخته می‌شود ولی accepted=0
 * می‌ماند تا بشود دید چه چیزی رد شده و چرا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zaban_fsrs_params', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('user_id')->default(0)->comment('۰ یعنی عمومی');
            $t->string('exam', 2)->default('')->comment('ce|it|cs — خالی یعنی همه‌ی رشته‌ها');

            $t->json('w')->comment('۲۱ وزن FSRS-6');

            /* نتیجه‌ی آموزش — برای اینکه بشود قضاوت کرد، نه فقط اعتماد */
            $t->unsignedInteger('sample_reviews')->default(0);
            $t->unsignedInteger('sample_cards')->default(0);
            $t->decimal('loss_default', 8, 5)->nullable()->comment('خطای وزن پیش‌فرض روی کارت‌های کنارگذاشته');
            $t->decimal('loss_tuned', 8, 5)->nullable()->comment('خطای وزن تازه روی همان‌ها');
            $t->boolean('accepted')->default(false)->comment('فقط وزن پذیرفته‌شده استفاده می‌شود');

            $t->dateTime('trained_at')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'exam'], 'fsrs_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zaban_fsrs_params');
    }
};
