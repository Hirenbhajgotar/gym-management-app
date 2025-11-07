<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Models\Gym;
use App\Models\NotificationSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\TwilioAccountManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class WhatsAppTestMessageRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected NotificationSetting $settings;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
        ]);

        // Create test user and gym
        $this->user = User::factory()
            ->create([
                'name' => 'Test Owner',
                'email' => 'owner@example.com',
            ])
            ->assignRole(DefaultRole::GYM_OWNER->value);

        $this->gym = Gym::factory()->create();
        $this->user->gyms()->attach($this->gym);

        // Create notification settings
        $this->settings = NotificationSetting::create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
            'whatsapp_business_number' => '+1234567890',
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

    public function test_user_can_send_first_test_message(): void
    {
        $accountManager = Mockery::mock(TwilioAccountManager::class);
        $accountManager
            ->shouldReceive('sendTestWhatsAppMessage')
            ->once()
            ->andReturn([
                'success' => true,
                'message_id' => 'SM123456789',
            ]);

        $this->app->instance(TwilioAccountManager::class, $accountManager);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage')
            ->assertHasNoErrors();

        $rateLimitKey = "whatsapp-test-message:{$this->user->id}";
        $this->assertEquals(1, RateLimiter::attempts($rateLimitKey));
    }

    public function test_user_can_send_up_to_three_test_messages(): void
    {
        $accountManager = Mockery::mock(TwilioAccountManager::class);
        $accountManager
            ->shouldReceive('sendTestWhatsAppMessage')
            ->times(3)
            ->andReturn([
                'success' => true,
                'message_id' => 'SM123456789',
            ]);

        $this->app->instance(TwilioAccountManager::class, $accountManager);

        // Send 3 test messages
        for ($i = 0; $i < 3; $i++) {
            Livewire::actingAs($this->user)
                ->test(WhatsAppSetup::class)
                ->call('sendTestMessage')
                ->assertHasNoErrors();
        }

        $rateLimitKey = "whatsapp-test-message:{$this->user->id}";
        $this->assertEquals(3, RateLimiter::attempts($rateLimitKey));
    }

    public function test_user_cannot_send_more_than_three_test_messages_per_day(): void
    {
        $rateLimitKey = "whatsapp-test-message:{$this->user->id}";

        // Simulate 3 messages already sent
        RateLimiter::hit($rateLimitKey, 86400);
        RateLimiter::hit($rateLimitKey, 86400);
        RateLimiter::hit($rateLimitKey, 86400);

        // Mock should not be called since rate limit is reached
        $accountManager = Mockery::mock(TwilioAccountManager::class);
        $accountManager->shouldNotReceive('sendTestWhatsAppMessage');

        $this->app->instance(TwilioAccountManager::class, $accountManager);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage')
            ->assertNotified(
                fn ($notification) => $notification->getTitle() ===
                    '⚠️ Daily Limit Reached',
            );

        // Should still be 3 attempts
        $this->assertEquals(3, RateLimiter::attempts($rateLimitKey));
    }

    public function test_failed_messages_do_not_count_toward_limit(): void
    {
        $accountManager = Mockery::mock(TwilioAccountManager::class);
        $accountManager
            ->shouldReceive('sendTestWhatsAppMessage')
            ->times(4)
            ->andReturn([
                'success' => false,
                'error' => 'Test error',
            ]);

        $this->app->instance(TwilioAccountManager::class, $accountManager);

        // Send 4 failed messages - none should count toward limit
        for ($i = 0; $i < 4; $i++) {
            Livewire::actingAs($this->user)
                ->test(WhatsAppSetup::class)
                ->call('sendTestMessage')
                ->assertNotified(
                    fn ($notification) => $notification->getTitle() ===
                        '❌ Test Message Failed',
                );
        }

        $rateLimitKey = "whatsapp-test-message:{$this->user->id}";
        $this->assertEquals(0, RateLimiter::attempts($rateLimitKey));
    }

    public function test_rate_limit_is_set_to_24_hours(): void
    {
        $accountManager = Mockery::mock(TwilioAccountManager::class);
        $accountManager
            ->shouldReceive('sendTestWhatsAppMessage')
            ->once()
            ->andReturn([
                'success' => true,
                'message_id' => 'SM123',
            ]);

        $this->app->instance(TwilioAccountManager::class, $accountManager);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage')
            ->assertHasNoErrors();

        $rateLimitKey = "whatsapp-test-message:{$this->user->id}";
        $availableIn = RateLimiter::availableIn($rateLimitKey);

        // Should be approximately 24 hours (86400 seconds)
        $this->assertGreaterThan(86390, $availableIn);
        $this->assertLessThanOrEqual(86400, $availableIn);
    }

    public function test_different_users_have_separate_rate_limits(): void
    {
        // Create another user
        $anotherUser = User::factory()
            ->create([
                'email' => 'another@example.com',
            ])
            ->assignRole(DefaultRole::GYM_OWNER->value);

        $anotherUser->gyms()->attach($this->gym);

        // First user hits rate limit
        $rateLimitKey1 = "whatsapp-test-message:{$this->user->id}";
        RateLimiter::hit($rateLimitKey1, 86400);
        RateLimiter::hit($rateLimitKey1, 86400);
        RateLimiter::hit($rateLimitKey1, 86400);

        $this->assertTrue(RateLimiter::tooManyAttempts($rateLimitKey1, 3));

        // Second user should still be able to send messages
        $rateLimitKey2 = "whatsapp-test-message:{$anotherUser->id}";
        $this->assertFalse(RateLimiter::tooManyAttempts($rateLimitKey2, 3));

        $accountManager = Mockery::mock(TwilioAccountManager::class);
        $accountManager
            ->shouldReceive('sendTestWhatsAppMessage')
            ->once()
            ->andReturn([
                'success' => true,
                'message_id' => 'SM_USER2',
            ]);

        $this->app->instance(TwilioAccountManager::class, $accountManager);

        Livewire::actingAs($anotherUser)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage')
            ->assertHasNoErrors();

        $this->assertEquals(1, RateLimiter::attempts($rateLimitKey2));
    }

    public function test_rate_limiter_clears_after_time_expires(): void
    {
        $rateLimitKey = "whatsapp-test-message:{$this->user->id}";

        // Hit the rate limiter with a short decay time (1 second)
        RateLimiter::hit($rateLimitKey, 1);
        $this->assertEquals(1, RateLimiter::attempts($rateLimitKey));

        // Wait for decay
        sleep(2);

        // Rate limiter should have cleared
        $this->assertEquals(0, RateLimiter::attempts($rateLimitKey));
    }

    public function test_notification_shows_remaining_messages(): void
    {
        $accountManager = Mockery::mock(TwilioAccountManager::class);
        $accountManager
            ->shouldReceive('sendTestWhatsAppMessage')
            ->once()
            ->andReturn([
                'success' => true,
                'message_id' => 'SM123',
            ]);

        $this->app->instance(TwilioAccountManager::class, $accountManager);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage')
            ->assertNotified(
                fn ($notification) => str_contains(
                    $notification->getBody(),
                    'Remaining test messages today: 2',
                ),
            );
    }

    public function test_rate_limit_notification_shows_time_until_reset(): void
    {
        $rateLimitKey = "whatsapp-test-message:{$this->user->id}";

        // Hit rate limit
        RateLimiter::hit($rateLimitKey, 86400);
        RateLimiter::hit($rateLimitKey, 86400);
        RateLimiter::hit($rateLimitKey, 86400);

        $accountManager = Mockery::mock(TwilioAccountManager::class);
        $accountManager->shouldNotReceive('sendTestWhatsAppMessage');

        $this->app->instance(TwilioAccountManager::class, $accountManager);

        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage')
            ->assertNotified(
                fn ($notification) => str_contains(
                    $notification->getBody(),
                    'Please try again in',
                ) &&
                    str_contains(
                        $notification->getBody(),
                        'Daily Limit Reached',
                    ),
            );
    }

    public function test_remaining_count_decreases_with_each_successful_message(): void
    {
        $accountManager = Mockery::mock(TwilioAccountManager::class);
        $accountManager
            ->shouldReceive('sendTestWhatsAppMessage')
            ->times(3)
            ->andReturn([
                'success' => true,
                'message_id' => 'SM123',
            ]);

        $this->app->instance(TwilioAccountManager::class, $accountManager);

        // First message
        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage')
            ->assertNotified(
                fn ($notification) => str_contains(
                    $notification->getBody(),
                    'Remaining test messages today: 2',
                ),
            );

        // Second message
        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage')
            ->assertNotified(
                fn ($notification) => str_contains(
                    $notification->getBody(),
                    'Remaining test messages today: 1',
                ),
            );

        // Third message
        Livewire::actingAs($this->user)
            ->test(WhatsAppSetup::class)
            ->call('sendTestMessage')
            ->assertNotified(
                fn ($notification) => str_contains(
                    $notification->getBody(),
                    'Remaining test messages today: 0',
                ),
            );
    }
}
