<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use App\Models\WabaSetupSession;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Services\TwilioAccountManager;
use App\Services\TwilioWhatsAppService;
use App\Services\WhatsAppBillingService;
use App\Services\WhatsAppTemplateService;
use Exception;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery;
use Mockery\Expectation;
use PHPUnit\Framework\Attributes\Test;
use Tests\WhatsAppSetupTestCase;
use Throwable;

class WhatsAppSetupTest extends WhatsAppSetupTestCase
{
    protected User $user;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate(['name' => DefaultRole::GYM_OWNER->value, 'guard_name' => 'web']);

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
        } catch (Throwable $_) {
        }

        Mockery::close();
        parent::tearDown();
    }

    // ========== VIEW STATE TESTS ==========

    #[Test]
    public function it_shows_onboarding_state_when_no_whatsapp_account_exists(): void
    {
        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'onboarding');
        $setupPage->assertSet('setupStep', 1);
        $setupPage->assertSee('Welcome to WhatsApp Business');
        $setupPage->assertSee('Start Setup');
    }

    #[Test]
    public function it_shows_setup_state_when_account_is_pending(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
    }

    #[Test]
    public function it_shows_active_state_when_account_is_active_with_phone(): void
    {
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'active');
        $setupPage->assertSee('WhatsApp Business Status');
        $setupPage->assertSee('Active');
    }

    #[Test]
    public function it_shows_error_state_when_account_active_but_no_phone(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'error');
        $setupPage->assertSee('Setup Incomplete');
    }

    // ========== SETUP STEP DETERMINATION TESTS ==========

    #[Test]
    public function it_sets_step_2_when_waba_id_is_missing(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 2);
    }

    #[Test]
    public function it_sets_step_3_when_phone_number_is_missing(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 3);
    }

    // ========== START SETUP ACTION TEST ==========

    #[Test]
    public function it_transitions_to_setup_state_on_start(): void
    {
        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'onboarding');
        $setupPage->call('startSetup');
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 1);
    }

    // ========== CREATE SUBACCOUNT TESTS ==========

    #[Test]
    public function it_creates_subaccount_successfully(): void
    {
        $mock = Mockery::mock(TwilioAccountManager::class);

        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('createSubaccount');
        $expectation->once();
        $expectation->andReturn(['success' => true]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        // Pre-create the WhatsAppAccount so loadWhatsAppData finds it
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACnew123',
            'twilio_auth_token' => 'new_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('createSubaccount');
        $setupPage->assertSet('setupStep', 2);
    }

    #[Test]
    public function it_shows_error_on_subaccount_creation_failure(): void
    {
        $mock = Mockery::mock(TwilioAccountManager::class);

        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('createSubaccount');
        $expectation->once();
        $expectation->andReturn([
            'success' => false,
            'error' => 'Subaccount limit reached',
        ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('createSubaccount');
        $setupPage->assertNotified('Failed to Create Account');
    }

    #[Test]
    public function it_handles_exception_during_subaccount_creation(): void
    {
        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('createSubaccount');
        $expectation->once();
        $expectation->andThrow(new Exception('Connection timeout'));

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('createSubaccount');
        $setupPage->assertNotified('Error');
    }

    // ========== SAVE BUSINESS PROFILE TESTS ==========

    #[Test]
    public function it_saves_business_profile_and_advances_step(): void
    {
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $component */
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

    #[Test]
    public function it_creates_waba_successfully(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('createWaba');
        $expectation->once();
        $expectation->andReturn(['success' => true]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('createWaba');
        $setupPage->assertSet('setupStep', 3);
        $setupPage->assertNotified('WABA Created');
    }

    #[Test]
    public function it_handles_waba_creation_failure(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('createWaba');
        $expectation->once();
        $expectation->andReturn([
            'success' => false,
            'error' => 'WABA creation failed',
        ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('createWaba');
        $setupPage->assertNotified('Error Creating WABA');
    }

    // ========== SEARCH NUMBERS TESTS ==========

    #[Test]
    public function it_searches_available_numbers(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('searchNumbers');
        $expectation->once();
        $expectation->andReturn([
            ['phoneNumber' => '+14155551234', 'locality' => 'San Francisco'],
            ['phoneNumber' => '+14155555678', 'locality' => 'Oakland'],
        ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('searchNumbers');
        $setupPage->assertSet('availableNumbers', [
            ['phoneNumber' => '+14155551234', 'locality' => 'San Francisco'],
            ['phoneNumber' => '+14155555678', 'locality' => 'Oakland'],
        ]);
    }

    #[Test]
    public function it_shows_warning_when_no_numbers_found(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('searchNumbers');
        $expectation->once();
        $expectation->andReturn([]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('searchNumbers');
        $setupPage->assertNotified('No Numbers Found');
    }

    // ========== PURCHASE NUMBER TESTS ==========

    #[Test]
    public function it_purchases_selected_number_successfully(): void
    {
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('purchaseNumber');
        $expectation->once();
        $expectation->andReturn(['success' => true]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('selectNumber', '+14155551234');
        $setupPage->assertSet('availableNumbers', []);
        $setupPage->assertSet('selectedNumber', null);
        $setupPage->assertSet('setupStep', 4);
        $setupPage->assertNotified('Number Purchased');
    }

    #[Test]
    public function it_warns_if_no_number_selected_before_purchase(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('purchaseNumber');
        $setupPage->assertNotified('Select Number');
    }

    // ========== CREATE DEFAULT TEMPLATES TESTS ==========

    #[Test]
    public function it_creates_default_templates(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'messaging_service_sid' => 'MStest123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(WhatsAppTemplateService::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('createDefaultTemplates');
        $expectation->with($this->gym->id);
        $expectation->once();

        $this->app->singleton(WhatsAppTemplateService::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('createDefaultTemplates');
        $setupPage->assertSet('setupStep', 5);
        $setupPage->assertNotified('Templates Created');
    }

    #[Test]
    public function it_handles_template_creation_failure(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'messaging_service_sid' => 'MStest123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(WhatsAppTemplateService::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('createDefaultTemplates');
        $expectation->once();
        $expectation->andThrow(new Exception('Template API error'));

        $this->app->singleton(WhatsAppTemplateService::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('createDefaultTemplates');
        $setupPage->assertNotified('Template Creation Failed');
    }

    // ========== WELCOME CREDITS TESTS ==========

    #[Test]
    public function it_claims_welcome_credits_and_activates_account(): void
    {
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'messaging_service_sid' => 'MStest123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $billingMock = Mockery::mock(WhatsAppBillingService::class);
        /** @var Expectation $expectation */
        $expectation = $billingMock->shouldReceive('addWelcomeCredits');
        $expectation->with($this->gym->id);
        $expectation->once();

        $this->app->singleton(WhatsAppBillingService::class, fn () => $billingMock);

        // Create the credits record the method will look up after calling addWelcomeCredits
        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 50,
            'used_credits' => 0,
            'remaining_credits' => 50,
            'welcome_credits_claimed' => true,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('claimWelcomeCredits');
        $setupPage->assertSet('welcomeCreditsClaimed', true);
        $setupPage->assertSet('viewState', 'active');
        $setupPage->assertNotified('Welcome Credits Claimed!');

        $account->refresh();
        $this->assertEquals('active', $account->status->value ?? $account->status);
    }

    #[Test]
    public function it_handles_welcome_credits_claim_failure(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $billingMock = Mockery::mock(WhatsAppBillingService::class);
        /** @var Expectation $expectation */
        $expectation = $billingMock->shouldReceive('addWelcomeCredits');
        $expectation->once();
        $expectation->andThrow(new Exception('Credits already claimed'));

        $this->app->singleton(WhatsAppBillingService::class, fn () => $billingMock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('claimWelcomeCredits');
        $setupPage->assertNotified('Error');
    }

    // ========== SEND TEST MESSAGE TESTS ==========

    #[Test]
    public function it_sends_test_message_successfully(): void
    {
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        $mock = Mockery::mock(TwilioWhatsAppService::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('sendTemplateMessage');
        $expectation->once();
        $expectation->andReturn([
            'success' => true,
            'message_id' => 'SM123456789',
            'status' => 'queued',
        ]);

        $this->app->singleton(TwilioWhatsAppService::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('sendTestMessage', ['test_phone' => '+19876543210']);
        $setupPage->assertNotified('Test Message Sent!');
    }

    #[Test]
    public function it_handles_test_message_failure(): void
    {
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        $mock = Mockery::mock(TwilioWhatsAppService::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('sendTemplateMessage');
        $expectation->once();
        $expectation->andReturn([
            'success' => false,
            'error' => 'Template not approved',
        ]);

        $this->app->singleton(TwilioWhatsAppService::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('sendTestMessage', ['test_phone' => '+19876543210']);
        $setupPage->assertNotified('Failed to Send');
    }

    #[Test]
    public function it_requires_phone_number_for_test_message(): void
    {
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('sendTestMessage', ['test_phone' => null]);
        $setupPage->assertNotified('Failed to Send');
    }

    // ========== FORM INITIALIZATION TESTS ==========

    #[Test]
    public function it_initializes_form_data_from_gym_and_user(): void
    {
        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('data.gym_name', 'FitZone Gym');
        $setupPage->assertSet('data.owner_email', 'john@example.com');
    }

    // ========== REFRESH TESTS ==========

    #[Test]
    public function it_refreshes_data_and_recalculates_state(): void
    {
        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'onboarding');

        // Create account in the background
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+14155551234',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'friendly_name' => '(415) 555-1234',
        ]);

        // Refresh should pick up the new account
        $setupPage->call('refresh');
        $setupPage->assertSet('viewState', 'active');
    }

    // ========== CONNECT EXISTING WABA TESTS ==========

    #[Test]
    public function it_shows_waba_connection_method_options_on_step_2(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 2);
        $setupPage->assertSee('How would you like to set up your WABA?');
        $setupPage->assertSee('Connect Existing WABA');
        $setupPage->assertSee('Create New WABA');
    }

    #[Test]
    public function it_connects_existing_waba_successfully(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('connectManualWaba');
        $expectation->once();
        $expectation->with($this->gym->id, 'WABA999', '+15551234567', null);
        $expectation->andReturn([
            'success' => true,
            'waba_id' => 'WABA999',
            'sender_sid' => 'SNtest123',
        ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', 'WABA999');
        $component->set('data.manual_phone_number', '+15551234567');

        $component->call('connectExistingWaba');
        $component->assertSet('wabaCreated', true);
        $component->assertSet('setupStep', 3);
        $component->assertNotified('WABA Connected');
    }

    #[Test]
    public function it_connects_existing_waba_with_access_token(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('connectManualWaba');
        $expectation->once();
        $expectation->with($this->gym->id, 'WABA999', '+15551234567', 'EAAtoken123');
        $expectation->andReturn([
            'success' => true,
            'waba_id' => 'WABA999',
            'sender_sid' => 'SNtest123',
        ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', 'WABA999');
        $component->set('data.manual_phone_number', '+15551234567');
        $component->set('data.meta_access_token', 'EAAtoken123');

        $component->call('connectExistingWaba');
        $component->assertSet('wabaCreated', true);
        $component->assertSet('setupStep', 3);
        $component->assertNotified('WABA Connected');
    }

    #[Test]
    public function it_shows_error_when_existing_waba_connection_fails(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('connectManualWaba');
        $expectation->once();
        $expectation->andReturn([
            'success' => false,
            'error' => 'Invalid WABA ID',
        ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', 'INVALID');
        $component->set('data.manual_phone_number', '+15551234567');

        $component->call('connectExistingWaba')
            ->assertNotified('Connection Failed');
    }

    #[Test]
    public function it_shows_error_when_waba_id_missing_on_connect(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', '');
        $component->set('data.manual_phone_number', '+15551234567');

        $component->call('connectExistingWaba')
            ->assertNotified('Missing Information');
    }

    #[Test]
    public function it_shows_error_when_phone_missing_on_connect(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', 'WABA999');
        $component->set('data.manual_phone_number', '');

        $component->call('connectExistingWaba')
            ->assertNotified('Missing Information');
    }

    #[Test]
    public function it_handles_exception_during_existing_waba_connection(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => null,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('connectManualWaba');
        $expectation->once();
        $expectation->andThrow(new Exception('Network timeout'));

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class);

        $component->set('data.waba_connection_method', 'connect_existing');
        $component->set('data.waba_id', 'WABA999');
        $component->set('data.manual_phone_number', '+15551234567');

        $component->call('connectExistingWaba')
            ->assertNotified('Connection Failed');
    }

    #[Test]
    public function it_still_creates_new_waba_successfully_after_integration(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('createWaba');
        $expectation->once();
        $expectation->andReturn(['success' => true]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->call('createWaba');
        $setupPage->assertSet('setupStep', 3);
        $setupPage->assertNotified('WABA Created');
    }

    #[Test]
    public function it_shows_phone_number_source_radio_on_step_3(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertFormFieldExists('phone_number_source');
        $setupPage->set('data.phone_number_source', 'use_own_number');
        $setupPage->assertSet('data.phone_number_source', 'use_own_number');
        $setupPage->set('data.phone_number_source', 'purchase_twilio');
        $setupPage->assertSet('data.phone_number_source', 'purchase_twilio');
    }

    #[Test]
    public function it_registers_own_phone_number_and_triggers_otp(): void
    {
        WhatsAppAccount::query()->create([
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
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('registerOwnNumber');
        $expectation->once();
        $expectation->with($this->gym->id, '+919876543210');
        $expectation->andReturn([
            'success' => true,
            'sender_sid' => 'SNtest123',
            'status' => 'pending_verification',
        ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->set('data.phone_number_source', 'use_own_number');
        $setupPage->set('data.own_phone_number', '+919876543210');
        $setupPage->call('registerOwnPhoneNumber');
        $setupPage->assertSet('ownNumberRegistered', true);
        $setupPage->assertSet('senderSid', 'SNtest123');
        $setupPage->assertSet('numberPurchased', false);
        $setupPage->assertNotified('Verification Code Sent');
    }

    #[Test]
    public function it_handles_own_number_registration_failure(): void
    {
        WhatsAppAccount::query()->create([
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
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('registerOwnNumber');
        $expectation->once();
        $expectation->andReturn([
            'success' => false,
            'error' => 'Number already registered with WhatsApp',
        ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->set('data.phone_number_source', 'use_own_number');
        $setupPage->set('data.own_phone_number', '+919876543210');
        $setupPage->call('registerOwnPhoneNumber');
        $setupPage->assertSet('ownNumberRegistered', false);
        $setupPage->assertNotified('Registration Failed');
    }

    #[Test]
    public function it_handles_exception_during_own_number_registration(): void
    {
        WhatsAppAccount::query()->create([
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
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('registerOwnNumber');
        $expectation->once();
        $expectation->andThrow(new Exception('Twilio API timeout'));

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->set('data.phone_number_source', 'use_own_number');
        $setupPage->set('data.own_phone_number', '+919876543210');
        $setupPage->call('registerOwnPhoneNumber');
        $setupPage->assertSet('ownNumberRegistered', false);
        $setupPage->assertNotified('Registration Failed');
    }

    #[Test]
    public function it_warns_when_registering_own_number_without_input(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->set('data.phone_number_source', 'use_own_number');
        $setupPage->call('registerOwnPhoneNumber');
        $setupPage->assertNotified('Phone Number Required');
        $setupPage->assertSet('ownNumberRegistered', false);
        $setupPage->assertSet('numberPurchased', false);
    }

    // ========== OTP VERIFICATION TESTS ==========

    #[Test]
    public function it_verifies_own_phone_number_otp_successfully(): void
    {
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+919876543210',
            'phone_number_sid' => 'SNtest123',
            'country_code' => 'IN',
            'status' => 'purchased',
        ]);

        $mock = Mockery::mock(TwilioAccountManager::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('verifyOwnNumber');
        $expectation->once();
        $expectation->with($this->gym->id, 'SNtest123', '123456');
        $expectation->andReturn([
            'success' => true,
            'message' => 'Phone number verified successfully.',
        ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->set('senderSid', 'SNtest123');
        $setupPage->set('ownNumberRegistered', true);
        $setupPage->set('data.otp_code', '123456');
        $setupPage->call('verifyOwnPhoneNumber');
        $setupPage->assertSet('numberPurchased', true);
        $setupPage->assertSet('setupStep', 4);
        $setupPage->assertNotified('Number Verified');
    }

    #[Test]
    public function it_handles_otp_verification_failure(): void
    {
        WhatsAppAccount::query()->create([
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
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('verifyOwnNumber');
        $expectation->once();
        $expectation->andReturn([
            'success' => false,
            'error' => 'Invalid verification code',
        ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->set('senderSid', 'SNtest123');
        $setupPage->set('ownNumberRegistered', true);
        $setupPage->set('data.otp_code', '000000');
        $setupPage->call('verifyOwnPhoneNumber');
        $setupPage->assertSet('numberPurchased', false);
        $setupPage->assertNotified('Verification Failed');
    }

    #[Test]
    public function it_warns_when_verifying_without_otp_code(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'waba_id' => 'WABA123',
            'business_name' => 'FitZone',
            'business_email' => 'fit@zone.com',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->set('senderSid', 'SNtest123');
        $setupPage->set('ownNumberRegistered', true);
        $setupPage->call('verifyOwnPhoneNumber');
        $setupPage->assertNotified('Code Required');
        $setupPage->assertSet('numberPurchased', false);
    }

    // ========== RESEND OTP TESTS ==========

    #[Test]
    public function it_resends_otp_successfully(): void
    {
        WhatsAppAccount::query()->create([
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
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('resendOwnNumberOtp');
        $expectation->once();
        $expectation->with($this->gym->id, 'SNtest123');
        $expectation->andReturn([
            'success' => true,
            'message' => 'Verification code has been resent.',
        ]);

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->set('senderSid', 'SNtest123');
        $setupPage->set('ownNumberRegistered', true);
        $setupPage->call('resendOwnPhoneOtp');
        $setupPage->assertNotified('Code Resent');
    }

    #[Test]
    public function it_handles_resend_otp_failure(): void
    {
        WhatsAppAccount::query()->create([
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
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('resendOwnNumberOtp');
        $expectation->once();
        $expectation->andThrow(new Exception('Rate limit exceeded'));

        $this->app->singleton(TwilioAccountManager::class, fn () => $mock);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->set('senderSid', 'SNtest123');
        $setupPage->set('ownNumberRegistered', true);
        $setupPage->call('resendOwnPhoneOtp');
        $setupPage->assertNotified('Resend Failed');
    }

    #[Test]
    public function it_allows_access_to_setup_step_when_waba_account_exists_and_setup_step_is_requested(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        request()->merge(['setup_step' => '2']);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 2);
    }

    #[Test]
    public function it_gracefully_lands_on_onboarding_when_setup_step_requested_without_an_account(): void
    {
        // A ?setup_step=N request without an existing account must NOT 403.
        // A hard 403 on the post-embedded-signup redirect broke navigation
        // and produced a blank wizard on refresh. It should fall back to onboarding.
        request()->merge(['setup_step' => '2']);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'onboarding');
        $setupPage->assertSet('setupStep', 1);
    }

    #[Test]
    public function it_allows_access_to_setup_step_3_for_twilio_when_account_sid_is_filled(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        request()->merge(['setup_step' => '3']);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 3);
    }

    #[Test]
    public function it_ignores_the_wizard_step_query_param_to_avoid_collisions_with_persist_step_in_query_string(): void
    {
        // The Filament Wizard owns the `step` query param via persistStepInQueryString().
        // The page must NOT treat a bare `step` param as a landing signal, otherwise
        // Livewire serializes `step=undefined` and renders a blank wizard.
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'waba_id' => 'WABA123',
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        request()->merge(['step' => 'undefined']);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        $setupPage->assertSet('viewState', 'setup');
        $setupPage->assertSet('setupStep', 2);
    }

    #[Test]
    public function it_rejects_expired_oauth_state(): void
    {
        $session = WabaSetupSession::createForGym($this->gym->id);
        $session->update(['expires_at' => now()->subHour()]);

        $response = $this->get(route('whatsapp.embedded-signup.callback', [
            'code' => 'test_code',
            'state' => $session->session_id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Invalid OAuth state');
    }

    #[Test]
    public function it_rejects_tenant_mismatch_in_oauth_state(): void
    {
        $otherGym = Gym::factory()->create();
        $session = WabaSetupSession::createForGym($otherGym->id);

        $this->session([
            'embedded_signup_tenant_id' => $this->gym->id,
            'embedded_signup_tenant_slug' => $this->gym->slug,
        ]);

        $response = $this->get(route('whatsapp.embedded-signup.callback', [
            'code' => 'test_code',
            'state' => $session->session_id,
        ]));

        $response->assertSessionHas('error', 'Invalid OAuth state');
    }
}
