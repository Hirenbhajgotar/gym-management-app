# Partial Payments & Installment Tracking for Gym Members

## TL;DR

> **Quick Summary**: Implement installment/partial payment tracking for gym members. Currently the system assumes full upfront payment, but now members can pay in installments (e.g., 5000 of 10000 plan price). A new `member_payments_transactions` table tracks each payment event, `PaymentStatus` gets a `PARTIAL` enum, and `member_payments` becomes the parent billing record with total plan amount.
>
> **Deliverables**:
> - New `member_payments_transactions` table + migration
> - New `MemberPaymentTransaction` Eloquent model
> - Updated `PaymentStatus` enum with `PARTIAL`
> - Updated `MemberPayment`, `GymMember` models with transaction relationships
> - Updated `GymMemberResource` form/table to handle partial payments
> - Updated `PaymentsRelationManager` with installment display
> - Updated `GymMemberImport` to handle installment data
> - Updated `GymMemberExporter` to include transactions
> - Updated `CreateGymMember` wizard for partial payment flow
> - Updated gym access logic for partial payments
>
> **Estimated Effort**: Medium
> **Parallel Execution**: YES - 4 waves
> **Critical Path**: Migration → MemberPaymentTransaction model → PaymentsRelationManager transactions display

---

## Context

### Original Request
Handle partial payments for gym members. If a member pays in installments (e.g., plan price is 10000, member pays 5000 and starts gym, then pays 5000 next month), we need to track each payment installment/transaction in a new `member_payments_transactions` table. Update related files: GymMemberResource, GymMemberImport, GymMember model, MemberPayment model, PaymentStatus enum, GymMemberExporter. Handle import/export for payments, and update GymMemberResource to handle partial payments.

### Interview Summary
**Key Discussions**:
- **Partial Payment Access**: Members with partial payments get FULL gym access (not restricted)
- **UI Approach**: Always show "Amount Paid" field separate from plan price (no toggle needed)
- **Testing**: No automated tests; QA scenarios only (agent-executed verification)

**Research Findings**:
- `member_payments` table currently stores individual payment records with single `amount` field
- `PaymentStatus` enum has: PENDING, COMPLETED, FAILED, EXPIRED (no PARTIAL)
- `GymMember.payment_status` on the member tracks overall status
- `PaymentsRelationManager` handles payment CRUD in a wizard with password confirmation
- `CreateGymMember` wizard captures payment data in `mutateFormDataBeforeCreate` and creates payment in `afterCreate`
- `GymMemberImport` creates both member and single payment per row
- `MemberPaymentObserver` clears revenue report caches on payment changes
- Existing payments expire old ones when new ones are created
- `canAccessGym()` checks `payment_status !== COMPLETED` → blocks access if not completed

### Metis Review
(Metis consultation timed out; gaps addressed by self-review below)

---

## Work Objectives

### Core Objective
Enable partial payment/installment tracking for gym members by introducing a `member_payments_transactions` table, `PARTIAL` payment status, and updating all related UI, import/export, and access logic.

### Concrete Deliverables
- New database migration for `member_payments_transactions` table
- New `MemberPaymentTransaction` Eloquent model with relationships
- Updated `PaymentStatus` enum with `PARTIAL` case
- Updated `MemberPayment` model with `transactions()` hasMany relationship
- Updated `GymMember` model with computed payment methods and updated `canAccessGym()`
- Updated `MemberPaymentObserver` to handle transaction-based cache clearing
- Updated `GymMemberResource` form and table (payment details section)
- Updated `PaymentsRelationManager` with transaction history display and partial payment support
- Updated `CreateGymMember` page to support partial payment amounts
- Updated `GymMemberImport` to handle transaction rows
- Updated `GymMemberExporter` to include transaction columns
- Existing payment test verifies backward compatibility

### Definition of Done
- [ ] Migration runs: `php artisan migrate` → member_payments_transactions table created
- [ ] New MemberPaymentTransaction model exists with proper relationships
- [ ] PaymentStatus enum has PARTIAL with correct color/icon/label
- [ ] Creating a payment with amount < plan price creates MemberPayment with PARTIAL status + transaction
- [ ] Completing remaining balance auto-updates MemberPayment to COMPLETED
- [ ] PaymentsRelationManager shows transaction history per payment
- [ ] GymMemberImport handles transaction columns
- [ ] GymMemberExporter includes transaction data
- [ ] canAccessGym() allows PARTIAL payment status

### Must Have
- New `member_payments_transactions` table with migration
- `MemberPaymentTransaction` model with `memberPayment()` BelongsTo
- `PaymentStatus::PARTIAL` enum case (icon: heroicon-o-adjustments-horizontal, color: warning)
- `MemberPayment.transactions()` HasMany relationship
- `MemberPaymentTransaction.memberPayment()` BelongsTo relationship
- `MemberPayment` auto-calculates total_paid from transactions
- Payment form shows "Amount Paid" field separate from auto-filled plan price
- `canAccessGym()` returns true when `payment_status` is PARTIAL
- PaymentsRelationManager table shows transaction history as sub-rows or inline
- Import handles `payment_amount_paid`, `payment_installment`, `payment_method` columns
- Export includes `Total Paid`, `Remaining`, `Installments` columns

### Must NOT Have (Guardrails)
- Do NOT modify existing `member_payments` table schema (add columns via new migration only)
- Do NOT change existing completed payment behavior (backward compatible)
- Do NOT add recurring payment scheduling/automation
- Do NOT add email reminders for due installments
- Do NOT add late fee calculations
- Do NOT remove the existing `amount` field behavior (it remains as total plan price)
- Do NOT break revenue/insight caching logic in MemberPaymentObserver

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** - ALL verification is agent-executed. No exceptions.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: NO (per user preference)
- **Framework**: PHPUnit (already configured)
- **Agent-Executed QA**: ALWAYS - every task includes QA scenarios

### QA Policy
Every task MUST include agent-executed QA scenarios (see TODO template below).
Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

- **Database/migration**: Bash - Run migration, check table structure via SQL
- **Model/Model logic**: `tinker` - Create records, verify relationships, compute values
- **Filament UI**: Playwright - Navigate to page, fill forms, assert UI elements
- **Import**: Bash + database-query - Import CSV, verify created records
- **Export**: Bash - Run export, verify file contents

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation - 3 parallel tasks):
├── Task 1: Create migration for member_payments_transactions table
├── Task 2: Add PARTIAL to PaymentStatus enum
└── Task 3: Create MemberPaymentTransaction model

