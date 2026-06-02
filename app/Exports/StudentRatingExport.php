<?php

namespace App\Exports;

use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentRating;
use App\Models\YnConsent;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StudentRatingExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    use Exportable;

    protected ?string $department;
    protected ?string $specialty;
    protected ?string $level;
    protected ?string $search;
    protected ?string $subjectId;
    protected ?string $groupName;
    protected ?string $semesterCode;
    private int $totalRows = 0;

    public function __construct(
        ?string $department = null,
        ?string $specialty = null,
        ?string $level = null,
        ?string $search = null,
        ?string $subjectId = null,
        ?string $groupName = null,
        ?string $semesterCode = null
    ) {
        $this->department = $department;
        $this->specialty = $specialty;
        $this->level = $level;
        $this->search = $search;
        $this->subjectId = $subjectId;
        $this->groupName = $groupName;
        $this->semesterCode = $semesterCode;
    }

    public function headings(): array
    {
        return [
            '#',
            'F.I.O',
            'Fakultet',
            'Yo\'nalish',
            'Kurs',
            'Guruh',
            'Semestr',
            'Fan nomi',
            'Kunlar',
            'JN bali',
            'MT bali',
            'OSKI',
            'Test',
            'YN',
        ];
    }

    public function array(): array
    {
        $query = StudentRating::query();

        if ($this->department) {
            $query->where('department_code', $this->department);
        }
        if ($this->specialty) {
            $query->where('specialty_code', $this->specialty);
        }
        if ($this->level) {
            $query->where('level_code', $this->level);
        }
        if ($this->semesterCode) {
            $query->where('semester_code', $this->semesterCode);
        }
        if ($this->groupName) {
            $query->where('group_name', $this->groupName);
        }
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('full_name', 'like', '%' . $this->search . '%')
                  ->orWhere('group_name', 'like', '%' . $this->search . '%');
            });
        }
        if ($this->subjectId) {
            $studentIdsWithSubject = StudentGrade::where('subject_id', $this->subjectId)
                ->distinct()->pluck('student_hemis_id');
            $query->whereIn('student_hemis_id', $studentIdsWithSubject);
        }

        $query->orderBy('group_name')->orderByDesc('jn_average');
        $ratings = $query->get();

        // Kurs kodi → matn (level_code mapping)
        $levelLabels = [
            '11' => '1-kurs', '12' => '2-kurs', '13' => '3-kurs',
            '14' => '4-kurs', '15' => '5-kurs', '16' => '6-kurs',
        ];

        // Semester kodi → nom xaritasi (12 → "1-semestr" kabi)
        $semesterCodes = $ratings->pluck('semester_code')->filter()->unique()->all();
        $semesterNames = Semester::whereIn('code', $semesterCodes)
            ->get(['code', 'name'])
            ->unique('code')
            ->pluck('name', 'code')
            ->all();

        $excludeTypes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
        $rows = [];
        $rank = 0;
        $currentRow = 2;

        foreach ($ratings as $rating) {
            $rank++;

            $deptName = $rating->department_name ?? '';
            $specName = $rating->specialty_name ?? '';
            $levelName = $levelLabels[(string) $rating->level_code] ?? ($rating->level_code ?? '');
            $groupName = $rating->group_name ?? '';
            $semCode = $rating->semester_code ?? '';
            $semLabel = $semesterNames[$semCode] ?? $semCode;

            $student = Student::where('hemis_id', $rating->student_hemis_id)->first();
            if (!$student) {
                $rows[] = [
                    $rank, $rating->full_name, $deptName, $specName, $levelName,
                    $groupName, $semLabel,
                    '-', '-', $rating->jn_average, '-', '-', '-', '-',
                ];
                $currentRow++;
                continue;
            }

            $subjects = $this->getSubjects($student, $excludeTypes);

            if ($this->subjectId) {
                $subjects = array_values(array_filter(
                    $subjects,
                    fn($s) => (string) $s['subject_id'] === (string) $this->subjectId
                ));
            }

            if (empty($subjects)) {
                $rows[] = [
                    $rank, $rating->full_name, $deptName, $specName, $levelName,
                    $groupName, $semLabel,
                    '-', '-', $rating->jn_average, '-', '-', '-', '-',
                ];
                $currentRow++;
                continue;
            }

            foreach ($subjects as $s) {
                $rows[] = [
                    $rank, $rating->full_name, $deptName, $specName, $levelName,
                    $groupName, $semLabel,
                    $s['name'], $s['days'], $s['average'],
                    $s['mt'], $s['oski'], $s['test'], $s['yn'],
                ];
                $currentRow++;
            }
        }

        $this->totalRows = $currentRow - 1;
        return $rows;
    }

    private function getSubjects(Student $student, array $excludeTypes): array
    {
        $grades = StudentGrade::where('student_hemis_id', $student->hemis_id)
            ->where('semester_code', $student->semester_code)
            ->when($student->education_year_code, fn($q) => $q->where(function ($q2) use ($student) {
                $q2->where('education_year_code', $student->education_year_code)
                    ->orWhereNull('education_year_code');
            }))
            ->whereNotIn('training_type_code', $excludeTypes)
            ->whereNotNull('lesson_date')
            ->get();

        $otherGrades = StudentGrade::where('student_hemis_id', $student->hemis_id)
            ->where('semester_code', $student->semester_code)
            ->when($student->education_year_code, fn($q) => $q->where(function ($q2) use ($student) {
                $q2->where('education_year_code', $student->education_year_code)
                    ->orWhereNull('education_year_code');
            }))
            ->whereIn('training_type_code', [99, 101, 102])
            ->get();

        $mtBySubject = [];
        $oskiBySubject = [];
        $testBySubject = [];
        foreach ($otherGrades as $g) {
            $val = $g->retake_grade ?? $g->grade;
            if ($val === null) continue;
            $code = (int) $g->training_type_code;
            if ($code === 99) {
                $mtBySubject[$g->subject_id][] = (float) $val;
            } elseif ($code === 101) {
                $cur = $oskiBySubject[$g->subject_id] ?? null;
                if ($cur === null || (float) $val > $cur) {
                    $oskiBySubject[$g->subject_id] = (float) $val;
                }
            } elseif ($code === 102) {
                $cur = $testBySubject[$g->subject_id] ?? null;
                if ($cur === null || (float) $val > $cur) {
                    $testBySubject[$g->subject_id] = (float) $val;
                }
            }
        }

        if ($grades->isEmpty() && empty($mtBySubject) && empty($oskiBySubject) && empty($testBySubject)) {
            return [];
        }

        $bySubject = $grades->groupBy('subject_id');
        $subjects = [];

        $allSubjectIds = $bySubject->keys()->merge(array_keys($mtBySubject))
            ->merge(array_keys($oskiBySubject))
            ->merge(array_keys($testBySubject))
            ->unique();

        $ynConsents = YnConsent::where('student_hemis_id', $student->hemis_id)
            ->where('semester_code', $student->semester_code)
            ->whereIn('subject_id', $allSubjectIds->all())
            ->get()
            ->keyBy('subject_id');

        foreach ($allSubjectIds as $subjectId) {
            $subjectGrades = $bySubject->get($subjectId, collect());
            $subjectName = $subjectGrades->first()->subject_name ?? null;

            $byDate = $subjectGrades->groupBy(fn($g) => substr($g->lesson_date, 0, 10));
            $totalDaily = 0;
            $daysCount = 0;

            foreach ($byDate as $dailyGrades) {
                $dayTotal = 0;
                $dayCount = 0;
                $absentCount = 0;

                foreach ($dailyGrades as $g) {
                    if ($g->status === 'retake') {
                        $dayTotal += $g->retake_grade ?? 0;
                    } elseif ($g->status === 'pending' && $g->reason === 'absent') {
                        $absentCount++;
                    } else {
                        $dayTotal += $g->grade ?? 0;
                    }
                    $dayCount++;
                }

                if ($dayCount === 0) continue;
                $totalDaily += ($absentCount === $dayCount) ? 0 : round($dayTotal / $dayCount);
                $daysCount++;
            }

            $avg = $daysCount > 0 ? round($totalDaily / $daysCount, 1) : 0;

            $mtValues = $mtBySubject[$subjectId] ?? [];
            $mt = count($mtValues) > 0 ? round(array_sum($mtValues) / count($mtValues), 1) : '';

            $oski = $oskiBySubject[$subjectId] ?? '';
            $test = $testBySubject[$subjectId] ?? '';

            if (!$subjectName) {
                $otherForSubject = $otherGrades->where('subject_id', $subjectId)->first();
                $subjectName = $otherForSubject->subject_name ?? $subjectId;
            }

            $consent = $ynConsents->get($subjectId);
            $yn = match ($consent?->status) {
                'approved' => 'Tayyor',
                'rejected' => 'Rad etilgan',
                default    => 'Kutilmoqda',
            };

            $subjects[] = [
                'subject_id' => (string) $subjectId,
                'name'       => $subjectName,
                'days'       => $daysCount,
                'average'    => $avg,
                'mt'         => $mt,
                'oski'       => $oski !== '' ? round((float) $oski, 1) : '',
                'test'       => $test !== '' ? round((float) $test, 1) : '',
                'yn'         => $yn,
            ];
        }

        usort($subjects, fn($a, $b) => $b['average'] <=> $a['average']);
        return $subjects;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];

        // Header row — moviy fon
        $styles[1] = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        // Borders for all data
        if ($this->totalRows > 0) {
            $range = 'A1:N' . ($this->totalRows + 1);
            $sheet->getStyle($range)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB'],
                    ],
                ],
            ]);

            // Excel auto-filter — har ustun bo'yicha filtrlash imkoni
            $sheet->setAutoFilter('A1:N' . ($this->totalRows + 1));
        }

        return $styles;
    }
}
