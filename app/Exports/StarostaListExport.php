<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Starostalar ro'yxati eksporti: guruh, kafedra, yo'nalish, kurs, starosta.
 */
class StarostaListExport implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithTitle
{
    private const LAST_COL = 'J';

    public function __construct(private Collection $rows) {}

    public function title(): string
    {
        return 'Starostalar';
    }

    public function headings(): array
    {
        return ['№', 'Guruh', 'Kafedra', "Yo'nalish", 'Kurs', 'Talabalar soni', 'Starosta (F.I.SH)', 'Talaba ID', 'Telefon', 'Holat'];
    }

    public function array(): array
    {
        $out = [];
        $i = 1;
        foreach ($this->rows as $r) {
            $out[] = [
                $i++,
                $r->group,
                $r->department ?? '',
                $r->specialty ?? '',
                $r->course ? $r->course.'-kurs' : '',
                $r->student_count,
                $r->starosta ?? '',
                $r->starosta_id_number ?? '',
                $r->starosta_phone ?? '',
                $r->starosta ? 'Belgilangan' : 'Belgilanmagan',
            ];
        }

        return $out;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 18, 'C' => 32, 'D' => 30, 'E' => 8, 'F' => 14, 'G' => 36, 'H' => 14, 'I' => 16, 'J' => 16];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lc = self::LAST_COL;
                $last = $this->rows->count() + 1;

                $sheet->getStyle("A1:{$lc}1")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A1:{$lc}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle("A1:{$lc}1")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->freezePane('A2');

                if ($last >= 2) {
                    $sheet->setAutoFilter("A1:{$lc}{$last}");
                    $sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E2:F{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}
