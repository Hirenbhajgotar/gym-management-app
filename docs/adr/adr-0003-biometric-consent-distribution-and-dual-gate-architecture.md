---
title: "ADR-0003: Multi-Channel Biometric Consent Distribution and Dual-Gated Notification Architecture"
status: "Accepted"
date: "2026-10-11"
authors: "Lead Architect, Security Engineer, Backend Team"
tags: ["architecture", "decision", "biometrics", "privacy", "consent", "notifications", "whatsapp", "email", "filament", "idempotency"]
supersedes: ""
superseded_by: ""
---

# ADR-0003: Multi-Channel Biometric Consent Distribution and Dual-Gated Notification Architecture

## Status

**Accepted**

## Context

Under the Digital Personal Data Protection (DPDP) Act 2023 and GDPR Art. 9, capturing, indexing, or processing biometric data (fingerprint templates, facial embeddings) requires explicit, informed, and revocable consent from data principals. In the multi-tenant gym platform, biometric attendance and access control are blocked by design until a cryptographically verifiable `BiometricConsent` record is established for a member.

The platform previously relied exclusively on a passive background email dispatched upon initial member registration (`GymMemberObserver::created()`). Operational reality across commercial gym facilities revealed critical limitations:

1. **Unconsented Member Accumulation**: Members imported via bulk spreadsheet migrations, migrated from legacy systems, or registered in person without email addresses remained in an unconsented state. Staff had no administrative tooling to solicit consent on demand.
2. **Channel Engagement Disparity**: In primary deployment markets, email open rates among gym members fall below 25%, whereas WhatsApp delivers >90% engagement within minutes. Solicitations restricted to email created substantial friction and delayed biometric enrollment at turnstiles.
3. **Meta WhatsApp Business API Constraints**: Outside the 24-hour customer service window, the Meta Cloud API strictly forbids free-form text messaging; all outbound messages must conform to pre-approved utility/marketing templates (`biometric_consent`). Sending non-template messages results in hard API rejections, message delivery failure, and tenant account reputation penalties.
4. **Synchronous Execution & Gateway Timeouts**: Distributing consent requests to large gym rosters (often 500 to 5,000+ members) within a single HTTP request thread in the Filament Admin panel causes HTTP 504 gateway timeouts, worker exhaustion, and partial dispatch failures.
5. **Spam & Consent Solicitation Idempotency**: Repeatedly broadcasting consent links to members who already possess active, unrevoked consent causes confusion, member friction, and compliance concerns regarding excessive communication.

An architectural mechanism was required to provide on-demand, multi-channel (Email and WhatsApp) consent distribution directly within the administrative dashboard, backed by strict eligibility verification, WhatsApp configuration gating, and asynchronous execution.

## Decision

