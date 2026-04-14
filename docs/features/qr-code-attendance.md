---
feature: QR Code Attendance
status: draft
last_updated: 2026-04-13
author: docs-agent
related_docs:
  - ../architecture/ADR-004-qr-attendance-architecture.md
  - ../database/gyms-fields.md
---

# QR Code Attendance

## Overview

The QR Code Attendance feature enables gym members to check in and check out by scanning a QR code displayed at the gym location. Members scan the QR code with their phone camera, which opens a web-based attendance form where they enter their phone number (with optional OTP verification for security). One QR code is generated per tenant (gym), and the gym owner can generate separate QR codes for check-in and check-out from the admin panel.

## Architecture

### Classes & Services Involved

| Class / File | Responsibility |
|---|---|
| `QRAttendanceService` (`app/Services/QRAttendanceService.php`) | Core business logic for check-in/check-out, member lookup, duplicate prevention |
| `QRAttendance` (`app/Livewire/Frontend/QRAttendance.php`) | Livewire component for member-facing attendance form |
| `QRCodeManager` (`app/Filament/Admin/Pages/QRCodeManager.php`) | Filament admin page for generating QR codes |
| `Gym` (`app/Models/Gym.php`) | Model with QR token generation and validation methods |

### Data Flow

```
Gym Owner (Admin)
      ↓ generate QR code
QRCodeManager → Gym.generateCheckInQrCodeToken()
      ↓ saves token to gym
gyms.qr_code_token
      ↓
Member scans QR code
      ↓ (physical QR printed at gym door)
attendance/qr-checkin/{gymId}/{token}
      ↓
QRAttendance Livewire component
      ↓ validates token
Gym.hasValidCheckInQrCodeToken()
      ↓
Member enters phone number
      ↓
QRAttendanceService.findMember() / checkIn()
      ↓ (optional OTP verification)
OtpService.sendVerification() / verifyCode()
      ↓
Attendances table (check_in_time, status, check_in_method='qr_code')
```

### Design Patterns Used

- **Service Layer**: `QRAttendanceService` encapsulates all attendance business logic
- **Token-based URL Security**: QR codes contain cryptographically random tokens embedded in URLs
- **Optional OTP Verification**: Twilio Verify integration for member identity verification before check-in

### External Dependencies

- `lara-zeus/qr` (v2.0): QR code rendering in admin panel
- `laravel/sanctum` / Twilio Verify API: OTP verification (optional, configurable)

## Feature Flow

### Trigger

Gym owner visits Admin Panel → Settings → QR Code page to generate QR codes.

### Happy Path

1. **Admin generates QR code**:
   - Gym owner visits `/admin/qr-code` in Filament admin panel
   - Clicks "Generate Check-In QR Code" or "Generate Check-Out QR Code"
   - System generates unique 32-character token via `Str::random(32)`
   - Token stored in `gyms.qr_code_token` or `gyms.qr_code_checkout_token`
   - QR code rendered using `lara-zeus/qr` facade

2. **Member scans QR code**:
   - Member visits gym, scans QR code displayed at entrance
   - URL: `/attendance/qr-checkin/{gymId}/{token}` or `/attendance/qr-checkout/{gymId}/{token}`
   - Token validated against gym's stored token

3. **Member enters phone number**:
   - Member enters phone number in form
   - System looks up `GymMember` by phone in same gym

4. **Attendance recorded** (check-in):
   - Check if already checked in today → return existing attendance
   - Check membership status (not frozen, not expired)
   - Create `Attendances` record with `check_in_method = 'qr_code'`

5. **Attendance recorded** (check-out):
   - Find today's attendance for member
   - Update with `check_out_time` and `status = AttendanceStatus::Completed`

### Failure & Edge Cases

| Scenario | Behaviour |
|---|---|
| Invalid QR token | 404 error - "Invalid QR code. Please scan a valid QR code from your gym." |
| Member not found | Error message - "Member not found with this phone number." |
| Membership frozen | Error message - "Your membership is currently frozen. It will be unfrozen on [date]." |
| Membership expired | Error message - "Your membership has expired. Please renew to check in." |
| Already checked in | Success - "You are already checked in today." (returns existing attendance) |
| No check-in for checkout | Error message - "No check-in found for today. Please check in first before checking out." |
| Already checked out | Success - "You have already checked out today." |
| OTP verification fails | Error message - "Invalid verification code. Please try again." |

### Database Writes

| Table | Operation | Condition |
|---|---|---|
| `gyms` | UPDATE | Set `qr_code_token` or `qr_code_checkout_token` when generating QR |
| `attendances` | INSERT | New check-in with `check_in_method = 'qr_code'` |
| `attendances` | UPDATE | Set `check_out_time` and `status = 'completed'` for check-out |

## API / Endpoints

> No HTTP API - this is a web-based flow accessed via browser.

### `GET /attendance/qr-checkin/{gymId}/{token}`

**Auth:** None (public, token-secured)  
**Called by:** Member scanning QR code with phone camera

**Response:** Livewire component rendered

### `GET /attendance/qr-checkout/{gymId}/{token}`

**Auth:** None (public, token-secured)  
**Called by:** Member scanning QR code with phone camera

**Response:** Livewire component rendered

## Enums & Constants

| Enum | Values | Meaning in this feature |
|---|---|---|
| `AttendanceStatus` | `Present` | Member checked in |
| | `Completed` | Member checked out |
| | `NoShow` | Member did not check in |

## Business Rules & Edge Cases

- **One check-in per day**: Duplicate check-ins prevented by checking for existing attendance record with today's date
- **Separate QR codes**: Check-in and check-out have separate tokens to allow gym owners to display QR codes at different locations
- **Token regeneration**: Gym owner can regenerate QR code (invalidates old token, generates new one)
- **OTP optional**: OTP verification is used only if Twilio Verify is configured for the gym
- **Member identification**: Members can be identified by phone number (primary) or member ID
- **Check-in method tracking**: All QR-attendance records marked with `check_in_method = 'qr_code'`

## Compliance & Data Privacy

- **Data stored**: Member phone number, attendance timestamps, check-in/check-out status
- **Retention**: Follows gym's general data retention policy for attendance records
- **Deletion flow**: Standard attendance deletion via model soft-delete
- **Consent mechanism**: N/A - no biometric data collected in this feature

## Related Features

| Feature | Relationship |
|---|---|
| [Biometric Check-in Flow](./biometric-checkin-flow.md) | Alternative check-in method using biometrics |
| [Door Access Control](./door-access-control.md) | Can be used alongside QR attendance for door unlock |
| [Attendances](./attendance-system.md) | Core attendance records updated by this feature |