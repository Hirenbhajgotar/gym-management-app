# ZKTeco / eSSL MB20 ADMS USERINFO Name Synchronization Wire Format Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Correct the ADMS `DATA UPDATE USERINFO` server-to-device command wire format so that member names ("Hiren", PIN `369`) are successfully received, stored, and displayed on physical eSSL MB20 biometric terminals instead of being wiped out to blank.

**Architecture:** Replace the erroneous hardware `OPERLOG` dump emulation string (`Pri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000`) in `AdmsDeviceCommand::userCommandString()` with the clean, standard ZKTeco Push SDK specification format (`C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tPrivilege={privilege}`). Update corresponding unit and feature tests to enforce the standard wire format, run verification inside Docker, and update architectural documentation.

**Tech Stack:** PHP 8.2, Laravel 12, Filament v3, ZKTeco ADMS / Push SDK v2.4.1/v3.1, PHPUnit 11.

**Spec:** [docs/biometric/adms-userinfo-name-synchronization-findings.md](file:///c:/xampp/htdocs/projects/gym-app/docs/biometric/adms-userinfo-name-synchronization-findings.md)

## Global Constraints

- **NO GIT COMMIT OR ADD**: The user reviews and stages files manually. Do not run `git commit` or `git add`.
- **DATABASE SAFETY**: The main database is `gym_app`. The test database is `gym_app_test`. Never run destructive commands against `gym_app`.
- **DOCKER EXECUTION**: All artisan / PHP commands must run inside the Docker container via `docker-compose exec -T app ...` with `BypassSandbox: true`.
- **STRICT TESTING**: Tests must be updated first (TDD), executed, and proven passing.
- **CODE STYLE**: Must pass Laravel Pint formatter (`docker-compose exec -T app vendor/bin/pint --dirty --format agent`).

## Review Focus

1. **Empty Token Elimination**: Verify that no empty tokens such as `Passwd=` or `Card=` are transmitted over the wire, preventing embedded C-parser tokenizer halting.
2. **Access Control Separation**: Ensure speculative access group (`Grp=1`) and timezone bitmask (`TZ=0000000100000000`) tokens are omitted from basic user enrollment commands.
3. **Privilege Attribute Standardization**: Ensure `Privilege=0` is used for `USERINFO` schema while keeping legacy `Pri=0` for the legacy `user` schema.
4. **Name Casing & Escaping**: Verify that `Name` remains PascalCase and control characters (`\r`, `\n`, `\t`) are sanitized to spaces without line breaking.
5. **Screen Cache Persistence**: Ensure that after sending a clean `user_add` command, a subsequent or operator-initiated hardware reboot (`CONTROL DEVICE 03000000`) reliably refreshes the TFT GUI memory cache without blanking the name.

---

### Task 1: Update Wire Format Specification Tests

**Files:**
- Modify: `tests/Unit/Biometrics/AdmsDeviceCommandWireFormatTest.php:15-65`
- Modify: `tests/Feature/AdmsProtocolSanitizerTest.php:70-130`

**Interfaces:**
- Consumes: `App\Models\AdmsDeviceCommand::toAdmsCommandString()`
- Produces: Wire format assertions expecting `C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tPrivilege=0`

- [ ] **Step 1: Update unit tests in `AdmsDeviceCommandWireFormatTest.php`**
  - Update `user_add_with_userinfo_schema_produces_device_native_wire_format` to assert:
    `C:11:DATA UPDATE USERINFO PIN=369\tName=Hiren\tPrivilege=0`
  - Remove negative assertion `assertStringNotContainsString('Privilege=', ...)`.
  - Add negative assertion `assertStringNotContainsString('Pri=', ...)` for `USERINFO`.
  - Add negative assertions for `Passwd=`, `Card=`, `Grp=`, and `TZ=`.
  - Update `user_update_with_userinfo_schema_produces_device_native_wire_format` to assert:
    `C:19:DATA UPDATE USERINFO PIN=369\tName=Hiren Sarla\tPrivilege=0`

- [ ] **Step 2: Update feature tests in `AdmsProtocolSanitizerTest.php`**
  - Update `test_userinfo_emits_native_hardware_payload_fields` to assert:
    `C:10:DATA UPDATE USERINFO PIN=369\tName=Hiren Sarla\tPrivilege=0`
  - Update `test_payload_accepts_case_insensitive_name_key` to assert:
    `C:10:DATA UPDATE USERINFO PIN=369\tName=Hiren Sarla\tPrivilege=0`
  - Update `test_untrusted_password_and_card_payload_fields_are_safely_ignored` to assert:
    `C:10:DATA UPDATE USERINFO PIN=369\tName=Hiren Sarla\tPrivilege=0`

- [ ] **Step 3: Run PHPUnit tests to verify failure (TDD Red step)**
  - Command: `docker-compose exec -T app php artisan test --filter=AdmsDeviceCommandWireFormatTest`
  - Expected: Fails because `AdmsDeviceCommand.php` still emits `Pri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000`.

---

### Task 2: Implement Clean Wire Format in `AdmsDeviceCommand.php`

**Files:**
- Modify: `app/Models/AdmsDeviceCommand.php:445-475`

**Interfaces:**
- Consumes: `$wireTable`, `$pinKey`, `$pin`, `$name`, `$privilege`
- Produces: `C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tPrivilege={privilege}` when `$wireTable === 'USERINFO'`

- [ ] **Step 1: Update `userCommandString()` in `app/Models/AdmsDeviceCommand.php`**
  - Replace lines 458-468:
    ```php
    if ($wireTable === 'USERINFO') {
        // ZKTeco Push SDK / BioTime Standard Wire Format:
        // C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tPrivilege={privilege}
        return sprintf(
            "C:%d:DATA UPDATE USERINFO PIN=%s\tName=%s\tPrivilege=%d",
            $this->id,
            $pin,
            $name,
            $privilege
        );
    }
    ```
  - Keep legacy `user` schema intact:
    ```php
    return sprintf(
        "C:%d:DATA UPDATE %s %s=%s\tName=%s\tPri=0",
        $this->id,
        $wireTable,
        $pinKey,
        $pin,
        $name
    );
    ```

- [ ] **Step 2: Run PHPUnit tests to verify pass (TDD Green step)**
  - Command: `docker-compose exec -T app php artisan test --filter=AdmsDeviceCommandWireFormatTest`
  - Expected: PASS
  - Command: `docker-compose exec -T app php artisan test --filter=AdmsProtocolSanitizerTest`
  - Expected: PASS

---

### Task 3: Format Code with Pint

**Files:**
- Modify: Any touched files

- [ ] **Step 1: Run Pint code formatter**
  - Command: `docker-compose exec -T app vendor/bin/pint --dirty --format agent`
  - Expected: Formatted cleanly with 0 errors.

---

### Task 4: Update Documentation & ADR

**Files:**
- Create: `docs/adr/adr-0013-biometric-adms-userinfo-clean-wire-format-and-name-persistence.md`
- Modify: `docs/biometric/adms-userinfo-name-synchronization-findings.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Create ADR-0013 documenting the root cause and standard wire format**
  - Detail why copying the `OPERLOG` dump (`Pri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000100000000`) caused embedded C tokenizer errors and blanked the terminal LCD name.
  - Document the clean standard Push SDK format `C:{id}:DATA UPDATE USERINFO PIN={pin}\tName={name}\tPrivilege=0`.
- [ ] **Step 2: Update `adms-userinfo-name-synchronization-findings.md`**
  - Document the live verification on device `EUF7262500413`, the blank name regression caused by `Passwd=\tCard=`, and the verified resolution.
- [ ] **Step 3: Update `CHANGELOG.md`**
  - Note the fix for ADMS user name synchronization under `[Unreleased]`.
