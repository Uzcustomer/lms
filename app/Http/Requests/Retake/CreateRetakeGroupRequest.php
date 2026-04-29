<?php

namespace App\Http\Requests\Retake;

use Illuminate\Foundation\Http\FormRequest;

class CreateRetakeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! method_exists($user, 'hasRole')) {
            return false;
        }
        return $user->hasRole(['oquv_bolimi', 'oquv_bolimi_boshligi', 'admin', 'superadmin']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'subject_name' => ['required', 'string', 'max:255'],
            'semester_id' => ['required', 'integer', 'min:1'],
            'semester_name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'max_students' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'application_ids' => ['required', 'array', 'min:1'],
            'application_ids.*' => ['integer', 'exists:retake_applications,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'application_ids.required' => 'Eng kamida 1 ta talabani guruhga biriktirish kerak.',
            'teacher_id.exists' => 'Bunday o\'qituvchi topilmadi.',
            'end_date.after_or_equal' => 'Tugash sanasi boshlanish sanasidan keyin bo\'lishi shart.',
        ];
    }
}
