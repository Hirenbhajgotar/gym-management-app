---
table: whatsapp_broadcast_recipients
feature: WhatsApp Integration
status: draft
last_updated: 2026-05-29
related_docs:
  - ../feature/whatsapp-overview.md
---

# whatsapp_broadcast_recipients

Tracks individual recipients within a broadcast campaign. One row per member per broadcast.

## Columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | no | auto | Primary key |
| `whatsapp_broadcast_id` | bigint | no | — | FK to `whatsapp_broadcasts.id` |
| `member_id` | bigint | no | — | FK to `gym_members.id` |
| `phone_number` | varchar | no | — | Recipient phone number |
| `member_name` | varchar | no | — | Recipient's name (denormalized for performance) |
| `status` | varchar | yes | null | Recipient status enum (`WhatsAppBroadcastRecipientStatusEnum`) |
| `twilio_message_sid` | varchar | yes | null | Message tracking ID from provider |
| `failure_reason` | varchar | yes | null | Error details if delivery failed |
| `sent_at` | timestamp | yes | null | When the message was sent |
| `delivered_at` | timestamp | yes | null | When the message was confirmed delivered |
| `created_at` | timestamp | yes | null | Creation timestamp |
| `updated_at` | timestamp | yes | null | Last update timestamp |

## Indexes

| Index | Columns | Type | Description |
|-------|---------|------|-------------|
| Primary | `id` | primary | Auto-increment |
| Index | `whatsapp_broadcast_id` | index | For broadcast-scoped queries |

## Relationships

| Method | Type | Related Model | FK |
|--------|------|---------------|----|
| `broadcast()` | BelongsTo | `WhatsAppBroadcast` | `whatsapp_broadcast_id` |
| `member()` | BelongsTo | `GymMember` | `member_id` |

## Key Helper Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `isPending()` | bool | Status is `PENDING` |
| `isSending()` | bool | Status is `SENDING` |
| `isSent()` | bool | Status is `SENT` |
| `isDelivered()` | bool | Status is `DELIVERED` |
| `isFailed()` | bool | Status is `FAILED` |
| `markAsSent()` | void | Sets status, twilio_message_sid, sent_at |
| `markAsDelivered()` | void | Sets status, delivered_at |
| `markAsFailed()` | void | Sets status, failure_reason |

## Recipient Status Flow

```
PENDING → SENDING → SENT → DELIVERED
                ↓
            FAILED
```

## Migration History

- `2026_02_12_133639` — Initial creation
