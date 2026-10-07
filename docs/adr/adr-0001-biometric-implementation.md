---
title: "ADR-0001: Biometric Hardware, Access Control, and Verification System Architecture"
status: "Accepted"
date: "2026-09-26"
authors: "Lead Architect, Security Engineer, Backend Team"
tags: ["architecture", "decision", "biometrics", "iot", "security", "access-control", "privacy"]
supersedes: ""
superseded_by: ""
---

# ADR-0001: Biometric Hardware, Access Control, and Verification System Architecture

## Status

**Accepted**

## Context

Gym facilities managed under the multi-tenant SaaS platform require automated, reliable, and secure physical access control and attendance tracking. The operational environment presents several critical engineering and compliance challenges:

1. **Heterogeneous Hardware Ecosystem**: Gym locations utilize various hardware devices ranging from cloud-connected edge biometric terminals, optical/facial scanners, and standalone push-protocol attendance terminals (eSSL, ZKTeco using ADMS/iclock protocols, and Hikvision access control terminals) to physical turnstiles, electromagnetic locks, and barrier relays.
2. **Strict Privacy & Regulatory Compliance**: Processing biometric data (facial embeddings, fingerprint minutiae) is heavily regulated under the Digital Personal Data Protection (DPDP) Act 2023 and GDPR. Explicit, revocable consent, cryptographically signed consent records, template redaction in logs, and verifiable automated purging upon member exit or consent revocation are legally required.
3. **High-Availability & Edge Offline Resilience**: Gym attendance check-in must maintain sub-second response latency (< 300ms for door release) during peak hours. Furthermore, intermittent internet connectivity must not strand members outside facilities or result in lost attendance logs.
4. **Anti-Fraud and Anti-Passback Requirements**: Physical access systems must detect and prevent fraudulent access, including buddy check-ins, multi-entry passback exploitation on single-sensor doorways, and spoofing attacks via static photographs or video replays.
5. **Hardware Lifecycle & Deprovisioning**: Expired and non-renewed members must be deprovisioned from physical device memory to prevent unauthorized entry while allowing multi-day grace periods for membership renewal without forcing destructive re-enrollment workflows.

## Decision

We designed and implemented a unified, multi-tier biometric architecture structured into five cohesive subsystems:

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                             BIOMETRIC SYSTEM ARCHITECTURE                        │
└──────────────────────────────────────────────────────────────────────────────────┘

   ┌────────────────────────────────────────────────────────────────────────────┐
   │                         1. Hardware Ingress Tier                           │
   └────────────────────────────────────────────────────────────────────────────┘
        │                                                     │
   [ADMS / iclock Devices]                           [Door Webhooks / ISAPI]
   (eSSL, ZKTeco Push)                              (Facial Terminals / Relays)
        │                                                     │
        ▼                                                     ▼
   AdmsController (`/api/iclock/*`)                 BiometricWebhookController
   AuthenticateAdmsDevice Middleware                AuthenticateBiometricDevice
        │                                                     │
        └───────────────────────┬─────────────────────────────┘
                                │
   ┌────────────────────────────▼───────────────────────────────────────────────┐
   │                     2. Core Orchestration & Security                       │
   └────────────────────────────────────────────────────────────────────────────┘
        │
        ├──► RedisBiometricCacheService (O(1) Serial/Token/Member Resolution)
        ├──► BiometricConsent (DPDP Act / GDPR Pre-Enrollment Verification)
        ├──► MembershipGuard (Active Status, Tier, Zone, Time-Window Validation)
        ├──► FraudDetector & AntiSpoofGuard (Liveness Score >= 0.85 Threshold)
        └──► CheckInService & AdmsEventService (Attendance Session Management)
                                │
        ┌───────────────────────┴─────────────────────────────┐
        │                                                     │
   ┌────▼───────────────────────────────────────┐   ┌─────────▼────────────────────────┐
   │    3. Pluggable Biometric Provider Layer   │   │  4. Access Control & Physical    │
   └────────────────────────────────────────────┘   └──────────────────────────────────┘
        │                                                │
        ├── RekognitionProvider (AWS Cloud)              ├── DoorControlService (Relay/Wiegand)
        ├── AzureFaceProvider (Azure Face API)           ├── Anti-Passback & Auto-Checkout
        ├── BioIDProvider (Liveness / BioID)             ├── OfflineQueueManager & Sync
        ├── LocalModelProvider (DeepFace/InsightFace)    └── DoorEvent Audit Logging
        └── CustomApiProvider (HTTP Bridge)
                                │
   ┌────────────────────────────▼───────────────────────────────────────────────┐
   │                  5. Hardware Lifecycle & Command Queue                     │
   └────────────────────────────────────────────────────────────────────────────┘
        │
        ├── AdmsDeviceCommand Queue (`PENDING` -> `SENT` -> `ACKNOWLEDGED`)
        ├── 3-Tier Expired Grace Period Engine (`gym_grace_days` + `adms_grace_days`)
        └── BiometricDataDeletionService (Zero-Trace Template Anonymization)
