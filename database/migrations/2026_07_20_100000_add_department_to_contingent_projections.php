<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yangi yo'nalishlar (masalan Oliy hamshiralik) uchun joriy talaba bo'lmagani
 * sababli fakultetni aniqlab bo'lmaydi — shuning uchun fakultetni bashorat
 * bilan birga saqlaymiz.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('contingent_projections', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('level_code');
            $table->string('department_name')->nullable()->after('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('contingent_projections', function (Blueprint $table) {
            $table->dropColumn(['department_id', 'department_name']);
        });
    }
};
