<?php

declare(strict_types=1);

namespace App\Biometrics\DTOs;

use Carbon\Carbon;

readonly class DoorControlResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $success,
        public string $status,
        // unlocked | locked | failed | skipped | not_applicable
        public ?string $failureReason,
        public ?Carbon $unlockedAt,
        public array $raw = []
    ) {}

    public static function unlocked(): self
    {
        return new self(
            success: true,
            status: 'unlocked',
            failureReason: null,
            unlockedAt: now()
        );
    }

    public static function locked(string $reason): self
    {
        return new self(
            success: false,
            status: 'locked',
            failureReason: $reason,
            unlockedAt: null
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failed(string $reason, array $raw = []): self
    {
        return new self(
            success: false,
            status: 'failed',
            failureReason: $reason,
            unlockedAt: null,
            raw: $raw
        );
    }

    public static function skipped(): self
    {
        // Device has door control type but relay/wiegand —
        // FluxFitness does not call an API, device handles it via webhook response
        return new self(
            success: true,
            status: 'skipped',
            failureReason: null,
            unlockedAt: now()
        );
    }

    public static function notApplicable(): self
    {
        // attendance_only device — door control not relevant
        return new self(
            success: true,
            status: 'not_applicable',
            failureReason: null,
            unlockedAt: null
        );
    }
}
