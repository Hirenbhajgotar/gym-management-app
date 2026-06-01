---
feature: Biometric Check-in Flow
status: draft
last_updated: 2026-04-03
author: docs-agent
related_docs:
  - ../architecture/ADR-001-biometric-provider-abstraction.md
  - ../architecture/ADR-002-door-access-attendance-integrity.md
  - ../database/attendances-fields.md
  - ../database/biometric-devices-fields.md
---

# Biometric Check-in Flow

## Overview

The biometric check-in flow handles member authentication via biometric devices at gym entrances. It integrates with the device purpose system to either record attendance only, unlock doors with attendance, or unlock doors without attendance. The flow uses the `GymMember` model (not `User`) and enforces business rules based on device configuration.

## Architecture

### Classes & Services Involved

| Class / File | Responsibility |
|---|---|
| `CheckInService` (`app/Biometrics/Services/CheckInService.php`) | Main orchestrator for the entire check-in flow |
| `DoorControlService` (`app/Biometrics/Services/DoorControlService.php`) | Handles door unlock operations |
| `BiometricProviderRegistry` (`app/Biometrics/BiometricProviderRegistry.php`) | Resolves biometric provider instances |
| `AntiSpoofGuard` (`app/Biometrics/Services/AntiSpoofGuard.php`) | Validates liveness scores |
| `MembershipGuard` (`app/Biometrics/Services/MembershipGuard.php`) | Validates membership status |
| `FraudDetector` (`app/Biometrics/Services/FraudDetector.php`) | Detects duplicate/suspicious check-ins |
| `BiometricWebhookController` (`app/Http/Controllers/Api/BiometricWebhookController.php`) | Receives device POSTs |
| `GymMember` (`app/Models/GymMember.php`) | Member model with access methods |
| `BiometricDevice` (`app/Models/BiometricDevice.php`) | Device with purpose helpers |
| `Attendances` (`app/Models/Attendances.php`) | Attendance record model |

### Data Flow

```
Physical Device (biometric scan)
    ↓
POST /api/v1/biometric/webhook (with device token + biometric sample)
    ↓
BiometricWebhookController → CheckInService.processCheckIn(sample, device)
    ↓
    ├→ AntiSpoofGuard (liveness check)
    ├→ BiometricProvider.verify() (identity verification)
    ├→ MembershipGuard.canCheckIn() (membership validation)
    ├→ FraudDetector (duplicate detection)
    │
    ├─→ [Device Purpose: attendance_only]
    │   └→ Create attendance record → MemberCheckedIn event
    │
    ├─→ [Device Purpose: door_access]
    │   ├→ DoorControlService.unlock() → If success → Create attendance → Update door status on existing if present
    │   └→ MemberCheckedIn event
    │
    └─→ [Device Purpose: door_access_no_attendance]
        └→ DoorControlService.unlock() → No attendance created
```

### Design Patterns Used

- **Service Layer**: CheckInService orchestrates multiple guards and services
- **Strategy**: Device purpose determines flow behavior
- **Event-Driven**: MemberCheckedIn/MemberCheckedOut events broadcast to WebSocket

## Feature Flow

### Trigger

Physical biometric device POSTs to `POST /api/v1/biometric/webhook` with:
- Device authentication token
- Biometric sample (base64 image or template)
- Device ID

### Happy Path: Attendance-Only Device

1. Device sends biometric sample to webhook
2. CheckInService retrieves provider from device config
3. AntiSpoofGuard validates liveness score (threshold: 0.75)
4. Provider verifies sample against all active templates for gym
5. If matched with confidence >= 0.80, retrieve GymMember
6. MembershipGuard validates: membership active, payment complete, not frozen
7. FraudDetector checks for duplicate today
8. Create Attendances record with `status = AttendanceStatus::Present`
9. Fire MemberCheckedIn event (broadcasts to WebSocket)
10. Return success response to device

### Happy Path: Door Access Device

1-6. Same as attendance-only (liveness, verification, membership check)

7. FraudDetector checks for duplicate today

8. **Check for existing attendance today** (prevents duplicates for two-device gyms):
   - Query for existing attendance for this member today with status Present
   - If exists, skip creating new attendance

9. **Attempt door unlock**:
   - DoorControlService.unlock() called
   - If door_control_type = relay/wiegand → return skipped (success assumed)
   - If door_control_type = api → call door controller API (3s timeout)
   - If API fails → DO NOT create attendance, return error

10. **If door succeeded and should create attendance**:
    - Create attendance record (or update existing)
    - Set door_status = 'unlocked', door_unlocked_at = timestamp

11. Log door event to DoorEvent table

12. Fire MemberCheckedIn event

