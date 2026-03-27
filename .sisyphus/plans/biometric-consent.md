# Biometric Consent System — FluxFitness

## TL;DR

> **Quick Summary**: Implement complete biometric consent flow for gym members — automated email with signed URL, public consent page (no login), staff management via Filament, consent gates on enrollment/check-in.
> 
> **Deliverables**:
> - Routes for public consent page (signed URLs, no auth required)
> - Events, Job, Mail, Controller, Views for consent workflow
> - Staff actions in GymMemberResource (resend, copy link, revoke)
> - Consent gates in biometric enrollment and check-in
> 
> **Estimated Effort**: Medium-Large
> **Parallel Execution**: YES — 3 waves
> **Critical Path**: Routes → Events/Job → Controller/Views → Observer → Listeners → Resource → Tests

---

## Context

### Original Request
Implement biometric consent system per detailed 14-phase specification. Consent must be recorded BEFORE biometric enrollment is allowed. No login required — signed URLs used for security.

### Interview Summary
**Key Discussions**:
- Tests required: Use `.env.testing` with `gym_app_test` database, run via `docker-compose exec app php artisan test`
- Validation: Use Form Request classes
- Existing components verified and working

**Existing (Verified — NO WORK NEEDED)**:
- BiometricConsent model with all columns and relationships
- BiometricConsent migration (all required columns)
- config/biometrics.php with `consent_version: '1.0'`
- GymMember model: `biometricConsents()`, `activeBiometricConsent()`, `hasActiveBiometricConsent()`
- MembershipGuard: consent check in `canCheckIn()`
- BiometricEnrollController: consent gate in `enroll()` and `syncFromDevice()`
- BiometricDataDeletionService for GDPR deletion

**Research Findings**:
- Events registered via `FacadesEvent::listen()` in AppServiceProvider (existing pattern)
- Routes go in `routes/web.php`
- Observer already exists at `app/Observers/GymMemberObserver.php`

---

## Work Objectives

### Core Objective
Complete biometric consent system enabling:
1. Auto-send consent email when new member created (2 min delay)
2. Public consent page via signed URL (no login)
3. Staff can resend link, copy link, open tablet mode, or revoke consent
4. Block enrollment/check-in if no active consent

### Concrete Deliverables
- Public consent routes with signed URL validation
- Email with consent link (7 day expiry)
- Consent form with checkbox (reads policy, gives consent)
- Success/already-consented views
- GymMemberResource: status column + action buttons
- Consent gates in biometric enrollment/check-in

### Definition of Done
- [ ] New member created → consent email sent after 2 min
- [ ] Signed URL opens consent form
- [ ] Checkbox required before submit
- [ ] Consent recorded to database
- [ ] Staff can resend/copy/revoke from Filament
- [ ] Enrollment API rejects without consent (422)
- [ ] Check-in blocked without consent (GuardResult deny)

### Must Have
- Idempotency: don't create duplicate consents, don't resend if already consented
- Signed URL expiry: 7 days for email, 1 hour for tablet
- All consent actions create audit trail

### Must NOT Have (Guardrails)
- NO login/auth on consent page (security via signed URL only)
- NO automatic consent creation (must be explicit member action)
- NO biometric enrollment without active consent

---

## Verification Strategy (MANDATORY)

### Test Decision
- **Infrastructure exists**: YES (PHPUnit)
- **Automated tests**: YES (tests-after approach)
- **Framework**: PHPUnit (via `php artisan test`)
- **Database**: `gym_app_test` (via `.env.testing`)

### QA Policy
Every task MUST include agent-executed QA scenarios. Evidence saved to `.sisyphus/evidence/`.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (Foundation — all can start immediately):
├── Task 1: Routes in web.php (consent GET + POST)
├── Task 2: BiometricConsentGiven event
├── Task 3: BiometricConsentRevoked event
├── Task 4: BiometricConsentStoreRequest form
├── Task 5: BiometricConsentController
└── Task 6: Consent views (show, success, already-consented)

