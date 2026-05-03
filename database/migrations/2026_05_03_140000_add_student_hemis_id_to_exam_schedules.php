<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2-urinish va 3-urinish (12a/12b) sanalarini individual talabaga belgilash uchun
 * exam_schedules.student_hemis_id ustunini qo'shish.
 *
 * Mantiq:
 *  - student_hemis_id NULL → guruhga umumiy yozuv (1-urinish OSKI/Test sanasi)
 *  - student_hemis_id qiymati bor → faqat shu talaba uchun (2/3-urinish)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('exam_schedules')) return;

        Schema::table('exam_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_schedules', 'student_hemis_id')) {
                $table->string('student_hemis_id')->nullable()->after('group_hemis_id');
                $table->index(['student_hemis_id', 'subject_id', 'semester_code'], 'es_student_subj_sem_idx');
            }
        });

        // Mavjud unique cheklov (group, subject, semester) — student_hemis_id ham hisoblanishi uchun yumshatish.
        // MySQL'da NULLable column'ga unique tushganda NULL qiymatlar takrorlanishi mumkin —
        // shuning uchun yangi unique (group, subject, semester, student_hemis_id) qo'shish biroz qiyin.
        // Hozirgi cheklov (mavjud bo'lsa) qoldiriladi; insert/update mantiq qoidalari controllerda nazoratlanadi.
    }

    public function down(): void
    {
        if (!Schema::hasTable('exam_schedules')) return;
        Schema::table('exam_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('exam_schedules', 'student_hemis_id')) {
                try { $table->dropIndex('es_student_subj_sem_idx'); } catch (\Throwable $e) {}
                $table->dropColumn('student_hemis_id');
            }
        });
    }
};