13. Return success with door action

### Happy Path: Door Access No Attendance

1-7. Same verification steps

8. Attempt door unlock only (skip attendance creation entirely)

9. Return success with door action (no attendance in response)

### Failure & Edge Cases

| Scenario | Behaviour |
|---|---|
| Liveness score < 0.75 | Deny: 'liveness_failed' |
| No template enrolled for gym | Deny: 'no_template_enrolled' |
| Verification confidence < 0.80 | Deny: 'verification_failed' |
| Member not found | Deny: 'member_not_found' |
| Membership expired | Deny: 'membership_expired' |
| Payment not completed | Deny: 'payment_pending' |
| Member is frozen | Deny: 'membership_frozen' |
| Duplicate check-in today | Allow but mark suspicious |
| Door API unreachable | Deny: 'door_failed' - no attendance created |
| Door API returns error | Deny: 'door_api_error' - no attendance created |

## Database Writes

| Table | Operation | Condition |
|---|---|---|
| `attendances` | INSERT | On successful check-in (attendance_only or door_access) |
| `attendances` | UPDATE | On door_access device when existing attendance found (update door_status) |
| `door_events` | INSERT | On door_access and door_access_no_attendance devices (all events: unlock_granted, unlock_denied, unlock_failed) |

## API / Endpoints

### `POST /api/v1/biometric/webhook`

**Auth:** Device token (`Authorization: Bearer <device_token>`)  
**Called by:** Physical biometric device

**Request:**
```json
{
  "biometric_type": "face",
  "sample": "base64_encoded_image_or_template",
  "device_id": "uuid-of-device"
}
```

**Response (200 - Attendance Only):**
```json
{
  "result": "granted",
  "action": "record_attendance",
  "member": {
    "id": 123,
    "name": "John Doe",
    "photo_url": "https://..."
  },
  "attendance": {
    "id": 456,
    "check_in_time": "2026-04-03T10:30:00Z"
  }
}
```

**Response (200 - Door Access):**
```json
{
  "result": "granted",
  "action": "unlock",
  "door": {
    "action": "unlock",
    "duration_seconds": 5,
    "zone": "main_entrance"
  },
  "member": { ... },
  "attendance": { ... }
}
```

**Response (200 - Door Access No Attendance):**
```json
{
  "result": "granted",
  "action": "unlock",
  "door": {
    "action": "unlock",
    "duration_seconds": 5
  },
  "member": { ... }
}
```

**Error responses:**

| Status | Meaning | Response |
|---|---|---|
| 401 | Invalid device token | `{"error": "unauthorized"}` |
| 422 | Validation failed | `{"error": "validation_failed", "details": {...}}` |
| 200 | Denied | `{"result": "denied", "denial_reason": "membership_expired"}` |

## Enums & Constants

| Enum | Values | Meaning in this feature |
|---|---|---|
| `BiometricDevicePurpose` | `attendance_only` | Record attendance only, no door involved |
| | `door_access` | Unlock door FIRST, create attendance ONLY if door succeeds |
| | `door_access_no_attendance` | Unlock door only, never create attendance |
| `AttendanceStatus` | `Present` | Member checked in |
| | `Completed` | Member checked out |
| | `Absent` | Denied check-in (recorded for audit) |
| `BiometricDoorControlType` | `relay` | Device handles door locally |
| | `wiegand` | Device sends signal to controller |
| | `api` | FluxFitness calls door controller |

## Business Rules & Edge Cases

- **Two-device setup**: If gym has separate attendance_only and door_access devices, the door_access device checks `isCurrentlyCheckedIn()` before creating attendance to prevent duplicates
- **BiometricDevicePurpose is single source of truth**: Drives form visibility in Filament AND business logic in services
- **Match score threshold**: Must validate `confidence >= 0.80` before any action
- **All door events logged**: Regardless of success/failure, every door attempt logged to DoorEvent table
- **Door failure = no attendance**: If door unlock fails on door_access device, attendance must NOT be created

## Compliance & Data Privacy

- **Raw biometric data**: Never stored in database — only template references
- **Consent required**: No enrollment allowed without BiometricConsent record
- **Retention**: Attendance records kept per gym data policy; biometric templates on provider side
- **Deletion flow**: When member deleted or consent revoked, BiometricDataDeletionService calls `deleteTemplate()` on provider

## Related Features

| Feature | Relationship |
|---|---|
| [Biometric Provider Abstraction](./biometric-provider-abstraction.md) | Provides verify() and liveness checks |
| [Door Access Control](./door-access-control.md) | Called by CheckInService for door operations |
| [Biometric Consent System](./biometric-consent-system.md) | Must have consent before check-in allowed |
