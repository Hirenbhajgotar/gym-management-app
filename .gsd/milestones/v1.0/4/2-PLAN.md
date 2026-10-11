---
phase: 4
plan: 2
wave: 2
gap_closure: false
---

# Plan 4.2: Feature Tests for WhatsApp Dual-Gate Visibility Matrix & Delivery QA

## Objective
Write comprehensive feature test cases in `tests/Feature/Filament/BiometricConsentDualGateTest.php` validating the positive and negative matrix for WhatsApp toggle visibility on both Header and Bulk actions. Tests verify that the WhatsApp toggle is visible ONLY when WhatsApp notifications are enabled, the Twilio/WABA account is active with an active number, and an approved biometric consent template exists. Further, negative pathways (disabled setting, unapproved template, inactive number) are empirically proven to hide the toggle, satisfying REQ-08 and REQ-09.

## Context
Load these files for context:
- [.gsd/SPEC.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/SPEC.md)
- [.gsd/REQUIREMENTS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/REQUIREMENTS.md)
- [app/Services/NotificationChannelResolver.php](file:///c:/xampp/htdocs/projects/gym-app/app/Services/NotificationChannelResolver.php)
- [app/Filament/Admin/Resources/GymMemberResource.php](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Resources/GymMemberResource.php)
- [app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php)
- [tests/Unit/Services/NotificationChannelResolverDualGateTest.php](file:///c:/xampp/htdocs/projects/gym-app/tests/Unit/Services/NotificationChannelResolverDualGateTest.php)

## Tasks

<task type="auto">
  <name>Create BiometricConsentDualGateTest feature test class</name>
  <files>tests/Feature/Filament/BiometricConsentDualGateTest.php</files>
  <action>
    Create `tests/Feature/Filament/BiometricConsentDualGateTest.php` with `RefreshDatabase`.
    Implement test cases covering the complete positive and negative matrix:
    1. `test_whatsapp_toggle_is_visible_on_header_and_bulk_actions_when_dual_gate_passes`:
       - Configure Gym with setting `'whatsapp_notifications' => true`.
       - Create active Twilio WhatsApp account with active phone number for the gym.
       - Create approved active `WhatsAppTemplate` for `NotificationType::BIOMETRIC_CONSENT`.
       - Mount `ListGymMembers` in Livewire as gym owner.
       - Assert Header Action form schema has `send_whatsapp` field visible.
       - Call action with `['send_email' => false, 'send_whatsapp' => true]` and verify job is dispatched with `sendWhatsApp: true`.
    2. `test_whatsapp_toggle_is_hidden_when_tenant_whatsapp_setting_is_disabled`:
       - Configure gym with setting `'whatsapp_notifications' => false`.
       - Keep account and approved template present.
       - Assert `send_whatsapp` field is hidden on Header Action form schema.
    3. `test_whatsapp_toggle_is_hidden_when_biometric_consent_template_is_missing_or_unapproved`:
       - Enable setting `'whatsapp_notifications' => true` and active account.
       - Set template status to `WhatsAppTemplateStatusEnum::PENDING` (or omit template).
       - Assert `send_whatsapp` field is hidden on Header Action form schema.
    4. `test_whatsapp_toggle_is_hidden_when_account_has_no_active_phone_number`:
       - Template approved and setting enabled, but phone number is deactivated or missing.
       - Assert `send_whatsapp` field is hidden on Header Action form schema.
  </action>
  <verify>docker compose exec app php artisan test --env=testing tests/Feature/Filament/BiometricConsentDualGateTest.php</verify>
  <done>All dual-gate positive and negative scenarios pass on gym_app_test.</done>
</task>

<task type="auto">
  <name>Execute complete test regression and Pint formatting</name>
  <files>tests/Feature/Filament/BiometricConsentDualGateTest.php</files>
  <action>
    Run `vendor/bin/pint --dirty` to ensure PSR-12 standard across all new tests.
    Run all Phase 1-4 tests together using:
    `docker compose exec app php artisan test --env=testing tests/Unit/Jobs/SendBiometricConsentNotificationJobTest.php tests/Unit/Services/NotificationChannelResolverDualGateTest.php tests/Feature/Filament/BiometricConsentActionsTest.php tests/Feature/Filament/BiometricConsentDualGateTest.php`
  </action>
  <verify>docker compose exec app php artisan test --env=testing tests/Unit/Jobs/SendBiometricConsentNotificationJobTest.php tests/Unit/Services/NotificationChannelResolverDualGateTest.php tests/Feature/Filament/BiometricConsentActionsTest.php tests/Feature/Filament/BiometricConsentDualGateTest.php</verify>
  <done>All tests in the biometric consent feature suite pass with 100% success.</done>
</task>

## Must-Haves
- [ ] Positive test confirms WhatsApp toggle visible and selectable when dual-gate passes
- [ ] Negative test confirms WhatsApp toggle hidden when setting is disabled
- [ ] Negative test confirms WhatsApp toggle hidden when template is unapproved/missing
- [ ] Negative test confirms WhatsApp toggle hidden when phone number is inactive/missing
- [ ] Zero regression across entire test suite

## Success Criteria
- [ ] All dual-gate feature tests pass
- [ ] All previous unit and feature tests pass
- [ ] Pint code formatting clean
