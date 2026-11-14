<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qaydnoma shakli (YnqForm: 12, 12q, 12a, 12aq, 12b, 12bq) — har shakl alohida vedomost, o'z tasdiq
 * zanjiri bilan. Mavjud tasdiq va rad etishlar — asosiy 12-shakl.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['yn_qaydnoma_confirmations', 'yn_qaydnoma_rejections'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'form')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('form', 8)->default('12')->after('group_hemis_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['yn_qaydnoma_confirmations', 'yn_qaydnoma_rejections'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'form')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('form'));
            }
        }
    }
};
