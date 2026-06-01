# WhatsApp Provider Interface Implementation Plan

## TL;DR

> Create a future-proof WhatsAppProviderInterface to support both Twilio and Meta WhatsApp Business API (WABA). The system will use a single provider configured globally via config('services.whatsapp.provider').
> 
> **Deliverables**:
> - WhatsAppProviderInterface with comprehensive methods
> - TwilioWhatsAppProvider (wraps existing TwilioWhatsAppService + TwilioAccountManager)
> - MetaWhatsAppProvider (new implementation for WABA API)
> - WhatsAppProviderFactory for config-based provider selection
> - Updated WhatsAppChannel to use the factory
> - Config option for provider selection
> 
> **Estimated Effort**: Medium
> **Parallel Execution**: NO - sequential (each task builds on previous)
> **Critical Path**: Interface → Twilio Provider → Meta Provider → Factory → Config → WhatsAppChannel

---

## Context

### Original Request
Create a WhatsAppProviderInterface for future-proofing. Need to handle both Twilio and Meta WhatsApp APIs with methods like createSubAccount(), sendMessage(), getMessageStatus(), createTemplate(), getAvailableNumbers(), etc. Must not break existing functionality. System should use provider configured in config.

### Interview Summary
**Key Decisions** (from user):
- **Provider Selection**: Single Provider (globally configured via config)
- **Meta Credentials**: From existing WhatsAppAccount Model (meta_access_token field)
- **Backward Compatibility**: Full - existing TwilioWhatsAppService and TwilioAccountManager continue to work

**Research Findings**:
- Existing simple interface (`WhatsAppServiceInterface`) only has `sendMessage()`
- `TwilioWhatsAppService` handles message sending with credit management
- `TwilioAccountManager` handles account/subaccount operations
- `WhatsAppAccount` model already has fields for Meta credentials: `waba_id`, `meta_access_token`, `meta_business_portfolio_id`
- `WhatsAppChannel` directly instantiates `TwilioWhatsAppService`

### Metis Review
**Identified Gaps** (to address in plan):
- Need to map all existing Twilio functionality to interface methods
- Meta WABA API requires different API structure - need to implement from scratch
- Factory pattern needed for clean provider switching
- Must ensure WhatsAppChannel uses factory, not direct instantiation

---

## Work Objectives

### Core Objective
Implement a comprehensive WhatsAppProviderInterface that abstracts all WhatsApp messaging operations, supporting both Twilio and Meta WABA providers. The system will use a single provider configured via config.

### Concrete Deliverables
1. **WhatsAppProviderInterface** - Comprehensive interface with all required methods
2. **TwilioWhatsAppProvider** - Wraps existing TwilioWhatsAppService and TwilioAccountManager
3. **MetaWhatsAppProvider** - New implementation for Meta WABA API
4. **WhatsAppProviderFactory** - Factory for creating provider based on config
5. **Config Update** - Add `whatsapp.provider` option to services.php
6. **WhatsAppChannel Update** - Use factory instead of direct instantiation

### Definition of Done
- [ ] Interface defines all required methods for both providers
- [ ] TwilioWhatsAppProvider wraps existing services without breaking functionality
- [ ] MetaWhatsAppProvider implements all interface methods using WABA API
- [ ] Factory correctly instantiates provider based on config
- [ ] WhatsAppChannel uses factory and maintains backward compatibility
- [ ] Config option added and documented

### Must Have
- All existing Twilio functionality preserved
- Provider selection via config
- Clean abstraction for easy future provider additions

### Must NOT Have (Guardrails)
- Breaking changes to existing API endpoints
- Modifying existing model structures
- Removing existing TwilioWhatsAppService - keep for backward compatibility

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** - ALL verification is agent-executed.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: Existing tests for TwilioWhatsAppService
- **Framework**: PHPUnit

### QA Policy
Every task includes agent-executed QA scenarios.

---

## Execution Strategy

### Sequential Implementation Waves

**Wave 1 (Interface & Twilio Provider)**:
- Task 1: Create comprehensive WhatsAppProviderInterface
- Task 2: Create TwilioWhatsAppProvider wrapping existing services
- Task 3: Update TwilioWhatsAppService to implement interface

