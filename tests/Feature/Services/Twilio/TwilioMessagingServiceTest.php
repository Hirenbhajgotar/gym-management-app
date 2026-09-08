<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Twilio;

use App\Exceptions\BroadcastConnectionException;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Services\Twilio\TwilioContext;
use App\Services\Twilio\TwilioMessagingService;
use App\Services\WhatsApp\WhatsAppCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Api\V2010\Account\MessageList;
use Twilio\Rest\Client;

class TwilioMessagingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected WhatsAppAccount $account;

    protected WhatsAppPhoneNumber $phoneNumber;

    protected WhatsAppTemplate $template;

    protected WhatsAppMessageCredit $credits;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'twilio_account_sid' => 'ACtest_sid',
            'twilio_auth_token' => 'test_auth_token',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $this->phoneNumber = WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $this->account->id,
            'phone_number' => '+14155551234',
            'friendly_name' => '+14155551234',
            'formatted_number' => '(415) 555-1234',
            'country_code' => 'US',
            'twilio_phone_number_sid' => 'PNtest123',
            'verification_status' => 'verified',
        ]);

        $this->template = WhatsAppTemplate::query()->create([
            'gym_id' => $this->gym->id,
            'name' => 'welcome_message',
            'display_name' => 'Welcome Message',
            'body' => 'Hello {{1}}, welcome!',
            'category' => 'utility',
            'variables' => [['name' => 'member_name']],
            'variable_count' => 1,
            'twilio_template_sid' => 'HX_template_123',
            'status' => 'approved',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->credits = WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 0,
            'remaining_credits' => 100,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_error_when_template_not_found(): void
    {
        $context = new TwilioContext($this->gym->id);
        $credits = new WhatsAppCreditService($this->gym->id);
        $service = new TwilioMessagingService($context, $credits);

        $result = $service->sendTemplateMessage('+14155559999', 'non_existent_template');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found or not approved', $result['error'] ?? '');
        $this->assertSame(100, $this->credits->refresh()->remaining_credits);
    }

    #[Test]
    public function it_throws_broadcast_connection_exception_on_connection_failure(): void
    {
        /** @var Client&Mockery\MockInterface $mockClient */
        $mockClient = Mockery::mock(Client::class);
        /** @var MessageList&Mockery\MockInterface $mockMessages */
        $mockMessages = Mockery::mock(MessageList::class);
        $mockClient->messages = $mockMessages;

        $connException = new ConnectionException('Connection timed out to Twilio');
        /** @var Mockery\Expectation $createExp */
        $createExp = $mockMessages->shouldReceive('create');
        $createExp->once()->andThrow($connException);

        /** @var TwilioContext&Mockery\MockInterface $mockContext */
        $mockContext = Mockery::mock(TwilioContext::class, [$this->gym->id])->makePartial();
        $mockContext->allows([
            'getClient' => $mockClient,
            'getSenderPhoneNumber' => '+14155551234',
        ]);

        $credits = new WhatsAppCreditService($this->gym->id);
        $service = new TwilioMessagingService($mockContext, $credits);

        try {
            $service->sendMessage('+14155559999', 'Hello world');
            $this->fail('Expected BroadcastConnectionException was not thrown');
        } catch (BroadcastConnectionException $e) {
            $this->assertSame('Connection timed out to Twilio', $e->getMessage());
            $this->assertSame(100, $this->credits->refresh()->remaining_credits);
        }
    }

    #[Test]
    public function it_throws_broadcast_connection_exception_on_transient_rest_exception_and_sanitizes_message(): void
    {
        /** @var Client&Mockery\MockInterface $mockClient */
        $mockClient = Mockery::mock(Client::class);
        /** @var MessageList&Mockery\MockInterface $mockMessages */
        $mockMessages = Mockery::mock(MessageList::class);
        $mockClient->messages = $mockMessages;

        // Twilio 503 Service Unavailable with a secret in the message
        $restException = new RestException('Twilio server error: secret AC1234567890abcdef1234567890abcdef', 12345, 503);
        /** @var Mockery\Expectation $createExp */
        $createExp = $mockMessages->shouldReceive('create');
        $createExp->once()->andThrow($restException);

        /** @var TwilioContext&Mockery\MockInterface $mockContext */
        $mockContext = Mockery::mock(TwilioContext::class, [$this->gym->id])->makePartial();
        $mockContext->allows([
            'getClient' => $mockClient,
            'getSenderPhoneNumber' => '+14155551234',
        ]);

        $credits = new WhatsAppCreditService($this->gym->id);
        $service = new TwilioMessagingService($mockContext, $credits);

        try {
            $service->sendMessage('+14155559999', 'Hello world');
            $this->fail('Expected BroadcastConnectionException was not thrown');
        } catch (BroadcastConnectionException $e) {
            $this->assertStringContainsString('[SID_MASKED]', $e->getMessage());
            $this->assertStringNotContainsString('AC1234567890abcdef1234567890abcdef', $e->getMessage());
            $this->assertSame($restException, $e->getPrevious());
            $this->assertSame(100, $this->credits->refresh()->remaining_credits);
        }
    }

    #[Test]
    public function it_handles_non_transient_rest_exception_gracefully_without_rethrowing(): void
    {
        /** @var Client&Mockery\MockInterface $mockClient */
        $mockClient = Mockery::mock(Client::class);
        /** @var MessageList&Mockery\MockInterface $mockMessages */
        $mockMessages = Mockery::mock(MessageList::class);
        $mockClient->messages = $mockMessages;

        // Twilio 400 Bad Request (terminal error)
        $restException = new RestException('Invalid phone number provided', 21608, 400);
        /** @var Mockery\Expectation $createExp */
        $createExp = $mockMessages->shouldReceive('create');
        $createExp->once()->andThrow($restException);

        /** @var TwilioContext&Mockery\MockInterface $mockContext */
        $mockContext = Mockery::mock(TwilioContext::class, [$this->gym->id])->makePartial();
        $mockContext->allows([
            'getClient' => $mockClient,
            'getSenderPhoneNumber' => '+14155551234',
        ]);

        $credits = new WhatsAppCreditService($this->gym->id);
        $service = new TwilioMessagingService($mockContext, $credits);

        $result = $service->sendMessage('+14155559999', 'Hello world');

        $this->assertFalse($result['success']);
        $this->assertSame(400, $result['status_code'] ?? null);
        $this->assertStringContainsString('Invalid phone number provided', $result['error'] ?? '');
        $this->assertSame(100, $this->credits->refresh()->remaining_credits);
    }

    #[Test]
    public function it_returns_error_when_sender_phone_number_is_missing(): void
    {
        /** @var TwilioContext&Mockery\MockInterface $mockContext */
        $mockContext = Mockery::mock(TwilioContext::class, [$this->gym->id])->makePartial();
        $mockContext->allows([
            'getSenderPhoneNumber' => null,
        ]);

        $credits = new WhatsAppCreditService($this->gym->id);
        $service = new TwilioMessagingService($mockContext, $credits);

        $result = $service->sendMessage('+14155559999', 'Hello world');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No phone number configured', $result['error'] ?? '');
        $this->assertSame(100, $this->credits->refresh()->remaining_credits);
    }
}
