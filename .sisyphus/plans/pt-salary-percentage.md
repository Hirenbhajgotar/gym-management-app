# Convert `pt_salary_per_member` to Percentage-Based Commission

## TL;DR

> **Quick Summary**: Change the `pt_salary_per_member` field on Trainer from a fixed salary amount (e.g., ₹5000/member) to a percentage-based commission rate (e.g., 10% of each assigned member's actual payments). Update the model, Filament resource, imports/exports, factory, migration, and tests.
>
> **Deliverables**:
> - Updated `Trainer.php` model with new `totalEarnings()` calculation
> - Updated `TrainerResource.php` form (percentage input) and table (percentage display)
> - Updated import/export files for percentage semantics
> - New migration to add column comment + set existing values to 10% default
> - Updated factory, test data
>
> **Estimated Effort**: Short
> **Parallel Execution**: YES — 3 waves
> **Critical Path**: Migration → Model → Resource → Import/Export → Tests → Verification

---

## Context

### Original Request
Convert `pt_salary_per_member` from a fixed salary amount to a percentage-based commission rate (percentage of the member's package payments per assigned member).

### Interview Summary
**Key Decisions**:
- **Storage format**: Whole-number percentage — `10.00` means 10%.
- **Base price**: Actual `MemberPayment.amount` — sum of ALL completed payments per member within their current membership period (`membership_start` → `membership_end`).
- **Discount handling**: Commission is from the **discounted/actual paid amount** (e.g., ₹4000, not ₹5000 plan price).
- **Installment handling**: Sum ALL completed payments in current membership period (e.g., ₹2500 + ₹2500 = ₹5000), not just the latest.
- **Data migration**: New migration to set existing values to 10.00 (10%) — old salary amounts can't be meaningfully converted.
- **Form UI**: Remove currency prefix, add `%` suffix, relabel to "PT Commission Rate (%)".
- **`totalEarnings()`** formula: `basic_salary + Σ(SUM of member's completed payments within membership period × pt_salary_per_member / 100)`

**Research Findings**:
- `GymMember` has `membership_start` and `membership_end` date fields for period filtering.
- `MemberPayment` has `payment_date` (date), `amount` (decimal:2), and `status` (PaymentStatus enum) fields.
- `PaymentStatus::COMPLETED` filters for confirmed payments only.
- `Trainer.getEloquentQuery()` already loads `members` — needs `members.payments` added for installment-aware calculation.

### Metis Review
Metis consultation timed out; self-review performed instead. No critical gaps found.

---

## Work Objectives

### Core Objective
Convert the `pt_salary_per_member` field on the Trainer model from a fixed salary amount to a percentage-based commission rate applied to actual member payments.

### Concrete Deliverables
- 1 new migration file
- 8 updated PHP source files
- 1 updated test file
- All existing tests pass
- No schema changes (column type stays `decimal(10,2)`)

### Definition of Done
- [ ] `composer run test` passes with no failures
- [ ] `vendor/bin/pint --dirty --format agent` passes
- [ ] Trainer form shows "PT Commission Rate (%)" with `%` suffix
- [ ] Trainer table shows `pt_salary_per_member` as percentage (e.g., "10%")
- [ ] Import validates `pt_salary_per_member` between 0–100
- [ ] Export shows `pt_salary_per_member` as percentage value
- [ ] `totalEarnings()` correctly sums `basic_salary + (sum of completed payments within membership period × rate / 100)`
- [ ] Discount scenario: trainer commission based on actual paid (₹4000), not plan price (₹5000)
- [ ] Installment scenario: both ₹2500 payments summed when within membership period
- [ ] Data migration sets existing rows to `10.00`

### Must Have
- All references to `pt_salary_per_member` updated across 10 source files
- Form and table display percentage semantics, not currency semantics
- `totalEarnings()` uses new formula with `MemberPayment.amount`
- Import/export validation enforces 0–100 range

### Must NOT Have (Guardrails)
- No database column type changes (schema stays `decimal(10,2)`)
- No changes to unrelated trainer fields (`basic_salary`, `currency`)
- No changes to `MemberPayment` or `GymMember` models
- No frontend/JS changes needed
- No new packages or dependencies

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit, schema:sqlite testing)
- **Automated tests**: YES (Tests-after — existing tests updated + new assertions)
- **Framework**: PHPUnit (via `php artisan test`)
- **Pre-commit checks**: `vendor/bin/pint --dirty --format agent`

### QA Policy
Every task includes agent-executed QA scenarios with evidence saved to `.sisyphus/evidence/`.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation — 3 parallel tasks):
├── Task 1: New migration (column comment + data default)
├── Task 2: Model changes (Trainer.php)
└── Task 3: Factory update (TrainerFactory.php)