**Wave 2 (Meta Provider)**:
- Task 4: Create MetaWhatsAppProvider implementing interface
- Task 5: Implement WABA API methods in MetaWhatsAppProvider

**Wave 3 (Factory & Integration)**:
- Task 6: Create WhatsAppProviderFactory
- Task 7: Update services.php config with provider option
- Task 8: Update WhatsAppChannel to use factory

**Wave 4 (Final Integration)**:
- Task 9: Verify backward compatibility with existing tests

---

## TODOs

- [ ] 1. Create comprehensive WhatsAppProviderInterface

  **What to do**:
  - Create `app/Interfaces/WhatsAppProviderInterface.php`
  - Define all required methods grouped by category:
    - **Messaging**: sendMessage(), sendTemplateMessage(), sendBroadcast(), getMessageStatus()
    - **Account Management**: createSubAccount(), getAccountStatus(), cleanupAccount()
    - **Phone Numbers**: getAvailableNumbers(), purchaseNumber(), registerOwnNumber(), verifyOwnNumber()
    - **Templates**: createTemplate(), getTemplates(), syncTemplateStatus()
    - **Credits**: getCreditBalance(), getMonthlyUsage()
  - Include return type annotations and PHPDoc for each method

  **Must NOT do**:
  - Don't add implementation details
  - Don't reference specific provider APIs

  **Recommended Agent Profile**:
  - **Category**: `ultrabrain`
    - Reason: Interface design requires understanding both providers' capabilities
  - **Skills**: []
  - **Skills Evaluated but Omitted**:
    - No domain overlap needed for interface definition

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Sequential
  - **Blocks**: Tasks 2, 3, 4
  - **Blocked By**: None (can start immediately)

  **References**:
  - `app/Interfaces/WhatsAppServiceInterface.php:1-8` - Current minimal interface
  - `app/Services/TwilioWhatsAppService.php:1-548` - Existing message sending implementation
  - `app/Services/TwilioAccountManager.php:1-1389` - Account management methods

  **WHY Each Reference Matters**:
  - WhatsAppServiceInterface: Current interface to extend
  - TwilioWhatsAppService: Methods to wrap in provider
  - TwilioAccountManager: Account operations to abstract

  **Acceptance Criteria**:
  - [ ] Interface file created at app/Interfaces/WhatsAppProviderInterface.php
  - [ ] All method signatures defined with proper return types
  - [ ] Methods documented with PHPDoc explaining purpose

  **QA Scenarios**:

  ```
  Scenario: Verify interface file exists and has methods
    Tool: Bash
    Preconditions: None
    Steps:
      1. ls app/Interfaces/WhatsAppProviderInterface.php
      2. grep -c "function sendMessage" app/Interfaces/WhatsAppProviderInterface.php
      3. grep -c "function createSubAccount" app/Interfaces/WhatsAppProviderInterface.php
    Expected Result: File exists, at least 10 method signatures defined
    Evidence: .sisyphus/evidence/task-1-interface-exists.txt
  ```

- [ ] 2. Create TwilioWhatsAppProvider wrapping existing services

  **What to do**:
  - Create `app/Providers/TwilioWhatsAppProvider.php`
  - Wrap TwilioWhatsAppService for messaging
  - Wrap TwilioAccountManager for account operations
  - Implement all WhatsAppProviderInterface methods
  - Delegate to existing services where possible

  **Must NOT do**:
  - Don't duplicate logic from existing services
  - Don't modify existing service files

  **Recommended Agent Profile**:
  - **Category**: `deep`
    - Reason: Need to integrate existing services into new provider pattern
  - **Skills**: []
  - **Skills Evaluated but Omitted**:

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Sequential
  - **Blocks**: Task 6 (Factory)
  - **Blocked By**: Task 1 (Interface)

  **References**:
  - `app/Services/TwilioWhatsAppService.php` - Wrapped messaging service
  - `app/Services/TwilioAccountManager.php` - Wrapped account service

  **Acceptance Criteria**:
  - [ ] Provider class created and implements interface
  - [ ] sendMessage() delegates to TwilioWhatsAppService
  - [ ] createSubAccount() delegates to TwilioAccountManager

  **QA Scenarios**:

  ```
  Scenario: Verify TwilioWhatsAppProvider implements interface
    Tool: Bash
    Preconditions: Task 1 complete
    Steps:
      1. ls app/Providers/TwilioWhatsAppProvider.php
      2. grep "implements WhatsAppProviderInterface" app/Providers/TwilioWhatsAppProvider.php
    Expected Result: File exists with implements clause
    Evidence: .sisyphus/evidence/task-2-provider-exists.txt
  ```

