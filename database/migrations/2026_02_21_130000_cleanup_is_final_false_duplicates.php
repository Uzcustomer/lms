<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bir martalik tozalash migratsiyasi (faqat joriy semestr: 2026-01-26 dan keyin):
 *
 * 1-QADAM: Dublikatlarni tozalash — bir xil (student_id, subject_id, lesson_date) uchun
 *          is_final=true mavjud bo'lsa, is_final=false yozuvni soft-delete qilish
 *
 * 2-QADAM: Qolgan is_final=false yozuvlarni is_final=true ga o'zgartirish
 *          (bugundan oldingi — ular tarixiy, qayta import qilinmaydi)
 *
 * Eslatma: O'tgan semestr yozuvlariga (2026-01-26 dan oldingi) tegmaslik —
 * ular is_final=false bo'lib qoladi, lekin live/final import ularga tegmaydi.
 */
return new class extends Migration {
    // Joriy semestr boshlanish sanasi
    private const SEMESTER_START = '2026-01-26';

    public function up(): void
    {
        $semesterStart = self::SEMESTER_START;
        $today = \Carbon\Carbon::today();

        // 1-QADAM: Dublikatlarni tozalash (faqat joriy semestr ichida)
        // is_final=true mavjud bo'lganda is_final=false ni soft-delete
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
        ", [$semesterStart, $today->toDateString()]);

        Log::info("[Cleanup] Step 1: Soft-deleted {$cleaned} duplicate is_final=false records (>= {$semesterStart})");

        // 2-QADAM: Qolgan is_final=false yozuvlarni is_final=true ga o'zgartirish
        // Faqat joriy semestr ichida, bugundan oldingi
        $updated = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('is_final', false)
            ->where('lesson_date', '>=', $semesterStart)
            ->where('lesson_date', '<', $today)
            ->update(['is_final' => true]);

        Log::info("[Cleanup] Step 2: Updated {$updated} records to is_final=true (>= {$semesterStart})");
    }

    public function down(): void
    {
        // Bu tozalash migratsiyasi — orqaga qaytarish mumkin emas
    }
};
