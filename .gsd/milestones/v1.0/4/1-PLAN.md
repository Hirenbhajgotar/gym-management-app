---
phase: 4
plan: 1
wave: 1
gap_closure: false
---

# Plan 4.1: Feature Tests for Biometric Consent Header & Bulk Actions

## Objective
Write comprehensive feature test cases in `tests/Feature/Filament/BiometricConsentActionsTest.php` verifying the Header Action (`sendBiometricConsentToAll`) on `ListGymMembers` and the Table Bulk Action (`sendBiometricConsent`) on `GymMemberResource`. These tests ensure proper recipient filtering (only active, unarchived members lacking active biometric consent), multi-channel queue dispatching (`SendBiometricConsentNotificationJob`), and user-facing notifications for edge cases (zero eligible recipients, no channel selected).

## Context
Load these files for context:
- [.gsd/SPEC.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/SPEC.md)
- [.gsd/REQUIREMENTS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/REQUIREMENTS.md)
- [app/Filament/Admin/Resources/GymMemberResource.php](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Resources/GymMemberResource.php)
- [app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php)
- [app/Jobs/SendBiometricConsentNotificationJob.php](file:///c:/xampp/htdocs/projects/gym-app/app/Jobs/SendBiometricConsentNotificationJob.php)
- [tests/Feature/GymMemberArchivingTest.php](file:///c:/xampp/htdocs/projects/gym-app/tests/Feature/GymMemberArchivingTest.php)

## Tasks

<task type="auto">
  <name>Create BiometricConsentActionsTest feature test class</name>
  <files>tests/Feature/Filament/BiometricConsentActionsTest.php</files>
  <action>
    Create `tests/Feature/Filament/BiometricConsentActionsTest.php` inheriting from `Tests\TestCase` with `RefreshDatabase`.
    Implement test cases:
    1. `test_header_action_dispatches_jobs_for_all_unconsented_active_members`:
       - Create active members: unconsented active members, an active member with existing unrevoked `BiometricConsent`, an archived member, and an expired membership member.
       - Use `Queue::fake()`.
       - Test `ListGymMembers` page using `Livewire::actingAs($gymOwner)->test(ListGymMembers::class)->callAction('sendBiometricConsentToAll', ['send_email' => true, 'send_whatsapp' => false])`.
       - Assert `SendBiometricConsentNotificationJob` was pushed only for the unconsented active members and NOT for consented, archived, or expired members.
    2. `test_header_action_shows_warning_when_no_eligible_members`:
       - Seed only consented active members.
       - Call `sendBiometricConsentToAll`.
       - Assert warning notification (`No eligible members`) and assert queue pushed 0 jobs.
    3. `test_header_action_requires_at_least_one_channel_selected`:
       - Call `sendBiometricConsentToAll` with `['send_email' => false, 'send_whatsapp' => false]`.
       - Assert danger notification (`No delivery channel selected`) and assert queue pushed 0 jobs.
    4. `test_bulk_action_dispatches_jobs_for_selected_unconsented_members`:
       - Create unconsented member A and already-consented member B.
       - Call table bulk action `sendBiometricConsent` with records `[$memberA, $memberB]` and `['send_email' => true, 'send_whatsapp' => false]`.
       - Assert job pushed for member A and not member B.
    5. `test_bulk_action_shows_warning_when_all_selected_members_are_already_consented`:
       - Call bulk action on only member B (already consented).
       - Assert warning notification and 0 jobs pushed.
    6. `test_bulk_action_requires_at_least_one_channel_selected`:
       - Call bulk action with `['send_email' => false, 'send_whatsapp' => false]`.
       - Assert danger notification and 0 jobs pushed.
  </action>
  <verify>docker compose exec app php artisan test --env=testing tests/Feature/Filament/BiometricConsentActionsTest.php</verify>
  <done>All 6 test cases in BiometricConsentActionsTest pass against gym_app_test.</done>
</task>

<task type="auto">
  <name>Verify Pint formatting and execution</name>
  <files>tests/Feature/Filament/BiometricConsentActionsTest.php</files>
  <action>
    Run `vendor/bin/pint --dirty` inside the container to format the test file.
    Re-run the test suite to confirm 100% pass rate.
  </action>
  <verify>docker compose exec app php artisan test --env=testing tests/Feature/Filament/BiometricConsentActionsTest.php</verify>
  <done>Pint clean and all feature tests pass.</done>
</task>

## Must-Haves
- [ ] Header action sends only to unconsented active members
- [ ] Bulk action filters out already-consented members
- [ ] Dispatches SendBiometricConsentNotificationJob with selected channel flags
- [ ] Validates channel selection requiring at least one channel
- [ ] Database safety strictly enforced (gym_app_test)

## Success Criteria
- [ ] All tests in BiometricConsentActionsTest pass
- [ ] Code formatted with Pint
