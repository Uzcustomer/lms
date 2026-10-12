<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test davomida chalg'ish cheklovi:
 *   away_streak  — oxirgi ochib berishdan beri chalg'ishlar soni;
 *   lock_count   — necha marta qulflangan (1 — o'qituvchi ochib berishi mumkin,
 *                  2 — test yakunlangan, qayta ochilmaydi);
 *   locked_at    — hozir qulflangan bo'lsa, qachondan beri;
 *   ended_reason — test qanday sababdan yakunlangani ("away" — chalg'igani uchun).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fan_testi_attempts') || Schema::hasColumn('fan_testi_attempts', 'lock_count')) {
            return;
        }

        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('away_streak')->default(0);
            $table->unsignedTinyInteger('lock_count')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->string('ended_reason', 20)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('fan_testi_attempts', 'lock_count')) {
            return;
        }

        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->dropColumn(['away_streak', 'lock_count', 'locked_at', 'ended_reason']);
        });
    }
};
