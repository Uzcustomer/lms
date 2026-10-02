<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Dars juftliklari — alohida jadval yo'q, ular dars jadvalida (schedules)
 * saqlanadi. Shuning uchun ro'yxat shu jadvaldan takrorlarsiz yig'iladi:
 * juftlik kodi (HEMIS id), nomi va soatlari. Vaqti o'zgargan juftlik
 * alohida qator bo'lib chiqadi — qaysi variant qancha darsda ishlatilgani
 * ko'rinib tursin.
 */
class LessonPairsExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    use Exportable;

    public function collection()
    {
        return DB::table('schedules')
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_pair_code')
            ->select(
                'lesson_pair_code',
                'lesson_pair_name',
                'lesson_pair_start_time',
                'lesson_pair_end_time',
                DB::raw('COUNT(*) as lessons'),
                DB::raw('MAX(DATE(lesson_date)) as last_lesson_date')
            )
            ->groupBy('lesson_pair_code', 'lesson_pair_name', 'lesson_pair_start_time', 'lesson_pair_end_time')
            ->orderByRaw('CAST(lesson_pair_code AS UNSIGNED)')
            ->orderBy('lesson_pair_start_time')
            ->get()
            ->map(function ($row) {
                return [
                    $row->lesson_pair_code,
                    $row->lesson_pair_name,
                    $this->shortTime($row->lesson_pair_start_time),
                    $this->shortTime($row->lesson_pair_end_time),
                    $this->minutes($row->lesson_pair_start_time, $row->lesson_pair_end_time),
                    $row->lessons,
                    $row->last_lesson_date,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Juftlik kodi (ID)',
            'Nomi',
            'Boshlanishi',
            'Tugashi',
            'Davomiyligi (daqiqa)',
            'Darslar soni',
            'Oxirgi dars sanasi',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1a3268']],
            ],
        ];
    }

    /** "08:30:00" → "08:30" */
    private function shortTime(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '';
    }

    private function minutes(?string $start, ?string $end): ?int
    {
        if (!$start || !$end) {
            return null;
        }

        try {
            $from = \Carbon\Carbon::parse($start);
            $to = \Carbon\Carbon::parse($end);
        } catch (\Throwable $e) {
            return null;
        }

        return (int) abs($from->diffInMinutes($to));
    }
}
