# Multi-Tenant Biometric Authentication System

## TL;DR

> **Quick Summary**: Implement a comprehensive multi-tenant biometric authentication system supporting both hardware devices (fingerprint/face/iris scanners) and third-party cloud APIs (Rekognition/Azure Face/BioID) for gym check-ins.
> 
> **Deliverables**:
> - 5 Database migrations (devices, enrollments, logs, vendors, settings)
> - 6 Eloquent models with proper tenant isolation
> - BiometricService with vendor adapter pattern
> - 12 Filament pages for full device/API management
> - Error handling with graceful fallbacks
> - Activity logging and audit trails
> 
> **Estimated Effort**: Large
> **Parallel Execution**: YES - 4 waves
> **Critical Path**: Models → Services → Core Pages → Integration

---

## Context

### Original Request
Design and define all necessary pages, settings, and configurations for multi-tenant biometric authentication - covering both native device support and third-party API integration.

### Interview Summary
**Key Discussions**:
- Multi-tenant: Each gym (tenant) independently configures their own biometric device
- Device Types: fingerprint, face ID, iris scan, turnstile/gate systems
- Hardware Brands: Multiple vendors (ZKTeco, DigitalPersona, Futronic, etc.)
- Third-Party APIs: Amazon Rekognition, Azure Face, BioID, Luxand
- Isolation: One tenant's config must not affect another
- Scalability: Must support adding new vendors easily

### Research Findings
**From Codebase**:
- Use `HasTenantAccess` trait for gym_id scoping
- Follow `GymSetting` pattern for key-value config storage
- Follow `WhatsAppAccount` pattern for dedicated credential models
- Use `TwilioAccountManager` pattern for vendor adapter services
- Use `WhatsAppSetup.php` wizard pattern for multi-step device setup

**From External Research**:
- 1:1 (WebAuthn) vs 1:N (identification) authentication models
- Multi-tenant isolation via tenant_id on all biometric tables
- Liveness detection (PAD) required for anti-spoofing
- Graceful fallbacks: Biometric → QR Code → RFID/PIN
- Store only templates, never raw biometric images

---

## Work Objectives

### Core Objective
Build a complete biometric authentication system where gym owners can:
1. Connect and manage biometric hardware devices
2. Integrate third-party biometric APIs
3. Enroll users and manage biometric templates
4. Track authentication events and generate reports

### Concrete Deliverables
- Database: 5 migrations, 6 models, proper indexes
- Backend: BiometricService with vendor adapter pattern
- Frontend: 12 Filament pages (dashboard, devices, enrollments, logs, settings)
- Tests: Unit tests for services, feature tests for pages

### Definition of Done
- [ ] Gym owner can add a hardware device (fingerprint scanner)
- [ ] Gym owner can connect a cloud API (Rekognition)
- [ ] Users can enroll biometric templates
- [ ] Biometric authentication creates attendance record
- [ ] Failed attempts are logged with error details
- [ ] All data is isolated per gym (tenant)

### Must Have
- Tenant isolation on ALL biometric data
- Encrypted storage of API credentials
- Real-time device status monitoring
- Graceful fallbacks on failure

### Must NOT Have
- Cross-tenant biometric matching
- Raw biometric image storage
- Hardcoded vendor credentials in code

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES
- **Automated tests**: YES (tests-after)
- **Framework**: PHPUnit
- **Test Strategy**: Unit tests for services, feature tests for critical flows

### QA Policy
Every task includes Agent-Executed QA Scenarios:
- **Hardware Device**: Test connection, enrollment, authentication
- **Cloud API**: Test credential validation, face match, liveness
- **Integration**: Verify attendance created on success

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation - Can Start Immediately):
├── T1: Database migrations (biometric tables)
├── T2: Enums (device types, statuses, event types)
├── T3: BiometricDevice model + factory
├── T4: BiometricEnrollment model + factory
├── T5: BiometricLog model + factory
├── T6: BiometricVendor model + seeder
└── T7: GymBiometricSetting model (key-value settings)

