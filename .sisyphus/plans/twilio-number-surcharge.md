# Twilio Phone Number Cost Surcharge

## TL;DR

> **Quick Summary**: Add a dynamic surcharge to WhatsApp package purchases for tenants using Twilio provider. The surcharge equals the actual Twilio phone number monthly cost (USD → INR) and is collected periodically (~every 30 days) when tenants buy packages. Phone number purchase during WhatsApp setup remains free.
>
> **Deliverables**:
> - 2 new migrations (phone number `last_number_cost_charged_at` + transaction `phone_number_surcharge`)
> - Updated `WhatsAppPhoneNumber` model with surcharge helper
> - Updated `WhatsAppAccount` model with Twilio detection helper
> - Updated `WhatsAppCreditTransaction` model with surcharge column
> - Updated `WhatsAppBillingService` to handle surcharge in purchases
> - Updated `PurchaseCredits` page with surcharge detection + payment logic
> - Updated blade view with surcharge line item display
> - PHPUnit feature test for surcharge flow
>
> **Estimated Effort**: Medium
> **Parallel Execution**: YES - 4 waves
> **Critical Path**: Migrations → Models → Billing Service → PurchaseCredits → View → Tests

---

## Context

### Original Request
Charge tenants for Twilio phone number cost, but NOT at purchase time during WhatsApp setup. Instead, dynamically increase WhatsApp package price when a tenant has Twilio provider setup with an active phone number.

### Interview Summary
**Key Decisions**:
- **Approach**: Dynamic surcharge at checkout (Option A) — adds phone's `monthly_cost × exchange_rate` on top of package price
- **Frequency**: Periodic Recovery — surcharge re-collected every ~30 days from last charge (`last_number_cost_charged_at` tracking)
- **Margin**: Pass-through (no markup) — charges exact Twilio cost in INR
- **Tests**: YES (Tests After) — PHPUnit feature tests for the surcharge flow
- **Phone number purchase during setup**: Remains free — no charge at that point

### Research Findings
- `WhatsAppPackage` already has `twilio_number_cost`, `twilio_msg_cost_per_unit`, `includes_number` fields — but `twilio_number_cost` is only used in `yourProfit()` calculation, never charged to tenant
- `WhatsAppPhoneNumber.monthly_cost` stores actual Twilio monthly cost per-tenant (decimal column)
- `config('services.twilio.exchange_rate', 90.0)` exists — converts USD to INR
- `WhatsAppBillingService::purchasePackage()` uses Cache::lock + DB::transaction — safe concurrent pattern
- Razorpay order amount is `package.selling_price` — needs modification for surcharge
- `PurchaseCredits` loads packages via `WhatsAppPackage::active()->ordered()->get()->toArray()`

---

## Work Objectives

### Core Objective
Add Twilio phone number monthly cost as a dynamic surcharge to WhatsApp package purchases for eligible Twilio tenants.

### Concrete Deliverables
- Database migrations adding tracking columns
- Helper methods on models for surcharge detection and calculation
- Modified billing service accepting and recording surcharge
- Modified PurchaseCredits page calculating surcharge and adjusting Razorpay orders
- Blade view showing surcharge line item
- PHPUnit feature test verifying the flow

### Definition of Done
- [ ] Tenant with Twilio provider + active phone number sees surcharge in purchase breakdown
- [ ] Razorpay order total = package `selling_price` + surcharge
- [ ] Surcharge recorded in `WhatsAppCreditTransaction.phone_number_surcharge`
- [ ] `WhatsAppPhoneNumber.last_number_cost_charged_at` updated after successful payment
- [ ] Surcharge NOT re-added for ≥30 days after last charge
- [ ] Tenant without Twilio provider sees standard pricing (no surcharge)
- [ ] `php artisan test --filter=TwilioNumberSurchargeTest` → PASS

### Must Have
- Surcharge detection: Twilio provider + active phone + last charged > 30 days (or never)
- Surcharge calculation: `phone.monthly_cost × exchange_rate`, rounded to 2 decimals
- Surcharge display: shown as separate line item before Razorpay modal
- Surcharge recording: stored in transaction record, timestamp updated on phone record

### Must NOT Have (Guardrails)
- Do NOT modify phone number purchase flow (remains free during setup)
- Do NOT change WABA provider pricing (surcharge only for Twilio)
- Do NOT add subscription/invoice infrastructure
- Do NOT modify the existing credit deduction logic
- Do NOT change `WhatsAppPackage` model fields or package admin CRUD

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed. No exceptions.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit/Pest)
- **Automated tests**: YES (Tests After) — feature test for surcharge flow
- **Framework**: PHPUnit (via `php artisan test`)

