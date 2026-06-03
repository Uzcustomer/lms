<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Dars belgilash" hisoboti uchun (CalculateLessonAssignmentJob)
 * jadvallar uchun composite indekslar qo'shish.
 *
 * Qaysi so'rovlar tezlashadi:
 *   1) attendance_controls (subject_schedule_id, load) — Job line 138-142
 *      WHERE subject_schedule_id IN (...) AND load > 0
 *   2) student_grades (lesson_date, subject_id) — Job line 161-170
 *      WHERE lesson_date BETWEEN ? AND ? AND subject_id IN (...)
 *      (DATE() ni olib tashlagandan keyin ishlaydi)
 *
 * ALGORITHM=INPLACE, LOCK=NONE — onlayn, jadvalni lock qilmasdan.
 */
return new class extends Migration {
    public function up(): void
    {
        $indexes = [
            [
                'table' => 'attendance_controls',
                'name' => 'idx_ac_schedule_load',
                'sql' => 'ALTER TABLE attendance_controls ADD INDEX idx_ac_schedule_load (subject_schedule_id, load) ALGORITHM=INPLACE, LOCK=NONE',
            ],
            [
                'table' => 'student_grades',
                'name' => 'idx_sg_lesson_date_subject',
                'sql' => 'ALTER TABLE student_grades ADD INDEX idx_sg_lesson_date_subject (lesson_date, subject_id) ALGORITHM=INPLACE, LOCK=NONE',
            ],
        ];

        foreach ($indexes as $index) {
            try {
                if (!Schema::hasTable($index['table'])) {
                    Log::info("[Migration] Table {$index['table']} mavjud emas, {$index['name']} o'tkazib yuboramiz.");
                    continue;
                }
                $exists = DB::select("SHOW INDEX FROM {$index['table']} WHERE Key_name = ?", [$index['name']]);
                if (!empty($exists)) {
                    Log::info("[Migration] Index {$index['name']} allaqachon mavjud, o'tkazib yuboramiz.");
                    continue;
                }
                DB::statement($index['sql']);
                Log::info("[Migration] Index {$index['name']} yaratildi.");
            } catch (\Throwable $e) {
                try {
                    $fallback = str_replace(' ALGORITHM=INPLACE, LOCK=NONE', '', $index['sql']);
                    DB::statement($fallback);
                    Log::info("[Migration] Index {$index['name']} fallback bilan yaratildi.");
                } catch (\Throwable $e2) {
                    Log::warning("[Migration] Index {$index['name']} yaratilmadi: " . $e2->getMessage());
                }
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            ['table' => 'attendance_controls', 'name' => 'idx_ac_schedule_load'],
            ['table' => 'student_grades', 'name' => 'idx_sg_lesson_date_subject'],
        ];

        foreach ($indexes as $idx) {
            try {
                if (!Schema::hasTable($idx['table'])) continue;
                $exists = DB::select("SHOW INDEX FROM {$idx['table']} WHERE Key_name = ?", [$idx['name']]);
                if (empty($exists)) continue;
                DB::statement("ALTER TABLE {$idx['table']} DROP INDEX {$idx['name']}");
            } catch (\Throwable $e) {
                Log::warning("[Migration] {$idx['name']} drop xato: " . $e->getMessage());
            }
        }
    }
};
