---
adr: 006
title: WhatsApp Broadcast Architecture
status: accepted
date: 2026-05-28
author: docs-agent
related_features:
  - ../whatsapp/feature/whatsapp-overview.md
---

# ADR-006: WhatsApp Broadcast Architecture

## Status

Accepted

## Context

The gym management system needed a broadcast capability to send template messages to multiple members at once (e.g., membership reminders, promotional campaigns, class schedule updates). Key requirements:

1. **Scale** — Some gyms have thousands of members; broadcasts need to handle large recipient lists without memory issues
2. **Credit management** — Credits must be deducted before sending; if a gym has 500 members but only 200 credits, the broadcast should fail gracefully
3. **Provider-agnostic** — Broadcasts must work with both Twilio and Meta providers
4. **Progress tracking** — Gym owners need visibility into broadcast status (sending, completed, failed)
5. **Queue-based** — Sending messages is I/O-bound and must be done asynchronously

## Decision

We implemented a broadcast system with the following architecture:

### 1. Upfront Credit Deduction

Credits are deducted **before** the broadcast starts, not per-message. This prevents overspending and ensures the gym has sufficient credits. If deduction fails (insufficient credits), the broadcast is rejected.

In `WhatsAppCreditService`, `skipDeduction` mode is enabled during broadcast flows — the orchestration layer handles deduction, and individual send jobs skip the check.

### 2. Dispatch Pattern

`WhatsAppBroadcastService::dispatchBroadcast()` dispatches a single `SendBroadcastJob` to the queue. This job:

1. Loads the broadcast, template, and gym
2. Resolves recipients from `WhatsAppBroadcastRecipient`
3. Creates a Laravel Bus batch for progress tracking
4. Dispatches chunk jobs that send messages in batches

### 3. Chunked Processing

Recipient lists are processed in chunks to prevent memory exhaustion. Each chunk:

- Loads a batch of recipients
- Resolves template variables per recipient (via `WhatsAppVariableResolver`)
- Sends messages through the provider
- Updates recipient status (sent/delivered/failed)
- Increments broadcast counters

### 4. Batch Progress Tracking

Each broadcast creates a Laravel Bus batch (`$broadcast->batch_id`). The `getProgressAttribute()` accessor returns:

```php
['total' => int, 'pending' => int, 'failed' => int, 'progress' => int]
```

This allows the frontend to display real-time progress without polling individual recipients.

### 5. Recipient-Level Status Tracking

Each `WhatsAppBroadcastRecipient` tracks its own status lifecycle:

```
PENDING → SENDING → SENT → DELIVERED
                ↓
            FAILED (with failure_reason)
```

### 6. Failure Classification & Transient Retry

Transient errors (e.g. rate limits, HTTP 429, timeouts, network disconnections) vs. permanent errors (e.g. invalid phone number, opt-out, unapproved template) are classified by `WhatsAppFailureClassifier`.
- **Transient failures**: Caught and retried with backoff.
- **Permanent failures**: Recorded immediately as `FAILED` on the recipient without blocking the remainder of the chunk.

### 7. Automatic Credit Refund Pipeline

If a broadcast encounters failed recipients or is cancelled mid-flight:
1. `credits_deducted_upfront` tracks whether initial full quota was deducted.
2. At completion or on cancellation, `WhatsAppBroadcastService` / `SendBroadcastJob` calculates total unspent or failed message credits.
3. A refund transaction (`WhatsAppCreditTransaction`) is logged and credits are restored to `whatsapp_message_credits`.
4. `refunded_credits` and `refunded_at` timestamps are persisted on the `whatsapp_broadcasts` record.

## Flow Diagram

```
Gym Owner clicks "Send Broadcast"
    │
    ▼
WhatsAppBroadcastService::dispatchBroadcast()
    │
    ▼
SendBroadcastJob (queued)
    │
    ├── Load broadcast, template, gym
    ├── Deduct credits upfront (`credits_deducted_upfront = true`)
    ├── Create Bus batch (`batch_id`)
    │
    └── For each chunk of recipients:
         │
         ├── SendMessageChunkJob (queued)
         │    │
         │    ├── Resolve messaging service provider (Twilio or Meta)
         │    ├── For each recipient:
         │    │    ├── Check marketing opt-in & snapshots
         │    │    ├── Resolve template variables (WhatsAppVariableResolver)
         │    │    ├── Provider::sendTemplateMessage()
         │    │    ├── If failed: WhatsAppFailureClassifier classifies error
         │    │    ├── Update recipient status & error details
         │    │    └── Update broadcast counters (sent / failed)
         │    │
         │    └── Batch completion hook
         │
         └── Final Reconciliation & Refund:
              ├── If failed_count > 0 or status == CANCELLED:
              │    ├── Calculate refund amount (failed_count * cost_per_msg)
              │    ├── Restore credits to WhatsAppMessageCredit
              │    ├── Create WhatsAppCreditTransaction (REFUND)
              │    └── Set refunded_credits and refunded_at
              └── Mark status as COMPLETED
```

## Alternatives Considered

| Alternative | Why rejected |
|---|---|
| Per-message credit deduction | Race conditions; credits could run out mid-broadcast; harder to refund |
| Synchronous sending | Would block the user's browser for minutes/hours |
| Single job (no chunks) | Memory exhaustion with 10k+ recipients; no progress tracking |
| External broadcast service (e.g., SendGrid) | Vendor lock-in; doesn't integrate with existing WhatsApp setup |
| Webhook-based delivery tracking | Adds complexity; delivery confirmation not critical for initial implementation |

## Consequences

### Positive

- Credit deduction upfront prevents overspending while automated refunding protects gym owners from losing credits on invalid recipients
- Chunked processing (`SendMessageChunkJob`) handles large recipient lists without memory issues
- Bus batches provide built-in progress tracking
- Intelligent error classification via `WhatsAppFailureClassifier` prevents false alarm retries on permanent errors
- Provider-agnostic — works with Twilio and Meta
- Individual recipient status tracking for auditing
- Queue-based — non-blocking for the user

### Negative / Trade-offs

- Bus batch overhead adds database writes
- Chunk size tuning needed for optimal performance vs. memory trade-off

## Related Decisions

- [ADR-005](./ADR-005-whatsapp-provider-abstraction.md) — Provider abstraction enables broadcast to work with both providers
- [ADR-007](./ADR-007-database-migration-history.md) — Database migration history

