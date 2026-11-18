---
title: "ADR-0011: Biometric ADMS Remote Reboot, Embedded GUI Cache Synchronization, and In-Flight Safeguards"
status: "Accepted"
date: "2026-11-16"
authors: "Lead Architect, Biometric Systems Engineer, Senior Full-Stack Engineer"
tags: ["architecture", "decision", "biometrics", "adms", "hardware-integration", "zkteco", "essl", "mb20", "reboot", "telemetry"]
supersedes: ""
superseded_by: ""
---

# ADR-0011: Biometric ADMS Remote Reboot, Embedded GUI Cache Synchronization, and In-Flight Safeguards

## Status

**Accepted**

## Context

During field deployment and verification on eSSL MB20 biometric terminals (`ZLM60_TFT` Linux architecture), an operational discrepancy was encountered:

1. **The LCD Screen Display Paradox**:
   - Web-initiated user enrollment successfully pushed `DATA UPDATE USERINFO PIN=369\tName=Hiren\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000` via the ADMS command queue (`/iclock/getrequest`).
   - The terminal acknowledged the command with `Return=0` (Success).
   - Telemetry from diagnostic query commands (`DATA QUERY USERINFO PIN=369`) confirmed that the terminal's internal SQLite database (`/mnt/mtd/device.db`) accurately stored `Name=Hiren`.
   - Despite this, the physical LCD screen interface (`Menu -> User Mgt -> All Users -> 369 -> Edit`) continued displaying `Name: [blank]`.

2. **Embedded Process Separation (ADMS Daemon vs. TFT GUI Process)**:
   - On ZKTeco/eSSL Linux devices, the ADMS push client (`iclock` daemon) operates as a headless background daemon that writes records directly to the on-disk SQLite database (`device.db`).
   - The LCD user interface runs as an independent foreground graphical application (`main` / `ssr_tft`) that reads from an in-memory struct cache (`user.dat`) loaded during boot.
   - On standard MB20 firmware builds, background database mutations do not trigger an asynchronous GUI memory reload until the device restarts or executes a warm boot.

3. **Keypad Re-Enrollment Blank-Name Freezing**:
   - When device operators manually register or edit fingerprints directly on the terminal keypad, they frequently skip alphanumeric name entry on the 0-9 numeric pad.
   - The terminal commits `Name=""` locally, emits an audit event (`OPLOG 4`), and firmware safeguard logic locks the local display profile from being overwritten by subsequent `DATA UPDATE` commands without a fresh purge or restart.

4. **Production Log Saturation from Idle Polling**:
   - In production, ADMS terminals poll `GET /iclock/getrequest` every 3 to 10 seconds.
   - Emitting `Log::info()` on every idle poll generated 8,600 to 28,800 log lines per device daily, causing log pipeline saturation and high I/O overhead.

5. **Risk of Hardware Reboot Loops on Connection-Dropping Firmware**:
   - Many ZKTeco/eSSL firmware variants drop the TCP connection immediately upon parsing `C:{id}:REBOOT` to begin hardware reset without emitting a `POST /iclock/devicecmd` acknowledgement (`Return=0`).
   - If an unacknowledged reboot command is queued without concurrency control, its lease can expire, causing re-delivery or multiple reboot commands to queue, triggering an infinite reboot loop upon device reconnection.

## Decision

We enacted the following architectural decisions to ensure reliable display persistence, production-grade telemetry, and hardware reboot safety:

### 1. Remote Reboot Command Dispatch via ADMS Queue
- Implemented Artisan command `php artisan biometrics:reboot-device` (`--device-id=` or `--serial=`).
- Enqueues a standardized ADMS command with wire serialization `C:{id}:CONTROL DEVICE 03000000` terminated with RFC CRLF (`\r\n`). Application-level strings like `REBOOT` and `Restart` are acknowledged by ZLM60 firmware with `Return=0` but swallowed without resetting hardware; `CONTROL DEVICE 03000000` executes the low-level hardware restart cycle (opcode `03`).
- When received by the terminal during `/iclock/getrequest`, the device restarts, forcing the foreground GUI process (`ssr_tft`) to re-read `/mnt/mtd/device.db` and display the synchronized name.

### 2. In-Flight Command Guards & High-Resolution Idempotency
- `AdmsRebootDeviceCommand` inspects `adms_device_commands` for existing `PENDING` or `SENT` reboot commands for the target device before creating a new one. If an in-flight command exists, it warns the operator and exits without creating duplicate queue items.
- Idempotency keys use microtime and cryptographically secure random components (`reboot:{gym_id}:{device_id}:{timestamp}:{random}`) to prevent database unique index collisions.

