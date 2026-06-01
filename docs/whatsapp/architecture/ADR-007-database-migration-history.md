---
adr: 007
title: WhatsApp Database Migration History
status: accepted
date: 2026-05-28
author: docs-agent
related_features:
  - ../whatsapp/feature/whatsapp-overview.md
---

# ADR-007: WhatsApp Database Migration History

## Status

Accepted

## Context

The WhatsApp feature evolved across 29 migration files spanning October 2025 to May 2026. This ADR documents the schema evolution chronologically, capturing key decisions about table structures, column additions, and index strategies.

## Migration Timeline

### Phase 1: Initial Schema (October 2025)

| Migration | Date | Action |
|---|---|---|
| `2025_10_02_082101_create_whatsapp_templates_table.php` | 2025-10-02 | Initial templates table (v1): `gym_id`, `template_name`, `template_sid`, `language`, `variables`, `status`, `synced_at` |
| `2025_10_07_130155_create_gym_whatsapp_balances_table.php` | 2025-10-07 | Initial credit balances table |
| `2025_10_07_130412_create_whatsapp_message_packages_table.php` | 2025-10-07 | Initial message packages table |

### Phase 2: Full Schema Rebuild (February 2026)

A major rebuild on 2026-02-12 created all core tables with proper enums and constraints:

| Migration | Table | Key Columns |
|---|---|---|
| `2026_02_12_133317` | `whatsapp_accounts` | `gym_id`, `twilio_account_sid` (unique), `twilio_auth_token`, `waba_id`, `messaging_service_sid`, `status`, `verification_status`, `business_name/email/description`, `verified_at`. Index: `[gym_id, status]` |
| `2026_02_12_133620` | `whatsapp_phone_numbers` | `whatsapp_account_id`, `phone_number` (unique), `whatsapp_number_sid`, `display_name`, `status` |
| `2026_02_12_133628` | `whatsapp_templates` | Recreated with `gym_id`, `name`, `display_name`, `body`, `category`, `status`, `variables`, `variable_count`, `variable_placeholder`, `is_active`, `is_default`, `rejection_reason`, `twilio_template_sid` |
| `2026_02_12_133635` | `whatsapp_message_credits` | `gym_id`, `total_credits`, `used_credits`, `remaining_credits`, `cost_per_message`, `selling_price_per_msg`, `welcome_credits_used`, `welcome_credits_claimed`, `last_used_at` |
| `2026_02_12_133638` | `whatsapp_broadcasts` | `gym_id`, `whatsapp_template_id`, `title`, `description`, `target_filters`, `target_count`, `status`, `scheduled_at`, timestamps, `sent_count`, `failed_count`, `credits_used`, `message_variables` |
| `2026_02_12_133639` | `whatsapp_broadcast_recipients` | `whatsapp_broadcast_id`, `gym_member_id`, `phone_number`, `status`, `sent_at`, `delivered_at`, `twilio_message_sid`, `error_message` |
| `2026_02_12_133639` | `whatsapp_packages` | `name`, `description`, `message_count`, `selling_price`, `twilio_msg_cost_per_unit`, `twilio_number_cost`, `includes_number`, `features`, `is_active`, `is_popular`, `sort_order`, `validity_days` |
| `2026_02_12_133639` | `whatsapp_usage_logs` | `gym_id`, `whatsapp_template_id`, `recipient`, `twilio_message_sid`, `status`, `twilio_cost`, `sent_at`, `delivered_at`, `error_message`, `credits_used` |
| `2026_02_12_133640` | `whatsapp_credit_transactions` | `gym_id`, `type`, `credits`, `balance_before`, `balance_after`, `amount`, `description`, `payment_reference`, `whatsapp_package_id`, `metadata` |

### Phase 3: Incremental Improvements (February–March 2026)

