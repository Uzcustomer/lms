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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "LMS ga kirishlar" hisobotining bitta varag'i.
 *
 * Chap jadval (A–H): fakultet + kurs + davr, sana o'sib borish tartibida.
 * O'ng jadval (J–N): shu davr bo'yicha umumiy — fakultetsiz. Ikkalasi bitta
 * varaqda yonma-yon; orasida bitta bo'sh ustun (I).
 *
 * Chap jadvalning oxirida "Jami" qatori: kirishlar yig'iladi. Foiz
 * yig'ilmaydi — noyob talabalar davrlar bo'yicha qo'shilmaydi; o'ng jadvalda
 * esa har davrning foizi butun universitetga nisbatan.
 */
class StudentActivityStatsSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private const LEFT_END = 'H';

    private const RIGHT_START = 'J';

    private const RIGHT_END = 'N';

    private int $leftLast = 1;

    private int $rightLast = 1;

    public function __construct(
        private string $title,
        private array $rows,
        private string $periodLabel,
        private array $summary = [],
    ) {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        $left = [[
            '#', 'Fakultet', 'Kurs', $this->periodLabel,
            'Kirishlar soni', 'Kirgan talabalar', 'Jami talabalar', 'Kirganlar %',
        ]];
        foreach ($this->rows as $i => $r) {
            $left[] = [$i + 1, $r['faculty'], $r['course'], $r['period'],
                $r['logins'], $r['unique_students'], $r['total_students'], (float) $r['percent']];
        }
        if ($this->rows === []) {
            $left[] = array_pad(["Bu oraliqda kirish topilmadi."], 8, '');
        } else {
            $left[] = ['', 'Jami', '', '', array_sum(array_column($this->rows, 'logins')), '', '', ''];
        }
        $this->leftLast = count($left);

        // O'ng jadval: umumiy, davr bo'yicha
        $right = [[
            'Umumiy: '.mb_strtolower($this->periodLabel), 'Kirishlar soni', 'Kirgan talabalar', 'Jami talabalar', 'Kirganlar %',
        ]];
        foreach ($this->summary as $r) {
            $right[] = [$r['period'], $r['logins'], $r['unique_students'], $r['total_students'], (float) $r['percent']];
        }
        if ($this->summary !== []) {
            $right[] = ['Jami', array_sum(array_column($this->summary, 'logins')), '', '', ''];
        }
        $this->rightLast = count($right);

        // Ikkala jadvalni bitta massivga yonma-yon joylaymiz (I ustuni bo'sh)
        $out = [];
        $n = max(count($left), count($right));
        for ($i = 0; $i < $n; $i++) {
            $out[] = array_merge(
                array_pad($left[$i] ?? [], 8, ''),
                [''],
                array_pad($right[$i] ?? [], 5, '')
            );
        }

        return $out;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6, 'B' => 34, 'C' => 10, 'D' => 20, 'E' => 14, 'F' => 16, 'G' => 14, 'H' => 12,
            'I' => 4,
            'J' => 20, 'K' => 14, 'L' => 16, 'M' => 14, 'N' => 12,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);

                $this->styleTable($sheet, 'A', self::LEFT_END, $this->leftLast, $this->rows !== [], 'E', 'H', 'C');
                $this->styleTable($sheet, self::RIGHT_START, self::RIGHT_END, $this->rightLast, $this->summary !== [], 'K', 'N', 'K');

                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->freezePane('A2');
                if ($this->rows !== []) {
                    $sheet->setAutoFilter('A1:'.self::LEFT_END.($this->leftLast - 1));
                }
            },
        ];
    }

    /**
     * Bitta jadvalni bezash: sarlavha, chiziqlar, markazlash, foiz formati,
     * "Jami" qatori. $boldCol — qalin son ustuni, $pctCol — foiz ustuni,
     * $centerFrom — shu ustundan oxirigacha markazda.
     */
    private function styleTable(Worksheet $sheet, string $start, string $end, int $last, bool $hasRows, string $boldCol, string $pctCol, string $centerFrom): void
    {
        $sheet->getStyle("{$start}1:{$end}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3268']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        if (! $hasRows) {
            if ($last >= 2) {
                $sheet->mergeCells("{$start}2:{$end}2");
            }

            return;
        }

        $sheet->getStyle("{$start}2:{$end}{$last}")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
        $sheet->getStyle("{$centerFrom}2:{$end}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("{$start}2:{$start}{$last}")->getAlignment()->setHorizontal($start === 'A' ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("{$pctCol}2:{$pctCol}{$last}")->getNumberFormat()->setFormatCode('0.0');
        $sheet->getStyle("{$boldCol}2:{$boldCol}{$last}")->getFont()->setBold(true);

        $sheet->getStyle("{$start}{$last}:{$end}{$last}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EEF7']],
        ]);
        $sheet->getStyle("{$start}{$last}:{$end}{$last}")->getBorders()->getTop()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('1A3268');
    }
}
