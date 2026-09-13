<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Umumiy (klinik) karta: ma'ruza va amaliy bitta kartada (is_mixed). Kafedra uni
 * haftalar bo'yicha ajratadi — lecture_weeks (ma'ruza haftalari, NULL = hali
 * ajratilmagan; qolgan haftalar amaliy) va ma'ruza haftalari uchun alohida
 * o'qituvchi/xona (amaliy rekvizitlari asosiy teacher_id va auditorium_code ustunlarida).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('timetable_cards')) {
            return;
        }

        Schema::table('timetable_cards', function (Blueprint $table) {
            if (!Schema::hasColumn('timetable_cards', 'is_mixed')) {
                $table->boolean('is_mixed')->default(false)->after('training_type');
            }
            if (!Schema::hasColumn('timetable_cards', 'lecture_weeks')) {
                $table->json('lecture_weeks')->nullable()->after('weeks');
            }
            if (!Schema::hasColumn('timetable_cards', 'lecture_teacher_id')) {
                $table->unsignedBigInteger('lecture_teacher_id')->nullable()->after('lecture_weeks');
            }
            if (!Schema::hasColumn('timetable_cards', 'lecture_teacher_name')) {
                $table->string('lecture_teacher_name')->nullable()->after('lecture_teacher_id');
            }
            if (!Schema::hasColumn('timetable_cards', 'lecture_auditorium_code')) {
                $table->string('lecture_auditorium_code', 50)->nullable()->after('lecture_teacher_name');
            }
            if (!Schema::hasColumn('timetable_cards', 'lecture_auditorium_name')) {
                $table->string('lecture_auditorium_name')->nullable()->after('lecture_auditorium_code');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('timetable_cards')) {
            return;
        }

        Schema::table('timetable_cards', function (Blueprint $table) {
            foreach (['lecture_auditorium_name', 'lecture_auditorium_code', 'lecture_teacher_name',
                      'lecture_teacher_id', 'lecture_weeks', 'is_mixed'] as $col) {
                if (Schema::hasColumn('timetable_cards', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
