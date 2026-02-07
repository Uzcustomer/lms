<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Models\StudentGrade;
use App\Models\Semester;
use Carbon\Carbon;

class StudentGradesExportAdmin implements FromQuery, WithHeadings, ShouldAutoSize, WithMapping
{
    use Exportable;

    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = StudentGrade::query()
            ->join('students', 'student_grades.student_hemis_id', '=', 'students.hemis_id')
            ->leftJoin('teachers', 'student_grades.employee_id', '=', 'teachers.hemis_id')
            ->join('groups', 'students.group_id', '=', 'groups.group_hemis_id')
            ->join('departments', 'groups.department_hemis_id', '=', 'departments.department_hemis_id')
            ->join('curriculum_subjects', 'student_grades.subject_id', '=', 'curriculum_subjects.subject_id');

        if (isset($this->filters['department'])) {
            $query->where('departments.id', $this->filters['department']);
        }

        if (isset($this->filters['level_code'])) {
            $query->where('students.level_code', $this->filters['level_code']);
        }
        if (isset($this->filters['group'])) {
            $query->where('groups.id', $this->filters['group']);
        }

        if (isset($this->filters['semester'])) {
            $semester = Semester::findOrFail($this->filters['semester']);
            $query->where('student_grades.semester_code', $semester->code);
        }

        if (isset($this->filters['subject'])) {
            $query->where('curriculum_subjects.id', $this->filters['subject']);
        }

        return $query->select(
            'student_grades.id',
            'students.student_id_number',
            'students.full_name',
            'students.level_name',
            'departments.name as faculty',
            'groups.name as group_name',
            'students.education_year_name',
            'student_grades.semester_name',
            'students.specialty_name',
            'student_grades.lesson_date',
            'curriculum_subjects.subject_name',
            'student_grades.training_type_name',
            'student_grades.lesson_pair_name',
            DB::raw('COALESCE(teachers.full_name, student_grades.employee_name) as teacher_name'),
            'student_grades.grade',
            'student_grades.status',
            'student_grades.retake_grade',
            'student_grades.retake_graded_at',
            'student_grades.reason'
        )
//            ->where('student_grades.training_type_code', '<>', 11)
            ->orderBy('students.student_id_number')
            ->orderBy('student_grades.lesson_date')
            ->orderBy('student_grades.lesson_pair_name')
            ->distinct();
    }

    public function map($row): array
    {
        $grade = $row->status === 'retake' ? $row->retake_grade : $row->grade;

        return [
            $row->student_id_number,
            $row->full_name,
            $row->level_name,
            $row->faculty,
            $row->group_name,
            $row->education_year_name,
            $row->semester_name,
            $row->specialty_name,
            format_date($row->lesson_date),
            $row->subject_name,
            $row->training_type_name,
            $row->lesson_pair_name,
            $row->teacher_name,
            $grade,
            $this->translateStatus($row->status),
            format_date($row->retake_graded_at),
            $this->translateReason($row->reason),
        ];
    }

    public function headings(): array
    {
        return [
            'Talaba ID',
            'To\'liq ismi',
            'Kurs',
            'Fakultet',
            'Guruh',
            'O\'quv yili',
            'Semestr',
            'Mutaxassislik',
            'Dars sanasi',
            'Fan',
            'Mashg\'ulot turi',
            'Juftlik',
            'O\'qituvchi',
            'Baho',
            'Holat',
            'Qayta topshirish sanasi',
            'Sabab'
        ];
    }

    private function translateStatus($status)
    {
        return [
            'pending' => 'Kutilmoqda',
            'retake' => 'Qayta topshirilgan',
            'closed' => 'Yopilgan',
            'recorded' => 'Baholangan'
        ][$status] ?? $status;
    }

    private function translateReason($reason)
    {
        return [
            'absent' => 'Yo\'qlama',
            'low_grade' => 'Past baho'
        ][$reason] ?? $reason;
    }
}
