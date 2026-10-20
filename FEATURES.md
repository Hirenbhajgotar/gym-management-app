# Gym Management System — Feature Overview & Catalog

A modern, multi-tenant **Gym Management & Operations Platform** built with **Laravel 12**, **Filament v3**, **Livewire v3 + Volt**, **Tailwind CSS v4**, and **Laravel Reverb**. Designed for end-to-end gym administration, member retention, automated access control, marketing, and SaaS scalability.

---

## 📑 Table of Contents
1. [Multi-Tenancy & Onboarding](#1-multi-tenancy--onboarding)
2. [Member Management & Health Tracking](#2-member-management--health-tracking)
3. [Smart Attendance & Access Control (Biometric + QR)](#3-smart-attendance--access-control-biometric--qr)
4. [Lead Management & Multi-Channel CRM](#4-lead-management--multi-channel-crm)
5. [WhatsApp Marketing & Automation Suite](#5-whatsapp-marketing--automation-suite)
6. [Membership Plans, Invoicing & Payments](#6-membership-plans-invoicing--payments)
7. [Expense Management & Budgeting](#7-expense-management--budgeting)
8. [Staff Management & Role-Based Access Control (RBAC)](#8-staff-management--role-based-access-control-rbac)
9. [Analytics, AI Churn Prediction & Reporting](#9-analytics-ai-churn-prediction--reporting)
10. [SuperAdmin & Platform Administration](#10-superadmin--platform-administration)
11. [Real-time Infrastructure & System Architecture](#11-real-time-infrastructure--system-architecture)

---

## 1. Multi-Tenancy & Onboarding
* **Complete Tenant Data Isolation**: Gyms operate in isolated tenant boundaries with automatic query scoping via `gym_id` / tenant context.
* **Streamlined Gym Onboarding**: Dedicated signup and setup wizard for gym owners to configure business details, logos, operational hours, and tax settings.
* **SaaS Subscription Provisioning**: Automatic assignment and enforcement of platform tier limits (e.g., maximum members, allowed staff accounts, feature flags).
* **Multi-Gym / Multi-Branch Support**: Gym owners can oversee single or multiple branches from unified credentials with seamless context switching.

---

## 2. Member Management & Health Tracking
* **Comprehensive Member Profiles**: Centralized records covering contact information, emergency contacts, medical history, joining dates, and membership status.
* **Intelligent Membership Freeze & Expiry Extension Engine**:
  * **Flexible Freeze Types**: Support for fixed-duration (date-based) and manual (indefinite) membership freezes.
  * **Automated Expiration Extension (`extend_membership`)**: Automatically extends membership end date and synchronizes the active payment term date (`updateQuietly`) based on frozen duration.
  * **Threshold & Plan Duration Safeguards**: Governed by gym-level settings (`min_freeze_days`, `max_freeze_days`) and capped by the member's plan duration to prevent monetization leakage.
  * **Dynamic Proration & Early/Late Reconciliation**: Recalculates granted extensions in real time upon early return or late unfreeze.
  * **Automated Expiry Checks & Simulation**: Daily automated scheduler (`gym:check-membership-expiry`) to auto-unfreeze expired freezes, with `--dry-run` simulation support.
  * **Audit Trail & Concurrency Protection**: High-concurrency row locking (`lockForUpdate`), activity logging (`gym_member`), and queued transactional email notifications (`MemberFrozenNotification`).
* **Body Measurements & Progress History**: Log and monitor metrics over time (weight, body fat percentage, chest, waist, hips, biceps) with visual progress charts.
* **Member Archival & Physical Access Revocation Lifecycle**:
  * **Single-Owner State Machine (`MemberArchiveService`)**: Atomic, row-locked database transitions enforcing instant access revocation (`canAccessGym() = false`), reported as `member_archived` in `MembershipGuard`.
  * **Automated Inactive Member Archival (`app:archive-inactive-gym-members`)**: Scheduled console command sweeping members expired > 2 years across all tenants, with per-record transaction boundaries, dry-run simulation, and concurrency skipping.
  * **Granular RBAC Authorization**: Gated behind dedicated `archive_gym_member` and `unarchive_gym_member` Spatie abilities via `MemberArchiveAuthorization`, forbidding unverified ambient-guard mutations.
  * **Consent Snapshot Round-Tripping**: Preserves marketing opt-in preferences via `whatsapp_marketing_snapshot`, forcing `allow_whatsapp_marketing` to false while archived and faithfully restoring it upon reactivation.
  * **Sub-Resource Mutation Shield (`GuardsArchivedOwner`)**: Automatically disables mutation actions (create, edit, delete) on child relations (payments, body measurements, biometric templates) while presenting informative status banners.
  * **Financial & Audit Integrity**: Uses explicit, opt-in `notArchived()` scoping instead of global Eloquent scopes, guaranteeing that historical payments, tax invoices, and revenue reports never lose member relationship associations.
* **Membership Lifecycle Management**: Track statuses across *Active*, *Expiring Soon*, *Expired*, *Frozen*, *Archived*, and *Cancelled*.
* **Member Notes & Interaction History**: Timeline of communications, plan upgrades, trainer interactions, and check-ins.
* **Bulk Import & Export**: Fast onboarding and backups via Excel/CSV import and export tools with archive-state permission gating.

---

## 3. Smart Attendance & Access Control (Biometric + QR)
### Biometric Device Integration
* **Provider-Agnostic Biometric Engine**: Hardware abstraction layer supporting major biometric hardware vendors (e.g., ZKTeco, Hikvision, eSSL).
* **ADMS Push Protocol Engine**: Native HTTP push communication protocol supporting eSSL and ZKTeco (`/api/iclock/cdata`, `/api/iclock/getrequest`, `/api/iclock/devicecmd`) and Hikvision ISAPI XML event push (`/api/v1/biometric/hikvision/event`).
* **Dual-Channel Coexistence**: Real-time webhook door unlock (`/api/v1/biometric/webhook/{token}`) for physical relay opening alongside background ADMS attendance sync and remote command queues on a single terminal.
* **Cryptographic Biometric Erasure & Deprovisioning Lifecycle**:
  * **Synchronous Credential Revocation**: Instantly issues hardware `DATA DELETE user` commands and cloud template erasures via `BiometricDeProvisioner` when members are archived.
  * **Grant-Side Enrollment Denial**: `GymMember::assertMayHoldBiometricCredential()` and row-locked controller gates throw `MemberArchivedException` to block archived members from holding credentials.
  * **Durable Machine Erasure Obligations (`is_erasure_obligation`)**: Flags orphan markers and cleanup obligations to prevent new enrollments from superseding deletion tasks or falsifying compliance audits.
  * **Time-Based Erasure Cooldown**: 24-hour cloud cooldown window (`erasure_first_attempted_at`) and retry counters preventing infinite worker loops during third-party provider outages.
  * **Terminal State Management (`DeletionUnresolvable`)**: Automatically isolates broken references and exhausted cooldowns from polling loops, surfacing them for administrative action.
  * **Monotonic Hardware Epoch Tracking (`epoch_credential_user_add_id`)**: Tracks credential epoch IDs on `AdmsDeviceCommand` so re-enrolled members' deletions are safely re-driven without getting stuck at attempt caps.
  * **Biometric Operator Attention Widget**: Dashboard widget and relation manager "Needs Operator" filter surfacing unresolvable erasures and stuck terminal commands with one-click "Retry Erasure".
  * **Fail-Closed Webhook HMAC Verification**: Mandatory HMAC-SHA256 signature verification on `/api/v1/biometric/webhook/{token}` rejecting secret-less devices with HTTP 401, provisioned via `biometrics:provision-webhook-secrets`.
* **Smart Auto-Checkout Detection**: Automatically determines whether an incoming push punch represents a check-in or check-out based on open attendance records for the day.
* **Automated Expired Member Deprovisioning**: Daily scheduled hardware cleanup (`biometrics:cleanup-expired-members`) purging members expired beyond the 3-layer grace threshold (`plan_expiry + gym_grace_days + adms_grace_days`).
* **Remote Device Command Queue**: Asynchronous FIFO command dispatching (`AdmsDeviceCommand`) for remote user registration, deletion, and biometric updates with status tracking and manual resend capabilities.
* **Biometric Consent System**: Compliant consent collection (GDPR/DPDP ready) before capturing or syncing fingerprints/facial templates.
* **Template Management**: Secure synchronization of biometric identifiers directly to edge devices.
* **Automated Door Access Control**: Real-time door unlocking based on valid, active membership; automatically revokes access when plans expire.
* **Door Event Audit Trail**: Granular logs of entry attempts, granted access, denied access, and manual overrides.

### Dynamic QR Code Attendance
* **Front-Desk Kiosk Scanner**: Web-based QR scanner for reception tablets and dedicated kiosks.
* **Dynamic Rotating QR Codes**: Anti-proxy check-in with time-expiring QR codes generated for the gym facility or individual members.
* **Geofenced Check-Ins**: Optional GPS location verification to ensure members are physically present inside the facility.
* **Live Occupancy Monitoring**: Real-time counter of current active members inside the gym.

---

## 4. Lead Management & Multi-Channel CRM
* **Multi-Channel Automated Ingestion**: Webhook integrations that automatically capture incoming leads from:
  * **Facebook Lead Ads**
  * **Instagram Direct / Ads**
  * **IndiaMART**
  * **JustDial**
  * **WhatsApp Inquiries**
  * **Website Contact Forms**
* **Interactive Kanban Board**: Visual drag-and-drop pipeline stages (*New*, *Contacted*, *Trial Booked*, *Trial Attended*, *Negotiating*, *Converted*, *Lost*).
* **Automated Lead Scoring**: Heuristic scoring engine evaluating lead readiness based on response speed, inquiry source, and interaction frequency.
* **Follow-up Reminders & Task Delegation**: Assign leads to specific staff or sales reps with automated reminders for scheduled calls or facility tours.
* **Conversion Analytics**: Track conversion rates per marketing channel and staff member.

---

## 5. WhatsApp Marketing & Automation Suite
* **Dual Meta & Twilio Architecture**: Integrates with official **Meta Cloud API (WABA)** via embedded signup as well as **Twilio WhatsApp** services.
* **Template Management & Meta Sync**: Design rich WhatsApp templates (headers, buttons, variables) with direct submission and status syncing with Meta.
* **Targeted Broadcast Campaigns**:
  * Broadcast to filtered member groups (e.g., expiring this week, inactive 14+ days, VIP members).
  * High-throughput background processing with chunking, throttling, and queue job distribution.
  * WhatsApp failure classification and delivery reporting (sent, delivered, read, failed).
* **Automated Transactional Notifications**:
  * Welcome messages & member onboarding packs
  * Instant payment and renewal receipts
  * Automated payment & membership expiry reminders (T-7, T-3, T-1 days)
  * Daily attendance confirmation & milestone celebrations
  * WhatsApp OTP authentication & password recovery
* **Prepaid WhatsApp Credit Wallet**:
  * In-app credit purchasing via Razorpay
  * Real-time balance deduction, transaction logs, and package top-ups.

---

## 6. Membership Plans, Invoicing & Payments
* **Flexible Membership Configurations**:
  * Custom durations (daily passes, monthly, quarterly, semi-annual, annual).
  * Peak vs. off-peak access, class-specific limits, personal training add-ons.
  * Admission/registration fees and seasonal discount codes.
* **Razorpay Gateway Integration**: Seamless checkout links and online payment collection for renewals and packages via UPI, Cards, Net Banking, and Wallets.
* **Partial Payments & Installment Plans**: Record partial payments, track outstanding balances, and send automated balance due reminders.
* **Automated PDF Invoices & Receipts**: Branded, downloadable, and printable tax invoices compliant with local GST/tax regulations.
* **Financial Transaction Ledger**: Complete payment histories per member with payment method reconciliation (Cash, POS, UPI, Online Gateway).

---

## 7. Expense Management & Budgeting
* **Expense Tracking**: Log operational expenses (rent, utilities, equipment maintenance, staff salaries, supplements, marketing).
* **Dynamic Expense Categories**: Hierarchical categorization for clear financial breakdowns.
* **Monthly & Quarterly Budget Thresholds**: Set departmental or category budget caps with automated warning alerts when nearing limits.
* **Expense Reports & Analytics**: Compare budgeted vs. actual expenditures with visual breakdown charts.

---

## 8. Staff Management & Role-Based Access Control (RBAC)
* **Dedicated Role-Specific Portals**:
  * **Gym Owner / Admin Panel**: Full operational and financial command of the tenant.
  * **Manager Panel**: Day-to-day operations, attendance approval, staff scheduling, lead assignments.
  * **Trainer Panel**: Assigned member lists, workout/diet logs, measurement tracking, session bookings.
  * **Receptionist / Front-Desk Panel**: Quick member check-ins, guest passes, new registrations, POS payments.
* **Granular Permissions (Spatie-Powered)**: Dynamic permission auto-discovery with custom role-permission matrix configuration.
* **Login & Security Audit Trails**: Log user logins, IP addresses, user agents, and sensitive operations.

---

## 9. Analytics, AI Churn Prediction & Reporting
* **Gym Health Score**: Composite scoring metric analyzing member retention, revenue growth, attendance density, and lead conversion.
* **AI & Heuristic Churn Risk Prediction**: Identifies at-risk members based on dropping visit frequency, overdue payments, and nearing expiry dates.
* **Revenue Forecasting & Trend Analysis**: Predictive projections for future renewal revenues and recurring subscription flow.
* **Custom PDF Report Generator**: Generates high-resolution reports with embedded chart images and tabular summaries.
* **Scheduled Reports & Secure Shareable Links**:
  * Automated recurring email reports (Daily summary, Weekly executive brief, Monthly financial close).
  * Password-protected, time-expiring public shareable links for stakeholders.
* **Goal Tracking & KPI Targets**: Set monthly targets for revenue, new member acquisitions, and retention rates with progress gauges.

---

## 10. SuperAdmin & Platform Administration
* **Multi-Tenant Global Oversight**: Manage all registered gyms, tenant subscriptions, and tenant lifecycle (active, suspended, past-due).
* **Platform Plan Management**: Configure SaaS subscription pricing tiers, feature toggles, and billing cycles for gym owners.
* **WhatsApp Credit Management**: Define credit bundles and pricing for WhatsApp broadcast marketing.
* **CMS & Content Management**: Manage marketing landing pages, terms, privacy policies, and public blog posts.
* **Global Error & Exception Monitor**: Centralized exception logging, payload inspection, and diagnostic error reports.
* **Master Geographical Data**: Built-in master datasets for Countries, States, and Cities for clean address management.
* **Platform Inquiries & Support Feedback**: Triage and resolve inbound demo requests, contact inquiries, and gym feedback.

---

## 11. Real-time Infrastructure & System Architecture
* **Laravel Reverb & Echo**: WebSocket broadcasting for instant live member check-ins, push notifications, and counter updates.
* **Redis Caching & Queue Management**: High-throughput background workers handling notifications, webhook ingestion, and batch WhatsApp broadcasts.
* **Filament v3 Component Architecture**: Responsive desktop and mobile interfaces with dark mode support.
* **Strict Type Safety & Quality**: PHP 8.2+ strict typing, constructor property promotion, Pint code styling, and comprehensive PHPUnit test coverage.

---
*Maintained by the Gym Management Platform Engineering Team.*
