# WhatsApp Setup Step 3 — Phone Number Source Selection

## TL;DR

> **Quick Summary**: Enhance Step 3 of the WhatsApp setup wizard to let gym owners choose between using their own phone number or purchasing one from Twilio, with proper warnings/instructions for each path.
>
> **Deliverables**:
> - Updated `getPhoneNumberStep()` method in `WhatsAppSetup.php`
> - New `registerOwnPhoneNumber()` action method
> - New `phone_number_source` public property + `own_phone_number` form field
>
> **Estimated Effort**: Quick
> **Parallel Execution**: NO — sequential single-file change
> **Critical Path**: Property → Form schema → Action method → Pint → Test

---

## Context

### Original Request
In `app/Filament/Admin/Pages/WhatsAppSetup.php`, during **Step 3**, allow users to register their own phone number for WhatsApp Business Account (WABA).

### Key Findings From Codebase Analysis

**Existing Radio pattern (Step 2 — WABA Setup, lines 368–382):**
```php
Forms\Components\Radio::make('waba_connection_method')
    ->label('How would you like to set up your WABA?')
    ->options([
        'connect_existing' => 'Connect Existing WABA',
        'create_new'       => 'Create New WABA',
    ])
    ->descriptions([
        'connect_existing' => '...',
        'create_new'       => '...',
    ])
    ->default('create_new')
    ->live()
    ->inline()
    ->inlineLabel(false)
    ->disabled($this->wabaCreated)
```

Then conditional `Section`s are shown/hidden with:
```php
->visible(fn (Get $get): bool => $get('waba_connection_method') === 'connect_existing')
```

**This is the exact pattern to replicate for Step 3.**

**Existing Twilio number flow (lines 483–533):**  
- `Forms\Components\Select::make('search_country')` → country selection  
- `Forms\Components\Actions\Action::make('searchNumbers')` → calls `searchNumbers()`  
- `Forms\Components\Placeholder::make('numbers_list')` → renders `available-numbers.blade.php` partial  
- `selectNumber(string $phoneNumber)` → calls `purchaseNumber()`

**Warning/notice pattern used in WABA step (lines 456–461):**
```php
Forms\Components\Placeholder::make('embedded_signup_info')
    ->content(new HtmlString('
        <div class="mt-4 p-4 bg-blue-50 dark:bg-gray-800 border border-blue-200 dark:border-blue-400 rounded-lg">
            ...
        </div>
    '))
```
For warnings use `bg-amber-50`, `border-amber-200`, `text-amber-800` (yellow/amber palette — danger-adjacent but not alarming).

**`initializeFormData()` (lines 167–188):** Add `'phone_number_source' => 'purchase_twilio'` and `'own_phone_number' => ''` to `$this->data`.

**No existing `registerOwnPhoneNumber()` method** — needs to be added. The method should save the phone number to `WhatsAppPhoneNumber` via `WhatsAppAccount` and mark `numberPurchased = true`.

**`WhatsAppPhoneNumber` model** — stores `phone_number`, `gym_id`, `whats_app_account_id` (check exact columns via `database-query` tool if needed during implementation).

---

## Work Objectives

### Core Objective
Replace the current Twilio-only Step 3 with a two-path flow: **"Use my own number"** or **"Purchase from Twilio"**, with proper warnings for the own-number path.

### Concrete Deliverables
1. `public string $phoneNumberSource = 'purchase_twilio';` property on the page class
2. `'phone_number_source' => 'purchase_twilio'` and `'own_phone_number' => ''` in `initializeFormData()`
3. Fully rewritten `getPhoneNumberStep()` using Radio + two conditional Sections
4. `registerOwnPhoneNumber()` action method that saves the number and advances setup
5. Tests updated/added for the new paths

### Must Have
- Radio: two options (`purchase_twilio`, `use_own_number`) with inline labels + descriptions
- "Use Own Number" section: amber warning box listing the 4 Meta requirements, then a text input for the number, then a Register button that calls `registerOwnPhoneNumber()`
- "Purchase from Twilio" section: unchanged existing flow (country select, search button, numbers list)
- Both sections: `->visible(fn (Get $get): bool => $get('phone_number_source') === '...')`
- Radio is `->disabled($this->numberPurchased)` to lock after completion
- `registerOwnPhoneNumber()` sets `numberPurchased = true` and `setupStep = 4`
- `own_phone_number` input: `->tel()`, `->required()`, `->disabled($this->numberPurchased)`

