<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BiometricDevicePurpose;
use App\Enums\BiometricDeviceStatusEnum;
use App\Enums\DefaultRole;
use App\Models\BiometricDevice;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
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
        $this->assertStringContainsString('ERR: Invalid or missing pushcommkey', (string) $response->getContent());
    }

    public function test_adms_request_with_invalid_comm_key_fails(): void
    {
        $response = $this->call(
            'GET',
            '/api/iclock/cdata?SN=SN-AUTH-TEST-001&pushcommkey=wrong-key'
        );

        $response->assertUnauthorized();
        $this->assertStringContainsString('ERR: Invalid or missing pushcommkey', (string) $response->getContent());
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
}
