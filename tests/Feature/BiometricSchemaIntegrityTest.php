<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Read-only schema integrity tests verifying table structures and dropped columns.
 * Designed to run without RefreshDatabase to preserve test suite performance.
 * Any tests performing database writes must live in a dedicated RefreshDatabase test class.
 */
class BiometricSchemaIntegrityTest extends TestCase
{
    public function test_biometric_templates_table_has_expected_schema(): void
    {
        $this->assertTrue(Schema::hasTable('biometric_templates'));
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'id'));
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'gym_member_id'));
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'sync_status'));
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'enrolled_at'));
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'hardware_confirmed_at'));

        // OpenSpec §3 retains Superseded rows for audit, so the retirement is a soft delete
        // and the erasure-override discriminator must remain queryable.
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'superseded_reason'));

        // The erasure lifecycle's discriminators and observability columns. `erasure_attempts`
        // and `erasure_first_attempted_at` are what let a cloud obligation stop looping without
        // being mistaken for a transient failure; `erasure_requested_at` is the Art. 17 vs
        // machine-park discriminator.
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'is_erasure_obligation'));
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'erasure_attempts'));
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'erasure_first_attempted_at'));
        $this->assertTrue(Schema::hasColumn('biometric_templates', 'erasure_requested_at'));
    }

    public function test_biometric_device_table_has_expected_schema(): void
    {
        $this->assertTrue(Schema::hasTable('biometric_devices'));

        // Columns that should exist in forward schema
        $this->assertTrue(Schema::hasColumn('biometric_devices', 'id'));
        $this->assertTrue(Schema::hasColumn('biometric_devices', 'gym_id'));
        $this->assertTrue(Schema::hasColumn('biometric_devices', 'device_name'));
        $this->assertTrue(Schema::hasColumn('biometric_devices', 'device_serial'));
        $this->assertTrue(Schema::hasColumn('biometric_devices', 'status'));
        $this->assertTrue(Schema::hasColumn('biometric_devices', 'adms_enabled'));
        $this->assertTrue(Schema::hasColumn('biometric_devices', 'adms_comm_key_hash'));
        $this->assertTrue(Schema::hasColumn('biometric_devices', 'adms_comm_key_salt'));
        $this->assertTrue(Schema::hasColumn('biometric_devices', 'last_heartbeat_at'));

        // Forward-schema assertions: Columns dropped by intentional migrations must not exist in active schema
        $this->assertFalse(Schema::hasColumn('biometric_devices', 'door_control_type'));
        $this->assertFalse(Schema::hasColumn('biometric_devices', 'door_controller_url'));
        $this->assertFalse(Schema::hasColumn('biometric_devices', 'door_controller_token'));
    }

    public function test_adms_device_commands_table_has_expected_schema(): void
    {
        $this->assertTrue(Schema::hasTable('adms_device_commands'));

        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'id'));
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'device_id'));
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'command_type'));
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'command_payload'));
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'status'));
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'attempt_count'));
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'leased_at'));
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'expires_at'));
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'idempotency_key'));
    }

    public function test_nullable_provider_type_migration_throws_on_down(): void
    {
        $migration = require database_path('migrations/2026_09_19_190000_make_provider_type_nullable_in_biometric_devices_table.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, 'down'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reversing nullable provider_type is irreversible');
        call_user_func([$migration, 'down']);
    }

    public function test_dropped_door_columns_migration_throws_on_down(): void
    {
        $migration = require database_path('migrations/2026_09_22_190000_remove_door_and_poll_columns_from_biometric_devices_table.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, 'down'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reversing dropped door control and polling columns is irreversible');
        call_user_func([$migration, 'down']);
    }

    public function test_nullable_enrolled_at_migration_throws_on_down(): void
    {
        $migration = require database_path('migrations/2026_09_30_220000_make_enrolled_at_nullable_in_biometric_templates_table.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, 'down'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rolling back nullability of enrolled_at is irreversible');
        call_user_func([$migration, 'down']);
    }

    public function test_revoke_page_enroll_member_migration_throws_on_down(): void
    {
        /** @var Migration&object{down(): void} $migration */
        $migration = require database_path('migrations/2026_10_01_000000_revoke_page_enroll_member_from_trainer_role.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, 'down'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rolling back revocation and deletion of page_enroll_member is prohibited');
        $migration->down();
    }

    public function test_backfill_migration_down_is_a_safe_no_op(): void
    {
        /** @var Migration&object{down(): void} $migration */
        $migration = require database_path('migrations/2026_10_03_000002_backfill_hardware_confirmed_at_from_enrolled_at.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, 'down'));

        // The backfill repairs historical evidence that no longer exists to re-derive, so
        // `down()` must be callable and must not attempt to un-write the column.
        $migration->down();

        $this->assertTrue(Schema::hasColumn('biometric_templates', 'hardware_confirmed_at'));
    }
}
