# TwilioWhatsAppProvider Composition Refactoring

## TL;DR

> **Quick Summary**: Refactor TwilioWhatsAppProvider (~1766 lines - God Class) into composition-based orchestrator delegating to 7 specialized services following Laravel best practices (arch-service-classes).

> **Deliverables**:
> - 7 specialized service classes (150-350 lines each)
> - 1 orchestrator provider (~250 lines)
> - Service provider for dependency injection

> **Estimated Effort**: Large (~1200 lines to refactor)
> **Parallel Execution**: YES - 3 phases with internal parallelization
> **Critical Path**: Phase 1 → Phase 2 → Phase 3

---

## Context

### Original Problem
`TwilioWhatsAppProvider.php` at `app/Providers/TwilioWhatsAppProvider.php` is a **1766-line God Class** that:
- Implements 7 separate interfaces directly
- Has no dependency injection (uses `app()` inline)
- Contains 25+ public methods + 15+ private helpers
- Code duplication (multiple `makeSubaccountClient*` methods)
- Impossible to unit test

### Interface Reference
`WhatsAppProviderInterface` at `app/Interfaces/WhatsAppProviderInterface.php` (476 lines) defines 40 methods:

| Group | Methods | Current Implementation Location |
|-------|--------|---------------------|
| Messaging | sendMessage, sendTemplateMessage, sendTestMessage, sendBroadcast, getMessageStatus | ~320 lines |
| Account | createSubAccount, getAccountStatus, cleanupAccount, getWabaMode, requiresEmbeddedSignup | ~350 lines |
| Phone Numbers | getAvailableNumbers, purchaseNumber, registerOwnNumber, verifyOwnNumber, resendOwnNumberOtp | ~250 lines |
| Templates | createTemplate, submitTemplate, syncTemplateStatus, getTemplates | ~50 lines |
| Credits | getCreditBalance, getMonthlyUsage | ~80 lines |
| WABA Setup | createWaba, createSharedWaba, createIndividualWaba, connectIndividualWaba, connectManualWaba | ~350 lines |
| WABA Verify | verifyWabaConnection | ~10 lines |
| Embedded Signup | startEmbeddedSignup, getEmbeddedSignupLoginUrl, handleEmbeddedSignupCallback | ~100 lines |
| Test Messages | sendTestWhatsAppMessage | ~80 lines |
| Client Mgmt (private) | getClient, makeSubaccountClient, getMasterClient, isSystemTenant | ~100 lines |

### Metis Review
**Identified Gaps** (to address in plan):
- Must maintain backward compatibility with WhatsAppProviderFactory
- Need service provider for proper DI container registration
- Consider interface segregation (messaging vs account vs phone)
- Test coverage requirements

---

## Work Objectives

### Core Objective
Transform TwilioWhatsAppProvider from monolithic to composition-based architecture:

```
BEFORE (Monolithic):
┌──────────────────────────────────────────┐
│  TwilioWhatsAppProvider                 │
│  ─────────────────────                │
│  Implements 7 interfaces           │
│  ~1766 lines of code              │
│  All logic inline                │
└──────────────────────────────────────────┘

AFTER (Composition):
┌──────────────────────────────────────────┐
│  TwilioWhatsAppProvider (Orchestrator)     │
│  ─────────────────────                │
│  ~250 lines                       │
│  Delegates to services              │
└──────────────────────────────────────────┘
         │
    ┌─────┴──────┬───────┬────────┬─��────┐
    │            │       │        │       │
 WhatsApp    WhatsApp Account Phone  Credit/Template
 Messaging  Service   Service  Service Services
  Service   (~350)   (~250)   (~80)
  (~350)
```

### Concrete Deliverables
- [ ] `app/Services/WhatsApp/WhatsAppClientFactory.php` - Twilio client creation
- [ ] `app/Services/WhatsApp/WhatsAppMessagingService.php` - sendMessage/broadcast
- [ ] `app/Services/WhatsApp/WhatsAppAccountService.php` - subaccount/WABA management
- [ ] `app/Services/WhatsApp/WhatsAppPhoneService.php` - phone number ops
- [ ] `app/Services/WhatsApp/WhatsAppCreditService.php` - credit management
- [ ] `app/Services/WhatsApp/WhatsAppTemplateService.php` - template proxy
- [ ] `app/Services/WhatsApp/WhatsAppTestMessageService.php` - test messages
- [ ] `app/Providers/TwilioWhatsAppProvider.php` - refactored orchestrator
- [ ] `app/Providers/WhatsAppServiceProvider.php` - DI registration

