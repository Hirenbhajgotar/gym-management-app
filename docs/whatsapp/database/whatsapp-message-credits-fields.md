---
table: whatsapp_message_credits
feature: WhatsApp Integration
status: draft
last_updated: 2026-05-29
related_docs:
  - ../feature/whatsapp-overview.md
---

# whatsapp_message_credits

Tracks the message credit balance for each gym. One record per gym.

## Columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | no | auto | Primary key |
| `gym_id` | bigint | no | — | FK to `gyms.id`. Unique constraint |
| `total_credits` | int | yes | 0 | Total credits purchased |
| `used_credits` | int | yes | 0 | Credits consumed |
| `remaining_credits` | int | yes | 0 | Credits available |
| `cost_per_message` | decimal(10,4) | yes | null | Cost per message (provider cost) |
| `selling_price_per_msg` | decimal(10,4) | yes | null | Selling price per message to gym |
| `welcome_credits_used` | int | yes | 0 | Number of welcome credits consumed |
| `welcome_credits_claimed` | bool | yes | false | Whether 50 free welcome credits were claimed |
| `is_low_credit_notified` | bool | yes | false | Whether low-credit notification was sent |
| `last_used_at` | timestamp | yes | null | When credits were last used |
| `created_at` | timestamp | yes | null | Creation timestamp |
| `updated_at` | timestamp | yes | null | Last update timestamp |

## Indexes

| Index | Columns | Type | Description |
|-------|---------|------|-------------|
| Primary | `id` | primary | Auto-increment |
| Unique | `gym_id` | unique | One credit record per gym |

## Relationships

| Method | Type | Related Model | FK |
|--------|------|---------------|----|
| `gym()` | BelongsTo | `Gym` | `gym_id` |

## Key Helper Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `canSendMessage(int)` | bool | `remaining_credits >= count` |
| `getWelcomeCreditsRemaining()` | int | 50 minus `welcome_credits_used` (if claimed) |
| `hasWelcomeCredits()` | bool | Welcome credits remaining > 0 |
| `useWelcomeCredit()` | bool | Increments welcome_credits_used, decrements remaining |
| `deductCredits(int)` | bool | Deducts from remaining, increments used |
| `addCredits(int)` | void | Adds to total and remaining |
| `claimWelcomeCredits()` | void | Sets welcome_credits_claimed = true, remaining = 50 |
| `getUsagePercentage()` | float | used / total * 100 |
| `getBalance()` | float | remaining * selling_price_per_msg |

## Welcome Credits

- Default 50 free credits per gym
- Configurable via `config('services.whatsapp.welcome_credits')`
- One-time claim tracked by `welcome_credits_claimed`
- Deducted first before purchased credits

## Migration History

- `2026_02_12_133635` — Initial creation
- `2026_02_26_100441` — Added `is_low_credit_notified`
