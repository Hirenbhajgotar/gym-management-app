---
table: whatsapp_packages
feature: WhatsApp Integration
status: draft
last_updated: 2026-05-29
related_docs:
  - ../feature/whatsapp-overview.md
---

# whatsapp_packages

Defines available message credit packages that gyms can purchase. Managed by Super Admin.

## Columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | no | auto | Primary key |
| `name` | varchar | no | — | Package display name |
| `description` | varchar | yes | null | Package description |
| `message_count` | int | no | — | Number of messages included |
| `selling_price` | decimal(10,2) | no | — | Price in INR |
| `twilio_number_cost` | decimal(10,2) | no | 0 | Twilio phone number cost |
| `twilio_msg_cost_per_unit` | decimal(10,4) | no | 0 | Twilio cost per message |
| `validity_days` | int | no | — | Days until credits expire |
| `sort_order` | int | no | 0 | Display order |
| `is_active` | bool | no | true | Whether package is available for purchase |
| `is_popular` | bool | no | false | Whether to highlight as popular |
| `includes_number` | bool | no | false | Whether package includes a phone number |
| `features` | json | yes | null | Array of feature descriptions |
| `created_at` | timestamp | yes | null | Creation timestamp |
| `updated_at` | timestamp | yes | null | Last update timestamp |

## Indexes

| Index | Columns | Type | Description |
|-------|---------|------|-------------|
| Primary | `id` | primary | Auto-increment |

## Scopes

| Scope | Description |
|-------|-------------|
| `active()` | Filter packages where `is_active = true`, ordered by `sort_order` |
| `popular()` | Filter packages where `is_popular = true` |
| `ordered()` | Order by `sort_order`, then `selling_price` |

## Key Helper Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `isPopular()` | bool | `is_popular` is true |
| `yourProfit()` | Attribute | `selling_price - (message_count * twilio_msg_cost_per_unit + twilio_number_cost)` |
| `marginPercentage()` | Attribute | profit / selling_price * 100 |
| `costPerMessage()` | Attribute | selling_price / message_count |

## Migration History

- `2026_02_12_133639` — Initial creation
