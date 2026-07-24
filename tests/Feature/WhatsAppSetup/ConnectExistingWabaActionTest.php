<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsAppSetup;

use App\Filament\Admin\Pages\WhatsAppSetup;
use App\Filament\Admin\WhatsAppSetup\Actions\ConnectExistingWabaAction;
use App\Jobs\ImportWhatsAppTemplatesJob;
use App\Models\Gym;
use App\Models\User;
use App\Providers\MetaWhatsAppProvider;
use Exception;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery;
use Mockery\Expectation;
use PHPUnit\Framework\Attributes\Test;
use Tests\WhatsAppSetupTestCase;
use Throwable;

class ConnectExistingWabaActionTest extends WhatsAppSetupTestCase
{
    protected User $user;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsGymOwner();
        $tenant = Filament::getTenant();
        assert($tenant instanceof Gym);
        $this->gym = $tenant;
    }

    protected function tearDown(): void
    {
        try {
            Filament::setTenant(null);
        } catch (Throwable $_) {
            // ignore
        }

        RateLimiter::clear("whatsapp:connect_waba:{$this->gym->id}");

        parent::tearDown();
    }

    #[Test]
    public function it_throws_validation_exception_when_data_is_missing_or_invalid(): void
    {
        $this->expectException(ValidationException::class);

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        /** @var WhatsAppSetup $pageInstance */
        $pageInstance = $setupPage->instance();
        $pageInstance->data = [
            'manual_phone_number' => 'invalid-phone',
            'waba_id' => '123',
            'waba_phone_number_id' => 'abc',
            'meta_access_token' => 'short',
        ];

        $action = new ConnectExistingWabaAction($pageInstance);
        $action();
    }

    #[Test]
    public function it_throws_exception_when_rate_limiter_exceeded(): void
    {
        $rateKey = "whatsapp:connect_waba:{$this->gym->id}";
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($rateKey, 600);
        }

        /** @var Testable $setupPage */
        $setupPage = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);
        /** @var WhatsAppSetup $pageInstance */
        $pageInstance = $setupPage->instance();
        $pageInstance->data = [
            'manual_phone_number' => '+918469290865',
            'waba_id' => '1057724660050086',
            'waba_phone_number_id' => '1163624323508414',
            'meta_access_token' => 'EAABTESTTOKEN123456',
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Too many connection attempts.');

        $action = new ConnectExistingWabaAction($pageInstance);
        $action();
    }

    #[Test]
    public function it_sends_notification_when_provider_does_not_implement_waba_setup_interface(): void
    {
        /** @var WhatsAppSetup&Mockery\MockInterface $pageMock */
        $pageMock = Mockery::mock(WhatsAppSetup::class)->makePartial();
        $pageMock->data = [
            'manual_phone_number' => '+918469290865',
            'waba_id' => '1057724660050086',
            'waba_phone_number_id' => '1163624323508414',
            'meta_access_token' => 'EAABTESTTOKEN123456',
        ];

        /** @var Expectation $expectation */
        $expectation = $pageMock->shouldReceive('getGymId');
        $expectation->andReturn($this->gym->id);

        /** @var Expectation $expectationProvider */
        $expectationProvider = $pageMock->shouldReceive('getProvider');
        $expectationProvider->andReturn(null);

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);

        $action = new ConnectExistingWabaAction($pageMock);
        $action();

        $component->assertNotified('Connection Failed');
    }

    #[Test]
    public function it_sends_notification_when_connect_manual_waba_returns_failure(): void
    {
        /** @var MetaWhatsAppProvider&Mockery\MockInterface $providerMock */
        $providerMock = Mockery::mock(MetaWhatsAppProvider::class);

        /** @var Expectation $expectation */
        $expectation = $providerMock->shouldReceive('connectManualWaba');
        $expectation->once();
        $expectation->andReturn([
            'success' => false,
            'error' => 'Invalid WABA ID provided to Meta',
        ]);

        /** @var WhatsAppSetup&Mockery\MockInterface $pageMock */
        $pageMock = Mockery::mock(WhatsAppSetup::class)->makePartial();
        $pageMock->data = [
            'manual_phone_number' => '+918469290865',
            'waba_id' => '1057724660050086',
            'waba_phone_number_id' => '1163624323508414',
            'meta_access_token' => 'EAABTESTTOKEN123456',
        ];

        /** @var Expectation $expectationGymId */
        $expectationGymId = $pageMock->shouldReceive('getGymId');
        $expectationGymId->andReturn($this->gym->id);

        /** @var Expectation $expectationProvider */
        $expectationProvider = $pageMock->shouldReceive('getProvider');
        $expectationProvider->andReturn($providerMock);

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);

        $action = new ConnectExistingWabaAction($pageMock);
        $action();

        $component->assertNotified('Connection Failed');
    }

    #[Test]
    public function it_sends_notification_when_connect_manual_waba_returns_failure_without_error_string(): void
    {
        /** @var MetaWhatsAppProvider&Mockery\MockInterface $providerMock */
        $providerMock = Mockery::mock(MetaWhatsAppProvider::class);

        /** @var Expectation $expectation */
        $expectation = $providerMock->shouldReceive('connectManualWaba');
        $expectation->once();
        $expectation->andReturn([
            'success' => false,
        ]);

        /** @var WhatsAppSetup&Mockery\MockInterface $pageMock */
        $pageMock = Mockery::mock(WhatsAppSetup::class)->makePartial();
        $pageMock->data = [
            'manual_phone_number' => '+918469290865',
            'waba_id' => '1057724660050086',
            'waba_phone_number_id' => '1163624323508414',
            'meta_access_token' => 'EAABTESTTOKEN123456',
        ];

        /** @var Expectation $expectationGymId */
        $expectationGymId = $pageMock->shouldReceive('getGymId');
        $expectationGymId->andReturn($this->gym->id);

        /** @var Expectation $expectationProvider */
        $expectationProvider = $pageMock->shouldReceive('getProvider');
        $expectationProvider->andReturn($providerMock);

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);

        $action = new ConnectExistingWabaAction($pageMock);
        $action();

        $component->assertNotified('Connection Failed');
    }

    #[Test]
    public function it_handles_unexpected_exception_and_logs_error(): void
    {
        /** @var MetaWhatsAppProvider&Mockery\MockInterface $providerMock */
        $providerMock = Mockery::mock(MetaWhatsAppProvider::class);

        /** @var Expectation $expectation */
        $expectation = $providerMock->shouldReceive('connectManualWaba');
        $expectation->once();
        $expectation->andThrow(new Exception('Meta API timeout error'));

        /** @var WhatsAppSetup&Mockery\MockInterface $pageMock */
        $pageMock = Mockery::mock(WhatsAppSetup::class)->makePartial();
        $pageMock->data = [
            'manual_phone_number' => '+918469290865',
            'waba_id' => '1057724660050086',
            'waba_phone_number_id' => '1163624323508414',
            'meta_access_token' => 'EAABTESTTOKEN123456',
        ];

        /** @var Expectation $expectationGymId */
        $expectationGymId = $pageMock->shouldReceive('getGymId');
        $expectationGymId->andReturn($this->gym->id);

        /** @var Expectation $expectationProvider */
        $expectationProvider = $pageMock->shouldReceive('getProvider');
        $expectationProvider->andReturn($providerMock);

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);

        $action = new ConnectExistingWabaAction($pageMock);
        $action();

        $component->assertNotified('Connection Failed');
    }

    #[Test]
    public function it_connects_waba_successfully_dispatches_template_job_and_refreshes_page(): void
    {
        Bus::fake();

        $gymId = $this->gym->id;
        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN123456';
        $phoneNumber = '+918469290865';

        /** @var MetaWhatsAppProvider&Mockery\MockInterface $providerMock */
        $providerMock = Mockery::mock(MetaWhatsAppProvider::class);

        /** @var Expectation $expectation */
        $expectation = $providerMock->shouldReceive('connectManualWaba');
        $expectation->once();
        $expectation->andReturn([
            'success' => true,
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
        ]);

        /** @var WhatsAppSetup&Mockery\MockInterface $pageMock */
        $pageMock = Mockery::mock(WhatsAppSetup::class)->makePartial();
        $pageMock->data = [
            'manual_phone_number' => $phoneNumber,
            'waba_id' => $wabaId,
            'waba_phone_number_id' => $phoneNumberId,
            'meta_access_token' => $accessToken,
            'business_name' => 'FitZone Gym',
            'business_email' => 'fitzone@example.com',
            'business_description' => 'Best Fitness Center',
        ];

        /** @var Expectation $expectationGymId */
        $expectationGymId = $pageMock->shouldReceive('getGymId');
        $expectationGymId->andReturn($gymId);

        /** @var Expectation $expectationProvider */
        $expectationProvider = $pageMock->shouldReceive('getProvider');
        $expectationProvider->andReturn($providerMock);

        /** @var Expectation $expectationRefresh */
        $expectationRefresh = $pageMock->shouldReceive('refresh');
        $expectationRefresh->once();

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);

        $action = new ConnectExistingWabaAction($pageMock);
        $action();

        Bus::assertDispatched(ImportWhatsAppTemplatesJob::class);

        $component->assertNotified('WABA Connected');
    }

    #[Test]
    public function it_falls_back_to_user_name_and_email_when_business_info_omitted(): void
    {
        Bus::fake();

        $gymId = $this->gym->id;
        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN123456';
        $phoneNumber = '+918469290865';

        /** @var MetaWhatsAppProvider&Mockery\MockInterface $providerMock */
        $providerMock = Mockery::mock(MetaWhatsAppProvider::class);

        /** @var Expectation $expectation */
        $expectation = $providerMock->shouldReceive('connectManualWaba');
        $expectation->once();
        $expectation->andReturn([
            'success' => true,
        ]);

        /** @var WhatsAppSetup&Mockery\MockInterface $pageMock */
        $pageMock = Mockery::mock(WhatsAppSetup::class)->makePartial();
        $pageMock->data = [
            'manual_phone_number' => $phoneNumber,
            'waba_id' => $wabaId,
            'waba_phone_number_id' => $phoneNumberId,
            'meta_access_token' => $accessToken,
        ];

        /** @var Expectation $expectationGymId */
        $expectationGymId = $pageMock->shouldReceive('getGymId');
        $expectationGymId->andReturn($gymId);

        /** @var Expectation $expectationProvider */
        $expectationProvider = $pageMock->shouldReceive('getProvider');
        $expectationProvider->andReturn($providerMock);

        /** @var Expectation $expectationRefresh */
        $expectationRefresh = $pageMock->shouldReceive('refresh');
        $expectationRefresh->once();

        /** @var Testable $component */
        $component = Livewire::actingAs($this->user)->test(WhatsAppSetup::class);

        $action = new ConnectExistingWabaAction($pageMock);
        $action();

        Bus::assertDispatched(ImportWhatsAppTemplatesJob::class);
        $component->assertNotified('WABA Connected');
    }
}
