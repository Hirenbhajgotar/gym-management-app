---
table: attendances
last_updated: 2026-04-03
author: docs-agent
related_features:
  - ../feature/biometric-checkin-flow.md
  - ../feature/door-access-control.md
---

# `attendances` — Field Reference

## Purpose

Records member check-in/check-out events. Uses polymorphic relationship (`attendanceable`) to support both GymMember and potentially other entities (Staff, etc.). Extended with biometric and door fields for the biometric check-in system.

## Field Reference

| Column | Type | Nullable | Default | Written by | Purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO | auto | — | Primary key |
| `attendanceable_type` | varchar(255) | NO | — | Check-in | Polymorphic type (GymMember::class) |
| `attendanceable_id` | bigint unsigned | NO | — | Check-in | Polymorphic ID (member ID) |
| `gym_id` | bigint unsigned | NO | — | Check-in | Foreign key to gyms |
| `check_in_time` | timestamp | YES | null | Check-in | When member checked in |
| `check_out_time` | timestamp | YES | null | Check-out | When member checked out |
| `status` | enum | YES | null | Check-in/check-out | AttendanceStatus (Present/Completed/Absent) |
| `note` | text | YES | null | Admin | Optional note |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Creation timestamp |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Update timestamp |

### Biometric Fields (added by migration 2026_03_23_120003)

| Column | Type | Nullable | Default | Written by | Purpose |
|---|---|---|---|---|---|
| `device_id` | bigint unsigned | YES | null | Biometric check-in | FK to biometric_devices |
| `check_in_method` | varchar(50) | YES | null | Check-in | How checked in: null (manual), biometric, card, mobile |
| `biometric_type` | varchar(20) | YES | null | Biometric check-in | Type: face, fingerprint, iris |
| `provider_type` | varchar(50) | YES | null | Biometric check-in | Provider: rekognition, azure_face, etc. |
| `confidence_score` | float | YES | null | Biometric check-in | 0.0-1.0 match confidence |
| `liveness_score` | float | YES | null | Biometric check-in | 0.0-1.0 liveness score |
| `fusion_score` | float | YES | null | Biometric check-in | Combined score if multi-modal |
| `check_in_result` | varchar(20) | YES | null | Check-in | granted, denied, suspicious |
| `denial_reason` | varchar(50) | YES | null | Check-in | Why denied: membership_expired, etc. |
| `synced_at` | timestamp | YES | null | Offline sync | When attendance synced from offline device |
| `check_in_ip` | varchar(45) | YES | null | Check-in | IP address of check-in request |

### Door Fields (added by migration 2026_03_28_000002)

| Column | Type | Nullable | Default | Written by | Purpose |
|---|---|---|---|---|---|
| `door_status` | varchar(20) | YES | null | Door access | unlocked, locked, failed, skipped, not_applicable |
| `door_denial_reason` | varchar(50) | YES | null | Door access | Why door stayed locked |
| `door_unlocked_at` | timestamp | YES | null | Door access | When door was unlocked |

## Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `PRIMARY` | `id` | PRIMARY | — |
| `attendances_gym_id_foreign` | `gym_id` | INDEX | Multi-tenant queries |
| `attendanceable` | `attendanceable_type`, `attendanceable_id` | INDEX | Polymorphic queries |
| `attendances_check_in_time_index` | `check_in_time` | INDEX | Date range queries |
| `attendances_status_check_in_time_index` | `status`, `check_in_time` | INDEX | Active attendance queries |
| `biometric_templates_lookup_idx` | `gym_member_id`, `gym_id`, `biometric_type`, `is_active` | INDEX | Biometric lookup |

## Relationships

| Relationship | Type | Foreign Key | Related Model |
|---|---|---|---|
| `attendanceable()` | MorphTo | `attendanceable_id`, `attendanceable_type` | GymMember or other |
| `gym()` | belongsTo | `gym_id` | `Gym` |
| `device()` | belongsTo | `device_id` | `BiometricDevice` |

## Usage by Feature

| Feature | Reads | Writes | Notes |
|---|---|---|---|
| [Biometric Check-in Flow](../feature/biometric-checkin-flow.md) | All fields | All biometric + door fields | Creates attendance on successful check-in |
| [Door Access Control](../feature/door-access-control.md) | door_status, door_unlocked_at | door_status, door_unlocked_at | Updates door status on existing attendance |

## Key Methods / Scopes

| Method/Scope | Purpose |
|---|---|
| `scopeActive()` | Returns currently checked-in (status=Present, check_out_time=null) |
| `scopeCompleted()` | Returns finished sessions (status=Completed) |
| `scopeAbsent()` | Returns denied attempts (status=Absent) |
| `scopeByDate($date)` | Filter by specific date |
| `isBiometric()` | Returns bool — check_in_method = 'biometric' |
| `isOfflineSync()` | Returns bool — synced_at is not null |

## Migration History

| Migration | Change |
|---|---|
| `2024_xx_xx_create_attendances_table` | Initial table creation |
| `2026_03_23_120003_add_biometric_columns_to_attendances_table` | Added biometric check-in fields |
| `2026_03_28_000002_add_door_columns_to_attendances_table` | Added door control fields |

## Notes

- Polymorphic relationship allows future expansion (staff attendance, etc.)
- Biometric fields allow auditing of who/when/how for every check-in
- Door fields tie attendance to physical access events
- `check_in_method = null` means manual entry (backward compatible)
