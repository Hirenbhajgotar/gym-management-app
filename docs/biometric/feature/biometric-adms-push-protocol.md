---
feature: Biometric ADMS Push Protocol
status: accepted
last_updated: 2026-10-30
author: docs-agent
related_docs:
  - ../architecture/ADR-004-adms-push-protocol-and-lifecycle.md
  - ../../adr/adr-0004-member-archival-and-biometric-erasure-lifecycle.md
  - ../../adr/adr-0005-biometric-adms-communication-key-hardware-standardization.md
  - ../../adr/adr-0006-biometric-adms-legacy-hardware-endpoint-and-transport-compatibility.md
  - ../architecture/ADR-002-door-access-attendance-integrity.md
  - ../database/adms-device-commands-fields.md
  - ../database/biometric-templates-fields.md
  - ../database/biometric-devices-fields.md
  - ../database/attendances-fields.md
  - ./biometric-checkin-flow.md
  - ./door-access-control.md
---

# Biometric ADMS Push Protocol

## Overview

The **ADMS (Automatic Data Master Server)** push protocol provides seamless integration for smart hardware terminals (primarily **eSSL**, **ZKTeco**, and compatible OEM models) as well as **Hikvision ISAPI** access control terminals.

Unlike purely webhook-driven cloud setups, ADMS-capable hardware terminals verify biometrics directly on-chip and autonomously push attendance verification events to the server. Simultaneously, the server uses a command queue to push user provisioning and deprovisioning operations (`DATA UPDATE USERINFO`, `DATA DELETE USERINFO`, or configurable `user` table per [ADR-0007](../../adr/adr-0007-biometric-adms-multi-device-table-schema-negotiation.md)) down to the physical memory of the hardware terminals when members register or expire.

---

## Dual-Channel Architecture (Webhook + ADMS Coexistence)

Terminals can operate in **Dual-Channel Mode**, leveraging the best capabilities of both protocols simultaneously on a single device:

```
┌────────────────────────────────────────────────────────────────────────┐
│                   Physical Terminal (eSSL / ZKTeco)                    │
└───────────────────┬────────────────────────────────┬───────────────────┘
                    │                                │
    Channel 1: ADMS Push Protocol         Channel 2: Vendor Webhook
   (Attendance Sync & Member Mgmt)         (Real-time Door Control)
                    │                                │
                    ▼                                ▼
       POST /api/iclock/cdata           POST /api/v1/biometric/webhook/{token}
    (Device pushes ATTLOG records)        (Device requests door unlock relay)
                    │                                │
                    ▼                                ▼
            AdmsEventService                 CheckInService / DoorControl
  (Auto-checkout, anti-passback,               (Relay / Wiegand trigger,
   Attendance DB, Reverb broadcast)           instant door access response)
                    │
                    ▼
       GET /api/iclock/getrequest
   (Server-to-Device Command Polling:
    DATA DELETE USERINFO PIN=..., etc.)
```

1. **Vendor Webhook (`/api/v1/biometric/webhook/{token}`)**:
   - High-speed door unlock decisions.
   - Responds with immediate relay control directives (`door.action: unlock`).
2. **ADMS Protocol (`/api/iclock/*`)**:
   - Device pushes attendance event records (`ATTLOG`).
   - Device regularly polls for pending server commands (`getrequest`).
   - Terminal acknowledges executed commands (`devicecmd`).
   - Responds with standard ADMS protocol strings (`OK`, `C:{id}:...`).

---

## Endpoints Reference

Every endpoint is registered on both the `/api/iclock/*` and unprefixed `/iclock/*` surfaces, and each has an `.aspx`-suffixed alias (for example `POST /api/iclock/cdata.aspx`). Canonical and suffixed forms share the same controller action, middleware, and plain-text responses.

