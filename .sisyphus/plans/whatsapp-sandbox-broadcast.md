# WhatsApp Sandbox Test Message for Broadcast Page

## TL;DR

> **Quick Summary**: Add "Send Test Message" button to the WhatsApp Broadcast page that sends a test message to the Twilio sandbox number when `use_sandbox` is enabled in config, allowing gym owners to test their WhatsApp integration before sending actual broadcasts.

> **Deliverables**:
> - Updated `app/Filament/Admin/Pages/WhatsApp/Broadcast.php` with test message functionality
> - Test case for sandbox test message feature

> **Estimated Effort**: Short
> **Parallel Execution**: NO - sequential (single file change)
> **Critical Path**: Modify Broadcast.php → Add test method → Add test case

---

## Context

### Original Request
User wants to add sandbox functionality in `Broadcast.php` - when `use_sandbox` is true in `config/services.php` (line 98), it should send a test message from Twilio sandbox instead of creating a full broadcast.

### Research Findings

1. **TwilioWhatsAppService already supports sandbox mode**:
   - Constructor reads `config('services.twilio.use_sandbox', false)` (line 30)
   - `getClient()` method returns master credentials when sandbox is enabled (lines 144-149)
   - Uses sandbox number from `config('services.twilio.sandbox_number', '+15557626186')`

2. **Existing pattern in notifications**:
   - `BirthdayNotification` and other notifications have `getSandboxWhatsAppPayload()` method
   - When sandbox is enabled, returns a text message payload with sandbox number as recipient

3. **Current Broadcast.php flow**:
   - User selects template → sets variables → clicks "Send Broadcast"
   - Creates `WhatsAppBroadcast` record with all recipients
   - Dispatches `SendBroadcastJob` which sends template messages to all recipients

4. **Key insight**: The service already handles sandbox correctly - we just need to add a UI action that uses it for a single test message.

---

## Work Objectives

### Core Objective
Add a "Send Test Message" button to the Broadcast page that, when sandbox mode is enabled, sends a single test message to the configured sandbox phone number using the selected template.

### Concrete Deliverables
- Modified `Broadcast.php` with new test message action
- Test case verifying sandbox test message functionality

### Definition of Done
- [ ] "Send Test Message" button appears in Broadcast form (only when sandbox enabled)
- [ ] Clicking button sends test to sandbox number with selected template
- [ ] No broadcast record created, no credits deducted
- [ ] Success/failure notification displayed
- [ ] Test case passes

### Must Have
- Button only visible when `config('services.twilio.use_sandbox')` is true
- Uses selected template with provided variables
- Sends to sandbox number: `config('services.twilio.sandbox_number')`

### Must NOT Have
- Don't modify TwilioWhatsAppService (already supports sandbox)
- Don't create broadcast records for test messages
- Don't deduct credits for test messages

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: Tests-after
- **Framework**: PHPUnit

### QA Policy
Agent-executed QA via manual browser test + unit test verification.

---

## Execution Strategy

### Parallel Execution
This is a single-file modification - no parallel waves needed.

---

## TODOs

- [ ] 1. Add test message method to Broadcast.php

  **What to do**:
  - Add `sendTestMessage()` method that:
    - Checks if sandbox is enabled (`config('services.twilio.use_sandbox')`)
    - Gets selected template and variables from form data
    - Creates TwilioWhatsAppService instance
    - Sends template message to sandbox number
    - Returns success/failure result
  - Add validation: require template selection before sending test

  **Must NOT do**:
  - Don't create WhatsAppBroadcast records
  - Don't deduct credits
  - Don't process multiple recipients

  **Recommended Agent Profile**:
  > - **Category**: `unspecified-low` - Simple addition to existing file
  > - **Skills**: `[]` - No special skills needed

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Sequential**: After form schema update

  **References**:
  - `app/Filament/Admin/Pages/WhatsApp/Broadcast.php:508-624` - sendBroadcast() method for reference
  - `app/Services/TwilioWhatsAppService.php:163-240` - sendTemplateMessage() method
  - `config/services.php:97-99` - sandbox configuration

  **Acceptance Criteria**:
  - [ ] Method returns array with 'success' key
  - [ ] Returns error if no template selected

  **QA Scenarios**:

  Scenario: Test message sent successfully in sandbox mode
    Tool: Bash (curl) + manual browser verification
    Preconditions: TWILIO_USE_SANDBOX=true in .env
    Steps:
      1. Navigate to Broadcast page in Filament admin
      2. Select a template
      3. Fill in any required variables
      4. Click "Send Test Message" button
      5. Verify success notification appears
    Expected Result: Notification shows "Test message sent successfully"
    Evidence: Browser notification

  Scenario: Test message fails when no template selected
    Tool: Manual browser test
    Preconditions: Sandbox enabled, no template selected
    Steps:
      1. Navigate to Broadcast page
      2. Click "Send Test Message" without selecting template
    Expected Result: Error notification "Please select a template first"
    Evidence: Browser notification

  **Commit**: NO

