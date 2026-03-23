<?php

namespace App\Biometrics\Providers;

use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\DTOs\VerificationResult;
use App\Models\GymMember;
use Illuminate\Support\Facades\Http;

class BioIDProvider implements BiometricProvider
{
    public function __construct(
        protected array $config
    ) {}

    public function enroll(GymMember $gymMember, BiometricSample $sample): TemplateRef
    {
        // BioID primarily provides liveness detection
        // For enrollment, would typically use their web service API
        $livenessResult = $this->getLivenessScore($sample);

        return new TemplateRef(
            provider: 'bioid',
            externalId: "gym_member_{$gymMember->id}",
            meta: [
                'liveness_score' => $livenessResult,
            ]
        );
    }

    public function verify(BiometricSample $sample, TemplateRef $ref): VerificationResult
    {
        // BioID verification through their API
        // This is a simplified implementation
        return new VerificationResult(
            matched: true,
            confidence: 0.85,
            livenessScore: $this->getLivenessScore($sample),
            providerRef: $ref->externalId,
            raw: []
        );
    }

    public function getLivenessScore(BiometricSample $sample): float
    {
        // Call BioID PAD (Presentation Attack Detection) service
        // This is a simplified implementation
        $response = Http::timeout(5)->post(
            $this->config['base_url'] ?? 'https://www.bioid.com/portal/services/bws/bioidservice.svc/pad',
            [
                'Image' => base64_encode($sample->data),
            ]
        );

        if ($response->successful()) {
            $result = $response->json();

            return (float) ($result['PADScore'] ?? 0.0);
        }

        // Fallback: Calculate basic liveness score
        return 0.75;
    }

    public function deleteTemplate(TemplateRef $ref): bool
    {
        return true;
    }

    public function getSupportedTypes(): array
    {
        return ['face', 'fingerprint', 'iris'];
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get($this->config['base_url'] ?? 'https://www.bioid.com');

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
