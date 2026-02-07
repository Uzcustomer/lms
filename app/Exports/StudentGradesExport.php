<?php

namespace App\Exports;


use App\Models\StudentGrade;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;

class StudentGradesExport implements FromCollection
{
    protected $students;
    protected $weeks;
    protected $dates;
    protected $viewType;
    protected $subject;

    public function __construct($students, $weeks, $dates, $viewType, $subject)
    {
        $this->students = $students;
        $this->weeks = $weeks;
        $this->dates = $dates;
        $this->viewType = $viewType;
        $this->subject = $subject;
    }

    public function collection()
    {
        $data = new Collection();

        $teacher = StudentGrade::where('subject_id', $this->subject->subject_id)
            ->whereNotNull('employee_name')
            ->first();

        $teacherName = $teacher ? $teacher->employee_name : 'Noma’lum o‘qituvchi';
        $data->push(['Fan:', $this->subject->subject_name]);
        $data->push(['O\'qituvchi:', $teacherName]);
        $data->push(['Semestr:', $this->subject->semester_name]);
        $data->push([]);

        $header = ['Talaba ID', 'F.I.Sh'];
        if ($this->viewType == 'week') {
            foreach ($this->weeks as $index => $week) {
                $header[] = 'Hafta ' . ($index + 1);
            }
        } else {
            foreach ($this->dates as $date) {
                $header[] = format_date($date);
            }
        }
        $data->push($header);

        // Data Rows
        foreach ($this->students as $student) {
            $row = [
                $student->student_id_number,
                $student->full_name
            ];
            if ($this->viewType == 'week') {
                foreach ($this->weeks as $week) {
                    $averageGrade = $student->getAverageGradeForWeek($this->subject->subject_id, $week->start_date, $week->end_date);
                    $row[] = $averageGrade ? number_format(round($averageGrade), 2) : '';
                }
            } else {
                foreach ($this->dates as $date) {
                    $grade = $student->getGradeForDate($this->subject->subject_id, $date);
                    $row[] = $grade !== null && $grade !== '' ? number_format(round($grade), 2) : '';
                }
            }
            $data->push($row);
        }

        return $data;
    }
}

