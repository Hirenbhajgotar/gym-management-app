---
table: whatsapp_phone_numbers
feature: WhatsApp Integration
status: draft
last_updated: 2026-05-29
related_docs:
  - ../feature/whatsapp-overview.md
---

# whatsapp_phone_numbers

Stores phone numbers purchased or registered for WhatsApp messaging. One primary number per WhatsApp account.

## Columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | no | auto | Primary key |
| `whatsapp_account_id` | bigint | yes | null | FK to `whatsapp_accounts.id` |
| `phone_number` | varchar | no | — | Phone number (digits only, without +) |
| `phone_number_sid` | varchar | yes | null | Twilio IncomingPhoneNumbers SID (starts with `PN`, nullable for Meta WABA) |
| `channel_sender_sid` | varchar | yes | null | WhatsApp Channel Sender SID (starts with `CN`) |
| `waba_phone_number_id` | varchar | yes | null | Meta WABA phone number ID (for WABA provider) |
| `country_code` | varchar | no | — | Country code (e.g., `IN`, `US`) |
| `friendly_name` | varchar | yes | null | Display-friendly phone number |
| `locality` | varchar | yes | null | City/locality |
| `region` | varchar | yes | null | State/region |
| `monthly_cost` | decimal(10,2) | no | 0 | Monthly recurring cost |
| `number_cost` | decimal(10,2) | no | 0 | One-time purchase cost |
| `status` | varchar | no | — | Phone number status enum (`WhatsAppPhoneNumberStatusEnum`) |
| `type` | varchar | no | — | Phone number type enum (`WhatsAppPhoneNumberTypesEnum`) |
| `purchased_at` | timestamp | yes | null | When the number was purchased |
| `last_number_cost_charged_at` | timestamp | yes | null | When the number surcharge was last charged |
| `created_at` | timestamp | yes | null | Creation timestamp |
| `updated_at` | timestamp | yes | null | Last update timestamp |

## Indexes

| Index | Columns | Type | Description |
|-------|---------|------|-------------|
| Primary | `id` | primary | Auto-increment |
| Index | `whatsapp_account_id` | index | For account-scoped queries |
| Unique | `phone_number_sid` | unique | Unique Twilio SID when present |
| Unique | `[whatsapp_account_id, waba_phone_number_id]` | unique | Unique WABA phone number per account (`wa_acc_waba_phone_unique`) |

## Relationships

| Method | Type | Related Model | FK |
|--------|------|---------------|----|
| `account()` | BelongsTo | `WhatsAppPhoneNumber` | `whatsapp_account_id` |

## Key Helper Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `isActive()` | bool | Status is `ACTIVE` |
| `getFormattedNumber()` | string | Returns `+{phone_number}` |
| `getChannelSenderSid()` | ?string | Channel Sender SID (starts with `XE`) |
| `getIncomingPhoneNumberSid()` | ?string | Phone Number SID (starts with `PN`) |
| `isValidIncomingPhoneNumberSid()` | bool | SID starts with `PN` |
| `isValidChannelSenderSid()` | bool | SID starts with `XE` |
| `hasProperSidSeparation()` | bool | Both SIDs in correct fields |
| `isSurchargeDue()` | bool | Whether number cost needs recharging (30-day cycle) |
| `getSurchargeAmount()` | float | `number_cost` or default `136.41` |

## SID Types

The model tracks three distinct SID concepts:

1. **Phone Number SID** (`phone_number_sid`) — Twilio's IncomingPhoneNumbers API resource (starts with `PN`)
2. **Channel Sender SID** (`channel_sender_sid`) — WhatsApp Channel Sender registration (starts with `CN`)
3. **WABA Phone Number ID** (`waba_phone_number_id`) — Meta's WABA phone number identifier

## Migration History

- `2026_02_12_133620` — Initial creation
- `2026_03_14_000000` — Added `channel_sender_sid`
- `2026_04_17_202810` — Added `waba_phone_number_id`
- `2026_05_22_220537` — Added `last_number_cost_charged_at`
- `2026_07_22_210000` — Made `phone_number_sid` nullable and added unique index on `[whatsapp_account_id, waba_phone_number_id]`

