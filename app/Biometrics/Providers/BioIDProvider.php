<?php

declare(strict_types=1);

namespace App\Biometrics\Providers;

use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\DTOs\VerificationResult;
use App\Models\GymMember;
use Exception;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BioIDProvider implements BiometricProvider
{
    public function __construct(
        protected array $config = []
    ) {
        /** @var array<string, mixed> $systemConfig */
        $systemConfig = config()->array('biometrics.providers_config.bioid', []);
        /** @var array<string, mixed> $filteredConfig */
        $filteredConfig = array_filter($config, static fn ($v): bool => $v !== null && $v !== '');
        $this->config = array_merge($systemConfig, $filteredConfig);
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode(
                ($this->config['app_identifier'] ?? '') . ':' . ($this->config['api_key'] ?? '')
            ),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    protected function getBaseUrl(): string
    {
        // BWS 3.0 base URL
        return rtrim(
            $this->config['wsdl_url']
                ?? 'https://bws.bioid.com/extension',
            '/'
        );
    }

    // BCID = BioID Class ID — per member + per gym unique identifier
    protected function getBcid(int $gymMemberId): string
    {
        $serviceId = $this->config['service_id'] ?? '0';

        return "{$serviceId}.0.{$gymMemberId}"; // BioID BCID format: storage.partition.classid
    }

    public function enroll(GymMember $gymMember, BiometricSample $sample): TemplateRef
    {
        $bcid = $this->getBcid($gymMember->id); // BioID Class ID — per member unique ID

        // Step 1: Enrollment upload karo
        $response = Http::timeout(30)
            ->withHeaders($this->getHeaders())
            ->post($this->getBaseUrl() . '/enrollment', [
                'BCID' => $bcid,
                'Images' => [
                    ['Data' => $sample->data], // base64 as-is for BioID
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'BioID enrollment failed: ' . $response->body()
            );
        }

        $result = $response->json();

        return new TemplateRef(
            provider: 'bioid',
            externalId: $bcid,   // BCID hi template reference hai BioID mein
            meta: [
                'bcid' => $bcid,
                'error' => $result['Error'] ?? null,
                'quality_score' => $result['ImageQualityScore'] ?? null,
            ]
        );
    }

    public function verify(BiometricSample $sample, TemplateRef $ref): VerificationResult
    {
        $bcid = $ref->externalId; // enrollment mein store kiya tha

        $response = Http::timeout(30)
            ->withHeaders($this->getHeaders())
            ->post($this->getBaseUrl() . '/verify', [
                'BCID' => $bcid,
                'Images' => [
                    ['Data' => $sample->data],
                ],
            ]);

        if (! $response->successful()) {
            return new VerificationResult(
                matched: false,
                confidence: 0.0,
                livenessScore: 0.0,
                providerRef: $bcid,
                raw: ['error' => $response->body()]
            );
        }

        $result = $response->json();

        // BioID response: Error = 0 means success
        $matched = ($result['Error'] ?? -1) === 0;
        $confidence = $matched
            ? (float) ($result['VerificationScore'] ?? 0.85)
            : 0.0;

        return new VerificationResult(
            matched: $matched,
            confidence: $confidence,
            livenessScore: (float) ($result['LivenessScore'] ?? 0.0),
            providerRef: $bcid,
            raw: $result
        );
    }

    public function getLivenessScore(BiometricSample $sample): float
    {
        $response = Http::timeout(10)
            ->withHeaders($this->getHeaders())
            ->post($this->getBaseUrl() . '/livenessdetection', [
                'Images' => [
                    ['Data' => $sample->data], // already base64
                ],
            ]);

        if (! $response->successful()) {
            // Liveness endpoint fail — safe default: 0
            return 0.0;
        }

        $result = $response->json();

        // BioID LivenessScore 0-1 range mein hota hai
        return (float) ($result['LivenessScore'] ?? 0.0);
    }

    public function deleteTemplate(TemplateRef $ref): bool
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders($this->getHeaders())
                ->delete($this->getBaseUrl() . '/enrollment', [
                    'BCID' => $ref->externalId,
                ]);

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    public function getSupportedTypes(): array
    {
        return ['face'];
    }

    public function isAvailable(): bool
    {
        try {
            // BioID BWS token endpoint se check karo
            $response = Http::timeout(5)
                ->withHeaders($this->getHeaders())
                ->get($this->getBaseUrl() . '/status');

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }
}
