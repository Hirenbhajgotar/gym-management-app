<?php

namespace Tests\Feature\Filament;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Services\TwilioAccountManager;
use App\Services\TwilioWhatsAppService;
use App\Services\WhatsAppBillingService;
use App\Services\WhatsAppTemplateService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class WhatsAppSetupTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => DefaultRole::GYM_OWNER->value, 'guard_name' => 'web']);

        $this->user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ])->assignRole(DefaultRole::GYM_OWNER->value);

        $this->gym = Gym::factory()->create(['name' => 'FitZone Gym']);

        $this->user->gyms()->attach($this->gym);

        $this->actingAs($this->user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->gym);
    }

    protected function tearDown(): void
    {
        try {
            Filament::setTenant(null);
        } catch (\Throwable $_) {
        }

        Mockery::close();
        parent::tearDown();
    }

    // ========== VIEW STATE TESTS ==========

    /** @test */
    public function it_shows_onboarding_state_when_no_whatsapp_account_exists(): void
    {
        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSet('viewState', 'onboarding')
            ->assertSet('setupStep', 1)
            ->assertSee('Welcome to WhatsApp Business')
            ->assertSee('Start Setup');
    }

    /** @test */
    public function it_shows_setup_state_when_account_is_pending(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSet('viewState', 'setup');
    }

    /** @test */
    public function it_shows_active_state_when_account_is_active_with_phone(): void
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        WhatsAppPhoneNumber::create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSet('viewState', 'active')
            ->assertSee('WhatsApp Business Status')
            ->assertSee('Active');
    }

    /** @test */
    public function it_shows_error_state_when_account_active_but_no_phone(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSet('viewState', 'error')
            ->assertSee('Setup Incomplete');
    }

    // ========== SETUP STEP DETERMINATION TESTS ==========

    /** @test */
    public function it_sets_step_2_when_waba_id_is_missing(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSet('viewState', 'setup')
            ->assertSet('setupStep', 2);
    }

    /** @test */
    public function it_sets_step_3_when_phone_number_is_missing(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSet('viewState', 'setup')
            ->assertSet('setupStep', 3);
    }

    // ========== START SETUP ACTION TEST ==========

    /** @test */
    public function it_transitions_to_setup_state_on_start(): void
    {
        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSet('viewState', 'onboarding')
            ->call('startSetup')
            ->assertSet('viewState', 'setup')
            ->assertSet('setupStep', 1);
    }

    // ========== CREATE SUBACCOUNT TESTS ==========

    /** @test */
    public function it_creates_subaccount_successfully(): void
    {
        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('createSubaccount')
            ->once()
            ->andReturn(['success' => true]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        // Pre-create the WhatsAppAccount so loadWhatsAppData finds it
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACnew123',
            'twilio_auth_token' => 'new_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('createSubaccount')
            ->assertSet('setupStep', 2);
    }

    /** @test */
    public function it_shows_error_on_subaccount_creation_failure(): void
    {
        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('createSubaccount')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Subaccount limit reached',
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('createSubaccount')
            ->assertNotified('Failed to Create Account');
    }

    /** @test */
    public function it_handles_exception_during_subaccount_creation(): void
    {
        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('createSubaccount')
            ->once()
            ->andThrow(new \Exception('Connection timeout'));

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('createSubaccount')
            ->assertNotified('Error');
    }

    // ========== SAVE BUSINESS PROFILE TESTS ==========

    /** @test */
    public function it_saves_business_profile_and_advances_step(): void
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.business_name', 'My Fitness Hub');
        $component->set('data.business_email', 'info@myfitnesshub.com');
        $component->set('data.business_description', 'Premium gym facility');

        $component->call('saveBusinessProfile')
            ->assertSet('setupStep', 2);

        $account->refresh();
        $this->assertEquals('My Fitness Hub', $account->business_name);
        $this->assertEquals('info@myfitnesshub.com', $account->business_email);
    }

    // ========== CREATE WABA TESTS ==========

    /** @test */
    public function it_creates_waba_successfully(): void
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('createWaba')
            ->once()
            ->andReturn(['success' => true]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('createWaba')
            ->assertSet('setupStep', 3)
            ->assertNotified('WABA Created');
    }

    /** @test */
    public function it_handles_waba_creation_failure(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('createWaba')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'WABA creation failed',
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('createWaba')
            ->assertNotified('Error Creating WABA');
    }

    // ========== SEARCH NUMBERS TESTS ==========

    /** @test */
    public function it_searches_available_numbers(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('searchNumbers')
            ->once()
            ->andReturn([
                ['phoneNumber' => '+14155551234', 'locality' => 'San Francisco'],
                ['phoneNumber' => '+14155555678', 'locality' => 'Oakland'],
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('searchNumbers')
            ->assertSet('availableNumbers', [
                ['phoneNumber' => '+14155551234', 'locality' => 'San Francisco'],
                ['phoneNumber' => '+14155555678', 'locality' => 'Oakland'],
            ]);
    }

    /** @test */
    public function it_shows_warning_when_no_numbers_found(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('searchNumbers')
            ->once()
            ->andReturn([]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('searchNumbers')
            ->assertNotified('No Numbers Found');
    }

    // ========== PURCHASE NUMBER TESTS ==========

    /** @test */
    public function it_purchases_selected_number_successfully(): void
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        WhatsAppPhoneNumber::create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('purchaseNumber')
            ->once()
            ->andReturn(['success' => true]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('selectNumber', '+14155551234')
            ->assertSet('availableNumbers', [])
            ->assertSet('selectedNumber', null)
            ->assertSet('setupStep', 4)
            ->assertNotified('Number Purchased');
    }

    /** @test */
    public function it_warns_if_no_number_selected_before_purchase(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('purchaseNumber')
            ->assertNotified('Select Number');
    }

    // ========== CREATE DEFAULT TEMPLATES TESTS ==========

    /** @test */
    public function it_creates_default_templates(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'messaging_service_sid' => 'MStest123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(WhatsAppTemplateService::class);
        $mock->shouldReceive('createDefaultTemplates')
            ->with($this->gym->id)
            ->once();

        $this->app->singleton(WhatsAppTemplateService::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('createDefaultTemplates')
            ->assertSet('setupStep', 5)
            ->assertNotified('Templates Created');
    }

    /** @test */
    public function it_handles_template_creation_failure(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'messaging_service_sid' => 'MStest123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(WhatsAppTemplateService::class);
        $mock->shouldReceive('createDefaultTemplates')
            ->once()
            ->andThrow(new \Exception('Template API error'));

        $this->app->singleton(WhatsAppTemplateService::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('createDefaultTemplates')
            ->assertNotified('Template Creation Failed');
    }

    // ========== WELCOME CREDITS TESTS ==========

    /** @test */
    public function it_claims_welcome_credits_and_activates_account(): void
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'messaging_service_sid' => 'MStest123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $billingMock = Mockery::mock(WhatsAppBillingService::class);
        $billingMock->shouldReceive('addWelcomeCredits')
            ->with($this->gym->id)
            ->once();

        $this->app->singleton(WhatsAppBillingService::class, fn () => $billingMock);

        // Create the credits record the method will look up after calling addWelcomeCredits
        WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 50,
            'used_credits' => 0,
            'remaining_credits' => 50,
            'welcome_credits_claimed' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('claimWelcomeCredits')
            ->assertSet('welcomeCreditsClaimed', true)
            ->assertSet('viewState', 'active')
            ->assertNotified('Welcome Credits Claimed!');

        $account->refresh();
        $this->assertEquals('active', $account->status->value ?? $account->status);
    }

    /** @test */
    public function it_handles_welcome_credits_claim_failure(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $billingMock = Mockery::mock(WhatsAppBillingService::class);
        $billingMock->shouldReceive('addWelcomeCredits')
            ->once()
            ->andThrow(new \Exception('Credits already claimed'));

        $this->app->singleton(WhatsAppBillingService::class, fn () => $billingMock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('claimWelcomeCredits')
            ->assertNotified('Error');
    }

    // ========== SEND TEST MESSAGE TESTS ==========

    /** @test */
    public function it_sends_test_message_successfully(): void
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        WhatsAppPhoneNumber::create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        $mock = Mockery::mock(TwilioWhatsAppService::class);
        $mock->shouldReceive('sendTemplateMessage')
            ->once()
            ->andReturn([
                'success' => true,
                'message_id' => 'SM123456789',
                'status' => 'queued',
            ]);

        $this->app->singleton(TwilioWhatsAppService::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage', ['test_phone' => '+19876543210'])
            ->assertNotified('Test Message Sent!');
    }

    /** @test */
    public function it_handles_test_message_failure(): void
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        WhatsAppPhoneNumber::create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        $mock = Mockery::mock(TwilioWhatsAppService::class);
        $mock->shouldReceive('sendTemplateMessage')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Template not approved',
            ]);

        $this->app->singleton(TwilioWhatsAppService::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage', ['test_phone' => '+19876543210'])
            ->assertNotified('Failed to Send');
    }

    /** @test */
    public function it_requires_phone_number_for_test_message(): void
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        WhatsAppPhoneNumber::create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage', ['test_phone' => null])
            ->assertNotified('Failed to Send');
    }

    // ========== FORM INITIALIZATION TESTS ==========

    /** @test */
    public function it_initializes_form_data_from_gym_and_user(): void
    {
        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSet('data.gym_name', 'FitZone Gym')
            ->assertSet('data.owner_email', 'john@example.com');
    }

    // ========== REFRESH TESTS ==========

    /** @test */
    public function it_refreshes_data_and_recalculates_state(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSet('viewState', 'onboarding');

        // Create account in the background
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        WhatsAppPhoneNumber::create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        // Refresh should pick up the new account
        $component->call('refresh')
            ->assertSet('viewState', 'active');
    }

    // ========== CONNECT EXISTING WABA TESTS ==========

    /** @test */
    public function it_shows_waba_connection_method_options_on_step_2(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSet('viewState', 'setup')
            ->assertSet('setupStep', 2)
            ->assertSee('How would you like to set up your WABA?')
            ->assertSee('Connect Existing WABA')
            ->assertSee('Create New WABA');
    }

    /** @test */
    public function it_connects_existing_waba_successfully(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('connectManualWaba')
            ->once()
            ->with($this->gym->id, 'WABA999', '+15551234567', null)
            ->andReturn([
                'success' => true,
                'waba_id' => 'WABA999',
                'sender_sid' => 'SNtest123',
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', 'WABA999');
        $component->set('data.manual_phone_number', '+15551234567');

        $component->call('connectExistingWaba')
            ->assertSet('wabaCreated', true)
            ->assertSet('setupStep', 3)
            ->assertNotified('WABA Connected');
    }

    /** @test */
    public function it_connects_existing_waba_with_access_token(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('connectManualWaba')
            ->once()
            ->with($this->gym->id, 'WABA999', '+15551234567', 'EAAtoken123')
            ->andReturn([
                'success' => true,
                'waba_id' => 'WABA999',
                'sender_sid' => 'SNtest123',
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', 'WABA999');
        $component->set('data.manual_phone_number', '+15551234567');
        $component->set('data.meta_access_token', 'EAAtoken123');

        $component->call('connectExistingWaba')
            ->assertSet('wabaCreated', true)
            ->assertSet('setupStep', 3)
            ->assertNotified('WABA Connected');
    }

    /** @test */
    public function it_shows_error_when_existing_waba_connection_fails(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('connectManualWaba')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Invalid WABA ID',
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', 'INVALID');
        $component->set('data.manual_phone_number', '+15551234567');

        $component->call('connectExistingWaba')
            ->assertNotified('Connection Failed');
    }

    /** @test */
    public function it_shows_error_when_waba_id_missing_on_connect(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', '');
        $component->set('data.manual_phone_number', '+15551234567');

        $component->call('connectExistingWaba')
            ->assertNotified('Missing Information');
    }

    /** @test */
    public function it_shows_error_when_phone_missing_on_connect(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', 'WABA999');
        $component->set('data.manual_phone_number', '');

        $component->call('connectExistingWaba')
            ->assertNotified('Missing Information');
    }

    /** @test */
    public function it_handles_exception_during_existing_waba_connection(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('connectManualWaba')
            ->once()
            ->andThrow(new \Exception('Network timeout'));

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', 'WABA999');
        $component->set('data.manual_phone_number', '+15551234567');

        $component->call('connectExistingWaba')
            ->assertNotified('Connection Failed');
    }

    /** @test */
    public function it_still_creates_new_waba_successfully_after_integration(): void
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('createWaba')
            ->once()
            ->andReturn(['success' => true]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('createWaba')
            ->assertSet('setupStep', 3)
            ->assertNotified('WABA Created');
    }

    /** @test */
    public function it_shows_phone_number_source_radio_on_step_3(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertFormFieldExists('phone_number_source')
            ->set('data.phone_number_source', 'use_own_number')
            ->assertSet('data.phone_number_source', 'use_own_number')
            ->set('data.phone_number_source', 'purchase_twilio')
            ->assertSet('data.phone_number_source', 'purchase_twilio');
    }

    /** @test */
    public function it_registers_own_phone_number_and_triggers_otp(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('registerOwnNumber')
            ->once()
            ->with($this->gym->id, '+919876543210')
            ->andReturn([
                'success' => true,
                'sender_sid' => 'SNtest123',
                'status' => 'pending_verification',
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->set('data.phone_number_source', 'use_own_number')
            ->set('data.own_phone_number', '+919876543210')
            ->call('registerOwnPhoneNumber')
            ->assertSet('ownNumberRegistered', true)
            ->assertSet('senderSid', 'SNtest123')
            ->assertSet('numberPurchased', false)
            ->assertNotified('Verification Code Sent');
    }

    /** @test */
    public function it_handles_own_number_registration_failure(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('registerOwnNumber')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Number already registered with WhatsApp',
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->set('data.phone_number_source', 'use_own_number')
            ->set('data.own_phone_number', '+919876543210')
            ->call('registerOwnPhoneNumber')
            ->assertSet('ownNumberRegistered', false)
            ->assertNotified('Registration Failed');
    }

    /** @test */
    public function it_handles_exception_during_own_number_registration(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('registerOwnNumber')
            ->once()
            ->andThrow(new \Exception('Twilio API timeout'));

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->set('data.phone_number_source', 'use_own_number')
            ->set('data.own_phone_number', '+919876543210')
            ->call('registerOwnPhoneNumber')
            ->assertSet('ownNumberRegistered', false)
            ->assertNotified('Registration Failed');
    }

    /** @test */
    public function it_warns_when_registering_own_number_without_input(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->set('data.phone_number_source', 'use_own_number')
            ->call('registerOwnPhoneNumber')
            ->assertNotified('Phone Number Required')
            ->assertSet('ownNumberRegistered', false)
            ->assertSet('numberPurchased', false);
    }

    // ========== OTP VERIFICATION TESTS ==========

    /** @test */
    public function it_verifies_own_phone_number_otp_successfully(): void
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        WhatsAppPhoneNumber::create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+919876543210',
            'phone_number_sid' => 'SNtest123',
            'country_code' => 'IN',
            'status' => 'purchased',
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('verifyOwnNumber')
            ->once()
            ->with($this->gym->id, 'SNtest123', '123456')
            ->andReturn([
                'success' => true,
                'message' => 'Phone number verified successfully.',
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->set('senderSid', 'SNtest123')
            ->set('ownNumberRegistered', true)
            ->set('data.otp_code', '123456')
            ->call('verifyOwnPhoneNumber')
            ->assertSet('numberPurchased', true)
            ->assertSet('setupStep', 4)
            ->assertNotified('Number Verified');
    }

    /** @test */
    public function it_handles_otp_verification_failure(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('verifyOwnNumber')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Invalid verification code',
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->set('senderSid', 'SNtest123')
            ->set('ownNumberRegistered', true)
            ->set('data.otp_code', '000000')
            ->call('verifyOwnPhoneNumber')
            ->assertSet('numberPurchased', false)
            ->assertNotified('Verification Failed');
    }

    /** @test */
    public function it_warns_when_verifying_without_otp_code(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->set('senderSid', 'SNtest123')
            ->set('ownNumberRegistered', true)
            ->call('verifyOwnPhoneNumber')
            ->assertNotified('Code Required')
            ->assertSet('numberPurchased', false);
    }

    // ========== RESEND OTP TESTS ==========

    /** @test */
    public function it_resends_otp_successfully(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('resendOwnNumberOtp')
            ->once()
            ->with($this->gym->id, 'SNtest123')
            ->andReturn([
                'success' => true,
                'message' => 'Verification code has been resent.',
            ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->set('senderSid', 'SNtest123')
            ->set('ownNumberRegistered', true)
            ->call('resendOwnPhoneOtp')
            ->assertNotified('Code Resent');
    }

    /** @test */
    public function it_handles_resend_otp_failure(): void
    {
        WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        $mock->shouldReceive('resendOwnNumberOtp')
            ->once()
            ->andThrow(new \Exception('Rate limit exceeded'));

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->set('senderSid', 'SNtest123')
            ->set('ownNumberRegistered', true)
            ->call('resendOwnPhoneOtp')
            ->assertNotified('Resend Failed');
    }
}