### Definition of Done
- [ ] All existing WhatsAppProviderInterface methods work identically
- [ ] No breaking changes to WhatsAppProviderFactory usage
- [ ] php -l passes on all new files
- [ ] vendor/bin/pint passes on all files
- [ ] Container can resolve individual services

### Must Have
- Maintain all method signatures exactly
- Preserve all return value structures
- Keep error message formatting identical
- Preserve logging behavior

### Must NOT Have (Guardrails)
- NO breaking API changes
- NO new database migrations
- NO changes to model relationships
- NO modified WhatsAppProviderInterface

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: Existing tests should pass
- **Framework**: PHPUnit (from laravel-best-practices)

### QA Policy
Every service MUST include agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/`.


---

## Execution Strategy

### Phase 1: Foundation (Client Factory + Service Base)
**Wave 1 - Start Immediately** (2 tasks, can parallelize):
├── Task 1: Create WhatsAppClientFactory (~100 lines)
│   - Extracted from TwilioWhatsAppProvider private methods
│   - getClient, makeSubaccountClient, getMasterClient, isSystemTenant
│   └── Task 2: Create abstract WhatsAppService base (~50 lines)
│       - Shared constructor, gymId, account, phoneNumber
│
**Wave 1 Verification**:
- [ ] WhatsAppClientFactory instantiates without errors
- [ ] Base service gets gym context

### Phase 2: Core Services (Messaging + Account)
**Wave 2 - After Wave 1** (4 tasks, can parallelize):
├── Task 3: WhatsAppMessagingService (~350 lines)
│   - Extracted: sendMessage, sendTemplateMessage, sendBroadcast, getMessageStatus
│   - Uses: WhatsAppClientFactory, WhatsAppCreditService (injected)
├── Task 4: WhatsAppAccountService (~350 lines)
│   - Extracted: createSubAccount, getAccountStatus, cleanupAccount
│   - Extracted: createWaba, connectIndividualWaba, connectManualWaba
│   - Uses: WhatsAppClientFactory, WhatsAppPhoneService (injected)
├── Task 5: WhatsAppPhoneService (~250 lines)
│   - Extracted: getAvailableNumbers, purchaseNumber, registerOwnNumber
│   - Extracted: verifyOwnNumber, resendOwnNumberOtp
│   - Uses: WhatsAppClientFactory
└─── Task 6: WhatsAppCreditService (~80 lines)
    - Extracted: getCreditBalance, getMonthlyUsage
    - Helpers: checkAndDeductCredits, logUsage

**Wave 2 Verification**:
- [ ] Messaging service sends text/template messages
- [ ] Account service creates/cleans up subaccounts
- [ ] Phone service searches/purchases numbers

### Phase 3: Remaining Services + Orchestrator
**Wave 3 - After Wave 2** (3 tasks):
├── Task 7: WhatsAppTemplateService (~50 lines)
│   - Delegates to WhatsAppTemplateService (app())
├── Task 8: WhatsAppTestMessageService (~80 lines)
│   - sendTestWhatsAppMessage method
│   └── Task 9: Refactor TwilioWhatsAppProvider (~200 lines)
        - Inject all services
        - Delegate to service methods
        - Keep interface implementation

**Wave 3 Verification**:
- [ ] Full sendMessage flow works (delegates to messaging)
- [ ] Test message works (delegates to test service)

### Phase 4: Integration + DI Registration
**Wave 4 - After Wave 3** (1 task):
└── Task 10: WhatsAppServiceProvider (~80 lines)
    - Register services in container
    - Use singleton for client factory
    - Scoped resolution per gym

**Wave 4 Verification**:
- [ ] app(WhatsAppMessagingService::class) resolves
- [ ] Services get correct gym context

### Dependency Matrix
```
Task 1 (ClientFactory)        → - - Task 3, Task 4, Task 5
Task 2 (Service Base)    → Task 1 - Task 3, Task 4
Task 3 (Messaging)     → Task 1 Task 2 Task 6, Task 9
Task 4 (Account)       → Task 1 Task 2 -, Task 5, Task 9
Task 5 (Phone)         → Task 1 Task 2 -, Task 9
Task 6 (Credit)       → - - - Task 3, Task 9
Task 7 (Template)    → - - - Task 9
Task 8 (TestMsg)     → Task 1 Task 2 -, Task 9
Task 9 (Provider)    → Task 3 Task 4 Task 5 Task 6 Task 7 Task 8 - -
Task 10 (Provider)    → Task 1 Task 2 Task 3 Task 4 Task 5 Task 6 Task 7 Task 8 Task 9 -
```

---

## TODOs

- [ ] 1. Create WhatsAppClientFactory

  **What to do**: Extract client creation logic from TwilioWhatsAppProvider
  - isSystemTenant(), getClient(), makeSubaccountClient(), getMasterClient()
  - makeSubaccountClientForGym(), makeSubaccountClientForAccount()
  - Use constructor promotion for config values
  - Create interface for testability
  
  **Must NOT do**: 
  - Don't implement full WhatsAppProviderInterface - it's for clients
  
  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Straight extraction, minimal logic changes
  - **Skills**: []
  
  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Task 2)
  - **Blocks**: Tasks 3, 4, 5, 8
  - **Blocked By**: None (can start immediately)

  **References**:
  - `app/Providers/TwilioWhatsAppProvider.php:92-145` - Current client methods
  
  **Acceptance Criteria**:
  - [ ] Client factory can create master client
  - [ ] Client factory can create gym-specific client

  **QA Scenarios**:
  ```
  Scenario: Create master client
    Tool: Bash (tinker)
    Preconditions: Config has twilio credentials
    Steps:
      1. $factory = app(\App\Services\WhatsApp\WhatsAppClientFactory::class);
      2. $client = $factory->getMasterClient();
      3. Assert instanceof \Twilio\Rest\Client
    Expected Result: Client instance returned
    Evidence: .sisyphus/evidence/task-1-client.json
  ```

- [ ] 2. Create WhatsAppService base class

  **What to do**: Abstract base for all services
  - Protected: gymId, whatsAppAccount, whatsAppPhoneNumber
  - Constructor accepting gymId and resolving models
  - Helper methods for config access
  
  **Must NOT do**: 
  - Don't implement any interface methods
  
  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple structural class
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Task 1)
  - **Blocks**: Tasks 3-9
  - **Blocked By**: Task 1

- [ ] 3. Create WhatsAppMessagingService

  **What to do**: Extract messaging logic
  - sendMessage(), sendTextMessage(), sendMessageAsTemplate()
  - sendTemplateMessage(), prepareVariables(), logUsage()
  - sendBroadcast(), checkAndDeductCredits()
  - Inject: WhatsAppClientFactory, WhatsAppCreditService
  - Uses traits for shared behavior if needed
  
  **Must NOT do**:
  - Don't change return value structure
  - Don't change error message format
  
  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Most complex service (~350 lines)
  - **Skills**: ["laravel-specialist"]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4,5,6)
  - **Blocks**: Task 9 (Provider needs this)
  - **Blocked By**: Task 1, Task 2

  **References**:
  - `app/Providers/TwilioWhatsAppProvider.php:148-507` - Current messaging methods
  - `app/Interfaces/MessagingInterface.php` - Interface to implement
  - `app/Services/WhatsAppVariableResolver.php` - Variable preparation

  **Acceptance Criteria**:
  - [ ] sendMessage returns same structure as before
  - [ ] sendTemplateMessage works same as before
  - [ ] sendBroadcast processes all recipients

  **QA Scenarios**:
  ```
  Scenario: Send text message delegates to service
    Tool: Bash (tinker)
    Preconditions: Gym with phone number, credit balance
    Steps:
      1. $service = app(\App\Services\WhatsApp\WhatsAppMessagingService::class);
      2. $result = $service->sendMessage('+1234567890', 'Test message');
    Expected Result: Array with success key
    Failure Indicators: Exception thrown
    Evidence: .sisyphus/evidence/task-3-send.json
  ```

- [ ] 4. Create WhatsAppAccountService

  **What to do**: Extract account/WABA management
  - createSubAccount(), getAccountStatus(), cleanupAccount()
  - getWabaMode(), requiresEmbeddedSignup()
  - createWaba(), createSharedWaba(), createIndividualWaba()
  - connectIndividualWaba(), connectManualWaba()
  - releasePhoneNumbers(), deleteMessagingService(), closeSubaccount()
  - Inject: WhatsAppClientFactory, WhatsAppPhoneService
  
  **Must NOT do**:
  - Don't change WhatsAppAccount model updates
  
  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Large service (~350 lines)
  - **Skills**: ["laravel-specialist"]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 3,5,6)
  - **Blocks**: Task 9
  - **Blocked By**: Task 1, Task 2

  **References**:
  - `app/Providers/TwilioWhatsAppProvider.php:588-1108` - Current account methods

- [ ] 5. Create WhatsAppPhoneService

  **What to do**: Extract phone number management
  - getAvailableNumbers(), purchaseNumber()
  - registerOwnNumber(), verifyOwnNumber(), resendOwnNumberOtp()
  - registerWhatsAppSender(), getAccountOrFail()
  - Inject: WhatsAppClientFactory
  
  **Must NOT do**:
  - Don't change phone number record structure
  
  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Medium service (~250 lines)
  - **Skills**: ["laravel-specialist"]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 3,4,6)
  - **Blocks**: Task 9
  - **Blocked By**: Task 1, Task 2

  **References**:
  - `app/Providers/TwilioWhatsAppProvider.php:961-1353` - Current phone methods
  - `app/Models/WhatsAppPhoneNumber.php` - Phone model

- [ ] 6. Create WhatsAppCreditService

  **What to do**: Extract credit management
  - getCreditBalance(), getMonthlyUsage()
  - checkAndDeductCredits() (used by messaging)
  - logUsage() (used by messaging)
  
  **Must NOT do**:
  - Don't change credit calculation logic
  
  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple service (~80 lines)
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 3,4,5)
  - **Blocks**: Task 3 (messaging uses credit service)
  - **Blocked By**: Task 2

- [ ] 7. Create WhatsAppTemplateService

  **What to do**: Extract template delegation
  - createTemplate() → app(WhatsAppTemplateService::class)
  - submitTemplate() → app(WhatsAppTemplateService::class)
  - syncTemplateStatus() → app(WhatsAppTemplateService::class)
  - getTemplates() → WhatsAppTemplateManager
  
  **Must NOT do**:
  - Don't duplicate template logic - just delegate
  
  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Thin proxy (~50 lines)
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Task 8)
  - **Blocks**: Task 9
  - **Blocked By**: Task 2

- [ ] 8. Create WhatsAppTestMessageService

  **What to do**: Extract test message sending
  - sendTestWhatsAppMessage()
  - Uses: WhatsAppMessagingService, WhatsAppAccountService
  
  **Must NOT do**:
  - Don't change test message format
  
  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple service (~80 lines)
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Task 7)
  - **Blocks**: Task 9
  - **Blocked By**: Task 1, Task 2, Task 3

- [ ] 9. Refactor TwilioWhatsAppProvider (Orchestrator)

  **What to do**: Transform to orchestrator
  - Constructor injects all 6 services
  - Each method delegates to appropriate service
  - Implements WhatsAppProviderInterface
  - ~200-250 lines
  
  **Must NOT do**:
  - Don't contain business logic
  - Don't create clients directly
  
  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Integration work (~200 lines)
  - **Skills**: ["laravel-specialist"]

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Sequential after**: Tasks 1-8
  - **Blocks**: Task 10
  - **Blocked By**: Tasks 1-8 complete

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php` - Full interface
  - `app/Providers/WhatsAppProviderFactory.php` - Current factory

  **Acceptance Criteria**:
  - [ ] Implements all 40 interface methods
  - [ ] Delegates to services
  - [ ] Same behavior as before

  **QA Scenarios**:
  ```
  Scenario: Full provider works through factory
    Tool: Bash (tinker)
    Preconditions: Gym with existing WhatsAppAccount
    Steps:
      1. $provider = \App\Providers\WhatsAppProviderFactory::create($gymId);
      2. $result = $provider->sendMessage('+1234567890', 'Test');
      3. Assert $result['success'] === true || $result['success'] === false
    Expected Result: Array response
    Evidence: .sisyphus/evidence/task-9-factory.json
  ```

