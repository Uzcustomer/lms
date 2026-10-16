<?php

namespace App\Services\Monitoring\Detectors;

use App\Services\Monitoring\Contracts\Detector;
use App\Services\Monitoring\OwnerResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * D-1 va D-2 uchun umumiy hisob: joriy o'quv yili, talabaning joriy semestri bo'yicha
 * SUM(attendances.absent_off) — HEMIS sababsiz soatlari; LMS'da tasdiqlangan tibbiy arizalar
 * qamragan kunlardagi soatlar ayiriladi.
 */
abstract class UnexcusedAbsenceDetector implements Detector
{
    public const STUDYING = '11';

    public function __construct(protected OwnerResolver $owners)
    {
    }

    public static function scope($educationYearCode, $semesterCode): string
    {
        return "{$educationYearCode}:{$semesterCode}";
    }

    /**
     * Shu run'da to'liq tekshiriladigan semestrlar (joriy o'quv yili × talabalarning joriy semestrlari) —
     * faqat ulardagi istisnolar avtomatik yopiladi. Katta davomat jadvalini qayta skan qilmaslik uchun
     * semestrlar students'dan olinadi.
     */
    protected function currentScopes(): array
    {
        $currentYear = DB::table('attendances')->where('education_year_current', true)->max('education_year_code');

        return $currentYear === null ? [] : DB::table('students')
            ->where('student_status_code', self::STUDYING)
            ->whereNotNull('semester_code')
            ->distinct()
            ->pluck('semester_code')
            ->map(fn ($semester) => self::scope($currentYear, $semester))
            ->values()->all();
    }

    /** Joriy semestrda sababsiz soatlari yig'indisi $operator $hours bo'lgan talabalar. */
    protected function candidates(array $excludedTypes, string $operator, float $hours): Collection
    {
        return DB::table('attendances as a')
            ->join('students as s', 's.hemis_id', '=', 'a.student_hemis_id')
            ->where('a.education_year_current', true)
            ->whereColumn('a.semester_code', 's.semester_code')
            ->when($excludedTypes !== [], fn ($q) => $q->whereNotIn('a.training_type_code', $excludedTypes))
            ->groupBy('a.student_hemis_id', 'a.education_year_code', 'a.semester_code')
            ->havingRaw("SUM(a.absent_off) {$operator} ?", [$hours])
            ->selectRaw('a.student_hemis_id, a.education_year_code, MAX(a.education_year_name) as education_year_name, a.semester_code, MAX(a.semester_name) as semester_name, SUM(a.absent_off) as hours')
            ->get();
    }

    protected function students(Collection $candidates): Collection
    {
        return DB::table('students')
            ->whereIn('hemis_id', $candidates->pluck('student_hemis_id')->all())
            ->get(['hemis_id', 'full_name', 'student_id_number', 'group_name', 'department_id', 'student_status_code'])
            ->keyBy('hemis_id');
    }

    protected function medicallyExcusedHours(object $row, array $reasons, array $excludedTypes): float
    {
        return (float) DB::table('attendances as a')
            ->where('a.student_hemis_id', $row->student_hemis_id)
            ->where('a.education_year_code', $row->education_year_code)
            ->where('a.semester_code', $row->semester_code)
            ->when($excludedTypes !== [], fn ($q) => $q->whereNotIn('a.training_type_code', $excludedTypes))
            ->whereExists(function (Builder $q) use ($reasons) {
                $q->selectRaw('1')
                    ->from('absence_excuses as e')
                    ->whereColumn('e.student_hemis_id', 'a.student_hemis_id')
                    ->where('e.status', 'approved')
                    ->whereIn('e.reason', $reasons)
                    ->whereRaw('DATE(a.lesson_date) BETWEEN e.start_date AND e.end_date');
            })
            ->sum('a.absent_off');
    }
}
