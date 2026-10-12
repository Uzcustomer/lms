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
 * "Superkontrakt" varag'i: superkontrakt talabalarning HEMISga kiritilishidan
 * OLDINGI darslarda baho qo'yilmagan holatlari. Ular "Talabalar kesimida"
 * varag'ida ham bor (baho qo'yilmagan hisoblanadi), bu yerda alohida
 * ko'rsatiladi: o'qituvchi bu baholarni jurnalda arizasiz qo'yadi.
 *
 * 1-qator — jami, 2-qator — sarlavha, ma'lumot 3-qatordan.
 */
class LessonOpeningTeacherSuperkontraktSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private const LAST_COL = 'L';

    private const HEADER_ROW = 2;

    private int $lastRow = 0;

    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Superkontrakt';
    }

    public function array(): array
    {
        $rows = $this->report['superkontrakt'] ?? [];
        $distinct = count(array_unique(array_column($rows, 'student_hemis_id')));

        // A1 bo'sh bo'lmasin: Laravel Excel qatorlarni 1000 talab yozadi va A1
        // bo'sh bo'lsa keyingi bo'lakni yana 1-qatordan yozib, ma'lumotni bosadi.
        $out = [
            array_pad(['Jami:', "{$distinct} ta superkontrakt talaba · " . count($rows) . " ta dars (HEMISga kiritilishidan oldin)"], 12, ''),
            [
                '№', 'Talaba', 'Talaba ID', 'Guruh', 'Kurs', 'Fan', 'Dars sanasi', 'Juftlik',
                "O'qituvchi", 'Kafedra', 'HEMISga kiritilgan', "Mas'ul xodim",
            ],
        ];

        foreach ($rows as $index => $row) {
            $out[] = [
                $index + 1,
                $row['student'] !== '' ? $row['student'] : '(nomi topilmadi)',
                $row['student_id_number'] !== '' ? $row['student_id_number'] : $row['student_hemis_id'],
                $row['group'],
                $row['course'] ?? '',
                $row['subject'],
                $row['date'] ? \Carbon\Carbon::parse($row['date'])->format('d.m.Y') : '',
                $row['pair'] ?? '',
                $row['teacher'],
                $row['department'] ?? '',
                !empty($row['joined_at']) ? \Carbon\Carbon::parse($row['joined_at'])->format('d.m.Y') : '',
                ($row['manager'] ?? '') !== '' ? $row['manager'] : LessonOpeningTeacherStudentsSheet::UNASSIGNED,
            ];
        }

        if ($rows === []) {
            $out[] = array_pad(["Bu davrda superkontrakt talabalarda HEMISga kiritilishidan oldingi baho qo'yilmagan dars yo'q."], 12, '');
        }

        $this->lastRow = count($out);

        return $out;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 38, 'C' => 16, 'D' => 16, 'E' => 9, 'F' => 40,
            'G' => 13, 'H' => 14, 'I' => 34, 'J' => 32, 'K' => 15, 'L' => 34];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->lastRow;
                $lc = self::LAST_COL;
                $h = self::HEADER_ROW;
                $first = $h + 1;

                // Jami qatori — sariq (jurnaldagi superkontrakt kataklari bilan bir xil)
                $sheet->getStyle("A1:{$lc}1")->getFont()->setBold(true)->getColor()->setRGB('7A4B00');
                $sheet->getStyle("A1:{$lc}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
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
                if (($this->report['superkontrakt'] ?? []) === []) {
                    $sheet->mergeCells("A{$last}:{$lc}{$last}");

                    return;
                }

                $sheet->setAutoFilter("A{$h}:{$lc}{$last}");
                $sheet->getStyle("A{$first}:{$lc}{$last}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                $sheet->getStyle("A{$first}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$first}:B{$last}")->getFont()->setBold(true);
                $sheet->getStyle("C{$first}:E{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$first}:H{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("K{$first}:K{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("K{$first}:K{$last}")->getFont()->setBold(true)->getColor()->setRGB('92400E');
                $sheet->getStyle("A{$h}:{$lc}{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
