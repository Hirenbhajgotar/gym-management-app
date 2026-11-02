---
title: "ADR-0004: Member Archival Lifecycle, Cryptographic Biometric Credential Erasure, and Access Revocation Architecture"
status: "Accepted"
date: "2026-10-19"
authors: "Lead Architect, Security Engineer, Compliance & Backend Team"
tags: ["architecture", "decision", "member-archival", "biometrics", "gdpr", "data-protection", "erasure", "adms", "hardware-lifecycle", "authorization", "audit", "filament"]
supersedes: ""
superseded_by: ""
---

# ADR-0004: Member Archival Lifecycle, Cryptographic Biometric Credential Erasure, and Access Revocation Architecture

## Status

**Accepted**

## Context

In multi-tenant commercial gym operations, member turnover and inactivity are frequent. Members frequently pause subscriptions, relocate, or discontinue gym access without explicitly filing a formal data erasure or cancellation request. Over multi-year operating horizons, thousands of inactive member records accumulate across databases and on-premise access control hardware.

Managing this lifecycle presents several critical architectural, legal, and operational dilemmas:

1. **Access Revocation vs. Audit Integrity**:
   Hard-deleting or cascade-purging member accounts (`gym_members`) destroys billing ledgers, historical invoices, tax records, workout/BMI history, and access audit trails. Under corporate and tax regulations, accounting transactions must be retained for at least 7 years. Conversely, soft-deleting members or relying on global query scopes (`withoutArchived`) hides rows from relationship queries (such as `$member->payments`), breaking financial reports and lifetime-value calculations.
2. **Physical Security & Zombie Hardware Credentials**:
   Access revocation in a physical gym requires that credentials (fingerprints, facial vectors, PINs, RFID tokens) stop granting physical ingress at turnstiles and electromagnetic locks. When members lapse into inactivity, previously enrolled credentials often persist indefinitely in the local flash memory of standalone ADMS/iclock terminals (eSSL, ZKTeco) or in cloud biometric provider vaults (AWS Rekognition, Azure Face, BioID). If a member record is flagged "archived" in the database but hardware de-provisioning is skipped or delayed, the member can physically bypass turnstiles.
3. **GDPR Art. 17 & DPDP Act 2023 Compliance**:
   Biometric identifiers (fingerprint minutiae and facial embeddings) constitute sensitive personal data. Retaining live biometric templates for inactive data subjects violates data minimization principles. Furthermore, explicit member consent revocations and data erasure obligations must be guaranteed and recorded with tamper-evident audit trails.
4. **Transient Outages & Infinite Retry Loops**:
   Cloud biometric providers frequently experience transient rate limits, token rotation requirements, or occasional API deprecations. If an erasure obligation is retried indefinitely on an hourly schedule with no attempt budget or cooldown window, workers loop eternally, exhausting API quotas. Conversely, if a queue worker drops an erasure job after 3 quick retries across 5 minutes, an ephemeral network blip leaves a live remote credential permanently stranded.
5. **Hardware Command Races & Epoch Drift**:
   In push-protocol biometric devices (eSSL/ZKTeco via ADMS), server commands are polled asynchronously. When a member is archived, a schema-negotiated `DATA DELETE` command is queued. If that terminal is offline or repeatedly rejects the command up to the maximum retry cap, the command enters a dead-letter state. If the member later returns, unarchives, re-enrolls, and is archived again, naive deletion re-driving fails because the command's timestamp never advances and remains stuck at the attempt cap.
6. **Authorization & Worker Attribution Hazards**:
   Member archiving and unarchiving grant and revoke physical building access. Allowing low-privilege roles (e.g., receptionists or trainers uploading CSV spreadsheets) to trigger unarchive transitions compromises security. Furthermore, executing bulk archive transitions within queue workers or background CLI commands without explicit operator attribution risks inheriting ambient authentication context or executing unverified mutations.
7. **Webhook Gateway Vulnerabilities**:
   On-premise access control terminals that transmit real-time door release directives via HTTP webhooks (`/api/v1/biometric/webhook/{token}`) historically accepted URL tokens alone. Because URL tokens can leak into intermediate proxy logs, server access logs, and HTTP referrers, an attacker possessing the token could forge check-in events and trigger door release relays without physical authentication.

