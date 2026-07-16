<?php

namespace App\Imports;

use App\Models\AdmissionIndicator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Qabul ko'rsatkichlarini Excel (.xlsx) fayldan import qilish.
 *
 * Kutilayotgan sarlavhalar (WithHeadingRow — kichik harf, bo'sh joy "_" ga):
 *   qabul_yili | talim_turi | talim_shakli | mutaxassislik |
 *   mutaxassislik_kodi | tolov_shakli | reja | qabul_soni | min_ball | izoh
 */
class AdmissionIndicatorImport implements ToCollection, WithHeadingRow
{
    /** @var int Muvaffaqiyatli import qilingan qatorlar soni */
    public int $imported = 0;

    /** @var array Xatolik bo'lgan qatorlar [ ['row' => n, 'error' => '...'] ] */
    public array $errors = [];

    public function __construct(private ?int $userId = null)
    {
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            // Excel qator raqami (sarlavha 1-qator, ma'lumot 2-qatordan)
            $rowNumber = $index + 2;

            $year = $this->intOrNull($row['qabul_yili'] ?? null);

            // Butunlay bo'sh qatorlarni jimgina o'tkazib yuboramiz
            if ($year === null && $this->isRowEmpty($row)) {
                continue;
            }

            if ($year === null || $year < 1900 || $year > 2100) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'error' => "Qabul yili noto'g'ri yoki bo'sh (1900–2100 oralig'ida bo'lishi kerak)",
                ];
                continue;
            }

            AdmissionIndicator::create([
                'qabul_yili' => $year,
                'talim_turi' => $this->strOrNull($row['talim_turi'] ?? null),
                'talim_shakli' => $this->strOrNull($row['talim_shakli'] ?? null),
                'mutaxassislik' => $this->strOrNull($row['mutaxassislik'] ?? null),
                'mutaxassislik_kodi' => $this->strOrNull($row['mutaxassislik_kodi'] ?? null),
                'tolov_shakli' => $this->strOrNull($row['tolov_shakli'] ?? null),
                'reja' => $this->intOrNull($row['reja'] ?? null),
                'qabul_soni' => $this->intOrNull($row['qabul_soni'] ?? null),
                'min_ball' => $this->floatOrNull($row['min_ball'] ?? null),
                'izoh' => $this->strOrNull($row['izoh'] ?? null),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
            ]);

            $this->imported++;
        }
    }

    private function isRowEmpty($row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function strOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function intOrNull($value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return (int) round((float) str_replace([' ', ','], ['', '.'], (string) $value));
    }

    private function floatOrNull($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return (float) str_replace([' ', ','], ['', '.'], (string) $value);
    }
}
