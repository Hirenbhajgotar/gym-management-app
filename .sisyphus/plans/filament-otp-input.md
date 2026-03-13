# Work Plan: Reusable OTP Input Component for Filament

## TL;DR

> **Quick Summary**: Create a reusable OTP (One Time Password) input component for Filament v3, similar to the `filament-otp-input` package. The component will provide a polished user experience with separate input boxes for each digit, auto-focus navigation, and customizable options.

> **Deliverables**:
> - `app/Filament/Components/OtpInput.php` - Main OTP input component class
> - `resources/views/filament/components/otp-input.blade.php` - Blade view for rendering
> - Updated `WhatsAppSetup.php` to use the new OTP component

> **Estimated Effort**: Short
> **Parallel Execution**: NO - sequential tasks
> **Critical Path**: Create PHP Component → Create Blade View → Update WhatsAppSetup

---

## Context

### Original Request
Create a reusable OTP input component for Filament, similar to https://github.com/hasan-ahani/filament-otp-input

### Research Findings

1. **Existing OTP in Project**: `WhatsAppSetup.php` already uses a simple `TextInput::make('otp_code')` for OTP verification - needs upgrading

2. **Reference Package Features**:
   - Customizable number of OTP digits (default 4, commonly 6)
   - Auto-advance to next input after digit entry
   - Auto-backward on backspace when empty
   - Input types: number (default), password, text
   - Callback support via `afterStateUpdated`

3. **Project Stack**:
   - Filament v3.3.47
   - Laravel v12
   - Tailwind CSS v4
   - Livewire v3 + Volt
   - Flux UI v2

4. **Existing Component Pattern**: `app/Filament/Components/FeedbackPopup.php` shows how to create Livewire-integrated form components in this project

---

## Work Objectives

### Core Objective
Create a reusable OTP input component for Filament that provides a polished user experience with:
- Multiple separate input boxes for each OTP digit
- Auto-focus navigation (next on input, prev on backspace)
- Customizable number of digits
- Configurable input type (number/password/text)

### Concrete Deliverables
1. `app/Filament/Components/OtpInput.php` - Main component class
2. `resources/views/filament/components/otp-input.blade.php` - Blade view
3. Updated `app/Filament/Admin/Pages/WhatsAppSetup.php` to use new component

### Definition of Done
- [ ] OTP component renders multiple input boxes
- [ ] Auto-focus moves to next input after digit entry
- [ ] Backspace navigates to previous input
- [ ] Configurable number of digits (default 6)
- [ ] Configurable input type (number/password/text)
- [ ] Works with Filament form validation
- [ ] WhatsAppSetup page uses new component

### Must Have
- Multiple separate input fields for each OTP digit
- Auto-advance to next field on input
- Auto-backtrack on backspace
- Tailwind CSS styling matching project conventions

### Must NOT Have
- External npm dependencies (use vanilla JS/Alpine via Filament's existing setup)
- Complex build steps

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: NO (component is UI-focused, will verify via manual QA)
- **Framework**: N/A

### QA Policy
Every task includes agent-executed QA scenarios (see TODO below).

---

## Execution Strategy

### Tasks (Sequential - small component)

---

## TODOs

- [ ] 1. Create OTP Input Component Class

  **What to do**:
  - Create `app/Filament/Components/OtpInput.php`
  - Extend `Filament\Forms\Components\TextInput`
  - Add configurable options: number of inputs, input type
  - Implement `getChildComponents()` to render multiple inputs
  - Add `afterStateUpdated` callback support
  - Configure state path to work with Filament forms

  **Must NOT do**:
  - Don't create a standalone Livewire component (Filament handles this)

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Requires understanding of Filament's form component architecture
  - **Skills**: [`fluxui-development`]
    - `fluxui-development`: Understanding of UI component patterns

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Wave**: 1
  - **Blocks**: Task 2
  - **Blocked By**: None

  **References**:
  - `app/Filament/Components/FeedbackPopup.php` - Existing Filament component pattern
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:589` - Current OTP implementation to replace

  **Acceptance Criteria**:
  - [ ] File created at `app/Filament/Components/OtpInput.php`
  - [ ] Component extends `TextInput`
  - [ ] Has `numberInput()` method for configuring digit count
  - [ ] Has `password()` and `text()` methods for input type
  - [ ] `php artisan route:list` shows no errors

- [ ] 2. Create OTP Input Blade View

  **What to do**:
  - Create `resources/views/filament/components/otp-input.blade.php`
  - Render multiple input fields based on configuration
  - Add JavaScript for:
    - Auto-focus next input on digit entry
    - Auto-focus previous input on backspace when empty
    - Paste support (fill all digits at once)
  - Style with Tailwind CSS matching project conventions

  **Must NOT do**:
  - Don't use external libraries (use vanilla JS)
  - Don't break existing Filament form behavior

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
    - Reason: Requires UI template creation and JavaScript
  - **Skills**: [`tailwindcss-development`, `volt-development`]
    - `tailwindcss-development`: For styling the component
    - `volt-development`: Understanding Blade/Livewire integration

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Wave**: 1
  - **Blocks**: Task 3
  - **Blocked By**: Task 1

  **References**:
  - `resources/views/filament/components/feedback-popup.blade.php` - Existing component view (check if exists)
  - Tailwind CSS v4 documentation for styling patterns

  **Acceptance Criteria**:
  - [ ] File created at `resources/views/filament/components/otp-input.blade.php`
  - [ ] Renders multiple input fields
  - [ ] JavaScript handles auto-focus navigation
  - [ ] Paste functionality works
  - [ ] Styled appropriately

- [ ] 3. Update WhatsAppSetup to Use OTP Component

  **What to do**:
  - Update `app/Filament/Admin/Pages/WhatsAppSetup.php`
  - Replace `TextInput::make('otp_code')` with new `OtpInput::make('otp_code')`
  - Configure for 6 digits
  - Set input type to number
  - Run tests if available

  **Must NOT do**:
  - Don't break existing WhatsApp verification flow

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Modifying existing Filament page
  - **Skills**: [`fluxui-development`]
    - `fluxui-development`: For understanding Flux UI components

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Wave**: 1
  - **Blocks**: None
  - **Blocked By**: Task 2

  **References**:
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:589` - Location of current OTP field

  **Acceptance Criteria**:
  - [ ] WhatsAppSetup uses new OtpInput component
  - [ ] OTP verification still works correctly
  - [ ] `php artisan test --filter=WhatsAppSetup` passes (if tests exist)

- [ ] 4. Run Pint Formatter

  **What to do**:
  - Run `vendor/bin/pint --dirty` to format all PHP files

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Wave**: 1
  - **Blocks**: None
  - **Blocked By**: Task 3

  **Acceptance Criteria**:
  - [ ] Pint reports no formatting issues

---

## Final Verification Wave

- [ ] F1. **Component Renders Correctly** — Read the component file, verify structure
- [ ] F2. **Blade View Has Proper JavaScript** — Check auto-focus logic exists
- [ ] F3. **WhatsAppSetup Uses New Component** — Verify import and usage
- [ ] F4. **Code Style Passes** — Run pint formatter

---

## Commit Strategy

- **4**: `feat(filament): add reusable OTP input component`

---

## Success Criteria

### Verification Commands
```bash
php artisan route:list  # No errors
vendor/bin/pint --dirty  # No formatting issues
```

### Final Checklist
- [ ] OTP component created
- [ ] Blade view created with proper JS
- [ ] WhatsAppSetup updated
- [ ] Code style passes
