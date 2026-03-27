<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BiometricConsentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gym_member_id' => ['required', 'integer', 'exists:gym_members,id'],
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'consented' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'gym_member_id.required' => 'Gym member ID is required',
            'gym_member_id.exists' => 'Gym member not found',
            'gym_id.required' => 'Gym ID is required',
            'gym_id.exists' => 'Gym not found',
            'consented.required' => 'You must check the consent checkbox',
            'consented.accepted' => 'You must accept the consent to proceed',
        ];
    }
}
