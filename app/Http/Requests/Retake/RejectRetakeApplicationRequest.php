<?php

namespace App\Http\Requests\Retake;

use App\Services\Retake\RetakeApprovalService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dekan, registrator va o'quv bo'limi tomonidan ariza rad etish uchun
 * umumiy Request. Sabab majburiy va aniq belgilar oralig'ida.
 */
class RejectRetakeApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => [
                'required',
                'string',
                'min:' . RetakeApprovalService::REJECTION_REASON_MIN,
                'max:' . RetakeApprovalService::REJECTION_REASON_MAX,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Rad etish sababi majburiy.',
            'rejection_reason.min' => 'Sabab eng kamida :min belgi bo\'lishi kerak.',
            'rejection_reason.max' => 'Sabab :max belgidan oshmasligi kerak.',
        ];
    }
}
