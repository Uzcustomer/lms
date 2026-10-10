<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * "Mas'ul xodimlar" varag'i: mas'ul back ofis xodimi bo'yicha jami — nechta
 * noyob talabaga baho qo'yilmagan, nechta dars kunida va nechta "talaba × kun"
 * qatori ("Talabalar kesimida" varag'ida shu xodim bo'yicha filtrlaganda
 * chiqadigan qatorlar soni). Telegram xabaridagi ro'yxat bilan bir xil.
 */
class LessonOpeningTeacherManagersSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private const LAST_COL = 'E';

    private int $lastRow = 0;

    public function __construct(private array $report) {}

    public function title(): string
    {
        return "Mas'ul xodimlar";
    }

    public function array(): array
    {
        $rows = [[
            '№', "Mas'ul xodim", "Baho qo'yilmagan talabalar", 'Dars kunlari', 'Talaba × dars kuni (qatorlar)',
        ]];

        $i = 1;
        foreach (($this->report['managers'] ?? []) as $manager) {
            $unassigned = $manager['manager_id'] === 0;
            $rows[] = [
                $unassigned ? '' : $i++,
                $unassigned ? LessonOpeningTeacherStudentsSheet::UNASSIGNED : $manager['name'],
                $manager['students'],
                $manager['days'],
                $manager['cases'],
            ];
        }

        // Jami: talabalar noyob (har biri bitta mas'ulga tegishli), dars kunlari
        // umumiy son — bir kunda ikki mas'ulning talabasi bo'lsa ham bir marta
        $rows[] = [
            '',
            'Jami',
            $this->report['ungraded_students'] ?? 0,
            count(array_filter($this->report['days'] ?? [], fn ($day) => ($day['ungraded_students'] ?? 0) > 0)),
            count($this->report['students'] ?? []),
        ];

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 40, 'C' => 18, 'D' => 14, 'E' => 18];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->lastRow;
                $lc = self::LAST_COL;

                $sheet->getStyle("A1:{$lc}1")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A1:{$lc}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle("A1:{$lc}1")->getAlignment()
                    ->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(32);
                $sheet->freezePane('A2');

                $sheet->getStyle("A2:{$lc}{$last}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                $sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C2:{$lc}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C2:C{$last}")->getFont()->setBold(true);

                // Jami qatori
                $sheet->getStyle("A{$last}:{$lc}{$last}")->getFont()->setBold(true)->getColor()->setRGB('1B3A63');
                $sheet->getStyle("A{$last}:{$lc}{$last}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF7');
                $sheet->getStyle("A{$last}:{$lc}{$last}")->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('1B3A63');

                $sheet->getStyle("A1:{$lc}{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
