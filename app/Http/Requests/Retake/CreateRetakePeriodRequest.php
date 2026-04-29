<?php

namespace App\Http\Requests\Retake;

use Illuminate\Foundation\Http\FormRequest;

class CreateRetakePeriodRequest extends FormRequest
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
            'specialty_id' => ['required', 'integer', 'min:1'],
            'course' => ['required', 'integer', 'min:1', 'max:6'],
            'semester_id' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'course.min' => 'Kurs 1 dan 6 gacha bo\'lishi kerak.',
            'course.max' => 'Kurs 1 dan 6 gacha bo\'lishi kerak.',
            'end_date.after_or_equal' => 'Tugash sanasi boshlanish sanasidan keyin bo\'lishi shart.',
        ];
    }
}
