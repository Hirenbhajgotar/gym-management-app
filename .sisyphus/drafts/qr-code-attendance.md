# Draft: QR Code Attendance System

## Requirements (confirmed)

### Core Functionality
- **One QR code per gym**: Owner can generate ONE QR code from admin panel
- **QR code validity**: Always valid (no expiration)
- **Check-in flow**: Member scans QR code → enters member ID/phone → attendance recorded automatically
- **Duplicate prevention**: One check-in per day allowed (existing check-in is updated, not duplicated)

### Technical Details
- **QR Code Content**: URL pointing to attendance page with gym identifier
- **QR Library**: Need to add a QR code generation library (simple PHP QR code or similar)
- **Attendance recording**: Use existing `attendances` table, add `check_in_method = 'qr_code'`
- **Admin Panel**: Filament page in gym admin for generating/viewing QR code
- **Frontend**: Web-based attendance page (no mobile app yet)

## Technical Decisions

### QR Code Library
- Need to add: `simplesoftwareio/simple-qrcode` package or similar

### Database Changes
- May need to add `qr_code_token` column to `gyms` table (unique identifier for QR URL)
- Or use existing gym slug/ID in URL

### URL Structure
- Route: `/attendance/qr/{gym_id}/{token}`
- Token validates the QR code is for this specific gym

### Check-in Method
- Use existing `check_in_method` field with value `qr_code`

## Scope Boundaries
- **INCLUDE**: 
  - Admin page to generate/view QR code
  - QR code generation logic
  - Frontend attendance page (member scans, enters ID, gets checked in)
  - Attendance recording with qr_code method
  - Duplicate check-in prevention
  
- **EXCLUDE**:
  - Mobile app integration (not ready)
  - Biometric integration with QR (separate feature)
  - Trainer QR attendance (different flow)

## Open Questions
1. Should the QR code display in the admin panel or be downloadable?
2. Should there be an option to regenerate the QR code?
3. What happens if member enters invalid ID?
4. Should we show member name confirmation before recording attendance?

## Multi-Tenant Context
- Each gym (tenant) has ONE QR code
- Gym owner generates from their admin panel
- QR code is unique per gym via token in URL
