<?php

namespace App\Exports;

use App\Models\DistributionDraftAssignment;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
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
 * "Taqsimlanadigan guruhlar" vkladkasining Excel varag'i — yassi ro'yxat:
 * har qatorda guruh, talaba va shu guruhdagi talabalar soni. Bloklarga
 * bo'linmagan, shuning uchun Excelda filtr va saralash qulay.
 *
 * Rejim: 'old' — LMS dagi hozirgi tarkib; 'new' — reja qo'llangan holat
 * (ko'chirilgan talaba yangi guruhida hisoblanadi).
 */
class DistributionSourceStudentsSheet implements FromArray, WithTitle, WithEvents, WithColumnWidths, ShouldAutoSize
{
    private int $lastRow = 0;

    public function __construct(
        private Collection $groups,
        private string $heading = 'Taqsimlanadigan guruhlar',
        private string $mode = 'old'
    ) {
    }

    public function title(): string
    {
        return 'Talabalar';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = [$this->heading, '', '', '', '', ''];
        $rows[] = ['', '', '', '', '', ''];
        $rows[] = ['№', 'Guruh', 'F.I.Sh', 'Talaba ID', 'Guruhdagi talaba soni', 'Fakultet · Yo\'nalish · Kurs'];

        $byGroup = $this->studentsByGroup();
        $number = 1;

        foreach ($this->groups as $group) {
            $students = $byGroup->get((int) $group['group_hemis_id'], collect());
            $meta = collect([
                $group['faculty_name'] ?? null,
                $group['specialty_name'] ?? null,
                !empty($group['course']) ? $group['course'] . '-kurs' : null,
                $group['language_name'] ?? null,
            ])->filter()->implode(' · ');

            if ($students->isEmpty()) {
                $rows[] = [$number++, $group['group_name'], 'Bu guruhda talaba yo\'q', '', 0, $meta];
                continue;
            }

            foreach ($students as $student) {
                $rows[] = [
                    $number++,
                    $group['group_name'],
                    $student['full_name'],
                    $student['student_id_number'],
                    $students->count(),
                    $meta,
                ];
            }
        }

        if ($this->groups->isEmpty()) {
            $rows[] = ['Taqsimlanadigan guruh belgilanmagan.', '', '', '', '', ''];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    /** Talabalar guruh bo'yicha, rejimga qarab (LMS yoki reja). */
    private function studentsByGroup(): Collection
    {
        $groupIds = $this->groups->pluck('group_hemis_id')->map(fn ($id) => (int) $id);

        if ($groupIds->isEmpty()) {
            return collect();
        }

        $drafts = Schema::hasTable('distribution_draft_assignments')
            ? DistributionDraftAssignment::query()->get()
            : collect();
        $moves = $drafts->keyBy('student_id');

        $extraIds = $this->mode === 'new'
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
            ->map(function ($student) use ($moves) {
                $groupId = (int) $student->group_id;

                if ($this->mode === 'new' && $moves->has($student->id)) {
                    $groupId = (int) $moves->get($student->id)->to_group_hemis_id;
                }

                return [
                    'group_id' => $groupId,
                    'full_name' => $student->full_name,
                    'student_id_number' => (string) $student->student_id_number,
                ];
            })
            ->filter(fn ($row) => $groupIds->contains($row['group_id']))
            ->groupBy('group_id');
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 20, 'C' => 40, 'D' => 16, 'E' => 12, 'F' => 44];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = max(4, $this->lastRow);

                $sheet->mergeCells('A1:F1');
                $sheet->getStyle('A1:F1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('0F2748');
                $sheet->getRowDimension(1)->setRowHeight(24);

                $sheet->getStyle('A3:F3')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A3:F3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('8A5A06');
                $sheet->getRowDimension(3)->setRowHeight(20);
                $sheet->freezePane('A4');
                $sheet->setAutoFilter('A3:F' . $last);

                $sheet->getStyle("A4:F{$last}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E2E8F0');
                $sheet->getStyle("A4:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D4:E{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B4:B{$last}")->getFont()->setBold(true)->getColor()->setRGB('1B3A63');
                $sheet->getStyle("F4:F{$last}")->getFont()->getColor()->setRGB('8798B1');
                $sheet->getStyle("A1:F{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
