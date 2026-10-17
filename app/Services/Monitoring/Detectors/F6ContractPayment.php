<?php

namespace App\Services\Monitoring\Detectors;

use App\Models\MonitoringDetector;
use App\Services\ContractCutoffs;
use App\Services\Monitoring\Contracts\Detector;
use App\Services\Monitoring\DetectorResult;
use App\Services\Monitoring\Finding;
use App\Services\Monitoring\OwnerResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * F-6. Kontrakt to'lovi bosqichlari: ≥25% — 1-kurs 15-sentabr (2026/2027 qabul — 1-noyabr), 2+ kurs 1-oktabr;
 * ≥50% — 1-yanvar; ≥75% — 1-mart; 100% — 1-may. (DR-2431 22; PF-58; OTFIV 7-son bayoni)
 *
 * Muddatlar kodda emas — Sozlamalar → «Kontrakt to'lov muddatlari — kurs kesimida» (ContractCutoffs):
 * superkontrakt turi → kurs jadvali → umumiy jadval. O'quv yili boshlanishidan oldingi sanalar (eski yildan
 * qolgan jadval) hisobga olinmaydi.
 * Trigger: o'qiyotgan talabaning shu o'quv yilidagi kontrakti (contract_list); o'tgan eng oxirgi bosqich foizi
 *          to'lanmagan. Kutilgan harakat: to'lov, direktor buyrug'i bilan muddat uzaytirilishi yoki chetlashtirish (E-10).
 */
class F6ContractPayment implements Detector
{
    public function __construct(private OwnerResolver $owners)
    {
    }

    public function run(array $params, MonitoringDetector $detector): DetectorResult
    {
        $year = (string) ($params['education_year'] ?? '2026-2027');
        $result = new DetectorResult([$year]);

        $startYear = (int) substr($year, 0, 4);
        $yearStart = $startYear > 0 ? Carbon::create($startYear, 8, 1)->startOfDay() : null;
        $today = now();

        $contracts = DB::table('contract_list')
            ->where('edu_year', 'like', $year . '%')
            ->whereNotNull('student_hemis_id')
            ->orderBy('id')
            ->get(['id', 'student_hemis_id', 'edu_cours_id', 'edu_contract_type_code', 'edu_contract_type_name', 'edu_contract_sum', 'paid_credit_amount'])
            ->keyBy('student_hemis_id'); // talabaning eng oxirgi shartnomasi (YN oldi qaydnoma bilan bir xil)
        if ($contracts->isEmpty()) {
            return $result;
        }

        $students = DB::table('students')
            ->whereIn('hemis_id', $contracts->keys()->all())
            ->where('student_status_code', UnexcusedAbsenceDetector::STUDYING)
            ->get(['hemis_id', 'full_name', 'student_id_number', 'group_name', 'department_id', 'specialty_id', 'level_code'])
            ->keyBy('hemis_id');

        foreach ($contracts as $hemisId => $contract) {
            $student = $students->get($hemisId);
            $sum = (float) $contract->edu_contract_sum;
            if ($student === null || $sum <= 0) {
                continue;
            }

            $course = $contract->edu_cours_id ? (int) $contract->edu_cours_id
                : (is_numeric($student->level_code) ? (int) $student->level_code - 10 : null);
            $schedule = ContractCutoffs::scheduleFor($course, $contract->edu_contract_type_code !== null ? (string) $contract->edu_contract_type_code : null);
            $stage = ContractCutoffs::passedStage($schedule['cutoffs'], $today, $yearStart);
            if ($stage === null) {
                continue; // birinchi bosqich muddati hali kelmagan
            }

            // Summa bo'yicha solishtiriladi (yaxlitlangan foiz 24,96 % ni 25 % deb o'tkazib yubormasin).
            $paid = (float) $contract->paid_credit_amount;
            $required = $sum * $stage['percent'] / 100;
            if ($paid >= $required - 0.005) {
                continue;
            }
            $paidPercent = round($paid / $sum * 100, 1);

            $result->add(new Finding(
                fingerprint: "F-6:{$hemisId}:{$year}",
                subjectType: 'tolov',
                subjectLabel: "{$student->full_name} ({$student->student_id_number}), {$student->group_name}",
                subjectRef: (string) $student->student_id_number,
                details: [
                    'oquv_yili' => $year,
                    'kurs' => $course,
                    'kontrakt_turi' => $contract->edu_contract_type_name,
                    'jadval' => $schedule['label'],
                    'muddat' => $stage['deadline'],
                    'talab_foizi' => $stage['percent'],
                    'tolov_foizi' => $paidPercent,
                    'yetishmaydi_summa' => round($required - $paid, 2),
                    'kutilgan_harakat' => "To'lov, direktor buyrug'i bilan muddat uzaytirilishi yoki chetlashtirish (E-10)",
                ],
                scopeKey: $year,
                facultyHemisId: $student->department_id ? (int) $student->department_id : null,
                owner: $this->owners->registrarOfStudent($student, $params['registrar_office'] ?? null),
            ));
        }

        return $result;
    }
}
