# Plan: GymMember Phone Not Nullable, Unique — Email Nullable

## TL;DR

> **Quick Summary**: Make `phone` the primary identifier for gym members instead of `email`. Phone becomes NOT NULL, UNIQUE (per-gym via composite key), and INDEXED. Email becomes nullable (optional contact info only). Update all related files: migration, model, Filament resource, imports, factory, and tests.
>
> **Deliverables**:
> - New migration altering `gym_members` columns
> - Updated `GymMember` model PHPDoc
> - Updated `GymMemberResource` form (email optional, phone unique)
> - Updated `GymMemberImport.php` and `GymMembersImport.php` (email nullable, phone required)
> - Updated `GymMemberFactory` (unique phone)
> - Updated/added tests covering the new constraints
>
> **Estimated Effort**: Short
> **Parallel Execution**: YES — 3 waves (foundation → updates → tests)
> **Critical Path**: Migration → All file updates → Tests pass

---

## Context

### Original Request
User wants to make `phone` the primary member identifier instead of `email`. Gym members typically have phones, not emails. In the future, `GymMember` will be authenticatable via phone. Requirements:
- `phone`: NOT NULL, UNIQUE (per-gym `gym_id, phone`), INDEXED
- `email`: NULLABLE (contact info only)
- Filament form: phone required, email optional
- Import rules: phone required, email optional

### Interview Summary
**Key Decisions** (from user):
- **Existing NULL phones**: Auto-generate placeholder numbers (e.g., `'0000000000' + ID` )
- **Uniqueness scope**: Composite unique(`gym_id`, `phone`) — allows same phone across different gyms
- **Testing**: Include tests for the new constraints

**Research Findings** (from codebase exploration):
- `FraudDetector::isSuspiciousVelocity()` already guards `if (!$gymMember->email) return false;`
- `GymMemberObserver::created()` already checks `filled($gymMember->email)` before dispatching biometric consent email
- `SendBiometricConsentEmail::handle()` already guards `blank($this->gymMember->email)`
- All notifications (MembershipExpiry, PaymentReminder, Promotional) already handle null phone with null coalescing
- WhatsApp Broadcast query already filters `->whereNotNull('phone')`
- No existing `unique:gym_members,phone` validation anywhere
- Email has existing `unique` index in DB — works fine with NULL (MySQL InnoDB allows multiple NULLs in unique index)

### Metis Review
(Timed out — auto-proceeded with own analysis)

---

## Work Objectives

### Core Objective
Migrate `gym_members` table and all related code so `phone` becomes the required, unique member identifier and `email` becomes optional.

### Concrete Deliverables
- 1 new migration file in `database/migrations/`
- 1 updated model file: `app/Models/GymMember.php`
- 1 updated Filament resource: `app/Filament/Admin/Resources/GymMemberResource.php`
- 2 updated import files: `app/Imports/GymMemberImport.php`, `app/Imports/GymMembersImport.php`
- 1 updated factory: `database/factories/GymMemberFactory.php`
- 2 updated/added test files

### Definition of Done
- [ ] `composer run test` passes with no failures
- [ ] `vendor/bin/pint --dirty` passes (0 formatting issues)
- [ ] `php artisan migrate` applies new migration cleanly (with null phone fill-in)
- [ ] `php artisan migrate:rollback` reverses the migration cleanly
- [ ] Filament create/edit forms validate phone as required and unique per gym
- [ ] Filament create/edit forms accept empty email
- [ ] CSV import requires phone but not email

### Must Have
- Migration makes email nullable (remove NOT NULL)
- Migration makes phone NOT NULL with composite unique(gym_id, phone) + index
- Migration auto-fills existing null phone records with placeholder value
- Filament form: phone required + unique per gym, email optional
- Import files: phone required, email optional
- Factory generates unique phone numbers
- Tests cover: phone uniqueness, email optionality, form validation

### Must NOT Have (Guardrails)
- **NO** authentication changes — phone-based auth is future scope
- **NO** changes to notification/mail classes (already handle null gracefully)
- **NO** changes to exporters (just read data)
- **NO** changes to WhatsApp broadcast logic (already handles phone correctly)
- **NO** deleting the email column — just make it nullable

---

## Verification Strategy

