<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Services\Meta\MetaApiClient;
use App\Services\Meta\MetaContext;
use App\Services\Meta\MetaWabaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Expectation;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class MetaWabaServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    #[Test]
    public function it_connects_manual_waba_successfully_and_saves_phone_number_without_phone_number_sid(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';
        $phoneNumber = '+918469290865';

        // Mock phone details request
        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $phoneNumberId,
                    'display_phone_number' => '+91 84692 90865',
                    'verified_name' => 'FitZone Gym',
                    'code_verification_status' => 'VERIFIED',
                    'quality_score' => 'GREEN',
                ],
            ]);

        // Mock WABA details request
        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $wabaId,
                    'name' => 'FitZone Gym WABA',
                    'account_review_status' => 'APPROVED',
                    'business_verification_status' => 'VERIFIED',
                    'currency' => 'INR',
                    'timezone' => 'Asia/Kolkata',
                ],
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->connectManualWaba(
            $gymId,
            $wabaId,
            $phoneNumber,
            $accessToken,
            $phoneNumberId,
            [
                'business_name' => 'FitZone Gym',
                'business_email' => 'fitzone@example.com',
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('waba_id', $result);
        $this->assertEquals($wabaId, $result['waba_id'] ?? '');
        $this->assertArrayHasKey('phone_number_id', $result);
        $this->assertEquals($phoneNumberId, $result['phone_number_id'] ?? '');

        $this->assertDatabaseHas('whatsapp_accounts', [
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'status' => WhatsAppAccountStatusEnum::ACTIVE->value,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED->value,
        ]);

        $account = WhatsAppAccount::query()->where('gym_id', $gymId)->first();
        $this->assertNotNull($account);

        $this->assertDatabaseHas('whatsapp_phone_numbers', [
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => '+918469290865',
            'phone_number_sid' => null,
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE->value,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS->value,
        ]);
    }

    #[Test]
    public function it_returns_error_when_phone_api_returns_failure(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Invalid OAuth access token',
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->connectManualWaba(
            $gymId,
            $wabaId,
            '+918469290865',
            $accessToken,
            $phoneNumberId,
        );

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertDatabaseMissing('whatsapp_accounts', [
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
        ]);
    }

    #[Test]
    public function it_returns_error_when_meta_api_throws_exception(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andThrow(new RuntimeException('Connection timed out'));

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->connectManualWaba(
            $gymId,
            $wabaId,
            '+918469290865',
            $accessToken,
            $phoneNumberId,
        );

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertDatabaseMissing('whatsapp_accounts', [
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
        ]);
    }

    #[Test]
    public function it_returns_error_when_required_params_are_missing(): void
    {
        $gymId = 1;
        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->connectManualWaba(
            $gymId,
            '1057724660050086',
            '+918469290865',
        );

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('required', $result['error'] ?? '');
    }

    #[Test]
    public function it_returns_error_when_lock_timeout_occurs_during_connect_manual_waba(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $lock = Cache::lock("meta:manual-waba:{$gymId}", 60);
        $this->assertTrue($lock->get());

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->connectManualWaba(
            $gymId,
            '1057724660050086',
            '+918469290865',
            'EAABTESTTOKEN',
            '1163624323508414'
        );

        $lock->release();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('in progress', $result['error'] ?? '');
    }

    #[Test]
    public function it_returns_error_when_verification_result_is_unverified(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Phone number ID not found in Meta system',
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->connectManualWaba(
            $gymId,
            $wabaId,
            '+918469290865',
            $accessToken,
            $phoneNumberId
        );

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Phone number ID not found', $result['error'] ?? '');
    }

    #[Test]
    public function it_correctly_maps_waba_status_and_verification_status_and_sanitizes_business_info(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $phoneNumberId,
                    'display_phone_number' => '+918469290865',
                    'verified_name' => 'FitZone Gym',
                ],
            ]);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $wabaId,
                    'name' => 'FitZone Gym WABA',
                    'account_review_status' => 'PENDING',
                    'business_verification_status' => 'NOT_VERIFIED',
                ],
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->connectManualWaba(
            $gymId,
            $wabaId,
            '+918469290865',
            $accessToken,
            $phoneNumberId,
            [
                'business_name' => '<b>FitZone Gym</b>',
                'business_email' => 'not-an-email',
                'business_description' => '<script>alert(1)</script>Modern Gym',
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('+918469290865', $result['display_phone_number'] ?? '');

        $this->assertDatabaseHas('whatsapp_accounts', [
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'business_name' => 'FitZone Gym WABA',
            'business_email' => null,
            'business_description' => 'alert(1)Modern Gym',
            'status' => WhatsAppAccountStatusEnum::PENDING->value,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED->value,
        ]);

        $account = WhatsAppAccount::query()->where('gym_id', $gymId)->first();
        $this->assertNotNull($account);

        $this->assertDatabaseHas('whatsapp_phone_numbers', [
            'whatsapp_account_id' => $account->id,
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
        ]);
    }

    #[Test]
    public function it_updates_existing_whatsapp_account_and_phone_number_records_on_reconnect(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $existingAccount = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => 'OLD_WABA',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::PENDING,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $existingAccount->id,
            'waba_phone_number_id' => '1163624323508414',
            'phone_number' => '+911111111111',
            'country_code' => 'IN',
            'friendly_name' => '+911111111111',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $phoneNumberId,
                    'display_phone_number' => '+918469290865',
                ],
            ]);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $wabaId,
                    'name' => 'Updated Gym WABA',
                    'account_review_status' => 'APPROVED',
                    'business_verification_status' => 'VERIFIED',
                ],
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->connectManualWaba(
            $gymId,
            $wabaId,
            '+918469290865',
            $accessToken,
            $phoneNumberId
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(1, WhatsAppAccount::query()->where('gym_id', $gymId)->count());
        $this->assertEquals(1, WhatsAppPhoneNumber::query()->where('whatsapp_account_id', $existingAccount->id)->count());

        $this->assertDatabaseHas('whatsapp_accounts', [
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'waba_name' => 'Updated Gym WABA',
        ]);

        $this->assertDatabaseHas('whatsapp_phone_numbers', [
            'whatsapp_account_id' => $existingAccount->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => '+918469290865',
        ]);
    }

    #[Test]
    public function it_returns_error_and_logs_when_unexpected_db_exception_occurs_during_connect_manual_waba(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $phoneNumberId,
                    'display_phone_number' => '+918469290865',
                ],
            ]);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $wabaId,
                    'name' => 'Gym WABA',
                ],
            ]);

        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new RuntimeException('Database connection failed'));

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->connectManualWaba(
            $gymId,
            $wabaId,
            '+918469290865',
            $accessToken,
            $phoneNumberId
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('An unexpected error occurred while connecting WABA.', $result['error'] ?? '');
    }

    #[Test]
    public function it_completes_embedded_signup_saves_waba_and_phone_number(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $phoneNumberId,
                    'display_phone_number' => '+91 84692 90865',
                ],
            ]);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $wabaId,
                    'name' => 'FitZone Gym WABA',
                    'account_review_status' => 'APPROVED',
                    'business_verification_status' => 'NOT_VERIFIED',
                ],
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->completeEmbeddedSignup($gymId, $wabaId, $accessToken, $phoneNumberId);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('waba_id', $result);
        $this->assertEquals($wabaId, $result['waba_id'] ?? '');

        $this->assertDatabaseHas('whatsapp_accounts', [
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'embedded_signup_completed' => true,
            'status' => WhatsAppAccountStatusEnum::ACTIVE->value,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED->value,
            'provider_type' => WhatsAppProviderTypeEnum::WABA->value,
        ]);

        $account = WhatsAppAccount::query()->where('gym_id', $gymId)->first();
        $this->assertNotNull($account);

        $this->assertDatabaseHas('whatsapp_phone_numbers', [
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => '+918469290865',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE->value,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS->value,
        ]);
    }

    #[Test]
    public function it_completes_embedded_signup_prefers_status_field_over_account_review_status(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $getPhoneExpectation */
        $getPhoneExpectation = $apiMock->shouldReceive('get');
        $getPhoneExpectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $phoneNumberId,
                    'display_phone_number' => '+91 84692 90865',
                ],
            ]);

        /** @var Expectation $getWabaExpectation */
        $getWabaExpectation = $apiMock->shouldReceive('get');
        $getWabaExpectation
            ->with("/{$wabaId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $wabaId,
                    'name' => 'FitZone Gym WABA',
                    'status' => 'ACTIVE',
                    'account_review_status' => 'PENDING',
                    'business_verification_status' => 'VERIFIED',
                ],
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->completeEmbeddedSignup($gymId, $wabaId, $accessToken, $phoneNumberId);

        $this->assertTrue($result['success']);

        // When both `status` and `account_review_status` are present, the
        // `status` field must win (ACTIVE), not the PENDING that
        // account_review_status alone would map to.
        $this->assertDatabaseHas('whatsapp_accounts', [
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'status' => WhatsAppAccountStatusEnum::ACTIVE->value,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED->value,
        ]);
    }

    #[Test]
    public function it_skips_if_embedded_signup_already_completed(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $wabaId = '1057724660050086';
        $accessToken = 'EAABTESTTOKEN';

        WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'embedded_signup_completed' => true,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->completeEmbeddedSignup($gymId, $wabaId, $accessToken);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Embedded signup already completed', $result['message'] ?? '');
    }

    #[Test]
    public function it_creates_phone_number_only_when_phone_number_id_provided(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $wabaId = '1057724660050086';
        $accessToken = 'EAABTESTTOKEN';

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $wabaId,
                    'name' => 'FitZone Gym WABA',
                    'account_review_status' => 'APPROVED',
                    'business_verification_status' => 'NOT_VERIFIED',
                ],
            ]);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}/phone_numbers", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'data' => [],
                ],
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->completeEmbeddedSignup($gymId, $wabaId, $accessToken);

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('whatsapp_accounts', [
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'embedded_signup_completed' => true,
        ]);

        $account = WhatsAppAccount::query()->where('gym_id', $gymId)->first();
        $this->assertNotNull($account);

        $this->assertDatabaseMissing('whatsapp_phone_numbers', [
            'whatsapp_account_id' => $account->id,
        ]);
    }

    // =====================================================
    // verifyWabaConnection Tests
    // =====================================================

    #[Test]
    public function it_verifies_waba_connection_successfully(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $phoneNumberId,
                    'display_phone_number' => '+91 84692 90865',
                    'verified_name' => 'FitZone Gym',
                    'quality_score' => 'GREEN',
                    'status' => 'ACTIVE',
                ],
            ]);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $wabaId,
                    'name' => 'FitZone Gym WABA',
                    'account_review_status' => 'APPROVED',
                    'business_verification_status' => 'VERIFIED',
                    'currency' => 'INR',
                    'timezone' => 'Asia/Kolkata',
                ],
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->verifyWabaConnection($wabaId, $phoneNumberId, $accessToken);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['verified']);
        $this->assertEquals($phoneNumberId, $result['phone_number_id'] ?? null);
        $this->assertEquals($wabaId, $result['waba_id'] ?? null);
        $this->assertEquals('+91 84692 90865', $result['display_phone_number'] ?? null);
        $this->assertEquals('FitZone Gym', $result['verified_name'] ?? null);
        $this->assertEquals(WhatsAppAccountVerificationStatusEnum::VERIFIED->value, $result['business_verification_status'] ?? null);
        $this->assertEquals(WhatsAppPhoneNumberStatusEnum::ACTIVE->value, $result['phone_number_status'] ?? null);
        $this->assertEquals('GREEN', $result['quality_score'] ?? null);
        $this->assertEquals('FitZone Gym WABA', $result['business_name'] ?? null);
        $this->assertEquals(WhatsAppAccountStatusEnum::ACTIVE->value, $result['status'] ?? null);
        $this->assertEquals('INR', $result['currency'] ?? null);
        $this->assertEquals('Asia/Kolkata', $result['timezone'] ?? null);
    }

    #[Test]
    public function it_returns_error_when_phone_api_fails_during_verification(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Invalid OAuth access token',
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->verifyWabaConnection($wabaId, $phoneNumberId, $accessToken);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['verified']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid OAuth access token', $result['error'] ?? '');
    }

    #[Test]
    public function it_handles_waba_api_failure_gracefully(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $phoneNumberId,
                    'display_phone_number' => '+91 84692 90865',
                    'verified_name' => 'FitZone Gym',
                ],
            ]);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'WABA not found',
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->verifyWabaConnection($wabaId, $phoneNumberId, $accessToken);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['verified']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('WABA not found', $result['error'] ?? '');
    }

    #[Test]
    public function it_returns_error_when_access_token_is_missing(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->verifyWabaConnection($wabaId, $phoneNumberId);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['verified']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('access token', $result['error'] ?? '');
    }

    #[Test]
    public function it_returns_error_when_verification_throws_exception(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$phoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andThrow(new RuntimeException('Connection timed out'));

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->verifyWabaConnection($wabaId, $phoneNumberId, $accessToken);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['verified']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Connection timed out', $result['error'] ?? '');
    }

    #[Test]
    public function it_updates_existing_phone_number_record_for_account(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $wabaId = '1057724660050086';
        $oldPhoneNumberId = '111111111111111';
        $newPhoneNumberId = '222222222222222';
        $accessToken = 'EAABTESTTOKEN';

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => '999999999999999',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::PENDING,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $oldPhoneNumberId,
            'phone_number' => '+919999999999',
            'country_code' => 'IN',
            'friendly_name' => '+919999999999',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$newPhoneNumberId}", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $newPhoneNumberId,
                    'display_phone_number' => '+1 555 123 4567',
                ],
            ]);

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->completeEmbeddedSignup($gymId, $wabaId, $accessToken, $newPhoneNumberId);

        $this->assertTrue($result['success']);

        $this->assertDatabaseHas('whatsapp_phone_numbers', [
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $newPhoneNumberId,
            'phone_number' => '+15551234567',
        ]);

        $this->assertDatabaseMissing('whatsapp_phone_numbers', [
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $oldPhoneNumberId,
        ]);

        $phoneCount = WhatsAppPhoneNumber::query()
            ->where('whatsapp_account_id', $account->id)
            ->count();
        $this->assertEquals(1, $phoneCount);
    }

    // =====================================================
    // getWabaPhoneNumbers Tests
    // =====================================================

    #[Test]
    public function it_fetches_waba_phone_numbers_successfully(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}/phone_numbers", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'data' => [
                        [
                            'id' => '1163624323508414',
                            'display_phone_number' => '+91 84692 90865',
                            'verified_name' => 'FitZone Gym',
                            'status' => 'PENDING',
                            'quality_score' => 'UNKNOWN',
                        ],
                    ],
                ],
            ]);

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->getWabaPhoneNumbers($wabaId, $accessToken);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data'] ?? []);
        $this->assertEquals('1163624323508414', $result['data'][0]['id'] ?? '');
    }

    #[Test]
    public function it_returns_error_when_token_missing_for_get_waba_phone_numbers(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->getWabaPhoneNumbers('1057724660050086');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('access token is required', $result['error'] ?? '');
    }

    #[Test]
    public function it_returns_error_when_meta_api_fails_fetching_phone_numbers(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}/phone_numbers", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'API rate limit exceeded',
            ]);

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->getWabaPhoneNumbers($wabaId, $accessToken);

        $this->assertFalse($result['success']);
        $this->assertEquals('API rate limit exceeded', $result['error'] ?? '');
    }

    #[Test]
    public function it_handles_exception_during_get_waba_phone_numbers(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $wabaId = '1057724660050086';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}/phone_numbers", Mockery::type('array'), $accessToken)
            ->once()
            ->andThrow(new RuntimeException('Network connection failed'));

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->getWabaPhoneNumbers($wabaId, $accessToken);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Network connection failed', $result['error'] ?? '');
    }

    // =====================================================
    // registerPhoneNumber Tests
    // =====================================================

    #[Test]
    public function it_registers_phone_number_successfully(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $phoneNumberId = '1163624323508414';
        $pin = '654321';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with("/{$phoneNumberId}/register", [
                'messaging_product' => 'whatsapp',
                'pin' => $pin,
            ], $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => ['success' => true],
            ]);

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->registerPhoneNumber($phoneNumberId, $pin, $accessToken);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('successfully', $result['message'] ?? '');
    }

    #[Test]
    public function it_returns_error_when_token_missing_for_register_phone_number(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->registerPhoneNumber('1163624323508414', '123456');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('access token is required', $result['error'] ?? '');
    }

    #[Test]
    public function it_handles_pin_mismatch_error_during_phone_registration(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $phoneNumberId = '1163624323508414';
        $pin = '123456';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with("/{$phoneNumberId}/register", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => false,
                'error' => '(#133005) Two-step verification PIN mismatch',
            ]);

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->registerPhoneNumber($phoneNumberId, $pin, $accessToken);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('PIN mismatch', $result['error'] ?? '');
    }

    #[Test]
    public function it_handles_generic_api_failure_during_phone_registration(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with("/{$phoneNumberId}/register", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Invalid phone number ID',
            ]);

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->registerPhoneNumber($phoneNumberId, '123456', $accessToken);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid phone number ID', $result['error'] ?? '');
    }

    #[Test]
    public function it_handles_exception_during_phone_registration(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $phoneNumberId = '1163624323508414';
        $accessToken = 'EAABTESTTOKEN';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with("/{$phoneNumberId}/register", Mockery::type('array'), $accessToken)
            ->once()
            ->andThrow(new RuntimeException('API server unavailable'));

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->registerPhoneNumber($phoneNumberId, '123456', $accessToken);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API server unavailable', $result['error'] ?? '');
    }

    // =====================================================
    // syncWabaPhoneNumbers Tests
    // =====================================================

    #[Test]
    public function it_syncs_and_registers_waba_phone_numbers_successfully(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $wabaId = '1057724660050086';
        $accessToken = 'EAABTESTTOKEN';
        $phoneNumberId = '1163624323508414';

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'meta_access_token' => $accessToken,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}/phone_numbers", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'data' => [
                        [
                            'id' => $phoneNumberId,
                            'display_phone_number' => '+91 84692 90865',
                            'status' => 'PENDING',
                        ],
                    ],
                ],
            ]);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with("/{$phoneNumberId}/register", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => ['success' => true],
            ]);

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->syncWabaPhoneNumbers($gymId, $wabaId, $accessToken, '123456');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['synced_count'] ?? 0);

        $this->assertDatabaseHas('whatsapp_phone_numbers', [
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => '+918469290865',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('whatsapp_accounts', [
            'id' => $account->id,
            'status' => WhatsAppAccountStatusEnum::ACTIVE->value,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED->value,
        ]);
    }

    #[Test]
    public function it_returns_error_when_account_or_waba_id_missing_during_sync(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->syncWabaPhoneNumbers($gym->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('account not found', $result['error'] ?? '');
    }

    #[Test]
    public function it_returns_error_when_no_phone_numbers_returned_during_sync(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $wabaId = '1057724660050086';
        $accessToken = 'EAABTESTTOKEN';

        WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'meta_access_token' => $accessToken,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::PENDING,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}/phone_numbers", Mockery::type('array'), $accessToken)
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'data' => [],
                ],
            ]);

        $service = new MetaWabaService($context, $apiMock);
        $result = $service->syncWabaPhoneNumbers($gymId, $wabaId, $accessToken);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No phone numbers found', $result['error'] ?? '');
    }

    #[Test]
    public function extract_account_review_status_prefers_status_over_account_review_status(): void
    {
        $this->assertSame('ACTIVE', MetaWabaService::extractAccountReviewStatus(['status' => 'ACTIVE', 'account_review_status' => 'IN_REVIEW']));
        $this->assertSame('IN_REVIEW', MetaWabaService::extractAccountReviewStatus(['account_review_status' => 'IN_REVIEW']));
        $this->assertNull(MetaWabaService::extractAccountReviewStatus([]));
        $this->assertNull(MetaWabaService::extractAccountReviewStatus(['business_verification_status' => 'VERIFIED']));
        $this->assertNull(MetaWabaService::extractAccountReviewStatus(['status' => 123]));
    }
}
