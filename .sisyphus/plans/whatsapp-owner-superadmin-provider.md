# WhatsApp Provider: Owner vs Super-Admin Implementation Plan

## TL;DR

> Update WhatsApp provider logic so owners use WABA (Meta) by default, while super-admins use Twilio for system messages. Add `isSystemMessage()` flag to notifications and update factory to choose provider based on this flag.
> 
> **Deliverables**:
> - Add `isSystemMessage()` method to BaseTenantNotification
> - Update WhatsAppProviderFactory to check `isSystemMessage()` flag
> - Update WhatsAppChannel to pass the flag
> - Test the logic
> 
> **Estimated Effort**: Small
> **Parallel Execution**: NO - sequential
> **Critical Path**: BaseTenantNotification → Factory → Channel → Test

---

## Context

### Original Request (from plan)
Create WhatsAppProviderInterface supporting both Twilio and Meta WABA providers with config-based selection.

### New Requirement from User
- **WABA is default provider for owners** - Their account uses WABA (embedded signup already implemented in WhatsAppSetup.php)
- **Super-admin sends system messages via Twilio** - When super-admin sends notifications, use Twilio config
- **Provider selection logic**: Based on whether message is a "system message" from super-admin

### User Decision
- Detection method: **Explicit flag in notification** (`isSystemMessage()` method)
- If notification has `isSystemMessage() === true`, use Twilio
- Otherwise use configured provider (default: WABA for owners)

---

## Work Objectives

### Core Objective
Implement provider selection logic that switches between Twilio (super-admin system messages) and WABA (owner messages) based on the `isSystemMessage()` flag on notifications.

### Concrete Deliverables
1. Add `isSystemMessage()` method to `BaseTenantNotification`
2. Update `WhatsAppProviderFactory::create()` to accept and check the flag
3. Update `WhatsAppChannel` to detect and pass the flag
4. Verify logic with tests

### Definition of Done
- [ ] `isSystemMessage()` method exists in BaseTenantNotification (default: false)
- [ ] Factory accepts `$isSystemMessage` parameter
- [ ] Factory returns Twilio when flag is true, WABA when false
- [ ] WhatsAppChannel detects the flag from notification and passes it

### Must Have
- Backward compatibility: Existing notifications continue to work (they return false)
- Clear logic: System messages → Twilio, Owner messages → WABA

### Must NOT Have
- Don't change existing notification behavior (default is false = owner/WABA)
- Don't remove any existing functionality

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Test approach**: Agent-executed manual verification
- **No TDD** - Adding small feature to existing code

### QA Policy
Every task includes agent-executed QA scenarios verifying the logic.

---

## Execution Strategy

### Sequential Tasks

**Task 1**: Add `isSystemMessage()` to BaseTenantNotification
- Add method `isSystemMessage(): bool` returning false by default

**Task 2**: Update WhatsAppProviderFactory
- Modify `create()` to accept optional `$isSystemMessage` parameter
- Return TwilioProvider when true, WABAProvider when false

**Task 3**: Update WhatsAppChannel
- Detect if notification has `isSystemMessage()` method
- Pass the flag to factory

**Task 4**: Verify with tests
- Check existing notifications return false by default
- Verify factory logic works correctly

---

## TODOs

- [ ] 1. Add isSystemMessage() method to BaseTenantNotification

  **What to do**:
  - Edit `app/Notifications/BaseTenantNotification.php`
  - Add method: `public function isSystemMessage(): bool { return false; }`
  - Add PHPDoc explaining it controls provider selection

  **Must NOT do**:
  - Don't change any existing notification behavior
  - Don't add any other methods

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - **Skills Evaluated but Omitted**: None

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: None (can start immediately)

  **References**:
  - `app/Notifications/BaseTenantNotification.php` - Where to add method

  **Acceptance Criteria**:
  - [ ] Method exists and returns false by default

  **QA Scenarios**:

  ```
  Scenario: Verify isSystemMessage method exists
    Tool: Bash
    Preconditions: None
    Steps:
      1. grep -n "isSystemMessage" app/Notifications/BaseTenantNotification.php
    Expected Result: Method found with return false
    Evidence: .sisyphus/evidence/task-1-method-exists.txt
  ```

