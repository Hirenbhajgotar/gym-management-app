<?php

declare(strict_types=1);

namespace App\Biometrics\DTOs;

readonly class VerificationResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $matched,
        public float $confidence,
        public float $livenessScore,
        public string $providerRef,
        public array $raw = []
    ) {}
}
