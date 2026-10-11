---
phase: 5
plan: 2
wave: 2
gap_closure: false
---

# Plan 5.2: Unit & Feature Test Suite for Welcome Email Biometric Notice

## Objective
Write a comprehensive test suite in `tests/Feature/Notifications/WelcomeEmailBiometricNoticeTest.php` covering the positive and negative pathways for the biometric attendance notice and consent submission link in the member welcome email (`WelcomeNotification` and `WelcomeMemberMail`).

## Context
Load these files for context:
- [.gsd/SPEC.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/SPEC.md)
- [.gsd/REQUIREMENTS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/REQUIREMENTS.md)
- [app/Notifications/Member/WelcomeNotification.php](file:///c:/xampp/htdocs/projects/gym-app/app/Notifications/Member/WelcomeNotification.php)
- [app/Mail/WelcomeMemberMail.php](file:///c:/xampp/htdocs/projects/gym-app/app/Mail/WelcomeMemberMail.php)
- [app/Models/BiometricDevice.php](file:///c:/xampp/htdocs/projects/gym-app/app/Models/BiometricDevice.php)
- [tests/Feature/BirthdayNotificationTest.php](file:///c:/xampp/htdocs/projects/gym-app/tests/Feature/BirthdayNotificationTest.php)

## Tasks

<task type="auto">
  <name>Create WelcomeEmailBiometricNoticeTest test class</name>
  <files>tests/Feature/Notifications/WelcomeEmailBiometricNoticeTest.php</files>
  <action>
    Create `tests/Feature/Notifications/WelcomeEmailBiometricNoticeTest.php` extending `Tests\TestCase` with `RefreshDatabase`.
    Implement test cases:
    1. `test_welcome_notification_includes_biometric_notice_and_link_when_gym_has_active_device`:
       - Create Gym and active BiometricDevice (`status = Active`, `is_enabled = true`).
       - Create GymMember without biometric consent.
       - Instantiate `WelcomeNotification($member)`.
       - Render `$mail = $notification->toMail($member)->render()`.
       - Assert HTML contains 'Biometric Attendance' or 'biometric devices for check-in'.
       - Assert HTML contains signed route URL for 'biometric.consent.show'.
    2. `test_welcome_notification_omits_biometric_notice_when_gym_has_no_biometric_devices`:
       - Create Gym with 0 devices and GymMember.
       - Render `$mail = $notification->toMail($member)->render()`.
       - Assert HTML does NOT contain 'Biometric Attendance' or consent URL.
    3. `test_welcome_notification_omits_biometric_notice_when_device_is_inactive_or_disabled`:
       - Create BiometricDevice with `status = Inactive` or `is_enabled = false`.
       - Render `$mail = $notification->toMail($member)->render()`.
       - Assert HTML does NOT contain biometric notice or consent URL.
    4. `test_welcome_notification_omits_biometric_notice_when_member_already_has_active_consent`:
       - Create active device for gym, but create active `BiometricConsent` for member (`revoked_at = null`).
       - Render `$mail = $notification->toMail($member)->render()`.
       - Assert HTML does NOT contain biometric notice.
    5. `test_welcome_member_mailable_conditionally_renders_biometric_section`:
       - Positive: Gym has active device -> `(new WelcomeMemberMail($member))->render()` contains consent URL.
       - Negative: Gym has no device -> `(new WelcomeMemberMail($memberNoDevice))->render()` omits consent section.
  </action>
  <verify>docker compose exec app php artisan test --env=testing tests/Feature/Notifications/WelcomeEmailBiometricNoticeTest.php</verify>
  <done>All positive and negative test cases for welcome email biometric notice pass against gym_app_test.</done>
</task>

<task type="auto">
  <name>Run Pint formatter and verify complete regression test suite</name>
  <files>tests/Feature/Notifications/WelcomeEmailBiometricNoticeTest.php</files>
  <action>
    - Run `vendor/bin/pint --dirty` to enforce PSR-12 formatting.
    - Run the entire biometric test suite on `gym_app_test`:
      `docker compose exec app php artisan test --env=testing tests/Unit/Jobs/SendBiometricConsentNotificationJobTest.php tests/Unit/Services/NotificationChannelResolverDualGateTest.php tests/Feature/Filament/BiometricConsentActionsTest.php tests/Feature/Filament/BiometricConsentDualGateTest.php tests/Feature/Notifications/WelcomeEmailBiometricNoticeTest.php`
  </action>
  <verify>docker compose exec app php artisan test --env=testing tests/Feature/Notifications/WelcomeEmailBiometricNoticeTest.php</verify>
  <done>Pint clean and full test suite passes with 0 regressions.</done>
</task>

## Must-Haves
- [ ] Positive test confirms biometric notice & consent URL present when active device exists
- [ ] Negative test confirms notice omitted when no devices exist
- [ ] Negative test confirms notice omitted when device is inactive/disabled
- [ ] Negative test confirms notice omitted when member already consented
- [ ] Both WelcomeNotification and WelcomeMemberMail tested
- [ ] Strictly targets gym_app_test with --env=testing

## Success Criteria
- [ ] All tests in WelcomeEmailBiometricNoticeTest pass
- [ ] Pint code formatting clean
