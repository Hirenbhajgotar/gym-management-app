<?php

namespace App\Biometrics\DTOs;

readonly class BiometricSample
{
    public function __construct(
        public string $type,
        public string $data,
        public array $metadata = []
    ) {}
}