Wave 2 (Models & Logic - 4 parallel tasks):
├── Task 4: Update MemberPayment model (transactions relationship, computed totals)
├── Task 5: Update GymMember model (updated canAccessGym, total_paid helper)
├── Task 6: Create MemberPaymentTransactionObserver (optional - for cache clearing)
└── Task 7: Update MemberPaymentObserver (handles transaction-based changes)

Wave 3 (Filament UI - 4 parallel tasks):
├── Task 8: Update PaymentsRelationManager (transaction display, partial payment form)
├── Task 9: Update CreateGymMember wizard (amount paid field)
├── Task 10: Update GymMemberResource form/table (payment sections)
└── Task 11: Add/update Filament actions for adding installments

Wave 4 (Import/Export - 2 parallel tasks + final verification):
├── Task 12: Update GymMemberImport for transaction handling
├── Task 13: Update GymMemberExporter for transaction columns
└── Task F1-F4: Final Verification Wave (4 parallel reviews)

Critical Path: Task 1 → Task 4 → Task 8 → F1-F4
Parallel Speedup: ~60% faster than sequential
```

---

## TODOs

- [ ] 1. Create migration for `member_payments_transactions` table

  **What to do**:
  - Run `php artisan make:migration create_member_payments_transactions_table`
  - Create table with columns:
    - `id` (bigIncrements)
    - `member_payment_id` (foreignId → constrained to member_payments → cascadeOnDelete)
    - `amount` (decimal 8,2, nullable)
    - `currency` (string, default 'INR', nullable)
    - `payment_method` (string, default 'cash', nullable)
    - `transaction_date` (date, nullable)
    - `status` (string, default 'completed', nullable) — values: completed, pending, failed, refunded
    - `notes` (text, nullable)
    - `timestamps()`
  - Add indexes: `member_payment_id`, `status`, `transaction_date`
  - Follow the existing migration pattern from `2025_04_18_175505_create_member_payments_table.php`

  **Must NOT do**:
  - Do NOT modify the existing `member_payments` table structure
  - Do NOT add foreign key constraints that might fail on existing data

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Migration patterns and Laravel schema builder convention

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3)
  - **Blocks**: Tasks 4, 5, 6, 7, 8, 12
  - **Blocked By**: None

  **References**:
  - `database/migrations/2025_04_18_175505_create_member_payments_table.php` - Migration pattern to follow (column types, nullable, default values)
  - `database/migrations/2025_08_05_141849_add_gym_id_in_member_payments.php` - Foreign key constraint pattern
  - `app/Models/MemberPayment.php` - Checks which columns the parent model references

  **Acceptance Criteria**:
  - [ ] Migration file exists in database/migrations/
  - [ ] `php artisan migrate` runs without error
  - [ ] Table `member_payments_transactions` exists in database (verify via `Schema::hasTable()`)
  - [ ] Table has all specified columns with correct types

  **QA Scenarios**:
  ```
  Scenario: Migration creates table successfully
    Tool: interactive_bash (tmux)
    Preconditions: Database is migrated to latest
    Steps:
      1. Run: docker-compose exec app php artisan migrate
      2. Run: docker-compose exec app php artisan tinker --execute="echo Schema::hasTable('member_payments_transactions') ? 'YES' : 'NO';"
    Expected Result: Output is "YES"
    Evidence: .sisyphus/evidence/task-1-migration-table-exists.txt

  Scenario: Table columns match specification
    Tool: interactive_bash (tmux)
    Preconditions: Migration ran successfully
    Steps:
      1. Run: docker-compose exec app php artisan tinker --execute="print_r(Schema::getColumnListing('member_payments_transactions'));"
    Expected Result: Columns include: id, member_payment_id, amount, currency, payment_method, transaction_date, status, notes, created_at, updated_at
    Evidence: .sisyphus/evidence/task-1-table-columns.txt
  ```

- [ ] 2. Add `PARTIAL` case to `PaymentStatus` enum

  **What to do**:
  - Add `case PARTIAL = 'partial';` to `App\Enums\PaymentStatus`
  - Add match case in `getLabel()` → return 'Partial'
  - Add match case in `getColor()` → return 'warning' (indicates progress, not danger)
  - Add match case in `getIcon()` → return 'heroicon-o-adjustments-horizontal' (or similar progress icon)
  - Verify `toSelectOptions()` includes PARTIAL automatically (uses self::cases())

  **Must NOT do**:
  - Do NOT change existing enum cases or their values
  - Do NOT change existing getLabel/getColor/getIcon return values

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: PHP 8 enum conventions and Filament enum integration

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3)
  - **Blocks**: Tasks 4, 5, 8, 9, 10
  - **Blocked By**: None

  **References**:
  - `app/Enums/PaymentStatus.php` - Full file to modify (add PARTIAL case following existing pattern)
  - `vendor/filament/support/src/Contracts/HasColor.php` - Interface for getColor()
  - `vendor/filament/support/src/Contracts/HasIcon.php` - Interface for getIcon()

  **Acceptance Criteria**:
  - [ ] `PaymentStatus::PARTIAL` exists and has value 'partial'
  - [ ] `PaymentStatus::PARTIAL->getLabel()` returns 'Partial'
  - [ ] `PaymentStatus::PARTIAL->getColor()` returns 'warning'
  - [ ] `PaymentStatus::PARTIAL->getIcon()` returns a valid heroicon name
  - [ ] `PaymentStatus::toSelectOptions()` includes 'partial' => 'Partial'
  - [ ] Existing enum cases unchanged (PENDING, COMPLETED, FAILED, EXPIRED)

  **QA Scenarios**:
  ```
  Scenario: PARTIAL enum case exists with correct properties
    Tool: interactive_bash (tmux)
    Preconditions: File has been modified
    Steps:
      1. Run: docker-compose exec app php artisan tinker --execute="echo App\Enums\PaymentStatus::PARTIAL->value;"
      2. Run: docker-compose exec app php artisan tinker --execute="echo App\Enums\PaymentStatus::PARTIAL->getLabel();"
      3. Run: docker-compose exec app php artisan tinker --execute="echo App\Enums\PaymentStatus::PARTIAL->getColor();"
    Expected Result: Value="partial", Label="Partial", Color="warning"
    Evidence: .sisyphus/evidence/task-2-partial-enum.txt

  Scenario: toSelectOptions contains all cases including PARTIAL
    Tool: interactive_bash (tmux)
    Preconditions: File has been modified
    Steps:
      1. Run: docker-compose exec app php artisan tinker --execute="print_r(App\Enums\PaymentStatus::toSelectOptions());"
    Expected Result: Array has keys: pending, completed, failed, expired, partial
    Evidence: .sisyphus/evidence/task-2-select-options.txt
  ```


- [ ] 3. Create `MemberPaymentTransaction` model

  **What to do**:
  - Run `php artisan make:model MemberPaymentTransaction`
  - Use `App\Models\MemberPaymentTransaction`
  - Set `$fillable`: member_payment_id, amount, currency, payment_method, transaction_date, status, notes
  - Set `$casts`: amount → 'decimal:2', transaction_date → 'date', currency → Currency::class
  - Add relationship: `memberPayment(): BelongsTo` → `$this->belongsTo(MemberPayment::class)`
  - Add relationship: `memberPaymentTransactions()` on MemberPayment model (but that's Task 4)
  - Use `HasFactory` trait, `LogsActivity` trait (following MemberPayment pattern)
  - Set activity log options similar to MemberPayment model
  - Generate factory: `php artisan make:factory MemberPaymentTransactionFactory --model=MemberPaymentTransaction`

  **Must NOT do**:
  - Do NOT add relationships back to GymMember (go through MemberPayment)
  - Do NOT add `gym_id` or `member_id` to this model (it's linked through MemberPayment)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Eloquent model conventions, relationships, casts, fillable

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2)
  - **Blocks**: Tasks 4, 5, 6, 7, 8, 12, 13
  - **Blocked By**: Task 1 (migration must exist)

  **References**:
  - `app/Models/MemberPayment.php:72-103` - Follow exact pattern for fillable, casts, LogsActivity, HasFactory
  - `app/Enums/Currency.php` - Currency enum for casts
  - `app/Enums/PaymentMethod.php` - Payment method enum reference

  **Acceptance Criteria**:
  - [ ] Model file exists at `app/Models/MemberPaymentTransaction.php`
  - [ ] Factory file exists at `database/factories/MemberPaymentTransactionFactory.php`
  - [ ] `$fillable` contains all transaction columns
  - [ ] `$casts` has proper types for amount, transaction_date, currency
  - [ ] `memberPayment()` BelongsTo relationship defined
  - [ ] `LogsActivity` trait configured

  **QA Scenarios**:
  ```
  Scenario: Model can be instantiated and saved
    Tool: interactive_bash (tmux)
    Preconditions: Migration ran, model file exists
    Steps:
      1. First create a member payment: docker-compose exec app php artisan tinker --execute="\$mp = App\Models\MemberPayment::first(); if(!\$mp) { echo 'No member payment found - run seeder first'; exit; }"
      2. Create a transaction via model
    Expected Result: Model saves, relationship works
    Evidence: .sisyphus/evidence/task-3-model-create.txt

  Scenario: Relationship returns correct parent
    Tool: interactive_bash (tmux)
    Preconditions: Transaction record exists
    Steps:
      1. Get a transaction: \$t = App\Models\MemberPaymentTransaction::first()
      2. Get parent: \$t->memberPayment
    Expected Result: Returns the associated MemberPayment model instance
    Evidence: .sisyphus/evidence/task-3-relationship.txt
  ```

- [ ] 4. Update `MemberPayment` model with transactions relationship and computed totals

  **What to do**:
  - Add PHPDoc property: `@property-read Collection|MemberPaymentTransaction[] $transactions`
  - Add method: `transactions(): HasMany` → `$this->hasMany(MemberPaymentTransaction::class)`
  - Add method: `getTotalPaidAttribute(): string|float` — computes sum of completed transaction amounts:
    ```php
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->transactions()
            ->where('status', 'completed')
            ->sum('amount');
    }
    ```
  - Add method: `getRemainingAmountAttribute(): float` — returns max(0, total - paid)
    ```php
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->amount - $this->total_paid);
    }
    ```
  - Add method: `getIsFullyPaidAttribute(): bool` — returns true if no remaining amount
    ```php
    public function getIsFullyPaidAttribute(): bool
    {
        return $this->remaining_amount <= 0;
    }
    ```
  - Add relationship `memberPaymentTransactions()` as the HasMany (the relation method)
  - Update `getActivitylogOptions()` to include transactions summary
  - Add `$with` or `$withCount` for transactions if useful (optional, prefer lazy loading with `::with()`)

  **Must NOT do**:
  - Do NOT modify existing `$fillable` or `$casts`
  - Do NOT remove existing relationships (member, gym, membershipPlan)
  - Do NOT add automatic status update logic here (handled in observer/relation manager)

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Eloquent computed attributes, relationship patterns, accessor conventions

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 5, 6, 7)
  - **Blocks**: Tasks 8, 9, 10, 11, 12
  - **Blocked By**: Task 3 (MemberPaymentTransaction model must exist)

  **References**:
  - `app/Models/MemberPayment.php:72-103` - Existing model to extend
  - `app/Models/GymMember.php:206-209` - Existing HasMany relationship pattern (payments())
  - `app/Models/MemberPayment.php:150-158` - Computed attribute pattern (formattedAmount)

  **Acceptance Criteria**:
  - [ ] `$memberPayment->transactions` returns Collection of MemberPaymentTransaction
  - [ ] `$memberPayment->total_paid` returns sum of completed transaction amounts
  - [ ] `$memberPayment->remaining_amount` returns max(0, amount - total_paid)
  - [ ] `$memberPayment->is_fully_paid` returns true when remaining_amount <= 0
  - [ ] Existing tests still pass

  **QA Scenarios**:
  ```
  Scenario: Transactions relationship returns associated records
    Tool: interactive_bash (tmux)
    Preconditions: At least one MemberPayment has transactions
    Steps:
      1. Get or create a MemberPayment with transactions via tinker
      2. Assert $payment->transactions is a Collection
      3. Assert $payment->transactions->first() is MemberPaymentTransaction
    Expected Result: Relationship loads correctly
    Evidence: .sisyphus/evidence/task-4-relationship.txt

  Scenario: Computed attributes return correct values
    Tool: interactive_bash (tmux)
    Preconditions: MemberPayment exists with transactions totaling 5000 out of 10000
    Steps:
      1. Create payment: MemberPayment::create(['amount' => 10000, ...])
      2. Add transaction: $payment->transactions()->create(['amount' => 5000, 'status' => 'completed', ...])
      3. Assert $payment->total_paid == 5000
      4. Assert $payment->remaining_amount == 5000
      5. Assert $payment->is_fully_paid == false
    Expected Result: Computed attributes are correct
    Evidence: .sisyphus/evidence/task-4-computed-attrs.txt

  Scenario: Full payment scenario
    Tool: interactive_bash (tmux)
    Preconditions: Same setup
    Steps:
      1. Add second transaction: $payment->transactions()->create(['amount' => 5000, 'status' => 'completed', ...])
      2. Assert $payment->fresh()->is_fully_paid == true
      3. Assert $payment->fresh()->remaining_amount == 0
    Expected Result: Remaining drops to 0, is_fully_paid becomes true
    Evidence: .sisyphus/evidence/task-4-fully-paid.txt
  ```


- [ ] 5. Update `GymMember` model for partial payment support

  **What to do**:
  - Update `canAccessGym()` method to allow access when `payment_status` is PARTIAL:
    ```php
    public function canAccessGym(): bool
    {
        // Check if membership is active
        if (! $this->membership_end || $this->membership_end->isPast()) {
            return false;
        }

        // Check if payment is completed or partial (allow access with partial payment)
        if (! in_array($this->payment_status, [PaymentStatus::COMPLETED, PaymentStatus::PARTIAL])) {
            return false;
        }

        // Check if frozen
        if ($this->isFrozen()) {
            return false;
        }

        return true;
    }
    ```
  - Add helper method to get total paid across all payments:
    ```php
    public function getTotalPaymentsAmountAttribute(): float
    {
        return (float) $this->payments()
            ->with('transactions')
            ->get()
            ->sum(fn (MemberPayment $payment) => $payment->total_paid);
    }
    ```
  - Add helper to get outstanding balance:
    ```php
    public function getOutstandingBalanceAttribute(): float
    {
        // Total of all plan amounts minus total paid
        $totalPlans = (float) $this->payments()->sum('amount');
        return max(0, $totalPlans - $this->total_payments_amount);
    }
    ```
  - Update PHPDoc with `@property-read float $total_payments_amount`
  - Add `$appends` array or keep as computed attributes (follow existing pattern - check if others use $appends)

  **Must NOT do**:
  - Do NOT change existing `canAccessGym()` logic for COMPLETED, PENDING, FAILED, EXPIRED
  - Do NOT modify `$fillable` or `$casts`
  - Do NOT add database columns - all computed

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Accessor patterns, computed attributes, access control logic

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 6, 7)
  - **Blocks**: Tasks 8, 9, 10, 11
  - **Blocked By**: Task 4 (MemberPayment model updates)

  **References**:
  - `app/Models/GymMember.php:474-492` - Existing canAccessGym() to modify
  - `app/Enums/PaymentStatus.php` - PARTIAL enum reference
  - `app/Models/MemberPayment.php` - Relationship and computed attributes

  **Acceptance Criteria**:
  - [ ] `canAccessGym()` returns true when payment_status is PARTIAL
  - [ ] `canAccessGym()` returns false when payment_status is PENDING, FAILED, or EXPIRED (unchanged)
  - [ ] `$member->total_payments_amount` returns correct sum across all payments' completed transactions
  - [ ] `$member->outstanding_balance` returns correct remaining balance

  **QA Scenarios**:
  ```
  Scenario: canAccessGym allows partial payment member
    Tool: interactive_bash (tmux)
    Preconditions: GymMember with active membership + PARTIAL payment status
    Steps:
      1. Create GymMember with membership_start=2 months ago, membership_end=2 months from now
      2. Set payment_status=PARTIAL
      3. Assert $member->canAccessGym() returns true
    Expected Result: Partial payment members can access gym
    Evidence: .sisyphus/evidence/task-5-partial-access.txt

  Scenario: canAccessGym blocks pending payment member
    Tool: interactive_bash (tmux)
    Preconditions: GymMember with active membership but PENDING payment
    Steps:
      1. Create with payment_status=PENDING
      2. Assert canAccessGym() returns false
    Expected Result: Pending payment blocks access (unchanged behavior)
    Evidence: .sisyphus/evidence/task-5-pending-blocked.txt
  ```


- [ ] 6. Update `MemberPaymentObserver` for transaction-aware cache clearing

  **What to do**:
  - Update `clearCacheAndBroadcast()` method to also handle when transactions change
  - Add `created` listener for MemberPaymentTransaction (optional — handled via MemberPayment observer)
  - The MemberPaymentObserver already clears caches on MemberPayment created/updated/deleted
  - Since transactions are children of MemberPayment, any transaction change should also trigger cache clearing
  - Add a static helper method that can be called from transaction operations:
    ```php
    /**
     * Clear payment-related caches for a given gym.
     */
    public static function clearPaymentCaches(int $gymId): void
    {
        $service = app(ReportCacheService::class);
        $dashboardService = app(DashboardCacheService::class);
        
        $service->invalidateRevenueReports($gymId);
        $service->invalidateInsights($gymId);
        $dashboardService->invalidateRevenueWidgets($gymId);
        
        broadcast(new RevenueStatsUpdated($gymId));
    }
    ```
  - This allows anyone adding a transaction to call `MemberPaymentObserver::clearPaymentCaches($gymId)` directly

  **Must NOT do**:
  - Do NOT break existing cache clearing behavior
  - Do NOT add duplicate cache clearing calls
  - Do NOT modify the constructor or existing interface

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Observer patterns, cache invalidation, service container

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 5, 7)
  - **Blocks**: Task 8, 11
  - **Blocked By**: Task 1 (migration), Task 3 (model)

  **References**:
  - `app/Observers/MemberPaymentObserver.php` - Full file to modify
  - `app/Services/ReportCacheService.php` - Cache service methods
  - `app/Services/DashboardCacheService.php` - Dashboard cache methods
  - `app/Events/Reports/RevenueStatsUpdated.php` - Broadcast event

  **Acceptance Criteria**:
  - [ ] `clearPaymentCaches()` static method exists
  - [ ] Method invalidates revenue reports for the given gym
  - [ ] Method broadcasts RevenueStatsUpdated event
  - [ ] Existing observer methods still work (backward compatible)

  **QA Scenarios**:
  ```
  Scenario: Static clearPaymentCaches method works
    Tool: interactive_bash (tmux)
    Preconditions: Observer file updated
    Steps:
      1. Run: docker-compose exec app php artisan tinker --execute="App\Observers\MemberPaymentObserver::clearPaymentCaches(1);"
    Expected Result: No error thrown, method executes without exception
    Evidence: .sisyphus/evidence/task-6-cache-clear.txt
  ```


- [ ] 7. Auto-update `MemberPayment` status based on transactions

  **What to do**:
  Add a method to sync MemberPayment status based on transaction totals:
  - Add method to `MemberPayment` model:
    ```php
    /**
     * Sync the payment status based on completed transaction totals.
     * Call this after creating/updating transactions.
     */
    public function syncStatusFromTransactions(): void
    {
        if ($this->is_fully_paid) {
            $this->update(['status' => PaymentStatus::COMPLETED]);
        } elseif ($this->total_paid > 0) {
            $this->update(['status' => PaymentStatus::PARTIAL]);
        }
        // If total_paid is 0, leave current status as-is (could be PENDING/FAILED)
    }
    ```
  - This will be called from PaymentsRelationManager when adding transactions
  - Ensure it only updates if the status actually changed (use `update` vs `save` appropriately)

  **Must NOT do**:
  - Do NOT auto-change status to EXPIRED based on date (that's separate logic)
  - Do NOT revert COMPLETED to PARTIAL if transactions get deleted (that's intentional)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Model state management, observer patterns

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 5, 6)
  - **Blocks**: Tasks 8, 11
  - **Blocked By**: Task 4 (MemberPayment computed attributes)

  **References**:
  - `app/Enums/PaymentStatus.php` - PARTIAL and COMPLETED enum reference
  - `app/Models/MemberPayment.php` - Model to add method to
  - `app/Filament/Admin/Resources/GymMemberResource/RelationManagers/PaymentsRelationManager.php:315-336` - Where this will be called from

  **Acceptance Criteria**:
  - [ ] `syncStatusFromTransactions()` method exists on MemberPayment
  - [ ] When total_paid >= amount, status becomes COMPLETED
  - [ ] When total_paid > 0 but < amount, status becomes PARTIAL
  - [ ] When total_paid = 0, status unchanged

  **QA Scenarios**:
  ```
  Scenario: Partial payment sets PARTIAL status
    Tool: interactive_bash (tmux)
    Preconditions: MemberPayment with amount=10000
    Steps:
      1. Create payment with amount=10000, status=PENDING
      2. Add transaction: amount=5000, status=completed
      3. Call $payment->fresh()->syncStatusFromTransactions()
      4. Assert $payment->fresh()->status == PARTIAL
    Expected Result: Status changes to PARTIAL
    Evidence: .sisyphus/evidence/task-7-partial-status.txt

  Scenario: Full payment sets COMPLETED status
    Tool: interactive_bash (tmux)
    Preconditions: MemberPayment with status=PARTIAL
    Steps:
      1. Add second transaction: amount=5000, status=completed
      2. Call $payment->fresh()->syncStatusFromTransactions()
      3. Assert $payment->fresh()->status == COMPLETED
    Expected Result: Status changes to COMPLETED
    Evidence: .sisyphus/evidence/task-7-completed-status.txt
  ```


- [ ] 8. Update `PaymentsRelationManager` for partial payments UI

  **What to do**:
  - **Payment Form Changes**:
    - Add `TextInput::make('amount_paid')` field alongside existing `amount` field
    - `amount` = total plan price (auto-filled from plan selection)
    - `amount_paid` = what the member actually pays now (defaults to amount for full payment)
    - If `amount_paid < amount`, the payment will be created with status PARTIAL and a transaction for amount_paid
    - Add field description: "Enter the amount the member is paying now. If less than the plan price, remaining balance will be tracked."
  
  - **Transaction Display in Table**:
    - Add new columns after payment amount:
      - `TextColumn::make('total_paid')` → computed from transactions
      - `TextColumn::make('remaining_amount')` → computed
    - Add expandable row or inline section showing transactions
    - Use `Table\Columns\Layout\Stack` or modal to show transaction details
    - Show: date, amount, method, status per transaction

  - **Create Action**:
    - Modify `handleCreatePayment()` method:
      1. Create MemberPayment with amount = plan price
      2. Create MemberPaymentTransaction with amount_paid
      3. Call `$payment->syncStatusFromTransactions()`
      4. Update Member's GymMember record (payment_status, dates)
      5. Call `MemberPaymentObserver::clearPaymentCaches()`
    - Remove old "expire previous payment" logic since partial payments coexist

  - **Edit Action**:
    - Update to display existing transactions
    - Allow adding new transactions via the edit form

  - **Add "Add Installment" action**:
    - Action button on each payment row: "Add Payment"
    - Opens modal with: amount, payment_method, transaction_date, notes
    - On submit: creates new MemberPaymentTransaction, calls syncStatusFromTransactions(), updates GymMember

  - **Table modifyQueryUsing**:
    - Eager load transactions: `$query->with('transactions')`

  **Must NOT do**:
  - Do NOT remove the password confirmation step from payment operations
  - Do NOT break the existing "expire old payment" logic entirely — adjust it
  - Do NOT add complex nested table — keep transaction history as expandable section or modal

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high` (+ `fluxui-development` for UI)
  - **Skills**: [`laravel-specialist`, `fluxui-development`]
    - `laravel-specialist`: Filament RelationManager patterns, form customization
    - `fluxui-development`: Flux UI component conventions for Filament forms

  **Parallelization**:
  - **Can Run In Parallel**: YES (somewhat — depends on Tasks 4, 5, 7 but can start)
  - **Parallel Group**: Wave 3 (with Tasks 9, 10, 11)
  - **Blocks**: Task F1-F4
  - **Blocked By**: Tasks 4, 5, 7 (models and business logic)

  **References**:
  - `app/Filament/Admin/Resources/GymMemberResource/RelationManagers/PaymentsRelationManager.php` - Full file to modify
  - `app/Models/MemberPayment.php` - Computed attributes (total_paid, remaining_amount)
  - `app/Enums/PaymentStatus.php` - For status options
  - `app/Models/MemberPaymentTransaction.php` - For creating transactions
  - `app/Observers/MemberPaymentObserver.php` - For cache clearing

  **Acceptance Criteria**:
  - [ ] Payment form has `amount_paid` field (defaults to plan price)
  - [ ] Creating payment with amount_paid < amount creates MemberPayment with PARTIAL status + transaction
  - [ ] Creating payment with amount_paid = amount creates COMPLETED payment (backward compatible)
  - [ ] Payment table shows total_paid and remaining_amount columns
  - [ ] Transaction history is viewable per payment (expandable or modal)
  - [ ] "Add Installment" action works correctly
  - [ ] Password confirmation still required for payment operations
  - [ ] MemberPaymentObserver caches are cleared on transaction changes

  **QA Scenarios**:
  ```
  Scenario: Create partial payment via relation manager
    Tool: Playwright
    Preconditions: Logged in as admin/gym_owner, viewing a gym member's edit page
    Steps:
      1. Navigate to GymMember edit page
      2. Go to Payments tab
      3. Click "Create Payment" button
      4. Enter password
      5. Select membership plan (plan price = 10000)
      6. Enter amount_paid = 5000
      7. Select payment method = Cash
      8. Select payment date
      9. Select status = Completed (for the transaction)
      10. Submit
    Expected Result: Success notification. Payment shows amount=10000, total_paid=5000, remaining=5000, status=Partial
    Evidence: .sisyphus/evidence/task-8-partial-payment-create.png

  Scenario: Add installment to existing partial payment
    Tool: Playwright
    Preconditions: Partial payment exists with remaining balance
    Steps:
      1. Click "Add Installment" on the partial payment row
      2. Enter amount = 5000
      3. Select payment method = UPI
      4. Submit
    Expected Result: Success notification. Payment shows remaining=0, status=Completed
    Evidence: .sisyphus/evidence/task-8-add-installment.png
  ```


