---
phase: 2
plan: 1
wave: 1
---

# Plan 2.1: Dual-Gate WhatsApp & Template Availability Service

## Objective
Implement and test the dedicated dual-gate service methods `hasApprovedTemplate(Gym $gym, NotificationType|string $type)` and `canSendBiometricConsentViaWhatsApp(Gym $gym)` on `App\Services\NotificationChannelResolver`. This ensures that WhatsApp actions and options are presented to gym owners and managers only when:
1. WhatsApp notifications are enabled in tenant settings (`whatsapp_notifications`) AND the gym has an active WhatsApp account with an active phone number (`isWhatsAppConfigured`).
2. An approved, active WhatsApp template exists for biometric consent notifications (`biometric_consent_notifications`), either gym-specific or system default.

## Context
- [.gsd/SPEC.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/SPEC.md)
- [.gsd/REQUIREMENTS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/REQUIREMENTS.md)
- [.gsd/DECISIONS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/DECISIONS.md)
- [app/Services/NotificationChannelResolver.php](file:///c:/xampp/htdocs/projects/gym-app/app/Services/NotificationChannelResolver.php)
- [app/Enums/NotificationType.php](file:///c:/xampp/htdocs/projects/gym-app/app/Enums/NotificationType.php)
- [app/Models/WhatsAppTemplate.php](file:///c:/xampp/htdocs/projects/gym-app/app/Models/WhatsAppTemplate.php)
- [app/Models/WhatsAppAccount.php](file:///c:/xampp/htdocs/projects/gym-app/app/Models/WhatsAppAccount.php)

## Tasks

<task type="auto">
  <name>Implement Dual-Gate methods on NotificationChannelResolver</name>
  <files>app/Services/NotificationChannelResolver.php</files>
  <action>
    - Open `app/Services/NotificationChannelResolver.php`.
    - Add `hasApprovedTemplate(Gym $gym, NotificationType|string $type): bool`:
      - Query `WhatsAppTemplate` without global scopes for `name = $typeName`, `status = APPROVED`, `is_active = true`, matching either `gym_id = $gym->id` or `gym_id IS NULL`.
      - Return `bool` whether at least one matching template exists.
    - Add `canSendBiometricConsentViaWhatsApp(Gym $gym): bool`:
      - Retrieve tenant settings via `self::getSetting($gym->id)`.
      - Gate 1a: Check if `whatsapp_notifications` is enabled in tenant settings (`(bool) ($settings->get('whatsapp_notifications')?->value ?? false)`). Return `false` if not enabled.
      - Gate 1b: Check `self::isWhatsAppConfigured($gym)` (active account + active phone number). Return `false` if not configured.
      - Gate 2: Check `self::hasApprovedTemplate($gym, NotificationType::BIOMETRIC_CONSENT)`. Return `false` if no approved active template exists.
      - Return `true` only when all gates pass.
    - Maintain strict typing `declare(strict_types=1);` and PHPDoc type annotations.
  </action>
  <verify>php -r "require 'vendor/autoload.php'; echo method_exists(\App\Services\NotificationChannelResolver::class, 'canSendBiometricConsentViaWhatsApp') ? 'OK' : 'FAIL';"</verify>
  <done>NotificationChannelResolver exposes hasApprovedTemplate and canSendBiometricConsentViaWhatsApp methods.</done>
</task>

<task type="auto">
  <name>Write unit tests for Dual-Gate validation</name>
  <files>tests/Unit/Services/NotificationChannelResolverDualGateTest.php</files>
  <action>
    - Create `tests/Unit/Services/NotificationChannelResolverDualGateTest.php` extending `Tests\TestCase` with `RefreshDatabase`.
    - Implement comprehensive test cases covering positive and negative pathways:
      1. `test_returns_false_when_whatsapp_notifications_setting_disabled_or_missing`: Setting missing or false -> returns false.
      2. `test_returns_false_when_account_missing_or_inactive`: Setting true, but no account or inactive account -> returns false.
      3. `test_returns_false_when_account_has_no_active_phone_number`: Setting true, account active, but phone missing or inactive -> returns false.
      4. `test_returns_false_when_no_template_exists`: Setting true, account active with active phone, but no template exists -> returns false.
      5. `test_returns_false_when_template_unapproved_or_inactive`: Template exists but status is pending/draft/rejected, or `is_active = false` -> returns false.
      6. `test_returns_true_when_gym_has_active_gym_specific_approved_template`: All gates pass with gym-specific template -> returns true.
      7. `test_returns_true_when_gym_uses_active_system_default_approved_template`: All gates pass with global/system template (`gym_id = null`) -> returns true.
    - Run the tests against `gym_app_test`.
  </action>
  <verify>docker compose exec app php artisan test --env=testing tests/Unit/Services/NotificationChannelResolverDualGateTest.php</verify>
  <done>All 7 unit tests pass verifying the complete dual-gate decision matrix.</done>
</task>

## Success Criteria
- [ ] NotificationChannelResolver implements canSendBiometricConsentViaWhatsApp and hasApprovedTemplate
- [ ] WhatsApp setting gate, account/phone active gate, and approved template gate are all strictly enforced
- [ ] Full positive and negative test suite passes against gym_app_test
