---
feature: WhatsApp Integration
status: draft
last_updated: 2026-05-29
related_docs:
  - docs/whatsapp/database/whatsapp-accounts-fields.md
  - docs/whatsapp/database/whatsapp-templates-fields.md
  - docs/whatsapp/database/whatsapp-message-credits-fields.md
  - docs/whatsapp/database/whatsapp-broadcasts-fields.md
  - docs/whatsapp/database/whatsapp-usage-logs-fields.md
  - docs/whatsapp/database/whatsapp-packages-fields.md
  - docs/whatsapp/database/whatsapp-phone-numbers-fields.md
  - docs/whatsapp/database/whatsapp-credit-transactions-fields.md
  - docs/whatsapp/architecture/ADR-005-whatsapp-provider-abstraction.md
  - docs/whatsapp/architecture/ADR-006-whatsapp-broadcast-architecture.md
---

# WhatsApp Integration

## Overview

The WhatsApp integration enables gym owners to communicate with members via WhatsApp — sending membership reminders, payment notifications, class schedules, and promotional messages. It is a multi-tenant feature where each gym has its own WhatsApp account, phone number, credit balance, and templates.

The system supports two provider backends (Meta WhatsApp Business API and Twilio) and includes a credit-based billing model where gyms purchase message credits in packages.

## Architecture

### Provider Abstraction

The system abstracts WhatsApp providers behind two interfaces:

- **`TemplateManagementInterface`** — create, submit, sync, and list templates
- **`WhatsAppProviderInterface`** (deprecated) — full-spectrum operations including messaging, account management, phone numbers, templates, credits, WABA setup, embedded signup

The `WhatsAppProviderFactory` resolves which provider to use per gym (per-tenant `provider_type` in `whatsapp_accounts`), with SWR caching (1 day min, 1 week max). System messages always route through Twilio regardless of tenant config.

Two concrete providers implement these interfaces:

| Provider | Use Case |
|----------|----------|
| `MetaWhatsAppProvider` | Direct Meta WABA integration — embedded signup, manual WABA connection, template management via Meta API |
| `TwilioWhatsAppProvider` | Twilio-based messaging — sub-account creation, number purchasing, BYOD (bring your own device) number verification |

### Core Services & Helpers

| Service / Class | Responsibility |
|---|---|
| `WhatsAppCreditService` | Provider-agnostic credit operations — balance checks, upfront deductions, refunds, usage logging. Implements `CreditManagementInterface`. |
| `WhatsAppBillingService` | Razorpay payment integration — package purchases, phone number surcharges, welcome credits |
| `WhatsAppBroadcastService` | Broadcast orchestration and dispatch via `SendBroadcastJob` & `SendMessageChunkJob` |
| `WhatsAppVariableResolver` | Resolves template variables (member name, gym name, dates, custom parameters) |
| `WhatsAppFailureClassifier` | Classifies delivery errors into transient vs. permanent failures for intelligent retries |
| `TemplateVariableHelper` | Utility for template variable parsing |

### Data Flow

```
Gym Owner
    │
    ├── WhatsApp Setup (connect WABA or Twilio account)
    │       → WhatsAppAccount record
    │       → WhatsAppPhoneNumber record
    │
    ├── Create Template (via Meta API or Twilio)
    │       → WhatsAppTemplate record
    │       → Submitted for Meta approval
    │
    ├── Purchase Credits (via Razorpay)
    │       → WhatsAppMessageCredit balance updated
    │       → WhatsAppTransaction log
    │
    └── Send Message/Broadcast
            → Upfront credit deduction (checked against WhatsAppMessageCredit)
            → Chunked dispatch (SendMessageChunkJob)
            → Provider sends via API (Meta or Twilio)
            → If errors: WhatsAppFailureClassifier categorizes failures
            → If failures / cancellation: Unspent credits refunded automatically
            → WhatsAppUsageLog recorded
```

## Admin Panel (Filament)

The WhatsApp admin interface is a group of Filament pages and resources under the "WhatsApp" navigation group:

| Page/Resource | Sort | Description |
|---------------|------|-------------|
| `WhatsappDashboard` | 1 | Credit balance, monthly usage stats, recent messages |
| `WhatsAppSetup` | 2 | Multi-step wizard to connect Meta WABA or Twilio account |
| `PurchaseCredits` | 3 | Browse packages, purchase via Razorpay, claim welcome credits |
| `Broadcast` | 4 | Send batch marketing and informational broadcasts with recipient filters |
| `WhatsAppTemplateResource` | 5 | CRUD for templates with default template selection, category guidance, preview |
| `WhatsAppTransactionResource` | 6 | Audit log of credit purchases, deductions, and refunds |
| `WhatsAppUsageLogResource` | 7 | Real-time logs of delivered and outbound WhatsApp messages |

### WhatsAppDashboard

Displays:
- Credit balance (total, remaining, usage percentage)
- Monthly usage stats (messages sent, delivered, failed, delivery rate)
- Recent messages

Loads data from `WhatsAppAccount`, `WhatsAppMessageCredit`, and `WhatsAppUsageLog` scoped to the current gym tenant.

### WhatsAppSetup

A multi-step wizard page with traits delegated to:
- `HasFormInitialization` — form state setup
- `HasSetupState` — view state management (setup required / already configured)
- `SetupActions` — connection actions (connect WABA, connect Twilio, etc.)

Supports two provider paths:
- **Meta WABA**: Manual WABA connection (enter WABA ID, phone number, access token) or embedded signup (OAuth flow)
- **Twilio**: Create sub-account → search/purchase numbers or BYOD (bring your own device) number verification

The `getProvider()` method lazily instantiates the correct provider based on `selectedProvider`, caching the instance.

