---
title: "ADR-0007: Biometric ADMS Multi-Device Table Schema Negotiation and Wire Protocol Standardization"
status: "Accepted"
date: "2026-10-31"
authors: "Lead Architect, Hardware Integration Engineer, DevOps Engineer"
tags: ["architecture", "decision", "biometrics", "adms", "hardware-integration", "zkteco", "essl", "push-protocol", "multi-device"]
supersedes: ""
superseded_by: ""
---

# ADR-0007: Biometric ADMS Multi-Device Table Schema Negotiation and Wire Protocol Standardization

## Status

**Accepted**

## Context

Following the implementation of ADR-0006 (which introduced native `/iclock/*` routing, `.aspx` suffix compatibility, and plaintext HTTP port 80 bypass), physical biometric terminals (running `pushver=2.4.1`, User-Agent `iClock Proxy/1.09`) successfully completed network handshakes with the production server at `fluxfitness.in`.

However, during active member enrollment dispatching, the terminal immediately rejected queued `user_add` commands. The terminal reported execution results to `/iclock/devicecmd` with:
```json
{
  "device_id": 2,
  "command_id": 3,
  "return_code": "-1004",
  "status": "failed"
}
```

In the ZKTeco PUSH SDK and Attendance PUSH Communication Protocol specifications, return code **`-1004`** explicitly defines:
> **"The data and equipment configuration are inconsistent"** *(Table, schema, or feature not supported on the target device model).*

### Root Cause Analysis

Investigation into the command generation pipeline in `AdmsDeviceCommand::toAdmsCommandString()` revealed that the application was transmitting:
```text
C:3:DATA UPDATE user Pin=369\tName=Hiren Sarla\tPri=0
```

Across the commercial biometric hardware ecosystem (ZKTeco, eSSL, Biomax, and OEM variants), database schema conventions diverge significantly across hardware generations:

1. **Standard / Legacy Firmware (`pushver=2.x`, eSSL K30, MB20, SilkBio, UFace)**:
   The embedded database schema defines user master records in table **`USERINFO`** (all caps). The identifier is formatted as **`PIN=`**. When the server requests an update on table `user`, the device parser fails to resolve the table name and aborts with `-1004`.
2. **Modern Visible-Light / Linux / Android Terminals (`pushver=3.x+`, SpeedFace, ProFace)**:
   Modern terminals often operate on a refactored internal database schema where table identifiers are strictly lowercase (**`user`**) and reject uppercase `USERINFO` with syntax error `-1002`.
3. **Multi-Device Gym Deployments**:
   Commercial fitness centers frequently deploy heterogeneous hardware fleets simultaneously (e.g., standard eSSL fingerprint readers at general turnstiles alongside modern face-recognition terminals at premium studio entrances). A global, hardcoded table format prevents mixed-fleet operation.

## Decision

We implemented a multi-tiered table schema negotiation architecture that combines backward-compatible defaults, per-device schema isolation, and transparent administrative configuration.

### 1. Default Wire Protocol Format Standardized to `USERINFO`

Updated `AdmsDeviceCommand::toAdmsCommandString()` to serialize commands using the standard `USERINFO` wire schema by default:

* **User Add / Update (`user_add`, `user_update`)**:
  ```text
  C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tPri=0
  ```
* **User Delete (`user_delete`)**:
  ```text
  C:{id}:DATA DELETE USERINFO PIN={pin}
  ```
* **Device-level commands (`reboot`, `clear_log`, `INFO`)** are schema-independent and identical under both schemas.

Delimiter handling (`\r`, `\n`, `\t`, and other C0 control characters) is applied to the interpolated fields in both schemas: `Name` is free text, so delimiters are replaced with spaces; `Pin`/`PIN` is an identity field, so a value containing a control character rejects the command instead of being repaired into a different PIN. The serializer emits no other user fields: `Passwd`/`Card` were removed because no producer ever wrote them, so their presence in the payload was speculative. The table name itself is selected by equality against a known schema and is never interpolated from operator input.

### 2. Multi-Device Per-Terminal Isolation via `provider_config`

Rather than enforcing a monolithic global setting across the entire application, each `BiometricDevice` model maintains its own table schema preference within its encrypted `provider_config` JSON attribute:
* Key: `provider_config['adms_user_table']`. There is no legacy key: the speculative `provider_config['user_table']` fallback was removed because nothing ever wrote it, and a stored value could only shadow the global default with a stale schema.
* Supported values: `'USERINFO'` (default) and `'user'`.

Commands resolve their target wire format using a three-stage resolution hierarchy:

1. `provider_config['adms_user_table']` on the terminal (per-device override).
2. `config('biometrics.adms.user_table')`, sourced from `ADMS_USER_TABLE`.
3. `USERINFO` (the documented default).

Resolution is strict. Only `USERINFO` and `user` (case-insensitive) are recognised; a blank, misspelled or otherwise unknown value falls through to the default rather than being coerced to the legacy `user` schema. Coercion was the original defect: `ADMS_USER_TABLE=` is not `null`, so an empty value silently selected `user` fleet-wide and reintroduced `-1004`.

