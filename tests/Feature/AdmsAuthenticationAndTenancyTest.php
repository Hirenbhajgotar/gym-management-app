<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\Services\AdmsDeviceAuthenticator;
use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\DefaultRole;
use App\Http\Controllers\Api\AdmsController;
use App\Http\Middleware\AuthenticateBiometricDevice;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdmsAuthenticationAndTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected BiometricDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        config(['biometrics.adms.pbkdf2_iterations' => 1000]);

        $this->user = User::factory()->create();
        $this->gym = Gym::factory()
            ->hasAttached($this->user, ['role' => DefaultRole::GYM_OWNER->value, 'active' => true], 'users')
            ->create();

        $this->device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'ADMS Device with Key',
            'device_serial' => 'SN-AUTH-TEST-001',
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'adms_comm_key_hash' => hash('sha256', 'secret-comm-key-123'),
        ]);
    }

    public function test_adms_request_without_comm_key_fails_when_device_has_key_configured(): void
    {
        $response = $this->call(
            'GET',
            '/api/iclock/cdata?SN=SN-AUTH-TEST-001'
        );

        $response->assertUnauthorized();
        $this->assertStringContainsString('ERR: Unauthorized', (string) $response->getContent());
    }

    public function test_unkeyed_device_authenticates_with_serial_number_when_no_comm_key_is_configured(): void
    {
        $unkeyedDevice = BiometricDevice::factory()->admsEnabled('SN-MB20-TEST-001')->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Unkeyed MB20 Terminal',
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
        ]);

        $response = $this->call(
            'GET',
            '/api/iclock/cdata?SN=SN-MB20-TEST-001'
        );

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
    }

    public function test_active_unkeyed_device_is_rejected_when_global_key_enforcement_is_enabled(): void
    {
        config(['biometrics.adms.require_comm_key' => true]);

        $device = BiometricDevice::factory()->admsEnabled('SN-UNKEYED-STRICT')->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Unkeyed Active Device Strict',
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
        ]);

        $response = $this->call('GET', '/api/iclock/cdata?SN=SN-UNKEYED-STRICT');
        $response->assertUnauthorized();
        $this->assertStringContainsString('ERR: Unauthorized', (string) $response->getContent());
    }

    public function test_unkeyed_device_supplied_comm_key_is_accepted_and_logged_as_ignored(): void
    {
        Log::spy();

        $device = BiometricDevice::factory()->admsEnabled('SN-UNKEYED-EXTRA-KEY')->create([
            'gym_id' => $this->gym->id,
        ]);

        $response = $this->call('GET', '/api/iclock/cdata?SN=SN-UNKEYED-EXTRA-KEY&pushcommkey=unexpected123');
        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($device): bool {
                return str_contains($message, 'unkeyed device supplied a pushcommkey')
                    && ($context['device_id'] ?? null) === $device->id;
            });
    }

    public function test_unkeyed_device_under_global_enforcement_never_accumulates_a_lockout(): void
    {
        Log::spy();

        config(['biometrics.adms.require_comm_key' => true]);

        $device = BiometricDevice::factory()->admsEnabled('SN-UNKEYED-NO-LOCKOUT')->create([
            'gym_id' => $this->gym->id,
        ]);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->call('GET', '/api/iclock/cdata?SN=SN-UNKEYED-NO-LOCKOUT')
                ->assertUnauthorized();
        }

        $authenticator = resolve(AdmsDeviceAuthenticator::class);

        $this->assertFalse($authenticator->isLockedOut($device));

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($device): bool {
                return str_contains($message, 'unkeyed device refused while global key enforcement is enabled')
                    && ($context['device_id'] ?? null) === $device->id;
            });
    }

    public function test_keyed_device_locks_out_after_five_requests_that_omit_the_comm_key(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->call('GET', '/api/iclock/cdata?SN=SN-AUTH-TEST-001')
                ->assertUnauthorized();
        }

        $authenticator = resolve(AdmsDeviceAuthenticator::class);

        $this->assertTrue($authenticator->isLockedOut($this->device));

        $this->call('GET', '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=secret-comm-key-123')
            ->assertUnauthorized();
    }

    public function test_unkeyed_device_in_maintenance_is_rejected(): void
    {
        BiometricDevice::factory()->admsEnabled('SN-UNKEYED-MAINTENANCE')->maintenance()->create([
            'gym_id' => $this->gym->id,
        ]);

        $this->call('GET', '/api/iclock/cdata?SN=SN-UNKEYED-MAINTENANCE')
            ->assertUnauthorized();
    }

    public function test_unkeyed_device_that_was_deleted_is_rejected(): void
    {
        $device = BiometricDevice::factory()->admsEnabled('SN-UNKEYED-DELETED')->create([
            'gym_id' => $this->gym->id,
        ]);

        $device->delete();

        $this->call('GET', '/api/iclock/cdata?SN=SN-UNKEYED-DELETED')
            ->assertUnauthorized();
    }

    public function test_unkeyed_success_clears_a_leftover_failure_counter_and_lockout(): void
    {
        $device = BiometricDevice::factory()->admsEnabled('SN-UNKEYED-RESET')->create([
            'gym_id' => $this->gym->id,
        ]);

        Cache::put(AdmsDeviceAuthenticator::FAILURE_COUNTER_PREFIX . $device->id, 3, now()->addSeconds(900));
        Cache::put(AdmsDeviceAuthenticator::LOCKOUT_PREFIX . $device->id, true, now()->addSeconds(900));

        $this->call('GET', '/api/iclock/cdata?SN=SN-UNKEYED-RESET')
            ->assertOk();

        $authenticator = resolve(AdmsDeviceAuthenticator::class);

        $this->assertFalse($authenticator->isLockedOut($device));
        $this->assertNull(Cache::get(AdmsDeviceAuthenticator::FAILURE_COUNTER_PREFIX . $device->id));
    }

    public function test_adms_request_with_invalid_comm_key_fails(): void
    {
        $response = $this->call(
            'GET',
            '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=wrong-key'
        );

        $response->assertUnauthorized();
        $this->assertStringContainsString('ERR: Unauthorized', (string) $response->getContent());
    }

    /**
     * Both CData surfaces, canonical and `.aspx`, so the aliases are proven to
     * inherit the same authentication contract as their twins.
     *
     * @return array<string, array{0: string}>
     */
    public static function cdataSurfaceUriProvider(): array
    {
        return [
            'api canonical' => ['/api/iclock/cdata'],
            'api aspx' => ['/api/iclock/cdata.aspx'],
            'native canonical' => ['/iclock/cdata'],
            'native aspx' => ['/iclock/cdata.aspx'],
        ];
    }

    #[DataProvider('cdataSurfaceUriProvider')]
    public function test_adms_cdata_surfaces_reject_unknown_serial_with_unauthorized(string $uri): void
    {
        $response = $this->call('GET', $uri . '?SN=SN-DOES-NOT-EXIST');

        $response->assertUnauthorized();
        $this->assertSame('ERR: Unauthorized', $response->getContent());
    }

    #[DataProvider('cdataSurfaceUriProvider')]
    public function test_adms_cdata_surfaces_reject_invalid_comm_key_with_unauthorized(string $uri): void
    {
        $response = $this->call('GET', $uri . '?SN=SN-AUTH-TEST-001&pushcommkey=wrong-key');

        $response->assertUnauthorized();
        $this->assertSame('ERR: Unauthorized', $response->getContent());
    }

    #[DataProvider('cdataSurfaceUriProvider')]
    public function test_adms_cdata_surfaces_reject_missing_serial_with_bad_request(string $uri): void
    {
        $response = $this->call('GET', $uri);

        $response->assertStatus(400);
        $this->assertSame('ERR: Unauthorized', $response->getContent());
    }

    public function test_adms_request_with_valid_comm_key_in_query_succeeds(): void
    {
        $response = $this->call(
            'GET',
            '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=secret-comm-key-123'
        );

        $response->assertOk();
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_adms_request_with_valid_comm_key_in_header_succeeds(): void
    {
        $response = $this->call(
            'GET',
            '/api/iclock/cdata?SN=SN-AUTH-TEST-001',
            [],
            [],
            [],
            ['HTTP_X_PUSHCOMMKEY' => 'secret-comm-key-123']
        );

        $response->assertOk();
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_adms_auth_failure_logs_hashed_serial_without_raw_serial(): void
    {
        Log::spy();

        $response = $this->call(
            'GET',
            '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=wrong-key'
        );

        $response->assertUnauthorized();

        $expectedHash = hash('sha256', 'SN-AUTH-TEST-001');

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($expectedHash): bool {
                if (! str_contains($message, 'Device authentication failed')) {
                    return false;
                }

                // Must contain hashed serial
                $hasHash = isset($context['serial_hash']) && $context['serial_hash'] === $expectedHash;

                // Must NOT contain raw serial anywhere in the context
                $hasRawSerial = in_array('SN-AUTH-TEST-001', $context, true)
                    || isset($context['serial'])
                    || isset($context['device_serial']);

                return $hasHash && ! $hasRawSerial;
            });
    }

    public function test_inactive_device_auto_recovers_to_active_strictly_with_verified_key(): void
    {
        Log::spy();

        $this->device->update([
            'status' => BiometricDeviceStatusEnum::Inactive,
        ]);

        Cache::put(BiometricDevice::missCounterCacheKey($this->device->id), 5);

        // 1. Request without valid comm key fails and device remains Inactive
        $failResponse = $this->call(
            'GET',
            '/api/iclock/cdata?SN=SN-AUTH-TEST-001'
        );
        $failResponse->assertUnauthorized();
        $this->device->refresh();
        $this->assertEquals(BiometricDeviceStatusEnum::Inactive, $this->device->status);

        // 2. Request with valid comm key succeeds, auto-recovers to Active and resets miss counter
        $successResponse = $this->call(
            'GET',
            '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=secret-comm-key-123'
        );
        $successResponse->assertOk();
        $this->device->refresh();
        $this->assertEquals(BiometricDeviceStatusEnum::Active, $this->device->status);
        $this->assertNull(Cache::get(BiometricDevice::missCounterCacheKey($this->device->id)));
    }

    public function test_stateless_lead_api_resolves_tenant_from_authenticated_user(): void
    {
        Sanctum::actingAs($this->user);

        Lead::factory()->create([
            'gym_id' => $this->gym->id,
            'source' => 'facebook',
        ]);

        $response = $this->getJson('/api/leads/source/facebook');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'current_page']);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
    }

    public function test_stateless_lead_api_resolves_tenant_with_custom_header_for_active_gym(): void
    {
        $secondGym = Gym::factory()
            ->hasAttached($this->user, ['role' => DefaultRole::GYM_OWNER->value, 'active' => true], 'users')
            ->create();

        Lead::factory()->create([
            'gym_id' => $secondGym->id,
            'source' => 'instagram',
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->withHeaders(['X-Gym-Id' => (string) $secondGym->id])
            ->getJson('/api/leads/source/instagram');

        $response->assertOk();
        $secondData = $response->json('data');
        $this->assertIsArray($secondData);
        $this->assertCount(1, $secondData);
    }

    public function test_stateless_lead_api_rejects_deactivated_gym_membership_header(): void
    {
        $revokedGym = Gym::factory()
            ->hasAttached($this->user, ['role' => DefaultRole::GYM_OWNER->value, 'active' => false], 'users')
            ->create();

        Lead::factory()->create([
            'gym_id' => $revokedGym->id,
            'source' => 'instagram',
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->withHeaders(['X-Gym-Id' => (string) $revokedGym->id])
            ->getJson('/api/leads/source/instagram');

        $response->assertForbidden();
    }

    public function test_stateless_lead_api_rejects_unauthorized_gym_header(): void
    {
        $unauthorizedGym = Gym::factory()->create();

        Sanctum::actingAs($this->user);

        $response = $this->withHeaders(['X-Gym-Id' => (string) $unauthorizedGym->id])
            ->getJson('/api/leads/source/facebook');

        $response->assertForbidden();
    }

    public function test_adms_request_with_salted_pbkdf2_key_tolerates_query_whitespace(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();
        $this->device->forceFill([
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
        ])->saveQuietly();
        $this->assertNotNull($this->device->refresh()->adms_comm_key_salt);

        $response = $this->call(
            'GET',
            '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=%20123%20456%20'
        );

        $response->assertOk();
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_adms_request_with_salted_pbkdf2_key_tolerates_header_and_body_whitespace(): void
    {
        $salt = BiometricDevice::generateCommKeySalt();
        $this->device->forceFill([
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
        ])->saveQuietly();
        $this->assertNotNull($this->device->refresh()->adms_comm_key_salt);

        $headerResponse = $this->call(
            'GET',
            '/api/iclock/cdata?SN=SN-AUTH-TEST-001',
            [],
            [],
            [],
            ['HTTP_X_PUSHCOMMKEY' => ' 123 456 ']
        );
        $headerResponse->assertOk();

        $bodyResponse = $this->call(
            'POST',
            '/api/iclock/cdata?SN=SN-AUTH-TEST-001&table=ATTLOG',
            ['pushcommkey' => ' 123 456 ']
        );
        $bodyResponse->assertOk();
    }

    public function test_adms_auth_failures_return_a_uniform_body_for_unknown_and_invalid_devices(): void
    {
        $unknown = $this->call('GET', '/api/iclock/cdata?SN=SN-DOES-NOT-EXIST&pushcommkey=123456');
        $unknown->assertUnauthorized();
        $this->assertSame('ERR: Unauthorized', $unknown->getContent());

        $invalid = $this->call('GET', '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=654321');
        $invalid->assertUnauthorized();
        $this->assertSame('ERR: Unauthorized', $invalid->getContent());
    }

    public function test_adms_device_locks_out_after_repeated_failed_attempts_and_a_valid_key_is_rejected_until_the_lockout_expires(): void
    {
        config([
            'biometrics.adms.auth_max_failures' => 3,
            'biometrics.adms.auth_lockout_seconds' => 60,
        ]);

        $salt = BiometricDevice::generateCommKeySalt();
        $this->device->forceFill([
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
        ])->saveQuietly();
        $this->assertNotNull($this->device->refresh()->adms_comm_key_salt);

        /** @var AdmsDeviceAuthenticator $authenticator */
        $authenticator = resolve(AdmsDeviceAuthenticator::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->call('GET', '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=000000')
                ->assertUnauthorized();
        }

        $this->assertTrue($authenticator->isLockedOut($this->device->refresh()));

        // Lockout is checked before verification, so even the correct key is
        // rejected while the device is locked. This is what caps guesses; a
        // verify-first order would accept this request and clear the lockout.
        $this->call('GET', '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=123456')
            ->assertUnauthorized();

        $this->assertTrue($authenticator->isLockedOut($this->device->refresh()));

        $this->travel(61)->seconds();

        $this->call('GET', '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=123456')
            ->assertOk();

        $this->assertFalse($authenticator->isLockedOut($this->device->refresh()));

        // The counter was cleared by the successful authentication, so a
        // single later failure must not immediately re-lock the device.
        $this->call('GET', '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=000000')
            ->assertUnauthorized();
        $this->assertFalse($authenticator->isLockedOut($this->device->refresh()));
    }

    public function test_lockout_expires_after_the_configured_cooldown(): void
    {
        config([
            'biometrics.adms.auth_max_failures' => 2,
            'biometrics.adms.auth_lockout_seconds' => 60,
        ]);

        $salt = BiometricDevice::generateCommKeySalt();
        $this->device->forceFill([
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $salt),
            'adms_comm_key_salt' => $salt,
        ])->saveQuietly();
        $this->assertNotNull($this->device->refresh()->adms_comm_key_salt);

        /** @var AdmsDeviceAuthenticator $authenticator */
        $authenticator = resolve(AdmsDeviceAuthenticator::class);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->call('GET', '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=000000')
                ->assertUnauthorized();
        }

        $this->assertTrue($authenticator->isLockedOut($this->device->refresh()));

        $this->travel(61)->seconds();

        $this->assertFalse($authenticator->isLockedOut($this->device->refresh()));
    }

    public function test_lockout_is_isolated_per_device(): void
    {
        config([
            'biometrics.adms.auth_max_failures' => 2,
            'biometrics.adms.auth_lockout_seconds' => 60,
        ]);

        $secondSalt = BiometricDevice::generateCommKeySalt();

        $second = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Second ADMS Device',
            'device_serial' => 'SN-AUTH-TEST-002',
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('654321', $secondSalt),
            'adms_comm_key_salt' => $secondSalt,
        ]);

        $firstSalt = BiometricDevice::generateCommKeySalt();
        $this->device->forceFill([
            'adms_comm_key_hash' => BiometricDevice::hashCommKey('123456', $firstSalt),
            'adms_comm_key_salt' => $firstSalt,
        ])->saveQuietly();
        $this->assertNotNull($this->device->refresh()->adms_comm_key_salt);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->call('GET', '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=000000')
                ->assertUnauthorized();
        }

        /** @var AdmsDeviceAuthenticator $authenticator */
        $authenticator = resolve(AdmsDeviceAuthenticator::class);

        $this->assertTrue($authenticator->isLockedOut($this->device->refresh()));

        $this->call('GET', '/api/iclock/cdata?SN=SN-AUTH-TEST-002&pushcommkey=654321')
            ->assertOk();

        $this->assertFalse($authenticator->isLockedOut($second->refresh()));
        $this->assertTrue($authenticator->isLockedOut($this->device->refresh()));
    }

    public function test_adms_controller_fails_closed_when_only_a_generic_device_attribute_is_bound(): void
    {
        $plainToken = 'device-token-for-adms-cross-middleware-test';

        $this->device->update([
            'device_token' => $plainToken,
            'token_hash' => hash('sha256', $plainToken),
        ]);

        Route::middleware(AuthenticateBiometricDevice::class)
            ->get('/api/test-adms-cross-middleware', fn (Request $request) => resolve(AdmsController::class)->cdata($request));

        $response = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->get('/api/test-adms-cross-middleware?SN=SN-AUTH-TEST-001');

        $response->assertUnauthorized();
        $this->assertSame('ERR: Unauthorized', $response->getContent());
    }

    public function test_inactive_unkeyed_device_auto_recovers_when_key_enforcement_is_disabled(): void
    {
        config(['biometrics.adms.require_comm_key' => false]);

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Unkeyed Inactive Device',
            'device_serial' => 'SN-UNKEYED-INACTIVE',
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Inactive,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        $response = $this->call('GET', '/api/iclock/cdata?SN=SN-UNKEYED-INACTIVE');

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertEquals(BiometricDeviceStatusEnum::Active, $device->refresh()->status);
    }

    public function test_corrupt_provider_config_returns_unauthorized_instead_of_server_error(): void
    {
        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Corrupt Config Device',
            'device_serial' => 'SN-CORRUPT-CONFIG',
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Active,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => ['pushcommkey' => '123456'],
        ]);

        DB::table('biometric_devices')
            ->where('id', $device->id)
            ->update(['provider_config' => 'not-a-valid-encrypted-payload']);

        $response = $this->call('GET', '/api/iclock/cdata?SN=SN-CORRUPT-CONFIG&pushcommkey=123456');

        $response->assertUnauthorized();
        $this->assertSame('ERR: Unauthorized', $response->getContent());

        // An unreadable provider_config may still hold a key, so it must never
        // degrade to serial-only authentication: omitting the key is refused too.
        $this->call('GET', '/api/iclock/cdata?SN=SN-CORRUPT-CONFIG')
            ->assertUnauthorized();
    }

    public function test_inactive_unkeyed_device_still_cannot_recover_when_key_enforcement_is_enabled(): void
    {
        config(['biometrics.adms.require_comm_key' => true]);

        $device = BiometricDevice::factory()->create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Unkeyed Inactive Device Required',
            'device_serial' => 'SN-UNKEYED-INACTIVE-REQUIRED',
            'adms_enabled' => true,
            'status' => BiometricDeviceStatusEnum::Inactive,
            'purpose' => BiometricDevicePurpose::AttendanceOnly,
            'adms_comm_key_hash' => null,
            'adms_comm_key_salt' => null,
            'provider_config' => null,
        ]);

        $response = $this->call('GET', '/api/iclock/cdata?SN=SN-UNKEYED-INACTIVE-REQUIRED');

        $response->assertUnauthorized();
        $this->assertEquals(BiometricDeviceStatusEnum::Inactive, $device->refresh()->status);
    }
}
