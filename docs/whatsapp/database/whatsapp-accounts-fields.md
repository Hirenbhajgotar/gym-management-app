---
table: whatsapp_accounts
feature: WhatsApp Integration
status: draft
last_updated: 2026-05-29
related_docs:
  - ../feature/whatsapp-overview.md
---

# whatsapp_accounts

Stores the WhatsApp Business Account configuration for each gym tenant. One record per gym.

## Columns

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint | no | auto | Primary key |
| `gym_id` | bigint | no | — | FK to `gyms.id`. Unique constraint (one WhatsApp account per gym) |
| `twilio_account_sid` | varchar | yes | null | Twilio sub-account SID (used when provider is Twilio) |
| `twilio_auth_token` | text | yes | null | Twilio auth token (encrypted via `Crypt::encrypt()`, hidden from array/JSON) |
| `waba_id` | varchar | yes | null | Meta WhatsApp Business Account ID (used when provider is WABA) |
| `waba_name` | varchar | yes | null | Display name of the WABA |
| `messaging_service_sid` | varchar | yes | null | Twilio Messaging Service SID for sending messages |
| `verify_service_sid` | varchar | yes | null | Twilio Verify Service SID for OTP verification |
| `meta_business_portfolio_id` | varchar | yes | null | Meta Business Portfolio ID |
| `meta_access_token` | text | yes | null | Meta API access token (encrypted, hidden from array/JSON) |
| `embedded_signup_completed` | bool | yes | false | Whether Meta embedded signup flow was completed |
| `embedded_signup_session_id` | varchar | yes | null | Session ID for embedded signup flow |
| `status` | varchar | yes | null | Account status enum (`WhatsAppAccountStatusEnum`) |
| `verification_status` | varchar | yes | null | Verification status enum (`WhatsAppAccountVerificationStatusEnum`) |
| `business_name` | varchar | yes | null | Business name for the WhatsApp account |
| `business_email` | varchar | yes | null | Business email |
| `business_description` | varchar | yes | null | Business description |
| `verified_at` | timestamp | yes | null | When the account was verified |
| `provider_type` | varchar | yes | null | Provider type enum (`WhatsAppProviderTypeEnum`: `waba` or `twilio`) |
| `billing_mode` | varchar(20) | yes | `pay_via_app` | Billing mode (`pay_via_app`, `direct_meta`) |
| `created_at` | timestamp | yes | null | Creation timestamp |
| `updated_at` | timestamp | yes | null | Last update timestamp |

## Indexes

| Index | Columns | Type | Description |
|-------|---------|------|-------------|
| Primary | `id` | primary | Auto-increment |
| Unique | `gym_id` | unique | One WhatsApp account per gym |
| Index | `verification_status` | index | For filtering by verification status |
| Index | `billing_mode` | index | For filtering by billing mode |

## Relationships

| Method | Type | Related Model | FK |
|--------|------|---------------|----|
| `gym()` | BelongsTo | `Gym` | `gym_id` |
| `phoneNumber()` | HasOne | `WhatsAppPhoneNumber` | `whatsapp_account_id` |
| `templates()` | HasMany | `WhatsAppTemplate` | `gym_id` |

## Key Helper Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `isActive()` | bool | Status is `ACTIVE` |
| `isVerified()` | bool | Verification status is `VERIFIED` |
| `isSetupComplete()` | bool | Account is active, verified (WABA), and has a phone number |
| `isTwilioProvider()` | bool | Provider type is `TWILIO` |
| `isWabaProvider()` | bool | Provider type is `WABA` |
| `getPhoneNumberId()` | ?string | Returns `phone_number_sid` (Twilio) or `waba_phone_number_id` (WABA) |
| `isPhoneSurchargeDue()` | bool | Whether the Twilio number cost needs to be charged again |

## Security Notes

- `twilio_auth_token` and `meta_access_token` are encrypted at rest via `Crypt::encrypt()` and hidden from serialization
- Accessor mutators handle transparent encryption/decryption

## Migration History

- `2026_02_12_133317` — Initial creation
- `2026_02_21_121438` — Changed `twilio_auth_token` to `text` type (was varchar)
- `2026_04_09_135605` — Added `verify_service_sid`
- `2026_04_17_194921` — Added `provider_type`
- `2026_05_07_000000` — Added index on `verification_status`
- `2026_05_10_000000` — Added `waba_status_synced_at`
- `2026_05_11_175525` — Dropped `waba_mode` column
- `2026_07_30_152445` — Added `billing_mode` column with index