> **ZERO HUMAN INTERVENTION** — ALL verification is agent-executed. No exceptions.

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: YES (TDD: update existing + add new tests)
- **Framework**: PHPUnit (Pest-style and traditional)
- **Approach**: Update existing tests to work with new constraints, add new test class

### QA Policy
Every task MUST include agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/task-{N}-{scenario-slug}.{ext}`.

- **Backend/Migration**: Use `php artisan` commands — run migrate, verify schema, rollback
- **API/Validation**: Use PHPUnit via `php artisan test --filter=TestName`
- **Filament Forms**: Use Playwright to fill forms, submit, verify validation messages
- **DB Schema**: Use Laravel Boost `database-schema` tool to verify column changes

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation — start immediately, MAX PARALLEL):
├── Task 1: Create migration (column changes + null phone fill) [quick]
├── Task 2: Update GymMember model PHPDoc [quick]
├── Task 3: Update GymMemberResource form validation [quick]
├── Task 4: Update GymMemberImport.php [quick]
├── Task 5: Update GymMembersImport.php [quick]
└── Task 6: Update GymMemberFactory.php [quick]

Wave 2 (Tests — after Wave 1):
├── Task 7: Update CreateGymMemberTest and other existing tests [quick]
├── Task 8: Create GymMemberPhoneEmailValidationTest [quick]

Wave FINAL (Final verification — after ALL tasks):
├── Task F1: Run full test suite + migration verification
```

**Critical Path**: Task 1 → Tasks 2-6 (parallel) → Tasks 7-8 (parallel) → F1
**Parallel Speedup**: ~60% faster than sequential
**Max Concurrent**: 5 tasks in Wave 1

---

## TODOs

- [ ] 1. Create migration — alter gym_members columns + fill null phones

  **What to do**:
  - Run `php artisan make:migration update_gym_members_email_phone --table=gym_members --no-interaction`
  - In `up()`:
    1. First fill existing null phone numbers with placeholder: `DB::table('gym_members')->whereNull('phone')->update(['phone' => DB::raw("CONCAT('0000000000', id)")]);`
    2. Make email nullable: `$table->string('email')->nullable()->change();`
    3. Make phone NOT NULL: `$table->string('phone')->nullable(false)->change();`
    4. Add composite unique: `$table->unique(['gym_id', 'phone']);`
    5. Add index on phone: `$table->index('phone');`
  - In `down()`:
    1. Drop unique constraint and index
    2. Reverse nullable/nullable(false) changes
  - Use Doctrine DBAL: Ensure `doctrine/dbal` is installed for column type changes
  - Run `php artisan migrate` to verify
  - Run `php artisan migrate:rollback` to verify reversibility

  **Must NOT do**:
  - Don't drop the email column — only make it nullable
  - Don't change any other columns

  **Recommended Agent Profile**:
  - **Category**: `quick` — Single migration file, straightforward column changes
  - **Skills**: `[]`

  **Parallelization**:
  - **Can Run In Parallel**: NO (must be done first)
  - **Parallel Group**: Wave 1 (foundation)
  - **Blocks**: Tasks 7, 8 (tests depend on the migration being applied)
  - **Blocked By**: None (can start immediately)

  **References**:
  - `database/migrations/2025_04_16_164544_create_gym_members_table.php:20-22` — Original column definitions for email (`->unique()`) and phone (`->nullable()`)
  - `database/migrations/2025_05_31_175537_update_payment_status_to_nullable_in_gym_members.php` — Pattern for making a column nullable (sibling migration for reference pattern)

  **Acceptance Criteria**:

  - [ ] `php artisan migrate` runs without error
  - [ ] `php artisan migrate:rollback` runs without error
  - [ ] Database schema shows email as nullable (`YES` in Null column)
  - [ ] Database schema shows phone as NOT NULL (`NO` in Null column)
  - [ ] Database shows UNIQUE constraint on (gym_id, phone)
  - [ ] Database shows INDEX on phone column
  - [ ] Existing records with null phone now have placeholder like `00000000001`

  **QA Scenarios**:
  ```
  Scenario: Migration applies and rolls back cleanly
    Tool: Bash
    Preconditions: Working database connection
    Steps:
      1. php artisan migrate --env=testing
      2. php artisan migrate:rollback --env=testing
      3. php artisan migrate --env=testing
    Expected Result: All 3 commands exit with code 0
    Evidence: .sisyphus/evidence/task-1-migration-cycle.txt

  Scenario: Schema verification after migration
    Tool: Laravel Boost database-schema
    Preconditions: Migration has been applied
    Steps:
      1. Read schema for gym_members table
      2. Verify email column nullable=true
      3. Verify phone column nullable=false
      4. Verify unique constraint on (gym_id, phone)
    Expected Result: All schema properties match requirements
    Evidence: .sisyphus/evidence/task-1-schema-verification.txt

  Scenario: Null phone fill-in works
    Tool: Bash (php artisan tinker)
    Preconditions: Migration has been applied
    Steps:
      1. php artisan tinker --execute="DB::table('gym_members')->whereNull('phone')->count();"
    Expected Result: 0 records with null phone
    Evidence: .sisyphus/evidence/task-1-null-phone-count.txt
  ```

  **Evidence to Capture**:
  - [ ] `.sisyphus/evidence/task-1-migration-cycle.txt`
  - [ ] `.sisyphus/evidence/task-1-schema-verification.txt`
  - [ ] `.sisyphus/evidence/task-1-null-phone-count.txt`