- [ ] 9. Update `CreateGymMember` wizard for partial payment support

  **What to do**:
  - In `getMembershipFormSchema()` → the Membership Details step already includes `GymMemberResource::getPaymentFormSchema()`
  - The payment form schema is in `GymMemberResource::getPaymentFormSchema()` — update that
  - Modify the payment details section:
    - Add `amount_paid` field (TextInput, numeric) alongside the existing `amount` field
    - `amount` = total plan price (auto-filled from plan selection)
    - `amount_paid` = what member actually pays at signup (default = plan price)
    - Add helper text: "If this is less than the plan price, remaining balance will be tracked as a partial payment"
  
  - In `mutateFormDataBeforeCreate()`:
    - Add `amount_paid` to the payment data extraction
    - Keep rest of logic unchanged
  
  - In `afterCreate()`:
    - After creating the MemberPayment record:
      - Create a MemberPaymentTransaction for the amount_paid
      - Call `$payment->syncStatusFromTransactions()`
      - Update GymMember with PARTIAL status if applicable

  **Must NOT do**:
  - Do NOT change the wizard structure or step ordering
  - Do NOT remove existing payment creation logic
  - Do NOT remove measurement creation logic

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`, `fluxui-development`]
    - `laravel-specialist`: Filament form schema, wizard step handling
    - `fluxui-development`: Flux UI component conventions

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 8, 10, 11)
  - **Blocks**: Task F1-F4
  - **Blocked By**: Tasks 4, 7 (models)

  **References**:
  - `app/Filament/Admin/Resources/GymMemberResource/Pages/CreateGymMember.php` - Full file to modify
  - `app/Filament/Admin/Resources/GymMemberResource.php:940-998` - getPaymentFormSchema()
  - `app/Models/MemberPaymentTransaction.php` - For creating transaction records
  - `app/Models/MemberPayment.php` - For syncStatusFromTransactions()

  **Acceptance Criteria**:
  - [ ] Create member form has `amount_paid` field in payment section
  - [ ] `amount_paid` defaults to plan price (full payment behavior unchanged)
  - [ ] Creating member with amount_paid < plan price → partial payment setup
  - [ ] Member's payment_status set to PARTIAL
  - [ ] MemberPaymentTransaction record created for amount_paid
  - [ ] Backward compatible: amount_paid = full price creates COMPLETED payment as before

  **QA Scenarios**:
  ```
  Scenario: Create member with full payment (backward compat)
    Tool: Playwright
    Preconditions: Logged in, membership plan exists
    Steps:
      1. Navigate to Create Gym Member
      2. Fill personal info step
      3. Go to Membership Details step - select plan, dates
      4. amount_paid auto-fills to plan price (10000)
      5. Complete wizard
    Expected Result: Member created, payment status = Completed
    Evidence: .sisyphus/evidence/task-9-full-payment.png

  Scenario: Create member with partial payment
    Tool: Playwright
    Preconditions: Logged in, membership plan exists
    Steps:
      1. Navigate to Create Gym Member
      2. Fill personal info step
      3. Go to Membership Details step - select plan
      4. Change amount_paid to 5000 (plan price is 10000)
      5. Complete wizard
    Expected Result: Member created, payment status = Partial, transaction recorded for 5000
    Evidence: .sisyphus/evidence/task-9-partial-payment.png
  ```


- [ ] 10. Update `GymMemberResource` form and table for partial payments

  **What to do**:
  - **getPaymentFormSchema()** updates:
    - Add `amount_paid` TextInput (numeric, required)
    - Label: "Amount Paid Now"
    - Default value: tied to `amount` field via reactive state
    - Add helper text about partial payments
    - Make it reactive: when plan changes, sync amount_paid to plan price by default
  
  - **Table columns** updates:
    - Add `TextColumn::make('latestPayment.total_paid')` or similar if feasible (computed)
    - Add `TextColumn::make('outstanding_balance')` → computed from GymMember model
    - Add `payment_status` filter now includes PARTIAL option (auto-included from enum)
  
  - **Payment Status filter**:
    - The existing filter in table() uses hardcoded 'paid', 'unpaid', 'expire' options
    - Add 'partial' option mapping to PaymentStatus::PARTIAL
    - Or better: use dynamic options derived from PaymentStatus enum

  **Must NOT do**:
  - Do NOT duplicate the PaymentsRelationManager functionality
  - Do NOT add complex computed columns that cause N+1 queries
  - Do NOT change the existing form structure

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`, `fluxui-development`]
    - `laravel-specialist`: Filament Resource pattern, form/table modifications
    - `fluxui-development`: Form component best practices

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 8, 9, 11)
  - **Blocks**: Task F1-F4
  - **Blocked By**: Tasks 2, 4 (PaymentStatus PARTIAL, MemberPayment computed attrs)

  **References**:
  - `app/Filament/Admin/Resources/GymMemberResource.php:940-998` - getPaymentFormSchema()
  - `app/Filament/Admin/Resources/GymMemberResource.php:244-320` - Table columns and filters
  - `app/Models/GymMember.php` - For computed attributes

  **Acceptance Criteria**:
  - [ ] Payment form has `amount_paid` field with proper label and default
  - [ ] Table shows outstanding balance for members with partial payments
  - [ ] Payment status filter includes 'Partial' option
  - [ ] Backward compatible: full payments display as before

  **QA Scenarios**:
  ```
  Scenario: Payment form shows amount_paid field
    Tool: Playwright
    Preconditions: On a GymMember create page
    Steps:
      1. Navigate to membership details step
      2. Locate payment section
    Expected Result: "Amount Paid Now" field visible alongside plan price
    Evidence: .sisyphus/evidence/task-10-form-field.png

  Scenario: Table shows payment status with PARTIAL
    Tool: Playwright
    Preconditions: Member with partial payment exists
    Steps:
      1. Go to Gym Members list
    Expected Result: Member shows "Partial" payment status badge
    Evidence: .sisyphus/evidence/task-10-table-status.png
  ```

