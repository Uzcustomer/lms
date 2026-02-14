<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LectureScheduleTemplate implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return [
            'Kun',
            'Juftlik',
            'Boshlanish',
            'Tugash',
            'Guruh',
            'Fan',
            'O\'qituvchi',
            'Auditoriya',
            'Turi',
        ];
    }

    public function array(): array
    {
        return [
            ['Dushanba', '1-juftlik', '08:00', '09:20', '101-22-guruh', 'Oliy matematika', 'Aliyev A.A.', '301', 'Ma\'ruza'],
            ['Dushanba', '2-juftlik', '09:30', '10:50', '101-22-guruh', 'Fizika', 'Karimov B.B.', '205', 'Amaliy'],
            ['Seshanba', '1-juftlik', '08:00', '09:20', '102-22-guruh', 'Informatika', 'Raximov C.C.', '410', 'Seminar'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Ustun kengliklarini sozlash
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(25);
        $sheet->getColumnDimension('G')->setWidth(22);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(14);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 11],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'],
                ],
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            ],
        ];
    }
}
