<?php

namespace App\Services\Monitoring\Imports;

use App\Models\MonitoringAdmissionRecommendation;
use App\Models\MonitoringDataImport;
use Throwable;

/**
 * A-5: kontrakt bo'yicha tavsiya etilganlar (mandat) importi. Mandat test markaziga qog'ozda
 * keladi — ro'yxat Agentlikning elektron nusxasidan yoki mark shablonidan yuklanadi.
 */
class RecommendationImporter
{
    public const FIELDS = [
        'full_name' => ['label' => 'F.I.Sh.', 'required' => true, 'aliases' => ['f.i.sh', 'fish', 'familiya', 'ф.и.о', 'full name']],
        'applicant_id' => ['label' => 'Abituriyent ID', 'required' => false, 'aliases' => ['abituriyent', 'id']],
        'passport' => ['label' => 'Pasport (seriya va raqam)', 'required' => false, 'aliases' => ['pasport', 'passport', 'паспорт']],
        'student_hemis_id' => ['label' => 'HEMIS ID (bo\'lsa)', 'required' => false, 'aliases' => ['hemis']],
        'specialty_name' => ['label' => 'Yo\'nalish / mutaxassislik', 'required' => false, 'aliases' => ["yo'nalish", 'mutaxassislik', 'направление']],
        'education_form' => ['label' => 'Ta\'lim shakli', 'required' => false, 'aliases' => ["ta'lim shakli", 'shakl', 'форма']],
        'contract_type_raw' => ['label' => 'Kontrakt turi', 'required' => false, 'aliases' => ['kontrakt', "to'lov", 'tolov', 'контракт']],
        'recommended_at' => ['label' => 'Tavsiya sanasi', 'required' => false, 'aliases' => ['sana', 'дата']],
    ];

    public function __construct(private StudentMatcher $matcher)
    {
    }

    public static function contractType(?string $raw): string
    {
        $v = ImportSheet::normalize($raw);
        if (str_contains($v, 'tabaqalash') || str_contains($v, 'дифференц')) {
            return 'tabaqalashtirilgan';
        }
        if (str_contains($v, "qo'shimcha") || str_contains($v, 'qoshimcha') || str_contains($v, 'super') || str_contains($v, 'повышен')) {
            return 'qoshimcha';
        }

        return 'oddiy';
    }

    public function import(MonitoringDataImport $import, ImportSheet $sheet, array $map, string $educationYear): MonitoringDataImport
    {
        $errors = [];
        $ok = 0;
        $fioScope = ['level_code' => '11']; // F.I.Sh. bo'yicha moslashda faqat 1-kurslar

        foreach ($sheet->rows as $i => $row) {
            $line = $i + 2;
            try {
                $fullName = ImportSheet::cell($row, $map['full_name'] ?? null);
                if (!$fullName) {
                    $errors[] = ['row' => $line, 'message' => 'F.I.Sh. bo\'sh.'];
                    continue;
                }
                $applicantId = ImportSheet::cell($row, $map['applicant_id'] ?? null);
                $passport = ImportSheet::cell($row, $map['passport'] ?? null);
                $hemisId = ImportSheet::cell($row, $map['student_hemis_id'] ?? null);
                $rawContract = ImportSheet::cell($row, $map['contract_type_raw'] ?? null);

                $key = MonitoringAdmissionRecommendation::makeDedupeKey($educationYear, $applicantId, $fullName, $passport);
                $existing = MonitoringAdmissionRecommendation::where('dedupe_key', $key)->first();

                $attributes = [
                    'data_import_id' => $import->id,
                    'education_year' => $educationYear,
                    'applicant_id' => $applicantId,
                    'full_name' => mb_substr($fullName, 0, 255),
                    'passport' => $passport ? strtoupper(preg_replace('/\s+/', '', $passport)) : null,
                    'specialty_name' => ImportSheet::cell($row, $map['specialty_name'] ?? null),
                    'education_form' => ImportSheet::cell($row, $map['education_form'] ?? null),
                    'contract_type' => self::contractType($rawContract),
                    'contract_type_raw' => $rawContract,
                    'recommended_at' => ImportSheet::date($row[$map['recommended_at'] ?? -1] ?? null),
                ];

                // Qo'lda tasdiqlangan moslik qayta importda buzilmaydi.
                if (!$existing?->match_confirmed) {
                    [$studentHemisId, $method] = $this->matcher->match($hemisId, null, $passport, $fullName, $fioScope);
                    $attributes += [
                        'student_hemis_id' => $studentHemisId,
                        'match_method' => $method,
                        'match_confirmed' => in_array($method, ['hemis_id', 'passport'], true),
                    ];
                }

                MonitoringAdmissionRecommendation::updateOrCreate(['dedupe_key' => $key], $attributes);
                $ok++;
            } catch (Throwable $e) {
                $errors[] = ['row' => $line, 'message' => mb_substr($e->getMessage(), 0, 300)];
            }
        }

        $import->forceFill([
            'status' => MonitoringDataImport::STATUS_DONE,
            'rows_total' => count($sheet->rows),
            'rows_ok' => $ok,
            'rows_error' => count($errors),
            'errors' => array_slice($errors, 0, 500),
        ])->save();

        return $import;
    }
}
