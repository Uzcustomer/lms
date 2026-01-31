<?php

namespace App\Imports;

use App\Models\CurriculumSubject;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\Oski;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
class OskiImport implements ToCollection, WithHeadingRow, WithValidation
{
    public $error = [];

    public function collection(Collection $rows)
    {
        $test_ids = [];
        foreach ($rows as $row) {
            try {
                $row['date'] = \Carbon\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['date']))->format('Y-m-d');
            } catch (\Throwable $th) {
            }
            try {
                $row['test_date'] = \Carbon\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['test_date']))->format('Y-m-d');
            } catch (\Throwable $th) {
            }

            $exam_test = Oski::
                where('start_date', $row['date'])
                ->whereHas('subject', function ($query) use ($row) {
                    $query->where('subject_id', $row['subject_id']);
                })
                ->where('group_name', $row['group_name'])
                ->where('status', 0)
                ->first();
            if (empty($exam_test)) {
                $row['error'] = "Bu ma'lumotlarga oski topilmadi";
                $this->error[] = $row;

            } else {
                $group = Group::where('group_hemis_id', $exam_test->group_hemis_id)->first();
                $semester = Semester::where('code', $exam_test->semester_code)->where('curriculum_hemis_id', $group->curriculum_hemis_id)->first();
                $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                    ->where('semester_code', $semester->code)
                    ->where('subject_id', $exam_test->subject->subject_id)
                    ->first();
                $currentDate = now();

                $count = Schedule::where('subject_id', $subject->subject_id)
                    ->where('group_id', $group->group_hemis_id)
                    ->whereNotIn('training_type_code', config('app.training_type_code'))
                    ->where('lesson_date', '<=', $currentDate)
                    ->distinct('lesson_date')
                    ->count();
                $student = Student::selectRaw('
                    students.id,
                    students.hemis_id,
                    students.full_name as student_name,
                    students.student_id_number as student_id,
                   ROUND (
                        (SELECT sum(inner_table.average_grade)/ ' . $count . '
                        FROM (
                           SELECT lesson_date,AVG(COALESCE(
                                CASE 
                                    WHEN status = "retake" AND (reason = "absent" OR reason = "teacher_victim") 
                                    THEN retake_grade
                                    WHEN status = "retake" AND reason = "low_grade" 
                                    THEN retake_grade
                                    WHEN status = "pending" AND reason = "absent" 
                                    THEN grade
                                    ELSE grade
                                END, 0)) AS average_grade
                            FROM student_grades
                            WHERE student_grades.student_hemis_id = students.hemis_id
                            AND student_grades.subject_id = ' . $subject->subject_id . '
                            AND student_grades.training_type_code NOT IN (' . implode(',', config('app.training_type_code')) . ')
                            GROUP BY student_grades.lesson_date
                        ) AS inner_table)
                    ) as jn,
                    ROUND (
                       ( SELECT avg(
                            student_grades.grade
                        ) as average_grade
                        FROM student_grades
                        WHERE student_grades.student_hemis_id = students.hemis_id
                        AND student_grades.subject_id = ' . $subject->subject_id . '
                        AND student_grades.training_type_code =99
                        GROUP BY student_grades.student_hemis_id)
                    ) as mt,
                     students.hemis_id as hemis_id

                ')
                    ->where('students.student_id_number', $row['student_id'])
                    ->groupBy('students.id')
                    ->first();
                if (empty($student)) {
                    $row['error'] = "Talaba topilmadi";
                    $this->error[] = $row;
                } else {
                    $deadline = Deadline::where('level_code', $semester->level_code)->first();
                    $token = config('services.hemis.token');
                    $response = Http::withoutVerifying()->withToken($token)
                        ->get("https://student.ttatf.uz/rest/v1/data/attendance-list?limit=200&page=1&_group=" . $group->group_hemis_id . "&_subject=" . $subject->subject_id . "&_student=" . $student->hemis_id);
                    $qoldirgan = 0;
                    if ($response->successful()) {
                        $data = $response->json()['data'];
                        foreach ($data['items'] as $item) {
                            $qoldirgan += $item['absent_off'];
                        }
                    }
                    $student->qoldiq = round($qoldirgan * 100 / $subject->total_acload, 2);
                    if ($student->qoldiq > 25) {
                        $row['error'] = "Sababsiz dars ko'p dars qoldirgan";
                        $this->error[] = $row;
                    } elseif ($student->jn < $deadline->joriy) {
                        $row['error'] = "Joriy bahosi yetarli emas";
                        $this->error[] = $row;
                    } elseif ($student->mt < $deadline->mustaqil_talim) {
                        $row['error'] = "Mustaqil ta'lim bahosi yetarli emas";
                        $this->error[] = $row;
                    } else {


                        $grade = StudentGrade::where(
                            'student_hemis_id',
                            $student->hemis_id
                        )->where(
                                'test_id',
                                $exam_test->id
                            )->first();

                        if (empty($grade)) {
                            StudentGrade::create(
                                [
                                    'student_id' => $student->id,
                                    'student_hemis_id' => $student->hemis_id,
                                    'test_id' => $exam_test->id,
                                    'hemis_id' => 888888888,
                                    'semester_code' => $exam_test->semester->code,
                                    'semester_name' => $exam_test->semester->name,
                                    'subject_schedule_id' => 0,
                                    'subject_id' => $exam_test->subject->subject_id,
                                    'subject_name' => $exam_test->subject->subject_name,
                                    'subject_code' => $exam_test->subject->subject_code,
                                    'training_type_code' => 101,
                                    'training_type_name' => "OSKI",
                                    'employee_id' => "0",
                                    'employee_name' => auth()->user()->name,
                                    'lesson_pair_name' => "",
                                    'lesson_pair_code' => "",
                                    'lesson_pair_start_time' => "",
                                    'lesson_pair_end_time' => "",
                                    'lesson_date' => $exam_test->start_date,
                                    'created_at_api' => $exam_test->created_at,
                                    'reason' => 'teacher_victim',
                                    "retake_by" => $row['status'],
                                    'status' => 'recorded',
                                    'grade' => isset($row['grade']) ? round($row['grade']) : "",
                                    'deadline' => now(),
                                ]
                            );
                            $test_ids[] = $exam_test->id;
                        } else {
                            $row['error'] = "Talaba oldin baholangan";
                            $this->error[] = $row;
                        }
                    }
                }

            }
        }
        Oski::whereIn('id', $test_ids)->update([
            'status' => 1,
            "grade_teacher" => auth()->user()->name
        ]);
    }

    public function rules(): array
    {
        return [
            'test_date' => 'required',
            'group_name' => 'required',
            'student_id' => 'required',
            'grade' => 'required|numeric|min:0|max:100',
            'subject_id' => 'required',
            'date' => "required",
        ];
    }

    public function customValidationMessages()
    {
        return [
            'test_date.required' => "Test bo'lgan sana bo'lishi shart",
            'group_name.required' => "Gruh nomi bo'lishi shart",
            'student_id.required' => "Student ID bo'lishi shart",
            'subject_id.required' => "Fan ID bo'lishi shart",
            'date.required' => "Test kuni bo'lishi shart",
            'grade.numeric' => 'Test bali butun son bo\'lishi kerak.',
            'grade.min' => 'Test bali 0 dan kam bo\'lmasligi kerak.',
            'grade.max' => 'Test bali 100 dan oshmasligi kerak.',
        ];
    }
}