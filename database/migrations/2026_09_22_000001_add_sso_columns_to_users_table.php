<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ستون‌های ورود یکپارچه روی جدول users.
 *
 * mobile        : شماره‌ی ۱۱ رقمی 09XXXXXXXXX. ZabanController::me() از قبل
 *                 دنبال همین نام ستون است.
 * global_uid    : شناسه‌ی هویت مرکزی در auth-server. رابط این سایت با بقیه‌ی
 *                 سایت‌ها همین است، نه id عددی.
 * sso_logout_at : علامت خروج مرکزی که backchannel می‌گذارد و SsoSessionGuard
 *                 می‌خواند.
 *
 * هر سه nullable‌اند: کاربرانی که از مسیر dev-login ساخته شده‌اند مقدار ندارند.
 * unique روی ستون nullable در MySQL چند NULL را می‌پذیرد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('mobile', 11)->nullable()->unique()->after('email');
            $t->char('global_uid', 36)->nullable()->unique()->after('mobile');
            $t->timestamp('sso_logout_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['mobile']);
            $t->dropUnique(['global_uid']);
            $t->dropColumn(['mobile', 'global_uid', 'sso_logout_at']);
        });
    }
};