- [ ] 11. Add "Record Installment" action and transaction history display

  **What to do**:
  This task focuses on the user-facing action to record additional payments against existing partial payments.
  
  - Add a new Action to the PaymentsRelationManager table actions:
    ```php
    Action::make('record_installment')
        ->label('Record Payment')
        ->icon('heroicon-o-plus-circle')
        ->color('success')
        ->visible(fn (MemberPayment $record) => ! $record->is_fully_paid)
        ->form([
            TextInput::make('amount')
                ->numeric()
                ->required()
                ->minValue(1)
                ->maxValue(fn (MemberPayment $record) => $record->remaining_amount)
                ->suffix(fn ($get) => 'Max: ' . number_format($record->remaining_amount, 2)),
            Select::make('payment_method')
                ->options(PaymentMethod::toSelectOptions())
                ->required()
                ->default(PaymentMethod::CASH->value),
            DatePicker::make('transaction_date')
                ->required()
                ->default(now()),
            Textarea::make('notes')
                ->maxLength(65535)
                ->rows(2),
        ])
        ->action(function (array $data, MemberPayment $record) {
            $record->transactions()->create([
                'member_payment_id' => $record->id,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'transaction_date' => $data['transaction_date'],
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'currency' => $record->currency,
            ]);
            
            $record->syncStatusFromTransactions();
            
            // Update GymMember record
            $member = $record->member;
            if ($member) {
                $member->update([
                    'payment_status' => $record->status,
                    'latest_payment_at' => $data['transaction_date'],
                ]);
            }
            
            // Clear caches
            if ($record->gym_id) {
                MemberPaymentObserver::clearPaymentCaches($record->gym_id);
            }
        })
    ```

  - Add transaction history display in the payment table:
    - Either as an expandable row: `->collapseible()` on a details section
    - Or as a column that shows a badge with transaction count + tooltip
    - Add `TextColumn::make('transactions_count')` with count badge
  
  - Ensure the table query includes transaction count:
    ```php
    ->modifyQueryUsing(fn (Builder $query) => $query
        ->withCount('transactions')
        ->orderByRaw(...)
    )
    ```

  **Must NOT do**:
  - Do NOT allow recording installment that exceeds remaining_amount
  - Do NOT allow recording installment on fully paid payments
  - Do NOT skip cache clearing

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Filament Action patterns, form validation, action handlers

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 8, 9, 10)
  - **Blocks**: Task F1-F4
  - **Blocked By**: Tasks 4, 6, 7

  **References**:
  - `app/Filament/Admin/Resources/GymMemberResource/RelationManagers/PaymentsRelationManager.php` - Where to add the action
  - `app/Models/MemberPayment.php` - For is_fully_paid, remaining_amount, syncStatusFromTransactions()
  - `app/Models/MemberPaymentTransaction.php` - For create()
  - `app/Observers/MemberPaymentObserver.php` - For clearPaymentCaches()

  **Acceptance Criteria**:
  - [ ] "Record Payment" action visible on partial payments
  - [ ] Action hidden when payment is fully paid
  - [ ] Amount field validates max = remaining_amount
  - [ ] Transaction is created with status='completed'
  - [ ] Payment status auto-updates via syncStatusFromTransactions()
  - [ ] GymMember payment_status updated
  - [ ] Caches cleared

  **QA Scenarios**:
  ```
  Scenario: Record installment on partial payment
    Tool: Playwright
    Preconditions: Partial payment exists (amount=10000, paid=5000)
    Steps:
      1. Go to member's Payments tab
      2. Click "Record Payment" on the partial payment row
      3. Enter amount = 5000
      4. Select Cash payment method
      5. Enter notes = "Second installment"
      6. Submit
    Expected Result: Payment amount shows Total Paid = 10000, Status = Completed
    Evidence: .sisyphus/evidence/task-11-record-installment.png

  Scenario: Record Payment button hidden on completed payment
    Tool: Playwright
    Preconditions: Fully paid payment exists
    Steps:
      1. Go to member's Payments tab
    Expected Result: No "Record Payment" button visible on completed payments
    Evidence: .sisyphus/evidence/task-11-no-button.png
  ```


