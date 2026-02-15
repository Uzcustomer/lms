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
            'Kuni',
            'Juftlik',
            'Boshlanish',
            'Tugash',
            'Qavat',
            'Bino',
            'Xona',
            'Guruh_source',
            'Guruh',
            'Fan',
            'O\'qituvchi',
            'Turi',
            'Haftalar davomiyligi',
            'Juft-toq',
        ];
    }

    public function array(): array
    {
        return [
            ['Dushanba', '1-juftlik', '08:00', '09:20', '3', 'Bosh bino', '301', '101-22+102-22 ma\'ruza', '101-22-guruh', 'Oliy matematika', 'Aliyev A.A.', 'Ma\'ruza', '1-16', ''],
            ['Dushanba', '2-juftlik', '09:30', '10:50', '2', 'Bosh bino', '205', '', '101-22-guruh', 'Fizika', 'Karimov B.B.', 'Amaliy', '1-16', 'juft'],
            ['Seshanba', '1-juftlik', '08:00', '09:20', '4', '2-bino', '410', '103-22+104-22 ma\'ruza', '103-22-guruh', 'Informatika', 'Raximov C.C.', 'Seminar', '2-14', 'toq'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Ustun kengliklarini sozlash
        $sheet->getColumnDimension('A')->setWidth(14);  // Kuni
        $sheet->getColumnDimension('B')->setWidth(14);  // Juftlik
        $sheet->getColumnDimension('C')->setWidth(12);  // Boshlanish
        $sheet->getColumnDimension('D')->setWidth(12);  // Tugash
        $sheet->getColumnDimension('E')->setWidth(8);   // Qavat
        $sheet->getColumnDimension('F')->setWidth(14);  // Bino
        $sheet->getColumnDimension('G')->setWidth(10);  // Xona
        $sheet->getColumnDimension('H')->setWidth(26);  // Guruh_source
        $sheet->getColumnDimension('I')->setWidth(18);  // Guruh
        $sheet->getColumnDimension('J')->setWidth(25);  // Fan
        $sheet->getColumnDimension('K')->setWidth(22);  // O'qituvchi
        $sheet->getColumnDimension('L')->setWidth(14);  // Turi
        $sheet->getColumnDimension('M')->setWidth(20);  // Haftalar davomiyligi
        $sheet->getColumnDimension('N')->setWidth(12);  // Juft-toq

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