- [ ] 2. Add "Send Test Message" button to Broadcast form schema

  **What to do**:
  - Add new action button in the "Confirm" section of the form
  - Button should only be visible when `config('services.twilio.use_sandbox')` is true
  - Button calls `sendTestMessage()` method
  - Position: Before or alongside the existing "Send Broadcast" button

  **Must NOT do**:
  - Don't hide the regular "Send Broadcast" button
  - Don't change existing broadcast functionality

  **Recommended Agent Profile**:
  > - **Category**: `unspecified-low`
  > - **Skills**: `fluxui-development` - For proper button styling

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Sequential**: After test message method

  **References**:
  - `app/Filament/Admin/Pages/WhatsApp/Broadcast.php:442-449` - Existing action buttons
  - `config/services.php:98` - use_sandbox config key

  **Acceptance Criteria**:
  - [ ] Button visible only when sandbox enabled
  - [ ] Button calls sendTestMessage() method

  **QA Scenarios**:

  Scenario: Test button visible when sandbox enabled
    Tool: Playwright
    Preconditions: TWILIO_USE_SANDBOX=true
    Steps:
      1. Navigate to Broadcast page
      2. Check for "Send Test Message" button
    Expected Result: Button visible in Confirm section
    Evidence: Screenshot of page

  Scenario: Test button hidden when sandbox disabled
    Tool: Playwright  
    Preconditions: TWILIO_USE_SANDBOX=false
    Steps:
      1. Navigate to Broadcast page
      2. Check "Send Test Message" button is not present
    Expected Result: Button not visible
    Evidence: Screenshot of page

  **Commit**: NO

- [ ] 3. Add unit test for sandbox test message

  **What to do**:
  - Create test that verifies test message method works correctly
  - Mock TwilioWhatsAppService to avoid actual API calls
  - Test sandbox mode detection
  - Test validation (template required)

  **Must NOT do**:
  - Don't test actual Twilio API (mock the service)

  **Recommended Agent Profile**:
  > - **Category**: `unspecified-low`
  > - **Skills**: `[]` - Standard PHPUnit testing

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Sequential**: After button implementation

  **References**:
  - `tests/Feature/TwilioWhatsAppServiceTest.php` - Existing test patterns

  **Acceptance Criteria**:
  - [ ] Test passes when run with `php artisan test`

  **Commit**: NO

- [ ] 4. Run pint formatter

  **What to do**:
  - Run `vendor/bin/pint --dirty` to format the modified file

  **Must NOT do**:
  - Don't skip this step

  **Recommended Agent Profile**:
  > - **Category**: `quick`
  > - **Skills**: `[]`

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Sequential**: After all code changes

  **References**:
  - Project code style rules

  **Acceptance Criteria**:
  - [ ] pint --dirty shows no changes (file already formatted)

  **Commit**: NO

---

## Final Verification Wave

> Not required for small feature - skip oracle/quality review

---

## Success Criteria

### Verification Commands
```bash
php artisan test --filter=BroadcastTest
```

### Final Checklist
- [ ] "Send Test Message" button appears when sandbox enabled
- [ ] Button hidden when sandbox disabled
- [ ] Test message sends to sandbox number
- [ ] No broadcast record created
- [ ] No credits deducted
- [ ] Success/failure notification shown
- [ ] Unit test passes