### QA Policy
Every task MUST include agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

- **Database changes**: Use `php artisan migrate:fresh --seed` + `php artisan db:seed` then verify columns exist
- **Models**: Use `php artisan tinker` to test helper methods
- **API/Backend**: Use `php artisan test` with specific filter
- **UI**: Use Playwright to navigate PurchaseCredits page, verify surcharge display

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation - DB + Models):
├── Task 1: Migration — add last_number_cost_charged_at to whatsapp_phone_numbers
├── Task 2: Migration — add phone_number_surcharge to whatsapp_credit_transactions
├── Task 3: Update WhatsAppPhoneNumber model (cast + helper)
├── Task 4: Update WhatsAppCreditTransaction model (fillable + cast)
└── Task 5: Update WhatsAppAccount model (isTwilioWithActiveNumber helper)

Wave 2 (Core Logic):
├── Task 6: Update WhatsAppBillingService (accept + record surcharge)
└── Task 7: Add surcharge service method for detection + calculation

Wave 3 (UI + Integration):
├── Task 8: Update PurchaseCredits page (detect, add to order, mark charged)
└── Task 9: Update blade view (display surcharge line item)

Wave 4 (Tests + Verification):
├── Task 10: PHPUnit feature test for surcharge flow
└── FINAL: 4 parallel reviews

