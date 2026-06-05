<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * student_survey_answers jadvaliga student_hemis_id ustunini qo'shish.
 *
 * Eslatma: ilgari anonimlik uchun talabaga bog'lanmagan edi, lekin admin
 * tahlil uchun bog'liq bo'lishi kerak. CSV'da allaqachon talaba ma'lumotlari
 * saqlanyapti; bu DB tomonida ham mos qiladi.
 *
 * Mavjud satrlar uchun student_hemis_id'ni session_token bilan completions
 * jadvalidan vaqt mosligi orqali aniqlash mumkin emas (completion'da token
 * yo'q). Eski yozuvlar uchun NULL qoldiramiz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_survey_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('student_hemis_id')->nullable()->after('survey_key');
            $table->index(['survey_key', 'student_hemis_id'], 'survey_answers_student_idx');
        });

        // Mavjud yozuvlar uchun: created_at bo'yicha completions bilan moslab,
        // student_hemis_id'ni to'ldirish (yagona moslik bo'lsa).
        try {
            DB::statement("
                UPDATE student_survey_answers a
                JOIN (
                    SELECT a2.id AS answer_id, c.student_hemis_id
                    FROM student_survey_answers a2
                    JOIN student_survey_completions c
                      ON c.survey_key = a2.survey_key
                     AND ABS(TIMESTAMPDIFF(SECOND, c.completed_at, a2.created_at)) <= 5
                ) AS m ON m.answer_id = a.id
                SET a.student_hemis_id = m.student_hemis_id
                WHERE a.student_hemis_id IS NULL
            ");
        } catch (\Throwable $e) {
            // Backfill xato bersa migration ishlasin
        }
    }

    public function down(): void
    {
        Schema::table('student_survey_answers', function (Blueprint $table) {
            $table->dropIndex('survey_answers_student_idx');
            $table->dropColumn('student_hemis_id');
        });
    }
};
