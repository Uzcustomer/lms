<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TestCenterExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    use Exportable;

    protected Collection $scheduleData;
    private int $totalRows = 0;

    public function __construct(Collection $scheduleData)
    {
        $this->scheduleData = $scheduleData;
    }

    public function headings(): array
    {
        return [
            '#',
            'Guruh',
            "Yo'nalish",
            'Fan kodi',
            'Fan nomi',
            'Kurs',
            'Semestr',
            'YN turi',
            'Sana',
            'Vaqt',
            'Talabalar soni',
            'Topshirgan',
            'YN yuborilgan',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $idx = 0;

        foreach ($this->scheduleData as $groupHemisId => $items) {
            foreach ($items as $item) {
                $idx++;

                $group = $item['group'] ?? null;
                $subject = $item['subject'] ?? null;

                // Sana formatlash
                $ynDate = $item['yn_date'] ?? null;
                $dateStr = '';
                if ($item['yn_na'] ?? false) {
                    $dateStr = 'N/A';
                } elseif ($ynDate) {
                    $dateStr = \Carbon\Carbon::parse($ynDate)->format('d.m.Y');
                }

                // Vaqt formatlash
                $timeStr = '';
                if (!empty($item['test_time'])) {
                    $timeStr = \Carbon\Carbon::parse($item['test_time'])->format('H:i');
                }

                $studentCount = $item['student_count'] ?? 0;
                $quizCount = $item['quiz_count'] ?? 0;
                $excuseCount = $item['excuse_student_count'] ?? 0;
                $topshirganStr = $quizCount . '/' . $studentCount;
                if ($excuseCount > 0) {
                    $topshirganStr .= ' (+' . $excuseCount . ' sababli)';
                }

                $ynSubmittedStr = ($item['yn_submitted'] ?? false) ? 'Yuborilgan' : 'Yuborilmagan';

                $rows[] = [
                    $idx,
                    $group->name ?? '',
                    $item['specialty_name'] ?? '',
                    $item['subject_code'] ?? ($subject->subject_id ?? ''),
                    $subject->subject_name ?? '',
                    $item['level_name'] ?? '',
                    $item['semester_name'] ?? '',
                    $item['yn_type'] ?? '',
                    $dateStr,
                    $timeStr,
                    $studentCount,
                    $topshirganStr,
                    $ynSubmittedStr,
                ];
            }
        }

        $this->totalRows = count($rows);
        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E8B']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];

        if ($this->totalRows > 0) {
            $range = 'A1:M' . ($this->totalRows + 1);
            $sheet->getStyle($range)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1'],
                    ],
                ],
            ]);

            // Center-align key columns
            $sheet->getStyle('A2:A' . ($this->totalRows + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F2:M' . ($this->totalRows + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        return $styles;
    }
}