The resolved schema is **snapshotted on the command row** (`adms_device_commands.user_table`) when the command is queued:

```php
// AdmsDeviceCommand::booted() — the `creating` hook
static::creating(static function (self $command): void {
    $device = $command->device_id === null
        ? null
        : BiometricDevice::query()->find($command->device_id);

    $command->user_table ??= self::resolveUserTable($device);
    $command->device_serial ??= $device?->device_serial;
});
```

Delivery therefore never reads the device, never lazily loads it, and never reformats an in-flight command when an operator flips the selector. A command that was queued before the column existed falls back to the configured default at serialization time.

### 3. Human-Centric UI in Filament Admin Panel

Added an administrative selector to [`BiometricDeviceResource`](../../app/Filament/Admin/Resources/BiometricDeviceResource.php) within the ADMS Protocol section:
* **Component**: `Select::make('provider_config.adms_user_table')`
* **Options**:
  * `USERINFO` — `USERINFO (Standard / Legacy eSSL & ZKTeco)`
  * `user` — `user (Modern SpeedFace / Linux / Android)`
* **Default**: empty — the field inherits the global `ADMS_USER_TABLE` value. A concrete default would dehydrate into `provider_config` on create and permanently shadow the environment setting, making Create and Edit disagree about precedence.
* **Visibility**: gated on `providerConfigEditable()` like every sibling `provider_config` input, so an unreadable ciphertext cannot be silently overwritten through this field.
* Allowed and hydrated during form lifecycle via `EditBiometricDevice::providerConfigForForm()` without polluting shared webhook fields. The field is terminal state, not provider configuration, so it is not part of `providerConfigCommonFields()`.

### 4. Device Deletion, the Command Ledger, and Erasure Attestation

A terminal row can now be deleted while member templates are enrolled. That operation removes the application's access to the hardware, but it does **not** touch the fingerprint or face template stored in the terminal's flash. Three invariants follow:

* **A null `enrolled_by_device_id` is not proof of erasure.** `BiometricDeProvisioner`, the deletion hook, the abandoned-placeholder sweep and the delete modal share one predicate, `BiometricTemplate::hasDurableHardwareEvidence()` — the row state (`mayBeHeldByHardware()`) OR the ledger (`wasDeliveredToHardware()`, which stays readable after the foreign key is gone because every canonical `user_add` key ends with the template id it enrolled). A template that provably never reached hardware — `Pending`, no confirmation, and no `SENT`/`ACKNOWLEDGED` `user_add` in the ledger — converges to `Deleted`; anything with delivery evidence is parked at `DeletionUnresolvable`, flagged `is_erasure_obligation`, and surfaced in the operator "Needs Operator" filter. Automated de-provisioning never reports a credential erased while hardware may still hold it.
* **The obligation has a discharge path, and only where it is truthful.** An audited **Confirm Physical Erasure** action (actor, timestamp, activity entry) clears `is_erasure_obligation` and converges the row to `Deleted` after the gym has physically wiped the terminal. It is limited to hardware-backed rows with no bound terminal and to MANAGER+ (`GymMemberPolicy::dischargeBiometricErasure()`): a cloud credential is erased through its provider API, and a terminal that still exists is erased through the terminal. The write is a guarded `is_erasure_obligation = true` update, so a repeated confirmation affects no rows and writes no second audit entry.
* **A dead letter has an operator surface.** A hardware `user_delete` that exhausts its delivery cap, or whose PIN the ADMS wire cannot address, parks the template at `DeletionUnresolvable` with the obligation flag. Without it the row stayed at `PendingDeletion` with the flag false, the discharge action was hidden, and the obligation had no exit. A confirmed provider erase clears the flag.
* **The command ledger is retained.** `adms_device_commands.device_id` nulls on delete instead of cascading, and a `device_serial` snapshot is written with every command. The ledger is the only durable artefact that can prove a `user_delete` was never sent, so it must outlive the device row. Orphaned rows (null `device_id`) are terminalised `CANCELLED`: `PENDING` and `SENT` rows inside the device-deletion transaction (counted in the deletion audit), and `app:reclaim-stale-device-commands` for any that remain. Resend stays reachable on a `FAILED` orphan so it can explain the refusal. `max_attempt_count` preserves the highest delivery count ever reached, so an operator resend cannot make a dead-lettered row prunable.

Device deletion is also **atomic and auditable**: the tombstone writes, the ledger cancellation, the `DELETE`, and the activity entry share one transaction, so a failed delete cannot leave a live terminal whose credentials were already tombstoned. The audit entry is written for every deletion, including one with no enrolled templates; deletion is refused when activity logging is disabled or writes to another connection, and a `deleting` listener that returns `false` forces a rollback and reports failure.

