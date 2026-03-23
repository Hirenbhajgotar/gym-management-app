# Multi-Tenant Biometric Authentication System for FluxFitness

## TL;DR

> **Quick Summary**: Implement biometric check-in/check-out for gym members using face/fingerprint/iris recognition, integrated with existing attendance system. Multi-tenant support via Filament, real-time attendance dashboard via Laravel Reverb.

> **Deliverables**: 
> - 4 new database tables (biometric_devices, biometric_templates, biometric_consents, + columns to attendances)
> - 3 new models (BiometricDevice, BiometricTemplate, BiometricConsent)
> - Provider interface + 5 implementations (Rekognition, Azure, BioID, Local, Custom)
> - CheckInService orchestrator + 5 guard services
> - Device-facing REST API with token auth
> - Filament admin resource for device management
> - Live attendance dashboard with WebSocket updates
> - GDPR consent and data deletion services

> **Estimated Effort**: XL (60+ tasks)
> **Parallel Execution**: YES - 4 waves
> **Critical Path**: Migrations → Models → Provider Interface → CheckInService → API → Filament → Dashboard → Tests

---

## Context

### Original Request
Implement Multi-Tenant Biometric Authentication System for FluxFitness - a Laravel 12 + Filament 3 gym management platform. System should support biometric check-in via face/fingerprint/iris recognition, write to existing attendances table, and provide real-time attendance monitoring.

### Interview Summary
**Key Discussions**:
- [CONFIRMED] GymMember model has `canAccessGym()` method checking membership_end, payment_status, isFrozen
- [CONFIRMED] GymMember has `isFrozen()` with auto-unfreeze logic
- [CONFIRMED] GymMember has `isCurrentlyCheckedIn()` using currentAttendance() with status = 'checked_in'
- [CONFIRMED] GymMember has `avatar()` returning profile_photo or ui-avatars.com fallback
- [CONFIRMED] GymMember has `initials()` helper method
- [CONFIRMED] Attendances model has `scopeActive()` = check_in NOT NULL + check_out NULL
- [CONFIRMED] Attendances has static `isCurrentlyCheckedIn($memberId, $gymId, $attendanceableType)`
- [CONFIRMED] AttendanceStatus enum: Present, Completed, Absent (string values)
- [CONFIRMED] AttendanceObserver fires on save - already handles biometric writes automatically

**Research Findings**:
- [FOUND] GymMember uses PaymentStatus enum with COMPLETED value (not the old "paid" string)
- [FOUND] Attendances uses `status` column with AttendanceStatus enum (string values)
- [FOUND] currentAttendance() uses status = 'checked_in' but spec uses AttendanceStatus::Present - need to verify actual behavior
- [FOUND] BaseAttendanceResource has checkIn/checkOut actions that set status to AttendanceStatus::Present/Completed
- [FOUND] routes/api.php exists with webhook routes, can add biometric API routes
- [FOUND] routes/channels.php has broadcast channels, need to add gym.{gymId}.attendance channel

### Metis Review
**Identified Gaps** (addressed):
- [RESOLVED] Status value mismatch: GymMember.currentAttendance() checks status='checked_in' but AttendanceStatus enum has 'Present' - implementation should use AttendanceStatus::Present
- [RESOLVED] Need BiometricTypeEnum and BiometricDeviceStatusEnum for type safety
- [RESOLVED] Device token storage: use Hash::make() per spec, verifyToken() using Hash::check()
- [RESOLVED] Provider config encryption: use encrypted:array cast on BiometricDevice

---

## Work Objectives

### Core Objective
Add biometric authentication (face/fingerprint/iris) to FluxFitness gym management platform that writes check-in/out records to the existing attendances table, with multi-tenant Filament management and real-time WebSocket dashboard updates.

