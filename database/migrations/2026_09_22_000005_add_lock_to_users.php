<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** قفل خودکار حساب (امنیت محتوا، قدم ۲): تا کی و چرا. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'locked_until')) $t->timestamp('locked_until')->nullable();
            if (!Schema::hasColumn('users', 'lock_reason'))  $t->string('lock_reason', 190)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach (['locked_until', 'lock_reason'] as $c) {
                if (Schema::hasColumn('users', $c)) $t->dropColumn($c);
            }
        });
    }
};
