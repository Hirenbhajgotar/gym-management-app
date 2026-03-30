<?php

namespace App\Biometrics\DTOs;

readonly class GuardResult
{
    public function __construct(
        public bool $allowed,
        public ?string $reason
    ) {}

    public static function allow(): self
    {
        return new self(true, null);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}
