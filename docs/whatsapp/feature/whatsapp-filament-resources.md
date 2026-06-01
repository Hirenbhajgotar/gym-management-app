---
feature: WhatsApp Filament Resources
status: active
last_updated: 2026-05-28
related_docs:
  - whatsapp-overview.md
  - whatsapp-usage-logs-fields.md
  - whatsapp-credit-transactions-fields.md
  - whatsapp-accounts-fields.md
  - whatsapp-packages-fields.md
  - whatsapp-templates-fields.md
---

# WhatsApp Filament Resources

## Overview

The WhatsApp feature includes Filament Resources across both Admin and SuperAdmin panels for managing templates, usage logs, credit transactions, packages, and accounts.

---

## Admin Panel Resources

### WhatsAppUsageLogResource

**File:** `app/Filament/Admin/Resources/WhatsAppUsageLogResource.php`
**Model:** `WhatsAppUsageLog`
**Status:** Disabled (`canAccess()` returns `false`, `shouldRegisterNavigation()` returns `false`)

**Table columns:**

| Column | Type | Notes |
|---|---|---|
| `sent_at` | DateTime | Sortable, placeholder "Not sent" |
| `recipient` | Text | Searchable |
| `template.display_name` | Text | Limit 30 chars |
| `status` | Badge | From `WhatsAppUsageLogStatusEnum` |
| `credits_used` | Numeric | — |
| `twilio_cost` | Money (USD) | Toggleable |
| `delivered_at` | DateTime | Toggleable |
| `error_message` | Text | Limit 40, tooltip full message, toggleable, hidden by default |
| `twilio_message_sid` | Text | Toggleable, hidden by default, copyable |

**Filters:**
- Status select filter (from `WhatsAppUsageLogStatusEnum`)
- Date range filter on `sent_at`
- Has Error ternary filter

**Actions:** View only

**Scoping:** All queries scoped to `Filament::getTenant()`

### WhatsAppTransactionResource

**File:** `app/Filament/Admin/Resources/WhatsAppTransactionResource.php`
**Model:** `WhatsAppCreditTransaction`

**Table columns:**

| Column | Type | Notes |
|---|---|---|
| `created_at` | DateTime | Sortable |
| `type` | Badge | From `WhatsAppCreditTransactionEnum` |
| `credits` | Numeric | Prefixed +/-, colored green (credit) / red (debit) |
| `balance_before` | Numeric | Toggleable |
| `balance_after` | Numeric | Bold |
| `amount` | Money (INR) | Toggleable |
| `description` | Text | Limit 50, tooltip |
| `payment_reference` | Text | Toggleable, hidden by default |
| `package.name` | Text | Toggleable, hidden by default |

**Filters:**
- Transaction type select filter (from `WhatsAppCreditTransactionEnum`)
- Date range filter on `created_at`

**Infolist:** Read-only detail view with 4 sections:
1. Transaction Details (date, type badge)
2. Credit Information (credits +/-, balance before/after)
3. Payment Details (amount, reference, package)
4. Description

**Actions:** View only (read-only resource)

**Scoping:** All queries scoped to `Filament::getTenant()`

---

## SuperAdmin Panel Resources

### WhatsAppAccountResource

**File:** `app/Filament/SuperAdmin/Resources/WhatsAppAccountResource.php`
**Model:** `WhatsAppAccount`

**Navigation:** WhatsApp group, sort 11, badge shows total account count (SWR cached 30m-1h)

**Table columns:**

| Column | Type | Notes |
|---|---|---|
| `gym.name` | Text | Searchable, sortable |
| `business_name` | Text | Searchable, toggleable |
| `twilio_account_sid` | Text | Copyable, limit 20, toggleable |
| `status` | Badge | From `WhatsAppAccountStatusEnum` |
| `verification_status` | Badge | From `WhatsAppAccountVerificationStatusEnum` |
| `phoneNumber.phone_number` | Text | Toggleable |
| `created_at` | DateTime | Toggleable, hidden by default |

**Filters:** Status and verification status select filters

**Actions:**
- View — Detail view
- **Adjust Credits** — Custom action with form:
  - `amount` (numeric, required) — Positive to add, negative to deduct
  - `reason` (textarea, required, max 500)
  - Calls `WhatsAppBillingService::adjustCredits()`

**Infolist:** Read-only view with 3 sections:
1. Account Details (gym, business info, SID, WABA ID, status, verification, dates)
2. Phone Numbers (repeatable with number, display name, status)
3. Templates (repeatable with name, category, status)

