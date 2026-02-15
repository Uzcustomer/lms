<?php

namespace App\Exports;

use App\Models\LectureSchedule;
use App\Models\LectureScheduleBatch;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LectureScheduleExport implements FromArray, WithHeadings, WithStyles
{
    private LectureScheduleBatch $batch;

    public function __construct(LectureScheduleBatch $batch)
    {
        $this->batch = $batch;
    }

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
            'Hemis holati',
        ];
    }

    public function array(): array
    {
        $items = $this->batch->items()
            ->orderBy('week_day')
            ->orderBy('lesson_pair_code')
            ->get();

        $statusLabels = [
            'not_checked' => 'Tekshirilmagan',
            'match' => 'Mos',
            'partial' => 'Qisman mos',
            'mismatch' => 'Mos emas',
            'not_found' => 'Topilmadi',
        ];

        return $items->map(fn(LectureSchedule $item) => [
            LectureSchedule::WEEK_DAYS[$item->week_day] ?? $item->week_day,
            $item->lesson_pair_name ?? $item->lesson_pair_code,
            $item->lesson_pair_start_time ? substr($item->lesson_pair_start_time, 0, 5) : '',
            $item->lesson_pair_end_time ? substr($item->lesson_pair_end_time, 0, 5) : '',
            $item->floor ?? '',
            $item->building_name ?? '',
            $item->auditorium_name ?? '',
            $item->group_source ?? '',
            $item->group_name,
            $item->subject_name,
            $item->employee_name ?? '',
            $item->training_type_name ?? '',
            $item->weeks ?? '',
            $item->week_parity ?? '',
            $statusLabels[$item->hemis_status] ?? $item->hemis_status,
        ])->toArray();
    }

    public function styles(Worksheet $sheet): array
    {
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
        $sheet->getColumnDimension('O')->setWidth(16);  // Hemis holati

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
