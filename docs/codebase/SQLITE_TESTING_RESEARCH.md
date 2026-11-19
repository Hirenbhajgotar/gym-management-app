# SQLite for the Test Suite — Feasibility Research

## 0) Method and sources

This note answers: *can this Laravel 12 app's test suite run on SQLite, and what are the pros/cons?* Claims are traced to primary sources only:

- Installed framework source: `laravel/framework` **v12.51.0** (`composer.lock`, `name: laravel/framework` → `version: v12.51.0`).
- Installed config/harness in this repo: `config/database.php`, `phpunit.xml`, `bootstrap/app.php`, `tests/TestCase.php`, `tests/Concerns/*`, `tests/LightweightTestCase.php`.
- All 215 files in `database/migrations/` and grep of `app/**/*.php` + `tests/**/*.php`.
- Official docs: Laravel 12.x [Database Testing](https://laravel.com/docs/12.x/database-testing), [Migrations](https://laravel.com/docs/12.x/migrations); SQLite [Datatypes](https://www.sqlite.org/datatype3.html), [Foreign Keys](https://www.sqlite.org/foreignkeys.html), [UPDATE](https://www.sqlite.org/lang_update.html); MySQL 8.0 [Fixed-Point Types](https://dev.mysql.com/doc/refman/8.0/en/fixed-point-types.html), [Server SQL Modes](https://dev.mysql.com/doc/refman/8.0/en/sql-mode.html).
- **Experiment (primary source):** a real `migrate:fresh` against `sqlite` `:memory:` inside the app container. No command touched MySQL; the main DB `gym_app` was never targeted. See §2.

Environment note: the container reports `PHP 8.3.36`, `PDO drivers: sqlite, mysql`, `SQLite lib version: 3.46.1`. This differs from the brief's "PHP 8.2"; the 8.3 runtime also emits an unrelated `filament-icons` `final private method` deprecation during shutdown. Both are observations, not conclusions about the 8.2 target.

## 1) Verdict

**Partially feasible — with conditions. Not a drop-in replacement.**

- As a *supplementary* fast SQLite lane (pure logic, protocol fixtures, migration smoke test), it is practical after a small, bounded change set (§6). The whole 215-migration history replayed successfully on SQLite once three MySQL-only statements were rewritten (§2).
- As a *replacement for the canonical `gym_app_test` suite*, it is **not** acceptable without material work and would change what the tests prove: row-level locking disappears, collation/strict-typing/money semantics differ, one production unique index is silently skipped, and a family of harness/safety tests is built around MySQL database names (§4, §5).

Recommendation: keep MySQL as the source of truth for integration semantics; add SQLite only as an opt-in fast lane, and keep the semantics-sensitive suites (locks, consent uniqueness, money, collation, DB harness) on MySQL.

## 2) Experiment results

1. `docker-compose exec -T -e APP_ENV=local -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: app php artisan migrate:fresh --database=sqlite --force` ran 128 migrations, then **failed at `2026_10_31_000200_keep_adms_command_ledger_after_device_deletion.php:34`**:

   ```
   SQLSTATE[HY000]: General error: 1 near "c": syntax error (Connection: sqlite, Database: :memory:,
   SQL: UPDATE adms_device_commands c INNER JOIN biometric_devices d ON d.id = c.device_id
        SET c.device_serial = d.device_serial WHERE c.device_serial IS NULL)
   ```

   SQLite's `UPDATE` grammar has a `FROM` clause, not MySQL's `UPDATE t JOIN u ... SET`; the docs explicitly note MySQL implements the idea with the join between `UPDATE` and `SET`, while SQLite uses `UPDATE ... FROM` ([SQLite UPDATE §2.2](https://www.sqlite.org/lang_update.html)).

2. Copies of all 215 migrations were placed **only** under the container's `/tmp/sqlite_research_migrations`; the three MySQL `UPDATE ... JOIN` backfills were rewritten there to scalar-subquery `UPDATE ... SET col = (SELECT ...) WHERE ...`. Re-running `migrate:fresh --database=sqlite --path=/tmp/sqlite_research_migrations --realpath` then completed **215/215 migrations, exit 0**. Sum of reported per-migration timings ≈ **7.3 s** (vs the ~110 s DDL replay documented in `tests/Concerns/ReusesMigratedTestDatabase.php:14-16` for MySQL). No repository file was modified by the experiment.

3. Constraint: this proves the *schema* is portable after three edits. It does **not** prove the test suite passes — see the blockers in §4 and §5.

## 3) Concrete pros (for this codebase)

1. **Much faster schema setup.** ~7.3 s of DDL vs ~110 s for the 215 migrations on MySQL (`tests/Concerns/ReusesMigratedTestDatabase.php:11-21`). This directly attacks the cost that trait was written to avoid.
2. **Migrations run once per process natively.** Laravel 12 detects `:memory:` via `config("database.connections.{$name}.database") === ':memory:'`, migrates once behind `RefreshDatabaseState::$migrated`, and caches/restores the PDO between tests (`vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php:21-27, 51-58, 81-94, 101-109`). The custom `ReusesMigratedTestDatabase` re-application check becomes largely redundant.
3. **Natural per-process isolation.** Each PHPUnit process owns its in-memory DB, which removes the shared-schema corruption scenario described in `tests/TestCase.php:27-31` (three concurrent runners on `gym_app_test`) without per-runner MySQL database names.
4. **No external DB service needed** for DB-backed unit/protocol smoke tests; aligns with the existing fast lane (`tests/LightweightTestCase.php:13-28`, `tests/Concerns/SkipsFilamentBoot.php:7-14`). The `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array` settings in `phpunit.xml:26,42,43` already favour a self-contained runner.
5. **Laravel 12 supports the needed schema operations natively on SQLite.** `change()`, `dropColumn()`, `renameColumn()`, foreign-key add/drop are handled by table-rebuild SQL in `vendor/laravel/framework/src/Illuminate\Database\Schema\Grammars\SQLiteGrammar.php:324-377, 602`; `getForeignKeys()` used by two migrations is implemented at `SQLiteGrammar.php:211-222` and shaped by `vendor/.../Query/Processors/SQLiteProcessor.php:83-97`. Foreign keys are enabled by Laravel via `pragma foreign_keys = 1` (`vendor/.../Connectors/SQLiteConnector.php:93-99`, config default true at `config/database.php:41`). The full replay in §2 confirms these paths (32 `->change()`, many `dropColumn`/`dropForeign`) work in practice.

## 4) Concrete cons (specific to THIS codebase)

1. **Hard blockers in migrations** — three MySQL `UPDATE ... JOIN` statements (§5, §2). Without rewriting them the suite cannot migrate at all.
2. **Hard blockers in tests** — `tests/Feature/DatabaseDiagnosticTest.php:128,145` uses `SELECT DATABASE()` and `SHOW TABLES`; `tests/Feature/DatabaseWithoutRefreshDatabaseTest.php:43,100` does the same. Neither SQL statement exists in SQLite. `tests/Feature/TestingEnvironmentDatabaseTest.php:16-18,35-36` and the two diagnostic tests assert the literal `gym_app_test`, so they would fail or need rewriting.
3. **The DB-safety harness is MySQL-name-shaped.** `phpunit.xml:37-38` forces `DB_CONNECTION=mysql` and `DB_DATABASE=gym_app_test`; `bootstrap/app.php:26-39` re-forces `DB_DATABASE=gym_app_test` in testing mode; `config/database.php:39` uses `DB_DATABASE` as the **SQLite file path** (so a forced `gym_app_test` would create a relative file, not `:memory:`). `tests/TestCase.php:64-83` resolves a name via `isTestDatabaseName()` (`:memory:` fails the `/(^|_)test($|_)/` regex at line 144) and then mirrors `gym_app_test` into **all three** `DB_*` keys, overwriting any `:memory:` value; line 87 only patches `database.connections.mysql.database`, not the sqlite connection. `tests/WhatsAppSetupTestCase.php:33-35,44` hard-codes the same pattern. All of this must change together.
4. **Row locks vanish.** `lockForUpdate()`/`sharedLock()` compile to an empty string on SQLite (`vendor/laravel/framework/src/Illuminate/Database/Query/Grammars/SQLiteGrammar.php:31-34`). The app uses these extensively — e.g. `app/Biometrics/Services/AdmsEventService.php:295`, `app/Biometrics/Services/BiometricReceiptService.php:66`, `app/Services/QRAttendanceService.php:60,179`, `app/Services/MemberFreezeService.php:28,118`, `app/Models/BiometricDevice.php:258`. Any test that relies on those locks for TOCTOU safety would pass for the wrong reason. The *anti-passback* lock itself is Redis-based (`app/Biometrics/Services/RedisBiometricCacheService.php:83-117`, `config/biometrics.php:71`), so `tests/Feature/AntiPassbackLockReleaseTest.php` and `AdmsProtocolTest::test_adms_cdata_anti_passback_prevents_duplicate` are unaffected by the DB switch — but the DB locks around them are not.
5. **Collation changes.** MySQL uses `utf8mb4_unicode_ci` (`config/database.php:59`, case-insensitive); SQLite's default collation is `BINARY`, i.e. case-sensitive ([SQLite datatype3 §7.1](https://www.sqlite.org/datatype3.html)). Case-insensitive uniqueness/lookup behaviour would change, so tests could pass or fail differently for names, emails and codes.
6. **Strict typing is lost.** MySQL is configured `strict => true` (`config/database.php:62`; strict mode rejects invalid/missing values per [MySQL sql-mode](https://dev.mysql.com/doc/refman/8.0/en/sql-mode.html)). SQLite uses dynamic typing / type affinity and happily stores mismatched storage classes unless tables are declared `STRICT` ([SQLite datatype3 §1](https://www.sqlite.org/datatype3.html)); Laravel 12's SQLite grammar emits ordinary (non-`STRICT`) DDL. Malformed values that MySQL rejects could be silently accepted in tests.
7. **Money precision.** Laravel maps `decimal` to `numeric` on SQLite (`SQLiteGrammar.php:849-852`); `NUMERIC`/`DECIMAL` affinity stores INTEGER or REAL ([SQLite datatype3 §3.1](https://www.sqlite.org/datatype3.html)), while MySQL `DECIMAL` is exact fixed-point ([MySQL fixed-point](https://dev.mysql.com/doc/refman/8.0/en/fixed-point-types.html)). With 30+ money columns (e.g. `payments.amount`, `member_payments`, `plan_prices`) tests could mask floating-point rounding bugs.
8. **A production DB invariant is not created.** `database/migrations/2026_11_17_000000_update_biometric_consents_active_unique_index.php:12-14,25-26` replaces a plain unique key with a MySQL expression-based partial unique index only when `DB::getDriverName() === 'mysql'`; on SQLite it is a silent no-op. Tests around "one active consent" (e.g. `tests/Feature/BiometricConsentTest.php:440-527`) would no longer be backed by the same DB constraint.
9. **JSON becomes TEXT.** `config/database.php:36-45` has no `use_native_json` flag, and Laravel maps `json()` to `text` on SQLite (`SQLiteGrammar.php:886-889`; [Laravel 12 migrations docs](https://laravel.com/docs/12.x/migrations#column-method-json)). There are 32 migrations using `->json()`. Laravel's JSON query helpers still compile (`Query/Grammars/SQLiteGrammar.php:206-250`), but storage semantics differ from MySQL JSON.
10. **ENUM is emulated.** Laravel emits `varchar check ("col" in (...))` for enum on SQLite (`SQLiteGrammar.php:871-878`) vs real MySQL ENUM in `2025_07_24_100540_create_employee_details_table.php:20-21`, `2025_09_11_000004_create_social_media_webhook_logs_table.php:25`, `2026_01_29_170003_create_expense_budgets_table.php:27`.
11. **MySQL-only raw SQL in app code on tested paths.** Filament report widgets use `DATE_FORMAT`, `DAYOFWEEK`, `DATEDIFF`, `TIMESTAMPDIFF`, `HOUR` (e.g. `app/Filament/Admin/Clusters/Reports/Widgets/RevenueTrendChart.php:53`, `app/Filament/Admin/Clusters/Reports/Pages/AttendanceReports.php:143-302`, `MemberReports.php:172`). SQLite has no `DATE_FORMAT`. These code paths are not the main integration-test target, but they would break if exercised.
12. **Test-DB tooling is MySQL-specific.** `app/Console/Commands/TestDbFresh.php:102-124` hard-wires `--database => 'mysql'` and executes `SET FOREIGN_KEY_CHECKS = 0/1`; composer scripts `test:db:reset`, `test:fresh` (`composer.json:98-120`) and `docs/codebase/TESTING.md:12-25,52` all assume `gym_app_test`.
13. **ALTER-by-rebuild is a behavioural risk even though it works.** `SQLiteGrammar::compileAlter` (lines 324-371) creates a temp table, copies data, drops and renames, toggling `foreign_keys` around it. It succeeded in the experiment, but it is not the same operation as MySQL's in-place `ALTER`, and `->after()` column ordering (164 occurrences) is silently ignored by SQLite (`after` is a MariaDB/MySQL-only modifier per Laravel 12 docs).

## 5) MySQL-specific constructs found in migrations

| Construct | Files / locations | SQLite behaviour |
|---|---|---|
| `UPDATE ... INNER JOIN ... SET` backfill | `2026_10_31_000200_keep_adms_command_ledger_after_device_deletion.php:34-38`; `2026_10_31_000300_preserve_access_control_audit_after_device_deletion.php:136-140`; `2026_11_01_000000_add_device_serial_to_biometric_event_receipts_table.php:44-48` | **Hard failure** (confirmed). Rewrite to `UPDATE ... SET col = (SELECT ...) WHERE ...` or `UPDATE ... FROM` (SQLite ≥ 3.33; container has 3.46.1). |
| MySQL expression/partial unique index via raw `ALTER TABLE ... DROP INDEX ... ADD UNIQUE KEY ... ((CASE WHEN ...))` | `2026_11_17_000000_update_biometric_consents_active_unique_index.php:12-14,25-26` | Guarded by `DB::getDriverName() === 'mysql'`, so **silently skipped** on SQLite (no error, lost invariant). |
| `->after()` column placement — **164 occurrences** | e.g. `2025_04_14_162945_add_logo_favicon_columns_to_general_settings_table.php:14-15`, `2025_05_27_152112_add_payment_status_in_gym_members.php:19` | MySQL-only modifier; ignored by SQLite (Laravel 12 docs, "Column Modifiers" table). Harmless but changes physical column order. |
| `->comment()` — **17 occurrences** | e.g. `2025_04_21_150649_add_profile_fields_to_gym_members.php:18-19`, `2025_05_27_152112_add_payment_status_in_gym_members.php:21` | MySQL/MariaDB/PostgreSQL-only (Laravel 12 docs); ignored on SQLite. |
| `->change()` — **32 occurrences** | e.g. `2025_05_31_163800_update_payment_status_in_gym_members.php:17,27`, `2026_10_31_000200_...php:47,73` | Supported via table rebuild (`SQLiteGrammar.php:324-377`); verified by full replay. |
| `->enum()` — 3 files | `2025_07_24_100540_...php:20-21`; `2025_09_11_000004_...php:25`; `2026_01_29_170003_...php:27` | Emulated as `varchar + CHECK` (`SQLiteGrammar.php:871-878`). |
| `->json()` — 32 files | e.g. `2025_04_14_162944_create_general-settings_table.php:24-29` | Becomes `TEXT` (`SQLiteGrammar.php:886-889`). |
| `unsigned*()`, `->unsigned()` | many, e.g. `2025_06_29_070713_create_gym_user_table.php:34` | Ignored by SQLite grammar; no range enforcement. |
| `charset()->virtualAs('unhex(md5(...))')` (pulse tables) | `2025_07_07_131430_create_pulse_tables.php:25-29,42-46,61-65` | **Already portable** — has an explicit `'sqlite' => $table->string('key_hash')` branch. |
| `bigIncrements` / `id()` | many, e.g. `2025_05_03_160716_create_activity_log_table.php:14` | Portable: mapped to `integer` + autoincrement (`SQLiteGrammar.php:783-786`). |
| `insertOrIgnore` | `2026_10_13_000000_create_gym_member_archive_permissions.php:165` | Portable in Laravel 12 (`insert or ignore`). |
| `DB::statement('UPDATE ...')` (plain) | `2026_06_30_000001_update_pt_salary_per_member_to_percentage.php:25` | Portable. |
| `FULLTEXT`, `ON DUPLICATE KEY UPDATE`, `MODIFY/CHANGE COLUMN`, `SET FOREIGN_KEY_CHECKS` | not found in `database/migrations/` (grep returned only comments/backticks in docblocks) | n/a. (`SET FOREIGN_KEY_CHECKS` is in `app/Console/Commands/TestDbFresh.php:121,124`, not a migration.) |

## 6) Minimal change set if adopted

1. **Rewrite the three MySQL `UPDATE ... JOIN` backfills** listed in §5 as portable scalar-subquery updates (this is the only thing that blocks migration).
2. **Switch the connection in `phpunit.xml:37-38`** to `DB_CONNECTION=sqlite` and a database value that is actually `:memory:` (or a clearly-namespaced temp file); stop forcing `gym_app_test`.
3. **Reconcile the env overrides** so the value is not clobbered: `bootstrap/app.php:26-39`, `config/database.php:36-45` (decouple sqlite path from `DB_DATABASE`, or give SQLite its own key), `tests/TestCase.php:64-100` (accept `:memory:` / skip the MySQL-only mirroring and the `connections.mysql.database` patch when the active connection is sqlite), `tests/WhatsAppSetupTestCase.php:33-44`.
4. **Adapt the DB-harness tests**: `tests/Feature/TestingEnvironmentDatabaseTest.php`, `DatabaseDiagnosticTest.php`, `DatabaseWithoutRefreshDatabaseTest.php`, `tests/Unit/TestDatabaseNameResolutionTest.php`, `tests/Fixtures/AmbientDatabaseUrlProbeTest.php` — either gate them to MySQL or rewrite assertions to the SQLite connection. Replace `SHOW TABLES`/`SELECT DATABASE()` (use `Schema::getAllTables()` / `config('database.connections.sqlite.database')`).
5. **Update tooling/docs**: `app/Console/Commands/TestDbFresh.php:102-124` (connection name + drop the `FOREIGN_KEY_CHECKS` statements; SQLite uses `pragma` via `SQLiteConnector`), composer scripts (`composer.json:98-120`), `docs/codebase/TESTING.md:12-25,35,52`, and `AGENTS.md` DB-safety guidance.
6. **Decide explicitly** how to handle the semantic gaps in §4 (locks, collation, strict typing, money, skipped partial index) — either keep those suites on MySQL or add explicit SQLite-only expectations. Do not assume a green SQLite run proves MySQL behaviour.

## 7) Evidence links

- [`phpunit.xml#L20-L48`](file:///c:/xampp/htdocs/projects/gym-app/phpunit.xml)
- [`config/database.php#L21-L67`](file:///c:/xampp/htdocs/projects/gym-app/config/database.php)
- [`bootstrap/app.php#L26-L39`](file:///c:/xampp/htdocs/projects/gym-app/bootstrap/app.php)
- [`tests/TestCase.php#L46-L101`](file:///c:/xampp/htdocs/projects/gym-app/tests/TestCase.php)
- [`tests/Concerns/ReusesMigratedTestDatabase.php#L10-L21`](file:///c:/xampp/htdocs/projects/gym-app/tests/Concerns/ReusesMigratedTestDatabase.php)
- [`tests/LightweightTestCase.php#L13-L28`](file:///c:/xampp/htdocs/projects/gym-app/tests/LightweightTestCase.php)
- [`database/migrations/2026_10_31_000200_keep_adms_command_ledger_after_device_deletion.php#L26-L54`](file:///c:/xampp/htdocs/projects/gym-app/database/migrations/2026_10_31_000200_keep_adms_command_ledger_after_device_deletion.php)
- [`database/migrations/2026_10_31_000300_preserve_access_control_audit_after_device_deletion.php#L128-L141`](file:///c:/xampp/htdocs/projects/gym-app/database/migrations/2026_10_31_000300_preserve_access_control_audit_after_device_deletion.php)
- [`database/migrations/2026_11_01_000000_add_device_serial_to_biometric_event_receipts_table.php#L42-L49`](file:///c:/xampp/htdocs/projects/gym-app/database/migrations/2026_11_01_000000_add_device_serial_to_biometric_event_receipts_table.php)
- [`database/migrations/2026_11_17_000000_update_biometric_consents_active_unique_index.php#L10-L27`](file:///c:/xampp/htdocs/projects/gym-app/database/migrations/2026_11_17_000000_update_biometric_consents_active_unique_index.php)
- [`database/migrations/2025_07_07_131430_create_pulse_tables.php#L20-L65`](file:///c:/xampp/htdocs/projects/gym-app/database/migrations/2025_07_07_131430_create_pulse_tables.php)
- [`vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php#L21-L109`](file:///c:/xampp/htdocs/projects/gym-app/vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php)
- [`vendor/laravel/framework/src/Illuminate/Database/Schema/Grammars/SQLiteGrammar.php#L211-L222`](file:///c:/xampp/htdocs/projects/gym-app/vendor/laravel/framework/src/Illuminate/Database/Schema/Grammars/SQLiteGrammar.php)
- [`vendor/laravel/framework/src/Illuminate/Database/Query/Grammars/SQLiteGrammar.php#L24-L34`](file:///c:/xampp/htdocs/projects/gym-app/vendor/laravel/framework/src/Illuminate/Database/Query/Grammars/SQLiteGrammar.php)
- [`app/Console/Commands/TestDbFresh.php#L102-L124`](file:///c:/xampp/htdocs/projects/gym-app/app/Console/Commands/TestDbFresh.php)
- [`tests/Feature/DatabaseDiagnosticTest.php#L120-L153`](file:///c:/xampp/htdocs/projects/gym-app/tests/Feature/DatabaseDiagnosticTest.php)
- [`tests/Feature/DatabaseWithoutRefreshDatabaseTest.php#L26-L108`](file:///c:/xampp/htdocs/projects/gym-app/tests/Feature/DatabaseWithoutRefreshDatabaseTest.php)
- [`tests/Feature/TestingEnvironmentDatabaseTest.php#L13-L36`](file:///c:/xampp/htdocs/projects/gym-app/tests/Feature/TestingEnvironmentDatabaseTest.php)
- `docs/codebase/TESTING.md` (style reference; kept unchanged)
