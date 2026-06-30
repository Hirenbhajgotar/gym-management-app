<?php

declare(strict_types=1);

namespace App\Biometrics\Contracts;

use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\DTOs\VerificationResult;
use App\Models\GymMember;

interface BiometricProvider
{
    public function enroll(GymMember $gymMember, BiometricSample $sample): TemplateRef;

    public function verify(BiometricSample $sample, TemplateRef $ref): VerificationResult;

    public function getLivenessScore(BiometricSample $sample): float;

    public function deleteTemplate(TemplateRef $ref): bool;

    /** @return array<int, string> */
    public function getSupportedTypes(): array;

    public function isAvailable(): bool;
}