Finally, deletion **retains the access-control audit trail**. `door_events.device_id` and `biometric_event_receipts.device_id` null on delete (with a `device_serial` snapshot on both tables) instead of cascading, so lock/unlock and webhook history survives the terminal row. The delete modal states that consequence. Bulk deletion checks `delete()` per record in addition to the bulk `deleteAny()` permission, so the gym tenant predicate cannot be bypassed by the bulk path, and the toast reports deleted / refused / failed counts separately. The row policy enforces the gym tenant predicate in `BiometricDevicePolicy::delete()`.

**Gym deletion is out of scope for the device path.** `biometric_devices.gym_id` still cascades, so deleting a gym destroys its terminals at the database level and skips every invariant above. Instead of claiming otherwise, gym deletion is refused by `GymPolicy::delete()` and by `GymObserver::deleting()` while terminals exist; the operator retires each terminal through Device Management first.

## Consequences

### Positive

- **POS-001**: **Elimination of Hardware Error -1004**: Resolves firmware rejection on standard eSSL and ZKTeco attendance devices running `pushver=2.4.1`.
- **POS-002**: **Heterogeneous Fleet Support**: Enables a gym to simultaneously operate legacy fingerprint terminals and modern face-recognition panels from the same tenant management portal.
- **POS-003**: **Zero-Friction Default Experience**: `USERINFO` is the shipped default and the schema most of the fleet speaks, so a deployment with no `ADMS_USER_TABLE` configured talks to standard eSSL/ZKTeco terminals without any manual step.
- **POS-004**: **Non-Destructive Per-Device Overrides**: Storing table schemas in `provider_config` ensures that rotating, renaming, or updating device credentials does not alter schema negotiation.

### Negative

- **NEG-001**: **Schema Branching in Command Serialization**: Introduces conditional branching within `AdmsDeviceCommand::toAdmsCommandString()`, requiring automated test coverage for both schema variations.
- **NEG-002**: **Potential Misconfiguration by Operators**: If an operator manually sets a legacy terminal to `user` format, commands will fail with `-1004` until reverted to `USERINFO`.
- **NEG-003**: **Outstanding Hardware Erasure after Device Deletion**: deleting a terminal leaves any enrolled credentials in its flash. Those templates are parked for an operator instead of being reported as erased, so the gym must physically erase the terminal before disposing of it.
- **NEG-004**: **Forward-Only Erasure Migrations**: restoring the restrictive foreign keys is refused while detached templates or unbound ledger rows exist, because the alternative is discarding erasure evidence.

## Alternatives Considered

### Global Monolithic Environment Variable Only (`ADMS_USER_TABLE`)

- **ALT-001**: **Description**: Introduce `ADMS_USER_TABLE=USERINFO|user` in `.env` without per-device database storage.
- **ALT-002**: **Rejection Reason**: Prevents multi-device setups where a facility uses a legacy turnstile device alongside a newer visible-light facial terminal. One device would always fail with `-1004` or `-1002`.

### Automatic Dynamic Schema Discovery via Command Retry

- **ALT-003**: **Description**: Always attempt `user` first; upon receiving `-1004` ACK from the hardware, automatically flip the device to `USERINFO` and retry.
- **ALT-004**: **Rejection Reason**: Triples delivery cycles and network traffic on fresh enrollments, burning retry attempt quotas (`max_attempts: 3`) before the schema stabilizes. Explicit configuration with standard default provides deterministic, single-round-trip provisioning.

## Implementation Notes

- **IMP-001**: **Sanitization Guarantees**: Control characters in `Pin`/`PIN` reject the command (an identity field must never be repaired into a different record); control characters in `Name` are replaced with spaces. No other user fields are serialized. The lease path shares the same PIN predicate, so a command the serializer would reject is never leased, marked `SENT`, or charged a delivery attempt; the polling endpoint releases a lease defensively if serialization still returns an empty string.
- **IMP-002**: **Automated Test Parity**: `AdmsProtocolSanitizerTest` asserts wire formatting under the default `USERINFO` schema, an explicitly configured `user` schema, a per-device override snapshotted at enqueue, and the schema-independent device commands under both.
- **IMP-003**: **Production Migration**: Two migrations are required. `2026_10_31_000100` adds the nullable `user_table` snapshot and backfills existing rows from each device's stored override; `2026_10_31_000200` adds the nullable `device_serial` snapshot and rewrites the `device_id` foreign key to `nullOnDelete()`. Both are forward-only in practice: `down()` refuses while detached templates or unbound ledger rows exist, because restoring the restrictive keys would discard erasure evidence. `000000` restores the restrictive template key only when no detached hardware rows exist.

## References

- **REF-001**: [ADR-0005: Biometric ADMS Communication Key Hardware Standardization](adr-0005-biometric-adms-communication-key-hardware-standardization.md)
- **REF-002**: [ADR-0006: Biometric ADMS Legacy Hardware Endpoint, ASPX Routing, and Transport Compatibility](adr-0006-biometric-adms-legacy-hardware-endpoint-and-transport-compatibility.md)
- **REF-003**: ZKTeco Attendance PUSH Communication Protocol Specification (Push SDK v2.4.1 / v3.2.0)

