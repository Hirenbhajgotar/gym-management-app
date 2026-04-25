# WhatsApp Setup Page Refactor Plan

## TL;DR

> **Quick Summary**: Refactor the WhatsAppSetup Filament page to use provider-first wizard flow with full integration into WhatsAppProviderInterface. Users select their provider (Twilio or WABA) upfront, then see provider-specific setup steps.
> 
> **Deliverables**:
> - Provider-first wizard (select provider first, then setup steps)
> - Full WhatsAppProviderInterface integration for both providers
> - WABA: Embedded signup + manual connect options
> - Twilio: Search/purchase number + subaccount creation
> - Backward compatibility with existing Twilio accounts
> 
> **Estimated Effort**: Large
> **Parallel Execution**: YES - 2 waves
> **Critical Path**: Select Provider → Configure Provider → Phone Number → Templates → Credits

---

## Context

### Original Request
User wants to refactor WhatsAppSetup page to use the new provider type system:
- Options to select provider type (Twilio or WABA)
- If WABA: show WABA setup steps + store in database + embedded signup
- If Twilio: search/purchase number + create subaccount

### User Decisions (via Questions)
1. **Provider-First Flow** - Select provider first, then show provider-specific wizard
2. **Full Integration** - Use WhatsAppProviderInterface methods for everything
3. **Both WABA Methods** - Show both embedded signup and manual connect options

### Infrastructure Already Built
- `app/Interfaces/WhatsAppProviderInterface.php` - 25+ methods
- `app/Providers/WhatsAppProviderFactory.php` - Per-tenant provider selection
- `app/Providers/TwilioWhatsAppProvider.php` - Twilio implementation
- `app/Providers/MetaWhatsAppProvider.php` - Meta WABA implementation
- `app/Services/EmbeddedSignupService.php` - For WABA embedded signup

### Models Already Have Fields
- `WhatsAppAccount`: provider_type, waba_id, meta_access_token, meta_business_portfolio_id
- `WhatsAppPhoneNumber`: phone_number_sid (Twilio), waba_phone_number_id (WABA), channel_sender_sid

---

## Work Objectives

### Core Objective
Replace the current WhatsAppSetup page flow with a provider-first wizard that:
1. Shows provider selection at the start
2. Uses WhatsAppProviderInterface for all operations
3. Supports both WABA (embedded signup + manual) and Twilio (search/purchase + subaccount) flows
4. Maintains backward compatibility with existing Twilio accounts

### Concrete Deliverables
- [ ] Refactored WhatsAppSetup.php with provider-first wizard
- [ ] Integrated provider selection step as first step
- [ ] Twilio setup wizard steps: Subaccount → Phone Number → Templates → Credits
- [ ] WABA setup wizard steps: Setup Method → Connect → Templates → Credits
- [ ] Uses WhatsAppProviderFactory for provider instantiation
- [ ] Uses provider methods instead of direct TwilioAccountManager calls
- [ ] Proper phone number SID storage (phone_number_sid for Twilio, waba_phone_number_id for WABA)
- [ ] Backward compatibility with existing Twilio accounts

### Definition of Done
- [ ] Provider selection appears as first step in wizard
- [ ] Selecting "Twilio" shows Twilio-specific setup flow
- [ ] Selecting "WABA" shows WABA-specific setup flow
- [ ] Twilio flow creates subaccount via provider
- [ ] Twilio flow searches/purchases phone numbers via provider
- [ ] WABA flow supports embedded signup via provider
- [ ] WABA flow supports manual connect via provider
- [ ] All data stored in respective database columns
- [ ] Existing Twilio accounts still work (backward compatibility)
- [ ] Run `php artisan test` - all passing

### Must Have
- Provider selection UI as step 1
- Twilio provider fully integrated via interface
- WABA provider fully integrated via interface
- Embedded signup flow works for WABA
- Manual connect flow works for WABA
- Phone numbers stored with correct SID type

### Must NOT Have (Guardrails)
- No direct TwilioAccountManager calls in setup page (must use providers)
- No removing existing Twilio functionality (backward compatibility)
- No skipping provider_type in WhatsAppAccount
- No storing WABA data in wrong columns

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES
- **Automated tests**: NO (existing tests cover backend)
- **Framework**: N/A

### QA Policy
Every task includes agent-executed QA scenarios - verify Filament form renders and works.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation + Provider Integration):
├── T1: Add provider selection step to wizard (first step) [quick]
├── T2: Create provider-first wizard schema structure [quick]
├── T3: Add provider instance getter with factory [quick]
└── T4: Add provider-specific form schemas (Twilio/WABA) [quick]

