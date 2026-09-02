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
 * Rejimlar:
 *   old — LMS dagi hozirgi holat;
 *   new — reja qo'llangandagi holat (ko'chirilgan talaba yangi guruhida);
 *   ikkalasi — o'zgargan guruhlar yonma-yon (chapda eski, o'ngda yangi
 *   tarkib), o'zgarmagan guruhlar esa pastda bitta jadval bilan.
 */
class DistributionGroupStudentsExport implements FromArray, WithTitle, WithEvents, WithColumnWidths, ShouldAutoSize
{
    use Exportable;

    /** @var array<int, array{row:int, type:string, range?:string}> */
    private array $layout = [];

    private int $lastRow = 0;

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

    private function isComparison(): bool
    {
        return count($this->modes) > 1;
    }

    public function array(): array
    {
        return $this->isComparison() ? $this->comparisonRows() : $this->singleRows();
    }

    /* ==================== Bitta rejim (old yoki new) ==================== */

    private function singleRows(): array
    {
        $rows = [];
        $this->layout = [];

        $rows[] = [$this->heading, '', '', ''];
        $this->layout[] = ['row' => 1, 'type' => 'heading', 'range' => 'A1:D1'];
        $rows[] = ['', '', '', ''];

        $drafts = $this->drafts();
        $mode = $this->modes[0] ?? 'old';
        $byGroup = $this->studentsByGroup($mode, $drafts);

        foreach ($this->groups as $group) {
            $this->emitSingleBlock($rows, $group, $byGroup->get((int) $group['group_hemis_id'], collect()));
        }

        if ($this->groups->isEmpty()) {
            $rows[] = ['Tanlangan filtr bo\'yicha guruh topilmadi.', '', '', ''];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    /** Bitta guruh jadvali (A-D ustunlarda). */
    private function emitSingleBlock(array &$rows, array $group, Collection $students): void
    {
        $rows[] = [
            $group['group_name'],
            $this->metaText($group),
            '',
            $this->summary($group, $students->count()),
        ];
        $this->layout[] = ['row' => count($rows), 'type' => 'group', 'range' => 'A' . count($rows) . ':D' . count($rows)];

        $rows[] = ['№', 'Guruh', 'F.I.Sh', 'Talaba ID'];
        $this->layout[] = ['row' => count($rows), 'type' => 'header', 'range' => 'A' . count($rows) . ':D' . count($rows)];

        if ($students->isEmpty()) {
            $rows[] = ['', $group['group_name'], 'Bu guruhda talaba yo\'q', ''];
            $this->layout[] = ['row' => count($rows), 'type' => 'empty', 'range' => 'C' . count($rows)];
        } else {
            $number = 1;
            foreach ($students as $student) {
                $rows[] = [$number++, $group['group_name'], $student['full_name'], $student['student_id_number']];
                $this->layout[] = ['row' => count($rows), 'type' => 'data', 'range' => 'A' . count($rows) . ':D' . count($rows)];
            }
        }

        $rows[] = ['', '', '', ''];
    }

    /* ==================== Taqqoslash (ikkalasi) ==================== */

    private function comparisonRows(): array
    {
        $rows = [];
        $this->layout = [];

        $rows[] = [$this->heading];
        $this->layout[] = ['row' => 1, 'type' => 'heading', 'range' => 'A1:I1'];
        $rows[] = [''];

        $drafts = $this->drafts();
        $oldByGroup = $this->studentsByGroup('old', $drafts);
        $newByGroup = $this->studentsByGroup('new', $drafts);

        // O'zgargan guruhlar (reja bo'yicha kimdir kelgan yoki ketgan) yuqorida
        // yonma-yon; qolganlari pastda bitta jadval bilan.
        [$changed, $unchanged] = $this->groups->partition(
            fn ($group) => ($group['moved_in'] ?? 0) > 0 || ($group['moved_out'] ?? 0) > 0
        );

        foreach ($changed as $group) {
            $this->emitComparisonBlock(
                $rows,
                $group,
                $oldByGroup->get((int) $group['group_hemis_id'], collect())->values(),
                $newByGroup->get((int) $group['group_hemis_id'], collect())->values()
            );
        }

        if ($unchanged->isNotEmpty()) {
            $rows[] = ["O'ZGARISHSIZ GURUHLAR"];
            $this->layout[] = ['row' => count($rows), 'type' => 'section', 'range' => 'A' . count($rows) . ':I' . count($rows)];
            $rows[] = [''];

            foreach ($unchanged as $group) {
                $this->emitSingleBlock($rows, $group, $oldByGroup->get((int) $group['group_hemis_id'], collect()));
            }
        }

        if ($this->groups->isEmpty()) {
            $rows[] = ['Tanlangan filtr bo\'yicha guruh topilmadi.'];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    /** O'zgargan guruh: chapda (A-D) eski, o'ngda (F-I) yangi tarkib. */
    private function emitComparisonBlock(array &$rows, array $group, Collection $old, Collection $new): void
    {
        // Guruh banneri butun kenglikda
        $rows[] = [$group['group_name'] . '   ·   ' . $this->metaText($group)];
        $this->layout[] = ['row' => count($rows), 'type' => 'gband', 'range' => 'A' . count($rows) . ':I' . count($rows)];

        // Ikki tomon sarlavhalari
        $rows[] = [
            'ESKI HOLAT · ' . $old->count() . ' ta talaba', '', '', '', '',
            'YANGI HOLAT · ' . $this->summary($group, $new->count()),
        ];
        $r = count($rows);
        $this->layout[] = ['row' => $r, 'type' => 'subold', 'range' => "A{$r}:D{$r}"];
        $this->layout[] = ['row' => $r, 'type' => 'subnew', 'range' => "F{$r}:I{$r}"];

        // Ustun nomlari
        $rows[] = ['№', 'Guruh', 'F.I.Sh', 'Talaba ID', '', '№', 'Guruh', 'F.I.Sh', 'Talaba ID'];
        $r = count($rows);
        $this->layout[] = ['row' => $r, 'type' => 'header', 'range' => "A{$r}:D{$r}"];
        $this->layout[] = ['row' => $r, 'type' => 'header', 'range' => "F{$r}:I{$r}"];

        // Juft qatorlar
        $count = max($old->count(), $new->count());
        for ($index = 0; $index < $count; $index++) {
            $left = $old->get($index);
            $right = $new->get($index);

            $rows[] = [
                $left ? $index + 1 : '',
                $left ? $group['group_name'] : '',
                $left ? $left['full_name'] : '',
                $left ? $left['student_id_number'] : '',
                '',
                $right ? $index + 1 : '',
                $right ? $group['group_name'] : '',
                $right ? $right['full_name'] : '',
                $right ? $right['student_id_number'] : '',
            ];
            $r = count($rows);
            if ($left) {
                $this->layout[] = ['row' => $r, 'type' => 'data', 'range' => "A{$r}:D{$r}"];
            }
            if ($right) {
                $this->layout[] = ['row' => $r, 'type' => 'data', 'range' => "F{$r}:I{$r}"];
            }
        }

        $rows[] = [''];
    }

    /* ==================== Umumiy yordamchilar ==================== */

    private function metaText(array $group): string
    {
        return collect([
            $group['faculty_name'] ?? null,
            $group['specialty_name'] ?? null,
            !empty($group['course']) ? $group['course'] . '-kurs' : null,
            $group['language_name'] ?? null,
        ])->filter()->implode(' · ');
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

    /* ==================== Ko'rinish ==================== */

    public function columnWidths(): array
    {
        if ($this->isComparison()) {
            return ['A' => 5, 'B' => 16, 'C' => 36, 'D' => 15, 'E' => 3, 'F' => 5, 'G' => 16, 'H' => 36, 'I' => 15];
        }

        return ['A' => 6, 'B' => 20, 'C' => 42, 'D' => 34];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->layout as $entry) {
                    $row = $entry['row'];
                    $range = $entry['range'] ?? "A{$row}:D{$row}";

                    match ($entry['type']) {
                        'heading' => $this->styleHeading($sheet, $range, $row),
                        'section' => $this->styleSection($sheet, $range, $row),
                        'gband' => $this->styleBand($sheet, $range, $row),
                        'subold' => $this->styleSub($sheet, $range, 'EEF2F8', '4D6180'),
                        'subnew' => $this->styleSub($sheet, $range, 'FDF3E0', '8A5A06'),
                        'group' => $this->styleGroup($sheet, $range, $row),
                        'header' => $this->styleHeader($sheet, $range),
                        'data' => $this->styleData($sheet, $range),
                        'empty' => $sheet->getStyle($range)->getFont()->setItalic(true)->getColor()->setRGB('94A3B8'),
                        default => null,
                    };
                }

                $lastColumn = $this->isComparison() ? 'I' : 'D';
                $sheet->getStyle('A1:' . $lastColumn . max(1, $this->lastRow))
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

    private function styleSection($sheet, string $range, int $row): void
    {
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('8A5A06');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDF3E0');
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    private function styleBand($sheet, string $range, int $row): void
    {
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    private function styleSub($sheet, string $range, string $fill, string $color): void
    {
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB($color);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
    }

    private function styleGroup($sheet, string $range, int $row): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
        $endColumn = substr($range, strrpos($range, ':') + 1, 1);
        $sheet->getStyle("{$endColumn}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
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

        // Birinchi ustun (№) markazda, ikkinchi (guruh nomi) kulrang.
        $start = substr($range, 0, strcspn($range, '0123456789'));
        $row = (int) filter_var(explode(':', $range)[0], FILTER_SANITIZE_NUMBER_INT);
        $second = chr(ord($start) + 1);
        $sheet->getStyle("{$start}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("{$second}{$row}")->getFont()->getColor()->setRGB('4D6180');
    }
}
