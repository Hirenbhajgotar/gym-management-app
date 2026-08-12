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

        // Default: a name-lookup finds nothing in Meta, so submission proceeds to create.
        // The collision test overrides the lookup for its specific name. Two-arg matcher
        // (url + data) so single-argument get calls (e.g. status lookups by id) fall
        // through to their own per-test mocks.
        $this->apiMock->shouldReceive('get')
            ->with(
                Mockery::on(static fn ($url): bool => is_string($url) && str_contains($url, 'message_templates')),
                Mockery::on(static fn ($data): bool => is_array($data)
                    && array_key_exists('name', $data)
                    && ($data['name'] ?? null) !== 'colliding_template'),
            )
            ->andReturn(['success' => true, 'data' => ['data' => []]]);
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
        $apiMock->shouldReceive('get')->andReturn(['success' => true, 'data' => ['data' => []]]);

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
                    /** @var array{name: string, language: string, category: string, components: array<int, array{type: string, text: string, example?: array{body_text: array<int, array<int, string>}>}>} $data */
                    $bodyExample = $data['components'][0]['example']['body_text'][0] ?? null;

                    return $data['name'] === 'test_template'
                        && $data['language'] === 'en_US'
                        && $data['category'] === 'UTILITY'
                        && $data['components'][0]['type'] === 'BODY'
                        && $data['components'][0]['text'] === 'Hello {{1}}, your test is confirmed.'
                        && $bodyExample !== null
                        && $bodyExample[0] === 'Sample 1'
                        && ! str_contains($bodyExample[0], '{{1}}');
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

    // ========== EXAMPLE FIELD BUILT FROM VARIABLE PLACEHOLDERS ==========

    #[Test]
    public function it_builds_example_from_stored_variable_placeholders(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gymId,
            'name' => 'reminder',
            'display_name' => 'Reminder',
            'body' => 'Hi {{1}}, your {{2}} plan renews soon.',
            'status' => WhatsAppTemplateStatusEnum::DRAFT,
            'is_active' => true,
            'variable_placeholder' => [
                '1' => 'Jane',
                '2' => ['example' => 'Gold Plan', 'placeholder' => '{{2}}'],
            ],
        ]);

        $wabaId = 'test_waba_123';

        /** @var Expectation $expectation */
        $expectation = $this->apiMock->shouldReceive('post');
        $expectation
            ->with(
                "/{$wabaId}/message_templates",
                Mockery::on(function (array $data): bool {
                    $bodyExample = $data['components'][0]['example']['body_text'][0] ?? null;

                    return $bodyExample === ['Jane', 'Gold Plan'];
                })
            )
            ->once()
            ->andReturn([
                'success' => true,
                'data' => ['id' => 'meta_1', 'status' => 'PENDING'],
            ]);

        $result = $this->service->submitTemplate($template);

        $this->assertTrue($result->success);
    }

    #[Test]
    public function it_omits_example_when_body_has_no_variables(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gymId,
            'name' => 'no_vars',
            'display_name' => 'No Vars',
            'body' => 'Your membership is active.',
            'status' => WhatsAppTemplateStatusEnum::DRAFT,
            'is_active' => true,
        ]);

        $wabaId = 'test_waba_123';

        /** @var Expectation $expectation */
        $expectation = $this->apiMock->shouldReceive('post');
        $expectation
            ->with(
                "/{$wabaId}/message_templates",
                Mockery::on(function (array $data): bool {
                    return ! array_key_exists('example', $data['components'][0]);
                })
            )
            ->once()
            ->andReturn([
                'success' => true,
                'data' => ['id' => 'meta_2', 'status' => 'PENDING'],
            ]);

        $result = $this->service->submitTemplate($template);

        $this->assertTrue($result->success);
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

    // ========== NAME COLLISION RESOLUTION ==========

    #[Test]
    public function it_resolves_name_collision_when_meta_template_id_is_null(): void
    {
        $wabaId = 'test_waba_123';
        $existingMetaId = 'existing_meta_777';
        $newTemplateId = 'meta_new_999';

        $this->template->update(['name' => 'colliding_template']);

        // Meta already has a rejected template with this name; we find it by name.
        $this->apiMock->shouldReceive('get')
            ->with("/{$wabaId}/message_templates", ['name' => 'colliding_template'])
            ->once()
            ->andReturn(['success' => true, 'data' => ['data' => [['id' => $existingMetaId, 'status' => 'REJECTED']]]]);

        // Status lookup of the found template (rejected, not approved).
        $this->apiMock->shouldReceive('get')
            ->with("/{$existingMetaId}")
            ->once()
            ->andReturn(['success' => true, 'data' => ['status' => 'REJECTED']]);

        // Stale template is deleted before resubmission.
        $this->apiMock->shouldReceive('makeRequest')
            ->with('delete', "/{$wabaId}/message_templates", ['hsm_id' => $existingMetaId])
            ->once()
            ->andReturn(['success' => true]);

        $this->apiMock->shouldReceive('post')
            ->with("/{$wabaId}/message_templates", Mockery::type('array'))
            ->once()
            ->andReturn(['success' => true, 'data' => ['id' => $newTemplateId, 'status' => 'PENDING']]);

        $result = $this->service->submitTemplate($this->template->fresh());

        $this->assertTrue($result->success);
        $this->template->refresh();
        $this->assertEquals($newTemplateId, $this->template->meta_template_id);
        $this->assertEquals(WhatsAppTemplateStatusEnum::PENDING, $this->template->status);
    }

    #[Test]
    public function it_deletes_stale_template_and_resubmits_when_meta_template_id_exists(): void
    {
        $wabaId = 'test_waba_123';
        $oldMetaId = 'old_meta_999';
        $newTemplateId = 'meta_new_888';

        $this->template->meta_template_id = $oldMetaId;
        $this->template->save();

        $this->apiMock->shouldReceive('get')
            ->with("/{$oldMetaId}")
            ->once()
            ->andReturn(['success' => true, 'data' => ['status' => 'REJECTED']]);

        $this->apiMock->shouldReceive('makeRequest')
            ->with('delete', "/{$wabaId}/message_templates", ['hsm_id' => $oldMetaId])
            ->once()
            ->andReturn(['success' => true]);

        $this->apiMock->shouldReceive('post')
            ->with("/{$wabaId}/message_templates", Mockery::type('array'))
            ->once()
            ->andReturn(['success' => true, 'data' => ['id' => $newTemplateId, 'status' => 'PENDING']]);

        $result = $this->service->submitTemplate($this->template->fresh());

        $this->assertTrue($result->success);
        $this->template->refresh();
        $this->assertEquals($newTemplateId, $this->template->meta_template_id);
    }

    #[Test]
    public function it_returns_error_when_remote_template_is_already_approved(): void
    {
        $wabaId = 'test_waba_123';
        $oldMetaId = 'old_meta_777';

        $this->template->meta_template_id = $oldMetaId;
        $this->template->save();

        $this->apiMock->shouldReceive('get')
            ->with("/{$oldMetaId}")
            ->once()
            ->andReturn(['success' => true, 'data' => ['status' => 'APPROVED']]);

        $this->apiMock->shouldReceive('post')
            ->with("/{$wabaId}/message_templates", Mockery::type('array'))
            ->never();

        $result = $this->service->submitTemplate($this->template->fresh());

        $this->assertFalse($result->success);
        $this->assertEquals(
            'This template is already approved in Meta and cannot be resubmitted. Create a new template instead.',
            $result->error
        );
    }
}
