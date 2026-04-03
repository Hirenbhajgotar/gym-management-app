---
feature: Biometric Consent System
status: draft
last_updated: 2026-04-03
author: docs-agent
related_docs:
  - ../architecture/ADR-003-consent-before-enrollment.md
  - ../database/biometric-consents-fields.md
  - ../database/gym-members-fields.md
---

# Biometric Consent System

## Overview

The biometric consent system ensures GDPR compliance by requiring members to explicitly consent to biometric data processing before any enrollment can occur. Consent is captured via a time-limited signed URL sent to the member's email — no login required. The system automatically triggers consent emails when new members are created and prevents biometric operations until consent is granted.

## Architecture

### Classes & Services Involved

| Class / File | Responsibility |
|---|---|
| `GymMemberObserver` (`app/Observers/GymMemberObserver.php`) | Triggers consent email on member creation |
| `SendBiometricConsentEmail` (`app/Jobs/SendBiometricConsentEmail.php`) | Queued job with delay for sending consent email |
| `BiometricConsentMail` (`app/Mail/BiometricConsentMail.php`) | Email with signed consent URL |
| `BiometricConsentController` (`app/Http/Controllers/BiometricConsentController.php`) | Handles consent form GET/POST |
| `BiometricConsentStoreRequest` (`app/Http/Requests/BiometricConsentStoreRequest.php`) | Form request validation |
| `BiometricConsent` (`app/Models/BiometricConsent.php`) | Consent record model |
| `MembershipGuard` (`app/Biometrics/Services/MembershipGuard.php`) | Checks consent before allowing check-in |
| `BiometricDataDeletionService` (`app/Biometrics/Services/BiometricDataDeletionService.php`) | Handles consent revocation |

### Data Flow

```
New GymMember created
    ↓
GymMemberObserver::created()
    ↓
SendBiometricConsentEmail job dispatched (with delay)
    ↓
BiometricConsentMail sent with signed URL
    ↓
Member clicks link (no login required - signed URL validates)
    ↓
BiometricConsentController::show() displays consent form
    ↓
Member submits form (accept/decline)
    ↓
BiometricConsentController::store() creates BiometricConsent record
    ↓
BiometricConsentGiven event fires → NotifyStaffOnConsentGiven listener
```

### Design Patterns Used

- **Observer Pattern**: GymMemberObserver reacts to member creation
- **Job Queue**: Consent email sent asynchronously with delay
- **Signed URLs**: Temporary, expirable URLs for consent without authentication

## Feature Flow

### Trigger: New Member Creation

When a new `GymMember` is created (via admin panel or API):

1. `GymMemberObserver::created()` fires
2. If member has email address:
   - Dispatch `SendBiometricConsentEmail` job
   - Apply configured delay (default: 5 minutes)
   - Use `->afterCommit()` to ensure member is saved

### Consent Email Delivery

1. `SendBiometricConsentEmail` job executes
2. Load member with gym relationship
3. Generate signed URL:
   ```php
   URL::temporarySignedRoute(
       'consent.biometric.show',
       now()->addDays(config('biometrics.consent_url_expiry_days', 7)),
       ['member' => $member->id]
   );
   ```
4. Send email via `BiometricConsentMail`
5. Email contains:
   - Gym name
   - Brief explanation of biometric processing
   - Link to consent form (expires in 7 days)

### Consent Form Display

1. Member clicks link in email
2. `BiometricConsentController::show()` validates signed URL
3. If signature invalid/expired → 403 error
4. If member already has active consent → show "already consented" message
5. Display consent form with:
   - Explanation of data processing
   - Purpose (attendance, access control)
   - Storage method (encrypted template references)
   - Right to withdraw
   - Accept/Decline buttons

### Consent Submission

1. Member submits form
2. `BiometricConsentStoreRequest` validates:
   - `consent` must be "accepted"
   - Required checkbox must be checked
3. If declined: Record declined consent, no biometric allowed
4. If accepted:
   - Create `BiometricConsent` record with `consented_at` timestamp
   - Fire `BiometricConsentGiven` event
   - `NotifyStaffOnConsentGiven` listener notifies gym staff
5. Redirect to confirmation page

### Consent Revocation

1. Member (or admin) initiates revocation
2. `BiometricDataDeletionService` orchestrates:
   - Set `revoked_at` on BiometricConsent
   - Delete all BiometricTemplate records for member
   - Call provider's `deleteTemplate()` for each template
   - Fire `BiometricConsentRevoked` event
   - `HandleConsentRevoked` listener notifies staff

## API / Endpoints

### `GET /consent/biometric?member={id}&signature={sig}`

**Auth:** None (signed URL validation)  
**Called by:** Member clicking link in email

**Response (200):** Consent form HTML

**Error responses:**

| Status | Meaning |
|---|---|
| 403 | Invalid or expired signature |

### `POST /consent/biometric`

**Auth:** None (same signed URL context)  
**Called by:** Member submitting consent form

**Request:**
```json
{
  "consent": "accepted",
  "accept_terms": "on"
}
```

**Response (302):** Redirect to confirmation or error page

## GDPR Compliance

| Requirement | Implementation |
|---|---|
| Explicit consent | Member must actively accept via form |
| No biometric without consent | MembershipGuard rejects check-in if no active consent |
| Consent records | BiometricConsent stores version, IP, timestamp |
| Right to withdraw | Revocation triggers template deletion |
| Data deletion | BiometricDataDeletionService calls provider.deleteTemplate() |
| No raw biometric storage | Only template references, never raw data |
| Consent expiry | URL expires in 7 days (configurable) |

## Enums & Constants

| Enum | Values | Meaning in this feature |
|---|---|---|
| BiometricConsent.isActive() | true/false | Active if revoked_at is null |

## Business Rules & Edge Cases

- **No biometric without consent**: MembershipGuard checks for active consent before allowing check-in
- **Time-limited URL**: Consent link expires in 7 days — member must request new link if expired
- **Idempotent**: If member already has consent, form shows "already consented" — no duplicate created
- **Decline handling**: If member declines, they cannot use biometric features but can still check in manually
- **GDPR deletion**: When consent revoked, ALL biometric data must be removed (templates + provider-side)

## Related Features

| Feature | Relationship |
|---|---|
| [Biometric Check-in Flow](./biometric-checkin-flow.md) | MembershipGuard prevents check-in if no consent |
| [Biometric Provider Abstraction](./biometric-provider-abstraction.md) | deleteTemplate() called on consent revocation |
| [Door Access Control](./door-access-control.md) | Depends on check-in flow which depends on consent |