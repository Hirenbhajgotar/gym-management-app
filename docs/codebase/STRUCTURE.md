# Codebase Structure

## Core Sections (Required)

### 1) Top-Level Map

List only meaningful top-level directories and files.

| Path | Purpose | Evidence |
|------|---------|----------|
| `app/` | Core application logic (Filament resources/actions, models, services, actions, HTTP controllers, middleware, jobs, biometrics engine) | [`app/`](file:///c:/xampp/htdocs/projects/gym-app/app) |
| `bootstrap/` | Application bootstrap, routing configuration, exception handling, middleware registration | [`bootstrap/app.php`](file:///c:/xampp/htdocs/projects/gym-app/bootstrap/app.php) |
| `config/` | Application configuration files (app, auth, biometrics, database, filament, queue, services, etc.) | [`config/`](file:///c:/xampp/htdocs/projects/gym-app/config) |
| `database/` | Database migrations, factories, seeders, and SQLite fallback stores | [`database/migrations/`](file:///c:/xampp/htdocs/projects/gym-app/database/migrations) |
| `docker/` | Docker initialization scripts (e.g. MySQL test database creation script) | [`docker/mysql-test-init/`](file:///c:/xampp/htdocs/projects/gym-app/docker/mysql-test-init) |
| `docs/` | Architecture Decision Records (`docs/adr/`), biometric hardware guides (`docs/biometric/`), QR attendance, and codebase knowledge (`docs/codebase/`) | [`docs/`](file:///c:/xampp/htdocs/projects/gym-app/docs), [`docs/adr/`](file:///c:/xampp/htdocs/projects/gym-app/docs/adr) |
| `openspec/` | System specification and capability change proposals (`openspec/changes/`) | [`openspec/changes/`](file:///c:/xampp/htdocs/projects/gym-app/openspec/changes) |
| `public/` | Public web root and entry point (`index.php`), build assets, and uploaded public media | [`public/index.php`](file:///c:/xampp/htdocs/projects/gym-app/public/index.php) |
| `resources/` | Blade templates, Livewire/Volt views, CSS stylesheet (`resources/css/app.css`), and JavaScript entry | [`resources/views/`](file:///c:/xampp/htdocs/projects/gym-app/resources/views) |
| `routes/` | Application route files (`web.php`, `api.php`, `console.php`, `channels.php`, `auth.php`) | [`routes/`](file:///c:/xampp/htdocs/projects/gym-app/routes) |
| `storage/` | Local storage, temporary imports, app logs, framework cache, and generated PDF invoices | [`storage/`](file:///c:/xampp/htdocs/projects/gym-app/storage) |
| `tests/` | PHPUnit automated test suites (`Feature/` and `Unit/`) | [`tests/`](file:///c:/xampp/htdocs/projects/gym-app/tests) |

### 2) Entry Points

- Main runtime entry: `public/index.php` (HTTP web and API requests routed through Laravel's kernel pipeline) [`public/index.php`](file:///c:/xampp/htdocs/projects/gym-app/public/index.php)
- Biometric Edge device entry points:
  - `/api/iclock/cdata` (Handshake and `ATTLOG` attendance push) [`app/Http/Controllers/Api/AdmsController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/AdmsController.php)
  - `/api/iclock/getrequest` (Edge terminal command polling and atomic lease acquisition) [`app/Http/Controllers/Api/AdmsController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/AdmsController.php)
  - `/api/iclock/devicecmd` (Edge terminal command execution acknowledgments) [`app/Http/Controllers/Api/AdmsController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/AdmsController.php)
  - `/api/v1/biometric/device/sync-from-device` (Hardware biometric capture confirmation callback promoting Pending placeholders to Synced) [`app/Http/Controllers/Api/BiometricEnrollController.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Http/Controllers/Api/BiometricEnrollController.php)
- Secondary entry points (worker/cli/jobs):
  - `artisan` (CLI command dispatcher for migrations, custom console commands, cron scheduler) [`artisan`](file:///c:/xampp/htdocs/projects/gym-app/artisan)
  - `routes/console.php` (Cron scheduler definitions: `biometrics:reconcile-enrollments`, `biometrics:cleanup-superseded-templates`, `biometrics:reclaim-stale-commands`, `whatsapp:process-scheduled`) [`routes/console.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/console.php)
  - `routes/channels.php` (Reverb WebSocket channel authorization) [`routes/channels.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/channels.php)
- How entry is selected (script/config): Configured declaratively via `bootstrap/app.php` with routing definitions for web, api, console commands, and channels.

### 3) Module Boundaries

| Boundary | What belongs here | What must not be here |
|----------|-------------------|------------------------|
| `app/Filament/` | Panel-specific resources, pages, widgets, form schemas, table definitions, and UI actions (e.g. `EnrollBiometricAction` modal) | Low-level hardware drivers, external payment API network requests, direct un-scoped heavy DB mutations |
| `app/Biometrics/` | Hardware protocol drivers (`ADMS`, `ISAPI`), domain services (`BiometricTemplatePropagator`, `BiometricTemplateSuperseder`, `BiometricDeProvisioner`), value objects (`AdmsCommandKey`, `BiometricPropagationResult`), exceptions (`BiometricConsentRequiredException`) | Direct HTTP request responses, Blade views, payment handling |
| `app/Services/` | Cross-domain business logic orchestration (billing, WhatsApp dispatch, attendance calculations, error reporting) | Direct HTTP request parsing, Filament UI component declarations |
| `app/Actions/` | Single-responsibility invokable business actions (e.g. member freeze proration, attendance auto-checkout) | Complex multi-step view rendering, form definitions |
| `app/Http/Controllers/` | HTTP request routing, input validation dispatching, response transformation, edge device webhooks/callbacks | Heavy database calculations, direct domain mutations bypassing services |
| `app/Models/` | Eloquent entity definitions, typed relations, query scopes (`scopeActive`, `scopeDelivered`), attribute casts, atomic CAS leasing logic (`leaseNext`) | Direct API requests, email dispatching loops |
| `app/Console/Commands/` | Scheduled background synchronizers, reconcilers (`ReconcileBiometricEnrollmentsCommand`), expired credential purgers, lease monitors | Synchronous interactive UI logic |

### 4) Naming and Organization Rules

- File naming pattern: PascalCase for PHP classes (`MemberResource.php`, `EnrollBiometricAction.php`, `BiometricTemplatePropagator.php`), kebab-case for Blade/Volt views (`member-search.blade.php`), snake_case for database migrations (`2026_10_07_000000_index_deleted_at_on_biometric_templates_table.php`).
- Biometrics domain organization: Layered domain architecture under `app/Biometrics/`:
  - `app/Biometrics/Services/` for orchestration workflows (`BiometricTemplatePropagator`, `BiometricTemplateSuperseder`, `BiometricDeProvisioner`)
  - `app/Biometrics/ValueObjects/` for immutable domain types (`AdmsCommandKey`, `BiometricPropagationResult`)
  - `app/Biometrics/Exceptions/` for domain-specific failures (`BiometricConsentRequiredException`)
  - `app/Biometrics/Contracts/` & `Drivers/` for vendor hardware protocol abstraction
- Canonical wire key convention: Colon-delimited format parsed strictly in `AdmsCommandKey`: `user_add:{gymId}:{deviceId}:{memberId}[:{stage}]:{templateId}` and `user_delete:{deviceId}:{pin}`.
- Import aliasing or path conventions: PSR-4 root autoloading for `App\` mapped to `app/`, `Database\Factories\` mapped to `database/factories/`, `Database\Seeders\` mapped to `database/seeders/`, and `Tests\` mapped to `tests/`.

### 5) Evidence

- [`bootstrap/app.php`](file:///c:/xampp/htdocs/projects/gym-app/bootstrap/app.php)
- [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L65-L76)
- [`app/Biometrics/`](file:///c:/xampp/htdocs/projects/gym-app/app/Biometrics)
- [`app/Filament/Admin/Actions/EnrollBiometricAction.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Actions/EnrollBiometricAction.php)
- [`docs/adr/adr-0002-biometric-enrollment-and-device-reconciliation.md`](file:///c:/xampp/htdocs/projects/gym-app/docs/adr/adr-0002-biometric-enrollment-and-device-reconciliation.md)
- [`openspec/changes/2026-10-01-biometric-enrollment-lifecycle.md`](file:///c:/xampp/htdocs/projects/gym-app/openspec/changes/2026-10-01-biometric-enrollment-lifecycle.md)
- [`routes/web.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/web.php)
- [`routes/api.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/api.php)
- [`routes/console.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/console.php)
