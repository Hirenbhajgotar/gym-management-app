# Changelog

All notable changes to the **Gym Management System** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Breaking Changes
- **Enforce ADMS Device Communication Key by Default (`ADMS_REQUIRE_COMM_KEY=true`)**:
  - `config('biometrics.adms.require_comm_key')` now defaults to `true` (previously `false`). All unkeyed deployments will reject unauthenticated ADMS requests until provisioned.
  - PBKDF2 iterations are now configurable via `config('biometrics.adms.pbkdf2_iterations')` (default 100,000).
  - Backfill command (`biometrics:backfill-adms-comm-keys`) now detects and re-provisions legacy unsalted SHA-256 hashes when run with `--provision`.
  - Removed orphaned `BIOMETRIC_FALLBACK_PIN` from `.env.example`.

### Fixed
- **Door Controller TLS SNI (`DoorControlService`)**:
  - Replaced IP-in-URL rewriting with `CURLOPT_RESOLVE` to prevent DNS rebinding TOCTOU without breaking TLS SNI certificate validation on HTTPS door controllers.
- **Member Biometric Enrollment (`EnrollBiometricAction`)**:
  - Unified enrollment into modal action `EnrollBiometricAction` on Gym Member resource, deprecating and retiring the standalone `EnrollMember` page.
  - Replaced invalid `user_info` command type with `user_add`.
  - Canonical `idempotency_key` format (`user_add:{gym_id}:{device_id}:{member_id}[:stage]:{template_id}`) with `UniqueConstraintViolationException` handling and failure notification to operator.
  - Sibling terminals in the same gym are automatically provisioned via `BiometricTemplatePropagator` with `Propagated` lifecycle status.
- **Command Cancellation & Idempotency (`AdmsDeviceCommand`)**:
  - Added `cancelInFlightFor()` static helper ensuring correct SQL NULL-key semantics across cancel blocks.
  - Added `markCancelled()` method that sets status to `CANCELLED` (for both `PENDING` and `SENT` commands) and clears `idempotency_key` to prevent collisions upon retry.
  - Added `cancelDeleteCommands()` static helper for batch cancellation across writers.
  - Preserved `idempotency_key` across command execution and failures in `markAcknowledged()`, enabling reconcilers to re-drive existing failed commands within retry caps.
  - Reset `attempt_count` to 0 on Filament command resend (both single and bulk actions).
- **Nightly Enrollment Reconciler (`ReconcileBiometricEnrollmentsCommand`)**:
  - Nightly scheduled reconciliation for active `Synced` and `Propagated` templates, queueing replacement `user_add` commands when hardware delivery commands are missing or failed, with safeguards for archived/expired members and live de-provisioning commands.
- **Durable Hardware Delivery Tracking (`BiometricTemplate`)**:
  - Added `hardware_confirmed_at` timestamp column and data migration to record when hardware acknowledges credential installation across active templates, preserving delivery evidence independently of command log pruning.
  - A hardware acknowledgement is terminal confirmation: `AdmsDeviceCommand::reconcileUserAdd()` now promotes a `Pending` placeholder to `Synced` and activates it, so the 7-day abandoned-placeholder sweep can no longer revoke a credential the terminal is holding.
- **Superseded & Pending Placeholder Cleanup (`CleanupSupersededTemplatesCommand`)**:
  - Validates `--days > 0` and `--pending-days > 0` to prevent accidental immediate retirement.
  - Chunks records with `chunkById(100)` for memory safety.
  - Retires `Superseded` rows with a **soft delete** rather than destroying them, satisfying OpenSpec §3's "kept for audit/retention"; a `superseded_reason` discriminator distinguishes a plain re-enrolment from an operator overriding a GDPR Art. 17 erasure request, and the override is written to the activity log.
  - Pending placeholders delivered to hardware are handed to `BiometricDeProvisioner`, which emits a `user_delete` and parks the row at `PendingDeletion`; the command summary now reports those separately instead of counting them as purged.
  - Placeholders that were never delivered are retired outright, because there is no hardware credential to erase.
- **Consent Gate on Command Resend (`AdmsDeviceCommandResource`)**:
  - `resend` and `resendSelected` now refuse to re-queue a `user_add`/`user_update` when the target member has no active `BiometricConsent` (ADR-003 / OpenSpec §4). `user_delete` is exempt so erasure remains possible after consent is revoked.
