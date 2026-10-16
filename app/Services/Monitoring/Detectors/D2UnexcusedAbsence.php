<?php

namespace App\Services\Monitoring\Detectors;

use App\Models\MonitoringDetector;
use App\Services\Monitoring\Contracts\Detector;
use App\Services\Monitoring\DetectorResult;
use App\Services\Monitoring\Finding;
use App\Services\Monitoring\OwnerResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * D-2. Bir semestrda uzrli sababsiz 74 soatdan ortiq qoldirgan talaba ↔ chetlashtirish buyrug'i.
 * (VMQ 578 3-il. 49(g); DR-2431 27)
 *
 * Trigger: joriy o'quv yili, talabaning joriy semestri bo'yicha SUM(attendances.absent_off)
 *          (HEMIS sababsiz soatlari) > threshold_hours. exclude_medical bo'lsa, LMS'da
 *          tasdiqlangan tibbiy arizalar (medical_reasons) qamragan kunlardagi soatlar ayiriladi.
 * Kutilgan harakat: buyruqlar reyestrida shu o'quv yilida «chetlashtirish» buyrug'i bor
 *          yoki talaba endi «o'qimoqda» (student_status_code = 11) holatida emas.
 * Mas'ul: talaba fakulteti dekani.
 */
class D2UnexcusedAbsence implements Detector
{
    public const STUDYING = '11';

    public function __construct(private OwnerResolver $owners)
    {
    }

    public function run(array $params, MonitoringDetector $detector): DetectorResult
    {
        $threshold = (float) ($params['threshold_hours'] ?? 74);
        $excludeMedical = (bool) ($params['exclude_medical'] ?? true);
        $medicalReasons = array_values((array) ($params['medical_reasons'] ?? ['kasallik', 'tibbiy_korik']));
        $excludedTypes = array_map('strval', (array) ($params['excluded_training_types'] ?? []));

        $base = DB::table('attendances as a')
            ->join('students as s', 's.hemis_id', '=', 'a.student_hemis_id')
            ->where('a.education_year_current', true)
            ->whereColumn('a.semester_code', 's.semester_code')
            ->when($excludedTypes !== [], fn ($q) => $q->whereNotIn('a.training_type_code', $excludedTypes));

        // Shu run'da to'liq tekshirilgan semestrlar (joriy o'quv yili × talabalarning joriy semestrlari) —
        // faqat ulardagi istisnolar avtomatik yopiladi. Katta davomat jadvalini qayta skan qilmaslik uchun
        // semestrlar students'dan olinadi.
        $currentYear = DB::table('attendances')->where('education_year_current', true)->max('education_year_code');
        $scopes = $currentYear === null ? [] : DB::table('students')
            ->where('student_status_code', self::STUDYING)
            ->whereNotNull('semester_code')
            ->distinct()
            ->pluck('semester_code')
            ->map(fn ($semester) => self::scope($currentYear, $semester))
            ->values()->all();
        $result = new DetectorResult($scopes);

        $candidates = (clone $base)
            ->groupBy('a.student_hemis_id', 'a.education_year_code', 'a.semester_code')
            ->havingRaw('SUM(a.absent_off) > ?', [$threshold])
            ->selectRaw('a.student_hemis_id, a.education_year_code, MAX(a.education_year_name) as education_year_name, a.semester_code, MAX(a.semester_name) as semester_name, SUM(a.absent_off) as hours')
            ->get();

        if ($candidates->isEmpty()) {
            return $result;
        }

        $students = DB::table('students')
            ->whereIn('hemis_id', $candidates->pluck('student_hemis_id')->all())
            ->get(['hemis_id', 'full_name', 'student_id_number', 'group_name', 'department_id', 'student_status_code'])
            ->keyBy('hemis_id');

        foreach ($candidates as $row) {
            $student = $students->get($row->student_hemis_id);
            if ($student === null || (string) $student->student_status_code !== self::STUDYING) {
                // Talaba endi o'qimayapti — chetlashtirilgan yoki boshqa holatda: harakat bajarilgan.
                continue;
            }

            $excused = $excludeMedical && $medicalReasons !== []
                ? $this->medicallyExcusedHours($row, $medicalReasons, $excludedTypes)
                : 0.0;
            $net = (float) $row->hours - $excused;
            if ($net <= $threshold) {
                continue;
            }

            if ($this->hasExpulsionOrder($student, $row->education_year_code)) {
                continue;
            }

            $result->add(new Finding(
                fingerprint: "D-2:{$row->student_hemis_id}:{$row->education_year_code}:{$row->semester_code}",
                subjectType: 'talaba',
                subjectLabel: "{$student->full_name} ({$student->student_id_number}), {$student->group_name}",
                subjectRef: (string) $student->student_id_number,
                details: [
                    'oquv_yili' => $row->education_year_name ?: $row->education_year_code,
                    'semestr' => $row->semester_name ?: $row->semester_code,
                    'sababsiz_soat' => (float) $row->hours,
                    'tibbiy_ayirilgan_soat' => $excused,
                    'hisoblangan_soat' => $net,
                    'chegara' => $threshold,
                    'kutilgan_harakat' => 'Chetlashtirish buyrug\'i (yoki asosli istisno: belgilangan shakldagi tibbiy ma\'lumotnoma)',
                ],
                scopeKey: self::scope($row->education_year_code, $row->semester_code),
                facultyHemisId: $student->department_id ? (int) $student->department_id : null,
                owner: $this->owners->deanOfFaculty($student->department_id ? (int) $student->department_id : null),
            ));
        }

        return $result;
    }

    public static function scope($educationYearCode, $semesterCode): string
    {
        return "{$educationYearCode}:{$semesterCode}";
    }

    private function medicallyExcusedHours(object $row, array $reasons, array $excludedTypes): float
    {
        return (float) DB::table('attendances as a')
            ->where('a.student_hemis_id', $row->student_hemis_id)
            ->where('a.education_year_code', $row->education_year_code)
            ->where('a.semester_code', $row->semester_code)
            ->when($excludedTypes !== [], fn ($q) => $q->whereNotIn('a.training_type_code', $excludedTypes))
            ->whereExists(function ($q) use ($reasons) {
                $q->selectRaw('1')
                    ->from('absence_excuses as e')
                    ->whereColumn('e.student_hemis_id', 'a.student_hemis_id')
                    ->where('e.status', 'approved')
                    ->whereIn('e.reason', $reasons)
                    ->whereRaw('DATE(a.lesson_date) BETWEEN e.start_date AND e.end_date');
            })
            ->sum('a.absent_off');
    }

    private function hasExpulsionOrder(object $student, $educationYearCode): bool
    {
        $since = is_numeric($educationYearCode) ? Carbon::create((int) $educationYearCode, 8, 1)->toDateString() : null;

        return DB::table('monitoring_orders')
            ->where('order_type', 'chetlashtirish')
            ->where(function ($q) use ($student) {
                $q->where('student_hemis_id', $student->hemis_id);
                if ($student->student_id_number) {
                    $q->orWhere('student_id_number', $student->student_id_number);
                }
            })
            ->when($since, fn ($q) => $q->where('order_date', '>=', $since))
            ->exists();
    }
}
