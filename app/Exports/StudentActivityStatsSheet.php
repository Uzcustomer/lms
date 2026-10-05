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
 * "LMS ga kirishlar" hisobotining bitta varag'i: davr bo'yicha, bakalavr
 * talabalari, butun universitet. Birinchi qatorda sarlavha va jami bakalavr
 * soni, ikkinchi qatorda ustun nomlari, oxirida "Jami" (kirishlar yig'indisi;
 * noyob talabalar va foiz davrlar bo'yicha qo'shilmaydi).
 */
class StudentActivityStatsSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private const LAST_COL = 'F';

    private const HEADER_ROW = 2;

    private int $lastRow = 2;

    public function __construct(
        private string $title,
        private array $rows,
        private string $periodLabel,
        private int $totalStudents,
        private string $from,
        private string $to,
    ) {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        $out = [
            array_pad([
                'LMS ga kirishlar — '.mb_strtolower($this->title).' · '
                .\Carbon\Carbon::parse($this->from)->format('d.m.Y').' – '.\Carbon\Carbon::parse($this->to)->format('d.m.Y')
                .' · jami bakalavr talabalar: '.number_format($this->totalStudents, 0, '.', ' '),
            ], 6, ''),
            ['#', $this->periodLabel, 'Kirishlar soni', 'Kirgan talabalar', 'Jami bakalavr', 'Kirganlar %'],
        ];

        foreach ($this->rows as $i => $r) {
            $out[] = [$i + 1, $r['period'], $r['logins'], $r['unique_students'], $r['total_students'], (float) $r['percent']];
        }

        if ($this->rows === []) {
            $out[] = array_pad(['Bu oraliqda kirish topilmadi.'], 6, '');
        } else {
            $out[] = ['', 'Jami', array_sum(array_column($this->rows, 'logins')), '', '', ''];
        }
        $this->lastRow = count($out);

        return $out;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 22, 'C' => 16, 'D' => 18, 'E' => 16, 'F' => 14];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $end = self::LAST_COL;
                $h = self::HEADER_ROW;
                $last = $this->lastRow;

                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);

                // 1-qator: sarlavha
                $sheet->mergeCells("A1:{$end}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('0F2748');
                $sheet->getRowDimension(1)->setRowHeight(24);

                // 2-qator: ustun nomlari
                $sheet->getStyle("A{$h}:{$end}{$h}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3268']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);
                $sheet->getRowDimension($h)->setRowHeight(30);
                $sheet->freezePane('A'.($h + 1));

                if ($this->rows === []) {
                    $sheet->mergeCells("A{$last}:{$end}{$last}");

                    return;
                }

                $body = $h + 1;
                $sheet->setAutoFilter("A{$h}:{$end}".($last - 1));
                $sheet->getStyle("A{$body}:{$end}{$last}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                $sheet->getStyle("A{$body}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$body}:{$end}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$body}:F{$last}")->getNumberFormat()->setFormatCode('0.0');
                $sheet->getStyle("D{$body}:D{$last}")->getFont()->setBold(true);

                // Jami
                $sheet->getStyle("A{$last}:{$end}{$last}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EEF7']],
                ]);
                $sheet->getStyle("A{$last}:{$end}{$last}")->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('1A3268');
            },
        ];
    }
}
