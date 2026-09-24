<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حق دسترسی، سفارش، نسخه‌ی محتوا و لاگ افشای پاسخ.
 * معادل 04-schema-part3-entitlements.sql — یکی از این دو را اجرا کنید، نه هر دو.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zaban_entitlements', function (Blueprint $t) {
            $t->unsignedInteger('user_id');
            $t->enum('exam', ['ce', 'it', 'cs']);
            $t->enum('source', ['purchase', 'manual', 'trial', 'staff'])->default('purchase');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->dateTime('granted_at');
            $t->dateTime('expires_at')->nullable();
            $t->dateTime('revoked_at')->nullable();
            $t->string('note', 190)->nullable();
            $t->primary(['user_id', 'exam']);
            $t->index(['user_id', 'revoked_at', 'expires_at'], 'ent_live');
        });

        Schema::create('zaban_orders', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('user_id');
            $t->enum('status', ['pending', 'paid', 'failed', 'canceled', 'refunded'])->default('pending');
            $t->string('exams', 16);
            $t->unsignedInteger('list_price');
            $t->unsignedInteger('discount')->default(0);
            $t->unsignedInteger('payable');
            $t->string('price_version', 16);
            $t->string('gateway', 24)->nullable();
            $t->string('authority', 96)->nullable();
            $t->string('ref_id', 96)->nullable();
            $t->dateTime('expires_at')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->dateTime('paid_at')->nullable();
            $t->index(['user_id', 'status'], 'ord_user');
            $t->unique(['gateway', 'authority'], 'ord_authority');
            $t->unique(['gateway', 'ref_id'], 'ord_ref');
        });

        Schema::create('zaban_meta', function (Blueprint $t) {
            $t->string('k', 64)->primary();
            $t->string('v', 190);
            $t->timestamp('updated_at')->nullable();
        });

        foreach (['ce', 'it', 'cs'] as $e) {
            DB::table('zaban_meta')->insertOrIgnore([
                'k' => "content_version_$e", 'v' => '0', 'updated_at' => now(),
            ]);
        }

        Schema::create('answer_reveals', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('question_id');
            $t->enum('reason', ['free', 'washback', 'finished']);
            $t->unsignedBigInteger('attempt_id')->nullable();
            $t->dateTime('created_at');
            $t->index(['user_id', 'created_at'], 'rev_user_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_reveals');
        Schema::dropIfExists('zaban_meta');
        Schema::dropIfExists('zaban_orders');
        Schema::dropIfExists('zaban_entitlements');
    }
};
