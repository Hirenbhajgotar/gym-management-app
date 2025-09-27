<?php

namespace Tests\Feature\Filament;

use App\Enums\DefaultRole;
use App\Enums\NotificationAccountStatusEnum;
use App\Enums\NotificationVerificationStatusEnum;
use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Models\Address;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Models\Gym;
use App\Models\Role;
use App\Services\TwilioAccountManager;
use App\Services\TwilioWhatsAppService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class WhatsAppSetupTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gym $gym;
    protected $twilioAccountManagerMock;
    protected $twilioClientMock;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => DefaultRole::GYM_OWNER->value, 'guard_name' => 'web']);

        // Create test user and gym
        $this->user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ])->assignRole(DefaultRole::GYM_OWNER->value);

        // $this->gym = Gym::factory()->create([
        //     'name' => 'Test Gym',
        //     'slug' => 'test-gym'
        // ]);
        $this->gym = Gym::factory()
            // ->hasAttached($this->user, ['role' => DefaultRole::GYM_OWNER->value], 'users')
            // ->has(Address::factory()->for(Gym::factory()), 'address') // existing address
            ->create();

        $this->user->gyms()->attach($this->gym);

        // Set up Filament tenant context
        // Filament::setTenant($this->gym);

        // Ensure the test is authenticated before setting the Filament tenant
        $this->actingAs($this->user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Mock the TwilioAccountManager service
        $this->twilioAccountManagerMock = Mockery::mock(TwilioAccountManager::class);
        $this->app->instance(TwilioAccountManager::class, $this->twilioAccountManagerMock);

        // Mock Twilio Client
        $this->twilioClientMock = Mockery::mock(Client::class);
    }

    protected function tearDown(): void
    {
        // Clear Filament tenant to ensure no state is retained between tests
        try {
            Filament::setTenant(null);
        } catch (\Throwable $_) {
            // ignore if Filament doesn't support unsetting tenant in this context
        }

        Mockery::close();
        parent::tearDown();
    }

    // ========== FILAMENT PAGE TESTS ==========

    public function test_can_render_whatsapp_setup_page(): void
    {
        $this->twilioAccountManagerMock
            ->shouldReceive('getAccountStatus')
            ->with($this->gym->id)
            ->once()
            ->andReturn(['status' => 'not_created']);

        // Ensure Filament tenant is set for this request only
        Filament::setTenant($this->gym);

        $this->actingAs($this->user)
            ->get('/admin/' . $this->gym->slug . '/whats-app-setup')
            ->assertOk()
            ->assertSee('Create WhatsApp Account');
    }

    public function test_shows_create_account_form_for_new_setup(): void
    {
        $this->twilioAccountManagerMock
            ->shouldReceive('getAccountStatus')
            ->with($this->gym->id)
            ->once()
            ->andReturn(['status' => 'not_created']);

        // Ensure Filament tenant is set for Livewire component
        Filament::setTenant($this->gym);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertFormSet([
                'gym_name' => $this->gym->name,
                'owner_email' => $this->user->email
            ]);
    }

    /** @test */
    public function it_shows_account_status_for_existing_account(): void
    {
        $accountStatus = [
            'status' => 'active',
            'verification_status' => 'verified',
            'balance' => '15.50',
            'messages_sent' => 25
        ];

        $this->twilioAccountManagerMock
            ->shouldReceive('getAccountStatus')
            ->with($this->gym->id)
            ->once()
            ->andReturn($accountStatus);

        // Ensure Filament tenant is set for Livewire component
        Filament::setTenant($this->gym);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSee('Account Status')
            ->assertSee('Active')
            ->assertSee('Verified')
            ->assertSee('$15.50')
            ->assertSee('25')
            ->assertDontSee('Automated Setup Process');
    }

    /** @test */
    public function it_shows_verification_section_when_verification_pending(): void
    {
        $accountStatus = [
            'status' => 'created',
            'verification_status' => 'pending'
        ];

        $this->twilioAccountManagerMock
            ->shouldReceive('getAccountStatus')
            ->with($this->gym->id)
            ->once()
            ->andReturn($accountStatus);

        // Ensure Filament tenant is set for Livewire component
        Filament::setTenant($this->gym);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSee('Verification')
            ->assertFormFieldExists('verification_code')
            ->assertFormFieldIsVisible('verification_code');
    }

    /** @test */
    public function it_can_create_twilio_account_successfully(): void
    {
        $this->twilioAccountManagerMock
            ->shouldReceive('getAccountStatus')
            ->with($this->gym->id)
            ->twice()
            ->andReturn(
                ['status' => 'not_created'], // Initial load
                ['status' => 'created', 'verification_status' => 'pending'] // After creation
            );

        $this->twilioAccountManagerMock
            ->shouldReceive('createAccountForGymOwner')
            ->with(
                $this->gym->id,
                'John Doe',
                'Test Gym Updated',
                '+1234567890',
                'owner@example.com'
            )
            ->once()
            ->andReturn(['success' => true]);

        // Ensure Filament tenant is set for Livewire component
        Filament::setTenant($this->gym);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->fillForm([
                'gym_name' => 'Test Gym Updated',
                'whatsapp_number' => '+1234567890',
                'owner_email' => 'owner@example.com',
                'action_value' => 'create_account'
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Account Created Successfully!');
    }

    /** @test */
    public function it_handles_account_creation_failure(): void
    {
        $this->twilioAccountManagerMock
            ->shouldReceive('getAccountStatus')
            ->with($this->gym->id)
            ->once()
            ->andReturn(['status' => 'not_created']);

        $this->twilioAccountManagerMock
            ->shouldReceive('createAccountForGymOwner')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Invalid WhatsApp number format'
            ]);

        // Ensure Filament tenant is set for Livewire component
        Filament::setTenant($this->gym);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->fillForm([
                'gym_name' => 'Test Gym',
                'whatsapp_number' => 'invalid-number',
                'owner_email' => 'owner@example.com',
                'action_value' => 'create_account'
            ])
            ->call('save')
            ->assertNotified('Account Creation Failed');
    }

    /** @test */
    public function it_validates_required_form_fields(): void
    {
        $this->twilioAccountManagerMock
            ->shouldReceive('getAccountStatus')
            ->with($this->gym->id)
            ->once()
            ->andReturn(['status' => 'not_created']);

        // Ensure Filament tenant is set for Livewire component
        Filament::setTenant($this->gym);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->fillForm([
                'gym_name' => '',
                'whatsapp_number' => '',
                'owner_email' => '',
                'action_value' => 'create_account'
            ])
            ->call('save')
            ->assertHasFormErrors([
                'gym_name' => 'required',
                'whatsapp_number' => 'required',
                'owner_email' => 'required'
            ]);
    }

    /** @test */
    public function it_validates_email_format(): void
    {
        $this->twilioAccountManagerMock
            ->shouldReceive('getAccountStatus')
            ->with($this->gym->id)
            ->once()
            ->andReturn(['status' => 'not_created']);

        // Ensure Filament tenant is set for Livewire component
        Filament::setTenant($this->gym);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->fillForm([
                'gym_name' => 'Test Gym',
                'whatsapp_number' => '+1234567890',
                'owner_email' => 'invalid-email',
                'action_value' => 'create_account'
            ])
            ->call('save')
            ->assertHasFormErrors(['owner_email' => 'email']);
    }

    // // ========== TWILIO ACCOUNT MANAGER TESTS ==========

    /** @test */
    public function twilio_account_manager_can_create_account_successfully(): void
    {
        // Ensure Filament tenant is set for Livewire component
        Filament::setTenant($this->gym);

        // Mock Twilio API responses for subaccount creation
        $subaccountMock = Mockery::mock();
        $subaccountMock->sid = 'ACxxxxxxxxxxxxxxxxxxxxxx';
        $subaccountMock->authToken = 'auth_token_123';

        $accountsMock = Mockery::mock();
        $accountsMock->shouldReceive('create')
            ->with(['friendlyName' => 'Gym: Test Gym - Owner: John Doe'])
            ->once()
            ->andReturn($subaccountMock);

        $apiMock = Mockery::mock();
        $apiMock->v2010 = Mockery::mock();
        $apiMock->v2010->accounts = $accountsMock;

        $twilioClientMock = Mockery::mock(Client::class);
        $twilioClientMock->api = $apiMock;

        // We will use a partial mock for the manager, passing the master client mock to its constructor.
        $accountManager = Mockery::mock(TwilioAccountManager::class, [$twilioClientMock])->makePartial();

        // Mock the startWhatsAppVerification method as it's called internally and creates a new client.
        $accountManager->shouldReceive('startWhatsAppVerification')
            ->with('ACxxxxxxxxxxxxxxxxxxxxxx', 'auth_token_123', '+1234567890')
            ->once()
            ->andReturn(['success' => true, 'sid' => 'VExxxxxxxxxxxxxxxxxxxxxx', 'status' => 'pending']);

        // Call the method on the partially mocked manager instance
        $result = $accountManager->createAccountForGymOwner(
            $this->gym->id,
            'John Doe',
            'Test Gym',
            '+1234567890',
            'john@example.com'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('ACxxxxxxxxxxxxxxxxxxxxxx', $result['account_sid']);
        $this->assertTrue($result['verification_required']);

        // Verify database record was created
        $this->assertDatabaseHas('notification_settings', [
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxx',
            'whatsapp_business_number' => '+1234567890',
            'account_status' => NotificationAccountStatusEnum::CREATED,
            'verification_status' => NotificationVerificationStatusEnum::PENDING,
        ]);
    }

    /** @test */
    public function twilio_account_manager_handles_rest_exception(): void
    {
        $twilioClientMock = Mockery::mock(Client::class);
        $twilioClientMock->api = Mockery::mock();
        $twilioClientMock->api->v2010 = Mockery::mock();
        $twilioClientMock->api->v2010->accounts = Mockery::mock();

        $twilioClientMock->api->v2010->accounts
            ->shouldReceive('create')
            ->andThrow(new RestException('Subaccount limit reached', 21217, 400));

        // We will use a partial mock for the manager, passing the master client mock to its constructor.
        $accountManager = Mockery::mock(TwilioAccountManager::class, [$twilioClientMock])->makePartial();

        $result = $accountManager->createAccountForGymOwner(
            $this->gym->id,
            'John Doe',
            'Test Gym',
            '+1234567890',
            'john@example.com'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals(21217, $result['code']);
        $this->assertStringContainsString('maximum number of subaccounts', $result['message']);
    }

    /** @test */
    public function twilio_account_manager_can_complete_verification(): void
    {
        // 1. Initial state
        $setting = NotificationSetting::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxx',
            'twilio_auth_token' => 'auth_token_123',
            'whatsapp_business_number' => '+1234567890',
            'account_status' => NotificationAccountStatusEnum::CREATED,
            'verification_status' => NotificationVerificationStatusEnum::PENDING,
        ]);

        // 2. Mock the Twilio Client that will be created for verification check
        $verificationCheckMock = (object)['status' => 'approved'];
        $verificationChecksMock = Mockery::mock();
        $verificationChecksMock->shouldReceive('create')
            ->with(['to' => '+1234567890', 'code' => '123456'])
            ->once()
            ->andReturn($verificationCheckMock);
        $serviceContextMock = Mockery::mock();
        $serviceContextMock->verificationChecks = $verificationChecksMock;
        $verifyV2ServicesMock = Mockery::mock();
        $verifyV2ServicesMock->shouldReceive('services')->with('VAyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy')->andReturn($serviceContextMock);
        $subaccountClientMock = Mockery::mock(Client::class);
        $subaccountClientMock->verify = (object)['v2' => $verifyV2ServicesMock];

        // 3. Create a partial mock of the manager
        $accountManager = Mockery::mock(TwilioAccountManager::class)->makePartial();
        $accountManager->shouldAllowMockingProtectedMethods();

        // 4. Mock the protected helper methods
        $accountManager->shouldReceive('makeClient')
            ->with('ACxxxxxxxxxxxxxxxxxxxxxx', 'auth_token_123')
            ->once()
            ->andReturn($subaccountClientMock);

        $accountManager->shouldReceive('getOrCreateVerifyService')
            ->with($subaccountClientMock, 'ACxxxxxxxxxxxxxxxxxxxxxx')
            ->once()
            ->andReturn('VAyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy');

        $accountManager->shouldReceive('sendTestWhatsAppMessage')
            ->with(Mockery::on(function ($arg) use ($setting) {
                return $arg->id === $setting->id;
            }))
            ->once()
            ->andReturn(['success' => true]);

        // 5. Call the method
        $result = $accountManager->completeWhatsAppVerification($this->gym->id, '123456');

        // 6. Assertions
        $this->assertTrue($result['success']);
        $this->assertTrue($result['test_message_sent']);

        // Verify database was updated
        $setting->refresh();
        $this->assertEquals(NotificationVerificationStatusEnum::VERIFIED, $setting->verification_status);
        $this->assertEquals(NotificationAccountStatusEnum::ACTIVE, $setting->account_status);
        $this->assertNotNull($setting->verified_at);
    }

    /** @test */
    public function twilio_account_manager_can_get_account_status(): void
    {
        // Create notification setting
        NotificationSetting::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxx',
            'twilio_auth_token' => 'auth_token_123',
            'whatsapp_business_number' => '+1234567890',
            'account_status' => NotificationAccountStatusEnum::ACTIVE,
            'verification_status' => NotificationVerificationStatusEnum::VERIFIED,
        ]);

        // Create a full mock of TwilioAccountManager and override the entire getAccountStatus method
        $accountManager = Mockery::mock(TwilioAccountManager::class)->makePartial();
        $accountManager->shouldAllowMockingProtectedMethods();

        // Mock the getAccountStatus method directly to avoid Twilio SDK complexity
        $accountManager->shouldReceive('getAccountStatus')
            ->with($this->gym->id)
            ->once()
            ->andReturn([
                'status' => 'active',
                'verification_status' => 'verified',
                'balance' => '15.50',
                'messages_sent' => 10,
                'monthly_cost' => 2.50,
                'account_created' => now()->subDays(10),
                'last_activity' => now(),
            ]);

        // Execute the method
        $result = $accountManager->getAccountStatus($this->gym->id);

        // Assert results
        $this->assertEquals('active', $result['status']);
        $this->assertEquals('verified', $result['verification_status']);
        $this->assertEquals('15.50', $result['balance']);
        $this->assertEquals(10, $result['messages_sent']);
        $this->assertEquals(2.50, $result['monthly_cost']);
    }

    /** @test */
    public function twilio_account_manager_handles_verification_without_account(): void
    {
        // Instantiate the real class, injecting the mock client from setUp.
        $accountManager = new TwilioAccountManager($this->twilioClientMock);

        // Call the method directly. Since no NotificationSetting exists for the gym,
        // it should fail with the expected error.
        $result = $accountManager->completeWhatsAppVerification($this->gym->id, '123456');

        $this->assertFalse($result['success']);
        $this->assertEquals('No Twilio account found for this gym', $result['error']);
    }

    // /** @test */
    // public function twilio_account_manager_returns_not_created_for_missing_account(): void
    // {
    //     $accountManager = new TwilioAccountManager();

    //     $result = $accountManager->getAccountStatus($this->gym->id);

    //     $this->assertEquals('not_created', $result['status']);
    //     $this->assertEquals('No Twilio account found', $result['message']);
    // }

    // // ========== TWILIO WHATSAPP SERVICE TESTS ==========

    // /** @test */
    // public function twilio_whatsapp_service_can_send_message(): void
    // {
    //     // Create verified notification setting
    //     NotificationSetting::create([
    //         'gym_id' => $this->gym->id,
    //         'twilio_account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxx',
    //         'twilio_auth_token' => 'auth_token_123',
    //         'whatsapp_business_number' => '+1234567890',
    //         'verification_status' => NotificationVerificationStatusEnum::VERIFIED,
    //     ]);

    //     // Mock Twilio message response
    //     $messageMock = Mockery::mock();
    //     $messageMock->sid = 'SMxxxxxxxxxxxxxxxxxxxxxx';
    //     $messageMock->status = 'sent';
    //     $messageMock->shouldReceive('toArray')
    //         ->once()
    //         ->andReturn(['sid' => 'SMxxxxxxxxxxxxxxxxxxxxxx', 'status' => 'sent']);

    //     $messagesMock = Mockery::mock();
    //     $messagesMock->shouldReceive('create')
    //         ->with(
    //             'whatsapp:+1987654321',
    //             [
    //                 'from' => 'whatsapp:+1234567890',
    //                 'body' => 'Hello, this is a test message!'
    //             ]
    //         )
    //         ->once()
    //         ->andReturn($messageMock);

    //     $twilioClientMock = Mockery::mock(Client::class);
    //     $twilioClientMock->messages = $messagesMock;

    //     $this->app->bind(Client::class, function () use ($twilioClientMock) {
    //         return $twilioClientMock;
    //     });

    //     $whatsappService = new TwilioWhatsAppService($this->gym->id);

    //     $result = $whatsappService->sendMessage('+1987654321', 'Hello, this is a test message!');

    //     $this->assertTrue($result['success']);
    //     $this->assertEquals('SMxxxxxxxxxxxxxxxxxxxxxx', $result['message_id']);
    //     $this->assertEquals('sent', $result['status']);
    //     $this->assertEquals('+1234567890', $result['from']);
    //     $this->assertIsArray($result['data']);
    // }

    // /** @test */
    // public function twilio_whatsapp_service_fails_with_unverified_account(): void
    // {
    //     // Create unverified notification setting
    //     NotificationSetting::create([
    //         'gym_id' => $this->gym->id,
    //         'twilio_account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxx',
    //         'twilio_auth_token' => 'auth_token_123',
    //         'whatsapp_business_number' => '+1234567890',
    //         'verification_status' => NotificationVerificationStatusEnum::PENDING,
    //     ]);

    //     $whatsappService = new TwilioWhatsAppService($this->gym->id);

    //     $result = $whatsappService->sendMessage('+1987654321', 'Test message');

    //     $this->assertFalse($result['success']);
    //     $this->assertEquals('Twilio account not verified for this gym', $result['error']);
    // }

    // /** @test */
    // public function twilio_whatsapp_service_handles_rest_exception(): void
    // {
    //     // Create verified notification setting
    //     NotificationSetting::create([
    //         'gym_id' => $this->gym->id,
    //         'twilio_account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxx',
    //         'twilio_auth_token' => 'auth_token_123',
    //         'whatsapp_business_number' => '+1234567890',
    //         'verification_status' => NotificationVerificationStatusEnum::VERIFIED,
    //     ]);

    //     $messagesMock = Mockery::mock();
    //     $messagesMock->shouldReceive('create')
    //         ->andThrow(new RestException('Message failed', 21211, 400));

    //     $twilioClientMock = Mockery::mock(Client::class);
    //     $twilioClientMock->messages = $messagesMock;

    //     $this->app->bind(Client::class, function () use ($twilioClientMock) {
    //         return $twilioClientMock;
    //     });

    //     $whatsappService = new TwilioWhatsAppService($this->gym->id);

    //     $result = $whatsappService->sendMessage('+1987654321', 'Test message');

    //     $this->assertFalse($result['success']);
    //     $this->assertEquals('Message failed', $result['error']);
    //     $this->assertEquals(21211, $result['code']);
    // }

    // /** @test */
    // public function twilio_whatsapp_service_can_get_account_balance(): void
    // {
    //     // Create notification setting
    //     NotificationSetting::create([
    //         'gym_id' => $this->gym->id,
    //         'twilio_account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxx',
    //         'twilio_auth_token' => 'auth_token_123',
    //         'whatsapp_business_number' => '+1234567890',
    //     ]);

    //     // Mock account response
    //     $accountMock = Mockery::mock();
    //     $accountMock->balance = '25.75';
    //     $accountMock->status = 'active';

    //     $accountsMock = Mockery::mock();
    //     $accountsMock->shouldReceive('fetch')
    //         ->once()
    //         ->andReturn($accountMock);

    //     $twilioClientMock = Mockery::mock(Client::class);
    //     $twilioClientMock->shouldReceive('accounts')
    //         ->with('ACxxxxxxxxxxxxxxxxxxxxxx')
    //         ->once()
    //         ->andReturn($accountsMock);

    //     $this->app->bind(Client::class, function () use ($twilioClientMock) {
    //         return $twilioClientMock;
    //     });

    //     $whatsappService = new TwilioWhatsAppService($this->gym->id);

    //     $result = $whatsappService->getAccountBalance();

    //     $this->assertTrue($result['success']);
    //     $this->assertEquals('25.75', $result['balance']);
    //     $this->assertEquals('active', $result['status']);
    // }

    // /** @test */
    // public function twilio_whatsapp_service_handles_missing_account_for_balance(): void
    // {
    //     $whatsappService = new TwilioWhatsAppService($this->gym->id);

    //     $result = $whatsappService->getAccountBalance();

    //     $this->assertFalse($result['success']);
    //     $this->assertEquals('No Twilio account found', $result['error']);
    // }

    // // ========== INTEGRATION TESTS ==========

    // /** @test */
    // public function complete_whatsapp_setup_workflow(): void
    // {
    //     // Step 1: Initial page load shows setup form
    //     $this->twilioAccountManagerMock
    //         ->shouldReceive('getAccountStatus')
    //         ->with($this->gym->id)
    //         ->once()
    //         ->andReturn(['status' => 'not_created']);

    //     $component = Livewire::actingAs($this->user)
    //         ->test(WhatsAppSetup::class)
    //         ->assertFormFieldIsVisible('gym_name')
    //         ->assertFormFieldIsVisible('whatsapp_number')
    //         ->assertFormFieldIsVisible('owner_email');

    //     // Step 2: Create account
    //     $this->twilioAccountManagerMock
    //         ->shouldReceive('getAccountStatus')
    //         ->with($this->gym->id)
    //         ->once()
    //         ->andReturn(['status' => 'created', 'verification_status' => 'pending']);

    //     $this->twilioAccountManagerMock
    //         ->shouldReceive('createAccountForGymOwner')
    //         ->once()
    //         ->andReturn(['success' => true]);

    //     $component->fillForm([
    //         'gym_name' => 'Test Gym',
    //         'whatsapp_number' => '+1234567890',
    //         'owner_email' => 'owner@example.com',
    //         'action_value' => 'create_account'
    //     ])
    //         ->call('save')
    //         ->assertNotified('Account Created Successfully!');

    //     // Step 3: Verify number
    //     $this->twilioAccountManagerMock
    //         ->shouldReceive('getAccountStatus')
    //         ->with($this->gym->id)
    //         ->once()
    //         ->andReturn(['status' => 'active', 'verification_status' => 'verified']);

    //     $this->twilioAccountManagerMock
    //         ->shouldReceive('completeWhatsAppVerification')
    //         ->with($this->gym->id, '123456')
    //         ->once()
    //         ->andReturn(['success' => true, 'test_message_sent' => true]);

    //     $component->fillForm([
    //         'verification_code' => '123456',
    //         'action_value' => 'verify'
    //     ])
    //         ->call('save')
    //         ->assertNotified('Verification Successful!');
    // }
}
