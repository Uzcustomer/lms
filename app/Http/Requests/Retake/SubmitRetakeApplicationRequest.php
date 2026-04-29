<?php

namespace App\Http\Requests\Retake;

use App\Models\Student;
use App\Services\Retake\RetakeApplicationService;
use Illuminate\Foundation\Http\FormRequest;

class SubmitRetakeApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Student;
    }

    public function rules(): array
    {
        return [
            'subjects' => ['required', 'array', 'min:1', 'max:' . RetakeApplicationService::MAX_SUBJECTS],
            'subjects.*.subject_id' => ['required', 'integer', 'min:1'],
            'subjects.*.semester_id' => ['required', 'integer', 'min:1'],

            'receipt' => [
                'required',
                'file',
                'max:5120', // KB — 5 MB
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],

            'student_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'subjects.required' => 'Eng kamida 1 ta fan tanlash kerak.',
            'subjects.min' => 'Eng kamida 1 ta fan tanlash kerak.',
            'subjects.max' => 'Maksimal :max ta fan tanlash mumkin.',
            'receipt.required' => 'Kvitansiya yuklash majburiy.',
            'receipt.max' => 'Kvitansiya hajmi 5 MB dan oshmasligi kerak.',
            'receipt.mimetypes' => 'Kvitansiya faqat PDF, JPG yoki PNG bo\'lishi mumkin.',
            'student_note.max' => 'Izoh 500 belgidan oshmasligi kerak.',
        ];
    }
}
