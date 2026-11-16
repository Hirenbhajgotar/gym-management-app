# ADMS eSSL MB20 Name Synchronization & Hardware Reboot Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix remote terminal reboot by implementing the hardware-level `CONTROL DEVICE 03000000` ADMS wire command so that eSSL MB20 biometric terminals physically restart and flush their in-memory GUI cache to display synchronized member names on the terminal LCD.

**Architecture:** Update `AdmsDeviceCommand::toAdmsCommandString()` to emit the ZKTeco Push SDK hardware-level control command `CONTROL DEVICE 03000000` for `reboot` commands instead of application-level `Restart`/`REBOOT` strings that are swallowed by the ZLM60 firmware without restarting the Linux kernel. Update tests in `AdmsRebootDeviceCommandTest` and `AdmsProtocolSanitizerTest` to assert the new wire command and verify CRLF framing and acknowledgment handling. Update technical documentation to reflect the findings on embedded process separation (ADMS SQLite vs TFT RAM cache).

**Tech Stack:** PHP 8.2, Laravel 12, PHPUnit 11, Filament v3, ZKTeco ADMS / Push SDK v2.4.1

**Spec:** `docs/biometric/adms-userinfo-name-synchronization-findings.md` and `root-cause-analysis.md` (Artifact ID: `root-cause-analysis.md`)

## Global Constraints

- **No Agent Git Commits:** Do NOT run `git commit` or `git add`. The user reviews and stages files manually.
- **Database Safety:** Main DB is `gym_app`, test DB is `gym_app_test`. NEVER run destructive migrations on `gym_app`. Run test DB resets via `docker-compose exec app composer test:db:reset`.
- **Docker Environment:** All PHP/Artisan/Composer commands must execute inside Docker via `docker-compose exec app ...` with `BypassSandbox: true`.
- **PHP Conventions:** PHP 8.2 strict typing `declare(strict_types=1);`, explicit parameter and return types, no inline comments, single quotes for strings.
- **Code Formatting:** Format modified PHP files using `docker-compose exec app vendor/bin/pint --dirty --format agent`.

## Review Focus

1. **Hardware Restart Wire Format:** Terminal receives `C:<id>:CONTROL DEVICE 03000000\r\n` and successfully initiates a real hardware reboot cycle (taking 25-45s rather than polling 1s later).
2. **Device ACK Handling:** Device reports back `ID=<id>&Return=0&CMD=CONTROL DEVICE` which must be parsed by `AdmsController::deviceCmd()` without failure.
3. **In-Flight Protection:** Prevent duplicate reboot commands from piling up in the queue while one is already pending or sent (`PENDING` or `SENT`).
4. **Wire Serialization Agnosticism:** Non-PIN-addressed commands (`reboot`, `clear_log`, `INFO`) must format cleanly regardless of whether `user_table` is `USERINFO` or `user`.
5. **CRLF Line Framing:** Ensure `AdmsController::getRequest()` outputs commands strictly terminated with RFC-compliant `\r\n` (CRLF) as required by embedded microcontrollers.

---

### Task 1: Wire Protocol Update - Hardware Restart via `CONTROL DEVICE 03000000`

**Files:**
- Modify: `app/Models/AdmsDeviceCommand.php:358-367`
- Test: `tests/Feature/AdmsRebootDeviceCommandTest.php`
- Test: `tests/Feature/AdmsProtocolSanitizerTest.php`

**Interfaces:**
- Consumes: `AdmsDeviceCommand::$command_type` (`'reboot'`) and `AdmsDeviceCommand::$id`
- Produces: `toAdmsCommandString(): string` returning `sprintf('C:%d:CONTROL DEVICE 03000000', $this->id)`

- [ ] **Step 1: Write the failing test in `tests/Feature/AdmsRebootDeviceCommandTest.php` and `tests/Feature/AdmsProtocolSanitizerTest.php`**

Update assertions in `tests/Feature/AdmsRebootDeviceCommandTest.php`:
```php
// In test: it_queues_reboot_command_by_device_id
$this->assertSame("C:{$command->id}:CONTROL DEVICE 03000000", $command->toAdmsCommandString());

// In test: polled_reboot_command_is_delivered_with_crlf_terminator
$this->assertStringContainsString(':CONTROL DEVICE 03000000', $content);
```

Update assertions in `tests/Feature/AdmsProtocolSanitizerTest.php`:
```php
// In test: it_serializes_non_pin_commands_regardless_of_schema
$this->assertSame('C:3:CONTROL DEVICE 03000000', $reboot->toAdmsCommandString());

// In test: it_serializes_empty_payload_non_pin_commands_without_error
$this->assertSame('C:3:CONTROL DEVICE 03000000', $reboot->toAdmsCommandString());
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker-compose exec app php artisan test --filter=AdmsRebootDeviceCommandTest`
Expected: FAIL with `Failed asserting that two strings are identical` (expected `:CONTROL DEVICE 03000000`, got `:Restart`).

- [ ] **Step 3: Implement `CONTROL DEVICE 03000000` wire command in `app/Models/AdmsDeviceCommand.php`**