- [ ] 2. Update WhatsAppProviderFactory to accept isSystemMessage flag

  **What to do**:
  - Edit `app/Providers/WhatsAppProviderFactory.php`
  - Modify `create(int $gymId, bool $isSystemMessage = false)` signature
  - Return TwilioProvider when $isSystemMessage is true
  - Return WABAProvider (default) when $isSystemMessage is false

  **Must NOT do**:
  - Don't change the default provider behavior

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 1

  **References**:
  - `app/Providers/WhatsAppProviderFactory.php` - Current implementation

  **Acceptance Criteria**:
  - [ ] create() accepts second parameter
  - [ ] Returns Twilio when true, WABA when false

  **QA Scenarios**:

  ```
  Scenario: Verify factory accepts flag
    Tool: Bash
    Preconditions: Task 2 complete
    Steps:
      1. grep "bool.*isSystemMessage" app/Providers/WhatsAppProviderFactory.php
    Expected Result: Method signature updated
    Evidence: .sisyphus/evidence/task-2-factory-updated.txt
  ```

- [ ] 3. Update WhatsAppChannel to pass isSystemMessage flag

  **What to do**:
  - Edit `app/Notifications/WhatsAppChannel.php`
  - In send() method, check if notification has `isSystemMessage()` method
  - Pass the result to `WhatsAppProviderFactory::create($gymId, $isSystemMessage)`

  **Must NOT do**:
  - Don't change notification delivery logic
  - Don't break existing behavior

  **Recommended Agent Profile**:
  - **Category**: `deep`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 2

  **References**:
  - `app/Notifications/WhatsAppChannel.php` - Current implementation at line 56

  **Acceptance Criteria**:
  - [ ] Channel checks for isSystemMessage method
  - [ ] Passes flag to factory

  **QA Scenarios**:

  ```
  Scenario: Verify channel uses flag
    Tool: Bash
    Preconditions: Task 3 complete
    Steps:
      1. grep "isSystemMessage" app/Notifications/WhatsAppChannel.php
    Expected Result: Flag passed to factory
    Evidence: .sisyphus/evidence/task-3-channel-updated.txt
  ```

- [ ] 4. Verify logic works correctly

  **What to do**:
  - Run PHP syntax checks on modified files
  - Verify backward compatibility with existing tests
  - Test that default notifications use WABA

  **Must NOT do**:
  - Don't skip verification

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Blocked By**: Task 3

  **Acceptance Criteria**:
  - [ ] All files pass syntax check
  - [ ] Existing tests still pass
  - [ ] Logic verified

  **QA Scenarios**:

  ```
  Scenario: Verify syntax
    Tool: Bash
    Preconditions: All tasks complete
    Steps:
      1. php -l app/Notifications/BaseTenantNotification.php
      2. php -l app/Providers/WhatsAppProviderFactory.php
      3. php -l app/Notifications/WhatsAppChannel.php
    Expected Result: No syntax errors
    Evidence: .sisyphus/evidence/task-4-syntax.txt
  ```

---

## Final Verification Wave

- [ ] F1. **Logic Verification** — Check the isSystemMessage flag controls provider selection correctly
  Output: `Flag Detection [PASS/FAIL] | Factory Logic [PASS/FAIL] | VERDICT`

- [ ] F2. **Backward Compatibility** — Verify existing notifications work as before (default false = WABA)
  Output: `Default Behavior [PRESERVED/BROKEN] | VERDICT`

- [ ] F3. **Code Quality** — Run Pint on modified files
  Output: `Style [PASS/FAIL] | VERDICT`

---

## Commit Strategy

- **1**: `feat(whatsapp): add isSystemMessage method to BaseTenantNotification`
- **2**: `feat(whatsapp): update factory to support system message flag`
- **3**: `refactor(whatsapp): update WhatsAppChannel to pass flag to factory`
- **4**: `test(whatsapp): verify provider selection logic`

---

## Success Criteria

### Verification Commands
```bash
php -l app/Notifications/BaseTenantNotification.php
php -l app/Providers/WhatsAppProviderFactory.php
php -l app/Notifications/WhatsAppChannel.php
grep "isSystemMessage" app/Notifications/BaseTenantNotification.php
grep "isSystemMessage" app/Providers/WhatsAppProviderFactory.php
grep "isSystemMessage" app/Notifications/WhatsAppChannel.php
```

### Final Checklist
- [ ] isSystemMessage() method added to BaseTenantNotification
- [ ] Factory accepts the flag parameter
- [ ] WhatsAppChannel passes the flag
- [ ] Default behavior preserved (WABA for owners)
- [ ] System messages will use Twilio (when flag is true)
- [ ] All syntax checks pass