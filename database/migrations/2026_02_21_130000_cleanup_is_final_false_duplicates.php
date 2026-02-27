<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bir martalik tozalash migratsiyasi (faqat joriy semestr: 2026-01-26 dan keyin):
 *
 * 1-QADAM: Dublikatlarni tozalash — batch bo'lib (kuniga alohida)
 * 2-QADAM: Qolgan is_final=false ni is_final=true ga — batch bo'lib (5000 tadan)
 *
 * Batched approach — serverni lock qilmaydi, timeout bermaydi.
 */
return new class extends Migration {
    private const SEMESTER_START = '2026-01-26';
    private const BATCH_SIZE = 5000;

    public function up(): void
    {
        $semesterStart = self::SEMESTER_START;
        $today = \Carbon\Carbon::today()->toDateString();

        // 1-QADAM: Dublikatlarni kunma-kun tozalash
        // Bitta katta UPDATE o'rniga — har kunni alohida, qisqa tranzaksiya bilan
        $totalCleaned = 0;
        $dates = DB::table('student_grades')
            ->selectRaw('DATE(lesson_date) as grade_date')
            ->where('is_final', false)
            ->whereNull('deleted_at')
            ->whereRaw('DATE(lesson_date) >= ?', [$semesterStart])
            ->whereRaw('DATE(lesson_date) < ?', [$today])
            ->distinct()
            ->pluck('grade_date');

        foreach ($dates as $date) {
            try {
                $cleaned = DB::update("
                    UPDATE student_grades sg
                    INNER JOIN student_grades g2
                        ON g2.student_id = sg.student_id
                        AND g2.subject_id = sg.subject_id
                        AND DATE(g2.lesson_date) = DATE(sg.lesson_date)
                        AND g2.is_final = 1
                        AND g2.deleted_at IS NULL
                        AND g2.id != sg.id
                    SET sg.deleted_at = NOW()
                    WHERE sg.is_final = 0
                        AND sg.deleted_at IS NULL
                        AND DATE(sg.lesson_date) = ?
                ", [$date]);
                $totalCleaned += $cleaned;
            } catch (\Throwable $e) {
                Log::warning("[Cleanup] Date {$date} failed: {$e->getMessage()}");
            }
        }

        Log::info("[Cleanup] Step 1: Soft-deleted {$totalCleaned} duplicate is_final=false records");

        // 2-QADAM: Qolgan is_final=false ni batch bo'lib is_final=true ga o'zgartirish
        $totalUpdated = 0;
        do {
            $updated = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->where('is_final', false)
                ->where('lesson_date', '>=', $semesterStart)
                ->where('lesson_date', '<', $today)
                ->limit(self::BATCH_SIZE)
                ->update(['is_final' => true]);

            $totalUpdated += $updated;
        } while ($updated > 0);

        Log::info("[Cleanup] Step 2: Updated {$totalUpdated} records to is_final=true");
    }

    public function down(): void
    {
        // Bu tozalash migratsiyasi — orqaga qaytarish mumkin emas
    }
};
