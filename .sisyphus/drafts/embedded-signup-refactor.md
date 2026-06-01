# Draft: WhatsApp EmbeddedSignup Flow Refactoring

## Goal
Refactor the EmbeddedSignup flow to use `WhatsAppProviderInterface` instead of old Twilio services directly in `EmbeddedSignupService`.

## Current State

### Files Involved
- `app\Filament\Admin\Pages\WhatsAppSetup.php` (lines 1355-1386) - `startEmbeddedSignup()` method
- `app\Services\EmbeddedSignupService.php` - Uses `Twilio\Rest\Client` directly
- `app\Interfaces\WhatsAppProviderInterface.php` - Interface with 25+ methods
- `app\Providers\MetaWhatsAppProvider.php` - Implements interface for WABA

### Current Flow (WhatsAppSetup.php lines 1355-1386)
```php
public function startEmbeddedSignup(): void
{
    $gym = $this->getGym();
    // ... notification ...
    $this->redirect(route('whatsapp.embedded-signup.redirect', ['tenant' => $gym->slug]));
}
```

### EmbeddedSignupService Issues
- Direct `Twilio\Rest\Client` instantiation (line 12: `use Twilio\Rest\Client`)
- Uses old Twilio services instead of provider interface
- Duplicates logic that should be in MetaWhatsAppProvider

## Requirements (to gather)
- [TBD] Should EmbeddedSignupService be deprecated/removed or kept as fallback?
- [TBD] Error handling approach for callback failures
- [TBD] Session management strategy

## Technical Decisions (made)
- Use WhatsAppProviderInterface for all provider operations
- Add `startEmbeddedSignup()` method to interface
- Implement in MetaWhatsAppProvider
- Keep backward compatibility for existing routes

## Scope
- IN: Update EmbeddedSignupService to use provider, add interface methods
- OUT: Don't modify WhatsAppSetup.php UI, don't change route structure

## Open Questions
- How to handle Twilio-specific code in EmbeddedSignupService?
- Session storage approach (WabaSetupSession model exists)