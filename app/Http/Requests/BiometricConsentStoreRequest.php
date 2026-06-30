<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\GymMember;
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
            'gym_member_id' => [
                'required',
                'integer',
                'exists:gym_members,id',
                // Validate form value matches signed URL query parameter to prevent tampering
                function ($attribute, $value, $fail) {
                    $signedMemberId = $this->query('gym_member_id');
                    if ($signedMemberId !== null && (int) $value !== (int) $signedMemberId) {
                        $fail('The gym member ID does not match the signed URL.');
                    }
                },
            ],
            'gym_id' => [
                'required',
                'integer',
                'exists:gyms,id',
                // Validate form value matches signed URL query parameter to prevent tampering
                function ($attribute, $value, $fail) {
                    $signedGymId = $this->query('gym_id');
                    if ($signedGymId !== null && (int) $value !== (int) $signedGymId) {
                        $fail('The gym ID does not match the signed URL.');
                    }
                },
                // Also validate gym matches member
                function ($attribute, $value, $fail) {
                    $gymMemberId = $this->input('gym_member_id');
                    $member = GymMember::query()->find($gymMemberId);
                    if ($member && (int) $member->gym_id !== (int) $value) {
                        $fail('The gym does not match the member\'s gym.');
                    }
                },
            ],
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
