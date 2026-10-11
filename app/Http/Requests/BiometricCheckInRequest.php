<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BiometricTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BiometricCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gym_member_id' => ['nullable'],
            'member_id' => ['nullable'],
            'user_id' => ['nullable'],
            'pin' => ['nullable'],
            'template_ref' => ['nullable'],
            'biometric_type' => [
                'nullable',
                Rule::enum(BiometricTypeEnum::class),
            ],
            'data' => ['required_without_all:gym_member_id,member_id,user_id,pin,template_ref', 'nullable', 'string'],
            'captured_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'biometric_type.in' => 'Invalid biometric type. Must be ' . implode(', ', array_column(BiometricTypeEnum::cases(), 'value')),
            'data.required_without_all' => 'Biometric data or member identifier is required',
            'captured_at.date' => 'Invalid capture timestamp format',
        ];
    }
}