All ADMS endpoints are publicly accessible to terminals without session cookies, authenticating via device serial numbers (`SN`) and optional communication keys (`pushcommkey`):
- **Device Resolution & Auto-Recovery**: `BiometricDevice::findBySerial($sn)` resolves enabled devices. If an `Inactive` device (e.g. marked inactive by the deadman health check) transmits authenticated communication, it is auto-recovered to `Active` status and its consecutive miss counter is cleared.
- **Communication Key Verification & Hardware Standardization**: Per [ADR-0008](../../adr/adr-0008-biometric-adms-per-device-comm-key-negotiation.md), communication key verification is negotiated per-device. If a device has a key configured (`adms_comm_key_hash` or a legacy `provider_config` alias), incoming requests MUST provide a matching `pushcommkey` (query, body, or header). Missing or invalid keys return HTTP 401 with a uniform `ERR: Unauthorized` body; the specific reason is logged server-side only. If a device has no key configured (e.g. eSSL MB20 or legacy terminals that lack cloud pushcommkey menus), requests authenticate purely via hardware serial number (`SN`), provided global enforcement (`ADMS_REQUIRE_COMM_KEY`, default `false`) is not enabled. A device whose `provider_config` cannot be decrypted is treated as keyed (it may still hold a credential), so it never degrades to serial-only authentication; repair or replace the ciphertext to restore access. Under global enforcement an unkeyed terminal is refused without accruing a lockout (the refusal is unconditional, so a counter would cap nothing), while dummy PBKDF2 work keeps the response timing indistinguishable. Per [ADR-0005](../../adr/adr-0005-biometric-adms-communication-key-hardware-standardization.md), generated keys are standardized to 6-digit numeric PINs (`100000`–`999999`) using `BiometricDevice::generateCommKey()` to comply with eSSL (MB-20, KM-20, K90) and ZKTeco physical keypad firmware constraints. Incoming plain keys are sanitized via `BiometricDevice::sanitizeCommKey()` to remove ASCII/Unicode whitespace and format characters before PBKDF2 hash verification (`verifyCommKey()`). A raw-whitespace verifier self-heals to the sanitized form only when the terminal presents the exact raw form (or a matching plaintext exists for the backfill to re-hash); a terminal that presents only the sanitized form must be rotated. Failed attempts are counted per device (`ADMS_AUTH_MAX_FAILURES`, default 5) and lock the device (`ADMS_AUTH_LOCKOUT_SECONDS`, default 900) **before** verification, so guesses are capped; a locked-out terminal is rejected even with the correct key until the cooldown expires or an operator runs `php artisan biometrics:reset-adms-lockout --device-id={id}` (or `--serial`, including disabled devices). Rotation/re-provisioning clears the counter. New keys are exactly 6 alphanumeric characters. `provider_config['comm_key']` is an ADMS alias for device-native devices, including the legacy `provider_type = null` shape; on an explicit cloud provider it is a provider secret and is never read or deleted by ADMS code. A cloud→native switch records `comm_key_source = 'cloud'` so the preserved credential stays out of ADMS aliases and cleanup. A device-native `comm_key` is translated to `pushcommkey` when the provider leaves native mode, so a later return switch does not strand the only plaintext key. Generated keys are stored encrypted for a one-time modal reveal (never in a session-backed notification) and the value is published only after the create/save transaction commits. Error reports drop Livewire's serialized component snapshot and dotted update map before storage, and the error-report edit page renders technical fields read-only so a status change cannot overwrite the stored report. An operator can also remove a device's key (Remove Comm Key), reverting it to serial-only authentication: the action is refused while `ADMS_REQUIRE_COMM_KEY=true`, is hidden when the device has no key, its ADMS protocol is disabled, or its `provider_config` is unreadable, and it clears the PBKDF2 verifier and the ADMS aliases, forgets any pending one-time reveal and resets the failure counter.

### 1. `POST|GET /api/iclock/cdata`
- **Purpose**: Handshake, heartbeat, and attendance log (`ATTLOG`) push.
- **Query Parameters**:
  - `SN`: Device hardware serial number (e.g. `TEST-SN-001`).
  - `table`: Table name being uploaded (e.g. `ATTLOG`, `OPERLOG`).
- **Body Format** (Tab-delimited text):
  ```
  {userId}\t{timestamp}\t{status}\t{verifyMode}\t{workCode}
  ```
  - `status`: `0` = Check-in, `1` = Check-out.
  - `verifyMode`: `1` = Fingerprint, `2` = Card, `9` = Palm, `15` = Face.
- **Response**: Plain text `OK` (or `ERR: Unauthorized` with HTTP 401).

### 2. `GET /api/iclock/getrequest`
- **Purpose**: Polled periodically by terminals to fetch pending management commands.
- **Query Parameters**:
  - `SN`: Device hardware serial number.
- **Response**:
  - If commands pending: formatted ADMS command string in the schema snapshotted on the command (e.g. `C:15:DATA DELETE USERINFO PIN=42`, or `C:15:DATA DELETE user Pin=42` for terminals configured with the legacy `user` table).
  - If no commands: `OK`.
