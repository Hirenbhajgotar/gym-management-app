<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BiometricTypeEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Throwable;

class OfflineSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.offline_id' => ['required', 'string', 'max:100'],
            'events.*.gym_member_id' => ['nullable'],
            'events.*.member_id' => ['nullable'],
            'events.*.user_id' => ['nullable'],
            'events.*.template_ref' => ['nullable', 'string', 'max:100'],
            'events.*.biometric_type' => [
                'nullable',
                Rule::enum(BiometricTypeEnum::class),
            ],
            'events.*.data' => ['nullable', 'string', 'max:65535'],
            'events.*.captured_at' => ['required', 'date'],
            'events.*.direction' => ['nullable', 'string', Rule::in(['entry', 'exit', 'check_in', 'check_out', 'in', 'out'])],
            'events.*.confidence_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'events.*.liveness_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'events.*.metadata' => ['nullable', 'array', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $events = $this->input('events');
            if (! is_array($events)) {
                return;
            }

            $maxFuture = now()->addMinutes(10);
            $maxPast = now()->subDays(30);

            foreach ($events as $index => $event) {
                if (! is_array($event)) {
                    continue;
                }

                $capturedAtRaw = $event['captured_at'] ?? null;
                if (is_string($capturedAtRaw) && filled($capturedAtRaw)) {
                    try {
                        $capturedAt = Date::parse($capturedAtRaw);
                        if ($capturedAt->isAfter($maxFuture)) {
                            $validator->errors()->add(
                                "events.{$index}.captured_at",
                                'Event timestamp cannot be in the future (beyond clock skew tolerance)'
                            );
                        } elseif ($capturedAt->isBefore($maxPast)) {
                            $validator->errors()->add(
                                "events.{$index}.captured_at",
                                'Event timestamp is older than the 30-day retention window'
                            );
                        }
                    } catch (Throwable) {
                        $validator->errors()->add(
                            "events.{$index}.captured_at",
                            'Invalid event timestamp format'
                        );
                    }
                }

                $hasData = filled($event['data'] ?? null);
                $hasIdentifier = filled($event['gym_member_id'] ?? null)
                    || filled($event['member_id'] ?? null)
                    || filled($event['user_id'] ?? null)
                    || filled($event['template_ref'] ?? null);

                if (! $hasData && ! $hasIdentifier) {
                    $validator->errors()->add(
                        "events.{$index}.data",
                        'Biometric data or member identifier is required for each event'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'events.required' => 'Events array is required',
            'events.array' => 'Events must be an array',
            'events.min' => 'At least one event is required',
            'events.*.offline_id.required' => 'Offline ID is required for each event',
            'events.*.captured_at.required' => 'Capture timestamp is required for each event',
            'events.*.biometric_type.in' => 'Invalid biometric type. Must be ' . implode(', ', array_column(BiometricTypeEnum::cases(), 'value')),
        ];
    }
}
