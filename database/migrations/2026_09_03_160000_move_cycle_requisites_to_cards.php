<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sikl blokining o'qituvchi/xona rekvizitlari endi timetable_cards da
 * saqlanadi (Biriktirish tabi bilan bitta manba). Sikl jadvalida faqat
 * dars vaqti (lesson_time) qoladi — u kartada modellashtirilmagan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('timetable_cycle_placements')) {
            return;
        }

        Schema::table('timetable_cycle_placements', function (Blueprint $table) {
            foreach (['teacher_id', 'teacher_name', 'auditorium_code', 'auditorium_name'] as $column) {
                if (Schema::hasColumn('timetable_cycle_placements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        // Qaytarish shart emas — rekvizitlar kartalarda yashaydi.
    }
};
