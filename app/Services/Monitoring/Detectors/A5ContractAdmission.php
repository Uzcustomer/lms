<?php

namespace App\Services\Monitoring\Detectors;

use App\Models\MonitoringAdmissionRecommendation;
use App\Models\MonitoringDetector;
use App\Services\Monitoring\Contracts\Detector;
use App\Services\Monitoring\DetectorResult;
use App\Services\Monitoring\Finding;
use App\Services\Monitoring\OwnerResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A-5. 2026/2027: kontrakt to'lovi va qabul hujjatlarining yagona so'nggi muddati — 01.11.2026;
 * abituriyentlarni 3 ish kunida xabardor qilish; HEMISga to'liq kiritish.
 * (OTFIV komissiyasi 7-son bayoni; PF-58; to'lov ulushi — VMQ 578 1-il.)
 *
 * Trigger: mandat bo'yicha tavsiya etilganlar (monitoring_admission_recommendations).
 * Kutilgan harakat: to'lov ≥ chegara (contract_list) → qabul buyrug'i (buyruqlar reyestri) → HEMIS kontingenti.
 * Signal: to'lamagan bo'lib kontingentda turgan; to'lab buyrug'i chiqmagan; buyrug'i bor, HEMISda yo'q.
 * Muddatgacha — prognoz (faqat ogohlantirish), muddatdan keyin — haqiqiy istisno.
 */
class A5ContractAdmission implements Detector
{
    public function __construct(private OwnerResolver $owners)
    {
    }

    public function run(array $params, MonitoringDetector $detector): DetectorResult
    {
        $year = (string) ($params['education_year'] ?? '2026-2027');
        $deadline = Carbon::parse($params['deadline'] ?? '2026-11-01')->endOfDay();
        $minPercent = (float) ($params['min_paid_percent'] ?? 25);
        $minPercentSpecial = (float) ($params['min_paid_percent_special'] ?? 50);
        $forecast = (bool) ($params['forecast_before_deadline'] ?? true) && now()->lte($deadline);

        $result = new DetectorResult([$year]);

        if ((bool) ($params['check_notice_evidence'] ?? true)) {
            $result->add(new Finding(
                fingerprint: "A-5:xabarnoma:{$year}",
                subjectType: 'ariza',
                subjectLabel: "Abituriyentlarni xabardor qilish dalillari ({$year})",
                details: [
                    'talab' => "Sayt, ijtimoiy tarmoq, shaxsiy kabinet va SMS orqali 3 ish kunida xabardor qilinganini tasdiqlovchi dalillarni yuklang (e'lon havolasi, SMS hisoboti).",
                ],
                scopeKey: $year,
            ));
        }

        $recommendations = MonitoringAdmissionRecommendation::where('education_year', $year)->get();
        if ($recommendations->isEmpty()) {
            return $result;
        }

        $hemisIds = $recommendations->pluck('student_hemis_id')->filter()->unique()->values()->all();
        $students = DB::table('students')->whereIn('hemis_id', $hemisIds)
            ->get(['hemis_id', 'student_id_number', 'student_status_code', 'department_id', 'specialty_id', 'level_code', 'group_name'])
            ->keyBy('hemis_id');
        $contracts = DB::table('contract_list')->whereIn('student_hemis_id', $hemisIds)
            ->where('edu_year', 'like', $year . '%')
            ->orderBy('id')
            ->get(['student_hemis_id', 'edu_contract_sum', 'paid_credit_amount'])
            ->keyBy('student_hemis_id');
        $orders = DB::table('monitoring_orders')->where('order_type', 'qabul')
            ->where(function ($q) use ($hemisIds, $students) {
                $q->whereIn('student_hemis_id', $hemisIds ?: [0])
                    ->orWhereIn('student_id_number', $students->pluck('student_id_number')->filter()->all() ?: ['—']);
            })
            ->get(['student_hemis_id', 'student_id_number']);
        $orderedHemis = $orders->pluck('student_hemis_id')->filter()->map(fn ($v) => (string) $v)->all();
        $orderedIdNumbers = $orders->pluck('student_id_number')->filter()->all();

        foreach ($recommendations as $rec) {
            if (!$rec->student_hemis_id) {
                continue; // HEMIS'da topilmagan: to'lamagan va qabul qilinmagan bo'lsa — qoidabuzarlik emas
            }

            $student = $students->get($rec->student_hemis_id);
            $contract = $contracts->get($rec->student_hemis_id);
            $required = $rec->isSpecialContract() ? $minPercentSpecial : $minPercent;
            $paidPercent = ($contract && (float) $contract->edu_contract_sum > 0)
                ? round(((float) $contract->paid_credit_amount / (float) $contract->edu_contract_sum) * 100, 1)
                : 0.0;
            $paid = $paidPercent >= $required;
            $inContingent = $student !== null && (string) $student->student_status_code === D2UnexcusedAbsence::STUDYING;
            $hasOrder = in_array((string) $rec->student_hemis_id, $orderedHemis, true)
                || ($student?->student_id_number && in_array($student->student_id_number, $orderedIdNumbers, true));

            $signals = [];
            if (!$paid && $inContingent) {
                $signals[] = "To'lov {$paidPercent} % (talab {$required} %), lekin kontingentda turibdi";
            }
            if ($paid && !$hasOrder) {
                $signals[] = "To'lov qilingan ({$paidPercent} %), qabul buyrug'i reyestrda yo'q";
            }
            if ($paid && $hasOrder && $student === null) {
                $signals[] = 'Qabul buyrug\'i bor, HEMIS kontingentida topilmadi';
            }
            if ($signals === []) {
                continue;
            }

            $result->add(new Finding(
                fingerprint: "A-5:{$rec->id}",
                subjectType: 'tolov',
                subjectLabel: $rec->full_name . ($rec->specialty_name ? " — {$rec->specialty_name}" : ''),
                subjectRef: $rec->applicant_id,
                details: [
                    'oquv_yili' => $year,
                    'kontrakt_turi' => MonitoringAdmissionRecommendation::CONTRACT_TYPES[$rec->contract_type] ?? $rec->contract_type,
                    'tolov_foizi' => $paidPercent,
                    'talab_foizi' => $required,
                    'qabul_buyrugi' => $hasOrder,
                    'kontingentda' => $inContingent,
                    'muddat' => $deadline->toDateString(),
                    'signallar' => $signals,
                    'moslik' => $rec->match_confirmed ? 'tasdiqlangan' : (MonitoringAdmissionRecommendation::MATCH_METHODS[$rec->match_method] ?? $rec->match_method),
                ],
                scopeKey: $year,
                facultyHemisId: $student?->department_id ? (int) $student->department_id : null,
                owner: $this->owners->registrarOfStudent($student, $params['registrar_office'] ?? null),
                warningOnly: $forecast,
            ));
        }

        return $result;
    }
}
