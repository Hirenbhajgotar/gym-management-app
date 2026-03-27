<?php

namespace App\Biometrics\Services;

use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;

class AntiSpoofGuard
{
    public function check(BiometricSample $sample, BiometricProvider $provider): bool
    {
        $livenessScore = $provider->getLivenessScore($sample);
        $threshold = config('biometrics.liveness_threshold', 0.75);

        return $livenessScore >= $threshold;
    }

    public function getLivenessScore(BiometricSample $sample, BiometricProvider $provider): float
    {
        return $provider->getLivenessScore($sample);
    }
}
