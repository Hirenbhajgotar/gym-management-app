---
phase: 6
plan: 2
wave: 1
depends_on: []
---

# Plan 6.2: Fix PHPStan Issues in Biometric & Messaging Services

## Objective
Fix all 11 PHPStan / Larastan static analysis errors across `BiometricConsentUrlFactory.php`, `WhatsAppVariableResolver.php`, `MetaMessagingService.php`, and `TwilioMessagingService.php` ensuring strict array shapes, argument types, and return types.

## Context
- `phpstan.neon`
- `app/Services/Biometric/BiometricConsentUrlFactory.php`
- `app/Services/WhatsAppVariableResolver.php`
- `app/Services/Meta/MetaMessagingService.php`
- `app/Services/Twilio/TwilioMessagingService.php`

## Tasks

<task type="auto">
  <name>Fix typing in BiometricConsentUrlFactory and WhatsAppVariableResolver</name>
  <files>
    app/Services/Biometric/BiometricConsentUrlFactory.php
    app/Services/WhatsAppVariableResolver.php
  </files>
  <action>
    1. In `app/Services/Biometric/BiometricConsentUrlFactory.php`:
       - Line 35: Resolve `Cannot cast mixed to int` by using `config()->integer('biometrics.consent_url_expiry_days', self::MIN_EXPIRY_DAYS)` or safe integer extraction `is_numeric($raw = config('biometrics.consent_url_expiry_days', self::MIN_EXPIRY_DAYS)) ? (int) $raw : self::MIN_EXPIRY_DAYS`.
    2. In `app/Services/WhatsAppVariableResolver.php`:
       - Line 102: Resolve `Casting to string something that's already string` on `$key` in foreach loop by using `$variables[$key] = $this->resolveSource($source, $key, $placeholderArr, $context);`.
       - Line 269: Resolve useless string cast on `$key` in `$custom[$key] = ...`.
  </action>
  <verify>
    docker-compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress app/Services/Biometric/BiometricConsentUrlFactory.php app/Services/WhatsAppVariableResolver.php
  </verify>
  <done>
    PHPStan reports [OK] No errors for both BiometricConsentUrlFactory.php and WhatsAppVariableResolver.php.
  </done>
</task>

<task type="auto">
  <name>Fix return types and argument types in Meta and Twilio Messaging Services</name>
  <files>
    app/Services/Meta/MetaMessagingService.php
    app/Services/Twilio/TwilioMessagingService.php
  </files>
  <action>
    1. In `app/Services/Meta/MetaMessagingService.php`:
       - Lines 111 & 251: Resolve return type mismatch for `'error'`. The method return shape specifies `'error' => string|null`, but `$response['error']` from `WhatsAppSendCharge::attempt()` is `mixed`. Narrow `'error' => is_string($response['error'] ?? null) ? $response['error'] : 'Failed to send message'`.
       - Line 218: Resolve `Call to function is_scalar() with string will always evaluate to true` in `parameters` array mapping by removing redundant `is_scalar()` check if the item is already string-typed, or documenting the variable array type properly.
    2. In `app/Services/Twilio/TwilioMessagingService.php`:
       - Line 103: Resolve `Parameter #3 $messageId of method WhatsAppCreditService::logUsage() expects string|null, mixed given` by passing `is_string($response['message_id'] ?? null) ? $response['message_id'] : null`.
       - Lines 105 & 201: Resolve return type mismatch where methods return `array<string, mixed>` instead of their declared array shapes. Ensure `$response` returned from `WhatsAppSendCharge::attempt()` satisfies the documented shape, or type-assert the response array shape.
       - Line 198: Resolve `Parameter #3 $messageSid of method TwilioMessagingService::logUsage() expects string, mixed given` by passing `is_string($response['message_id'] ?? null) ? $response['message_id'] : ''` or ensuring `$messageSid` is a non-mixed string before logging.
  </action>
  <verify>
    docker-compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress app/Services/Meta/MetaMessagingService.php app/Services/Twilio/TwilioMessagingService.php
  </verify>
  <done>
    PHPStan reports [OK] No errors for both MetaMessagingService.php and TwilioMessagingService.php.
  </done>
</task>

## Success Criteria
- [ ] `BiometricConsentUrlFactory.php`, `WhatsAppVariableResolver.php`, `MetaMessagingService.php`, and `TwilioMessagingService.php` pass PHPStan with 0 errors.
- [ ] Related feature tests (`MetaMessagingServiceTest`, `SendBiometricConsentNotificationJobTest`, `TemplateLookupTest`) pass without regression.
