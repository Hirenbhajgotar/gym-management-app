<?php

namespace App\Http\Requests;

use App\Enums\BiometricTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfflineSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1'],
            'events.*.offline_id' => ['required', 'string'],
            'events.*.biometric_type' => [
                'required',
                Rule::in(BiometricTypeEnum::class),
            ],
            'events.*.data' => ['required', 'string'],
            'events.*.captured_at' => ['required', 'date'],
            'events.*.confidence_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'events.*.liveness_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'events.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'events.required' => 'Events array is required',
            'events.array' => 'Events must be an array',
            'events.min' => 'At least one event is required',
            'events.*.offline_id.required' => 'Offline ID is required for each event',
            'events.*.biometric_type.required' => 'Biometric type is required for each event',
            'events.*.data.required' => 'Biometric data is required for each event',
            'events.*.captured_at.required' => 'Capture timestamp is required for each event',
            'events.*.biometric_type.in' => 'Invalid biometric type. Must be ' . implode(', ', BiometricTypeEnum::cases()),
        ];
    }
}
