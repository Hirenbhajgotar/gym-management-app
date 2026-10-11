# DECISIONS.md — Architectural Decision Log

## ADR-001: Modal Channel Selection UX
- **Context**: When sending consent requests, admins need to select which communication channels to utilize.
- **Decision**: Provide checkboxes/toggles in an action confirmation modal (`Send via Email` and `Send via WhatsApp`) with a single primary submit button. `Send via WhatsApp` is conditionally visible ONLY if WhatsApp is enabled for the tenant AND an approved biometric consent template is available.
- **Rationale**: Allows administrators the flexibility to send through Email, WhatsApp, or both simultaneously while preventing failure when WhatsApp is not configured or lacks an approved template.

## ADR-002: Targeting Active Members Without Active Consent
- **Context**: Consent requests should not create spam or confuse already compliant members.
- **Decision**: Scope consent request broadcasts strictly to active members (`notArchived()`, active membership) who currently lack active biometric consent (`hasActiveBiometricConsent() === false`).
- **Rationale**: Prevents accidental re-solicitation of already consenting members and respects GDPR/privacy consent principles.

## ADR-003: WhatsApp Delivery Strategy (Template with Text Fallback)
- **Context**: Meta WhatsApp Business API enforces templates outside the 24h conversation window, while Twilio Sandbox / direct numbers can utilize direct body messages.
- **Decision**: Look for an approved biometric consent template if registered; if absent or in dev/sandbox mode, gracefully fall back to direct body text containing the signed URL.
- **Rationale**: Guarantees maximum delivery reliability across both production WABA setups and sandbox/testing environments.

## ADR-004: Dual-Gated WhatsApp Action Availability & Comprehensive Test Matrix
- **Context**: Admins should not see or be able to select WhatsApp if either the tenant lacks active WhatsApp credentials or the tenant lacks an approved template for biometric consent.
- **Decision**: Implement a dedicated gate method (e.g., `canSendBiometricConsentViaWhatsApp(Gym $gym)`) that checks:
  1. WhatsApp is enabled in tenant settings (`whatsapp_notifications`) AND WhatsApp account is active with an active phone number.
  2. An approved `biometric_consent` WhatsApp template exists and is active for the tenant (or default template).
  Require explicit positive and negative test cases covering all permutations of this gate and member opt-in status.
- **Rationale**: Prevents failed jobs, undelivered messages, and confusing UI states where WhatsApp is selected but cannot be delivered.

