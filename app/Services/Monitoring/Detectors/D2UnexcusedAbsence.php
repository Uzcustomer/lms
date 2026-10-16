<?php

namespace App\Services\Monitoring\Detectors;

use App\Models\MonitoringDetector;
use App\Services\Monitoring\DetectorResult;
use App\Services\Monitoring\Finding;
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
class D2UnexcusedAbsence extends UnexcusedAbsenceDetector
{
    public function run(array $params, MonitoringDetector $detector): DetectorResult
    {
        $threshold = (float) ($params['threshold_hours'] ?? 74);
        $excludeMedical = (bool) ($params['exclude_medical'] ?? true);
        $medicalReasons = array_values((array) ($params['medical_reasons'] ?? ['kasallik', 'tibbiy_korik']));
        $excludedTypes = array_map('strval', (array) ($params['excluded_training_types'] ?? []));

        $result = new DetectorResult($this->currentScopes());

        $candidates = $this->candidates($excludedTypes, '>', $threshold);
        if ($candidates->isEmpty()) {
            return $result;
        }

        $students = $this->students($candidates);

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
