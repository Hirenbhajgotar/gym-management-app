# SPEC.md — Biometric Consent Notification (Email & WhatsApp)

> **Status**: `FINALIZED`

## Vision
Empower gym owners and administrators to efficiently distribute biometric consent requests to active members who have not yet consented, via Email and WhatsApp (when configured). This provides seamless multi-channel communication for compliance with data protection regulations prior to biometric enrollment.

## Goals
1. **Header Action for All Eligible Members**: Add a header action on the `ListGymMembers` page to send biometric consent notifications to all active members of the tenant who do not currently have active biometric consent.
2. **Bulk Action for Selected Members**: Add a table bulk action in `GymMemberResource` allowing staff to select specific members and trigger biometric consent notifications.
3. **Dual-Gated WhatsApp Channel Availability**: Dynamically verify that:
   - WhatsApp is enabled and configured for the tenant (`whatsapp_notifications` setting is true and WhatsApp account is active with a verified phone number).
   - An approved WhatsApp template for biometric consent (`biometric_consent`) is available for the tenant/system.
   Only when BOTH conditions are met will the WhatsApp toggle/checkbox appear in the action modal.
4. **Channel Selection Modal**: Provide an intuitive confirmation modal with channel checkboxes/toggles for Email and WhatsApp (when dual-gate passes), displaying eligible recipient counts and executing via a unified "Send" button.
5. **Robust Delivery Pipeline**:
   - Deliver email via temporary signed route (`biometric.consent.show`) and `BiometricConsentMail`.
   - Deliver WhatsApp using the approved template or fallback message with the signed consent link, honoring member phone presence and WhatsApp opt-in gates (`canReceiveWhatsappNotifications()`).
   - Process bulk distributions asynchronously via background queue jobs to ensure UI responsiveness.

## Non-Goals (Out of Scope)
- Modifying the underlying biometric consent review/sign page logic (`biometric.consent.show`).
- Re-requesting consent from members who already have active biometric consent (`hasActiveBiometricConsent() === true`).
- Sending WhatsApp notifications to archived members or members who have explicitly opted out of WhatsApp messages.
- Hardware-side terminal provisioning changes (handled independently by ADMS once consent is registered).

## Users
- **Gym Owners & Managers**: Sending batch consent links to newly onboarded or unconsented active members via Filament Admin Panel.
- **Gym Members**: Receiving secure signed links on Email or WhatsApp to review and grant biometric consent on their own devices.

## Constraints
- Multi-tenancy: All queries and tenant settings MUST strictly scope to current tenant gym ID (`getGymId()`).
- Database Safety: Adhere strictly to AGENTS.md database safety guidelines (main DB `gym_app`, test DB `gym_app_test`).
- WhatsApp API rules: Comply with Meta/Twilio WhatsApp templates and credit balance validations.
- Asynchronous queues: Never send large batches synchronously in HTTP request thread; dispatch queued jobs.

## Success Criteria
- [ ] Header action on `ListGymMembers` allows sending consent request to all unconsented active members.
- [ ] Table bulk action on `GymMemberResource` allows sending consent request to selected unconsented active members.
- [ ] Action modal conditionally displays WhatsApp toggle ONLY IF both tenant WhatsApp is enabled AND an approved biometric consent template is available.
- [ ] Members with existing active consent are filtered out of notifications.
- [ ] Queued jobs successfully deliver email and WhatsApp with signed consent links.
- [ ] Comprehensive positive and negative test suite passes:
  - Positive: WhatsApp enabled + template present -> toggle visible, send succeeds for email & whatsapp.
  - Negative: WhatsApp disabled -> toggle hidden.
  - Negative: WhatsApp enabled but template missing/unapproved -> toggle hidden.
  - Negative: Member already consented / archived / opted out -> skipped.

