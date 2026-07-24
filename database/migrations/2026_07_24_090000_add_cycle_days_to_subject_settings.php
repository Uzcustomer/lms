<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sikl uzunligi o'quv KUNLARIDA (rasmdagidek: "Ichki kasalliklar 13" = 13 kun).
 * Avval hafta (cycle_weeks) edi — kun aniqroq. Mavjud qiymatlar (agar bo'lsa)
 * hafta → kun ga o'girilmaydi (yangi maydon; bo'sh qoladi, foydalanuvchi kiritadi).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('timetable_subject_settings')) {
            return;
        }
        Schema::table('timetable_subject_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('timetable_subject_settings', 'cycle_days')) {
                $table->unsignedSmallInteger('cycle_days')->nullable()->after('cycle_weeks');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('timetable_subject_settings') && Schema::hasColumn('timetable_subject_settings', 'cycle_days')) {
            Schema::table('timetable_subject_settings', function (Blueprint $table) {
                $table->dropColumn('cycle_days');
            });
        }
    }
};
