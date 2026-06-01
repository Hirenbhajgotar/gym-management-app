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

### 6. Provider Resolution

Each chunk job resolves the provider for the gym via `WhatsAppProviderFactory::create()`. This means:
- Different gyms in the same queue can use different providers
- Provider changes take effect on the next broadcast
- System messages (e.g., low credit notifications) always use Twilio

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
    ├── Deduct credits (upfront)
    ├── Create Bus batch
    │
    └── For each chunk of recipients:
         │
         ├── WhatsAppBroadcastChunkJob (queued)
         │    │
         │    ├── Resolve provider (Twilio or Meta)
         │    ├── For each recipient:
         │    │    ├── Resolve template variables
         │    │    ├── Provider::sendTemplateMessage()
         │    │    ├── Update recipient status
         │    │    └── Update broadcast counters
         │    │
         │    └── Mark broadcast as completed (if last chunk)
         │
         └── (next chunk...)
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

- Credit deduction upfront prevents overspending
- Chunked processing handles large recipient lists without memory issues
- Bus batches provide built-in progress tracking
- Provider-agnostic — works with Twilio and Meta
- Individual recipient status tracking for debugging
- Queue-based — non-blocking for the user

### Negative / Trade-ups

- Credits are deducted before sending; if broadcast fails mid-way, partial refunds are needed
- No real-time delivery webhooks — status is set at send time, not confirmed by provider
- Chunk size tuning needed for optimal performance vs. memory trade-off
- Bus batch overhead adds database writes
- No retry mechanism for individual failed recipients within a broadcast

## Related Decisions

- [ADR-005](./ADR-005-whatsapp-provider-abstraction.md) — Provider abstraction enables broadcast to work with both providers
