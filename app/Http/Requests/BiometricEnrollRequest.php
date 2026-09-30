<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BiometricTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BiometricEnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gym_member_id' => ['required', 'integer', 'exists:gym_members,id'],
            'biometric_type' => [
                'required',
                Rule::enum(BiometricTypeEnum::class),
            ],
            'data' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'gym_member_id.required' => 'Gym member ID is required',
            'gym_member_id.exists' => 'Gym member not found',
            'biometric_type.required' => 'Biometric type is required',
            'biometric_type.in' => 'Invalid biometric type. Must be ' . implode(', ', array_column(BiometricTypeEnum::cases(), 'value')),
            'data.required' => 'Biometric data is required',
        ];
    }
}
