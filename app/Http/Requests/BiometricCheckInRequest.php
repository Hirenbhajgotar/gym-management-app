<?php

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
            'biometric_type' => [
                'required',
                Rule::enum(BiometricTypeEnum::class),
            ],
            'data' => ['required', 'string'],
            'captured_at' => ['required', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'biometric_type.required' => 'Biometric type is required',
            'biometric_type.in' => 'Invalid biometric type. Must be ' . implode(', ', BiometricTypeEnum::cases()),
            'data.required' => 'Biometric data is required',
            'captured_at.required' => 'Capture timestamp is required',
            'captured_at.date' => 'Invalid capture timestamp format',
        ];
    }
}
