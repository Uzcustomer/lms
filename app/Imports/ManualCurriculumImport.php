<?php

namespace App\Imports;

use App\Models\ManualCurriculum;
use App\Models\ManualCurriculumSubject;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Namunaviy yoki ishchi o'quv reja Excel faylini o'qiydi.
 *
 * Fayl ichida sarlavha qatori "Fan nomi" ustuni orqali topiladi,
 * ustunlar nomi bo'yicha moslanadi, shuning uchun ustunlar tartibi muhim emas.
 * Bir fan bir nechta semestrda o'tilsa, har semestri alohida qatorda bo'ladi.
 */
class ManualCurriculumImport implements ToCollection
{
    public array $errors = [];
    public int $imported = 0;
    private bool $done = false;

    public function __construct(private ManualCurriculum $curriculum)
    {
    }

    public function collection(Collection $rows)
    {
        // Bir varaqdan muvaffaqiyatli o'qilgan bo'lsa, qolgan varaqlar o'tkazib yuboriladi
        if ($this->done) {
            return;
        }

        $columns = null;
        $currentBlock = null;

        foreach ($rows as $index => $row) {
            $values = $row->toArray();

            if ($columns === null) {
                $columns = $this->detectHeader($values);
                continue;
            }

            $name = trim((string) ($values[$columns['subject_name']] ?? ''));
            if ($name === '') {
                continue;
            }

            $record = [
                'manual_curriculum_id' => $this->curriculum->id,
                'block' => $currentBlock,
                'subject_name' => $name,
            ];
            foreach (['subject_code', 'reference_name', 'note', 'block'] as $field) {
                if (!isset($columns[$field])) {
                    continue;
                }
                $value = trim((string) ($values[$columns[$field]] ?? ''));
                if ($value !== '') {
                    $record[$field] = $value;
                }
            }
            foreach (['kurs', 'semester'] as $field) {
                if (isset($columns[$field])) {
                    $value = $this->toNumber($values[$columns[$field]] ?? null);
                    $record[$field] = $value !== null ? (int) $value : null;
                }
            }
            foreach (['total_hours', 'audit_total', 'lecture', 'practice', 'laboratory', 'seminar', 'independent', 'credit'] as $field) {
                if (isset($columns[$field])) {
                    $record[$field] = $this->toNumber($values[$columns[$field]] ?? null);
                }
            }

            // Soat ham, kredit ham bo'lmagan qator — bo'lim sarlavhasi
            // (masalan "Majburiy fanlar"), keyingi qatorlar uchun blok sifatida eslab qolinadi
            if (($record['total_hours'] ?? null) === null && ($record['credit'] ?? null) === null) {
                $currentBlock = $name;
                continue;
            }

            ManualCurriculumSubject::create($record);
            $this->imported++;
        }

        if ($columns === null) {
            $this->errors = ["Sarlavha qatori topilmadi: faylda \"Fan nomi\" ustuni bo'lishi shart."];
        } elseif ($this->imported === 0) {
            $this->errors = ["Fayldan birorta ham fan o'qib olinmadi. Shablon formatini tekshiring."];
        } else {
            $this->errors = [];
            $this->done = true;
        }
    }

    private function detectHeader(array $values): ?array
    {
        $map = [];
        foreach ($values as $col => $value) {
            $header = $this->normalize((string) $value);
            if ($header === '') {
                continue;
            }
            $field = match (true) {
                $header === 'fan nomi' => 'subject_name',
                $header === 'fan kodi' => 'subject_code',
                str_starts_with($header, 'blok') => 'block',
                str_starts_with($header, 'kurs') => 'kurs',
                str_starts_with($header, 'semestr') => 'semester',
                str_starts_with($header, 'umumiy') => 'total_hours',
                str_starts_with($header, 'auditoriya') => 'audit_total',
                str_starts_with($header, 'maruza') => 'lecture',
                str_starts_with($header, 'amaliy') => 'practice',
                str_starts_with($header, 'laboratoriya') => 'laboratory',
                str_starts_with($header, 'seminar') => 'seminar',
                str_starts_with($header, 'mustaqil') => 'independent',
                str_starts_with($header, 'kredit') => 'credit',
                str_contains($header, 'namunaviy') => 'reference_name',
                str_starts_with($header, 'izoh') => 'note',
                default => null,
            };
            if ($field !== null && !isset($map[$field])) {
                $map[$field] = $col;
            }
        }

        return isset($map['subject_name']) ? $map : null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        return str_replace(["'", '’', 'ʻ', 'ʼ', '`', '´'], '', $value);
    }

    private function toNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $value = str_replace([',', ' '], ['.', ''], trim((string) $value));
        return is_numeric($value) ? (float) $value : null;
    }
}
