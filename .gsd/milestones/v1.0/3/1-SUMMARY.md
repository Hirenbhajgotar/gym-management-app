# Plan 3.1 Summary: Filament Action UI & Dynamic Modal Schema

## Objective Achieved
Implemented the Header Action on `ListGymMembers` and the Table Bulk Action on `GymMemberResource` for distributing biometric consent notifications. Both actions present a confirmation modal with an Email toggle (default true) and a conditionally visible WhatsApp toggle governed by `NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($gym)`. Both actions dynamically target active unconsented members via `GymMember::scopeWithoutActiveBiometricConsent` and queue `SendBiometricConsentNotificationJob` for asynchronous execution.

## Changes Made
1. **[app/Models/GymMember.php](file:///c:/xampp/htdocs/projects/gym-app/app/Models/GymMember.php)**:
   - Added `scopeWithoutActiveBiometricConsent(Builder $query, ?int $gymId = null)` to filter members who do not have an active (unrevoked) biometric consent record for the tenant.
   - Added static method docblock `@method static Builder|GymMember withoutActiveBiometricConsent(?int $gymId = null)` for IDE autocompletion and static analysis.

2. **[app/Filament/Admin/Resources/GymMemberResource.php](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Resources/GymMemberResource.php)**:
   - Added `BulkAction::make('sendBiometricConsent')` to `bulkActions()` under `BulkActionGroup`.
   - Included dynamic `modalDescription` showing the count of selected members lacking active consent.
   - Added `send_email` toggle and dual-gated `send_whatsapp` toggle (visible only if `canSendBiometricConsentViaWhatsApp($tenant)` returns true).
   - Added validation ensuring at least one channel is selected, filtered out already-consented members, and queued `SendBiometricConsentNotificationJob` for eligible recipients.

3. **[app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php)**:
   - Added `Action::make('sendBiometricConsentToAll')` to `getHeaderActions()`.
   - Included dynamic `modalDescription` evaluating `GymMember::query()->where('gym_id', $gymId)->notArchived()->membershipActive()->withoutActiveBiometricConsent($gymId)->count()`.
   - Form schema contains `send_email` and dual-gated `send_whatsapp` toggle.
   - Action closure validates channels, batches all eligible active unconsented members using chunking (100 per chunk), queues `SendBiometricConsentNotificationJob`, and dispatches Filament success notifications.

4. **Code Quality & Verification**:
   - Verified PHP syntax with `php -l` on all touched files.
   - Formatted using `vendor/bin/pint --dirty`.
   - Verified regression safety with all 12 tests passing across Phase 1 and Phase 2 test suites.

## Verification Artifacts
- Syntax checks passed:
  - `app/Filament/Admin/Resources/GymMemberResource/Pages/ListGymMembers.php`: OK
  - `app/Filament/Admin/Resources/GymMemberResource.php`: OK
  - `app/Models/GymMember.php`: OK
- Code style: Pint passed with 0 errors across dirty files.
- Test suite: 12 tests passed (22 assertions) in `SendBiometricConsentNotificationJobTest` and `NotificationChannelResolverDualGateTest`.
