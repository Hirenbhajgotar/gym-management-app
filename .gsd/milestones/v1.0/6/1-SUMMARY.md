# Plan 6.1 Summary: Fix PHPStan Issues in Jobs, Console Commands & Controllers

## Overview
Resolved all 12 PHPStan / Larastan static analysis errors across `WorkQueuesCommand.php`, `BiometricConsentController.php`, `FanOutBiometricConsentRequestsJob.php`, and `SendBiometricConsentNotificationJob.php` under PHPStan Level Max and strict rules.

## Key Changes
1. **WorkQueuesCommand.php**:
   - Replaced mixed-to-string cast with safe `is_string($value) && $value !== '' ? $value : $default` in `$configured` closure.
   - Handled `config('queue.default', '')` safely with type checking.
2. **BiometricConsentController.php**:
   - Added `assert($gymMember instanceof GymMember)` after `findOrFail()` in both `show()` and `store()` methods to narrow the union return type of `findOrFail` (`GymMember|Collection`).
3. **FanOutBiometricConsentRequestsJob.php**:
   - Used `config()->string('queue.notifications_queue', 'notifications')` for type-safe queue assignment.
   - Replaced nullsafe `$actor->status?->value` with `$actor->status->value` since `status` is non-nullable on `User`.
4. **SendBiometricConsentNotificationJob.php**:
   - Used `config()->string(...)` for queue name and Twilio sandbox number.
   - Removed redundant string casts on `$member->phone` when passing to `PhoneNumber::toDialableE164()` and `WhatsAppSanitizer::maskPhoneNumber()`.
   - Supplied strict callback `static fn (string $val): bool => $val !== ''` to `array_filter()` and eliminated redundant string cast on `$this->gymMember->name`.

## Verification Evidence
Ran PHPStan with Level Max + strict rules:
```bash
docker-compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress \
  app/Console/Commands/WorkQueuesCommand.php \
  app/Http/Controllers/BiometricConsentController.php \
  app/Jobs/FanOutBiometricConsentRequestsJob.php \
  app/Jobs/SendBiometricConsentNotificationJob.php
```
Result: **[OK] No errors** across all 4 files.
