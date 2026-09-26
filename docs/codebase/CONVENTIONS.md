# Coding Conventions

## Core Sections (Required)

### 1) Naming Rules

| Item | Rule | Example | Evidence |
|------|------|---------|----------|
| Files | PascalCase for PHP classes, kebab-case for views/configs, snake_case for migrations | `MemberResource.php`, `member-search.blade.php`, `2025_01_01_000000_create_gyms_table.php` | [`app/Filament/Admin/Resources/GymMemberResource.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Filament/Admin/Resources/GymMemberResource.php) |
| Functions/methods | camelCase with explicit return types and parameter type hints | `calculateMembershipFee(User $user, string $planType): float` | [`app/Services/ErrorReportService.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Services/ErrorReportService.php) |
| Types/interfaces/DTOs | PascalCase, strict PHP types, Enums with TitleCase / UPPERCASE cases | `DefaultRole::SuperAdmin`, `AdmsPunchData` | [`app/Enums/DefaultRole.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Enums/DefaultRole.php) |
| Constants/env vars | UPPER_SNAKE_CASE | `RAZORPAY_KEY_ID`, `DB_DATABASE`, `MYSQL_TEST_DATABASE` | [`.env.example`](file:///c:/xampp/htdocs/projects/gym-app/.env.example) |

### 2) Formatting and Linting

- Formatter: Laravel Pint (v1.27) configured via `pint.json` using preset `laravel` with strict rules (strict types declaration, single quotes, array syntax).
- Linter: Larastan / PHPStan (v3) configured via `phpstan.neon` at `level: max` with strict rules extension.
- Most relevant enforced rules:
  - Mandatory strict typing declaration at file top: `declare(strict_types=1);`
  - Mandatory constructor property promotion in PHP 8.2 classes.
  - Model attribute casting using the `casts(): array` method rather than the legacy `$casts` property.
- Run commands:
  - `vendor/bin/pint --dirty` (formats modified PHP files)
  - `vendor/bin/phpstan analyse` (runs strict PHP static analysis)

### 3) Import and Module Conventions

- Import grouping/order: Grouped logically (PHP standard library, external packages / vendors, framework namespaces, and application namespaces) sorted alphabetically.
- Alias vs relative import policy: Full PSR-4 namespace imports (`use App\Models\GymMember;`), avoiding relative path imports in PHP.
- Public exports/barrel policy: Explicit class importing via `use` statements at the top of every PHP file.

### 4) Error and Logging Conventions

- Error strategy by layer:
  - HTTP / API: Handled in `bootstrap/app.php` via `ErrorReportService::renderJsonResponse()` returning unified JSON structures.
  - Filament Panels: Custom error views rendered via `ErrorReportService::renderFilamentError()`.
  - Background Workers: Logged with full stack trace to Redis/database and triaged in the SuperAdmin Error Monitor.
- Logging style and required context fields: Structured logging using `Log::info()` / `Log::error()` with explicit associative context arrays (e.g. `['gym_id' => $tenant->id, 'member_id' => $member->id]`).
- Sensitive-data redaction rules: Passwords, API secrets, biometric facial tokens, and payment card numbers are excluded from error payloads and logs.

### 5) Testing Conventions

- Test file naming/location rule: Placed in `tests/Feature/` or `tests/Unit/`, ending in `Test.php` (e.g. `tests/Feature/AdmsProtocolTest.php`).
- Mocking strategy norm: PHPUnit built-in mocking or Mockery (`$this->mock(...)`), fake notifications (`Notification::fake()`), and fake queues (`Queue::fake()`).
- Coverage expectation: High coverage across critical business paths (membership expiry, biometric protocols, payments, authorization policies). Test suite must pass against `gym_app_test`.

### 6) Evidence

- [`pint.json`](file:///c:/xampp/htdocs/projects/gym-app/pint.json)
- [`phpstan.neon`](file:///c:/xampp/htdocs/projects/gym-app/phpstan.neon)
- [`bootstrap/app.php`](file:///c:/xampp/htdocs/projects/gym-app/bootstrap/app.php)
- [`tests/Feature/AdmsProtocolTest.php`](file:///c:/xampp/htdocs/projects/gym-app/tests/Feature/AdmsProtocolTest.php)
- [`app/Services/ErrorReportService.php`](file:///c:/xampp/htdocs/projects/gym-app/app/Services/ErrorReportService.php)
