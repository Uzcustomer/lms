<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * schedules jadvali uchun muhim indekslar.
 *
 * Bu jadvalda 343k+ qator bor, lekin asl migration biror indeks qo'shmagan
 * (schedule_hemis_id unique'dan tashqari). Test markazi sahifasidagi
 * lessonDatesRaw so'rovi:
 *   WHERE group_id IN (...) AND deleted_at IS NULL GROUP BY group_id, subject_id, subject_name
 * har gal full table scan qiladi va sahifa 504 beradi.
 *
 * ALGORITHM=INPLACE, LOCK=NONE — onlayn yaratish, jadvalni lock qilmasdan.
 */
return new class extends Migration {
    public function up(): void
    {
        $indexes = [
            // lessonDatesRaw uchun asosiy index — group_id+subject_id covering
            ['table' => 'schedules', 'name' => 'idx_sch_group_subject',
                'sql' => 'ALTER TABLE schedules ADD INDEX idx_sch_group_subject (group_id, subject_id) ALGORITHM=INPLACE, LOCK=NONE'],
            // deleted_at filtri uchun
            ['table' => 'schedules', 'name' => 'idx_sch_deleted_at',
                'sql' => 'ALTER TABLE schedules ADD INDEX idx_sch_deleted_at (deleted_at) ALGORITHM=INPLACE, LOCK=NONE'],
            // SendLessonOpeningReminders va boshqa sana bo'yicha izlovlar uchun
            ['table' => 'schedules', 'name' => 'idx_sch_lesson_date',
                'sql' => 'ALTER TABLE schedules ADD INDEX idx_sch_lesson_date (lesson_date) ALGORITHM=INPLACE, LOCK=NONE'],
            // education_year_current bo'yicha filtr
            ['table' => 'schedules', 'name' => 'idx_sch_education_year_current',
                'sql' => 'ALTER TABLE schedules ADD INDEX idx_sch_education_year_current (education_year_current) ALGORITHM=INPLACE, LOCK=NONE'],
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
            ['table' => 'schedules', 'name' => 'idx_sch_group_subject'],
            ['table' => 'schedules', 'name' => 'idx_sch_deleted_at'],
            ['table' => 'schedules', 'name' => 'idx_sch_lesson_date'],
            ['table' => 'schedules', 'name' => 'idx_sch_education_year_current'],
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
