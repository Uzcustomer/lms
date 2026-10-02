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
 * "Talabalar kesimida" varag'i: baho qo'yilmagan HAR BIR talaba alohida qator.
 *
 * "Kunlar" varag'ida bir kun bitta qator va unda nechta talaba qoldirilgani son
 * bilan ko'rsatiladi; bu varaqda o'sha sonning ortidagi talabalar ism-familyasi
 * bilan ochib beriladi. Bir talaba bir necha kunda qoldirilsa, har kun uchun
 * alohida qator bo'ladi.
 */
class LessonOpeningTeacherStudentsSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    /** Ustunlar: A..L (Holat — L ustun) */
    private const LAST_COL = 'L';

    private const STATUS_COL = 'L';

    private int $lastRow = 0;

    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Talabalar kesimida';
    }

    public function array(): array
    {
        $rows = [[
            '№', 'Talaba', 'Talaba ID', 'Guruh', 'Kurs', 'Semestr',
            'Fan', 'Dars sanasi', 'Juftlik', "O'qituvchi", 'Kafedra', 'Holat',
        ]];

        foreach (($this->report['students'] ?? []) as $index => $row) {
            $rows[] = [
                $index + 1,
                $row['student'] !== '' ? $row['student'] : '(nomi topilmadi)',
                $row['student_id_number'] !== '' ? $row['student_id_number'] : $row['student_hemis_id'],
                $row['group'],
                $row['course'] ?? '',
                $row['semester'] ?? '',
                $row['subject'],
                $row['date'] ? \Carbon\Carbon::parse($row['date'])->format('d.m.Y') : '',
                $row['pair'] ?? '',
                $row['teacher'],
                $row['department'] ?? '',
                LessonOpeningTeacherReport::LABELS[$row['status']] ?? $row['status'],
            ];
        }

        if (($this->report['students'] ?? []) === []) {
            $rows[] = array_pad(["Bu davrda baho qo'yilmagan talaba topilmadi."], 12, '');
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 38, 'C' => 16, 'D' => 16, 'E' => 9, 'F' => 12,
            'G' => 40, 'H' => 13, 'I' => 16, 'J' => 34, 'K' => 32, 'L' => 36];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->lastRow;
                // Butun hujjat Times New Roman (boshqa varaqlar bilan bir xil)
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);
                $lc = self::LAST_COL;
                $sc = self::STATUS_COL;

                $sheet->getStyle("A1:{$lc}1")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A1:{$lc}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle("A1:{$lc}1")->getAlignment()
                    ->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->freezePane('A2');

                if ($last < 2) {
                    return;
                }

                if (($this->report['students'] ?? []) === []) {
                    $sheet->mergeCells("A{$last}:{$lc}{$last}");

                    return;
                }

                $sheet->setAutoFilter("A1:{$lc}{$last}");
                $sheet->getStyle("A2:{$lc}{$last}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                $sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Talaba ismi ajralib tursin
                $sheet->getStyle("B2:B{$last}")->getFont()->setBold(true);
                // Talaba ID, kurs, semestr — markazda
                $sheet->getStyle("C2:F{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Sana, juftlik — markazda
                $sheet->getStyle("H2:I{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Holat rangi — "Kunlar" varag'i bilan bir xil
                $colors = [
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::GRADED] => '0F7A52',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::APPROVED] => 'B45309',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::PENDING] => '1D4ED8',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::REJECTED] => 'B3261E',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::NO_REQUEST] => '64748B',
                ];
                for ($row = 2; $row <= $last; $row++) {
                    $color = $colors[(string) $sheet->getCell("{$sc}{$row}")->getValue()] ?? null;
                    if ($color) {
                        $sheet->getStyle("{$sc}{$row}")->getFont()->setBold(true)->getColor()->setRGB($color);
                    }
                }

                $sheet->getStyle("A1:{$lc}{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
