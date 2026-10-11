# Architecture

## Core Sections (Required)

### 1) Architectural Style

- Primary style: Multi-Tenant Layered MVC with Event-Driven Background Workers, Server-Driven UI (Filament + Livewire/Volt), and Asynchronous Edge Device Command Leasing
- Why this classification:
  - Tenancy isolation is enforced at the database and panel level using `gym_id` scoping with Spatie Teams permissions.
  - Three distinct Filament UI panels (`SuperAdmin`, `Admin`, `Staff`) provide server-driven interfaces mapped to user roles.
  - Asynchronous background workers (Redis + Laravel Queues) handle long-running operations (WhatsApp campaigns, scheduled PDF reporting, biometric sync, automated auto-checkout).
  - Biometric edge terminals communicate asynchronously via polling (`/api/iclock/getrequest`) using a two-phase reservation pattern (Pending -> Synced promotion) and CAS command leasing.
  - Lateral sibling propagation automatically synchronizes enrolled credentials across all active turnstiles/doors within a tenant gym without manual operator re-entry.
  - WebSockets (Laravel Reverb) deliver real-time attendance events and counter increments to browser clients.
- Primary constraints:
  - Multi-tenant data segregation: Gym owners/managers must strictly access only records matching their active `gym_id`.
  - Concurrency safety & idempotency: Row-level database locks (`lockForUpdate`), cache locks, and canonical deterministic idempotency keys (`AdmsCommandKey`) prevent duplicate command insertion and race conditions during payment processing, attendance checkout, and biometric provisioning.
  - Legal consent & Anti-Resurrection Access Control: DPDP Act 2023 & GDPR Art. 17 mandate active cryptographic consent before enrolling biometrics. In-flight `user_delete` commands take absolute priority over enrollment or reconciliation commands to prevent unauthorized access resurrection.
  - Test database isolation: Automated defense-in-depth aborts test execution if the active database is `gym_app` instead of `gym_app_test`.

### 2) System Flow

```text
[HTTP/Webhook/ADMS Request] -> [Middleware (Auth/Tenant/Safety)] -> [Controller/Filament/Volt] -> [Service/Action Layer] -> [Eloquent ORM / DB / Redis] -> [Response / Queue Job / Reverb Event]
```

#### Flow A: Member Attendance Check-in Request (Biometric / QR flow)
1. Entry: Biometric device pushes punch event to `/api/iclock/cdata` or `/api/v1/biometric/check-in` (or member scans rotating QR code at `/attendance/qr-checkin/{gymId}/{token}`).
2. Middleware: `AuthenticateAdmsDevice` or `AuthenticateBiometricDevice` validates device serial/token; `ResolveApiGymTenant` resolves the gym tenant context.
3. Controller / Action: `AdmsController` or `BiometricCheckInController` invokes `BiometricAttendanceService` / `CheckInAction`.
4. Domain Validation & Locking: Service checks member status (`Active`, `Frozen`, `Expired`), acquires transaction lock (`lockForUpdate`), records `Attendance` record with auto-checkout determination.
5. Integration & Dispatch: If door relay is configured, triggers hardware unlock; dispatches WebSocket event `MemberCheckedIn` via Laravel Reverb to live receptionist dashboard; updates occupancy counter in Redis.