| Migration | Date | Change |
|---|---|---|
| `2026_02_18_113630` | 2026-02-18 | `whatsapp_templates`: Added `variable_placeholder` JSON column for storing placeholder definitions |
| `2026_02_21_121438` | 2026-02-21 | `whatsapp_accounts`: Changed `twilio_auth_token` from varchar to `text` (for encrypted tokens) |
| `2026_02_25_064553` | 2026-02-25 | `whatsapp_templates`: Added `deleted_at` soft delete column |
| `2026_02_26_100441` | 2026-02-26 | `whatsapp_message_credits`: Added `is_low_credit_notified` boolean (default false) |
| `2026_03_14_000000` | 2026-03-14 | `whatsapp_phone_numbers`: Added `channel_sender_sid` column |
| `2026_03_31_175200` | 2026-03-31 | `whatsapp_templates`: Added unique constraint on `[gym_id, name]` |

### Phase 4: Provider Abstraction (April 2026)

| Migration | Date | Change |
|---|---|---|
| `2026_04_09_135605` | 2026-04-09 | `whatsapp_accounts`: Added `verify_service_sid` column for Twilio Verify integration |
| `2026_04_17_194921` | 2026-04-17 | `whatsapp_accounts`: Added `provider_type` varchar(20) with default `twilio`. Index: `provider_type` |
| `2026_04_17_202810` | 2026-04-17 | `whatsapp_phone_numbers`: Added `waba_phone_number_id` for Meta WABA phone number identification |

### Phase 5: WABA & Verification Enhancements (May 2026)

| Migration | Date | Change |
|---|---|---|
| `2026_05_07_000000` | 2026-05-07 | `whatsapp_accounts`: Added index on `verification_status` for faster filtering |
| `2026_05_10_000000` | 2026-05-10 | `whatsapp_accounts`: Added `waba_status_synced_at` timestamp for tracking WABA status sync |
| `2026_05_11_175525` | 2026-05-11 | `whatsapp_accounts`: Dropped `waba_mode` column (no longer needed with provider abstraction) |
| `2026_05_22_220537` | 2026-05-22 | `whatsapp_phone_numbers`: Added `last_number_cost_charged_at` timestamp |
| `2026_05_22_220632` | 2026-05-22 | `whatsapp_credit_transactions`: Added `phone_number_surcharge` decimal for tracking number costs |

### Phase 6: Broadcast Scaling (May 2026)

| Migration | Date | Change |
|---|---|---|
| `2026_05_28_165130` | 2026-05-28 | `whatsapp_broadcasts`: Added `batch_id` varchar(36) for Laravel Bus batch tracking |
| `2026_05_29_000001` | 2026-05-29 | `whatsapp_broadcasts`: Added index on `status` for broadcast queue queries |

## Key Schema Decisions

### Why `twilio_auth_token` was changed to TEXT
Encrypted tokens can exceed 255 characters. The migration on 2026-02-21 changed the column type to `text` to accommodate encryption.

### Why `provider_type` was added
The dual-provider architecture (Twilio + Meta WABA) required per-tenant provider selection. Adding it to `whatsapp_accounts` rather than a separate table keeps provider config co-located with account credentials.

### Why `waba_mode` was dropped
With the provider abstraction, the mode is determined by `provider_type` (twilio vs waba). The separate `waba_mode` column became redundant.

### Why `batch_id` was added late
Broadcast scaling required Bus batch tracking for progress monitoring. The column was added to `whatsapp_broadcasts` to store the batch UUID returned by `Bus::batch()`.

### Why `phone_number_surcharge` is on `whatsapp_credit_transactions`
Phone number costs (Twilio charges per number) are separate from message costs. Tracking them in the credit transaction audit trail ensures accurate billing.

## Related Decisions

- [ADR-005](./ADR-005-whatsapp-provider-abstraction.md) — Provider abstraction drove the `provider_type` column addition
- [ADR-006](./ADR-006-whatsapp-broadcast-architecture.md) — Broadcast architecture drove the `batch_id` column addition
