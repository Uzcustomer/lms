<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * student_grades.attempt ni quiz_result_id orqali backfill.
 *
 * Test markazi → Diagnostika → "Sistemaga yuklash" (yoki "Qayta yuklash")
 * tugmasi orqali kelgan OSKI/Test baholar reason='quiz_result' va
 * quiz_result_id bilan saqlanadi. Ularning haqiqiy urinish raqami
 * hemis_quiz_results.shakl ustunida "1-urinish" / "2-urinish" /
 * "3-urinish" ko'rinishida bo'ladi (attempt_number har doim ham
 * to'ldirilmagan bo'lishi mumkin).
 *
 * Avvalgi backfill (oski_id, test_id) bu yo'lni qamramagan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_grades') || !Schema::hasColumn('student_grades', 'attempt')) {
            return;
        }
        if (!Schema::hasTable('hemis_quiz_results') || !Schema::hasColumn('hemis_quiz_results', 'shakl')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        // shakl matni asosida attempt aniqlash: "2-urinish" → 2, "3-urinish" → 3.
        // attempt_number ham mavjud bo'lsa, shakl mos kelmagan holatda fallback.
        if ($driver === 'mysql') {
            DB::statement("
                UPDATE student_grades sg
                INNER JOIN hemis_quiz_results hqr ON sg.quiz_result_id = hqr.id
                SET sg.attempt = CASE
                    WHEN LOWER(TRIM(hqr.shakl)) = '2-urinish' THEN 2
                    WHEN LOWER(TRIM(hqr.shakl)) = '3-urinish' THEN 3
                    ELSE sg.attempt
                END
                WHERE sg.training_type_code IN (101, 102, 103)
                  AND sg.quiz_result_id IS NOT NULL
                  AND sg.attempt = 1
                  AND LOWER(TRIM(hqr.shakl)) IN ('2-urinish', '3-urinish')
            ");
        } else {
            DB::statement("
                UPDATE student_grades
                SET attempt = CASE
                    WHEN LOWER(TRIM(hqr.shakl)) = '2-urinish' THEN 2
                    WHEN LOWER(TRIM(hqr.shakl)) = '3-urinish' THEN 3
                    ELSE student_grades.attempt
                END
                FROM hemis_quiz_results hqr
                WHERE student_grades.quiz_result_id = hqr.id
                  AND student_grades.training_type_code IN (101, 102, 103)
                  AND student_grades.quiz_result_id IS NOT NULL
                  AND student_grades.attempt = 1
                  AND LOWER(TRIM(hqr.shakl)) IN ('2-urinish', '3-urinish')
            ");
        }
    }

    public function down(): void
    {
        // Teskari emas
    }
};
