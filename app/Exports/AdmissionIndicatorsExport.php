<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Arrayable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Filtrlangan qabul ko'rsatkichlarini Excel'ga eksport qilish.
 */
class AdmissionIndicatorsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param \Illuminate\Support\Collection $rows
     */
    public function __construct(private $rows)
    {
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Qabul yili',
            "Ta'lim turi",
            "Ta'lim shakli",
            'Mutaxassislik',
            'Mutaxassislik kodi',
            "To'lov shakli",
            'Reja',
            'Qabul soni',
            "Eng past ball",
            'Izoh',
        ];
    }

    public function map($row): array
    {
        return [
            $row->qabul_yili,
            $row->talim_turi,
            $row->talim_shakli,
            $row->mutaxassislik,
            $row->mutaxassislik_kodi,
            $row->tolov_shakli,
            $row->reja,
            $row->qabul_soni,
            $row->min_ball,
            $row->izoh,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
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
