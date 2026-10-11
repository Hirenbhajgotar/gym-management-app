---
phase: 1
plan: 2
wave: 2
depends_on: [1]
---

# Plan 1.2: Multi-Channel Biometric Consent Delivery Job & Pipeline

## Objective
Implement the asynchronous queued delivery job `SendBiometricConsentNotificationJob` supporting selective multi-channel dispatch (Email, WhatsApp, or both). The job will generate a 7-day temporary signed URL (`biometric.consent.show`), enforce strict consent idempotency, respect WhatsApp opt-in and phone presence gates, and provide seamless template or text fallback delivery.

## Context
- [.gsd/SPEC.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/SPEC.md)
- [.gsd/REQUIREMENTS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/REQUIREMENTS.md)
- [.gsd/DECISIONS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/DECISIONS.md)
- [app/Jobs/SendBiometricConsentEmail.php](file:///c:/xampp/htdocs/projects/gym-app/app/Jobs/SendBiometricConsentEmail.php)
- [app/Mail/BiometricConsentMail.php](file:///c:/xampp/htdocs/projects/gym-app/app/Mail/BiometricConsentMail.php)
- [app/Services/TwilioWhatsAppService.php](file:///c:/xampp/htdocs/projects/gym-app/app/Services/TwilioWhatsAppService.php)
- [app/Services/NotificationChannelResolver.php](file:///c:/xampp/htdocs/projects/gym-app/app/Services/NotificationChannelResolver.php)

## Tasks

<task type="auto">
  <name>Create SendBiometricConsentNotificationJob</name>
  <files>app/Jobs/SendBiometricConsentNotificationJob.php</files>
  <action>
    - Create `App\Jobs\SendBiometricConsentNotificationJob` implementing `ShouldQueue` with queue traits.
    - Constructor parameters:
      - `public readonly GymMember $gymMember`
      - `public readonly bool $sendEmail = true`
      - `public readonly bool $sendWhatsApp = false`
    - In `handle()`:
      1. **Idempotency Gate**: If `$this->gymMember->hasActiveBiometricConsent()`, log and exit immediately.
      2. **Signed URL**: Generate a 7-day temporary signed route `biometric.consent.show` with parameters `gym_member_id` and `gym_id`.
      3. **Email Delivery**: If `$this->sendEmail` and `filled($this->gymMember->email)`, send `BiometricConsentMail`.
      4. **WhatsApp Delivery**: If `$this->sendWhatsApp` and `$this->gymMember->canReceiveWhatsappNotifications()` and `filled($this->gymMember->phone)`:
         - Resolve formatted phone number via `ParserHelper::formatPhoneNumberWithCountryCode($this->gymMember->phone)`.
         - Check for approved `biometric_consent_notifications` template for the gym.
         - If template found: resolve variables with `WhatsAppVariableResolver` and send template message.
         - If template not found (or in sandbox mode): format body message with signed consent link and send text message.
    - Ensure strict typing `declare(strict_types=1);` and comprehensive error handling.
  </action>
  <verify>php -r "require 'vendor/autoload.php'; echo class_exists(\App\Jobs\SendBiometricConsentNotificationJob::class) ? 'OK' : 'MISSING';"</verify>
  <done>SendBiometricConsentNotificationJob is created and implements multi-channel consent delivery logic.</done>
</task>

<task type="auto">
  <name>Write unit tests for SendBiometricConsentNotificationJob</name>
  <files>tests/Unit/Jobs/SendBiometricConsentNotificationJobTest.php</files>
  <action>
    - Create `tests/Unit/Jobs/SendBiometricConsentNotificationJobTest.php` extending `Tests\TestCase`.
    - Test cases:
      1. `test_skips_when_member_already_has_active_consent`: Verify Mail and WhatsApp are not dispatched if member has active consent.
      2. `test_dispatches_email_when_send_email_true_and_email_present`: Verify `Mail::fake()` receives `BiometricConsentMail` with valid temporary signed link.
      3. `test_skips_email_when_send_email_false_or_email_blank`: Verify Mail is skipped.
      4. `test_skips_whatsapp_when_member_opted_out_or_missing_phone`: Verify WhatsApp is skipped if `canReceiveWhatsappNotifications()` returns false or phone is null.
    - Run the unit test against `gym_app_test`.
  </action>
  <verify>docker-compose exec -T app php artisan test --filter=SendBiometricConsentNotificationJobTest</verify>
  <done>All unit tests pass verifying job behavior, idempotency, and channel gates.</done>
</task>

## Success Criteria
- [ ] SendBiometricConsentNotificationJob handles both Email and WhatsApp channels
- [ ] Active consent idempotency prevents duplicate requests
- [ ] Opt-out and missing contact gates protect delivery
- [ ] Unit test suite passes on gym_app_test
