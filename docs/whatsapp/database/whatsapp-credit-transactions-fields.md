---
table: whatsapp_credit_transactions
feature: WhatsApp Integration
status: draft
last_updated: 2026-05-29
related_docs:
  - ../feature/whatsapp-overview.md
---

# whatsapp_credit_transactions

Audit log for all credit balance changes — purchases, usage, adjustments, refunds, expirations, and bonuses.

## Columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | no | auto | Primary key |
| `gym_id` | bigint | no | — | FK to `gyms.id` |
| `whatsapp_message_credit_id` | bigint | yes | null | FK to `whatsapp_message_credits.id` |
| `whatsapp_package_id` | bigint | yes | null | FK to `whatsapp_packages.id` (for purchases) |
| `type` | varchar | yes | null | Transaction type enum (`WhatsAppCreditTransactionEnum`) |
| `credits` | int | yes | null | Number of credits affected |
| `balance_before` | int | yes | null | Credit balance before this transaction |
| `balance_after` | int | yes | null | Credit balance after this transaction |
| `amount` | decimal(10,2) | yes | null | Payment amount in INR |
| `phone_number_surcharge` | decimal(10,2) | yes | null | Phone number surcharge amount |
| `payment_reference` | varchar | yes | null | Razorpay payment ID or reference |
| `description` | varchar | yes | null | Human-readable description |
| `admin_id` | int | yes | null | Admin user who made manual adjustments |
| `created_at` | timestamp | yes | null | Creation timestamp |
| `updated_at` | timestamp | yes | null | Last update timestamp |

## Indexes

| Index | Columns | Type | Description |
|-------|---------|------|-------------|
| Primary | `id` | primary | Auto-increment |
| Index | `gym_id` | index | For gym-scoped queries |

## Relationships

| Method | Type | Related Model | FK |
|--------|------|---------------|----|
| `gym()` | BelongsTo | `Gym` | `gym_id` |
| `credit()` | BelongsTo | `WhatsAppMessageCredit` | `whatsapp_message_credit_id` |
| `package()` | BelongsTo | `WhatsAppPackage` | `whatsapp_package_id` |

## Transaction Types

| Type | Color | Description |
|------|-------|-------------|
| `purchase` | success | Credits purchased via Razorpay |
| `usage` | danger | Credits consumed by message sends |
| `adjustment` | warning | Manual credit adjustment by admin |
| `refund` | info | Credits refunded (e.g., failed payment) |
| `expired` | gray | Credits that expired |
| `bonus` | primary | Bonus credits (e.g., welcome credits) |

## Migration History

- `2026_02_12_133640` — Initial creation
- `2026_05_22_220632` — Added `phone_number_surcharge`
