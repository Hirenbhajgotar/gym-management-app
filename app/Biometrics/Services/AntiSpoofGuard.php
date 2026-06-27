<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use Illuminate\Support\Facades\Log;

class AntiSpoofGuard
{
    public function check(BiometricSample $sample, BiometricProvider $provider): bool
    {
        try {
            $livenessScore = $provider->getLivenessScore($sample);
        } catch (\Exception $e) {
            // Log the error but deny access as a safety measure when liveness check fails
            Log::warning('Biometric liveness check failed, denying access', [
                'error' => $e->getMessage(),
                'provider' => get_class($provider),
            ]);

            return false;
        }

        $threshold = config('biometrics.liveness_threshold', 0.75);

        return $livenessScore >= $threshold;
    }

    public function getLivenessScore(BiometricSample $sample, BiometricProvider $provider): float
    {
        try {
            return $provider->getLivenessScore($sample);
        } catch (\Exception $e) {
            // Log the error and return 0.0 as a safe fallback
            Log::warning('Biometric liveness score retrieval failed', [
                'error' => $e->getMessage(),
                'provider' => get_class($provider),
            ]);

            return 0.0;
        }
    }
}
