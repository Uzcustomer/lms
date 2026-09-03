<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sikl blokiga rekvizitlar: o'qituvchi, dars vaqti va xona.
 *
 * Sikl jadvalida blok ustidagi tishli tugma orqali kiritiladi va blokda
 * kichik yozuv bo'lib ko'rinadi. Haftalik grid kartalariga aloqasi yo'q.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('timetable_cycle_placements')) {
            return;
        }

        Schema::table('timetable_cycle_placements', function (Blueprint $table) {
            if (!Schema::hasColumn('timetable_cycle_placements', 'teacher_id')) {
                $table->unsignedBigInteger('teacher_id')->nullable()->after('start_index');
            }
            if (!Schema::hasColumn('timetable_cycle_placements', 'teacher_name')) {
                $table->string('teacher_name')->nullable()->after('teacher_id');
            }
            if (!Schema::hasColumn('timetable_cycle_placements', 'lesson_time')) {
                $table->string('lesson_time', 50)->nullable()->after('teacher_name');
            }
            if (!Schema::hasColumn('timetable_cycle_placements', 'auditorium_code')) {
                $table->string('auditorium_code', 50)->nullable()->after('lesson_time');
            }
            if (!Schema::hasColumn('timetable_cycle_placements', 'auditorium_name')) {
                $table->string('auditorium_name')->nullable()->after('auditorium_code');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('timetable_cycle_placements')) {
            return;
        }

        Schema::table('timetable_cycle_placements', function (Blueprint $table) {
            foreach (['teacher_id', 'teacher_name', 'lesson_time', 'auditorium_code', 'auditorium_name'] as $column) {
                if (Schema::hasColumn('timetable_cycle_placements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