- **Side Effect**: Marks the dequeued `AdmsDeviceCommand` as `sent` (`AdmsCommandStatusEnum::SENT`).

### 3. `POST /api/iclock/devicecmd`
- **Purpose**: Terminal acknowledges command execution result.
- **Query Parameters**:
  - `SN`: Device hardware serial number.
- **Body Format** (`application/x-www-form-urlencoded`):
  ```
  ID=15&Return=0
  ```
- **Response**: Plain text `OK`.
- **Side Effect**: Updates `AdmsDeviceCommand` status to `acknowledged` (`AdmsCommandStatusEnum::ACKNOWLEDGED` if `Return=0`, or `FAILED` if non-zero) and saves the raw return code.

### 4. `POST /api/v1/biometric/hikvision/event`
- **Purpose**: Ingestion endpoint for Hikvision access control terminals pushing ISAPI XML event payloads.
- **Authentication**: `AuthenticateBiometricDevice` middleware via Bearer device token in `Authorization` header.
- **Body Format**: XML document containing `<EventNotificationAlert>` with `<AccessControllerEvent>`.
- **Response**: JSON `{"status": "ok", "action": "..."}`.

---

## Event Processing Pipeline (`AdmsEventService`)

The [AdmsEventService](../../../app/Biometrics/Services/AdmsEventService.php) processes incoming verified events through the following sequence:

```
Terminal Event (SN, userId, timestamp, status, verifyMode)
  │
  ├─► Step 1: Device Lookup
  │   Cached O(1) Redis resolution via BiometricDevice::findBySerial($sn)
  │
  ├─► Step 2: Member Resolution
  │   Find GymMember matching BiometricTemplate.template_ref OR GymMember.id
  │   (template lookups are scoped to this terminal's device, plus cloud rows
  │    with no device; an orphan enrolled elsewhere is never attributed here)
  │
  ├─► Step 3: Guard Verification (MembershipGuard)
  │   - Active membership validity
  │   - Payment status (COMPLETED)
  │   - Freeze status (not frozen)
  │   - Biometric consent granted
  │   └─► If denied: broadcast AdmsAccessDenied, return denied
  │
  ├─► Step 4: Check-in vs Check-out Determination
  │   - If status = 1 (Explicit Checkout): Proceed to checkout
  │   - If status = 0 (Check-in): Check if member has an open attendance today
  │     └─► If open attendance exists: Auto-detect as Checkout
  │     └─► Otherwise: Proceed to check-in
  │
  ├─► Step 5A: Process Check-Out
  │   - Update active attendance: check_out_time = $timestamp, status = Completed
  │   - Broadcast MemberCheckedOut event
  │   - Return AdmsEventResult::checkedOut
  │
  └─► Step 5B: Process Check-In
      - Acquire Anti-Passback Lock via Redis (120s window)
      - Create Attendances record (provider_type = 'adms', status = Present)
      - Broadcast MemberCheckedIn event
      - Return AdmsEventResult::checkedIn
```

### Auto-Checkout Detection Logic
Many gym devices only have a single biometric sensor where members swipe upon entering and leaving. When a member scans and `status=0` is sent:
- If the member already has an attendance record on that date with `check_in_result = 'granted'` and `check_out_time IS NULL`, the system detects this as an **automatic check-out** and closes the session.
- Because check-out is determined before anti-passback evaluation, valid check-outs are never blocked by check-in anti-passback locks.

---

## Expired Member Cleanup Lifecycle

To prevent long-expired members from continuing to access the facility when devices are offline or when terminals store local credentials, the system enforces automated hardware deprovisioning.

### Three-Layer Grace Period Formula

A member is only purged from physical terminal memory after exceeding the combined grace threshold:

$$\text{Cutoff Date} = \text{now}() - (\text{gym\_grace\_days} + \text{adms\_grace\_days})$$

Where:
- **`membership_end`**: Base expiration date of the member's subscription.
- **`gym.settings.grace_period_days`**: Gym-specific grace period (stored in `gym_settings`, fallback to `subscription.grace_period_days` = 7 days).
- **`config('biometrics.adms.expired_member_grace_days')`**: ADMS hardware deletion buffer (default: **10 days**).

### Automated Daily Command (`CleanupExpiredDeviceMembers`)

The scheduled command runs daily at **03:00**:
```bash
php artisan app:cleanup-expired-device-members
```

