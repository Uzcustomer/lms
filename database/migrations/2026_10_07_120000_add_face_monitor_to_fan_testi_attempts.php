<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Test davomidagi yuz kuzatuvi: jim olingan suratlar talabaning rasmi bilan
     * solishtiriladi, monitordan chalg'ish holatlari sanaladi — o'qituvchi
     * jurnalda ko'radi.
     */
    public function up(): void
    {
        if (!Schema::hasTable('fan_testi_attempts') || Schema::hasColumn('fan_testi_attempts', 'face_checks')) {
            return;
        }

        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->unsignedInteger('face_checks')->default(0)->after('is_passed');
            $table->unsignedInteger('face_mismatches')->default(0)->after('face_checks');
            $table->unsignedInteger('away_count')->default(0)->after('face_mismatches');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('fan_testi_attempts', 'face_checks')) {
            return;
        }

        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->dropColumn(['face_checks', 'face_mismatches', 'away_count']);
        });
    }
};
