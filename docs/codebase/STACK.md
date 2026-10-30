# Technology Stack

## Core Sections (Required)

### 1) Runtime Summary

| Area | Value | Evidence |
|------|-------|----------|
| Primary language | PHP 8.2+ & JavaScript/Node 20+ | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L12), [`Dockerfile`](file:///c:/xampp/htdocs/projects/gym-app/Dockerfile#L1) |
| Runtime + version | PHP 8.3-fpm, Node 20.x, Nginx alpine | [`Dockerfile`](file:///c:/xampp/htdocs/projects/gym-app/Dockerfile#L1-L28), [`docker-compose.yml`](file:///c:/xampp/htdocs/projects/gym-app/docker-compose.yml#L1-L37) |
| Package manager | Composer (PHP), NPM (Node.js) | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json), [`package.json`](file:///c:/xampp/htdocs/projects/gym-app/package.json) |
| Module/build system | Vite 6 + Tailwind CSS v4 Vite plugin | [`package.json`](file:///c:/xampp/htdocs/projects/gym-app/package.json#L9-L16), [`vite.config.js`](file:///c:/xampp/htdocs/projects/gym-app/vite.config.js) |

### 2) Production Frameworks and Dependencies

List only high-impact production dependencies (frameworks, data, transport, auth).

| Dependency | Version | Role in system | Evidence |
|------------|---------|----------------|----------|
| `laravel/framework` | `^12.0` | Core full-stack PHP framework & routing | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L25) |
| `filament/filament` | `^3.3` | Multi-panel administration and dashboard UI | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L20) |
| `livewire/volt` | `^1.7.0` | Single-file reactive frontend components | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L31) |
| `livewire/flux` | `^2.1.1` | Flux UI component library | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L30) |
| `spatie/laravel-permission` | `^6.20` | Team/Tenant-scoped Role-Based Access Control | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L44) |
| `laravel/sanctum` | `^4.0` | API Token Authentication | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L28) |
| `laravel/reverb` | `^1.0` | Real-time WebSocket broadcasting server | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L27) |
| `razorpay/razorpay` | `2.*` | Payment gateway and subscription checkout | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L40) |
| `twilio/sdk` | `^8.8` | Twilio WhatsApp & SMS notification delivery | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L47) |
| `facebook/graph-sdk` | `*` | Meta Cloud API / WABA Embedded Signup | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L18) |
| `predis/predis` | `^3.2` | Redis client for caching, sessions, and queues | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L38) |
| `maatwebsite/excel` | `^3.1` | Excel / CSV data exports and bulk imports | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L32) |
| `barryvdh/laravel-dompdf` | `^3.1` | PDF generation for invoices, receipts, and reports | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L14) |
| `spatie/laravel-backup` | `^9.3` | Scheduled database & file backups | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L43) |

### 3) Development Toolchain

| Tool | Purpose | Evidence |
|------|---------|----------|
| `phpunit/phpunit` (v11) | Automated Unit & Feature testing runner | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L63), [`phpunit.xml`](file:///c:/xampp/htdocs/projects/gym-app/phpunit.xml) |
| `larastan/larastan` (v3 / max) | Static PHP type analysis & rule enforcement | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L55), [`phpstan.neon`](file:///c:/xampp/htdocs/projects/gym-app/phpstan.neon) |
| `laravel/pint` (v1) | PHP code style & formatting | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L58), [`pint.json`](file:///c:/xampp/htdocs/projects/gym-app/pint.json) |
| `driftingly/rector-laravel` (v2) | Automated PHP refactoring and upgrades | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L53), [`rector.php`](file:///c:/xampp/htdocs/projects/gym-app/rector.php) |
| `barryvdh/laravel-debugbar` | In-browser debugging & query inspection | [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L51) |
| `tailwindcss` (v4) | Utility-first styling engine | [`package.json`](file:///c:/xampp/htdocs/projects/gym-app/package.json#L14) |
| `concurrently` (v9) | Multi-process dev runner (server + queue + vite) | [`package.json`](file:///c:/xampp/htdocs/projects/gym-app/package.json#L12), [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json#L96) |

### 4) Key Commands

```bash
# Install PHP and Node dependencies
composer install
npm install

# Full-stack local dev runner (Server + Queue Listener + Vite)
composer run dev

# Build frontend assets for production
npm run build

# Run PHPUnit test suite (safe test database: gym_app_test)
composer run test
docker-compose exec app composer test:fresh

# Format PHP code with Pint
vendor/bin/pint --dirty

# Run static analysis
vendor/bin/phpstan analyse

# Reconcile biometric enrollments (check dry-run or specific gym)
php artisan biometrics:reconcile-enrollments --dry-run
php artisan biometrics:reconcile-enrollments --gym=1

# Clean up superseded and abandoned biometric templates
php artisan biometrics:cleanup-superseded-templates --days=30 --pending-days=7

# Reclaim stale leased ADMS device commands
php artisan biometrics:reclaim-stale-commands

# Backfill and provision salted PBKDF2 ADMS communication keys
php artisan biometrics:backfill-adms-comm-keys --reprovision-legacy --reveal
```

### 5) Environment and Config

- Config sources: [`.env.example`](file:///c:/xampp/htdocs/projects/gym-app/.env.example), [`.env.testing`](file:///c:/xampp/htdocs/projects/gym-app/.env.testing), [`config/`](file:///c:/xampp/htdocs/projects/gym-app/config)
- Required env vars: `APP_KEY`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `MYSQL_TEST_DATABASE`, `REDIS_HOST`, `REVERB_APP_KEY`, `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `META_APP_ID`, `META_APP_SECRET`
- Biometrics hardware config ([`config/biometrics.php`](file:///c:/xampp/htdocs/projects/gym-app/config/biometrics.php)):
  - `ADMS_REQUIRE_COMM_KEY`: Enforces authentication keys for edge hardware requests (default `true`).
  - `ADMS_PBKDF2_ITERATIONS`: Iterations for key derivation (default 100,000).
  - `ADMS_COMMAND_LEASE_SECONDS`: Atomic command lease TTL (default 600s).
  - `ADMS_MAX_ATTEMPTS`: Maximum delivery retries before dead-letter circuit breaker (default 3).
- Deployment/runtime constraints: MySQL 8.0, Redis (for queue, cache, sessions, and Reverb locks), PHP 8.2+ with `gd`, `pdo_mysql`, `intl`, `zip`, `pcntl`, `redis`, and `imagick` extensions.

### 6) Evidence

- [`composer.json`](file:///c:/xampp/htdocs/projects/gym-app/composer.json)
- [`package.json`](file:///c:/xampp/htdocs/projects/gym-app/package.json)
- [`Dockerfile`](file:///c:/xampp/htdocs/projects/gym-app/Dockerfile)
- [`docker-compose.yml`](file:///c:/xampp/htdocs/projects/gym-app/docker-compose.yml)
- [`phpunit.xml`](file:///c:/xampp/htdocs/projects/gym-app/phpunit.xml)
- [`phpstan.neon`](file:///c:/xampp/htdocs/projects/gym-app/phpstan.neon)
- [`config/biometrics.php`](file:///c:/xampp/htdocs/projects/gym-app/config/biometrics.php)
- [`app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Console/Commands/ReconcileBiometricEnrollmentsCommand.php)
- [`.env.example`](file:///c:/xampp/htdocs/projects/gym-app/.env.example)
