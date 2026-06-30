<?php

declare(strict_types=1);

namespace App\Biometrics\Providers;

use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\DTOs\BiometricSample;
use App\Biometrics\DTOs\TemplateRef;
use App\Biometrics\DTOs\VerificationResult;
use App\Models\GymMember;
use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Aws\Result;
use Exception;

class RekognitionProvider implements BiometricProvider
{
    protected RekognitionClient $client;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config
    ) {
        $this->client = new RekognitionClient([
            'region' => $config['region'] ?? 'us-east-1',
            'version' => 'latest',
            'credentials' => [
                'key' => $config['access_key'] ?? '',
                'secret' => $config['secret_key'] ?? '',
            ],
        ]);
    }

    public function enroll(GymMember $gymMember, BiometricSample $sample): TemplateRef
    {
        $collectionId = $this->getCollectionId($gymMember->gym_id);

        // Ensure collection exists
        $this->ensureCollection($collectionId);

        // Index the face
        /** @var Result $result */
        $result = $this->client->IndexFaces([
            'CollectionId' => $collectionId,
            'Image' => [
                'Bytes' => base64_decode($sample->data, true),
            ],
            'ExternalImageId' => "gym_member_{$gymMember->id}",
            'DetectionAttributes' => ['DEFAULT'],
        ]);

        /** @var array<int, array<string, mixed>>|null $faceRecords */
        $faceRecords = $result->get('FaceRecords');

        throw_if(blank($faceRecords), Exception::class, 'No face detected in the image');

        /** @var string $faceId */
        $faceId = $faceRecords[0]['Face']['FaceId'];
        /** @var array<string, mixed>|null $quality */
        $quality = $faceRecords[0]['Face']['Quality'] ?? null;

        return new TemplateRef(
            provider: 'rekognition',
            externalId: $faceId,
            meta: [
                'collection_id' => $collectionId,
                'quality_score' => $quality ? ($quality['Brightness'] + $quality['Sharpness']) / 200 : null,
            ]
        );
    }

    public function verify(BiometricSample $sample, TemplateRef $ref): VerificationResult
    {
        // Search for matching face
        /** @var Result $result */
        $result = $this->client->SearchFacesByImage([
            'CollectionId' => $ref->meta['collection_id'],
            'Image' => [
                'Bytes' => base64_decode($sample->data, true),
            ],
            'MaxFaces' => 5,
            'FaceMatchThreshold' => ($this->config['similarity_threshold'] ?? 85),
        ]);

        /** @var array<int, array<string, mixed>>|null $faceMatches */
        $faceMatches = $result->get('FaceMatches');

        if (blank($faceMatches)) {
            return new VerificationResult(
                matched: false,
                confidence: 0.0,
                livenessScore: 0.0,
                providerRef: '',
                /** @var array<string, mixed> $raw */
                raw: $result->toArray()
            );
        }

        $expectedFaceId = $ref->externalId;
        $matchedFace = collect($faceMatches)->first(fn (array $m) => ($m['Face']['FaceId'] ?? null) === $expectedFaceId);

        if ($matchedFace === null) {
            return new VerificationResult(
                matched: false,
                confidence: 0.0,
                livenessScore: 0.0,
                providerRef: '',
                raw: $result->toArray()
            );
        }

        return new VerificationResult(
            matched: true,
            confidence: $matchedFace['Similarity'] / 100,
            livenessScore: 0.0,
            providerRef: $matchedFace['Face']['FaceId'],
            raw: $result->toArray()
        );
    }

    public function getLivenessScore(BiometricSample $sample): float
    {
        /** @var Result $result */
        $result = $this->client->DetectFaces([
            'Image' => [
                'Bytes' => base64_decode($sample->data, true),
            ],
            'Attributes' => ['ALL'],
        ]);

        /** @var array<int, array<string, mixed>>|null $faceDetails */
        $faceDetails = $result->get('FaceDetails');

        if (blank($faceDetails)) {
            return 0.0;
        }

        /** @var array<string, mixed> $detail */
        $detail = $faceDetails[0];
        /** @var array<string, mixed> $quality */
        $quality = $detail['Quality'] ?? [];
        /** @var array<string, mixed> $pose */
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
        $eyesOpen = $detail['EyesOpen']['Value'] ?? true;
        $confidence = $detail['Confidence'] / 100;

        $livenessScore = (
            $qualityScore * 0.3
            + $poseScore * 0.3
            + ($eyesOpen ? 0.2 : 0)
            + $confidence * 0.2
        );

        return min(1.0, max(0.0, $livenessScore));
    }

    public function deleteTemplate(TemplateRef $ref): bool
    {
        try {
            $this->client->DeleteFaces([
                'CollectionId' => $ref->meta['collection_id'],
                'FaceIds' => [$ref->externalId],
            ]);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    public function getSupportedTypes(): array
    {
        return ['face'];
    }

    public function isAvailable(): bool
    {
        try {
            $this->client->listCollections([
                'MaxResults' => 1,
            ]);

            return true;
        } catch (AwsException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getCollectionId(int $gymId): string
    {
        return "fluxfitness_gym_{$gymId}";
    }

    protected function ensureCollection(string $collectionId): void
    {
        try {
            $this->client->describeCollection([
                'CollectionId' => $collectionId,
            ]);
        } catch (Exception $e) {
            // Collection doesn't exist, create it
            $this->client->createCollection([
                'CollectionId' => $collectionId,
            ]);
        }
    }
}
