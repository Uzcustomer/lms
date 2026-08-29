<?php

namespace App\Exports;

use App\Models\Student;
use App\Models\StudentDistributionAssignment;
use App\Models\StudentGroupChangeApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentDistributionAssignmentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    use Exportable;

    private int $rowNum = 0;

    /** @var Collection<int, StudentGroupChangeApplication> keyed by student_id */
    private Collection $approvedApplications;

    /** @var Collection<int, Student> keyed by id */
    private Collection $students;

    public function __construct(private array $filters = [])
    {
        $this->approvedApplications = $this->loadApprovedApplications();
        $this->students = $this->loadStudents();
    }

    public function query(): Builder
    {
        $f = $this->filters;

        return StudentDistributionAssignment::query()
            ->with(['sourceGroup', 'targetGroup'])
            ->when(!empty($f['faculty']), fn (Builder $query) => $query->whereHas(
                'targetGroup',
                fn (Builder $group) => $group->where('faculty_name', $f['faculty'])
            ))
            ->when(!empty($f['specialty']), fn (Builder $query) => $query->whereHas(
                'targetGroup',
                fn (Builder $group) => $group->where('specialty_name', $f['specialty'])
            ))
            ->when(!empty($f['course']), fn (Builder $query) => $query->whereHas(
                'targetGroup',
                fn (Builder $group) => $group->where('course', (int) $f['course'])
            ))
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            '#',
            'Familya',
            'Ism',
            'Otasining ismi',
            'Talaba F.I.Sh',
            'Talaba ID',
            'Fakultet',
            'Yo\'nalish',
            'Kurs',
            'Eski guruh',
            'Yangi guruh',
            'O\'tkazish turi',
            'Ariza sababi',
            'Ariza tasdiqlangan sana',
            'Taqsimlangan sana',
        ];
    }

    public function map($assignment): array
    {
        $target = $assignment->targetGroup;
        $source = $assignment->sourceGroup;
        $application = $this->approvedApplications->get($assignment->student_id);
        $student = $this->students->get($assignment->student_id);

        $viaApplication = $application
            && (int) $application->target_group_id === (int) $assignment->target_group_id;

        $fullName = trim((string) ($assignment->student_name ?: $student?->full_name));
        [$lastName, $firstName, $middleName] = $this->splitName($fullName, $student);

        return [
            ++$this->rowNum,
            $lastName,
            $firstName,
            $middleName,
            $fullName ?: '—',
            $assignment->student_id_number ?: ($student?->student_id_number ?? ''),
            $target?->faculty_name ?? ($student?->department_name ?? ''),
            $target?->specialty_name ?? ($student?->specialty_name ?? ''),
            $target?->course ? $target->course . '-kurs' : '',
            $assignment->original_group_name ?: ($source?->group_name ?? ''),
            $target?->group_name ?? '',
            $viaApplication ? 'Ariza tasdig\'i orqali' : 'Qo\'lda taqsimlangan',
            $viaApplication ? (string) $application->reason : '',
            $viaApplication ? optional($application->reviewed_at)->format('d.m.Y H:i') : '',
            optional($assignment->created_at)->format('d.m.Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getRowDimension(1)->setRowHeight(28);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F2937']],
                'alignment' => ['vertical' => 'center', 'horizontal' => 'left'],
            ],
        ];
    }

    /**
     * Familya / Ism / Otasining ismi ustunlari. Students jadvalida alohida
     * ustunlar bo'lsa o'shalar, aks holda to'liq ismni bo'lakka ajratamiz.
     */
    private function splitName(string $fullName, ?Student $student): array
    {
        $lastName = trim((string) ($student?->second_name ?? ''));
        $firstName = trim((string) ($student?->first_name ?? ''));
        $middleName = trim((string) ($student?->third_name ?? ''));

        if ($lastName !== '' || $firstName !== '') {
            return [$lastName, $firstName, $middleName];
        }

        $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
            trim(implode(' ', array_slice($parts, 2))),
        ];
    }

    private function loadApprovedApplications(): Collection
    {
        if (!Schema::hasTable('student_group_change_applications')) {
            return collect();
        }

        return StudentGroupChangeApplication::query()
            ->where('status', 'approved')
            ->orderBy('reviewed_at')
            ->get()
            ->keyBy('student_id');
    }

    private function loadStudents(): Collection
    {
        $ids = StudentDistributionAssignment::query()->pluck('student_id')->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Student::query()->whereIn('id', $ids)->get()->keyBy('id');
    }
}
