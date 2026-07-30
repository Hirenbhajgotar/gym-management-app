<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Meta;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Services\Meta\MetaApiClient;
use App\Services\Meta\MetaContext;
use App\Services\Meta\MetaTemplateService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Expectation;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MetaTemplateServiceSubmitTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Gym $gym;

    private int $gymId;

    private MetaContext $context;

    private MetaApiClient&MockInterface $apiMock;

    private MetaTemplateService $service;

    private WhatsAppTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->gymId = $this->gym->id;

        WhatsAppAccount::query()->create([
            'gym_id' => $this->gymId,
            'waba_id' => 'test_waba_123',
            'meta_access_token' => 'test_meta_token_123',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
        ]);

        $this->context = new MetaContext($this->gymId);
        /** @var MetaApiClient&MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $this->apiMock = $apiMock;
        $this->service = new MetaTemplateService($this->context, $this->apiMock);

        $this->template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gymId,
            'name' => 'test_template',
            'display_name' => 'Test Template',
            'body' => 'Hello {{1}}, your test is confirmed.',
            'status' => WhatsAppTemplateStatusEnum::DRAFT,
            'is_active' => true,
        ]);
    }

    // ========== GUARD CLAUSE: WABA NOT CONFIGURED ==========

    #[Test]
    public function it_returns_error_when_waba_not_configured(): void
    {
        $gym = Gym::factory()->create();

        WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gym->id,
            'name' => 'test_template',
            'display_name' => 'Test Template',
            'body' => 'Hello {{1}}, your test is confirmed.',
            'status' => WhatsAppTemplateStatusEnum::DRAFT,
            'is_active' => true,
        ]);

        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $service = new MetaTemplateService($context, $apiMock);

        $result = $service->submitTemplate($template);

        $this->assertFalse($result->success);
        $this->assertEquals('WABA not configured', $result->error);
    }

    // ========== GUARD CLAUSE: ACCOUNT NOT FOUND ==========

    #[Test]
    public function it_returns_error_when_account_not_found(): void
    {
        $gymWithoutAccount = Gym::factory()->create();

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gymWithoutAccount->id,
            'name' => 'test_template',
            'display_name' => 'Test Template',
            'body' => 'Hello {{1}}, your test is confirmed.',
            'status' => WhatsAppTemplateStatusEnum::DRAFT,
            'is_active' => true,
        ]);

        $context = new MetaContext($gymWithoutAccount->id);
        /** @var MetaApiClient&MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $service = new MetaTemplateService($context, $apiMock);

        $result = $service->submitTemplate($template);

        $this->assertFalse($result->success);
        $this->assertEquals('WABA not configured', $result->error);
    }

    // ========== GUARD CLAUSE: ACCOUNT INACTIVE ==========

    #[Test]
    public function it_returns_error_when_account_is_inactive(): void
    {
        $gym = Gym::factory()->create();

        WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'waba_id' => 'test_waba_inactive',
            'status' => WhatsAppAccountStatusEnum::SUSPENDED,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
        ]);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gym->id,
            'name' => 'test_template',
            'display_name' => 'Test Template',
            'body' => 'Hello {{1}}, your test is confirmed.',
            'status' => WhatsAppTemplateStatusEnum::DRAFT,
            'is_active' => true,
        ]);

        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $service = new MetaTemplateService($context, $apiMock);

        $result = $service->submitTemplate($template);

        $this->assertFalse($result->success);
        $this->assertEquals('WhatsApp account is suspended. Please contact support for assistance.', $result->error);
    }

    // ========== GUARD CLAUSE: ACCOUNT NOT VERIFIED ==========

    #[Test]
    public function it_returns_error_when_account_not_verified(): void
    {
        $gym = Gym::factory()->create();

        WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'waba_id' => 'test_waba_unverified',
            'meta_access_token' => 'test_token',
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
        ]);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gym->id,
            'name' => 'test_template',
            'display_name' => 'Test Template',
            'body' => 'Hello {{1}}, your test is confirmed.',
            'status' => WhatsAppTemplateStatusEnum::DRAFT,
            'is_active' => true,
        ]);

        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with('/test_waba_unverified/message_templates', Mockery::type('array'))
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'WhatsApp account is not verified',
            ]);

        $service = new MetaTemplateService($context, $apiMock);

        $result = $service->submitTemplate($template);

        $this->assertFalse($result->success);
        $this->assertEquals('WhatsApp account is not verified', $result->error);
    }

    // ========== GUARD CLAUSE: TEMPLATE IS NULL ==========

    #[Test]
    public function it_returns_error_when_template_does_not_exist(): void
    {
        $result = $this->service->submitTemplate();

        $this->assertFalse($result->success);
        $this->assertEquals('Template no longer exists.', $result->error);
    }

    // ========== GUARD CLAUSE: LOCK NOT ACQUIRED ==========

    #[Test]
    public function it_returns_error_when_submission_lock_not_acquired(): void
    {
        $lock = Cache::lock("meta_template_submit:{$this->template->id}", 30);
        $this->assertTrue($lock->get());

        $result = $this->service->submitTemplate($this->template);

        $lock->release();

        $this->assertFalse($result->success);
        $this->assertEquals('Template submission already in progress', $result->error);
    }

    // ========== HAPPY PATH: SUCCESSFUL SUBMISSION ==========

    #[Test]
    public function it_submits_template_successfully(): void
    {
        $wabaId = 'test_waba_123';
        $expectedTemplateId = 'meta_template_456';

        /** @var Expectation $expectation */
        $expectation = $this->apiMock->shouldReceive('post');
        $expectation
            ->with(
                "/{$wabaId}/message_templates",
                Mockery::on(function (array $data): bool {
                    /** @var array{name: string, language: string, category: string, components: array<int, array{type: string, text: string}>} $data */
                    return $data['name'] === 'test_template'
                        && $data['language'] === 'en_US'
                        && $data['category'] === 'UTILITY'
                        && $data['components'][0]['type'] === 'BODY'
                        && $data['components'][0]['text'] === 'Hello {{1}}, your test is confirmed.';
                })
            )
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => $expectedTemplateId,
                    'status' => 'PENDING',
                ],
            ]);

        $result = $this->service->submitTemplate($this->template);

        $this->assertTrue($result->success);
        $this->assertEquals(WhatsAppTemplateStatusEnum::PENDING->value, $result->status);
        $this->assertEquals($expectedTemplateId, $result->templateId);

        $this->template->refresh();

        $this->assertEquals($expectedTemplateId, $this->template->meta_template_id);
        $this->assertEquals(WhatsAppTemplateStatusEnum::PENDING, $this->template->status);
    }

    // ========== MISSING TEMPLATE ID IN RESPONSE ==========

    #[Test]
    public function it_returns_error_when_missing_template_id_in_response(): void
    {
        $wabaId = 'test_waba_123';

        /** @var Expectation $expectation */
        $expectation = $this->apiMock->shouldReceive('post');
        $expectation
            ->with("/{$wabaId}/message_templates", Mockery::type('array'))
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'status' => 'PENDING',
                ],
            ]);

        $result = $this->service->submitTemplate($this->template);

        $this->assertFalse($result->success);
        $this->assertEquals('Missing template ID in Meta response', $result->error);

        $this->template->refresh();
        $this->assertNull($this->template->meta_template_id);
        $this->assertEquals(WhatsAppTemplateStatusEnum::DRAFT, $this->template->status);
    }

    // ========== API FAILURE ==========

    #[Test]
    public function it_returns_error_when_api_returns_failure(): void
    {
        $wabaId = 'test_waba_123';

        /** @var Expectation $expectation */
        $expectation = $this->apiMock->shouldReceive('post');
        $expectation
            ->with("/{$wabaId}/message_templates", Mockery::type('array'))
            ->once()
            ->andReturn([
                'success' => false,
                'error' => '(#100) Invalid parameter',
            ]);

        $result = $this->service->submitTemplate($this->template);

        $this->assertFalse($result->success);
        $this->assertEquals('(#100) Invalid parameter', $result->error);
    }

    // ========== LOCK TIMEOUT EXCEPTION ==========

    #[Test]
    public function it_returns_error_when_lock_timeout_exception_occurs(): void
    {
        $wabaId = 'test_waba_123';

        /** @var Expectation $expectation */
        $expectation = $this->apiMock->shouldReceive('post');
        $expectation
            ->with("/{$wabaId}/message_templates", Mockery::type('array'))
            ->once()
            ->andThrow(new LockTimeoutException('Timed out'));

        $result = $this->service->submitTemplate($this->template);

        $this->assertFalse($result->success);
        $this->assertEquals('Template submission is already in progress. Please wait.', $result->error);
    }

    // ========== NULL RESPONSE ERROR FALLBACK ==========

    #[Test]
    public function it_returns_generic_error_when_api_failure_has_no_error_message(): void
    {
        $wabaId = 'test_waba_123';

        /** @var Expectation $expectation */
        $expectation = $this->apiMock->shouldReceive('post');
        $expectation
            ->with("/{$wabaId}/message_templates", Mockery::type('array'))
            ->once()
            ->andReturn([
                'success' => false,
            ]);

        $result = $this->service->submitTemplate($this->template);

        $this->assertFalse($result->success);
        $this->assertEquals('Failed to submit template', $result->error);
    }
}