### 3. Mutual Exclusivity and Input Validation for CLI Diagnostic Tools
- CLI commands querying or modifying hardware (`biometrics:reboot-device`, `biometrics:query-user`) strictly validate integer device IDs and ensure passed `--device-id` and `--serial` arguments resolve to the same device without silent option overrides.

### 4. Telemetry Level Optimization & Structured Observability
- Idle polling in `AdmsController::getRequest()` is no longer logged at all. It was first demoted from `Log::info()` to `Log::debug()`, but the debug-level call still fired on every 3–10s poll (8,600–28,800 entries per device daily), so it was removed entirely in an amendment dated 2026-11-18.
- Structured contextual logging was introduced across `cdata`, `getrequest`, `devicecmd`, `operlog`, and `handshake` (capturing client IP, byte counts, raw request bodies, parsed parameters, and device serials).
- Fixed a property mismatch in `AdmsController::handleHandshake()` where `$device->device_serial_number` was queried instead of `$device->device_serial`.

## Consequences

### Positive

- **POS-001**: **Guaranteed LCD Display Synchronization**: Operators can remotely trigger a warm restart via `biometrics:reboot-device` to reload SQLite records into foreground GUI RAM without physically visiting or power cycling the terminal.
- **POS-002**: **Reboot Loop Elimination**: In-flight command checks guarantee that terminals dropping TCP connections before sending return ACKs cannot be trapped in infinite reboot cycles.
- **POS-003**: **Clean Production Logging**: Removing idle poll telemetry entirely eliminates log noise while preserving full diagnostic data during active command delivery and acknowledgement.
- **POS-004**: **End-to-End Diagnostic Visibility**: Support for `DATA QUERY USERINFO` and structured payload logging allows instant verification of internal SQLite state vs. LCD display state.

### Negative

- **NEG-001**: **Temporary Terminal Downtime During Reboot**: Rebooting the terminal causes a 30-to-60 second window where member check-ins via biometrics are offline while the hardware cycles power.
- **NEG-002**: **Asynchronous Execution Window**: Because ADMS is polling-based, the reboot does not happen instantaneously; it executes when the device reaches its next polling cycle (up to 30 seconds).

## Alternatives Considered

### Direct TCP/UDP Socket Broadcast (ZKTeco Protocol 4370)

- **ALT-001**: **Description**: Open a raw TCP/UDP port 4370 socket to the terminal and issue SDK command `RestartDevice()`.
- **ALT-002**: **Rejection Reason**: The application's architecture operates exclusively via outbound ADMS push over HTTP/HTTPS. Terminals in gyms are situated behind NAT routers, dynamic IPs, and firewalls without open inbound ports. Direct socket connectivity is architecturally unviable for cloud multi-tenancy.

### Forcing Device Deletion and Re-Creation on Every Sync

- **ALT-003**: **Description**: Issue `DATA DELETE USERINFO PIN=...` followed immediately by `DATA UPDATE USERINFO` on every sync.
- **ALT-004**: **Rejection Reason**: Deleting the user purges on-chip fingerprint and face templates enrolled on the hardware, requiring the member to physically re-enroll their biometrics at the terminal.

## Implementation Notes

- **IMP-001**: Command registered as `php artisan biometrics:reboot-device {--device-id=} {--serial=}`.
- **IMP-002**: Serializer `AdmsDeviceCommand::toAdmsCommandString()` formats `command_type = 'reboot'` as `C:{id}:CONTROL DEVICE 03000000`.
- **IMP-003**: Unit and feature testing verified in `tests/Feature/AdmsRebootDeviceCommandTest.php` covering input validation, mutual exclusivity, in-flight suppression, database assertions, and CRLF wire formatting over `/api/iclock/getrequest`.

## References

- **REF-001**: [ADR-0010: Biometric ADMS OPERLOG Handling, BioTime Standard USERINFO Struct Wire Serialization, and UTF-8 Charset](adr-0010-biometric-adms-operlog-handling-and-clean-wire-token-standardization.md)
- **REF-002**: [ADMS Userinfo Name Synchronization Findings](../biometric/adms-userinfo-name-synchronization-findings.md)
- **REF-003**: [Biometric ADMS Push Protocol Documentation](../biometric/feature/biometric-adms-push-protocol.md)