#### Flow B: Two-Phase Biometric Enrollment & Sibling Propagation Flow
1. Initiation (Filament UI): Operator opens member table or profile and clicks `EnrollBiometricAction` modal in [`app/Filament/Admin/Actions/EnrollBiometricAction.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Actions/EnrollBiometricAction.php).
2. Pre-Flight Consent & Deletion Guard: Inside a DB transaction, `$member->hasActiveBiometricConsent(fresh: true)` verifies consent. `AdmsDeviceCommand::cancelDeleteCommands()` purges pending/in-flight `user_delete` commands for the member's PIN on the target device.
3. Modality Superseding & Placeholder Creation: `BiometricTemplateSuperseder` retires obsolete templates of the identical biometric type with `superseded_reason`. A placeholder `BiometricTemplate` is created with `sync_status = 'pending'`, `is_active = false`, and `hardware_confirmed_at = null`.
4. Command Queueing: Queues an `AdmsDeviceCommand` of type `user_add` (`Pin`, `Name`) with a canonical idempotency key (`user_add:{gymId}:{deviceId}:{memberId}:{templateId}`). Preceding in-flight commands are cancelled via `AdmsDeviceCommand::cancelInFlightFor()`.
5. Edge Polling & Hardware Capture: The target physical terminal polls `/api/iclock/getrequest`. `AdmsDeviceCommand::leaseNext()` acquires an atomic lease (status `SENT`, `attempt_count` incremented). The member touches the fingerprint/face sensor on the physical terminal.
6. Edge Confirmation (Anti-TOCTOU): The terminal pushes `/api/v1/biometric/device/sync-from-device` to [`app/Http/Controllers/Api/BiometricEnrollController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/BiometricEnrollController.php). Consent is re-verified inside the transaction.
7. Promotion & Sibling Fan-Out: The template is promoted to `sync_status = 'synced'`, `is_active = true`, and stamped with `hardware_confirmed_at = now()`. Within the same transaction, `BiometricTemplatePropagator` provisions sibling `BiometricTemplate` rows (`sync_status = 'propagated'`) across all other active gym ADMS devices and queues `user_add` commands tagged with `STAGE_SYNC`.
8. Self-Healing Reconciliation: Scheduled nightly command [`app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php) sweeps active `Synced` and `Propagated` templates, checking 5 safety guards and re-driving missing/failed commands with `STAGE_RECONCILE` or halting on dead-letters.

### 3) Layer/Module Responsibilities

| Layer or module | Owns | Must not own | Evidence |
|-----------------|------|--------------|----------|
| `Filament Panels` (`SuperAdmin`, `Admin`, `Staff`) | Role-based navigation, table filters, form schemas, interactive modals (`EnrollBiometricAction`), and widgets | Direct hardware sockets, external third-party HTTP communication loops | [`app/Providers/Filament/`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/Filament), [`app/Filament/Admin/Actions/EnrollBiometricAction.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Actions/EnrollBiometricAction.php) |
| `Controllers` (`Http/Controllers/`) | Routing endpoints, request validation orchestration, HTTP response status formatting, edge sync callbacks (`BiometricEnrollController`) | Core business calculations, direct un-scoped database queries | [`app/Http/Controllers/`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers), [`app/Http/Controllers/Api/BiometricEnrollController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/BiometricEnrollController.php) |
| `Services` (`app/Services/`) | High-level business logic (payments, WhatsApp campaigns, membership freezing, error reporting) | Direct HTML rendering or Blade views | [`app/Services/`](file:///c:/xampp/htdocs/projects/gym-app/app/Services) |
| `Biometrics Engine` (`app/Biometrics/`) | Vendor protocol normalization (ZKTeco ADMS, Hikvision ISAPI), template propagation (`BiometricTemplatePropagator`), superseding (`BiometricTemplateSuperseder`), hardware de-provisioning (`BiometricDeProvisioner`), canonical idempotency keys (`AdmsCommandKey`) | Payment gateway processing, UI rendering | [`app/Biometrics/`](file:///c:/xampp/htdocs/projects/gym-app/app/Biometrics) |
| `Models & Scopes` (`app/Models/`) | Database schema definitions, typed relations, query scopes (`scopeActive`, `scopeDelivered`), attribute casting, atomic command leasing (`leaseNext`) | HTTP request parsing, sending SMS/WhatsApp network calls | [`app/Models/`](file:///c:/xampp/htdocs/projects/gym-app/app/Models), [`app/Models/AdmsDeviceCommand.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Models/AdmsDeviceCommand.php) |
| `Console Commands` (`app/Console/Commands/`) | CLI commands for scheduling, reconciliation (`ReconcileBiometricEnrollmentsCommand`), expired member de-provisioning, lease reclamation | Synchronous web request responses | [`app/Console/Commands/`](file:///c:/xampp/htdocs/projects/gym-app/app/Console/Commands) |
| `Jobs & Queue Listeners` (`app/Jobs/`) | Asynchronous report generation, WhatsApp chunked broadcasts, subscription reconciliation | Synchronous UI blocking responses | [`app/Jobs/`](file:///c:/xampp/htdocs/projects/gym-app/app/Jobs) |

### 4) Reused Patterns

| Pattern | Where found | Why it exists |
|---------|-------------|---------------|
| Canonical Idempotency Key | `app/Biometrics/ValueObjects/AdmsCommandKey.php` | Enforces parseable, collision-free wire keys (`user_add:{gymId}:{deviceId}:{memberId}[:stage]:{templateId}`) preventing duplicate command inserts and enabling stage tracking |
| Two-Phase Reservation / Confirmation | `EnrollBiometricAction.php`, `BiometricEnrollController.php` | Separates initial software enrollment intent (`Pending` placeholder) from physical biometric hardware capture (`Synced` + `hardware_confirmed_at`) |
| Lateral Sibling Fan-Out | `app/Biometrics/Services/BiometricTemplatePropagator.php` | Automatically synchronizes captured credentials to all other active ADMS turnstiles/doors within the same tenant gym |
| Atomic CAS Command Leasing | `app/Models/AdmsDeviceCommand.php#L220-L290` | Prevents race conditions during edge terminal polling; leases commands via atomic `lockForUpdate()` state transitions (`PENDING` -> `SENT`) |
| Deletion Convergence | `app/Models/AdmsDeviceCommand.php#L350-L375` | Maps missing-user terminal return codes (`-1001`, `unknown user`) to `ACKNOWLEDGED`, ensuring GDPR Art. 17 erasure completion |
| Self-Healing Reconciler & Circuit Breaker | `app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php` | Automatically repairs edge-cloud drift while capping delivery attempts (`maxAttempts`, default 3) to protect terminal flash memory |
| Defense-in-Depth DB Safety Guard | `bootstrap/app.php#L185`, `tests/TestCase.php` | Throws runtime exception if tests attempt to execute against the primary `gym_app` database |

### 5) Known Architectural Risks

- Edge Terminal Flash Memory Write Wear: Unbounded retry loops or un-scoped cron re-drives could exhaust embedded flash memory write endurance on edge terminals. Mitigated via strict `attempt_count >= maxAttempts` dead-letter circuit breakers in `AdmsCommandKey` and `ReconcileBiometricEnrollmentsCommand`.
- Anti-Resurrection Access Control Race: An in-flight or pending `user_delete` command must never be overwritten or out-raced by a re-enrollment or reconciliation command. Mitigated via PIN-scoped deletion checks, mandatory membership expiry guards, and `AdmsDeviceCommand::cancelDeleteCommands()`.
- Edge Device Network Latency & Offline Status: Biometric terminals in remote gyms may disconnect, leading to delayed push punches or stuck commands. Mitigated via `AdmsOfflineSync`, command lease reclamation (`biometrics:reclaim-stale-commands`), and health monitor scheduler.
- High-Volume WhatsApp Broadcast Throttling: Bulk messaging without rate limits risks Meta/Twilio rate-limit throttling or account ban. Mitigated via queue chunking and `whatsapp:process-scheduled` rate-limited workers.

### 6) Evidence

- [`bootstrap/app.php`](file:///c:/xampp/htdocs/projects/gym-app/bootstrap/app.php)
- [`docs/adr/adr-0002-biometric-enrollment-and-device-reconciliation.md`](file:///c:/xampp/htdocs/projects/gym-app/docs/adr/adr-0002-biometric-enrollment-and-device-reconciliation.md)
- [`app/Filament/Admin/Actions/EnrollBiometricAction.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Actions/EnrollBiometricAction.php)
- [`app/Biometrics/ValueObjects/AdmsCommandKey.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Biometrics/ValueObjects/AdmsCommandKey.php)
- [`app/Biometrics/Services/BiometricTemplatePropagator.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Biometrics/Services/BiometricTemplatePropagator.php)
- [`app/Biometrics/Services/BiometricTemplateSuperseder.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Biometrics/Services/BiometricTemplateSuperseder.php)
- [`app/Biometrics/Services/BiometricDeProvisioner.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Biometrics/Services/BiometricDeProvisioner.php)
- [`app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php)
- [`app/Models/AdmsDeviceCommand.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Models/AdmsDeviceCommand.php)
- [`app/Models/BiometricTemplate.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Models/BiometricTemplate.php)
- [`app/Http/Controllers/Api/BiometricEnrollController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/BiometricEnrollController.php)
- [`routes/api.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/api.php)
- [`routes/console.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/console.php)
