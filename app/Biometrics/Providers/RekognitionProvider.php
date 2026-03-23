<?php

namespace App\Biometrics\Providers;

use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\DTOs\VerificationResult;
use App\Models\GymMember;
use Aws\Rekognition\RekognitionClient;

class RekognitionProvider implements BiometricProvider
{
    protected RekognitionClient $client;

    protected string $collectionId;

    public function __construct(
        protected array $config
    ) {
        $this->client = new RekognitionClient([
            'region' => $config['aws_region'] ?? 'us-east-1',
            'version' => 'latest',
            'credentials' => [
                'key' => $config['aws_key'] ?? '',
                'secret' => $config['aws_secret'] ?? '',
            ],
        ]);
    }

    public function enroll(GymMember $gymMember, BiometricSample $sample): TemplateRef
    {
        $this->collectionId = $this->getCollectionId($gymMember->gym_id);

        // Ensure collection exists
        $this->ensureCollection();

        // Index the face
        $result = $this->client->IndexFaces([
            'CollectionId' => $this->collectionId,
            'Image' => [
                'Bytes' => base64_decode($sample->data),
            ],
            'ExternalImageId' => "gym_member_{$gymMember->id}",
            'DetectionAttributes' => ['DEFAULT'],
        ]);

        $faceRecords = $result->get('FaceRecords');

        if (empty($faceRecords)) {
            throw new \Exception('No face detected in the image');
        }

        $faceId = $faceRecords[0]['Face']['FaceId'];
        $quality = $faceRecords[0]['Face']['Quality'] ?? null;

        return new TemplateRef(
            provider: 'rekognition',
            externalId: $faceId,
            meta: [
                'collection_id' => $this->collectionId,
                'quality_score' => $quality ? ($quality['Brightness'] + $quality['Sharpness']) / 200 : null,
            ]
        );
    }

    public function verify(BiometricSample $sample, TemplateRef $ref): VerificationResult
    {
        // Search for matching face
        $result = $this->client->SearchFacesByImage([
            'CollectionId' => $ref->meta['collection_id'] ?? $this->collectionId,
            'Image' => [
                'Bytes' => base64_decode($sample->data),
            ],
            'MaxFaces' => 1,
        ]);

        $faceMatches = $result->get('FaceMatches');

        if (empty($faceMatches)) {
            return new VerificationResult(
                matched: false,
                confidence: 0.0,
                livenessScore: 0.0,
                providerRef: '',
                raw: $result->toArray()
            );
        }

        $match = $faceMatches[0];
        $face = $match['Face'];

        return new VerificationResult(
            matched: true,
            confidence: $match['Similarity'] / 100,
            livenessScore: 0.0, // Will be set by AntiSpoofGuard
            providerRef: $face['FaceId'],
            raw: $result->toArray()
        );
    }

    public function getLivenessScore(BiometricSample $sample): float
    {
        $result = $this->client->DetectFaces([
            'Image' => [
                'Bytes' => base64_decode($sample->data),
            ],
            'Attributes' => ['ALL'],
        ]);

        $faceDetails = $result->get('FaceDetails');

        if (empty($faceDetails)) {
            return 0.0;
        }

        $detail = $faceDetails[0];
        $quality = $detail['Quality'] ?? [];
        $pose = $detail['Pose'] ?? [];

        // Calculate liveness score based on quality and pose
        $brightness = $quality['Brightness'] ?? 50;
        $sharpness = $quality['Sharpness'] ?? 50;
        $yaw = abs($pose['Yaw'] ?? 0);
        $roll = abs($pose['Roll'] ?? 0);
        $pitch = abs($pose['Pitch'] ?? 0);

        // Higher quality and more centered face = higher liveness score
        $qualityScore = ($brightness + $sharpness) / 200;
        $poseScore = max(0, 1 - ($yaw + $roll + $pitch) / 90);
        $eyesOpen = $detail['EyesOpen'] ?? ['Value' => true];
        $confidence = $detail['Confidence'] / 100;

        $livenessScore = ($qualityScore * 0.3 + $poseScore * 0.3 + ($eyesOpen['Value'] ? 0.2 : 0) + $confidence * 0.2);

        return min(1.0, max(0.0, $livenessScore));
    }

    public function deleteTemplate(TemplateRef $ref): bool
    {
        try {
            $this->client->DeleteFaces([
                'CollectionId' => $ref->meta['collection_id'] ?? $this->collectionId,
                'FaceIds' => [$ref->externalId],
            ]);

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
            $this->client->describeCollection([
                'CollectionId' => 'test_collection',
            ]);

            return true;
        } catch (\Exception $e) {
            // Check if it's a credentials issue
            return ! str_contains($e->getMessage(), 'credentials');
        }
    }

    protected function getCollectionId(int $gymId): string
    {
        return "fluxfitness_gym_{$gymId}";
    }

    protected function ensureCollection(): void
    {
        try {
            $this->client->describeCollection([
                'CollectionId' => $this->collectionId,
            ]);
        } catch (\Exception $e) {
            // Collection doesn't exist, create it
            $this->client->createCollection([
                'CollectionId' => $this->collectionId,
            ]);
        }
    }
}
