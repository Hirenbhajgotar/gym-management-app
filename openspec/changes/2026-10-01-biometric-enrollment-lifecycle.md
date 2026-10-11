# OpenSpec Change Proposal: Biometric Enrollment Lifecycle & UI Consolidation

**ID**: `2026-10-01-biometric-enrollment-lifecycle`  
**Status**: Accepted / Implemented  
**Date**: 2026-10-01  
**Author**: Engineering Team  

## Context & Motivation

Historically, biometric templates could be manually entered via raw base64 data through an `EnrollMember` standalone page or relation manager create actions. This exposed sensitive unencrypted biometric PII and bypassed physical hardware verification. Furthermore, maintaining two parallel enrollment UIs (`EnrollMember` page and `EnrollBiometricAction` modal) caused divergence in command cancellation, idempotency key generation, and state management.

## Capabilities Changed

1. **Deprecation & Removal of Standalone `EnrollMember` Page**:
   - The `/admin/enroll-member` standalone page and its Blade view have been deleted.
   - All biometric enrollments are initiated via the unified modal action `EnrollBiometricAction` accessible from the Gym Member Resource (tables and member edit header).

2. **Removal of Manual Base64 Template Entry**:
   - Creating biometric templates without physical hardware interaction is removed.
   - Enrollment follows an ADMS push protocol workflow:
     1. Operator initiates enrollment via `EnrollBiometricAction`.
     2. A `Pending` placeholder is created in the database (`is_active = false`, `enrolled_at = null`, `template_ref = null`).
     3. An ADMS `user_add` command (`{Pin, Name}`) is queued for the target device.
     4. The operator completes physical fingerprint/biometric capture directly on the device keypad/sensor.
     5. The terminal sends `POST /api/v1/biometric/enroll-sync`, which promotes the `Pending` placeholder to `Synced` (`is_active = true`, `enrolled_at = now()`).
     6. Sibling terminals in the same gym are automatically provisioned with `{Pin, Name}` via `BiometricTemplatePropagator`.

3. **Lifecycle States & Invariants**:
   - `Pending`: Hardware enrollment initiated; awaiting operator/member scan at device (placeholder with `template_ref = null`, subject to 7-day abandoned cleanup).
   - `Propagated`: Dispatched to sibling terminal via ADMS fan-out; excluded from 7-day placeholder cleanup, actively monitored by nightly reconciler, and subject to GDPR de-provisioning.
   - `Synced`: Biometric confirmation received from terminal; active for gym access.
   - `PendingDeletion`: De-provisioning requested; awaiting confirmation from hardware terminal.
   - `Superseded`: Re-enrolled or replaced by a newer template; kept for audit/retention.
   - `Deleted`: Permanently deactivated and removed from hardware.

4. **Security & GDPR Consent Gate (ADR-003)**:
   - All enrollment write sites strictly enforce active `BiometricConsent` prior to command queueing, re-verified within database transactions with fresh DB queries.
   - Revoking consent denies attendance punches at the server and queues hardware de-provisioning (`user_delete`) commands across all provisioned terminals (`Synced` and `Propagated`).

5. **Failure Observability & Retry (ADR-004)**:
   - Sibling fan-out failures persist `FAILED` `AdmsDeviceCommand` rows so operators can inspect failure reasons and retry failed dispatches directly in the Filament panel.

6. **Nightly Self-Healing Reconciler**:
   - Scheduled command `biometrics:reconcile-enrollments` ensures active `Synced` and `Propagated` templates maintain healthy hardware command state, skipping archived members, expired memberships, and active hardware de-provisionings.

7. **Hardware Fan-out Invariant (SF19 / ADR-005)**:
   - Standalone ADMS biometric devices (e.g., eSSL, ZKTeco) perform on-device 1:N template matching for attendance check-ins. Therefore, both `DoorAccess` and `AttendanceOnly` devices receive propagated member credentials.

