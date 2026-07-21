<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dars jadvali doskasiga "Umumiy sozlamalar" (aSc Установки uslubida):
 *  - institution_name — muassasa nomi (chop etishda),
 *  - bell_schedule    — qo'ng'iroqlar jadvali (juftliklar vaqtlari va tanaffuslar),
 *  - day_names        — kun nomlari (qayta nomlash),
 *  - settings         — qolgan sozlamalar (dam olish kunlari, nol para va h.k.).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_boards', function (Blueprint $table) {
            $table->string('institution_name')->nullable()->after('name');
            $table->json('bell_schedule')->nullable()->after('weeks');
            $table->json('day_names')->nullable()->after('bell_schedule');
            $table->json('settings')->nullable()->after('day_names');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_boards', function (Blueprint $table) {
            $table->dropColumn(['institution_name', 'bell_schedule', 'day_names', 'settings']);
        });
    }
};
