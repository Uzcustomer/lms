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
 *
 * 1-qator — jami (filtrlanganda ko'rinayotgan qatorlar soni ham yangilanadi),
 * 2-qator — sarlavha, ma'lumot 3-qatordan.
 */
class LessonOpeningTeacherStudentsSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    /** Ustunlar: A..M (Holat — L, Mas'ul xodim — M) */
    private const LAST_COL = 'M';

    private const STATUS_COL = 'L';

    private const HEADER_ROW = 2;

    public const UNASSIGNED = '(biriktirilmagan)';

    private int $lastRow = 0;

    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Talabalar kesimida';
    }

    public function array(): array
    {
        $students = $this->report['students'] ?? [];
        $distinct = count(array_unique(array_column($students, 'student_hemis_id')));

        $rows = [
            array_pad(['', "Jami: {$distinct} ta talabaga baho qo'yilmagan · ".count($students).' ta qator'], 13, ''),
            [
                '№', 'Talaba', 'Talaba ID', 'Guruh', 'Kurs', 'Semestr',
                'Fan', 'Dars sanasi', 'Juftlik', "O'qituvchi", 'Kafedra', 'Holat', "Mas'ul xodim",
            ],
        ];

        foreach ($students as $index => $row) {
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
                ($row['manager'] ?? '') !== '' ? $row['manager'] : self::UNASSIGNED,
            ];
        }

        if ($students === []) {
            $rows[] = array_pad(["Bu davrda baho qo'yilmagan talaba topilmadi."], 13, '');
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 38, 'C' => 16, 'D' => 16, 'E' => 9, 'F' => 12,
            'G' => 40, 'H' => 13, 'I' => 16, 'J' => 34, 'K' => 32, 'L' => 36, 'M' => 34];
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
                $h = self::HEADER_ROW;
                $first = $h + 1;

                // Jami qatori
                $sheet->getStyle("A1:{$lc}1")->getFont()->setBold(true)->getColor()->setRGB('1B3A63');
                $sheet->getStyle("A1:{$lc}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF7');
                $sheet->getRowDimension(1)->setRowHeight(22);

                $sheet->getStyle("A{$h}:{$lc}{$h}")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$h}:{$lc}{$h}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle("A{$h}:{$lc}{$h}")->getAlignment()
                    ->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension($h)->setRowHeight(28);
                $sheet->freezePane("A{$first}");

                if ($last < $first) {
                    return;
                }

                if (($this->report['students'] ?? []) === []) {
                    $sheet->mergeCells("A{$last}:{$lc}{$last}");

                    return;
                }

                // Filtr qo'yilganda faqat ko'rinayotgan qatorlar sanaladi
                $sheet->setCellValue('G1', "=\"Filtrda ko'rinayotgan qatorlar: \"&SUBTOTAL(103,B{$first}:B{$last})");

                $sheet->setAutoFilter("A{$h}:{$lc}{$last}");
                $sheet->getStyle("A{$first}:{$lc}{$last}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                $sheet->getStyle("A{$first}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Talaba ismi ajralib tursin
                $sheet->getStyle("B{$first}:B{$last}")->getFont()->setBold(true);
                // Talaba ID, kurs, semestr — markazda
                $sheet->getStyle("C{$first}:F{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Sana, juftlik — markazda
                $sheet->getStyle("H{$first}:I{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Holat rangi — "Kunlar" varag'i bilan bir xil
                $colors = [
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::GRADED] => '0F7A52',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::APPROVED] => 'B45309',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::PENDING] => '1D4ED8',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::REJECTED] => 'B3261E',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::NO_REQUEST] => '64748B',
                ];
                // Har qatorga alohida uslub o'n minglab qatorda sekin edi — butun
                // ustunga holat bo'yicha shartli formatlash bir marta qo'yiladi.
                $rules = [];
                foreach ($colors as $label => $color) {
                    $rule = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
                    $rule->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS)
                        ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_EQUAL)
                        ->addCondition('"' . str_replace('"', '""', $label) . '"');
                    $rule->getStyle()->getFont()->setBold(true)->getColor()->setRGB($color);
                    $rules[] = $rule;
                }
                $sheet->getStyle("{$sc}{$first}:{$sc}{$last}")->setConditionalStyles($rules);

                $sheet->getStyle("A{$h}:{$lc}{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
