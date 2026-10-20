---
table: biometric_templates
last_updated: 2026-10-19
author: docs-agent
related_features:
  - ../feature/biometric-checkin-flow.md
  - ../feature/biometric-consent-system.md
  - ../feature/biometric-adms-push-protocol.md
  - ../../adr/adr-0002-biometric-enrollment-and-device-reconciliation.md
  - ../../adr/adr-0004-member-archival-and-biometric-erasure-lifecycle.md
---

# `biometric_templates` — Field Reference

## Purpose

Stores enrolled biometric credentials, facial vectors, fingerprint templates, and hardware reference tokens linked to `gym_members`. Handles synchronization status across physical edge devices (ADMS) and cloud providers (AWS Rekognition, Azure Face, BioID), tracking full lifecycle transitions from pending enrollment to archival deprovisioning and cryptographic erasure.

## Field Reference

| Column | Type | Nullable | Default | Written by | Purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO | auto | — | Primary key |
| `gym_member_id` | bigint unsigned | NO | — | Enrollment flow | Foreign key to `gym_members` |
| `gym_id` | bigint unsigned | NO | — | Enrollment flow | Foreign key to `gyms` (multi-tenant isolation) |
| `enrolled_by_device_id` | bigint unsigned | YES | null | Enrollment flow | Foreign key to `biometric_devices` (null for cloud-only) |
| `biometric_type` | varchar(255) | NO | — | Enrollment flow | `BiometricTypeEnum`: `face`, `fingerprint`, `palm`, `card` |
| `provider_type` | varchar(255) | NO | — | Enrollment flow | Provider key (`aws_rekognition`, `azure_face`, `bioid`, `adms`, `local`) |
| `template_ref` | text | YES | null | Enrollment flow | External provider reference token or JSON `TemplateRef` (encrypted cast) |
| `quality_score` | float | YES | null | Enrollment flow | Template quality or match confidence metric |
| `is_active` | tinyint(1) | NO | 1 | Lifecycle management | Whether credential actively permits door unlock & attendance check-in |
| `is_erasure_obligation` | tinyint(1) | NO | 0 | BiometricEnrollController / DeProvisioner | Distinguishes machine erasure obligations/orphan markers from real member enrollments |
| `erasure_attempts` | unsigned int | NO | 0 | BiometricDataDeletionService / DeProvisioner | Count of provider-side erase attempts executed against this row |
| `erasure_first_attempted_at` | timestamp | YES | null | BiometricDeProvisioner | Timestamp of first failed provider erasure attempt (anchors the 24h cooldown) |
| `erasure_requested_at` | timestamp | YES | null | BiometricDataDeletionService | Timestamp when explicit member erasure was requested (distinguishes GDPR Art. 17) |
| `sync_status` | varchar(255) | YES | pending | Sync lifecycle | `BiometricTemplateSyncStatusEnum` (see status table below) |
| `superseded_reason` | varchar(255) | YES | null | Template superseder | Reason retired: `re_enrolment` or `erasure_override` |
| `enrolled_at` | timestamp | YES | null | Enrollment flow | Timestamp of initial template enrollment |
| `hardware_confirmed_at` | timestamp | YES | null | ADMS ACK / Reconciler | Timestamp terminal confirmed credential write into local flash memory |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Creation timestamp |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Update timestamp |
| `deleted_at` | timestamp | YES | null | SoftDeletes | Soft deletion timestamp for audit retention |

## Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `PRIMARY` | `id` | PRIMARY | Primary key lookup |
| `biometric_templates_gym_member_id_foreign` | `gym_member_id` | INDEX | Member relationship queries |
| `biometric_templates_gym_id_foreign` | `gym_id` | INDEX | Multi-tenant tenant queries |
| `biometric_templates_enrolled_by_device_id_foreign` | `enrolled_by_device_id` | INDEX | Terminal lookup |
| `biometric_templates_sync_status_index` | `sync_status` | INDEX | Fast reconciliation & sweep querying |

## Relationships

| Relationship | Type | Foreign Key | Related Model |
|---|---|---|---|
| `gymMember()` | belongsTo | `gym_member_id` | `GymMember` |
| `gym()` | belongsTo | `gym_id` | `Gym` |
| `enrolledByDevice()` | belongsTo | `enrolled_by_device_id` | `BiometricDevice` |

## Enums: `BiometricTemplateSyncStatusEnum`

| Status Value | Badge Label | Color | Meaning & Lifecycle Role |
|---|---|---|---|
| `pending` | Pending | info | Newly enrolled; awaiting propagation or terminal confirmation |
| `propagated` | Propagated | warning | Dispatched across sibling devices or secondary terminals |
| `synced` | Synced | success | Confirmed live in hardware flash memory or active in cloud provider vault |
| `pending_deletion` | Pending Hardware Deletion | danger | Deletion obligation queued; awaiting terminal ACK or provider erase |
| `deleted` | Deleted | danger | Cryptographically erased from hardware and remote cloud providers |
| `superseded` | Superseded | gray | Replaced by newer enrollment or explicit erasure override |
| `deletion_unresolvable` | Unresolvable — Operator Required | danger | Terminal state: unreadable reference or provider cooldown exhausted; surfaced to admin |

## Key Methods & Scopes

| Method / Scope | Purpose |
|---|---|
| `scopeActive($query)` | Scopes query to active templates (`is_active = true`) |
| `scopeCloudBacked($query)` | Scopes query to external cloud AI providers |
| `scopeHardwareBacked($query)` | Scopes query to edge hardware devices |
| `representsLiveOrPendingCredential()` | Returns bool — true for `Pending`, `Propagated`, `Synced`, `PendingDeletion` |
| `requiresOperatorAttention()` | Returns bool — true if in `DeletionUnresolvable` or command at delivery cap |
| `wasDeliveredToHardware()` | Returns bool — true if `hardware_confirmed_at` is set or ADMS command sent |
| `getSyncStatusBadgeLabel()` | Returns human-readable label formatted for Filament tables |
| `getSyncStatusBadgeColor()` | Returns color token for Filament status badges |

## Migration History

| Migration | Change |
|---|---|
| `2024_xx_xx_create_biometric_templates_table` | Initial table creation |
| `2026_10_16_000000_add_erasure_obligation_to_biometric_templates_table` | Added `is_erasure_obligation` column to separate orphan/cleanup markers |
| `2026_10_17_000000_add_erasure_tracking_to_biometric_templates_table` | Added `erasure_attempts` and `erasure_requested_at` for GDPR tracking |
| `2026_10_17_000100_add_cloud_erasure_cooldown_to_biometric_templates_table` | Added `erasure_first_attempted_at` for time-based provider cooldown |
