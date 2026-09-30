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
 * Starostalar ro'yxati eksporti — talaba (starosta) kesimida.
 */
class StarostaListExport implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithTitle
{
    private const LAST_COL = 'J';

    public function __construct(private Collection $students) {}

    public function title(): string
    {
        return 'Starostalar';
    }

    public function headings(): array
    {
        return ['№', 'F.I.SH', 'HEMIS ID', 'Talaba ID', "Ta'lim turi", "Ta'lim shakli", 'Fakultet', "Yo'nalish", 'Kurs', 'Guruh'];
    }

    public function array(): array
    {
        $out = [];
        $i = 1;
        foreach ($this->students as $s) {
            $out[] = [
                $i++,
                $s->full_name,
                $s->hemis_id,
                $s->student_id_number,
                $s->education_type_name,
                $s->education_form_name,
                $s->department_name,
                $s->specialty_name,
                $s->level_name,
                $s->group_name,
            ];
        }

        return $out;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 34, 'C' => 10, 'D' => 16, 'E' => 14, 'F' => 14, 'G' => 26, 'H' => 28, 'I' => 12, 'J' => 16];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lc = self::LAST_COL;
                $last = $this->students->count() + 1;

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
                    $sheet->getStyle("I2:I{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}
