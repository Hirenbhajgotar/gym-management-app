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
                    'display_phone_number' => '918469290865',
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
            '918469290865',
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

        $service = new MetaWabaService($context, $apiMock);

        $result = $service->completeEmbeddedSignup($gymId, $wabaId, $accessToken, $phoneNumberId);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('waba_id', $result);
        $this->assertEquals($wabaId, $result['waba_id'] ?? '');

        $this->assertDatabaseHas('whatsapp_accounts', [
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'embedded_signup_completed' => true,
            'status' => WhatsAppAccountStatusEnum::PENDING->value,
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
    public function it_does_not_overwrite_existing_phone_number_record(): void
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
            'waba_phone_number_id' => $oldPhoneNumberId,
            'phone_number' => '+919999999999',
        ]);

        $this->assertDatabaseHas('whatsapp_phone_numbers', [
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $newPhoneNumberId,
            'phone_number' => '+15551234567',
        ]);

        $phoneCount = WhatsAppPhoneNumber::query()
            ->where('whatsapp_account_id', $account->id)
            ->count();
        $this->assertEquals(2, $phoneCount);
    }
}
