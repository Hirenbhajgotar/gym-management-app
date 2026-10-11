# Plan 6.2 Summary: Fix PHPStan Issues in Biometric & Messaging Services

## Overview
Resolved all 11 PHPStan / Larastan static analysis errors across `BiometricConsentUrlFactory.php`, `WhatsAppVariableResolver.php`, `MetaMessagingService.php`, and `TwilioMessagingService.php` under PHPStan Level Max and strict rules.

## Key Changes
1. **BiometricConsentUrlFactory.php**:
   - Switched from mixed-to-int cast `(int) config(...)` to Laravel 12's `config()->integer('biometrics.consent_url_expiry_days', self::MIN_EXPIRY_DAYS)`.
2. **WhatsAppVariableResolver.php**:
   - Removed redundant string casts on `$key` in `resolve()` and `extractCustomPlaceholders()` since array keys from `getVariablePlaceholders()` are already string-typed.
3. **MetaMessagingService.php**:
   - Safely narrowed `'error'` return offset in `sendMessage()` and `sendTemplateMessage()` to `string|null` instead of passing `mixed` from `$response['error']`.
   - Replaced redundant `is_scalar()` evaluation with direct typed `string $value` in parameter text mapping.
4. **TwilioMessagingService.php**:
   - Ensured `$messageId` is typed as `string|null` when calling `$this->credits->logUsage()`.
   - Guaranteed `$messageSid` is typed as `string` before calling `$this->logUsage()`.
   - Added array shape type annotations for `$response` in `sendTextMessage()` and `sendTemplateMessage()`.

## Verification Evidence
Ran PHPStan with Level Max + strict rules:
```bash
docker-compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress \
  app/Services/Biometric/BiometricConsentUrlFactory.php \
  app/Services/WhatsAppVariableResolver.php \
  app/Services/Meta/MetaMessagingService.php \
  app/Services/Twilio/TwilioMessagingService.php
```
Result: **[OK] No errors** across all 4 files.
