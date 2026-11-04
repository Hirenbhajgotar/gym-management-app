---
title: "ADR-0009: Biometric ADMS USERINFO Dual-Token Name and Attribute Standardization"
status: "Superseded"
date: "2026-11-04"
authors: "Lead Architect, Biometric Systems Engineer, Senior Full-Stack Engineer"
tags: ["architecture", "decision", "biometrics", "adms", "hardware-integration", "zkteco", "essl", "mb20", "push-protocol"]
supersedes: ""
superseded_by: "docs/adr/adr-0010-biometric-adms-operlog-handling-and-clean-wire-token-standardization.md"
---

# ADR-0009: Biometric ADMS USERINFO Dual-Token Name and Attribute Standardization

## Status

**Superseded** by [ADR-0010](adr-0010-biometric-adms-operlog-handling-and-clean-wire-token-standardization.md)

## Context

Following the implementation of ADR-0007 (which standardized the default wire table schema to `USERINFO` and `PIN={pin}`), member enrollment commands successfully executed across physical terminals with execution return code `Return=0` (Success).

However, during live hardware verification on an eSSL MB20 terminal (`Main Door 2 -ADMS`, serial `EUF7262500413`) for Member 369 (Hiren Sarla), an unexpected display defect was observed:
1. The user record was successfully created in the terminal's flash memory.
2. The user ID was assigned to `369` and the user role was assigned to `Normal User`.
3. The member's **Name field remained completely blank** on the physical LCD screen and in the terminal's local user management menu.

### Root Cause Analysis

Inspection of the queued command payload in `adms_device_commands` (Command ID 4) confirmed that the application had saved and queued:
```json
{
  "Pin": "369",
  "Name": "Hiren Sarla"
}
```
The serialized wire command transmitted by the server to the terminal was:
```text
C:4:DATA UPDATE USERINFO PIN=369\tName=Hiren Sarla\tPri=0
```

Reverse engineering and protocol debugging of the Linux/ZEM-based firmware in eSSL MB20 and related ZKTeco-family devices revealed the following parser characteristics:
1. **Case-Sensitive Token Parser**: Under the uppercase `USERINFO` table specification, the firmware tokenizer parses key-value pairs strictly case-sensitively. Specifically, it expects **`NAME=`** (all-caps).
2. **Silent Drop of Unknown Tokens**: When the firmware tokenizer encounters **`Name=`** (mixed-case), it does not fail or reject the command; instead, it silently discards the unrecognized token without raising a syntax error.
3. **Privilege Attribute Inconsistency**: Certain firmware iterations expect `Privilege=0` rather than `Pri=0` to confirm normal user access rights.
4. **Line-Oriented Response Framing**: Legacy HTTP socket parsers on embedded microcontrollers expect command lines delivered via `GET /iclock/getrequest` to terminate strictly with a single newline (`\n`). A response lacking a trailing newline can cause buffer lag or boundary parsing delays on embedded clients.

## Decision

We resolved the token parsing incompatibility by standardizing a dual-token wire serialization format for `USERINFO` commands, introducing case-tolerant payload deserialization, and enforcing newline-delimited command delivery.

### 1. Dual-Token Wire Serialization for `USERINFO`

In `AdmsDeviceCommand::toAdmsCommandString()`, when serializing commands under the `USERINFO` table schema, both case variants are emitted simultaneously separated by tab delimiters:
```text
C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tNAME={name}\tPri=0\tPrivilege=0
```

- Terminals expecting uppercase tokens (eSSL MB20, Linux/ZEM architecture) process `NAME={name}` and `Privilege=0`.
- Terminals expecting mixed-case tokens process `Name={name}` and `Pri=0`.
- Because both tokens carry identical, sanitized name strings, whichever token is recognized sets the terminal's display name accurately without parser ambiguity.

The legacy/modern `user` wire schema (`adms_user_table = 'user'`) is preserved without modification:
```text
C:{id}:DATA UPDATE user Pin={pin}\tName={name}\tPri=0
```

