# WhatsAppProviderInterface Refactor - Interface Segregation

## TL;DR

> **Quick Summary**: Split the monolithic `WhatsAppProviderInterface` (25+ methods) into focused, single-responsibility interfaces. Meta provider currently implements 7+ methods that return "not supported" errors, and Twilio implements 1 that doesn't apply. This refactor will eliminate dead code, improve type safety, and make the architecture clearer.

> **Deliverables**:
> - 8 new focused interfaces in `app/Interfaces/`
> - Updated `TwilioWhatsAppProvider` implementing only relevant interfaces
> - Updated `MetaWhatsAppProvider` implementing only relevant interfaces
> - Updated `WhatsAppProviderFactory` return type
> - Updated type hints in `WhatsAppSetup.php`

> **Estimated Effort**: Short | **Parallel Execution**: YES | **Critical Path**: Create interfaces → Update providers → Update factory → Verify

---

## Context

### Original Request
User identified that `WhatsAppProviderInterface` contains many methods that return "not supported" errors in their respective providers:
- **MetaWhatsAppProvider**: 7+ methods return "not supported" (createSubAccount, cleanupAccount, getAvailableNumbers, purchaseNumber, verifyOwnNumber, resendOwnNumberOtp, createWaba)
- **TwilioWhatsAppProvider**: 1 method returns "not supported" (verifyWabaConnection)

### Interface Segregation Principle
> Clients should not be forced to depend upon interfaces they do not use.

The ISP states that when a class has to implement methods it doesn't need, there should be smaller, focused interfaces instead of one fat interface.

### Research Findings
- **Files referencing interface**: 6 files (WhatsAppProviderFactory, both providers, deprecated interface, WhatsAppSetup)
- **Factory creates providers by type**: Uses `WhatsAppProviderInterface` as return type
- **WhatsAppSetup uses provider via `getProvider()` method**: Returns `?WhatsAppProviderInterface`

---

## Work Objectives

### Core Objective
Refactor the `WhatsAppProviderInterface` into focused interfaces using the Interface Segregation Principle, eliminating placeholder "not supported" methods from both providers.

### Concrete Deliverables
- 8 new focused interfaces in `app/Interfaces/`
- Clean implementations in both providers (no more error-returning stubs)
- Backward-compatible base marker interface

### Definition of Done
- [ ] `php -l app/Interfaces/*.php` returns no syntax errors
- [ ] `php -l app/Providers/TwilioWhatsAppProvider.php` returns no syntax errors
- [ ] `php -l app/Providers/MetaWhatsAppProvider.php` returns no syntax errors
- [ ] `composer run dev` starts without errors
- [ ] Provider factory still creates correct providers

