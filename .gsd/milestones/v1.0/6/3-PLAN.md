---
phase: 6
plan: 3
wave: 2
depends_on:
  - 6.1
  - 6.2
---

# Plan 6.3: Fix Filament GymSettings Null-Safety and Run Full Verification

## Objective
Resolve all 5 null-safety PHPStan errors in `GymSettings.php`, run full PHPStan analysis on all staged files in the feature branch ensuring 0 errors, and execute the full test regression suite on `gym_app_test`.

## Context
- `phpstan.neon`
- `app/Filament/Admin/Pages/GymSettings.php`
- All staged PHP files from the feature branch

## Tasks

<task type="auto">
  <name>Fix null-safety in GymSettings Filament Page</name>
  <files>
    app/Filament/Admin/Pages/GymSettings.php
  </files>
  <action>
    1. In `app/Filament/Admin/Pages/GymSettings.php`:
       - The page inherits `getGym(): ?Gym` from `HasTenantAccess`. PHPStan flags lines 74, 124, 156, 370, and 386 because `$this->getGym()` can return `null` if no tenant context is established.
       - In `mount()` (line 74): Retrieve `$gym = $this->getGym()`. If `$gym === null`, abort or return early without attempting to query `GymSetting` on null ID.
       - In `getWhatsAppNotificationTab()` (line 123-125): Retrieve `$gym = $this->getGym()`. If `$gym === null`, handle gracefully (`$account = null; $whatsappConfigured = false;`).
       - In `afterStateUpdated` (line 156): Retrieve `$gym = $this->getGym()`. Guard `$gym?->id` before executing query.
       - In `save()` (line 367-370): Check `if ($gym === null) { return; }` before iterating and saving settings.
       - In `downloadInvoice()` (line 386): Ensure `$gym = $this->getGym(); if ($gym === null) { abort(404); }` before scoping payment.
  </action>
  <verify>
    docker-compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress app/Filament/Admin/Pages/GymSettings.php
  </verify>
  <done>
    PHPStan reports [OK] No errors for GymSettings.php.
  </done>
</task>

<task type="auto">
  <name>Run full PHPStan analysis and test regression suite</name>
  <files>
    app/Console/Commands/WorkQueuesCommand.php
    app/Filament/Admin/Pages/GymSettings.php
    app/Http/Controllers/BiometricConsentController.php
    app/Jobs/FanOutBiometricConsentRequestsJob.php
    app/Jobs/SendBiometricConsentNotificationJob.php
    app/Services/Biometric/BiometricConsentUrlFactory.php
    app/Services/Meta/MetaMessagingService.php
    app/Services/Twilio/TwilioMessagingService.php
    app/Services/WhatsAppVariableResolver.php
  </files>
  <action>
    1. Run PHPStan on all staged PHP files in `app/` using `--memory-limit=2G`. Verify that 0 errors are found across all files.
    2. Run the full Biometric Consent feature test suite against `gym_app_test` to confirm all 90 tests pass with zero regressions.
    3. Run Pint code formatting (`vendor/bin/pint --dirty`) to ensure PSR-12 compliance.
  </action>
  <verify>
    docker-compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress app/Console/Commands/WorkQueuesCommand.php app/Enums/NotificationType.php app/Filament/Admin/Actions/EnrollBiometricAction.php app/Filament/Admin/Actions/RequestBiometricConsentAction.php app/Filament/Admin/Pages/GymSettings.php app/Filament/Admin/Resources/GymMemberResource.php app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php app/Helpers/PhoneNumber.php app/Http/Controllers/BiometricConsentController.php app/Jobs/FanOutBiometricConsentRequestsJob.php app/Jobs/SendBiometricConsentNotificationJob.php app/Mail/BiometricConsentMail.php app/Models/Gym.php app/Models/GymMember.php app/Notifications/Concerns/InteractsWithBiometricConsent.php app/Notifications/Member/WelcomeNotification.php app/Notifications/WhatsAppChannel.php app/Observers/GymMemberObserver.php app/Services/Biometric/BiometricConsentDispatchReport.php app/Services/Biometric/BiometricConsentEligibility.php app/Services/Biometric/BiometricConsentTemplateResolver.php app/Services/Biometric/BiometricConsentUrlFactory.php app/Services/GymSettingService.php app/Services/Meta/MetaMessagingService.php app/Services/NotificationChannelResolver.php app/Services/Twilio/TwilioMessagingService.php app/Services/WhatsApp/TemplateLookup.php app/Services/WhatsApp/WhatsAppSendCharge.php app/Services/WhatsAppVariableResolver.php
  </verify>
  <done>
    [OK] No errors reported by PHPStan across all staged files. Full test suite and Pint formatting pass cleanly.
  </done>
</task>

## Success Criteria
- [ ] `GymSettings.php` has 0 PHPStan errors.
- [ ] PHPStan reports 0 errors across all 29 staged files in `app/`.
- [ ] All 90 tests in the regression suite pass on `gym_app_test`.
- [ ] `vendor/bin/pint --dirty` reports clean formatting.
