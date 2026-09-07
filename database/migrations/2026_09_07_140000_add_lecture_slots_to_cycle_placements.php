<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kafedra mudiri sikl bloki ichida qaysi kun va qaysi soatlar ma'ruza
 * ekanini belgilaydi. Belgilar blok boshidan hisoblangan kun siljishi
 * bo'yicha saqlanadi: {"0": [1,2], "3": [1,2]} — nol-kunning 1 va 2 soati
 * ma'ruza. Nisbiy siljish tanlangani uchun blok surilsa belgilar ham
 * u bilan birga ko'chadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('timetable_cycle_placements')
            || Schema::hasColumn('timetable_cycle_placements', 'lecture_slots')) {
            return;
        }

        Schema::table('timetable_cycle_placements', function (Blueprint $table) {
            $table->json('lecture_slots')->nullable()->after('pair');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('timetable_cycle_placements')
            && Schema::hasColumn('timetable_cycle_placements', 'lecture_slots')) {
            Schema::table('timetable_cycle_placements', function (Blueprint $table) {
                $table->dropColumn('lecture_slots');
            });
        }
    }
};
