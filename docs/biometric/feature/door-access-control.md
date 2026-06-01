---
feature: Door Access Control
status: draft
last_updated: 2026-04-03
author: docs-agent
related_docs:
  - ../architecture/ADR-002-door-access-attendance-integrity.md
  - ../database/door-events-fields.md
  - ../database/biometric-devices-fields.md
  - ../database/attendances-fields.md
---

# Door Access Control

## Overview

Door access control integrates with biometric check-in to unlock physical doors when access is granted. The system supports three hardware protocols (relay, wiegand, API), logs all door events for audit, and enforces strict rules where attendance is only created if door unlock succeeds.

## Architecture

### Classes & Services Involved

| Class / File | Responsibility |
|---|---|
| `DoorControlService` (`app/Biometrics/Services/DoorControlService.php`) | Core service for door operations |
| `DoorControlResult` (`app/Biometrics/DTOs/DoorControlResult.php`) | DTO for door operation results |
| `DoorEvent` (`app/Models/DoorEvent.php`) | Audit log model for all door events |
| `DoorControllerUnreachable` (`app/Events/DoorControllerUnreachable.php`) | Event fired when door API fails |
| `DoorEventResource` (`app/Filament/Admin/Resources/DoorEventResource.php`) | Filament admin resource |
| `BiometricDevice` (`app/Models/BiometricDevice.php`) | Device with door configuration |
| `CheckInService` (`app/Biometrics/Services/CheckInService.php`) | Calls DoorControlService |

### Data Flow

```
CheckInService.processCheckIn()
    ↓
DoorControlService.unlock(device)
    ↓
    ├─→ Relay/Wiegand → return skipped() (success assumed)
    │
    └─→ API → callDoorApi(device)
              ├─→ Validate URL (trusted domains/subnets)
              ├─→ HTTP POST with Bearer token
              ├─→ On success → return unlocked()
              ├─→ On failure → log event + fire DoorControllerUnreachable event
              └─→ On exception → log event + fire event
```

### Design Patterns Used

- **DTO Pattern**: DoorControlResult encapsulates all door operation outcomes
- **Event-Driven**: DoorControllerUnreachable event allows notification system to alert staff

## Hardware Protocols

### Relay

- **door_control_type**: `relay`
- **How it works**: FluxFitness returns `door.action: "unlock"` in webhook response. Device has built-in relay and handles the physical door directly.
- **FluxFitness responsibility**: Just return "unlock" action — success assumed
- **DoorControlResult status**: `skipped`

### Wiegand

- **door_control_type**: `wiegand`
- **How it works**: FluxFitness returns `door.action: "unlock"` in webhook response. Device sends Wiegand signal to external access controller.
- **FluxFitness responsibility**: Just return "unlock" action — success assumed
- **DoorControlResult status**: `skipped`

### HTTP API

- **door_control_type**: `api`
- **How it works**: FluxFitness makes HTTP POST directly to door controller
- **Configuration required**: `door_controller_url`, `door_controller_token`
- **Timeout**: 3 seconds (configurable via `biometrics.door_controller_timeout`)

**API Request:**
```json
POST {door_controller_url}
Authorization: Bearer {door_controller_token}
Content-Type: application/json

{
  "action": "unlock",
  "duration": 5,
  "zone": "main_entrance"
}
```

**API Response (success):**
```json
{
  "status": "success",
  "unlocked_at": "2026-04-03T10:30:00Z"
}
```

**API Response (failure):**
```json
{
  "status": "error",
  "message": "Door controller offline"
}
```

## Security: URL Validation

The door controller URL is validated against trusted domains and subnets configured in `config/biometrics.php`:

```php
// config/biometrics.php
'trusted_domains' => [
    '192.168.1.0/24',
    '10.0.0.0/8',
    'door-controller.internal',
],
```

If no trusted domains/subnets are configured, all door API calls are denied by default.

## Door Events & Audit Logging

All door events are logged to the `door_events` table regardless of outcome:

| Event Type | When Logged |
|---|---|
| `unlock_granted` | Access granted, door unlocked (API success or relay/wiegand) |
| `unlock_denied` | Access denied (membership issue, biometric failure) |
| `unlock_failed` | Door unlock attempted but failed (API unreachable or error) |

Each record includes:
- gym_id, device_id, gym_member_id, attendance_id
- event_type, trigger_source, door_status
- denial_reason (if any)
- unlock_duration_seconds, unlocked_at
- ip_address, raw_response (JSON)

## Feature Flow

### Door Unlock Request

1. CheckInService calls `DoorControlService::unlock($device)`

2. If device is not door access (purpose is attendance_only):
   - Return `DoorControlResult::notApplicable()`

3. Route by door_control_type:
   - **Relay/Wiegand**: Return `skipped()` — success assumed
   - **API**: Proceed to step 4

4. **API door control**:
   a. Validate URL against trusted domains/subnets
   b. If invalid: return `failed('door_controller_url not allowed')`
   c. Make HTTP POST with configured token
   d. On success: return `unlocked()`
   e. On failure: return `failed()`, fire DoorControllerUnreachable event
   f. On exception: return `failed()`, fire event

5. **Log event**: Save to DoorEvent table with all relevant details

### Door Event Logging

Every door event is logged regardless of success or failure:

```php
DoorControlService::logEvent(
    device: $device,
    gymMember: $gymMember,    // nullable (may be null on denial)
    attendance: $attendance,  // nullable (may be null if no attendance)
    eventType: 'unlock_granted' | 'unlock_denied' | 'unlock_failed',
    result: $doorResult
);
```

## Database Writes

| Table | Operation | Condition |
|---|---|---|
| `door_events` | INSERT | On ALL door access devices (every lock/unlock attempt logged) |
| `attendances` | UPDATE door_status, door_unlocked_at | On door_access device when existing attendance found |

## API / Endpoints

No direct API — door control is triggered from within check-in webhook. The door event resource is available in Filament admin:

### DoorEventResource (Filament)

- **List**: Shows all door events with filters by gym, device, date
- **Fields**: Event type, door status, gym member, device, timestamp, denial reason
- **Useful for**: Troubleshooting access issues, security audits

## Enums & Constants

| Enum | Values | Meaning in this feature |
|---|---|---|
| `BiometricDoorControlType` | `relay` | Device built-in relay handles door |
| | `wiegand` | Device sends Wiegand signal |
| | `api` | Separate door controller with REST API |
| Door Status | `unlocked` | Door successfully opened |
| | `locked` | Access denied, door stays locked |
| | `failed` | Unlock attempted but failed |
| | `skipped` | Relay/wiegand - success assumed |
| | `not_applicable` | Device has no door control |

## Business Rules & Edge Cases

- **Door failure = attendance NOT created**: On door_access devices, if door unlock fails via API, attendance must NOT be created
- **Relay/Wiegand treated as success**: These protocols don't provide feedback, so assumed success
- **All door events logged**: Every attempt (granted, denied, failed) logged to DoorEvent
- **URL validation**: Door controller URLs must be in trusted list; if empty, all denied
- **Timeout**: 3-second timeout on API calls prevents hanging

## Compliance & Data Privacy

- **Audit trail**: Door events retained per gym data policy
- **IP logging**: Request IP captured for security auditing
- **No biometric data in door events**: Only references to member, not biometric data

## Related Features

| Feature | Relationship |
|---|---|
| [Biometric Check-in Flow](./biometric-checkin-flow.md) | Calls DoorControlService after biometric verification |
| [Biometric Provider Abstraction](./biometric-provider-abstraction.md) | Independent — door control works with any biometric provider |