### Must Have
- Both providers implement only interfaces relevant to their functionality
- No "not supported" stub methods remain
- Factory continues to work with type hints
- Backward compatibility preserved (existing code doesn't break)

### Must NOT Have
- No new business logic (only refactoring)
- No method signature changes (only interface restructuring)
- No breaking changes to existing code that uses providers

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES
- **Automated tests**: NO (interface refactoring - manual verification)
- **QA Policy**: Agent-executed verification via PHP syntax check + artisan test

### QA Policy
Every task includes agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/`.

**Verification Commands**:
```bash
php -l app/Interfaces/MessagingInterface.php  # Syntax check
php -l app/Providers/TwilioWhatsAppProvider.php # Syntax check
php -l app/Providers/MetaWhatsAppProvider.php  # Syntax check
composer run dev                                 # App startup
```

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation - Create all interfaces):
├── Task 1: Create MessagingInterface
├── Task 2: Create AccountManagementInterface
├── Task 3: Create PhoneNumberManagementInterface
├── Task 4: Create TemplateManagementInterface
├── Task 5: Create CreditManagementInterface
├── Task 6: Create WabaSetupInterface
├── Task 7: Create EmbeddedSignupInterface
└── Task 8: Create TestMessagingInterface

Wave 2 (Update Twilio Provider):
├── Task 9: Update TwilioWhatsAppProvider to implement specific interfaces

Wave 3 (Update Meta Provider):
├── Task 10: Update MetaWhatsAppProvider to implement specific interfaces

Wave 4 (Update Factory & Consumers):
├── Task 11: Update WhatsAppProviderFactory return type
└── Task 12: Update WhatsAppSetup.php type hints

Wave FINAL (Verification):
├── Task F1: PHP syntax verification all files
└── Task F2: App startup verification
```

### Dependency Matrix
- **1-8**: - - 9, 10 (interfaces must exist before providers implement them)
- **9, 10**: 1-8 - 11, 12 (providers implement interfaces first)
- **11, 12**: 9, 10 - F1, F2 (factory and consumers depend on providers)
- **F1, F2**: 11, 12 - COMPLETE

### Agent Dispatch Summary
- **1, 2, 3, 4**: `writing` - Creating interface files (parallel)
- **5, 6, 7, 8**: `writing` - Creating interface files (parallel)
- **9**: `quick` - Updating provider to implement interfaces
- **10**: `quick` - Updating provider to implement interfaces
- **11**: `quick` - Updating factory return type
- **12**: `quick` - Updating type hints

---

## TODOs

---

- [ ] 1. Create MessagingInterface

  **What to do**:
  - Create `app/Interfaces/MessagingInterface.php`
  - Define core messaging methods:
    - `sendMessage(string $to, string $message): array`
    - `sendTemplateMessage(string $to, string $templateName, array $variables = []): array`
    - `sendTestMessage(string $to, string $message): array`
    - `sendBroadcast(int $broadcastId): array`
    - `getMessageStatus(string $messageId): array` (may not be supported by all)
  - Include PHPDoc with array shape return types

  **Must NOT do**:
  - Don't include account/phone/template management methods
  - Don't add methods that aren't about messaging

  **Recommended Agent Profile**:
  - **Category**: `writing`
  - **Skills**: []
  - **Reason**: Simple interface file creation, no domain complexity

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2-8)
  - **Blocks**: Tasks 9, 10 (providers implement this interface)
  - **Blocked By**: None (can start immediately)

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:39-94` - Original messaging methods
  - `app/Providers/MetaWhatsAppProvider.php:119-191` - Meta sendMessage implementation
  - `app/Providers/TwilioWhatsAppProvider.php:41-82` - Twilio sendMessage implementation

  **Acceptance Criteria**:
  - [ ] File created: `app/Interfaces/MessagingInterface.php`
  - [ ] All 5 methods defined with correct signatures
  - [ ] PHPDoc comments with array shape return types
  - [ ] `php -l app/Interfaces/MessagingInterface.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: Interface file syntax validation
    Tool: Bash
    Preconditions: Clean workspace
    Steps:
      1. Run: php -l app/Interfaces/MessagingInterface.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-1-syntax-check.txt
  ```

  **Commit**: NO

---

- [ ] 2. Create AccountManagementInterface

  **What to do**:
  - Create `app/Interfaces/AccountManagementInterface.php`
  - Define Twilio-only account methods:
    - `createSubAccount(int $gymId, string $gymName, string $email): array`
    - `getAccountStatus(int $gymId): array`
    - `cleanupAccount(int $gymId): array`
    - `getWabaMode(int $gymId): string`
    - `requiresEmbeddedSignup(int $gymId): bool`
  - Include PHPDoc with array shape return types

  **Must NOT do**:
  - Don't include messaging methods
  - Don't include phone/template management methods

  **Recommended Agent Profile**:
  - **Category**: `writing`
  - **Skills**: []
  - **Reason**: Simple interface file creation

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3-8)
  - **Blocks**: Task 9 (Twilio provider implements)
  - **Blocked By**: None

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:99-156` - Original account methods
  - `app/Providers/TwilioWhatsAppProvider.php:91-126` - Twilio account implementation

  **Acceptance Criteria**:
  - [ ] File created: `app/Interfaces/AccountManagementInterface.php`
  - [ ] All 5 methods defined with correct signatures
  - [ ] `php -l app/Interfaces/AccountManagementInterface.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: Interface file syntax validation
    Tool: Bash
    Preconditions: Clean workspace
    Steps:
      1. Run: php -l app/Interfaces/AccountManagementInterface.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-2-syntax-check.txt
  ```

  **Commit**: NO

