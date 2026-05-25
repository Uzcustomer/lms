<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Test Markazi sahifasi "sana bo'yicha" filtri uchun indekslar.
 *
 * loadScheduleData endi exam_schedules ni
 *   WHERE oski_date BETWEEN ... OR test_date BETWEEN ... OR ...
 * shaklida 6 ta ustun bo'yicha so'rab oladi. Index bo'lmasa har bir
 * filtrlangan qidiruvda 50k+ qator skanlanadi va sahifa 504 beradi.
 *
 * Onlayn yaratish — ALGORITHM=INPLACE, LOCK=NONE.
 */
return new class extends Migration {
    public function up(): void
    {
        $indexes = [
            ['table' => 'exam_schedules', 'name' => 'idx_es_oski_date',
                'sql' => 'ALTER TABLE exam_schedules ADD INDEX idx_es_oski_date (oski_date) ALGORITHM=INPLACE, LOCK=NONE'],
            ['table' => 'exam_schedules', 'name' => 'idx_es_test_date',
                'sql' => 'ALTER TABLE exam_schedules ADD INDEX idx_es_test_date (test_date) ALGORITHM=INPLACE, LOCK=NONE'],
            ['table' => 'exam_schedules', 'name' => 'idx_es_oski_resit_date',
                'sql' => 'ALTER TABLE exam_schedules ADD INDEX idx_es_oski_resit_date (oski_resit_date) ALGORITHM=INPLACE, LOCK=NONE'],
            ['table' => 'exam_schedules', 'name' => 'idx_es_test_resit_date',
                'sql' => 'ALTER TABLE exam_schedules ADD INDEX idx_es_test_resit_date (test_resit_date) ALGORITHM=INPLACE, LOCK=NONE'],
            ['table' => 'exam_schedules', 'name' => 'idx_es_oski_resit2_date',
                'sql' => 'ALTER TABLE exam_schedules ADD INDEX idx_es_oski_resit2_date (oski_resit2_date) ALGORITHM=INPLACE, LOCK=NONE'],
            ['table' => 'exam_schedules', 'name' => 'idx_es_test_resit2_date',
                'sql' => 'ALTER TABLE exam_schedules ADD INDEX idx_es_test_resit2_date (test_resit2_date) ALGORITHM=INPLACE, LOCK=NONE'],
            // SendLessonOpeningReminders ham subject_schedule_id orqali izlaydi —
            // bu ustunga ham covering index foydali.
            ['table' => 'student_grades', 'name' => 'idx_sg_subject_schedule_id',
                'sql' => 'ALTER TABLE student_grades ADD INDEX idx_sg_subject_schedule_id (subject_schedule_id) ALGORITHM=INPLACE, LOCK=NONE'],
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
                // ALGORITHM=INPLACE qo'llab-quvvatlanmasa fallback
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
            ['table' => 'exam_schedules', 'name' => 'idx_es_oski_date'],
            ['table' => 'exam_schedules', 'name' => 'idx_es_test_date'],
            ['table' => 'exam_schedules', 'name' => 'idx_es_oski_resit_date'],
            ['table' => 'exam_schedules', 'name' => 'idx_es_test_resit_date'],
            ['table' => 'exam_schedules', 'name' => 'idx_es_oski_resit2_date'],
            ['table' => 'exam_schedules', 'name' => 'idx_es_test_resit2_date'],
            ['table' => 'student_grades', 'name' => 'idx_sg_subject_schedule_id'],
        ];
        foreach ($indexes as $idx) {
            try {
                DB::statement("ALTER TABLE {$idx['table']} DROP INDEX {$idx['name']}");
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
};
