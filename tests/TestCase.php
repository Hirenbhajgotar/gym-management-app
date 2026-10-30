<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Override OS-level env vars before Laravel bootstraps.
     *
     * Docker injects DB_DATABASE=gym_app as an OS env var which
     * takes priority over .env.testing and phpunit.xml. This ensures
     * tests always target a test database.
     *
     * A per-run/per-reviewer `.phpunit-<id>.xml` may set `DB_DATABASE`,
     * `DB_TEST_DATABASE`, or `DB_DATABASE_TEST`; all three are honoured and the resolved
     * value is mirrored into all three so the application and `config/database.php` cannot
     * disagree. An explicitly named *test* database is honoured rather than replaced, so a
     * per-run/per-reviewer config actually isolates. Pinning every run to the shared
     * `gym_app_test` meant three concurrent runners corrupted each other's schema and a
     * contaminated run had to be discarded. Anything that is not a recognisable test
     * database — including the Docker-injected `gym_app` — still falls back to the default.
     *
     * `DB_URL` is deliberately not part of the supported per-run harness: isolation goes
     * through the three `DB_*` keys above. An ambient `DB_URL` would otherwise survive into
     * `config/database.php`'s `'url' => env('DB_URL')`, and `ConfigurationUrlParser` merges
     * URL-derived options *over* the array config when a connection is created — silently
     * re-targeting the whole run at whatever database the URL names, including `gym_app`,
     * after the pre-boot guards have already passed. `setUp()` therefore removes `DB_URL`
     * from every environment source before `parent::setUp()` bootstraps, so nothing can
     * merge over the mirrored `DB_*` keys.
     *
     * `DB_TEST_DATABASE` and `DB_DATABASE_TEST` are testing-only keys, so resolving either
     * to `gym_app` is fatal regardless of the testing flag. `DB_DATABASE`/`DB_URL` are only
     * fatal outside an explicit testing context because Docker injects `DB_DATABASE=gym_app`.
     */
    protected function setUp(): void
    {
        // Pre-boot guard: inspect environment BEFORE overriding.
        $initialDb = Env::get('DB_DATABASE', getenv('DB_DATABASE')) ?: null;
        $initialUrl = Env::get('DB_URL', getenv('DB_URL')) ?: null;
        $urlDatabase = self::databaseNameFromUrl(is_string($initialUrl) ? $initialUrl : null);
        $isExplicitTesting = (isset($_SERVER['ARGV']) && is_array(Request::server('ARGV')) && in_array('--env=testing', Request::server('ARGV'), true))
            || Env::get('APP_ENV') === 'testing'
            || getenv('APP_ENV') === 'testing';

        throw_if(! $isExplicitTesting && ($initialDb === 'gym_app' || $urlDatabase === 'gym_app'), RuntimeException::class, 'SAFETY: DB_DATABASE/DB_URL resolves to "gym_app" without testing environment flag. Aborting before migrate:fresh.');

        foreach (['DB_TEST_DATABASE', 'DB_DATABASE_TEST'] as $key) {
            $initialTestingDb = Env::get($key, getenv($key) ?: null);

            throw_if($initialTestingDb === 'gym_app', RuntimeException::class, "SAFETY: {$key} resolves to \"gym_app\". These keys are testing-only. Aborting before migrate:fresh.");
        }

        $database = $this->resolveTestDatabaseName();

        // `DB_URL` is not part of the supported per-run harness: isolation goes through the
        // three `DB_*` keys below. `ConfigurationUrlParser` merges URL-derived options *over*
        // the array config when a connection is created, so an ambient `DB_URL` would survive
        // the pre-boot guards and re-point the whole run — including RefreshDatabase's
        // migrate:fresh — at whatever database it names. Remove it before boot so
        // `env('DB_URL')` resolves null and nothing can merge over the mirrored keys.
        self::forgetEnvironmentValue('DB_URL');

        // `config/database.php`'s testing branch reads `DB_TEST_DATABASE` and
        // `DB_DATABASE_TEST`, while the application reads `DB_DATABASE`. The override must
        // land before `parent::setUp()` bootstraps the connection: `RefreshDatabase` runs
        // inside it, and if the keys disagree it migrates a different database than the one
        // the tests then use. Mirror the resolved value into all three.
        foreach (['DB_DATABASE', 'DB_TEST_DATABASE', 'DB_DATABASE_TEST'] as $key) {
            putenv("{$key}={$database}");
            $_ENV[$key] = $database;
            $_SERVER[$key] = $database;
        }

        parent::setUp();

        $this->app->make('config')->set('database.connections.mysql.database', $database);

        // Keep the crypto floor explicit and fast for the test suite. The
        // production floor is the fixed 100,000 constant; BiometricDevice
        // ignores this config override outside the testing environment.
        $this->app->make('config')->set('biometrics.adms.pbkdf2_min_iterations', 1);

        if (DB::connection()->getDatabaseName() === 'gym_app') {
            $this->fail('SAFETY: Tests are targeting the production database "gym_app". Aborting to prevent data loss.');
        }

        if (config('database.connections.mysql.database') === 'gym_app') {
            $this->fail('SAFETY: config("database.connections.mysql.database") resolved to "gym_app". Aborting.');
        }
    }

    /**
     * The database this test run targets.
     *
     * Resolved in the same order `config/database.php` resolves it under the testing
     * environment, so the two cannot disagree. A `.phpunit-<id>.xml` may set
     * `DB_TEST_DATABASE`, `DB_DATABASE_TEST`, or `DB_DATABASE`; all three are honoured and
     * the resolved value is mirrored into each key by `setUp()`. Only a name accepted by
     * `isTestDatabaseName()` is honoured; everything else falls back to `gym_app_test`,
     * which is what the Docker-injected production name must always do.
     */
    private function resolveTestDatabaseName(): string
    {
        $candidates = [
            Env::get('DB_TEST_DATABASE', getenv('DB_TEST_DATABASE') ?: null),
            Env::get('DB_DATABASE_TEST', getenv('DB_DATABASE_TEST') ?: null),
            Env::get('DB_DATABASE', getenv('DB_DATABASE') ?: null),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && self::isTestDatabaseName($candidate)) {
                return $candidate;
            }
        }

        return 'gym_app_test';
    }

    /**
     * Whether the given name is a database this suite may safely migrate and use.
     *
     * A test database name is non-empty, is not the main `gym_app` database, and contains
     * `test` as a whole underscore-delimited segment. A plain substring check is too loose:
     * `contest` and `latest_gym` contain `test` but are not test databases. Names such as
     * `gym_app_test`, `gym_app_test_r3`, and `test_gym` are accepted.
     */
    public static function isTestDatabaseName(?string $name): bool
    {
        if ($name === null || $name === '' || $name === 'gym_app') {
            return false;
        }

        return preg_match('/(^|_)test($|_)/', $name) === 1;
    }

    /**
     * Extract the database name from a connection URL, or null when the URL carries none.
     *
     * The pre-boot guard needs the URL's database *segment*, not a substring: a plain
     * `str_contains($url, '/gym_app')` false-positives on `/gym_app_test`, which is exactly
     * the kind of URL an isolated `.phpunit-<id>.xml` harness may hand the suite. `parse_url()`
     * ignores the query string, and percent-encoded names are decoded so `gym%5Fapp` is
     * recognised as `gym_app`. A trailing slash is preserved (`gym_app/`), so only an exact
     * `gym_app` segment trips the guard; the `DB_URL` neutralisation in `setUp()` is the
     * primary guarantee and this only sharpens its belt-and-braces check.
     */
    public static function databaseNameFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        return rawurldecode(ltrim($path, '/'));
    }

    /**
     * Remove an environment variable from every source Laravel's `Env` repository reads.
     *
     * `putenv()` alone is not enough: depending on `variables_order` and the caller, the value
     * may be visible only in `$_ENV` or `$_SERVER`, and `Env::get()` may fall back to
     * `getenv()`. All three are cleared so the next `env()` call cannot see the value.
     */
    private static function forgetEnvironmentValue(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        // Avoid flushing Redis cache in integration environments where other services share the Redis instance; array cache is safe to flush per test.
        if (config('cache.default') !== 'redis') {
            Cache::flush();
        }

        parent::tearDown();
    }
}