- **Permission Sync Dynamic Set (`SyncPermissionsCommand`)**:
  - Strict separation between Filament-discovered permissions and hardcoded dynamic defaults: `permissions:sync` applies discovered permissions by default (falling back to dynamic defaults only if discovery resolves empty). Added explicit `--include-dynamic` flag to opt into merging dynamic default permissions, with per-role change logging.
- **Webhook & Event Response Handling (`BiometricWebhookController`)**:
  - Added missing denial reasons (`no_active_check_in`, `already_checked_in`, `anti_passback`, `device_offline`, `untrusted_door_controller_url`).
  - Propagates `$doorResult->failureReason` in response and adjusts display message/LED to warning when check-in succeeds but door fails to unlock.
- **Test Suite & CI Hardening**:
  - Corrected `DB_PASSWORD` from `root` to `password` and forced `DB_HOST=127.0.0.1` in `phpunit.xml`.
  - Removed `environment: Testing` from CI workflows and reduced `lint.yml` permissions to `contents: read`.
  - Added pre-boot safety guard in `TestCase` and `WhatsAppSetupTestCase` before `parent::setUp()` triggers `migrate:fresh`.
  - Removed duplicate safety guard from `StaffPanelTestCase`.
  - Deleted unused `FreezesClock` trait and removed vestigial `phpstan:dtos` composer script.
  - Replaced blanket `2026_09_28_130000` migration with an intentional no-op log.

### Added
- **Biometric ADMS Push Protocol (eSSL, ZKTeco, Hikvision)**:
  - Added native ADMS (`iclock`) push endpoints: `/api/iclock/cdata` (handshake & `ATTLOG` attendance push), `/api/iclock/getrequest` (command queue polling), and `/api/iclock/devicecmd` (execution acknowledgments).
  - Added Hikvision ISAPI XML event push endpoint `/api/v1/biometric/hikvision/event` with device token authentication.
  - Dual-channel coexistence: terminals can simultaneously use the vendor webhook (`/api/v1/biometric/webhook/{token}`) for real-time door relay unlock directives, and ADMS for attendance logging & member provisioning.
  - Implemented `AdmsEventService` with automated check-out detection (treating check-ins as check-outs if an open session already exists today) and anti-passback locking.
  - Added `AdmsDeviceCommand` model, migration, factory, and `AdmsCommandStatusEnum` (`PENDING`, `SENT`, `ACKNOWLEDGED`, `FAILED`) implementing Filament's `HasLabel` and `HasColor`.
  - Added three-layer expired member deprovisioning via `CleanupExpiredDeviceMembers` scheduled daily at 03:00, calculating cutoff via: `membership_end` + `gym_grace_days` (default 7) + `adms_grace_days` (default 10). Automatically queues `DATA DELETE user` commands and deactivates biometric templates.
  - Filament Admin UI updates: added `device_serial` input, `adms_enabled` toggle with dynamic setup instructions, and table column/action in `BiometricDeviceResource`; added tenant-scoped `AdmsDeviceCommandResource` with retry/resend capabilities.
  - Comprehensive feature test suites in `tests/Feature/AdmsProtocolTest.php` (11 tests) and `tests/Feature/CleanupExpiredDeviceMembersTest.php` (5 tests).
  - Technical documentation in `docs/biometric/feature/biometric-adms-push-protocol.md`, `docs/biometric/database/adms-device-commands-fields.md`, and `docs/biometric/architecture/ADR-004-adms-push-protocol-and-lifecycle.md`.

## [v1.7.2] - 2026-09-19 (Current GitHub Release)

### Added
- **Invoice PDF Engine & Modern Printable Views**:
  - Introduced shared printable styling component `resources/views/invoices/partials/styles.blade.php` with `DejaVu Sans` typography, CSS `@page` margins, brand styling, badge statuses, info cards, itemized billing tables, and summary sections.
  - Re-engineered `resources/views/invoices/default.blade.php` (for SaaS subscription invoices) and `resources/views/invoices/member.blade.php` (for gym member receipts) with clean modern layout, dynamic status badges (`Completed`, `Pending`, `Failed`), gym and member address rendering, payment method references, and gym owner contact details.
  - Added dedicated `invoices` filesystem disk in `config/filesystems.php` (configured with `driver: local`, `visibility: private`, `throw: true`) and updated `filesystems.invoice_disk` configuration.
  - Added `GymMember::memberResourceUrl(self|int $member, ?string $type = 'index'): string` helper to reliably generate Filament resource URLs for gym members across views.
  - Added dedicated test suite in `tests/Feature/Filament/Admin/Clusters/Reports/Pages/MemberReportsTest.php` covering report generation, authorization, tenant isolation, plan distribution, churn metrics, and cache lifecycle.
  - Added database migration `2026_09_18_185000_add_unique_to_invoice_number_columns.php` enforcing unique database constraints on `invoice_number` across both `payments` and `member_payments` tables.
