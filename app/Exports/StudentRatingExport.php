<?php

namespace App\Exports;

use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentRating;
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
    private array $studentHeaderRows = [];
    private array $averageRows = [];
    private int $totalRows = 0;

    public function __construct(?string $department = null, ?string $specialty = null, ?string $level = null, ?string $search = null)
    {
        $this->department = $department;
        $this->specialty = $specialty;
        $this->level = $level;
        $this->search = $search;
    }

    public function headings(): array
    {
        return ['#', 'F.I.O', 'Guruh', 'Fan nomi', 'Kunlar', 'JN bali'];
    }

    public function array(): array
    {
        $query = StudentRating::query()->orderByDesc('jn_average');

        if ($this->department) {
            $query->where('department_code', $this->department);
        }
        if ($this->specialty) {
            $query->where('specialty_code', $this->specialty);
        }
        if ($this->level) {
            $query->where('level_code', $this->level);
        }
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('full_name', 'like', '%' . $this->search . '%')
                  ->orWhere('group_name', 'like', '%' . $this->search . '%');
            });
        }

        $ratings = $query->get();
        $excludeTypes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
        $rows = [];
        $rank = 0;
        $currentRow = 2; // row 1 is heading

        foreach ($ratings as $rating) {
            $rank++;
            $student = Student::where('hemis_id', $rating->student_hemis_id)->first();
            if (!$student) {
                $rows[] = [$rank, $rating->full_name, $rating->group_name, '-', '-', $rating->jn_average];
                $this->studentHeaderRows[] = $currentRow;
                $this->averageRows[] = $currentRow;
                $currentRow++;
                continue;
            }

            $subjects = $this->getSubjects($student, $excludeTypes);

            if (empty($subjects)) {
                $rows[] = [$rank, $rating->full_name, $rating->group_name, '-', '-', $rating->jn_average];
                $this->studentHeaderRows[] = $currentRow;
                $this->averageRows[] = $currentRow;
                $currentRow++;
                continue;
            }

            // Student header row (first subject)
            $first = array_shift($subjects);
            $rows[] = [$rank, $rating->full_name, $rating->group_name, $first['name'], $first['days'], $first['average']];
            $this->studentHeaderRows[] = $currentRow;
            $currentRow++;

            // Remaining subjects
            foreach ($subjects as $s) {
                $rows[] = ['', '', '', $s['name'], $s['days'], $s['average']];
                $currentRow++;
            }

            // Average row
            $rows[] = ['', '', '', "O'rtacha", '', $rating->jn_average];
            $this->averageRows[] = $currentRow;
            $currentRow++;
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

        if ($grades->isEmpty()) return [];

        $bySubject = $grades->groupBy('subject_id');
        $subjects = [];

        foreach ($bySubject as $subjectId => $subjectGrades) {
            $subjectName = $subjectGrades->first()->subject_name ?? $subjectId;

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

            $subjects[] = [
                'name' => $subjectName,
                'days' => $daysCount,
                'average' => $avg,
            ];
        }

        usort($subjects, fn($a, $b) => $b['average'] <=> $a['average']);
        return $subjects;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];

        // Header row
        $styles[1] = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        // Student header rows — bold with light blue bg
        foreach ($this->studentHeaderRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
            ];
        }

        // Average rows — bold with light green bg
        foreach ($this->averageRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']],
            ];
        }

        // Borders for all data
        if ($this->totalRows > 0) {
            $range = 'A1:F' . ($this->totalRows + 1);
            $sheet->getStyle($range)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB'],
                    ],
                ],
            ]);
        }

        return $styles;
    }
}