An integrated, end-to-end architecture was necessary to unify member archival, access revocation, marketing consent snapshots, multi-tier biometric credential erasure, operator discovery surfaces, and fail-closed webhook authentication.

## Decision

We designed and implemented a unified, multi-tiered **Member Archival Lifecycle, Cryptographic Biometric Credential Erasure, and Access Revocation Architecture**:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│             MEMBER ARCHIVAL & BIOMETRIC ERASURE LIFECYCLE ARCHITECTURE                │
└────────────────────────────────────────────────────────────────────────────────────────┘

 1. ADMINISTRATIVE & AUTHORIZATION TIER
    ┌───────────────────────────┐      ┌─────────────────────────────┐
    │ Spatie Role Permissions   │      │ Filament Admin Panel        │
    │  - archive_gym_member     │      │  - GymMemberResource Actions│
    │  - unarchive_gym_member   │      │  - Confirmation Modals      │
    └─────────────┬─────────────┘      └──────────────┬──────────────┘
                  │                                   │
                  ▼                                   ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │ MemberArchiveAuthorization (Fail-Closed, Explicit Operator)     │
    │   ├─ Operator must be non-null and hold ability                 │
    │   └─ Strict Spatie Team Tenant Context Scoping                  │
    └───────────────────────────────┬─────────────────────────────────┘
                                    │
 2. STATE MACHINE & ACCESS GATE TIER│
                                    ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │ MemberArchiveService (Pessimistic Row Lock: lockForUpdate)      │
    │   ├─ Set is_archived = true, archived_at = now()                │
    │   ├─ Snapshot allow_whatsapp_marketing -> snapshot column       │
    │   ├─ Force allow_whatsapp_marketing = false                     │
    │   └─ Opt-in Model Scope: notArchived() (No Global Scope)        │
    └───────────────────────────────┬─────────────────────────────────┘
                                    │
                                    ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │ Physical Access Decision Point (GymMember::canAccessGym())      │
    │   ├─ Evaluates $this->is_archived FIRST                         │
    │   ├─ Immediately halts door release / QR check-in / biometric   │
    │   └─ MembershipGuard reports rejection reason: member_archived  │
    └───────────────────────────────┬─────────────────────────────────┘
                                    │
 3. BIOMETRIC ERASURE & DE-PROVISIONING TIER
                                    ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │ BiometricDeProvisioner & BiometricDataDeletionService           │
    │   ├─ Collects all Live & Pending Credentials                    │
    │   ├─ Refuses Future Grants: assertMayHoldBiometricCredential()  │
    │   ├─ Flags Machine Erasures: is_erasure_obligation = true       │
    │   └─ Track Cooldown: erasure_first_attempted_at, attempts       │
    └──────────────────┬──────────────────────────────┬───────────────┘
                       │                              │
         ┌─────────────┴────────────┐   ┌─────────────┴──────────────┐
         ▼                          ▼   ▼                            ▼
   [Cloud Providers]       [Hardware ADMS]        [Cooldown Timer]  [Operator Surface]
   - AWS Rekognition       - eSSL / ZKTeco        - 24h Window      - Attention Widget
   - Azure Face            - Monotonic Epoch ID   - Caps Retries    - Relation Manager
   - Delete Template Ref   - Monitored user_delete- Prevents Floods - "Needs Operator"
