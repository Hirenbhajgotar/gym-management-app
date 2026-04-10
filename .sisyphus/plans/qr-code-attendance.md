# QR Code Attendance System

## TL;DR

> **Quick Summary**: Implement a QR-code based attendance system where each gym owner can generate ONE QR code from their admin panel. Members scan the QR code with their phone, enter their member ID/phone, and get automatically checked in.
> 
> **Deliverables**:
> - QR code generation library integrated
> - Database migration adding `qr_code_token` to gyms table
> - Filament admin page to generate/view/print QR code
> - Frontend attendance page (Livewire) for member check-in
> - Attendance recording with `check_in_method = 'qr_code'`
> - Duplicate check-in prevention (one per day)
> 
> **Estimated Effort**: Medium
> **Parallel Execution**: YES - 3 waves
> **Critical Path**: Migration → Model → Admin Page → Frontend Page → Routes → Final QA

---

## Context

### Original Request
Create a QR-code based attendance system for gym members. The owner generates a QR code from the admin panel, puts it at the door/reception, and members scan it with their phone to check in. The web-based attendance system (member/trainer app not yet ready).

**Key Requirements Confirmed by User:**
- One QR code per gym (tenant)
- Always valid (no expiration)
- Check-in flow: Member scans QR → enters member ID/phone → auto attendance
- Duplicate prevention: One check-in per day allowed

### Interview Summary
**Key Discussions**:
- QR Code Generation: Using simplesoftwareio/simple-qrcode library
- QR Code Strategy: One per gym, always valid
- Check-in Method: Uses existing `check_in_method = 'qr_code'`
- Duplicate Prevention: Update existing attendance if already checked in today

**Research Findings**:
- Attendances model already has `check_in_method` field
- Gym model exists with `slug` field (can use for QR URL)
- Filament admin pages exist in `app/Filament/Admin/Pages/`
- Multi-tenant system uses `gym_id` on all tenant-scoped tables

### Metis Review
**Identified Gaps** (addressed):
- QR token security: Added unique token generation to prevent enumeration attacks
- Invalid member ID handling: Added validation and error messaging
- Check-in confirmation: Added success message with attendance details

---

## Work Objectives

### Core Objective
Allow gym owners to generate a QR code that members can scan to automatically check in to the gym.

### Concrete Deliverables
1. **QR Code Library**: Add `simplesoftwareio/simple-qrcode` package to generate QR codes
2. **Database Migration**: Add `qr_code_token` column to `gyms` table (unique, nullable)
3. **Gym Model Update**: Add `qrCodeToken` attribute and `generateQrCodeToken()` method
4. **Admin Page**: Create Filament page `QRCodeManager` in gym admin panel
5. **Frontend Page**: Create Livewire component `QRAttendance` for member check-in
6. **Attendance Service**: Implement check-in logic with duplicate prevention
7. **Routes**: Add routes for QR attendance page and check-in submission

### Definition of Done
- [ ] QR code displays in admin panel for each gym
- [ ] QR code points to valid attendance URL
- [ ] Member can scan QR and land on attendance page
- [ ] Member enters ID/phone and gets checked in
- [ ] Duplicate check-ins are prevented (one per day)
- [ ] Attendance shows `check_in_method = 'qr_code'`

### Must Have
- QR code generation for each gym
- Unique token per gym for security
- Mobile-responsive attendance page
- Clear success/error messages
- Audit trail via attendances table

### Must NOT Have (Guardrails)
- No biometric integration (separate feature)
- No trainer-specific QR codes (different flow)
- No mobile app integration (not ready)
- No time-based QR code rotation (scope creep)

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit with feature tests)
- **Automated tests**: Tests-after (add tests after implementation)
- **Framework**: PHPUnit (existing test setup)

### QA Policy
Every task includes agent-executed QA scenarios - no human action required.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation - Start Immediately):
├── Task 1: Add QR code library to composer.json + install
├── Task 2: Create database migration for qr_code_token
├── Task 3: Update Gym model with qrCodeToken methods
└── Task 4: Run migration