### Must NOT Have (Guardrails)
- Do NOT break the existing Twilio search/purchase flow
- Do NOT change any other step (1, 2, 4, 5)
- Do NOT add database migrations — use existing `WhatsAppPhoneNumber` model
- Do NOT use Flux UI components (this page uses Filament `Forms\Components\*` only)
- Do NOT add `->inline()` to the descriptions Radio if descriptions are provided — Filament v3 doesn't support both inline + descriptions simultaneously

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: Tests after implementation
- **Framework**: PHPUnit / `php artisan test`

### Test File
`tests/Feature/Filament/WhatsAppSetupTest.php` — check if it already exists and update it.

---

## Execution Strategy

This is a single sequential task — one file to change, plus tests.

---

## TODOs

- [ ] 1. Add `phoneNumberSource` property + update `initializeFormData()` + rewrite `getPhoneNumberStep()` + add `registerOwnPhoneNumber()` action

  **What to do**:

  ### 1a. Add public property (after `public bool $numberPurchased = false;`, line 85)
  ```php
  public string $phoneNumberSource = 'purchase_twilio';
  ```

  ### 1b. Update `initializeFormData()` — add two entries to `$this->data` array
  Add inside the array on lines 172–181:
  ```php
  'phone_number_source' => 'purchase_twilio',
  'own_phone_number' => '',
  ```

  ### 1c. Rewrite `getPhoneNumberStep()` (lines 483–533)
  Replace the entire method body with:

  ```php
  private function getPhoneNumberStep(): Forms\Components\Wizard\Step
  {
      return Forms\Components\Wizard\Step::make('Phone Number')
          ->label('3. Phone Number')
          ->schema([
              Forms\Components\Placeholder::make('')
                  ->content(new HtmlString('
                      <div class="mb-4">
                          <h3 class="font-medium text-gray-900 dark:text-gray-100 mb-2">Get Your WhatsApp Business Number</h3>
                          <p class="text-sm text-gray-500 dark:text-gray-400">A dedicated phone number is required to send WhatsApp messages to your members. Choose how you would like to get your number.</p>
                      </div>
                  ')),

              Forms\Components\Radio::make('phone_number_source')
                  ->label('How would you like to get your phone number?')
                  ->options([
                      'purchase_twilio' => 'Purchase a Number from Twilio',
                      'use_own_number'  => 'Use My Own Phone Number',
                  ])
                  ->descriptions([
                      'purchase_twilio' => 'Get a dedicated WhatsApp-ready number instantly from Twilio. Recommended for most gyms.',
                      'use_own_number'  => 'Register an existing phone number you own with your WhatsApp Business Account.',
                  ])
                  ->default('purchase_twilio')
                  ->live()
                  ->inline()
                  ->inlineLabel(false)
                  ->disabled($this->numberPurchased),

              // ── Own Number Section ──────────────────────────────────────
              Forms\Components\Section::make('Register Your Own Number')
                  ->description('Use an existing phone number for your WhatsApp Business Account.')
                  ->schema([
                      Forms\Components\Placeholder::make('own_number_warning')
                          ->hiddenLabel()
                          ->content(new HtmlString('
                              <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg">
                                  <div class="flex items-start gap-3">
                                      <div class="flex-shrink-0 mt-0.5">
                                          <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                          </svg>
                                      </div>
                                      <div>
                                          <h4 class="text-sm font-semibold text-amber-800 dark:text-amber-300 mb-2">Important Requirements Before You Proceed</h4>
                                          <p class="text-sm text-amber-700 dark:text-amber-400 mb-3">Your phone number must meet <strong>all</strong> of the following Meta requirements:</p>
                                          <ul class="space-y-1.5 text-sm text-amber-700 dark:text-amber-400">
                                              <li class="flex items-start gap-2">
                                                  <span class="font-bold mt-0.5">•</span>
                                                  <span><strong>Not registered with WhatsApp Messenger</strong> — the number must not be an active personal WhatsApp account.</span>
                                              </li>
                                              <li class="flex items-start gap-2">
                                                  <span class="font-bold mt-0.5">•</span>
                                                  <span><strong>Not registered with WhatsApp Business App</strong> — the number must not be used in the standalone WhatsApp Business mobile application.</span>
                                              </li>
                                              <li class="flex items-start gap-2">
                                                  <span class="font-bold mt-0.5">•</span>
                                                  <span><strong>Not connected to any existing Meta WhatsApp API</strong> — the number must not already be registered with another WhatsApp Business API integration.</span>
                                              </li>
                                              <li class="flex items-start gap-2">
                                                  <span class="font-bold mt-0.5">•</span>
                                                  <span><strong>Compliant with Meta guidelines</strong> — the number must comply with all <a href="https://developers.facebook.com/docs/whatsapp/phone-numbers" target="_blank" class="underline font-medium">Meta\'s official phone number requirements</a>.</span>
                                              </li>
                                          </ul>
                                          <p class="mt-3 text-xs text-amber-600 dark:text-amber-500">If your number is already registered with WhatsApp, you must delete that account first before registering it here.</p>
                                      </div>
                                  </div>
                              </div>
                          ')),

                      Forms\Components\TextInput::make('own_phone_number')
                          ->label('Your Phone Number')
                          ->tel()
                          ->required()
                          ->placeholder('+1234567890')
                          ->helperText('Enter the full phone number including country code (e.g., +1 for US, +44 for UK).')
                          ->disabled($this->numberPurchased),

                      Forms\Components\Actions::make([
                          Forms\Components\Actions\Action::make('registerOwnNumber')
                              ->label($this->numberPurchased ? 'Number Registered' : 'Register This Number')
                              ->color('primary')
                              ->icon($this->numberPurchased ? 'heroicon-o-check-circle' : 'heroicon-o-device-phone-mobile')
                              ->action('registerOwnPhoneNumber')
                              ->disabled($this->numberPurchased),
                      ]),
                  ])
                  ->visible(fn (Get $get): bool => $get('phone_number_source') === 'use_own_number'),

              // ── Purchase from Twilio Section ────────────────────────────
              Forms\Components\Section::make('Why This Number Works for You')
                  ->description('Common questions about international WhatsApp numbers')
                  ->schema([
                      Forms\Components\Placeholder::make('')
                          ->content(fn () => new HtmlString(
                              view('filament.admin.pages.whatsapp.partials.phone-number-info')->render()
                          )),
                  ])
                  ->visible(fn (Get $get): bool => $get('phone_number_source') === 'purchase_twilio'),

              Forms\Components\Select::make('search_country')
                  ->label('Select Country for Phone Number')
                  ->options([
                      'US' => 'United States (Recommended - Best Value)',
                      'GB' => 'United Kingdom',
                      'CA' => 'Canada',
                      'IN' => 'India',
                      'AU' => 'Australia',
                  ])
                  ->default('US')
                  ->required()
                  ->disabled($this->numberPurchased)
                  ->helperText('Choose the country where you want your WhatsApp number to be registered.')
                  ->visible(fn (Get $get): bool => $get('phone_number_source') === 'purchase_twilio'),

              Forms\Components\Actions::make([
                  Forms\Components\Actions\Action::make('searchNumbers')
                      ->label('Search Available Numbers')
                      ->action('searchNumbers')
                      ->disabled($this->isSearching || $this->numberPurchased),
              ])->visible(fn (Get $get): bool => $get('phone_number_source') === 'purchase_twilio'),

              Forms\Components\Placeholder::make('numbers_list')
                  ->hidden(fn () => ! $this->availableNumbersLoaded || $this->data['phone_number_source'] !== 'purchase_twilio')
                  ->content(fn () => new HtmlString(view('filament.admin.pages.whatsapp.partials.available-numbers', ['numbers' => $this->availableNumbers])->render())),
          ])
          ->afterValidation(function () {
              $this->setupStep = 4;
          });
  }
  ```

  ### 1d. Add `registerOwnPhoneNumber()` action method (add after `purchaseNumber()` method, around line 970)

  ```php
  public function registerOwnPhoneNumber(): void
  {
      $data = $this->form->getState();
      $ownNumber = $data['own_phone_number'] ?? null;

      if (! $ownNumber) {
          Notification::make()
              ->title('Phone Number Required')
              ->body('Please enter your phone number before registering.')
              ->warning()
              ->send();

          return;
      }

      try {
          $gymId = $this->getGymId();

          if (! $this->whatsappAccount) {
              throw new \Exception('WhatsApp account not found. Please complete the previous steps first.');
          }

          WhatsAppPhoneNumber::updateOrCreate(
              ['whats_app_account_id' => $this->whatsappAccount->id],
              [
                  'gym_id'       => $gymId,
                  'phone_number' => $ownNumber,
                  'status'       => 'active',
              ]
          );

          $this->phoneNumber = $this->whatsappAccount->phoneNumber()->first();
          $this->numberPurchased = true;
          $this->setupStep = 4;

          Notification::make()
              ->title('Number Registered')
              ->body('Your phone number has been registered with your WhatsApp Business Account.')
              ->success()
              ->send();
      } catch (\Exception $e) {
          Log::error('Failed to register own phone number', ['error' => $e->getMessage()]);

          Notification::make()
              ->title('Registration Failed')
              ->body($e->getMessage())
              ->danger()
              ->send();
      }
  }
  ```

  **Must NOT do**:
  - Do not change any other step's schema method
  - Do not add migrations
  - Do not touch `getWabaSetupStep()` or `getCreateAccountStep()`

  **Recommended Agent Profile**:
  > Edit a single PHP Filament page class — straightforward code changes.
  - **Category**: `quick`
  - **Skills**: [`tailwindcss-development`]
    - `tailwindcss-development`: The amber warning box uses Tailwind v4 utility classes — must use correct v4 syntax.

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Sequential only
  - **Blocks**: Task 2 (tests)
  - **Blocked By**: None

  **References**:

  **Pattern References** (existing code to follow):
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:355-481` — `getWabaSetupStep()` — exact Radio + conditional Section pattern to replicate
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:483-533` — `getPhoneNumberStep()` — current implementation to replace
  - `app/Filament/Admin/Pages/WhatsAppSetup.php:925-970` — `purchaseNumber()` — follow same try/catch/notification pattern in `registerOwnPhoneNumber()`

  **Type/Model References**:
  - `app/Models/WhatsAppPhoneNumber.php` — Read this to confirm the exact fillable column names before using `updateOrCreate()`. Likely columns: `whats_app_account_id`, `gym_id`, `phone_number`, `status` — but verify.

  **Test References**:
  - `tests/Feature/Filament/WhatsAppSetupTest.php` — Existing test file; update or add tests for the new radio + own number path.

  **Acceptance Criteria**:
  - [ ] `public string $phoneNumberSource = 'purchase_twilio';` exists on the class
  - [ ] `$this->data` array in `initializeFormData()` includes `'phone_number_source'` and `'own_phone_number'`
  - [ ] `getPhoneNumberStep()` returns a step with a Radio component having two options: `purchase_twilio` and `use_own_number`
  - [ ] "Use My Own Number" section is only visible when radio = `use_own_number`
  - [ ] Amber warning box lists all 4 Meta requirements
  - [ ] Twilio purchase flow (country select, search button, numbers list) is only visible when radio = `purchase_twilio`
  - [ ] `registerOwnPhoneNumber()` method exists, saves to `WhatsAppPhoneNumber`, sets `numberPurchased = true`

  **QA Scenarios**:

  ```
  Scenario: Default Twilio path still works
    Tool: Playwright
    Preconditions: User is on Step 3 of WhatsApp setup wizard (wabaCreated=true, numberPurchased=false)
    Steps:
      1. Navigate to /admin/{tenant}/whats-app-setup
      2. Assert radio "Purchase a Number from Twilio" is selected by default
      3. Assert "Select Country for Phone Number" select is visible
      4. Assert "Search Available Numbers" button is visible
      5. Assert "Register Your Own Number" section is NOT visible
    Expected Result: Twilio purchase section shows, own-number section hidden
    Evidence: .sisyphus/evidence/task-1-twilio-default.png

  Scenario: Own number path shows warning and input
    Tool: Playwright
    Preconditions: User is on Step 3, radio defaults to purchase_twilio
    Steps:
      1. Click the "Use My Own Phone Number" radio option
      2. Assert the amber warning box is visible (look for text "Not registered with WhatsApp Messenger")
      3. Assert a text input labeled "Your Phone Number" is visible
      4. Assert "Register This Number" button is visible
      5. Assert the Twilio country select is NOT visible
    Expected Result: Own-number section with warning shown, Twilio section hidden
    Evidence: .sisyphus/evidence/task-1-own-number-section.png

  Scenario: Registering own number without input shows warning
    Tool: Playwright
    Preconditions: Own number radio selected, phone input empty
    Steps:
      1. Click "Register This Number" with empty input
      2. Assert a warning notification appears with title "Phone Number Required"
    Expected Result: No registration attempted, warning notification shown
    Evidence: .sisyphus/evidence/task-1-own-number-empty-warning.png
  ```

  **Commit**: YES
  - Message: `feat(whatsapp-setup): add own phone number option in Step 3`
  - Files: `app/Filament/Admin/Pages/WhatsAppSetup.php`
  - Pre-commit: `vendor/bin/pint --dirty`