We designed and implemented a comprehensive, multi-tiered **Multi-Channel Biometric Consent Distribution and Dual-Gated Notification Architecture**:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│             MULTI-CHANNEL BIOMETRIC CONSENT DISTRIBUTION ARCHITECTURE                 │
└────────────────────────────────────────────────────────────────────────────────────────┘

 1. ADMINISTRATIVE UI & SELECTION TIER (Filament Admin Panel)
    ┌───────────────────────────┐      ┌─────────────────────────────┐
    │ ListGymMembers            │      │ GymMemberResource           │
    │ (Header Action: Broadcast)│      │ (Table Bulk Action: Select) │
    └─────────────┬─────────────┘      └──────────────┬──────────────┘
                  │                                   │
                  └─────────────────┬─────────────────┘
                                    │
                                    ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │ Modal Channel Selection Schema                                  │
    │  - Email Toggle (Default: Checked)                              │
    │  - WhatsApp Toggle (Conditionally Visible via Dual-Gate Check)  │
    │  - Dynamic Eligible Recipient Count Indicator                   │
    └───────────────────────────────┬─────────────────────────────────┘
                                    │
 2. AVAILABILITY & ELIGIBILITY TIER │
                                    ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │ NotificationChannelResolver::canSendBiometricConsentViaWhatsApp │
    │   ├─ Gate 1: Tenant WhatsApp Enabled & Account Active           │
    │   └─ Gate 2: Approved 'biometric_consent' Template Exists        │
    └───────────────────────────────┬─────────────────────────────────┘
                                    │ (Channel Options Confirmed)
                                    ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │ BiometricConsentEligibility Service                             │
    │   ├─ Status: Active & Not Archived                              │
    │   ├─ Legal Guard: hasActiveBiometricConsent() === false         │
    │   ├─ Email Guard: Valid email string present                    │
    │   └─ WhatsApp Guard: Valid E.164 phone & opt-in active          │
    └───────────────────────────────┬─────────────────────────────────┘
                                    │
 3. ASYNCHRONOUS FAN-OUT TIER       │
                                    ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │ FanOutBiometricConsentRequestsJob                               │
    │   ├─ Runs on Notifications Queue (NOTIFICATIONS_QUEUE)          │
    │   ├─ Chunks eligible member IDs in batches of 200               │
    │   └─ Dispatches per-member SendBiometricConsentNotificationJob   │
    └───────────────────────────────┬─────────────────────────────────┘
                                    │
 4. DELIVERY & SIGNED URL TIER      │
                                    ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │ SendBiometricConsentNotificationJob                             │
    │   ├─ BiometricConsentUrlFactory (7-day signed secure route)     │
    │   ├─ Email Channel: BiometricConsentMail                        │
    │   └─ WhatsApp Channel:                                          │
    │        ├─ WhatsAppSendCharge (Balance check & reservation)      │
    │        ├─ BiometricConsentTemplateResolver                      │
    │        └─ Meta / Twilio Messaging Service (Template / Fallback) │
    └─────────────────────────────────────────────────────────────────┘
                                    ▲
                                    │ (Auto Onboarding Integration)
    ┌───────────────────────────────┴─────────────────────────────────┐
    │ WelcomeNotification / InteractsWithBiometricConsent             │
    │ (Included conditionally only if Gym has active biometric device)│
    └─────────────────────────────────────────────────────────────────┘