**Permissions:** Cannot create or delete accounts

### WhatsAppPackageResource

**File:** `app/Filament/SuperAdmin/Resources/WhatsAppPackageResource.php`
**Model:** `WhatsAppPackage`

**Navigation:** WhatsApp group, sort 10, badge shows active package count (SWR cached 10m-1h)

**Constants:**
- `MARGIN_PERCENTAGE = 0.010` (1%)
- `TWILIO_COST_PER_MESSAGE = 1.53`
- `TWILIO_NUMBER_COST = 136.41`

**Form sections:**

1. **Package Details** — name (unique), message_count (live auto-pricing), description
2. **Pricing** — selling_price (auto-calculated from cost + margin), twilio_number_cost, twilio_msg_cost_per_unit (default ₹0.15), validity_days (default 365)
3. **Display Settings** — sort_order, is_active, is_popular, includes_number (triggers price recalculation)
4. **Features List** — TagsInput for feature descriptions

**Auto-pricing logic:**
```
baseCost = (message_count × twilio_msg_cost_per_unit) + (includes_number ? twilio_number_cost : 0)
margin = message_count × MARGIN_PERCENTAGE
selling_price = baseCost + margin
```

**Table columns:**

| Column | Type | Notes |
|---|---|---|
| `name` | Text | Searchable, bold |
| `message_count` | Numeric | Label "Messages" |
| `selling_price` | Money (INR) | — |
| `your_profit` | Money (INR) | Computed column |
| `margin_percentage` | Numeric | 2 decimals, % suffix |
| `is_popular` | Icon (boolean) | — |
| `is_active` | Toggle | Inline toggle |
| `sort_order` | Numeric | Toggleable, hidden |
| `created_at` | DateTime | Toggleable, hidden |

**Filters:** Active status, popular ternary filters

**Actions:** Edit, Delete (with bulk delete)

### WhatsAppTemplateResource (SuperAdmin)

**File:** `app/Filament/SuperAdmin/Resources/WhatsAppTemplateResource.php`
**Model:** `WhatsAppTemplate`

**Navigation:** WhatsApp group, sort 4, badge shows global template count (SWR cached 30m-1h)

**Query scoping:** Only shows templates where `gym_id IS NULL AND is_default = 1` (global templates)

**Form sections:**

1. **Template Details** — name (regex: `^[a-z][a-z0-9_]*$`, unique), display_name, category (utility/marketing)
2. **Template Body** — body textarea (max 1024, live debounce, character count, variable detection), variable placeholder guide, dynamic variable placeholder fields, WhatsApp-style message preview
3. **Status** — Collapsed, default "approved"

**Variable placeholder fields:** Dynamic fields auto-generated from detected `{{N}}` variables in body. Each has:
- Variable reference (readonly)
- Placeholder name (e.g., "Member Name")
- Example value
- Type selector (text, number, date, currency)

**Table columns:**

| Column | Type | Notes |
|---|---|---|
| `display_name` | Text | Searchable, bold, shows `name` as description |
| `category` | Badge | Colored: success (utility), warning (marketing) |
| `status` | Badge | From `WhatsAppTemplateStatusEnum` |
| `variable_count` | Numeric | Center-aligned |
| `body` | Text | Limit 50, tooltip full body |
| `created_at` | DateTime | Toggleable, hidden |

**Filters:** Status and category select filters

**Actions:**
- **Push to All Gyms** — Confirmation modal, dispatches `PushWhatsAppTemplateToGymJob`
- Edit
- Delete

**Bulk actions:**
- Push Selected to All Gyms — Dispatches `PushWhatsAppTemplateToGymJob` for each selected template
- Delete bulk

---

## Resource Comparison

| Resource | Panel | CRUD | Scoping | Badge Cache |
|---|---|---|---|---|
| WhatsAppUsageLogResource | Admin | Read-only | Tenant | N/A (disabled) |
| WhatsAppTransactionResource | Admin | Read-only | Tenant | N/A |
| WhatsAppAccountResource | SuperAdmin | View + Adjust Credits | Global | SWR 30m-1h |
| WhatsAppPackageResource | SuperAdmin | Full CRUD | Global | SWR 10m-1h |
| WhatsAppTemplateResource | SuperAdmin | Full CRUD + Push | Global (null gym_id) | SWR 30m-1h |
