<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Qabul ko'rsatkichlari import uchun namuna (shablon) Excel fayl.
 * Sarlavhalar AdmissionIndicatorImport (WithHeadingRow) kutgan nomlar bilan mos.
 */
class AdmissionIndicatorTemplate implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return [
            'qabul_yili',
            'talim_turi',
            'talim_shakli',
            'mutaxassislik',
            'mutaxassislik_kodi',
            'tolov_shakli',
            'reja',
            'qabul_soni',
            'min_ball',
            'izoh',
        ];
    }

    public function array(): array
    {
        return [
            ['2023', 'Bakalavr', 'Kunduzgi', 'Davolash ishi', '60910200', 'Davlat granti', '75', '75', '181.4', ''],
            ['2023', 'Bakalavr', 'Kunduzgi', 'Davolash ishi', '60910200', "To'lov-shartnoma", '150', '150', '145.2', ''],
            ['2023', 'Magistr', 'Kunduzgi', 'Kardiologiya', '70910101', 'Davlat granti', '10', '10', '', 'Namuna qatori'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getColumnDimension('A')->setWidth(12); // qabul_yili
        $sheet->getColumnDimension('B')->setWidth(14); // talim_turi
        $sheet->getColumnDimension('C')->setWidth(14); // talim_shakli
        $sheet->getColumnDimension('D')->setWidth(28); // mutaxassislik
        $sheet->getColumnDimension('E')->setWidth(18); // mutaxassislik_kodi
        $sheet->getColumnDimension('F')->setWidth(18); // tolov_shakli
        $sheet->getColumnDimension('G')->setWidth(10); // reja
        $sheet->getColumnDimension('H')->setWidth(12); // qabul_soni
        $sheet->getColumnDimension('I')->setWidth(10); // min_ball
        $sheet->getColumnDimension('J')->setWidth(24); // izoh

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'],
                ],
            ],
        ];
    }
}
