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

    protected string $secret;

    protected int $timeout;

    public function __construct(
        protected array $config
    ) {
        $baseUrl = rtrim($config['api_url'] ?? '', '/');

        $this->endpointMap = [
            'enroll' => $baseUrl . ($config['path_enroll'] ?? '/enroll'),
            'verify' => $baseUrl . ($config['path_verify'] ?? '/verify'),
            'liveness' => $baseUrl . ($config['path_liveness'] ?? '/liveness'),
            'delete' => $baseUrl . ($config['path_delete'] ?? '/delete/{id}'),
            'health' => $baseUrl . '/health',
        ];

        $this->apiKey = $config['api_key'] ?? '';
        $this->secret = $config['secret'] ?? '';
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

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Custom API enroll failed [{$response->status()}]: " . $response->body()
            );
        }

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

        $matchedKey = $this->config['response_matched_key'] ?? 'matched';
        $confidenceKey = $this->config['response_confidence_key'] ?? 'confidence';
        $livenessKey = $this->config['response_liveness_key'] ?? 'liveness_score';
        $threshold = ($this->config['confidence_threshold'] ?? 80) / 100;

        return new VerificationResult(
            matched: (bool) ($result[$matchedKey] ?? false) && (float) ($result[$confidenceKey] ?? 0) >= $threshold,
            confidence: (float) ($result[$confidenceKey] ?? 0.0),
            livenessScore: (float) ($result[$livenessKey] ?? 0.0),
            providerRef: $ref->externalId,
            raw: $result
        );
    }

    public function getLivenessScore(BiometricSample $sample): float
    {
        $endpoint = $this->endpointMap['liveness'] ?? $this->endpointMap['verify'] ?? null;

        if (! $endpoint) {
            // return 0.75; // Default fallback
            if ($this->config['skip_liveness'] ?? false) {
                return 1.0; // intentional bypass — config mein set hai
            }
            throw new \RuntimeException(
                'Liveness endpoint not configured for custom provider. ' .
                'Set skip_liveness=true in config to bypass liveness check.'
            );
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

        $deleteEndpoint = str_replace(
            ['{id}', ':id'],
            $ref->externalId,
            $endpoint
        );

        if ($deleteEndpoint === $endpoint) {
            $deleteEndpoint = rtrim($endpoint, '/') . '/' . $ref->externalId;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->delete($deleteEndpoint);

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

        if (! empty($this->config['headers'])) {
            $headers = array_merge($headers, $this->config['headers']);
        }

        if (! empty($this->apiKey)) {
            $authType = $this->config['auth_type'] ?? 'bearer';

            match ($authType) {
                'bearer' => $headers['Authorization'] = "Bearer {$this->apiKey}",
                'x-api-key' => $headers['X-API-Key'] = $this->apiKey,
                'basic' => $headers['Authorization'] = 'Basic ' . base64_encode($this->apiKey . ':' . $this->secret),
                'custom' => null,
                default => $headers['Authorization'] = "Bearer {$this->apiKey}",
            };
        }

        return $headers;
    }
}
