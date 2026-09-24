<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سقف کارت تازه در روز — تا امروز در کد ثابت بود (۲۰).
 * پیش‌فرض را مدیر در پنل می‌گذارد (zaban_meta.new_per_day) و هر دانشجو
 * می‌تواند برای خودش عددی بین ۵ تا ۱۰۰ بگذارد؛ NULL یعنی «پیش‌فرض مدیر».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('zaban_profiles', 'new_per_day')) {
            Schema::table('zaban_profiles', fn (Blueprint $t) => $t->unsignedTinyInteger('new_per_day')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('zaban_profiles', 'new_per_day')) {
            Schema::table('zaban_profiles', fn (Blueprint $t) => $t->dropColumn('new_per_day'));
        }
    }
};