```

### 1. Authorization and Single-Service Ownership
- **Dedicated Spatie Permissions**: We introduced `archive_gym_member` and `unarchive_gym_member` permissions, registered via migration `2026_10_13_000000_create_gym_member_archive_permissions.php`. Both permissions are assigned strictly to authorized tenant management roles (`gym_owner`, `manager`).
- **Fail-Closed Operator Attribution**: `App\Services\MemberArchiveAuthorization` rejects any mutation where the operator is not explicitly supplied or lacks permission. Ambient auth (`Auth::user()`) is forbidden to prevent queue workers or CLI processes from inheriting leftover credentials.
- **Single Owner Service**: `App\Services\MemberArchiveService` is the sole authority permitted to modify archival flags. All mutations use pessimistic database row locking (`lockForUpdate()`) inside atomic transactions.

### 2. Physical Access Revocation & Consent Snapshots
- **Primary Access Predicate**: `GymMember::canAccessGym()` evaluates `$this->is_archived` before reading subscription validity, payment status, or freeze states. If true, access is immediately refused. `MembershipGuard` categorizes the rejection as `member_archived`, preventing confusing "membership expired" messages.
- **Consent Round-Trip Snapshotting**: Archiving automatically snapshots `allow_whatsapp_marketing` into `whatsapp_marketing_snapshot` and forces `allow_whatsapp_marketing = false`. Unarchiving restores the exact preference from the snapshot. Importers that encounter archived records route updates through `MemberArchiveService::recordConsentWhileArchived()`.
- **Opt-In Model Scoping**: We explicitly avoided a global Eloquent scope (`withoutArchived`) because global scopes break financial reporting, payment lookups, and invoice history. Instead, an explicit `#[Scope] notArchived()` scope is used by active roster queries.
- **Mutation Freezing via `GuardsArchivedOwner`**: Sub-resources belonging to archived members (payments, body measurements, biometric templates) include the `GuardsArchivedOwner` trait. This trait renders informational warning banners and enforces server-side exceptions if any mutating operation is attempted on an archived member's related records.

### 3. Biometric Deprovisioning & Erasure Hardening
- **Synchronous Deprovisioning Cascade**: Upon archival, `MemberArchiveService` calls `BiometricDeProvisioner::deprovision()` for all templates in `liveOrPendingCredentialStates()` (`Pending`, `Propagated`, `Synced`, `PendingDeletion`).
- **Pre-Enrollment Denial**: `GymMember::assertMayHoldBiometricCredential()` throws `MemberArchivedException` if an enrollment is attempted on an archived member. `BiometricEnrollController` locks member rows and aborts enrollment if the member is archived.
- **Durable Erasure Obligations**:
  - `is_erasure_obligation` (boolean): Differentiates true member biometric templates from orphan markers and background cleanup records. Prevents subsequent enrollments from superseding erasure records or creating false GDPR compliance audit entries.
  - `erasure_attempts` & `erasure_first_attempted_at`: Tracks retry budgets based on elapsed time (24-hour cooldown) rather than execution count alone, preventing burst worker failures from triggering premature state exhaustion.
  - `BiometricTemplateSyncStatusEnum::DeletionUnresolvable`: A distinct terminal state for records with permanently unreadable provider references or providers that have exceeded cooldown periods. This removes the record from hourly polling loops while preserving it for operator review.
- **Hardware Epoch Credential Tracking**:
  - `epoch_credential_user_add_id` on `adms_device_commands`: Records the monotonic ID of the `user_add` command that established the credential. When a member is re-enrolled and archived again, the system recognizes a new credential epoch and properly re-drives terminal `user_delete` commands without hitting dead-letter caps.

### 4. Operator Discovery & Background Reconciliation
- **Automated Inactive Archival**: Console command `app:archive-inactive-gym-members` (`--years=2`, `--dry-run`) automatically evaluates members across all tenants whose memberships ended more than 2 years ago, processing them in chunks of 100 with per-member transaction isolation.
- **Device Member Cleanup Sweep**: `app:cleanup-expired-device-members` was expanded to sweep both expired and archived members across physical terminals.
- **Biometric Operator Attention Widget**: `BiometricOperatorAttentionWidget` sits on the Filament Admin dashboard, surfacing templates flagged as `DeletionUnresolvable` or hardware commands stuck at the delivery cap. `BiometricTemplatesRelationManager` provides a "Needs Operator" filter and a "Retry Erasure" modal action.

### 5. Fail-Closed Webhook HMAC Authentication
- **HMAC-SHA256 Signature Requirement**: `BiometricWebhookController` enforces that all incoming webhook requests from biometric terminals must supply a valid `X-Hub-Signature-256` or `X-Signature` matching the device's encrypted `webhook_secret`.
- **Fail-Closed Gate**: Terminals with no configured `webhook_secret` are rejected immediately with HTTP 401, eliminating insecure URL-token bearer access.
- **Secret Provisioning Tooling**: Console command `biometrics:provision-webhook-secrets` (`--gym=`, `--dry-run`, `--reveal`) generates cryptographically secure 64-character hex secrets for all unprovisioned devices.

