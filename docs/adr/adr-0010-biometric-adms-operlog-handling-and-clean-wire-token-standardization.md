---
title: "ADR-0010: Biometric ADMS OPERLOG Push Handling and Clean Wire Token Protocol Standardization"
status: "Accepted"
date: "2026-11-04"
authors: "Lead Architect, Biometric Systems Engineer, Senior Full-Stack Engineer"
tags: ["architecture", "decision", "biometrics", "adms", "hardware-integration", "zkteco", "essl", "mb20", "push-protocol", "operlog"]
supersedes: "docs/adr/adr-0009-biometric-adms-userinfo-dual-token-name-standardization.md"
superseded_by: ""
---

# ADR-0010: Biometric ADMS OPERLOG Push Handling and Clean Wire Token Protocol Standardization

## Status

**Accepted**

## Context

During production terminal verification on an eSSL MB20 biometric device (`Main Door 2 -ADMS`, serial `EUF7262500413`), two critical integration anomalies were observed:

1. **OPERLOG HTTP 400 Rejection Storm**:
   Whenever administrative or user operations occurred on the physical device, the terminal posted audit/operational records to `/iclock/cdata?SN=...&table=OPERLOG`. Because the application previously accepted only `table=ATTLOG`, all `OPERLOG` requests were rejected with `400 ERR: Unsupported table`. This triggered an infinite 5-second hardware retry loop, flooding server logs and stalling the terminal's outbound push queue.

2. **Terminal Name Display Drop & Duplicate Wire Token Incompatibility**:
   Live inspection of Member 369 on the MB20 LCD screen revealed that while User ID `369` and `User Role: Normal User` were successfully created in flash memory with `Return=0`, the user's `Name` field remained completely empty. Experimental dual-token serialization introduced in ADR-0009 (`Name=...\tNAME=...\tPri=0\tPrivilege=0`) emitted non-standard uppercase tokens (`NAME` and `Privilege`) which standard ZKTeco/eSSL C firmware parsers either drop or misalign during token splitting. Furthermore, embedded Linux firmware families (such as MB20) parse user names strictly through the lowercase `user` table schema (`DATA UPDATE user Pin=...`), whereas `USERINFO` is treated as a numeric index table on certain firmware builds.

## Decision

We enacted a two-part architectural resolution across the ADMS ingestion and command serialization layers:

### 1. Native `OPERLOG` Ingestion & Heartbeat Renewal in `AdmsController`
- Added explicit handling for HTTP `POST /iclock/cdata?table=OPERLOG`.
- In `AdmsController::handleOperlog()`, incoming operational audit payloads are logged at `info` level for diagnostic observability, the device heartbeat and miss counters are refreshed, and a standard plain text `OK` response is returned.
- This satisfies the terminal's delivery requirement and immediately halts infinite 5-second retry loops.

### 2. Clean Wire Token Standardization & Sanitization in `AdmsDeviceCommand`
- Deprecated and removed the duplicate non-standard tokens (`NAME=` and `Privilege=0`) from `AdmsDeviceCommand::userCommandString()`.
- Standardized wire token formats across both `USERINFO` and `user` tables to strict, single-token representations:
  - `USERINFO`: `C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tPri=0`
  - `user`: `C:{id}:DATA UPDATE user Pin={pin}\tName={name}\tPri=0`
- Updated `serializedName()` with trim sanitization and strict bounds clamping (`mb_substr(trim($sanitized), 0, 24)`) matching the 24-character maximum name buffer in ZKTeco embedded C structs.
- Documented operational guidance for eSSL MB20 fleets: when terminal LCDs require display names, operators configure `adms_user_table = 'user'` via Filament Admin per-device settings or global environment variable `ADMS_USER_TABLE=user`.

## Consequences

### Positive

- **POS-001**: Eliminates the 5-second `OPERLOG` HTTP 400 error loop and reduces unnecessary network traffic and server log noise.
- **POS-002**: Restores strict compliance with ZKTeco/eSSL wire protocol standards by removing non-standard duplicate tokens.
- **POS-003**: Prevents token boundary shifting and parser memory corruption caused by redundant parameters.
- **POS-004**: Enforces string length boundaries conforming to embedded hardware flash structs (24-byte limit).
- **POS-005**: Provides complete operational logging of incoming device-native registration events.

### Negative

- **NEG-001**: Supersedes ADR-0009 and requires updating protocol tests to assert single standard `Name=` and `Pri=0` tokens.
- **NEG-002**: Terminals requiring `user` table for display names must have their schema configured via device settings rather than expecting global automatic negotiation.

## Alternatives Considered

### Retaining Dual Tokens with Space Padding

- **ALT-001**: **Description**: Attempting to pad duplicate tokens or reorder `NAME` before `Name`.
- **ALT-002**: **Rejection Reason**: The eSSL MB20 C parser is not case-insensitive; adding unrecognised attributes causes silent parameter drop or buffer overrun. Standard single-token serialization is the only vendor-supported format.

### Dropping OPERLOG Silently Without Heartbeat Renewal

- **ALT-003**: **Description**: Returning 200 OK without logging or updating device heartbeat.
- **ALT-004**: **Rejection Reason**: Operational events contain critical diagnostic data when devices undergo manual keypad operations, and updating device heartbeat ensures accurate connection tracking.

## Implementation Details

- **IMP-001**: Modified `AdmsController::cdata()` to route `OPERLOG` to `handleOperlog()`.
- **IMP-002**: Modified `AdmsDeviceCommand::serializedName()` and `userCommandString()` to enforce standard tokens and length boundaries.
- **IMP-003**: Updated `AdmsProtocolTest` and `AdmsProtocolSanitizerTest` feature test suites.

## Related Documentation

- **REF-001**: [ADR-0007: Biometric ADMS Multi-Device Table Schema Negotiation](adr-0007-biometric-adms-multi-device-table-schema-negotiation.md)
- **REF-002**: [ADR-0008: Biometric ADMS Per-Device Communication Key Negotiation](adr-0008-biometric-adms-per-device-comm-key-negotiation.md)
- **REF-003**: [ADR-0009: Biometric ADMS USERINFO Dual-Token Name Standardization (Superseded)](adr-0009-biometric-adms-userinfo-dual-token-name-standardization.md)
- **REF-004**: [Biometric ADMS Push Protocol Feature Guide](../biometric/feature/biometric-adms-push-protocol.md)