**Actions executed per expired member:**
1. Queries members whose `membership_end` is older than the combined grace cutoff.
2. Identifies all active `BiometricTemplate` records for those members.
3. For each template enrolled on an `adms_enabled` device:
   - Verifies no duplicate `user_delete` command is currently pending or sent.
   - Creates an `AdmsDeviceCommand` of type `user_delete` with `Pin = {member_id}` and status `AdmsCommandStatusEnum::PENDING`.
4. Deactivates the biometric template (`is_active = false`).
5. Purges member access summary from Redis cache (`RedisBiometricCacheService::invalidateMember`).
6. Logs the operation to audit logs.

---

## Device Command Queue & Status Enum

Server-to-device commands are managed via the [AdmsDeviceCommand](../../../app/Models/AdmsDeviceCommand.php) model with the backing enum [AdmsCommandStatusEnum](../../../app/Enums/AdmsCommandStatusEnum.php):

| Status Enum Case | String Value | Filament Badge Color | Meaning |
|---|---|---|---|
| `AdmsCommandStatusEnum::PENDING` | `'pending'` | `warning` (Yellow) | Queued in DB, awaiting terminal polling |
| `AdmsCommandStatusEnum::SENT` | `'sent'` | `info` (Blue) | Terminal fetched the command via `getrequest` |
| `AdmsCommandStatusEnum::ACKNOWLEDGED` | `'acknowledged'` | `success` (Green) | Terminal executed command and returned code `0` |
| `AdmsCommandStatusEnum::FAILED` | `'failed'` | `danger` (Red) | Terminal returned non-zero error code |

### Command Pruning Lifecycle
Old executed commands (`ACKNOWLEDGED` and `FAILED`) are pruned automatically using Laravel's `Prunable` trait based on `config('biometrics.adms.command_retention_days')` (default 90 days), evaluated on `acknowledged_at` (falling back to `updated_at`).

### Member Archival Deprovisioning & Hardware Credential Epoch Tracking
When an operator archives a member (or when `app:archive-inactive-gym-members` executes the 2-year inactivity sweep), [MemberArchiveService](../../../app/Services/MemberArchiveService.php) immediately initiates physical credential revocation via [BiometricDeProvisioner](../../../app/Biometrics/Services/BiometricDeProvisioner.php):
- **Synchronous Command Queuing**: Queues a schema-negotiated `DATA DELETE` command (e.g. `C:{id}:DATA DELETE USERINFO PIN={memberId}`, or the lowercase `user` variant) for all active and pending templates across assigned hardware terminals.
- **Hardware Credential Epoch Tracking**: To prevent dead-letter loops where a previous deletion command failed at the maximum attempt cap, [AdmsDeviceCommand](../../../app/Models/AdmsDeviceCommand.php) tracks `epoch_credential_user_add_id`. When a restored member re-enrolls, the subsequent `user_add` establishes a new credential epoch. If the member is later archived again, `BiometricDeProvisioner::hasUnconsumedHardwareCredential()` recognizes the newer epoch and safely re-drives the terminal deletion without getting blocked by previous attempt caps.
- **Biometric Operator Attention**: Stuck or unresolvable deletions that reach the attempt cap are automatically surfaced to operators via the `BiometricOperatorAttentionWidget` and the "Needs Operator" filter on `BiometricTemplatesRelationManager`.

### Fail-Closed Webhook HMAC Authentication
In dual-channel mode, biometric devices dispatch instant door-unlock requests to `/api/v1/biometric/webhook/{token}`:
- **Mandatory HMAC-SHA256**: [BiometricWebhookController](../../../app/Http/Controllers/Api/BiometricWebhookController.php) mandates that incoming webhook requests must be cryptographically signed via `X-Hub-Signature-256` or `X-Signature`.
- **Fail-Closed Stance**: Devices lacking a configured `webhook_secret` in their `provider_config` are immediately refused with HTTP 401, completely eliminating bearer-token vulnerability over raw URLs.
- **Secret Provisioning**: Gym operators can generate and reveal device signing secrets using `php artisan biometrics:provision-webhook-secrets --gym={id} --reveal`.

---

## Filament Admin UI Management

### 1. Biometric Devices (`BiometricDeviceResource`)
- **Device Information**: Input for `device_serial` (Hardware Serial Number / SN).
- **Status & Protocol**:
  - `adms_enabled` toggle switch.
  - Setup instructions placeholder displaying the exact ADMS server address:
    `{app_url}/api/iclock/cdata`
  - Guidance on terminal setup: `Menu > Comm > ADMS / Cloud Server`.
