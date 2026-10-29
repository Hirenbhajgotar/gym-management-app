<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\BackfillAdmsCommKeyHashesCommand;
use Spatie\Activitylog\ActivityLogger;
use Spatie\Activitylog\PendingActivityLog;
use App\Biometrics\Services\CommKeyReveal;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\BiometricProvidersEnum;
use App\Enums\DefaultRole;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class BackfillAdmsCommKeyHashesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        config(['biometrics.adms.pbkdf2_iterations' => 1000]);

        $user = User::factory()->create();
        $this->gym = Gym::factory()
            ->hasAttached($user, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            ->create();
    }

    public function test_dry_run_mutates_nothing(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => 'secret123'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--dry-run' => true])
            ->expectsOutputToContain('ADMS comm key backfill completed. 1 migrated (0 normalized), 0 refused, 0 provisioned (0 failed), 0 stale plaintext cleaned (dry run).')
            ->assertSuccessful();

        $device->refresh();
        $this->assertNull($device->adms_comm_key_hash);
        $this->assertNull($device->adms_comm_key_salt);
        $this->assertSame(['pushcommkey' => 'secret123'], $device->provider_config);
    }

    public function test_a_concurrent_rotation_is_not_overwritten_by_the_backfill(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => 'secret123'],
        ]);

        $stale = BiometricDevice::query()->findOrFail($device->id);
        $rotatedHash = 'concurrent-rotation-hash';

        DB::table('biometric_devices')->where('id', $device->id)->update([
            'adms_comm_key_hash' => $rotatedHash,
            'adms_comm_key_salt' => 'concurrent-rotation-salt',
        ]);

        $command = resolve(BackfillAdmsCommKeyHashesCommand::class);
        $lockGuard = new ReflectionMethod($command, 'lockUnchangedDevice');
        $lockGuard->setAccessible(true);

        try {
            DB::transaction(static fn () => $lockGuard->invoke($command, $stale));
            $this->fail('The backfill lock guard accepted a stale device snapshot.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('changed concurrently', $exception->getMessage());
        }

        $this->assertSame(
            $rotatedHash,
            DB::table('biometric_devices')->where('id', $device->id)->value('adms_comm_key_hash')
        );
    }

    public function test_the_lock_guard_rejects_a_provider_type_only_change_and_retains_the_comm_key(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        $stale = BiometricDevice::query()->findOrFail($device->id);

        // Only provider_type changes: provider_config itself is untouched, so a
        // hash/salt/config-only comparison would pass and cleanup would treat
        // the cloud credential as an ADMS alias.
        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_type' => BiometricProvidersEnum::Custom->value]);

        $command = resolve(BackfillAdmsCommKeyHashesCommand::class);
        $lockGuard = new ReflectionMethod($command, 'lockUnchangedDevice');
        $lockGuard->setAccessible(true);

        try {
            DB::transaction(static fn () => $lockGuard->invoke($command, $stale));
            $this->fail('The backfill lock guard accepted a stale device snapshot after a provider-type change.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('changed concurrently', $exception->getMessage());
        }

        $fresh = $device->refresh();

        $this->assertSame(BiometricProvidersEnum::Custom, $fresh->provider_type);
        $this->assertSame('123456', $fresh->provider_config['comm_key'] ?? null);
    }

    public function test_cleanup_conflicts_and_retains_a_comm_key_when_the_row_becomes_cloud_before_the_lock(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-CLEANUP-STALE-CLOUD-001',
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['comm_key' => '123456', 'other_setting' => 'safe'],
        ]);

        $mutated = false;

        // The device is hydrated once by the legacy collector and once by the
        // cleanup chunk. Mutate on the second hydration: the cleanup snapshot
        // is already native in memory, while the database row becomes cloud
        // before the transaction locks it. The later lock-query hydration is
        // the third retrieval and is ignored.
        $retrievals = 0;

        BiometricDevice::retrieved(function (BiometricDevice $retrieved) use ($device, &$mutated, &$retrievals): void {
            if ($mutated || $retrieved->getKey() !== $device->getKey()) {
                return;
            }

            $retrievals++;

            if ($retrievals !== 2) {
                return;
            }

            $mutated = true;

            DB::table('biometric_devices')
                ->where('id', $device->id)
                ->update(['provider_type' => BiometricProvidersEnum::Custom->value]);
        });

        $buffer = new BufferedOutput();

        $exitCode = Artisan::call('biometrics:backfill-adms-comm-keys', [
            '--clean-plaintext' => true,
        ], $buffer);

        $output = $buffer->fetch();

        $this->assertTrue($mutated, 'The stale cleanup snapshot was never exercised.');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('cleanup conflict', $output);

        $fresh = $device->refresh();

        $this->assertSame(BiometricProvidersEnum::Custom, $fresh->provider_type);
        $this->assertSame('123456', $fresh->provider_config['comm_key'] ?? null);
        $this->assertSame('safe', $fresh->provider_config['other_setting'] ?? null);
    }

    public function test_an_omitted_cloud_marker_survives_an_eloquent_rewrite_and_cleanup(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-CLEANUP-CLOUD-OMITTED-MARKER-001',
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update([
                'provider_config' => Crypt::encryptString(json_encode([
                    'pushcommkey' => '123456',
                    'comm_key' => 'CLOUD-KEY-123456',
                    'comm_key_source' => 'cloud',
                ], JSON_THROW_ON_ERROR)),
            ]);

        $device->refresh();

        // An omission-style Eloquent rewrite must re-add the persisted marker;
        // otherwise `--clean-plaintext` would treat the cloud credential as an
        // ADMS alias and delete it.
        $device->update([
            'device_name' => 'Renamed without the marker',
            'provider_config' => [
                'pushcommkey' => '123456',
                'comm_key' => 'CLOUD-KEY-123456',
            ],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--clean-plaintext' => true])
            ->expectsOutputToContain('will remove pushcommkey and retain comm_key by provider policy')
            ->expectsOutputToContain('1 stale plaintext cleaned')
            ->assertSuccessful();

        $fresh = $device->refresh();

        $this->assertSame('CLOUD-KEY-123456', $fresh->provider_config['comm_key'] ?? null);
        $this->assertSame('cloud', $fresh->provider_config['comm_key_source'] ?? null);
        $this->assertArrayNotHasKey('pushcommkey', $fresh->provider_config);
        $this->assertFalse($fresh->mayDeleteCommKeyAlias());
    }

    public function test_cleanup_conflicts_and_retains_the_key_when_adms_is_disabled_before_the_lock(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-CLEANUP-STALE-ADMS-ENABLED-001',
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        $mutated = false;

        $this->mutateOnRetrieval($device, 2, function () use ($device): void {
            DB::table('biometric_devices')
                ->where('id', $device->id)
                ->update(['adms_enabled' => false]);
        }, $mutated);

        [$exitCode, $output] = $this->runBackfill(['--clean-plaintext' => true]);

        $this->assertTrue($mutated, 'The stale cleanup snapshot was never exercised.');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('cleanup conflict', $output);

        $fresh = $device->refresh();

        $this->assertFalse($fresh->adms_enabled);
        $this->assertSame('123456', $fresh->provider_config['comm_key'] ?? null);
    }

    public function test_cleanup_conflicts_and_retains_the_key_when_updated_at_changes_before_the_lock(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-CLEANUP-STALE-UPDATED-AT-001',
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        $mutated = false;

        $this->mutateOnRetrieval($device, 2, function () use ($device): void {
            DB::table('biometric_devices')
                ->where('id', $device->id)
                ->update([
                    'device_name' => 'Renamed During Cleanup',
                    'updated_at' => now()->addMinute(),
                ]);
        }, $mutated);

        [$exitCode, $output] = $this->runBackfill(['--clean-plaintext' => true]);

        $this->assertTrue($mutated, 'The stale cleanup snapshot was never exercised.');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('cleanup conflict', $output);

        $fresh = $device->refresh();

        $this->assertSame('Renamed During Cleanup', $fresh->device_name);
        $this->assertSame('123456', $fresh->provider_config['comm_key'] ?? null);
    }

    public function test_cleanup_conflicts_and_retains_the_key_when_only_the_config_is_rewritten_before_the_lock(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-CLEANUP-STALE-CONFIG-001',
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        $mutated = false;

        $this->mutateOnRetrieval($device, 2, function () use ($device): void {
            DB::table('biometric_devices')
                ->where('id', $device->id)
                ->update([
                    'provider_config' => Crypt::encryptString(json_encode([
                        'comm_key' => '123456',
                        'other_setting' => 'added during cleanup',
                    ], JSON_THROW_ON_ERROR)),
                ]);
        }, $mutated);

        [$exitCode, $output] = $this->runBackfill(['--clean-plaintext' => true]);

        $this->assertTrue($mutated, 'The stale cleanup snapshot was never exercised.');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('cleanup conflict', $output);

        $fresh = $device->refresh();

        $this->assertSame('123456', $fresh->provider_config['comm_key'] ?? null);
        $this->assertSame('added during cleanup', $fresh->provider_config['other_setting'] ?? null);
    }

    public function test_cleanup_conflicts_and_does_not_recreate_a_row_deleted_before_the_lock(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-CLEANUP-STALE-DELETED-001',
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['comm_key' => '123456'],
        ]);

        $mutated = false;

        $this->mutateOnRetrieval($device, 2, function () use ($device): void {
            DB::table('biometric_devices')
                ->where('id', $device->id)
                ->delete();
        }, $mutated);

        [$exitCode, $output] = $this->runBackfill(['--clean-plaintext' => true]);

        $this->assertTrue($mutated, 'The stale cleanup snapshot was never exercised.');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('cleanup conflict', $output);

        $this->assertDatabaseMissing('biometric_devices', ['id' => $device->id]);
    }

    public function test_provisioning_conflicts_and_installs_no_hash_when_the_row_changes_before_the_lock(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-PROVISION-STALE-001',
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        $mutated = false;

        $this->mutateOnRetrieval($device, 2, function () use ($device): void {
            DB::table('biometric_devices')
                ->where('id', $device->id)
                ->update([
                    'device_name' => 'Renamed During Provisioning',
                    'updated_at' => now()->addMinute(),
                ]);
        }, $mutated);

        [$exitCode, $output] = $this->runBackfill([
            '--provision-missing' => true,
            '--reveal' => true,
        ]);

        $this->assertTrue($mutated, 'The provisioning snapshot was never exercised.');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('changed concurrently', $output);
        $this->assertStringContainsString('provisioning failed', $output);
        $this->assertStringNotContainsString('PROVISIONED KEY', $output);

        $fresh = $device->refresh();

        $this->assertSame('Renamed During Provisioning', $fresh->device_name);
        $this->assertNull($fresh->adms_comm_key_hash);
        $this->assertNull($fresh->adms_comm_key_salt);
    }

    public function test_cleanup_retains_a_comm_key_marked_as_a_cloud_provider_credential(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-CLEANUP-CLOUD-MARKED-001',
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        // A native row can legitimately carry a cloud credential after a
        // cloud→native switch. The server-derived marker protects it.
        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update([
                'provider_config' => Crypt::encryptString(json_encode([
                    'pushcommkey' => '123456',
                    'comm_key' => 'cloud-provider-key',
                    'comm_key_source' => 'cloud',
                ], JSON_THROW_ON_ERROR)),
            ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--clean-plaintext' => true])
            ->expectsOutputToContain('will remove pushcommkey and retain comm_key by provider policy')
            ->expectsOutputToContain('1 stale plaintext cleaned')
            ->assertSuccessful();

        $fresh = $device->refresh();

        $this->assertArrayNotHasKey('pushcommkey', $fresh->provider_config);
        $this->assertSame('cloud-provider-key', $fresh->provider_config['comm_key'] ?? null);
        $this->assertSame('cloud', $fresh->provider_config['comm_key_source'] ?? null);
        $this->assertFalse($fresh->mayDeleteCommKeyAlias());
    }

    public function test_string_key_migrates_to_pbkdf2_and_removes_plaintext(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => 'secret123', 'other_setting' => 'safe'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys')
            ->expectsOutputToContain('ADMS comm key backfill completed. 1 migrated (0 normalized), 0 refused, 0 provisioned (0 failed), 0 stale plaintext cleaned.')
            ->assertSuccessful();

        $device->refresh();
        $this->assertNotNull($device->adms_comm_key_salt);
        $expectedHash = BiometricDevice::hashCommKey('secret123', $device->adms_comm_key_salt);
        $this->assertSame($expectedHash, $device->adms_comm_key_hash);
        $this->assertIsArray($device->provider_config);
        $this->assertArrayNotHasKey('pushcommkey', $device->provider_config);
        $this->assertSame('safe', $device->provider_config['other_setting'] ?? null);
        $this->assertTrue($device->verifyCommKey('secret123'));
    }

    public function test_whitespace_bearing_key_is_hashed_in_sanitized_form(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => " \t123 456\n "],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys')
            ->expectsOutputToContain('comm key contains whitespace; migrating its normalized form')
            ->expectsOutputToContain('1 migrated (1 normalized)')
            ->assertSuccessful();

        $device->refresh();
        $this->assertSame(
            BiometricDevice::hashCommKey('123456', (string) $device->adms_comm_key_salt),
            $device->adms_comm_key_hash
        );
        $this->assertTrue($device->verifyCommKey('123456'));
        $this->assertTrue($device->verifyCommKey(' 123 456 '));
        $this->assertArrayNotHasKey('pushcommkey', $device->provider_config);
    }

    public function test_comm_key_alias_is_migrated_and_deleted_for_native_and_legacy_null_devices(): void
    {
        $native = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_comm_key_hash' => null,
            'provider_config' => ['comm_key' => 'abc123'],
        ]);

        $legacyNative = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => null,
            'adms_comm_key_hash' => null,
            'provider_config' => ['comm_key' => 'xyz789'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys')
            ->expectsOutputToContain('2 migrated (0 normalized)')
            ->assertSuccessful();

        $native->refresh();
        $legacyNative->refresh();

        $this->assertArrayNotHasKey('comm_key', $native->provider_config);
        $this->assertTrue($native->verifyCommKey('abc123'));

        // provider_type=null is the legacy native shape: it is read and deleted
        // with the same policy as an explicit DeviceNative row.
        $this->assertArrayNotHasKey('comm_key', $legacyNative->provider_config);
        $this->assertTrue($legacyNative->verifyCommKey('xyz789'));
    }

    public function test_key_that_sanitizes_to_empty_is_refused(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'provider_config' => ['pushcommkey' => " \t\n"],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys')
            ->expectsOutputToContain('normalizes to an empty string')
            ->expectsOutputToContain('0 migrated (0 normalized), 1 refused, 0 provisioned (0 failed), 0 stale plaintext cleaned.')
            ->assertFailed();

        $this->assertNull($device->refresh()->adms_comm_key_hash);
    }

    public function test_conflicting_aliases_are_refused_and_left_for_review(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::DeviceNative,
            'adms_comm_key_hash' => null,
            'provider_config' => ['pushcommkey' => '111111', 'comm_key' => '222222'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys')
            ->expectsOutputToContain('conflicting pushcommkey and comm_key values')
            ->expectsOutputToContain('1 refused')
            ->assertFailed();

        $device->refresh();
        $this->assertNull($device->adms_comm_key_hash);
        $this->assertSame('111111', $device->provider_config['pushcommkey'] ?? null);
        $this->assertSame('222222', $device->provider_config['comm_key'] ?? null);
    }

    public function test_legacy_unsalted_sha256_fallback_verifies_correctly(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => hash('sha256', 'legacy-key-999'),
            'adms_comm_key_salt' => null, // Legacy: no salt
            'provider_config' => [],
        ]);

        $this->assertTrue($device->verifyCommKey('legacy-key-999'));
        $this->assertFalse($device->verifyCommKey('wrong-key'));
    }

    public function test_non_string_numeric_key_is_refused_with_a_failure_exit_code(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            // Stored as integer, not string
            'provider_config' => ['pushcommkey' => 12345],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys')
            ->expectsOutputToContain('Refused migration to prevent bricking device')
            ->expectsOutputToContain('0 migrated (0 normalized), 1 refused, 0 provisioned (0 failed), 0 stale plaintext cleaned.')
            ->assertFailed();

        $device->refresh();
        $this->assertNull($device->adms_comm_key_hash);
        $this->assertIsArray($device->provider_config);
        $this->assertSame(12345, $device->provider_config['pushcommkey'] ?? null);
    }

    public function test_provision_missing_generates_keys_for_unkeyed_devices(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--provision-missing' => true,
            '--reveal' => true,
        ])
            ->expectsOutputToContain('Provisioned ADMS Device Keys')
            ->expectsOutputToContain('0 migrated (0 normalized), 0 refused, 1 provisioned (0 failed), 0 stale plaintext cleaned.')
            ->assertSuccessful();

        $device->refresh();
        $this->assertNotNull($device->adms_comm_key_hash);
        $this->assertNotNull($device->adms_comm_key_salt);
    }

    public function test_provisioning_without_reveal_is_refused_before_any_mutation(): void
    {
        $migratable = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => 'secret123'],
        ]);

        $unkeyed = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--provision-missing' => true])
            ->expectsOutputToContain('Refusing to provision keys without --reveal')
            ->assertFailed();

        $this->assertNull($migratable->refresh()->adms_comm_key_hash);
        $this->assertNull($unkeyed->refresh()->adms_comm_key_hash);
        $this->assertSame(['pushcommkey' => 'secret123'], $migratable->provider_config);
    }

    public function test_provision_missing_with_reveal_prints_a_usable_key(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        $buffer = new BufferedOutput();

        $exitCode = Artisan::call('biometrics:backfill-adms-comm-keys', [
            '--provision-missing' => true,
            '--reveal' => true,
        ], $buffer);

        $this->assertSame(0, $exitCode);

        $output = $buffer->fetch();
        $this->assertMatchesRegularExpression('/PROVISIONED KEY\s+Device ID: ' . $device->id . '/', $output);
        preg_match('/PROVISIONED KEY\s+Device ID: \d+\s+SN: \S+\s+Gym ID: \d+\s+Key: ([A-Za-z0-9]+)/', $output, $matches);
        $this->assertTrue($device->refresh()->verifyCommKey($matches[1]));
    }

    public function test_provisioning_emits_earlier_keys_when_a_later_device_fails(): void
    {
        $first = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-PROVISION-OK-001',
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        $second = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_serial' => 'SN-PROVISION-FAIL-002',
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        $shouldFail = true;

        BiometricDevice::saving(function (BiometricDevice $device) use (&$shouldFail): void {
            if ($shouldFail && $device->device_serial === 'SN-PROVISION-FAIL-002') {
                $shouldFail = false;

                throw new RuntimeException('simulated save failure');
            }
        });

        $buffer = new BufferedOutput();

        $exitCode = Artisan::call('biometrics:backfill-adms-comm-keys', [
            '--provision-missing' => true,
            '--reveal' => true,
        ], $buffer);

        $output = $buffer->fetch();

        $this->assertSame(1, $exitCode);
        $this->assertMatchesRegularExpression('/PROVISIONED KEY\s+Device ID: ' . $first->id . '/', $output);
        $this->assertStringContainsString('provisioning failed', $output);

        $this->assertNotNull($first->refresh()->adms_comm_key_hash);
        $this->assertNull($second->refresh()->adms_comm_key_hash);
    }

    public function test_dry_run_provisioning_generates_no_keys(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--dry-run' => true,
            '--provision-missing' => true,
            '--reveal' => true,
        ])
            ->expectsOutputToContain('DRY RUN: devices that WOULD be provisioned')
            ->expectsOutputToContain('(dry run — not generated)')
            ->assertSuccessful();

        $device->refresh();
        $this->assertNull($device->adms_comm_key_hash);
        $this->assertNull($device->adms_comm_key_salt);
    }

    public function test_scoping_by_gym_id_and_device_id(): void
    {
        $otherGym = Gym::factory()->create();

        $device1 = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'provider_config' => ['pushcommkey' => 'key1'],
        ]);

        $device2 = BiometricDevice::factory()->create([
            'gym_id' => $otherGym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'provider_config' => ['pushcommkey' => 'key2'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--gym-id' => (string) $this->gym->id,
        ])
            ->expectsOutputToContain('ADMS comm key backfill completed. 1 migrated (0 normalized), 0 refused, 0 provisioned (0 failed), 0 stale plaintext cleaned.')
            ->assertSuccessful();

        $device1->refresh();
        $device2->refresh();

        $this->assertNotNull($device1->adms_comm_key_hash);
        $this->assertNull($device2->adms_comm_key_hash);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--device-id' => (string) $device2->id,
        ])
            ->expectsOutputToContain('1 migrated (0 normalized)')
            ->assertSuccessful();

        $this->assertNotNull($device2->refresh()->adms_comm_key_hash);
    }

    public function test_invalid_scope_options_are_rejected(): void
    {
        $this->artisan('biometrics:backfill-adms-comm-keys', ['--gym-id' => '0'])
            ->expectsOutputToContain('must be positive integers')
            ->assertFailed();
    }

    public function test_reprovision_legacy_replaces_unsalted_hashes(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => hash('sha256', 'legacy-key-999'),
            'adms_comm_key_salt' => null,
            'provider_config' => [],
        ]);

        // Without the explicit flag, it only warns about legacy devices.
        $this->artisan('biometrics:backfill-adms-comm-keys')
            ->expectsOutputToContain('legacy or unsafe comm key verifier')
            ->assertSuccessful();

        $device->refresh();
        $this->assertNull($device->adms_comm_key_salt);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--reprovision-legacy' => true,
            '--reveal' => true,
        ])
            ->expectsOutputToContain('Provisioned ADMS Device Keys')
            ->expectsOutputToContain('0 migrated (0 normalized), 0 refused, 1 provisioned (0 failed), 0 stale plaintext cleaned.')
            ->assertSuccessful();

        $device->refresh();
        $this->assertNotNull($device->adms_comm_key_hash);
        $this->assertNotNull($device->adms_comm_key_salt);
    }

    public function test_reprovision_legacy_printed_key_verifies(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => hash('sha256', 'legacy-key-999'),
            'adms_comm_key_salt' => null,
            'provider_config' => [],
        ]);

        $buffer = new BufferedOutput();

        Artisan::call('biometrics:backfill-adms-comm-keys', [
            '--reprovision-legacy' => true,
            '--reveal' => true,
        ], $buffer);

        $output = $buffer->fetch();
        preg_match('/PROVISIONED KEY\s+Device ID: ' . $device->id . '\s+SN: \S+\s+Gym ID: \d+\s+Key: ([A-Za-z0-9]+)/', $output, $matches);

        $this->assertTrue($device->refresh()->verifyCommKey($matches[1]));
    }

    public function test_provision_missing_does_not_reprovision_legacy_hashes(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => hash('sha256', 'legacy-key-999'),
            'adms_comm_key_salt' => null,
            'provider_config' => [],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--provision-missing' => true])
            ->assertSuccessful();

        $this->assertNull($device->refresh()->adms_comm_key_salt);
    }

    public function test_cloud_provider_comm_key_is_neither_used_nor_deleted_as_an_adms_key(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::Custom,
            'adms_comm_key_hash' => null,
            'provider_config' => ['comm_key' => 'cloud-provider-key', 'other_setting' => 'safe'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--clean-plaintext' => true])
            ->expectsOutputToContain('0 migrated (0 normalized), 0 refused, 0 provisioned (0 failed), 0 stale plaintext cleaned.')
            ->assertSuccessful();

        $device->refresh();
        $this->assertArrayHasKey('comm_key', $device->provider_config);
        $this->assertSame('cloud-provider-key', $device->provider_config['comm_key']);
        $this->assertNull($device->adms_comm_key_hash);
        $this->assertFalse($device->hasCommKeyConfigured());
        $this->assertFalse($device->verifyCommKey('cloud-provider-key'));
    }

    public function test_stale_plaintext_is_removed_only_when_clean_is_requested(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['pushcommkey' => '123456', 'other_setting' => 'safe'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys')
            ->expectsOutputToContain('still have legacy plaintext aliases')
            ->assertSuccessful();

        $this->assertSame('123456', $device->refresh()->provider_config['pushcommkey'] ?? null);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--clean-plaintext' => true])
            ->expectsOutputToContain('1 stale plaintext cleaned')
            ->assertSuccessful();

        $device->refresh();
        $this->assertArrayNotHasKey('pushcommkey', $device->provider_config);
        $this->assertSame('safe', $device->provider_config['other_setting'] ?? null);
        $this->assertTrue($device->verifyCommKey('123456'));
    }

    public function test_cleanup_dry_run_previews_without_deleting(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--dry-run' => true,
            '--clean-plaintext' => true,
        ])
            ->expectsOutputToContain('would remove verified legacy plaintext')
            ->expectsOutputToContain('1 stale plaintext cleaned (dry run)')
            ->assertSuccessful();

        $this->assertSame('123456', $device->refresh()->provider_config['pushcommkey'] ?? null);
    }

    public function test_cleanup_rehashes_a_raw_whitespace_verifier_before_deleting_plaintext(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey(' 123 456 ', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['pushcommkey' => ' 123 456 '],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--clean-plaintext' => true])
            ->expectsOutputToContain('matched only through the raw fallback; re-hashed the sanitized form')
            ->expectsOutputToContain('1 stale plaintext cleaned')
            ->assertSuccessful();

        $device->refresh();

        $this->assertArrayNotHasKey('pushcommkey', $device->provider_config);
        $this->assertTrue($device->verifyCommKeyWithoutLegacyFallback('123456'));

        config(['biometrics.adms.allow_legacy_unsanitized_comm_key' => false]);

        $this->assertTrue($device->refresh()->verifyCommKey('123456'));
    }

    public function test_cleanup_rehashes_an_unsalted_raw_whitespace_verifier_before_deleting_plaintext(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => hash('sha256', ' 123 456 '),
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => ' 123 456 '],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--clean-plaintext' => true])
            ->expectsOutputToContain('matched only through the raw fallback; re-hashed the sanitized form')
            ->expectsOutputToContain('1 stale plaintext cleaned')
            ->assertSuccessful();

        $device->refresh();

        $this->assertArrayNotHasKey('pushcommkey', $device->provider_config);
        $this->assertNotNull($device->adms_comm_key_salt);
        $this->assertStringStartsWith(BiometricDevice::PBKDF2_HASH_PREFIX, (string) $device->adms_comm_key_hash);
        $this->assertTrue($device->verifyCommKeyWithoutLegacyFallback('123456'));
    }

    public function test_empty_string_hash_and_plaintext_are_migrated_to_a_real_verifier(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => '',
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--clean-plaintext' => true])
            ->expectsOutputToContain('1 migrated (0 normalized), 0 refused, 0 provisioned (0 failed), 0 stale plaintext cleaned.')
            ->assertSuccessful();

        $device->refresh();

        $this->assertArrayNotHasKey('pushcommkey', $device->provider_config);
        $this->assertTrue($device->hasRealCommKeyHash());
        $this->assertTrue($device->verifyCommKey('123456'));
    }

    public function test_cleanup_mismatch_is_reported_and_left_in_place(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['pushcommkey' => '654321'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--clean-plaintext' => true])
            ->expectsOutputToContain('does not match its hash')
            ->expectsOutputToContain('could not be proven safe to delete')
            ->assertFailed();

        $this->assertSame('654321', $device->refresh()->provider_config['pushcommkey'] ?? null);
    }

    public function test_clean_plaintext_removes_null_provider_comm_key_and_reports_it_honestly(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => null,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['comm_key' => '123456', 'other_setting' => 'safe'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--clean-plaintext' => true])
            ->expectsOutputToContain('1 stale plaintext cleaned')
            ->assertSuccessful();

        $device->refresh();

        $this->assertArrayNotHasKey('comm_key', $device->provider_config);
        $this->assertSame('safe', $device->provider_config['other_setting'] ?? null);
        $this->assertTrue($device->verifyCommKey('123456'));
    }

    public function test_reprovision_legacy_removes_non_empty_aliases_before_rotation(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => hash('sha256', 'legacy-key-999'),
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => 'legacy-key-999', 'other_setting' => 'safe'],
        ]);

        $buffer = new BufferedOutput();

        Artisan::call('biometrics:backfill-adms-comm-keys', [
            '--reprovision-legacy' => true,
            '--reveal' => true,
        ], $buffer);

        $output = $buffer->fetch();
        preg_match('/PROVISIONED KEY\s+Device ID: ' . $device->id . '\s+SN: \S+\s+Gym ID: \d+\s+Key: ([A-Za-z0-9]+)/', $output, $matches);

        $device->refresh();

        $this->assertArrayNotHasKey('pushcommkey', $device->provider_config);
        $this->assertSame('safe', $device->provider_config['other_setting'] ?? null);
        $this->assertTrue($device->verifyCommKey($matches[1]));
        $this->assertFalse($device->verifyCommKey('legacy-key-999'));
    }

    public function test_combined_provisioning_flags_never_generate_two_keys_for_one_device(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => '   ',
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--provision-missing' => true,
            '--reprovision-legacy' => true,
            '--reveal' => true,
        ])
            ->expectsOutputToContain('1 migrated (0 normalized), 0 refused, 0 provisioned (0 failed)')
            ->assertSuccessful();

        $device->refresh();

        $this->assertTrue($device->verifyCommKey('123456'));
        $this->assertStringStartsWith(BiometricDevice::PBKDF2_HASH_PREFIX, (string) $device->adms_comm_key_hash);
    }

    public function test_activity_log_failure_does_not_mark_a_provisioned_device_as_failed(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        $logger = Mockery::mock(ActivityLogger::class);
        $logger->shouldReceive('setLogStatus')->andReturnSelf();
        $logger->shouldReceive('useLog')->andReturnSelf();
        $logger->shouldReceive('performedOn')->andReturnSelf();
        $logger->shouldReceive('withProperties')->andReturnSelf();
        $logger->shouldReceive('log')->andThrow(new RuntimeException('audit store unavailable'));
        $this->app->instance(ActivityLogger::class, $logger);
        $this->app->forgetInstance(PendingActivityLog::class);

        $buffer = new BufferedOutput();
        $exitCode = Artisan::call('biometrics:backfill-adms-comm-keys', [
            '--provision-missing' => true,
            '--reveal' => true,
        ], $buffer);

        $output = $buffer->fetch();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('PROVISIONED KEY', $output);
        $this->assertStringContainsString('activity log failed', $output);
        $this->assertNotNull($device->refresh()->adms_comm_key_hash);
    }

    public function test_corrupt_provider_config_is_reported_and_skipped(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => 'not-a-valid-encrypted-payload']);

        $this->artisan('biometrics:backfill-adms-comm-keys')
            ->expectsOutputToContain('provider_config could not be decrypted')
            ->expectsOutputToContain('0 migrated (0 normalized), 1 refused')
            ->assertFailed();
    }

    public function test_reprovision_legacy_selects_unsafe_versioned_hashes_but_not_unversioned_hashes(): void
    {
        $unversionedSalt = BiometricDevice::generateCommKeySalt();
        $unversionedHash = hash_pbkdf2('sha256', '123456', $unversionedSalt, 1000);
        $unversioned = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => $unversionedHash,
            'adms_comm_key_salt' => $unversionedSalt,
            'provider_config' => [],
        ]);

        $unsafe = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => BiometricDevice::PBKDF2_HASH_PREFIX . ':0:' . str_repeat('a', 64),
            'adms_comm_key_salt' => BiometricDevice::generateCommKeySalt(),
            'provider_config' => [],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--reprovision-legacy' => true,
            '--reveal' => true,
        ])
            ->expectsOutputToContain('1 provisioned (0 failed)')
            ->assertSuccessful();

        // The iteration count of an unversioned hash cannot be detected, so
        // the policy leaves it alone instead of rotating a fleet that may be
        // using the historical 100,000 count.
        $this->assertSame($unversionedHash, $unversioned->refresh()->adms_comm_key_hash);
        $this->assertStringStartsWith(BiometricDevice::PBKDF2_HASH_PREFIX, (string) $unsafe->refresh()->adms_comm_key_hash);
    }

    public function test_reprovision_legacy_selects_a_whitespace_only_hash(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => '   ',
            'adms_comm_key_salt' => null,
            'provider_config' => [],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--reprovision-legacy' => true,
            '--reveal' => true,
        ])
            ->expectsOutputToContain('1 provisioned (0 failed)')
            ->assertSuccessful();

        $this->assertStringStartsWith(
            BiometricDevice::PBKDF2_HASH_PREFIX,
            (string) $device->refresh()->adms_comm_key_hash
        );
    }

    public function test_reprovision_legacy_selects_a_corrupt_salted_hash(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => 'garbage',
            'adms_comm_key_salt' => BiometricDevice::generateCommKeySalt(),
            'provider_config' => [],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--reprovision-legacy' => true,
            '--reveal' => true,
        ])
            ->expectsOutputToContain('1 provisioned (0 failed)')
            ->assertSuccessful();

        $this->assertStringStartsWith(
            BiometricDevice::PBKDF2_HASH_PREFIX,
            (string) $device->refresh()->adms_comm_key_hash
        );
    }

    public function test_clean_plaintext_counts_a_cloud_comm_key_as_retained(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'provider_type' => BiometricProvidersEnum::Custom,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => ['comm_key' => 'cloud-provider-key'],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--clean-plaintext' => true])
            ->expectsOutputToContain('retained by provider policy')
            ->assertSuccessful();

        $this->assertSame('cloud-provider-key', $device->refresh()->provider_config['comm_key'] ?? null);
    }

    public function test_migration_keeps_a_pending_reveal_for_the_unchanged_key(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        CommKeyReveal::put($device->id, '123456');

        $this->artisan('biometrics:backfill-adms-comm-keys')->assertSuccessful();

        // The migration hashes the same key; the reveal still shows the
        // current key and must not be discarded.
        $this->assertSame('123456', CommKeyReveal::pull($device->id));
    }

    public function test_reprovisioning_invalidates_a_pending_one_time_reveal(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        CommKeyReveal::put($device->id, '999999');

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--provision-missing' => true,
            '--reveal' => true,
        ])->assertSuccessful();

        $this->assertNull(CommKeyReveal::pull($device->id));
    }

    public function test_whitespace_placeholder_is_provisioned_by_reprovision_legacy_without_a_false_refusal(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => '   '],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--reprovision-legacy' => true,
            '--reveal' => true,
        ])
            ->expectsOutputToContain('0 refused, 1 provisioned')
            ->assertSuccessful();

        $device->refresh();

        $this->assertNotNull($device->adms_comm_key_hash);
        $this->assertArrayNotHasKey('pushcommkey', $device->provider_config ?? []);
    }

    public function test_whitespace_placeholder_replaced_by_provisioning_is_not_counted_as_refused(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => '   '],
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', [
            '--provision-missing' => true,
            '--reveal' => true,
        ])
            ->expectsOutputToContain('0 refused, 1 provisioned')
            ->assertSuccessful();

        $device->refresh();

        $this->assertNotNull($device->adms_comm_key_hash);
        $this->assertArrayNotHasKey('pushcommkey', $device->provider_config ?? []);
    }

    /**
     * Apply a mutation when the device row is hydrated for the Nth time.
     *
     * The command hydrates each row several times: collectors, the cleanup or
     * provisioning snapshot, then the lock query. Mutating during the snapshot
     * hydration leaves that in-memory copy stale while the database moves on,
     * which is exactly the race the lock guard must reject.
     *
     * @param  callable(): void  $mutation
     */
    private function mutateOnRetrieval(BiometricDevice $device, int $retrieval, callable $mutation, bool &$mutated): void
    {
        $retrievals = 0;

        BiometricDevice::retrieved(function (BiometricDevice $retrieved) use ($device, $retrieval, $mutation, &$mutated, &$retrievals): void {
            if ($mutated || $retrieved->getKey() !== $device->getKey()) {
                return;
            }

            $retrievals++;

            if ($retrievals !== $retrieval) {
                return;
            }

            $mutated = true;
            $mutation();
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{0: int, 1: string}
     */
    private function runBackfill(array $options = []): array
    {
        $buffer = new BufferedOutput();

        $exitCode = Artisan::call('biometrics:backfill-adms-comm-keys', $options, $buffer);

        return [$exitCode, $buffer->fetch()];
    }
}
