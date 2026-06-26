<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Diagnostic test WITHOUT RefreshDatabase to verify pre-existing table state.
 *
 * This test does NOT use RefreshDatabase, so it relies on whatever
 * tables already exist in gym_app_test. If this test fails with
 * "table not found", it means the database wasn't migrated before tests run.
 */
class DatabaseWithoutRefreshDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_database_is_gym_app_test_even_without_refresh(): void
    {
        $database = config('database.connections.mysql.database');

        $this->assertSame(
            'gym_app_test',
            $database,
            'The test database must be gym_app_test even without RefreshDatabase.'
        );
    }

    public function test_mysql_connection_works_without_refresh(): void
    {
        try {
            $connection = DB::connection('mysql');
            $connection->getPdo();

            $actualDb = $connection->selectOne('SELECT DATABASE() as db');
            $this->assertSame(
                'gym_app_test',
                $actualDb->db,
                "Connected to wrong database: {$actualDb->db} (expected gym_app_test)"
            );
        } catch (\Throwable $e) {
            $this->fail("MySQL connection failed: {$e->getMessage()}");
        }
    }

    public function test_users_table_exists_without_refresh(): void
    {
        $this->assertTrue(
            Schema::hasTable('users'),
            'The users table should exist in gym_app_test even without RefreshDatabase. ' .
            'Run: docker-compose exec app composer test:db:reset'
        );
    }

    public function test_roles_table_exists_without_refresh(): void
    {
        $this->assertTrue(
            Schema::hasTable('roles'),
            'The roles table should exist in gym_app_test (from spatie/laravel-permission).'
        );
    }

    public function test_can_query_users_without_refresh(): void
    {
        $users = DB::table('users')->count();

        $this->assertIsInt($users);
        $this->assertGreaterThanOrEqual(0, $users);
    }

    public function test_can_insert_and_query_without_refresh(): void
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Diagnostic No Refresh',
            'email' => 'diag-no-refresh-' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(0, $id);

        $found = DB::table('users')->where('id', $id)->first();
        $this->assertNotNull($found);
        $this->assertSame('Diagnostic No Refresh', $found->name);

        DB::table('users')->where('id', $id)->delete();
    }

    public function test_at_least_5_tables_exist(): void
    {
        $tables = DB::select('SHOW TABLES');
        $tableCount = count($tables);

        $this->assertGreaterThanOrEqual(
            5,
            $tableCount,
            "gym_app_test only has {$tableCount} tables. Expected at least 5. " .
            'Run: docker-compose exec app composer test:db:reset'
        );
    }
}
