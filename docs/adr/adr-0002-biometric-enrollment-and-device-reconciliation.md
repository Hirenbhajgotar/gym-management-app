---
title: "ADR-0002: Biometric Enrollment and Device Reconciliation Architecture"
status: "Accepted"
date: "2026-10-07"
authors: "Lead Architect, Backend Team, Security Team"
tags: ["architecture", "decision", "biometrics", "adms", "reconciliation", "access-control", "idempotency"]
supersedes: ""
superseded_by: ""
---

# ADR-0002: Biometric Enrollment and Device Reconciliation Architecture

## Status

**Accepted**

## Context

Biometric access control terminals deployed across gym facilities (specifically eSSL and ZKTeco terminals running the ADMS/iclock push protocol, alongside Hikvision ISAPI devices) communicate asynchronously via periodic polling (`/api/iclock/getrequest`) rather than persistent, full-duplex RPC connections. This architecture introduces several complex synchronization, security, and lifecycle challenges:

1. **Two-Phase Enrollment Separation**: Initiating biometric enrollment in the administrative dashboard (Filament) cannot immediately capture physical biometrics; it must provision a user profile placeholder (`Pin`, `Name`, `Pri=0`) on the target terminal first. The member then physically presents their fingerprint or face at the terminal keypad/sensor. Only after the hardware registers the template does the terminal push confirmation back via `/api/v1/biometric/device/sync-from-device` or ADMS upload.
2. **Multi-Terminal Gym Propagation**: Gym facilities frequently deploy multiple entry turnstiles, VIP doors, and exit gates. Manually repeating the physical enrollment process across every device is operationally unacceptable. When a member enrolls on any primary device, their biometric credential must propagate laterally to all active sibling ADMS devices within the same tenant gym without human intervention.
3. **State Drift and Hardware Divergence**: Edge terminals are prone to power outages, firmware resets, local memory purges, and intermittent WAN connectivity. While the cloud database may reflect an active `Synced` or `Propagated` template, the physical terminal may lose or fail to receive the credential. Conversely, network glitches may leave commands stranded in transit. A continuous self-healing mechanism is necessary to guarantee hardware convergence with database state.
4. **GDPR / DPDP Compliance & Anti-Resurrection Guarantees**: Under DPDP Act 2023 and GDPR Art. 17, biometrics cannot be enrolled or restored without verified active consent. Furthermore, when a member is revoked, expired, or requests erasure, an in-flight or pending `user_delete` command must take absolute precedence over enrollment or reconciliation commands. Reconcilers must never accidentally "resurrect" deleted users on hardware.
5. **Flash Memory Wear & Write Flood Prevention**: Embedded biometric terminals feature limited flash memory write endurance. If network failures or terminal rejections occur, unbounded retry loops or un-scoped cron re-drives could overwhelm terminal command buffers or exhaust flash storage write cycles.

## Decision