- [ ] 12. Update `GymMemberImport` for partial payment/transaction handling

  **What to do**:
  - Add new columns to import handling:
    - `payment_amount_paid` (decimal) — the actual amount paid in this transaction
    - `payment_installment` (int, default 1) — which installment number this is
    
  - In `rules()`:
    - Add `payment_amount_paid` → `['nullable', 'numeric', 'min:0']`
    - Add `payment_installment` → `['nullable', 'integer', 'min:1']`
  
  - In `dateColumns`:
    - No new date columns needed (payment_date already exists)
  
  - In `processRow()`:
    - Update the payment creation logic:
      - If `payment_amount_paid` is set and < plan price:
        - Create MemberPayment with amount = plan price
        - Create MemberPaymentTransaction with amount = payment_amount_paid
        - Set payment status to PARTIAL
      - If `payment_amount_paid` is not set or equals plan price:
        - Existing behavior: single full payment
      - Create transaction with payment_date, currency from plan, payment_method
      
  - **Existing flow**: currently creates a single payment record per row
  - **Updated flow**: 
    - Create the MemberPayment with total amount
    - Create a MemberPaymentTransaction for the amount actually paid
    - Sync status via syncStatusFromTransactions()
  
  - Template files may also need updating (the import templates)

  **Must NOT do**:
  - Do NOT remove existing import functionality
  - Do NOT make payment_amount_paid required
  - Do NOT change the core member creation logic

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Import pattern for Laravel-Excel, validation rules, batch processing

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 4 (with Task 13)
  - **Blocks**: Task F1-F4
  - **Blocked By**: Tasks 1, 3, 4 (migration, model, relationships)

  **References**:
  - `app/Imports/GymMemberImport.php` - Full file to modify
  - `app/Models/MemberPaymentTransaction.php` - For creating transaction records
  - `app/Models/MemberPayment.php` - For syncStatusFromTransactions()
  - `app/Imports/BaseImporter.php` - Parent class structure
  - `app/Enums/Currency.php` - For currency from plan

  **Acceptance Criteria**:
  - [ ] New column `payment_amount_paid` handled in import
  - [ ] When payment_amount_paid < plan price → PARTIAL payment + transaction created
  - [ ] When payment_amount_paid = plan price → COMPLETED payment (backward compat)
  - [ ] When payment_amount_paid is null → uses plan price as before
  - [ ] Validation rules for new columns work correctly
  - [ ] Existing import data still works (backward compatible)

  **QA Scenarios**:
  ```
  Scenario: Import member with partial payment
    Tool: interactive_bash (tmux)
    Preconditions: CSV file with partial payment data
    Steps:
      1. Prepare import CSV with: name, phone, membership_plan, payment_amount_paid=5000, membership_start
      2. Run import via command or UI
      3. Check created records: Member, MemberPayment, MemberPaymentTransaction
    Expected Result: Member created, payment amount=10000, status=PARTIAL, transaction amount=5000
    Evidence: .sisyphus/evidence/task-12-import-partial.txt

  Scenario: Import member with full payment (backward compat)
    Tool: interactive_bash (tmux)
    Preconditions: CSV without payment_amount_paid column
    Steps:
      1. Import CSV with only standard columns
      2. Check payment
    Expected Result: Payment created as COMPLETED (no transaction needed for full payment simplicity, or a single transaction created)
    Evidence: .sisyphus/evidence/task-12-import-full.txt
  ```

