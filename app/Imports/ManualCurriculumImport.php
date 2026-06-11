<?php

namespace App\Imports;

use App\Models\ManualCurriculum;
use App\Models\ManualCurriculumSubject;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

/**
 * Namunaviy yoki ishchi o'quv reja Excel faylini o'qiydi.
 *
 * Ikki xil format qo'llab-quvvatlanadi:
 *
 * 1) Oddiy ro'yxat — "Fan nomi" ustunli, bir fan bir nechta semestrda
 *    o'tilsa har semestri alohida qatorda.
 *
 * 2) Setka (namunaviy reja) — "O'quv bloklari, fanlar va faoliyat
 *    turlarining nomlari" ustunli, kreditlar semestrlar bo'yicha alohida
 *    ustunlarda (1..12) taqsimlangan. Bunda har fan bitta yozuv sifatida
 *    jami soat/kredit bilan saqlanadi, semestr taqsimoti izohga yoziladi.
 *    Bloklar (MAJBURIY FANLAR, tanlov bloklari, MALAKAVIY AMALIYOT)
 *    T/r ustunidagi sarlavha qatorlaridan olinadi, "jami"/"HAMMASI"
 *    yig'indi qatorlari va "Izoh" o'tkazib yuboriladi.
 */
class ManualCurriculumImport implements ToCollection, WithCalculatedFormulas
{
    public array $errors = [];
    public int $imported = 0;
    private bool $done = false;

    private ?array $columns = null;
    private ?string $currentBlock = null;
    private bool $setka = false;
    /** @var array<int,int> ustun indeksi => semestr raqami (setka format) */
    private array $semesterCols = [];

    public function __construct(private ManualCurriculum $curriculum)
    {
    }

    public function collection(Collection $rows)
    {
        // Bir varaqdan muvaffaqiyatli o'qilgan bo'lsa, qolgan varaqlar o'tkazib yuboriladi
        if ($this->done) {
            return;
        }

        $this->columns = null;
        $this->currentBlock = null;
        $this->setka = false;
        $this->semesterCols = [];

        foreach ($rows as $row) {
            $values = $row->toArray();

            if ($this->columns === null) {
                if ($this->columns = $this->detectHeader($values)) {
                    continue;
                }
                if ($this->columns = $this->detectSetkaHeader($values)) {
                    $this->setka = true;
                }
                continue;
            }

            if ($this->setka) {
                $this->importSetkaRow($values);
            } else {
                $this->importListRow($values);
            }
        }

        if ($this->columns === null) {
            $this->errors = ["Sarlavha qatori topilmadi: faylda \"Fan nomi\" yoki \"O'quv bloklari, fanlar va faoliyat turlarining nomlari\" ustuni bo'lishi shart."];
        } elseif ($this->imported === 0) {
            $this->errors = ["Fayldan birorta ham fan o'qib olinmadi. Shablon formatini tekshiring."];
        } else {
            $this->errors = [];
            $this->done = true;
        }
    }

    private function importListRow(array $values): void
    {
        $name = trim((string) ($values[$this->columns['subject_name']] ?? ''));
        if ($name === '') {
            return;
        }

        $record = [
            'manual_curriculum_id' => $this->curriculum->id,
            'block' => $this->currentBlock,
            'subject_name' => $name,
        ];
        foreach (['subject_code', 'reference_name', 'note', 'block'] as $field) {
            if (!isset($this->columns[$field])) {
                continue;
            }
            $value = trim((string) ($values[$this->columns[$field]] ?? ''));
            if ($value !== '') {
                $record[$field] = $value;
            }
        }
        foreach (['kurs', 'semester'] as $field) {
            if (isset($this->columns[$field])) {
                $value = $this->toNumber($values[$this->columns[$field]] ?? null);
                $record[$field] = $value !== null ? (int) $value : null;
            }
        }
        foreach (['total_hours', 'audit_total', 'lecture', 'practice', 'laboratory', 'seminar', 'independent', 'credit'] as $field) {
            if (isset($this->columns[$field])) {
                $record[$field] = $this->toNumber($values[$this->columns[$field]] ?? null);
            }
        }

        // Soat ham, kredit ham bo'lmagan qator — bo'lim sarlavhasi
        // (masalan "Majburiy fanlar"), keyingi qatorlar uchun blok sifatida eslab qolinadi
        if (($record['total_hours'] ?? null) === null && ($record['credit'] ?? null) === null) {
            $this->currentBlock = $name;
            return;
        }

        ManualCurriculumSubject::create($record);
        $this->imported++;
    }

