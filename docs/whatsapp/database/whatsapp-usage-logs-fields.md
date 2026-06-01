---
table: whatsapp_usage_logs
feature: WhatsApp Integration
status: draft
last_updated: 2026-05-29
related_docs:
  - ../feature/whatsapp-overview.md
---

# whatsapp_usage_logs

Records every WhatsApp message sent from the platform. Used for analytics, billing reconciliation, and usage tracking.

## Columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | no | auto | Primary key |
| `gym_id` | bigint | no | — | FK to `gyms.id` |
| `whatsapp_template_id` | bigint | yes | null | FK to `whatsapp_templates.id` (null for test messages) |
| `twilio_message_sid` | varchar | yes | null | Message tracking ID from provider (named for backward compat) |
| `recipient` | varchar | no | — | Recipient phone number |
| `credits_used` | int | yes | null | Credits consumed (typically 1) |
| `twilio_cost` | decimal(10,4) | yes | null | Provider cost for this message |
| `status` | varchar | yes | null | Message status enum (`WhatsAppUsageLogStatusEnum`) |
| `error_message` | varchar | yes | null | Error details if delivery failed |
| `sent_at` | timestamp | yes | null | When the message was sent |
| `delivered_at` | timestamp | yes | null | When the message was confirmed delivered |
| `created_at` | timestamp | yes | null | Creation timestamp |
| `updated_at` | timestamp | yes | null | Last update timestamp |

## Indexes

| Index | Columns | Type | Description |
|-------|---------|------|-------------|
| Primary | `id` | primary | Auto-increment |
| Index | `gym_id` | index | For gym-scoped queries |
| Index | `sent_at` | index | For date-range queries |

## Relationships

| Method | Type | Related Model | FK |
|--------|------|---------------|----|
| `gym()` | BelongsTo | `Gym` | `gym_id` |
| `template()` | BelongsTo | `WhatsAppTemplate` | `whatsapp_template_id` |

## Key Helper Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `isDelivered()` | bool | Status is `DELIVERED` |
| `isFailed()` | bool | Status is `FAILED` |
| `isSent()` | bool | Status is `SENT` |
| `markAsDelivered()` | void | Sets status to DELIVERED, delivered_at |
| `markAsFailed()` | void | Sets status to FAILED, error_message |

## Usage Log Status Flow

```
QUEUED → SENDING → SENT → DELIVERED
                 ↓
               FAILED / UNDELIVERED
```

## Migration History

- `2026_02_12_133639` — Initial creation
