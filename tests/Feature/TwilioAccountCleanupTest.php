<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Services\TwilioAccountManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class TwilioAccountCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected WhatsAppAccount $whatsappAccount;

    protected WhatsAppPhoneNumber $phoneNumber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->whatsappAccount = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest_cleanup_sid',
            'twilio_auth_token' => 'test_auth_token',
            'messaging_service_sid' => 'MGtest_service_sid',
            'waba_id' => 'waba_test_id',
            'status' => 'active',
            'verification_status' => 'verified',
            'business_name' => 'Test Gym',
            'business_email' => 'test@gym.com',
        ]);

        $this->phoneNumber = WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $this->whatsappAccount->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest_cleanup_123',
            'country_code' => 'US',
            'friendly_name' => 'Test Number',
            'status' => 'active',
            'type' => 'local',
            'purchased_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========== CLEANUP TENANT RESOURCES ==========

    #[Test]
    public function it_returns_success_when_no_twilio_resources_exist(): void
    {
        $gymWithoutWhatsApp = Gym::factory()->create();

        $clientMock = Mockery::mock(Client::class);
        $manager = new TwilioAccountManager($clientMock);

        $result = $manager->cleanupTenantResources($gymWithoutWhatsApp->id);

        $this->assertTrue($result['success']);
        $this->assertEquals('No Twilio resources to clean up', $result['message']);
    }

    #[Test]
    public function it_returns_success_when_account_has_no_twilio_sid(): void
    {
        $gym = Gym::factory()->create();
        WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'twilio_account_sid' => '',
            'twilio_auth_token' => '',
            'status' => 'pending',
            'verification_status' => 'pending',
        ]);

        $clientMock = Mockery::mock(Client::class);
        $manager = new TwilioAccountManager($clientMock);

        $result = $manager->cleanupTenantResources($gym->id);

        $this->assertTrue($result['success']);
    }

    #[Test]
    public function it_cleans_up_resources_and_returns_cleaned_structure(): void
    {
        $clientMock = $this->createFullCleanupMock();

        config(['services.twilio.use_sandbox' => true]);

        $manager = new TwilioAccountManager($clientMock);
        $result = $manager->cleanupTenantResources($this->gym->id);

        $this->assertArrayHasKey('cleaned', $result);
        $this->assertArrayHasKey('phone_numbers', $result['cleaned']);
        $this->assertArrayHasKey('messaging_service', $result['cleaned']);
        $this->assertArrayHasKey('subaccount', $result['cleaned']);

        // Verify local account status was updated to SUSPENDED
        $this->whatsappAccount->refresh();
        $this->assertEquals(WhatsAppAccountStatusEnum::SUSPENDED, $this->whatsappAccount->status);
    }

    #[Test]
    public function it_handles_cleanup_with_no_phone_numbers(): void
    {
        $this->phoneNumber->delete();

        // Mock messaging service deletion
        $serviceDeleteMock = Mockery::mock();
        $serviceDeleteMock->shouldReceive('delete')->once();

        $v1Mock = Mockery::mock();
        $v1Mock->shouldReceive('services')
            ->with('MGtest_service_sid')
            ->once()
            ->andReturn($serviceDeleteMock);

        $messagingMock = Mockery::mock();
        $messagingMock->v1 = $v1Mock;

        // Mock subaccount closure
        $accountUpdateMock = Mockery::mock();
        $accountUpdateMock->shouldReceive('update')
            ->with(['status' => 'closed'])
            ->once();

        $v2010Mock = Mockery::mock();
        $v2010Mock->shouldReceive('accounts')
            ->with('ACtest_cleanup_sid')
            ->once()
            ->andReturn($accountUpdateMock);

        $apiMock = Mockery::mock();
        $apiMock->v2010 = $v2010Mock;

        $clientMock = Mockery::mock(Client::class);
        $clientMock->messaging = $messagingMock;
        $clientMock->api = $apiMock;

        config(['services.twilio.use_sandbox' => true]);

        $manager = new TwilioAccountManager($clientMock);
        $result = $manager->cleanupTenantResources($this->gym->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['cleaned']['phone_numbers']['released']);
    }

    #[Test]
    public function it_handles_cleanup_with_no_messaging_service(): void
    {
        $this->whatsappAccount->update(['messaging_service_sid' => null]);
        $this->phoneNumber->delete();

        // Mock subaccount closure only
        $accountUpdateMock = Mockery::mock();
        $accountUpdateMock->shouldReceive('update')
            ->with(['status' => 'closed'])
            ->once();

        $v2010Mock = Mockery::mock();
        $v2010Mock->shouldReceive('accounts')
            ->with('ACtest_cleanup_sid')
            ->once()
            ->andReturn($accountUpdateMock);

        $apiMock = Mockery::mock();
        $apiMock->v2010 = $v2010Mock;

        $clientMock = Mockery::mock(Client::class);
        $clientMock->api = $apiMock;

        config(['services.twilio.use_sandbox' => true]);

        $manager = new TwilioAccountManager($clientMock);
        $result = $manager->cleanupTenantResources($this->gym->id);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['cleaned']['messaging_service']['success']);
    }

    #[Test]
    public function it_handles_already_deleted_resources_gracefully(): void
    {
        $restException404 = new RestException('Resource not found', 20404, 404);

        // Phone number removal from messaging service - 404
        $phoneNumbersResourceMock = Mockery::mock();
        $phoneNumbersResourceMock->shouldReceive('delete')
            ->once()
            ->andThrow($restException404);

        $combinedServiceMock = Mockery::mock();
        $combinedServiceMock->shouldReceive('phoneNumbers')
            ->with('PNtest_cleanup_123')
            ->once()
            ->andReturn($phoneNumbersResourceMock);
        // Messaging service delete - 404
        $combinedServiceMock->shouldReceive('delete')
            ->once()
            ->andThrow($restException404);

        $v1Mock = Mockery::mock();
        $v1Mock->shouldReceive('services')
            ->with('MGtest_service_sid')
            ->andReturn($combinedServiceMock);

        $messagingMock = Mockery::mock();
        $messagingMock->v1 = $v1Mock;

        // Incoming phone number release - 404
        $incomingPhoneNumberMock = Mockery::mock();
        $incomingPhoneNumberMock->shouldReceive('delete')
            ->once()
            ->andThrow($restException404);

        // Subaccount closure - 404
        $accountUpdateMock = Mockery::mock();
        $accountUpdateMock->shouldReceive('update')
            ->with(['status' => 'closed'])
            ->once()
            ->andThrow($restException404);

        $v2010Mock = Mockery::mock();
        $v2010Mock->shouldReceive('accounts')
            ->with('ACtest_cleanup_sid')
            ->once()
            ->andReturn($accountUpdateMock);

        $apiMock = Mockery::mock();
        $apiMock->v2010 = $v2010Mock;

        $clientMock = Mockery::mock(Client::class);
        $clientMock->messaging = $messagingMock;
        $clientMock->shouldReceive('incomingPhoneNumbers')
            ->with('PNtest_cleanup_123')
            ->once()
            ->andReturn($incomingPhoneNumberMock);
        $clientMock->api = $apiMock;

        config(['services.twilio.use_sandbox' => true]);

        $manager = new TwilioAccountManager($clientMock);
        $result = $manager->cleanupTenantResources($this->gym->id);

        // 404 errors should be treated as success (already cleaned)
        $this->assertTrue($result['success']);
    }

    #[Test]
    public function it_continues_cleanup_even_when_phone_release_fails(): void
    {
        $restException500 = new RestException('Internal server error', 50000, 500);

        // Phone number removal from messaging service fails with 500
        $phoneNumbersResourceMock = Mockery::mock();
        $phoneNumbersResourceMock->shouldReceive('delete')
            ->once()
            ->andThrow($restException500);

        $combinedServiceMock = Mockery::mock();
        $combinedServiceMock->shouldReceive('phoneNumbers')
            ->with('PNtest_cleanup_123')
            ->once()
            ->andReturn($phoneNumbersResourceMock);
        // Messaging service delete should still be attempted
        $combinedServiceMock->shouldReceive('delete')->once();

        $v1Mock = Mockery::mock();
        $v1Mock->shouldReceive('services')
            ->with('MGtest_service_sid')
            ->andReturn($combinedServiceMock);

        $messagingMock = Mockery::mock();
        $messagingMock->v1 = $v1Mock;

        // Subaccount closure should still be attempted
        $accountUpdateMock = Mockery::mock();
        $accountUpdateMock->shouldReceive('update')
            ->with(['status' => 'closed'])
            ->once();

        $v2010Mock = Mockery::mock();
        $v2010Mock->shouldReceive('accounts')
            ->with('ACtest_cleanup_sid')
            ->once()
            ->andReturn($accountUpdateMock);

        $apiMock = Mockery::mock();
        $apiMock->v2010 = $v2010Mock;

        $clientMock = Mockery::mock(Client::class);
        $clientMock->messaging = $messagingMock;
        $clientMock->api = $apiMock;

        config(['services.twilio.use_sandbox' => true]);

        $manager = new TwilioAccountManager($clientMock);
        $result = $manager->cleanupTenantResources($this->gym->id);

        // Overall should report failure due to phone number error
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
        // But messaging service and subaccount should have succeeded
        $this->assertTrue($result['cleaned']['messaging_service']['success']);
        $this->assertTrue($result['cleaned']['subaccount']['success']);
    }

    // ========== GYM OBSERVER INTEGRATION ==========

    #[Test]
    public function it_calls_twilio_cleanup_when_gym_is_deleted(): void
    {
        $managerMock = Mockery::mock(TwilioAccountManager::class);
        $managerMock->shouldReceive('cleanupTenantResources')
            ->with($this->gym->id)
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'All Twilio resources cleaned up successfully',
                'cleaned' => [],
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $managerMock);

        $this->gym->delete();

        $this->assertDatabaseMissing('gyms', ['id' => $this->gym->id]);
        $this->assertDatabaseMissing('whatsapp_accounts', ['gym_id' => $this->gym->id]);
        $this->assertDatabaseMissing('whatsapp_phone_numbers', ['whatsapp_account_id' => $this->whatsappAccount->id]);
    }

    #[Test]
    public function it_still_deletes_gym_when_twilio_cleanup_fails(): void
    {
        $managerMock = Mockery::mock(TwilioAccountManager::class);
        $managerMock->shouldReceive('cleanupTenantResources')
            ->with($this->gym->id)
            ->once()
            ->andThrow(new \Exception('Twilio API unavailable'));

        $this->app->singleton(TwilioAccountManager::class, fn () => $managerMock);

        // Gym deletion should NOT be blocked by Twilio failure
        $this->gym->delete();

        $this->assertDatabaseMissing('gyms', ['id' => $this->gym->id]);
        $this->assertDatabaseMissing('whatsapp_accounts', ['gym_id' => $this->gym->id]);
    }

    #[Test]
    public function it_does_not_call_twilio_cleanup_when_gym_has_no_whatsapp_account(): void
    {
        $gymWithoutWhatsApp = Gym::factory()->create();

        $managerMock = Mockery::mock(TwilioAccountManager::class);
        $managerMock->shouldNotReceive('cleanupTenantResources');

        $this->app->singleton(TwilioAccountManager::class, fn () => $managerMock);

        $gymWithoutWhatsApp->delete();

        $this->assertDatabaseMissing('gyms', ['id' => $gymWithoutWhatsApp->id]);
    }

    #[Test]
    public function it_cleans_up_multiple_phone_numbers(): void
    {
        // Add a second phone number
        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $this->whatsappAccount->id,
            'phone_number' => '+14155559999',
            'phone_number_sid' => 'PNtest_cleanup_456',
            'country_code' => 'US',
            'friendly_name' => 'Second Number',
            'status' => 'active',
            'type' => 'local',
            'purchased_at' => now(),
        ]);

        $managerMock = Mockery::mock(TwilioAccountManager::class);
        $managerMock->shouldReceive('cleanupTenantResources')
            ->with($this->gym->id)
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'All Twilio resources cleaned up successfully',
                'cleaned' => [
                    'phone_numbers' => ['success' => true, 'released' => 2],
                ],
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $managerMock);

        $this->gym->delete();

        $this->assertDatabaseMissing('whatsapp_phone_numbers', [
            'whatsapp_account_id' => $this->whatsappAccount->id,
        ]);
    }

    // ========== HELPER METHODS ==========

    /**
     * Create a full mock for successful cleanup of all resources.
     */
    private function createFullCleanupMock(): Client
    {
        // Phone number removal from messaging service
        $phoneNumbersResourceMock = Mockery::mock();
        $phoneNumbersResourceMock->shouldReceive('delete')->once();

        $combinedServiceMock = Mockery::mock();
        $combinedServiceMock->shouldReceive('phoneNumbers')
            ->with('PNtest_cleanup_123')
            ->once()
            ->andReturn($phoneNumbersResourceMock);
        $combinedServiceMock->shouldReceive('delete')->once();

        $v1Mock = Mockery::mock();
        $v1Mock->shouldReceive('services')
            ->with('MGtest_service_sid')
            ->andReturn($combinedServiceMock);

        $messagingMock = Mockery::mock();
        $messagingMock->v1 = $v1Mock;

        // Incoming phone number release
        $incomingPhoneNumberMock = Mockery::mock();
        $incomingPhoneNumberMock->shouldReceive('delete')->once();

        // Subaccount closure
        $accountUpdateMock = Mockery::mock();
        $accountUpdateMock->shouldReceive('update')
            ->with(['status' => 'closed'])
            ->once();

        $v2010Mock = Mockery::mock();
        $v2010Mock->shouldReceive('accounts')
            ->with('ACtest_cleanup_sid')
            ->once()
            ->andReturn($accountUpdateMock);

        $apiMock = Mockery::mock();
        $apiMock->v2010 = $v2010Mock;

        $clientMock = Mockery::mock(Client::class);
        $clientMock->messaging = $messagingMock;
        $clientMock->shouldReceive('incomingPhoneNumbers')
            ->with('PNtest_cleanup_123')
            ->once()
            ->andReturn($incomingPhoneNumberMock);
        $clientMock->api = $apiMock;

        return $clientMock;
    }
}
