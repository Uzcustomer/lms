<?php

namespace App\Exports;

use App\Models\DistributionDraftAssignment;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
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
 * Taqsimlash sahifasidagi guruhlar va ulardagi talabalar.
 *
 * Ikki holatda chiqarish mumkin:
 *   old — LMS dagi hozirgi holat (reja hisobga olinmaydi);
 *   new — reja qo'llangandagi holat (ko'chirilgan talaba yangi guruhida).
 * Ikkalasi tanlansa bitta varaqda ketma-ket ikkita bo'lim bo'ladi.
 *
 * Guruh nomi ham blok sarlavhasida, ham har bir talaba qatorida alohida
 * ustunda turadi — shunda faylni saralash yoki filtrlash mumkin bo'ladi.
 */
class DistributionGroupStudentsExport implements FromArray, WithTitle, WithEvents, WithColumnWidths, ShouldAutoSize
{
    use Exportable;

    /** @var array<int, array{row:int, type:string}> Formatlash uchun qator xaritasi */
    private array $layout = [];

    private int $lastRow = 0;

    /**
     * @param Collection $groups Guruhlar katalogi
     * @param string $heading Umumiy sarlavha
     * @param array $modes 'old' va/yoki 'new'
     */
    public function __construct(
        private Collection $groups,
        private string $heading = 'Guruhlar bo\'yicha talabalar',
        private array $modes = ['old']
    ) {
    }

    public function title(): string
    {
        return 'Talabalar';
    }

    public function array(): array
    {
        $rows = [];
        $this->layout = [];

        $rows[] = [$this->heading, '', '', ''];
        $this->layout[] = ['row' => 1, 'type' => 'heading'];
        $rows[] = ['', '', '', ''];

        $drafts = $this->drafts();

        foreach ($this->modes as $mode) {
            if (count($this->modes) > 1) {
                $rows[] = [$mode === 'new' ? 'YANGI HOLAT (reja qo\'llangan)' : 'ESKI HOLAT (hozirgi)', '', '', ''];
                $this->layout[] = ['row' => count($rows), 'type' => 'section'];
                $rows[] = ['', '', '', ''];
            }

            $byGroup = $this->studentsByGroup($mode, $drafts);

            foreach ($this->groups as $group) {
                $groupId = (int) $group['group_hemis_id'];
                $students = $byGroup->get($groupId, collect());

                $meta = collect([
                    $group['faculty_name'] ?? null,
                    $group['specialty_name'] ?? null,
                    !empty($group['course']) ? $group['course'] . '-kurs' : null,
                    $group['language_name'] ?? null,
                ])->filter()->implode(' · ');

                $rows[] = [
                    $group['group_name'],
                    $meta,
                    '',
                    $this->summary($group, $students->count()),
                ];
                $this->layout[] = ['row' => count($rows), 'type' => 'group'];

                $rows[] = ['№', 'Guruh', 'F.I.Sh', 'Talaba ID'];
                $this->layout[] = ['row' => count($rows), 'type' => 'header'];

                if ($students->isEmpty()) {
                    $rows[] = ['', $group['group_name'], 'Bu guruhda talaba yo\'q', ''];
                    $this->layout[] = ['row' => count($rows), 'type' => 'empty'];
                } else {
                    $number = 1;
                    foreach ($students as $student) {
                        $rows[] = [
                            $number++,
                            $group['group_name'],
                            $student['full_name'],
                            $student['student_id_number'],
                        ];
                        $this->layout[] = ['row' => count($rows), 'type' => 'data'];
                    }
                }

                $rows[] = ['', '', '', ''];
            }
        }

        if ($this->groups->isEmpty()) {
            $rows[] = ['Tanlangan filtr bo\'yicha guruh topilmadi.', '', '', ''];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 20, 'C' => 42, 'D' => 34];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->layout as $entry) {
                    $row = $entry['row'];
                    $range = "A{$row}:D{$row}";

                    match ($entry['type']) {
                        'heading' => $this->styleHeading($sheet, $range, $row),
                        'section' => $this->styleSection($sheet, $range, $row),
                        'group' => $this->styleGroup($sheet, $range, $row),
                        'header' => $this->styleHeader($sheet, $range),
                        'data' => $this->styleData($sheet, $range, $row),
                        'empty' => $sheet->getStyle("C{$row}")->getFont()->setItalic(true)->getColor()->setRGB('94A3B8'),
                        default => null,
                    };
                }

                $sheet->getStyle('A1:D' . max(1, $this->lastRow))
                    ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }

    private function summary(array $group, int $count): string
    {
        $summary = $count . ' ta talaba';
        $capacity = $group['capacity'] ?? null;

        if ($capacity === null) {
            return $summary;
        }

        $summary .= ' / ' . $capacity . " sig'im";
        $free = $capacity - $count;

        if ($free > 0) {
            return $summary . '  ·  ' . $free . " bo'sh";
        }
        if ($free < 0) {
            return $summary . '  ·  ' . abs($free) . ' ortiqcha';
        }

        return $summary . "  ·  to'la";
    }

    private function drafts(): Collection
    {
        return Schema::hasTable('distribution_draft_assignments')
            ? DistributionDraftAssignment::query()->get()
            : collect();
    }

    /**
     * Talabalarni guruh bo'yicha guruhlaydi.
     *
     * 'old' — LMS dagi guruh; 'new' — reja qo'llangan holat: ko'chirilgan
     * talaba eski guruhidan chiqib, yangisida chiqadi.
     */
    private function studentsByGroup(string $mode, Collection $drafts): Collection
    {
        $groupIds = $this->groups->pluck('group_hemis_id')->map(fn ($id) => (int) $id);

        if ($groupIds->isEmpty()) {
            return collect();
        }

        $moves = $drafts->keyBy('student_id');

        // Rejaga ko'ra bu guruhlarga kelayotgan talaba boshqa guruhda bo'lishi
        // mumkin, shuning uchun ular ham so'rovga qo'shiladi.
        $extraIds = $mode === 'new'
            ? $drafts->whereIn('to_group_hemis_id', $groupIds->all())->pluck('student_id')
            : collect();

        return Student::query()
            ->where('student_status_code', 11)
            ->where(function ($query) use ($groupIds, $extraIds) {
                $query->whereIn('group_id', $groupIds->all());
                if ($extraIds->isNotEmpty()) {
                    $query->orWhereIn('id', $extraIds->all());
                }
            })
            ->orderBy('full_name')
            ->get(['id', 'group_id', 'full_name', 'student_id_number'])
            ->map(function ($student) use ($mode, $moves) {
                $groupId = (int) $student->group_id;

                if ($mode === 'new' && $moves->has($student->id)) {
                    $groupId = (int) $moves->get($student->id)->to_group_hemis_id;
                }

                return [
                    'group_id' => $groupId,
                    'full_name' => $student->full_name,
                    'student_id_number' => (string) $student->student_id_number,
                ];
            })
            ->groupBy('group_id');
    }

    private function styleHeading($sheet, string $range, int $row): void
    {
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('0F2748');
        $sheet->getRowDimension($row)->setRowHeight(24);
    }

    private function styleSection($sheet, string $range, int $row): void
    {
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('8A5A06');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDF3E0');
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    private function styleGroup($sheet, string $range, int $row): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
        $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
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

    private function styleData($sheet, string $range, int $row): void
    {
        $sheet->getStyle($range)->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E2E8F0');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$row}")->getFont()->getColor()->setRGB('4D6180');
    }
}