---

- [ ] 2. Update GymMember model PHPDoc annotations

  **What to do**:
  - Open `app/Models/GymMember.php`
  - Change `@property string $email` → `@property string|null $email` (line 32)
  - Change `@property string|null $phone` → `@property string $phone` (line 33)
  - No other changes needed — `$fillable`, `$casts`, relationships are all fine as-is

  **Must NOT do**:
  - Don't alter `$fillable` array (both `email` and `phone` are already there)
  - Don't change any logic/methods — only PHPDoc

  **Recommended Agent Profile**:
  - **Category**: `quick` — 2 line PHPDoc changes
  - **Skills**: `[]`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 3, 4, 5, 6)
  - **Blocks**: None
  - **Blocked By**: None

  **References**:
  - `app/Models/GymMember.php:32-33` — Lines to modify

  **Acceptance Criteria**:
  - [ ] `@property string|null $email` is present in PHPDoc
  - [ ] `@property string $phone` is present (no `|null`)
  - [ ] No PHP/LSP errors from the changes

  **QA Scenarios**:
  ```
  Scenario: PHPDoc changes verified
    Tool: Bash
    Preconditions: File edited
    Steps:
      1. grep "@property string.*email" app/Models/GymMember.php → should show "string|null $email"
      2. grep "@property string.*phone" app/Models/GymMember.php → should show "string $phone" (not "string|null")
    Expected Result: Both patterns match correctly
    Evidence: .sisyphus/evidence/task-2-phpdoc-verification.txt
  ```

  **Evidence to Capture**:
  - [ ] `.sisyphus/evidence/task-2-phpdoc-verification.txt`


---

- [ ] 3. Update GymMemberResource — email optional, phone unique

  **What to do**:
  - Open `app/Filament/Admin/Resources/GymMemberResource.php`
  - **Email field** (line 741-753):
    - Remove `->required()` 
    - Add `->nullable()`
    - Keep `->unique(GymMember::class, 'email', ignoreRecord: true)` — fine for nullable (won't enforce uniqueness on empty values)
    - Keep `->rules(['email:rfc', new ValidEmailDomain()])` — `ValidEmailDomain` already returns early for non-strings
  - **Phone field** (line 755-763):
    - Keep `->required()` 
    - Add `->unique(GymMember::class, 'phone', ignoreRecord: true)` — validates unique phone globally (we handle per-gym uniqueness in migration; this is an additional safety net)
    - Keep all other config (country IN, validateFor, etc.)

  **Must NOT do**:
  - Don't change the `validEmailDomain()` or remove it — it's still valid when email IS provided
  - Don't change any other fields or form structure
  - Don't alter table columns

  **Recommended Agent Profile**:
  - **Category**: `quick` — 3-4 line changes in a single file
  - **Skills**: `[]`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 4, 5, 6)
  - **Blocks**: Tasks 7, 8 (tests validate form behavior)
  - **Blocked By**: None

  **References**:
  - `app/Filament/Admin/Resources/GymMemberResource.php:741-753` — Email field current definition
  - `app/Filament/Admin/Resources/GymMemberResource.php:755-763` — Phone field current definition
  - Search existing unique rules in other Filament resources for pattern reference

  **Acceptance Criteria**:
  - [ ] Email field has `->nullable()` and no `->required()`
  - [ ] Phone field has `->unique(GymMember::class, 'phone', ignoreRecord: true)`
  - [ ] Form still renders without errors

  **QA Scenarios**:
  ```
  Scenario: Form renders without TypeError (known regression pattern)
    Tool: Bash (php artisan test)
    Preconditions: Code changes applied
    Steps:
      1. php artisan test --filter=CreateGymMemberTest --compact
    Expected Result: Tests pass (verifies form schema is valid)
    Evidence: .sisyphus/evidence/task-3-form-render.txt
  ```

  **Evidence to Capture**:
  - [ ] `.sisyphus/evidence/task-3-form-render.txt`