Wave 2 (Core Modules - After Wave 1):
├── Task 5: Create QRAttendanceService for check-in logic
├── Task 6: Create Filament QRCodeManager admin page
├── Task 7: Create Livewire QRAttendance frontend component
└── Task 8: Create QR attendance blade view

Wave 3 (Integration - After Wave 2):
├── Task 9: Add routes for QR attendance
├── Task 10: Test full flow end-to-end
└── Task 11: Run Pint formatter

Wave FINAL (After ALL tasks):
├── Task F1: Plan Compliance Audit (oracle)
├── Task F2: Code Quality Review (unspecified-high)
├── Task F3: Real Manual QA (unspecified-high)
└── Task F4: Scope Fidelity Check (deep)
-> Present results -> Get explicit user okay
```

### Dependency Matrix
- **1-4**: No dependencies - can start immediately
- **5-8**: Depend on Wave 1 completion
- **9-11**: Depend on Wave 2 completion
- **F1-F4**: Depend on Wave 3 completion

---

## TODOs

- [ ] 1. Add QR code library to composer.json

  **What to do**:
  - Add `simplesoftwareio/simple-qrcode` package to `composer.json`
  - Run `composer install` to install the package
  - Publish config if needed

  **Must NOT do**:
  - Don't modify any other package versions
  - Don't add unnecessary dependencies

  **Recommended Agent Profile**:
  > - **Category**: `quick`
  >   - Reason: Simple composer dependency addition
  > - **Skills**: []
  > - **Skills Evaluated but Omitted**: None needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3, 4)
  - **Blocks**: [Tasks 5-11]
  - **Blocked By**: None (can start immediately)

  **References**:
  - `composer.json:14` - Add new package in require section
  - Packagist: `simplesoftwareio/simple-qrcode` - QR code generation library

  **Acceptance Criteria**:
  - [ ] Package added to composer.json
  - [ ] `composer install` completes without errors

- [ ] 2. Create database migration for qr_code_token

  **What to do**:
  - Create migration: `database/migrations/XXXX_XX_XX_XXXXXX_add_qr_code_token_to_gyms_table.php`
  - Add nullable `qr_code_token` column (string, 64, unique)
  - Use `Str::random(32)` for token generation

  **Must NOT do**:
  - Don't drop any existing columns
  - Don't modify gym_id foreign keys

  **Recommended Agent Profile**:
  > - **Category**: `quick`
  >   - Reason: Standard Laravel migration
  > - **Skills**: []
  > - **Skills Evaluated but Omitted**: None needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3, 4)
  - **Blocks**: [Tasks 5-11]
  - **Blocked By**: None (can start immediately)

  **References**:
  - `database/migrations/` - Existing migration pattern
  - `app/Models/Gym.php` - Gym model structure

  **Acceptance Criteria**:
  - [ ] Migration file created
  - [ ] Migration adds qr_code_token column (nullable, unique)

- [ ] 3. Update Gym model with qrCodeToken methods

  **What to do**:
  - Add `qr_code_token` to `$fillable` array in Gym model
  - Add `qrCodeToken()` method to generate token if null
  - Add `getQrCodeUrl()` method to generate full attendance URL

  **Must NOT do**:
  - Don't modify existing relationships
  - Don't change existing fillable fields

  **Recommended Agent Profile**:
  > - **Category**: `quick`
  >   - Reason: Simple model extension
  > - **Skills**: []
  > - **Skills Evaluated but Omitted**: None needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 4)
  - **Blocks**: [Tasks 5-11]
  - **Blocked By**: None (can start immediately)

  **References**:
  - `app/Models/Gym.php` - Existing Gym model structure

  **Acceptance Criteria**:
  - [ ] qr_code_token in fillable
  - [ ] generateQrCodeToken() method exists
  - [ ] getQrCodeUrl() method exists

- [ ] 4. Run migration

  **What to do**:
  - Run `php artisan migrate` to apply the migration
  - Verify the column was added

  **Must NOT do**:
  - Don't force migration in production without backup

  **Recommended Agent Profile**:
  > - **Category**: `quick`
  >   - Reason: Standard artisan command
  > - **Skills**: []
  > - **Skills Evaluated but Omitted**: None needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 3)
  - **Blocks**: [Tasks 5-11]
  - **Blocked By**: Tasks 1, 2 (migration file must exist)

  **References**:
  - `php artisan migrate` - Laravel migration command

  **Acceptance Criteria**:
  - [ ] Migration runs successfully
  - [ ] `qr_code_token` column exists in gyms table

- [ ] 5. Create QRAttendanceService for check-in logic

  **What to do**:
  - Create `app/Services/QRAttendanceService.php`
  - Implement `checkIn(array $data): array` method
  - Implement duplicate check-in prevention logic
  - Use existing Attendances model to record attendance

  **Must NOT do**:
  - Don't modify existing attendance recording logic for other methods
  - Don't add biometric-related logic

  **Recommended Agent Profile**:
  > - **Category**: `deep`
  >   - Reason: Core business logic - requires careful implementation
  > - **Skills**: []
  > - **Skills Evaluated but Omitted**: None needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 6, 7, 8)
  - **Blocks**: [Tasks 9-11]
  - **Blocked By**: Wave 1 (Tasks 1-4)

  **References**:
  - `app/Models/Attendances.php` - Existing attendance model
  - `app/Models/GymMember.php` - Member model for lookup

  **Acceptance Criteria**:
  - [ ] Service created at app/Services/QRAttendanceService.php
  - [ ] checkIn() returns success/error array
  - [ ] Duplicate check-in returns existing attendance
  - [ ] New check-in creates attendance record

  **QA Scenarios**:

  ```
  Scenario: Successful first check-in of the day
    Tool: Bash
    Preconditions: Member exists, no attendance today
    Steps:
      1. Call QRAttendanceService->checkIn with valid member_id and gym_id
    Expected Result: Attendance created with check_in_method='qr_code'

  Scenario: Duplicate check-in prevention
    Tool: Bash
    Preconditions: Member already checked in today
    Steps:
      1. Call QRAttendanceService->checkIn with same member_id and gym_id
    Expected Result: Returns existing attendance, no duplicate created
  ```

- [ ] 6. Create Filament QRCodeManager admin page

  **What to do**:
  - Create `app/Filament/Admin/Pages/QRCodeManager.php`
  - Show current QR code (if generated)
  - Add "Generate QR Code" button
  - Add "Download QR Code" button
  - Add "Print QR Code" button
  - Display attendance URL for manual entry

  **Must NOT do**:
  - Don't create separate QR codes for different locations
  - Don't add time-based rotation options

  **Recommended Agent Profile**:
  > - **Category**: `visual-engineering`
  >   - Reason: Filament admin page with UI components
  > - **Skills**: [`fluxui-development`]
  >   - `fluxui-development`: For Flux UI components in the admin page
  > - **Skills Evaluated but Omitted**:
  >   - `tailwindcss-development`: Not needed - Flux components handle styling

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 5, 7, 8)
  - **Blocks**: [Tasks 9-11]
  - **Blocked By**: Wave 1 (Tasks 1-4)

  **References**:
  - `app/Filament/Admin/Pages/GymSettings.php` - Similar admin page pattern
  - `app/Filament/Admin/Pages/Dashboard.php` - Page structure
  - `simplesoftwareio/simple-qrcode` - QR generation

  **Acceptance Criteria**:
  - [ ] Page displays QR code (or placeholder if not generated)
  - [ ] "Generate QR Code" button works
  - [ ] "Download QR Code" button works
  - [ ] Attendance URL is displayed

  **QA Scenarios**:

  ```
  Scenario: View QR code page
    Tool: Browser automation
    Preconditions: Admin authenticated, on QRCodeManager page
    Steps:
      1. Navigate to QRCodeManager page
      2. Take screenshot
    Expected Result: Page loads with QR code section visible
  ```

- [ ] 7. Create Livewire QRAttendance frontend component

  **What to do**:
  - Create `app/Livewire/Frontend/QRAttendance.php`
  - Accept `$gymId` and `$token` in mount
  - Validate token matches gym
  - Handle member ID/phone input form
  - Call QRAttendanceService->checkIn()
  - Return success/error state

  **Must NOT do**:
  - Don't require authentication (members may not be logged in)
  - Don't add biometric verification

  **Recommended Agent Profile**:
  > - **Category**: `visual-engineering`
  >   - Reason: Livewire component with form handling
  > - **Skills**: [`volt-development`]
  >   - `volt-development`: For Volt single-file component patterns
  > - **Skills Evaluated but Omitted**:
  >   - `tailwindcss-development`: Not needed - existing frontend patterns

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 5, 6, 8)
  - **Blocks**: [Tasks 9-11]
  - **Blocked By**: Wave 1 (Tasks 1-4)

  **References**:
  - `app/Livewire/Frontend/BlogDetail.php` - Existing frontend Livewire pattern

  **Acceptance Criteria**:
  - [ ] Component validates gym token on mount
  - [ ] Form accepts member_id or phone
  - [ ] Check-in returns success/error
  - [ ] Success shows attendance confirmation

- [ ] 8. Create QR attendance blade view

  **What to do**:
  - Create `resources/views/livewire/frontend/qr-attendance.blade.php`
  - Mobile-responsive layout
  - Form with member ID / phone input
  - Submit button
  - Success message display
  - Error message display
  - Gym name and logo display

  **Must NOT do**:
  - Don't add complex navigation
  - Don't require login

  **Recommended Agent Profile**:
  > - **Category**: `visual-engineering`
  >   - Reason: Blade view with Tailwind styling
  > - **Skills**: [`tailwindcss-development`]
  >   - `tailwindcss-development`: For responsive styling
  > - **Skills Evaluated but Omitted**:
  >   - `fluxui-development`: Not needed - this is frontend, not admin

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 5, 6, 7)
  - **Blocks**: [Tasks 9-11]
  - **Blocked By**: Wave 1 (Tasks 1-4)

  **References**:
  - `resources/views/livewire/frontend/blog-detail.blade.php` - Existing frontend pattern

  **Acceptance Criteria**:
  - [ ] Form displays correctly
  - [ ] Mobile responsive
  - [ ] Success message shows on check-in
  - [ ] Error message shows on invalid input

- [ ] 9. Add routes for QR attendance

  **What to do**:
  - Add GET route: `/attendance/qr/{gym_id}/{token}` - renders QRAttendance page
  - Add POST route: `/attendance/qr/{gym_id}/{token}/check-in` - processes check-in
  - Register routes in `routes/web.php`

  **Must NOT do**:
  - Don't modify existing routes
  - Don't add API routes here

  **Recommended Agent Profile**:
  > - **Category**: `quick`
  >   - Reason: Simple route registration
  > - **Skills**: []
  > - **Skills Evaluated but Omitted**: None needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 10, 11)
  - **Blocks**: [Tasks F1-F4]
  - **Blocked By**: Wave 2 (Tasks 5-8)

  **References**:
  - `routes/web.php` - Existing route definitions

  **Acceptance Criteria**:
  - [ ] Routes registered in web.php
  - [ ] GET route renders QRAttendance component
  - [ ] POST route processes check-in
  - [ ] Invalid token returns 404

- [ ] 10. Test full flow end-to-end

  **What to do**:
  - Test QR code generation in admin
  - Test QR code URL generation
  - Test scanning QR code (manual URL visit)
  - Test valid member check-in
  - Test invalid member error
  - Test duplicate check-in prevention

  **Must NOT do**:
  - Don't modify test database data without verification

  **Recommended Agent Profile**:
  > - **Category**: `unspecified-high`
  >   - Reason: End-to-end testing across multiple components
  > - **Skills**: [`playwright`]
  >   - `playwright`: For browser automation testing

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 9, 11)
  - **Blocks**: [Tasks F1-F4]
  - **Blocked By**: Wave 2 (Tasks 5-8)

  **References**:
  - `tests/Feature/` - Existing test patterns

  **Acceptance Criteria**:
  - [ ] QR code displays in admin panel
  - [ ] QR code URL is valid
  - [ ] Member can check in with valid ID
  - [ ] Invalid ID shows error
  - [ ] Duplicate check-in shows existing attendance

- [ ] 11. Run Pint formatter

  **What to do**:
  - Run `vendor/bin/pint --dirty` on modified files
  - Verify all PHP files pass formatting

  **Must NOT do**:
  - Don't run pint on unchanged files unnecessarily

  **Recommended Agent Profile**:
  > - **Category**: `quick`
  >   - Reason: Code formatting
  > - **Skills**: []
  > - **Skills Evaluated but Omitted**: None needed

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 9, 10)
  - **Blocks**: [Tasks F1-F4]
  - **Blocked By**: Wave 2 (Tasks 5-8)

  **References**:
  - `vendor/bin/pint` - Laravel Pint formatter

  **Acceptance Criteria**:
  - [ ] Pint runs without errors
  - [ ] All files pass formatting

---

## Final Verification Wave

> 4 review agents run in PARALLEL. ALL must APPROVE. Present consolidated results to user and get explicit "okay" before completing.

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Read the plan end-to-end. For each "Must Have": verify implementation exists (read file, curl endpoint, run command). For each "Must NOT Have": search codebase for forbidden patterns — reject with file:line if found. Check evidence files exist in .sisyphus/evidence/. Compare deliverables against plan.
  Output: `Must Have [N/N] | Must NOT Have [N/N] | Tasks [N/N] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `tsc --noEmit` + linter + `bun test`. Review all changed files for: `as any`/`@ts-ignore`, empty catches, console.log in prod, commented-out code, unused imports. Check AI slop: excessive comments, over-abstraction, generic names (data/result/item/temp).
  Output: `Build [PASS/FAIL] | Lint [PASS/FAIL] | Tests [N pass/N fail] | Files [N clean/N issues] | VERDICT`

