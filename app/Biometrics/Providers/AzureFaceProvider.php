<?php

namespace App\Biometrics\Providers;

use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\DTOs\VerificationResult;
use App\Models\GymMember;
use Illuminate\Support\Facades\Http;

class AzureFaceProvider implements BiometricProvider
{
    protected string $endpoint;

    protected string $subscriptionKey;

    public function __construct(
        protected array $config
    ) {
        $this->endpoint = $config['endpoint'] ?? 'https://{location}.face.api.cognitive.microsoft.com';
        $this->subscriptionKey = $config['subscription_key'] ?? '';
    }

    public function enroll(GymMember $gymMember, BiometricSample $sample): TemplateRef
    {
        $personGroupId = "fluxfitness-gym-{$gymMember->gym_id}";
        $personId = "gym_member_{$gymMember->id}";

        // Ensure person group exists
        $this->ensurePersonGroup($personGroupId);

        // Create or get person
        $person = $this->createPerson($personGroupId, $personId);

        // Add face
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->withBody($sample->data, 'application/octet-stream')->post(
            str_replace('{location}', $this->config['location'] ?? 'eastus', $this->endpoint) .
            "/face/v1.0/persongroups/{$personGroupId}/persons/{$person['personId']}/persistedFaces"
        );

        $faceData = $response->json();

        return new TemplateRef(
            provider: 'azure_face',
            externalId: $faceData['persistedFaceId'] ?? $person['personId'],
            meta: [
                'person_group_id' => $personGroupId,
                'person_id' => $person['personId'],
                'quality_score' => $faceData['qualityForRecognition'] ?? null,
            ]
        );
    }

    public function verify(BiometricSample $sample, TemplateRef $ref): VerificationResult
    {
        // Detect face
        $detectResponse = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->withBody($sample->data, 'application/octet-stream')->post(
            str_replace('{location}', $this->config['location'] ?? 'eastus', $this->endpoint) .
            '/face/v1.0/detect?returnFaceId=true&returnFaceLandmarks=false&recognitionModel=recognition_03'
        );

        $faces = $detectResponse->json();

        if (empty($faces)) {
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
        $verifyResponse = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->post(
            str_replace('{location}', $this->config['location'] ?? 'eastus', $this->endpoint) .
            '/face/v1.0/verify',
            [
                'faceId' => $faceId,
                'personId' => $ref->meta['person_id'] ?? $ref->externalId,
                'personGroupId' => $ref->meta['person_group_id'] ?? '',
            ]
        );

        $result = $verifyResponse->json();

        return new VerificationResult(
            matched: $result['isIdentical'] ?? false,
            confidence: ($result['confidence'] ?? 0.0),
            livenessScore: 0.0,
            providerRef: $faceId,
            raw: $result
        );
    }

    public function getLivenessScore(BiometricSample $sample): float
    {
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->withBody($sample->data, 'application/octet-stream')->post(
            str_replace('{location}', $this->config['location'] ?? 'eastus', $this->endpoint) .
            '/face/v1.0/detect?returnFaceId=true&returnFaceLandmarks=true&recognitionModel=recognition_03&returnRecognitionModel=true'
        );

        $faces = $response->json();

        if (empty($faces)) {
            return 0.0;
        }

        $face = $faces[0];
        $faceAttributes = $face['faceAttributes'] ?? [];

        // Calculate liveness score from attributes
        $blur = $faceAttributes['blur']['blurLevel'] ?? 'low';
        $exposure = $faceAttributes['exposure']['exposureLevel'] ?? 'good';
        $noise = $faceAttributes['noise']['noiseLevel'] ?? 'low';
        $headPose = $faceAttributes['headPose'] ?? ['yaw' => 0, 'roll' => 0, 'pitch' => 0];

        $blurScore = match ($blur) {
            'low' => 1.0,
            'medium' => 0.7,
            'high' => 0.3,
            default => 0.5,
        };

        $exposureScore = match ($exposure) {
            'good' => 1.0,
            'overExposure' => 0.6,
            'underExposure' => 0.6,
            default => 0.8,
        };

        $noiseScore = match ($noise) {
            'low' => 1.0,
            'medium' => 0.7,
            'high' => 0.3,
            default => 0.5,
        };

        $poseScore = max(0, 1 - (abs($headPose['yaw']) + abs($headPose['roll']) + abs($headPose['pitch'])) / 90);

        return $blurScore * 0.2 + $exposureScore * 0.2 + $noiseScore * 0.2 + $poseScore * 0.4;
    }

    public function deleteTemplate(TemplateRef $ref): bool
    {
        // In a real implementation, would call Azure Face API to delete
        return true;
    }

    public function getSupportedTypes(): array
    {
        return ['face'];
    }

    public function isAvailable(): bool
    {
        return ! empty($this->subscriptionKey);
    }

    protected function ensurePersonGroup(string $personGroupId): void
    {
        // Would create person group if it doesn't exist
    }

    protected function createPerson(string $personGroupId, string $personId): array
    {
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->post(
            str_replace('{location}', $this->config['location'] ?? 'eastus', $this->endpoint) .
            "/face/v1.0/persongroups/{$personGroupId}/persons",
            [
                'name' => $personId,
                'userData' => 'Gym member enrollment',
            ]
        );

        return $response->json();
    }
}
