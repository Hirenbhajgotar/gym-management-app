<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BiometricTypeEnum;
use App\Models\BiometricDevice;
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
        // Scope the existence check to the device's gym, mirroring enroll-sync,
        // so validation and the controller's gym-scoped findOrFail() share one
        // boundary. The rule still fails for a member in another gym, but it no
        // longer acts as a global "does this id exist anywhere" oracle.
        $device = $this->attributes->get('device');
        $gymId = $device instanceof BiometricDevice ? $device->gym_id : null;

        return [
            'gym_member_id' => [
                'required',
                'integer',
                $gymId !== null
                    ? Rule::exists('gym_members', 'id')->where('gym_id', $gymId)
                    : 'exists:gym_members,id',
            ],
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

    /**
     * `validated('biometric_type')` is read by the controller as a
     * `BiometricTypeEnum` (`$biometricType->value`). `FormRequest::validated()`
     * returns the validator's raw validated data — the `Rule::enum()` check only
     * asserts membership, it does not transform the value — so without this the
     * controller receives a plain string and fails reading `->value` on it.
     *
     * This delegates to the parent for the actual key dispatch (including the
     * documented `array|int|string|null $key` and dotted keys) and only coerces
     * the one value it exists to coerce. A value the enum cannot express is
     * passed through untouched rather than silently substituted, so the
     * controller's own handling of it still runs.
     *
     * Both a scalar key ('biometric_type') and an array key
     * (['biometric_type']) go through the parent's `data_get()`, which treats an
     * array key as a PATH, not a projection — so both resolve to the RAW scalar
     * at that path, not to a sub-array. Both therefore need the same coercion,
     * and `keySelectsBiometricType()` is what recognises them.
     *
     * @param  array<string>|int|string|null  $key
     * @param  mixed  $default
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $this->keySelectsBiometricType($key)
                ? $this->coerceBiometricType($validated)
                : $validated;
        }

        if (is_array($validated) && array_key_exists('biometric_type', $validated)) {
            $validated['biometric_type'] = $this->coerceBiometricType($validated['biometric_type']);
        }

        return $validated;
    }

    /**
     * Whether the requested key path terminates at the biometric type.
     *
     * @param  array<string>|int|string  $key
     */
    private function keySelectsBiometricType(array|int|string $key): bool
    {
        return in_array('biometric_type', is_array($key) ? $key : [$key], true);
    }

    private function coerceBiometricType(mixed $value): mixed
    {
        if ($value instanceof BiometricTypeEnum) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        return BiometricTypeEnum::tryFrom($value) ?? $value;
    }
}
