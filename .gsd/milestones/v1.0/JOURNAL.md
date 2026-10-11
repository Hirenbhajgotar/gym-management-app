# JOURNAL.md — Project Activity Journal

## 2026-10-07
- Project initialized using `/new-project`.
- Captured user requirements:
  - Header action on members list to send biometric consent notification to all active unconsented members.
  - Bulk action on members list to send to selected members.
  - Action modal with Email and WhatsApp toggles and unified send button.
  - Conditional display of WhatsApp option based on tenant WhatsApp configuration status.
  - Delivery via approved WhatsApp template with direct fallback containing signed consent link.
- Created `SPEC.md`, `REQUIREMENTS.md`, `ROADMAP.md`, `STATE.md`, and `DECISIONS.md`.
- Executed `/insert-phase`:
  - Inserted dedicated Phase 2 for Dual-Gate availability check: WhatsApp enabled for tenant AND biometric consent template is available.
  - Renumbered UI & Action Modals to Phase 3.
  - Expanded Phase 4 to encompass a complete matrix of positive and negative test cases.
  - Logged architectural decision in `DECISIONS.md` under ADR-004.
- Executed Phase 1: Notification & Delivery Engine:
  - Plan 1.1: Added `NotificationType::BIOMETRIC_CONSENT`, registered default template in `WhatsAppDefaultTemplatesSeeder`, and wired up `biometric.consent_url` in `WhatsAppVariableResolver`.
  - Plan 1.2: Implemented `SendBiometricConsentNotificationJob` supporting multi-channel delivery, 7-day signed URLs, consent idempotency, and opt-in gates. Updated `SendBiometricConsentEmail` to delegate to it.
  - Verified tests via `tests/Unit/Jobs/SendBiometricConsentNotificationJobTest.php` passing 5/5 assertions against `gym_app_test`.
- Executed Phase 2: Dual-Gate WhatsApp & Template Availability Service:
  - Plan 2.1: Added `hasApprovedTemplate()` and `canSendBiometricConsentViaWhatsApp()` to `App\Services\NotificationChannelResolver`.
  - Created unit test suite `tests/Unit/Services/NotificationChannelResolverDualGateTest.php` with 7 test cases covering tenant settings, account/phone status, and template availability.
  - Formatted files with `vendor/bin/pint --dirty` and verified all 7 unit tests pass with 16 assertions.