### Concrete Deliverables
- Database migrations for biometric tables and columns
- BiometricDevice, BiometricTemplate, BiometricConsent models
- BiometricProvider interface with 5 implementations
- CheckInService orchestrator with AntiSpoofGuard, MembershipGuard, FraudDetector
- Device-facing REST API at /api/v1/biometric/*
- Filament BiometricDeviceResource for admin management
- GymMemberAttendanceResource extended with biometric columns
- Livewire GymLiveAttendance dashboard component
- BiometricDataDeletionService for GDPR compliance

### Definition of Done
- [ ] Biometric check-in creates attendance with status=AttendanceStatus::Present
- [ ] Device API authenticates via device_token Bearer auth
- [ ] Filament resource shows devices scoped to current gym tenant
- [ ] Live dashboard shows present members with WebSocket updates
- [ ] Member can delete biometric data via GDPR request

### Must Have
- Backward compatibility: existing manual check-in continues working (check_in_method=null)
- Multi-tenant scoping: devices and templates scoped to gym_id
- Real-time updates: WebSocket broadcast on check-in/out
- Liveness detection: anti-spoofing via provider API
- Consent tracking: must have active consent before enrollment

### Must NOT Have (Guardrails)
- No raw biometric data persisted anywhere (only provider-side references)
- No separate check_in_events table - reuse attendances table
- No biometric access without active consent record
- No bypass of membership/payment/freeze checks

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES - PHPUnit via composer test
- **Automated tests**: Tests-after (add test tasks after implementation)
- **Framework**: PHPUnit (existing test suite)

### QA Policy
Every task includes agent-executed QA scenarios - verification via:
- API endpoints: curl commands validating JSON responses
- Model methods: tinker/REPL to verify relationships and scopes
- Filament resources: browser automation to verify UI rendering
- WebSocket: check broadcast events fire correctly

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Start Immediately — foundation):
├── Task 1: Create biometric columns migration (extend attendances)
├── Task 2: Create biometric_devices_table migration
├── Task 3: Create biometric_templates_table migration
├── Task 4: Create biometric_consents_table migration
├── Task 5: Update Attendances model with biometric fields
├── Task 6: Add biometric methods to GymMember model
├── Task 7: Create BiometricTypeEnum and BiometricDeviceStatusEnum
├── Task 8: Create BiometricDevice model
├── Task 9: Create BiometricTemplate model
└── Task 10: Create BiometricConsent model

Wave 2 (Core services — MAX PARALLEL):
├── Task 11: Create DTOs (BiometricSample, TemplateRef, VerificationResult, CheckInResult)
├── Task 12: Create BiometricProvider interface
├── Task 13: Create BiometricProviderRegistry + config
├── Task 14: Create BiometricServiceProvider
├── Task 15: Create RekognitionProvider (prove pattern)
├── Task 16: Create AntiSpoofGuard service
├── Task 17: Create MembershipGuard service
├── Task 18: Create CheckInService orchestrator
├── Task 19: Create FusionEngine service
└── Task 20: Create FraudDetector service

Wave 3 (API + Filament):
├── Task 21: Add biometric API routes
├── Task 22: Create biometric device auth middleware
├── Task 23: Create BiometricCheckInController
├── Task 24: Create BiometricEnrollController
├── Task 25: Create BiometricDeviceController
├── Task 26: Create BiometricOfflineSyncController
├── Task 27: Create BiometricDeviceResource (Filament)
├── Task 28: Extend GymMemberAttendanceResource with biometric columns
├── Task 29: Create MemberCheckedIn/MemberCheckedOut events
├── Task 30: Update routes/channels.php with attendance channel
└── Task 31: Create BiometricEnrollmentPage (Filament)

Wave 4 (Dashboard + Final services):
├── Task 32: Create GymLiveAttendance Livewire component
├── Task 33: Create AzureFaceProvider
├── Task 34: Create BioIDProvider
├── Task 35: Create LocalModelProvider
├── Task 36: Create CustomApiProvider
├── Task 37: Create OfflineSyncService
├── Task 38: Create BiometricDataDeletionService
├── Task 39: Create GuardResult DTO
└── Task 40: Run pint + verify all code

Wave 5 (Tests):
├── Task 41: Test CheckInService - granted path
├── Task 42: Test CheckInService - denied paths (liveness, frozen, expired, payment)
├── Task 43: Test CheckInService - suspicious duplicate
├── Task 44: Test MembershipGuard
├── Task 45: Test FraudDetector isDuplicate
├── Task 46: Test FraudDetector isSuspiciousVelocity
├── Task 47: Test OfflineSyncService
├── Task 48: Test BiometricDataDeletionService
├── Task 49: Test FusionEngine
└── Task 50: Test AntiSpoofGuard thresholds

Wave 6 (Final integration):
├── Task 51: Test full biometric check-in flow
├── Task 52: Test device API endpoints
├── Task 53: Test Filament device management
├── Task 54: Test live attendance dashboard
└── Task 55: Integration test - cross-component validation

Final Verification:
├── F1: Plan compliance audit (oracle)
├── F2: Code quality review (unspecified-high)
├── F3: Real manual QA (unspecified-high)
└── F4: Scope fidelity check (deep)
```

### Dependency Matrix
- Tasks 1-4 (Migrations): Run first, no dependencies
- Task 5 (Attendances update): After migrations
- Task 6 (GymMember update): After migrations
- Tasks 7-10 (Models): After migrations
- Tasks 11-14 (DTOs + Interface + Registry): Independent
- Task 15 (RekognitionProvider): After interface (12)
- Tasks 16-20 (Services): After DTOs (11) and Provider (12,15)
- Tasks 21-26 (API): After services (16-20)
- Tasks 27-31 (Filament): Independent, can parallel with API
- Task 32 (Dashboard): After events (29) + channels (30)
- Tasks 33-36 (More providers): Independent after interface
- Tasks 37-38 (Offline + GDPR): After CheckInService
- Tasks 39-40 (GuardResult + pint): After services
- Tasks 41-50 (Tests): After implementation complete
- Tasks 51-55 (Integration): After tests pass
- Final tasks F1-F4: After all implementation

---

## TODOs

> Implementation + Test = ONE Task. Every task MUST have: Recommended Agent Profile + QA Scenarios.

### Wave 1: Database & Models

- [ ] 1. Create migration add_biometric_columns_to_attendances_table

  **What to do**: Create migration extending attendances table with biometric columns:
  - device_id (FK to biometric_devices, nullable)
  - check_in_method (nullable string: biometric/manual)
  - biometric_type, provider_type (nullable strings)
  - confidence_score, liveness_score, fusion_score (nullable floats)
  - check_in_result, denial_reason (nullable strings)
  - synced_at, check_in_ip (nullable datetime/string)
  
  Must run BEFORE create_biometric_devices_table due to FK constraint.

  **References**: 
  - `app/Models/Attendances.php` - existing model structure to extend

---

- [ ] 2. Create biometric_devices_table migration

  **What to do**: Create migration with:
  - id, gym_id (FK)
  - device_name, device_type, provider_type
  - provider_config (text, nullable)
  - status (active/inactive/maintenance)
  - assigned_staff_id (FK nullable)
  - offline_capable, supports_multi_modal (booleans)
  - last_heartbeat_at, firmware_version (nullable)
  - device_token (nullable, stored as hash)
  - created_by (FK)
  - timestamps
  - Index on [gym_id, status]

  **References**: 
  - Migration naming: `database/migrations/*_create_biometric_devices_table.php`

---

- [ ] 3. Create biometric_templates_table migration

  **What to do**: Create migration with:
  - id, gym_member_id, gym_id (FKs)
  - enrolled_by_device_id (FK)
  - biometric_type, provider_type
  - template_ref (text, encrypted)
  - quality_score (float nullable)
  - is_active (boolean)
  - enrolled_at (timestamp)
  - timestamps
  - Index on [gym_member_id, gym_id, biometric_type, is_active]

  **References**: 
  - Migration pattern from biometric_devices

---

- [ ] 4. Create biometric_consents_table migration

  **What to do**: Create migration with:
  - id, gym_member_id, gym_id (FKs)
  - ip_address, version
  - consented_at (timestamp)
  - revoked_at (nullable timestamp)
  - timestamps
  - Unique constraint on [gym_member_id, gym_id]

  **References**: 
  - Migration pattern from above

---

- [ ] 5. Update Attendances model with biometric fields

  **What to do**: Modify `app/Models/Attendances.php`:
  - Add to $fillable: device_id, check_in_method, biometric_type, provider_type, confidence_score, liveness_score, fusion_score, check_in_result, denial_reason, synced_at, check_in_ip
  - Add to casts(): confidence_score=float, liveness_score=float, fusion_score=float, synced_at=datetime, biometric_type=BiometricTypeEnum::class
  - Add relationship: device() -> BelongsTo(BiometricDevice::class)
  - Add helper: isBiometric() -> check_in_method === 'biometric'
  - Add helper: isOfflineSync() -> synced_at !== null && synced_at > check_in_time
  - Add scope: scopeBiometric() -> where('check_in_method', 'biometric')

  **References**: 
  - `app/Models/Attendances.php:54-68` - existing $fillable and $casts

---

- [ ] 6. Add biometric methods to GymMember model

  **What to do**: Modify `app/Models/GymMember.php`:
  - Add relationship: biometricTemplates() -> HasMany(BiometricTemplate::class)
  - Add relationship: biometricConsents() -> HasMany(BiometricConsent::class)
  - Add method: activeBiometricConsent() -> first() where revoked_at is null
  - Add method: hasActiveBiometricConsent() -> boolean

  **References**: 
  - `app/Models/GymMember.php:187-210` - existing relationships pattern

---

- [ ] 7. Create BiometricTypeEnum

  **What to do**: Create `app/Enums/BiometricTypeEnum.php`:
  - Cases: Face, Fingerprint, Iris
  - Implement HasLabel, HasColor (for badges)
  - Value format: lowercase (face, fingerprint, iris)

  **References**: 
  - `app/Enums/AttendanceStatus.php` - pattern to follow

---

- [ ] 8. Create BiometricDeviceStatusEnum

  **What to do**: Create `app/Enums/BiometricDeviceStatusEnum.php`:
  - Cases: Active, Inactive, Maintenance
  - Implement HasLabel, HasColor

  **References**: 
  - Pattern from BiometricTypeEnum

---

- [ ] 9. Create BiometricDevice model

  **What to do**: Create `app/Models/BiometricDevice.php`:
  - $fillable: gym_id, device_name, device_type, provider_type, provider_config, status, assigned_staff_id, offline_capable, supports_multi_modal, last_heartbeat_at, firmware_version, device_token, created_by
  - casts: provider_config=encrypted:array, offline_capable=boolean, supports_multi_modal=boolean, last_heartbeat_at=datetime, status=BiometricDeviceStatusEnum::class
  - Relationships: gym(), assignedStaff(), createdBy(), templates()
  - Scopes: scopeActive(), scopeForGym()
  - Method: verifyToken(string $plainToken) -> bool using Hash::check()

  **References**: 
  - `app/Models/GymMember.php` - model structure pattern

---

- [ ] 10. Create BiometricTemplate model

  **What to do**: Create `app/Models/BiometricTemplate.php`:
  - casts: template_ref=encrypted, quality_score=float, is_active=boolean, enrolled_at=datetime, biometric_type=BiometricTypeEnum::class
  - Relationships: gymMember(), gym(), enrolledByDevice()
  - Scopes: scopeActive(), scopeForGymMember(), scopeOfType(), scopeForGym()

  **References**: 
  - Pattern from BiometricDevice

---

- [ ] 11. Create BiometricConsent model

  **What to do**: Create `app/Models/BiometricConsent.php`:
  - $fillable: gym_member_id, gym_id, ip_address, version, consented_at, revoked_at
  - casts: consented_at=datetime, revoked_at=datetime
  - Relationships: gymMember(), gym()
  - Method: isActive() -> revoked_at === null

  **References**: 
  - Pattern from BiometricTemplate

### Wave 2: Core Services

- [ ] 12. Create DTOs

  **What to do**: Create DTOs in `app/Biometrics/DTOs/`:
  - BiometricSample: type (string), data (base64 string), metadata (array)
  - TemplateRef: provider, externalId, meta (array)
  - VerificationResult: matched (bool), confidence (float), livenessScore (float), providerRef (string), raw (array)
  - CheckInResult: granted (bool), result (string), denialReason (?string), attendance (?Attendances)
    - Static: granted(Attendances $attendance), denied(string $reason)

  **References**: 
  - Use PHP 8 constructor property promotion
  - Explicit return types

---

- [ ] 13. Create BiometricProvider interface

  **What to do**: Create `app/Biometrics/Contracts/BiometricProvider.php`:
  ```php
  interface BiometricProvider
  {
      public function enroll(GymMember $gymMember, BiometricSample $sample): TemplateRef;
      public function verify(BiometricSample $sample, TemplateRef $ref): VerificationResult;
      public function getLivenessScore(BiometricSample $sample): float;
      public function deleteTemplate(TemplateRef $ref): bool;
      public function getSupportedTypes(): array;
      public function isAvailable(): bool;
  }
  ```

  **References**: 
  - `app/Contracts/` directory if exists, else create in `app/Biometrics/Contracts/`

---

- [ ] 14. Create BiometricProviderRegistry + config

  **What to do**: 
  1. Create `config/biometrics.php` with liveness_threshold, confidence_threshold, fallback_pin_enabled, max_enroll_attempts, offline_ttl_hours, fraud_window_seconds, capacity_warn_pct, consent_version, providers array
  2. Create `app/Biometrics/BiometricProviderRegistry.php` with resolve(string $providerType, array $config): BiometricProvider

  **References**: 
  - Existing config files for pattern

---

- [ ] 15. Create BiometricServiceProvider

  **What to do**: Create `app/Providers/BiometricServiceProvider.php`:
  - register(): Bind BiometricProviderRegistry, CheckInService, FusionEngine, AntiSpoofGuard, FraudDetector, MembershipGuard, OfflineSyncService, BiometricDataDeletionService as singletons
  - boot(): Empty (Filament resources registered in PanelProvider)

  **References**: 
  - `app/Providers/` for existing service providers

---

- [ ] 16. Create RekognitionProvider

  **What to do**: Create `app/Biometrics/Providers/RekognitionProvider.php` implementing BiometricProvider:
  - Constructor: accepts config with aws_key, aws_secret, aws_region
  - enroll(): IndexFaces into gym's collection (fluxfitness_gym_{gym_id}), set ExternalImageId=gym_member_{gym_member_id}
  - verify(): SearchFacesByImage against gym's collection
  - getLivenessScore(): DetectFaces with Pose, Quality, EyesOpen attributes
  - deleteTemplate(): DeleteFaces by FaceId
  - getSupportedTypes(): ['face']
  - isAvailable(): Check AWS credentials and region

  **References**: 
  - AWS SDK for PHP (aws/aws-sdk-php) should be available
  - Use Guzzle HTTP client for API calls

---

- [ ] 17. Create AntiSpoofGuard service

  **What to do**: Create `app/Biometrics/Services/AntiSpoofGuard.php`:
  - Method: check(BiometricSample $sample, BiometricProvider $provider): CheckInResult
  - Calls provider->getLivenessScore()
  - Compares against config('biometrics.liveness_threshold', 0.75)
  - Returns denied('liveness_failed') if below threshold
  - Returns allowed otherwise

  **References**: 
  - `app/Services/` pattern for service classes

---

- [ ] 18. Create MembershipGuard service

  **What to do**: Create `app/Biometrics/Services/MembershipGuard.php`:
  - Method: canCheckIn(GymMember $gymMember): GuardResult
  - Calls $gymMember->canAccessGym() (existing)
  - Checks $gymMember->hasActiveBiometricConsent()
  - Returns GuardResult::deny(reason) or GuardResult::allow()
  - Private: resolveReason(GymMember) -> maps to membership_frozen, membership_expired, payment_pending, no_biometric_consent

  **References**: 
  - `GymMember::canAccessGym()` at line 389-407 of GymMember.php
  - Need GuardResult DTO first (Task 39)

---

- [ ] 19. Create CheckInService orchestrator

  **What to do**: Create `app/Biometrics/Services/CheckInService.php`:
  - processCheckIn(BiometricSample $sample, BiometricDevice $device): CheckInResult
  - Steps:
    1. Resolve provider via BiometricProviderRegistry
    2. AntiSpoofGuard::check() -> deny if fails
    3. Load BiometricTemplate for gym_member at this gym with matching biometric_type and is_active
    4. Get gymMember from template
    5. Provider::verify() -> deny if fails
    6. FusionEngine::merge() if multi-modal
    7. MembershipGuard::canCheckIn() -> deny if fails
    8. FraudDetector::isDuplicate() -> flag suspicious if true
    9. FraudDetector::isSuspiciousVelocity() -> fire event if true
    10. Create Attendance: gym_id, check_in_time=now(), status=AttendanceStatus::Present, check_in_method='biometric', device_id, biometric_type, provider_type, confidence_score, liveness_score, check_in_result, check_in_ip
    11. Fire MemberCheckedIn event
    12. Return CheckInResult::granted($attendance)
  
  - processCheckOut(GymMember $gymMember, BiometricDevice $device): CheckInResult
    1. Find active attendance via Attendances::isCurrentlyCheckedIn()
    2. Update check_out_time=now(), status=AttendanceStatus::Completed
    3. Fire MemberCheckedOut event

  **References**: 
  - Uses AttendanceStatus::Present from app/Enums/AttendanceStatus.php
  - Uses Attendances::isCurrentlyCheckedIn() static method

---

- [ ] 20. Create FusionEngine service

  **What to do**: Create `app/Biometrics/Services/FusionEngine.php`:
  - Method: merge(array $verificationResults): VerificationResult
  - Weighted average: confidence, livenessScore based on provider config
  - Combine raw data arrays
  - Return single VerificationResult

  **References**: 
  - Pattern from CheckInService

---

- [ ] 21. Create FraudDetector service

  **What to do**: Create `app/Biometrics/Services/FraudDetector.php`:
  - isDuplicate(GymMember $gymMember): bool
    - Uses Attendances::isCurrentlyCheckedIn($gymMember->id, $gymMember->gym_id, GymMember::class)
  - isSuspiciousVelocity(GymMember $gymMember): bool
    - Checks if same email checked in at 2+ gyms within 10 minutes
    - Query attendances where email matches (via morph) and check_in_time >= now()->subMinutes(10) and check_in_result='granted', count distinct gym_id >= 2

  **References**: 
  - Uses existing Attendances::isCurrentlyCheckedIn()

### Wave 3: API + Filament

- [ ] 22. Add biometric API routes

  **What to do**: Add to `routes/api.php`:
  ```php
  Route::prefix('v1/biometric')->middleware(['auth:biometric-device'])->group(function () {
      Route::post('/check-in', [BiometricCheckInController::class, 'checkIn']);
      Route::post('/check-out', [BiometricCheckInController::class, 'checkOut']);
      Route::post('/enroll', [BiometricEnrollController::class, 'enroll']);
      Route::delete('/template/{id}', [BiometricEnrollController::class, 'deleteTemplate']);
      Route::post('/heartbeat', [BiometricDeviceController::class, 'heartbeat']);
      Route::get('/device/config', [BiometricDeviceController::class, 'config']);
  });
  Route::post('/v1/biometric/offline-sync', [BiometricOfflineSyncController::class, 'sync']);
  Route::post('/v1/biometric/webhook/{token}', [BiometricWebhookController::class, 'handle']);
  ```

  **References**: 
  - Existing routes/api.php structure

---

- [ ] 23. Create biometric device auth middleware

  **What to do**: Create `app/Http/Middleware/AuthenticateBiometricDevice.php`:
  - Reads Authorization header: Bearer {plain_token}
  - Queries BiometricDevice::where('gym_id', ...)->where('status', 'active')
  - Calls $device->verifyToken($plainToken)
  - Sets $request->attributes->set('device', $device)
  - Returns 401 if invalid

  **References**: 
  - Existing middleware in app/Http/Middleware/

---

- [ ] 24. Create BiometricCheckInController

  **What to do**: Create `app/Http/Controllers/Api/BiometricCheckInController.php`:
  - checkIn(BiometricCheckInRequest $request): returns CheckInResult JSON
    - Validates: biometric_type, data (base64), captured_at, metadata optional
    - Calls CheckInService::processCheckIn($sample, $device)
  - checkOut(BiometricCheckOutRequest $request): returns CheckInResult JSON

  **References**: 
  - Existing API controllers in app/Http/Controllers/Api/

---

- [ ] 25. Create BiometricEnrollController

  **What to do**: Create `app/Http/Controllers/Api/BiometricEnrollController.php`:
  - enroll(BiometricEnrollRequest $request): enrolls new biometric template
    - Validates: gym_member_id, biometric_type, data
    - Checks consent: $gymMember->hasActiveBiometricConsent()
    - Creates BiometricConsent if none
    - Calls provider->enroll()
    - Creates BiometricTemplate record
  - deleteTemplate(BiometricTemplate $template): deletes template
    - Calls provider->deleteTemplate()
    - Soft-deletes template record

  **References**: 
  - Pattern from BiometricCheckInController

---

- [ ] 26. Create BiometricDeviceController

  **What to do**: Create `app/Http/Controllers/Api/BiometricDeviceController.php`:
  - heartbeat(HeartbeatRequest $request): updates last_heartbeat_at
  - config(): returns device config (provider_type, provider_config masked)

  **References**: 
  - Pattern from above

---

- [ ] 27. Create BiometricOfflineSyncController

  **What to do**: Create `app/Http/Controllers/Api/BiometricOfflineSyncController.php`:
  - sync(OfflineSyncRequest $request): processes batch of offline events
    - Validates: events array with offline_id, biometric_type, data, captured_at
    - Calls OfflineSyncService::processBatch()

  **References**: 
  - Pattern from above

---

- [ ] 28. Create BiometricDeviceResource (Filament)

  **What to do**: Create `app/Filament/Resources/BiometricDeviceResource.php`:
  - Model: BiometricDevice::class
  - getEloquentQuery(): scope to current tenant (gym_id)
  - Form:
    - device_name (required)
    - device_type (Select: fingerprint|face|iris|card_bio|mobile)
    - provider_type (Select: rekognition|azure_face|bioid|on_premise|custom) ->live()
    - Conditional sections for provider config (AWS, Azure, On-Premise, Custom)
    - assigned_staff_id (relationship, nullable)
    - status (ToggleButtons)
    - offline_capable, supports_multi_modal (toggles)
  - Table:
    - device_name, device_type (badge), provider_type (badge), status (color badge)
    - assigned_staff name, last_heartbeat_at (diffForHumans)
  - Actions:
    - TestConnectionAction -> calls Provider::isAvailable()
    - RegenerateTokenAction -> generates new device_token, shows in modal once

  **References**: 
  - `app/Filament/BaseResources/BaseAttendanceResource.php` for form/table patterns

---

- [ ] 29. Extend GymMemberAttendanceResource with biometric columns

  **What to do**: Modify `GymMemberAttendanceResource` (or BaseAttendanceResource) table:
  - Add TextColumn::make('check_in_method') -> badge, color gray for null, success for biometric
  - Add TextColumn::make('device.device_name') -> placeholder '—', toggleable
  - Add TextColumn::make('confidence_score') -> format as percentage, toggleable
  - Add TextColumn::make('check_in_result') -> badge (granted=success, denied=danger, suspicious=warning)
  - Add SelectFilter::make('check_in_method') -> options ['biometric' => 'Biometric', null => 'Manual']

  **References**: 
  - `app/Filament/BaseResources/BaseAttendanceResource.php:118-149` - table columns

---

- [ ] 30. Create MemberCheckedIn/MemberCheckedOut events

  **What to do**: Create events:
  - `app/Events/MemberCheckedIn.php` implements ShouldBroadcast
    - broadcastOn(): PrivateChannel("gym.{$this->gymId}.attendance")
    - broadcastWith(): gym_member_id, member_name, member_avatar, member_initials, device_name, check_in_time, current_count
  - `app/Events/MemberCheckedOut.php` - same structure with check_out_time

  **References**: 
  - `app/Observers/AttendanceObserver.php:56` - broadcasts AttendanceStatsUpdated

---

- [ ] 31. Update routes/channels.php with attendance channel

  **What to do**: Add to routes/channels.php:
  ```php
  Broadcast::channel('gym.{gymId}.attendance', function ($user, $gymId) {
      return $user->hasRole('super_admin') || $user->gyms->contains($gymId);
  });
  ```

  **References**: 
  - Existing channels at routes/channels.php

---

- [ ] 32. Create BiometricEnrollmentPage (Filament)

  **What to do**: Create `app/Filament/Pages/MemberBiometricEnrollment.php`:
  - Multi-step wizard:
    - Step 1: Select GymMember (where gym_id = tenant, membershipActive, notFrozen)
    - Step 2: Consent check -> if no consent, show text, on confirm create BiometricConsent
    - Step 3: Select biometric type + device (filter devices by supported types)
    - Step 4: Capture prompt (instructions for staff)
    - Step 5: Quality confirmation (show quality_score, create template on confirm)

  **References**: 
  - Existing Filament pages in app/Filament/

### Wave 4: Dashboard + Providers

- [ ] 33. Create GymLiveAttendance Livewire component

  **What to do**: Create `app/Livewire/GymLiveAttendance.php`:
  - Public: presentMembers (array keyed by gym_member_id), currentCount (int), gymId (int)
  - mount(Gym $gym): seed from Attendances::with('attendanceable')->byGym($gymId)->active()->whereDate(check_in_time, today())
  - handleCheckIn(array $data): #[On('echo-private:gym.{gymId}.attendance,MemberCheckedIn')] add to presentMembers
  - handleCheckOut(array $data): #[On('echo-private:gym.{gymId}.attendance,MemberCheckedOut')] remove from presentMembers
  - checkCapacityAlert(): dispatch if currentCount/pct >= 0.80 or 1.0

  **References**: 
  - Existing Livewire components in app/Livewire/

---

- [ ] 34. Create AzureFaceProvider

  **What to do**: Create `app/Biometrics/Providers/AzureFaceProvider.php`:
  - Uses Azure Face API
  - PersonGroup per gym: fluxfitness-gym-{gym_id}
  - Person name: gym_member_{gym_member_id}
  - enroll(): create Person, add persistedFace
  - verify(): detect, verify against personId
  - getLivenessScore(): detect with headPose, blur, exposure, noise

  **References**: 
  - Pattern from RekognitionProvider

---

- [ ] 35. Create BioIDProvider

  **What to do**: Create `app/Biometrics/Providers/BioIDProvider.php`:
  - Primarily liveness detection
  - Uses BioID Web API
  - Simplified implementation for demo

  **References**: 
  - Pattern from RekognitionProvider

---

- [ ] 36. Create LocalModelProvider

  **What to do**: Create `app/Biometrics/Providers/LocalModelProvider.php`:
  - Self-hosted DeepFace/InsightFace
  - config: base_url, api_key
  - HTTP adapter calls to local model service

  **References**: 
  - Pattern from RekognitionProvider

---

- [ ] 37. Create CustomApiProvider

  **What to do**: Create `app/Biometrics/Providers/CustomApiProvider.php`:
  - Generic HTTP adapter
  - Reads endpoint_map from provider_config
  - Maps enroll/verify/delete/liveness to configurable URLs

  **References**: 
  - Pattern from RekognitionProvider

---

- [ ] 38. Create OfflineSyncService

  **What to do**: Create `app/Biometrics/Services/OfflineSyncService.php`:
  - processBatch(array $events, BiometricDevice $device): array of results
  - For each event:
    1. Re-run AntiSpoofGuard
    2. Load BiometricTemplate -> gymMember
    3. Re-run MembershipGuard at captured_at time
    4. Re-run FraudDetector for captured_at window
    5. Create attendance with check_in_time=captured_at, synced_at=now()
    6. Return per-event: accepted | retroactively_denied

  **References**: 
  - Pattern from CheckInService

---

- [ ] 39. Create BiometricDataDeletionService

  **What to do**: Create `app/Biometrics/Services/BiometricDataDeletionService.php`:
  - deleteForGymMember(GymMember $gymMember): void
    1. Load BiometricTemplate where gym_member_id
    2. Call provider->deleteTemplate() for each
    3. Soft-delete templates (or set is_active=false)
    4. Anonymize biometric attendance columns:
       - check_in_method = 'anonymized'
       - biometric_type, provider_type, confidence_score, liveness_score, fusion_score, check_in_result = null
       - Keep check_in_time + status for stats
    5. Delete BiometricConsent records
    6. Log via Spatie activity log: activity()->on($gymMember)->log('Biometric data deleted per GDPR request')

  **References**: 
  - Uses existing Spatie activity log on GymMember

---

- [ ] 40. Create GuardResult DTO

  **What to do**: Create `app/Biometrics/DTOs/GuardResult.php`:
  - Properties: allowed (bool), reason (?string)
  - Static: allow(), deny(string $reason)

  **References**: 
  - Pattern from CheckInResult DTO

### Wave 5: Tests

- [ ] 41. Test CheckInService - granted path

  **What to do**: Feature test:
  - Mock provider returns successful VerificationResult
  - Mock AntiSpoofGuard passes
  - Mock MembershipGuard allows
  - Mock FraudDetector returns false
  - Call processCheckIn
  - Assert: Attendance created with status=AttendanceStatus::Present, check_in_method='biometric'
  - Assert: CheckInResult::granted returned

  **References**: 
  - Existing feature tests in tests/Feature/

---

- [ ] 42. Test CheckInService - denied paths

  **What to do**: Feature tests:
  - liveness_failed: AntiSpoofGuard returns fail
  - membership_frozen: GymMember with isFrozen()=true
  - membership_expired: GymMember with membership_end in past
  - payment_pending: GymMember with payment_status != COMPLETED
  - For each: Assert CheckInResult::denied returned, no attendance created

  **References**: 
  - From Task 41

---

- [ ] 43. Test CheckInService - suspicious duplicate

  **What to do**: Feature test:
  - FraudDetector::isDuplicate returns true
  - Call processCheckIn
  - Assert: Attendance created with check_in_result='suspicious'
  - Assert: Event fired for alerting

  **References**: 
  - From Task 41

---

- [ ] 44. Test MembershipGuard

  **What to do**: Unit tests:
  - canAccessGym returns false -> deny membership_frozen
  - canAccessGym returns false -> deny membership_expired  
  - canAccessGym returns false -> deny payment_pending
  - hasActiveBiometricConsent returns false -> deny no_biometric_consent
  - All pass -> allow

  **References**: 
  - Uses GymMember::canAccessGym()

---

- [ ] 45. Test FraudDetector isDuplicate

  **What to do**: Unit test:
  - Create GymMember with active attendance (via Attendances::isCurrentlyCheckedIn)
  - Call isDuplicate
  - Assert: true returned

  **References**: 
  - Uses Attendances::isCurrentlyCheckedIn()

---

- [ ] 46. Test FraudDetector isSuspiciousVelocity

  **What to do**: Unit test:
  - Create 2 attendances for same email at different gyms within 10 minutes
  - Call isSuspiciousVelocity
  - Assert: true returned

  **References**: 
  - From Task 45

---

- [ ] 47. Test OfflineSyncService

  **What to do**: Feature test:
  - Send batch of offline events with captured_at times
  - Call processBatch
  - Assert: Each attendance created with synced_at = now(), check_in_time = captured_at
  - Assert: status = AttendanceStatus::Present

  **References**: 
  - From Task 41

---

- [ ] 48. Test BiometricDataDeletionService

  **What to do**: Feature test:
  - Create GymMember with biometric templates and consents
  - Call deleteForGymMember
  - Assert: Templates soft-deleted or is_active=false
  - Assert: Attendance columns anonymized (check_in_method='anonymized', biometric columns null)
  - Assert: Consents deleted
  - Assert: Activity log entry created

  **References**: 
  - From Task 41

---

- [ ] 49. Test FusionEngine

  **What to do**: Unit test:
  - Pass array of VerificationResults with different confidence/liveness
  - Call merge
  - Assert: Weighted average calculated correctly

  **References**: 
  - Uses VerificationResult DTO

---

- [ ] 50. Test AntiSpoofGuard thresholds

  **What to do**: Unit test:
  - livenessScore = 0.749 -> deny (below 0.75 threshold)
  - livenessScore = 0.750 -> allow (at threshold)
  - livenessScore = 0.90 -> allow

  **References**: 
  - From Task 17

### Wave 6: Integration Tests

- [ ] 51. Test full biometric check-in flow

  **What to do**: Integration test:
  - Setup: GymMember with active consent, biometric template enrolled
  - Action: POST to /api/v1/biometric/check-in
  - Assert: 200 response with granted
  - Assert: Attendance created in database

  **References**: 
  - From Task 41

---

- [ ] 52. Test device API endpoints

  **What to do**: Integration tests:
  - /check-in, /check-out, /enroll, /heartbeat, /device/config
  - With valid device token -> 200
  - Without token -> 401

  **References**: 
  - From Task 41

---

- [ ] 53. Test Filament device management

  **What to do**: Feature test (Livewire):
  - Visit BiometricDeviceResource
  - Assert: Device list scoped to current gym
  - Create new device
  - Assert: Device appears in list
  - Test Connection action works

  **References**: 
  - Filament testing patterns

---

- [ ] 54. Test live attendance dashboard

  **What to do**: Integration test:
  - Seed with active attendances
  - Visit dashboard page
  - Assert: presentMembers populated
  - Trigger check-in event
  - Assert: presentMembers updated, count incremented

  **References**: 
  - Livewire testing patterns

---

- [ ] 55. Integration test - cross-component validation

  **What to do**: End-to-end test:
  - Enroll biometric template
  - Check-in via API
  - Verify attendance appears in Filament
  - Verify live dashboard updates
  - Check-out
  - Verify all states correct

  **References**: 
  - From Task 41

---

## Final Verification Wave

- [ ] F1. Plan Compliance Audit — `oracle`
  Read plan end-to-end. For each Must Have: verify implementation exists. For each Must NOT Have: search codebase. Check evidence files exist.

- [ ] F2. Code Quality Review — `unspecified-high`
  Run `vendor/bin/pint --dirty`, check for `as any`, empty catches, console.log, commented code, unused imports.

- [ ] F3. Real Manual QA — `unspecified-high`
  Execute every QA scenario from every task. Test cross-task integration. Test edge cases.

- [ ] F4. Scope Fidelity Check — `deep`
  For each task: read "What to do", read actual diff. Verify 1:1 - no missing, no creep. Check Must NOT do compliance.

---

## Commit Strategy

- **1**: `feat(biometric): add migrations for biometric tables and columns`
  Files: `database/migrations/*_create_biometric_*.php`, `database/migrations/*_add_biometric_columns_to_attendances_table.php`

- **2**: `feat(biometric): add biometric models and enums`
  Files: `app/Models/BiometricDevice.php`, `BiometricTemplate.php`, `BiometricConsent.php`, `app/Enums/BiometricTypeEnum.php`, `BiometricDeviceStatusEnum.php`

- **3**: `feat(biometric): update Attendances and GymMember with biometric fields`
  Files: `app/Models/Attendances.php`, `app/Models/GymMember.php`

- **4**: `feat(biometric): add DTOs and provider interface`
  Files: `app/Biometrics/DTOs/*.php`, `app/Biometrics/Contracts/BiometricProvider.php`

- **5**: `feat(biometric): add provider implementations`
  Files: `app/Biometrics/Providers/RekognitionProvider.php`, `AzureFaceProvider.php`, `BioIDProvider.php`, `LocalModelProvider.php`, `CustomApiProvider.php`

- **6**: `feat(biometric): add core services`
  Files: `app/Biometrics/Services/*.php`, `config/biometrics.php`, `app/Providers/BiometricServiceProvider.php`

- **7**: `feat(biometric): add device API endpoints`
  Files: `app/Http/Controllers/Api/Biometric*.php`, `app/Http/Middleware/AuthenticateBiometricDevice.php`, `routes/api.php` (additions)

- **8**: `feat(biometric): add Filament resources`
  Files: `app/Filament/Resources/BiometricDeviceResource.php`, `app/Filament/Pages/MemberBiometricEnrollment.php`

- **9**: `feat(biometric): add live attendance dashboard`
  Files: `app/Events/MemberCheckedIn.php`, `MemberCheckedOut.php`, `app/Livewire/GymLiveAttendance.php`, `routes/channels.php`

- **10**: `test(biometric): add biometric tests`
  Files: `tests/Feature/Biometric*.php`, `tests/Unit/Biometric*.php`

---

## Success Criteria

### Verification Commands
```bash
# Run biometric tests
php artisan test --filter=Biometric

# Run pint
vendor/bin/pint --dirty

# Test API endpoint
curl -X POST http://localhost/api/v1/biometric/check-in \
  -H "Authorization: Bearer {device_token}" \
  -H "Content-Type: application/json" \
  -d '{"biometric_type": "face", "data": "...", "captured_at": "2026-03-23T10:00:00Z"}'
```

### Final Checklist
- [ ] All Must Have items implemented
- [ ] All Must NOT Have items absent
- [ ] All biometric tests pass
- [ ] Pint shows no issues
- [ ] Backward compatibility: manual check-in still works (check_in_method=null)

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
