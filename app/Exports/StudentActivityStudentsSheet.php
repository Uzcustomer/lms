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
 * "Talabalar" varag'i: har bir o'qiyotgan bakalavr alohida qator — oraliqda
 * necha marta kirgan, nechta kunda, oraliqdagi birinchi va oxirgi kirishi,
 * hamda umuman oxirgi kirishi (oraliqdan tashqarida bo'lsa ham). Kirmaganlar
 * ham bor (0), ular qizg'ish rangda ajralib turadi. Ko'p kirganlar birinchi.
 */
class StudentActivityStudentsSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private const LAST_COL = 'K';

    private const HEADER_ROW = 2;

    private int $lastRow = 2;

    public function __construct(
        private array $rows,
        private int $totalStudents,
        private string $from,
        private string $to,
    ) {
    }

    public function title(): string
    {
        return 'Talabalar';
    }

    public function array(): array
    {
        $loggedIn = count(array_filter($this->rows, fn ($r) => $r['logins'] > 0));
        $out = [
            array_pad([
                'LMS ga kirishlar — talabalar kesimida · '
                .\Carbon\Carbon::parse($this->from)->format('d.m.Y').' – '.\Carbon\Carbon::parse($this->to)->format('d.m.Y')
                .' · jami bakalavr: '.number_format($this->totalStudents, 0, '.', ' ')
                .' · kirgan: '.number_format($loggedIn, 0, '.', ' ')
                .' · kirmagan: '.number_format($this->totalStudents - $loggedIn, 0, '.', ' '),
            ], 11, ''),
            ['#', 'Talaba', 'Talaba ID', 'Fakultet', 'Kurs', 'Guruh', 'Kirishlar soni', 'Kirgan kunlar',
                'Birinchi kirish (oraliqda)', 'Oxirgi kirish (oraliqda)', 'Oxirgi kirish (umuman)'],
        ];

        foreach ($this->rows as $i => $r) {
            $out[] = [
                $i + 1,
                $r['student'],
                $r['student_id_number'],
                $r['faculty'],
                $r['course'],
                $r['group'],
                // Satr sifatida: PhpSpreadsheet butun 0 ni bo'sh katak qilib saqlaydi
                (string) $r['logins'],
                (string) $r['days'],
                $r['first_login'] ? \Carbon\Carbon::parse($r['first_login'])->format('d.m.Y H:i') : '',
                $r['last_login'] ? \Carbon\Carbon::parse($r['last_login'])->format('d.m.Y H:i') : '',
                $r['last_ever'] ? \Carbon\Carbon::parse($r['last_ever'])->format('d.m.Y H:i') : "hech qachon",
            ];
        }

        if ($this->rows === []) {
            $out[] = array_pad(['Bakalavr talaba topilmadi.'], 11, '');
        }
        $this->lastRow = count($out);

        return $out;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 40, 'C' => 16, 'D' => 28, 'E' => 9, 'F' => 16, 'G' => 14, 'H' => 13, 'I' => 20, 'J' => 20, 'K' => 20];
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

                $sheet->mergeCells("A1:{$end}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('0F2748');
                $sheet->getRowDimension(1)->setRowHeight(24);

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
                $sheet->setAutoFilter("A{$h}:{$end}{$last}");
                $sheet->getStyle("A{$body}:{$end}{$last}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                $sheet->getStyle("A{$body}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$body}:C{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$body}:{$end}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$body}:G{$last}")->getFont()->setBold(true);

                // Kirmaganlar — qizg'ish qator. Ro'yxat kirishlar bo'yicha kamayib
                // boradi, shuning uchun nollar oxirida ketma-ket turadi.
                $firstZero = null;
                foreach ($this->rows as $i => $r) {
                    if ($r['logins'] === 0) {
                        $firstZero = $body + $i;
                        break;
                    }
                }
                if ($firstZero !== null) {
                    $sheet->getStyle("A{$firstZero}:{$end}{$last}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF1F2');
                    $sheet->getStyle("B{$firstZero}:B{$last}")->getFont()->getColor()->setRGB('B3261E');
                }
            },
        ];
    }
}
