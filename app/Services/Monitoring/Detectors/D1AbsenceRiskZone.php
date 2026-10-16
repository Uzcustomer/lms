<?php

namespace App\Services\Monitoring\Detectors;

use App\Models\MonitoringDetector;
use App\Services\Monitoring\DetectorResult;
use App\Services\Monitoring\Finding;

/**
 * D-1. Xavf zonasi: semestrda sababsiz 30–74 soat — intizomiy chora (Filial ichki tartib-qoidalari;
 * VMQ 578 3-il. 49(v) umumiy havola). Katalogda ⚠ — ogohlantirish rejimida boshlanadi.
 *
 * Trigger: D-2 bilan bir xil hisob (joriy semestr, HEMIS sababsiz soatlari, tibbiy arizalar ayirilgan),
 *          min_hours ≤ soat ≤ max_hours. Soat max_hours dan oshsa — D-2 ga o'tadi, bu yerda yopiladi.
 * Mas'ul: talaba fakulteti dekani.
 */
class D1AbsenceRiskZone extends UnexcusedAbsenceDetector
{
    public function run(array $params, MonitoringDetector $detector): DetectorResult
    {
        $min = (float) ($params['min_hours'] ?? 30);
        $max = (float) ($params['max_hours'] ?? 74);
        $excludeMedical = (bool) ($params['exclude_medical'] ?? true);
        $medicalReasons = array_values((array) ($params['medical_reasons'] ?? ['kasallik', 'tibbiy_korik']));
        $excludedTypes = array_map('strval', (array) ($params['excluded_training_types'] ?? []));

        $result = new DetectorResult($this->currentScopes());

        $candidates = $this->candidates($excludedTypes, '>=', $min);
        if ($candidates->isEmpty()) {
            return $result;
        }

        $students = $this->students($candidates);

        foreach ($candidates as $row) {
            $student = $students->get($row->student_hemis_id);
            if ($student === null || (string) $student->student_status_code !== self::STUDYING) {
                continue;
            }

            $excused = $excludeMedical && $medicalReasons !== []
                ? $this->medicallyExcusedHours($row, $medicalReasons, $excludedTypes)
                : 0.0;
            $net = (float) $row->hours - $excused;
            if ($net < $min || $net > $max) {
                continue;
            }

            $result->add(new Finding(
                fingerprint: "D-1:{$row->student_hemis_id}:{$row->education_year_code}:{$row->semester_code}",
                subjectType: 'talaba',
                subjectLabel: "{$student->full_name} ({$student->student_id_number}), {$student->group_name}",
                subjectRef: (string) $student->student_id_number,
                details: [
                    'oquv_yili' => $row->education_year_name ?: $row->education_year_code,
                    'semestr' => $row->semester_name ?: $row->semester_code,
                    'sababsiz_soat' => (float) $row->hours,
                    'tibbiy_ayirilgan_soat' => $excused,
                    'hisoblangan_soat' => $net,
                    'chegara' => "{$min}–{$max}",
                    'chetlashtirishgacha' => max(0, $max - $net),
                    'kutilgan_harakat' => 'Intizomiy chora (ichki tartib-qoidalar bo\'yicha) va profilaktik suhbat',
                ],
                scopeKey: self::scope($row->education_year_code, $row->semester_code),
                facultyHemisId: $student->department_id ? (int) $student->department_id : null,
                owner: $this->owners->deanOfFaculty($student->department_id ? (int) $student->department_id : null),
            ));
        }

        return $result;
    }
}
