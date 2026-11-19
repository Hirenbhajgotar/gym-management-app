<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

/**
 * Keep RefreshDatabase's per-test transaction isolation without replaying the
 * whole migration history on every PHPUnit process.
 *
 * Laravel's RefreshDatabase runs `migrate:fresh` once per process. With this
 * project's 215 migrations that DDL replay costs roughly 110 seconds before the
 * first assertion runs. This trait skips that replay when every registered
 * migration is already recorded as applied, and still falls back to a real
 * `migrate:fresh` when the schema is missing or out of date.
 *
 * After adding or changing a migration, rebuild the schema explicitly with
 * `composer test:db:reset` (or `composer test:fresh`).
 */
trait ReusesMigratedTestDatabase
{
    use RefreshDatabase {
        refreshTestDatabase as protected refreshDatabaseFromLaravel;
    }

    /**
     * Refresh a conventional test database, reusing an up-to-date schema.
     */
    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated && $this->testDatabaseIsFullyMigrated()) {
            RefreshDatabaseState::$migrated = true;
        }

        $this->refreshDatabaseFromLaravel();
    }

    /**
     * Whether every migration file the migrate command would load is already applied.
     */
    private function testDatabaseIsFullyMigrated(): bool
    {
        $migrator = $this->app->make('migrator');

        if (! $migrator->repositoryExists()) {
            return false;
        }

        $paths = array_merge($migrator->paths(), [$this->app->databasePath('migrations')]);
        $migrationFiles = $migrator->getMigrationFiles($paths);
        $appliedMigrations = $migrator->getRepository()->getRan();

        foreach (array_keys($migrationFiles) as $name) {
            if (! in_array($name, $appliedMigrations, true)) {
                return false;
            }
        }

        return true;
    }
}
