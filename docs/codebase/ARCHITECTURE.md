# Architecture

## Core Sections (Required)

### 1) Architectural Style

- Primary style: Multi-Tenant Layered MVC with Event-Driven Background Workers & Server-Driven UI (Filament + Livewire/Volt)
- Why this classification:
  - Tenancy isolation is enforced at the database and panel level using `gym_id` scoping with Spatie Teams permissions.
  - Three distinct Filament UI panels (`SuperAdmin`, `Admin`, `Staff`) provide server-driven interfaces mapped to user roles.
  - Asynchronous background workers (Redis + Laravel Queues) handle long-running operations (WhatsApp campaigns, scheduled PDF reporting, biometric sync, automated auto-checkout).
  - WebSockets (Laravel Reverb) deliver real-time attendance events and counter increments to browser clients.
- Primary constraints:
  - Multi-tenant data segregation: Gym owners/managers must strictly access only records matching their active `gym_id`.
  - Concurrency safety: Database row locking (`lockForUpdate`) and cache locks prevent race conditions during payment processing, attendance checkout, and membership freezes.
  - Test database isolation: Automated defense-in-depth aborts test execution if the active database is `gym_app` instead of `gym_app_test`.

### 2) System Flow

```text
[HTTP/Webhook/ADMS Request] -> [Middleware (Auth/Tenant/Safety)] -> [Controller/Filament/Volt] -> [Service/Action Layer] -> [Eloquent ORM / DB / Redis] -> [Response / Queue Job / Reverb Event]
```

Trace of a Member Check-in Request (Biometric / QR flow):
1. Entry: Biometric device pushes punch event to `/api/iclock/cdata` or `/api/v1/biometric/check-in` (or member scans rotating QR code at `/attendance/qr-checkin/{gymId}/{token}`).
2. Middleware: `AuthenticateAdmsDevice` or `AuthenticateBiometricDevice` validates device serial/token; `ResolveApiGymTenant` resolves the gym tenant context.
3. Controller / Action: `AdmsController` or `BiometricCheckInController` invokes `BiometricAttendanceService` / `CheckInAction`.
4. Domain Validation & Locking: Service checks member status (`Active`, `Frozen`, `Expired`), acquires transaction lock (`lockForUpdate`), records `Attendance` record with auto-checkout determination.
5. Integration & Dispatch: If door relay is configured, triggers hardware unlock; dispatches WebSocket event `MemberCheckedIn` via Laravel Reverb to live receptionist dashboard; updates occupancy counter in Redis.

### 3) Layer/Module Responsibilities

| Layer or module | Owns | Must not own | Evidence |
|-----------------|------|--------------|----------|
| `Filament Panels` (`SuperAdmin`, `Admin`, `Staff`) | Role-based navigation, table filters, form schemas, interactive modals, and widgets | Direct hardware sockets, external third-party HTTP communication loops | [`app/Providers/Filament/`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/Filament) |
| `Controllers` (`Http/Controllers/`) | Routing endpoints, request validation orchestration, HTTP response status formatting | Core business calculations, direct un-scoped database queries | [`app/Http/Controllers/`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers) |
| `Services` (`app/Services/`) | High-level business logic (payments, WhatsApp campaigns, membership freezing, error reporting) | Direct HTML rendering or Blade views | [`app/Services/`](file:///c:/xampp/htdocs/projects/gym-app/app/Services) |
| `Biometrics Engine` (`app/Biometrics/`) | Vendor protocol normalization (ZKTeco ADMS, Hikvision ISAPI, BioID, AWS Rekognition), command queuing | Payment gateway processing, UI rendering | [`app/Biometrics/`](file:///c:/xampp/htdocs/projects/gym-app/app/Biometrics) |
| `Models & Scopes` (`app/Models/`) | Database schema definitions, relationship methods, query scopes (`scopeActive`), attribute casting | HTTP request parsing, sending SMS/WhatsApp network calls | [`app/Models/`](file:///c:/xampp/htdocs/projects/gym-app/app/Models) |
| `Jobs & Queue Listeners` (`app/Jobs/`) | Asynchronous report generation, WhatsApp chunked broadcasts, subscription reconciliation | Synchronous UI blocking responses | [`app/Jobs/`](file:///c:/xampp/htdocs/projects/gym-app/app/Jobs) |

### 4) Reused Patterns

| Pattern | Where found | Why it exists |
|---------|-------------|---------------|
| Factory / Provider Pattern | `app/Providers/WhatsAppProviderFactory.php`, `app/Biometrics/` | Allows switching between Meta WABA and Twilio WhatsApp, or ZKTeco and Hikvision drivers dynamically |
| Action Pattern (Single-Responsibility) | `app/Actions/` | Encapsulates reusable multi-step mutations (e.g. membership freeze calculations, attendance auto-checkout) |
| Middleware-Based Tenant Scoping | `app/Http/Middleware/ResolveApiGymTenant.php`, `TeamsPermission.php` | Ensures strict tenant isolation across API and web panel routes |
| Asynchronous Command Queue | `App\Models\AdmsDeviceCommand`, `AdmsController` | Queues and reliably dispatches FIFO configuration and user sync commands to remote edge devices |
| Defense-in-Depth DB Safety Guard | `bootstrap/app.php#L185`, `tests/TestCase.php` | Throws runtime exception if tests attempt to execute against the primary `gym_app` database |

### 5) Known Architectural Risks

- Edge Device Network Latency & Offline Status: Biometric terminals in remote gyms may disconnect, leading to delayed push punches or stuck commands. Mitigated via `AdmsOfflineSync` and `biometrics:check-health` scheduler.
- High-Volume WhatsApp Broadcast Throttling: Bulk messaging without rate limits risks Meta/Twilio rate-limit throttling or account ban. Mitigated via queue chunking and `whatsapp:process-scheduled` rate-limited workers.

### 6) Evidence

- [`bootstrap/app.php`](file:///c:/xampp/htdocs/projects/gym-app/bootstrap/app.php)
- [`app/Providers/Filament/AdminPanelProvider.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/Filament/AdminPanelProvider.php)
- [`app/Providers/Filament/StaffPanelProvider.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/Filament/StaffPanelProvider.php)
- [`app/Providers/Filament/SuperAdminPanelProvider.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/Filament/SuperAdminPanelProvider.php)
- [`app/Providers/WhatsAppProviderFactory.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Providers/WhatsAppProviderFactory.php)
- [`app/Http/Middleware/ResolveApiGymTenant.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Middleware/ResolveApiGymTenant.php)
- [`routes/api.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/api.php)
- [`routes/console.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/console.php)
