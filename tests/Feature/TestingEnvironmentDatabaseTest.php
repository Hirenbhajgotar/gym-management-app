<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class TestingEnvironmentDatabaseTest extends TestCase
{
    public function test_testing_environment_resolves_to_test_database(): void
    {
        $database = config('database.connections.mysql.database');

        $this->assertSame(
            'gym_app_test',
            $database,
            "The testing environment database must be 'gym_app_test', got '{$database}'"
        );
    }

    public function test_database_is_never_production_gym_app_in_tests(): void
    {
        $database = config('database.connections.mysql.database');

        $this->assertNotSame(
            'gym_app',
            $database,
            'CRITICAL SAFETY ERROR: Tests resolved connection to the main production database "gym_app"!'
        );
    }

    public function test_env_database_in_testing_is_gym_app_test(): void
    {
        $this->assertSame('gym_app_test', env('DB_DATABASE'));
        $this->assertSame('gym_app_test', getenv('DB_DATABASE'));
    }
}
