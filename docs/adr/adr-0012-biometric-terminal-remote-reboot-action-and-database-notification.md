---
title: "ADR-0012: Biometric Terminal Remote Reboot GUI Action, Asynchronous Queueing, and Multi-Tenant Completion Notification"
status: "Accepted"
date: "2026-11-16"
authors: "Lead Architect, Biometric Systems Engineer, Senior Full-Stack Engineer"
tags: ["architecture", "decision", "biometrics", "adms", "filament", "reboot", "notifications", "multi-tenancy"]
supersedes: ""
superseded_by: ""
---

# ADR-0012: Biometric Terminal Remote Reboot GUI Action, Asynchronous Queueing, and Multi-Tenant Completion Notification

## Status

**Accepted**

## Context

Following the implementation of the CLI command `php artisan biometrics:reboot-device` (documented in ADR-0011) to address embedded LCD name synchronization anomalies, operational gym personnel (gym owners, managers, and receptionists) required an intuitive, self-service mechanism in the Filament Admin panel to trigger remote terminal restarts without technical shell access.

Several architectural constraints and requirements governed this capability:

1. **Non-Blocking User Experience**:
   - Biometric hardware using the ADMS push protocol operates asynchronously via periodic polling (`GET /iclock/getrequest`). Depending on device configuration and network conditions, a terminal may take from 5 to 60 seconds to pick up an instruction and complete power cycling.
   - Executing a reboot synchronously in an HTTP request would cause browser gateway timeouts, poor user experience, and risk concurrent duplicate triggers.

2. **Accurate, Targeted Feedback to the Requesting Operator**:
   - Because hardware reboots occur asynchronously in the background, operators need immediate feedback that the command was queued, followed by confirmation once the terminal has physically rebooted and reconnected.
   - In a multi-tenant system with multiple staff members across shifts, success alerts must not flood all gym staff; they must route directly to the specific operator who triggered the reboot.

3. **Multi-Tenant Authorization & Hardware Safeguards**:
   - A reboot briefly interrupts member check-ins. Therefore, triggering a restart requires strict authorization verification scoped to the operator's gym tenant (`userCanActOnGym`).
   - The system must prevent duplicate queueing when an existing reboot command is already `PENDING` or `SENT` for that terminal.

4. **Hardware Firmware Diversity in Command Acknowledgement**:
   - Live hardware profiling reveals two common acknowledgement patterns across ZKTeco/eSSL hardware:
     a. Standard terminals parse `C:{id}:REBOOT`, send `POST /iclock/devicecmd` (`ID={id}&Return=0`), and then reboot.
     b. Certain low-power or legacy terminals (such as eSSL MB20) parse the command, immediately terminate TCP sockets to reset hardware, and emit no return ACK before power cycling. When they boot up, their first HTTP call is `GET /iclock/cdata` (handshake).
   - If completion notification only listens for `devicecmd`, operators of connection-dropping firmware will never receive their success notification.

## Decision

We implemented a dedicated Filament Action, background ADMS queueing, policy gating, and dual-event notification architecture:

### 1. Dedicated Action Class (`RebootBiometricDeviceAction`)
- Created `App\Filament\Admin\Actions\RebootBiometricDeviceAction` providing both table row action (`make()`) and page action (`makeForPage()`) factories.
- Features:
  - Visible strictly on ADMS-enabled terminals (`adms_enabled = true`).
  - Styled with warning color and `heroicon-o-arrow-path` icon.
  - Interactive confirmation modal requiring operator confirmation, cautioning that check-ins will temporarily pause during restart.
  - In-flight command check querying `AdmsDeviceCommand` for `PENDING` or `SENT` status, preventing duplicate queueing.
  - Registers the requesting operator's ID in `command_payload['initiated_by_user_id'] = auth()->id()`.
  - Emits defensive audit trail logging via Spatie Activitylog (`activity()`).

### 2. Multi-Tenant Authorization via Policy Gate
- Added `reboot()` to `App\Policies\BiometricDevicePolicy` requiring both `userCanActOnGym($user, $device->gym_id)` and permission string `update_biometric_device`.
- Enforces strict tenant isolation across Filament admin surfaces.

