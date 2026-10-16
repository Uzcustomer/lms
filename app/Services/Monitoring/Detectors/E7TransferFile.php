<?php

namespace App\Services\Monitoring\Detectors;

use App\Models\MonitoringDetector;
use App\Models\MonitoringTransferChecklist;
use App\Services\Monitoring\Contracts\Detector;
use App\Services\Monitoring\DetectorResult;
use App\Services\Monitoring\Finding;
use App\Services\Monitoring\OwnerResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * E-7. Ko'chirib kelgan talaba yig'majildida 6 majburiy hujjat bo'lishi va komissiya
 * asoslovchi hujjatlar aslini tekshirishi shart. (VMQ 578 3-il. 53, 39(a), 52)
 *
 * Trigger: M8 cheklistlari (buyruqlar reyestridagi «ko'chirib kelish» buyrug'idan
 *          avtomatik ochiladi yoki qo'lda qo'shiladi).
 * Kutilgan harakat: 6 hujjat «bor» va (require_original_check bo'lsa) «asli tekshirildi».
 */
class E7TransferFile implements Detector
{
    public function __construct(private OwnerResolver $owners)
    {
    }

    public function run(array $params, MonitoringDetector $detector): DetectorResult
    {
        $requireOriginal = (bool) ($params['require_original_check'] ?? true);
        $since = !empty($params['since_date']) ? Carbon::parse($params['since_date'])->toDateString() : null;
        $documents = MonitoringTransferChecklist::documents();

        $result = new DetectorResult();

        $checklists = MonitoringTransferChecklist::query()
            ->when($since, fn ($q) => $q->where(fn ($w) => $w->whereNull('arrived_at')->orWhere('arrived_at', '>=', $since)))
            ->get();

        $students = DB::table('students')
            ->whereIn('hemis_id', $checklists->pluck('student_hemis_id')->filter()->all())
            ->get(['hemis_id', 'department_id', 'specialty_id', 'level_code'])
            ->keyBy('hemis_id');

        foreach ($checklists as $checklist) {
            $missing = $checklist->missingDocuments($requireOriginal);
            if ($missing === []) {
                continue;
            }

            $labels = [];
            foreach ($missing as $key => $reason) {
                $labels[] = ($documents[$key] ?? $key) . ($reason === 'asli_tekshirilmagan' ? ' — asli tekshirilmagan' : '');
            }
            $done = count($documents) - count($missing);
            $student = $checklist->student_hemis_id ? $students->get($checklist->student_hemis_id) : null;
            $faculty = $student?->department_id;

            $result->add(new Finding(
                fingerprint: "E-7:{$checklist->id}",
                subjectType: 'talaba',
                subjectLabel: "{$checklist->full_name} — yig'majild to'liq emas ({$done}/" . count($documents) . ')',
                subjectRef: $checklist->student_id_number,
                details: [
                    'kelgan_sana' => $checklist->arrived_at?->toDateString(),
                    'yetishmaydi' => $labels,
                ],
                facultyHemisId: $faculty ? (int) $faculty : null,
                owner: $this->owners->registrarOfStudent($student, $params['registrar_office'] ?? null),
            ));
        }

        return $result;
    }
}
