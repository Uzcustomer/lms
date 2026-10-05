<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "LMS ga kirishlar" hisobotining bitta varag'i: fakultet + kurs + davr.
 * Uch varaq faqat davr ustunining sarlavhasi bilan farq qiladi. Oxirida
 * "Jami" qatori: kirishlar yig'iladi, foiz hisoblanmaydi (davrlar bo'yicha
 * noyob talabalarni qo'shib bo'lmaydi).
 */
class StudentActivityStatsSheet implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private const LAST_COL = 'H';

    private int $lastRow = 1;

    public function __construct(private string $title, private array $rows, private string $periodLabel)
    {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return ['#', 'Fakultet', 'Kurs', $this->periodLabel, 'Kirishlar soni', 'Kirgan talabalar', 'Faol talabalar', 'Kirganlar %'];
    }

    public function array(): array
    {
        $out = [];
        foreach ($this->rows as $i => $r) {
            $out[] = [
                $i + 1,
                $r['faculty'],
                $r['course'],
                $r['period'],
                $r['logins'],
                $r['unique_students'],
                $r['active_students'],
                (float) $r['percent'],
            ];
        }

        if ($out === []) {
            $out[] = array_pad(["Bu oraliqda kirish topilmadi."], 8, '');
            $this->lastRow = 2;

            return $out;
        }

        $out[] = ['', 'Jami', '', '', array_sum(array_column($this->rows, 'logins')), '', '', ''];
        $this->lastRow = count($out) + 1;

        return $out;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 34, 'C' => 10, 'D' => 20, 'E' => 14, 'F' => 16, 'G' => 14, 'H' => 12];
    }

    public function styles(Worksheet $sheet): array
    {
        $end = self::LAST_COL;
        $last = $this->lastRow;

        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);

        $sheet->getStyle("A1:{$end}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1A3268']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('A2');

        if ($this->rows === []) {
            $sheet->mergeCells("A2:{$end}2");

            return [];
        }

        $sheet->setAutoFilter("A1:{$end}".($last - 1));
        $sheet->getStyle("A2:{$end}{$last}")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
        $sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C2:{$end}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H2:H{$last}")->getNumberFormat()->setFormatCode('0.0');
        $sheet->getStyle("E2:E{$last}")->getFont()->setBold(true);

        $sheet->getStyle("A{$last}:{$end}{$last}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8EEF7']],
        ]);
        $sheet->getStyle("A{$last}:{$end}{$last}")->getBorders()->getTop()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('1A3268');

        return [];
    }
}
