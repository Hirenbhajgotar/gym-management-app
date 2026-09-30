<?php

declare(strict_types=1);

namespace App\Biometrics\DTOs;

use App\Models\Attendances;

readonly class AdmsEventResult
{
    public function __construct(
        public bool $granted,
        public string $action,
        public ?string $denialReason,
        public ?Attendances $attendance,
        public ?DoorControlResult $doorResult,
    ) {}

    public static function checkedIn(Attendances $attendance, ?DoorControlResult $doorResult = null): self
    {
        return new self(
            granted: true,
            action: 'check_in',
            denialReason: null,
            attendance: $attendance,
            doorResult: $doorResult ?? DoorControlResult::notApplicable(),
        );
    }

    public static function checkedOut(Attendances $attendance, ?DoorControlResult $doorResult = null): self
    {
        return new self(
            granted: true,
            action: 'check_out',
            denialReason: null,
            attendance: $attendance,
            doorResult: $doorResult ?? DoorControlResult::notApplicable(),
        );
    }

    public static function ignored(string $reason = 'ignored'): self
    {
        return new self(
            granted: true,
            action: 'ignored',
            denialReason: $reason,
            attendance: null,
            doorResult: null,
        );
    }

    public static function denied(string $reason): self
    {
        return new self(
            granted: false,
            action: 'denied',
            denialReason: $reason,
            attendance: null,
            doorResult: null,
        );
    }
}