Critical Path: Task 1/2 → Task 3/4/5 → Task 6/7 → Task 8 → Task 9 → Task 10 → FINAL
```

### Agent Dispatch Summary
- **Wave 1**: 5 tasks — `quick` (migrations + model updates)
- **Wave 2**: 2 tasks — `unspecified-high` (service logic)
- **Wave 3**: 2 tasks — `unspecified-high` (page + view)
- **Wave 4**: 1 task — `unspecified-high` (tests)
- **FINAL**: 4 parallel reviews

---

## TODOs

- [ ] 1. **Migration — add `last_number_cost_charged_at` to `whatsapp_phone_numbers`**

  **What to do**:
  - Create a new migration: `php artisan make:migration add_last_number_cost_charged_at_to_whatsapp_phone_numbers`
  - Add nullable `timestamp` column `last_number_cost_charged_at` to `whatsapp_phone_numbers` table
  - Add `down()` method to drop the column
  - Run `php artisan migrate` to verify

  **Must NOT do**:
  - Do NOT modify existing columns
  - Do NOT drop or recreate the table

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple single-column migration, low complexity
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Migration conventions, Laravel schema builder syntax

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2-5)
  - **Blocks**: Tasks 3 (model update)
  - **Blocked By**: None

  **References**:
  - `database/migrations/2026_03_14_000000_add_channel_sender_sid_to_whatsapp_phone_numbers_table.php` — Pattern for adding column to whatsapp_phone_numbers
  - `app/Models/WhatsAppPhoneNumber.php:76-82` — Existing casts to follow for new column

  **Acceptance Criteria**:
  - [ ] Migration file exists with correct `up()` and `down()` methods
  - [ ] `php artisan migrate` completes without error
  - [ ] Column `last_number_cost_charged_at` exists in `whatsapp_phone_numbers` table (nullable timestamp)

  **QA Scenarios**:
  ```
  Scenario: Migration applies and rolls back cleanly
    Tool: Bash
    Preconditions: Fresh database (php artisan migrate:fresh)
    Steps:
      1. Run migration: php artisan migrate
      2. Check column exists: Run SQL query via artisan tinker: `Schema::hasColumn('whatsapp_phone_numbers', 'last_number_cost_charged_at')`
      3. Rollback: php artisan migrate:rollback
      4. Verify column removed: Same tinker check → false
    Expected Result: Column exists after migrate, removed after rollback
    Evidence: .sisyphus/evidence/task-1-migration-column.txt
  ```

  **Evidence to Capture**:
  - [ ] Migration apply + column check output
  - [ ] Rollback + column removal confirmation

  **Commit**: YES
  - Message: `feat(whatsapp): add last_number_cost_charged_at to phone numbers migration`
  - Files: `database/migrations/*_add_last_number_cost_charged_at_to_whatsapp_phone_numbers.php`
  - Pre-commit: `php artisan migrate:fresh --seed`

- [ ] 2. **Migration — add `phone_number_surcharge` to `whatsapp_credit_transactions`**

  **What to do**:
  - Create a new migration: `php artisan make:migration add_phone_number_surcharge_to_whatsapp_credit_transactions`
  - Add nullable `decimal:2` column `phone_number_surcharge` (default: 0.00) to `whatsapp_credit_transactions` table
  - Add `down()` method to drop the column
  - Run `php artisan migrate` to verify

  **Must NOT do**:
  - Do NOT modify existing columns on this table

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3-5)
  - **Blocks**: Task 4 (model update)
  - **Blocked By**: None

  **References**:
  - `database/migrations/2026_02_12_133640_create_whatsapp_credit_transactions_table.php` — Existing table structure
  - `app/Models/WhatsAppCreditTransaction.php:46-73` — Existing model structure

  **Acceptance Criteria**:
  - [ ] Migration file exists with correct `up()` and `down()`
  - [ ] `php artisan migrate` completes without error
  - [ ] Column `phone_number_surcharge` exists in `whatsapp_credit_transactions` (nullable decimal, 2 places)

  **QA Scenarios**:
  ```
  Scenario: Migration applies and rolls back
    Tool: Bash
    Preconditions: Fresh database after Task 1 migration
    Steps:
      1. Run: php artisan migrate
      2. Check: Schema::hasColumn('whatsapp_credit_transactions', 'phone_number_surcharge') → true
      3. Rollback: php artisan migrate:rollback
      4. Check removed: Schema::hasColumn → false
    Expected Result: Column exists after migrate, removed after rollback
    Evidence: .sisyphus/evidence/task-2-migration-column.txt
  ```

  **Evidence to Capture**:
  - [ ] Migration apply + column check output

  **Commit**: YES (groups with Task 1)
  - Message: `feat(whatsapp): add phone_number_surcharge to credit transactions migration`

- [ ] 3. **Update WhatsAppPhoneNumber model — add cast + surcharge helper**

  **What to do**:
  - Add `'last_number_cost_charged_at' => 'datetime'` to the `casts()` method
  - Add `isSurchargeDue(): bool` method:
    - Returns `false` if `last_number_cost_charged_at` is null AND no purchase ever made (first-time setup)
    - Actually: Returns `true` if `last_number_cost_charged_at === null` (never charged) OR `last_number_cost_charged_at->diffInDays(now()) >= 30`
  - Add `getSurchargeAmount(): float` method:
    - Returns `round((float) $this->monthly_cost * (float) config('services.twilio.exchange_rate', 90.0), 2)`
  - Verify with `php artisan tinker`

  **Must NOT do**:
  - Do NOT add to `$fillable` (managed by migration timestamp default)
  - Do NOT modify existing helper methods

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocked By**: Task 1 (column must exist)
  - **Blocks**: Task 7 (surcharge service)

  **References**:
  - `app/Models/WhatsAppPhoneNumber.php:73-82` — Existing casts method to append to
  - `app/Models/WhatsAppPhoneNumber.php:84-184` — Existing helper methods for pattern
  - `config/services.php:110` — `exchange_rate` config key

  **Acceptance Criteria**:
  - [ ] `WhatsAppPhoneNumber` has `last_number_cost_charged_at` cast as `datetime`
  - [ ] `isSurchargeDue()` returns correct values for null/old/recent timestamps
  - [ ] `getSurchargeAmount()` returns calculated value: `monthly_cost × exchange_rate`

  **QA Scenarios**:
  ```
  Scenario: Surcharge helper methods work correctly
    Tool: Bash (php artisan tinker)
    Preconditions: WhatsAppPhoneNumber record exists with monthly_cost=1.50
    Steps:
      1. Check isSurchargeDue when last_number_cost_charged_at is null → true
      2. Check isSurchargeDue when last_number_cost_charged_at is 40 days ago → true
      3. Check isSurchargeDue when last_number_cost_charged_at is 5 days ago → false
      4. Check getSurchargeAmount returns 135.0 (1.50 × 90.0)
    Expected Result: All 4 assertions pass
    Evidence: .sisyphus/evidence/task-3-model-helpers.txt
  ```

  **Evidence to Capture**:
  - [ ] Tinker output for all 4 scenarios

  **Commit**: YES (groups with Tasks 1-5 as a wave commit)
  - Message: `feat(whatsapp): update WhatsAppPhoneNumber model with surcharge helpers`

- [ ] 4. **Update WhatsAppCreditTransaction model — add surcharge column**

  **What to do**:
  - Add `'phone_number_surcharge'` to the `$fillable` array
  - Add `'phone_number_surcharge' => 'decimal:2'` to the `casts()` method

  **Must NOT do**:
  - Do NOT modify other fillable fields or casts

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocked By**: Task 2 (column must exist)
  - **Blocks**: Task 6 (billing service)

  **References**:
  - `app/Models/WhatsAppCreditTransaction.php:50-61` — Existing $fillable array
  - `app/Models/WhatsAppCreditTransaction.php:64-73` — Existing casts method

  **Acceptance Criteria**:
  - [ ] `phone_number_surcharge` in `$fillable`
  - [ ] `phone_number_surcharge` in `casts()` as `decimal:2`
  - [ ] `php artisan test` doesn't break (models still work)

  **QA Scenarios**:
  ```
  Scenario: Model fillable and cast updated correctly
    Tool: Bash (tinker)
    Preconditions: Migration from Task 2 applied
    Steps:
      1. Create a test transaction with phone_number_surcharge = 135.0
      2. Retrieve and assert phone_number_surcharge === 135.0
      3. Check it's cast to numeric type
    Expected Result: Model persists and retrieves surcharge correctly
    Evidence: .sisyphus/evidence/task-4-transaction-model.txt
  ```

  **Evidence to Capture**:
  - [ ] Tinker create + retrieve output

  **Commit**: YES (groups with Wave 1)
  - Message: `feat(whatsapp): update WhatsAppCreditTransaction model with phone_number_surcharge`

- [ ] 5. **Update WhatsAppAccount model — add `isTwilioWithActiveNumber()` helper**

  **What to do**:
  - Add method `isTwilioWithActiveNumber(): bool`:
    - Returns `true` if `$this->provider_type === WhatsAppProviderTypeEnum::TWILIO` AND `$this->phoneNumber?->isActive()`
    - Returns `false` otherwise
  - Add method `isPhoneSurchargeDue(): bool`:
    - Returns `false` if `! $this->isTwilioWithActiveNumber()`
    - Returns `$this->phoneNumber->isSurchargeDue()` (delegating to WhatsAppPhoneNumber)

  **Must NOT do**:
  - Do NOT modify existing helper methods on this model
  - Do NOT add relationships that don't exist (`phoneNumber` already exists as HasOne)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1
  - **Blocked By**: None (uses existing relationships)
  - **Blocks**: Task 7, Task 8

  **References**:
  - `app/Models/WhatsAppAccount.php:132-135` — Existing `phoneNumber()` HasOne relationship
  - `app/Models/WhatsAppAccount.php:170-193` — Existing helper methods for pattern (`hasPhoneNumber()`, `isActive()`)
  - `app/Enums/WhatsAppProviderTypeEnum.php` — TWILIO case value

  **Acceptance Criteria**:
  - [ ] `isTwilioWithActiveNumber()` returns true for Twilio accounts with active phone number
  - [ ] `isTwilioWithActiveNumber()` returns false for WABA accounts
  - [ ] `isTwilioWithActiveNumber()` returns false for Twilio accounts without active phone number
  - [ ] `isPhoneSurchargeDue()` delegates correctly

  **QA Scenarios**:
  ```
  Scenario: Helper methods work correctly for different account types
    Tool: Bash (tinker)
    Preconditions: 
      - One WhatsAppAccount with provider_type=TWILIO + active phone
      - One WhatsAppAccount with provider_type=WABA
    Steps:
      1. Call isTwilioWithActiveNumber() on Twilio account with phone → true
      2. Call isTwilioWithActiveNumber() on WABA account → false
      3. Call isPhoneSurchargeDue() on Twilio account with phone (never charged) → true
    Expected Result: Methods return correct values per account type
    Evidence: .sisyphus/evidence/task-5-account-helpers.txt
  ```

  **Evidence to Capture**:
  - [ ] Tinker output for each scenario

  **Commit**: YES (groups with Wave 1)
  - Message: `feat(whatsapp): add isTwilioWithActiveNumber helper to WhatsAppAccount`

- [ ] 6. **Update WhatsAppBillingService — accept and record surcharge in purchases**

  **What to do**:
  - Modify `purchasePackage(int $gymId, int $packageId, string $paymentReference, ?int $userId = null, float $phoneNumberSurcharge = 0.0): array`
  - In the DB transaction, after creating the WhatsAppCreditTransaction:
    - If `$phoneNumberSurcharge > 0`, update the transaction: `$transaction->update(['phone_number_surcharge' => $phoneNumberSurcharge])`
    - OR better: pass it directly in the `WhatsAppCreditTransaction::create()` call
  - Add `$phoneNumberSurcharge` to the return array for transparency
  - Return `phone_number_surcharge` in the success response

  **Must NOT do**:
  - Do NOT modify the core credit addition logic (total_credits, remaining_credits increments)
  - Do NOT change the Cache::lock or DB::transaction patterns

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Business logic change in existing transactional service
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Task 7)
  - **Blocked By**: Task 4 (transaction model must have fillable)
  - **Blocks**: Task 8 (PurchaseCredits)

  **References**:
  - `app/Services/WhatsAppBillingService.php:29-83` — Existing `purchasePackage()` method
  - `app/Services/WhatsAppBillingService.php:53-65` — Existing WhatsAppCreditTransaction::create call

  **Acceptance Criteria**:
  - [ ] `purchasePackage()` accepts optional `$phoneNumberSurcharge` parameter (default 0.0)
  - [ ] Transaction records `phone_number_surcharge` when provided
  - [ ] Existing purchases without surcharge still work (backward compatible)
  - [ ] Return array includes `phone_number_surcharge` key

  **QA Scenarios**:
  ```
  Scenario: Purchase with surcharge records it correctly
    Tool: Bash (tinker)
    Preconditions: WhatsAppPackage + WhatsAppMessageCredit exist for gym
    Steps:
      1. Create WhatsAppBillingService instance
      2. Call purchasePackage(gymId, packageId, 'test_ref', null, 135.0)
      3. Check the latest WhatsAppCreditTransaction has phone_number_surcharge = 135.0
    Expected Result: Surcharge recorded in transaction
    Evidence: .sisyphus/evidence/task-6-billing-surcharge.txt

  Scenario: Purchase without surcharge still works (backward compat)
    Tool: Bash (tinker)
    Preconditions: Same as above
    Steps:
      1. Call purchasePackage(gymId, packageId, 'test_ref_2', null, 0.0)
      2. Check transaction phone_number_surcharge is null or 0.0
    Expected Result: No surcharge recorded, existing flow intact
    Evidence: .sisyphus/evidence/task-6-billing-no-surcharge.txt
  ```

  **Evidence to Capture**:
  - [ ] Transaction record output for both scenarios

  **Commit**: YES
  - Message: `feat(whatsapp): add phone number surcharge support to billing service`

- [ ] 7. **Add surcharge detection + calculation service logic**

  **What to do**:
  - Create a service method (or add to an existing service) that centralizes surcharge logic:
  - In `WhatsAppBillingService` or a new method in `PurchaseCredits`:
    - `getTwilioNumberSurcharge(int $gymId): array{eligible: bool, amount: float, phone: ?WhatsAppPhoneNumber}`
    - Look up `WhatsAppAccount::where('gym_id', $gymId)->with('phoneNumber')->first()`
    - If account is Twilio (`provider_type === TWILIO`) AND has active phone number:
      - Check `phone->isSurchargeDue()`
      - If due: calculate amount via `phone->getSurchargeAmount()`
      - Return: `['eligible' => true, 'amount' => calculated, 'phone' => phone]`
    - Otherwise: `['eligible' => false, 'amount' => 0.0, 'phone' => null]`
  - This method will be called by PurchaseCredits

  **Must NOT do**:
  - Do NOT create a new service file unless existing ones don't fit
  - Do NOT add database queries in blade views

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2
  - **Blocked By**: Tasks 3, 5 (model helpers)
  - **Blocks**: Task 8

  **References**:
  - `app/Models/WhatsAppAccount.php` — Has `phoneNumber` relationship
  - `app/Enums/WhatsAppProviderTypeEnum.php` — TWILIO enum case
  - `app/Filament/Admin/Pages/WhatsApp/PurchaseCredits.php:48-58` — Existing `loadData()` pattern for gym queries

  **Acceptance Criteria**:
  - [ ] Method returns `eligible=true` + correct amount for Twilio tenant with due surcharge
  - [ ] Method returns `eligible=false` for Twilio tenant with recently-charged phone
  - [ ] Method returns `eligible=false` for WABA tenant

  **QA Scenarios**:
  ```
  Scenario: Surcharge detection for eligible tenant
    Tool: Bash (tinker)
    Preconditions: WhatsAppAccount with TWILIO provider, active phone, no last_number_cost_charged_at
    Steps:
      1. Call detection method with this gym's ID
      2. Assert eligible === true
      3. Assert amount === 135.0 (assuming monthly_cost=1.50, exchange=90)
    Expected Result: Eligible with correct amount
    Evidence: .sisyphus/evidence/task-7-surcharge-detection-eligible.txt

  Scenario: Surcharge skipped for recently charged tenant
    Tool: Bash (tinker)
    Preconditions: Same as above but last_number_cost_charged_at = now()->subDays(5)
    Steps:
      1. Call detection method
      2. Assert eligible === false
    Expected Result: Not eligible (recently charged)
    Evidence: .sisyphus/evidence/task-7-surcharge-detection-recent.txt

  Scenario: Surcharge skipped for WABA tenant
    Tool: Bash (tinker)
    Preconditions: WhatsAppAccount with WABA provider
    Steps:
      1. Call detection method
      2. Assert eligible === false
    Expected Result: Not eligible (wrong provider)
    Evidence: .sisyphus/evidence/task-7-surcharge-detection-waba.txt
  ```

  **Evidence to Capture**:
  - [ ] All 3 scenario outputs

  **Commit**: YES
  - Message: `feat(whatsapp): add surcharge detection and calculation logic`

- [ ] 8. **Update PurchaseCredits page — detect surcharge, adjust order, mark charged**

  **What to do**:
  - In `loadData()` or a new `loadSurchargeData()`:
    - Call surcharge detection method for current gym
    - Store `$this->phoneNumberSurcharge` and `$this->showSurcharge` properties on the page
  - Add new Livewire property: `public float $phoneNumberSurcharge = 0.0;` and `public bool $showSurcharge = false;`
  - In `initiatePayment()`:
    - If `$this->showSurcharge` and `$this->phoneNumberSurcharge > 0`:
      - Calculate order amount as: `$package->selling_price + $this->phoneNumberSurcharge`
      - Add notes to Razorpay order: `'phone_number_surcharge' => $this->phoneNumberSurcharge`
  - In `handlePaymentVerify()`:
    - After `WhatsAppBillingService::purchasePackage()` succeeds:
      - If `$this->phoneNumberSurcharge > 0`:
        - Find the tenant's `WhatsAppPhoneNumber` and update: `$phone->update(['last_number_cost_charged_at' => now()])`
    - Pass `$this->phoneNumberSurcharge` to `purchasePackage()` call
  - Add method `getFormattedSurcharge(): string` for blade: `'₹' . number_format($this->phoneNumberSurcharge, 2)`

  **Must NOT do**:
  - Do NOT modify the existing `selectPackage()` method behavior
  - Do NOT change how credits are added (only add surcharge to order amount)

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Multi-method update to a Livewire page component
  - **Skills**: [`laravel-specialist`]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 7)
  - **Parallel Group**: Sequential (Wave 3)
  - **Blocked By**: Tasks 6, 7 (service methods)
  - **Blocks**: Task 9 (blade view)

  **References**:
  - `app/Filament/Admin/Pages/WhatsApp/PurchaseCredits.php:33-42` — Existing properties pattern
  - `app/Filament/Admin/Pages/WhatsApp/PurchaseCredits.php:60-96` — Existing `selectPackage()` and `initiatePayment()` methods
  - `app/Filament/Admin/Pages/WhatsApp/PurchaseCredits.php:123-173` — Existing `handlePaymentVerify()` method

  **Acceptance Criteria**:
  - [ ] `showSurcharge` correctly reflects surcharge eligibility
  - [ ] Razorpay order amount includes surcharge when eligible
  - [ ] `last_number_cost_charged_at` updated after successful payment
  - [ ] Purchase without surcharge still works for non-Twilio tenants

  **QA Scenarios**:
  ```
  Scenario: Surcharge included in Razorpay order for eligible tenant
    Tool: Browser automation (Playwright)
    Preconditions: 
      - Logged in as Twilio tenant with active phone + no recent charge
      - Visit PurchaseCredits page
    Steps:
      1. Navigate to /admin/whatsapp/purchase-credits
      2. Verify page shows surcharge line item (text match: "Phone Number")
      3. Click "Select" on a package
      4. In Razorpay modal, verify the amount = package.price + surcharge
    Expected Result: Surcharge visible on page + included in payment
    Evidence: .sisyphus/evidence/task-8-surcharge-page.png
    Evidence: .sisyphus/evidence/task-8-surcharge-modal.png

  Scenario: No surcharge for non-Twilio tenant (WABA)
    Tool: Browser automation (Playwright)
    Preconditions:
      - Logged in as WABA tenant
      - Visit PurchaseCredits page
    Steps:
      1. Navigate to the page
      2. Verify NO surcharge line item is shown
      3. Click "Select" on a package
      4. Verify Razorpay amount = package.selling_price only
    Expected Result: Standard pricing without surcharge
    Evidence: .sisyphus/evidence/task-8-no-surcharge.png
  ```

  **Evidence to Capture**:
  - [ ] Screenshots showing surcharge display + payment modal
  - [ ] Screenshot showing no surcharge for non-Twilio tenant

  **Commit**: YES
  - Message: `feat(whatsapp): update PurchaseCredits with phone number surcharge`
  - Files: `app/Filament/Admin/Pages/WhatsApp/PurchaseCredits.php`

- [ ] 9. **Update blade view — display surcharge line item in pricing breakdown**

  **What to do**:
  - In `resources/views/filament/tenant/pages/whatsapp-purchase-credits.blade.php`:
    - After the `wa-message-info` div inside each pricing card (or in a new section visible when surcharge applies):
    - Add conditional block that shows when `$this->showSurcharge` is true:
      ```blade
      @if($this->showSurcharge)
          <div class="wa-surcharge-info">
              <div class="row">
                  <span class="label">Phone Number (monthly)</span>
                  <span class="value">{{ $this->getFormattedSurcharge() }}</span>
              </div>
          </div>
      @endif
      ```
    - Style the surcharge info to match the existing `wa-message-info` styling (same background, border-radius, padding)
    - Optionally add a small note: "One-time charge for your Twilio phone number. Charged every 30 days."
    - Add the CSS class styles for `wa-surcharge-info` in the existing `<style>` block

  **Must NOT do**:
  - Do NOT modify the main pricing card layout
  - Do NOT remove any existing functionality
  - Do NOT add surcharge to packages where it doesn't apply

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
    - Reason: Blade UI changes with conditional rendering
  - **Skills**: [`tailwindcss-development`, `fluxui-development`]

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Sequential (Wave 3, after Task 8)
  - **Blocked By**: Task 8 (page properties must exist)
  - **Blocks**: Task 10 (tests)

  **References**:
  - `resources/views/filament/tenant/pages/whatsapp-purchase-credits.blade.php:202-227` — Existing `wa-message-info` styling pattern
  - `resources/views/filament/tenant/pages/whatsapp-purchase-credits.blade.php:574-639` — Package card rendering loop

  **Acceptance Criteria**:
  - [ ] Surcharge line item visible on PurchaseCredits page for eligible tenants
  - [ ] Surcharge line item NOT visible for non-eligible tenants
  - [ ] Styling matches existing message info section (same design language)
  - [ ] Amount displays correctly formatted (e.g., "₹135.00")

  **QA Scenarios**:
  ```
  Scenario: Surcharge display for eligible tenant
    Tool: Playwright
    Preconditions: Twilio tenant with active phone + surcharge due
    Steps:
      1. Navigate to PurchaseCredits page
      2. Assert element containing "Phone Number (monthly)" is visible
      3. Assert amount text matches expected format "₹135.00"
      4. Assert the surcharge info box has same styling as message-info section
    Expected Result: Surcharge section visible with correct styling and amount
    Evidence: .sisyphus/evidence/task-9-surcharge-display.png

  Scenario: No surcharge for non-eligible tenant
    Tool: Playwright  
    Preconditions: WABA tenant
    Steps:
      1. Navigate to PurchaseCredits page
      2. Assert element "Phone Number (monthly)" does NOT exist
    Expected Result: No surcharge section for non-Twilio tenants
    Evidence: .sisyphus/evidence/task-9-no-surcharge.png
  ```

  **Evidence to Capture**:
  - [ ] Screenshots for both eligible and non-eligible tenants

  **Commit**: YES (groups with Task 8)
  - Message: `feat(whatsapp): display phone number surcharge on purchase credits page`

- [ ] 10. **PHPUnit feature test for surcharge flow**

  **What to do**:
  - Create test file: `php artisan make:test --phpunit --feature TwilioNumberSurchargeTest`
  - Test cases:
    1. `test_surcharge_is_detected_for_eligible_twilio_tenant`:
       - Create Gym + WhatsAppAccount (TWILIO, verified) + WhatsAppPhoneNumber (active, monthly_cost=1.50, no last_number_cost_charged_at)
       - Assert detection method returns eligible=true, amount=135.0
    2. `test_surcharge_not_detected_for_recently_charged_tenant`:
       - Same setup but last_number_cost_charged_at = now()->subDays(5)
       - Assert detection returns eligible=false
    3. `test_surcharge_not_detected_for_waba_tenant`:
       - Create Gym + WhatsAppAccount (WABA)
       - Assert detection returns eligible=false
    4. `test_purchase_package_with_surcharge_records_correctly`:
       - Create Gym + WhatsAppMessageCredit + WhatsAppPackage
       - Call WhatsAppBillingService::purchasePackage with surcharge=135.0
       - Assert transaction has phone_number_surcharge=135.0
    5. `test_purchase_package_without_surcharge_backward_compatible`:
       - Same setup, surcharge=0.0
       - Assert transaction has null or 0.0 surcharge
    6. `test_purchase_credits_detects_surcharge_for_twilio_tenant`:
       - Create Gym with Twilio setup + phone
       - Call PurchaseCredits page, assert `showSurcharge=true`

  **Must NOT do**:
  - Do NOT test external Razorpay API (mock it)
  - Do NOT use `Http::fake()` unless testing webhook callbacks
  - Do NOT remove existing test assertions

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`phpunit-best-practices`]

  **Parallelization**:
  - **Can Run In Parallel**: YES (standalone test)
  - **Parallel Group**: Wave 4
  - **Blocked By**: Tasks 1-9 (all implementation must be complete)
  - **Blocks**: None

  **References**:
  - `tests/Feature/WhatsAppTemplateServiceTest.php` — Existing feature test pattern
  - `app/Services/WhatsAppBillingService.php` — Service under test
  - `app/Filament/Admin/Pages/WhatsApp/PurchaseCredits.php` — Page under test
  - `phpunit-best-practices` skill: Test conventions and patterns

  **Acceptance Criteria**:
  - [ ] All 6 test cases pass
  - [ ] `php artisan test --compact --filter=TwilioNumberSurchargeTest` → PASS (N tests)
  - [ ] Test covers happy path (eligible), edge case (recently charged), and backward compat (no surcharge)

  **QA Scenarios**:
  ```
  Scenario: All test cases pass
    Tool: Bash
    Preconditions: Migrations applied, models updated
    Steps:
      1. Run: php artisan test --compact --filter=TwilioNumberSurchargeTest
      2. Assert exit code 0
      3. Assert output shows "PASS" or "OK"
    Expected Result: All 6+ tests pass
    Evidence: .sisyphus/evidence/task-10-test-results.txt

  Scenario: Individual test can be run in isolation
    Tool: Bash
    Preconditions: Same
    Steps:
      1. Run: php artisan test --compact --filter=test_surcharge_is_detected_for_eligible_twilio_tenant
      2. Assert single test passes
    Expected Result: Tests can be run individually
    Evidence: .sisyphus/evidence/task-10-test-individual.txt
  ```

  **Evidence to Capture**:
  - [ ] Full test suite output
  - [ ] Individual test output

  **Commit**: YES
  - Message: `test(whatsapp): add feature test for Twilio number surcharge flow`
  - Files: `tests/Feature/TwilioNumberSurchargeTest.php`

---

## Final Verification Wave

> 4 review agents run in PARALLEL. ALL must APPROVE. Present consolidated results to user and get explicit "okay" before completing.

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (read file, run migration checks, tinker model calls). For each "Must NOT Have": search codebase for forbidden patterns. Check evidence files exist in `.sisyphus/evidence/`. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `php -l` on all changed files. Run `vendor/bin/pint --dirty --format agent`. Run `php artisan test --compact --filter=TwilioNumberSurcharge`. Check for `dd()`, `dump()`, or debug code. Check AI slop: excessive comments, over-abstraction.
  Output: `Lint [PASS/FAIL] | Pint [PASS/FAIL] | Tests [N pass/N fail] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Start from clean state. Execute EVERY QA scenario from EVERY task — follow exact steps. Test cross-task integration (migration → model → service → page → display). Test edge cases: non-Twilio tenant, Twilio without phone, Twilio with recently charged phone.
  Output: `Scenarios [N/N pass] | Integration [N/N] | Edge Cases [N tested] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  Verify diff matches plan. No changes to phone number purchase flow. No changes to WABA pricing. No subscription infrastructure. Check `WhatsAppPackage` admin CRUD untouched.
  Output: `Tasks [N/N compliant] | Scope Creep [CLEAN/N issues] | VERDICT`

---

## Commit Strategy

- **1**: `feat(whatsapp): add last_number_cost_charged_at to phone numbers migration`
- **2**: `feat(whatsapp): add phone_number_surcharge to credit transactions migration`
- **3-5**: `feat(whatsapp): update models for surcharge tracking`
- **6-7**: `feat(whatsapp): add surcharge logic to billing service`
- **8**: `feat(whatsapp): update PurchaseCredits with surcharge handling`
- **9**: `feat(whatsapp): display surcharge line item on purchase page`
- **10**: `test(whatsapp): add feature test for number surcharge flow`

---

## Success Criteria

### Verification Commands
```bash
php artisan test --compact --filter=TwilioNumberSurchargeTest  # Expected: PASS (1 test, 5+ assertions)
php artisan migrate:fresh                                      # Expected: new columns applied without error
php artisan tinker --execute="(new App\Models\WhatsAppPhoneNumber)->getTable()"  # Expected: table accessible
```

### Final Checklist
- [ ] All "Must Have" present
- [ ] All "Must NOT Have" absent
- [ ] All tests pass
- [ ] Twilio tenant sees surcharge on purchase page
- [ ] Non-Twilio tenant sees standard pricing
- [ ] Surcharge not re-added within 30 days of last charge
