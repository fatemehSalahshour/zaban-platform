<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * zaban_orders.status در schema.sql یک ENUM بود (pending/paid/failed) و حالت‌های
 * تازه‌ی Checkout — verifying و expired — را نمی‌پذیرفت: برگشت از درگاه با خطای
 * «Data truncated for column 'status'» می‌شکست. اینجا به VARCHAR تبدیل می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('zaban_orders')) return;

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE zaban_orders MODIFY status VARCHAR(16) NOT NULL DEFAULT 'pending'");
        }
        /* SQLite (تست): جدول در migration قبلی با string ساخته می‌شود؛ کاری لازم نیست. */
    }

    public function down(): void
    {
        /* برگرداندن به ENUM سفارش‌های verifying/expired را خراب می‌کند؛ عمداً خالی. */
    }
};
