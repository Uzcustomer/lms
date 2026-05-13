<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * student_grades.attempt ustunini eski OSKI/Test yozuvlari uchun
 * oski.shakl / exam_test.shakl qiymatlari bilan to'ldirish (backfill).
 *
 * Sabab: avval yuklash kodlari attempt ustunini to'ldirmas edi va
 * barcha yozuvlar default qiymat (1) bilan saqlangan. Bu jurnaldagi
 * 2/3-urinish ustunlarining ko'rinmasligiga va o'rtachalash xatosiga
 * sabab bo'lardi.
 *
 * Backfill faqat hozir attempt=1 bo'lgan yozuvlarni o'zgartiradi —
 * yuklash kodi tuzatilgandan keyin to'g'ri attempt bilan saqlangan
 * yozuvlarga tegmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_grades') || !Schema::hasColumn('student_grades', 'attempt')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        // OSKI (training_type_code=101) ← oskis.shakl
        if (Schema::hasTable('oskis') && Schema::hasColumn('oskis', 'shakl')) {
            if ($driver === 'mysql') {
                DB::statement('
                    UPDATE student_grades sg
                    INNER JOIN oskis o ON sg.oski_id = o.id
                    SET sg.attempt = o.shakl
                    WHERE sg.training_type_code = 101
                      AND sg.oski_id IS NOT NULL
                      AND sg.attempt = 1
                      AND o.shakl IN (2, 3)
                ');
            } else {
                DB::statement('
                    UPDATE student_grades
                    SET attempt = o.shakl
                    FROM oskis o
                    WHERE student_grades.oski_id = o.id
                      AND student_grades.training_type_code = 101
                      AND student_grades.oski_id IS NOT NULL
                      AND student_grades.attempt = 1
                      AND o.shakl IN (2, 3)
                ');
            }
        }

        // Test (training_type_code=102) ← exam_tests.shakl
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
        // Backfill teskari emas: avvalgi noto'g'ri holatga qaytarish
        // ma'lumotni buzadi. Shu sababli down bo'sh qoldirildi.
    }
};
