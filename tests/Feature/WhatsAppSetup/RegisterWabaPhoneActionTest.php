<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsAppSetup;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Filament\Admin\WhatsAppSetup\Actions\RegisterWabaPhoneAction;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Providers\MetaWhatsAppProvider;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Expectation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterWabaPhoneActionTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the gym_owner role (global, no gym_id) for test usage
        Role::query()->firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
        ]);
    }

    #[Test]
    public function it_successfully_registers_and_syncs_waba_phone_number_via_action(): void
    {
        $gym = Gym::factory()->create(['slug' => 'test-gym']);
        $admin = User::factory()->create();
        $admin->assignRole(DefaultRole::GYM_OWNER->value);
        $admin->gyms()->attach($gym->id);

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'waba_id' => '1057724660050086',
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::PENDING,
        ]);

        $this->actingAs($admin);

        /** @var MetaWhatsAppProvider&Mockery\MockInterface $providerMock */
        $providerMock = Mockery::mock(MetaWhatsAppProvider::class);

        /** @var Expectation $providerExpectation */
        $providerExpectation = $providerMock->shouldReceive('syncWabaPhoneNumbers');
        $providerExpectation
            ->with($gym->id, '1057724660050086', 'EAABTESTTOKEN', '654321')
            ->once()
            ->andReturn([
                'success' => true,
                'synced_count' => 1,
                'synced' => [
                    [
                        'phone_number_id' => '1163624323508414',
                        'phone_number' => '+918469290865',
                        'status' => 'CONNECTED',
                    ],
                ],
            ]);

        /** @var WhatsAppSetup&Mockery\MockInterface $pageMock */
        $pageMock = Mockery::mock(WhatsAppSetup::class)->makePartial();
        $pageMock->whatsappAccount = $account;

        /** @var Expectation $gymIdExpectation */
        $gymIdExpectation = $pageMock->shouldReceive('getGymId');
        $gymIdExpectation->andReturn($gym->id);

        /** @var Expectation $tenantSlugExpectation */
        $tenantSlugExpectation = $pageMock->shouldReceive('getTenantSlug');
        $tenantSlugExpectation->andReturn('test-gym');

        /** @var Expectation $providerPageExpectation */
        $providerPageExpectation = $pageMock->shouldReceive('getProvider');
        $providerPageExpectation->andReturn($providerMock);

        /** @var Expectation $redirectExpectation */
        $redirectExpectation = $pageMock->shouldReceive('redirect');
        $redirectExpectation->once();

        $action = new RegisterWabaPhoneAction($pageMock);
        $action('654321');

        Notification::assertNotified('Phone Number Registered & Synced');
    }

    #[Test]
    public function it_triggers_rate_limiter_after_max_attempts(): void
    {
        $gym = Gym::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole(DefaultRole::GYM_OWNER->value);
        $admin->gyms()->attach($gym->id);

        $this->actingAs($admin);

        $rateLimiterKey = "whatsapp:register_waba_phone:{$gym->id}";
        RateLimiter::hit($rateLimiterKey, 300);
        RateLimiter::hit($rateLimiterKey, 300);
        RateLimiter::hit($rateLimiterKey, 300);
        RateLimiter::hit($rateLimiterKey, 300);
        RateLimiter::hit($rateLimiterKey, 300);

        /** @var WhatsAppSetup&Mockery\MockInterface $pageMock */
        $pageMock = Mockery::mock(WhatsAppSetup::class)->makePartial();

        /** @var Expectation $gymIdExpectation */
        $gymIdExpectation = $pageMock->shouldReceive('getGymId');
        $gymIdExpectation->andReturn($gym->id);

        $action = new RegisterWabaPhoneAction($pageMock);
        $action('123456');

        Notification::assertNotified('Too Many Registration Attempts');
    }

    #[Test]
    public function it_handles_provider_registration_failure_gracefully(): void
    {
        $gym = Gym::factory()->create(['slug' => 'test-gym']);
        $admin = User::factory()->create();
        $admin->assignRole(DefaultRole::GYM_OWNER->value);
        $admin->gyms()->attach($gym->id);

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'waba_id' => '1057724660050086',
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::PENDING,
        ]);

        $this->actingAs($admin);

        /** @var MetaWhatsAppProvider&Mockery\MockInterface $providerMock */
        $providerMock = Mockery::mock(MetaWhatsAppProvider::class);

        /** @var Expectation $providerExpectation */
        $providerExpectation = $providerMock->shouldReceive('syncWabaPhoneNumbers');
        $providerExpectation
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Meta 2-Step Verification PIN mismatch.',
            ]);

        /** @var WhatsAppSetup&Mockery\MockInterface $pageMock */
        $pageMock = Mockery::mock(WhatsAppSetup::class)->makePartial();
        $pageMock->whatsappAccount = $account;

        /** @var Expectation $gymIdExpectation */
        $gymIdExpectation = $pageMock->shouldReceive('getGymId');
        $gymIdExpectation->andReturn($gym->id);

        /** @var Expectation $providerPageExpectation */
        $providerPageExpectation = $pageMock->shouldReceive('getProvider');
        $providerPageExpectation->andReturn($providerMock);

        $action = new RegisterWabaPhoneAction($pageMock);
        $action('000000');

        Notification::assertNotified('Registration Failed');
    }
}
