<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Diagnostic test to verify database setup for the test suite.
 *
 * These tests help identify whether RefreshDatabase is working correctly
 * and whether the test database (gym_app_test) has the expected tables.
 */
class DatabaseDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_database_creates_users_table(): void
    {
        $this->assertTrue(
            Schema::hasTable('users'),
            'RefreshDatabase should migrate the users table. Check that migrations run successfully on gym_app_test.'
        );
    }

    public function test_refresh_database_creates_roles_table(): void
    {
        $this->assertTrue(
            Schema::hasTable('roles'),
            'RefreshDatabase should migrate the roles table (from spatie/laravel-permission).'
        );
    }

    public function test_refresh_database_creates_gyms_table(): void
    {
        $this->assertTrue(
            Schema::hasTable('gyms'),
            'RefreshDatabase should migrate the gyms table.'
        );
    }

    public function test_refresh_database_creates_membership_plans_table(): void
    {
        $this->assertTrue(
            Schema::hasTable('membership_plans'),
            'RefreshDatabase should migrate the membership_plans table.'
        );
    }

    public function test_database_is_gym_app_test(): void
    {
        $database = config('database.connections.mysql.database');

        $this->assertSame(
            'gym_app_test',
            $database,
            'The test database must be gym_app_test, got: ' . $database
        );
    }

    public function test_can_insert_and_retrieve_user(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_can_query_all_expected_tables(): void
    {
        $expectedTables = [
            'users',
            'gyms',
            'gym_user',
            'roles',
            'permissions',
            'membership_plans',
            'trainers',
            'attendances',
            'gym_members',
            'migrations',
        ];

        $missingTables = [];

        foreach ($expectedTables as $table) {
            if (! Schema::hasTable($table)) {
                $missingTables[] = $table;
            }
        }

        $this->assertEmpty(
            $missingTables,
            'The following tables are missing from gym_app_test: ' . implode(', ', $missingTables) .
            '. Run: docker-compose exec app composer test:db:reset'
        );
    }

    public function test_tables_are_fresh_after_refresh_database(): void
    {
        $user = \App\Models\User::factory()->create(['name' => 'Diagnostic User']);

        $this->assertDatabaseHas('users', ['name' => 'Diagnostic User']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_mysql_connection_works(): void
    {
        $database = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host');

        try {
            $connection = DB::connection('mysql');
            $connection->getPdo();

            $this->assertTrue(true, 'MySQL connection successful');

            $actualDb = $connection->selectOne('SELECT DATABASE() as db');
            $this->assertSame(
                'gym_app_test',
                $actualDb->db,
                "Connected to wrong database: {$actualDb->db} (expected gym_app_test)"
            );
        } catch (\Throwable $e) {
            $this->fail("MySQL connection failed: {$e->getMessage()}. Host: {$host}, Database: {$database}");
        }
    }

    public function test_migration_status(): void
    {
        $database = config('database.connections.mysql.database');

        $this->assertSame('gym_app_test', $database);

        $tables = DB::select('SHOW TABLES');
        $tableCount = count($tables);

        $this->assertGreaterThan(
            0,
            $tableCount,
            'gym_app_test has no tables. Migrations may not have run. Run: docker-compose exec app composer test:db:reset'
        );
    }
}
