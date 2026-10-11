# REQUIREMENTS.md

## Requirements Traceability Matrix

| ID | Requirement | Source | Status |
|----|-------------|--------|--------|
| REQ-01 | Add `SendBiometricConsentNotification` class / queued job supporting Email and WhatsApp channels with signed consent URLs | SPEC Goal 5 | Complete (Phase 1) |
| REQ-02 | Add `BIOMETRIC_CONSENT` notification type and default WhatsApp template with variable mapping and text fallback | SPEC Goal 5 | Complete (Phase 1) |
| REQ-03 | Implement Dual-Gate Service method to verify tenant WhatsApp is enabled (`isWhatsAppConfigured`) AND biometric consent template is approved/available | SPEC Goal 3 | Complete (Phase 2) |
| REQ-04 | Add Header Action on `ListGymMembers` page to send consent requests to all active unconsented members of the tenant | SPEC Goal 1 | Complete (Phase 3) |
| REQ-05 | Add Bulk Action on `GymMemberResource` table to send consent requests to selected unconsented members | SPEC Goal 2 | Complete (Phase 3) |
| REQ-06 | Design action confirmation modal with Email toggle and conditional WhatsApp toggle (visible ONLY when dual-gate passes) | SPEC Goal 4 | Complete (Phase 3) |
| REQ-07 | Ensure member consent idempotency: skip members who already have active consent, missing contacts, or revoked WhatsApp permissions | SPEC Goal 5 | Complete (Phase 1) |
| REQ-08 | Write comprehensive positive test cases: dual-gate passes -> toggle visible, email/whatsapp dispatches, active unconsented members receive links | SPEC Success Criteria | Complete (Phase 4) |
| REQ-09 | Write comprehensive negative test cases: WhatsApp disabled -> toggle hidden; template missing -> toggle hidden; already consented/archived/opted-out members skipped | SPEC Success Criteria | Complete (Phase 4) |
| REQ-10 | Conditionally include biometric attendance notice and consent link in member welcome email when gym has active biometric device available | User Request | Complete (Phase 5) |
| REQ-11 | Suppress biometric attendance notice in welcome email when gym has no active biometric devices or member already has active consent | User Request | Complete (Phase 5) |