---

- [ ] 2. Write/update PHPUnit tests

  **What to do**:
  - Open (or create) `tests/Feature/Filament/WhatsAppSetupTest.php`
  - Add test methods covering:
    1. `test_step3_shows_phone_number_source_radio` — Assert the radio component exists with both options
    2. `test_step3_own_number_path_registers_phone_number` — Fill `phone_number_source = use_own_number`, fill `own_phone_number = +12345678901`, call `registerOwnPhoneNumber`, assert `WhatsAppPhoneNumber` record created
    3. `test_step3_own_number_without_input_sends_warning_notification` — Call `registerOwnPhoneNumber` with blank `own_phone_number`, assert notification sent with `warning` type

  **Must NOT do**:
  - Do not delete existing tests
  - Do not use Pest syntax — PHPUnit only

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Sequential, after Task 1
  - **Blocks**: Nothing
  - **Blocked By**: Task 1

  **References**:
  - `tests/Feature/Filament/WhatsAppSetupTest.php` — existing test file to extend
  - `app/Filament/Admin/Pages/WhatsAppSetup.php` — the page being tested
  - `app/Models/WhatsAppPhoneNumber.php` — assert database record

  **Acceptance Criteria**:
  - [ ] `php artisan test --compact --filter=WhatsAppSetupTest` passes

  **QA Scenarios**:

  ```
  Scenario: Tests pass
    Tool: Bash
    Steps:
      1. Run: php artisan test --compact tests/Feature/Filament/WhatsAppSetupTest.php
      2. Assert: 0 failures
    Expected Result: All tests green
    Evidence: .sisyphus/evidence/task-2-tests-pass.txt
  ```

  **Commit**: YES (group with Task 1 or separate)
  - Message: `test(whatsapp-setup): add tests for own phone number registration in Step 3`
  - Files: `tests/Feature/Filament/WhatsAppSetupTest.php`

