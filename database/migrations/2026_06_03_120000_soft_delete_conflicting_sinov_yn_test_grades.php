<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Soxta YN test baholarini tozalash.
 *
 * Sabab: yopilish shakli ('closing_form') vaqtincha 'sinov' bo'lgan paytda
 * JournalController::submitToYn() har bir talaba uchun JN o'rtachasini
 * "test bahosi" (training_type_code=102, reason='sinov_yn_test') sifatida
 * yozib qo'ygan. Ayrim fanlarda esa AYNI paytda haqiqiy test markazi
 * natijasi (reason!='sinov_yn_test') ham bor — natijada MAX(grade) eski/soxta
 * qiymatni tanlashga sabab bo'lgan.
 *
 * Bu migration FAQAT ziddiyatli holatlarni tozalaydi: ya'ni o'sha
 * talaba/fan/semestr uchun haqiqiy (sinov bo'lmagan) 102-qator ham mavjud
 * bo'lsa, soxta 'sinov_yn_test' qatorini soft-delete qiladi. Haqiqiy sinov
 * fanlariga (faqat sinov qatori bor) TEGILMAYDI.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ziddiyatli soxta qatorlar id'lari: real (sinov bo'lmagan) 102-qator
        // bilan birga turgan 'sinov_yn_test' qatorlar.
        $ids = DB::table('student_grades as s')
            ->join('student_grades as r', function ($join) {
                $join->on('r.student_hemis_id', '=', 's.student_hemis_id')
                    ->on('r.subject_id', '=', 's.subject_id')
                    ->on('r.semester_code', '=', 's.semester_code')
                    ->where('r.training_type_code', 102)
                    ->where(function ($q) {
                        $q->where('r.reason', '<>', 'sinov_yn_test')
                            ->orWhereNull('r.reason');
                    })
                    ->whereNull('r.deleted_at');
            })
            ->where('s.training_type_code', 102)
            ->where('s.reason', 'sinov_yn_test')
            ->whereNull('s.deleted_at')
            ->distinct()
            ->pluck('s.id');

        if ($ids->isNotEmpty()) {
            DB::table('student_grades')
                ->whereIn('id', $ids)
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Ma'lumotni to'g'rilash — qaytarib bo'lmaydi (qaysi qatorlar aynan shu
        // migration tomonidan o'chirilgani ishonchli saqlanmaydi). Ataylab no-op.
    }
};
