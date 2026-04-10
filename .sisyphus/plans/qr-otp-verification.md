# QR Code Attendance - OTP Verification Implementation Plan

## Overview
Implement secure OTP verification for QR code attendance system using Twilio Verify API, with fallback to simple phone verification when OTP is disabled or WhatsApp is not configured.

## Current State
- QR code attendance system exists with OTP verification via manual WhatsApp message
- GymSettings page has notification toggles but no QR attendance OTP setting
- No Twilio Verify service integration

## Requirements

### 1. Twilio Verify API Integration
- Use Twilio Verify API instead of manual WhatsApp message for OTP
- **Step 1**: Create verification service (or use existing one stored per gym)
- **Step 2**: Send verification via `POST /Services/{sid}/Verifications` with channel: whatsapp
- **Step 3**: Verify code via `POST /Services/{sid}/VerificationChecks` with code and phone
- Response: `status: "approved"` = success, `status: "pending"` = failed

### 2. Settings Toggle
- Add "CheckIn/CheckOut OTP" toggle in GymSettings notification tab
- Only show/enable when WhatsApp is configured
- Store in GymSetting with key: `qr_checkin_otp_enabled`

### 3. OtpService Enhancement
- Check if OTP enabled via setting
- Check if WhatsApp configured
- Use Twilio Verify API for sending and verifying OTP
- Return proper flags for fallback logic

### 4. QRAttendance Component Update
- If OTP enabled + WhatsApp configured → OTP flow (send → verify)
- If OTP disabled OR WhatsApp not configured → direct check-in (phone → verify membership → check-in)
- Use OtpService for all OTP operations

### 5. Background Job (Optional for v2)
- For now, keep synchronous for simplicity
- Can add `SendOtpJob` later if needed

---

## Implementation Tasks

### Task 1: Update OtpService with Twilio Verify API
- Replace manual WhatsApp message with Verify API calls
- Add `verifyCode()` method to verify OTP
- Add `getOrCreateVerifyServiceSid()` to get per-gym service SID

**File**: `app/Services/OtpService.php`

**Changes**:
```php
// New methods needed:
- getOrCreateVerifyServiceSid(Gym $gym): string
- sendVerification(GymMember $member, Gym $gym): array
- verifyCode(string $phone, string $code, Gym $gym): array
```

### Task 2: Update QRAttendance Component
- Use OtpService for OTP flow
- Implement fallback: if OTP disabled/not configured → direct check-in

**File**: `app/Livewire/Frontend/QRAttendance.php`

**Changes**:
- Remove inline OTP logic, use OtpService
- Add `checkInWithoutOtp()` method for fallback
- Update UI to show either OTP form or direct check-in based on config

### Task 3: Add Settings Toggle in GymSettings
- Add "CheckIn/CheckOut OTP" toggle in notification tab
- Only show when WhatsApp is configured
- Add to NOTIFICATION_KEYS array

**File**: `app/Filament/Admin/Pages/GymSettings.php`

**Changes**:
- Add `qr_checkin_otp_enabled` to NOTIFICATION_KEYS
- Add Toggle component in WhatsApp notification tab

### Task 4: Create Verify Service Config
- Store Verify service SID per gym in GymSetting
- Use a single global service or per-gym service

**Key**: `twilio_verify_service_sid`

---

## Technical Details

### Twilio Verify API Endpoints
```
POST https://verify.twilio.com/v2/Services/{service_sid}/Verifications
  - channel: whatsapp
  - to: +91XXXXXXXXXX

POST https://verify.twilio.com/v2/Services/{service_sid}/VerificationChecks  
  - code: 123456
  - to: +91XXXXXXXXXX
```

### Response Handling
```php
// Send verification response:
['status' => 'pending', 'sid' => 'VE...']

// Check verification response:
['status' => 'approved', 'valid' => true]  // Success
['status' => 'pending', 'valid' => false]   // Failed
```

### GymSetting Keys
| Key | Type | Description |
|-----|------|-------------|
| `qr_checkin_otp_enabled` | boolean | Enable OTP for QR check-in |
| `twilio_verify_service_sid` | string | Per-gym Verify service SID |

---

## Verification Strategy

### Test Scenarios
1. **OTP Enabled + WhatsApp Configured**: OTP flow (enter phone → receive code → verify → check-in)
2. **OTP Disabled**: Direct check-in (enter phone → verify → check-in)
3. **OTP Enabled + WhatsApp Not Configured**: Direct check-in (fallback)
4. **Invalid OTP**: Show error, allow retry
5. **Already Checked In**: Show already checked in message

### Acceptance Criteria
- [ ] Gym owner can enable/disable OTP in Settings
- [ ] OTP toggle only visible when WhatsApp configured
- [ ] OTP flow works with Twilio Verify API
- [ ] Fallback works when OTP disabled
- [ ] Duplicate check-in prevention still works
- [ ] Member can check in with phone number only (no OTP)

---

## Files to Modify

| File | Action |
|------|--------|
| `app/Services/OtpService.php` | Update with Verify API |
| `app/Livewire/Frontend/QRAttendance.php` | Use OtpService, add fallback |
| `app/Filament/Admin/Pages/GymSettings.php` | Add toggle |
| `resources/views/livewire/frontend/qr-attendance.blade.php` | Update UI for fallback |

---

## Dependencies
- Twilio PHP SDK (already installed via twilio/sdk)
- GymSetting model (already exists)
- WhatsAppAccount model (already exists)
- TwilioWhatsAppService (already exists - but Verify is separate)

---

## Next Steps (After Implementation)
1. Run migration (if any new settings columns needed)
2. Test OTP flow end-to-end
3. Test fallback when OTP disabled
4. Run Pint formatter
5. Consider adding background job for OTP (future)