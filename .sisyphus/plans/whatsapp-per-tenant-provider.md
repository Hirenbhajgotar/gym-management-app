# WhatsApp Per-Tenant Provider Selection Plan

## TL;DR

> **Quick Summary**: Implement per-tenant WhatsApp provider selection - if tenant has phone number use WABA (Meta embedded signup), if needs to purchase use Twilio (subaccount). Store provider in database, update factory and setup flow.
> 
> **Deliverables**:
> - Database migration for provider_type field
> - Updated WhatsAppAccount model with provider and phone_number_id
> - Per-tenant WhatsAppProviderFactory
> - Redesigned 5-step WhatsAppSetup wizard
> - Proper WABA credential storage
> 
> **Estimated Effort**: Large
> **Parallel Execution**: YES - 3 waves
> **Critical Path**: Migration → Model → Factory → Setup Page

---

## Context

### Original Request
User wants provider selection to be per gym (tenant), not global. The logic:
- If tenant already has a number → use WABA provider (Meta WhatsApp embedded signup flow)
- If tenant wants to purchase number → use Twilio provider (subaccount + WhatsApp sender registration)

### Research Findings

#### Current Architecture
1. **WhatsAppAccount Model** (`app/Models/WhatsAppAccount.php`):
   - Already has fields: `waba_id`, `waba_name`, `waba_status`, `waba_mode`, `messaging_service_sid`
   - Meta credentials: `meta_business_portfolio_id`, `meta_access_token`, `embedded_signup_completed`, `embedded_signup_session_id`
   - Twilio credentials: `twilio_account_sid`, `twilio_auth_token`
   - Status enums: `status`, `verification_status`
   - Business info: `business_name`, `business_email`, `business_description`

2. **WhatsAppProviderFactory** (`app/Providers/WhatsAppProviderFactory.php`):
   - Uses global config: `config('services.whatsapp.provider')`
   - Constants: `PROVIDER_TWILIO = 'twilio'`, `PROVIDER_META = 'waba'`
   - Method `create(int $gymId)` returns provider instance
   - Current: Uses config, NOT per-tenant

3. **WhatsAppSetup Page** (`app/Filament/Admin/Pages/WhatsAppSetup.php`):
   - Current 6 steps (not 5 as mentioned):
     1. Create Account
     2. WABA Setup (connect existing or create new)
     3. Phone Number (purchase or use own - redundant with Step 1)
     4. Templates
     5. Welcome Credits
     6. Finish
   - The "Phone Number" step should be combined with "Create Account" step

4. **Existing Providers** (`app/Providers/`):
   - `TwilioWhatsAppProvider.php` - wraps TwilioWhatsAppService
   - `MetaWhatsAppProvider.php` - implements all interface methods

5. **Key Services**:
   - `TwilioAccountManager.php` - creates subaccounts, manages phone numbers, WABA
   - `EmbeddedSignupService.php` - handles WABA embedded signup flow

---

## Work Objectives

### Core Objective
Implement per-tenant WhatsApp provider selection stored in database, with setup wizard flow that combines account creation and phone number handling, then routes to appropriate provider setup.

### Concrete Deliverables
- [ ] `provider_type` column in `whatsapp_accounts` table (ENUM: 'twilio' | 'waba')
- [ ] `waba_phone_number_id` field in `whatsapp_accounts` table (for WABA phone number ID)
- [ ] Updated factory reads provider from WhatsAppAccount
- [ ] 5-step WhatsAppSetup wizard (combines Phone Number with Create Account)
- [ ] Proper WABA credential storage in database
- [ ] Backward compatibility with existing accounts

### Definition of Done
- [ ] New gym can select provider during setup flow
- [ ] Provider selection persists and is used for messaging
- [ ] Existing accounts continue working (default to Twilio)
- [ ] WABA setup uses embedded signup when selected
- [ ] Twilio setup uses subaccount when selected

### Must Have
- Per-tenant provider selection (not global config)
- WABA phone number ID storage
- Combined Create Account + Phone Number step
- Proper credential storage for both providers
- Backward compatibility

### Must NOT Have
- Breaking existing Twilio functionality
- Global provider config override without migration path
- Duplicate phone number logic in multiple steps

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: Tests-after implementation
- **Framework**: Laravel PHPUnit

### QA Policy
Every task includes agent-executed QA scenarios - the executing agent will directly verify each deliverable.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation - Start Immediately):
├── Task 1: Database migration for provider_type and waba_phone_number_id
├── Task 2: Update WhatsAppAccount model with new fields
├── Task 3: Update WhatsAppProviderFactory for per-tenant selection
└── Task 4: Add provider helper methods to WhatsAppAccount