- [ ] 3. Update existing WhatsAppServiceInterface (DEPRECATION)

  **What to do**:
  - Mark existing WhatsAppServiceInterface as deprecated
  - Add @deprecated PHPDoc tag
  - Point to new WhatsAppProviderInterface

  **Must NOT do**:
  - Don't remove the interface completely (backward compatibility)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**:

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 1, 2

  **References**: Existing interface file

  **Acceptance Criteria**:
  - [ ] @deprecated tag added to existing interface
  - [ ] DocBlock explains to use WhatsAppProviderInterface

- [ ] 4. Create MetaWhatsAppProvider implementing interface

  **What to do**:
  - Create `app/Providers/MetaWhatsAppProvider.php`
  - Implement all WhatsAppProviderInterface methods
  - Use Meta WABA API endpoints
  - Use WhatsAppAccount model for credentials (meta_access_token, waba_id)

  **Must NOT do**:
  - Don't implement Twilio-specific methods (use Meta API equivalents)

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Need to implement Meta WABA API from scratch
  - **Skills**: []
  - **Skills Evaluated but Omitted**:

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 1

  **References**:
  - `app/Models/WhatsAppAccount.php` - Meta credentials storage
  - WABA API Documentation for method equivalents

  **Acceptance Criteria**:
  - [ ] MetaWhatsAppProvider implements interface
  - [ ] Uses Meta WABA API for operations
  - [ ] Retrieves credentials from WhatsAppAccount model

  **QA Scenarios**:

  ```
  Scenario: Verify MetaWhatsAppProvider exists
    Tool: Bash
    Steps:
      1. ls app/Providers/MetaWhatsAppProvider.php
    Expected Result: File exists
    Evidence: .sisyphus/evidence/task-4-meta-provider.txt
  ```

- [ ] 5. Implement WABA API methods in MetaWhatsAppProvider

  **What to do**:
  - Implement sendMessage using Meta Graph API
  - Implement getMessageStatus using message APIs
  - Implement template management using WABA API
  - Implement phone number management

  **Must NOT do**:
  - Don't hardcode credentials (must use WhatsAppAccount model)

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []
  - **Skills Evaluated but Omitted**:

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 4

- [ ] 6. Create WhatsAppProviderFactory

  **What to do**:
  - Create `app/Providers/WhatsAppProviderFactory.php`
  - Read provider from config('services.whatsapp.provider')
  - Return appropriate provider instance
  - Support 'twilio' and 'waba' providers

  **Must NOT do**:
  - Don't hardcode default to 'twilio' (use config default)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Tasks 2, 4

  **References**:
  - Existing factory patterns in Laravel app

  **Acceptance Criteria**:
  - [ ] Factory reads from config
  - [ ] Returns TwilioWhatsAppProvider for 'twilio'
  - [ ] Returns MetaWhatsAppProvider for 'waba'

- [ ] 7. Update services.php config with provider option

  **What to do**:
  - Add 'provider' => env('WHATSAPP_PROVIDER', 'twilio') to twilio config section
  - Document the option

  **Must NOT do**:
  - Don't change existing twilio settings

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 6

  **References**:
  - `config/services.php` - Current configuration

  **Acceptance Criteria**:
  - [ ] Provider option added to config
  - [ ] Default is 'twilio'