Wave 2 (Twilio Flow + WABA Flow + Integration):
├── T5: Implement Twilio subaccount via provider (depends: T1,T2,T3) [deep]
├── T6: Implement Twilio phone number search via provider (depends: T5) [deep]
├── T7: Implement Twilio phone number purchase via provider (depends: T6) [deep]
├── T8: Implement WABA embedded signup via provider (depends: T1,T2,T3) [deep]
├── T9: Implement WABA manual connect via provider (depends: T8) [deep]
├── T10: Add backward compatibility for existing Twilio accounts [deep]
├── T11: Update all action methods to use providers (depends: T5,T6,T7,T8,T9) [deep]
└── T12: Run tests and verify end-to-end (depends: T5-T11) [deep]
```

### Dependency Matrix
- **T1**: - - T2,T3,T4
- **T2**: T1 - T4,T5,T6,T7,T8,T9
- **T3**: T1,T2 - T4,T5,T6,T7,T8,T9
- **T4**: T1,T2,T3 - -
- **T5**: T4 - T6,T7,T10
- **T6**: T5 - T7,T10,T11
- **T7**: T6 - T10,T11,T12
- **T8**: T4 - T9,T10,T11
- **T9**: T8 - T10,T11,T12
- **T10**: T5,T6,T7,T8,T9 - T11,T12
- **T11**: T6,T7,T9,T10 - T12
- **T12**: T7,T9,T11 - -

---

## TODOs

- [ ] T1. Add provider selection step to wizard as first step

  **What to do**:
  - Add new wizard step "Select Provider" as step 1
  - Radio options: 'twilio' (Twilio) and 'waba' (Meta WABA)
  - Store provider selection in $selectedProvider property
  - Show descriptions for each provider

  **Must NOT do**:
  - Remove existing provider_type from form schema

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: UI/form changes only
  - **Skills**: [`volt-development`]
    - Reason: Filament form components

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: T2,T3,T4
  - **Blocked By**: None

  **References**:
  - Current `WhatsAppSetup.php` lines 388-426: Existing provider selection section
  - `app/Enums/WhatsAppProviderTypeEnum.php`: Provider type enum values

  **Acceptance Criteria**:
  - [ ] New "Select Provider" step appears as first wizard step
  - [ ] Can select between Twilio and WABA options
  - [ ] Provider selection persists during wizard flow

- [ ] T2. Create provider-first wizard schema structure

  **What to do**:
  - Restructure wizard to show provider steps based on selection
  - Create separate schema methods: getTwilioWizardSteps() and getWabaWizardSteps()
  - Each wizard has: Account/Setup → Phone Number → Templates → Credits
  - Use dynamic step loading based on selected provider

  **Must NOT do**:
  - Change existing view states (onboarding, setup, active, error)

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: UI restructuring

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: T5,T8
  - **Blocked By**: T1

  **References**:
  - Current `WhatsAppSetup.php` lines 339-360: Existing getSetupSchema() wizard

  **Acceptance Criteria**:
  - [ ] Twilio selection shows Twilio-specific wizard steps
  - [ ] WABA selection shows WABA-specific wizard steps
  - [ ] Wizard flow matches user's Provider-First decision

- [ ] T3. Add provider instance getter with factory

  **What to do**:
  - Add protected property: `protected ?WhatsAppProviderInterface $provider`
  - Add method: `getProvider(): WhatsAppProviderInterface`
  - Uses `WhatsAppProviderFactory::create($this->getGymId())`
  - Lazy loads provider on first access

  **Must NOT do**:
  - Hard-code provider instantiation

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple helper method

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: T5,T6,T7,T8,T9,T10,T11
  - **Blocked By**: T1

  **References**:
  - `app/Providers/WhatsAppProviderFactory.php`: Factory pattern

  **Acceptance Criteria**:
  - [ ] getProvider() returns correct provider instance
  - [ ] Provider based on selected provider_type in account
  - [ ] Falls back to config if no account exists

- [ ] T4. Add provider-specific form schemas

  **What to do**:
  - Create `getTwilioAccountSchema()` - shows subaccount + phone number sections
  - Create `getTwilioPhoneNumberSchema()` - search/purchase options
  - Create `getWabaSetupSchema()` - embedded signup + manual connect options
  - Create `getWabaPhoneNumberSchema()` - shows when phone number is connected

  **Must NOT do**:
  - Duplicate form sections between providers

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Form schema definitions

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: T5,T6,T7,T8,T9
  - **Blocked By**: T1,T2,T3

  **References**:
  - Current `WhatsAppSetup.php` lines 362-443: getCreateAccountStep()

  **Acceptance Criteria**:
  - [ ] Twilio form schema shows for Twilio provider
  - [ ] WABA form schema shows for WABA provider
  - [ ] Form fields match provider needs

- [ ] T5. Implement Twilio subaccount creation via provider

  **What to do**:
  - Replace direct `TwilioAccountManager::createSubaccount()` call
  - Use `$this->getProvider()->createSubAccount($gymId, $name, $email)`
  - Store returned account_sid in WhatsAppAccount
  - Handle success/error responses

  **Must NOT do**:
  - Keep calling TwilioAccountManager directly

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Business logic integration with provider interface
  - **Skills**: [`volt-development`]
    - Reason: Integration with existing page

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T6,T7,T10
  - **Blocked By**: T1,T2,T3,T4

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:113`: createSubAccount method
  - Current `WhatsAppSetup.php` lines 655-689: createSubaccount() method

  **QA Scenarios**:
  ```
  Scenario: Create Twilio subaccount successfully
    Tool: Livewire test (simulate form submission)
    Preconditions: Provider selected as Twilio, no account exists
    Steps:
      1. Submit account creation with business_name, business_email
      2. Call provider->createSubAccount()
    Expected Result: Account SID stored in database
    Evidence: Database record with twilio_account_sid

  Scenario: Handle subaccount creation failure
    Tool: Same as above
    Preconditions: Invalid credentials
    Steps:
      1. Submit account creation
      2. Provider returns error
    Expected Result: Error notification displayed
    Evidence: Notification shown to user
  ```

  **Acceptance Criteria**:
  - [ ] Subaccount created via provider interface
  - [ ] Account SID stored in WhatsAppAccount
  - [ ] Error handling works correctly

