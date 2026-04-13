<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Dars belgilash" (lesson-assignment) hisoboti 504 timeout berayotgani
 * sabab: student_grades va attendance_controls jadvallarida
 * subject_schedule_id ustuniga index yo'q edi.
 *
 * Hisobot bir kun uchun ~1000 ta scheduleni tekshiradi:
 *   WHERE subject_schedule_id IN (1000 ta ID)
 * Index bo'lmasa — to'liq skaner (millionlab qator), sekundlar emas, daqiqalarga ketadi.
 */
return new class extends Migration {
    public function up(): void
    {
        $indexes = [
            [
                'table' => 'student_grades',
                'name' => 'idx_sg_subject_schedule_id',
                'sql' => 'ALTER TABLE student_grades ADD INDEX idx_sg_subject_schedule_id (subject_schedule_id) ALGORITHM=INPLACE, LOCK=NONE',
            ],
            [
                'table' => 'attendance_controls',
                'name' => 'idx_ac_subject_schedule_id',
                'sql' => 'ALTER TABLE attendance_controls ADD INDEX idx_ac_subject_schedule_id (subject_schedule_id) ALGORITHM=INPLACE, LOCK=NONE',
            ],
        ];

        foreach ($indexes as $index) {
            try {
                $exists = DB::select("SHOW INDEX FROM {$index['table']} WHERE Key_name = ?", [$index['name']]);
                if (!empty($exists)) {
                    Log::info("[Migration] Index {$index['name']} already exists, skipping.");
                    continue;
                }

                DB::statement($index['sql']);
                Log::info("[Migration] Created index {$index['name']} successfully.");
            } catch (\Throwable $e) {
                Log::warning("[Migration] Index {$index['name']} failed: {$e->getMessage()}");
            }
        }
    }

    public function down(): void
    {
        $drops = [
            ['student_grades', 'idx_sg_subject_schedule_id'],
            ['attendance_controls', 'idx_ac_subject_schedule_id'],
        ];

        foreach ($drops as [$table, $name]) {
            try {
                DB::statement("ALTER TABLE {$table} DROP INDEX {$name}");
            } catch (\Throwable $e) {
                Log::warning("[Migration] Drop index {$name} failed: {$e->getMessage()}");
            }
        }
    }
};
