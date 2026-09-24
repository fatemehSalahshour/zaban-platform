<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            /* همان مقادیری که پلتفرم آزمون دارد، تا SSO بتواند مستقیم
            منتقلشان کند بدون نگاشت. */
            $t->enum('type', ['student','teacher','operator','editor',
                            'manager','admin','print','test','trial'])
            ->default('student')->after('email')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('type'));
    }
 
};