---

- [ ] 3. Create PhoneNumberManagementInterface

  **What to do**:
  - Create `app/Interfaces/PhoneNumberManagementInterface.php`
  - Define phone number methods:
    - `getAvailableNumbers(string $country, string $type = 'local', int $limit = 5): array`
    - `purchaseNumber(int $gymId, string $phoneNumber): array`
    - `registerOwnNumber(int $gymId, string $phoneNumber): array`
    - `verifyOwnNumber(int $gymId, string $senderSid, string $otpCode): array`
    - `resendOwnNumberOtp(int $gymId, string $senderSid, string $channel = 'sms'): array`

  **Must NOT do**:
  - Don't include messaging or account methods

  **Recommended Agent Profile**:
  - **Category**: `writing`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1-2, 4-8)
  - **Blocks**: Task 9
  - **Blocked By**: None

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:162-232` - Original phone methods
  - `app/Providers/TwilioWhatsAppProvider.php:135-170` - Twilio phone implementation

  **Acceptance Criteria**:
  - [ ] File created: `app/Interfaces/PhoneNumberManagementInterface.php`
  - [ ] All 5 methods defined with correct signatures
  - [ ] `php -l app/Interfaces/PhoneNumberManagementInterface.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: Interface file syntax validation
    Tool: Bash
    Preconditions: Clean workspace
    Steps:
      1. Run: php -l app/Interfaces/PhoneNumberManagementInterface.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-3-syntax-check.txt
  ```

  **Commit**: NO

---

- [ ] 4. Create TemplateManagementInterface

  **What to do**:
  - Create `app/Interfaces/TemplateManagementInterface.php`
  - Define template methods:
    - `createTemplate(int $gymId, array $data): WhatsAppTemplate`
    - `submitTemplate(WhatsAppTemplate $template): array`
    - `syncTemplateStatus(WhatsAppTemplate $template): void`
    - `getTemplates(int $gymId): array`

  **Must NOT do**:
  - Don't include messaging or account methods

  **Recommended Agent Profile**:
  - **Category**: `writing`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: Tasks 9, 10
  - **Blocked By**: None

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:237-276` - Original template methods
  - Both providers implement these

  **Acceptance Criteria**:
  - [ ] File created: `app/Interfaces/TemplateManagementInterface.php`
  - [ ] All 4 methods defined with correct signatures
  - [ ] `php -l app/Interfaces/TemplateManagementInterface.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: Interface file syntax validation
    Tool: Bash
    Preconditions: Clean workspace
    Steps:
      1. Run: php -l app/Interfaces/TemplateManagementInterface.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-4-syntax-check.txt
  ```

  **Commit**: NO

---

- [ ] 5. Create CreditManagementInterface

  **What to do**:
  - Create `app/Interfaces/CreditManagementInterface.php`
  - Define credit methods:
    - `getCreditBalance(): array`
    - `getMonthlyUsage(int $year, int $month): array`

  **Must NOT do**:
  - Don't include other management methods

  **Recommended Agent Profile**:
  - **Category**: `writing`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: Tasks 9, 10
  - **Blocked By**: None

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:282-308` - Original credit methods
  - Both providers implement these

  **Acceptance Criteria**:
  - [ ] File created: `app/Interfaces/CreditManagementInterface.php`
  - [ ] All 2 methods defined with correct signatures
  - [ ] `php -l app/Interfaces/CreditManagementInterface.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: Interface file syntax validation
    Tool: Bash
    Preconditions: Clean workspace
    Steps:
      1. Run: php -l app/Interfaces/CreditManagementInterface.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-5-syntax-check.txt
  ```

  **Commit**: NO

---

- [ ] 6. Create WabaSetupInterface

  **What to do**:
  - Create `app/Interfaces/WabaSetupInterface.php`
  - Define Meta-specific WABA setup methods:
    - `createWaba(int $gymId, array $businessProfile): array`
    - `connectIndividualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken = null): array`
    - `connectManualWaba(...): array`
    - `verifyWabaConnection(string $wabaId, string $phoneNumberId, ?string $accessToken = null): array`

  **Must NOT do**:
  - Don't include generic messaging methods
  - Don't include Twilio-only methods

  **Recommended Agent Profile**:
  - **Category**: `writing`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: Task 10
  - **Blocked By**: None

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:314-414` - Original WABA methods
  - `app/Providers/MetaWhatsAppProvider.php:685-830` - Meta WABA implementation

  **Acceptance Criteria**:
  - [ ] File created: `app/Interfaces/WabaSetupInterface.php`
  - [ ] All 4 methods defined with correct signatures
  - [ ] `php -l app/Interfaces/WabaSetupInterface.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: Interface file syntax validation
    Tool: Bash
    Preconditions: Clean workspace
    Steps:
      1. Run: php -l app/Interfaces/WabaSetupInterface.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-6-syntax-check.txt
  ```

  **Commit**: NO

