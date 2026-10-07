<?php

namespace App\Services;

use App\Models\AbsenceExcuse;
use Carbon\Carbon;

/**
 * Sababli ariza orqali MT ochilishi — talabaning fayl yuklashi va
 * o'qituvchining (YN ga yuborilgandan keyin ham) baho qo'yishi bir xil
 * qoidaga tayanadi, aks holda talaba yuklagan ishni o'qituvchi baholay
 * olmay qolardi.
 */
class SababliMtWindow
{
    /**
     * MT ni ochadigan tasdiqlangan ariza.
     *
     * Ariza shu fanni qamrab olgan bo'lishi kerak. Nazorat turlari ro'yxatiga
     * MT alohida kiritilmagan bo'lishi mumkin (masalan faqat JN va OSKI
     * belgilangan) — u holda MT muddati yo'qlik davri ichida tugagan bo'lsa
     * yetarli: talaba aynan kasalligi sababli ulgurmagan bo'ladi.
     * MT muddati yo'qlik boshlanishidan oldin tugagan bo'lsa ariza bu MT ga
     * aloqador emas.
     */
    public static function excuse(string $studentHemisId, $subjectId, $mtDeadline): ?AbsenceExcuse
    {
        if (!$subjectId || !$mtDeadline) {
            return null;
        }

        $deadline = Carbon::parse($mtDeadline);

        return AbsenceExcuse::where('status', 'approved')
            ->where('student_hemis_id', $studentHemisId)
            ->whereNotNull('reviewed_at')
            ->whereHas('makeups', fn ($q) => $q->where('subject_id', $subjectId))
            ->orderByDesc('reviewed_at')
            ->get()
            ->first(function (AbsenceExcuse $candidate) use ($deadline, $subjectId) {
                if ($deadline->lt(Carbon::parse($candidate->start_date))) {
                    return false;
                }

                $hasMtMakeup = $candidate->makeups
                    ->where('subject_id', $subjectId)
                    ->where('assessment_type', 'mt')
                    ->isNotEmpty();
                if ($hasMtMakeup) {
                    return true;
                }

                return $deadline->lte(Carbon::parse($candidate->end_date)->endOfDay());
            });
    }

    /** Ariza tasdiqlangan kundan boshlab yo'qlik kunlari soni — shu muddatgacha ochiq. */
    public static function closesAt(AbsenceExcuse $excuse): Carbon
    {
        $days = Carbon::parse($excuse->start_date)->diffInDays(Carbon::parse($excuse->end_date)) + 1;

        return Carbon::parse($excuse->reviewed_at)->addDays($days)->endOfDay();
    }
}
