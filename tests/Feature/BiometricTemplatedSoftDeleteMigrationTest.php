<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The retired-template migrations must be genuinely reversible: a `down()` that cannot undo an
 * `up()` strands the schema mid-state on rollback.
 */
class BiometricTemplatedSoftDeleteMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function softDeleteMigration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_10_06_000000_soft_delete_superseded_biometric_templates.php');

        return $migration;
    }

    protected function deletedAtIndexMigration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_10_07_000000_index_deleted_at_on_biometric_templates_table.php');

        return $migration;
    }

    #[Test]
    public function the_soft_delete_migration_up_adds_both_the_reason_column_and_the_soft_delete_column(): void
    {
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'superseded_reason'));
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'deleted_at'));

        $migration = $this->softDeleteMigration();
        $this->assertTrue(method_exists($migration, 'down'));
        $migration->down();

        $this->assertFalse(Schema::hasColumn('biometric_templates', 'superseded_reason'));
        $this->assertFalse(Schema::hasColumn('biometric_templates', 'deleted_at'));

        $this->assertTrue(method_exists($migration, 'up'));
        call_user_func([$migration, 'up']);

        $this->assertTrue(Schema::hasColumn('biometric_templates', 'superseded_reason'));
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'deleted_at'));
    }

    #[Test]
    public function the_deleted_at_index_migration_is_reversible(): void
    {
        $migration = $this->deletedAtIndexMigration();

        $this->assertTrue(method_exists($migration, 'down'));
        $migration->down();
        $this->assertFalse($this->hasDeletedAtIndex());

        $this->assertTrue(method_exists($migration, 'up'));
        call_user_func([$migration, 'up']);
        $this->assertTrue($this->hasDeletedAtIndex());
    }

    protected function hasDeletedAtIndex(): bool
    {
        return collect(Schema::getIndexes('biometric_templates'))
            ->contains(fn (array $index): bool => $index['name'] === 'biometric_templates_deleted_at_index');
    }
}
