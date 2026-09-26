# Codebase Structure

## Core Sections (Required)

### 1) Top-Level Map

List only meaningful top-level directories and files.

| Path | Purpose | Evidence |
|------|---------|----------|
| `app/` | Core application logic (Filament resources, models, services, actions, HTTP controllers, middleware, jobs, biometrics) | [`app/`](file:///c:/xampp/htdocs/projects/gym-app/app) |
| `bootstrap/` | Application bootstrap, routing configuration, exception handling, middleware registration | [`bootstrap/app.php`](file:///c:/xampp/htdocs/projects/gym-app/bootstrap/app.php) |
| `config/` | Application configuration files (app, auth, biometrics, database, filament, queue, services, etc.) | [`config/`](file:///c:/xampp/htdocs/projects/gym-app/config) |
| `database/` | Database migrations, factories, seeders, and SQLite fallback stores | [`database/migrations/`](file:///c:/xampp/htdocs/projects/gym-app/database/migrations) |
| `docker/` | Docker initialization scripts (e.g. MySQL test database creation script) | [`docker/mysql-test-init/`](file:///c:/xampp/htdocs/projects/gym-app/docker/mysql-test-init) |
| `docs/` | Feature and integration documentation (biometric hardware, QR attendance, WhatsApp automation, codebase knowledge) | [`docs/`](file:///c:/xampp/htdocs/projects/gym-app/docs) |
| `public/` | Public web root and entry point (`index.php`), build assets, and uploaded public media | [`public/index.php`](file:///c:/xampp/htdocs/projects/gym-app/public/index.php) |
| `resources/` | Blade templates, Livewire/Volt views, CSS stylesheet (`resources/css/app.css`), and JavaScript entry | [`resources/views/`](file:///c:/xampp/htdocs/projects/gym-app/resources/views) |
| `routes/` | Application route files (`web.php`, `api.php`, `console.php`, `channels.php`, `auth.php`) | [`routes/`](file:///c:/xampp/htdocs/projects/gym-app/routes) |
| `storage/` | Local storage, temporary imports, app logs, framework cache, and generated PDF invoices | [`storage/`](file:///c:/xampp/htdocs/projects/gym-app/storage) |
| `tests/` | PHPUnit automated test suites (`Feature/` and `Unit/`) | [`tests/`](file:///c:/xampp/htdocs/projects/gym-app/tests) |

### 2) Entry Points

- Main runtime entry: `public/index.php` (HTTP web and API requests routed through Laravel's kernel pipeline) [`public/index.php`](file:///c:/xampp/htdocs/projects/gym-app/public/index.php)
- Secondary entry points (worker/cli/jobs):
  - `artisan` (CLI command dispatcher for migrations, custom console commands, cron scheduler) [`artisan`](file:///c:/xampp/htdocs/projects/gym-app/artisan)
  - `routes/console.php` (Cron scheduler definitions for recurring jobs and cleanup tasks) [`routes/console.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/console.php)
  - `routes/channels.php` (Reverb WebSocket channel authorization) [`routes/channels.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/channels.php)
- How entry is selected (script/config): Configured declaratively via `bootstrap/app.php` with routing definitions for web, api, console commands, and channels.

### 3) Module Boundaries

| Boundary | What belongs here | What must not be here |
|----------|-------------------|------------------------|
| `app/Filament/` | Panel-specific resources, pages, widgets, form schemas, table definitions, and UI actions | Low-level hardware drivers, external payment API network requests, direct heavy DB mutation bypassing services |
| `app/Services/` | Domain and business logic orchestration (billing, WhatsApp dispatch, biometric sync, report generators, error reporting) | Direct HTTP request parsing, Filament UI component declarations |
| `app/Biometrics/` | Hardware protocol drivers (ADMS push, ZKTeco, Hikvision ISAPI, BioID, AWS Rekognition, Azure Face) | Generic web controller responses, Blade views |
| `app/Actions/` | Single-responsibility invokable business actions (e.g. member freeze proration, attendance calculations) | Complex multi-step view rendering, form definitions |
| `app/Http/Controllers/` | HTTP request routing, input validation dispatching, response transformation | Heavy database queries, complex payment calculations |
| `app/Models/` | Eloquent entity definitions, typed relations, query scopes, and attribute casts | Direct API requests, email dispatching loops |

### 4) Naming and Organization Rules

- File naming pattern: PascalCase for PHP classes (`MemberResource.php`, `BiometricDeviceController.php`), kebab-case for Blade/Volt views (`member-search.blade.php`, `request-demo.blade.php`), snake_case for database migrations (`2025_01_01_000000_create_gyms_table.php`).
- Directory organization pattern: Layer- and domain-based layout (`app/Filament/{Admin,SuperAdmin,Staff}`, `app/Services/`, `app/Biometrics/{Contracts,Drivers,DTOs}`, `app/Livewire/Frontend/`).
- Import aliasing or path conventions: PSR-4 root autoloading for `App\` mapped to `app/`, `Database\Factories\` mapped to `database/factories/`, `Database\Seeders\` mapped to `database/seeders/`, and `Tests\` mapped to `tests/`.

### 5) Evidence

- [`bootstrap/app.php`](file:///c:/xampp/htdocs/projects/gym-app/bootstrap/app.php)
- [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L65-L76)
- [`app/Filament/`](file:///c:/xampp/htdocs/projects/gym-app/app/Filament)
- [`app/Services/`](file:///c:/xampp/htdocs/projects/gym-app/app/Services)
- [`routes/web.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/web.php)
- [`routes/api.php`](file:///c:/xampp/htdocs/projects/gym-app/routes/api.php)
