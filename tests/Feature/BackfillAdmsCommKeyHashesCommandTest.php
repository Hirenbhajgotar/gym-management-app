<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\DefaultRole;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillAdmsCommKeyHashesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

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
            ->expectsOutputToContain('ADMS comm key backfill completed. 1 migrated, 0 refused, 0 provisioned (dry run).')
            ->assertSuccessful();

        $device->refresh();
        $this->assertNull($device->adms_comm_key_hash);
        $this->assertNull($device->adms_comm_key_salt);
        $this->assertSame(['pushcommkey' => 'secret123'], $device->provider_config);
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
            ->expectsOutputToContain('ADMS comm key backfill completed. 1 migrated, 0 refused, 0 provisioned.')
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

    public function test_non_string_numeric_key_is_refused(): void
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
            ->expectsOutputToContain('0 migrated, 1 refused, 0 provisioned')
            ->assertSuccessful();

        $device->refresh();
        $this->assertNull($device->adms_comm_key_hash);
        $this->assertIsArray($device->provider_config);
        $this->assertSame(12345, $device->provider_config['pushcommkey'] ?? null);
    }

    public function test_provision_generates_keys_for_unkeyed_devices(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        $this->artisan('biometrics:backfill-adms-comm-keys', ['--provision' => true])
            ->expectsOutputToContain('Provisioned ADMS Device Keys')
            ->expectsOutputToContain('0 migrated, 0 refused, 1 provisioned')
            ->assertSuccessful();

        $device->refresh();
        $this->assertNotNull($device->adms_comm_key_hash);
        $this->assertNotNull($device->adms_comm_key_salt);
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
            ->expectsOutputToContain('ADMS comm key backfill completed. 1 migrated, 0 refused, 0 provisioned.')
            ->assertSuccessful();

        $device1->refresh();
        $device2->refresh();

        $this->assertNotNull($device1->adms_comm_key_hash);
        $this->assertNull($device2->adms_comm_key_hash);
    }

    public function test_provision_reprovisions_legacy_unsalted_hashed_devices(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => hash('sha256', 'legacy-key-999'),
            'adms_comm_key_salt' => null,
            'provider_config' => [],
        ]);

        // Without --provision, it warns about legacy devices
        $this->artisan('biometrics:backfill-adms-comm-keys')
            ->expectsOutputToContain('have legacy unsalted comm key hashes (no salt)')
            ->assertSuccessful();

        $device->refresh();
        $this->assertNull($device->adms_comm_key_salt);

        // With --provision, it generates salt and PBKDF2 hash
        $this->artisan('biometrics:backfill-adms-comm-keys', ['--provision' => true])
            ->expectsOutputToContain('Provisioned ADMS Device Keys')
            ->expectsOutputToContain('0 migrated, 0 refused, 1 provisioned')
            ->assertSuccessful();

        $device->refresh();
        $this->assertNotNull($device->adms_comm_key_hash);
        $this->assertNotNull($device->adms_comm_key_salt);
    }
}
