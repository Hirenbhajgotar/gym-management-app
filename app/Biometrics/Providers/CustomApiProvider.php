<?php

namespace App\Biometrics\Providers;

use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\DTOs\VerificationResult;
use App\Models\GymMember;
use Illuminate\Support\Facades\Http;

class CustomApiProvider implements BiometricProvider
{
    /**
     * @param  array<string, string>  $endpointMap  Keys: enroll, verify, delete, liveness
     */
    protected array $endpointMap;

    protected string $apiKey;

    protected int $timeout;

    public function __construct(
        protected array $config
    ) {
        $this->endpointMap = $config['endpoint_map'] ?? [];
        $this->apiKey = $config['api_key'] ?? '';
        $this->timeout = (int) ($config['timeout'] ?? 10);
    }

    public function enroll(GymMember $gymMember, BiometricSample $sample): TemplateRef
    {
        $endpoint = $this->endpointMap['enroll'] ?? null;

        if (! $endpoint) {
            throw new \Exception('Enroll endpoint not configured');
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders($this->getHeaders())
            ->post($endpoint, [
                'image' => $sample->data,
                'user_id' => "gym_member_{$gymMember->id}",
                'gym_id' => $gymMember->gym_id,
                'type' => $sample->type,
                'metadata' => $sample->metadata,
            ]);

        $result = $response->json();

        return new TemplateRef(
            provider: 'custom',
            externalId: $result['template_id'] ?? $result['id'] ?? "gym_member_{$gymMember->id}",
            meta: $result
        );
    }

    public function verify(BiometricSample $sample, TemplateRef $ref): VerificationResult
    {
        $endpoint = $this->endpointMap['verify'] ?? null;

        if (! $endpoint) {
            throw new \Exception('Verify endpoint not configured');
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders($this->getHeaders())
            ->post($endpoint, [
                'image' => $sample->data,
                'template_id' => $ref->externalId,
            ]);

        $result = $response->json();

        return new VerificationResult(
            matched: $result['matched'] ?? false,
            confidence: (float) ($result['confidence'] ?? $result['score'] ?? 0.0),
            livenessScore: (float) ($result['liveness'] ?? $result['liveness_score'] ?? 0.0),
            providerRef: $ref->externalId,
            raw: $result
        );
    }

    public function getLivenessScore(BiometricSample $sample): float
    {
        $endpoint = $this->endpointMap['liveness'] ?? $this->endpointMap['verify'] ?? null;

        if (! $endpoint) {
            return 0.75; // Default fallback
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders($this->getHeaders())
            ->post($endpoint, [
                'image' => $sample->data,
                'check' => 'liveness',
            ]);

        $result = $response->json();

        return (float) ($result['liveness'] ?? $result['liveness_score'] ?? $result['score'] ?? 0.75);
    }

    public function deleteTemplate(TemplateRef $ref): bool
    {
        $endpoint = $this->endpointMap['delete'] ?? null;

        if (! $endpoint) {
            return false;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->delete(str_replace('{id}', $ref->externalId, $endpoint));

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getSupportedTypes(): array
    {
        return $this->config['supported_types'] ?? ['face', 'fingerprint'];
    }

    public function isAvailable(): bool
    {
        $endpoint = $this->endpointMap['health'] ?? $this->endpointMap['verify'] ?? null;

        if (! $endpoint) {
            return true; // Assume available if no health check configured
        }

        try {
            $response = Http::timeout(3)
                ->withHeaders($this->getHeaders())
                ->get($endpoint);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (! empty($this->apiKey)) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
            $headers['X-API-Key'] = $this->apiKey;
        }

        return $headers;
    }
}
