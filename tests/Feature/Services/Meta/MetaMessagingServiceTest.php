<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Meta;

use App\Enums\TemplateCategoryEnum;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Exceptions\BroadcastConnectionException;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Services\Meta\MetaApiClient;
use App\Services\Meta\MetaContext;
use App\Services\Meta\MetaMessagingService;
use App\Services\WhatsApp\WhatsAppCreditService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Expectation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetaMessagingServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    #[Test]
    public function it_sends_text_message_using_phone_number_id_endpoint_and_deducts_credits(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;
        $phoneNumberId = '1163624323508414';
        $wabaId = '1057724660050086';

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $gymId,
            'total_credits' => 100,
            'remaining_credits' => 100,
            'used_credits' => 0,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with("/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => '+919876543210',
                'type' => 'text',
                'text' => [
                    'body' => 'Hello member!',
                ],
            ])
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'messages' => [
                        ['id' => 'wamid.HBgLOTE5ODc2NTQzMjEwFQIAERgSQTIz'],
                    ],
                ],
            ]);

        $credits = new WhatsAppCreditService($gymId);
        $service = new MetaMessagingService($context, $apiMock, $credits);

        $result = $service->sendMessage('+919876543210', 'Hello member!');

        $this->assertTrue($result['success']);
        $this->assertEquals('wamid.HBgLOTE5ODc2NTQzMjEwFQIAERgSQTIz', $result['message_id'] ?? null);

        $creditRecord = WhatsAppMessageCredit::query()->where('gym_id', $gymId)->first();
        $this->assertNotNull($creditRecord);
        $this->assertEquals(99, $creditRecord->remaining_credits);
    }

    #[Test]
    public function it_returns_error_when_whatsapp_account_is_not_configured(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $credits = new WhatsAppCreditService($gym->id);

        $service = new MetaMessagingService($context, $apiMock, $credits);
        $result = $service->sendMessage('+919876543210', 'Test message');

        $this->assertFalse($result['success']);
        $this->assertEquals('WhatsApp account not configured', $result['error'] ?? null);
    }

    #[Test]
    public function it_returns_error_when_phone_number_is_not_configured(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => '1057724660050086',
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $credits = new WhatsAppCreditService($gymId);

        $service = new MetaMessagingService($context, $apiMock, $credits);
        $result = $service->sendMessage('+919876543210', 'Test message');

        $this->assertFalse($result['success']);
        $this->assertEquals('Phone number not configured', $result['error'] ?? null);
    }

    #[Test]
    public function it_addresses_meta_by_the_provider_side_template_id_not_the_internal_name(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;
        $phoneNumberId = '1163624323508414';

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => '1057724660050086',
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gymId,
            'name' => 'payment_reminder',
            'display_name' => 'Payment Reminder',
            'category' => TemplateCategoryEnum::UTILITY,
            'body' => 'Hello {{1}}, your payment of {{2}} is due.',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        // meta_template_id is deliberately not fillable, so a create() payload
        // carrying it is discarded silently. Assigning it directly is what makes
        // this fixture real — with it left null the send falls back to the
        // internal name and the assertion below would pass either way.
        $template->meta_template_id = '1234567890';
        $template->save();

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $gymId,
            'total_credits' => 50,
            'remaining_credits' => 50,
            'used_credits' => 0,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with("/{$phoneNumberId}/messages", Mockery::on(function (array $payload): bool {
                return data_get($payload, 'template.name') === '1234567890'
                    && data_get($payload, 'template.name') !== 'payment_reminder';
            }))
            ->once()
            ->andReturn([
                'success' => true,
                'data' => ['messages' => [['id' => 'wamid.TEMPLATE_MSG_ID']]],
            ]);

        $credits = new WhatsAppCreditService($gymId);
        $service = new MetaMessagingService($context, $apiMock, $credits);

        $result = $service->sendTemplateMessage('+919876543210', 'payment_reminder', ['John', '$50']);

        $this->assertTrue($result['success']);
    }

    #[Test]
    public function it_orders_body_parameters_by_placeholder_number_not_by_arrival(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;
        $phoneNumberId = '1163624323508414';

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => '1057724660050086',
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gymId,
            'name' => 'payment_reminder',
            'display_name' => 'Payment Reminder',
            'category' => TemplateCategoryEnum::UTILITY,
            'body' => 'Hello {{1}}, your payment of {{2}} is due.',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        $template->meta_template_id = '1234567890';
        $template->save();

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $gymId,
            'total_credits' => 50,
            'remaining_credits' => 50,
            'used_credits' => 0,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with("/{$phoneNumberId}/messages", Mockery::on(function (array $payload): bool {
                return array_column(
                    data_get($payload, 'template.components.0.parameters', []),
                    'text'
                ) === ['John', '$50'];
            }))
            ->once()
            ->andReturn([
                'success' => true,
                'data' => ['messages' => [['id' => 'wamid.TEMPLATE_MSG_ID']]],
            ]);

        $credits = new WhatsAppCreditService($gymId);
        $service = new MetaMessagingService($context, $apiMock, $credits);

        // Reversed, which is what a template whose placeholder JSON happened to be
        // written out of order produces. Meta substitutes these positionally, so
        // posting them reversed swaps the member's name and the amount and still
        // returns 200 — the gym is billed for a message that says the wrong thing.
        $result = $service->sendTemplateMessage('+919876543210', 'payment_reminder', [
            '2' => '$50',
            '1' => 'John',
        ]);

        $this->assertTrue($result['success']);
    }

    #[Test]
    public function it_refuses_a_template_with_no_meta_id_before_deducting_a_credit(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => '1057724660050086',
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => '1163624323508414',
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        WhatsAppTemplate::query()->create([
            'gym_id' => $gymId,
            'name' => 'payment_reminder',
            'display_name' => 'Payment Reminder',
            'category' => TemplateCategoryEnum::UTILITY,
            'body' => 'Hello',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        $credit = WhatsAppMessageCredit::query()->create([
            'gym_id' => $gymId,
            'total_credits' => 50,
            'remaining_credits' => 50,
            'used_credits' => 0,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $apiMock->shouldNotReceive('post');

        $service = new MetaMessagingService($context, $apiMock, new WhatsAppCreditService($gymId));
        $result = $service->sendTemplateMessage('+919876543210', 'payment_reminder');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no Meta template id', (string) ($result['error'] ?? ''));
        $this->assertSame(50, $credit->refresh()->remaining_credits, 'No credit may be taken for a template that cannot be dispatched.');
    }

    /**
     * The regression test for the double refund. WhatsAppChannel::send() used to
     * refund on any exception while the provider had already refunded inside its
     * own failure path, so one rejected templated send credited the gym twice.
     * Driving the provider the way the channel does — and then again, since the
     * channel's own refund is what used to compound it — proves the balance now
     * moves exactly once per failed send.
     */
    #[Test]
    public function a_rejected_template_send_refunds_the_credit_exactly_once(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => '1057724660050086',
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => '1163624323508414',
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gymId,
            'name' => 'payment_reminder',
            'display_name' => 'Payment Reminder',
            'category' => TemplateCategoryEnum::UTILITY,
            'body' => 'Hello {{1}}',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        $template->meta_template_id = '1234567890';
        $template->save();

        $credit = WhatsAppMessageCredit::query()->create([
            'gym_id' => $gymId,
            'total_credits' => 50,
            'remaining_credits' => 50,
            'used_credits' => 0,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $apiMock->shouldReceive('post')->once()->andReturn([
            'success' => false,
            'error' => 'Template does not exist',
        ]);

        $service = new MetaMessagingService($context, $apiMock, new WhatsAppCreditService($gymId));
        $result = $service->sendTemplateMessage('+919876543210', 'payment_reminder', ['John']);

        $this->assertFalse($result['success']);
        $this->assertSame(50, $credit->refresh()->remaining_credits, 'A failed send must cost nothing.');
    }

    /**
     * A connection failure used to deduct and then propagate with no refund, so
     * one outage cost three credits per member across the job's retry budget.
     */
    #[Test]
    public function a_connection_failure_refunds_the_credit(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => '1057724660050086',
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => '1163624323508414',
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gymId,
            'name' => 'payment_reminder',
            'display_name' => 'Payment Reminder',
            'category' => TemplateCategoryEnum::UTILITY,
            'body' => 'Hello {{1}}',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        $template->meta_template_id = '1234567890';
        $template->save();

        $credit = WhatsAppMessageCredit::query()->create([
            'gym_id' => $gymId,
            'total_credits' => 50,
            'remaining_credits' => 50,
            'used_credits' => 0,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $apiMock->shouldReceive('post')->once()->andThrow(new BroadcastConnectionException('Connection timed out'));

        $service = new MetaMessagingService($context, $apiMock, new WhatsAppCreditService($gymId));

        try {
            $service->sendTemplateMessage('+919876543210', 'payment_reminder', ['John']);
            $this->fail('Expected the connection failure to propagate so the job can retry.');
        } catch (BroadcastConnectionException) {
            // Expected.
        }

        $this->assertSame(50, $credit->refresh()->remaining_credits);
    }

    /**
     * A gym whose balance cannot cover the send must not be credited back for a
     * deduction that never happened. WhatsAppChannel refunded on any exception, so
     * the "insufficient credits" throw used to hand the gym a credit.
     */
    #[Test]
    public function a_rejected_charge_is_not_refunded(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => '1057724660050086',
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => '1163624323508414',
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $creditable = WhatsAppTemplate::query()->create([
            'gym_id' => $gymId,
            'name' => 'payment_reminder',
            'display_name' => 'Payment Reminder',
            'category' => TemplateCategoryEnum::UTILITY,
            'body' => 'Hello {{1}}',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        $this->attachMetaTemplateId($creditable);

        $credit = WhatsAppMessageCredit::query()->create([
            'gym_id' => $gymId,
            'total_credits' => 50,
            'remaining_credits' => 0,
            'used_credits' => 50,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $apiMock->shouldNotReceive('post');

        $service = new MetaMessagingService($context, $apiMock, new WhatsAppCreditService($gymId));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient message credits');

        try {
            $service->sendTemplateMessage('+919876543210', 'payment_reminder', ['John']);
        } finally {
            $this->assertSame(0, $credit->refresh()->remaining_credits);
        }
    }

    #[Test]
    public function it_sends_template_message_successfully_using_phone_number_id_endpoint(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;
        $phoneNumberId = '1163624323508414';
        $wabaId = '1057724660050086';

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gymId,
            'name' => 'payment_reminder',
            'display_name' => 'Payment Reminder',
            'category' => TemplateCategoryEnum::UTILITY,
            'body' => 'Hello {{1}}, your payment of {{2}} is due.',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        $this->attachMetaTemplateId($template);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $gymId,
            'total_credits' => 50,
            'remaining_credits' => 50,
            'used_credits' => 0,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with("/{$phoneNumberId}/messages", Mockery::on(function (array $payload) use ($template): bool {
                return $payload['messaging_product'] === 'whatsapp'
                    && $payload['to'] === '+919876543210'
                    && $payload['type'] === 'template'
                    && is_array($payload['template'] ?? null)
                    && ($payload['template']['name'] ?? null) === $template->meta_template_id;
            }))
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'messages' => [
                        ['id' => 'wamid.TEMPLATE_MSG_123'],
                    ],
                ],
            ]);

        $credits = new WhatsAppCreditService($gymId);
        $service = new MetaMessagingService($context, $apiMock, $credits);

        $result = $service->sendTemplateMessage('+919876543210', 'payment_reminder', ['John', '$50']);

        $this->assertTrue($result['success']);
        $this->assertEquals('wamid.TEMPLATE_MSG_123', $result['message_id'] ?? null);

        $template->refresh();
        $this->assertEquals(1, $template->usage_count);
    }

    #[Test]
    public function it_returns_error_when_template_is_not_approved_or_missing(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => '1057724660050086',
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => '1163624323508414',
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $unapproved = WhatsAppTemplate::query()->create([
            'gym_id' => $gymId,
            'name' => 'unapproved_template',
            'display_name' => 'Unapproved Template',
            'category' => TemplateCategoryEnum::UTILITY,
            'body' => 'Draft body',
            'status' => WhatsAppTemplateStatusEnum::DRAFT,
        ]);

        $this->attachMetaTemplateId($unapproved);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $credits = new WhatsAppCreditService($gymId);

        $service = new MetaMessagingService($context, $apiMock, $credits);
        $result = $service->sendTemplateMessage('+919876543210', 'unapproved_template');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found or not approved', ($result['error'] ?? ''));
    }

    #[Test]
    public function it_fetches_message_status_successfully(): void
    {
        $gym = Gym::factory()->create();
        $context = new MetaContext($gym->id);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        $credits = new WhatsAppCreditService($gym->id);

        $messageId = 'wamid.HBgLOTE5ODc2NTQzMjEwFQIAERgSQTIz';

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$messageId}")
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'status' => 'delivered',
                ],
            ]);

        $service = new MetaMessagingService($context, $apiMock, $credits);
        $result = $service->getMessageStatus($messageId);

        $this->assertTrue($result['success']);
        $responseData = is_array($result['data'] ?? null) ? $result['data'] : [];
        $this->assertEquals('delivered', $responseData['status'] ?? '');
    }

    #[Test]
    public function it_normalizes_bare_en_to_default_meta_locale_on_send_template_message(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;
        $phoneNumberId = '1163624323508414';
        $wabaId = '1057724660050086';

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $twilioSynced = WhatsAppTemplate::query()->create([
            'gym_id' => $gymId,
            'name' => 'twilio_synced_template',
            'display_name' => 'Twilio Synced Template',
            'category' => TemplateCategoryEnum::UTILITY,
            'body' => 'Hello member!',
            'language' => 'en', // Bare Twilio language code
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        $this->attachMetaTemplateId($twilioSynced);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $gymId,
            'total_credits' => 10,
            'remaining_credits' => 10,
            'used_credits' => 0,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with("/{$phoneNumberId}/messages", Mockery::on(function (array $payload) {
                // Must normalize bare 'en' to 'en_US'
                return data_get($payload, 'template.language.code') === 'en_US';
            }))
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'messages' => [['id' => 'wamid.MSG_NORM_1']],
                ],
            ]);

        $credits = new WhatsAppCreditService($gymId);
        $service = new MetaMessagingService($context, $apiMock, $credits);

        $result = $service->sendTemplateMessage('+919876543210', 'twilio_synced_template');

        $this->assertTrue($result['success']);
    }

    #[Test]
    public function it_preserves_explicit_meta_full_locale_on_send_template_message(): void
    {
        $gym = Gym::factory()->create();
        $gymId = $gym->id;
        $phoneNumberId = '1163624323508414';
        $wabaId = '1057724660050086';

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'meta_access_token' => 'EAABTESTTOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => '+918469290865',
            'country_code' => 'IN',
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        $spanish = WhatsAppTemplate::query()->create([
            'gym_id' => $gymId,
            'name' => 'spanish_template',
            'display_name' => 'Spanish Template',
            'category' => TemplateCategoryEnum::UTILITY,
            'body' => 'Hola miembro!',
            'language' => 'es_ES',
            'status' => WhatsAppTemplateStatusEnum::APPROVED,
        ]);

        $this->attachMetaTemplateId($spanish);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $gymId,
            'total_credits' => 10,
            'remaining_credits' => 10,
            'used_credits' => 0,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);

        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('post');
        $expectation
            ->with("/{$phoneNumberId}/messages", Mockery::on(function (array $payload) {
                return data_get($payload, 'template.language.code') === 'es_ES';
            }))
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'messages' => [['id' => 'wamid.MSG_NORM_2']],
                ],
            ]);

        $credits = new WhatsAppCreditService($gymId);
        $service = new MetaMessagingService($context, $apiMock, $credits);

        $result = $service->sendTemplateMessage('+919876543210', 'spanish_template');

        $this->assertTrue($result['success']);
    }

    /**
     * meta_template_id is not fillable, so passing it to create() is silently
     * discarded and every fixture that did so resolved with a null identifier.
     * The send then fell back to the internal name column, which is why those
     * tests passed while asserting the wrong payload.
     */
    private function attachMetaTemplateId(WhatsAppTemplate $template): WhatsAppTemplate
    {
        $template->meta_template_id = '1234567890';
        $template->save();

        return $template->refresh();
    }
}
