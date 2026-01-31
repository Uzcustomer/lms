<?php

namespace App\Imports;

use App\Models\StudentGrade;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StudentGradeUpdateViaExcel implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        $grade = StudentGrade::where('id', $row['grade_id'])->first();

        if ($grade && $grade->status === 'pending' && $grade->deadline < Carbon::now()) {
            $grade->update([
                'retake_grade' => $row['retake_grade'],
                'status' => 'retake',
                'retake_by' => $row['examiner_name'] ?? null,
                'retake_graded_at' =>  now(),
//                'retake_graded_at' => isset($row['retake_time']) ? Carbon::parse($row['retake_time']) : now(),
            ]);
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'grade_id' => 'required',
            'retake_grade' => 'required|integer|min:0|max:100',
            'examiner_name' => 'required|string',
//            'retake_time' => 'date_format:Y-m-d|nullable|sometimes',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'grade_id.required' => 'Grade ID maydoni to\'ldirilishi shart.',
            'retake_grade.required' => 'Qayta topshirish bali maydoni to\'ldirilishi shart.',
            'retake_grade.integer' => 'Qayta topshirish bali butun son bo\'lishi kerak.',
            'retake_grade.min' => 'Qayta topshirish bali 0 dan kam bo\'lmasligi kerak.',
            'retake_grade.max' => 'Qayta topshirish bali 100 dan oshmasligi kerak.',
            'examiner_name.required' => 'Imtihon oluvchi nomi maydoni to\'ldirilishi shart.',
            'retake_time.date' => 'Qayta topshirish vaqti to\'g\'ri sana formatida bo\'lishi kerak.',
        ];
    }
}
