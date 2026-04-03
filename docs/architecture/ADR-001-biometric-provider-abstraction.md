---
adr: 001
title: Biometric Provider Abstraction
status: accepted
date: 2026-04-03
author: docs-agent
related_features:
  - ../features/biometric-provider-abstraction.md
---

# ADR-001: Biometric Provider Abstraction

## Status

Accepted

## Context

FluxFitness needs to support multiple biometric service providers (AWS Rekognition, Azure Face API, BioID, self-hosted models, custom HTTP APIs) without tying the application to a single vendor. Different gyms may have different preferences or existing infrastructure. Additionally, some gyms may want on-premise biometric solutions that don't send data to cloud services.

## Decision

Create a provider abstraction layer with:

1. **`BiometricProvider` interface** (`app/Biometrics/Contracts/BiometricProvider.php`):
   - `enroll(GymMember, BiometricSample): TemplateRef`
   - `verify(BiometricSample, TemplateRef): VerificationResult`
   - `getLivenessScore(BiometricSample): float`
   - `deleteTemplate(TemplateRef): bool` — GDPR requirement
   - `getSupportedTypes(): array`
   - `isAvailable(): bool`

2. **Provider implementations**:
   - `RekognitionProvider` — AWS Rekognition
   - `AzureFaceProvider` — Azure Face API
   - `BioIDProvider` — BioID liveness detection
   - `LocalModelProvider` — Self-hosted (DeepFace, InsightFace)
   - `CustomApiProvider` — Generic HTTP adapter

3. **`BiometricProviderRegistry`** — Factory that resolves provider instances based on device configuration

4. **Configuration** (`config/biometrics.php`) — Centralized provider configs with credentials

## Alternatives Considered

| Alternative | Why rejected |
|---|---|
| Single provider (hardcoded AWS) | Vendor lock-in, not all gyms use AWS |
| Runtime provider switching without interface | Code duplication, hard to maintain |
| Generic "plugin" system | Over-engineered for current needs |

## Consequences

### Positive

- gyms can choose their preferred provider
- Easy to add new providers in future
- Clean separation of concerns
- Testable (can mock interface)

### Negative / Trade-offs

- Additional abstraction overhead
- Each provider needs maintenance
- Must ensure deleteTemplate() is implemented for GDPR

## Related Decisions

- [ADR-003](./ADR-003-consent-before-enrollment.md) — Consent system builds on this abstraction