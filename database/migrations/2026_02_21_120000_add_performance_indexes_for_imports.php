<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Lock wait timeout xatosini hal qilish uchun muhim indekslar.
 *
 * ALGORITHM=INPLACE, LOCK=NONE — jadvalni lock qilmasdan online index yaratadi.
 * Bu production serverda 504 timeout muammosini oldini oladi.
 */
return new class extends Migration {
    public function up(): void
    {
        // Har bir indexni alohida va LOCK=NONE bilan yaratish
        // Agar index allaqachon mavjud bo'lsa, o'tkazib yuborish

        $indexes = [
            [
                'table' => 'student_grades',
                'name' => 'idx_sg_lesson_date',
                'sql' => 'ALTER TABLE student_grades ADD INDEX idx_sg_lesson_date (lesson_date) ALGORITHM=INPLACE, LOCK=NONE',
            ],
            [
                'table' => 'student_grades',
                'name' => 'idx_sg_deleted_at',
                'sql' => 'ALTER TABLE student_grades ADD INDEX idx_sg_deleted_at (deleted_at) ALGORITHM=INPLACE, LOCK=NONE',
            ],
            [
                'table' => 'student_grades',
                'name' => 'idx_sg_retake_lookup',
                'sql' => 'ALTER TABLE student_grades ADD INDEX idx_sg_retake_lookup (student_hemis_id, subject_id, lesson_date, lesson_pair_code) ALGORITHM=INPLACE, LOCK=NONE',
            ],
            [
                'table' => 'attendance_controls',
                'name' => 'idx_ac_deleted_at',
                'sql' => 'ALTER TABLE attendance_controls ADD INDEX idx_ac_deleted_at (deleted_at) ALGORITHM=INPLACE, LOCK=NONE',
            ],
        ];

        foreach ($indexes as $index) {
            try {
                // Index allaqachon mavjudmi tekshirish
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
        Schema::table('student_grades', function ($table) {
            $table->dropIndex('idx_sg_lesson_date');
            $table->dropIndex('idx_sg_deleted_at');
            $table->dropIndex('idx_sg_retake_lookup');
        });

        Schema::table('attendance_controls', function ($table) {
            $table->dropIndex('idx_ac_deleted_at');
        });
    }
};
