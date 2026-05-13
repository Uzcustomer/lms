<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * student_grades.attempt ni quiz_result_id orqali backfill.
 *
 * "Yuklangan natijalar" sahifasidan kelgan OSKI/Test baholar
 * `reason='quiz_result'` va `quiz_result_id` bilan saqlanadi.
 * Ularning haqiqiy urinish raqami `hemis_quiz_results.attempt_number`
 * ustunida bo'ladi.
 *
 * Avvalgi 2 ta backfill (oski_id va test_id) bu yo'lni qamramagan,
 * shu sababli quiz orqali kelgan 2/3-urinish baholari hali ham
 * attempt=1 bo'lib qolgan edi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_grades') || !Schema::hasColumn('student_grades', 'attempt')) {
            return;
        }
        if (!Schema::hasTable('hemis_quiz_results') || !Schema::hasColumn('hemis_quiz_results', 'attempt_number')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('
                UPDATE student_grades sg
                INNER JOIN hemis_quiz_results hqr ON sg.quiz_result_id = hqr.id
                SET sg.attempt = hqr.attempt_number
                WHERE sg.training_type_code IN (101, 102, 103)
                  AND sg.quiz_result_id IS NOT NULL
                  AND sg.attempt = 1
                  AND hqr.attempt_number > 1
            ');
        } else {
            DB::statement('
                UPDATE student_grades
                SET attempt = hqr.attempt_number
                FROM hemis_quiz_results hqr
                WHERE student_grades.quiz_result_id = hqr.id
                  AND student_grades.training_type_code IN (101, 102, 103)
                  AND student_grades.quiz_result_id IS NOT NULL
                  AND student_grades.attempt = 1
                  AND hqr.attempt_number > 1
            ');
        }
    }

    public function down(): void
    {
        // Teskari emas
    }
};