Wave 2 (Core Implementation - Max Parallel):
├── Task 5: Redesign WhatsAppSetup wizard (5-step flow)
├── Task 6: Implement combined Account + Phone Number step
├── Task 7: Conditional WABA setup based on provider
├── Task 8: Conditional Twilio setup based on provider
└── Task 9: Update TwilioAccountManager provider logic

Wave 3 (Integration + Polish):
├── Task 10: Test provider selection flow
├── Task 11: Verify backward compatibility
├── Task 12: Run Pint formatter
└── Task 13: Syntax validation

Critical Path: Task 1 → Task 2 → Task 3 → Task 5 → Task 6-8 → Task 10
```

---

## TODOs

- [ ] 1. Create database migration for provider_type column

  **What to do**:
  - Add `provider_type` ENUM column: 'twilio' (default) | 'waba'
  - Add `waba_phone_number_id` for WABA phone number storage
  - Add `phone_number_id` for Twilio phone number (alternative storage)
  - Make fields nullable for existing records

  **Must NOT do**:
  - Drop existing columns
  - Make fields NOT NULL without handling existing data

  **Recommended Agent Profile**:
  > **Category**: `quick`
  >   Reason: Database migration is straightforward
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES (Wave 1)
  - **Parallel Group**: Wave 1 (with Tasks 2, 3, 4)

  **References**:
  - `app/Models/WhatsAppAccount.php` - existing fields to add to
  - `database/migrations/` - check existing migration pattern

  **Acceptance Criteria**:
  - [ ] Migration runs successfully
  - [ ] Existing data preserved

- [ ] 2. Update WhatsAppAccount model with provider_type field

  **What to do**:
  - Add `provider_type` to `$fillable`
  - Add cast for provider_type enum
  - Add helper: `getProvider(): string`
  - Add helper: `isTwilioProvider(): bool`
  - Add helper: `isWabaProvider(): bool`
  - Add helper: `getPhoneNumberId(): ?string` (unified for both providers)

  **Must NOT do**:
  - Break existing model methods

  **Recommended Agent Profile**:
  > **Category**: `quick`
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES (Wave 1)
  - **Parallel Group**: Wave 1 (with Tasks 1, 3, 4)

  **References**:
  - `app/Models/WhatsAppAccount.php` lines 86-236

  **Acceptance Criteria**:
  - [ ] Model has provider_type in fillable
  - [ ] Helper methods work correctly

- [ ] 3. Update WhatsAppProviderFactory for per-tenant selection

  **What to do**:
  - Modify `create(int $gymId)` to read from WhatsAppAccount
  - Query WhatsAppAccount for provider_type
  - Fallback to Twilio if null (backward compatibility)
  - Update method `getProviderForGym(int $gymId)`

  **Must NOT do**:
  - Remove existing constants (needed for config)

  **Recommended Agent Profile**:
  > **Category**: `quick`
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES (Wave 1)
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 4)

  **References**:
  - `app/Providers/WhatsAppProviderFactory.php` lines 33-60

  **Acceptance Criteria**:
  - [ ] Factory reads provider_type from database
  - [ ] Default fallback to Twilio for null

- [ ] 4. Add provider helper methods to WhatsAppAccount

  **What to do**:
  - Add `hasProvider(): bool`
  - Add `requiresWabaSetup(): bool` (phone number exists but no WABA)
  - Add `requiresTwilioSetup(): bool` (no phone number, needs one)

  **Must NOT do**:
  - Duplicate logic already in model

  **Recommended Agent Profile**:
  > **Category**: `quick`
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES (Wave 1)
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 3)

  **References**:
  - `app/Models/WhatsAppAccount.php` lines 178-235

  **Acceptance Criteria**:
  - [ ] Helper methods return correct values

- [ ] 5. Redesign WhatsAppSetup wizard (5-step flow)

  **What to do**:
  - Combine "Create Account" with "Phone Number" → Step 1: Create Account
  - Rename Step 2 to "WhatsApp Business"
  - Remove Step 3 (Phone Number - now in Step 1)
  - Keep Step 4: Default Templates → Step 3
  - Keep Step 5: Welcome Credits → Step 4  
  - Keep Step 6: Finish → Step 5

  **Must NOT do**:
  - Change business logic, just reorganize steps

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Tasks 1-4 must complete first
  - **Blocks**: Tasks 6-9

  **References**:
  - `app/Filament/Admin/Pages/WhatsAppSetup.php` lines 318-340

  **Acceptance Criteria**:
  - [ ] Wizard shows 5 steps instead of 6
  - [ ] Steps are logically organized

- [ ] 6. Implement combined Account + Phone Number step

  **What to do**:
  - In Step 1, ask: "Do you have a phone number?"
    - YES → Use WABA provider, skip subaccount creation
    - NO → Create Twilio subaccount, proceed to purchase
  - If has number: set provider_type = 'waba'
  - If needs number: set provider_type = 'twilio', create subaccount
  - Handle "purchase number" option in same step

  **Must NOT do**:
  - Create duplicate phone numbers

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 5
  - **Blocks**: Tasks 7, 8

  **References**:
  - `app/Filament/Admin/Pages/WhatsAppSetup.php` lines 342-385 (Create Account step)
  - `app/Filament/Admin/Pages/WhatsAppSetup.php` lines 531-736 (Phone Number step)

  **Acceptance Criteria**:
  - [ ] Provider selected based on phone number choice
  - [ ] provider_type saved to database

- [ ] 7. Conditional WABA setup based on provider

  **What to do**:
  - In Step 2 (WhatsApp Business):
    - If provider = 'waba':
      - Use WABA embedded signup flow
      - Use EmbeddedSignupService
      - Don't create Twilio subaccount
    - If provider = 'twilio':
      - Use Twilio WABA flow
  - Show appropriate UI for each provider

  **Must NOT do**:
  - Run WABA flow for Twilio provider

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 5
  - **Blocks**: Task 9

  **References**:
  - `app/Filament/Admin/Pages/WhatsAppSetup.php` lines 387-529 (WABA step)
  - `app/Services/EmbeddedSignupService.php`

  **Acceptance Criteria**:
  - [ ] WABA flow for waba provider
  - [ ] Twilio flow for twilio provider

- [ ] 8. Conditional Twilio setup based on provider

  **What to do**:
  - If provider = 'twilio':
    - Create Twilio subaccount (if not done in Step 1)
    - Purchase/register phone number
    - Register as WhatsApp sender
  - Store credentials in database

  **Must NOT do**:
  - Duplicate subaccount creation

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Tasks 5, 7

  **References**:
  - `app/Services/TwilioAccountManager.php`

  **Acceptance Criteria**:
  - [ ] Twilio subaccount created
  - [ ] Phone number purchased

- [ ] 9. Update TwilioAccountManager provider logic

  **What to do**:
  - Add check for provider_type in key methods
  - If WABA provider, skip certain Twilio operations
  - Clean up method logic for dual provider support

  **Must NOT do**:
  - Break existing Twilio functionality

  **Recommended Agent Profile**:
  > **Category**: `quick`
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 5

  **References**:
  - `app/Services/TwilioAccountManager.php`

  **Acceptance Criteria**:
  - [ ] Manager respects provider selection

- [ ] 10. Test provider selection flow

  **What to do**:
  - Simulate setup flow with existing number (WABA)
  - Simulate setup flow without number (Twilio)
  - Verify records created correctly

  **Must NOT do**:
  - Test in production

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Wave 2

  **Acceptance Criteria**:
  - [ ] WABA flow works
  - [ ] Twilio flow works

- [ ] 11. Verify backward compatibility

  **What to do**:
  - Load existing WhatsAppAccount
  - Verify provider_type defaults to 'twilio'
  - Verify messaging still works
  - Test WhatsAppChannel sends messages

  **Must NOT do**:
  - Break existing functionality

  **Recommended Agent Profile**:
  > **Category**: `unspecified-high`
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 10

  **Acceptance Criteria**:
  - [ ] Existing accounts work
  - [ ] No breaking changes

- [ ] 12. Run Pint formatter

  **What to do**:
  - Run `vendor/bin/pint --dirty`

  **Recommended Agent Profile**:
  > **Category**: `quick`
  > **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Tasks 1-11

- [ ] 13. Syntax validation

  **What to do**:
  - Run `php -l` on modified files

---

## Final Verification Wave

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan. Verify all 13 tasks address the requirements.

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run syntax check and Pint.

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Test setup flows manually.

- [ ] F4. **Scope Fidelity Check** — `deep`
  Verify no scope creep.

---

## Commit Strategy

- **1**: `feat(whatsapp): add per-tenant provider selection`

---

## Success Criteria

### Verification Commands
```bash
php artisan migrate  # Migration runs
php -l app/Models/WhatsAppAccount.php  # Syntax OK
vendor/bin/pint --dirty  # Code formatted
```

### Final Checklist
- [ ] New gym can select provider during setup
- [ ] Provider persists in database
- [ ] WABA uses embedded signup flow
- [ ] Twilio uses subaccount flow
- [ ] Existing accounts unchanged