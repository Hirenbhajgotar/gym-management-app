# Plan: Check-Out QR Code Implementation

## TL;DR

> **Quick Summary**: Implement check-out QR code functionality alongside existing check-in QR code in QRCodeManager.php with split form layout.

> **Deliverables**:
> - Database migration for checkout QR token
> - Gym model methods for checkout QR
> - QRAttendanceService checkOut method
> - QRCodeManager with split form (check-in + check-out sections)

> **Estimated Effort**: Short
> **Parallel Execution**: NO - sequential
> **Critical Path**: Migration → Model → Service → Page

---

## Context

### Original Request
Add checkout QR-code in `app\Filament\Admin\Pages\QRCodeManager.php`. Check-in QR code is already implemented. Use split form layout for check-in QR code and check-out QR code.

### Current Implementation
- **Check-in QR**: Working via `qr_code_token` in `gyms` table
- **QRCodeManager**: Single section for check-in QR only
- **QRAttendanceService**: Has `checkIn()` method only

### What's Missing
- Checkout QR code token column
- Checkout QR generation methods in Gym model
- Checkout method in QRAttendanceService
- Split form layout in QRCodeManager

---

## Work Objectives

### Core Objective
Add check-out QR code functionality to allow members to check out by scanning a QR code.

### Concrete Deliverables
1. Database migration: Add `qr_code_checkout_token` column to `gyms` table
2. Gym model: Add checkout QR methods (`generateCheckoutQrCodeToken()`, `getCheckoutQrCodeUrl()`)
3. QRAttendanceService: Add `checkOut()` method with duplicate prevention
4. QRCodeManager: Split form layout with check-in and check-out sections

### Definition of Done
- [ ] Migration runs successfully
- [ ] Check-out QR code generates and displays in admin panel
- [ ] Members can scan check-out QR and record their checkout time
- [ ] Duplicate checkout prevention (one checkout per day)
- [ ] Pint formatter passes

### Must Have
- Check-out QR code generation and display
- Check-out attendance recording
- Duplicate prevention for checkout

### Must NOT Have
- Overwriting existing check-in functionality
- Creating duplicate attendance records

---

## Execution Strategy

### Task Sequence
1. Create migration for `qr_code_checkout_token` column
2. Update Gym model with checkout QR methods
3. Update QRAttendanceService with checkOut method
4. Update QRCodeManager with split form layout
5. Run Pint formatter

---

## TODOs

- [ ] 1. Create migration for checkout QR token column

  **What to do**:
  - Create migration: `2026_04_13_000000_add_qr_code_checkout_token_to_gyms_table.php`
  - Add column: `qr_code_checkout_token` (string, nullable)
  - Run migration

  **References**:
  - `database/migrations/2026_04_08_120000_add_qr_code_token_to_gyms_table.php` - Reference for existing QR token migration

  **Acceptance Criteria**:
  - [ ] Migration file created
  - [ ] php artisan migrate runs successfully

- [ ] 2. Add checkout QR methods to Gym model

  **What to do**:
  - Add `$fillable` update to include `qr_code_checkout_token`
  - Add `generateCheckoutQrCodeToken()` method
  - Add `getCheckoutQrCodeUrl()` method
  - Add `hasValidCheckoutQrCodeToken()` method

  **References**:
  - `app/Models/Gym.php:292-327` - Existing QR code methods to follow pattern

  **Acceptance Criteria**:
  - [ ] Gym model updated with checkout QR methods
  - [ ] Methods follow existing pattern from check-in methods

- [ ] 3. Add checkOut method to QRAttendanceService

  **What to do**:
  - Add `checkOut()` method with logic:
    - Find member by ID or phone
    - Check if member has checked in today
    - Check if already checked out today (prevent duplicates)
    - Update attendance with check_out_time
    - Return appropriate response

  **References**:
  - `app/Services/QRAttendanceService.php:18-65` - Existing checkIn method for pattern

  **Acceptance Criteria**:
  - [ ] checkOut method implemented
  - [ ] Duplicate checkout prevention works
  - [ ] Returns proper response structure

- [ ] 4. Update QRCodeManager with split form layout

  **What to do**:
  - Keep existing check-in section
  - Add check-out section with:
    - Placeholder for no QR message (if not generated)
    - Generate button
    - QR display (when generated)
    - Regenerate button with confirmation
  - Use Grid or Split layout to put them side by side or stacked

  **References**:
  - `app/Filament/Admin/Pages/QRCodeManager.php:52-138` - Existing form structure
  - Use LaraZeus QR facade as currently used

  **Acceptance Criteria**:
  - [ ] Both check-in and check-out QR codes displayed
  - [ ] Split layout applied
  - [ ] Generate/regenerate buttons work for both

- [ ] 5. Run Pint formatter

  **What to do**:
  - Run `vendor/bin/pint --dirty` on all modified files

  **Acceptance Criteria**:
  - [ ] All files formatted correctly

---

## Final Verification Wave

- [ ] F1. Verify migration runs without errors
- [ ] F2. Verify check-out QR generates in admin panel
- [ ] F3. Verify split form layout displays correctly
- [ ] F4. Run Pint formatter

---

## Success Criteria

### Verification Commands
```bash
php artisan migrate
vendor/bin/pint --dirty
```

### Final Checklist
- [ ] Check-out QR code column exists in database
- [ ] Check-out QR code generates in admin panel
- [ ] Split form layout shows both QR codes
- [ ] Code follows existing patterns
- [ ] Pint formatter passes