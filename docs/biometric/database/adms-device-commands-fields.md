---
table: adms_device_commands
last_updated: 2026-11-04
author: docs-agent
related_features:
  - ../feature/biometric-adms-push-protocol.md
  - ../feature/biometric-checkin-flow.md
  - ../feature/door-access-control.md
---

# `adms_device_commands` — Field Reference

## Purpose

Stores server-to-device commands queued for biometric hardware terminals operating over the ADMS (Automatic Data Master Server) protocol (such as eSSL and ZKTeco devices). When terminals poll the server via `GET /api/iclock/getrequest?SN={serial}`, pending commands are dispatched and tracked through their execution lifecycle.

## Field Reference

| Column | Type | Nullable | Default | Written by | Purpose |
|---|---|---|---|---|---|
| `id` | bigint unsigned | NO | auto | — | Primary key (used as command ID in ADMS protocol strings `C:{id}:...`) |
| `gym_id` | bigint unsigned | NO | — | Command creation | Foreign key to `gyms` |
| `device_id` | bigint unsigned | YES | — | Command creation | Foreign key to `biometric_devices`. Nulls when the device is deleted so the ledger survives its terminal |
| `device_serial` | varchar(255) | YES | null | Command creation | Snapshot of the terminal serial at queue time; retained after `device_id` is nulled |
| `command_type` | varchar(255) | NO | — | Command creation | Type of command: `user_add`, `user_delete`, `user_update`, `reboot`, `clear_log` |
| `command_payload` | json | YES | null | Command creation | Key-value arguments needed to format the command (e.g., `{"Pin": "42", "Name": "John"}`) |
| `user_table` | varchar(255) | YES | null | Command creation | Wire schema snapshotted at queue time (`USERINFO` or `user`). Null on pre-snapshot rows, which fall back to the configured default |
| `status` | varchar(255) | NO | pending | Command lifecycle | `AdmsCommandStatusEnum`: `pending`, `sent`, `acknowledged`, `failed` |
| `attempt_count` | unsigned int | NO | 0 | Command lease/delivery | Delivery attempt counter, capped at max attempts |
| `epoch_credential_user_add_id` | bigint unsigned | YES | null | BiometricDeProvisioner | ID of the `user_add` command that established the credential epoch |
| `sent_at` | timestamp | YES | null | Device polling | Timestamp when the terminal fetched the command via `getrequest` |
| `acknowledged_at` | timestamp | YES | null | Device execution | Timestamp when the terminal reported execution result via `devicecmd` |
| `device_return_code` | varchar(255) | YES | null | Device execution | Raw return code string from device (`0` = success, non-zero = error) |
| `created_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Creation timestamp |
| `updated_at` | timestamp | NO | CURRENT_TIMESTAMP | — | Update timestamp |

## Indexes

| Index | Columns | Type | Purpose |
|---|---|---|---|
| `PRIMARY` | `id` | PRIMARY | Primary key lookup |
| `adms_device_commands_device_id_status_index` | `device_id`, `status` | INDEX | Rapid O(1) polling lookup for oldest pending command per device |
| `adms_device_commands_gym_id_foreign` | `gym_id` | INDEX | Foreign key constraint on `gyms` |
| `adms_device_commands_device_id_foreign` | `device_id` | INDEX | Foreign key constraint on `biometric_devices` |

## Relationships

| Relationship | Type | Foreign Key | Related Model |
|---|---|---|---|
| `gym()` | belongsTo | `gym_id` | `Gym` |
| `device()` | belongsTo | `device_id` | `BiometricDevice` (null after device deletion) |

## Key Methods & Scopes

| Method / Scope | Purpose |
|---|---|
| `toAdmsCommandString()` | Builds the formatted protocol string for the command's snapshotted schema (e.g. `C:12:DATA DELETE USERINFO PIN=42`) |
| `markSent()` | Transitions status to `AdmsCommandStatusEnum::SENT` and records `sent_at` timestamp |
| `markAcknowledged(string $returnCode)` | Transitions status to `ACKNOWLEDGED` (if code `0`) or `FAILED`, setting `acknowledged_at` |
| `prunable()` | Returns Builder for acknowledged/failed commands past `command_retention_days` (default 90d) |
| `scopePending($query)` | Scopes query to commands with `status = AdmsCommandStatusEnum::PENDING` |
| `scopeForDevice($query, int $deviceId)` | Scopes query to specific device ID |

## Enums

| Enum | Values | Applied To |
|---|---|---|
| `AdmsCommandStatusEnum` | `PENDING = 'pending'`, `SENT = 'sent'`, `ACKNOWLEDGED = 'acknowledged'`, `FAILED = 'failed'` | `status` column |

## Command String Formats

The ADMS push protocol uses colon-delimited string commands returned in response to `getrequest`. In accordance with [ADR-0007](../../adr/adr-0007-biometric-adms-multi-device-table-schema-negotiation.md) and [ADR-0009](../../adr/adr-0009-biometric-adms-userinfo-dual-token-name-standardization.md), commands support both the default `USERINFO` and the legacy/modern `user` schema. The schema is snapshotted on the row when the command is queued (`user_table`), not resolved from the device at delivery. The terminal serial is snapshotted alongside it (`device_serial`) so the row still names its hardware after the device is deleted.

No user field other than the identifying `Pin`/`PIN` and `Name` is serialized. `Passwd`/`Card` were removed because no producer ever wrote them.

- **Update / Add user (Default `USERINFO` per ADR-0009)**:
  `C:{id}:DATA UPDATE USERINFO PIN={memberId}\tName={name}\tNAME={name}\tPri=0\tPrivilege=0`
- **Delete user from hardware memory (Default `USERINFO`)**:
  `C:{id}:DATA DELETE USERINFO PIN={memberId}`
- **Update / Add user (Alternative `user`)**:
  `C:{id}:DATA UPDATE user Pin={memberId}\tName={name}\tPri=0`
- **Delete user from hardware memory (Alternative `user`)**:
  `C:{id}:DATA DELETE user Pin={memberId}`
- **Reboot hardware terminal**:
  `C:{id}:REBOOT`
- **Clear terminal event logs**:
  `C:{id}:CLEAR LOG`

## Usage by Feature

| Feature | Reads | Writes | Notes |
|---|---|---|---|
| [Biometric ADMS Push Protocol](../feature/biometric-adms-push-protocol.md) | `device_id`, `status`, `command_type`, `command_payload` | `status`, `sent_at`, `acknowledged_at`, `device_return_code`, `attempt_count` | Serves commands to polling terminals & handles execution ACKs |
| [Member Archival Lifecycle](../../adr/adr-0004-member-archival-and-biometric-erasure-lifecycle.md) | `epoch_credential_user_add_id`, `idempotency_key` | `epoch_credential_user_add_id`, `status` | Re-drives dead-lettered user_delete commands across credential epochs |
| Expired Member Cleanup Command | `device_id`, `command_type`, `command_payload`, `status` | Inserts new command | Queues user deletion when membership expires beyond grace window |
| Filament Admin Resource (`AdmsDeviceCommandResource`) | All fields | `status`, `sent_at`, `acknowledged_at`, `device_return_code` | Allows gym admins to audit and resend failed commands |

## Migration History

| Migration | Change |
|---|---|
| `2026_09_23_133303_create_adms_device_commands_table` | Initial table creation with compound index on `(device_id, status)` |
| `2026_10_18_000000_add_epoch_credential_to_adms_device_commands_table` | Added `epoch_credential_user_add_id` column to track credential epochs |
| `2026_10_31_000100_add_user_table_to_adms_device_commands_table` | Added `user_table` and backfilled queued commands from their device's schema override |
| `2026_10_31_000200_keep_adms_command_ledger_after_device_deletion` | Made `device_id` nullable with `nullOnDelete` and added the `device_serial` snapshot |
| `2026_10_31_000300_preserve_access_control_audit_after_device_deletion` | Made `door_events.device_id` and `biometric_event_receipts.device_id` nullable with `nullOnDelete`; added the `door_events.device_serial` snapshot |
