# Biometric Device Door Lock/Unlock Implementation Plan

## TL;DR

> **Quick Summary**: Add door control capabilities to the existing biometric check-in system with three device purposes (attendance_only, door_access, door_access_no_attendance), supporting relay/wiegand/API door control methods, and duplicate attendance prevention for gyms with multiple devices.
> 
> **Deliverables**: 
> - 3 new enums (BiometricDevicePurpose, BiometricDoorControlType, DoorStatus)
> - 3 database migrations (device door columns, attendance door columns, door_events table)
> - 2 new models (DoorEvent), 2 updated models (BiometricDevice, Attendances)
> - 2 new DTOs (DoorControlResult), 1 updated DTO (CheckInResult)
> - 1 new service (DoorControlService), 1 rewritten service (CheckInService)
> - Updated webhook controller response format
> - Updated Filament form with purpose-driven visibility
> - 1 new command (PollBiometricDevices), 1 new event (DoorControllerUnreachable)
> 
> **Estimated Effort**: Large (18 discrete tasks)
> **Parallel Execution**: YES - Multiple waves
> **Critical Path**: Enums → Migrations → Models → DTOs → DoorControlService → CheckInService rewrite → Controller → Filament

---

## Context

### Existing Implementation
The biometric check-in system is already implemented with:
- **BiometricDevice** model with basic fields (device_name, device_type, provider_type, provider_config, status)
- **CheckInService** with processCheckIn() that verifies biometric, checks membership, detects fraud, creates attendance
- **BiometricDeviceResource** Filament form with full provider configuration
- **DTOs**: BiometricSample, TemplateRef, VerificationResult, GuardResult, CheckInResult

### What's Missing
The system currently has NO door control capability. The user's specification requires:
- Device purpose selection (attendance only vs door access)
- Door control types (relay, wiegand, API)
- Door controller integration
- Door event audit logging
- Duplicate attendance prevention

---

## Work Objectives

### Core Objective
Implement a complete door lock/unlock system integrated with biometric check-in that:
1. Supports 3 device purposes with different behaviors
2. Controls doors via relay, Wiegand, or external API
3. Creates complete audit trail for all door events
4. Prevents duplicate attendance when gym has multiple devices

### Concrete Deliverables
- Device purpose field drives all form visibility and business logic
- Door control configuration for API-based controllers
- Door events table for complete audit logging
- Updated CheckInService with purpose-driven branching logic

### Definition of Done
- [ ] Device form shows purpose selector, door config only shows when purpose != attendance_only
- [ ] Attendance records include door_status, door_denial_reason, door_unlocked_at
- [ ] Door events create audit records for unlock_granted, unlock_denied, unlock_failed
- [ ] API door controller called with proper timeout and error handling
- [ ] Relay/Wiegand devices return "skipped" status (device handles locally)
- [ ] Duplicate attendance prevented when attendance_only device already created record

### Must Have
- Purpose-driven behavior (attendance_only vs door_access vs door_access_no_attendance)
- Door control API integration with timeout and error handling
- Door event audit logging
- Duplicate attendance prevention

### Must NOT Have
- Separate check-in events table (use existing attendances table)
- Complex door relay wiring logic (device handles relay/wiegand locally)
- Multiple attendance records per member per day from different devices

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES - PHPUnit with existing biometric tests
- **Automated tests**: Tests-after (add tests for new door functionality)
- **Framework**: php artisan test (PHPUnit)

### QA Policy
Every task includes agent-executed QA scenarios:
- **Unit tests**: Test DoorControlResult factory methods, device purpose helpers
- **Feature tests**: Test CheckInService branching logic with mocked door control
- **Manual verification**: Check webhook response format matches specification

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation - Can run immediately):
├── Task 1: Create BiometricDevicePurpose enum
├── Task 2: Create BiometricDoorControlType enum
├── Task 3: Create DoorStatus enum (optional - can use string)
├── Task 4: Migration - add_purpose_and_door_columns_to_biometric_devices
├── Task 5: Migration - add_door_columns_to_attendances
├── Task 6: Migration - create_door_events_table
└── Task 7: Create DoorControlResult DTO