### 2. Case-Insensitive Name Extraction in Deserialization

In `AdmsDeviceCommand::serializedName()`, payload extraction was updated to safely tolerate diverse casing in stored JSON payloads:
```php
$rawName = $payload['Name'] ?? $payload['name'] ?? $payload['NAME'] ?? '';
```
Non-string or non-numeric values continue to evaluate to an empty string, and control characters (`[\x00-\x1F\x7F]`) are stripped into spaces prior to serialization.

### 3. Trailing Newline Enforcement in Command Polling

In `AdmsController::getRequest()`, the command dispatch response is guaranteed to terminate with exactly one newline character:
```php
return $this->plainResponse(rtrim($commandString) . "\n");
```
Idle polling responses (`'OK'`) and HTTP headers remain unaffected.

## Consequences

### Positive

- **POS-001**: Resolves the blank user name display defect on eSSL MB20 and related ZKTeco biometric terminals.
- **POS-002**: Preserves 100% backward compatibility with existing ZKTeco terminals and modern visible-light devices using the lowercase `user` table schema.
- **POS-003**: Prevents silent payload parsing drops caused by key-casing mismatches (`Name` vs `name` vs `NAME`).
- **POS-004**: Guarantees compliance with embedded socket line-buffering expectations via strict newline termination on command polling.
- **POS-005**: Maintains zero mutation risk on PIN identity and security tokens; control character sanitization applies equally to both `Name=` and `NAME=` tokens.

### Negative

- **NEG-001**: Command payload wire length increases by approximately 15–30 bytes per user enrollment command due to duplicate token emission.
- **NEG-002**: Requires automated test suites to assert both tokens when testing the wire format for `USERINFO`.

## Alternatives Considered

### Dedicated Per-Device Attribute Casing Matrix

- **ALT-001**: **Description**: Add a new column or configuration key `name_attribute_case` (`uppercase`, `mixedcase`, `lowercase`) in `biometric_devices.provider_config`.
- **ALT-002**: **Rejection Reason**: Adds administrative complexity and operator overhead. Biometric terminal administrators cannot easily identify internal C tokenizer case sensitivity from physical label plates. Dual-token emission solves the issue transparently without configuration burden.

### Replacing `Name=` with `NAME=` Exclusively

- **ALT-003**: **Description**: Change `Name=` to `NAME=` for all `USERINFO` commands.
- **ALT-004**: **Rejection Reason**: Newer ZKTeco firmware revisions strictly expect `Name=`. Changing exclusively to `NAME=` would break newer terminals while fixing eSSL MB20. Emitting both tokens provides universal compatibility.

## Implementation Notes

- **IMP-001**: Implemented in `app/Models/AdmsDeviceCommand.php` within `userCommandString()` and `serializedName()`.
- **IMP-002**: Implemented in `app/Http/Controllers/Api/AdmsController.php` within `getRequest()`.
- **IMP-003**: Verified through comprehensive test suite in `tests/Feature/AdmsProtocolSanitizerTest.php` and `tests/Feature/AdmsProtocolTest.php`.
- **IMP-004**: Rollout requires no database schema migrations; effective immediately upon code deployment.

## References

- **REF-001**: [ADR-0007: Biometric ADMS Multi-Device Table Schema Negotiation and Wire Protocol Standardization](adr-0007-biometric-adms-multi-device-table-schema-negotiation.md)
- **REF-002**: [ADR-0006: Biometric ADMS Legacy Hardware Endpoint and Transport Compatibility](adr-0006-biometric-adms-legacy-hardware-endpoint-and-transport-compatibility.md)
- **REF-003**: [ADR-0005: Biometric ADMS Communication Key Hardware Standardization](adr-0005-biometric-adms-communication-key-hardware-standardization.md)
- **REF-004**: [Biometric ADMS Push Protocol Feature Documentation](../biometric/feature/biometric-adms-push-protocol.md)

