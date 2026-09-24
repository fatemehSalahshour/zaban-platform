<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ستون‌های درگاه ایران کیش روی zaban_orders.
 * جدول با schema.sql ساخته شده؛ فقط ستونی اضافه می‌شود که نباشد.
 * اگر خود جدول نباشد (دیتابیس تست)، کاملش ساخته می‌شود.
 *
 * وضعیت‌ها: pending → verifying → paid | failed | expired
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('zaban_orders')) {
            Schema::create('zaban_orders', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->string('status', 16)->default('pending')->index();
                $t->string('exams', 20);
                $t->unsignedInteger('list_price');
                $t->unsignedInteger('discount')->default(0);
                $t->unsignedInteger('payable');
                $t->string('price_version', 20)->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->string('gateway', 20)->nullable();
                $t->string('authority', 64)->nullable();
                $t->string('ref_id', 64)->nullable();
                $t->timestamp('paid_at')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        $add = [
            'request_id'  => fn (Blueprint $t) => $t->string('request_id', 20)->nullable()->unique(),
            'token'       => fn (Blueprint $t) => $t->string('token', 48)->nullable()->unique(),
            'amount_rial' => fn (Blueprint $t) => $t->unsignedBigInteger('amount_rial')->nullable(),
            'rrn'         => fn (Blueprint $t) => $t->string('rrn', 12)->nullable(),
            'stan'        => fn (Blueprint $t) => $t->string('stan', 6)->nullable(),
            'masked_pan'  => fn (Blueprint $t) => $t->string('masked_pan', 19)->nullable(),
            'gateway_code'=> fn (Blueprint $t) => $t->string('gateway_code', 4)->nullable(),
            'returned_at' => fn (Blueprint $t) => $t->timestamp('returned_at')->nullable(),
            'updated_at'  => fn (Blueprint $t) => $t->timestamp('updated_at')->nullable(),
        ];
        foreach ($add as $col => $def) {
            if (!Schema::hasColumn('zaban_orders', $col)) {
                Schema::table('zaban_orders', fn (Blueprint $t) => $def($t));
            }
        }
    }

    public function down(): void
    {
        foreach (['request_id', 'token', 'amount_rial', 'rrn', 'stan', 'masked_pan', 'gateway_code', 'returned_at'] as $col) {
            if (Schema::hasColumn('zaban_orders', $col)) {
                Schema::table('zaban_orders', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