Wave 2 (UI & I/O — 5 parallel tasks, depends on Wave 1):
├── Task 4: TrainerResource.php (form + table)
├── Task 5: TrainerImport.php (legacy import)
├── Task 6: TrainerImporter.php (Filament import)
├── Task 7: TrainerExporter.php (Filament export)
└── Task 8: TrainerReportExport.php (report export)

Wave 3 (Tests & Quality — 2 parallel tasks, depends on Wave 2):
├── Task 9: Update TrainerImportTest.php
└── Task 10: Run full test suite + Pint

Critical Path: Task 1 → Task 2 → Task 4...9 (sequential dependency is minimal)
Parallel Speedup: ~60% faster than sequential
Max Concurrent: 5 tasks (Wave 2)
```

---

## TODOs

- [ ] 1. Create new migration — add column comment + set existing values to 10%

  **What to do**:
  - Create a new migration file: `database/migrations/2025_06_30_000001_update_pt_salary_per_member_to_percentage.php`
  - In `up()`: Update the `pt_salary_per_member` column — add a `->comment('Percentage commission rate (e.g., 10.00 = 10%)')` and set default to 10.00
  - Add a raw SQL update: `DB::statement('UPDATE trainers SET pt_salary_per_member = 10.00 WHERE pt_salary_per_member IS NOT NULL')`
  - In `down()`: Revert the comment and default back

  **Must NOT do**:
  - Do NOT change column type (stays `decimal(10,2)`)
  - Do NOT drop/recreate the column (use `->change()` with full column definition)

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3)
  - **Blocks**: Task 9 (test data expectations)
  - **Blocked By**: None

  **References**:
  - `database/migrations/2025_05_10_145426_create_trainers_table.php:30` — Original column definition to replicate on `->change()`
  - Laravel docs: `https://laravel.com/docs/12.x/migrations#modifying-column-attributes` — Syntax for `->change()` with full column attributes

  **Acceptance Criteria**:

  **QA Scenarios**:
  ```
  Scenario: Migration runs successfully
    Tool: Bash
    Preconditions: Database has trainers with pt_salary_per_member = 5000.00
    Steps:
      1. Run migration: docker-compose exec app php artisan migrate --env=testing
      2. Query: docker-compose exec app php artisan tinker --execute="echo DB::table('trainers')->value('pt_salary_per_member');"
    Expected Result: All existing rows have pt_salary_per_member = 10.00
    Failure Indicators: Values not 10.00, migration errors
    Evidence: .sisyphus/evidence/task-1-migration-output.txt
  ```

