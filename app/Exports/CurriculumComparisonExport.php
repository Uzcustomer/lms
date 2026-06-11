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
            ['T/r', 'Blok', "Fan nomi (namunaviy reja)", "Ishchi rejadagi nomi (farqli bo'lsa)",
                'Nam. soat', 'Ishchi soat', 'Soat farqi', 'Nam. kredit', 'Ishchi kredit', 'Kredit farqi',
                'Kurs(lar)', 'Semestr(lar)', 'Holati', 'Izoh'],
        ];

        foreach ($this->comparison['rows'] as $i => $row) {
            $data[] = [
                $i + 1,
                $row['block'],
                $row['ref_name'],
                $row['name_differs'] ? $row['work_name'] : ($row['ref_name'] === null ? $row['work_name'] : ''),
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
        $data[] = ['', '', 'JAMI', '',
            $totals['ref_hours'], $totals['work_hours'], $totals['hours_diff'],
            $totals['ref_credit'], $totals['work_credit'], $totals['credit_diff'],
            '', '', '', ''];

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:N1');
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