We designed and implemented a unified, idempotent, self-healing **Biometric Enrollment and Device Reconciliation Architecture** consisting of five core architectural components:

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│              BIOMETRIC ENROLLMENT & DEVICE RECONCILIATION ARCHITECTURE                  │
└─────────────────────────────────────────────────────────────────────────────────────────┘

 1. INITIATION TIER (Filament / Admin)
    ┌─────────────────────────┐
    │  EnrollBiometricAction  │ ──► Verify Fresh Consent ──► Cancel Delete Commands
    └───────────┬─────────────┘ ──► Supersede Old Modality ──► Create "Pending" Template
                │
                ▼ (Canonical Idempotency Key)
    ┌─────────────────────────┐
    │ AdmsDeviceCommand Queue │ (Status: PENDING, Type: user_add)
    └───────────┬─────────────┘
                │
                │ (ADMS Polling: leaseNext() -> SENT)
                ▼
 2. EDGE CAPTURE & CONFIRMATION TIER
    ┌─────────────────────────┐
    │  Physical Edge Scanner  │ (Member presents Finger/Face at Terminal)
    └───────────┬─────────────┘
                │
                ▼ (POST /api/v1/biometric/device/sync-from-device)
    ┌─────────────────────────┐
    │ BiometricEnrollControl. │ ──► Re-check Fresh Consent (Anti-TOCTOU)
    └───────────┬─────────────┘ ──► Promote to SYNCED & set hardware_confirmed_at
                │
                ▼
 3. LATERAL SIBLING PROPAGATION TIER
    ┌─────────────────────────┐
    │BiometricTemplatePropag. │ ──► Fan-out to all sibling ADMS devices in gym
    └───────────┬─────────────┘ ──► Create PROPAGATED templates & user_add commands
                │
 4. ASYNCHRONOUS COMMAND LEASE & ACKNOWLEDGMENT
    ┌─────────────────────────┐
    │   AdmsDeviceCommand     │ ──► Atomic CAS transition: PENDING -> SENT -> ACK / FAILED
    └───────────┬─────────────┘ ──► Deletion Convergence Codes (Art. 17 Non-Zero Ack)
                │
 5. SELF-HEALING RECONCILIATION ENGINE
    ┌─────────────────────────┐
    │ReconcileBiometricEnroll.│ ──► Scheduled scan of active Synced/Propagated templates
    └─────────────────────────┘ ──► Safety Guards: Consent, Active Membership, No Deletes
                                ──► Idempotent re-drive or replacement queueing
                                ──► Dead-Letter Cap (attempt_count >= maxAttempts breaker)
