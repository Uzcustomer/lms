<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Taqsimlash eksportining "Guruhlar" varag'i — sahifadagi ro'yxatning o'zi:
 * har guruh bir qator, fakulteti, yo'nalishi, kursi, tili, talabalar soni,
 * sig'imi va holati bilan.
 *
 * Talabalar soni rejimga bog'liq: "old" — LMS dagi hozirgi son, aks holda
 * reja qo'llangan son (sahifada ko'rinadigani).
 */
class DistributionGroupsSheet implements FromArray, WithTitle, WithEvents, WithColumnWidths, ShouldAutoSize
{
    private int $lastRow = 0;

    public function __construct(
        private Collection $groups,
        private string $heading = 'Guruhlar',
        private string $mode = 'old'
    ) {
    }

    public function title(): string
    {
        return 'Guruhlar';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = [$this->heading, '', '', '', '', '', '', '', '', ''];
        $rows[] = ['', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['№', 'Guruh', 'Fakultet', 'Yo\'nalish', 'Kurs', 'Ta\'lim tili', 'Talaba', 'Sig\'im', 'Bo\'sh joy', 'Holat'];

        $number = 1;
        foreach ($this->groups as $group) {
            $students = $this->mode === 'old'
                ? (int) ($group['lms_student_count'] ?? $group['student_count'] ?? 0)
                : (int) ($group['student_count'] ?? 0);
            $capacity = $group['capacity'] ?? null;
            $free = $capacity === null ? null : (int) $capacity - $students;

            $rows[] = [
                $number++,
                $group['group_name'] ?? '',
                $group['faculty_name'] ?? '',
                $group['specialty_name'] ?? '',
                !empty($group['course']) ? $group['course'] . '-kurs' : ($group['level_name'] ?? ''),
                $group['language_name'] ?? '',
                $students,
                $capacity ?? '',
                $free ?? '',
                $this->statusText($free),
            ];
        }

        if ($this->groups->isEmpty()) {
            $rows[] = ['Tanlangan filtr bo\'yicha guruh topilmadi.', '', '', '', '', '', '', '', '', ''];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    private function statusText(?int $free): string
    {
        if ($free === null) {
            return 'sig\'im belgilanmagan';
        }
        if ($free > 0) {
            return 'bo\'sh joy bor';
        }
        if ($free < 0) {
            return abs($free) . ' ortiqcha';
        }

        return 'to\'la';
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 20, 'C' => 22, 'D' => 24, 'E' => 9, 'F' => 12, 'G' => 9, 'H' => 9, 'I' => 10, 'J' => 18];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = max(4, $this->lastRow);

                $sheet->mergeCells('A1:J1');
                $sheet->getStyle('A1:J1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('0F2748');
                $sheet->getRowDimension(1)->setRowHeight(24);

                $sheet->getStyle('A3:J3')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A3:J3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getRowDimension(3)->setRowHeight(20);
                $sheet->freezePane('A4');
                $sheet->setAutoFilter('A3:J' . $last);

                if ($last >= 4) {
                    $sheet->getStyle("A4:J{$last}")->getBorders()->getBottom()
                        ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E2E8F0');
                    $sheet->getStyle("A4:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E4:I{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B4:B{$last}")->getFont()->setBold(true);

                    // Holat rangi: yashil — bo'sh joy bor, kulrang — to'la, qizil — ortiqcha.
                    for ($row = 4; $row <= $last; $row++) {
                        $status = (string) $sheet->getCell("J{$row}")->getValue();
                        $color = str_contains($status, 'ortiqcha') ? 'B3261E'
                            : (str_starts_with($status, 'bo\'sh') ? '0F7A52' : '8798B1');
                        $sheet->getStyle("J{$row}")->getFont()->setBold(true)->getColor()->setRGB($color);
                    }
                }

                $sheet->getStyle("A1:J{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
