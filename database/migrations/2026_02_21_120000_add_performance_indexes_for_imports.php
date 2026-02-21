<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lock wait timeout xatosini hal qilish uchun muhim indekslar.
 *
 * Muammo: Import jarayonida lesson_date bo'yicha range query va
 * deleted_at bo'yicha onlyTrashed() querylari indekssiz ishlaydi,
 * bu full table scan va uzoq lock vaqtiga olib keladi.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            // Import da lesson_date range query uchun (soft-delete, backup, restore)
            $table->index('lesson_date', 'idx_sg_lesson_date');
            // onlyTrashed() querylari uchun
            $table->index('deleted_at', 'idx_sg_deleted_at');
            // Retake restore: student + subject + lesson_date composite
            $table->index(
                ['student_hemis_id', 'subject_id', 'lesson_date', 'lesson_pair_code'],
                'idx_sg_retake_lookup'
            );
        });

        Schema::table('attendance_controls', function (Blueprint $table) {
            // onlyTrashed() / withTrashed() querylari uchun
            $table->index('deleted_at', 'idx_ac_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropIndex('idx_sg_lesson_date');
            $table->dropIndex('idx_sg_deleted_at');
            $table->dropIndex('idx_sg_retake_lookup');
        });

        Schema::table('attendance_controls', function (Blueprint $table) {
            $table->dropIndex('idx_ac_deleted_at');
        });
    }
};
