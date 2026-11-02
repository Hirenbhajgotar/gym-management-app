<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Models\AdmsDeviceCommand;
use App\Models\BiometricTemplate;
use App\Models\DoorEvent;
use App\Models\Gym;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Pins the schema cutover and the pure backfill logic of the 2026_10_31
 * biometric audit-retention migrations without ever running a rollback that
 * could perform DDL.
 */
class BiometricSchemaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_audit_snapshot_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'user_table'));
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'device_serial'));
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'max_attempt_count'));
        $this->assertTrue(Schema::hasColumn('door_events', 'device_serial'));
        $this->assertTrue(Schema::hasColumn('biometric_event_receipts', 'device_serial'));
    }

    /**
     * The serial is snapshotted, so it must fit the widest value the source column and the
     * device form accept. A 64-char column silently truncated longer hardware serials.
     */
    public function test_a_hundred_character_serial_round_trips_through_the_snapshots(): void
    {
        $serial = str_repeat('SN-0123456789-', 7) . 'EN';
        $this->assertSame(100, strlen($serial));

        $device = \App\Models\BiometricDevice::factory()->create([
            'gym_id' => Gym::factory()->create()->id,
            'device_serial' => $serial,
        ]);

        $command = AdmsDeviceCommand::query()->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
            'command_type' => 'reboot',
            'command_payload' => null,
            'status' => \App\Enums\AdmsCommandStatusEnum::PENDING,
        ]);

        $this->assertSame($serial, $command->fresh()?->device_serial);
        $this->assertSame($serial, DoorEvent::query()->create([
            'gym_id' => $device->gym_id,
            'device_id' => $device->id,
            'device_serial' => $serial,
            'event_type' => 'unlock_granted',
            'trigger_source' => 'biometric',
            'door_status' => 'unlocked',
        ])->fresh()?->device_serial);
    }

    public function test_access_control_device_foreign_keys_are_nullable(): void
    {
        $this->assertTrue($this->columnIsNullable('door_events', 'device_id'));
        $this->assertTrue($this->columnIsNullable('biometric_event_receipts', 'device_id'));
    }

    /**
     * The audit rows written before the snapshots existed must keep their attribution. The
     * test suite always runs against an already-migrated schema, so the backfill is invoked
     * directly rather than by replaying `up()`'s DDL.
     */
    public function test_the_serial_backfills_populate_pre_migration_rows(): void
    {
        $gym = Gym::factory()->create();
        $device = \App\Models\BiometricDevice::factory()->create([
            'gym_id' => $gym->id,
            'device_serial' => 'SN-BACKFILL-42',
        ]);

        $event = DoorEvent::query()->create([
            'gym_id' => $gym->id,
            'device_id' => $device->id,
            'device_serial' => null,
            'event_type' => 'unlock_granted',
            'trigger_source' => 'biometric',
            'door_status' => 'unlocked',
        ]);

        $receipt = \App\Models\BiometricEventReceipt::factory()->create([
            'gym_id' => $gym->id,
            'device_id' => $device->id,
            'device_serial' => null,
        ]);

        $auditMigration = $this->migration('2026_10_31_000300_preserve_access_control_audit_after_device_deletion.php');
        $doorBackfill = new ReflectionMethod($auditMigration, 'backfillDoorEventSerials');
        $doorBackfill->setAccessible(true);
        $doorBackfill->invoke($auditMigration);

        $receiptsMigration = $this->migration('2026_11_01_000000_add_device_serial_to_biometric_event_receipts_table.php');
        $receiptBackfill = new ReflectionMethod($receiptsMigration, 'backfillDeviceSerials');
        $receiptBackfill->setAccessible(true);
        $receiptBackfill->invoke($receiptsMigration);

        $this->assertSame('SN-BACKFILL-42', $event->fresh()?->device_serial);
        $this->assertSame('SN-BACKFILL-42', $receipt->fresh()?->device_serial);
    }

    public function test_stored_override_reads_a_valid_encrypted_value(): void
    {
        $migration = $this->userTableMigration();

        $raw = Crypt::encryptString(json_encode(['adms_user_table' => 'user'], JSON_THROW_ON_ERROR));

        $this->assertSame('user', $this->invokeStoredOverride($migration, $raw, 41));
    }

    public function test_stored_override_normalizes_the_userinfo_value_case_insensitively(): void
    {
        $migration = $this->userTableMigration();

        $raw = Crypt::encryptString(json_encode(['adms_user_table' => 'UserInfo'], JSON_THROW_ON_ERROR));

        $this->assertSame('USERINFO', $this->invokeStoredOverride($migration, $raw, 42));
    }

    public function test_stored_override_returns_null_for_an_unknown_value(): void
    {
        $migration = $this->userTableMigration();

        $raw = Crypt::encryptString(json_encode(['adms_user_table' => 'USERS'], JSON_THROW_ON_ERROR));

        $this->assertNull($this->invokeStoredOverride($migration, $raw, 43));
    }

    public function test_stored_override_returns_null_and_logs_when_decryption_fails(): void
    {
        Log::spy();

        $migration = $this->userTableMigration();

        $this->assertNull($this->invokeStoredOverride($migration, 'not-a-valid-ciphertext', 77));

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => ($context['device_id'] ?? null) === 77
                && isset($context['exception']));
    }

    public function test_stored_override_returns_null_for_a_non_string_value(): void
    {
        $migration = $this->userTableMigration();

        $this->assertNull($this->invokeStoredOverride($migration, ['adms_user_table' => 'user'], 44));
    }

    public function test_normalize_only_accepts_known_user_tables(): void
    {
        $migration = $this->userTableMigration();
        $normalize = new ReflectionMethod($migration, 'normalize');
        $normalize->setAccessible(true);

        $this->assertSame('USERINFO', $normalize->invoke($migration, 'USERINFO'));
        $this->assertSame('user', $normalize->invoke($migration, ' User '));
        $this->assertNull($normalize->invoke($migration, ['USERINFO']));
        $this->assertNull($normalize->invoke($migration, 'UNKNOWN'));
    }

    public function test_adms_ledger_rollback_refuses_before_dropping_the_serial_snapshot(): void
    {
        AdmsDeviceCommand::factory()->create(['device_id' => null]);

        $migration = $this->migration('2026_10_31_000200_keep_adms_command_ledger_after_device_deletion.php');

        try {
            $migration->down();
            $this->fail('Expected the forward-only ledger rollback to refuse.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('the ledger contains rows whose device was deleted', $exception->getMessage());
        }

        // The guard runs before any DDL, so the serial snapshot must still exist.
        $this->assertTrue(Schema::hasColumn('adms_device_commands', 'device_serial'));
    }

    public function test_template_rollback_refuses_detached_hardware_credentials(): void
    {
        BiometricTemplate::factory()->deviceNative()->create(['enrolled_by_device_id' => null]);

        $migration = $this->migration('2026_10_31_000000_set_null_on_delete_for_biometric_templates_enrolled_by_device_id.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('detached hardware credentials exist');

        $migration->down();
    }

    /**
     * A detached cloud row legitimately had no terminal before this changeset, so it must not
     * block the restrictive key. Asserted through the guard itself rather than by executing
     * `down()`: the DDL would poison the shared schema if the guard ever regressed.
     */
    public function test_template_rollback_guard_ignores_detached_cloud_rows(): void
    {
        $gym = Gym::factory()->create();

        BiometricTemplate::factory()->cloudBacked()->create([
            'gym_id' => $gym->id,
            'enrolled_by_device_id' => null,
        ]);

        $migration = $this->migration('2026_10_31_000000_set_null_on_delete_for_biometric_templates_enrolled_by_device_id.php');
        $guard = new ReflectionMethod($migration, 'detachedHardwareCredentialsExist');
        $guard->setAccessible(true);

        $this->assertFalse($guard->invoke($migration));

        BiometricTemplate::factory()->deviceNative()->create([
            'gym_id' => $gym->id,
            'enrolled_by_device_id' => null,
        ]);

        $this->assertTrue($guard->invoke($migration));
    }

    public function test_access_control_rollback_refuses_before_altering_the_tables(): void
    {
        DoorEvent::query()->create([
            'gym_id' => Gym::factory()->create()->id,
            'device_id' => null,
            'event_type' => 'unlock_granted',
            'trigger_source' => 'biometric',
            'door_status' => 'unlocked',
        ]);

        $migration = $this->migration('2026_10_31_000300_preserve_access_control_audit_after_device_deletion.php');

        try {
            $migration->down();
            $this->fail('Expected the forward-only access-control rollback to refuse.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('access-control audit rows', $exception->getMessage());
        }

        // The guard runs before any DDL, so the snapshot column must still exist.
        $this->assertTrue(Schema::hasColumn('door_events', 'device_serial'));
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        foreach (Schema::getColumns($table) as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return (bool) ($definition['nullable'] ?? false);
            }
        }

        $this->fail("Column {$table}.{$column} does not exist.");
    }

    private function userTableMigration(): object
    {
        return $this->migration('2026_10_31_000100_add_user_table_to_adms_device_commands_table.php');
    }

    private function migration(string $file): Migration
    {
        $migration = require database_path('migrations/' . $file);

        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function invokeStoredOverride(object $migration, mixed $raw, int|string|null $deviceId = null): ?string
    {
        $method = new ReflectionMethod($migration, 'storedOverride');
        $method->setAccessible(true);

        /** @var string|null $override */
        $override = $method->invoke($migration, $raw, $deviceId);

        return $override;
    }
}