---

## Final Verification Wave

- [ ] F1. **Pint formatting** — Run `vendor/bin/pint --dirty` after all edits and confirm zero formatting changes needed.
- [ ] F2. **PHP syntax check** — Run `php -l app/Filament/Admin/Pages/WhatsAppSetup.php` and confirm "No syntax errors detected".
- [ ] F3. **Test suite** — Run `php artisan test --compact tests/Feature/Filament/WhatsAppSetupTest.php` and confirm all pass.

---

## Commit Strategy

- **Task 1**: `feat(whatsapp-setup): add own phone number option in Step 3` — `app/Filament/Admin/Pages/WhatsAppSetup.php`
- **Task 2**: `test(whatsapp-setup): add tests for own phone number registration in Step 3` — `tests/Feature/Filament/WhatsAppSetupTest.php`

---

## Success Criteria

```bash
php -l app/Filament/Admin/Pages/WhatsAppSetup.php
# Expected: No syntax errors detected

vendor/bin/pint --dirty
# Expected: No files changed (already formatted)

php artisan test --compact tests/Feature/Filament/WhatsAppSetupTest.php
# Expected: All tests pass, 0 failures
```

### Final Checklist
- [ ] Radio with two options present in Step 3
- [ ] Amber warning box with all 4 Meta requirements visible on own-number path
- [ ] Twilio purchase flow still works and is the default
- [ ] `registerOwnPhoneNumber()` method saves to `WhatsAppPhoneNumber` and advances setup
- [ ] All existing behavior unchanged
- [ ] Tests pass