- **Frontend User Profile Hub & Modular Architecture**:
  - Modularized the user profile view (`ProfileController` / `profile-controller.blade.php`) into 8 dedicated partial blade components: `_header.blade.php`, `_profile_card.blade.php`, `_subscription.blade.php`, `_plans.blade.php`, `_gyms.blade.php`, `_payments.blade.php`, `_qr_modal.blade.php`, and `_settings_modal.blade.php`.
  - Added standalone high-resolution SVG QR code generation (`getCheckInQrCodeSvg()`, `getCheckoutQrCodeSvg()`) on `Gym` model using cached SVG generation (`QrGenerator`), decoupling QR rendering from Filament panel dependencies.
  - Implemented timing-safe token verification methods `hasValidCheckInQrCodeToken()` and `hasValidCheckoutQrCodeToken()` on `Gym` model using `hash_equals()`.
  - Added dedicated factory state helpers `withCheckInQrCodeToken()` and `withCheckoutQrCodeToken()` in `GymFactory`.
  - Added comprehensive feature test suite in `tests/Feature/Frontend/ProfileTest.php` with 17 tests covering profile updates, password changes, checkout upgrades/renewals, invoice downloads, timing-safe QR verification, mass assignment guarding, and session management.
- **Request Demo Hub & High-Converting Landing Architecture**:
  - Dedicated configuration file `config/request-demo.php` encapsulating rate limiting rules (`REQUEST_DEMO_RATE_LIMIT_MAX`, `REQUEST_DEMO_RATE_LIMIT_DECAY`), notification overrides (`REQUEST_DEMO_FORCE_CONTACT_EMAIL`), DNS validation caching/TTL (`REQUEST_DEMO_DNS_CHECK_ENABLED`, `REQUEST_DEMO_DNS_CACHE_TTL`), dynamic WhatsApp messages, operator case study testimonials, and objection-handling FAQs.
  - Re-engineered `/request-demo` page (`request-demo.blade.php`) featuring a dark-themed SaaS layout, 20-minute walkthrough agenda breakdown, real-time phone and email validation, verified gym case study metrics, Alpine.js interactive FAQ accordion, and direct WhatsApp support CTA (`https://wa.me/...`).
  - Modernized `RequestDemo` Livewire component with typed properties, `whatsAppUrl` computed property, `resetForm()` lifecycle state reset, and isolated testimonial/FAQ retrieval methods.
  - Added dedicated test suite in `tests/Feature/HomeControllerTest.php` for isolated home controller rendering verification.
- **Public Landing Page Redesign & Instant Callback Hub**:
  - Re-architected public homepage (`HomeController` / `home-controller.blade.php`), navigation (`navbar.blade.php`), and footer (`footer.blade.php`) with modern SaaS styling, interactive feature showcases, ROI calculator, and FAQ sections.
  - Introduced `RequestCallbackAction` and `HomeController::requestCallback()` enabling instant lead capture with E.164 phone normalization, rate limiting (3 requests/hour per IP), and 15-minute idempotency deduplication into `RequestContact`.
  - Added `contact_whatsapp` config parameter in `config/app.php` (`CONTACT_WHATSAPP` env var) for direct WhatsApp customer support link resolution.
  - Added `Enterprise & Franchise` tier definition to `PlanSeeder`.
  - Added dedicated unit and feature test coverage in `RequestCallbackActionTest` and `HomeControllerTest`.
- **Member Membership Freeze & Plan Extension Engine**:
  - Introduced `MemberFreezeService` providing atomic freeze/unfreeze operations with optional membership expiration extension (`extend_membership`).
  - Added gym-level configurable threshold settings (`min_freeze_days` default 7, `max_freeze_days` default 60) in `GymSettings`.
  - Added automatic expiration date reconciliation on early, exact, or late unfreezes, capped by individual membership plan durations.
  - Implemented automatic synchronization of latest non-expired `MemberPayment` records using `updateQuietly()` to preserve report cache integrity.
  - Added row locking (`lockForUpdate()`) for concurrency safety and full activity audit logging on both freeze and unfreeze events.
  - Enhanced `CheckMembershipExpiry` command (`gym:check-membership-expiry`) with `--dry-run` preview capability and automated unfreezing for expired freeze periods.
  - Enhanced `GymMemberResource` table action `toggle_freeze` with duration options, plan extension toggle, role-based authorization gates (`SUPER_ADMIN`, `ADMIN`, `GYM_OWNER`, `MANAGER`, `RECEPTIONIST`), and contextual notifications.
  - Added comprehensive test suite in `GymMemberFreezeExtensionTest` with 27 unit/feature tests covering boundary conditions, proration logic, notifications, activity logs, and authorization.

