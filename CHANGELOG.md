# Changelog

All notable changes to the **Gym Management System** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Changed
- **Meta WABA Template Language Resolution**: Refactored `MetaTemplateService::resolveLanguage()` to delegate directly to `TemplateFieldMapper::sanitizeLanguage()`, preserving both 2-letter ISO language codes (e.g. `en`, `hi`, `es`) and full locale codes (`en_US`, `en_GB`) for WABA templates.
- **WhatsApp Setup Wizard Step Alignment & Slug Redirection**: Updated `EmbeddedSignupController` redirects (for OAuth callbacks, manual connection, and error states) to use semantic step slugs (`?setup_step=whats-app-business`) instead of hardcoded numeric step indices, decoupling controller navigation from wizard step renumbering.
- **WABA Wizard Step Schema Alignment**: Aligned `SetupWizardSchema` step constants and `HasSetupState` state machine accessibility checks so that WABA Billing Mode is Step 2 (`WABA_BILLING_MODE_STEP = 2`) and WABA Account Connection is Step 3 (`WABA_ACCOUNT_STEP = 3`), removing deprecated dead constants.
- **Embedded Signup Test Architecture**: Extracted `seedEmbeddedSignupSession()` helper to `WhatsAppSetupTestCase` to standardize session initialization across embedded signup controller tests.

### Fixed
- **Meta API Test Mocking**: Added comprehensive `Http::fake()` coverage for Meta Graph API token exchange, debug token, phone numbers, and WABA details in `EmbeddedSignupControllerTest`, preventing live network calls and asserting asynchronous `ImportWhatsAppTemplatesJob` dispatching and session flash flags on manual connect.
- **WABA Language Resolution Test Coverage**: Added unit test coverage in `MetaTemplateServiceTest` verifying language resolution for 2-letter ISO codes (`en`, `hi`), full locale codes (`en_US`), and `null` fallbacks.
- **Setup State Step Comments**: Corrected inverted step-number comments in `HasSetupState::determineSetupStep()` and `HasSetupState::isStepAccessible()`.

---

## [v1.7.1] - 2026-09-09 (Current GitHub Release)

### Added
- **Multi-Language WhatsApp Template Support**: Added `language` attribute support to `whatsapp_templates`, including `CreateTemplateData` and `UpdateTemplateData` DTOs, database migration, and provider synchronization.
- **WhatsApp Template Locale Sanitization**: Introduced `TemplateFieldMapper::sanitizeLanguage()` with ISO/BCP47 regex validation and provider-aware fallbacks (`MetaTemplateService::defaultTemplateLanguage()` / `TwilioTemplateService::TWILIO_DEFAULT_LANGUAGE`).

### Changed
- Updated `MetaMessagingService`, `MetaTemplateService`, `TwilioTemplateService`, and `SendBroadcastJob` to dynamically resolve and dispatch template messages using each template's configured language.

---

## [v1.7.0] - 2026-09-08

### Added
- **WhatsApp Broadcast Refunds**: Automated refunding of message credits for failed recipients or cancelled broadcast batches.
- **Member Marketing Snapshots**: Added WhatsApp marketing snapshots and archival status tracking to member records.
- **Twilio & Meta Failure Classifier**: Intelligent categorization of delivery errors to distinguish between invalid numbers, throttles, and template rejections.

### Changed
- Refactored `SendBroadcastJob` and `SendMessageChunkJob` to utilize high-throughput batching with exponential backoff and rate-limiting.
- Enhanced PT commission calculations with percentage-based salary allocations per member.

### Fixed
- Fixed strict type casting and nullability issues across WhatsApp broadcast queue listeners.
- Resolved race conditions during credit reservation and final reconciliation.

---

## [v1.6.0] - 2026-07-30

### Added
- **Dynamic Plan Pricing & Multi-Currency**: Introduced `PlanPrices` with flexible billing interval support and localized pricing.
- **SaaS Checkout Sessions**: Integrated Razorpay order checkout sessions for self-service gym subscription upgrades.
- **Member Payment Transactions Ledger**: Detailed transaction logs tracking discounts, tax breakdowns, and payment method reconciliations.
- **Lead Reminder Tracking**: Automated background reminders for unattended leads and stale follow-ups.

