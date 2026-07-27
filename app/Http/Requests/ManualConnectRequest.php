<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Gym;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ManualConnectRequest extends FormRequest
{
    protected ?Gym $resolvedGym = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->resolvedGym !== null && Auth::user()?->gyms()->where('gyms.id', $this->resolvedGym->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'waba_id' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9]+$/'],
            'manual_phone_number' => ['required', 'string', 'max:20', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'waba_phone_number_id' => ['required', 'string', 'max:100'],
            'meta_access_token' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->resolvedGym = Gym::query()->where('slug', $this->route('tenant'))->first();
    }

    public function getGym(): ?Gym
    {
        return $this->resolvedGym;
    }
}
