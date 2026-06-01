# WhatsApp EmbeddedSignup Flow Refactoring Plan

## TL;DR

> **Quick Summary**: Refactor the EmbeddedSignup flow to use the existing `WhatsAppProviderInterface` instead of directly using Twilio services, eliminating code duplication and improving maintainability.
>
> **Deliverables**:
> - Updated `EmbeddedSignupService` to delegate to `MetaWhatsAppProvider`
> - Added missing methods to `WhatsAppProviderInterface`
> - Updated `WhatsAppSetup` page to use provider-based flow
> - Deprecated service methods with clear migration path
> - Tests for new functionality
>
> **Estimated Effort**: Medium
> **Parallel Execution**: YES - 3 waves
> **Critical Path**: T1 → T2 → T4 → T7 → T8 → Final Review

---

## Context

### Original Request
User requested refactoring of the EmbeddedSignup flow in `WhatsAppSetup.php` (lines 1355-1386) to use `WhatsAppProviderInterface` instead of old Twilio services.

### Interview Summary
**Key Discussions**:
- User specified `@app\Interfaces\WhatsAppProviderInterface.php` as the target interface
- User mentioned old Twilio services need to be updated
- User activated `laravel-security` and `laravel-specialist` skills

**Research Findings**:
- `WhatsAppProviderInterface` already has 25+ methods including `connectManualWaba()`, `verifyWabaConnection()`, `connectIndividualWaba()`
- `MetaWhatsAppProvider` implements the interface for WABA operations
- `EmbeddedSignupService` directly uses `Twilio\Rest\Client` and duplicates provider logic
- Session management via `WabaSetupSession` model is separate from providers

### Metis Review
**Identified Gaps** (noted - proceeding without Metis due to timeout):
- Session management should remain in EmbeddedSignupService (provider-agnostic)
- Error handling standardization needed across services
- Security considerations for token handling

---

## Work Objectives

### Core Objective
Decouple the EmbeddedSignup flow from Twilio-specific code by using `WhatsAppProviderInterface`, enabling future provider switching and reducing code duplication.

### Concrete Deliverables
1. Add `startEmbeddedSignup()` method to `WhatsAppProviderInterface`
2. Add `handleEmbeddedSignupCallback()` method to `WhatsAppProviderInterface`
3. Add `getEmbeddedSignupLoginUrl()` method to `WhatsAppProviderInterface`
4. Implement these methods in `MetaWhatsAppProvider`
5. Update `EmbeddedSignupService` to delegate to provider
6. Update `WhatsAppSetup::startEmbeddedSignup()` to use provider
7. Add deprecation notices to service methods
8. Create tests for new functionality

### Definition of Done
- [ ] `php artisan test --filter=EmbeddedSignup` → PASS (if tests exist)
- [ ] `php artisan test --filter=WhatsAppProvider` → PASS
- [ ] Code compiles without errors: `php -l app/Services/EmbeddedSignupService.php`
- [ ] No Twilio direct usage in provider-related code

### Must Have
- Backward compatibility with existing routes
- Session management preserved via WabaSetupSession
- Security best practices for token handling (laravel-security)
- Laravel best practices (laravel-specialist)

### Must NOT Have
- Direct `Twilio\Rest\Client` instantiation in providers
- Code duplication between EmbeddedSignupService and providers
- Breaking changes to existing routes
- Storing raw tokens without encryption consideration

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: Tests-after (add new tests for refactored code)
- **Framework**: PHPUnit (per project standards)

### QA Policy
Every task includes agent-executed QA scenarios (Playwright/TUI/curl not needed for backend refactor).

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation - Interface changes):
├── Task 1: Add interface methods to WhatsAppProviderInterface
├── Task 2: Implement methods in MetaWhatsAppProvider
└── Task 3: Create unit tests for provider methods

Wave 2 (Service Refactoring):
├── Task 4: Update EmbeddedSignupService to delegate to provider
├── Task 5: Update WhatsAppSetup to use provider
├── Task 6: Add deprecation notices
└── Task 7: Update integration tests

Wave 3 (Verification & Polish):
├── Task 8: Run Pint formatter
├── Task 9: Verify no breaking changes
└── Task 10: Update documentation comments