### Changed
- **Invoice Generation & Authorization Hardening (`InvoiceService`)**:
  - Standardized invoice number generation to format `INV-{YYYYMMDD}-{S|M}{gym_id}-{key}` with 4-digit key padding and regex format validation.
  - Added `ensureInvoiceNumber(InvoiceablePayment $payment): string` return type for guaranteed format parity across invoice creators.
  - Implemented `eagerLoadRelations()` to pre-load relational attributes (`user`, `gym.address.city`, `gym.address.state`, `member.address.city`, `member.address.state`, `membershipPlan`) preventing N+1 queries during PDF generation.
  - Hardened authorization checks in `assertAccessible()` and `MemberInvoiceController::show()` to permit member user owners (`$payment->member->user_id === $authUser->id`) as well as tenant-scoped staff roles (`SUPER_ADMIN`, `GYM_OWNER`, `MANAGER`, `RECEPTIONIST`).
  - Removed `invoice_number` and `invoice_path` from `$fillable` arrays on both `Payment` and `MemberPayment` models to guard against mass assignment.
  - Updated `GymMemberImporter` to delegate invoice number generation directly to `resolve(InvoiceService::class)->ensureInvoiceNumber($payment)` and pass Carbon date instances directly in `beforeSave()`.
  - Added explicit return type hints and generics on `MemberReports::getFilteredMembersQuery()`, `getGrowthData()`, and `getPlanDistribution()`.
  - Updated `resources/views/filament/clusters/reports/pages/member-reports.blade.php` to render member links via `$member->memberResourceUrl($member, 'edit')`.
- **Frontend User Profile Controller Modernization & Subscription Upgrades**:
  - Re-engineered `ProfileController` with strict type hints, return types, PHPDoc blocks, and computed property caching.
  - Added prorated plan upgrade checkout session handling (`CheckoutSession::TYPE_UPGRADE`) when `subscriptions.proration_enabled` is true.
  - Added plan renewal checkout session support (`CheckoutSession::TYPE_RENEWAL`) resolving real-time active plan pricing.
  - Added secure invoice download endpoint (`downloadInvoice()`) delegating to `InvoiceService` with user-scoped payment authorization.
  - Optimized `User::subscription()` helper to reuse eager-loaded `activeSubscription` relation when available, eliminating redundant database queries.
  - Introduced `Payment::profileLatestPaymentsCacheKey()` helper and updated `PaymentObserver::clearCache()` for authenticated user payment caching consistency.
  - Registered `\Illuminate\Session\Middleware\AuthenticateSession` middleware in `bootstrap/app.php` to ensure session revocation across devices on password updates.
  - Deprecated legacy profile layout `resources/views/frontend/layouts/profile.blade.php` to standardize on `x-frontend.layouts.app_new`.
- **Gym Model Security Hardening**: Guarded sensitive QR code tokens (`qr_code_token`, `qr_code_checkout_token`) from mass assignment in `Gym` model.
- **Request Demo Resilience & Validation Hardening**:
  - Enhanced `ValidEmailDomain` rule with cached DNS lookups (`checkdnsrr` for MX or A records), configurable TTL, fail-open network exception handling, and a testable static closure resolver seam (`ValidEmailDomain::$resolver`).
  - Hardened `RequestDemoNotification` with queued retries (`$tries = 3`), exponential `$backoff = [60, 300, 900]`, constructor property promotion, and structured error logging on permanent dispatch failure.
  - Added `throttle:60,1` rate-limiting middleware to `/request-demo` route in `routes/web.php`.
  - Updated `bootstrap/app.php` reverse proxy configuration to dynamically parse `TRUSTED_PROXIES` environment variable (supporting wildcard `*`, comma-separated IP lists, and fallback to RFC1918 subnets).
  - Explicitly configured `APP_ENV=testing` in `phpunit.xml`.
