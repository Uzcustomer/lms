<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apelyatsiya bilan o'chirilgan baho keyinchalik (mas. noto'g'ri o'chirilgani
 * aniqlansa) tiklansa — shu ustunlarga izohlanadi. Shunda audit saqlanadi va
 * tiklangan yozuv qayta "o'chirilgan" deb hisoblanmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quiz_grade_appeals')) {
            return;
        }
        Schema::table('quiz_grade_appeals', function (Blueprint $table) {
            $table->dateTime('reversed_at')->nullable()->after('performed_by_role');
            $table->string('reversed_by', 120)->nullable()->after('reversed_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('quiz_grade_appeals')) {
            return;
        }
        Schema::table('quiz_grade_appeals', function (Blueprint $table) {
            $table->dropColumn(['reversed_at', 'reversed_by']);
        });
    }
};
