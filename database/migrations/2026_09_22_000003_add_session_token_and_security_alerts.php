<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - users.session_token: نشانه‌ی تنها دستگاه مجاز هر حساب (SingleSession)
 * - security_alerts: هشدارهای امنیتی برای مدیر؛ هر نوع هشدار برای هر کاربر
 *   روزی یک بار (قید یکتا) تا پنل از هشدار تکراری پر نشود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'session_token')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('session_token', 64)->nullable());
        }

        Schema::create('security_alerts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('kind', 32);                 // answer_cap | account_sharing
            $t->string('detail', 255)->nullable();
            $t->date('day');
            $t->timestamp('created_at')->nullable();
            $t->timestamp('seen_at')->nullable();
            $t->unique(['user_id', 'kind', 'day']);
            $t->index(['seen_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
        if (Schema::hasColumn('users', 'session_token')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('session_token'));
        }
    }
};
