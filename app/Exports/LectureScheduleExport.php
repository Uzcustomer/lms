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
            'Kun',
            'Juftlik',
            'Boshlanish',
            'Tugash',
            'Guruh',
            'Fan',
            'O\'qituvchi',
            'Auditoriya',
            'Turi',
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
            $item->group_name,
            $item->subject_name,
            $item->employee_name ?? '',
            $item->auditorium_name ?? '',
            $item->training_type_name ?? '',
            $statusLabels[$item->hemis_status] ?? $item->hemis_status,
        ])->toArray();
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(25);
        $sheet->getColumnDimension('G')->setWidth(22);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(14);
        $sheet->getColumnDimension('J')->setWidth(16);

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
