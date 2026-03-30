<?php

namespace App\Biometrics\DTOs;

readonly class VerificationResult
{
    public function __construct(
        public bool $matched,
        public float $confidence,
        public float $livenessScore,
        public string $providerRef,
        public array $raw = []
    ) {}
}