```

### 1. Two-Phase Enrollment Initiation (`EnrollBiometricAction`)
- **Strict Consent Enforcement**: Before any database mutation or hardware command is generated, `$member->hasActiveBiometricConsent(fresh: true)` verifies legal consent directly against the database inside a transaction.
- **PIN-Scoped Deletion Cancellation**: Row-level locking (`lockForUpdate()`) inspects existing templates for the member on the target device, collecting all resolved PINs and canceling any conflicting pending/in-flight `user_delete` commands via `AdmsDeviceCommand::cancelDeleteCommands()`.
- **Modality Superseding**: `BiometricTemplateSuperseder` retires obsolete templates of the identical biometric type to prevent conflicting credentials.
- **Pending Placeholder Creation**: Creates a `BiometricTemplate` record in `sync_status = 'pending'`, `is_active = false`, and `enrolled_at = null`.
- **Atomic Command Generation**: Queues an `AdmsDeviceCommand` of type `user_add` with payload `{'Pin': $pin, 'Name': $name}`, canceling preceding in-flight commands while protecting the newly queued key via `AdmsDeviceCommand::cancelInFlightFor()`.

### 2. Hardware Confirmation and Lateral Fan-Out (`BiometricEnrollController`)
- **TOCTOU Protection**: When the terminal posts `/api/v1/biometric/device/sync-from-device` with captured template metadata, consent is re-evaluated within the database transaction.
- **Terminal Confirmation Promotion**: Upon successful registration, the pending template is atomically promoted to `sync_status = 'synced'`, `is_active = true`, and stamped with `hardware_confirmed_at = now()`.
- **Transactional Lateral Sibling Propagation**: Within the same database transaction boundary, `BiometricTemplatePropagator` queries all other active ADMS terminals belonging to the same gym. It provisions `BiometricTemplate` records in `sync_status = 'propagated'` and queues matching `user_add` commands with stage-tagged idempotency keys.

### 3. Canonical Deterministic Idempotency Keys (`AdmsCommandKey`)
- All commands enforce a strict, parseable canonical idempotency key schema:
  $$\text{Key} = \text{user\_add}:\{\text{gym\_id}\}:\{\text{device\_id}\}:\{\text{member\_id}\}[:\{\text{stage}\}]:\{\text{template\_id}\}$$
- Stages delineate lifecycle phases: `STAGE_INITIAL`, `STAGE_SIBLING_SYNC`, and `STAGE_RECONCILE`.
- Prevents database duplicate insertions via unique constraint enforcement, allowing retry and reconciliation mechanisms to safely re-drive commands without duplicating hardware operations.

### 4. Atomic Command Leasing & CAS State Machine (`AdmsDeviceCommand`)
- Terminals pull commands via `AdmsDeviceCommand::leaseNext($deviceId)`. An atomic database transaction with `lockForUpdate()` evaluates `PENDING` commands and expired `SENT` leases.
- Compare-And-Set (CAS) semantics enforce that only `SENT` commands transition to terminal states (`ACKNOWLEDGED`, `FAILED`, `CANCELLED`).
- **Deletion Convergence**: For `user_delete` operations, firmware return codes indicating the user is already absent (e.g., `-1001`, `unknown user`) converge to `ACKNOWLEDGED`. This guarantees GDPR Art. 17 erasure completion without failing or stalling the command pipeline.

### 5. Scheduled Self-Healing Reconciliation Engine (`ReconcileBiometricEnrollmentsCommand`)
- Exposed via Artisan command `biometrics:reconcile-enrollments` (with `--dry-run` and `--gym={id}` options).
- Chunks active `Synced` and `Propagated` biometric templates whose target devices are active and ADMS-enabled.
- **Five Mandatory Reconciler Safety Guards**:
  1. *Consent Guard*: Member must hold active cryptographic consent (`hasActiveBiometricConsent()`).
  2. *Membership Guard*: Member must not be archived and their membership expiry date must not be in the past.
  3. *In-Flight Deletion Guard*: If a pending or sent `user_delete` exists for the member's PIN on the terminal, skip provisioning immediately to prevent access resurrection.
  4. *In-Flight Delivery Guard*: If a `user_add` command is currently `PENDING`, or `SENT` below `maxAttempts`, skip to avoid duplicate queuing.
  5. *Dead-Letter Breaker*: If previous reconciliation commands for the template reached or exceeded `maxAttempts`, skip and log a dead-letter warning, preventing terminal flash write exhaustion.
- **Self-Healing Execution**: If the latest command is missing or in a failed state under the attempt cap, the reconciler resets the failed command to `PENDING` or queues a replacement command tagged with `STAGE_RECONCILE`.

## Consequences

### Positive

- **POS-001**: **Deterministic Edge-Cloud Parity**: The reconciliation engine automatically detects and heals terminal flash desynchronization caused by device reboots, network dropped packets, or manual terminal wipes.
- **POS-002**: **Zero Manual Re-Enrollment for Multi-Door Gyms**: Lateral sibling propagation distributes captured credentials to all gym turnstiles and doors automatically within a single database transaction.
- **POS-003**: **Anti-Resurrection Access Security**: In-flight `user_delete` checks and membership expiry guards ensure expired, revoked, or deleted members are never re-provisioned by the reconciler.
- **POS-004**: **Hardware Flash Endurance Protection**: Delivery caps (`maxAttempts`) and dead-letter circuit breakers protect embedded terminal flash memory from unbounded retry spam during permanent hardware faults.
- **POS-005**: **Durable Legal Audit Trail**: `hardware_confirmed_at` timestamps on `BiometricTemplate` preserve durable evidence of physical credential existence even after ephemeral command logs are pruned.

### Negative

- **NEG-001**: **Eventual Parity Latency**: Sibling propagation and reconciliation depend on terminal pull intervals (`getrequest`), meaning secondary devices receive credentials within seconds to minutes rather than instantaneously.
- **NEG-002**: **Command Queue Table Growth**: Generating lateral fan-out commands across gyms with many terminals increases row churn in `adms_device_commands`, requiring automated model pruning (`AdmsDeviceCommand::prunable()`).
- **NEG-003**: **Operational Complexity in Dead-Letter Resolution**: Devices entering dead-letter status require administrative inspection or hardware re-registration to resume synchronization.

## Alternatives Considered

### Direct Synchronous Polling / Blocking WebSockets During Enrollment

- **ALT-001**: **Description**: Hold open HTTP/WebSocket connections from the Filament UI to the physical terminal during enrollment, blocking until the fingerprint sensor detects a touch.
- **ALT-002**: **Rejection Reason**: Commodity ADMS/iclock hardware (eSSL, ZKTeco) does not support inbound server push connections or persistent bi-directional WebSockets; devices only initiate outbound HTTP polling.

### Database Change-Data-Capture (CDC) / Triggers for Lateral Sync

- **ALT-003**: **Description**: Use database triggers or external CDC workers (e.g., Debezium) to detect newly synced templates and generate sibling commands.
- **ALT-004**: **Rejection Reason**: Bypasses Laravel's multi-tenant scoping, policy authorization, and cryptographic consent checks. Increases infrastructure dependency overhead for on-premise and single-server deployments.

### Blind Cron Re-Syncing All Active Members Daily

- **ALT-005**: **Description**: Run a daily script that iterates over all active members and blindly issues `user_add` commands to every terminal in the gym.
- **ALT-006**: **Rejection Reason**: Generates massive command queue bloat, saturates terminal polling bandwidth, degrades access control door latency during peak hours, and drastically accelerates embedded flash memory wear.

## Implementation Notes

- **IMP-001**: **Canonical Idempotency Key Specification**:
  - Implemented in `App\Biometrics\ValueObjects\AdmsCommandKey`.
  - Keys use format `user_add:{gym_id}:{device_id}:{member_id}[:stage]:{template_id}`.
  - Delete keys use format `user_delete:{device_id}:{pin}` (PIN-scoped because ADMS erases credentials at the PIN granularity).
- **IMP-002**: **Lease-Based Queue Architecture**:
  - Managed via `AdmsDeviceCommand::leaseNext()`. Default lease duration is 600 seconds (`biometrics.adms.command_lease_seconds`).
  - Stale leases with `leased_at < now() - lease_duration` or `expires_at < now()` are automatically reclaimed up to `AdmsCommandKey::maxAttempts()` (default: 3).
- **IMP-003**: **Reconciler Command Configuration**:
  - Registered as `biometrics:reconcile-enrollments` in `app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php`.
  - Scheduled in `bootstrap/app.php` or `routes/console.php` for periodic execution.
  - Supports `--dry-run` for safe dry-run audits without writing database commands.
- **IMP-004**: **Durable Delivery Tracking**:
  - `hardware_confirmed_at` timestamp in `biometric_templates` records terminal execution confirmation.
  - `AdmsDeviceCommand::reconcileUserAdd()` atomically promotes `sync_status = 'pending'` placeholders to `sync_status = 'synced'` and activates them upon terminal acknowledgment.
- **IMP-005**: **Soft Deletion & Audit Retention**:
  - Obsolete templates superseded during re-enrollment are retired with soft-delete timestamps and categorized with `superseded_reason`, maintaining DPDP/GDPR auditability without immediate irreversible data loss.

## References

- **REF-001**: [ADR-0001: Biometric Hardware, Access Control, and Verification System Architecture](file:///docs/adr/adr-0001-biometric-implementation.md)
- **REF-002**: [EnrollBiometricAction](file:///app/Filament/Admin/Actions/EnrollBiometricAction.php)
- **REF-003**: [ReconcileBiometricEnrollmentsCommand](file:///app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php)
- **REF-004**: [AdmsDeviceCommand Model](file:///app/Models/AdmsDeviceCommand.php)
- **REF-005**: [BiometricEnrollController](file:///app/Http/Controllers/Api/BiometricEnrollController.php)
- **REF-006**: [BiometricTemplatePropagator Service](file:///app/Biometrics/Services/BiometricTemplatePropagator.php)
- **REF-007**: [BiometricTemplateSuperseder Service](file:///app/Biometrics/Services/BiometricTemplateSuperseder.php)
- **REF-008**: [AdmsCommandKey Value Object](file:///app/Biometrics/ValueObjects/AdmsCommandKey.php)
