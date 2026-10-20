# Biometric System Documentation

Comprehensive technical documentation for the biometric hardware integration, access control, consent management, and device push protocols.

---

## 📑 Table of Contents

### 1. Features & Workflows (`docs/biometric/feature/`)
- **[Biometric ADMS Push Protocol](feature/biometric-adms-push-protocol.md)**: Full guide to eSSL, ZKTeco, and Hikvision terminal integration, dual-mode coexistence with webhook door control, auto-checkout detection, command polling queue, and automated expired member deprovisioning.
- **[Biometric Check-in Flow](feature/biometric-checkin-flow.md)**: Check-in orchestration, membership guards, anti-spoofing liveness verification, fraud prevention, and real-time WebSocket broadcasting.
- **[Door Access Control](feature/door-access-control.md)**: Relay, Wiegand, and HTTP API door controller integration, unlock duration, and access audit logging.
- **[Biometric Provider Abstraction](feature/biometric-provider-abstraction.md)**: Pluggable AI providers (AWS Rekognition, Azure Face API, BioID, local models, custom HTTP).
- **[Biometric Consent System](feature/biometric-consent-system.md)**: DPDP Act / GDPR compliance, cryptographic token signing, and mandatory pre-enrollment consent.

### 2. Architecture Decision Records (`docs/biometric/architecture/` & `docs/adr/`)
- **[ADR-001: Biometric Provider Abstraction](architecture/ADR-001-biometric-provider-abstraction.md)**
- **[ADR-002: Door Access & Attendance Integrity](architecture/ADR-002-door-access-attendance-integrity.md)**
- **[ADR-003: Consent Before Enrollment](architecture/ADR-003-consent-before-enrollment.md)**
- **[ADR-004: ADMS Push Protocol and Hardware Lifecycle Management](architecture/ADR-004-adms-push-protocol-and-lifecycle.md)**
- **[ADR-0001: Biometric System Architecture](../adr/adr-0001-biometric-implementation.md)**
- **[ADR-0002: Biometric Enrollment & Device Reconciliation](../adr/adr-0002-biometric-enrollment-and-device-reconciliation.md)**
- **[ADR-0003: Multi-Channel Biometric Consent Distribution & Dual-Gate Architecture](../adr/adr-0003-biometric-consent-distribution-and-dual-gate-architecture.md)**
- **[ADR-0004: Member Archival Lifecycle, Cryptographic Biometric Credential Erasure, and Access Revocation Architecture](../adr/adr-0004-member-archival-and-biometric-erasure-lifecycle.md)**

### 3. Database Schema & Field References (`docs/biometric/database/`)
- **[`biometric_devices`](database/biometric-devices-fields.md)**: Hardware devices, provider configurations, serial numbers, door zones, and ADMS toggle.
- **[`adms_device_commands`](database/adms-device-commands-fields.md)**: Queued server-to-device commands, payload parameters, execution status lifecycle, and device return codes.
- **[`biometric_templates`](database/biometric-templates-fields.md)**: Physical & cloud biometric credentials, sync statuses, erasure tracking, and deletion obligations.
- **[`attendances`](database/attendances-fields.md)**: Member check-in and check-out records, biometric confidence scores, verification methods, and door unlock statuses.
- **[`door_events`](database/door-events-fields.md)**: Physical door unlock audit trail, controller responses, and latency metrics.
- **[`gym_members`](database/gym-members-fields.md)**: Member profile fields, archive lifecycle status, and biometric consent relations.
