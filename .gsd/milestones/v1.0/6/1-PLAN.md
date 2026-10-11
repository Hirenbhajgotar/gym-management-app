---
phase: 6
plan: 1
wave: 1
depends_on: []
---

# Plan 6.1: Fix PHPStan Issues in Jobs, Console Commands & Controllers

## Objective
Fix all 12 PHPStan / Larastan static analysis errors across `WorkQueuesCommand.php`, `BiometricConsentController.php`, `FanOutBiometricConsentRequestsJob.php`, and `SendBiometricConsentNotificationJob.php` adhering to strict types, Laravel 12 helper conventions, and null safety.

## Context
- `phpstan.neon` (Level max with strict rules)
- `app/Console/Commands/WorkQueuesCommand.php`
- `app/Http/Controllers/BiometricConsentController.php`
- `app/Jobs/FanOutBiometricConsentRequestsJob.php`
- `app/Jobs/SendBiometricConsentNotificationJob.php`

## Tasks

<task type="auto">
  <name>Fix typing in WorkQueuesCommand and BiometricConsentController</name>
  <files>
    app/Console/Commands/WorkQueuesCommand.php
    app/Http/Controllers/BiometricConsentController.php
  </files>
  <action>
    1. In `app/Console/Commands/WorkQueuesCommand.php`:
       - Line 52: Resolve `Cannot cast mixed to string` in `$configured` closure. Inspect `config($key, $default)`. Safely typecast or check: `is_string($val = config($key, $default)) ? $val : $default`.
       - Line 79: Resolve `Cannot cast mixed to string` in `defaultQueueName()`. Retrieve config string safely via `is_string($conn = config('queue.default', '')) ? $conn : ''` or `config()->string(...)`.
    2. In `app/Http/Controllers/BiometricConsentController.php`:
       - Lines 23-25 & 45-49: Larastan reports `Parameter #1 $gymMember of method ... guard() expects App\Models\GymMember, GymMember|Collection given` because `findOrFail` has a union return type.
       - Cast/assert `$gymMember` as an instance of `GymMember`: `assert($gymMember instanceof GymMember);` immediately after `findOrFail(...)` in both `show()` and `store()`.
  </action>
  <verify>
    docker-compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress app/Console/Commands/WorkQueuesCommand.php app/Http/Controllers/BiometricConsentController.php
  </verify>
  <done>
    PHPStan reports [OK] No errors for both WorkQueuesCommand.php and BiometricConsentController.php.
  </done>
</task>

<task type="auto">
  <name>Fix typing in FanOut and Send Notification Jobs</name>
  <files>
    app/Jobs/FanOutBiometricConsentRequestsJob.php
    app/Jobs/SendBiometricConsentNotificationJob.php
  </files>
  <action>
    1. In `app/Jobs/FanOutBiometricConsentRequestsJob.php`:
       - Line 63: Resolve `Cannot cast mixed to string` in `__construct()` by using `config()->string('queue.notifications_queue', 'notifications')` or `is_string($q = config('queue.notifications_queue', 'notifications')) ? $q : 'notifications'`.
       - Line 93: Resolve `Using nullsafe property access on non-nullable type App\Enums\UserStatus` by replacing `$actor->status?->value` with `$actor->status->value` since `$actor->status` is non-nullable.
    2. In `app/Jobs/SendBiometricConsentNotificationJob.php`:
       - Line 46: Resolve `Cannot cast mixed to string` by using `config()->string('queue.notifications_queue', 'notifications')`.
       - Line 175: Resolve `Cannot cast mixed to string` for sandbox number by using `config()->string('services.twilio.sandbox_number', '+15557626186')` or safe string check.
       - Lines 280 & 285: Resolve `Casting to string something that's already string` on `$member->phone` by passing `$member->phone` directly to `PhoneNumber::toDialableE164($member->phone)` and `WhatsAppSanitizer::maskPhoneNumber($member->phone)`.
       - Line 338: Resolve `Call to function array_filter() requires parameter #2 to be passed to avoid loose comparison semantics` by supplying explicit strict callback `static fn (string $val): bool => $val !== ''`.
       - Line 339: Resolve useless string cast on `$this->gymMember->name`.
  </action>
  <verify>
    docker-compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress app/Jobs/FanOutBiometricConsentRequestsJob.php app/Jobs/SendBiometricConsentNotificationJob.php
  </verify>
  <done>
    PHPStan reports [OK] No errors for both FanOutBiometricConsentRequestsJob.php and SendBiometricConsentNotificationJob.php.
  </done>
</task>

## Success Criteria
- [ ] `WorkQueuesCommand.php`, `BiometricConsentController.php`, `FanOutBiometricConsentRequestsJob.php`, and `SendBiometricConsentNotificationJob.php` pass PHPStan analysis with 0 errors.
- [ ] Related feature tests (`WorkQueuesCommandTest`, `BiometricConsentTest`, `BiometricConsentAuthorizationTest`, `SendBiometricConsentNotificationJobTest`) remain green.
