<?php

namespace App\Biometrics\DTOs;

use App\Models\Attendances;

readonly class CheckInResult
{
    public function __construct(
        public bool $granted,
        public string $result,
        public ?string $denialReason,
        public ?Attendances $attendance
    ) {}

    public static function granted(Attendances $attendance): self
    {
        return new self(true, 'granted', null, $attendance);
    }

    public static function denied(string $reason): self
    {
        return new self(false, 'denied', $reason, null);
    }

    public static function suspicious(?string $reason, ?Attendances $attendance): self
    {
        return new self(true, 'suspicious', $reason, $attendance);
    }
}
