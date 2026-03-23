<?php

namespace App\Biometrics\Providers;

use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\DTOs\VerificationResult;
use App\Models\GymMember;
use Illuminate\Support\Facades\Http;

class LocalModelProvider implements BiometricProvider
{
    protected string $baseUrl;

    protected string $apiKey;

    protected int $timeout;

    public function __construct(
        protected array $config
    ) {
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:8000', '/');
        $this->apiKey = $config['api_key'] ?? '';
        $this->timeout = (int) ($config['timeout'] ?? 5);
    }

    public function enroll(GymMember $gymMember, BiometricSample $sample): TemplateRef
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/api/enroll", [
                'image' => $sample->data,
                'user_id' => "gym_member_{$gymMember->id}",
                'type' => $sample->type,
            ]);

        $result = $response->json();

        return new TemplateRef(
            provider: 'on_premise',
            externalId: $result['template_id'] ?? "gym_member_{$gymMember->id}",
            meta: [
                'quality_score' => $result['quality'] ?? null,
            ]
        );
    }

    public function verify(BiometricSample $sample, TemplateRef $ref): VerificationResult
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/api/verify", [
                'image' => $sample->data,
                'template_id' => $ref->externalId,
            ]);

        $result = $response->json();

        return new VerificationResult(
            matched: $result['matched'] ?? false,
            confidence: (float) ($result['confidence'] ?? 0.0),
            livenessScore: (float) ($result['liveness'] ?? 0.0),
            providerRef: $ref->externalId,
            raw: $result
        );
    }

    public function getLivenessScore(BiometricSample $sample): float
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/api/liveness", [
                'image' => $sample->data,
            ]);

        $result = $response->json();

        return (float) ($result['liveness_score'] ?? 0.0);
    }

    public function deleteTemplate(TemplateRef $ref): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                ])
                ->delete("{$this->baseUrl}/api/template/{$ref->externalId}");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getSupportedTypes(): array
    {
        return ['face', 'fingerprint'];
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/api/health");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
