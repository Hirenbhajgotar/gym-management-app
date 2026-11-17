---
title: "ADR-0013: Biometric ADMS USERINFO Clean Wire Format and Name Persistence"
status: "Accepted"
date: "2026-11-17"
authors: "Lead Architect, Hardware Integration Engineer, DevOps Engineer"
tags: ["architecture", "decision", "biometrics", "adms", "hardware-integration", "zkteco", "essl", "mb20", "userinfo"]
supersedes: "docs/adr/adr-0010-biometric-adms-operlog-handling-and-clean-wire-token-standardization.md"
superseded_by: ""
---

# ADR-0013: Biometric ADMS USERINFO Clean Wire Format and Name Persistence

## Status

**Accepted**

## Context

Following the implementation of ADR-0011 and ADR-0012, the physical eSSL MB20 biometric attendance terminal (`EUF7262500413`, Device ID `3`, Gym ID `3`) successfully acknowledged and executed hardware remote reboot commands (`CONTROL DEVICE 03000000`).

However, during live testing of member enrollment on the physical terminal, an issue was observed regarding member display names:
1. When an operator manually entered the name "Hiren" directly into the terminal keypad (`User Mgt -> All Users -> 369 -> Edit`), the LCD display correctly retained and showed `Name: Hiren`.
2. When the operator initiated an enrollment / re-enrollment from the web application (`fluxfitness.in`), the server pushed the queued `user_add` command:
   ```text
   C:48:DATA UPDATE USERINFO PIN=369\tName=Hiren\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000
   ```
3. The terminal acknowledged the command with `ID=48&Return=0&CMD=DATA`.
4. Immediately following this command execution, inspecting the user record on the physical device LCD screen revealed that **the previously saved name had been wiped to blank / empty (`Name: `)**.

### Root Cause Analysis

Investigation traced this regression to commit `9730a982` (dated Nov 7, 2026). During early debugging, an operator executed `DATA QUERY USERINFO PIN=369`, and the terminal dumped its raw internal memory record via `OPERLOG`:
```text
USER PIN=369\tName=Hiren\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000\tExpires=0\t...
```
This device-to-server **read dump** was mistakenly treated as the required wire format for server-to-device **write commands** (`DATA UPDATE USERINFO`).

In embedded C string tokenizers (`strtok` / `sscanf`) inside the ZKTeco Linux firmware (`ZLM60_TFT` / `pushver=2.4.1`):
1. **Empty Value Assignments (`Passwd=\tCard=`)**: Emitting keys with empty values immediately followed by a tab or next delimiter causes delimiter parsing errors or instructs the internal SQLite driver to overwrite user fields with null/empty values.
2. **Abbreviated `Pri=0` vs `Privilege=0`**: In the ZKTeco Push SDK specification for the `USERINFO` table, the role attribute is strictly `Privilege`. `Pri` is a legacy/read-only token used in `OPERLOG` table logs and legacy `user` tables.
3. **Speculative Access Control Fields**: Appending `Grp=1` and `TZ=0000000100000000` to a basic `USERINFO` user update corrupts the memory struct offset in standard attendance terminals where access control timezone rules are not configured.

As a result, executing `DATA UPDATE USERINFO PIN=369\tName=Hiren\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000` caused the device firmware to overwrite the user record with an empty name.

## Decision

We have updated `AdmsDeviceCommand::userCommandString()` to serialize `USERINFO` commands according to the canonical ZKTeco Push SDK / BioTime wire format standard:

### 1. Canonical `USERINFO` Wire Format

```text
C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tPrivilege={privilege}
```

- **Table:** Strictly uppercase `USERINFO`.
- **PIN Key:** Strictly uppercase `PIN={pin}`.
- **Name Key:** PascalCase `Name={name}`. Free of injected delimiters (newlines and tabs sanitized to single spaces).
- **Privilege Key:** Full keyword `Privilege={privilege}` (`0` for normal users).
- **No Empty Tokens:** Completely omit `Passwd=` and `Card=`.
- **No Access Control Struct:** Completely omit `Grp=` and `TZ=`.

### 2. Backward Compatibility for Legacy `user` Schema

For devices configured with the legacy lowercase `user` table schema (`provider_config['adms_user_table'] = 'user'`), the format remains:
```text
C:{id}:DATA UPDATE user Pin={pin}\tName={name}\tPri=0
```

## Consequences

- Member names pushed from the web application are cleanly parsed by the terminal firmware without tokenizer errors.
- Existing profiles and manually entered names are updated with the correct name rather than wiped to blank.
- All unit and feature tests (`AdmsDeviceCommandWireFormatTest` and `AdmsProtocolSanitizerTest`) explicitly assert against empty tokens (`Passwd=`, `Card=`) and access control tokens (`Grp=`, `TZ=`).
