<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BiometricCheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gym_member_id' => ['required', 'integer', 'exists:gym_members,id'],
            'captured_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'gym_member_id.required' => 'Gym member ID is required',
            'gym_member_id.integer' => 'Invalid gym member ID format',
            'gym_member_id.exists' => 'Gym member not found',
        ];
    }
}