## Consequences

### Positive

- **POS-001**: **Zero-Trust Physical Security**: Archived members are instantly denied entry at turnstiles and doors via in-memory checks, offline terminal command queues, and webhook gates.
- **POS-002**: **Regulatory Compliance (GDPR & DPDP Act)**: Cryptographic biometric identifiers are systematically erased from hardware devices and cloud vaults upon member archival, fulfilling data minimization mandates.
- **POS-003**: **Financial & Audit Integrity**: By rejecting global Eloquent scopes in favor of `notArchived()`, payment history, invoices, and lifetime value calculations remain fully intact.
- **POS-004**: **Idempotent Consent Round-Tripping**: Marketing communication preferences are safely locked during archival and perfectly restored on reactivation without state corruption.
- **POS-005**: **Resilient Erasure Retry Pipeline**: Time-based cooldowns (`erasure_first_attempted_at`) and monotonic hardware epoch IDs prevent infinite worker loops while avoiding premature exhaustion during brief cloud outages.
- **POS-006**: **Operator Transparency**: Dedicated dashboard widgets and relation manager filters guarantee that hardware or provider-level erasure failures cannot fail silently.

### Negative

- **NEG-001**: **Synchronous Archival Latency**: Synchronously triggering hardware command generation and provider-side de-provisioning during member archival introduces minor database latency (typically 50–150ms per member).
- **NEG-002**: **Manual Intervention for Unresolvable States**: Templates with corrupted or missing provider references require operator review via the `BiometricOperatorAttentionWidget` rather than being automatically purged.
- **NEG-003**: **Firmware Replay Limitations**: Because legacy on-premise biometric firmware does not transmit timestamps or nonces in webhook payloads, webhook HMAC verification validates body integrity and origin but cannot prevent network-level packet replay without hardware firmware updates.
- **NEG-004**: **Database Schema Overhead**: Adding columns (`is_erasure_obligation`, `erasure_attempts`, `erasure_first_attempted_at`, `epoch_credential_user_add_id`, `whatsapp_marketing_snapshot`) expands index sizes and schema complexity.

## Alternatives Considered

### Soft Deletes (`SoftDeletes` Trait on `GymMember`)

- **ALT-001**: **Description**: Apply Laravel's default `SoftDeletes` trait to `GymMember` and soft-delete members upon archival.
- **ALT-002**: **Rejection Reason**: Soft-deleting member models breaks foreign key constraints across existing payments, bookings, check-in history, and member dashboards. Restoring soft-deleted models often leads to cascade-restoration bugs and hides audit records from financial accountants.

### Global Query Scope (`withoutArchived`)

- **ALT-003**: **Description**: Apply a global Eloquent scope to filter out `is_archived = true` members across all application queries.
- **ALT-004**: **Rejection Reason**: Global scopes silently blank related models in relationships (e.g. `$payment->gymMember` returning `null`). This corrupts revenue exports, payroll reporting, and historical audit queries unless developers remember to append `withoutGlobalScopes()` everywhere.

### Asynchronous-Only Biometric Erasure Sweep

- **ALT-005**: **Description**: Flag the member as archived in the database and defer all biometric credential deletions to the nightly `app:cleanup-expired-device-members` cron job.
- **ALT-006**: **Rejection Reason**: Created a critical security vulnerability where an archived or expelled member could still scan their fingerprint/face at a turnstile and gain unauthorized physical entry for up to 24 hours.

### Unbounded Hourly Retry Loops for Failed Cloud Erasures

- **ALT-007**: **Description**: Re-run cloud provider template deletions on every hourly schedule run until the external API returns HTTP 200.
- **ALT-008**: **Rejection Reason**: If an external provider reference is deleted out-of-band or an API key is revoked, the reconciler repeatedly fails on every run, spamming logs, wasting queue resources, and delaying legitimate reconciliations.

## Implementation Notes

- **IMP-001**: **Permission Registration & Migration**:
  - Permissions `archive_gym_member` and `unarchive_gym_member` are created in migration `2026_10_13_000000_create_gym_member_archive_permissions.php` under the `web` guard and category `gym_member`.
  - Roles `gym_owner` and `manager` receive default grants.
