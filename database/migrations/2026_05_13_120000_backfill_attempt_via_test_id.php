<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * student_grades.attempt ni `test_id` ustuni orqali backfill qilish.
 *
 * Avvalgi backfill (2026_05_13_110000) faqat oski_id orqali bog'lanardi.
 * Lekin OskiImport.php da OSKI baholari student_grades.test_id ustuniga
 * yoziladi (chalkash nomlash). Shu sababli OskiImport orqali yuklangan
 * eski yozuvlar attempt qiymati to'g'rilanmadi.
 *
 * Bu migratsiya:
 *   - training_type_code = 101 (OSKI) → oskis.shakl orqali test_id matching
 *   - training_type_code = 102 (Test) → exam_tests.shakl orqali test_id matching
 *     (ExamTestImport ham test_id ga yozadi — bu yo'l avvalgi backfillda yo'q edi)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_grades') || !Schema::hasColumn('student_grades', 'attempt')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        // OSKI (101): test_id → oskis.id → shakl
        if (Schema::hasTable('oskis') && Schema::hasColumn('oskis', 'shakl')) {
            if ($driver === 'mysql') {
                DB::statement('
                    UPDATE student_grades sg
                    INNER JOIN oskis o ON sg.test_id = o.id
                    SET sg.attempt = o.shakl
                    WHERE sg.training_type_code = 101
                      AND sg.test_id IS NOT NULL
                      AND sg.attempt = 1
                      AND o.shakl IN (2, 3)
                ');
            } else {
                DB::statement('
                    UPDATE student_grades
                    SET attempt = o.shakl
                    FROM oskis o
                    WHERE student_grades.test_id = o.id
                      AND student_grades.training_type_code = 101
                      AND student_grades.test_id IS NOT NULL
                      AND student_grades.attempt = 1
                      AND o.shakl IN (2, 3)
                ');
            }
        }

        // Test (102): test_id → exam_tests.id → shakl
        // (avvalgi backfill bu yo'lni bajargan, lekin ehtiyot uchun qaytadan)
        if (Schema::hasTable('exam_tests') && Schema::hasColumn('exam_tests', 'shakl')) {
            if ($driver === 'mysql') {
                DB::statement('
                    UPDATE student_grades sg
                    INNER JOIN exam_tests t ON sg.test_id = t.id
                    SET sg.attempt = t.shakl
                    WHERE sg.training_type_code = 102
                      AND sg.test_id IS NOT NULL
                      AND sg.attempt = 1
                      AND t.shakl IN (2, 3)
                ');
            } else {
                DB::statement('
                    UPDATE student_grades
                    SET attempt = t.shakl
                    FROM exam_tests t
                    WHERE student_grades.test_id = t.id
                      AND student_grades.training_type_code = 102
                      AND student_grades.test_id IS NOT NULL
                      AND student_grades.attempt = 1
                      AND t.shakl IN (2, 3)
                ');
            }
        }
    }

    public function down(): void
    {
        // Teskari emas
    }
};
