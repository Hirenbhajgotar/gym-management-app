---
table: whatsapp_broadcasts
feature: WhatsApp Integration
status: draft
last_updated: 2026-05-29
related_docs:
  - ../feature/whatsapp-overview.md
---

# whatsapp_broadcasts

Records WhatsApp broadcast campaigns. Each broadcast targets a filtered list of gym members with a specific template.

## Columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | no | auto | Primary key |
| `gym_id` | bigint | no | — | FK to `gyms.id` |
| `whatsapp_template_id` | bigint | no | — | FK to `whatsapp_templates.id` |
| `title` | varchar | no | — | Broadcast title (user-facing) |
| `description` | varchar | yes | null | Broadcast description |
| `target_filters` | json | yes | null | Filter criteria for member selection |
| `target_count` | int | no | 0 | Number of targeted recipients |
| `status` | varchar | yes | null | Broadcast status enum (`WhatsAppBroadcastStatusEnum`) |
| `batch_id` | varchar | yes | null | Laravel Bus batch ID for tracking progress |
| `scheduled_at` | timestamp | yes | null | When the broadcast is scheduled to start |
| `started_at` | timestamp | yes | null | When the broadcast actually started |
| `completed_at` | timestamp | yes | null | When the broadcast finished |
| `sent_count` | int | no | 0 | Number of successfully sent messages |
| `failed_count` | int | no | 0 | Number of failed messages |
| `credits_used` | int | no | 0 | Total credits consumed |
| `message_variables` | json | yes | null | JSON template variables for the broadcast |
| `created_at` | timestamp | yes | null | Creation timestamp |
| `updated_at` | timestamp | yes | null | Last update timestamp |

## Indexes

| Index | Columns | Type | Description |
|-------|---------|------|-------------|
| Primary | `id` | primary | Auto-increment |
| Index | `gym_id` | index | For gym-scoped queries |
| Index | `status` | index | For filtering by broadcast status |

## Relationships

| Method | Type | Related Model | FK |
|--------|------|---------------|----|
| `gym()` | BelongsTo | `Gym` | `gym_id` |
| `template()` | BelongsTo | `WhatsAppTemplate` | `whatsapp_template_id` |
| `recipients()` | HasMany | `WhatsAppBroadcastRecipient` | `whatsapp_broadcast_id` |

## Key Helper Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `isDraft()` | bool | Status is `DRAFT` |
| `isScheduled()` | bool | Status is `SCHEDULED` |
| `isSending()` | bool | Status is `SENDING` |
| `isCompleted()` | bool | Status is `COMPLETED` |
| `markAsSending()` | void | Sets status to SENDING, started_at |
| `markAsCompleted()` | void | Sets status to COMPLETED, completed_at |
| `markAsCancelled()` | void | Sets status to CANCELLED |
| `getDeliveryRate()` | float | sent / (sent + failed) * 100 |
| `getProgressAttribute()` | array | Returns batch progress (total, pending, failed, progress %) |

## Broadcast Status Flow

```
DRAFT → (dispatch) → SENDING → (all sent) → COMPLETED
                          ↓
                      CANCELLED / FAILED
```

## Migration History

- `2026_02_12_133638` — Initial creation
- `2026_05_28_165130` — Added `batch_id`
- `2026_05_29_000001` — Added index on `status`
