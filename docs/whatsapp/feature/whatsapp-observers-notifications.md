---
feature: WhatsApp Observers & Notifications
status: active
last_updated: 2026-05-28
related_docs:
  - whatsapp-overview.md
  - whatsapp-accounts-fields.md
  - whatsapp-templates-fields.md
  - whatsapp-packages-fields.md
---

# WhatsApp Observers & Notifications

## Overview

The WhatsApp feature uses three Eloquent Observers and four Notification classes to handle model lifecycle events and status change alerts. Observers manage cache invalidation; notifications handle both database (Filament) and email delivery.

---

## Observers

### WhatsAppTemplateObserver

**File:** `app/Observers/WhatsAppTemplateObserver.php`

Listens to all `WhatsAppTemplate` model events and invalidates navigation badge caches.

| Event | Action |
|---|---|
| `created` | Clears `NAVIGATION_BADGE_CACHE_KEY` (admin) and `SUPER_ADMIN_NAVIGATION_BADGE_CACHE_KEY` |
| `updated` | Same as created |
| `deleted` | Same as created |
| `restored` | Same as created |
| `forceDeleted` | Same as created |

Cache keys use the model constants `WhatsAppTemplate::NAVIGATION_BADGE_CACHE_KEY` (scoped to `gym_id`) and `WhatsAppTemplate::SUPER_ADMIN_NAVIGATION_BADGE_CACHE_KEY` (global).

### WhatsAppAccountObserver

**File:** `app/Observers/WhatsAppAccountObserver.php`

Listens to all `WhatsAppAccount` model events and performs two cache operations:

| Event | Actions |
|---|---|
| `created` | 1. Clears navigation badge cache via `ParserHelper::clearCache()`<br>2. Invalidates `WhatsAppProviderFactory` cache for the gym |
| `updated` | Same as created |
| `deleted` | Same as created |
| `restored` | Same as created |
| `forceDeleted` | Same as created |

The factory cache invalidation (`WhatsAppProviderFactory::invalidateProviderCache()`) ensures that if a gym changes its `provider_type`, the next API call uses the new provider.

### WhatsAppPackageObserver

**File:** `app/Observers/WhatsAppPackageObserver.php`

Listens to all `WhatsAppPackage` model events and invalidates the navigation badge cache via `ParserHelper::clearCache()`.

| Event | Action |
|---|---|
| `created` | Clears `WhatsAppPackage::NAVIGATION_BADGE_CACHE_KEY` |
| `updated` | Same as created |
| `deleted` | Same as created |
| `restored` | Same as created |
| `forceDeleted` | Same as created |

---

## Notifications

### WhatsAppTemplateStatusChangedNotification

**File:** `app/Notifications/WhatsAppTemplateStatusChangedNotification.php`
**Implements:** `ShouldQueue`
**Channels:** `mail`, `database`

Sent when a template's status changes to `PENDING`, `DISABLED`, or other non-terminal states via webhook.

**Constructor parameters:**
- `WhatsAppTemplate $template`
- `WhatsAppTemplateStatusEnum $newStatus`
- `string $oldStatus`

**Email content:**
- `PENDING` — Subject includes "⏳ Pending Review"; body explains Meta review process
- `DISABLED` — Subject includes "🚫 Disabled"; body explains Meta disabled the template
- Other statuses — Generic status change notification

**Database notification:** Filament notification with appropriate severity (info for pending, danger for disabled, warning for others) and a "View Template" action link.

### WhatsAppTemplateApprovedNotification

**File:** `app/Notifications/WhatsAppTemplateApprovedNotification.php`
**Implements:** `ShouldQueue`
**Channels:** `mail`, `database`

Sent when a template is approved by Meta.

**Constructor parameters:**
- `WhatsAppTemplate $template`

**Email content:** Subject "✅ Approved", congratulatory message, template details (name, category), action link to view template. Salutation: "Happy messaging! 🎉"

**Database notification:** Success-level Filament notification with check-circle icon and "View Template" action.

### WhatsAppTemplateRejectedNotification

**File:** `app/Notifications/WhatsAppTemplateRejectedNotification.php`
**Implements:** `ShouldQueue`
**Channels:** `mail`, `database`

Sent when a template is rejected by Meta.

**Constructor parameters:**
- `WhatsAppTemplate $template`

**Email content:** Subject "❌ Rejected", includes rejection reason from `$template->rejection_reason`. Provides actionable fix steps:
1. Review rejection reason
2. Utility templates: Remove emojis, promotional language, CTAs
3. Marketing templates: Ensure compliance with Meta commerce policy
4. Edit and resubmit

**Database notification:** Danger-level Filament notification with rejection reason in the body and "View Template" action.

### WhatsAppChannel

**File:** `app/Notifications/WhatsAppChannel.php`

Custom notification channel for sending WhatsApp messages. Implements `HasTenantAccess` trait.

**Resolution flow:**
1. Calls `$notification->toWhatsApp($notifiable)` to get the message payload
2. Validates message format (requires `to` + either `body` or `template_sid`)
3. Resolves `gym_id` from:
   - `HasGymContext` interface on the notification
   - Notifiable's `gym_id` property or `gym` relationship
   - Filament tenant context
   - User's gyms (falls back if only one gym)
4. Creates provider via `WhatsAppProviderFactory::create(gymId, isSystemMessage)`
5. Sends either template message (`sendTemplateMessage`) or plain text (`sendMessage`)
6. Logs errors but does not throw exceptions

**Key behavior:**
- `$isSystemMessage` flag from notification determines if Twilio (system) or gym-specific provider is used
- No return value for fire-and-forget notifications
- Returns provider result array for tracking

---

## Integration Points

| Component | Depends On |
|---|---|
| WhatsAppTemplateObserver | `WhatsAppTemplate::NAVIGATION_BADGE_CACHE_KEY` |
| WhatsAppAccountObserver | `WhatsAppProviderFactory::invalidateProviderCache()` |
| WhatsAppPackageObserver | `WhatsAppPackage::NAVIGATION_BADGE_CACHE_KEY` |
| All notifications | `WhatsAppTemplate->gym` relationship for URL generation |
| WhatsAppChannel | `WhatsAppProviderFactory::create()` |
| WhatsAppChannel | `HasTenantAccess` trait for gym resolution |
| Template status notifications | Webhook controller → `ProcessMetaTemplateWebhook` job |

---

## Data Flow

```
Meta Webhook arrives
    │
    ▼
WhatsAppTemplateWebhookController
    │
    ▼
ProcessMetaTemplateWebhook (job)
    │
    ├── Updates template status via WhatsAppTemplateStatusMapper
    │
    └── Dispatches notification based on new status:
         │
         ├── APPROVED → WhatsAppTemplateApprovedNotification
         ├── REJECTED → WhatsAppTemplateRejectedNotification
         └── PENDING/DISABLED → WhatsAppTemplateStatusChangedNotification
              │
              ▼
         WhatsAppTemplateObserver clears badge cache
              │
              ▼
         Badge count updates in Filament sidebar
```