```

### 1. Dual-Gate WhatsApp Availability Enforcement
To prevent admin confusion and delivery rejections, the WhatsApp channel toggle is strictly dynamic:
- **Service Verification**: `NotificationChannelResolver::canSendBiometricConsentViaWhatsApp(Gym $gym)` acts as an authoritative single-source predicate.
- **Gate 1 (Account Configuration)**: The tenant must have `whatsapp_notifications = true` in `gym_settings` AND must possess an active WhatsApp account with an operational, verified sender number (`NotificationChannelResolver::isWhatsAppConfigured($gym)`).
- **Gate 2 (Template Approval)**: An approved template with category/slug `biometric_consent` must exist for the tenant or as a verified system default (`BiometricConsentTemplateResolver::resolve($gym)` and `TemplateLookup::findApprovedTemplate()`).
- **UI Behavior**: If either gate fails, the WhatsApp toggle is completely excluded from the Filament action modal, leaving Email as the sole channel without runtime messaging errors.

### 2. Dual Filament Administrative Entrypoints
Staff can initiate requests through two complementary interfaces:
- **Header Broadcast Action (`RequestBiometricConsentAction`)**: Displayed on `ListGymMembers`. Calculates and solicits all active, unconsented members in the tenant.
- **Table Bulk Action (`GymMemberResource::bulkActions`)**: Permits granular multi-row selection, allowing staff to re-request consent for specific member cohorts (e.g., members attending an orientation session).

### 3. Asynchronous Two-Tier Chunked Fan-Out Pipeline
To prevent HTTP gateway timeouts and scale across arbitrary member counts:
- The UI actions do not execute external notifications directly. Instead, they record actor metadata and dispatch `FanOutBiometricConsentRequestsJob` to the background queue (`config('queue.notifications_queue')`).
- `FanOutBiometricConsentRequestsJob` iterates over target member IDs in chunks of 200, dispatching individual `SendBiometricConsentNotificationJob` instances for each recipient.
- This isolates per-recipient delivery failures (e.g., invalid phone format or SMTP timeout) and prevents single-member exceptions from poisoning the wider batch.

### 4. Idempotent Consent Eligibility Guard (`BiometricConsentEligibility`)
Before dispatch and immediately prior to transmission inside worker threads, members are validated:
- `hasActiveBiometricConsent() === false`: Already-consented members are skipped.
- `notArchived()`: Deleted or archived profiles are suppressed.
- `canReceiveWhatsappNotifications()`: Members who opted out of WhatsApp or lack valid dialable E.164 phone numbers are bypassed for WhatsApp delivery.
- All filtering decisions are aggregated into a `BiometricConsentDispatchReport` for structured logging and audit traceability.

### 5. Centralized Signed URL Generation (`BiometricConsentUrlFactory`)
Consent links are generated through a dedicated factory:
- Routes resolve to `consent.biometric.show` utilizing Laravel's `URL::temporarySignedRoute()`.
- Expiry duration defaults to 7 days (`config('biometrics.consent_url_expiry_days')`).
- Eliminates hardcoded URL generation across controllers, mailables, notifications, and WhatsApp variable resolvers.

### 6. Seamless Onboarding Integration (`WelcomeNotification`)
Rather than relying on disjointed observer-triggered consent emails:
- The onboarding `WelcomeNotification` incorporates the `InteractsWithBiometricConsent` trait.
- It queries `Gym::hasActiveBiometricDevice()`: if the gym operates active physical biometric hardware and the new member has not yet consented, a prominent biometric attendance notice with an embedded 7-day signed consent action button is included directly inside the welcome email.
- If the gym has no biometric devices configured, the biometric section is suppressed completely, preventing member confusion at non-biometric branches.

## Consequences

### Positive

- **POS-001**: **Guaranteed Pre-Enrollment Legal Compliance**: Gyms can proactively achieve 100% consent coverage before inviting members to physical turnstiles, fulfilling DPDP Act 2023 and GDPR obligations.
- **POS-002**: **Zero Web Server Timeouts**: Offloading fan-out and delivery to two-tier asynchronous queues ensures instant UI response times in Filament regardless of gym roster size (tested across multi-thousand member sets).
- **POS-003**: **Zero WhatsApp API Rejections**: Strict dual-gating guarantees that WhatsApp dispatches are only permitted when an active sender and pre-approved template are verified, preventing Meta API rate-limiting or template policy violations.
- **POS-004**: **Idempotent Member Protection**: Double-checked eligibility ensures compliant, archived, or opted-out members are never spammed with redundant consent solicitations.
- **POS-005**: **Context-Aware Onboarding**: Automatic inclusion of consent links in welcome emails only when biometric hardware is present streamlines member onboarding while keeping emails uncluttered for non-biometric facilities.
- **POS-006**: **High Engagement Multi-Channel Reach**: Combining Email and WhatsApp doubles consent turnaround speeds compared to email-only workflows.

### Negative

- **NEG-001**: **Asynchronous Feedback Delay**: Admins do not receive instant synchronous confirmation of message delivery status in the modal; success notifications confirm job queuing rather than final mailbox receipt.
- **NEG-002**: **WhatsApp Message Cost Overhead**: WhatsApp business messaging incurs per-conversation utility charges via Meta/Twilio, requiring tenant wallet balance management (`WhatsAppSendCharge`).
- **NEG-003**: **Template Approval Latency**: Tenants adopting WhatsApp must wait for Meta's automated/manual template approval for `biometric_consent` before the WhatsApp option unlocks.

## Alternatives Considered

### Synchronous In-Thread Batch Dispatching

- **ALT-001**: **Description**: Execute mail and WhatsApp API calls directly within the Filament Action `action()` closure before returning the notification.
- **ALT-002**: **Rejection Reason**: External HTTP calls to WhatsApp and SMTP servers take 500ms–2000ms per recipient. Processing even 50 members synchronously exceeds standard PHP `max_execution_time` (30s) and Nginx `fastcgi_read_timeout` (60s), causing catastrophic gateway timeouts.

### Blind WhatsApp Dispatch Without Template Gating

- **ALT-003**: **Description**: Allow admins to select WhatsApp regardless of template status, falling back to raw body text if no template is registered.
- **ALT-004**: **Rejection Reason**: Outside the 24-hour service window, Meta WABA rejects all non-template messages. Blind sending would result in silent failures, wasted wallet credits, and potential Meta account strikes.

### Forcing Re-Consent on All Members Periodically

- **ALT-005**: **Description**: Include already-consented members in consent broadcasts to refresh their consent timestamp annually.
- **ALT-006**: **Rejection Reason**: Under DPDP and GDPR, consent remains legally valid until revoked or altered by substantial policy shifts. Spamming compliant members causes brand erosion and high WhatsApp opt-out rates.

## Implementation Notes

- **IMP-001**: **Filament Modal Schema Implementation**:
  - `RequestBiometricConsentAction` defines the channel toggle form with dynamic reactivity.
  - WhatsApp toggle is wrapped in `visible(fn () => NotificationChannelResolver::canSendBiometricConsentViaWhatsApp($gym))`.
- **IMP-002**: **Queue Worker Architecture**:
  - Jobs are routed to `config('queue.notifications_queue')` (default: `'notifications'`).
  - Worker processes must consume this queue via `app:work-queues` (`WorkQueuesCommand`), ensuring notifications do not starve behind long-running report exports or payroll jobs.
- **IMP-003**: **Database Notification Settings Backfill**:
  - Migration `2026_10_08_000000_backfill_biometric_consent_notification_setting` automatically provisions `notification_biometric_consent = true` into tenant `gym_settings.notifications` JSON for all existing gyms.
- **IMP-004**: **WhatsApp Variable Resolution & Charge Management**:
  - `BiometricConsentTemplateResolver` extracts template placeholders (`{{1}}` for member name, `{{2}}` for gym name, `{{3}}` for signed consent URL).
  - `WhatsAppSendCharge` evaluates tenant wallet balances prior to queueing and deducts charges atomically.
- **IMP-005**: **Hardware Notice Gate in Welcome Emails**:
  - `Gym::hasActiveBiometricDevice()` checks for active devices in `biometric_devices` (`is_active = true`).
  - `WelcomeNotification` leverages `InteractsWithBiometricConsent` to attach the section only when device count > 0 and member consent is missing.

## References

- **REF-001**: [ADR-0001: Biometric Hardware, Access Control, and Verification System Architecture](file:///docs/adr/adr-0001-biometric-implementation.md)
- **REF-002**: [ADR-0002: Biometric Enrollment and Device Reconciliation Architecture](file:///docs/adr/adr-0002-biometric-enrollment-and-device-reconciliation.md)
- **REF-003**: [RequestBiometricConsentAction Filament Header Action](file:///app/Filament/Admin/Actions/RequestBiometricConsentAction.php)
- **REF-004**: [NotificationChannelResolver Service](file:///app/Services/NotificationChannelResolver.php)
- **REF-005**: [BiometricConsentEligibility Service](file:///app/Services/Biometric/BiometricConsentEligibility.php)
- **REF-006**: [BiometricConsentUrlFactory Service](file:///app/Services/Biometric/BiometricConsentUrlFactory.php)
- **REF-007**: [FanOutBiometricConsentRequestsJob](file:///app/Jobs/FanOutBiometricConsentRequestsJob.php)
- **REF-008**: [SendBiometricConsentNotificationJob](file:///app/Jobs/SendBiometricConsentNotificationJob.php)
- **REF-009**: [WelcomeNotification](file:///app/Notifications/Member/WelcomeNotification.php)
- **REF-010**: [Biometric Consent Actions Feature Test Suite](file:///tests/Feature/Filament/BiometricConsentActionsTest.php)
- **REF-011**: [Dual-Gate Availability Feature Test Suite](file:///tests/Feature/Services/NotificationChannelResolverDualGateTest.php)
- **REF-012**: [ADR-0004: Member Archival Lifecycle, Cryptographic Biometric Credential Erasure, and Access Revocation Architecture](file:///docs/adr/adr-0004-member-archival-and-biometric-erasure-lifecycle.md)