Wave 2 (Models & Relationships):
├── Task 8: Create DoorEvent model
├── Task 9: Update BiometricDevice model (fillable, casts, helpers)
├── Task 10: Update Attendances model (fillable, casts, doorEvent relationship)
└── Task 11: Update CheckInResult DTO (add doorResult field)

Wave 3 (Core Services):
├── Task 12: Create DoorControlService
└── Task 13: Rewrite CheckInService.processCheckIn() with door logic

Wave 4 (API & UI):
├── Task 14: Update BiometricWebhookController response format
├── Task 15: Update BiometricDeviceResource form (purpose-driven)
├── Task 16: Create DoorEventResource for audit viewing
└── Task 17: Create DoorControllerUnreachable event

Wave 5 (Command & Registration):
├── Task 18: Create PollBiometricDevices command
└── Task 19: Update BiometricServiceProvider (register DoorControlService)

Wave FINAL (Verification):
├── Task F1: Run php artisan test for biometric tests
├── Task F2: Verify webhook response format
└── Task F3: Verify form field visibility based on purpose
```

### Dependency Matrix
- **Tasks 1-7**: — — No dependencies, start immediately
- **Task 8**: 6 — Depends on migration creating door_events table
- **Task 9**: 6 — Depends on device door columns migration
- **Task 10**: 5 — Depends on attendance door columns migration  
- **Task 11**: 7 — Depends on DoorControlResult creation
- **Task 12**: 8, 9 — Depends on DoorEvent model and DoorControlResult
- **Task 13**: 9, 10, 11, 12 — Depends on updated models, DTO, and DoorControlService
- **Task 14**: 13 — Depends on CheckInService returning doorResult
- **Task 15**: 9, 13 — Depends on updated BiometricDevice and CheckInService
- **Task 16**: 8 — Depends on DoorEvent model
- **Task 17**: 12 — Depends on DoorControlService (fires event on API failure)
- **Task 18**: 9 — Depends on BiometricDevice having poll fields
- **Task 19**: 12 — Depends on DoorControlService being created

---

## TODOs

> Implementation + Test = ONE Task. Every task MUST have: Recommended Agent Profile + Parallelization info + QA Scenarios.

- [ ] 1. Create BiometricDevicePurpose enum

  **What to do**:
  - Create app/Enums/BiometricDevicePurpose.php
  - Define cases: AttendanceOnly, DoorAccess, DoorAccessNoAttendance
  - Add label() method returning human-readable strings
  - Use string backing type

  **Must NOT do**:
  - Don't add unrelated cases

  **Recommended Agent Profile**:
  - **Category**: `quick`
    - Reason: Simple enum creation, no complex logic
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2-7)
  - **Blocks**: Tasks 9, 13, 15
  - **Blocked By**: None

  **References**:
  - app/Enums/BiometricDeviceStatusEnum.php - Existing enum pattern to follow

  **Acceptance Criteria**:
  - [ ] Enum file created at app/Enums/BiometricDevicePurpose.php
  - [ ] Three cases defined with string backing
  - [ ] label() method returns correct strings

  **Commit**: YES (group 1)
  - Message: `feat(biometrics): add device purpose enum`
  - Files: `app/Enums/BiometricDevicePurpose.php`

---

- [ ] 2. Create BiometricDoorControlType enum

  **What to do**:
  - Create app/Enums/BiometricDoorControlType.php
  - Define cases: Relay, Wiegand, Api
  - Add label() method returning human-readable strings

  **Must NOT do**:
  - Don't add unrelated cases

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3-7)
  - **Blocks**: Tasks 9, 12, 15
  - **Blocked By**: None

  **References**:
  - app/Enums/BiometricDeviceStatusEnum.php - Existing enum pattern

  **Acceptance Criteria**:
  - [ ] Enum file created at app/Enums/BiometricDoorControlType.php
  - [ ] Three cases defined with string backing

  **Commit**: YES (group 1)
  - Message: `feat(biometrics): add door control type enum`
  - Files: `app/Enums/BiometricDoorControlType.php`

---

- [ ] 3. Migration - add purpose and door columns to biometric_devices

  **What to do**:
  - Create migration: database/migrations/2026_03_XX_XXXXXX_add_purpose_and_door_columns_to_biometric_devices_table.php
  - Add columns:
    - purpose: string, default 'attendance_only'
    - door_control_type: string, nullable
    - door_controller_url: string, nullable
    - door_controller_token: string, nullable (encrypted)
    - unlock_duration_seconds: integer, default 5
    - door_zone: string, nullable
    - connection_mode: string, default 'webhook'
    - poll_interval_seconds: integer, default 60
    - last_polled_at: timestamp, nullable

  **Must NOT do**:
  - Don't remove existing columns (offline_capable, supports_multi_modal)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1-2, 4-7)
  - **Blocks**: Task 9
  - **Blocked By**: None

  **References**:
  - database/migrations/2026_03_23_120000_create_biometric_devices_table.php - Existing structure

  **Acceptance Criteria**:
  - [ ] Migration created with all door-related columns
  - [ ] php artisan migrate runs without error

  **Commit**: YES (group 1)
  - Message: `feat(biometrics): add door control columns to biometric_devices`
  - Files: `database/migrations/*_add_purpose_and_door_columns_to_biometric_devices_table.php`

---

- [ ] 4. Migration - add door columns to attendances

  **What to do**:
  - Create migration: database/migrations/2026_03_XX_XXXXXX_add_door_columns_to_attendances_table.php
  - Add columns:
    - door_status: string, nullable (unlocked, locked, failed, skipped, not_applicable)
    - door_denial_reason: string, nullable
    - door_unlocked_at: timestamp, nullable

  **Must NOT do**:
  - Don't modify existing attendance columns

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1-3, 5-7)
  - **Blocks**: Task 10
  - **Blocked By**: None

  **References**:
  - database/migrations/2026_03_23_120003_add_biometric_columns_to_attendances_table.php - Existing attendance columns

  **Acceptance Criteria**:
  - [ ] Migration created with door status columns
  - [ ] php artisan migrate runs without error

  **Commit**: YES (group 1)
  - Message: `feat(biometrics): add door columns to attendances`
  - Files: `database/migrations/*_add_door_columns_to_attendances_table.php`

---

- [ ] 5. Migration - create door_events table

  **What to do**:
  - Create migration: database/migrations/2026_03_XX_XXXXXX_create_door_events_table.php
  - Create table with:
    - id, gym_id, device_id, gym_member_id (nullable), attendance_id (nullable)
    - event_type: unlock_granted, unlock_denied, unlock_failed, lock_confirmed
    - trigger_source: biometric, manual_staff, emergency
    - door_status: unlocked, locked, failed, skipped
    - denial_reason: nullable
    - unlock_duration_seconds: nullable
    - unlocked_at: nullable
    - ip_address: nullable
    - raw_response: json, nullable
    - timestamps
    - indexes on [gym_id, created_at], [gym_member_id, created_at], [device_id, created_at]

  **Must NOT do**:
  - Don't create unrelated tables

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1-4, 6-7)
  - **Blocks**: Task 8
  - **Blocked By**: None

  **References**:
  - database/migrations/2026_03_23_120001_create_biometric_templates_table.php - Reference for table structure

  **Acceptance Criteria**:
  - [ ] Migration creates door_events table with all columns
  - [ ] php artisan migrate runs without error

  **Commit**: YES (group 1)
  - Message: `feat(biometrics): create door_events table`
  - Files: `database/migrations/*_create_door_events_table.php`

---

- [ ] 6. Create DoorControlResult DTO

  **What to do**:
  - Create app/Biometrics/DTOs/DoorControlResult.php
  - Properties: success (bool), status (string), failureReason (?string), unlockedAt (?Carbon), raw (array)
  - Factory methods: unlocked(), locked(string), failed(string, array), skipped(), notApplicable()

  **Must NOT do**:
  - Don't create unnecessary properties

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1-5, 7)
  - **Blocks**: Task 11, 12
  - **Blocked By**: None

  **References**:
  - app/Biometrics/DTOs/CheckInResult.php - Existing DTO pattern
  - app/Biometrics/DTOs/VerificationResult.php - Existing DTO pattern

  **Acceptance Criteria**:
  - [ ] DoorControlResult created with all factory methods
  - [ ] Each factory returns correct status value

  **Commit**: YES (group 1)
  - Message: `feat(biometrics): add DoorControlResult DTO`
  - Files: `app/Biometrics/DTOs/DoorControlResult.php`

---

- [ ] 7. Create DoorEvent model

  **What to do**:
  - Create app/Models/DoorEvent.php
  - Fillable: gym_id, device_id, gym_member_id, attendance_id, event_type, trigger_source, denial_reason, door_status, unlock_duration_seconds, unlocked_at, ip_address, raw_response
  - Casts: unlocked_at => datetime, raw_response => array
  - Relationships: device(), gymMember(), attendance(), gym()

  **Must NOT do**:
  - Don't add business logic to model

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 5 migration)
  - **Parallel Group**: Wave 2 (with Tasks 8-10)
  - **Blocks**: Task 16
  - **Blocked By**: Task 5

  **References**:
  - app/Models/BiometricDevice.php - Existing model pattern
  - app/Models/BiometricTemplate.php - Existing model pattern

  **Acceptance Criteria**:
  - [ ] DoorEvent model created with all relationships
  - [ ] php artisan model:show DoorEvent shows correct schema

  **Commit**: YES (group 2)
  - Message: `feat(biometrics): create DoorEvent model`
  - Files: `app/Models/DoorEvent.php`

---

- [ ] 8. Update BiometricDevice model

  **What to do**:
  - Update app/Models/BiometricDevice.php
  - Add to $fillable: purpose, door_control_type, door_controller_url, door_controller_token, unlock_duration_seconds, door_zone, connection_mode, poll_interval_seconds, last_polled_at
  - Add to casts: door_controller_token => encrypted, unlock_duration_seconds => integer, poll_interval_seconds => integer, last_polled_at => datetime, purpose => BiometricDevicePurpose::class, door_control_type => BiometricDoorControlType::class
  - Add doorEvents() relationship: hasMany(DoorEvent::class, 'device_id')
  - Add helper methods: isAttendanceOnly(), isDoorAccess(), shouldCreateAttendance(), hasDoorApiControl()

  **Must NOT do**:
  - Don't remove existing fields/relationships

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Tasks 1-3 migration)
  - **Parallel Group**: Wave 2 (with Tasks 7, 9-10)
  - **Blocks**: Tasks 12, 13, 15, 18
  - **Blocked By**: Task 3

  **References**:
  - app/Models/BiometricDevice.php - Current state (read first)
  - app/Enums/BiometricDevicePurpose.php - New enum (Task 1)
  - app/Enums/BiometricDoorControlType.php - New enum (Task 2)

  **Acceptance Criteria**:
  - [ ] All new fields added to $fillable
  - [ ] All casts configured correctly
  - [ ] doorEvents() relationship works
  - [ ] Helper methods return correct values

  **Commit**: YES (group 2)
  - Message: `feat(biometrics): add door fields and helpers to BiometricDevice`
  - Files: `app/Models/BiometricDevice.php`

---

- [ ] 9. Update Attendances model

  **What to do**:
  - Update app/Models/Attendances.php
  - Add to $fillable: door_status, door_denial_reason, door_unlocked_at
  - Add to casts: door_unlocked_at => datetime
  - Add doorEvent() relationship: hasOne(DoorEvent::class, 'attendance_id')
  - Add wasDoorUnlocked() helper method

  **Must NOT do**:
  - Don't modify existing attendance logic

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 4 migration)
  - **Parallel Group**: Wave 2 (with Tasks 7-8, 10)
  - **Blocks**: Task 13
  - **Blocked By**: Task 4

  **References**:
  - app/Models/Attendances.php - Current state (read first)

  **Acceptance Criteria**:
  - [ ] Door columns added to $fillable
  - [ ] doorEvent() relationship works
  - [ ] wasDoorUnlocked() returns correct boolean

  **Commit**: YES (group 2)
  - Message: `feat(biometrics): add door fields to Attendances`
  - Files: `app/Models/Attendances.php`

---

- [ ] 10. Update CheckInResult DTO

  **What to do**:
  - Update app/Biometrics/DTOs/CheckInResult.php
  - Add doorResult property: ?DoorControlResult
  - Update constructor to accept doorResult
  - Add granted() static method to accept DoorControlResult
  - Add grantedNoDoor() static method for attendance_only devices

  **Must NOT do**:
  - Don't remove existing methods

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 6 DoorControlResult)
  - **Parallel Group**: Wave 2 (with Tasks 7-9)
  - **Blocks**: Task 13
  - **Blocked By**: Task 6

  **References**:
  - app/Biometrics/DTOs/CheckInResult.php - Current state (read first)
  - app/Biometrics/DTOs/DoorControlResult.php - New DTO (Task 6)

  **Acceptance Criteria**:
  - [ ] doorResult property added
  - [ ] granted() accepts DoorControlResult
  - [ ] grantedNoDoor() method exists

  **Commit**: YES (group 2)
  - Message: `feat(biometrics): add doorResult to CheckInResult`
  - Files: `app/Biometrics/DTOs/CheckInResult.php`

---

- [ ] 11. Create DoorControlService

  **What to do**:
  - Create app/Biometrics/Services/DoorControlService.php
  - Implement unlock(BiometricDevice $device): DoorControlResult
    - If device is not door access → return notApplicable()
    - If relay/wiegand → return skipped()
    - If api → call callDoorApi()
  - Implement callDoorApi(BiometricDevice $device): DoorControlResult
    - Use Http::timeout(3)
    - Include Authorization Bearer token
    - Handle exceptions, fire DoorControllerUnreachable event
  - Implement logEvent(...): DoorEvent

  **Must NOT do**:
  - Don't add business logic that belongs in CheckInService

  **Recommended Agent Profile**:
  - **Category**: `deep`
  - **Reason**: Core service with HTTP integration and error handling
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Tasks 7-8)
  - **Parallel Group**: Wave 3 (with Task 12)
  - **Blocks**: Tasks 13, 17
  - **Blocked By**: Tasks 7, 8

  **References**:
  - app/Biometrics/Services/CheckInService.php - Existing service pattern
  - app/Biometrics/Services/AntiSpoofGuard.php - Existing service pattern

  **Acceptance Criteria**:
  - [ ] unlock() returns correct DoorControlResult for each device type
  - [ ] callDoorApi() makes HTTP request with proper timeout
  - [ ] logEvent() creates DoorEvent record

  **Commit**: YES (group 3)
  - Message: `feat(biometrics): add DoorControlService`
  - Files: `app/Biometrics/Services/DoorControlService.php`

---

- [ ] 12. Rewrite CheckInService.processCheckIn()

  **What to do**:
  - Update app/Biometrics/Services/CheckInService.php
  - Add DoorControlService injection
  - Rewrite processCheckIn() with purpose-driven branching:
    - Step 1-6: Keep existing (provider, liveness, template, verification, membership, fraud)
    - Step 7: Branch on device purpose
      - If attendance_only: create attendance with door_status=not_applicable
      - If door_access: 
        - Check existing attendance today
        - Attempt door unlock via DoorControlService
        - If door failed: return denied with door_failed reason
        - If door succeeded: create attendance (or update existing) with door status
      - If door_access_no_attendance: verify + unlock door, no attendance
  - Update createAttendance() helper to include door fields
  - Update denyAndLog() to include door denial logic

  **Must NOT do**:
  - Don't break existing check-in logic for non-door devices
  - Don't create attendance if door unlock fails

  **Recommended Agent Profile**:
  - **Category**: `deep`
  - **Reason**: Complex branching logic with multiple scenarios
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Tasks 8-11, 11)
  - **Parallel Group**: Wave 3 (with Task 11)
  - **Blocks**: Task 14
  - **Blocked By**: Tasks 8, 9, 10, 11

  **References**:
  - app/Biometrics/Services/CheckInService.php - Current state (read first)
  - User specification in this plan - Full processCheckIn() logic

  **Acceptance Criteria**:
  - [ ] attendance_only creates attendance with not_applicable door status
  - [ ] door_access checks existing attendance before creating new
  - [ ] door_access creates attendance only after door unlock succeeds
  - [ ] door_access_no_attendance unlocks door but creates no attendance
  - [ ] DoorControlService.logEvent() called for all door interactions

  **Commit**: YES (group 3)
  - Message: `feat(biometrics): rewrite CheckInService with door control`
  - Files: `app/Biometrics/Services/CheckInService.php`

---

- [ ] 13. Update BiometricWebhookController response

  **What to do**:
  - Update app/Http/Controllers/Api/BiometricWebhookController.php
  - Update formatResponse() to include door action in JSON:
    - If denied: door.action = 'keep_locked'
    - If granted and door_access with skipped: door.action = 'unlock'
    - If granted and door_access with unlocked: door.action = 'confirm_unlock'
    - If granted and not door access: door.action = 'none'
  - Include door.duration_seconds, door.zone in response
  - Update getDenialMessage() for door_controller_unreachable

  **Must NOT do**:
  - Don't change non-door related response format

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Reason**: Controller update with response format changes
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 12)
  - **Parallel Group**: Wave 4 (with Tasks 14-16)
  - **Blocks**: None
  - **Blocked By**: Task 12

  **References**:
  - app/Http/Controllers/Api/BiometricWebhookController.php - Current controller
  - User specification - Response format details

  **Acceptance Criteria**:
  - [ ] Response includes correct door.action based on device purpose
  - [ ] door.duration_seconds included when applicable
  - [ ] door.zone included when applicable

  **Commit**: YES (group 4)
  - Message: `feat(biometrics): add door response to webhook`
  - Files: `app/Http/Controllers/Api/BiometricWebhookController.php`

---

- [ ] 14. Update BiometricDeviceResource form

  **What to do**:
  - Update app/Filament/Admin/Resources/BiometricDeviceResource.php
  - Add Device Purpose section with ToggleButtons::make('purpose')
  - Add Door Control Configuration section (only visible when purpose != attendance_only)
  - Add fields: door_control_type, door_controller_url, door_controller_token, unlock_duration_seconds, door_zone
  - Add connection_mode, poll_interval_seconds, last_polled_at fields
  - Use ->live() and ->visible() for conditional display
  - Add helperText explaining each door control type

  **Must NOT do**:
  - Don't break existing form fields

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Reason**: Filament form with conditional visibility
  - **Skills**: ["fluxui-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Tasks 8, 12)
  - **Parallel Group**: Wave 4 (with Tasks 13, 15-16)
  - **Blocks**: Task F3
  - **Blocked By**: Tasks 8, 12

  **References**:
  - app/Filament/Admin/Resources/BiometricDeviceResource.php - Current form (read first)
  - User specification - Full form schema

  **Acceptance Criteria**:
  - [ ] Purpose selector visible and functional
  - [ ] Door control section only shows for door_access purposes
  - [ ] connection_mode toggle works (webhook vs poll)

  **Commit**: YES (group 4)
  - Message: `feat(biometrics): add door config to device form`
  - Files: `app/Filament/Admin/Resources/BiometricDeviceResource.php`

---

- [ ] 15. Create DoorEventResource (optional - for audit viewing)

  **What to do**:
  - Create app/Filament/Admin/Resources/DoorEventResource.php
  - Table columns: event_type, door_status, gym_member, device, created_at
  - Filters: by gym, by device, by date, by event_type
  - Read-only (no create/edit actions needed)

  **Must NOT do**:
  - Don't create if not needed (audit can be viewed via table)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Reason**: Simple read-only Filament resource
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 7 DoorEvent model)
  - **Parallel Group**: Wave 4 (with Tasks 13-14, 16)
  - **Blocks**: None
  - **Blocked By**: Task 7

  **References**:
  - app/Filament/Admin/Resources/BiometricDeviceResource.php - Reference for structure

  **Acceptance Criteria**:
  - [ ] DoorEventResource created
  - [ ] Shows door events in table format

  **Commit**: YES (group 4)
  - Message: `feat(biometrics): add DoorEventResource for audit`
  - Files: `app/Filament/Admin/Resources/DoorEventResource.php`

---

- [ ] 16. Create DoorControllerUnreachable event

  **What to do**:
  - Create app/Events/DoorControllerUnreachable.php
  - Properties: device (BiometricDevice), errorMessage (string)
  - Implements ShouldBroadcast (for real-time alerts)

  **Must NOT do**:
  - Don't add complex event logic

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Reason**: Simple event class
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 11 DoorControlService)
  - **Parallel Group**: Wave 5 (with Task 17)
  - **Blocks**: None
  - **Blocked By**: Task 11

  **References**:
  - app/Events/MemberCheckedIn.php - Existing event pattern

  **Acceptance Criteria**:
  - [ ] Event created and can be dispatched

  **Commit**: YES (group 5)
  - Message: `feat(biometrics): add DoorControllerUnreachable event`
  - Files: `app/Events/DoorControllerUnreachable.php`

---

- [ ] 17. Create PollBiometricDevices command

  **What to do**:
  - Create app/Console/Commands/PollBiometricDevices.php
  - Signature: biometric:poll
  - Query devices where connection_mode = 'poll' and status = 'active'
  - For each device: resolve provider, fetch recent events, process via CheckInService
  - Update last_polled_at timestamp
  - Log errors, dispatch DoorControllerUnreachable on failure

  **Must NOT do**:
  - Don't create complex polling logic

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Reason**: Command with external API integration
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 3 migration)
  - **Parallel Group**: Wave 5 (with Task 16)
  - **Blocks**: None
  - **Blocked By**: Task 3

  **References**:
  - User specification - Command signature and logic

  **Acceptance Criteria**:
  - [ ] Command registered and runs without error
  - [ ] Processes poll-mode devices

  **Commit**: YES (group 5)
  - Message: `feat(biometrics): add PollBiometricDevices command`
  - Files: `app/Console/Commands/PollBiometricDevices.php`

---

- [ ] 18. Update BiometricServiceProvider

  **What to do**:
  - Update app/Providers/BiometricServiceProvider.php
  - Register DoorControlService as singleton
  - Add Event::listen for DoorControllerUnreachable (optional: add listener)

  **Must NOT do**:
  - Don't break existing service registrations

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Reason**: Simple service provider update
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on Task 11 DoorControlService)
  - **Parallel Group**: Wave 5 (with Tasks 16-17)
  - **Blocks**: None
  - **Blocked By**: Task 11

  **References**:
  - app/Providers/BiometricServiceProvider.php - Current state (read first)

  **Acceptance Criteria**:
  - [ ] DoorControlService registered as singleton
  - [ ] Application runs without errors

  **Commit**: YES (group 5)
  - Message: `feat(biometrics): register DoorControlService`
  - Files: `app/Providers/BiometricServiceProvider.php`

---

## Final Verification Wave

- [ ] F1. Run biometric tests

  **What to do**:
  - Run `php artisan test --filter=biometric` or full test suite
  - Verify all existing tests still pass
  - Add new tests for door functionality if needed

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`

- [ ] F2. Verify webhook response format

  **What to do**:
  - Check BiometricWebhookController::formatResponse() output
  - Verify door fields present in JSON response

- [ ] F3. Verify form field visibility

  **What to do**:
  - Check BiometricDeviceResource form
  - Verify door fields hidden when purpose=attendance_only

---

## Commit Strategy

- **Group 1**: Enums + Migrations
  - `feat(biometrics): add device purpose and door control enums`
  - `feat(biometrics): add door columns to biometric_devices`
  - `feat(biometrics): add door columns to attendances`
  - `feat(biometrics): create door_events table`
  - `feat(biometrics): add DoorControlResult DTO`

- **Group 2**: Models + DTOs
  - `feat(biometrics): create DoorEvent model`
  - `feat(biometrics): add door fields to BiometricDevice`
  - `feat(biometrics): add door fields to Attendances`
  - `feat(biometrics): add doorResult to CheckInResult`

- **Group 3**: Core Services
  - `feat(biometrics): add DoorControlService`
  - `feat(biometrics): rewrite CheckInService with door control`

- **Group 4**: API + UI
  - `feat(biometrics): add door response to webhook`
  - `feat(biometrics): add door config to device form`
  - `feat(biometrics): add DoorEventResource`

- **Group 5**: Commands + Events
  - `feat(biometrics): add DoorControllerUnreachable event`
  - `feat(biometrics): add PollBiometricDevices command`
  - `feat(biometrics): register DoorControlService`

---

## Success Criteria

### Verification Commands
```bash
php artisan test --filter=Biometric  # Run biometric tests
php artisan migrate:status            # Verify migrations
php artisan model:show DoorEvent     # Verify DoorEvent model
```

### Final Checklist
- [ ] All 18 tasks completed
- [ ] All migrations run successfully
- [ ] All tests pass
- [ ] Door control logic works for all 3 device purposes
- [ ] Duplicate attendance prevention works
- [ ] Door event audit logging works
