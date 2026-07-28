<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kartochka necha HAFTADA o'tiladi.
 *
 * Avval bu faqat ma'ruza uchun soatdan hisoblanardi. Endi amaliy kartalarda
 * ham kerak: haftalik yuk chegarasi tufayli ma'ruzali haftada amaliy paralar
 * kamayadi. Masalan 12 s ma'ruza + 78 s amaliy / 15 hafta = 6 s/hafta bo'lsa:
 *  - ma'ruza kartasi        → 6 hafta
 *  - 2 ta amaliy kartasi    → 15 hafta (har hafta)
 *  - 1 ta amaliy kartasi    → 9 hafta (faqat ma'ruzasiz haftalarda)
 * Jami amaliy: (2*15 + 1*9) * 2 s = 78 s — rejaga aniq mos.
 *
 * NULL = eski kartalar (hisob avvalgidek soatdan chiqariladi).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('timetable_cards')) {
            return;
        }
        Schema::table('timetable_cards', function (Blueprint $table) {
            if (!Schema::hasColumn('timetable_cards', 'weeks')) {
                $table->unsignedTinyInteger('weeks')->nullable()->after('len_half');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('timetable_cards') && Schema::hasColumn('timetable_cards', 'weeks')) {
            Schema::table('timetable_cards', function (Blueprint $table) {
                $table->dropColumn('weeks');
            });
        }
    }
};
