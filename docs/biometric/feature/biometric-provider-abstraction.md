---
feature: Biometric Provider Abstraction
status: draft
last_updated: 2026-04-03
author: docs-agent
related_docs:
  - ../database/biometric-devices-fields.md
  - ../database/biometric-templates-fields.md
---

# Biometric Provider Abstraction

## Overview

The biometric provider abstraction layer allows FluxFitness to support multiple biometric service backends through a standardized interface. This enables gyms to choose their preferred biometric provider (AWS, Azure, BioID, or custom on-premise) without changing application code.

## Architecture

### Classes & Services Involved

| Class / File | Responsibility |
|---|---|
| `BiometricProvider` (`app/Biometrics/Contracts/BiometricProvider.php`) | Interface defining all provider operations |
| `BiometricProviderRegistry` (`app/Biometrics/BiometricProviderRegistry.php`) | Factory/resolver for provider instances |
| `RekognitionProvider` (`app/Biometrics/Providers/RekognitionProvider.php`) | AWS Rekognition implementation |
| `AzureFaceProvider` (`app/Biometrics/Providers/AzureFaceProvider.php`) | Azure Face API implementation |
| `BioIDProvider` (`app/Biometrics/Providers/BioIDProvider.php`) | BioID liveness detection |
| `LocalModelProvider` (`app/Biometrics/Providers/LocalModelProvider.php`) | Self-hosted DeepFace/InsightFace |
| `CustomApiProvider` (`app/Biometrics/Providers/CustomApiProvider.php`) | Generic HTTP adapter |
| `BiometricServiceProvider` (`app/Providers/BiometricServiceProvider.php`) | Service provider registration |
| `config/biometrics.php` | Configuration file |

### Data Flow

```
BiometricDevice (configured with provider_type)
    ↓
CheckInService → BiometricProviderRegistry.resolve(provider_type, config)
    ↓
Specific Provider (e.g., RekognitionProvider)
    ↓
Provider API (AWS/Azure/BioID/etc.)
```

### Design Patterns Used

- **Strategy Pattern**: Each provider implements the same interface, interchangeable at runtime
- **Factory Pattern**: `BiometricProviderRegistry` creates provider instances based on config

### External Dependencies

- **AWS SDK** (`aws/aws-sdk-php`): For Rekognition operations
- **Azure Face SDK**: For Azure Face API operations
- **BioID Web Services**: For liveness detection

## Interface Methods

### `enroll(GymMember $gymMember, BiometricSample $sample): TemplateRef`

Enrolls a new biometric template for a member. The provider processes the raw biometric sample and returns a reference ID (not raw data) that can be used for verification.

**Returns:** `TemplateRef` - An encrypted reference containing provider-side template ID

### `verify(BiometricSample $sample, TemplateRef $ref): VerificationResult`

Verifies a biometric sample against an enrolled template.

**Returns:** `VerificationResult` - Contains `matched` (bool), `confidence` (float), and optional `details`

### `getLivenessScore(BiometricSample $sample): float`

Returns a liveness score (0.0-1.0) to detect spoofing attacks.

### `deleteTemplate(TemplateRef $ref): bool`

**GDPR CRITICAL**: Removes the biometric template from the provider's system. Must be fully implemented for all providers.

**Returns:** `bool` - True if deletion succeeded

### `getSupportedTypes(): array`

Returns supported biometric types (e.g., `['face', 'fingerprint', 'iris']`)

### `isAvailable(): bool`

Checks if the provider service is reachable and operational.

## Provider Implementations

### AWS Rekognition

- **Provider Type**: `rekognition`
- **Features**: Face matching, liveness (via separate Amazon One API)
- **Config Required**: `aws_region`, `aws_access_key`, `aws_secret_key`

### Azure Face API

- **Provider Type**: `azure_face`
- **Features**: Face detection, verification, liveness
- **Config Required**: `azure_endpoint`, `azure_key`

### BioID

- **Provider Type**: `bioid`
- **Features**: Liveness detection, face verification
- **Config Required**: `bioid_endpoint`, `bioid_customer_id`, `bioid_password`

### Local Model (Self-hosted)

- **Provider Type**: `local_model` (on_premise)
- **Features**: Face recognition using DeepFace or InsightFace
- **Config Required**: `model_path`, `model_type` (DeepFace, InsightFace, ArcFace)

### Custom API

- **Provider Type**: `custom` (CustomApiProvider)
- **Features**: Generic HTTP adapter for any REST-based biometric service
- **Config Required**: `api_url`, `api_key`, `endpoints` (enroll, verify, delete)

## Configuration

```php
// config/biometrics.php
'providers' => [
    'rekognition' => [
        'driver' => 'rekognition',
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
    ],
    // ... other providers
],
```

## Custom Device Enrollment Sync

Custom/on-premise devices that cannot directly call the provider API can sync enrollment data via:

**Endpoint:** `POST /api/v1/biometric/enroll-sync`

**Auth:** Device token (`Authorization: Bearer <device_token>`)

**Request:**
```json
{
  "member_id": 123,
  "biometric_type": "face",
  "template_data": "base64_encoded_template_or_reference",
  "device_id": "device-uuid"
}
```

**Response:**
```json
{
  "success": true,
  "template_id": "prov_abc123",
  "enrolled_at": "2026-04-03T10:30:00Z"
}
```

## GDPR Compliance

| Requirement | Implementation |
|---|---|
| No raw biometric storage | Only template references stored (provider-side IDs) |
| Consent required | BiometricConsent model tracks consent before enrollment |
| Data deletion | `deleteTemplate()` must be called when member revokes consent or is deleted |
| Right to erasure | BiometricDataDeletionService orchestrates cleanup across all tables |

## Related Features

| Feature | Relationship |
|---|---|
| [Biometric Check-in Flow](./biometric-checkin-flow.md) | Uses providers to verify member identity |
| [Biometric Consent System](./biometric-consent-system.md) | Must have consent before enrollment is allowed |
| [Door Access Control](./door-access-control.md) | Uses CheckInService which depends on providers |
