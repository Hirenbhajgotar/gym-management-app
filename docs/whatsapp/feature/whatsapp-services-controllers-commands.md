---
feature: WhatsApp Services, Controllers & Commands
status: active
last_updated: 2026-09-10
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

### TemplateFieldMapper

**File:** `app/Services/WhatsApp/TemplateFieldMapper.php`

Normalizes raw template payloads from different WhatsApp providers (Meta WABA and Twilio) into standardized template attributes for database storage and synchronization.

**Key methods:**
- `mapToTemplateFields(rawData, providerType)` — Normalizes provider-specific template structures into a unified dictionary containing `name`, `display_name`, `body`, `category`, `language`, `status`, `provider_sid`, `variables`, `variable_count`, and `variable_placeholder`.
- `sanitizeLanguage(lang, providerType)` — Static helper validating locale/language strings against ISO/BCP47 formats (`^[a-zA-Z]{2,3}(?:[_-][a-zA-Z0-9]{2,8})?$`). Returns fallback default (`MetaTemplateService::defaultTemplateLanguage()` / `en_US` for Meta or `TwilioTemplateService::TWILIO_DEFAULT_LANGUAGE` / `en` for Twilio) when null or invalid.

### WhatsAppTemplateService

**File:** `app/Services/WhatsAppTemplateService.php`

Handles template CRUD, default template creation, multi-language configuration, and (deprecated) Twilio template operations.

**Active methods:**
- `createDefaultTemplates(gymId)` — Creates default templates for a gym from `WhatsAppTemplate::getDefaults()`
- `createTemplate(gymId, CreateTemplateData)` — Creates template with configured or default language (`MetaTemplateService::defaultTemplateLanguage()`) and auto-extracted variables from body
- `updateTemplate(template, UpdateTemplateData)` — Updates template display name, body, category, or language (preserves existing language if omitted)
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

## Web & API Controllers

### EmbeddedSignupController

**File:** `app/Http/Controllers/WhatsApp/EmbeddedSignupController.php`
**Routes:**
- `GET /whatsapp/embedded-signup/redirect/{tenant}` (`whatsapp.embedded-signup.redirect`)
- `GET /whatsapp/embedded-signup/callback` (`whatsapp.embedded-signup.callback`)
- `POST /whatsapp/embedded-signup/manual-connect/{tenant}` (`whatsapp.embedded-signup.manual-connect`)

Handles Meta WhatsApp Business Account (WABA) Embedded Signup onboarding and manual WABA credentials connection:

**Endpoints & Flow:**
1. **Redirect (`redirect`)**:
   - Authorizes tenant gym and user gym association (`abort_unless($user->gyms->contains('id', $gym->id), 403)`).
   - Verifies Embedded Signup feature enablement.
   - Sets session keys `embedded_signup_tenant_id` and `embedded_signup_tenant_slug`.
   - Creates a `WabaSetupSession` (UUID state, 1-hour expiry).
   - Generates Meta OAuth URL and redirects the gym admin.
   - On tenant/config errors, redirects back to the wizard using slug: `?setup_step=whats-app-business`.

2. **OAuth Callback (`callback`)**:
   - Validates `code` and `state` parameters against session tenant IDs and `WabaSetupSession` status (prevents CSRF and cross-tenant state replay).
   - Exchanges code for access token via `MetaApiClient` and debugs token to obtain `waba_id`.
   - Provisions or updates `WhatsAppAccount` with WABA credentials within `DB::transaction` / `Cache::lock`.
   - Initiates phone number synchronization (`MetaWabaService::syncWabaPhoneNumbers`).
   - Dispatches `ImportWhatsAppTemplatesJob` in the background for automatic template synchronization.
   - Redirects to `?setup_step=whats-app-business` with success flash message.

3. **Manual Connect (`manualConnect`)**:
   - Validates manual input (`waba_id`, `manual_phone_number`, `access_token`) via `ManualConnectRequest`.
   - Verifies and connects WABA credentials using `MetaWabaService::connectManualWaba()`.
   - Dispatches background `ImportWhatsAppTemplatesJob` for template onboarding.
   - Redirects to `route('filament.admin.pages.whats-app-setup', ['tenant' => $tenant, 'setup_step' => 'whats-app-business'])` with `templates_import_pending` flash flag.

**Navigation Pattern:**
- Decoupled from numeric step indices: all redirects explicitly target the semantic step slug `setup_step=whats-app-business`.

---

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

### WhatsAppFailureClassifier

**File:** `app/Helpers/WhatsAppFailureClassifier.php`

Classifies delivery failures encountered during API interactions into actionable error categories:

**Capabilities:**
- `isConnectionException(Throwable $e)` — Identifies network dropouts, SSL timeouts, and curl connectivity errors (e.g., CURLE 6, 7, 28, 35, 55, 56)
- `isTransientServerError(int $statusCode)` — Checks for HTTP 408, 500, 502, 503, 504, 524
- `isRateLimitException(Throwable $e)` & `isRateLimitStatusCode(int $statusCode)` — Detects HTTP 429 throttling
- `isRateLimitMessage(string $message)` & `isConnectionMessage(string $message)` — Evaluates regex matches on error messages

---

## Jobs & Queues

### SendBroadcastJob

**File:** `app/Jobs/SendBroadcastJob.php`

Orchestrates the entire lifecycle of a broadcast campaign:
1. Deducts required credits upfront (`credits_deducted_upfront = true`).
2. Creates a Laravel Bus batch for real-time progress tracking.
3. Chunks the recipient audience into discrete `SendMessageChunkJob` instances.
4. On batch completion/cancellation, reconciles failed message counts and restores unspent credits via `WhatsAppCreditTransaction`.

### SendMessageChunkJob

**File:** `app/Jobs/SendMessageChunkJob.php`

Processes an isolated chunk of recipients:
1. Verifies member marketing opt-ins and archival status.
2. Resolves template variables per recipient using `WhatsAppVariableResolver`.
3. Calls the underlying messaging provider (`TwilioMessagingService` or `MetaWhatsAppProvider`).
4. Catches exceptions and uses `WhatsAppFailureClassifier` to tag recipient errors.
5. Updates individual recipient statuses (`SENT`, `DELIVERED`, `FAILED`).

---

## Artisan Commands

### ProcessScheduledBroadcastsCommand

**File:** `app/Console/Commands/ProcessScheduledBroadcastsCommand.php`
**Signature:** `whatsapp:process-scheduled-broadcasts`

Runs via the Laravel scheduler to pick up broadcasts whose `scheduled_at` timestamp has arrived and dispatches them to the queue.

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

WhatsAppBroadcastService / ProcessScheduledBroadcastsCommand
    │
    ▼
SendBroadcastJob (Batched)
    │
    ├── Upfront credit reservation
    ├── Dispatches SendMessageChunkJob instances
    │       ├── Uses WhatsAppVariableResolver
    │       ├── Invokes MessagingInterface (Twilio / Meta)
    │       └── Evaluates errors with WhatsAppFailureClassifier
    └── Reconciliation: Refunds failed credits automatically

WhatsAppMigrateToIndividualWaba (CLI)
    │
    ▼
WabaMigrationService
    │
    └── Creates/starts migration records
```

