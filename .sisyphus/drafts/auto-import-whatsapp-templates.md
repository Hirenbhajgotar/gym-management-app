# Draft: Auto-Import WhatsApp Templates on Manual WABA Registration

## Requirements (confirmed from user)
- Import WhatsApp templates when a user manually registers their WABA account
- "Only imports its when user register his/her account manually" — scope limited to manual WABA connection
- Use DRY Principle across integration points
- Reuse existing `ImportWhatsAppTemplatesJob` and `ImportTenantWhatsAppTemplates` command infrastructure

## Technical Decisions
- **DRY approach**: Add static `dispatchForGym()` method to `ImportWhatsAppTemplatesJob` to centralize dispatch logic
- **Integration points**: 
  1. `ConnectExistingWabaAction::__invoke()` — Filament action (primary)
  2. `EmbeddedSignupController::manualConnect()` — HTTP controller (secondary)
- **Excluded from scope**: Embedded signup callback (`EmbeddedSignupController@callback`), Twilio account setup

## Trigger Points Identified

### 1. ConnectExistingWabaAction (Primary)
- File: `app/Filament/Admin/WhatsAppSetup/Actions/ConnectExistingWabaAction.php`
- Current flow: validate → rate limit → call `connectManualWaba()` → show notification on success
- Insertion point: After line 44 (`if ($result['success'])`), before notification sends
- After connection, account has `status = ACTIVE` and `verification_status = VERIFIED`

### 2. EmbeddedSignupController::manualConnect() (Secondary)
- File: `app/Http/Controllers/WhatsApp/EmbeddedSignupController.php`
- Route: `POST /whatsapp/embedded-signup/manual-connect/{tenant}` (authenticated)
- Current flow: validate → call `connectManualWaba()` → redirect on success
- Insertion point: After line 126 (success check), before redirect

## Existing Infrastructure
- `ImportWhatsAppTemplatesJob` — queue job, `ShouldBeUnique`, `$tries=3`, `$backoff=60`, queue: `whatsapp-templates`
- `ImportTenantWhatsAppTemplates` command — dispatches job for one or all gyms
- `ImportWhatsAppTemplatesJob` constructor: `(int $gymId, ?WhatsAppProviderTypeEnum $providerType = null)`

## Queue Check
- Import job already uses `ShouldBeUnique` based on gym ID → safe to dispatch multiple times
- Import job checks `isActive() && isVerified()` before syncing → only imports when account is ready

## Edge Cases
- Import job is unique per gym → concurrent dispatches won't duplicate work
- Import job has 3 retries with 60s backoff
- If queue worker is not running, import happens when worker starts
- Account must be active + verified or job silently returns (logged warning)

## Scope Boundaries
- INCLUDE: Manual WABA connection via ConnectExistingWabaAction and EmbeddedSignupController::manualConnect
- INCLUDE: DRY dispatch helper on ImportWhatsAppTemplatesJob
- EXCLUDE: Embedded signup callback flow
- EXCLUDE: Twilio account setup
- EXCLUDE: Changes to the import job's handle() logic
- EXCLUDE: New database columns or migrations
