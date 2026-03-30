<?php

namespace App\Biometrics\DTOs;

use App\Models\Attendances;

readonly class CheckInResult
{
    public function __construct(
        public bool $granted,
        public string $result,
        public ?string $denialReason,
        public ?Attendances $attendance,
        public ?DoorControlResult $doorResult = null
    ) {}

    public static function granted(Attendances $attendance, ?DoorControlResult $doorResult = null): self
    {
        return new self(true, 'granted', null, $attendance, $doorResult);
    }

    public static function grantedNoDoor(Attendances $attendance): self
    {
        // attendance_only device
        return new self(true, 'granted', null, $attendance, DoorControlResult::notApplicable());
    }

    public static function denied(string $reason): self
    {
        return new self(false, 'denied', $reason, null, null);
    }

    public static function suspicious(?string $reason, ?Attendances $attendance): self
    {
        return new self(true, 'suspicious', $reason, $attendance, null);
    }
}
