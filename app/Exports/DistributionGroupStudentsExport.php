<?php

namespace App\Exports;

use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
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
 * Taqsimlash sahifasidagi filtrga mos guruhlar va ulardagi talabalar.
 *
 * Bitta varaqda guruhlar ketma-ket bloklar bo'lib chiqadi: guruh sarlavhasi,
 * ustun nomlari, talabalar ro'yxati, so'ng bo'sh qator.
 */
class DistributionGroupStudentsExport implements FromArray, WithTitle, WithEvents, WithColumnWidths, ShouldAutoSize
{
    use Exportable;

    /** @var array<int, array{row:int, type:string, span?:int}> Formatlash uchun qator xaritasi */
    private array $layout = [];

    private int $lastRow = 0;

    /**
     * @param Collection $groups Guruhlar: group_hemis_id, group_name, faculty_name, specialty_name, level_name
     */
    public function __construct(private Collection $groups, private string $heading = 'Guruhlar bo\'yicha talabalar')
    {
    }

    public function title(): string
    {
        return 'Talabalar';
    }

    public function array(): array
    {
        $rows = [];
        $this->layout = [];

        $groupIds = $this->groups->pluck('group_hemis_id')->all();
        $studentsByGroup = $this->studentsByGroup($groupIds);

        // Umumiy sarlavha
        $rows[] = [$this->heading, '', ''];
        $this->layout[] = ['row' => 1, 'type' => 'heading'];
        $rows[] = ['', '', ''];

        foreach ($this->groups as $group) {
            $students = $studentsByGroup->get((int) $group['group_hemis_id'], collect());

            $meta = collect([
                $group['faculty_name'] ?? null,
                $group['specialty_name'] ?? null,
                !empty($group['course']) ? $group['course'] . '-kurs' : ($group['level_name'] ?? null),
            ])->filter()->implode(' · ');

            $rows[] = [
                $group['group_name'] . ($meta !== '' ? '   ·   ' . $meta : ''),
                '',
                $students->count() . ' ta talaba',
            ];
            $this->layout[] = ['row' => count($rows), 'type' => 'group'];

            $rows[] = ['№', 'F.I.Sh', 'Talaba ID'];
            $this->layout[] = ['row' => count($rows), 'type' => 'header'];

            if ($students->isEmpty()) {
                $rows[] = ['', 'Bu guruhda o\'qiyotgan talaba yo\'q', ''];
                $this->layout[] = ['row' => count($rows), 'type' => 'empty'];
            } else {
                $number = 1;
                foreach ($students as $student) {
                    $rows[] = [
                        $number++,
                        $student->full_name,
                        (string) $student->student_id_number,
                    ];
                    $this->layout[] = ['row' => count($rows), 'type' => 'data'];
                }
            }

            $rows[] = ['', '', ''];
        }

        if ($this->groups->isEmpty()) {
            $rows[] = ['Tanlangan filtr bo\'yicha guruh topilmadi.', '', ''];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 46, 'C' => 18];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->layout as $entry) {
                    $row = $entry['row'];
                    $range = "A{$row}:C{$row}";

                    match ($entry['type']) {
                        'heading' => $this->styleHeading($sheet, $range, $row),
                        'group' => $this->styleGroup($sheet, $range, $row),
                        'header' => $this->styleHeader($sheet, $range),
                        'data' => $this->styleData($sheet, $range),
                        'empty' => $sheet->getStyle($range)->getFont()->setItalic(true)->getColor()->setRGB('94A3B8'),
                        default => null,
                    };
                }

                $sheet->getStyle('A1:C' . max(1, $this->lastRow))
                    ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }

    private function styleHeading($sheet, string $range, int $row): void
    {
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('0F2748');
        $sheet->getRowDimension($row)->setRowHeight(24);
    }

    private function styleGroup($sheet, string $range, int $row): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
        $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('4D6180');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2F8');
        $sheet->getStyle($range)->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('C4D0E0');
    }

    private function styleData($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E2E8F0');
        $sheet->getStyle(str_replace(':C', ':A', $range))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /**
     * Guruhlardagi o'qiyotgan talabalar, guruh id si bo'yicha guruhlangan.
     * Bitta so'rov bilan olinadi — guruh soni ko'p bo'lishi mumkin.
     */
    private function studentsByGroup(array $groupIds): Collection
    {
        if (empty($groupIds)) {
            return collect();
        }

        return Student::query()
            ->where('student_status_code', 11)
            ->whereIn('group_id', $groupIds)
            ->orderBy('full_name')
            ->get(['group_id', 'full_name', 'student_id_number'])
            ->groupBy(fn ($student) => (int) $student->group_id);
    }
}
