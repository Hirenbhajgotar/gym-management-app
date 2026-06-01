---
adr: 003
title: Consent Before Enrollment
status: accepted
date: 2026-04-03
author: docs-agent
related_features:
  - ../feature/biometric-consent-system.md
---

# ADR-003: Consent Before Enrollment

## Status

Accepted

## Context

GDPR requires explicit consent before processing biometric data. We need a mechanism that:
1. Prevents biometric operations (check-in, enrollment) until consent is given
2. Captures consent with audit trail (IP, timestamp, version)
3. Allows withdrawal of consent with automatic data deletion
4. Works without requiring member login (for initial consent capture)

## Decision

Implement consent system with these components:

1. **Automatic consent email trigger**: When `GymMember` is created, `GymMemberObserver` dispatches `SendBiometricConsentEmail` job (with delay) to send consent link.

2. **Signed URL consent form**: Consent URL contains encrypted signature validating member identity without login. URL expires in 7 days.

3. **BiometricConsent model**: Stores consent record with:
   - `gym_member_id`, `gym_id`
   - `ip_address` — captured at consent time
   - `version` — consent form version
   - `consented_at` — timestamp
   - `revoked_at` — timestamp (null if active)

4. **MembershipGuard consent check**: Before any biometric operation, check for active `BiometricConsent`. If none exists, deny.

5. **BiometricDataDeletionService**: On consent revocation:
   - Set `revoked_at` on consent record
   - Delete all `BiometricTemplate` records
   - Call provider's `deleteTemplate()` for each template (GDPR requirement)

## Alternatives Considered

| Alternative | Why rejected |
|---|---|
| Consent at registration time | Not all members want biometrics — better to ask later |
| Login-required consent | Some members may not have accounts yet |
| Admin-initiated consent | Adds administrative burden |

## Consequences

### Positive

- GDPR compliant — explicit consent with audit trail
- No biometric data stored without consent
- Automatic cleanup on revocation
- Works for all members regardless of login status

### Negative / Trade-offs

- Additional complexity in check-in flow (consent check)
- Must track consent version for compliance
- Email deliverability required for consent workflow

## Related Decisions

- [ADR-001](./ADR-001-biometric-provider-abstraction.md) — deleteTemplate() required for GDPR cleanup