- **Table Columns & Actions**:
  - `device_serial` column (toggleable).
  - `adms_enabled` icon column.
  - **"View Commands"** action: Quick-jumps directly to the filtered command queue for that device.

### 2. Device Commands (`AdmsDeviceCommandResource`)
- Tenant-scoped read-only audit log of all device commands.
- Visual status badges powered directly by `AdmsCommandStatusEnum`.
- Interactive JSON payload modal inspector and device return code display.
- **Resend Action**: Single and bulk action to re-queue stuck or failed commands back to `PENDING`.

---

## Configuration (`config/biometrics.php`)

```php
'heartbeat_timeout_minutes' => (int) env('BIOMETRIC_HEARTBEAT_TIMEOUT_MINUTES', 15),
'heartbeat_consecutive_misses' => (int) env('BIOMETRIC_HEARTBEAT_CONSECUTIVE_MISSES', 1),

'adms' => [
    'enabled' => filter_var(env('ADMS_ENABLED', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
    'require_comm_key' => filter_var(env('ADMS_REQUIRE_COMM_KEY', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
    'pbkdf2_iterations' => filter_var(env('ADMS_PBKDF2_ITERATIONS', 100000), FILTER_VALIDATE_INT, [
        'options' => ['default' => 100000, 'min_range' => 1],
    ]),
    'allow_legacy_unsanitized_comm_key' => filter_var(env('ADMS_ALLOW_LEGACY_UNSANITIZED_COMM_KEY', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
    'auth_max_failures' => filter_var(env('ADMS_AUTH_MAX_FAILURES', 5), FILTER_VALIDATE_INT, [
        'options' => ['default' => 5, 'min_range' => 1],
    ]),
    'auth_lockout_seconds' => filter_var(env('ADMS_AUTH_LOCKOUT_SECONDS', 900), FILTER_VALIDATE_INT, [
        'options' => ['default' => 900, 'min_range' => 1],
    ]),
    'command_lease_seconds' => (int) env('ADMS_COMMAND_LEASE_SECONDS', 600),
    'command_retention_days' => (int) env('ADMS_COMMAND_RETENTION_DAYS', 90),
    'receipt_retention_days' => (int) env('ADMS_RECEIPT_RETENTION_DAYS', 30),
    'expired_member_grace_days' => (int) env('ADMS_EXPIRED_GRACE_DAYS', 10),
],
```

The PBKDF2 verification floor is the fixed `BiometricDevice::MIN_PBKDF2_ITERATIONS` (100,000) constant in every environment. The test suite lowers it at runtime in the testing environment only; there is no production environment variable for the floor.

---

## Reverse Proxy & Transport Compatibility (Nginx)

Budget and entry-level biometric hardware lack hardware SSL/TLS crypto offloading and do not follow HTTP 301/302 redirects. Production reverse proxies (Nginx) must be configured on port 80 to bypass HTTPS redirection exclusively for biometric ADMS endpoints:

```nginx
server {
    listen 80;
    server_name your-gym-domain.com;

    root /var/www/gym-management-app/public;
    index index.php index.html;

    # Intercept ADMS traffic on plain HTTP without 301 redirect
    location /iclock/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /api/iclock/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    # Enforce HTTPS redirection for all other human/browser traffic
    location / {
        return 301 https://$host$request_uri;
    }
}
```

---

## Testing & Verification

Feature tests covering the full ADMS protocol and cleanup lifecycles:

- **[`tests/Feature/AdmsProtocolTest.php`](../../../tests/Feature/AdmsProtocolTest.php)**:
  - Check-in event processing via raw ATTLOG push.
  - Automatic check-out detection for open attendance records.
  - Denial of expired members with `AdmsAccessDenied` broadcast.
  - Rejection of unknown device serial numbers (HTTP 401).
  - Anti-passback duplicate rejection for rapid check-ins.
  - eSSL device format compatibility.
  - Server command queue retrieval and state transition to `SENT`.
  - Command acknowledgment and state transition to `ACKNOWLEDGED`.
  - Terminal handshake and heartbeat updates.
  - Dual-mode coexistence with existing vendor webhook endpoint.

- **[`tests/Feature/CleanupExpiredDeviceMembersTest.php`](../../../tests/Feature/CleanupExpiredDeviceMembersTest.php)**:
  - Automated command queuing for expired members beyond the 3-layer grace period.
  - Respect for custom gym grace period settings.
  - Exclusion of members currently within their grace window.
  - Biometric template deactivation.
  - Prevention of duplicate pending commands.
