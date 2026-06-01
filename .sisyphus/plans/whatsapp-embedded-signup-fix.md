# WhatsApp Embedded Signup Fix Plan

## TL;DR

> **Quick Summary**: Fix `EmbeddedSignupController.php` to properly save WABA details after Meta OAuth callback. Add new method `completeEmbeddedSignup()` in `MetaWabaService` to handle saving the newly created WABA (vs connecting existing WABA which `connectManualWaba` handles).

> **Deliverables**:
> - New `MetaWabaService::completeEmbeddedSignup()` method - saves WABA after embedded signup OAuth
> - Updated `EmbeddedSignupController.php` - calls the new method instead of old service

> **Estimated Effort**: Short
> **Parallel Execution**: NO - sequential task
> **Critical Path**: Create new method → Update controller → Test

---

## Context

### User Clarification (Critical)
- **MetaWabaService::connectManualWaba()**: For connecting an **existing** WABA (user already has WhatsApp Business Account and enters details manually)
- **MetaWabaService::connectIndividualWaba()**: For connecting existing individual WABA
- **MetaEmbeddedSignupService**: For **creating a NEW** WABA via Meta's embedded signup OAuth flow

### Problem Analysis
The embedded signup flow:
1. User clicks "Connect via Facebook" → `MetaEmbeddedSignupService::startEmbeddedSignup()` → redirects to Facebook
2. User authenticates on Facebook → callback comes back with code and state
3. `MetaEmbeddedSignupService::handleEmbeddedSignupCallback()` processes OAuth, returns:
   - `waba_id` - the newly created WABA ID
   - `phone_number_id` - the phone number ID (may be null if user hasn't selected yet)
   - `access_token` - Meta access token for API calls
4. **GAP**: Nothing saves these to the database!

The current code (lines 94-103) tries to use `EmbeddedSignupService::connectWabaToSubaccount()` which:
- Creates Twilio Client with subaccount credentials
- Tries to register WhatsApp sender via Twilio API
- **WRONG** for WABA provider - this is not a Twilio flow!

### What Exists Already
- `MetaWhatsAppProvider` implements `EmbeddedSignupInterface`
- `MetaEmbeddedSignupService` - handles OAuth callback and token exchange (already called at line 83)
- `MetaWabaService` - has `connectIndividualWaba()` and `connectManualWaba()` for EXISTING WABA
- Models: `WhatsAppAccount`, `WhatsAppPhoneNumber`, `WabaSetupSession`

---

## Work Objectives

### Core Objective
After `MetaEmbeddedSignupService::handleEmbeddedSignupCallback()` returns successfully, properly save the newly created WABA:
1. Save WABA details (waba_id, access_token) to WhatsAppAccount
2. Set waba_mode = INDIVIDUAL (user created via embedded signup, owns it)
3. Set embedded_signup_completed = true
4. Set status = PENDING (needs verification with Meta)
5. If phone_number_id exists, can optionally fetch phone details and save (but often it's null as user selects later)

### Concrete Deliverables
1. **New MetaWabaService::completeEmbeddedSignup()** - Method specifically for completing embedded signup (creating NEW WABA)
2. **Updated EmbeddedSignupController** - Calls the new method instead of old Twilio service

### Definition of Done
- [ ] MetaWabaService::completeEmbeddedSignup() method added
- [ ] EmbeddedSignupController no longer calls old EmbeddedSignupService
- [ ] WABA details properly saved to WhatsAppAccount after callback
- [ ] Tests pass

### Must Have
- Preserve existing functionality - users should still be able to complete embedded signup
- Handle both cases: with phone_number_id and without (phone selection happens later)
- Don't use Twilio APIs - this is pure Meta flow
- Set status = PENDING (not ACTIVE until verified)

### Must NOT Have
- Don't break existing WABA provider functionality
- Don't modify WhatsAppSetup.php (it's already using the provider correctly)
- Don't set status to ACTIVE immediately - needs verification

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: Tests-after implementation
- **Framework**: PHPUnit

### QA Policy
- Use `php artisan test` to verify functionality
- Test the callback flow with mock data

---

## Execution Strategy

### Task Breakdown

#### Task 1: Create MetaWabaService::completeEmbeddedSignup() method
**File**: `app/Services/Meta/MetaWabaService.php`

**What to do**:
Add new public method for completing embedded signup (saving NEW WABA created via OAuth):

```php
public function completeEmbeddedSignup(int $gymId, string $wabaId, string $accessToken, ?string $phoneNumberId = null): array
{
    // Get or create WhatsAppAccount
    $account = WhatsAppAccount::firstOrCreate(
        ['gym_id' => $gymId],
        [
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
        ]
    );

    // Update with WABA details from embedded signup
    $account->update([
        'waba_id' => $wabaId,
        'waba_mode' => WabaModeEnum::INDIVIDUAL,  // User created via embedded signup
        'meta_access_token' => $accessToken,
        'embedded_signup_completed' => true,
        'status' => WhatsAppAccountStatusEnum::PENDING,  // Needs verification
    ]);

    // Note: phone_number_id is often null in embedded signup 
    // (user selects phone in next step of wizard)
    // Only create phone record if we have actual phone number details
    
    // Invalidate provider cache
    WhatsAppProviderFactory::invalidateProviderCache($gymId);

    return [
        'success' => true,
        'waba_id' => $wabaId,
        'message' => 'Embedded signup completed successfully',
    ];
}
```

**Notes**:
- For embedded signup, phone_number_id might be null (user selects phone in next step)
- We should NOT create phone number record if phone_number_id is null
- The access token from embedded signup is different from manual connect - we save it directly

**Recommended Agent Profile**:
- **Category**: `quick` (single method addition)
- **Skills**: `["laravel-specialist", "laravel-best-practices"]`

**References**:
- `app/Services/Meta/MetaWabaService.php:connectManualWaba()` - For reference on saving (but this is different use case)
- `app/Services/Meta/MetaWabaService.php:connectIndividualWaba()` - Simple WABA save pattern
- `app/Models/WhatsAppAccount.php` - Model with waba_id, meta_access_token fields

**QA Scenarios**:

Scenario: Embedded signup callback with WABA ID (happy path)
  Tool: Bash (php artisan tinker / PHP)
  Preconditions: Meta OAuth returns waba_id and access_token
  Steps:
    1. Create test gym with WhatsAppAccount
    2. Call completeEmbeddedSignup with mock data
    3. Assert WhatsAppAccount has waba_id set
    4. Assert meta_access_token is stored (encrypted)
    5. Assert embedded_signup_completed = true
    6. Assert waba_mode = INDIVIDUAL
    7. Assert status = PENDING
  Expected Result: WABA details saved correctly
  Evidence: Database record verification

---

#### Task 2: Update EmbeddedSignupController to use new method
**File**: `app/Http/Controllers/WhatsApp/EmbeddedSignupController.php`

**What to do**:
- Replace old call:
  ```php
  // OLD (WRONG):
  $this->embeddedSignupService->connectWabaToSubaccount(
      $tenantId,
      $result['waba_id'],
      $phoneNumber,
      $result['access_token']
  );
  ```
- With new call via MetaWhatsAppProvider:
  ```php
  // NEW (CORRECT):
  $this->metaWhatsAppProvider->completeEmbeddedSignup(
      $tenantId,
      $result['waba_id'],
      $result['access_token'],
      $result['phone_number_id'] ?? null
  );
  ```

**Why inject MetaWhatsAppProvider**:
- It already has MetaWabaService accessible via `wabaSetup()` method
- Keeps single point of contact for Meta operations

**Recommended Agent Profile**:
- **Category**: `quick` (single file change)
- **Skills**: `["laravel-specialist", "laravel-best-practices"]`

**References**:
- `app/Providers/MetaWhatsAppProvider.php` - Has wabaSetup() method to access MetaWabaService
- Current controller already injects MetaWhatsAppProvider (line 16)

---

#### Task 3: Run tests and verify
**What to do**:
- Run existing WhatsApp-related tests
- Verify no regressions
- Check PHP syntax

**Recommended Agent Profile**:
- **Category**: `quick`
- **Testing**: Run `php artisan test --filter=WhatsApp`

---

## Final Verification Wave

- [ ] F1. Code Review - Verify MetaWabaService::completeEmbeddedSignup() added correctly
- [ ] F2. Code Review - Verify controller uses new method instead of old service
- [ ] F3. PHP Syntax - `php -l` on changed files
- [ ] F4. Tests - Run existing WhatsApp tests

---

## Commit Strategy

- Message: `fix(whatsapp): use MetaWabaService for embedded signup instead of Twilio`
- Files: 
  - `app/Services/Meta/MetaWabaService.php`
  - `app/Http/Controllers/WhatsApp/EmbeddedSignupController.php`

---

## Success Criteria

### Final Checklist
- [ ] MetaWabaService::completeEmbeddedSignup() method added
- [ ] EmbeddedSignupController uses new method instead of old service
- [ ] WABA details properly saved to WhatsAppAccount (waba_id, waba_mode=INDIVIDUAL, meta_access_token, embedded_signup_completed=true, status=PENDING)
- [ ] PHP syntax valid
- [ ] No test regressions