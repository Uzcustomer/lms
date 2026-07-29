<?php

namespace App\Exports;

use App\Services\CurriculumComparisonService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CurriculumComparisonExport implements FromArray, ShouldAutoSize, WithStrictNullComparison, WithStyles, WithTitle
{
    private const STATUS_COLORS = [
        CurriculumComparisonService::STATUS_OK => 'C6EFCE',
        CurriculumComparisonService::STATUS_NAME => 'FFEB9C',
        CurriculumComparisonService::STATUS_HOURS => 'FFC7CE',
        CurriculumComparisonService::STATUS_CREDIT => 'FFC7CE',
        CurriculumComparisonService::STATUS_HOURS_CREDIT => 'FFC7CE',
        CurriculumComparisonService::STATUS_MISSING_IN_WORKING => 'FF9999',
        CurriculumComparisonService::STATUS_MISSING_IN_REFERENCE => 'D9D2E9',
        CurriculumComparisonService::STATUS_GROUP_DIFF => 'FCD5B4',
    ];

    public function __construct(
        private string $title,
        private array $comparison,
    ) {
    }

    public function title(): string
    {
        return 'Solishtirma';
    }

    public function array(): array
    {
        $data = [
            [$this->title],
            ['T/r', 'Blok', 'Fan nomi (HEMIS)', 'Namunaviy nomi', 'Ishchi nomi',
                'Nam. soat', 'Ishchi soat', 'Soat farqi', 'Nam. kredit', 'Ishchi kredit', 'Kredit farqi',
                'Kurs(lar)', 'Semestr(lar)', 'Holati', 'Izoh'],
        ];

        foreach ($this->comparison['rows'] as $i => $row) {
            $data[] = [
                $i + 1,
                $row['block'],
                $this->hemisCell($row),
                $this->nameCell($row['ref_name'], $row['ref_parts'] ?? []),
                $this->nameCell($row['work_name'], $row['work_parts'] ?? []),
                $row['ref_hours'],
                $row['work_hours'],
                $row['hours_diff'],
                $row['ref_credit'],
                $row['work_credit'],
                $row['credit_diff'],
                $row['kurslar'],
                $row['semestrlar'],
                $row['status'],
                $row['note'],
            ];
        }

        $totals = $this->comparison['totals'];
        $data[] = ['', '', 'JAMI', '', '',
            $totals['ref_hours'], $totals['work_hours'], $totals['hours_diff'],
            $totals['ref_credit'], $totals['work_credit'], $totals['credit_diff'],
            '', '', '', ''];

        return $data;
    }

    /**
     * HEMIS ustuni. Birikkan guruhda HEMIS'da fanlar alohida turadi — ular
     * qo'shib yuborilmasdan, har biri o'z qatorida yoziladi.
     */
    private function hemisCell(array $row): ?string
    {
        if (($row['hemis_name'] ?? null) !== null) {
            return $row['hemis_name'];
        }
        if (!empty($row['hemis_parts'])) {
            return implode("\n", array_map(
                fn ($p) => $p['hemis'] ?? ($p['name'] . " (HEMIS'da topilmadi)"),
                $row['hemis_parts']
            ));
        }

        return $row['ref_name'] ?? $row['work_name'];
    }

    /**
     * Birikkan guruh qatorida fan nomi ostiga tarkibiy fanlar soati bilan
     * yoziladi ("Ichki kasalliklar (120 soat)") — Excelda ham qaysi fanlar
     * birlashtirilgani va ularning ulushi ko'rinib tursin.
     */
    private function nameCell(?string $name, array $parts): ?string
    {
        if ($name === null || count($parts) < 2) {
            return $name;
        }

        $lines = [$name];
        foreach ($parts as $part) {
            $hours = $part['hours'] ?? null;
            $lines[] = '  • ' . ($part['name'] ?? '')
                . ($hours !== null ? ' (' . rtrim(rtrim(number_format((float) $hours, 2, '.', ''), '0'), '.') . ' soat)' : '');
        }

        return implode("\n", $lines);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:O1');
        $lastRow = count($this->comparison['rows']) + 3;

        $styles = [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            2 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1a3268']],
            ],
            $lastRow => ['font' => ['bold' => true]],
        ];

        foreach ($this->comparison['rows'] as $i => $row) {
            $color = self::STATUS_COLORS[$row['status']] ?? null;
            if ($color && $row['status'] !== CurriculumComparisonService::STATUS_OK) {
                $styles[$i + 3] = ['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $color]]];
            }
        }

        return $styles;
    }
}