    private function importSetkaRow(array $values): void
    {
        $tr = trim((string) ($values[$this->columns['tr']] ?? ''));
        $name = trim((string) ($values[$this->columns['subject_name']] ?? ''));

        // T/r ham, fan nomi ham bo'sh — sarlavhaning davom qatori
        // (kurs nomlari, semestr raqamlari) yoki bo'sh qator
        if ($tr === '' && $name === '') {
            $this->harvestSetkaSubHeader($values);
            return;
        }

        // Amaliyot/attestatsiya qatorlarida nom T/r ustunida turadi
        $label = $name !== '' ? $name : $tr;
        $norm = $this->normalize($label);
        if (preg_match('/\bjami\b/u', $norm) || $norm === 'hammasi' || str_starts_with($norm, 'izoh')) {
            return; // yig'indi va izoh qatorlari
        }

        $record = [
            'manual_curriculum_id' => $this->curriculum->id,
            'block' => $this->currentBlock,
            'subject_name' => $label,
        ];
        if (isset($this->columns['subject_code'])) {
            $code = trim((string) ($values[$this->columns['subject_code']] ?? ''));
            if ($code !== '') {
                $record['subject_code'] = $code;
            }
        }
        foreach (['total_hours', 'audit_total', 'lecture', 'practice', 'laboratory', 'seminar', 'independent', 'credit'] as $field) {
            if (isset($this->columns[$field])) {
                $record[$field] = $this->toNumber($values[$this->columns[$field]] ?? null);
            }
        }
        // D ustuni topilmagan yoki bo'sh bo'lsa, audit + mustaqil dan hisoblash
        if (($record['total_hours'] ?? null) === null) {
            $a = (float) ($record['audit_total'] ?? 0);
            $i = (float) ($record['independent'] ?? 0);
            if ($a > 0 || $i > 0) {
                $record['total_hours'] = $a + $i;
            }
        }

        $credits = [];
        foreach ($this->semesterCols as $col => $semester) {
            $value = $this->toNumber($values[$col] ?? null);
            if ($value !== null && $value != 0) {
                $credits[$semester] = $value;
            }
        }
        if (($record['credit'] ?? null) === null && $credits !== []) {
            $record['credit'] = array_sum($credits);
        }

        if (($record['total_hours'] ?? null) === null && ($record['credit'] ?? null) === null) {
            $this->currentBlock = $label;
            return;
        }

        if ($credits === []) {
            ManualCurriculumSubject::create($record);
            $this->imported++;
            return;
        }

        ksort($credits);

        if (count($credits) === 1) {
            $record['semester'] = array_key_first($credits);
            $record['kurs'] = (int) ceil($record['semester'] / 2);
            ManualCurriculumSubject::create($record);
            $this->imported++;
            return;
        }

        // Bir nechta semestrda o'tiluvchi fan: har semestr uchun alohida qator.
        // Birinchi qatorda soat ma'lumotlari + jami kredit izohda, keyingilarida faqat semestr/kredit.
        $semList = implode(', ', array_map(
            fn($s, $c) => "{$s}-sem " . $this->formatNumber($c),
            array_keys($credits), $credits
        ));
        $isFirst = true;
        foreach ($credits as $semester => $credit) {
            $row = $isFirst
                ? array_merge($record, ['note' => "Jami {$record['credit']} kredit: {$semList}"])
                : [
                    'manual_curriculum_id' => $this->curriculum->id,
                    'block' => $this->currentBlock,
                    'subject_name' => $record['subject_name'],
                    'subject_code' => $record['subject_code'] ?? null,
                ];
            $row['semester'] = $semester;
            $row['kurs'] = (int) ceil($semester / 2);
            $row['credit'] = $credit;
            ManualCurriculumSubject::create($row);
            $this->imported++;
            $isFirst = false;
        }
    }

    /**
     * Setka sarlavhasining pastki qatorlari: "Jami/Ma'ruza/Amaliy/..."
     * ustun nomlari va 1..12 semestr raqamlari shu yerdan olinadi.
     */
    private function harvestSetkaSubHeader(array $values): void
    {
        foreach ($values as $col => $value) {
            $header = $this->normalize((string) $value);
            if ($header === '') {
                continue;
            }
            if (preg_match('/^\d{1,2}$/', $header)) {
                $semester = (int) $header;
                if ($semester >= 1 && $semester <= 14) {
                    $this->semesterCols[$col] = $semester;
                }
                continue;
            }
            $field = match (true) {
                $header === 'jami' => 'audit_total',
                str_starts_with($header, 'maruza') => 'lecture',
                str_starts_with($header, 'amaliy') => 'practice',
                str_starts_with($header, 'laboratoriya') => 'laboratory',
                str_starts_with($header, 'seminar') => 'seminar',
                default => null,
            };
            if ($field !== null && !isset($this->columns[$field])) {
                $this->columns[$field] = $col;
            }
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

    private function detectSetkaHeader(array $values): ?array
    {
        $map = [];
        foreach ($values as $col => $value) {
            $header = $this->normalize((string) $value);
            if ($header === '') {
                continue;
            }
            $field = match (true) {
                str_contains($header, 'fanlar') && str_contains($header, 'nomlari') => 'subject_name',
                $header === 't/r' || $header === 'tr' => 'tr',
                $header === 'fan kodi' => 'subject_code',
                str_starts_with($header, 'umumiy') => 'total_hours',
                str_starts_with($header, 'auditoriya') => 'audit_total',
                str_starts_with($header, 'mustaqil') => 'independent',
                str_contains($header, 'jami') && str_contains($header, 'kredit') => 'credit',
                default => null,
            };
            if ($field !== null && !isset($map[$field])) {
                $map[$field] = $col;
            }
        }

        return isset($map['subject_name'], $map['tr']) ? $map : null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(["'", '’', 'ʻ', 'ʼ', '`', '´'], '', $value);
        return preg_replace('/\s+/u', ' ', $value);
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
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