### PurchaseCredits

- Lists active `WhatsAppPackage` records
- Integrates with Razorpay for payment
- Handles phone number surcharges via `WhatsAppBillingService`
- Supports "welcome credits" (50 free credits for new accounts)
- Uses `Cache::lock()` to prevent concurrent payment attempts
- Dispatches `open-razorpay` event for frontend payment modal
- Handles payment verification via `handle-payment-verify` Livewire event

### Broadcast

- Allows selecting approved templates, setting audience filters (membership type, status, expiration window, marketing opt-ins).
- Calculates upfront credit requirements.
- Dispatches queued chunks via `SendBroadcastJob` and displays live progress via Bus batch tracking.

### WhatsAppTemplateResource

- Full CRUD with Create, Edit, List pages
- **Default Template Selection**: Pre-built templates auto-fill name, body, category, and variable placeholders
- **Category Guidance**: Shows contextual guidance for "utility" vs "marketing" categories (Meta classification rules)
- **Live Preview**: WhatsApp-style bubble preview with sample values
- **Navigation Badge**: Cached count of templates for the current tenant
- **Tenant Scoping**: In sandbox mode, shows templates with null gym_id; in production, scoped to tenant


## Credit System

### Credit Balance (`WhatsAppMessageCredit`)

- `total_credits` — total purchased
- `remaining_credits` — currently available
- `welcome_credits_claimed` — whether 50 free credits have been claimed
- `getUsagePercentage()` — used / total * 100
- `canSendMessage()` — remaining > 0
- `hasWelcomeCredits()` — welcome credits not yet claimed
- `getWelcomeCreditsRemaining()` — 50 minus used welcome credits

### Welcome Credits

New accounts can claim 50 free credits via `claimWelcomeCredits()`. This is a one-time offer tracked by `welcome_credits_claimed`. The claim action redirects to the dashboard after success.

### Credit Deduction

`WhatsAppCreditService::checkAndDeduct()`:
1. If `skipDeduction` is true (broadcast mode), return immediately
2. Check `canSendMessage()` — throw if insufficient
3. If welcome credits available and sufficient, use welcome credits first
4. Otherwise, deduct from purchased credits

### Phone Number Surcharge

`WhatsAppBillingService::getTwilioNumberSurcharge()` checks if the gym's Twilio phone number incurs a surcharge (for non-US numbers). If eligible, the surcharge is added to package purchase amounts.

## Billing

### Packages (`WhatsAppPackage`)

- `name` — package display name
- `message_count` — number of messages included
- `selling_price` — price in INR
- `active()` — scope for active packages
- `ordered()` — sorted by display order

### Purchase Flow

1. User selects a package → `selectPackage()` acquires a Cache lock
2. `initiatePayment()` creates a Razorpay order via `RazorpayService`
3. Frontend opens Razorpay modal via `open-razorpay` event
4. After payment, `handle-payment-verify` event triggers verification
5. `WhatsAppBillingService::purchasePackage()` adds credits to the gym's balance
6. If phone number surcharge applies, `last_number_cost_charged_at` is updated

## Templates

### Template Model (`WhatsAppTemplate`)

- `name` — internal template name
- `display_name` — user-facing name
- `body` — template content with `{{1}}`, `{{2}}` placeholders
- `category` — `utility` or `marketing`
- `variable_placeholder` — JSON describing variable types/labels
- `status` — approval status
- `gym_id` — nullable (null = system/sandbox template)

### Variable Resolution

`WhatsAppVariableResolver` resolves template variables to actual values:
- `{{1}}` → member name
- `{{2}}` → gym name
- `{{3}}` → dates, amounts, plan names, etc.

Resolution is scoped to the current gym tenant.

### Default Templates

`WhatsAppTemplateResource::getDefaultTemplatesForForm()` provides pre-built templates that auto-fill the form on create. Templates cover common scenarios: membership reminders, payment confirmations, class schedules, promotional offers.

## Usage Logging

Every message sent is logged to `WhatsAppUsageLog`:
- `recipient` — phone number
- `template` — which template was used
- `twilio_message_sid` — message tracking ID (column named for backward compat)
- `credits_used` — typically 1
- `twilio_cost` — provider cost
- `status` — sent / delivered / failed / undelivered
- `sent_at` / `delivered_at` — timestamps

Monthly stats are computed by aggregating logs for the current month.

## Broadcast System

Broadcasts allow sending a template message to multiple members at once.

- `WhatsAppBroadcastService::dispatchBroadcast()` queues a `SendBroadcastJob`
- Recipients are stored in `WhatsAppBroadcastRecipient` with individual status tracking
- Credits are deducted upfront at the orchestration layer (skip-deduction mode in `WhatsAppCreditService`)
- Each recipient tracks: pending → sending → sent/delivered/failed
- Broadcast status: draft → scheduled → sending → completed/failed/cancelled

## Key Design Decisions

See:
- [[ADR-005-whatsapp-provider-abstraction]] — Why two providers and a factory pattern
- [[ADR-006-whatsapp-broadcast-architecture]] — Why broadcast credits are deducted upfront

## Known Limitations

1. **Template approval latency** — Meta template approval can take days to weeks, creating friction for gym owners
2. **Credit model complexity** — Phone number surcharges, welcome credits, and package pricing create multiple code paths
3. **Broadcast scalability** — Current implementation dispatches a single job; no queue partitioning for very large recipient lists
4. **No delivery webhooks** — Usage log statuses are set at send time; real-time delivery confirmation from Meta/Twilio callbacks is not integrated
5. **Provider feature parity** — Not all features are available on both providers (e.g., embedded signup is Meta-only)
