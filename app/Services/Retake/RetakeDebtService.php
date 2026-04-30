<?php

namespace App\Services\Retake;

use App\Models\AcademicRecord;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Talabaning akademik qarzdor fanlari ro'yxati (lokal academic_records jadvalidan).
 *
 * HEMIS API ga REAL-TIME chaqiruv qilinmaydi — faqat lokal sync qilingan
 * ma'lumotlar ishlatiladi. Mantiq StudentApiController.php (74-79) bilan mos.
 */
class RetakeDebtService
{
    /**
     * Talaba qarzdor bo'lgan fanlar ro'yxati.
     *
     * @return Collection<AcademicRecord>
     */
    public function debts(Student $student): Collection
    {
        return AcademicRecord::query()
            ->where('student_id', $student->hemis_id)
            ->where(function ($q) {
                $q->whereNull('grade')
                  ->orWhereIn('grade', ['2', '0'])
                  ->orWhere('retraining_status', true);
            })
            ->when($student->semester_id, function ($q) use ($student) {
                $q->where('semester_id', '!=', $student->semester_id);
            })
            ->orderBy('semester_id')
            ->orderBy('subject_name')
            ->get();
    }

    /**
     * Talaba berilgan fan bo'yicha qarzdormi (HEMIS sync'dan keyin tekshirish).
     */
    public function isStillDebtor(int $studentHemisId, string $subjectId, string $semesterId): bool
    {
        $record = AcademicRecord::query()
            ->where('student_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_id', $semesterId)
            ->first();

        if (!$record) {
            // academic_records'da yozuv yo'q — qarzdor emas (yoki o'chirilgan)
            return false;
        }

        return $record->grade === null
            || in_array((string) $record->grade, ['2', '0'], true)
            || (bool) $record->retraining_status;
    }
}
