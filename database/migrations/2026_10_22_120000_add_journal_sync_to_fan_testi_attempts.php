<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Qayta topshirish testi natijasi jurnaldagi mavzuga mos NB katagiga otrabotka
 * bahosi bo'lib tushadi — natija (yozildi / nega yozilmadi) urinishda saqlanadi
 * va Test jurnalida ko'rsatiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fan_testi_attempts', 'journal_synced_at')) {
            return;
        }
        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->timestamp('journal_synced_at')->nullable()->after('is_retake');
            $table->string('journal_sync_note', 255)->nullable()->after('journal_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->dropColumn(['journal_synced_at', 'journal_sync_note']);
        });
    }
};
