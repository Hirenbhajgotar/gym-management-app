<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BiometricDeviceStatusEnum;
use App\Models\BiometricDevice;
use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BiometricCommKeyVerifierUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        config(['biometrics.adms.pbkdf2_iterations' => 1000]);

        $this->gym = Gym::factory()->create();
    }

    public function test_successful_unsalted_verification_is_upgraded_to_salted_pbkdf2(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => hash('sha256', '123456'),
            'adms_comm_key_salt' => null,
            'provider_config' => [],
        ]);

        $this->assertTrue($device->verifyCommKey('123456'));

        $device->refresh();

        $this->assertNotNull($device->adms_comm_key_salt);
        $this->assertStringStartsWith(BiometricDevice::PBKDF2_HASH_PREFIX, (string) $device->adms_comm_key_hash);
        $this->assertNotSame(hash('sha256', '123456'), $device->adms_comm_key_hash);
        $this->assertTrue($device->verifyCommKeyWithoutLegacyFallback('123456'));
    }

    public function test_successful_raw_fallback_is_persisted_as_the_sanitized_form(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey(' 123 456 ', $salt),
            'adms_comm_key_salt' => $salt,
            'provider_config' => [],
        ]);

        $this->assertTrue($device->verifyCommKey(' 123 456 '));

        $device->refresh();

        $this->assertTrue($device->verifyCommKeyWithoutLegacyFallback('123456'));

        config(['biometrics.adms.allow_legacy_unsanitized_comm_key' => false]);

        $this->assertTrue($device->refresh()->verifyCommKey('123456'));
    }

    public function test_provider_config_plaintext_keys_are_not_persisted_by_verification(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => ' 123 456 '],
        ]);

        $this->assertTrue($device->verifyCommKey('123456'));

        $device->refresh();

        $this->assertNull($device->adms_comm_key_hash);
        $this->assertSame(' 123 456 ', $device->provider_config['pushcommkey'] ?? null);
    }

    public function test_a_lost_cas_does_not_leave_a_dirty_verifier_that_a_later_save_resurrects(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();
        $originalHash = BiometricDevice::hashCommKey(' 123 456 ', $salt);
        $rotatedHash = BiometricDevice::hashCommKey('999999', 'rotated-salt');

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => BiometricDeviceStatusEnum::Active,
            'adms_enabled' => true,
            'adms_comm_key_hash' => $originalHash,
            'adms_comm_key_salt' => $salt,
            'provider_config' => [],
        ]);

        $fired = false;

        DB::beforeExecuting(function (string $query) use (&$fired, $device, $rotatedHash): void {
            if (
                $fired
                || ! str_contains($query, 'update `biometric_devices`')
                || ! str_contains($query, 'adms_comm_key_hash')
            ) {
                return;
            }

            $fired = true;

            // Simulate an operator rotation between verification and the CAS.
            DB::table('biometric_devices')->where('id', $device->id)->update([
                'adms_comm_key_hash' => $rotatedHash,
                'adms_comm_key_salt' => 'rotated-salt',
            ]);
        });

        $this->assertTrue($device->verifyCommKey(' 123 456 '));
        $this->assertTrue($fired);

        // The controller's heartbeat update runs on the same model instance.
        $device->update(['last_heartbeat_at' => now()]);

        $this->assertSame(
            $rotatedHash,
            DB::table('biometric_devices')->where('id', $device->id)->value('adms_comm_key_hash')
        );
    }
}