- [ ] 2. Update Trainer model — new `totalEarnings()` formula + PHPDoc

  **What to do**:
  - In `app/Models/Trainer.php`:
     1. Update PHPDoc `@property` for `pt_salary_per_member`: `* @property numeric|null $pt_salary_per_member` (stays the same, semantics change)
     2. Update `totalEarnings()` accessor — **sum ALL completed payments within each member's current membership period** (handles both discounts and installments):
        ```php
        protected function totalEarnings(): Attribute
        {
            return Attribute::make(get: function () {
                $basicSalary = (float) ($this->basic_salary ?? 0);
                $rate = (float) ($this->pt_salary_per_member ?? 0);

                if ($rate <= 0) {
                    return $basicSalary;
                }

                $ptEarnings = $this->members->reduce(function (float $carry, $member) use ($rate) {
                    // Only consider completed payments within the member's current membership period
                    $paymentsInPeriod = $member->payments->filter(function ($payment) use ($member) {
                        return $payment->payment_date
                            && $member->membership_start
                            && $member->membership_end
                            && $payment->payment_date->between($member->membership_start, $member->membership_end)
                            && $payment->status === \App\Enums\PaymentStatus::COMPLETED;
                    });

                    $totalPaid = (float) $paymentsInPeriod->sum('amount');

                    return $carry + ($totalPaid * $rate / 100);
                }, 0.0);

                return $basicSalary + $ptEarnings;
            });
        }
        ```
     3. The cast stays as `'pt_salary_per_member' => 'decimal:2'` (still correct for percentage values)
     4. The fillable stays as `'pt_salary_per_member'` (still valid)
     5. Update `TrainerResource::getEloquentQuery()` to eager load `members.payments`:
        ```php
        public static function getEloquentQuery(): Builder
        {
            return parent::getEloquentQuery()
                ->with(['members.payments'])
                ->withCount('members');
        }
        ```

  **Must NOT do**:
  - Do NOT change `basic_salary`, `currency`, or other unrelated fields
  - Do NOT remove `with('members')` from the query — we need it loaded

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3)
  - **Blocks**: Tasks 4–8 (they depend on the model's behavior)
  - **Blocked By**: None

  **References**:
  - `app/Models/GymMember.php:membership_start`, `membership_end` — Date fields for period filtering
  - `app/Models/GymMember.php:payments()` — HasMany relationship to MemberPayment
  - `app/Models/MemberPayment.php:amount`, `payment_date`, `status` — Fields used in calculation
  - `app/Enums/PaymentStatus.php:COMPLETED` — Status enum for filtering completed payments
  - `app/Models/Trainer.php:122-129` — Current `totalEarnings()` to replace

  **Acceptance Criteria**:

  **QA Scenarios**:
  ```
  Scenario: totalEarnings with discount + installments
    Tool: Bash (php artisan tinker)
    Preconditions:
      - Trainer: basic_salary=50000, pt_salary_per_member=10
      - Member A: plan ₹5000, paid ₹4000 (discount, one payment)
      - Member B: plan ₹5000, paid ₹2500 + ₹2500 (two installments, both completed)
      - Both payments within current membership period
    Steps:
      1. Create test data via tinker
      2. Call $trainer->total_earnings
    Expected Result: 50000 + (4000*0.10 + 5000*0.10) = 50000 + 400 + 500 = 50900
    Failure Indicators: Uses plan price instead of actual paid, only latest payment instead of sum
    Evidence: .sisyphus/evidence/task-2-installment-test.txt

  Scenario: totalEarnings with zero rate
    Tool: Bash (tinker)
    Preconditions: Trainer has pt_salary_per_member=0
    Steps: Call $trainer->total_earnings
    Expected Result: Equals basic_salary only
    Evidence: .sisyphus/evidence/task-2-zero-rate.txt

  Scenario: totalEarnings ignores payments outside membership period
    Tool: Bash (tinker)
    Preconditions: Member has a completed payment dated before membership_start
    Steps: Call $trainer->total_earnings
    Expected Result: That payment NOT included in calculation
    Evidence: .sisyphus/evidence/task-2-period-filter.txt
  ```

- [ ] 3. Update TrainerFactory — percentage-based fake data

  **What to do**:
  - In `database/factories/TrainerFactory.php`:
    - Change `'pt_salary_per_member' => fake()->randomFloat(4, 2000, 10000)` to `'pt_salary_per_member' => fake()->randomFloat(2, 5, 50)`

  **Must NOT do**:
  - Do NOT change any other factory fields

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2)
  - **Blocks**: Task 9 (test relies on factory)
  - **Blocked By**: None

  **References**:
  - `database/factories/TrainerFactory.php:33` — Current definition to replace

  **Acceptance Criteria**:

  **QA Scenarios**:
  ```
  Scenario: Factory generates valid percentage
    Tool: Bash (tinker)
    Steps:
      1. docker-compose exec app php artisan tinker --execute="var_dump(App\Models\Trainer::factory()->make()->pt_salary_per_member);"
    Expected Result: A float between 5 and 50
    Evidence: .sisyphus/evidence/task-3-factory-value.txt
  ```

---

