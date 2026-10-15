<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Dars jadvali: o'qituvchi va xona biriktirish hisoboti (admin).
 *   "Kafedralar"        — har kafedra bo'yicha dars birliklari, o'qituvchi/xona
 *                         biriktirilgan va biriktirilmaganlar soni;
 *   "Biriktirilmaganlar" — o'qituvchisi yoki xonasi yo'q dars birliklari.
 *
 * Dars birligi = yo'nalish + kurs + fan + tur + oqim (ma'ruza) yoki guruh (amaliy).
 * Birlik "biriktirilgan" — uning BARCHA kartalarida o'qituvchi (xona) bo'lsa.
 */
class TimetableAssignmentReportExport implements WithMultipleSheets
{
    public function __construct(private array $kafedras, private array $missing, private string $boardTitle) {}

    public function sheets(): array
    {
        return [
            new TimetableAssignmentSummarySheet($this->kafedras, $this->boardTitle),
            new TimetableAssignmentMissingSheet($this->missing),
        ];
    }
}

// WithStrictNullComparison: 0 qiymatlar bo'sh katak bo'lib qolmasin ("O'qituvchisiz: 0")
class TimetableAssignmentSummarySheet implements FromArray, WithColumnWidths, WithEvents, WithTitle, WithStrictNullComparison
{
    private int $lastRow = 0;

    public function __construct(private array $kafedras, private string $boardTitle) {}

    public function title(): string
    {
        return 'Kafedralar';
    }

    public function array(): array
    {
        $rows = [
            array_pad(["O'qituvchi va xona biriktirish hisoboti — " . $this->boardTitle . ' · ' . now('Asia/Tashkent')->format('d.m.Y H:i')], 9, ''),
            ['№', 'Kafedra', 'Dars birliklari', "O'qituvchi biriktirilgan", "O'qituvchisiz", 'Xona biriktirilgan', 'Xonasiz', "To'liq biriktirilgan", "Fanlar soni"],
        ];
        $sum = array_fill(0, 7, 0);
        foreach (array_values($this->kafedras) as $i => $k) {
            $vals = [$k['units'], $k['teacher'], $k['units'] - $k['teacher'], $k['room'], $k['units'] - $k['room'], $k['full'], $k['subjects']];
            foreach ($vals as $j => $v) {
                $sum[$j] += $v;
            }
            $rows[] = array_merge([$i + 1, $k['name']], $vals);
        }
        $rows[] = array_merge(['', 'Jami'], $sum);
        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 52, 'C' => 14, 'D' => 15, 'E' => 13, 'F' => 14, 'G' => 11, 'H' => 15, 'I' => 11];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->lastRow;
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);
                $sheet->mergeCells('A1:I1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1B3A63');
                $sheet->getStyle('A2:I2')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A2:I2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle('A2:I2')->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(2)->setRowHeight(32);
                $sheet->freezePane('A3');
                $sheet->getStyle("A3:I{$last}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                $sheet->getStyle("A3:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C3:I{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Biriktirilmaganlar ajralib tursin
                $sheet->getStyle("E3:E{$last}")->getFont()->getColor()->setRGB('B3261E');
                $sheet->getStyle("G3:G{$last}")->getFont()->getColor()->setRGB('B3261E');
                $sheet->getStyle("A{$last}:I{$last}")->getFont()->setBold(true);
                $sheet->getStyle("A{$last}:I{$last}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF7');
                if ($last > 3) {
                    $sheet->setAutoFilter('A2:I' . ($last - 1));
                }
            },
        ];
    }
}

class TimetableAssignmentMissingSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private int $lastRow = 0;

    public function __construct(private array $missing) {}

    public function title(): string
    {
        return 'Biriktirilmaganlar';
    }

    public function array(): array
    {
        // A1 bo'sh bo'lmasin: Laravel Excel bo'laklab yozadi (1000 qatordan keyin ustma-ust yozmasin)
        $rows = [['№', 'Kafedra', 'Fan', 'Fakultet / yo\'nalish · kurs', 'Tur', 'Oqim / guruh', 'Karta', "O'qituvchi", 'Xona']];
        foreach (array_values($this->missing) as $i => $u) {
            $rows[] = [
                $i + 1, $u['kafedra'], $u['subject'], $u['scope'], $u['type'], $u['group'], $u['cards'],
                $u['teacher'] ?: '— biriktirilmagan —',
                $u['room'] ?: '— biriktirilmagan —',
            ];
        }
        if (count($rows) === 1) {
            $rows[] = ['', "Barcha dars birliklariga o'qituvchi va xona biriktirilgan."];
        }
        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 40, 'C' => 40, 'D' => 36, 'E' => 10, 'F' => 22, 'G' => 8, 'H' => 28, 'I' => 22];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->lastRow;
                $sheet->getStyle('A1:I1')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle('A1:I1')->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->freezePane('A2');
                if ($last > 1) {
                    $sheet->setAutoFilter("A1:I{$last}");
                    $sheet->getStyle("A2:I{$last}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                    $sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E2:G{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}