- [ ] T6. Implement Twilio phone number search via provider

  **What to do**:
  - Replace `TwilioAccountManager::searchNumbers()` with provider equivalent
  - Use `$this->getProvider()->getAvailableNumbers($country, $type, $limit)`
  - Display results in UI
  - Handle empty results

  **Must NOT do**:
  - Keep using TwilioAccountManager directly

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Integration with provider interface
  - **Skills**: [`volt-development`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T7,T10
  - **Blocked By**: T5

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:174`: getAvailableNumbers method
  - Current `WhatsAppSetup.php` lines 946-985: searchNumbers() method

  **Acceptance Criteria**:
  - [ ] Phone numbers searched via provider
  - [ ] Results displayed in UI
  - [ ] Empty state handled

- [ ] T7. Implement Twilio phone number purchase via provider

  **What to do**:
  - Replace `TwilioAccountManager::purchaseNumber()` with provider
  - Use `$this->getProvider()->purchaseNumber($gymId, $phoneNumber)`
  - Store returned phone_number_sid in WhatsAppPhoneNumber
  - Handle purchase success/error

  **Must NOT do**:
  - Store incorrect SID type in wrong column

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Provider integration
  - **Skills**: [`volt-development`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T10,T11,T12
  - **Blocked By**: T6

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:188`: purchaseNumber method
  - Current `WhatsAppSetup.php` lines 987-1038: purchaseNumber() method

  **QA Scenarios**:
  ```
  Scenario: Purchase Twilio phone number
    Tool: Livewire test
    Preconditions: Number selected from search results
    Steps:
      1. Select phone number
      2. Call provider->purchaseNumber()
    Expected Result: Phone number record created with phone_number_sid
    Evidence: whatsapp_phone_numbers table has record
  ```

  **Acceptance Criteria**:
  - [ ] Number purchased via provider
  - [ ] phone_number_sid stored in WhatsAppPhoneNumber (not waba_phone_number_id)
  - [ ] Purchase success notification shown

- [ ] T8. Implement WABA embedded signup via provider

  **What to do**:
  - Use provider's requiresEmbeddedSignup() to check if needed
  - Use provider's createWaba() method for initial setup
  - Show EmbeddedSignupService redirect for completion
  - Persist WABA credentials after completion

  **Must NOT do**:
  - Skip storing WABA credentials properly

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Complex multi-step flow
  - **Skills**: [`volt-development`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T9,T10,T11
  - **Blocked By**: T4

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:328`: createWaba method
  - `app/Services/EmbeddedSignupService.php`: Existing service
  - Current `WhatsAppSetup.php` lines 800-849: createWaba() method

  **Acceptance Criteria**:
  - [ ] WABA created via provider
  - [ ] waba_id stored in WhatsAppAccount
  - [ ] Embedded signup flow works
  - [ ] Credentials persisted in correct columns

- [ ] T9. Implement WABA manual connect via provider

  **What to do**:
  - Add manual connect form fields (WABA ID, Phone Number, Meta Token)
  - Use provider's connectManualWaba() method
  - Store waba_id and meta_access_token in WhatsAppAccount
  - Handle validation errors

  **Must NOT do**:
  - Store credentials in wrong columns

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Manual connect flow integration
  - **Skills**: [`volt-development`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T10,T11,T12
  - **Blocked By**: T8

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:362`: connectManualWaba method
  - Current `WhatsAppSetup.php` lines 851-897: connectExistingWaba() method

  **Acceptance Criteria**:
  - [ ] Manual connect form shows for WABA
  - [ ] connectManualWaba called via provider
  - [ ] waba_id stored correctly
  - [ ] meta_access_token stored correctly

- [ ] T10. Add backward compatibility for existing Twilio accounts

  **What to do**:
  - When loading existing account, detect provider_type
  - If provider_type is null or old format, default to Twilio
  - If existing phone numbers, migrate SIDs to correct columns if needed
  - Load existing Twilio data properly

  **Must NOT do**:
  - Break existing accounts

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Migration/compatibility logic
  - **Skills**: [`volt-development`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocks**: T11,T12
  - **Blocked By**: T5,T6,T7,T8,T9

  **References**:
  - `WhatsAppPhoneNumber.php` helper methods for SID validation

  **Acceptance Criteria**:
  - [ ] Existing Twilio accounts load without changes
  - [ ] Provider defaults to Twilio for old accounts
  - [ ] No data loss on existing records

- [ ] T11. Update all action methods to use providers

  **What to do**:
  - Update claimWelcomeCredits() to use provider
  - Update sendTestMessage() to use provider
  - Update any remaining TwilioAccountManager calls
  - Ensure all provider methods called consistently

  **Must NOT do**:
  - Leave any TwilioAccountManager direct calls in setup page

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Code consolidation

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 2
  - **Blocks**: T12
  - **Blocked By**: T7,T9,T10

  **References**:
  - Full WhatsAppSetup.php action methods

  **Acceptance Criteria**:
  - [ ] All actions use provider methods
  - [ ] Zero TwilioAccountManager calls in page

- [ ] T12. Run tests and verify end-to-end

  **What to do**:
  - Run existing WhatsApp tests
  - Run any new edge case tests
  - Verify backward compatibility
  - Check form renders correctly

  **Must NOT do**:
  - Skip running tests

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Full verification

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 2
  - **Blocks**: None
  - **Blocked By**: T11

  **Acceptance Criteria**:
  - [ ] All tests pass
  - [ ] Form renders for both providers
  - [ ] End-to-end flow works

---

## Final Verification Wave

- [ ] F1. **Plan Compliance Audit** — `oracle`
- [ ] F2. **Code Quality Review** — `unspecified-high`
- [ ] F3. **Real Manual QA** — `unspecified-high`
- [ ] F4. **Scope Fidelity Check** — `deep`

---

## Commit Strategy

- **1**: `refactor(whatsapp-setup): Add provider selection step` - WhatsAppSetup.php
- **2**: `refactor(whatsapp-setup): Provider-first wizard flow` - WhatsAppSetup.php
- **3**: `refactor(whatsapp-setup): Twilio provider integration` - WhatsAppSetup.php
- **4**: `refactor(whatsapp-setup): WABA provider integration` - WhatsAppSetup.php
- **5**: `refactor(whatsapp-setup): Backward compatibility` - WhatsAppSetup.php

---

## Success Criteria

### Verification Commands
```bash
vendor/bin/pint --dirty  # Code formatting
php artisan test --filter=WhatsApp  # Tests pass
```

### Final Checklist
- [ ] Provider selection shows first
- [ ] Twilio flow creates subaccount
- [ ] Twilio flow searches/purchases numbers
- [ ] WABA flow embedded signup works
- [ ] WABA flow manual connect works
- [ ] All data stored correctly
- [ ] Existing accounts work
- [ ] All tests pass