<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimetableUnplacedDiagnosticsExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle, ShouldAutoSize
{
    public function __construct(private readonly array $rows)
    {
    }

    public function title(): string
    {
        return 'Joylashmagan kartalar';
    }

    public function headings(): array
    {
        return [
            '#',
            'Karta ID',
            'Dars turi',
            'Fakultet',
            "Yo'nalish",
            'Kurs',
            'Oqim',
            'Guruh(lar)',
            'Fan',
            'Kafedra',
            'Talaba',
            "O'qituvchi",
            'Uzunlik',
            'Faol haftalar',
            'Tekshirilgan slot',
            "Bo'sh slot",
            'Asosiy sabab',
            'Sabab kodi',
            'Guruh band',
            "O'qituvchi band",
            'Auditoriya band',
            "To'qnashuv misollari",
            'Tavsiya',
        ];
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6, 'B' => 10, 'C' => 12, 'D' => 22, 'E' => 22, 'F' => 8,
            'G' => 14, 'H' => 22, 'I' => 34, 'J' => 30, 'K' => 10, 'L' => 24,
            'M' => 12, 'N' => 18, 'O' => 16, 'P' => 12, 'Q' => 52, 'R' => 28,
            'S' => 13, 'T' => 16, 'U' => 16, 'V' => 48, 'W' => 52,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->rows) + 1;
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:W' . max(1, $lastRow));
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A1:W1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E8B']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle('A2:W' . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ]);
            $sheet->getStyle('A2:C' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F2:F' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('K2:K' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('M2:U' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            for ($row = 2; $row <= $lastRow; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(42);
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:W{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }
            }
        }

        return [];
    }
}
