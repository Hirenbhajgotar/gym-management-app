<?php

namespace App\Biometrics\Services;

use App\Biometrics\DTOs\VerificationResult;

class FusionEngine
{
    /**
     * Merge multiple verification results using weighted average
     *
     * @param  VerificationResult[]  $verificationResults
     */
    public function merge(array $verificationResults): VerificationResult
    {
        if (empty($verificationResults)) {
            return new VerificationResult(
                matched: false,
                confidence: 0.0,
                livenessScore: 0.0,
                providerRef: '',
                raw: []
            );
        }

        if (count($verificationResults) === 1) {
            return $verificationResults[0];
        }

        $totalConfidence = 0.0;
        $totalLiveness = 0.0;
        $totalWeight = 0.0;
        $allMatched = true;
        $rawData = [];

        foreach ($verificationResults as $result) {
            $weight = $result->confidence + 0.001; // Avoid division by zero
            $totalConfidence += $result->confidence * $weight;
            $totalLiveness += $result->livenessScore * $weight;
            $totalWeight += $weight;
            $allMatched = $allMatched && $result->matched;
            $rawData = array_merge($rawData, $result->raw);
        }

        // Avoid division by zero (should not happen given +0.001, but be safe)
        if ($totalWeight === 0.0) {
            return new VerificationResult(
                matched: false,
                confidence: 0.0,
                livenessScore: 0.0,
                providerRef: '',
                raw: []
            );
        }

        return new VerificationResult(
            matched: $allMatched,
            confidence: $totalConfidence / $totalWeight,
            livenessScore: $totalLiveness / $totalWeight,
            providerRef: $verificationResults[0]->providerRef,
            raw: $rawData
        );
    }
}
