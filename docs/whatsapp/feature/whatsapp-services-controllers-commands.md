---
feature: WhatsApp Services, Controllers & Commands
status: active
last_updated: 2026-05-28
related_docs:
  - whatsapp-overview.md
  - whatsapp-templates-fields.md
  - whatsapp-accounts-fields.md
  - whatsapp-observers-notifications.md
---

# WhatsApp Services, Controllers & Commands

## Overview

The WhatsApp feature has several supporting services, API controllers, and an Artisan command that extend beyond the core credit and broadcast systems documented in the main overview.

---

## Services

### WhatsAppVariableResolver

**File:** `app/Services/WhatsAppVariableResolver.php`

Resolves template variable placeholders to actual data from members, gyms, plans, trainers, and payments.

**Available data sources:**

| Group | Key | Example |
|---|---|---|
| Member | `member.name` | John Doe |
| Member | `member.email` | john@example.com |
| Member | `member.phone` | +919876543210 |
| Member | `member.dob` | May 15, 1999 |
| Member | `member.membership_start` | Jan 1, 2026 |
| Member | `member.membership_end` | Jul 1, 2026 |
| Gym | `gym.name` | FitZone Gym |
| Plan | `plan.name` | Premium Monthly |
| Plan | `plan.price` | 1,500 |
| Plan | `plan.duration` | 3 |
| Trainer | `trainer.name` | Mike Coach |
| Trainer | `trainer.phone` | +919876543211 |
| Trainer | `trainer.specialization` | Strength Training |
| Payment | `payment.formatted_amount` | Rs.1,500.00 |
| Payment | `payment.amount` | 1500.00 |
| Payment | `payment.payment_date` | Jan 15, 2026 |
| Payment | `payment.payment_method` | Cash |
| Payment | `payment.invoice_number` | INV-001 |
| Other | `custom` | (user-provided at send time) |

**Key methods:**
- `getAvailableDataSources()` — Returns all source keys with labels and examples
- `getGroupedSelectOptions()` — Returns options grouped for Filament Select dropdowns
- `resolve(template, context)` — Resolves all variables to actual values using member data
- `hasCustomVariables(template)` — Checks if template has any `custom` source variables
- `getCustomVariables(template)` — Returns only variables requiring manual input
- `getSourcesRequiringMember()` — Lists all source keys except `custom`

**Resolution logic:**
- `custom` sources use the `context['custom'][$key]` value
- All other sources traverse the `GymMember` model relationships (gym, membershipPlan, trainer, latestPayment)
- Dates are formatted as `M j, Y`
- Falls back to the placeholder's example value if source data is unavailable

### WhatsAppTemplateService

**File:** `app/Services/WhatsAppTemplateService.php`

Handles template CRUD, default template creation, and (deprecated) Twilio template operations.

**Active methods:**
- `createDefaultTemplates(gymId)` — Creates default templates for a gym from `WhatsAppTemplate::getDefaults()`
- `createTemplate(gymId, CreateTemplateData)` — Creates template with auto-extracted variables from body
- `updateTemplate(template, UpdateTemplateData)` — Updates template, re-extracts variables if body changed
- `softDeleteTemplate(template)` — Soft deletes template
- `restoreTemplate(template)` — Restores soft-deleted template
- `getApprovedTemplates(gymId)` — Returns approved, active templates sorted by display name
- `validateVariables(template, variables)` — Validates variable count matches template

**Deprecated methods (v1.x):**
- `submitForVerification(template)` — Submits to Twilio Content API for approval (use `TwilioTemplateService` instead)
- `syncTemplateStatus(template)` — Fetches approval status from Twilio API
- `deleteFromTwilio(template)` — Deletes template from Twilio Content API
- `parseVariables(body, variables)` — Delegates to `TemplateVariableHelper`
- `syncTemplatesFromTwilio()` — Syncs all templates from Twilio master account to local DB

### WhatsAppTemplateManager

**File:** `app/Services/WhatsAppTemplateManager.php`

Manages templates for a specific tenant using the Twilio Content API. Supports sandbox mode.

**Constructor:** `__construct(string $tenantId)` — Sets tenant context and sandbox config.

**Methods:**
- `fetchTemplates()` — Fetches approved templates from Twilio Content API. Returns `TemplateFetchResult` DTO.
- `getTemplateByName(templateName)` — Fetches a specific template by friendly name (cached 24h)
- `syncTemplatesToDatabase()` — Syncs Twilio templates to `whatsapp_templates` table (upsert)
- `getTemplate(templateName)` — Returns `WhatsAppTemplate` from DB by name (approved + active)
- `clearCache(templateName?)` — Clears template cache for specific template or all

**Sandbox behavior:** When `config('services.twilio.use_sandbox')` is true, uses master SID/token instead of the gym's subaccount credentials.

