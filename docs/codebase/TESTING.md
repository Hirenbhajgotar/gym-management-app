# Testing Patterns

## Core Sections (Required)

### 1) Test Stack and Commands

- Primary test framework: PHPUnit 11 (`phpunit/phpunit: ^11.5.3`) with Laravel test runner (`Illuminate\Foundation\Testing\TestCase`)
- Assertion/mocking tools: PHPUnit built-in assertions, Mockery (`mockery/mockery: ^1.6`), Livewire test utilities (`Livewire::test()`, `Volt::test()`), Laravel Notification and Queue fakes (`Notification::fake()`, `Queue::fake()`).
- Commands:

```bash
# Run all tests (uses test database gym_app_test via phpunit.xml)
composer run test
docker-compose exec app composer test

# Run a specific biometric test file or filter by suite
docker-compose exec app php artisan test tests/Feature/BiometricEnrollmentSyncTest.php
docker-compose exec app php artisan test tests/Feature/ReconcileBiometricEnrollmentsTest.php
docker-compose exec app php artisan test tests/Unit/Biometrics/AdmsCommandKeyTest.php
docker-compose exec app php artisan test --filter=Biometric

# Safely reset and migrate the test database (gym_app_test) before running
docker-compose exec app composer test:fresh
docker-compose exec app composer test:db:reset
```

### 2) Test Layout

- Test file placement pattern: Organized under `tests/` divided into:
  - `tests/Feature/` (functional workflows, HTTP endpoints, policies, migrations, Filament actions)
  - `tests/Unit/` (isolated domain logic, value objects like `tests/Unit/Biometrics/AdmsCommandKeyTest.php`)
- Naming convention: Test classes end with `Test.php` in PascalCase (e.g. `tests/Feature/BiometricEnrollmentSyncTest.php`, `tests/Feature/EnrollBiometricActionTenantIsolationTest.php`).
- Setup files and where they run:
  - `tests/TestCase.php`: Base setup class configuring environment, bootstrapping database connections, and enforcing test safety assertion (`$this->fail()` if database is `gym_app`).
  - `phpunit.xml`: Declares `<env name="DB_DATABASE" value="gym_app_test" force="true"/>`, `<env name="QUEUE_CONNECTION" value="sync" force="true"/>`, `<env name="CACHE_STORE" value="array"/>`.

### 3) Test Scope Matrix

| Scope | Covered? | Typical target | Notes |
|-------|----------|----------------|-------|
| Unit | Yes | Utility helpers, calculation services, DTOs, Enums, Value Objects (`AdmsCommandKeyTest`, `BiometricTemplateBadgeTest`, `BiometricThrottleConfigTest`) | Fast execution in memory without database dependencies |
| Integration | Yes | API webhooks (Razorpay, WhatsApp), ADMS protocol endpoints, Two-phase Biometric Enrollment (`BiometricEnrollmentSyncTest`), Sibling Fan-out (`BiometricActiveSiblingDeletionTest`), Reconciler Safety Guards (`ReconcileBiometricEnrollmentsTest`), Dead-Letter Breakers (`BiometricDeadLetterReconciliationTest`), Tenant Isolation (`EnrollBiometricActionTenantIsolationTest`), GDPR De-provisioning (`BiometricGdprErasureDurabilityTest`), Migrations (`BackfillHardwareConfirmedAtMigrationTest`) | Uses MySQL `gym_app_test` schema with transaction rollbacks |
| E2E | Partial | Volt single-file components, multi-step gym registration & subscription checkout | Tested via Livewire/Volt component assertions and HTTP simulated requests |

### 4) Mocking and Isolation Strategy

- Main mocking approach:
  - External HTTP Gateways (Meta Graph API, Twilio SDK, Razorpay Client) are mocked via service container binding overrides or Mockery instances.
  - Biometric hardware push requests are verified with HTTP simulated requests posting fixture payloads and device serial/comm-key headers.
  - Asynchronous dispatches are verified using `Queue::fake()` and `Event::fake()`.
- Isolation guarantees:
  - Strict database isolation: Tests exclusively execute against `gym_app_test`.
  - Database transactions (`DatabaseTransactions` or fresh migrations) guarantee zero state leakage across test cases.
  - Multi-tenant query isolation tests verify that users cannot view or mutate records belonging to different `gym_id` tenants (e.g. `EnrollBiometricActionTenantIsolationTest`).
- Common failure mode in tests: Running migrations without `--env=testing` on host systems with non-standard DB environment variables (caught and blocked by `bootstrap/app.php` and `tests/TestCase.php`).

### 5) Coverage and Quality Signals

- Coverage tool + threshold: PHPUnit code coverage driver (PCOV / Xdebug); high coverage enforced across core billing, auth, and biometric protocol engines.
- Current reported coverage: Comprehensive feature coverage across `tests/Feature/` covering all key user journeys, edge protocols, reconciler safety guards, and tenant boundaries.
- Known gaps/flaky areas: Real biometric hardware timing and edge hardware firmware quirks are covered using simulated ADMS fixtures rather than physical devices.

### 6) Evidence

- [`phpunit.xml`](file:///c:/xampp/htdocs/projects/gym-app/phpunit.xml)
- [`tests/TestCase.php`](file:///c:/xampp/htdocs/projects/gym-app/tests/TestCase.php)
- [`tests/Feature/BiometricEnrollmentSyncTest.php`](file:///c:/xampp/htdocs/projects/gym-app/tests/Feature/BiometricEnrollmentSyncTest.php)
- [`tests/Feature/ReconcileBiometricEnrollmentsTest.php`](file:///c:/xampp/htdocs/projects/gym-app/tests/Feature/ReconcileBiometricEnrollmentsTest.php)
- [`tests/Feature/Filament/EnrollBiometricActionTenantIsolationTest.php`](file:///c:/xampp/htdocs/projects/gym-app/tests/Feature/Filament/EnrollBiometricActionTenantIsolationTest.php)
- [`tests/Unit/Biometrics/AdmsCommandKeyTest.php`](file:///c:/xampp/htdocs/projects/gym-app/tests/Unit/Biometrics/AdmsCommandKeyTest.php)
- [`bootstrap/app.php#L185-L193`](file:///c:/xampp/htdocs/projects/gym-app/bootstrap/app.php#L185-L193)
- [`AGENTS.md#L1-L40`](file:///c:/xampp/htdocs/projects/gym-app/AGENTS.md#L1-L40)
