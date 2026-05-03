<?php

namespace App\Services\Retake;

use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Talabaning akademik qarzdor fanlari ro'yxati.
 *
 * Mantiq:
 *  - student_subjects (o'quv rejasi) — talaba o'qishi shart bo'lgan fanlar
 *  - academic_records — HEMIS'dan kelgan baholar
 *  - LEFT JOIN: o'quv rejasidagi fan academic_records'da bahosiz bo'lsa →
 *    talaba shu fan bo'yicha qarzdor.
 *
 * StudentApiController.php (55-93-qatorlar) dagi pattern bilan mos.
 * HEMIS API ga real-time chaqiruv qilinmaydi — faqat lokal jadvallar.
 */
class RetakeDebtService
{
    /**
     * Talaba qarzdor bo'lgan fanlar ro'yxati.
     *
     * Qarzdor: o'quv rejasida bor, lekin academic_records'da bahosi yo'q yoki
     * past (NULL/2/0/retraining_status=1). Joriy semestr istisno.
     *
     * @return Collection<\stdClass>  Har element: subject_id, subject_name,
     *         semester_id, semester_name, credit, grade, retraining_status,
     *         debt_reason
     */
    public function debts(Student $student): Collection
    {
        $currentSemesterId = $student->semester_id;

        $rows = DB::table('student_subjects as ss')
            ->select([
                'ss.subject_id',
                'ss.subject_name',
                'ss.semester_id',
                'ss.curriculum_subject_hemis_id',
                DB::raw('COALESCE(ar.semester_name, sem.name, cs.semester_name) as semester_name'),
                'ar.id as ar_id',
                DB::raw('COALESCE(ar.credit, cs.credit) as credit'),
                'ar.grade',
                'ar.retraining_status',
            ])
            ->leftJoin('academic_records as ar', function ($join) use ($student) {
                $join->on('ar.student_id', '=', DB::raw((int) $student->hemis_id))
                     ->on('ar.subject_id', '=', 'ss.subject_id')
                     ->on('ar.semester_id', '=', 'ss.semester_id');
            })
            ->leftJoin('curriculum_subjects as cs', 'cs.curriculum_subject_hemis_id', '=', 'ss.curriculum_subject_hemis_id')
            ->leftJoin('semesters as sem', 'sem.code', '=', 'ss.semester_id')
            ->where('ss.student_hemis_id', $student->hemis_id)
            ->when($currentSemesterId, fn ($q) => $q->where('ss.semester_id', '!=', $currentSemesterId))
            ->where(function ($q) {
                $q->whereNull('ar.id')                       // o'quv rejada bor, academic_records'da yo'q
                  ->orWhereNull('ar.grade')                  // baho qo'yilmagan
                  ->orWhereIn('ar.grade', ['2', '0'])        // past baho
                  ->orWhere('ar.retraining_status', true);   // qayta o'qish kerak
            })
            ->orderBy('ss.semester_id')
            ->orderBy('ss.subject_name')
            ->get();

        return $rows->map(function ($r) {
            // Qarzdorlik sababini aniqlash
            if (!$r->ar_id) {
                $r->debt_reason = 'no_record';   // academic_records yozuvi yo'q
            } elseif ($r->grade === null) {
                $r->debt_reason = 'no_grade';
            } elseif (in_array((string) $r->grade, ['2', '0'], true)) {
                $r->debt_reason = 'low_grade';
            } elseif ((bool) $r->retraining_status) {
                $r->debt_reason = 'retraining';
            } else {
                $r->debt_reason = 'unknown';
            }

            // credit bo'lmasa default qiymat (kvitansiya hisobi uchun)
            $r->credit = $r->credit !== null ? (float) $r->credit : 0.0;

            return $r;
        });
    }

    /**
     * Talaba berilgan fan bo'yicha hali ham qarzdormi (HEMIS sync'dan keyin
     * auto-cancel uchun).
     */
    public function isStillDebtor(int $studentHemisId, string $subjectId, string $semesterId): bool
    {
        $record = DB::table('academic_records')
            ->where('student_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_id', $semesterId)
            ->first(['grade', 'retraining_status']);

        if (!$record) {
            // O'quv rejasida bo'lsa-yu, academic_records'da yozuv yo'q bo'lsa →
            // hali ham qarzdor (academic_records dan boshqa joydan kelgan
            // tasdiqdan keyin auto-cancel qilmaymiz).
            $inCurriculum = DB::table('student_subjects')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_id', $semesterId)
                ->exists();
            return $inCurriculum;
        }

        return $record->grade === null
            || in_array((string) $record->grade, ['2', '0'], true)
            || (bool) $record->retraining_status;
    }
}