- [ ] F3. **Real Manual QA** — `unspecified-high` (+ `playwright` skill if UI)
  Start from clean state. Execute EVERY QA scenario from EVERY task — follow exact steps, capture evidence. Test cross-task integration (features working together, not isolation). Test edge cases: empty state, invalid input, rapid actions. Save to `.sisyphus/evidence/final-qa/`.
  Output: `Scenarios [N/N pass] | Integration [N/N] | Edge Cases [N tested] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  For each task: read "What to do", read actual diff (git log/diff). Verify 1:1 — everything in spec was built (no missing), nothing beyond spec was built (no creep). Check "Must NOT do" compliance. Detect cross-task contamination: Task N touching Task M's files. Flag unaccounted changes.
  Output: `Tasks [N/N compliant] | Contamination [CLEAN/N issues] | Unaccounted [CLEAN/N files] | VERDICT`

---

## Commit Strategy

- **1**: `feat(attendance): add QR code attendance system` - Initial feature

Or split into multiple commits:
- `feat(attendance): add QR code library and migration` - Tasks 1-4
- `feat(attendance): add QR attendance service` - Task 5
- `feat(attendance): add QR code manager admin page` - Task 6
- `feat(attendance): add frontend QR attendance page` - Tasks 7-8
- `feat(attendance): add QR attendance routes` - Task 9

---

## Success Criteria

### Verification Commands
```bash
composer install  # QR library installed
php artisan migrate  # qr_code_token column added
# Visit admin panel QR code page
# Generate QR code
# Scan QR code with phone browser
# Enter member ID
# Verify attendance created with check_in_method='qr_code'
```

### Final Checklist
- [ ] QR code library installed
- [ ] Database migration applied
- [ ] Gym model updated with token methods
- [ ] Admin page displays QR code
- [ ] Frontend page accepts member check-in
- [ ] Attendance recorded with qr_code method
- [ ] Duplicate check-in prevented
- [ ] All tests pass
- [ ] Pint formatting applied