---

- [ ] 7. Create EmbeddedSignupInterface

  **What to do**:
  - Create `app/Interfaces/EmbeddedSignupInterface.php`
  - Define Meta embedded signup methods:
    - `startEmbeddedSignup(int $gymId): string`
    - `getEmbeddedSignupLoginUrl(int $gymId): string`
    - `handleEmbeddedSignupCallback(string $code, string $state): array`

  **Must NOT do**:
  - Don't include generic messaging methods
  - Don't include Twilio-only methods

  **Recommended Agent Profile**:
  - **Category**: `writing`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: Task 10
  - **Blocked By**: None

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:420-454` - Original embedded signup methods
  - `app/Providers/MetaWhatsAppProvider.php:1121-1215` - Meta embedded signup implementation

  **Acceptance Criteria**:
  - [ ] File created: `app/Interfaces/EmbeddedSignupInterface.php`
  - [ ] All 3 methods defined with correct signatures
  - [ ] `php -l app/Interfaces/EmbeddedSignupInterface.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: Interface file syntax validation
    Tool: Bash
    Preconditions: Clean workspace
    Steps:
      1. Run: php -l app/Interfaces/EmbeddedSignupInterface.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-7-syntax-check.txt
  ```

  **Commit**: NO

---

- [ ] 8. Create TestMessagingInterface

  **What to do**:
  - Create `app/Interfaces/TestMessagingInterface.php`
  - Define test message method:
    - `sendTestWhatsAppMessage(int $gymId, ?string $to = null): array`

  **Must NOT do**:
  - Don't include other messaging methods (already in MessagingInterface)

  **Recommended Agent Profile**:
  - **Category**: `writing`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocks**: Tasks 9, 10
  - **Blocked By**: None

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:460-475` - Original test method
  - Both providers implement this

  **Acceptance Criteria**:
  - [ ] File created: `app/Interfaces/TestMessagingInterface.php`
  - [ ] Method defined with correct signature
  - [ ] `php -l app/Interfaces/TestMessagingInterface.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: Interface file syntax validation
    Tool: Bash
    Preconditions: Clean workspace
    Steps:
      1. Run: php -l app/Interfaces/TestMessagingInterface.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-8-syntax-check.txt
  ```

  **Commit**: NO

---