In `app/Models/AdmsDeviceCommand.php` around line 360, update the `'reboot'` branch of the match expression:
```php
if (! $this->isPinAddressed()) {
    return match ($this->command_type) {
        'reboot' => sprintf('C:%d:CONTROL DEVICE 03000000', $this->id),
        'clear_log' => sprintf('C:%d:CLEAR LOG', $this->id),
        'query_all_users' => ($this->user_table ?? AdmsUserTableEnum::resolve(config('biometrics.adms.user_table'))) === AdmsUserTableEnum::UserInfo
            ? sprintf('C:%d:DATA QUERY USERINFO', $this->id)
            : sprintf('C:%d:DATA QUERY user', $this->id),
        default => sprintf('C:%d:INFO', $this->id),
    };
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker-compose exec app php artisan test --filter=AdmsRebootDeviceCommandTest`
Run: `docker-compose exec app php artisan test --filter=AdmsProtocolSanitizerTest`
Expected: All tests in both test suites PASS.

- [ ] **Step 5: Code formatting check**

Run: `docker-compose exec app vendor/bin/pint --dirty --format agent`
Expected: Pint passes with 0 violations.

---

### Task 2: Verify Devicecmd ACK Parsing for `CONTROL DEVICE`

**Files:**
- Test: `tests/Feature/AdmsRebootDeviceCommandTest.php`
- Verify: `app/Http/Controllers/Api/AdmsController.php:181-240`

**Interfaces:**
- Consumes: HTTP POST `/iclock/devicecmd` with body `ID=<id>&Return=0&CMD=CONTROL DEVICE`
- Produces: `AdmsDeviceCommand` status transitioning to `ACKNOWLEDGED` and HTTP 200 `OK` response.

- [ ] **Step 1: Write test for devicecmd ACK of `CONTROL DEVICE` command**

In `tests/Feature/AdmsRebootDeviceCommandTest.php`, add a new test method:
```php
public function it_acknowledges_control_device_reboot_command_from_devicecmd(): void
{
    $device = BiometricDevice::factory()
        ->admsEnabled('SN-REBOOT-ACK-001')
        ->create();

    $command = AdmsDeviceCommand::query()->create([
        'gym_id' => $device->gym_id,
        'device_id' => $device->id,
        'device_serial' => $device->device_serial,
        'command_type' => 'reboot',
        'status' => AdmsCommandStatusEnum::SENT,
        'idempotency_key' => 'reboot:ack:test:1',
    ]);

    $response = $this->post(
        "/iclock/devicecmd?SN={$device->device_serial}",
        "ID={$command->id}&Return=0&CMD=CONTROL DEVICE",
        ['Content-Type' => 'text/plain']
    );

    $response->assertOk();
    $this->assertSame('OK', $response->getContent());
    $this->assertSame(AdmsCommandStatusEnum::ACKNOWLEDGED, $command->fresh()->status);
}
```

- [ ] **Step 2: Run test to verify it passes**

Run: `docker-compose exec app php artisan test --filter=it_acknowledges_control_device_reboot_command_from_devicecmd`
Expected: PASS (confirms `parse_str` and `markAcknowledged` handle spaces in `CMD=CONTROL DEVICE` cleanly).

- [ ] **Step 3: Code formatting check**

Run: `docker-compose exec app vendor/bin/pint --dirty --format agent`
Expected: Pint passes with 0 violations.

---

### Task 3: Documentation and Architectural Guidance Update

**Files:**
- Modify: `docs/biometric/adms-userinfo-name-synchronization-findings.md`
- Modify: `docs/adr/adr-0011-biometric-adms-reboot-command-and-display-cache-synchronization.md` (or relevant ADR)
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes: Root cause analysis findings regarding `CONTROL DEVICE 03000000`, ZLM60 TFT GUI memory architecture, and manual power cycle fallback.
- Produces: Updated markdown documentation and changelog records.

- [ ] **Step 1: Update `docs/biometric/adms-userinfo-name-synchronization-findings.md`**

Add section detailing:
1. `REBOOT` and `Restart` lack of hardware reset on ZLM60 firmware build `ZLM60-NF28VA-Ver2.0.12`.
2. Transition to `CONTROL DEVICE 03000000` (hex-based opcode `03` = hardware restart).
3. The necessity of a reboot (hardware restart via command or physical power cycle) to force `ssr_tft` foreground process to reload SQLite database (`device.db`) into RAM cache (`user.dat`).

- [ ] **Step 2: Update `CHANGELOG.md`**

Document the change under `[Unreleased]` or current section:
- Fix: Use `CONTROL DEVICE 03000000` for ADMS terminal reboot commands on eSSL / ZKTeco terminals.

---

### Task 4: Full Verification and Test Suite Run

**Files:**
- Verify: Full biometric test suite

**Interfaces:**
- Consumes: All updated codebase files
- Produces: Passing test reports and green Pint lint status

- [ ] **Step 1: Run complete Adms feature tests**

Run: `docker-compose exec app php artisan test --filter=Adms`
Expected: All tests pass.

- [ ] **Step 2: Run Pint linting on all modified files**

Run: `docker-compose exec app vendor/bin/pint --dirty --format agent`
Expected: Pass with 0 warnings or errors.