- **Plan Pricing Caching & Model Touch Invalidation**:
  - Refactored `HomeController` to use Livewire `#[Computed(persist: true)]` for `plans` and `enterprisePlans` with isolated cache keys (`Plan::ALL_PLANS_FOR_HOME_CACHE_KEY`, `Plan::ALL_ENTERPRISE_PLANS_FOR_HOME_CACHE_KEY`).
  - Added `$touches = ['plan']` and `plan()` relationship to `PlanFeature` model to cascade timestamp updates and trigger `PlanObserver::clearCache()` on feature mutations.
  - Added `standard()` and `enterprise()` query scopes and modernized scope method signatures to `void` on `Plan` model.
- **Reverse Proxy & Header Trust Configuration**:
  - Configured `$middleware->trustProxies()` in `bootstrap/app.php` across RFC1918 private subnets and loopback addresses to accurately resolve client IP (`Request::getClientIp()`) and protocols behind Nginx/Docker.
  - Added `/health` proxy location block in `nginx.conf`.
- **Meta WABA Template Language Resolution**: Refactored `MetaTemplateService::resolveLanguage()` to delegate directly to `TemplateFieldMapper::sanitizeLanguage()`, preserving both 2-letter ISO language codes (e.g. `en`, `hi`, `es`) and full locale codes (`en_US`, `en_GB`) for WABA templates.
- **WhatsApp Setup Wizard Step Alignment & Slug Redirection**: Updated `EmbeddedSignupController` redirects (for OAuth callbacks, manual connection, and error states) to use semantic step slugs (`?setup_step=whats-app-business`) instead of hardcoded numeric step indices, decoupling controller navigation from wizard step renumbering.
- **WABA Wizard Step Schema Alignment**: Aligned `SetupWizardSchema` step constants and `HasSetupState` state machine accessibility checks so that WABA Billing Mode is Step 2 (`WABA_BILLING_MODE_STEP = 2`) and WABA Account Connection is Step 3 (`WABA_ACCOUNT_STEP = 3`), removing deprecated dead constants.
- **Embedded Signup Test Architecture**: Extracted `seedEmbeddedSignupSession()` helper to `WhatsAppSetupTestCase` to standardize session initialization across embedded signup controller tests.

### Fixed
- **Churn Risk Payment Status Enum Handling**:
  - Updated `ChurnRiskService::calculateRiskScore()` and `identifyRiskFactors()` to check against `PaymentStatus::FAILED` enum in addition to raw string values.
- **Gym Member Latest Payment Association**:
  - Removed redundant `where('gym_id', $this->gym_id)` constraint on `GymMember::latestPayment()` `hasOne` relationship to ensure accurate latest payment resolution.
- **Invoice Service Test Coverage**:
  - Expanded `tests/Feature/InvoiceServiceTest.php` with tests verifying member owner authorization, disk validation exceptions, invoice number uniqueness and format assertions, blade rendering, address inclusion, and queue job integration.
- **Request Demo Error Handling & Test Reliability**:
  - Updated `RequestDemo::submitRequest()` to rethrow `ValidationException` for native Livewire form error bag handling while logging unexpected runtime exceptions.
  - Refactored `RequestDemoTest` to verify DNS resolution pass/fail, rate limiter counting on both valid and invalid attempts, queued admin notifications, form reset state, and WhatsApp URL generation.
- **App Configuration & Migration Idempotency**:
  - Hardened `APP_PREVIOUS_KEYS` array parsing in `config/app.php` to prevent type errors when environment variable is unset or empty.
  - Added `Schema::hasColumn()` existence guard in `2026_05_10_000000_add_waba_status_synced_at_to_whatsapp_accounts_table.php` migration to prevent duplicate column addition errors on re-runs.
- **Meta API Test Mocking**: Added comprehensive `Http::fake()` coverage for Meta Graph API token exchange, debug token, phone numbers, and WABA details in `EmbeddedSignupControllerTest`, preventing live network calls and asserting asynchronous `ImportWhatsAppTemplatesJob` dispatching and session flash flags on manual connect.
- **WABA Language Resolution Test Coverage**: Added unit test coverage in `MetaTemplateServiceTest` verifying language resolution for 2-letter ISO codes (`en`, `hi`), full locale codes (`en_US`), and `null` fallbacks.
- **Setup State Step Comments**: Corrected inverted step-number comments in `HasSetupState::determineSetupStep()` and `HasSetupState::isStepAccessible()`.

---

## [v1.7.1] - 2026-09-09

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