---

- [ ] 4. Update GymMemberImport — phone required, email optional

  **What to do**:
  - Open `app/Imports/GymMemberImport.php`
  - **Update `rules()` method** (line 121-137):
    - Change email rule from `['required', ...]` to `['nullable', 'string', 'email', 'max:255', 'unique:gym_members,email']`
    - Change phone rule from `['nullable', 'max:20']` to `['required', 'max:20']`
  - **Update `processRow()` method** (line 77-92):
    - Change `'email' => $row['email']` → `'email' => $row['email'] ?? null`
    - Change `'phone' => $row['phone'] ?? null` → `'phone' => $row['phone']`

  **Must NOT do**:
  - Don't alter any other import logic
  - Don't change the BaseImporter or other imports

  **Recommended Agent Profile**:
  - **Category**: `quick` — 4 line changes in one file
  - **Skills**: `[]`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3, 5, 6)
  - **Blocks**: Tasks 7, 8
  - **Blocked By**: None

  **Acceptance Criteria**:
  - [ ] `rules()` has `email` as nullable
  - [ ] `rules()` has `phone` as required
  - [ ] `processRow()` passes email as `?? null`
  - [ ] `processRow()` passes phone directly (no `?? null`)

  **QA Scenarios**:
  ```
  Scenario: Import rules updated correctly
    Tool: Bash
    Preconditions: File edited
    Steps:
      1. grep "phone.*required" app/Imports/GymMemberImport.php
      2. grep "email.*nullable" app/Imports/GymMemberImport.php
    Expected Result: Both patterns match
    Evidence: .sisyphus/evidence/task-4-import-rules.txt
  ```

  **Evidence to Capture**:
  - [ ] `.sisyphus/evidence/task-4-import-rules.txt`


---

- [ ] 5. Update GymMembersImport — phone required, email optional

  **What to do**:
  - Open `app/Imports/GymMembersImport.php`
  - **Update `rules()` method** (line 86-104):
    - Change `'email' => 'required|email|unique:gym_members,email'` → `'email' => 'nullable|email|unique:gym_members,email'`
    - Change `'phone' => ['nullable', 'max:255', new ValidPhone()]` → `'phone' => ['required', 'max:255', new ValidPhone()]`
  - **Update `model()` method** (line 51-63):
    - Change `'email' => $row['email']` → `'email' => $row['email'] ?? null`
    - Change `'phone' => $row['phone'] ?? null` → `'phone' => $row['phone']`

  **Must NOT do**:
  - Don't alter any other import logic or event handlers

  **Recommended Agent Profile**:
  - **Category**: `quick` — 4 line changes in one file
  - **Skills**: `[]`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3, 4, 6)
  - **Blocks**: Tasks 7, 8
  - **Blocked By**: None

  **Acceptance Criteria**:
  - [ ] `rules()` has email as nullable
  - [ ] `rules()` has phone as required
  - [ ] `model()` passes email as `?? null`
  - [ ] `model()` passes phone directly (no `?? null`)

  **QA Scenarios**:
  ```
  Scenario: Import rules updated correctly
    Tool: Bash
    Preconditions: File edited
    Steps:
      1. grep "phone.*required" app/Imports/GymMembersImport.php
      2. grep "email.*nullable" app/Imports/GymMembersImport.php
    Expected Result: Both patterns match
    Evidence: .sisyphus/evidence/task-5-import-rules.txt
  ```

  **Evidence to Capture**:
  - [ ] `.sisyphus/evidence/task-5-import-rules.txt`

---

