<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * YN qaydnoma tasdig'ini kim bergani: amaliy o'qituvchi (oqituvchi) yoki fakultet dekani (dekan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yn_qaydnoma_confirmations', function (Blueprint $table) {
            $table->string('role', 20)->default('oqituvchi')->after('teacher_name');
        });
    }

    public function down(): void
    {
        Schema::table('yn_qaydnoma_confirmations', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
