<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Dars ochish hisoboti (LessonOpeningTeacherReport) sana oralig'idagi baholarni
 * fan bo'yicha o'qiydi: markedPairs() `WHERE subject_id IN (...) AND lesson_date
 * BETWEEN ...`. (subject_id, lesson_date) kompozit indeksi shu izlovni
 * tezlashtiradi — aks holda katta oraliqda to'liq skan bo'lardi.
 *
 * Online DDL (INPLACE, LOCK=NONE) — katta jadvalda ham bloklamaydi; qo'llab
 * quvvatlanmasa oddiy ALTER bilan tushiriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $name = 'idx_sg_subject_lesson_date';
        if (! Schema::hasTable('student_grades') || ! Schema::hasColumn('student_grades', 'lesson_date')) {
            return;
        }

        try {
            $exists = DB::select('SHOW INDEX FROM student_grades WHERE Key_name = ?', [$name]);
            if (! empty($exists)) {
                Log::info("[Migration] Index {$name} allaqachon mavjud, o'tkazib yuboramiz.");

                return;
            }
            DB::statement("ALTER TABLE student_grades ADD INDEX {$name} (subject_id, lesson_date) ALGORITHM=INPLACE, LOCK=NONE");
            Log::info("[Migration] Index {$name} yaratildi.");
        } catch (\Throwable $e) {
            try {
                DB::statement("ALTER TABLE student_grades ADD INDEX {$name} (subject_id, lesson_date)");
                Log::info("[Migration] Index {$name} fallback bilan yaratildi.");
            } catch (\Throwable $e2) {
                Log::warning("[Migration] Index {$name} yaratilmadi: ".$e2->getMessage());
            }
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE student_grades DROP INDEX idx_sg_subject_lesson_date');
        } catch (\Throwable $e) {
            // e'tiborsiz
        }
    }
};