- [ ] 6. Update GymMemberFactory — unique phone

  **What to do**:
  - Open `database/factories/GymMemberFactory.php`
  - Change line 27: `'phone' => fake()->phoneNumber()` → `'phone' => fake()->unique()->phoneNumber()`
  - Keep `'email' => fake()->unique()->safeEmail()` as-is (email is still unique when provided)

  **Must NOT do**:
  - Don't remove email from factory — it's still valid test data
  - Don't change any other factory definitions

  **Recommended Agent Profile**:
  - **Category**: `quick` — 1 word change
  - **Skills**: `[]`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3, 4, 5)
  - **Blocks**: Tasks 7, 8 (tests use factory)
  - **Blocked By**: None

  **Acceptance Criteria**:
  - [ ] Factory generates unique phone numbers
  - [ ] Factory still generates valid emails

  **QA Scenarios**:
  ```
  Scenario: Factory generates unique phones
    Tool: Bash (php artisan tinker)
    Preconditions: Factory updated
    Steps:
      1. php artisan tinker --execute="\$members = \App\Models\GymMember::factory()->count(10)->create();"
      2. php artisan tinker --execute="\$phones = \$members->pluck('phone'); \$phones->count() === \$phones->unique()->count();"
    Expected Result: All 10 phone numbers are unique
    Evidence: .sisyphus/evidence/task-6-factory-unique.txt
  ```

  **Evidence to Capture**:
  - [ ] `.sisyphus/evidence/task-6-factory-unique.txt`

---

- [ ] 7. Update existing tests for new constraints

  **What to do**:
  - Open `tests/Feature/CreateGymMemberTest.php` (and any other tests that create gym members via form)
  - Search for any test that uses `'email' => ''` or omits email — ensure it still passes with nullable email
  - Search for any test that omits phone or passes null phone — update to always provide a phone
  - Ensure tests using `GymMemberFactory` still pass (factory now generates unique phones)
  - Run `php artisan test --compact` to identify any failures

  **Must NOT do**:
  - Don't delete tests — only update test data to match new constraints
  - Don't change assertion logic — only adjust input data

  **Recommended Agent Profile**:
  - **Category**: `quick` — Update test data, no structural changes
  - **Skills**: `[]`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Task 8)
  - **Blocks**: Final Verification
  - **Blocked By**: Tasks 1-6 (all must be applied before tests can run)

  **References**:
  - `tests/Feature/CreateGymMemberTest.php` — Main test to update
  - `tests/Feature/BiometricConsentTest.php` — Check for email-related assertions
  - Also search: `grep -r "phone" tests/ --include="*.php"` for all phone-related test assertions
  - Also search: `grep -r "email" tests/ --include="*.php"` for all email-related test assertions

  **Acceptance Criteria**:
  - [ ] All existing tests pass with updated data
  - [ ] No test uses null/missing email as a required field
  - [ ] No test uses null/missing phone (all tests provide phone)

  **QA Scenarios**:
  ```
  Scenario: Full test suite passes
    Tool: Bash
    Preconditions: All code changes from Tasks 1-6 applied, migration run
    Steps:
      1. php artisan test --compact
    Expected Result: All tests PASS, exit code 0
    Evidence: .sisyphus/evidence/task-7-test-suite-pass.txt
  ```

  **Evidence to Capture**:
  - [ ] `.sisyphus/evidence/task-7-test-suite-pass.txt`

---