- **IMP-002**: **Atomic State Transitions**:
  - `MemberArchiveService::archive()` and `unarchive()` use `DB::transaction()` and `GymMember::query()->lockForUpdate()`.
  - Operator permissions are asserted via `MemberArchiveAuthorization::assertCanArchive()` and `assertCanUnarchive()`.
- **IMP-003**: **Mutation Freezing in Admin UI**:
  - `GuardsArchivedOwner` is attached to `BiometricTemplatesRelationManager`, `MeasurementsRelationManager`, and `PaymentsRelationManager`.
  - Calling `guardArchivedOwner()` inside mutation hooks prevents Livewire parameter tampering.
- **IMP-004**: **Erasure Cooldown & Terminal Transition**:
  - Config key `biometrics.cloud_erasure_cooldown_hours` defaults to 24 hours.
  - When provider deletions fail, `erasure_first_attempted_at` is set. Once `now()->diffInHours(erasure_first_attempted_at) >= 24`, the status transitions to `BiometricTemplateSyncStatusEnum::DeletionUnresolvable`.
- **IMP-005**: **Hardware ADMS Command Epoch Tracking**:
  - `AdmsDeviceCommand` stores `epoch_credential_user_add_id`.
  - `BiometricDeProvisioner::hasUnconsumedHardwareCredential()` evaluates if a newer `user_add` command exists before re-driving terminal `user_delete` commands.
- **IMP-006**: **Webhook Secret Generation & Signing**:
  - Console command `biometrics:provision-webhook-secrets` populates encrypted `webhook_secret` values in `biometric_devices.provider_config`.
  - `BiometricWebhookController::verifySignature()` computes HMAC-SHA256 across `Request::getContent()` and rejects unauthenticated devices with HTTP 401.

## References

- **REF-001**: [ADR-0001: Biometric Hardware, Access Control, and Verification System Architecture](file:///docs/adr/adr-0001-biometric-implementation.md)
- **REF-002**: [ADR-0002: Biometric Enrollment and Device Reconciliation Architecture](file:///docs/adr/adr-0002-biometric-enrollment-and-device-reconciliation.md)
- **REF-003**: [ADR-0003: Multi-Channel Biometric Consent Distribution and Dual-Gated Notification Architecture](file:///docs/adr/adr-0003-biometric-consent-distribution-and-dual-gate-architecture.md)
- **REF-004**: [MemberArchiveService Core Implementation](file:///app/Services/MemberArchiveService.php)
- **REF-005**: [MemberArchiveAuthorization Policy Gate](file:///app/Services/MemberArchiveAuthorization.php)
- **REF-006**: [BiometricDeProvisioner Hardware & Cloud Deprovisioning Service](file:///app/Biometrics/Services/BiometricDeProvisioner.php)
- **REF-007**: [BiometricDataDeletionService Erasure Service](file:///app/Biometrics/Services/BiometricDataDeletionService.php)
- **REF-008**: [GuardsArchivedOwner Filament Relation Trait](file:///app/Traits/GuardsArchivedOwner.php)
- **REF-009**: [BiometricOperatorAttentionWidget Filament Table Widget](file:///app/Filament/Admin/Widgets/BiometricOperatorAttentionWidget.php)
- **REF-010**: [ArchiveInactiveGymMembersCommand Console Command](file:///app/Console/Commands/ArchiveInactiveGymMembersCommand.php)
- **REF-011**: [ProvisionBiometricWebhookSecretsCommand Console Command](file:///app/Console/Commands/ProvisionBiometricWebhookSecretsCommand.php)
- **REF-012**: [BiometricWebhookController API Controller](file:///app/Http/Controllers/Api/BiometricWebhookController.php)
- **REF-013**: [GymMember Archiving Test Suite](file:///tests/Feature/GymMemberArchivingTest.php)
- **REF-014**: [Biometric Erasure Hardening Test Suite](file:///tests/Feature/BiometricErasureHardeningTest.php)
- **REF-015**: [Biometric Archive Denial Test Suite](file:///tests/Feature/BiometricArchiveDenialTest.php)
