<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class BiometricSchemaIntegrityTest extends TestCase
{
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
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_09_19_190000_make_provider_type_nullable_in_biometric_devices_table.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reversing nullable provider_type is irreversible');
        $migration->down();
    }

    public function test_dropped_door_columns_migration_throws_on_down(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_09_22_190000_remove_door_and_poll_columns_from_biometric_devices_table.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reversing dropped door control and polling columns is irreversible');
        $migration->down();
    }
}