- [ ] 8. Create GymMemberPhoneEmailValidationTest

  **What to do**:
  - Create new test file: `tests/Feature/GymMemberPhoneEmailValidationTest.php`
  - Run `php artisan make:test GymMemberPhoneEmailValidationTest --phpunit --no-interaction`
  - Write tests covering:

    **1. Email is optional:**
    - Create gym member via Filament form with empty email → should succeed
    - Create gym member via factory without email (using `state(['email' => null])`) → should succeed
    - Assert email is null in database

    **2. Phone is required:**
    - Create gym member via Filament form with empty phone → should fail validation
    - Create gym member via factory without phone and defaulting explicitly → should fail at DB level (NOT NULL)

    **3. Phone uniqueness (per gym):**
    - Create first member with a phone → succeeds
    - Create second member with same phone *in same gym* → should fail (unique violation)

    **4. Same phone in different gyms is allowed:**
    - Create one member with phone X in gym A
    - Create another with phone X in gym B → should succeed

  **Must NOT do**:
  - Don't test email uniqueness thoroughly — it already works and we're not changing it
  - Don't over-test — focus on what changed

  **Recommended Agent Profile**:
  - **Category**: `quick` — Straightforward test file, ~5 test methods
  - **Skills**: `[]`

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Task 7)
  - **Blocks**: Final Verification
  - **Blocked By**: Tasks 1-6

  **References**:
  - `tests/Feature/CreateGymMemberTest.php` — Follow existing test patterns (Filament form testing, assertion style)
  - `tests/Feature/BiometricConsentTest.php` — Existing test pattern for gym member creation
  - `database/factories/GymMemberFactory.php` — Factory usage pattern

  **Acceptance Criteria**:
  - [ ] `php artisan test --filter=GymMemberPhoneEmailValidationTest --compact` passes (all test methods)
  - [ ] Each test covers a specific constraint change

  **QA Scenarios**:
  ```
  Scenario: New validation tests pass
    Tool: Bash
    Preconditions: All code changes applied, migration run
    Steps:
      1. php artisan test --filter=GymMemberPhoneEmailValidationTest --compact
    Expected Result: All tests PASS, exit code 0
    Evidence: .sisyphus/evidence/task-8-new-test-pass.txt

  Scenario: Full test suite still passes
    Tool: Bash
    Preconditions: Previous scenario passed
    Steps:
      1. php artisan test --compact
    Expected Result: All tests PASS, exit code 0
    Evidence: .sisyphus/evidence/task-8-full-suite-pass.txt
  ```

  **Evidence to Capture**:
  - [ ] `.sisyphus/evidence/task-8-new-test-pass.txt`
  - [ ] `.sisyphus/evidence/task-8-full-suite-pass.txt`


---

## Final Verification Wave

> Run after ALL implementation tasks complete. All must PASS before work is marked done.

- [ ] F1. **Run full test suite** — `bash`
  Run `php artisan test --compact`. All tests must pass. If any fail, investigate and fix.
  Output: `Tests: [N] passed, [N] failed`
  Evidence: `.sisyphus/evidence/final-full-test-suite.txt`

- [ ] F2. **Run Pint formatting check** — `bash`
  Run `vendor/bin/pint --dirty`. No unfixed formatting issues.
  Output: `PASS` (no changes needed) or `FAIL` (changes pending)
  Evidence: `.sisyphus/evidence/final-pint-check.txt`

- [ ] F3. **Verify migration cycle** — `bash`
  Run `php artisan migrate:rollback && php artisan migrate` to verify both directions work.
  Output: Both commands exit 0
  Evidence: `.sisyphus/evidence/final-migration-cycle.txt`

- [ ] F4. **Verify schema matches requirements** — `bash` + `Laravel Boost database-schema`
  Check: email nullable=true, phone nullable=false, unique(gym_id, phone) exists, index(phone) exists.
  Output: All 4 schema properties verified
  Evidence: `.sisyphus/evidence/final-schema-verification.txt`

---


## Success Criteria

### Verification Commands
```bash
# Run all tests
composer test

# Run pint formatting
vendor/bin/pint --dirty

# Apply migration
php artisan migrate

# Rollback migration
php artisan migrate:rollback

# Check schema
php artisan db:show --table=gym_members
```

### Final Checklist
- [ ] **Migration applies and rolls back** — `php artisan migrate` then `php artisan migrate:rollback` both succeed
- [ ] **Email is nullable** — DB schema shows `YES` in Null for email column
- [ ] **Phone is NOT NULL** — DB schema shows `NO` in Null for phone column
- [ ] **Composite unique** — DB shows UNIQUE constraint on (`gym_id`, `phone`)
- [ ] **Phone index** — DB shows INDEX on `phone`
- [ ] **Existing null phones filled** — Zero records with null phone after migration
- [ ] **All tests pass** — `composer test` exits with code 0
- [ ] **Pint passes** — `vendor/bin/pint --dirty` exits with code 0
- [ ] **Filament form accepts empty email** — Manual Playwright verification
- [ ] **Filament form requires phone** — Manual Playwright verification
- [ ] **Import accepts missing email** — CSV import test
- [ ] **Import rejects missing phone** — CSV import test
- [ ] **Factory generates unique phones** — `GymMember::factory()->count(5)->create()` all unique
