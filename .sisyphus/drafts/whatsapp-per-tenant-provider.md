# Draft: WhatsApp Per-Tenant Provider Selection

## Requirements (Confirmed)
- Provider selection should be per gym (tenant), NOT global
- If tenant already has a phone number → use WABA provider (Meta WhatsApp embedded signup)
- If tenant wants to purchase number → use Twilio provider (subaccount + WhatsApp sender registration)
- Store provider in whatsapp_accounts table
- WABA credentials must be properly stored: waba_id, phone_number_id, access_token, etc.

## Current Architecture Discovery
- WhatsAppAccount model: has fields for both Twilio and WABA credentials
- Current provider selection: global via config('services.whatsapp.provider')
- WhatsAppSetup page: 6-step wizard (need to reduce to 5 steps)
- Existing factory: WhatsAppProviderFactory (uses config, not per-tenant)

## Technical Decisions
- Add `provider_type` ENUM to whatsapp_accounts: 'twilio' | 'waba'
- Update factory to read provider from WhatsAppAccount
- Must store all WABA fields: waba_id, phone_number_id, meta_access_token, meta_business_portfolio_id, etc.

## Scope Boundaries
- INCLUDE:
  - Database migration for provider_type
  - WhatsAppAccount model updates
  - WhatsAppProviderFactory update
  - WhatsAppSetup page flow redesign (5 steps)
  - TwilioAccountManager changes per provider selection
  - WABA config storage in database
- EXCLUDE:
  - Changes to existing Twilio/Meta provider implementations (they work)
  - Breaking existing functionality

## Open Questions
- What exact fields need to be stored for WABA (beyond existing)?
  - Already have: waba_id, meta_access_token, meta_business_portfolio_id, embedded_signup_completed, embedded_signup_session_id
  - Need to add: phone_number_id (for WABA phone number)
- How to handle migration of existing accounts?
  - Keep existing Twilio as default, let owner upgrade to WABA