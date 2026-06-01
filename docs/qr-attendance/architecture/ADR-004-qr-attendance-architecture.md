---
adr: 004
title: QR Code Attendance Architecture
status: accepted
date: 2026-04-13
author: docs-agent
related_features:
  - ../feature/qr-code-attendance.md
---

# ADR-004: QR Code Attendance Architecture

## Status

Accepted

## Context

The gym management system needed a simple, web-based attendance mechanism that members could use without dedicated mobile apps or biometric devices. The requirement was for one QR code per gym that members could scan at the door, enter their member ID/phone, and have attendance automatically recorded. Key constraints included: duplicate prevention (one check-in per day), optional OTP verification for security, and separate QR codes for check-in vs check-out.

## Decision

We implemented a token-based QR code attendance system with the following architecture:

1. **Token-based URL Security**: Each gym has a cryptographically random 32-character token (`Str::random(32)`) stored in the database. The QR code URL contains the token, which is validated against the stored token on access.

2. **Separate Check-in/Check-out QR Codes**: Each gym can have a separate `qr_code_token` and `qr_code_checkout_token`. This allows gym owners to display QR codes at different locations (e.g., entrance for check-in, exit for check-out).

3. **Phone-based Member Identification**: Members identify themselves by entering their phone number (primary) or member ID. The system looks up the `GymMember` record by phone number within the same gym.

4. **Optional OTP Verification**: Twilio Verify integration provides an optional second factor. If configured for the gym, members receive a verification code via SMS/WhatsApp before attendance is recorded.

5. **Duplicate Prevention**: The system checks for existing attendance records with today's date before creating new ones.

6. **Service Layer Pattern**: All attendance business logic is encapsulated in `QRAttendanceService`, separating concerns from the Livewire component.

## Alternatives Considered

| Alternative | Why rejected |
|---|---|
| Member ID instead of phone | Phone is more familiar to members and easier to enter; ID varies by gym |
| Biometric verification | More complex; requires biometric device or app - not yet available |
| Login required | Adds friction; members should be able to check in quickly |
| Single QR for both check-in/out | Cannot differentiate location-based attendance; less flexible |

## Consequences

### Positive

- Simple web-based access - no app required
- QR code printed once and reused
- Optional OTP adds security without hardware
- Duplicate prevention works across devices
- Separate check-in/check-out provides data integrity

### Negative / Trade-offs

- Need to regenerate QR if token compromised (can be done in admin)
- Members need to enter phone number each time (vs biometric instant recognition)
- No real-time door unlock (unlike biometric door access feature)

## Related Decisions

- [ADR-002](../../biometric/architecture/ADR-002-door-access-attendance-integrity.md) — Door access + attendance integration when using biometric devices
- [ADR-001](../../biometric/architecture/ADR-001-biometric-provider-abstraction.md) — Biometric provider abstraction (alternative to QR attendance)