Final Verification Wave (4 parallel reviews):
├── Task F1: Plan Compliance Audit
├── Task F2: Code Quality Review
├── Task F3: Security Review
└── Task F4: Scope Fidelity Check
```

### Dependency Matrix

| Task | Blocks | Blocked By |
|------|--------|-----------|
| T1: Add interface methods | T2 | - |
| T2: Implement in MetaWhatsAppProvider | T4, T5 | T1 |
| T3: Unit tests (can parallel) | - | T2 |
| T4: Update EmbeddedSignupService | - | T2 |
| T5: Update WhatsAppSetup | - | T2 |
| T6: Deprecation notices | - | T4, T5 |
| T7: Integration tests | - | T4, T5 |
| T8: Pint formatter | - | T6, T7 |
| F1-F4 | - | T8 |

---

## TODOs

- [ ] 1. **Add Interface Methods to WhatsAppProviderInterface**

  **What to do**:
  - Add `startEmbeddedSignup(int $gymId): string` method - Returns Facebook OAuth login URL
  - Add `handleEmbeddedSignupCallback(string $code, string $state): array` method - Handles OAuth callback
  - Add `getEmbeddedSignupLoginUrl(int $gymId): string` method - Generates login URL with session
  - Add proper PHPDoc with return types matching existing interface style

  **Must NOT do**:
  - Don't modify existing method signatures
  - Don't add methods not related to embedded signup

  **Recommended Agent Profile**:
  > **Category**: `laravel-specialist`
  > **Reason**: Adding interface methods requires understanding of existing patterns
  > **Skills**: [`laravel-specialist`, `laravel-security`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with T2, T3)
  - **Blocks**: T2
  - **Blocked By**: None

  **References**:
  - `app/Interfaces/WhatsAppProviderInterface.php:1-50` - Existing interface structure, PHPDoc style
  - `app/Services/EmbeddedSignupService.php:53-67` - `getLoginUrl()` implementation to port
  - `app/Services/EmbeddedSignupService.php:69-148` - `handleCallback()` implementation to port

  **Acceptance Criteria**:
  - [ ] `php -l app/Interfaces/WhatsAppProviderInterface.php` → Syntax OK
  - [ ] Interface has 3 new methods with matching signatures

  **QA Scenarios**:
  ```
  Scenario: Interface method signature check
    Tool: Bash
    Preconditions: Interface file exists
    Steps:
      1. grep "function startEmbeddedSignup" app/Interfaces/WhatsAppProviderInterface.php
      2. grep "function handleEmbeddedSignupCallback" app/Interfaces/WhatsAppProviderInterface.php
      3. grep "function getEmbeddedSignupLoginUrl" app/Interfaces/WhatsAppProviderInterface.php
    Expected Result: All 3 methods found with correct signatures
    Evidence: .sisyphus/evidence/task-1-interface-methods.txt
  ```

  **Commit**: YES
  - Message: `refactor(whatsapp): add embedded signup methods to provider interface`
  - Files: `app/Interfaces/WhatsAppProviderInterface.php`

---

- [ ] 2. **Implement Methods in MetaWhatsAppProvider**

  **What to do**:
  - Implement `startEmbeddedSignup(int $gymId): string` - Create WabaSetupSession, return login URL
  - Implement `handleEmbeddedSignupCallback(string $code, string $state): array` - Exchange token, verify, update session
  - Implement `getEmbeddedSignupLoginUrl(int $gymId): string` - Same as startEmbeddedSignup

  **Must NOT do**:
  - Don't directly use Twilio\Rest\Client
  - Don't duplicate session creation logic

  **Recommended Agent Profile**:
  > **Category**: `laravel-specialist`
  > **Reason**: Implementing provider methods following Laravel patterns
  > **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with T1, T3)
  - **Blocks**: T4, T5
  - **Blocked By**: T1

  **References**:
  - `app/Services/EmbeddedSignupService.php:53-148` - Reference implementations
  - `app/Providers/MetaWhatsAppProvider.php:1-100` - Existing method structure
  - `app/Models/WabaSetupSession.php` - Session model for gym

  **Acceptance Criteria**:
  - [ ] `php -l app/Providers/MetaWhatsAppProvider.php` → Syntax OK
  - [ ] All 3 interface methods implemented

  **QA Scenarios**:
  ```
  Scenario: Provider method implementation check
    Tool: Bash
    Preconditions: Provider file exists
    Steps:
      1. grep -c "public function startEmbeddedSignup" app/Providers/MetaWhatsAppProvider.php
      2. grep -c "public function handleEmbeddedSignupCallback" app/Providers/MetaWhatsAppProvider.php
    Expected Result: Both methods exist, count = 1 each
    Evidence: .sisyphus/evidence/task-2-provider-impl.txt
  ```

  **Commit**: YES
  - Message: `refactor(whatsapp): implement embedded signup in MetaWhatsAppProvider`
  - Files: `app/Providers/MetaWhatsAppProvider.php`

---

- [ ] 3. **Create Unit Tests for Provider Methods**

  **What to do**:
  - Create `tests/Unit/MetaWhatsAppProviderEmbeddedSignupTest.php`
  - Test `startEmbeddedSignup()` returns valid URL format
  - Test `handleEmbeddedSignupCallback()` success/failure paths
  - Mock external HTTP calls

  **Must NOT do**:
  - Don't test with real Meta API credentials
  - Don't create integration tests here

  **Recommended Agent Profile**:
  > **Category**: `laravel-specialist`
  > **Reason**: Writing PHPUnit tests following project conventions
  > **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with T1, T2)
  - **Blocks**: None
  - **Blocked By**: T2

  **References**:
  - `tests/Unit/` - Existing unit test examples
  - `tests/Feature/WhatsAppSetupTest.php` (if exists) - Test patterns

  **Acceptance Criteria**:
  - [ ] Test file created with proper structure
  - [ ] Tests for success and failure paths

  **Commit**: YES
  - Message: `test(whatsapp): add unit tests for embedded signup provider`
  - Files: `tests/Unit/MetaWhatsAppProviderEmbeddedSignupTest.php`

---

- [ ] 4. **Update EmbeddedSignupService to Delegate to Provider**

  **What to do**:
  - Replace direct Twilio\Rest\Client usage with provider calls
  - Keep session management in service (provider-agnostic)
  - Update `connectWabaToSubaccount()` to use `connectIndividualWaba()` from interface
  - Remove duplicate methods that now exist in provider

  **Must NOT do**:
  - Don't remove session management (still needed for OAuth flow)
  - Don't break existing route handlers

  **Recommended Agent Profile**:
  > **Category**: `laravel-specialist`
  > **Reason**: Service refactoring requires understanding of delegation pattern
  > **Skills**: [`laravel-specialist`, `laravel-security`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with T5, T6, T7)
  - **Blocks**: T6
  - **Blocked By**: T2

  **References**:
  - `app/Services/EmbeddedSignupService.php:254-365` - `connectWabaToSubaccount()` to refactor
  - `app/Services/EmbeddedSignupService.php:367-434` - `connectManualWaba()` to delegate
  - `app/Interfaces/WhatsAppProviderInterface.php:345` - `connectIndividualWaba()` signature

  **Acceptance Criteria**:
  - [ ] `php -l app/Services/EmbeddedSignupService.php` → Syntax OK
  - [ ] No `Twilio\Rest\Client` usage for WABA connections
  - [ ] Existing routes still work

  **QA Scenarios**:
  ```
  Scenario: No Twilio usage in refactored service
    Tool: Bash
    Preconditions: Refactored service exists
    Steps:
      1. grep "Twilio\\\\Rest\\\\Client" app/Services/EmbeddedSignupService.php
    Expected Result: No matches (Twilio usage removed)
    Evidence: .sisyphus/evidence/task-4-no-twilio.txt
  ```

  **Commit**: YES
  - Message: `refactor(whatsapp): delegate WABA operations to provider in EmbeddedSignupService`
  - Files: `app/Services/EmbeddedSignupService.php`

---

- [ ] 5. **Update WhatsAppSetup to Use Provider**

  **What to do**:
  - Update `startEmbeddedSignup()` method (lines 1355-1386) to use provider
  - Use `$this->getProvider()->startEmbeddedSignup($gymId)` instead of route redirect
  - Handle provider return value for URL generation

  **Must NOT do**:
  - Don't change the redirect flow (user should still be redirected)
  - Don't modify other methods in the page

  **Recommended Agent Profile**:
  > **Category**: `laravel-specialist`
  > **Reason**: Filament page update following Laravel patterns
  > **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with T4, T6, T7)
  - **Blocks**: T6
  - **Blocked By**: T2

  **References**:
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:1355-1386` - Current implementation
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:131-140` - `getProvider()` method

  **Acceptance Criteria**:
  - [ ] `php -l app/Filament/Admin/Pages/WhatsAppSetup.php` → Syntax OK
  - [ ] `startEmbeddedSignup()` uses provider

  **QA Scenarios**:
  ```
  Scenario: WhatsAppSetup uses provider for embedded signup
    Tool: Bash
    Preconditions: Updated page exists
    Steps:
      1. grep "getProvider()->startEmbeddedSignup" app/Filament/Admin/Pages/WhatsAppSetup.php
    Expected Result: Found in startEmbeddedSignup method
    Evidence: .sisyphus/evidence/task-5-setup-uses-provider.txt
  ```

  **Commit**: YES
  - Message: `refactor(whatsapp): use provider in WhatsAppSetup startEmbeddedSignup`
  - Files: `app/Filament/Admin/Pages/WhatsAppSetup.php`

---

- [ ] 6. **Add Deprecation Notices**

  **What to do**:
  - Add `@deprecated` annotation to methods in EmbeddedSignupService that should now use provider
  - Add `@see` pointing to provider interface method
  - Include version and migration guidance in docblock

  **Must NOT do**:
  - Don't remove deprecated methods (backward compatibility)
  - Don't deprecate session management methods

  **Recommended Agent Profile**:
  > **Category**: `writing`
  > **Reason**: Writing clear deprecation documentation
  > **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with T4, T5, T7)
  - **Blocks**: T8
  - **Blocked By**: T4, T5

  **References**:
  - `app/Services/EmbeddedSignupService.php` - Methods to deprecate
  - `app/Interfaces/WhatsAppProviderInterface.php` - Target methods

  **Acceptance Criteria**:
  - [ ] Deprecated methods have `@deprecated` docblock
  - [ ] Migration guidance included

---

- [ ] 7. **Update Integration Tests**

  **What to do**:
  - Update existing WhatsAppSetup tests to reflect provider changes
  - Add test for full embedded signup flow
  - Verify route redirects still work

  **Must NOT do**:
  - Don't create tests that require real Meta credentials
  - Don't modify unrelated test files

  **Recommended Agent Profile**:
  > **Category**: `laravel-specialist`
  > **Reason**: Integration testing for complete flow
  > **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with T4, T5, T6)
  - **Blocks**: T8
  - **Blocked By**: T4, T5

  **References**:
  - `tests/Feature/WhatsAppSetupTest.php` - Test file to update
  - `tests/Feature/WhatsAppProviderTest.php` - Provider tests

  **Acceptance Criteria**:
  - [ ] Tests pass after refactoring
  - [ ] Full flow tested

---

- [ ] 8. **Run Pint Formatter**

  **What to do**:
  - Run `vendor/bin/pint --dirty` on all modified files
  - Verify no linting issues

  **Must NOT do**:
  - Don't run with `--test` flag (use `--dirty` to fix)

  **Recommended Agent Profile**:
  > **Category**: `quick`
  > **Reason**: Simple formatting task
  > **Skills**: None needed

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 3
  - **Blocks**: Final verification
  - **Blocked By**: T6, T7

  **References**:
  - Project root - Pint configuration

  **Acceptance Criteria**:
  - [ ] `vendor/bin/pint --dirty` → Success with no errors

---

## Final Verification Wave

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists. For each "Must NOT Have": search for forbidden patterns.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `php -l` on all modified files. Check for PSR-12 compliance, proper type hints, and Laravel conventions.
  Output: `Syntax [PASS/FAIL] | Conventions [PASS/FAIL] | VERDICT`

- [ ] F3. **Security Review** — `security-auditor`
  Review token handling, session management, and API credentials for security issues per laravel-security skill.
  Output: `Token Security [N issues] | Session Security [N issues] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  Verify 1:1 mapping between plan and implementation. No added features beyond scope.
  Output: `Tasks [N/N compliant] | VERDICT`

---

## Commit Strategy

- **1**: `refactor(whatsapp): migrate EmbeddedSignup to use provider interface` - interface + provider + service files
- **2**: `test(whatsapp): add tests for embedded signup provider methods` - test files

---

## Success Criteria

### Verification Commands
```bash
php -l app/Interfaces/WhatsAppProviderInterface.php  # Syntax check
php -l app/Providers/MetaWhatsAppProvider.php        # Syntax check
php -l app/Services/EmbeddedSignupService.php       # Syntax check
vendor/bin/pint --dirty                              # Format check
php artisan test --filter=EmbeddedSignup             # Run tests
```

### Final Checklist
- [ ] All "Must Have" present
- [ ] All "Must NOT Have" absent
- [ ] Interface methods match WhatsAppProviderInterface signature
- [ ] No direct Twilio usage in refactored code
- [ ] Backward compatibility with existing routes preserved