- [ ] 13. Update `GymMemberExporter` for partial payment transaction columns

  **What to do**:
  - Add new export columns to the existing exporter:
    ```php
    ExportColumn::make('total_paid')
        ->formatStateUsing(fn (GymMember $record) => $record->total_payments_amount),
    
    ExportColumn::make('outstanding_balance')
        ->formatStateUsing(fn (GymMember $record) => $record->outstanding_balance),
    
    ExportColumn::make('payment_installments')
        ->formatStateUsing(fn (GymMember $record) => $record->payments->sum(fn ($p) => $p->transactions->count())),
    
    ExportColumn::make('latest_payment_date')
        ->formatStateUsing(fn (GymMember $record) => $record->latest_payment_at?->toDateString()),
    ```
  - Note: These are computed values from relationships, so eager load:
    ```php
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('payments.transactions');
    }
    ```
  - Keep all existing export columns unchanged
  - Add new columns in logical positions (after payment_status)

  **Must NOT do**:
  - Do NOT remove existing export columns
  - Do NOT cause N+1 queries (use eager loading via modifyQuery)
  - Do NOT add too many computed columns that slow exports

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`laravel-specialist`]
    - `laravel-specialist`: Filament Exporter patterns, query optimization

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 4 (with Task 12)
  - **Blocks**: Task F1-F4
  - **Blocked By**: Task 5 (GymMember computed attributes)

  **References**:
  - `app/Filament/Exports/GymMemberExporter.php` - Full file to modify
  - `app/Models/GymMember.php` - Computed attributes (total_payments_amount, outstanding_balance)
  - `app/Models/MemberPayment.php` - transactions relationship

  **Acceptance Criteria**:
  - [ ] Export includes total_paid column with correct values
  - [ ] Export includes outstanding_balance column with correct values
  - [ ] Export includes payment_installments column with count
  - [ ] Export includes latest_payment_date column
  - [ ] Existing columns unchanged
  - [ ] Export doesn't cause N+1 queries
  - [ ] Export file can be generated successfully

  **QA Scenarios**:
  ```
  Scenario: Export includes transaction columns
    Tool: interactive_bash (tmux)
    Preconditions: Members exist with various payment states
    Steps:
      1. Trigger export from GymMember list page (or run exporter manually)
      2. Download the export file
      3. Read the CSV/xlsx content
    Expected Result: File contains columns: Total Paid, Outstanding Balance, Installments, Latest Payment Date
    Evidence: .sisyphus/evidence/task-13-export-columns.txt

  Scenario: Export values are correct for partial payment member
    Tool: interactive_bash (tmux)
    Preconditions: GymMember with partial payment (plan=10000, paid=5000)
    Steps:
      1. Export member
      2. Check exported value for this member
    Expected Result: Total Paid = 5000, Outstanding Balance = 5000, Installments = 1
    Evidence: .sisyphus/evidence/task-13-export-values.txt
  ```


