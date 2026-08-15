<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Jobs\SyncWabaStatus;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Providers\MetaWhatsAppProvider;
use App\Providers\WhatsAppProviderFactory;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Expectation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncWabaStatusTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    #[Test]
    public function it_successfully_syncs_waba_status_and_triggers_phone_number_sync(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;
        $wabaId = '1057724660050086';
        $token = 'EAABTESTTOKEN';

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'meta_access_token' => $token,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::PENDING,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        /** @var MetaWhatsAppProvider&Mockery\MockInterface $providerMock */
        $providerMock = Mockery::mock(MetaWhatsAppProvider::class);

        /** @var Expectation $expectation1 */
        $expectation1 = $providerMock->shouldReceive('getWabaDetails');
        $expectation1
            ->with($wabaId, $token)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $wabaId,
                    'name' => 'FitZone WABA',
                    'account_review_status' => 'APPROVED',
                    'business_verification_status' => 'VERIFIED',
                ],
            ]);

        /** @var Expectation $expectation2 */
        $expectation2 = $providerMock->shouldReceive('syncWabaPhoneNumbers');
        $expectation2
            ->with($gymId, $wabaId, $token)
            ->once()
            ->andReturn(['success' => true]);

        WhatsAppProviderFactory::setTestProvider($gymId, $providerMock);

        $job = new SyncWabaStatus($account->id);
        $job->handle();

        $account->refresh();
        $this->assertEquals(WhatsAppAccountStatusEnum::ACTIVE, $account->status);
        $this->assertEquals(WhatsAppAccountVerificationStatusEnum::VERIFIED, $account->verification_status);
        $this->assertEquals('FitZone WABA', $account->waba_name);
        $this->assertNotNull($account->waba_status_synced_at);

        WhatsAppProviderFactory::clearTestProviders();
    }

    #[Test]
    public function it_skips_execution_when_account_data_is_invalid(): void
    {
        $gym = Gym::factory()->create();
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'waba_id' => null,
            'meta_access_token' => null,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
        ]);

        $job = new SyncWabaStatus($account->id);
        $job->handle();

        $account->refresh();
        $this->assertNull($account->waba_status_synced_at);
    }

    #[Test]
    public function it_throws_exception_when_meta_api_fails(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;
        $wabaId = '1057724660050086';
        $token = 'EAABTESTTOKEN';

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'meta_access_token' => $token,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::PENDING,
        ]);

        /** @var MetaWhatsAppProvider&Mockery\MockInterface $providerMock */
        $providerMock = Mockery::mock(MetaWhatsAppProvider::class);

        /** @var Expectation $expectation */
        $expectation = $providerMock->shouldReceive('getWabaDetails');
        $expectation
            ->with($wabaId, $token)
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Meta API Rate limit exceeded',
            ]);

        WhatsAppProviderFactory::setTestProvider($gymId, $providerMock);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Meta API Rate limit exceeded');

        try {
            $job = new SyncWabaStatus($account->id);
            $job->handle();
        } finally {
            WhatsAppProviderFactory::clearTestProviders();
        }
    }
}
