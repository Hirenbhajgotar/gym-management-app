---
table: biometric_devices
last_updated: 2026-04-03
author: docs-agent
related_features:
  - ../feature/biometric-provider-abstraction.md
  - ../feature/biometric-checkin-flow.md
  - ../feature/door-access-control.md
  - ../feature/biometric-adms-push-protocol.md
---

# `biometric_devices` — Field Reference

## Purpose

Stores biometric hardware devices deployed at gym locations. Each device has a provider type (how it processes biometrics), purpose (what it does), and optional door control configuration.

## Field Reference

| Column | Type | Nullable | Default | Written by | Purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO | auto | — | Primary key |
| `gym_id` | bigint unsigned | NO | — | Device creation | Foreign key to gyms |
| `device_name` | varchar(255) | NO | — | Device creation | Display name |
| `device_type` | varchar(50) | NO | all_in_one | Device creation | BiometricTypeEnum: all_in_one, face, fingerprint, card, iris |
| `provider_type` | varchar(50) | YES | null | Device configuration | rekognition, azure_face, bioid, on_premise, custom |
| `provider_config` | text | YES | null | Device configuration | Encrypted JSON with provider credentials |
| `status` | enum | NO | active | Device management | BiometricDeviceStatusEnum: active, inactive, maintenance |
| `assigned_staff_id` | bigint unsigned | YES | null | Device assignment | FK to users |
| `last_heartbeat_at` | timestamp | YES | null | Device heartbeat | Last time device checked in |
| `device_token` | varchar(255) | YES | null | Device auth | Hashed token for API auth |
| `created_by` | bigint unsigned | NO | — | Device creation | FK to users |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Creation timestamp |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Update timestamp |

### Purpose & Door Fields (added by migration 2026_03_28_000001)

| Column | Type | Nullable | Default | Written by | Purpose |
|---|---|---|---|---|---|
| `purpose` | varchar(50) | NO | attendance_only | Device configuration | BiometricDevicePurpose: attendance_only, door_access, door_access_no_attendance |
| `door_control_type` | varchar(20) | YES | null | Door config | relay, wiegand, api |
| `door_controller_url` | varchar(500) | YES | null | Door config | URL for API-based door control |
| `door_controller_token` | varchar(255) | YES | null | Door config | Encrypted auth token for door API |
| `unlock_duration_seconds` | int | NO | 5 | Door config | How long door stays unlocked |
| `door_zone` | varchar(50) | YES | null | Door config | Label: main_entrance, gym_floor, pool, etc. |
| `connection_mode` | varchar(20) | NO | webhook | Device config | webhook (device POSTs) or poll (FluxFitness polls) |
| `poll_interval_seconds` | int | NO | 60 | Device config | How often to poll (if poll mode) |
| `last_polled_at` | timestamp | YES | null | Device poll | Last time FluxFitness polled |

### ADMS Protocol Fields (added by migration 2026_09_23_133154)

| Column | Type | Nullable | Default | Written by | Purpose |
|---|---|---|---|---|---|
| `device_serial` | varchar(255) | YES | null | Device configuration | Hardware serial number (SN) used by ADMS push protocol (eSSL, ZKTeco) |
| `adms_enabled` | boolean | NO | false | Device configuration | Enables ADMS push endpoint and member sync queue for this device |

## Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `PRIMARY` | `id` | PRIMARY | — |
| `biometric_devices_gym_id_status_index` | `gym_id`, `status` | INDEX | Device list queries |
| `unique_gym_device_serial` | `gym_id`, `device_serial` | UNIQUE | Ensures unique terminal SN within tenant |

## Relationships

| Relationship | Type | Foreign Key | Related Model |
|---|---|---|---|
| `gym()` | belongsTo | `gym_id` | `Gym` |
| `assignedStaff()` | belongsTo | `assigned_staff_id` | `User` |
| `createdBy()` | belongsTo | `created_by` | `User` |
| `templates()` | hasMany | `enrolled_by_device_id` | `BiometricTemplate` |
| `doorEvents()` | hasMany | `device_id` | `DoorEvent` |
| `admsCommands()` | hasMany | `device_id` | `AdmsDeviceCommand` |

## Usage by Feature

| Feature | Reads | Writes | Notes |
|---|---|---|---|
| [Biometric Provider Abstraction](../feature/biometric-provider-abstraction.md) | provider_type, provider_config | provider_type, provider_config | Resolves provider at check-in |
| [Biometric Check-in Flow](../feature/biometric-checkin-flow.md) | All fields | — | Uses purpose to branch logic |
| [Door Access Control](../feature/door-access-control.md) | door_control_type, door_controller_url, etc. | — | Controls door unlock behavior |
| [Biometric ADMS Push Protocol](../feature/biometric-adms-push-protocol.md) | device_serial, adms_enabled, last_heartbeat_at | last_heartbeat_at | Handles eSSL/ZKTeco push protocol and command polling |

## Key Methods

| Method | Purpose |
|---|---|
| `findBySerial(string $serial)` | Returns ?BiometricDevice — Redis-cached O(1) lookup by device serial number for enabled devices (resolves both Active and Inactive for auto-recovery) |
| `isAttendanceOnly()` | Returns bool — purpose = attendance_only |
| `isDoorAccess()` | Returns bool — purpose = door_access or door_access_no_attendance |
| `shouldCreateAttendance()` | Returns bool — purpose = attendance_only or door_access |
| `hasDoorApiControl()` | Returns bool — door_control_type = api and URL configured |
| `verifyToken($plainToken)` | Returns bool — validates device token |

## Enums

| Enum | Values | Applied To |
|---|---|---|
| `BiometricDeviceStatusEnum` | active, inactive, maintenance | `status` column |
| `BiometricDevicePurpose` | attendance_only, door_access, door_access_no_attendance | `purpose` column |
| `BiometricDoorControlType` | relay, wiegand, api | `door_control_type` column |

## Migration History

| Migration | Change |
|---|---|
| `2026_03_23_120000_create_biometric_devices_table` | Initial table creation |
| `2026_03_28_000001_add_purpose_and_door_columns_to_biometric_devices_table` | Added purpose and door control fields |
| `2026_09_23_133154_add_adms_columns_to_biometric_devices_table` | Added `device_serial` and `adms_enabled` fields with unique constraint |

## Notes

- `device_token` stored as hashed (like passwords) for security
- `provider_config` encrypted - contains API keys/secrets
- `door_controller_token` encrypted separately
- `purpose` drives ALL business logic in CheckInService
- `door_control_type` only relevant when purpose != attendance_only
- `device_serial` is cached in Redis for fast O(1) ADMS request routing
- Deadman switch (`biometrics:check-health`) marks devices `inactive` when consecutive missing heartbeats reach threshold (`heartbeat_consecutive_misses`, default 1)
- Inactive devices transmitting authenticated ADMS communication automatically recover to `active` status
