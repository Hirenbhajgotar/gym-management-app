---
table: whatsapp_templates
feature: WhatsApp Integration
status: active
last_updated: 2026-09-09
related_docs:
  - ../feature/whatsapp-overview.md
---

# whatsapp_templates

Stores WhatsApp message templates for each gym. Templates must be submitted to Meta for approval before use. Supports soft deletes.

## Columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | no | auto | Primary key |
| `gym_id` | bigint | yes | null | FK to `gyms.id`. Null for system/sandbox templates |
| `name` | varchar | no | — | Internal template name (lowercase, underscores) |
| `display_name` | varchar | no | — | User-facing display name |
| `body` | varchar | yes | null | Template body text with `{{1}}`, `{{2}}` variable placeholders |
| `category` | varchar | yes | null | `utility` or `marketing` |
| `language` | varchar(20) | no | `'en_US'` | Template language/locale code (e.g., `en_US`, `es_ES`, `en`) |
| `variables` | json | yes | null | Array of variable definitions |
| `variable_count` | int | yes | null | Number of `{{N}}` variables detected in body |
| `variable_placeholder` | json | yes | null | JSON mapping variable numbers to types, labels, examples |
| `twilio_template_sid` | varchar | yes | null | Twilio template SID after submission |
| `meta_template_id` | varchar | yes | null | Meta template ID after approval |
| `status` | varchar | yes | null | Template status enum (`WhatsAppTemplateStatusEnum`) |
| `rejection_reason` | varchar | yes | null | Meta rejection reason text |
| `is_default` | bool | yes | false | Whether this is a system default template |
| `is_active` | bool | yes | true | Whether template appears in broadcast picker |
| `usage_count` | int | yes | null | Number of times this template has been sent |
| `last_used_at` | timestamp | yes | null | When the template was last used |
| `created_at` | timestamp | yes | null | Creation timestamp |
| `updated_at` | timestamp | yes | null | Last update timestamp |
| `deleted_at` | timestamp | yes | null | Soft delete timestamp |

## Indexes

| Index | Columns | Type | Description |
|-------|---------|------|-------------|
| Primary | `id` | primary | Auto-increment |
| Unique | `name`, `gym_id` | unique | Template name unique per gym |

## Relationships

| Method | Type | Related Model | FK |
|--------|------|---------------|----|
| `gym()` | BelongsTo | `Gym` | `gym_id` |

## Scopes

| Scope | Description |
|-------|-------------|
| `isDefault()` | Filter templates where `is_default = true` |
| `isActive()` | Filter templates where `is_active = true` |

## Key Helper Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `isApproved()` | bool | Status is `APPROVED` |
| `isPending()` | bool | Status is `PENDING` |
| `isDraft()` | bool | Status is `DRAFT` |
| `isRejected()` | bool | Status is `REJECTED` |
| `canBeUsed()` | bool | Approved AND active |
| `markAsApproved()` | void | Sets status, twilio_template_sid, meta_template_id |
| `markAsRejected()` | void | Sets status and rejection_reason |
| `incrementUsage()` | void | Increments usage_count, sets last_used_at |
| `validateVariables()` | bool | Checks if provided variables satisfy template requirements |
| `getDefaults()` | Collection | Gets system default templates (gym_id = null) |

## Template Status Flow

```
DRAFT → (submit) → PENDING → (Meta approval) → APPROVED
                                        ↓
                                    REJECTED → (edit) → DRAFT → (resubmit) → PENDING
```

## Migration History

- `2025_10_02_082101` — Initial creation (older version)
- `2026_02_12_133628` — Re-created with new schema
- `2026_02_18_113630` — Added `variable_placeholder`
- `2026_02_25_064553` — Added `deleted_at` (soft deletes)
- `2026_03_31_175200` — Added unique constraint on `name` + `gym_id`
- `2026_09_08_000001` — Added `language` column (default `en_US`)