- [ ] 10. Create WhatsAppServiceProvider

  **What to do**: Register services in container
  - Register WhatsAppClientFactory as singleton
  - Register each service as scoped/cached
  - Use method injection in provider
  
  **Must NOT do**:
  - Don't break existing resolution
  
  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Service provider pattern (~80 lines)
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Sequential after**: All services complete
  - **Blocked By**: Task 9

  **References**:
  - `app/Providers/AppServiceProvider.php` - Existing provider pattern

  **Acceptance Criteria**:
  - [ ] resolve(WhatsAppMessagingService::class) works
  - [ ] resolve(WhatsAppAccountService::class) works

---

## Final Verification Wave

- [ ] F1. **WhatsAppProviderFactory Test** — All methods delegate correctly
  Read provider methods, verify they call service methods, not contain logic.
  Output: `Methods [40/40 delegating] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Service Test** — Each service can be resolved from container
  Attempt resolution via app(), verify DI works.
  Output: `Services [7/7 resolvable] | VERDICT: APPROVE/REJECT`

- [ ] F3. **Integration Test** — sendMessage through full stack
  Create provider, send message, verify delegation chain works.
  Output: `Delegation chain works | VERDICT: APPROVE/REJECT`

---

## Commit Strategy

- **1**: `refactor(whatsapp): extract WhatsAppClientFactory` - app/Services/WhatsApp/WhatsAppClientFactory.php
- **2**: `refactor(whatsapp): extract messaging service` - app/Services/WhatsApp/WhatsAppMessagingService.php
- **3**: `refactor(whatsapp): extract account service` - app/Services/WhatsApp/WhatsAppAccountService.php
- **4**: `refactor(whatsapp): extract phone service` - app/Services/WhatsApp/WhatsAppPhoneService.php
- **5**: `refactor(whatsapp): extract credit service` - app/Services/WhatsApp/WhatsAppCreditService.php
- **6**: `refactor(whatsapp): extract template/test services` - app/Services/WhatsApp/WhatsAppTemplateService.php, WhatsAppTestMessageService.php
- **7**: `refactor(whatsapp): compose provider orchestrator` - app/Providers/TwilioWhatsAppProvider.php
- **8**: `refactor(whatsapp): add service provider` - app/Providers/WhatsAppServiceProvider.php

---

## Success Criteria

### Verification Commands
```bash
php -l app/Services/WhatsApp/WhatsAppClientFactory.php  # No syntax errors
php -l app/Services/WhatsApp/WhatsAppMessagingService.php  # No syntax errors
php -l app/Services/WhatsApp/WhatsAppAccountService.php  # No syntax errors
php -l app/Services/WhatsApp/WhatsAppPhoneService.php  # No syntax errors
php -l app/Services/WhatsApp/WhatsAppCreditService.php  # No syntax errors
php -l app/Services/WhatsApp/WhatsAppTemplateService.php  # No syntax errors
php -l app/Services/WhatsApp/WhatsAppTestMessageService.php  # No syntax errors
php -l app/Providers/TwilioWhatsAppProvider.php  # No syntax errors
php -l app/Providers/WhatsAppServiceProvider.php  # No syntax errors
vendor/bin/pint --dirty  # All formatted
```

### Final Checklist
- [ ] All 40 interface methods implemented
- [ ] All services resolvable from container
- [ ] sendMessage flow works (messaging → credit → logging)
- [ ] account cleanup flow works (account → phone → service → subaccount)
- [ ] No new database migrations
- [ ] No model changes
- [ ] Backward compatible with existing usage