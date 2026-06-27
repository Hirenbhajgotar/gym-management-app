<?php

declare(strict_types=1);

namespace App\Biometrics\Providers;

use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\DTOs\VerificationResult;
use App\Models\GymMember;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AzureFaceProvider implements BiometricProvider
{
    protected string $endpoint;

    protected string $subscriptionKey;

    public function __construct(
        protected array $config
    ) {
        $location = $config['location'] ?? 'eastus';

        $this->endpoint = rtrim(
            str_replace(
                '{location}',
                $location,
                $config['endpoint'] ?? "https://{$location}.api.cognitive.microsoft.com"
            ),
            '/'
        );

        $this->subscriptionKey = $config['subscription_key'] ?? '';
    }

    protected function getBaseUrl(): string
    {
        return $this->endpoint;
    }

    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->timeout(10);
    }

    public function enroll(GymMember $gymMember, BiometricSample $sample): TemplateRef
    {
        $personGroupId = "fluxfitness-gym-{$gymMember->gym_id}";
        $personName = "gym_member_{$gymMember->id}";

        // Ensure person group exists
        $this->ensurePersonGroup($personGroupId);

        // Create or get person
        $person = $this->findOrCreatePerson($personGroupId, $personName);
        $azurePersonId = $person['personId'];

        $base = $this->getBaseUrl();

        // Add face
        $response = $this->http()
            ->withBody(base64_decode($sample->data), 'application/octet-stream')
            ->post(
                "{$base}/face/v1.0/persongroups/{$personGroupId}/persons/{$azurePersonId}/persistedFaces?detectionModel=detection_03"
            )
            ->throw();

        $faceData = $response->json();

        if (blank($faceData['persistedFaceId'])) {
            throw new \RuntimeException('Azure Face enrollment failed: ' . json_encode($faceData));
        }

        $this->trainPersonGroup($personGroupId);

        return new TemplateRef(
            provider: 'azure_face',
            externalId: $faceData['persistedFaceId'],
            meta: [
                'person_group_id' => $personGroupId,
                'person_id' => $azurePersonId,
                'quality_score' => $faceData['qualityForRecognition'] ?? null,
            ]
        );
    }

    public function verify(BiometricSample $sample, TemplateRef $ref): VerificationResult
    {
        $base = $this->getBaseUrl();

        // Detect face
        $detectResponse = $this->http()
            ->withBody(base64_decode($sample->data), 'application/octet-stream')
            ->post("{$base}/face/v1.0/detect?returnFaceId=true&returnFaceLandmarks=false&recognitionModel=recognition_03");

        $faces = $detectResponse->json();

        if (blank($faces)) {
            return new VerificationResult(
                matched: false,
                confidence: 0.0,
                livenessScore: 0.0,
                providerRef: '',
                raw: []
            );
        }

        $faceId = $faces[0]['faceId'];

        // Verify against person
        $verifyResponse = $this->http()
            ->post(
                "{$base}/face/v1.0/verify",
                [
                    'faceId' => $faceId,
                    'personId' => $ref->meta['person_id'],
                    'personGroupId' => $ref->meta['person_group_id'],
                ]
            );

        $result = $verifyResponse->json();

        $confidenceThreshold = ($this->config['confidence_threshold'] ?? 80) / 100;

        return new VerificationResult(
            matched: ($result['isIdentical'] ?? false) && ($result['confidence'] ?? 0) >= $confidenceThreshold,
            confidence: ($result['confidence'] ?? 0.0),
            livenessScore: 0.0,
            providerRef: $faceId,
            raw: $result
        );
    }

    public function getLivenessScore(BiometricSample $sample): float
    {
        $base = $this->getBaseUrl();

        $response = $this->http()
            ->withBody(base64_decode($sample->data), 'application/octet-stream')
            ->post(
                "{$base}/face/v1.0/detect?returnFaceId=true&returnFaceAttributes=blur,exposure,noise,headPose&recognitionModel=recognition_04&detectionModel=detection_03"
                // '/face/v1.0/detect?returnFaceId=true&returnFaceLandmarks=true&recognitionModel=recognition_03&returnRecognitionModel=true'
            );

        $faces = $response->json();

        if (blank($faces)) {
            return 0.0;
        }

        $face = $faces[0];
        $faceAttributes = $face['faceAttributes'] ?? [];

        // Calculate liveness score from attributes
        $headPose = $faceAttributes['headPose'] ?? ['yaw' => 0, 'roll' => 0, 'pitch' => 0];

        $blurScore = match ($faceAttributes['blur']['blurLevel'] ?? 'low') {
            'low' => 1.0,
            'medium' => 0.7,
            'high' => 0.3,
            default => 0.5,
        };

        $exposureScore = match ($faceAttributes['exposure']['exposureLevel'] ?? 'goodExposure') {
            'goodExposure' => 1.0,
            'overExposure' => 0.6,
            'underExposure' => 0.6,
            default => 0.8,
        };

        $noiseScore = match ($faceAttributes['noise']['noiseLevel'] ?? 'low') {
            'low' => 1.0,
            'medium' => 0.7,
            'high' => 0.3,
            default => 0.5,
        };

        $poseScore = max(0, 1 - (
            abs($headPose['yaw']) +
            abs($headPose['roll']) +
            abs($headPose['pitch'])
        ) / 90);

        return min(1.0, max(0.0,
            $blurScore * 0.2 +
            $exposureScore * 0.2 +
            $noiseScore * 0.2 +
            $poseScore * 0.4
        ));
    }

    public function deleteTemplate(TemplateRef $ref): bool
    {
        try {
            $base = $this->getBaseUrl();
            $personGroupId = $ref->meta['person_group_id'];
            $personId = $ref->meta['person_id'];
            $persistedFaceId = $ref->externalId;

            // Delete specific face
            $this->http()->delete(
                "{$base}/face/v1.0/persongroups/{$personGroupId}/persons/{$personId}/persistedFaces/{$persistedFaceId}"
            )->throw();

            return true;
        } catch (\Exception $e) {
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
            return $this->http()->timeout(5)->get($this->getBaseUrl() . '/face/v1.0/persongroups?top=1')->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function ensurePersonGroup(string $personGroupId): void
    {
        $base = $this->getBaseUrl();

        // Check if exists
        $response = $this->http()->get("{$base}/face/v1.0/persongroups/{$personGroupId}");

        if ($response->successful()) {
            return; // Already exists
        }

        // Create it
        $this->http()->put("{$base}/face/v1.0/persongroups/{$personGroupId}", [
            'name' => $personGroupId,
            'userData' => 'FluxFitness gym members',
            'recognitionModel' => 'recognition_04', // latest model
        ])->throw();
    }

    protected function findOrCreatePerson(string $personGroupId, string $personName): array
    {
        $base = $this->getBaseUrl();

        $listResponse = $this->http()->get("{$base}/face/v1.0/persongroups/{$personGroupId}/persons");

        if ($listResponse->successful()) {
            $existing = collect($listResponse->json())
                ->first(fn ($p) => $p['name'] === $personName);

            if ($existing) {
                return $existing;
            }
        }

        return $this->http()
            ->post("{$base}/face/v1.0/persongroups/{$personGroupId}/persons",
                [
                    'name' => $personName,
                    'userData' => 'gym_member',
                ]
            )
            ->throw()
            ->json();
    }

    protected function trainPersonGroup(string $personGroupId): void
    {
        $this->http()
            ->post(
                $this->getBaseUrl() . "/face/v1.0/persongroups/{$personGroupId}/train"
            );

        // Training is async — in production add polling via job/queue
    }
}
