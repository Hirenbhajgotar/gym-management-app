# External Integrations

## Core Sections (Required)

### 1) Integration Inventory

| System | Type (API/DB/Queue/etc) | Purpose | Auth model | Criticality | Evidence |
|--------|---------------------------|---------|------------|-------------|----------|
| Razorpay | Payment Gateway REST API & Webhook | Online subscription checkouts, member payment links, credit top-ups | Basic Auth (Key ID + Secret) & HMAC Webhook Signatures | High | [`app/Http/Controllers/Api/RazorpayWebhookController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/RazorpayWebhookController.php), [`config/services.php`](file:///c:/xampp/htdocs/projects/gym-app/config/services.php) |
| Meta Cloud API (WABA) | WhatsApp Business Messaging API | Broadcast campaigns, transactional notifications, embedded signup | OAuth2 Bearer Tokens & Webhook Verification Tokens | High | [`app/Providers/MetaWhatsAppProvider.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/MetaWhatsAppProvider.php), [`routes/api.php#L56`](file:///c:/xampp/htdocs/projects/gym-app/routes/api.php#L56) |
| Twilio WhatsApp & SMS | Messaging Gateway API | WhatsApp notifications, SMS fallback, delivery callbacks | Account SID + Auth Token & HMAC signatures | High | [`app/Providers/TwilioWhatsAppProvider.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/TwilioWhatsAppProvider.php), [`config/services.php`](file:///c:/xampp/htdocs/projects/gym-app/config/services.php) |
| Biometric Hardware (ZKTeco / eSSL / Hikvision) | ADMS HTTP Push Protocol & ISAPI Webhooks | Attendance punch sync, remote template provisioning, door relay unlock | Device serial token auth (`AuthenticateAdmsDevice`, `AuthenticateBiometricDevice`) | High | [`app/Http/Controllers/Api/AdmsController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/AdmsController.php), [`app/Http/Controllers/Api/HikvisionAdmsController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/HikvisionAdmsController.php) |
| Cloud AI Biometrics (AWS Rekognition / Azure Face / BioID) | Cloud Facial Biometric Recognition APIs | Face template verification, liveness checks, consent compliance | AWS IAM Secret / Azure Endpoint Key / BioID SOAP credentials | Medium | [`.env.example#L165-L175`](file:///c:/xampp/htdocs/projects/gym-app/.env.example#L165-L175), [`config/biometrics.php`](file:///c:/xampp/htdocs/projects/gym-app/config/biometrics.php) |
| Meta Lead Ads & Social CRM (Facebook / Instagram) | Social CRM Webhook APIs | Inbound lead ingestion from ads and direct messages | Webhook Hub Secret / App Secret verification | Medium | [`app/Http/Controllers/Api/FacebookLeadController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/FacebookLeadController.php), [`routes/api.php#L48`](file:///c:/xampp/htdocs/projects/gym-app/routes/api.php#L48) |
| Mailtrap / SMTP | Transactional Email Service | System notifications, password reset, error alerts | SMTP credentials | Medium | [`.env.example#L68-L75`](file:///c:/xampp/htdocs/projects/gym-app/.env.example#L68-L75) |

### 2) Data Stores

| Store | Role | Access layer | Key risk | Evidence |
|-------|------|--------------|----------|----------|
| MySQL 8.0 | Primary relational store for all tenant entities, members, attendance, payments | Eloquent ORM & Query Builder | Concurrency locks during high-traffic punch events or checkout | [`config/database.php`](file:///c:/xampp/htdocs/projects/gym-app/config/database.php), [`docker-compose.yml`](file:///c:/xampp/htdocs/projects/gym-app/docker-compose.yml#L38-L54) |
| Redis (Alpine) | Caching, session store, queue backend, Reverb broadcast coordination | `predis/predis` / Laravel Cache & Queue facade | Cache eviction or connection loss halting async jobs | [`config/queue.php`](file:///c:/xampp/htdocs/projects/gym-app/config/queue.php), [`docker-compose.yml`](file:///c:/xampp/htdocs/projects/gym-app/docker-compose.yml#L69-L83) |

### 3) Secrets and Credentials Handling

- Credential sources: Environment variables loaded via `.env` (never hardcoded in source repository), accessed via Laravel `config()` helper.
- Hardcoding checks: Verified no raw API keys or secrets exist in tracked PHP/JS code.
- Rotation or lifecycle notes: Razorpay secrets, Meta access tokens, and Twilio credentials can be refreshed dynamically in `.env` without database schema changes.

### 4) Reliability and Failure Behavior

- Retry/backoff behavior: Queue jobs (`ScheduledReportJob`, `SubscriptionRenewalJob`, WhatsApp broadcast jobs) configure exponential backoff and max retry attempts (`$tries = 3`, `$backoff = [60, 180]`).
- Timeout policy: Webhook processing has strict request timeout thresholds; long-running operations are deferred to Redis background queues.
- Circuit-breaker or fallback behavior: WhatsApp delivery falls back from Meta WABA to Twilio if configured; Biometric check-in supports manual PIN / QR fallback if hardware offline.

### 5) Observability for Integrations

- Logging around external calls: All outgoing and incoming webhook payloads (Razorpay, Meta, Twilio, ADMS punches) are recorded with error context and transaction IDs via `Log::info()` / `Log::error()`.
- Metrics/tracing coverage: Laravel Pulse (`dotswan/filament-laravel-pulse`) and custom Error Monitoring (`ErrorReportService`).
- Missing visibility gaps: External third-party gateway latency spikes (e.g. Meta API downtime) are surfaced via error queue alerts.

### 6) Evidence

- [`config/services.php`](file:///c:/xampp/htdocs/projects/gym-app/config/services.php)
- [`config/biometrics.php`](file:///c:/xampp/htdocs/projects/gym-app/config/biometrics.php)
- [`config/database.php`](file:///c:/xampp/htdocs/projects/gym-app/config/database.php)
- [`app/Http/Controllers/Api/RazorpayWebhookController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/RazorpayWebhookController.php)
- [`app/Http/Controllers/Api/AdmsController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/AdmsController.php)
- [`app/Providers/MetaWhatsAppProvider.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/MetaWhatsAppProvider.php)
- [`app/Providers/TwilioWhatsAppProvider.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/TwilioWhatsAppProvider.php)