### WhatsAppTemplateStatusMapper

**File:** `app/Services/Support/WhatsAppTemplateStatusMapper.php`

Maps provider-specific status strings to `WhatsAppTemplateStatusEnum`.

| Provider Status | Mapped To |
|---|---|
| `APPROVED` | `WhatsAppTemplateStatusEnum::APPROVED` |
| `REJECTED` | `WhatsAppTemplateStatusEnum::REJECTED` |
| `FLAGGED` | `WhatsAppTemplateStatusEnum::REJECTED` |
| `PENDING` | `WhatsAppTemplateStatusEnum::PENDING` |
| `PENDING_DELETION` | `WhatsAppTemplateStatusEnum::PENDING` |
| `UNAPPROVED` | `WhatsAppTemplateStatusEnum::PENDING` |
| `DISABLED` | `WhatsAppTemplateStatusEnum::DISABLED` |
| `PAUSED` | `WhatsAppTemplateStatusEnum::DISABLED` |
| `UNSUBMITTED` | `WhatsAppTemplateStatusEnum::UNSUBMITTED` |

Used by both `ProcessMetaTemplateWebhook` and `ProcessTwilioTemplateWebhook` jobs.

### WhatsAppLeadProcessor

**File:** `app/Services/WhatsAppLeadProcessor.php`

Extends `SocialMediaLeadProcessor` to process WhatsApp Business API lead webhooks.

**Key methods:**
- `processLead(data)` — Main entry point. Extracts lead data, resolves tenant config, creates `Lead` record, sends acknowledgment.
- `verifyWebhook(data, headers)` — Verifies webhook signature using HMAC-SHA256 with `app_secret`
- `extractLeadData(data)` — Parses WhatsApp webhook payload (Business Account format or batch format)
- `extractContactInfo(message)` — Extracts name, email, phone from message text using regex patterns
- `sendAcknowledgmentMessage(lead, config)` — Sends template message acknowledgment if `auto_reply_enabled`

**Webhook verification:**
- For setup verification: checks `hub_verify_token` against configured token
- For message webhooks: verifies `x-hub-signature-256` header using HMAC-SHA256

---

## API Controllers

### WhatsAppTemplateWebhookController

**File:** `app/Http/Controllers/Api/WhatsAppTemplateWebhookController.php`
**Route:** Handles Meta WhatsApp Business API template status webhooks

**Endpoints:**
- `GET` — Webhook verification (`hub_mode`, `hub_verify_token`, `hub_challenge`)
- `POST` — Template status update events

**POST flow:**
1. Verifies `X-Hub-Signature-256` header using HMAC-SHA256 with `services.meta.app_secret`
2. Parses payload for `message_template_status_update` field changes
3. For each entry: extracts `wabaId`, `metaTemplateId`, `event`, `reason`, `previousEvent`
4. Dispatches `ProcessMetaTemplateWebhook` job for async processing

**Security:** Uses `hash_equals()` to prevent timing attacks on signature verification.

### WhatsAppLeadController

**File:** `app/Http/Controllers/Api/WhatsAppLeadController.php`

Minimal controller that injects `WhatsAppLeadProcessor`. Inherits parent `Controller` methods.

---

## Artisan Commands

### WhatsAppMigrateToIndividualWaba

**File:** `app/Console/Commands/WhatsAppMigrateToIndividualWaba.php`
**Signature:** `whatsapp:migrate-to-individual-waba`

Migrates existing tenants from a shared WABA (WhatsApp Business Account) to individual WABA configurations.

**Options:**
| Option | Description |
|---|---|
| `--dry-run` | Show what would be migrated without making changes |
| `--gym=` | Migrate a specific gym by ID |

**Flow:**
1. Checks `config('services.twilio.use_individual_waba')` — fails if not enabled
2. If `--gym` specified: creates migration for that gym, optionally starts it
3. Otherwise: finds all gyms needing migration, creates migrations in bulk, optionally starts all

**Requires:** `WabaMigrationService` for actual migration logic.

---

## Integration Map

```
Webhook (Meta)
    │
    ▼
WhatsAppTemplateWebhookController
    │
    ▼
ProcessMetaTemplateWebhook (job)
    │
    ├── Uses WhatsAppTemplateStatusMapper
    ├── Updates WhatsAppTemplate.status
    └── Dispatches notification

WhatsAppLeadController
    │
    ▼
WhatsAppLeadProcessor
    │
    ├── Extends SocialMediaLeadProcessor
    ├── Creates Lead record
    └── Sends acknowledgment via WhatsApp API

WhatsAppMigrateToIndividualWaba (CLI)
    │
    ▼
WabaMigrationService
    │
    └── Creates/starts migration records

WhatsAppVariableResolver
    │
    ├── Used by WhatsAppBroadcastService (per-recipient resolution)
    └── Uses GymMember relationships for data
```