### 3. Dual-Path Acknowledgement & Idempotent Completion Notification
- In `App\Http\Controllers\Api\AdmsController`:
  - **Path A (`deviceCmd`)**: When a terminal reports `devicecmd` with `Return=0`, `markAcknowledged('0')` transitions the command from `SENT` to `ACKNOWLEDGED` and dispatches `notifyRebootInitiator()`.
  - **Path B (`handleHandshake`)**: When a terminal performs `GET /iclock/cdata` handshake while a `SENT` reboot command remains active, the controller marks it acknowledged (`0`) and dispatches `notifyRebootInitiator()`.
  - **Idempotency Guard**: `notifyRebootInitiator()` stamps `reboot_notified_at` into `command_payload`, guaranteeing that if both `devicecmd` and `handleHandshake` trigger, the initiating user receives exactly one notification.
  - **Targeted Notification**: Dispatches a persistent Filament database notification (`Notification::make()->...->sendToDatabase($user)`) directed specifically to the initiating staff member's notification bell.

## Consequences

### Positive

- **POS-001**: **Self-Service GUI Operations**: Staff can remotely cycle terminals with one click directly from the device table without CLI access.
- **POS-002**: **Reliable Feedback**: Operators receive an instant confirmation toast on click, followed by a persistent bell notification once the terminal physically comes back online.
- **POS-003**: **Firmware Resilience**: Supports both pre-reboot ACK firmware and post-reboot handshake-rejoin firmware without missing notifications.
- **POS-004**: **Multi-Tenant Security**: Enforces gym tenant scoping at the policy layer so unauthorized cross-gym restarts are prevented.
- **POS-005**: **Reboot Loop Prevention**: In-flight command inspection blocks rapid double-clicks from queueing multiple restart cycles.

### Negative

- **NEG-001**: **Temporary Attendance Pause**: Physical terminal reboot requires 30–60 seconds, during which members cannot scan attendance at that terminal.
- **NEG-002**: **Asynchronous Polling Delay**: The reboot command is picked up on the device's polling cadence (typically 5–30 seconds), so execution is not instantaneous.

## Alternatives Considered

### Synchronous Blocking Modal with Polling Loop

- **ALT-001**: **Description**: Keep the modal open in Livewire and poll the backend every 2 seconds until the device reports reboot completion.
- **ALT-002**: **Rejection Reason**: Freezes the user's browser tab for up to a minute, drains Livewire connection cycles, and fails if the operator navigates away.

### Broad Staff Notification Broadcast

- **ALT-003**: **Description**: Broadcast the success notification to all users assigned to the gym tenant.
- **ALT-004**: **Rejection Reason**: Causes alert fatigue for uninvolved front-desk staff or trainers on duty; targeted notification to `initiated_by_user_id` delivers context only to the person who requested the reboot.

## Implementation Notes

- **IMP-001**: Action class implemented in `app/Filament/Admin/Actions/RebootBiometricDeviceAction.php`.
- **IMP-002**: Action registered in `app/Filament/Admin/Resources/BiometricDeviceResource.php` table `ActionGroup`.
- **IMP-003**: Policy gate implemented in `app/Policies/BiometricDevicePolicy.php::reboot()`.
- **IMP-004**: Protocol handling in `app/Http/Controllers/Api/AdmsController.php` (`deviceCmd` and `handleHandshake`).
- **IMP-005**: Test suite in `tests/Feature/Filament/BiometricDeviceRebootActionTest.php` covering action visibility, background queueing, duplicate prevention, cross-tenant denial, and database notification delivery across both HTTP protocols.

## References

- **REF-001**: [ADR-0011: Biometric ADMS Remote Reboot, Embedded GUI Cache Synchronization, and In-Flight Safeguards](adr-0011-biometric-adms-remote-reboot-gui-cache-synchronization-and-telemetry.md)
- **REF-002**: [ADMS Push Protocol Specification](../biometric/feature/biometric-adms-push-protocol.md)
- **REF-003**: [ADR-0003: Biometric Consent Distribution and Dual-Gate Architecture](adr-0003-biometric-consent-distribution-and-dual-gate-architecture.md)
