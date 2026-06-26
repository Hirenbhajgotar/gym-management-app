<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

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

        $database = $this->app->make('config')->get('database.connections.mysql.database');

        if ($database === 'gym_app') {
            $this->fail('SAFETY: Tests are targeting the production database "gym_app". Aborting to prevent data loss.');
        }
    }
}
