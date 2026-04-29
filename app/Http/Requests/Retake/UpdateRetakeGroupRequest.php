<?php

namespace App\Http\Requests\Retake;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRetakeGroupRequest extends FormRequest
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
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'max_students' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'teacher_id.exists' => 'Bunday o\'qituvchi topilmadi.',
            'end_date.after_or_equal' => 'Tugash sanasi boshlanish sanasidan keyin bo\'lishi shart.',
        ];
    }
}
