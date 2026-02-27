<?php

namespace App\Imports;

use App\Models\HemisQuizResult;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\CurriculumSubject;
use App\Models\Group;
use App\Models\Semester;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class QuizResultImport implements ToCollection, WithHeadingRow, WithValidation
{
    public array $errors = [];
    public int $successCount = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $attemptId = $row['attempt_id'] ?? null;
            $studentHemisId = $row['student_id'] ?? null;
            $grade = $row['grade'] ?? null;
            $fanId = $row['fan_id'] ?? null;

            // hemis_quiz_results dan attempt topish
            $quizResult = null;
            if ($attemptId) {
                $quizResult = HemisQuizResult::where('attempt_id', $attemptId)->first();
            }

            // Talabani topish
            $student = Student::where('hemis_id', $studentHemisId)->first();
            if (!$student) {
                $this->errors[] = [
                    'attempt_id' => $attemptId,
                    'student_id' => $studentHemisId,
                    'student_name' => $row['student_name'] ?? '',
                    'fan_name' => $row['fan_name'] ?? '',
                    'grade' => $grade,
                    'error' => "Talaba topilmadi (student_id: $studentHemisId)",
                ];
                continue;
            }

            // Fanni topish - curriculum_subjects dan
            $group = Group::where('group_hemis_id', $student->group_id)->first();
            if (!$group) {
                $this->errors[] = [
                    'attempt_id' => $attemptId,
                    'student_id' => $studentHemisId,
                    'student_name' => $row['student_name'] ?? '',
                    'fan_name' => $row['fan_name'] ?? '',
                    'grade' => $grade,
                    'error' => "Talaba guruhida guruh topilmadi",
                ];
                continue;
            }

            $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
                ->where('code', $student->semester_code)
                ->first();

            $subject = null;
            if ($fanId) {
                $subject = CurriculumSubject::where('subject_id', $fanId)->first();
            }

            if (!$subject) {
                $this->errors[] = [
                    'attempt_id' => $attemptId,
                    'student_id' => $studentHemisId,
                    'student_name' => $row['student_name'] ?? '',
                    'fan_name' => $row['fan_name'] ?? '',
                    'grade' => $grade,
                    'error' => "Fan topilmadi (fan_id: $fanId)",
                ];
                continue;
            }

            // Avval bu natija student_grades ga yozilganmi tekshirish
            if ($quizResult) {
                $existing = StudentGrade::where('quiz_result_id', $quizResult->id)->first();
                if ($existing) {
                    $this->errors[] = [
                        'attempt_id' => $attemptId,
                        'student_id' => $studentHemisId,
                        'student_name' => $row['student_name'] ?? '',
                        'fan_name' => $row['fan_name'] ?? '',
                        'grade' => $grade,
                        'error' => "Bu natija avval yuklangan",
                    ];
                    continue;
                }
            }

            // Quiz type dan training_type_code va name aniqlash
            $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
            $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
            $quizType = $quizResult?->quiz_type;

            if (in_array($quizType, $oskiTypes)) {
                $trainingTypeCode = 101;
                $trainingTypeName = 'Oski';
            } elseif (in_array($quizType, $testTypes)) {
                $trainingTypeCode = 102;
                $trainingTypeName = 'Yakuniy test';
            } else {
                $trainingTypeCode = 103;
                $trainingTypeName = 'Quiz test';
            }

            // Student grade yaratish
            StudentGrade::create([
                'student_id' => $student->id,
                'student_hemis_id' => $student->hemis_id,
                'hemis_id' => 999999999,
                'semester_code' => $semester->code ?? $student->semester_code,
                'semester_name' => $semester->name ?? $student->semester_name,
                'subject_schedule_id' => 0,
                'subject_id' => $subject->subject_id,
                'subject_name' => $subject->subject_name,
                'subject_code' => $subject->subject_code ?? '',
                'training_type_code' => $trainingTypeCode,
                'training_type_name' => $trainingTypeName,
                'employee_id' => 0,
                'employee_name' => auth()->user()->name ?? 'Test markazi',
                'lesson_pair_name' => '',
                'lesson_pair_code' => '',
                'lesson_pair_start_time' => '',
                'lesson_pair_end_time' => '',
                'lesson_date' => $quizResult?->date_finish ?? now(),
                'created_at_api' => $quizResult?->created_at ?? now(),
                'reason' => 'quiz_result',
                'status' => 'recorded',
                'grade' => round($grade),
                'deadline' => now(),
                'quiz_result_id' => $quizResult?->id,
                'is_final' => true,
            ]);

            $this->successCount++;
        }
    }

    public function rules(): array
    {
        return [
            'student_id' => 'required',
            'fan_id' => 'required',
            'grade' => 'required|numeric|min:0|max:100',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'student_id.required' => "Student ID bo'lishi shart",
            'fan_id.required' => "Fan ID bo'lishi shart",
            'grade.required' => "Baho bo'lishi shart",
            'grade.numeric' => "Baho raqam bo'lishi kerak",
            'grade.min' => "Baho 0 dan kam bo'lmasligi kerak",
            'grade.max' => "Baho 100 dan oshmasligi kerak",
        ];
    }
}
