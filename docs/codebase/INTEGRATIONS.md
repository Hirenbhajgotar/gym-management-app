# External Integrations

## Core Sections (Required)

### 1) Integration Inventory

| System | Type (API/DB/Queue/etc) | Purpose | Auth model | Criticality | Evidence |
|--------|---------------------------|---------|------------|-------------|----------|
| Razorpay | Payment Gateway REST API & Webhook | Online subscription checkouts, member payment links, credit top-ups | Basic Auth (Key ID + Secret) & HMAC Webhook Signatures | High | [`app/Http/Controllers/Api/RazorpayWebhookController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/RazorpayWebhookController.php), [`config/services.php`](file:///c:/xampp/htdocs/projects/gym-app/config/services.php) |
| Meta Cloud API (WABA) | WhatsApp Business Messaging API | Broadcast campaigns, transactional notifications, embedded signup | OAuth2 Bearer Tokens & Webhook Verification Tokens | High | [`app/Providers/MetaWhatsAppProvider.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/MetaWhatsAppProvider.php), [`routes/api.php#L56`](file:///c:/xampp/htdocs/projects/gym-app/routes/api.php#L56) |
| Twilio WhatsApp & SMS | Messaging Gateway API | WhatsApp notifications, SMS fallback, delivery callbacks | Account SID + Auth Token & HMAC signatures | High | [`app/Providers/TwilioWhatsAppProvider.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/TwilioWhatsAppProvider.php), [`config/services.php`](file:///c:/xampp/htdocs/projects/gym-app/config/services.php) |
| Biometric Hardware (ZKTeco / eSSL / Hikvision) | ADMS HTTP Push Protocol & ISAPI Webhooks | Attendance punch sync, two-phase biometric enrollment (`EnrollBiometricAction`), lateral sibling propagation (`BiometricTemplatePropagator`), and door relay unlock | Per-device PBKDF2 comm key (keyed terminals) or hardware Serial Number (unkeyed terminals); optional server-wide gate `ADMS_REQUIRE_COMM_KEY=true`; serial token auth (`AuthenticateAdmsDevice`, `AuthenticateBiometricDevice`) | High | [`app/Http/Controllers/Api/AdmsController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/AdmsController.php), [`app/Http/Controllers/Api/BiometricEnrollController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/BiometricEnrollController.php), [`app/Biometrics/Services/BiometricTemplatePropagator.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Biometrics/Services/BiometricTemplatePropagator.php) |
| Cloud AI Biometrics (AWS Rekognition / Azure Face / BioID) | Cloud Facial Biometric Recognition APIs | Face template verification, liveness checks, consent compliance | AWS IAM Secret / Azure Endpoint Key / BioID SOAP credentials | Medium | [`.env.example#L165-L175`](file:///c:/xampp/htdocs/projects/gym-app/.env.example#L165-L175), [`config/biometrics.php`](file:///c:/xampp/htdocs/projects/gym-app/config/biometrics.php) |
| Meta Lead Ads & Social CRM (Facebook / Instagram) | Social CRM Webhook APIs | Inbound lead ingestion from ads and direct messages | Webhook Hub Secret / App Secret verification | Medium | [`app/Http/Controllers/Api/FacebookLeadController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/FacebookLeadController.php), [`routes/api.php#L48`](file:///c:/xampp/htdocs/projects/gym-app/routes/api.php#L48) |
| Mailtrap / SMTP | Transactional Email Service | System notifications, password reset, error alerts | SMTP credentials | Medium | [`.env.example#L68-L75`](file:///c:/xampp/htdocs/projects/gym-app/.env.example#L68-L75) |

### 2) Data Stores

| Store | Role | Access layer | Key risk | Evidence |
|-------|------|--------------|----------|----------|
| MySQL 8.0 | Primary relational store for all tenant entities, members, attendance, payments, biometric templates (`biometric_templates` with `hardware_confirmed_at`, `deleted_at`), and device command queues (`adms_device_commands`) | Eloquent ORM & Query Builder | Concurrency locks during high-traffic punch events or checkout; unique constraints on canonical idempotency keys | [`config/database.php`](file:///c:/xampp/htdocs/projects/gym-app/config/database.php), [`docker-compose.yml`](file:///c:/xampp/htdocs/projects/gym-app/docker-compose.yml#L38-L54), [`app/Models/BiometricTemplate.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Models/BiometricTemplate.php) |
| Redis (Alpine) | Caching, session store, queue backend, Reverb broadcast coordination | `predis/predis` / Laravel Cache & Queue facade | Cache eviction or connection loss halting async jobs | [`config/queue.php`](file:///c:/xampp/htdocs/projects/gym-app/config/queue.php), [`docker-compose.yml`](file:///c:/xampp/htdocs/projects/gym-app/docker-compose.yml#L69-L83) |

### 3) Secrets and Credentials Handling

- Credential sources: Environment variables loaded via `.env` (never hardcoded in source repository), accessed via Laravel `config()` helper.
- Biometric Communication Keys: authentication is negotiated per device (`config('biometrics.adms.require_comm_key')`, default `false`). Keyed terminals must present a PBKDF2 salted communication key (iterations from `config('biometrics.adms.pbkdf2_iterations')`, default 100,000); unkeyed terminals authenticate by hardware Serial Number. Set `ADMS_REQUIRE_COMM_KEY=true` to refuse every unkeyed terminal server-wide. Legacy unsalted SHA-256 hashes are detected and re-provisioned via `biometrics:backfill-adms-comm-keys --reprovision-legacy --reveal`.
- Hardcoding checks: Verified no raw API keys or secrets exist in tracked PHP/JS code.
- Rotation or lifecycle notes: Razorpay secrets, Meta access tokens, Twilio credentials, and ADMS comm keys can be rotated without breaking database schemas.

### 4) Reliability and Failure Behavior

- Biometric Edge Command Leasing & Retry Cap: Commands are leased via `AdmsDeviceCommand::leaseNext()` with a configurable lease duration (`ADMS_COMMAND_LEASE_SECONDS`, default 600s). Stale leases are reclaimed via `biometrics:reclaim-stale-commands`. Attempts are strictly capped at `AdmsCommandKey::maxAttempts()` (default 3); exceeding attempts triggers dead-letter halting to protect terminal flash memory endurance.
- Deletion Convergence: When a `user_delete` command is executed on hardware and the terminal returns a code indicating the user is not found (e.g. `-1001`), the command converges to `ACKNOWLEDGED` to guarantee GDPR Art. 17 erasure durability without stalling the pipeline.
- Sibling Fan-Out Resilience: In `BiometricTemplatePropagator`, unique constraint collisions on canonical idempotency keys are caught gracefully, and failed sibling provisions record `FAILED` commands allowing manual or automated retry.
- Circuit-breaker or fallback behavior: WhatsApp delivery falls back from Meta WABA to Twilio if configured; Biometric check-in supports manual PIN / QR fallback if hardware is offline.

### 5) Observability for Integrations

- Logging around external calls: All outgoing and incoming webhook payloads (Razorpay, Meta, Twilio, ADMS punches) are recorded with error context and transaction IDs via `Log::info()` / `Log::error()`.
- PII and Biometric Log Redaction: `BiometricLogRedactor` redacts raw fingerprint/face vectors, template strings, and device credentials from logs.
- Metrics/tracing coverage: Laravel Pulse (`dotswan/filament-laravel-pulse`) and custom Error Monitoring (`ErrorReportService`).
- Reconciler Observability: `biometrics:reconcile-enrollments` provides detailed CLI and log reporting on scanned, reconciled, skipped (consent, membership, deletion in-flight), and dead-lettered templates.

### 6) Evidence

- [`config/services.php`](file:///c:/xampp/htdocs/projects/gym-app/config/services.php)
- [`config/biometrics.php`](file:///c:/xampp/htdocs/projects/gym-app/config/biometrics.php)
- [`config/database.php`](file:///c:/xampp/htdocs/projects/gym-app/config/database.php)
- [`app/Http/Controllers/Api/AdmsController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/AdmsController.php)
- [`app/Http/Controllers/Api/BiometricEnrollController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/BiometricEnrollController.php)
- [`app/Biometrics/Services/BiometricTemplatePropagator.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Biometrics/Services/BiometricTemplatePropagator.php)
- [`app/Biometrics/ValueObjects/AdmsCommandKey.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Biometrics/ValueObjects/AdmsCommandKey.php)
- [`app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php)
- [`app/Providers/MetaWhatsAppProvider.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/MetaWhatsAppProvider.php)
- [`app/Providers/TwilioWhatsAppProvider.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/TwilioWhatsAppProvider.php)
