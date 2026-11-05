---
title: "ADR-0010: Biometric ADMS OPERLOG Handling, BioTime Standard USERINFO Struct Wire Serialization, and UTF-8 Charset"
status: "Accepted"
date: "2026-11-05"
authors: "Lead Architect, Biometric Systems Engineer, Senior Full-Stack Engineer"
tags: ["architecture", "decision", "biometrics", "adms", "hardware-integration", "zkteco", "essl", "mb20", "push-protocol", "operlog", "biotime"]
supersedes: "docs/adr/adr-0009-biometric-adms-userinfo-dual-token-name-standardization.md"
superseded_by: ""
---

# ADR-0010: Biometric ADMS OPERLOG Handling, BioTime Standard USERINFO Struct Wire Serialization, and UTF-8 Charset

## Status

**Accepted**

## Context

During production terminal verification on an eSSL MB20 biometric device (`Main Door 2 -ADMS`, serial `EUF7262500413`), several critical integration behaviors were diagnosed through live hardware telemetry and return logs:

1. **OPERLOG HTTP 400 Rejection Storm**:
   Whenever administrative or user operations occurred on the physical device, the terminal posted audit/operational records to `/iclock/cdata?SN=...&table=OPERLOG`. Because the application previously accepted only `table=ATTLOG`, all `OPERLOG` requests were rejected with `400 ERR: Unsupported table`. This triggered an infinite 5-second hardware retry loop, flooding server logs and stalling the terminal's outbound push queue.

2. **Empirical Disproof of `user` Table Compatibility (`return_code: -1004`)**:
   When configuring `adms_user_table = 'user'` to test whether lowercase `user` table schema (`DATA UPDATE user Pin=...`) would store display names on the MB20 LCD, the terminal explicitly rejected the command during polling:
   ```json
   {
     "device_id": 3,
     "command_id": 7,
     "return_code": "-1004",
     "status": "failed"
   }
   ```
   In the ZKTeco / eSSL Push Protocol specification, error code `-1004` strictly defines:
   **"Table or feature not supported on this device model"**.
   This empirically proves that eSSL MB20 hardware does **not** recognize or support the `user` table, and operates exclusively via the standard `USERINFO` table.

3. **C-Struct Token Alignment & Missing Trailing Fields**:
   While `USERINFO` commands were acknowledged with `Return=0`, the user's `Name` field remained blank on the LCD screen ("Edit : 369"). Deep reverse engineering of ZKTeco and official BioTime 8.0/8.5 push payloads revealed that the embedded C parser expects the full user struct sequence:
   ```text
   PIN={pin}\tName={name}\tPri=0\tPasswd=\tCard=\tGrp=1
   ```
   Omitting trailing struct tokens (`Passwd=`, `Card=`, `Grp=1`) or adding redundant duplicate casing tokens causes the embedded parser to allocate the numeric index (`PIN`) into SQLite/flash memory while aborting the text name buffer.

4. **Missing Explicit UTF-8 Content-Type**:
   Controller responses previously emitted `Content-Type: text/plain` without `charset=utf-8`. Embedded microcontrollers without explicit charset definitions fall back to ISO-8859-1 or drop multibyte/text string assignments during payload parsing.

## Decision

We enacted a complete resolution across the ADMS ingestion and command serialization layers:

### 1. Native `OPERLOG` Ingestion & Heartbeat Renewal in `AdmsController`
- Added explicit handling for HTTP `POST /iclock/cdata?table=OPERLOG`.
- In `AdmsController::handleOperlog()`, incoming operational audit payloads are logged at `info` level for diagnostic observability, the device heartbeat and miss counters are refreshed, and a standard plain text `OK` response is returned.
- This satisfies the terminal's delivery requirement and immediately halts infinite 5-second retry loops.

### 2. Complete BioTime Standard USERINFO Struct Serialization in `AdmsDeviceCommand`
- When `USERINFO` wire schema is active (default for eSSL MB20 and ZKTeco fleets), `AdmsDeviceCommand::userCommandString()` emits the complete vendor-standard struct:
  ```text
  C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tPri=0\tPasswd=\tCard=\tGrp=1
  ```
- This satisfies the embedded C struct parser sequentially, guaranteeing that both `PIN` and `Name` are committed to device flash memory.
- The lowercase `user` table schema is preserved for modern visible-light devices that require it:
  ```text
  C:{id}:DATA UPDATE user Pin={pin}\tName={name}\tPri=0
  ```

### 3. Enforced UTF-8 Charset Header in `AdmsController`
- Updated `AdmsController::plainResponse()` to explicitly set `Content-Type: text/plain; charset=utf-8` on all outgoing ADMS responses, ensuring character encoding integrity across all firmware builds.

## Consequences

### Positive

- **POS-001**: Eliminates the 5-second `OPERLOG` HTTP 400 error loop and halts device retry storms.
- **POS-002**: Solves the blank name issue on eSSL MB20 and ZKTeco terminals by fulfilling the complete `USERINFO` struct sequence expected by firmware.
- **POS-003**: Prevents character encoding corruption through explicit UTF-8 HTTP response headers.
- **POS-004**: Fully adheres to official BioTime 8.0/8.5 and ZKTeco Push SDK wire specifications.

## Implementation Details

- **IMP-001**: `AdmsController::cdata()` routes `OPERLOG` to `handleOperlog()`.
- **IMP-002**: `AdmsController::plainResponse()` returns `Content-Type: text/plain; charset=utf-8`.
- **IMP-003**: `AdmsDeviceCommand::userCommandString()` emits `PIN={pin}\tName={name}\tPri=0\tPasswd=\tCard=\tGrp=1`.
- **IMP-004**: Updated `AdmsProtocolTest` and `AdmsProtocolSanitizerTest` feature test suites.
- **REF-003**: [ADR-0009: Biometric ADMS USERINFO Dual-Token Name Standardization (Superseded)](adr-0009-biometric-adms-userinfo-dual-token-name-standardization.md)
- **REF-004**: [Biometric ADMS Push Protocol Feature Guide](../biometric/feature/biometric-adms-push-protocol.md)