### Security
- Added unique gym-member constraint to enforce data isolation per tenant gym.
- Enhanced API token permission scoping for multi-gym owner accounts.

---

## [v1.5.0] - 2026-05-29

### Added
- **Meta Cloud API (WABA) Direct Integration**: Direct Embedded Signup flow for gym owners to link Meta Business Accounts without third-party brokers.
- **WhatsApp Phone Number Management**: Automated monthly phone number cost deductions and credit transaction logging.
- **Broadcast Queue Indexing**: Optimized database indexes for large-scale broadcast recipient lookups.

### Changed
- Unified WhatsApp provider interface supporting seamless runtime switching between Meta Cloud API and Twilio WhatsApp.
- Backfilled and normalized granular permissions across Manager and Staff roles.

---

## [v1.4.0] - 2026-04-17

### Added
- **Provider-Agnostic Biometric Hardware Integration**: Native engine supporting ZKTeco, Hikvision, and eSSL devices for fingerprint and facial recognition.
- **Biometric Consent Management**: Built-in consent capture workflow complying with DPDP and GDPR requirements before biometric enrollment.
- **Automated Door Access Control**: Real-time door event logging (`DoorEvents`) and automated entry authorization based on active membership status.
- **Dynamic Anti-Proxy QR Attendance**: Rotating QR tokens and kiosk scanning interface with geofencing validation.
- **Public CMS & Blog Engine**: SEO-ready blog posts, CMS pages, and meta tags managed from the SuperAdmin panel.

### Fixed
- Fixed offline attendance synchronization to resolve duplicate timestamps during hardware reconnection.

---

## [v1.3.0] - 2026-02-28

### Added
- **Financial Expense Management**: Expense logging, hierarchical categories, system default templates, and quarterly/monthly budget limits with threshold alerts.
- **Advanced Reporting & BI Analytics**:
  - Saved report filter presets and automated scheduled email reports.
  - Secure, time-expiring shareable report links.
  - Report KPI target goals with real-time progress indicators.
- **Member Body Measurements**: Historical body composition tracking (weight, body fat %, measurements) with trend charts.
- **Membership Freeze Flow**: Ability to temporarily pause member subscriptions without losing remaining active days.
- **Security Audit Logs**: Tracked user login activity, IP addresses, and user-agent metadata.

---

## [v1.2.0] - 2025-12-30

### Added
- **WhatsApp Marketing & Automation Suite**:
  - WhatsApp template builder with multi-variable placeholders.
  - Broadcast campaign scheduler with recipient filtering.
  - Prepaid WhatsApp credit wallet with package purchasing via Razorpay.
- **Automated Tax Invoicing**: Branded PDF invoice and payment receipt generation for gym memberships and SaaS subscriptions.
- **SaaS Subscription Provisioning**: Multi-tier subscription plans with automatic feature provisioning and grace periods.
- **Razorpay Payment Gateway**: Seamless online payment links, webhook verification, and automated signature validation.

---

## [v1.1.0] - 2025-09-15

### Added
- **Multi-Channel Lead Ingestion CRM**:
  - Automated webhooks for Facebook Lead Ads, Instagram Ads, IndiaMART, JustDial, and WhatsApp inquiries.
  - Interactive drag-and-drop Lead Kanban board.
  - Heuristic lead scoring and conversion tracking.
- **Multi-Channel Notification System**: Configurable SMS, WhatsApp, and Email notification templates with event logs.

---

## [v1.0.0] - 2025-05-18

### Added
- **Multi-Tenancy Foundation**: Tenant data isolation, gym onboarding, and scoped query filters.
- **Multi-Role Filament v3 Admin Architecture**: Dedicated panels for SuperAdmin, Gym Owner (Admin), Manager, Trainer, and Staff with Spatie RBAC.
- **Core Member & Trainer Management**: Member registration, profile data, emergency contacts, trainer allocation, and membership plan assignment.
- **Payment & Membership Tracking**: Manual payment recording, payment status tracking, and membership duration calculations.
- **Bulk Data Import & Export**: Excel/CSV tools for member and payment data migration.
- **Master Geographic Datasets**: Pre-populated datasets for Countries, States, and Cities.
- **Activity Logging & Error Diagnostics**: Spatie ActivityLog integration and centralized exception monitoring.
