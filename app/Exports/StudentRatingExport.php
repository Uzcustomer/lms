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
        } elseif (!$this->level && !$this->groupName) {
            // Hech qanday semester/level/group tanlanmagan → joriy semestrlar
            // bo'yicha filter qilamiz, aks holda million qator skanlash 504 beradi.
            $currentCodes = Semester::where('current', true)->distinct()->pluck('code')->all();
            if (!empty($currentCodes)) {
                $query->whereIn('semester_code', $currentCodes);
            }
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

        $levelLabels = [
            '11' => '1-kurs', '12' => '2-kurs', '13' => '3-kurs',
            '14' => '4-kurs', '15' => '5-kurs', '16' => '6-kurs',
        ];

        $semesterCodesOfRatings = $ratings->pluck('semester_code')->filter()->unique()->all();
        $semesterNames = Semester::whereIn('code', $semesterCodesOfRatings)
            ->get(['code', 'name'])
            ->unique('code')
            ->pluck('name', 'code')
            ->all();

        // ─── BULK-LOAD: barcha kerakli ma'lumotlar bir necha query bilan ───
        $hemisIds = $ratings->pluck('student_hemis_id')->filter()->unique()->values()->all();
        $excludeTypes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        // 1) Students (hemis_id => Student)
        $studentsMap = Student::whereIn('hemis_id', $hemisIds)
            ->get()
            ->keyBy('hemis_id');

        // 2) JN baholari (lesson_date bilan) — bulk
        $jnGradesByHemis = StudentGrade::whereIn('student_hemis_id', $hemisIds)
            ->whereIn('semester_code', $semesterCodesOfRatings)
            ->whereNotIn('training_type_code', $excludeTypes)
            ->whereNotNull('lesson_date')
            ->select([
                'student_hemis_id', 'semester_code', 'education_year_code',
                'subject_id', 'subject_name', 'lesson_date', 'grade',
                'retake_grade', 'status', 'reason',
            ])
            ->get()
            ->groupBy('student_hemis_id');

        // 3) MT/OSKI/Test baholari — bulk
        $otherGradesByHemis = StudentGrade::whereIn('student_hemis_id', $hemisIds)
            ->whereIn('semester_code', $semesterCodesOfRatings)
            ->whereIn('training_type_code', [99, 101, 102])
            ->select([
                'student_hemis_id', 'semester_code', 'education_year_code',
                'subject_id', 'subject_name', 'grade', 'retake_grade', 'training_type_code',
            ])
            ->get()
            ->groupBy('student_hemis_id');

        // 4) YN consent — bulk
        $ynConsentsByHemis = YnConsent::whereIn('student_hemis_id', $hemisIds)
            ->whereIn('semester_code', $semesterCodesOfRatings)
            ->select(['student_hemis_id', 'subject_id', 'status'])
            ->get()
            ->groupBy('student_hemis_id');

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

            $student = $studentsMap->get($rating->student_hemis_id);
            if (!$student) {
                $rows[] = [
                    $rank, $rating->full_name, $deptName, $specName, $levelName,
                    $groupName, $semLabel,
                    '-', '-', $rating->jn_average, '-', '-', '-', '-',
                ];
                $currentRow++;
                continue;
            }

            $subjects = $this->buildSubjects(
                $student,
                $jnGradesByHemis->get($student->hemis_id, collect()),
                $otherGradesByHemis->get($student->hemis_id, collect()),
                $ynConsentsByHemis->get($student->hemis_id, collect()),
            );

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

    /**
     * Talaba uchun fanlar to'plamini tuzish — pre-loaded collectionlar asosida
     * (bulk-load yondashuvi N+1 muammosini olib tashlaydi).
     */
    private function buildSubjects($student, $jnGrades, $otherGrades, $ynConsents): array
    {
        $semFilter = fn($g) => (string) $g->semester_code === (string) $student->semester_code;

        $grades = $jnGrades->filter($semFilter);
        $otherGradesFiltered = $otherGrades->filter($semFilter);

        $mtBySubject = [];
        $oskiBySubject = [];
        $testBySubject = [];
        foreach ($otherGradesFiltered as $g) {
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
        $allSubjectIds = $bySubject->keys()
            ->merge(array_keys($mtBySubject))
            ->merge(array_keys($oskiBySubject))
            ->merge(array_keys($testBySubject))
            ->unique();

        $consentBySubject = $ynConsents->keyBy('subject_id');

        $subjects = [];
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
                $otherForSubject = $otherGradesFiltered->where('subject_id', $subjectId)->first();
                $subjectName = $otherForSubject->subject_name ?? $subjectId;
            }

            $consent = $consentBySubject->get($subjectId);
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
