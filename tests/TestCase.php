<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

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
        putenv('DB_DATABASE=gym_app_test');
        $_ENV['DB_DATABASE'] = 'gym_app_test';
        $_SERVER['DB_DATABASE'] = 'gym_app_test';

        parent::setUp();

        $this->app->make('config')->set('database.connections.mysql.database', 'gym_app_test');

        $database = DB::connection()->getDatabaseName();

        if ($database === 'gym_app') {
            $this->fail('SAFETY: Tests are targeting the production database "gym_app". Aborting to prevent data loss.');
        }
    }
}