Wave 2 (Core Services - After Wave 1):
├── T8: BiometricService (main service + factory)
├── T9: Vendor adapters (HardwareAdapter, RekognitionAdapter, AzureAdapter, BioIDAdapter)
├── T10: BiometricAuthenticationController (API endpoints)
├── T11: Device connection test logic
├── T12: Template capture + storage logic
└── T13: Attendance integration service

Wave 3 (UI - After Wave 2):
├── T14: BiometricDashboard page + widgets
├── T15: DeviceListPage (CRUD)
├── T16: DeviceSetupPage (wizard)
├── T17: DeviceDetailPage
├── T18: EnrollmentListPage
└── T19: EnrollmentWizardPage

Wave 4 (UI + Integration - After Wave 3):
├── T20: ApiCredentialsPage
├── T21: AttendanceSettingsPage
├── T22: AccessControlPage
├── T23: ActivityLogsPage
├── T24: ReportsPage
├── T25: GlobalSettingsPage
└── T26: Panel provider registration

Wave FINAL (Verification):
├── F1: Integration testing
├── F2: End-to-end authentication flow
├── F3: Code quality review
└── F4: Scope fidelity check
```

### Dependency Matrix
- T1-T7: No dependencies (Wave 1)
- T8-T13: Depend on T1-T7 (Wave 2)
- T14-T19: Depend on T8-T13 (Wave 3)
- T20-T26: Depend on T14-T19 (Wave 4)
- F1-F4: Depend on all implementation tasks

---

## TODOs

- [ ] 1. Create database migrations for biometric tables

  **What to do**:
  - Create migration `create_biometric_devices_table` with: gym_id, name, device_type, vendor, connection_type, config_json (encrypted), location, status, last_sync_at, created_at, updated_at
  - Create migration `create_biometric_enrollments_table` with: gym_id, user_id, device_id, biometric_type, template_data (encrypted), quality_score, enrolled_at, last_used_at, status
  - Create migration `create_biometric_logs_table` with: gym_id, user_id, device_id, event_type, result, metadata_json, ip_address, created_at
  - Create migration `create_biometric_vendors_table` with: name, api_class, config_schema_json, is_active
  - Create migration `create_gym_biometric_settings_table` with: gym_id, key, value, type
  - Add foreign keys with proper constraints
  - Add indexes for common queries (gym_id, user_id, device_id, status)

  **Must NOT do**:
  - Store raw biometric images
  - Use text fields for encrypted data (use binary or cast)

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with T2-T7)
  - **Blocks**: T8-T13
  - **Blocked By**: None

  **References**:
  - `database/migrations/2025_06_29_070713_create_gym_user_table.php` - Reference for tenant_id pattern
  - `app/Models/GymSetting.php` - Key-value storage pattern
  - `database/migrations/2026_03_04_100645_create_user_login_logs_table.php` - Log table pattern

  **Acceptance Criteria**:
  - [ ] Migration runs without errors
  - [ ] All tables created with correct columns
  - [ ] Foreign keys properly set

  **QA Scenarios**:
  - Run `php artisan migrate` and verify all 5 tables created
  - Check table structures match migration definitions

  **Commit**: YES (with T2-T7)
  - Message: `feat(biometric): add database migrations for biometric system`
  - Files: `database/migrations/*biometric*.php`

---

- [ ] 2. Create enums for biometric types and statuses

  **What to do**:
  - Create `app/Enums/BiometricDeviceTypeEnum.php`: FINGERPRINT, FACE, IRIS, TURNSTILE
  - Create `app/Enums/BiometricConnectionTypeEnum.php`: USB, NETWORK, API
  - Create `app/Enums/BiometricDeviceStatusEnum.php`: ACTIVE, INACTIVE, ERROR, OFFLINE
  - Create `app/Enums/BiometricEventTypeEnum.php`: ENROLLMENT, AUTHENTICATION, VERIFICATION, DELETE
  - Create `app/Enums/BiometricResultEnum.php`: SUCCESS, FAILURE, ERROR, TIMEOUT
  - Create `app/Enums/BiometricVendorEnum.php`: ZKTEKO, DIGITALPERSONA, FUTRONIC, REKOGNITION, AZURE_FACE, BIOID, LUXAND, OTHER

  **Must NOT do**:
  - Use numeric values for enum keys

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with T1, T3-T7)
  - **Blocks**: T8-T13
  - **Blocked By**: None

  **References**:
  - `app/Enums/WhatsAppAccountStatusEnum.php` - Enum pattern
  - `app/Enums/WhatsAppAccountVerificationStatusEnum.php` - TitleCase keys

  **Acceptance Criteria**:
  - [ ] All 6 enums created with proper keys
  - [ ] Follow TitleCase convention

  **QA Scenarios**:
  - Check enum files exist and are properly formatted
  - Verify all enum cases are accessible

  **Commit**: YES (with T1, T3-T7)
  - Message: `feat(biometric): add enums for biometric types and statuses`
  - Files: `app/Enums/Biometric*.php`

---

- [ ] 3. Create BiometricDevice model with factory

  **What to do**:
  - Create `app/Models/BiometricDevice.php`
  - Add: tenant_id (gym_id), name, device_type (enum), vendor, connection_type, config_json (encrypted cast), location, status, last_sync_at
  - Add casts: config_json (array), last_sync_at (datetime)
  - Add relationships: gym(), enrollments(), logs()
  - Add scopes: active(), byGym()
  - Create factory: `database/factories/BiometricDeviceFactory.php`

  **Must NOT do**:
  - Store raw credentials in plain text

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with T1, T2, T4-T7)
  - **Blocks**: T8, T15-T17
  - **Blocked By**: None

  **References**:
  - `app/Models/WhatsAppAccount.php` - Model with encrypted config pattern
  - `app/Models/GymSetting.php` - Relationship pattern

  **Acceptance Criteria**:
  - [ ] Model created with all required fields
  - [ ] Factory creates valid device instances
  - [ ] Encryption cast works correctly

  **QA Scenarios**:
  - Create device via factory and verify fields
  - Test encryption/decryption of config

  **Commit**: YES (with T1, T2, T4-T7)
  - Message: `feat(biometric): add BiometricDevice model`
  - Files: `app/Models/BiometricDevice.php`, `database/factories/BiometricDeviceFactory.php`

---

- [ ] 4. Create BiometricEnrollment model with factory

  **What to do**:
  - Create `app/Models/BiometricEnrollment.php`
  - Add: gym_id, user_id, device_id, biometric_type, template_data (encrypted), quality_score, enrolled_at, last_used_at, status
  - Add casts: template_data (array), enrolled_at (datetime), last_used_at (datetime)
  - Add relationships: gym(), user(), device()
  - Add scopes: active(), byGym(), byUser()
  - Create factory: `database/factories/BiometricEnrollmentFactory.php`

  **Must NOT do**:
  - Store raw biometric images

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with T1-T3, T5-T7)
  - **Blocks**: T12, T18-T19
  - **Blocked By**: None

  **References**:
  - `app/Models/WhatsAppAccount.php` - Encrypted data pattern
  - `app/Models/GymMember.php` - User relationship pattern

  **Acceptance Criteria**:
  - [ ] Model with all required fields and relationships
  - [ ] Factory creates valid enrollments

  **QA Scenarios**:
  - Create enrollment and verify relationships
  - Test template encryption

  **Commit**: YES (with T1-T3, T5-T7)
  - Message: `feat(biometric): add BiometricEnrollment model`
  - Files: `app/Models/BiometricEnrollment.php`, `database/factories/BiometricEnrollmentFactory.php`

---

- [ ] 5. Create BiometricLog model

  **What to do**:
  - Create `app/Models/BiometricLog.php`
  - Add: gym_id, user_id, device_id, event_type, result, metadata_json, ip_address
  - Add casts: metadata_json (array)
  - Add relationships: gym(), user(), device()
  - Add scopes: byGym(), byUser(), byDevice(), byEventType(), byResult()

  **Must NOT do**:
  - Store raw biometric data in metadata

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with T1-T4, T6-T7)
  - **Blocks**: T10, T23
  - **Blocked By**: None

  **References**:
  - `app/Models/UserLoginLog.php` - Log model pattern

  **Acceptance Criteria**:
  - [ ] Model with all required fields
  - [ ] Scopes work correctly

  **QA Scenarios**:
  - Create log entry and query by scopes

  **Commit**: YES (with T1-T4, T6-T7)
  - Message: `feat(biometric): add BiometricLog model`
  - Files: `app/Models/BiometricLog.php`

---

- [ ] 6. Create BiometricVendor model and seeder

  **What to do**:
  - Create `app/Models/BiometricVendor.php`
  - Add: name, api_class, config_schema_json, is_active
  - Add casts: config_schema_json (array)
  - Create seeder: `database/seeders/BiometricVendorSeeder.php`
  - Seed with: ZKTeco, DigitalPersona, Futronic, Amazon Rekognition, Azure Face, BioID, Luxand

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with T1-T5, T7)
  - **Blocks**: T8-T9
  - **Blocked By**: None

  **References**:
  - `database/seeders/DatabaseSeeder.php` - Seeder pattern
  - `app/Models/WhatsAppAccount.php` - Config schema pattern

  **Acceptance Criteria**:
  - [ ] Model with vendor fields
  - [ ] Seeder creates all vendors

  **QA Scenarios**:
  - Run seeder and verify vendors created

  **Commit**: YES (with T1-T5)
  - Message: `feat(biometric): add BiometricVendor model and seeder`
  - Files: `app/Models/BiometricVendor.php`, `database/seeders/BiometricVendorSeeder.php`

---

- [ ] 7. Create GymBiometricSetting model

  **What to do**:
  - Create `app/Models/GymBiometricSetting.php`
  - Follow GymSetting pattern: gym_id, key, value, type
  - Add casts for type conversion (boolean, integer)
  - Add relationship: gym()

  **Must NOT do**:
  - Duplicate existing GymSetting logic

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with T1-T6)
  - **Blocks**: T21, T25
  - **Blocked By**: None

  **References**:
  - `app/Models/GymSetting.php` - Exact pattern to follow

  **Acceptance Criteria**:
  - [ ] Model follows GymSetting pattern
  - [ ] Type casting works correctly

  **QA Scenarios**:
  - Create setting and verify type casting

  **Commit**: YES (with T1-T6)
  - Message: `feat(biometric): add GymBiometricSetting model`
  - Files: `app/Models/GymBiometricSetting.php`

  **Commit**: YES (with T1-T6)
  - Message: `feat(biometric): add GymBiometricSetting model`
  - Files: `app/Models/GymBiometricSetting.php`

---

- [ ] 8. Create BiometricService with vendor adapter pattern

  **What to do**:
  - Create `app/Services/BiometricService.php`
  - Implement main service with methods: enroll(), authenticate(), verify(), testConnection()
  - Add vendor adapter factory pattern
  - Add tenant isolation (scope by gym_id)
  - Add encrypted credential handling
  - Implement fallback logic (biometric → QR → manual)

  **Must NOT do**:
  - Hardcode vendor-specific logic
  - Skip tenant isolation

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T1-T7)
  - **Parallel Group**: Wave 2
  - **Blocks**: T10-T13, T14-T26
  - **Blocked By**: T1-T7

  **References**:
  - `app/Services/TwilioAccountManager.php` - Service pattern with adapters
  - `app/Services/WhatsAppTemplateManager.php` - Vendor adapter pattern

  **Acceptance Criteria**:
  - [ ] Service created with all methods
  - [ ] Vendor factory works correctly

  **QA Scenarios**:
  - Test service instantiation
  - Test vendor adapter resolution

  **Commit**: YES (with T9)
  - Message: `feat(biometric): add BiometricService`
  - Files: `app/Services/BiometricService.php`

---

- [ ] 9. Create vendor adapters (Hardware, Rekognition, Azure, BioID)

  **What to do**:
  - Create `app/Services/Biometric/VendorAdapterInterface.php`
  - Create `app/Services/Biometric/HardwareAdapter.php` - For USB/Network devices
  - Create `app/Services/Biometric/RekognitionAdapter.php` - Amazon Rekognition
  - Create `app/Services/Biometric/AzureFaceAdapter.php` - Azure Face API
  - Create `app/Services/Biometric/BioIDAdapter.php` - BioID service
  - Each adapter: connect(), enroll(), authenticate(), testConnection()

  **Must NOT do**:
  - Store raw biometric data
  - Skip error handling

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T1-T7)
  - **Parallel Group**: Wave 2
  - **Blocks**: T10-T13, T14-T26
  - **Blocked By**: T1-T7

  **References**:
  - `app/Services/TwilioAccountManager.php` - Adapter pattern

  **Acceptance Criteria**:
  - [ ] Interface defines all required methods
  - [ ] All 4 adapters implement interface

  **QA Scenarios**:
  - Test each adapter can be instantiated
  - Verify interface compliance

  **Commit**: YES (with T8)
  - Message: `feat(biometric): add vendor adapters`
  - Files: `app/Services/Biometric/*.php`

---

- [ ] 10. Create BiometricAuthenticationController (API endpoints)

  **What to do**:
  - Create `app/Http/Controllers/BiometricAuthController.php`
  - Endpoints: enroll, authenticate, verify, test-device
  - Add proper request validation
  - Add tenant scope middleware
  - Return JSON responses

  **Must NOT do**:
  - Skip authentication on endpoints

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T8-T9)
  - **Parallel Group**: Wave 2
  - **Blocks**: T14-T26
  - **Blocked By**: T8-T9

  **References**:
  - `app/Http/Controllers/Api/` - API controller patterns

  **Acceptance Criteria**:
  - [ ] All endpoints defined and working
  - [ ] Proper validation

  **QA Scenarios**:
  - Test endpoint responses
  - Test validation rules

  **Commit**: YES (with T11-T13)
  - Message: `feat(biometric): add API controller`
  - Files: `app/Http/Controllers/BiometricAuthController.php`

---

- [ ] 11. Implement device connection test logic

  **What to do**:
  - Add testConnection() method to BiometricService
  - Implement ping test for hardware devices
  - Implement credential validation for cloud APIs
  - Return status and latency

  **Must NOT do**:
  - Leave connection tests unimplemented

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T8-T9)
  - **Parallel Group**: Wave 2
  - **Blocks**: T14-T26
  - **Blocked By**: T8-T9

  **References**:
  - `app/Services/TwilioAccountManager.php` - Connection test pattern

  **Acceptance Criteria**:
  - [ ] Test returns device status
  - [ ] Latency measured

  **QA Scenarios**:
  - Test connection to fake device
  - Verify response format

  **Commit**: YES (with T10, T12-T13)
  - Message: `feat(biometric): add device connection tests`
  - Files: `app/Services/BiometricService.php`

---

- [ ] 12. Implement template capture and storage

  **What to do**:
  - Add template capture from devices/APIs
  - Add encryption before storage
  - Add template validation (quality score)
  - Add template retrieval for matching

  **Must NOT do**:
  - Store raw biometric images

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T8-T9)
  - **Parallel Group**: Wave 2
  - **Blocks**: T14-T26
  - **Blocked By**: T8-T9

  **References**:
  - `app/Models/BiometricEnrollment.php` - Template storage

  **Acceptance Criteria**:
  - [ ] Templates encrypted before storage
  - [ ] Quality validation works

  **QA Scenarios**:
  - Test template encryption
  - Test quality score calculation

  **Commit**: YES (with T10-T11, T13)
  - Message: `feat(biometric): add template management`
  - Files: `app/Services/BiometricService.php`

---

- [ ] 13. Create Attendance integration service

  **What to do**:
  - Create `app/Services/BiometricAttendanceService.php`
  - On successful biometric: create attendance record
  - Handle auto check-out logic
  - Add fallback handling

  **Must NOT do**:
  - Skip attendance record creation

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T8-T9)
  - **Parallel Group**: Wave 2
  - **Blocks**: T14-T26
  - **Blocked By**: T8-T9

  **References**:
  - `app/Models/Attendance.php` - Attendance pattern

  **Acceptance Criteria**:
  - [ ] Creates attendance on success
  - [ ] Auto check-out works

  **QA Scenarios**:
  - Test attendance creation
  - Test auto check-out

  **Commit**: YES (with T10-T12)
  - Message: `feat(biometric): add attendance integration`
  - Files: `app/Services/BiometricAttendanceService.php`

---

- [ ] 14. Create BiometricDashboard page with widgets

  **What to do**:
  - Create `app/Filament/Admin/Pages/Biometric/BiometricDashboard.php`
  - Add device status cards
  - Add today's auth count widget
  - Add failed attempts alert widget
  - Add quick action buttons

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development", "tailwindcss-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T8-T13)
  - **Parallel Group**: Wave 3
  - **Blocks**: T26
  - **Blocked By**: T8-T13

  **References**:
  - `app/Filament/Admin/Pages/Dashboard.php` - Dashboard pattern
  - `app/Filament/Admin/Pages/WhatsAppSetup.php` - Page structure

  **Acceptance Criteria**:
  - [ ] Dashboard shows device status
  - [ ] Widgets display correct data

  **QA Scenarios**:
  - Test page loads
  - Test widget data

  **Commit**: YES (with T15-T19)
  - Message: `feat(biometric): add dashboard page`
  - Files: `app/Filament/Admin/Pages/Biometric/BiometricDashboard.php`

---

- [ ] 15. Create DeviceListPage (CRUD)

  **What to do**:
  - Create list page for BiometricDevice resource
  - Table with: name, type, vendor, status, location, last_sync
  - Actions: create, edit, delete, test
  - Filters: status, type, vendor

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T8-T13)
  - **Parallel Group**: Wave 3
  - **Blocks**: T26
  - **Blocked By**: T8-T13

  **References**:
  - Existing Filament resource pages

  **Acceptance Criteria**:
  - [ ] List shows all devices
  - [ ] CRUD actions work

  **QA Scenarios**:
  - Test CRUD operations
  - Test filters

  **Commit**: YES (with T14, T16-T19)
  - Message: `feat(biometric): add device list page`
  - Files: `app/Filament/Admin/Resources/BiometricDeviceResource.php`

---

- [ ] 16. Create DeviceSetupPage (wizard)

  **What to do**:
  - Create multi-step wizard (similar to WhatsAppSetup)
  - Step 1: Device type selection (Hardware/API/Turnstile)
  - Step 2: Configuration based on type
  - Step 3: Test connection
  - Step 4: Review & save
  - Use Cache for wizard state

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development", "volt-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T8-T13)
  - **Parallel Group**: Wave 3
  - **Blocks**: T26
  - **Blocked By**: T8-T13

  **References**:
  - `app/Filament/Admin/Pages/WhatsAppSetup.php` - Wizard pattern

  **Acceptance Criteria**:
  - [ ] All steps work
  - [ ] State persisted

  **QA Scenarios**:
  - Test wizard flow
  - Test state recovery

  **Commit**: YES (with T14-T15, T17-T19)
  - Message: `feat(biometric): add device setup wizard`
  - Files: `app/Filament/Admin/Pages/Biometric/DeviceSetup.php`

---

- [ ] 17. Create DeviceDetailPage

  **What to do**:
  - Show device information
  - Show connection status
  - Show performance metrics
  - Show recent activity
  - Actions: Test, Restart, Delete

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T8-T13)
  - **Parallel Group**: Wave 3
  - **Blocks**: T26
  - **Blocked By**: T8-T13

  **References**:
  - Similar detail pages in Filament

  **Acceptance Criteria**:
  - [ ] Shows all device details
  - [ ] Actions work

  **QA Scenarios**:
  - Test page displays
  - Test actions

  **Commit**: YES (with T14-T16, T18-T19)
  - Message: `feat(biometric): add device detail page`
  - Files: `app/Filament/Admin/Pages/Biometric/DeviceDetail.php`

---

- [ ] 18. Create EnrollmentListPage

  **What to do**:
  - List all biometric enrollments
  - Table: user, type, enrolled date, last used, status
  - Actions: re-enroll, delete, verify
  - Filters: status, type

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T8-T13)
  - **Parallel Group**: Wave 3
  - **Blocks**: T26
  - **Blocked By**: T8-T13

  **References**:
  - Similar list pages

  **Acceptance Criteria**:
  - [ ] List shows enrollments
  - [ ] Actions work

  **QA Scenarios**:
  - Test list display
  - Test bulk actions

  **Commit**: YES (with T14-T17, T19)
  - Message: `feat(biometric): add enrollment list page`
  - Files: `app/Filament/Admin/Resources/BiometricEnrollmentResource.php`

---

- [ ] 19. Create EnrollmentWizardPage

  **What to do**:
  - Step 1: Select user
  - Step 2: Select biometric type
  - Step 3: Capture sample (device/API)
  - Step 4: Verify quality
  - Step 5: Confirm & save

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development", "volt-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T8-T13)
  - **Parallel Group**: Wave 3
  - **Blocks**: T26
  - **Blocked By**: T8-T13

  **References**:
  - `app/Filament/Admin/Pages/WhatsAppSetup.php` - Wizard pattern

  **Acceptance Criteria**:
  - [ ] All steps work
  - [ ] Quality validation

  **QA Scenarios**:
  - Test enrollment flow
  - Test quality check

  **Commit**: YES (with T14-T18)
  - Message: `feat(biometric): add enrollment wizard`
  - Files: `app/Filament/Admin/Pages/Biometric/EnrollmentWizard.php`

---

- [ ] 20. Create ApiCredentialsPage

  **What to do**:
  - List connected API providers
  - Show masked credentials with reveal toggle
  - Test connection button
  - Disconnect button

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T14-T19)
  - **Parallel Group**: Wave 4
  - **Blocks**: T26
  - **Blocked By**: T14-T19

  **References**:
  - `app/Filament/Admin/Pages/GymSettings.php` - Settings pattern

  **Acceptance Criteria**:
  - [ ] Credentials displayed securely
  - [ ] Test works

  **QA Scenarios**:
  - Test credential masking
  - Test connection test

  **Commit**: YES (with T21-T25)
  - Message: `feat(biometric): add API credentials page`
  - Files: `app/Filament/Admin/Pages/Biometric/ApiCredentials.php`

---

- [ ] 21. Create AttendanceSettingsPage

  **What to do**:
  - Check-in method selection
  - Auto check-out settings
  - Fallback method configuration
  - Grace period settings

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T14-T19)
  - **Parallel Group**: Wave 4
  - **Blocks**: T26
  - **Blocked By**: T14-T19

  **References**:
  - `app/Filament/Admin/Pages/GymSettings.php` - Settings form pattern

  **Acceptance Criteria**:
  - [ ] Settings save correctly
  - [ ] Fallback logic configured

  **QA Scenarios**:
  - Test settings save
  - Test fallback triggers

  **Commit**: YES (with T20, T22-T25)
  - Message: `feat(biometric): add attendance settings`
  - Files: `app/Filament/Admin/Pages/Biometric/AttendanceSettings.php`

---

- [ ] 22. Create AccessControlPage

  **What to do**:
  - Roles allowed to use biometric
  - Roles allowed to enroll
  - Roles allowed to manage devices
  - Time-based access
  - Location restrictions

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T14-T19)
  - **Parallel Group**: Wave 4
  - **Blocks**: T26
  - **Blocked By**: T14-T19

  **References**:
  - Similar permission pages

  **Acceptance Criteria**:
  - [ ] Role restrictions work
  - [ ] Settings persist

  **QA Scenarios**:
  - Test role restrictions
  - Test time-based access

  **Commit**: YES (with T20-T21, T23-T25)
  - Message: `feat(biometric): add access control page`
  - Files: `app/Filament/Admin/Pages/Biometric/AccessControl.php`

---

- [ ] 23. Create ActivityLogsPage

  **What to do**:
  - Table with all biometric events
  - Filters: date, user, device, event, result
  - Expandable details
  - Export functionality

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T14-T19)
  - **Parallel Group**: Wave 4
  - **Blocks**: T26
  - **Blocked By**: T14-T19

  **References**:
  - `app/Filament/SuperAdmin/Pages/LoginActivity.php` - Log page pattern

  **Acceptance Criteria**:
  - [ ] Logs display correctly
  - [ ] Filters work

  **QA Scenarios**:
  - Test log display
  - Test filters

  **Commit**: YES (with T20-T22, T24-T25)
  - Message: `feat(biometric): add activity logs page`
  - Files: `app/Filament/Admin/Pages/Biometric/ActivityLogs.php`

---

- [ ] 24. Create ReportsPage

  **What to do**:
  - Daily auth chart
  - Success/failure rate
  - Peak hours
  - Device reliability

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development", "tailwindcss-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T14-T19)
  - **Parallel Group**: Wave 4
  - **Blocks**: T26
  - **Blocked By**: T14-T19

  **References**:
  - Existing report pages

  **Acceptance Criteria**:
  - [ ] Charts display correctly
  - [ ] Data accurate

  **QA Scenarios**:
  - Test chart rendering
  - Test data accuracy

  **Commit**: YES (with T20-T23, T25)
  - Message: `feat(biometric): add reports page`
  - Files: `app/Filament/Admin/Pages/Biometric/Reports.php`

---

- [ ] 25. Create GlobalSettingsPage

  **What to do**:
  - Enable/disable biometric
  - Session timeout
  - Max failed attempts
  - Lockout duration
  - Notification settings

  **Recommended Agent Profile**:
  - **Category**: `visual-engineering`
  - **Skills**: ["fluxui-development"]

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T14-T19)
  - **Parallel Group**: Wave 4
  - **Blocks**: T26
  - **Blocked By**: T14-T19

  **References**:
  - `app/Filament/Admin/Pages/GymSettings.php` - Settings pattern

  **Acceptance Criteria**:
  - [ ] Settings save correctly
  - [ ] Global toggle works

  **QA Scenarios**:
  - Test settings persistence
  - Test global enable/disable

  **Commit**: YES (with T20-T24)
  - Message: `feat(biometric): add global settings page`
  - Files: `app/Filament/Admin/Pages/Biometric/GlobalSettings.php`

---

- [ ] 26. Register all pages in AdminPanelProvider

  **What to do**:
  - Add navigation group "Biometric"
  - Register all pages
  - Add sidebar items

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: NO (depends on T14-T25)
  - **Parallel Group**: Wave 4
  - **Blocks**: None
  - **Blocked By**: T14-T25

  **References**:
  - `app/Providers/Filament/AdminPanelProvider.php` - Registration pattern

  **Acceptance Criteria**:
  - [ ] All pages in navigation
  - [ ] Accessible

  **QA Scenarios**:
  - Test navigation
  - Test page access

  **Commit**: YES (final)
  - Message: `feat(biometric): register pages in admin panel`
  - Files: `app/Providers/Filament/AdminPanelProvider.php`

---

## Final Verification Wave

- [ ] F1. Integration testing - Test complete biometric flow
- [ ] F2. End-to-end authentication - Verify full auth flow
- [ ] F3. Code quality review - Run pint, phpstan
- [ ] F4. Scope fidelity check - Verify all requirements met

## Final Verification Wave

---

## Commit Strategy

---

## Success Criteria

### Verification Commands
```bash
php artisan migrate  # All biometric tables created
php artisan db:seed --class=BiometricVendorSeeder  # Seed vendors
php artisan test --filter=Biometric  # Run biometric tests
```

### Final Checklist
- [ ] All "Must Have" present
- [ ] All "Must NOT Have" absent
- [ ] All tests pass
- [ ] Tenant isolation verified
