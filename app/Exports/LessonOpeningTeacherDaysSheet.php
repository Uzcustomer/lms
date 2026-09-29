<?php

namespace App\Exports;

use App\Services\LessonOpeningTeacherReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * "Kunlar" varag'i: yig'madagi har bir son ortidagi dars kunlari — o'qituvchi,
 * guruh, fan, dars sanasi va ariza holati.
 */
class LessonOpeningTeacherDaysSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private int $lastRow = 0;

    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Kunlar';
    }

    public function array(): array
    {
        $rows = [['№', "O'qituvchi", 'Guruh', 'Fan', 'Dars sanasi', 'Holat', "So'rov raqami", 'Ariza sanasi']];

        foreach ($this->report['days'] as $index => $day) {
            $rows[] = [
                $index + 1,
                $day['teacher'],
                $day['group'],
                $day['subject'],
                $day['date'] ? \Carbon\Carbon::parse($day['date'])->format('d.m.Y') : '',
                LessonOpeningTeacherReport::LABELS[$day['status']] ?? $day['status'],
                $day['request_number'] ?? '',
                $day['requested_at'] ? $day['requested_at']->format('d.m.Y H:i') : '',
            ];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 36, 'C' => 16, 'D' => 42, 'E' => 13, 'F' => 38, 'G' => 11, 'H' => 17];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->lastRow;

                $sheet->getStyle('A1:H1')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:H1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle('A1:H1')->getAlignment()
                    ->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->freezePane('A2');

                if ($last < 2) {
                    return;
                }

                $sheet->setAutoFilter("A1:H{$last}");
                $sheet->getStyle("A2:H{$last}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                $sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E2:E{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G2:H{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Holat rangi: yashil — baho qo'yilgan, to'q sariq — tasdiqlangan lekin baho yo'q,
                // ko'k — kutilmoqda, qizil — rad etilgan, kulrang — ariza yo'q
                $colors = [
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::GRADED] => '0F7A52',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::APPROVED] => 'B45309',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::PENDING] => '1D4ED8',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::REJECTED] => 'B3261E',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::NO_REQUEST] => '64748B',
                ];
                for ($row = 2; $row <= $last; $row++) {
                    $color = $colors[(string) $sheet->getCell("F{$row}")->getValue()] ?? null;
                    if ($color) {
                        $sheet->getStyle("F{$row}")->getFont()->setBold(true)->getColor()->setRGB($color);
                    }
                }

                $sheet->getStyle("A1:H{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