Wave 2 (Depends on Wave 1):
├── Task 7: SendBiometricConsentEmail job
├── Task 8: BiometricConsentMail + email view
├── Task 9: GymMemberObserver update (add job dispatch)
├── Task 10: NotifyStaffOnConsentGiven listener
├── Task 11: HandleConsentRevoked listener
└── Task 12: AppServiceProvider event registration

Wave 3 (Integration + Tests):
├── Task 13: GymMemberResource consent status column
├── Task 14: GymMemberResource consent actions
├── Task 15: BiometricConsentTest (feature tests)
└── Task 16: Verify full integration flow
```

### Dependency Matrix
- **Tasks 1-6**: No dependencies — start immediately
- **Task 7**: Depends on Task 2 (event exists)
- **Task 8**: Depends on Task 7 (job sends mail)
- **Task 9**: Depends on Task 7 (job exists)
- **Task 10**: Depends on Task 2 (event exists)
- **Task 11**: Depends on Task 3 (event exists)
- **Task 12**: Depends on Tasks 10, 11 (listeners exist)
- **Task 13**: No dependency (just adds column)
- **Task 14**: Depends on Task 7 (uses job)
- **Task 15**: Depends on Tasks 1-12 (tests integration)
- **Task 16**: Depends on Tasks 13-15

---

## TODOs

- [ ] 1. Add biometric consent routes to web.php

  **What to do**:
  - Add GET route: `/consent/biometric` → `BiometricConsentController@show` with `signed` middleware
  - Add POST route: `/consent/biometric` → `BiometricConsentController@store` with `signed` middleware
  - Use named routes: `biometric.consent.show`, `biometric.consent.store`

  **Must NOT do**:
  - Do NOT add auth middleware — these are public routes protected by signed URL

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - Reason: Simple route addition, standard Laravel patterns

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2-6)
  - **Blocks**: None
  - **Blocked By**: None

  **References**:
  - `routes/web.php` - existing route definitions to follow pattern
  - Laravel docs: signed routes

  **Acceptance Criteria**:
  - [ ] Route exists: `Route::get('/consent/biometric', ...)->name('biometric.consent.show')->middleware('signed')`
  - [ ] Route exists: `Route::post('/consent/biometric', ...)->name('biometric.consent.store')->middleware('signed')`

  **QA Scenarios**:
  - N/A — route definitions verified via code review

  **Commit**: YES (groups with T2-T6)
  - Message: `feat(consent): add biometric consent routes`
  - Files: `routes/web.php`

- [ ] 2. Create BiometricConsentGiven event

  **What to do**:
  - Create `app/Events/BiometricConsentGiven.php`
  - Constructor accepts `GymMember $gymMember` and `BiometricConsent $consent`
  - Use readonly properties

  **Must NOT do**:
  - Do NOT add any event dispatch logic — just define the event class

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - Reason: Simple event class creation, follows existing event patterns

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3-6)
  - **Blocks**: Task 7 (job uses this event), Task 10 (listener listens)
  - **Blocked By**: None

  **References**:
  - `app/Events/Reports/MemberStatsUpdated.php` — existing event pattern in project

  **Acceptance Criteria**:
  - [ ] Event class created at `app/Events/BiometricConsentGiven.php`
  - [ ] Constructor has readonly GymMember and BiometricConsent

  **QA Scenarios**:
  - N/A — class structure verified via code review

  **Commit**: YES (groups with T1, T3-T6)
  - Message: `feat(consent): add BiometricConsentGiven event`
  - Files: `app/Events/BiometricConsentGiven.php`

- [ ] 3. Create BiometricConsentRevoked event

  **What to do**:
  - Create `app/Events/BiometricConsentRevoked.php`
  - Constructor accepts only `GymMember $gymMember` (consent already being revoked)

  **Must NOT do**:
  - Do NOT add any event dispatch logic

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - Reason: Simple event class creation

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1-2, 4-6)
  - **Blocks**: Task 11 (listener listens)
  - **Blocked By**: None

  **References**:
  - `app/Events/Reports/MemberStatsUpdated.php` — existing event pattern

  **Acceptance Criteria**:
  - [ ] Event class created at `app/Events/BiometricConsentRevoked.php`
  - [ ] Constructor has readonly GymMember

  **QA Scenarios**:
  - N/A — class structure verified via code review

  **Commit**: YES (groups with T1-T2, T4-T6)
  - Message: `feat(consent): add BiometricConsentRevoked event`
  - Files: `app/Events/BiometricConsentRevoked.php`

- [ ] 4. Create BiometricConsentStoreRequest form

  **What to do**:
  - Create `app/Http/Requests/BiometricConsentStoreRequest.php`
  - Rules: gym_member_id (required, exists), gym_id (required, exists), consented (required, accepted)

  **Must NOT do**:
  - Do NOT add custom authorization — controller handles idempotency

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - Reason: Simple Form Request creation

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1-3, 5-6)
  - **Blocks**: Task 5 (controller uses this)
  - **Blocked By**: None

  **References**:
  - `app/Http/Requests/` - existing form requests to follow pattern

  **Acceptance Criteria**:
  - [ ] Form Request created at `app/Http/Requests/BiometricConsentStoreRequest.php`
  - [ ] Validation rules: gym_member_id (required, exists:gym_members,id), gym_id (required, exists:gyms,id), consented (required, accepted)

  **QA Scenarios**:
  - N/A — validation rules verified via code review

  **Commit**: YES (groups with T1-T3, T5-T6)
  - Message: `feat(consent): add BiometricConsentStoreRequest form`
  - Files: `app/Http/Requests/BiometricConsentStoreRequest.php`

- [ ] 5. Create BiometricConsentController

  **What to do**:
  - Create `app/Http/Controllers/BiometricConsentController.php`
  - `show()`: Find member, if hasActiveBiometricConsent show already-consented view, else show consent form
  - `store()`: Validate with BiometricConsentStoreRequest, idempotent check, create consent, dispatch BiometricConsentGiven event, show success

  **Must NOT do**:
  - Do NOT add auth middleware — routes already have signed middleware
  - Do NOT create duplicate consents — check hasActiveBiometricConsent() first

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []
  - Reason: Controller with multiple methods, needs proper request handling

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1-4, 6)
  - **Blocks**: Task 16 (integration test)
  - **Blocked By**: None

  **References**:
  - `app/Http/Controllers/Api/BiometricEnrollController.php` - existing controller pattern
  - `app/Models/GymMember.php` - hasActiveBiometricConsent() method at line 230

  **Acceptance Criteria**:
  - [ ] Controller created at `app/Http/Controllers/BiometricConsentController.php`
  - [ ] show() returns correct views based on consent status
  - [ ] store() creates consent and dispatches event

  **QA Scenarios**:
  - N/A — controller logic verified via code review

  **Commit**: YES (groups with T1-T4, T6)
  - Message: `feat(consent): add BiometricConsentController`
  - Files: `app/Http/Controllers/BiometricConsentController.php`

- [ ] 6. Create consent views

  **What to do**:
  - Create `resources/views/consent/biometric/show.blade.php` - consent form with checkbox
  - Create `resources/views/consent/biometric/success.blade.php` - success message
  - Create `resources/views/consent/biometric/already-consented.blade.php` - already consented message

  **Must NOT do**:
  - Do NOT require login — these are public pages
  - Do NOT add complex JavaScript — simple checkbox enable/disable

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`tailwindcss-development`]
  - Reason: Creating Blade views with Tailwind styling

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1-5)
  - **Blocks**: Task 16 (integration test)
  - **Blocked By**: None

  **References**:
  - Spec provides complete HTML/templates to follow
  - `resources/views/emails/` - existing Blade templates

  **Acceptance Criteria**:
  - [ ] show.blade.php: Checkbox disables submit until checked
  - [ ] success.blade.php: Shows member name and gym name
  - [ ] already-consented.blade.php: Shows member name and gym name

  **QA Scenarios**:
  - N/A — view structure verified via code review

  **Commit**: YES (groups with T1-T5)
  - Message: `feat(consent): add biometric consent views`
  - Files: `resources/views/consent/biometric/` (3 files)

- [ ] 7. Create SendBiometricConsentEmail job

  **What to do**:
  - Create `app/Jobs/SendBiometricConsentEmail.php`
  - Implements ShouldQueue
  - Handle: skip if already consented, skip if no email, generate signed URL, send mail
  - 3 tries, 60 second backoff

  **Must NOT do**:
  - Do NOT send if member already has active consent (idempotent)
  - Do NOT send if no email address

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []
  - Reason: Job with queue logic, idempotency checks

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 8-12)
  - **Blocks**: Task 9 (observer dispatches this), Task 14 (resource actions use this)
  - **Blocked By**: Task 2 (needs BiometricConsentGiven event for dispatch)

  **References**:
  - `app/Jobs/` - existing jobs to follow pattern
  - `config/biometrics.php` - consent_version config
  - Laravel docs: temporarySignedRoute

  **Acceptance Criteria**:
  - [ ] Job created at `app/Jobs/SendBiometricConsentEmail.php`
  - [ ] Idempotent: skips if hasActiveBiometricConsent()
  - [ ] Generates 7-day signed URL
  - [ ] Sends BiometricConsentMail

  **QA Scenarios**:
  - N/A — job logic verified via code review

  **Commit**: YES (groups with T8-T12)
  - Message: `feat(consent): add SendBiometricConsentEmail job`
  - Files: `app/Jobs/SendBiometricConsentEmail.php`

- [ ] 8. Create BiometricConsentMail and email view

  **What to do**:
  - Create `app/Mail/BiometricConsentMail.php`
  - Create `resources/views/emails/biometric-consent.blade.php`
  - Envelope: subject = "Biometric Data Consent — {gym name}"
  - View passes gymMember, gym, consentUrl, expiresIn (7 days)

  **Must NOT do**:
  - Do NOT include sensitive data in email

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - Reason: Standard mailable creation

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 7, 9-12)
  - **Blocks**: Task 16 (integration test)
  - **Blocked By**: Task 7 (job sends this mail)

  **References**:
  - `app/Mail/` - existing mailables to follow pattern
  - Spec provides complete email template

  **Acceptance Criteria**:
  - [ ] Mailable created at `app/Mail/BiometricConsentMail.php`
  - [ ] Email view created at `resources/views/emails/biometric-consent.blade.php`
  - [ ] Subject includes gym name

  **QA Scenarios**:
  - N/A — mail structure verified via code review

  **Commit**: YES (groups with T7, T9-T12)
  - Message: `feat(consent): add BiometricConsentMail and email view`
  - Files: `app/Mail/BiometricConsentMail.php`, `resources/views/emails/biometric-consent.blade.php`

- [ ] 9. Update GymMemberObserver to dispatch consent email job

  **What to do**:
  - Modify `app/Observers/GymMemberObserver.php`
  - Add to existing created() method: dispatch SendBiometricConsentEmail with 2 min delay
  - Only if email is not empty

  **Must NOT do**:
  - Do NOT replace existing logic in created() — ADD to it
  - Do NOT dispatch if member has no email

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - Reason: Simple observer update

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 7-8, 10-12)
  - **Blocks**: Task 16 (integration test)
  - **Blocked By**: Task 7 (job must exist)

  **References**:
  - `app/Observers/GymMemberObserver.php` - existing file (lines 25-34)
  - `app/Jobs/SendBiometricConsentEmail.php` - job being dispatched

  **Acceptance Criteria**:
  - [ ] created() dispatches SendBiometricConsentEmail
  - [ ] Has 2 minute delay: `->delay(now()->addMinutes(2))`
  - [ ] Only if !empty($gymMember->email)

  **QA Scenarios**:
  - N/A — observer modification verified via code review

  **Commit**: YES (groups with T7-T8, T10-T12)
  - Message: `feat(consent): trigger consent email on member creation`
  - Files: `app/Observers/GymMemberObserver.php`

- [ ] 10. Create NotifyStaffOnConsentGiven listener

  **What to do**:
  - Create `app/Listeners/NotifyStaffOnConsentGiven.php`
  - Handle BiometricConsentGiven event
  - Find gym owners via gym->users() with pivot role 'owner'
  - Send Filament database notification to each owner

  **Must NOT do**:
  - Do NOT notify all gym users — only owners

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - Reason: Simple listener with notification

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 7-9, 11-12)
  - **Blocks**: Task 12 (register in AppServiceProvider)
  - **Blocked By**: Task 2 (needs event)

  **References**:
  - `app/Listeners/` - existing listeners to follow pattern
  - `app/Providers/AppServiceProvider.php` - event registration pattern

  **Acceptance Criteria**:
  - [ ] Listener created at `app/Listeners/NotifyStaffOnConsentGiven.php`
  - [ ] Sends notification to gym owners only

  **QA Scenarios**:
  - N/A — listener logic verified via code review

  **Commit**: YES (groups with T7-T9, T11-T12)
  - Message: `feat(consent): add NotifyStaffOnConsentGiven listener`
  - Files: `app/Listeners/NotifyStaffOnConsentGiven.php`

- [ ] 11. Create HandleConsentRevoked listener

  **What to do**:
  - Create `app/Listeners/HandleConsentRevoked.php`
  - Handle BiometricConsentRevoked event
  - Call BiometricDataDeletionService::deleteForGymMember()

  **Must NOT do**:
  - Do NOT delete consent record — just biometric data

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - Reason: Simple listener calling service

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 7-10, T12)
  - **Blocks**: Task 12 (register in AppServiceProvider)
  - **Blocked By**: Task 3 (needs event)

  **References**:
  - `app/Biometrics/Services/BiometricDataDeletionService.php` - existing service
  - `app/Listeners/` - existing patterns

  **Acceptance Criteria**:
  - [ ] Listener created at `app/Listeners/HandleConsentRevoked.php`
  - [ ] Calls deleteForGymMember() on BiometricDataDeletionService

  **QA Scenarios**:
  - N/A — listener logic verified via code review

  **Commit**: YES (groups with T7-T10, T12)
  - Message: `feat(consent): add HandleConsentRevoked listener`
  - Files: `app/Listeners/HandleConsentRevoked.php`

- [ ] 12. Register event listeners in AppServiceProvider

  **What to do**:
  - Modify `app/Providers/AppServiceProvider.php`
  - Add FacadesEvent::listen() for BiometricConsentGiven → NotifyStaffOnConsentGiven
  - Add FacadesEvent::listen() for BiometricConsentRevoked → HandleConsentRevoked

  **Must NOT do**:
  - Do NOT break existing event registrations

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []
  - Reason: Simple service provider update following existing pattern

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 2 (with Tasks 7-11)
  - **Blocks**: Task 16 (integration test)
  - **Blocked By**: Tasks 10, 11 (listeners must exist)

  **References**:
  - `app/Providers/AppServiceProvider.php` - existing event registrations (lines 77-94)
  - Laravel docs: FacadesEvent::listen()

  **Acceptance Criteria**:
  - [ ] BiometricConsentGiven → NotifyStaffOnConsentGiven registered
  - [ ] BiometricConsentRevoked → HandleConsentRevoked registered

  **QA Scenarios**:
  - N/A — registration verified via code review

  **Commit**: YES (groups with T7-T11)
  - Message: `feat(consent): register consent event listeners`
  - Files: `app/Providers/AppServiceProvider.php`

- [ ] 13. Add consent status column to GymMemberResource table

  **What to do**:
  - Modify `app/Filament/Admin/Resources/GymMemberResource.php`
  - Add TextColumn: biometric_consent_status with badge (Consented/Pending)
  - Use hasActiveBiometricConsent() to determine state

  **Must NOT do**:
  - Do NOT remove existing columns

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: [`fluxui-development`]
  - Reason: Adding Filament column

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 14-16)
  - **Blocks**: None
  - **Blocked By**: None

  **References**:
  - `app/Filament/Admin/Resources/GymMemberResource.php` - existing resource
  - Spec: Phase 11.1

  **Acceptance Criteria**:
  - [ ] Column added: TextColumn::make('biometric_consent_status')
  - [ ] Badge: green for Consented, yellow for Pending

  **QA Scenarios**:
  - N/A — column addition verified via code review

  **Commit**: YES (groups with T14-T16)
  - Message: `feat(consent): add consent status column to GymMemberResource`
  - Files: `app/Filament/Admin/Resources/GymMemberResource.php`

- [ ] 14. Add consent actions to GymMemberResource

  **What to do**:
  - Modify `app/Filament/Admin/Resources/GymMemberResource.php`
  - Add ActionGroup with:
    - resend_consent_email: dispatches SendBiometricConsentEmail
    - copy_consent_link: copies 7-day signed URL to clipboard
    - open_consent_page: opens 1-hour signed URL in new tab
    - revoke_consent: sets revoked_at, triggers BiometricConsentRevoked event

  **Must NOT do**:
  - Do NOT show actions if member already consented (except revoke)
  - Do NOT show revoke if no active consent

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: [`fluxui-development`]
  - Reason: Complex Filament actions with notifications and clipboard

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 13, 15-16)
  - **Blocks**: Task 16 (integration test)
  - **Blocked By**: Task 7 (uses job)

  **References**:
  - `app/Filament/Admin/Resources/GymMemberResource.php` - existing resource
  - `app/Jobs/SendBiometricConsentEmail.php` - job for resend
  - Spec: Phase 11.2

  **Acceptance Criteria**:
  - [ ] resend_consent_email: visible when no consent + has email
  - [ ] copy_consent_link: visible when no consent
  - [ ] open_consent_page: visible when no consent (1hr expiry)
  - [ ] revoke_consent: visible when has consent (requires confirmation)

  **QA Scenarios**:
  - N/A — actions verified via code review

  **Commit**: YES (groups with T13, T15-T16)
  - Message: `feat(consent): add consent actions to GymMemberResource`
  - Files: `app/Filament/Admin/Resources/GymMemberResource.php`

- [ ] 15. Create BiometricConsent feature tests

  **What to do**:
  - Create `tests/Feature/BiometricConsentTest.php`
  - Test consent flow: signed URL access, form submission, consent recorded
  - Test idempotency: cannot create duplicate consents
  - Test staff actions: resend email creates job, copy link works
  - Use .env.testing with gym_app_test database

  **Must NOT do**:
  - Do NOT test external services (Twilio, email)

  **Recommended Agent Profile**:
  - **Category**: `unspecified-high`
  - **Skills**: []
  - Reason: Feature test creation

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 3 (with Tasks 13-14, 16)
  - **Blocks**: None
  - **Blocked By**: Tasks 1-14 (tests depend on implementation)

  **References**:
  - `tests/Feature/` - existing feature tests to follow pattern
  - Laravel docs: testing signed URLs

  **Acceptance Criteria**:
  - [ ] Test: GET consent page with valid signed URL returns 200
  - [ ] Test: GET consent page without signed URL returns 403
  - [ ] Test: POST consent with checkbox creates BiometricConsent record
  - [ ] Test: POST consent when already consented shows success (idempotent)
  - [ ] All tests pass

  **QA Scenarios**:
  - Run: `docker-compose exec app php artisan test --filter=BiometricConsentTest`
  - Expected: All tests pass

  **Evidence**: `.sisyphus/evidence/task-15-test-output.txt`

  **Commit**: YES
  - Message: `test(consent): add BiometricConsent feature tests`
  - Files: `tests/Feature/BiometricConsentTest.php`
  - Pre-commit: `docker-compose exec app php artisan test --filter=BiometricConsentTest`

- [ ] 16. Verify full integration flow

  **What to do**:
  - End-to-end verification:
    1. Create member via tinker or test
    2. Verify job is dispatched (check database queue)
    3. Simulate consent flow with signed URL
    4. Verify consent recorded
    5. Verify event fires and listener runs
    6. Verify GymMemberResource shows "Consented"

  **Must NOT do**:
  - Do NOT modify any files — verification only

  **Recommended Agent Profile**:
  - **Category**: `deep`
  - **Skills**: []
  - Reason: Integration verification across multiple components

  **Parallelization**:
  - **Can Run In Parallel**: NO
  - **Parallel Group**: Wave 3 (final task)
  - **Blocks**: None
  - **Blocked By**: Tasks 1-15

  **References**:
  - All implemented files from Tasks 1-15

  **Acceptance Criteria**:
  - [ ] Full flow works: member created → email job → consent given → staff notified
  - [ ] Consent blocks enrollment (verified via existing code)
  - [ ] Consent blocks check-in via MembershipGuard (verified via existing code)

  **QA Scenarios**:
  - Scenario: Full consent workflow
    Tool: Bash + tinker
    Steps:
      1. Create test gym and member
      2. Check queue for SendBiometricConsentEmail job
      3. Generate signed URL manually
      4. POST to consent URL with consented=1
      5. Verify BiometricConsent record created
      6. Verify GymMemberResource shows Consented
    Expected Result: All steps succeed
    Evidence: `.sisyphus/evidence/task-16-integration.log`

  **Commit**: NO (verification only)

---

## Final Verification Wave (MANDATORY)

- [ ] F1. **Plan Compliance Audit** — `oracle`
  Verify all acceptance criteria from Tasks 1-16 are met.
  Output: `Tasks [16/16] | VERDICT: APPROVE/REJECT`

- [ ] F2. **Code Quality Review** — `unspecified-high`
  Run `vendor/bin/pint --dirty` on all changed files.
  Check for: proper use of readonly, explicit return types, PHPDoc blocks.
  Output: `Lint [PASS/FAIL] | VERDICT`

- [ ] F3. **Test Suite Run** — `unspecified-high`
  Run full test suite: `docker-compose exec app php artisan test`
  Ensure no regressions introduced.
  Output: `Tests [N pass/N fail] | VERDICT`

- [ ] F4. **Scope Fidelity Check** — `deep`
  Verify no extra files modified beyond plan scope.
  Output: `Scope [CLEAN/N issues] | VERDICT`

---

## Commit Strategy

- **Wave 1** (T1-T6): `feat(consent): add biometric consent core (routes, events, controller, views)`
- **Wave 2** (T7-T12): `feat(consent): add email job, listeners, event registration`
- **Wave 3** (T13-T15): `feat(consent): add GymMemberResource columns/actions + tests`

---

## Success Criteria

### Verification Commands
```bash
# Run consent tests
docker-compose exec app php artisan test --filter=BiometricConsentTest

# Verify routes exist
docker-compose exec app php artisan route:list | grep consent

# Verify observer registered
grep -r "SendBiometricConsentEmail" app/Observers/
```

### Final Checklist
- [ ] All 16 tasks completed
- [ ] Tests pass
- [ ] No lint errors
- [ ] All acceptance criteria met
- [ ] User can start work with `/start-work biometric-consent`