- [ ] 8. Update WhatsAppChannel to use factory

  **What to do**:
  - Update WhatsAppChannel::send() to use WhatsAppProviderFactory
  - Maintain all existing logic for determining gymId
  - Pass message to factory-created provider

  **Must NOT do**:
  - Don't change notification processing logic
  - Don't break existing notification flow

  **Recommended Agent Profile**:
  - **Category**: `deep`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Tasks 6, 7

  **References**:
  - `app/Notifications/WhatsAppChannel.php:26-119` - Current implementation

  **Acceptance Criteria**:
  - [ ] WhatsAppChannel uses factory
  - [ ] Existing behavior preserved

  **QA Scenarios**:

  ```
  Scenario: WhatsAppChannel uses factory
    Tool: Bash
    Preconditions: Task 8 complete
    Steps:
      1. grep "WhatsAppProviderFactory" app/Notifications/WhatsAppChannel.php
    Expected Result: Factory is used in the channel
    Evidence: .sisyphus/evidence/task-8-channel-uses-factory.txt
  ```

- [ ] 9. Verify backward compatibility with existing tests

  **What to do**:
  - Run existing WhatsApp-related tests
  - Ensure no regressions in Twilio functionality
  - Check WhatsAppChannel behavior

  **Must NOT do**:
  - Don't skip failing tests

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []
  - **Skills Evaluated but Omitted**:

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: All tasks

  **References**:
  - `tests/Feature/TwilioWhatsAppServiceTest.php`
  - `tests/Feature/WhatsAppTemplateServiceTest.php`

  **Acceptance Criteria**:
  - [ ] All existing tests pass
  - [ ] No breaking changes detected

  **QA Scenarios**:

  ```
  Scenario: Run existing Twilio tests
    Tool: Bash
    Preconditions: Task 9 ready
    Steps:
      1. php artisan test --compact tests/Feature/TwilioWhatsAppServiceTest.php
    Expected Result: All tests pass
    Evidence: .sisyphus/evidence/task-9-test-results.txt
  ```

---

## Execution Status
- [x] All tasks completed

> 4 review agents run in PARALLEL. ALL must APPROVE.

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists. For each "Must NOT Have": search for forbidden patterns.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `pint --dirty`, verify no style violations. Check provider implementations follow interface.
  Output: `Style [PASS/FAIL] | Interface [N/N implemented] | VERDICT`

- [ ] F3. **Integration Review** — `unspecified-high`
  Verify WhatsAppChannel uses factory, verify config option works.
  Output: `Factory Used [YES/NO] | Config Option [YES/NO] | VERDICT`

- [ ] F4. **Backward Compatibility Review** — `deep`
  Verify existing tests pass, verify no breaking changes to public APIs.
  Output: `Tests [N/N pass] | Breaking Changes [CLEAN/N] | VERDICT`

---

## Commit Strategy

- **1**: `feat(whatsapp): add WhatsAppProviderInterface` - Interface and deprecation
- **2**: `feat(whatsapp): add TwilioWhatsAppProvider` - Twilio provider implementation  
- **3**: `feat(whatsapp): add MetaWhatsAppProvider` - Meta WABA provider
- **4**: `feat(whatsapp): add WhatsAppProviderFactory` - Factory and config
- **5**: `refactor(whatsapp): update WhatsAppChannel to use factory` - Integration
- **6**: `test(whatsapp): verify backward compatibility` - Tests

---

## Success Criteria

### Verification Commands
```bash
vendor/bin/pint --dirty  # Should show no style violations
php artisan test --compact tests/Feature/TwilioWhatsAppServiceTest.php  # All pass
grep "WhatsAppProviderInterface" app/Interfaces/WhatsAppProviderInterface.php  # Exists
grep "WhatsAppProviderFactory" app/Notifications/WhatsAppChannel.php  # Uses factory
```

### Final Checklist
- [ ] All required methods defined in interface
- [ ] TwilioWhatsAppProvider wraps existing services
- [ ] MetaWhatsAppProvider implements all methods
- [ ] Factory selects provider based on config
- [ ] Config option added
- [ ] WhatsAppChannel uses factory
- [ ] All existing tests pass
