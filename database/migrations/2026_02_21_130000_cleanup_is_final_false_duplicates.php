<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bir martalik tozalash migratsiyasi:
 *
 * 1-QADAM: Dublikatlarni tozalash — bir xil (student_id, subject_id, lesson_date) uchun
 *          is_final=true mavjud bo'lsa, is_final=false yozuvni soft-delete qilish
 *
 * 2-QADAM: Qolgan barcha eski is_final=false yozuvlarni is_final=true ga o'zgartirish
 *          (bugundan oldingi — ular tarixiy yozuvlar, qayta import qilinmaydi)
 *
 * Sabab: 2026_02_17 migratsiya barcha eski recordlarni is_final=false default qilib qo'ygan,
 * lekin faqat backfill qilingan kunlar uchun yangi is_final=true recordlar yaratilgan.
 * Natijada: ~2M+ eski yozuv is_final=false qolib ketgan + dublikatlar paydo bo'lgan.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1-QADAM: Dublikatlarni tozalash (is_final=true mavjud bo'lganda is_final=false ni o'chirish)
        // Katta jadvalda lock timeout bo'lmasligi uchun 30 kunlik oynalarda bajariladi
        $minDate = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('is_final', false)
            ->min(DB::raw('DATE(lesson_date)'));

        if (!$minDate) {
            Log::info('[Cleanup] No is_final=false records found, skipping.');
            return;
        }

        $currentStart = \Carbon\Carbon::parse($minDate)->startOfDay();
        $today = \Carbon\Carbon::today();
        $totalCleaned = 0;

        while ($currentStart < $today) {
            $currentEnd = $currentStart->copy()->addDays(30);
            if ($currentEnd > $today) {
                $currentEnd = $today;
            }

            // Dublikatlarni soft-delete
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
                    AND DATE(sg.lesson_date) >= ?
                    AND DATE(sg.lesson_date) < ?
            ", [$currentStart->toDateString(), $currentEnd->toDateString()]);

            $totalCleaned += $cleaned;
            $currentStart = $currentEnd;
        }

        Log::info("[Cleanup] Step 1: Soft-deleted {$totalCleaned} duplicate is_final=false records");

        // 2-QADAM: Qolgan is_final=false yozuvlarni is_final=true ga o'zgartirish
        // Bugundan oldingi barcha yozuvlar — tarixiy, qayta import qilinmaydi
        $updated = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('is_final', false)
            ->where('lesson_date', '<', $today)
            ->update(['is_final' => true]);

        Log::info("[Cleanup] Step 2: Updated {$updated} old records to is_final=true");
    }

    public function down(): void
    {
        // Bu tozalash migratsiyasi — orqaga qaytarish mumkin emas
    }
};
