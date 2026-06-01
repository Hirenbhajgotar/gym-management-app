---
adr: 005
title: WhatsApp Provider Abstraction
status: accepted
date: 2026-04-17
author: docs-agent
related_features:
  - ../whatsapp/feature/whatsapp-overview.md
---

# ADR-005: WhatsApp Provider Abstraction

## Status

Accepted

## Context

The gym management system needed to support multiple WhatsApp messaging providers because:

1. **Different gyms have different preferences** — some already use Meta's WhatsApp Business API directly, others prefer Twilio's abstraction layer for simplicity
2. **Cost optimization** — Twilio charges per message + number costs; direct Meta API may be cheaper for high-volume senders
3. **Feature availability** — Meta's embedded signup flow and BYOD (bring your own device) phone number registration are Meta-only features; Twilio offers number purchasing and simpler setup
4. **Future flexibility** — New providers (e.g., Vonage, MessageBird) could be added without breaking existing integrations

The initial implementation hardcoded Twilio as the only provider, creating a single `TwilioWhatsAppService` that handled everything. This created vendor lock-in and made it impossible for gyms to use Meta's direct API.

## Decision

We implemented a provider abstraction layer with the following design:

### 1. Per-Tenant Provider Configuration

Each gym's `WhatsAppAccount` record stores a `provider_type` field (`WhatsAppProviderTypeEnum`: `twilio` or `waba`). This allows different gyms on the same platform to use different providers.

### 2. Factory Pattern with SWR Caching

`WhatsAppProviderFactory` resolves the correct provider per gym:

- Checks `WhatsAppAccount.provider_type` (per-tenant, preferred)
- Falls back to `config('services.whatsapp.default_provider')` for backward compatibility
- Uses `Cache::flexible()` with 1-day minimum / 1-week maximum TTL (Stale-While-Revalidate pattern)
- System messages always route through Twilio (via `$isSystemMessage` flag)
- `invalidateProviderCache()` method for manual cache clearing

### 3. Two Interfaces

- **`TemplateManagementInterface`** — Core template operations (create, submit, sync, get) implemented by both providers
- **`WhatsAppProviderInterface`** (deprecated) — Full-spectrum operations including messaging, account management, phone numbers, credits, WABA setup, embedded signup. Being phased out in favor of smaller, focused interfaces.

### 4. Concrete Providers

- **`MetaWhatsAppProvider`** — Direct Meta API integration. Supports embedded signup, manual WABA connection, Meta template management.
- **`TwilioWhatsAppProvider`** — Twilio API integration. Supports sub-account creation, number purchasing, BYOD number verification with OTP.

### 5. Shared Services

- `WhatsAppCreditService` is provider-agnostic — credit operations are database-only and shared
- `WhatsAppBroadcastService` dispatches jobs that resolve providers at runtime

## Alternatives Considered

| Alternative | Why rejected |
|---|---|
| Single provider (hardcoded) | Vendor lock-in; different gyms need different providers |
| Runtime provider switching without interface | Code duplication, no polymorphism, hard to test |
| Generic plugin system | Over-engineered for current 2-provider need |
| Config-only switching (no per-tenant) | All gyms forced to same provider; no flexibility |
| Strategy pattern with DI container | More complex; factory + cache is simpler for this use case |

## Consequences

### Positive

- Each gym can choose their preferred provider
- Easy to add new providers by implementing interfaces
- Clean separation between provider-specific and shared logic
- Testable (can mock interfaces)
- SWR caching minimizes database queries for provider resolution
- Backward compatible (config fallback for gyms without explicit provider_type)

### Negative / Trade-offs

- Additional abstraction overhead (factory, interfaces, two providers)
- Each provider needs maintenance and updates
- Template operations may behave differently between providers (Meta approval vs Twilio)
- `WhatsAppProviderInterface` is too broad (being deprecated in favor of focused interfaces)
- Provider-specific features (embedded signup = Meta only) create asymmetric capabilities

## Related Decisions

- [ADR-006](./ADR-006-whatsapp-broadcast-architecture.md) — Broadcast architecture builds on provider abstraction for sending
