# TODO.md — Project Todo List

## Phase 1: Notification & Delivery Engine
- [ ] Add `NotificationType::BIOMETRIC_CONSENT` enum case
- [ ] Create default WhatsApp biometric consent template definition & variable resolvers
- [ ] Create/Extend `SendBiometricConsentNotification` / `SendBiometricConsentEmail` supporting Email and WhatsApp with signed URL (`biometric.consent.show`)
- [ ] Ensure idempotent skip for already consented members and respect WhatsApp opt-in gates

## Phase 2: Dual-Gate WhatsApp & Template Availability Service
- [ ] Implement `BiometricConsentChannelService::canSendViaWhatsApp(Gym $gym)` (or helper on NotificationChannelResolver)
- [ ] Verify both tenant WhatsApp setup (`isWhatsAppConfigured`) and active/approved `biometric_consent` template presence

## Phase 3: Filament Action UI & Dynamic Modal Schema
- [ ] Add Header Action in `ListGymMembers` page with dynamic count of unconsented active members
- [ ] Add Bulk Action in `GymMemberResource` for selected records
- [ ] Implement confirmation modal schema with Email toggle and conditional WhatsApp toggle guarded by Dual-Gate Service
- [ ] Wire actions to dispatch background batch jobs and render informative Filament notifications

## Phase 4: Comprehensive Positive & Negative Test Suite & QA
- [ ] Write positive tests: dual-gate passes -> toggle visible, email & whatsapp dispatched, signed links generated
- [ ] Write negative tests: WhatsApp disabled -> toggle hidden
- [ ] Write negative tests: WhatsApp enabled but template missing/unapproved -> toggle hidden
- [ ] Write negative tests: already consented / archived / opted-out members skipped
- [ ] Run `vendor/bin/pint --dirty`
- [ ] Verify test suite against `gym_app_test` safely
