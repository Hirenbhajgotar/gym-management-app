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
     * tests always target the test database.
     */
    protected function setUp(): void
    {
        // Pre-boot guard: inspect environment BEFORE overriding.
        $initialDb = Env::get('DB_DATABASE', getenv('DB_DATABASE')) ?: null;
        $initialUrl = Env::get('DB_URL', getenv('DB_URL')) ?: null;
        $isExplicitTesting = (isset($_SERVER['ARGV']) && is_array(Request::server('ARGV')) && in_array('--env=testing', Request::server('ARGV'), true))
            || Env::get('APP_ENV') === 'testing'
            || getenv('APP_ENV') === 'testing';

        throw_if(! $isExplicitTesting && ($initialDb === 'gym_app' || (is_string($initialUrl) && str_contains($initialUrl, '/gym_app'))), RuntimeException::class, 'SAFETY: DB_DATABASE/DB_URL resolves to "gym_app" without testing environment flag. Aborting before migrate:fresh.');

        putenv('DB_DATABASE=gym_app_test');
        $_ENV['DB_DATABASE'] = 'gym_app_test';
        $_SERVER['DB_DATABASE'] = 'gym_app_test';

        parent::setUp();

        $this->app->make('config')->set('database.connections.mysql.database', 'gym_app_test');

        $database = DB::connection()->getDatabaseName();

        if ($database === 'gym_app') {
            $this->fail('SAFETY: Tests are targeting the production database "gym_app". Aborting to prevent data loss.');
        }

        $configDb = config('database.connections.mysql.database');
        if ($configDb === 'gym_app') {
            $this->fail('SAFETY: config("database.connections.mysql.database") resolved to "gym_app". Aborting.');
        }
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
