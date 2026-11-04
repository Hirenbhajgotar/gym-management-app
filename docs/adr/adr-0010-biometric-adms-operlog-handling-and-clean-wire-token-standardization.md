---
title: "ADR-0010: Biometric ADMS OPERLOG Handling, USERINFO Dual-Token Enforcement, and Table Resolution"
status: "Accepted"
date: "2026-11-04"
authors: "Lead Architect, Biometric Systems Engineer, Senior Full-Stack Engineer"
tags: ["architecture", "decision", "biometrics", "adms", "hardware-integration", "zkteco", "essl", "mb20", "push-protocol", "operlog"]
supersedes: ""
superseded_by: ""
---

# ADR-0010: Biometric ADMS OPERLOG Handling, USERINFO Dual-Token Enforcement, and Table Resolution

## Status

**Accepted**

## Context

During production terminal verification on an eSSL MB20 biometric device (`Main Door 2 -ADMS`, serial `EUF7262500413`), two critical integration behaviors were diagnosed through live hardware telemetry and return logs:

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

3. **C-Tokenizer Case Sensitivity in `USERINFO`**:
   While `USERINFO` is accepted by MB20 (`Return=0`), its internal C firmware tokenizer parses attribute keys case-sensitively:
   - When only mixed-case `Name=` is transmitted, the tokenizer silently skips the unrecognized token, leaving the member's name blank on the LCD screen.
   - The tokenizer requires uppercase `NAME=` and `Privilege=0` under the `USERINFO` table.
   - Conversely, standard modern ZKTeco firmware variants expect `Name=` and `Pri=0`.

## Decision

We enacted a complete resolution across the ADMS ingestion and command serialization layers:

### 1. Native `OPERLOG` Ingestion & Heartbeat Renewal in `AdmsController`
- Added explicit handling for HTTP `POST /iclock/cdata?table=OPERLOG`.
- In `AdmsController::handleOperlog()`, incoming operational audit payloads are logged at `info` level for diagnostic observability, the device heartbeat and miss counters are refreshed, and a standard plain text `OK` response is returned.
- This satisfies the terminal's delivery requirement and immediately halts infinite 5-second retry loops.

### 2. Dual-Token Serialization for `USERINFO` in `AdmsDeviceCommand`
- When `USERINFO` wire schema is active (default for eSSL MB20 and ZKTeco fleets), `AdmsDeviceCommand::userCommandString()` emits both token variants:
  ```text
  C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tNAME={name}\tPri=0\tPrivilege=0
  ```
- This satisfies firmware expecting uppercase tokens (`NAME=`, `Privilege=0`) such as eSSL MB20, while simultaneously satisfying firmware expecting mixed-case tokens (`Name=`, `Pri=0`).
- The `user` table schema is retained for devices supporting that schema:
  ```text
  C:{id}:DATA UPDATE user Pin={pin}\tName={name}\tPri=0
  ```
- In `serializedName()`, control characters are replaced with spaces without arbitrary truncation, ensuring full sanitization without clipping valid payloads.

## Consequences

### Positive

- **POS-001**: Eliminates the 5-second `OPERLOG` HTTP 400 error loop and reduces server log noise.
- **POS-002**: Fixes the blank name display issue on eSSL MB20 by delivering the required uppercase `NAME=` token.
- **POS-003**: Preserves universal compatibility across both legacy and modern ZKTeco/eSSL hardware variants.
- **POS-004**: Prevents misconfiguration where operators might attempt to switch MB20 to unsupported `user` table.

## Implementation Details

- **IMP-001**: `AdmsController::cdata()` routes `OPERLOG` to `handleOperlog()`.
- **IMP-002**: `AdmsDeviceCommand::userCommandString()` emits dual tokens under `USERINFO`.
- **IMP-003**: Updated `AdmsProtocolTest` and `AdmsProtocolSanitizerTest` feature test suites.
- **REF-003**: [ADR-0009: Biometric ADMS USERINFO Dual-Token Name Standardization (Superseded)](adr-0009-biometric-adms-userinfo-dual-token-name-standardization.md)
- **REF-004**: [Biometric ADMS Push Protocol Feature Guide](../biometric/feature/biometric-adms-push-protocol.md)