- [ ] 4. Update TrainerResource.php — form field + table column

  **What to do**:
  - In `app/Filament/Admin/Resources/TrainerResource.php`:

  **Form changes** (lines 203-211):
  - Change the `pt_salary_per_member` TextInput:
    ```php
    TextInput::make('pt_salary_per_member')
        ->label('PT Commission Rate (%)')
        ->numeric()
        ->minValue(0)
        ->maxValue(100)
        ->required()
        ->step(0.01)
        ->suffix('%')
        ->placeholder('10.00')
        ->columnSpan(1),
    ```
    - Remove `->prefix(fn ($get) => ...)` — no longer currency-based
    - Add `->suffix('%')` — indicate percentage
    - Add `->maxValue(100)` — percentage can't exceed 100
    - Change label to `'PT Commission Rate (%)'`

  **Table changes** (lines 239-241):
  - Change the `pt_salary_per_member` TextColumn:
    ```php
    TextColumn::make('pt_salary_per_member')
        ->label('PT Commission Rate')
        ->formatStateUsing(fn ($state) => number_format($state, 2) . '%'),
    ```
    - Remove `->money(...)` — no longer currency-based
    - Add `->formatStateUsing(...)` — show as percentage with `%` suffix
    - Change label

  **Must NOT do**:
  - Do NOT change `basic_salary` or `total_earnings` fields
  - Do NOT remove import statements that are still used

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 5, 6, 7, 8)
  - **Blocked By**: Task 2 (model changes)

  **References**:
  - `app/Filament/Admin/Resources/TrainerResource.php:203-211` — Current form field
  - `app/Filament/Admin/Resources/TrainerResource.php:239-241` — Current table column

  **QA Scenarios**:
  ```
  Scenario: Form shows percentage input with % suffix
    Tool: Playwright
    Preconditions: Admin logged in, on trainer edit page
    Steps:
      1. Navigate to /admin/trainers/{id}/edit
      2. Inspect the pt_salary_per_member field
    Expected Result: Field labeled "PT Commission Rate (%)", has "%" suffix, accepts decimal values
    Failure Indicators: Shows currency prefix, missing % suffix
    Evidence: .sisyphus/evidence/task-4-form-screenshot.png

  Scenario: Table shows percentage values
    Tool: Playwright
    Preconditions: Admin on trainer list page
    Steps:
      1. Navigate to /admin/trainers
      2. Check the "PT Commission Rate" column
    Expected Result: Values displayed as "10.00%" format
    Failure Indicators: Shows ₹ or $ prefix, missing % suffix
    Evidence: .sisyphus/evidence/task-4-table-screenshot.png
  ```

- [ ] 5. Update TrainerImport.php (legacy import) — validation

  **What to do**:
  - In `app/Imports/TrainerImport.php`:
    - Change validation rule on line 68 from `'pt_salary_per_member' => 'nullable|numeric|min:0'` to `'pt_salary_per_member' => 'nullable|numeric|min:0|max:100'`
    - The import logic on line 45 stays the same (just casts to float — percentage values work the same)

  **Must NOT do**:
  - Do NOT change the import logic for other fields

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 6, 7, 8)
  - **Blocked By**: Task 2 (model changes)

  **References**:
  - `app/Imports/TrainerImport.php:68` — Current validation rule
  - `app/Imports/TrainerImport.php:45` — Import mapping (stays the same)

  **QA Scenarios**:
  ```
  Scenario: Import rejects percentage > 100
    Tool: Bash (phpunit)
    Steps: docker-compose exec app php artisan test --compact --filter=TrainerImportTest
    Expected Result: Test passes with updated validation
    Failure Indicators: Import test fails
    Evidence: .sisyphus/evidence/task-5-import-test.txt
  ```

- [ ] 6. Update TrainerImporter.php (Filament import) — validation

  **What to do**:
  - In `app/Filament/Imports/TrainerImporter.php`:
    - Change the import column on lines 39-41:
      - Add label: `->label('PT Commission Rate (%)')`
      - Change rules from `['integer']` to `['numeric', 'min:0', 'max:100']`
    - The mapping logic in `resolveRecord()` on line 60 stays the same

  **Must NOT do**:
  - Do NOT change other import columns

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 5, 7, 8)
  - **Blocked By**: Task 2 (model changes)

  **References**:
  - `app/Filament/Imports/TrainerImporter.php:39-41` — Current import column
  - `app/Filament/Imports/TrainerImporter.php:60` — resolveRecord mapping

  **QA Scenarios**:
  ```
  Scenario: Validation accepts 10.50
    Tool: Bash (tinker)
    Steps: Validate a row with pt_salary_per_member=10.50 passes
    Expected Result: No validation errors for valid percentage
    Evidence: .sisyphus/evidence/task-6-import-validation.txt
  ```

- [ ] 7. Update TrainerExporter.php — column display

  **What to do**:
  - In `app/Filament/Exports/TrainerExporter.php`:
    - Change the `pt_salary_per_member` export column (lines 35-36):
      ```php
      ExportColumn::make('pt_salary_per_member')
          ->label('PT Commission Rate (%)')
          ->getStateUsing(fn ($record) => $record->pt_salary_per_member . '%'),
      ```
    - Add `getStateUsing()` to append `%` suffix
    - Update the label

  **Must NOT do**:
  - Do NOT change other export columns

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 5, 6, 8)
  - **Blocked By**: Task 2 (model changes)

  **References**:
  - `app/Filament/Exports/TrainerExporter.php:35-36` — Current export column

  **QA Scenarios**:
  ```
  Scenario: Export column shows percentage
    Tool: Bash (tinker)
    Steps: Simulate export and check column value
    Expected Result: Export value includes "%" suffix
    Failure Indicators: Missing % suffix
    Evidence: .sisyphus/evidence/task-7-export-value.txt
  ```