---

## Final Verification Wave

> 4 review agents run in PARALLEL. ALL must APPROVE.

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (run migration, check file exists, tinker to test relationship). For each "Must NOT Have": search codebase for forbidden patterns. Check evidence files exist.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `php artisan tsc --noEmit` (if applicable) + `vendor/bin/pint --test` + `php artisan test --compact --filter=GymMemberPaymentUpdateTest`. Check for: proper type hints, no `dd()`/`dump()`, no commented code, proper PHPDoc blocks.
  Output: `Lint [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high`
  Execute EVERY QA scenario from EVERY task. Test cross-task integration (creating partial payment → adding installment → auto-completion). Save to `.sisyphus/evidence/final-qa/`.
  Output: `Scenarios [N/N pass] | Integration [N/N] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff. Verify 1:1 — everything in spec was built, nothing beyond spec was built.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | VERDICT`

---


## Success Criteria

### Verification Commands
```bash
# Run migration
docker-compose exec app php artisan migrate

# Verify table exists
docker-compose exec app php artisan tinker --execute="Schema::hasTable('member_payments_transactions')"

# Run existing tests (backward compat)
docker-compose exec app php artisan test --compact --filter=GymMemberPaymentUpdateTest

# Run pint
vendor/bin/pint --test
```

### Final Checklist
- [ ] Migration runs cleanly (no errors)
- [ ] New model exists with correct relationships
- [ ] PaymentStatus::PARTIAL defined with proper label/color/icon
- [ ] canAccessGym() allows PARTIAL status
- [ ] PaymentsRelationManager shows transaction history
- [ ] Import handles payment_amount_paid column
- [ ] Export includes Total Paid, Remaining fields
- [ ] Existing GymMemberPaymentUpdateTest passes
