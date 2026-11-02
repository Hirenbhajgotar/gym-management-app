---
table: door_events
last_updated: 2026-04-03
author: docs-agent
related_features:
  - ../feature/door-access-control.md
  - ../feature/biometric-checkin-flow.md
---

# `door_events` — Field Reference

## Purpose

Audit log for all door lock/unlock attempts. Every door event (granted, denied, failed) is recorded regardless of outcome for security auditing and troubleshooting. This table exists specifically for the door access control feature.

## Field Reference

| Column | Type | Nullable | Default | Written by | Purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO | auto | — | Primary key |
| `gym_id` | bigint unsigned | NO | — | DoorControlService | Foreign key to gyms |
| `device_id` | bigint unsigned | YES | null | DoorControlService | Foreign key to biometric_devices; nulls on device deletion so the event is retained |
| `device_serial` | varchar(255) | YES | null | DoorControlService / migration backfill | Snapshot of the terminal serial, retained after the device row is deleted |
| `gym_member_id` | bigint unsigned | YES | null | DoorControlService | Foreign key to gym_members (nullable - may be null on denial) |
| `attendance_id` | bigint unsigned | YES | null | DoorControlService | Foreign key to attendances (nullable - may be null if no attendance) |
| `event_type` | varchar(50) | NO | — | DoorControlService | unlock_granted, unlock_denied, unlock_failed, lock_confirmed |
| `trigger_source` | varchar(20) | NO | biometric | DoorControlService | biometric, manual_staff, emergency |
| `door_status` | varchar(20) | NO | — | DoorControlService | unlocked, locked, failed, skipped |
| `denial_reason` | varchar(50) | YES | null | DoorControlService | Why access denied |
| `unlock_duration_seconds` | int | YES | null | DoorControlService | How long door stayed unlocked |
| `unlocked_at` | timestamp | YES | null | DoorControlService | When door was unlocked |
| `ip_address` | varchar(45) | YES | null | DoorControlService | Request IP |
| `raw_response` | json | YES | null | DoorControlService | Raw response from door controller |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Creation timestamp |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Update timestamp |

## Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `PRIMARY` | `id` | PRIMARY | — |
| `door_events_gym_id_created_at_index` | `gym_id`, `created_at` | INDEX | Audit queries by gym and date |
| `door_events_gym_member_id_created_at_index` | `gym_member_id`, `created_at` | INDEX | Member door history |
| `door_events_device_id_created_at_index` | `device_id`, `created_at_index` | INDEX | Device door history |

## Relationships

| Relationship | Type | Foreign Key | Related Model |
|---|---|---|---|
| `device()` | belongsTo | `device_id` | `BiometricDevice` |
| `gymMember()` | belongsTo | `gym_member_id` | `GymMember` |
| `attendance()` | belongsTo | `attendance_id` | `Attendances` |
| `gym()` | belongsTo | `gym_id` | `Gym` |

## Usage by Feature

| Feature | Reads | Writes | Notes |
|---|---|---|---|
| [Biometric Check-in Flow](../feature/biometric-checkin-flow.md) | — | All fields | Logs every door attempt from check-in |
| [Door Access Control](../feature/door-access-control.md) | All fields | All fields | Main write source for this table |

## Event Types

| Event Type | When Created |
|---|---|
| `unlock_granted` | Access granted, door unlocked (API success or relay/wiegand) |
| `unlock_denied` | Access denied due to membership/verification issues |
| `unlock_failed` | Door unlock attempted but failed (API unreachable or error) |
| `lock_confirmed` | Door returned to locked state (optional, depends on hardware) |

## Door Status Values

| Status | Meaning |
|---|---|
| `unlocked` | Door successfully opened |
| `locked` | Access denied, door stayed locked |
| `failed` | Unlock attempted but failed |
| `skipped` | Relay/wiegand - success assumed (no confirmation available) |

## Trigger Sources

| Source | Meaning |
|---|---|
| `biometric` | Triggered from biometric check-in |
| `manual_staff` | Staff manually unlocked via admin panel |
| `emergency` | Emergency unlock (fire alarm, etc.) |

## Migration History

| Migration | Change |
|---|---|
| `2026_03_28_000003_create_door_events_table` | Initial table creation |
| `2026_10_31_000300_preserve_access_control_audit_after_device_deletion` | `device_id` becomes nullable with `nullOnDelete()`; adds `device_serial` and backfills it |

## Retention Semantics

`device_id` is nullable and its foreign key is `nullOnDelete()`: deleting a biometric terminal must not destroy its
access-control history. The `device_serial` snapshot taken at write time (and backfilled for pre-existing rows) keeps
the "which hardware" attribution for a retained event whose device row is gone. A `null` `device_id` therefore means
"the terminal row was deleted", not "no terminal was involved". The migration's `down()` refuses to restore the
restrictive key while such rows exist.

## Notes

- Every door attempt logged regardless of success or failure
- gym_member_id nullable because denial may occur before member identification
- attendance_id nullable because door may unlock without creating attendance (door_access_no_attendance)
- raw_response contains full API response for troubleshooting
- IP address captured for security auditing