```

### 1. Dual-Channel Protocol Ingress & Multi-Vendor Terminal Support
- Implement dual ingress endpoints:
  - **ADMS / iclock Push Protocol**: Exposed via `/api/iclock/cdata`, `/api/iclock/getrequest`, and `/api/iclock/devicecmd` to support eSSL and ZKTeco terminals with automated `ATTLOG` attendance ingestion and bidirectional asynchronous command polling.
  - **HTTP Webhook & ISAPI Event Receiver**: Exposed via `/api/v1/biometric/webhook/{token}` and `/api/v1/biometric/hikvision/event` for direct IP terminal integration, camera relays, and real-time door unlocking directives.
- Device authentication is enforced via `AuthenticateAdmsDevice` (hardware serial lookup with Redis-cached device resolution) and `AuthenticateBiometricDevice` (Bearer/header HMAC token authentication).

### 2. Pluggable Biometric Engine Abstraction
- Defined `BiometricProvider` contract (`app/Biometrics/Contracts/BiometricProvider.php`) with operations: `enroll()`, `verify()`, `getLivenessScore()`, `deleteTemplate()`, `getSupportedTypes()`, and `isAvailable()`.
- Implemented `BiometricProviderRegistry` enabling tenant-level and device-level provider selection across AWS Rekognition, Azure Face API, BioID, Local Neural Models (InsightFace/DeepFace), and Custom HTTP APIs without code changes.

### 3. Pre-Enrollment Mandatory Consent & Privacy Compliance
- Enforce strict pre-enrollment consent checks (`BiometricConsent`) before biometric templates (`BiometricTemplate`) can be captured or stored.
- Secure consent workflow with cryptographic verification tokens (`token` + `expires_at`), timestamped signature audit trails, and automatic revocation listeners (`HandleConsentRevoked`) that immediately deactivate device credentials.
- Automatic log redaction via `BiometricLogRedactor` ensuring raw biometric vectors and sensitive identifiers are masked in application logs.

### 4. Resilient Access Control, Anti-Passback, and Auto-Checkout
- Real-time door relay execution managed via `DoorControlService` supporting relay hold durations, door zone constraints, and `DoorEvent` audit logging.
- Intelligent single-sensor auto-checkout: `AdmsEventService` checks for open daily attendance records before evaluating anti-passback rules, automatically converting exit scans into check-out timestamps without requiring distinct entry and exit turnstile hardware.
- Offline buffering and synchronization managed by `OfflineQueueManager` and `OfflineSyncService` ensuring edge scanners can sync accumulated punches with idempotency keys upon link restoration.

### 5. Asynchronous Command Queue & Multi-Tier Grace Period Deprovisioning
- Asynchronous server-to-device command delivery backed by `AdmsDeviceCommand` and `AdmsCommandStatusEnum` (`PENDING`, `SENT`, `ACKNOWLEDGED`, `FAILED`).
- Three-tier grace period evaluation:
  $$\text{Hardware Cutoff} = \text{membership\_end\_date} + \text{gym\_grace\_days} + \text{adms\_grace\_days}$$
- Automated daily maintenance (`app:cleanup-expired-device-members`) queues batch `user_delete` commands, deactivates local template mappings, and flushes tenant hardware caches.

## Consequences

### Positive

- **POS-001**: **Vendor Independence & Extensibility**: The system integrates with standard commodity hardware (eSSL, ZKTeco, Hikvision) as well as modern AI vision providers without vendor lock-in.
- **POS-002**: **Sub-Millisecond Cache-Driven Verification**: Ingress lookups for device serials, membership validity, and zone authorizations leverage Redis caching (`RedisBiometricCacheService`), keeping average door release evaluation under 50ms.
- **POS-003**: **Regulatory Compliance by Design**: Cryptographic consent tracking, zero-trace template deletion (`BiometricDataDeletionService`), and log redaction satisfy DPDP Act 2023 and GDPR mandate requirements out of the box.
- **POS-004**: **Reduced Hardware Footprint**: Auto-checkout detection allows gyms with single physical readers to maintain complete time-in and time-out records without investing in secondary exit turnstiles.
- **POS-005**: **Fault Tolerance & Edge Survivability**: Offline queue management and idempotent batch synchronization prevent attendance data loss during ISP or cloud downtime.

### Negative

- **NEG-001**: **Eventual Deprovisioning Latency**: ADMS devices operate via client polling (`getrequest`); user deletions and template updates take effect on the device's next poll cycle (typically 10-60s) rather than instantly.
- **NEG-002**: **Configuration Complexity**: Supporting both ADMS push and webhook relay triggers requires dual-endpoint configuration on physical devices in advanced hybrid setups.
- **NEG-003**: **State Synchronization Overhead**: Maintaining state parity between cloud databases, Redis caches, and embedded device flash memory requires dedicated reconciliation jobs and retry handling for failed device commands.

## Alternatives Considered

### Direct Vendor Desktop SDK / Middleware Client

- **ALT-001**: **Description**: Run Windows-based desktop client applications on local gym PCs to connect to devices via vendor C++/C# SDKs and push data to cloud APIs.
- **ALT-002**: **Rejection Reason**: Requires dedicated on-premise PC hardware running 24/7 at each gym branch, increases IT maintenance overhead, introduces a single point of failure locally, and is incompatible with modern containerized/cloud deployments.

### Pure Webhook Architecture Without ADMS Support

- **ALT-003**: **Description**: Require all biometric terminals to support custom HTTP REST webhooks and discard native ADMS push protocol support.
- **ALT-004**: **Rejection Reason**: The vast majority of affordable, widely deployed attendance terminals (eSSL, ZKTeco) natively speak only ADMS/iclock protocols and cannot send custom JSON webhook payloads without expensive third-party hardware converters.

### Hardcoded Single Biometric Cloud Provider (AWS Rekognition Only)

- **ALT-005**: **Description**: Bind the biometric check-in engine directly to AWS Rekognition APIs without an abstraction layer.
- **ALT-006**: **Rejection Reason**: Created cloud vendor lock-in, prevented on-premise local model inference for low-bandwidth gyms, and failed to accommodate gym owners with existing Azure or BioID subscriptions.

## Implementation Notes

- **IMP-001**: **Routing & Middleware Registration**:
  - ADMS routes are registered under `routes/api.php` under prefix `/api/iclock` mapped to `AdmsController` and protected by `AuthenticateAdmsDevice`.
  - Hikvision event listeners are registered under `/api/v1/biometric/hikvision/event` mapped to `HikvisionAdmsController`.
  - Webhook check-in routes are registered under `/api/v1/biometric/webhook/{token}` mapped to `BiometricWebhookController`.
- **IMP-002**: **Cache Architecture**:
  - Device configurations and serial-to-gym mappings are cached in Redis with tags: `biometric:device:{serial}`.
  - Member active credentials and anti-passback locks are stored in Redis with TTLs matching tenant check-in guard windows.
- **IMP-003**: **Database Migrations & Schema**:
  - `biometric_devices`: Stores terminal serial numbers, IP/port settings, door zones, provider identifiers, and ADMS enablement flags.
  - `adms_device_commands`: Manages queued and dispatched server commands with status lifecycle and device return codes.
  - `biometric_consents`: Auditable legal consent records linked to `gym_members` with cryptographic signature verification.
  - `biometric_templates`: Encrypted or tokenized biometric references mapped per provider and device.
  - `door_events`: Physical access logs capturing lock state changes, latencies, and authorization status.
- **IMP-004**: **Scheduled Maintenance**:
  - Daily console command `app:cleanup-expired-device-members` executed via Laravel scheduler to generate hardware deprovisioning commands based on the 3-tier grace window.

## References

- **REF-001**: [Biometric System Documentation Index](file:///docs/biometric/README.md)
- **REF-002**: [Biometric ADMS Push Protocol Guide](file:///docs/biometric/feature/biometric-adms-push-protocol.md)
- **REF-003**: [Biometric Check-in & Security Flow](file:///docs/biometric/feature/biometric-checkin-flow.md)
- **REF-004**: [Door Access Control & Relay Architecture](file:///docs/biometric/feature/door-access-control.md)
- **REF-005**: [Biometric Consent & Privacy System](file:///docs/biometric/feature/biometric-consent-system.md)
- **REF-006**: [Biometric Provider Abstraction Specification](file:///docs/biometric/feature/biometric-provider-abstraction.md)
- **REF-007**: [AdmsProtocolTest Feature Test Suite](file:///tests/Feature/AdmsProtocolTest.php)
- **REF-008**: [ADR-0002: Biometric Enrollment and Device Reconciliation Architecture](file:///docs/adr/adr-0002-biometric-enrollment-and-device-reconciliation.md)
