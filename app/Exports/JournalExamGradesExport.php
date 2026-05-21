<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Jurnal — tanlangan sana oralig'i, fakultet va kurslar bo'yicha barcha
 * fanlardan OSKI va Test baholarining (1/2/3-urinish + qo'shimcha farmoyish)
 * Excel eksporti. Har qator — bitta talabaning bitta fani.
 */
class JournalExamGradesExport implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private readonly array $rows,
        private readonly string $dateFrom,
        private readonly string $dateTo,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            '№',
            'Student ID',
            'F.I.SH.',
            'Kurs',
            'Guruh',
            'Fan',
            'Semestr',
            'OSKI 1-urinish',
            'OSKI 2-urinish',
            'OSKI 3-urinish',
            "OSKI (qo'shimcha farmoyish)",
            'Test 1-urinish',
            'Test 2-urinish',
            'Test 3-urinish',
            "Test (qo'shimcha farmoyish)",
        ];
    }

    public function title(): string
    {
        return 'OSKI-Test ' . $this->dateFrom . ' _ ' . $this->dateTo;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);
        $sheet->getStyle('A1:O1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E2F3');
        $sheet->freezePane('A2');
        return [];
    }
}