- [ ] 9. Update TwilioWhatsAppProvider

  **What to do**:
  - Update `app/Providers/TwilioWhatsAppProvider.php`
  - Change class declaration to implement specific interfaces instead of single monolithic interface:
    ```php
    class TwilioWhatsAppProvider implements 
        MessagingInterface,
        AccountManagementInterface,
        PhoneNumberManagementInterface,
        TemplateManagementInterface,
        CreditManagementInterface,
        WabaSetupInterface,
        TestMessagingInterface
    ```
  - Remove all stub "not supported" methods (verifyWabaConnection returns error)
  - Keep all working Twilio-specific implementations
  - Add use statements for all new interfaces

  **Must NOT do**:
  - Don't remove working implementations
  - Don't change method signatures
  - Don't modify business logic

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Reason**: Updating class declaration and removing unused methods

  **Parallelization**:
  - **Can Run In Parallel**: NO (must follow interfaces creation)
  - **Parallel Group**: Wave 2 (alone)
  - **Blocks**: Tasks 11, 12
  - **Blocked By**: Tasks 1-8

  **References**:
  - `app/Providers/TwilioWhatsAppProvider.php` - Current implementation
  - New interfaces from Tasks 1-8

  **Acceptance Criteria**:
  - [ ] Class implements correct interfaces (Messaging, Account, Phone, Template, Credit, WabaSetup, Test)
  - [ ] No verifyWabaConnection method (removed - not Twilio's concern)
  - [ ] All other methods remain unchanged
  - [ ] `php -l app/Providers/TwilioWhatsAppProvider.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: Twilio provider PHP syntax validation
    Tool: Bash
    Preconditions: Interfaces created, provider updated
    Steps:
      1. Run: php -l app/Providers/TwilioWhatsAppProvider.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-9-syntax-check.txt
  ```

  **Commit**: NO

---

- [ ] 10. Update MetaWhatsAppProvider

  **What to do**:
  - Update `app/Providers/MetaWhatsAppProvider.php`
  - Change class declaration to implement specific interfaces:
    ```php
    class MetaWhatsAppProvider implements
        MessagingInterface,
        TemplateManagementInterface,
        CreditManagementInterface,
        WabaSetupInterface,
        EmbeddedSignupInterface,
        TestMessagingInterface
    ```
  - **REMOVE** these stub methods that return "not supported":
    - `createSubAccount()` - Remove (Meta doesn't support)
    - `cleanupAccount()` - Remove (Meta doesn't support)
    - `getAvailableNumbers()` - Remove (Meta doesn't support)
    - `purchaseNumber()` - Remove (Meta doesn't support)
    - `verifyOwnNumber()` - Remove (Meta doesn't support)
    - `resendOwnNumberOtp()` - Remove (Meta doesn't support)
    - `createWaba()` - Remove (Meta doesn't support - WABA creation is external)
  - Keep `registerOwnNumber()` - Meta actually supports this
  - Add use statements for all new interfaces

  **Must NOT do**:
  - Don't remove working implementations
  - Don't modify `registerOwnNumber()` - Meta supports it
  - Don't change business logic in other methods

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Reason**: Updating class declaration and removing unused methods

  **Parallelization**:
  - **Can Run In Parallel**: NO (must follow interfaces creation)
  - **Parallel Group**: Wave 3 (alone)
  - **Blocks**: Tasks 11, 12
  - **Blocked By**: Tasks 1-8

  **References**:
  - `app/Providers/MetaWhatsAppProvider.php` - Current implementation
  - `app/Interfaces/WhatsAppProviderInterface.php` - Original methods to compare

  **Acceptance Criteria**:
  - [ ] Class implements correct interfaces (Messaging, Template, Credit, WabaSetup, EmbeddedSignup, Test)
  - [ ] 7 stub methods removed (not supported by Meta)
  - [ ] `registerOwnNumber()` kept (Meta supports it)
  - [ ] `php -l app/Providers/MetaWhatsAppProvider.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: Meta provider PHP syntax validation
    Tool: Bash
    Preconditions: Interfaces created, provider updated
    Steps:
      1. Run: php -l app/Providers/MetaWhatsAppProvider.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-10-syntax-check.txt
  ```

  **Commit**: NO

---

- [ ] 11. Update WhatsAppProviderFactory

  **What to do**:
  - Update `app/Providers/WhatsAppProviderFactory.php`
  - Keep `WhatsAppProviderInterface` as backward-compatible marker interface
  - Add comment explaining the interface segregation approach
  - **Option A**: Keep factory returning `WhatsAppProviderInterface` (marker pattern)
  - **Option B**: Update to return union type or create separate factory methods
  - Recommendation: Option A for minimal changes

  **Must NOT do**:
  - Don't break existing code that uses factory
  - Don't change factory logic

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Reason**: Minor update to return type annotation

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 4 (with Task 12)
  - **Blocks**: Tasks F1, F2
  - **Blocked By**: Tasks 9, 10

  **References**:
  - `app/Providers/WhatsAppProviderFactory.php` - Current factory
  - `app/Interfaces/WhatsAppProviderInterface.php` - Marker interface

  **Acceptance Criteria**:
  - [ ] Factory continues to work (no logic changes)
  - [ ] Comment added explaining interface segregation
  - [ ] `php -l app/Providers/WhatsAppProviderFactory.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: Factory PHP syntax validation
    Tool: Bash
    Preconditions: Provider updated
    Steps:
      1. Run: php -l app/Providers/WhatsAppProviderFactory.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-11-syntax-check.txt
  ```

  **Commit**: NO

---

- [ ] 12. Update WhatsAppSetup.php type hints

  **What to do**:
  - Update `app/Filament/Admin/Pages/WhatsAppSetup.php`
  - Update `$provider` property type hint from `?WhatsAppProviderInterface` to `?WhatsAppProviderInterface`
  - Add comment about interface segregation (optional)
  - Add use statements for new interfaces if needed

  **Must NOT do**:
  - Don't change business logic
  - Don't change method implementations

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Reason**: Minor type hint update

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 4 (with Task 11)
  - **Blocks**: Tasks F1, F2
  - **Blocked By**: Tasks 9, 10

  **References**:
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:109` - Current provider property

  **Acceptance Criteria**:
  - [ ] Property type hint remains compatible
  - [ ] `php -l app/Filament/Admin/Pages/WhatsAppSetup.php` returns no errors

  **QA Scenarios**:

  ```
  Scenario: WhatsAppSetup PHP syntax validation
    Tool: Bash
    Preconditions: Provider updated
    Steps:
      1. Run: php -l app/Filament/Admin/Pages/WhatsAppSetup.php
    Expected Result: No syntax errors reported
    Evidence: .sisyphus/evidence/task-12-syntax-check.txt
  ```

  **Commit**: NO

---

## Final Verification Wave

- [ ] F1. **PHP Syntax Verification** — `quick`
  Run `php -l` on all 8 interfaces + 2 providers + factory + WhatsAppSetup page.
  Output: `All [N/N] files pass syntax check | VERDICT: PASS/FAIL`

- [ ] F2. **Application Startup Verification** — `quick`
  Run `composer run dev` to verify application starts without errors.
  Output: `App starts [YES/NO] | VERDICT: PASS/FAIL`

---

## Commit Strategy

- **Group all changes**: `feat(whatsapp): apply interface segregation to WhatsAppProviderInterface`
- **Files**: All 8 interfaces + 2 providers + factory + WhatsAppSetup
- **Pre-commit**: None (no tests for refactoring)

---

## Success Criteria

### Verification Commands
```bash
php -l app/Interfaces/MessagingInterface.php
php -l app/Interfaces/AccountManagementInterface.php
php -l app/Interfaces/PhoneNumberManagementInterface.php
php -l app/Interfaces/TemplateManagementInterface.php
php -l app/Interfaces/CreditManagementInterface.php
php -l app/Interfaces/WabaSetupInterface.php
php -l app/Interfaces/EmbeddedSignupInterface.php
php -l app/Interfaces/TestMessagingInterface.php
php -l app/Providers/TwilioWhatsAppProvider.php
php -l app/Providers/MetaWhatsAppProvider.php
php -l app/Providers/WhatsAppProviderFactory.php
php -l app/Filament/Admin/Pages/WhatsAppSetup.php
```

### Final Checklist
- [ ] All 8 new interfaces created
- [ ] TwilioWhatsAppProvider implements only relevant interfaces (no verifyWabaConnection)
- [ ] MetaWhatsAppProvider implements only relevant interfaces (7 stub methods removed)
- [ ] WhatsAppProviderFactory unchanged (backward compatible)
- [ ] All PHP syntax checks pass
- [ ] Application starts without errors