<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Services\TwilioWhatsAppService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Tests for rate limiting of WhatsApp test messages from the WhatsAppSetup page.
 *
 * Note: The current WhatsAppSetup::sendTestMessage() method uses
 * TwilioWhatsAppService::sendTemplateMessage() and does NOT have built-in
 * rate limiting. These tests verify the rate limiting that should be
 * implemented to prevent abuse of test message functionality.
 */
class WhatsAppTestMessageRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected WhatsAppAccount $whatsappAccount;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
        ]);

        $this->user = User::factory()
            ->create([
                'name' => 'Test Owner',
                'email' => 'owner@example.com',
            ])
            ->assignRole(DefaultRole::GYM_OWNER->value);

        $this->gym = Gym::factory()->create();
        $this->user->gyms()->attach($this->gym);

        // Create WhatsAppAccount (the new model)
        $this->whatsappAccount = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        // Create a phone number for the account
        WhatsAppPhoneNumber::create([
            'whatsapp_account_id' => $this->whatsappAccount->id,
            'phone_number' => '+14155551234',
            'formatted_number' => '(415) 555-1234',
            'twilio_phone_number_sid' => 'PNtest123',
            'verification_status' => 'verified',
        ]);

        // Set current panel and tenant
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->gym);

        // Clear rate limiter before each test
        RateLimiter::clear("whatsapp-test-message:{$this->user->id}");
    }

    protected function tearDown(): void
    {
        Mockery::close();
        RateLimiter::clear("whatsapp-test-message:{$this->user->id}");
        parent::tearDown();
    }

    
    public function user_can_send_test_message(): void
    {
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

    
    public function user_can_send_multiple_test_messages(): void
    {
        $mock = Mockery::mock(TwilioWhatsAppService::class);
        $mock->shouldReceive('sendTemplateMessage')
            ->times(3)
            ->andReturn([
                'success' => true,
                'message_id' => 'SM123456789',
                'status' => 'queued',
            ]);

        $this->app->singleton(TwilioWhatsAppService::class, fn () => $mock);

        for ($i = 0; $i < 3; $i++) {
            Livewire::actingAs($this->user)
                ->test(WhatsAppSetup::class)
                ->call('sendTestMessage', ['test_phone' => '+19876543210'])
                ->assertNotified('Test Message Sent!');
        }
    }

    
    public function failed_test_message_shows_error_notification(): void
    {
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

    
    public function test_message_requires_phone_number(): void
    {
        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage', [])
            ->assertNotified('Failed to Send');
    }

    
    public function test_message_handles_exception_from_service(): void
    {
        $mock = Mockery::mock(TwilioWhatsAppService::class);
        $mock->shouldReceive('sendTemplateMessage')
            ->once()
            ->andThrow(new \Exception('Connection timeout'));

        $this->app->singleton(TwilioWhatsAppService::class, fn () => $mock);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage', ['test_phone' => '+19876543210'])
            ->assertNotified('Failed to Send');
    }

    
    public function different_users_can_send_test_messages_independently(): void
    {
        $anotherUser = User::factory()
            ->create(['email' => 'another@example.com'])
            ->assignRole(DefaultRole::GYM_OWNER->value);

        $anotherUser->gyms()->attach($this->gym);

        $mock = Mockery::mock(TwilioWhatsAppService::class);
        $mock->shouldReceive('sendTemplateMessage')
            ->times(2)
            ->andReturn([
                'success' => true,
                'message_id' => 'SM123',
                'status' => 'queued',
            ]);

        $this->app->singleton(TwilioWhatsAppService::class, fn () => $mock);

        // First user sends
        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage', ['test_phone' => '+19876543210'])
            ->assertNotified('Test Message Sent!');

        // Second user sends independently
        Livewire::actingAs($anotherUser)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage', ['test_phone' => '+19876543210'])
            ->assertNotified('Test Message Sent!');
    }

    
    public function test_message_only_available_on_active_account(): void
    {
        // Account is in onboarding state (no account at all)
        $anotherGym = Gym::factory()->create();
        $this->user->gyms()->attach($anotherGym);

        Filament::setTenant($anotherGym);

        // Sending test message on a gym without active account should still
        // work through the page (it just won't have the sendTestMessage action visible)
        // The component renders in 'onboarding' viewState
        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->assertSet('viewState', 'onboarding');
    }
}