- [ ] 8. Update TrainerReportExport.php — heading + data

  **What to do**:
  - In `app/Exports/TrainerReportExport.php`:
    - Change heading on line 51 from `'PT Salary/Member'` to `'PT Commission Rate (%)'`
    - The `map()` method on line 72 already uses `$trainer->pt_salary_per_member` — no change needed for raw data

  **Must NOT do**:
  - Do NOT change other headings or mappings

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 4, 5, 6, 7)
  - **Blocked By**: Task 2 (model changes)

  **References**:
  - `app/Exports/TrainerReportExport.php:51` — Current heading
  - `app/Exports/TrainerReportExport.php:72` — Current data mapping (stays the same)

  **QA Scenarios**:
  ```
  Scenario: Report export heading updated
    Tool: Bash (phpunit or direct inspection)
    Steps: Read the headings() method
    Expected Result: Heading says "PT Commission Rate (%)"
    Failure Indicators: Old heading "PT Salary/Member" remains
    Evidence: .sisyphus/evidence/task-8-heading.txt
  ```

---

- [ ] 9. Update TrainerImportTest.php — test data values

  **What to do**:
  - In `tests/Feature/Feature/TrainerImportTest.php`:
    - Change the CSV data on lines 32-33:
      - Change `100` (old: salary amount) to `10` (new: percentage value)
      - Change `120` to `15`
    - The test structure stays the same

  **Must NOT do**:
  - Do NOT change the test assertions or logic (only data values)

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Task 10)
  - **Blocked By**: Tasks 1-8 (all model/import changes must be done)

  **References**:
  - `tests/Feature/Feature/TrainerImportTest.php:31-34` — CSV data to update

  **QA Scenarios**:
  ```
  Scenario: Test passes with new percentage data
    Tool: Bash
    Steps: docker-compose exec app php artisan test --compact --filter=TrainerImportTest
    Expected Result: PASS (1 test, 2 assertions)
    Evidence: .sisyphus/evidence/task-9-test-pass.txt
  ```

- [ ] 10. Run full test suite + Pint

  **What to do**:
  - Run `composer run test` to ensure all existing tests pass
  - Run `vendor/bin/pint --dirty --format agent` for code style
  - If any tests fail, fix them before finalizing
  - If Pint changes files, review and commit them

  **Must NOT do**:
  - Do NOT skip any failing test — fix the root cause

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Task 9)
  - **Blocked By**: Tasks 1-9 (all changes must be done)

  **References**:
  - AGENTS.md: Build/lint/test commands section

  **QA Scenarios**:
  ```
  Scenario: All tests pass
    Tool: Bash
    Steps: docker-compose exec app composer run test
    Expected Result: All tests pass, zero failures
    Evidence: .sisyphus/evidence/task-10-test-results.txt

  Scenario: Pint passes
    Tool: Bash
    Steps: vendor/bin/pint --dirty --format agent
    Expected Result: No formatting issues, or changes auto-applied
    Evidence: .sisyphus/evidence/task-10-pint-output.txt
  ```

---

## Final Verification Wave

> 3 review agents run in PARALLEL. ALL must APPROVE.

- [ ] F1. **Plan Compliance Audit** — `quick`
  Verify each file was changed per spec. Run `git diff --stat` to confirm 11+ files changed. Check form renders in browser via Playwright. Check table column displays `%` suffix.

- [ ] F2. **Code Quality + Tests** — `unspecified-high`
  Run `composer run test`, `vendor/bin/pint --dirty --format agent`. Verify zero test failures and zero lint issues.

- [ ] F3. **Scope Fidelity Check** — `quick`
  Verify no changes to `basic_salary`, `currency`, `MemberPayment`, `GymMember`, or unrelated files. Verify no schema column type changes.

---

## Success Criteria

### Verification Commands
```bash
docker-compose exec app php artisan test --compact --filter=TrainerImportTest  # Expected: PASS (1 test, 2 assertions)
docker-compose exec app php artisan test --compact --filter=TrainerTest        # Expected: PASS (if tests exist)
vendor/bin/pint --dirty --format agent                                          # Expected: clean
```

### Final Checklist
- [ ] All "Must Have" present
- [ ] All "Must NOT Have" absent
- [ ] All tests pass
