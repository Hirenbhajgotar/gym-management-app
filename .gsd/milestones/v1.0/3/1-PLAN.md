---
phase: 3
plan: 1
wave: 1
---

# Plan 3.1: Filament Action UI & Dynamic Modal Schema

## Objective
Implement the Header Action on `ListGymMembers` and Table Bulk Action on `GymMemberResource` for sending biometric consent notifications. Both actions will render a channel selection modal containing an Email toggle and a conditionally rendered WhatsApp toggle guarded by `NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($gym)`. Both actions will target eligible active members without active consent and dispatch `SendBiometricConsentNotificationJob` asynchronously.

## Context
- [.gsd/SPEC.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/SPEC.md)
- [.gsd/REQUIREMENTS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/REQUIREMENTS.md)
- [.gsd/DECISIONS.md](file:///c:/xampp/htdocs/projects/gym-app/.gsd/DECISIONS.md)
- [app/Models/GymMember.php](file:///c:/xampp/htdocs/projects/gym-app/app/Models/GymMember.php)
- [app/Filament/Admin/Resources/GymMemberResource.php](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Resources/GymMemberResource.php)
- [app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php)
- [app/Services/NotificationChannelResolver.php](file:///c:/xampp/htdocs/projects/gym-app/app/Services/NotificationChannelResolver.php)
- [app/Jobs/SendBiometricConsentNotificationJob.php](file:///c:/xampp/htdocs/projects/gym-app/app/Jobs/SendBiometricConsentNotificationJob.php)

## Tasks

<task type="auto">
  <name>Add scopeWithoutActiveBiometricConsent to GymMember and Bulk Action to GymMemberResource</name>
  <files>app/Models/GymMember.php, app/Filament/Admin/Resources/GymMemberResource.php</files>
  <action>
    - In `app/Models/GymMember.php`:
      - Add `scopeWithoutActiveBiometricConsent(Builder $query, ?int $gymId = null): Builder` which queries members where `whereDoesntHave('biometricConsents', fn($q) => $q->whereNull('revoked_at')->when($gymId, fn($q) => $q->where('gym_id', $gymId)))`.
    - In `app/Filament/Admin/Resources/GymMemberResource.php`:
      - Import `Filament\Forms\Components\Toggle`, `App\Services\NotificationChannelResolver`, and `App\Jobs\SendBiometricConsentNotificationJob`.
      - In `table()->bulkActions()`:
        - Add `BulkAction::make('sendBiometricConsent')` under `BulkActionGroup`:
          - `label('Send Biometric Consent')`
          - `icon('heroicon-o-finger-print')`
          - `color('primary')`
          - `modalHeading('Send Biometric Consent Request')`
          - `modalDescription(fn (EloquentCollection $records) => ...)` summarizing count of selected unconsented members.
          - `form([ ... ])`:
            - `Toggle::make('send_email')->label('Send via Email')->default(true)`
            - `Toggle::make('send_whatsapp')->label('Send via WhatsApp')->default(false)->visible(fn () => ($gym = Filament::getTenant()) instanceof Gym && NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($gym))`
          - `action(function (EloquentCollection $records, array $data): void)`:
            - Validate at least one channel is selected. If not, notify danger and return.
            - Filter `$records` to those where `!$member->hasActiveBiometricConsent()`.
            - If no eligible members, notify warning and return.
            - Dispatch `SendBiometricConsentNotificationJob` for each eligible member with chosen channels.
            - Send success notification reporting queued count.
          - `deselectRecordsAfterCompletion()`
  </action>
  <verify>php -r "require 'vendor/autoload.php'; echo method_exists(\App\Models\GymMember::class, 'scopeWithoutActiveBiometricConsent') ? 'OK' : 'FAIL';"</verify>
  <done>GymMember has unconsented scope and GymMemberResource has sendBiometricConsent bulk action.</done>
</task>

<task type="auto">
  <name>Implement Header Action on ListGymMembers page</name>
  <files>app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php</files>
  <action>
    - In `app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php`:
      - Import `Filament\Actions\Action`, `Filament\Forms\Components\Toggle`, `Filament\Notifications\Notification`, `App\Services\NotificationChannelResolver`, `App\Jobs\SendBiometricConsentNotificationJob`, and `App\Models\Gym`.
      - In `getHeaderActions()`:
        - Add `Action::make('sendBiometricConsentToAll')`:
          - `label('Request Biometric Consent')`
          - `icon('heroicon-o-finger-print')`
          - `color('primary')`
          - `modalHeading('Send Biometric Consent to All Eligible Members')`
          - `modalDescription(function () { ... })` dynamically displaying count of active, unarchived members lacking active consent for the tenant:
            `GymMember::query()->where('gym_id', $this->getGymId())->notArchived()->membershipActive()->withoutActiveBiometricConsent((int) $this->getGymId())->count()`
          - `form([ ... ])`:
            - `Toggle::make('send_email')->label('Send via Email')->default(true)`
            - `Toggle::make('send_whatsapp')->label('Send via WhatsApp')->default(false)->visible(fn () => ($gym = $this->getGym()) && NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($gym))`
          - `action(function (array $data): void)`:
            - Validate at least one channel is selected.
            - Query all active unconsented members using `withoutActiveBiometricConsent((int) $this->getGymId())`.
            - Dispatch `SendBiometricConsentNotificationJob` for each member.
            - Notify success with queued count.
  </action>
  <verify>docker compose exec app php -l app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php</verify>
  <done>ListGymMembers exposes header action with dynamic description, dual-gate WhatsApp toggle, and queue dispatch.</done>
</task>

<task type="auto">
  <name>Format code and verify syntax</name>
  <files>app/Models/GymMember.php, app/Filament/Admin/Resources/GymMemberResource.php, app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php</files>
  <action>
    - Run PHP linter on all modified files inside the container.
    - Run `vendor/bin/pint --dirty` to ensure PSR-12 and Pint code formatting standards.
  </action>
  <verify>docker compose exec app vendor/bin/pint --dirty</verify>
  <done>All modified files pass syntax check and Pint formatting.</done>
</task>

## Success Criteria
- [ ] GymMember model provides scopeWithoutActiveBiometricConsent
- [ ] GymMemberResource includes sendBiometricConsent bulk action with modal toggles
- [ ] ListGymMembers includes sendBiometricConsentToAll header action with eligible recipient count
- [ ] WhatsApp toggle is visible ONLY when NotificationChannelResolver::canSendBiometricConsentViaWhatsApp returns true
- [ ] All code passes linting and Pint formatting
