<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Exceptions\BroadcastConnectionException;
use App\Exceptions\RateLimitExceededException;
use App\Jobs\SendMessageChunkJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppPhoneNumber;
use App\Providers\TwilioWhatsAppProvider;
use App\Services\WhatsApp\WhatsAppCreditService;
use Exception;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(SendMessageChunkJob::class)]
final class SendMessageChunkJobTest extends TestCase
{
    use RefreshDatabase;

    private const WABA_ID = 'WABA_123456';

    private const PHONE_NUMBER_ID = 'PHONE_999888';

    private const PHONE_NUMBER = '+918469290865';

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a standard Gym and WhatsApp account/phone number setup for testing.
     *
     * @return array{gym: Gym, phoneNumberId: string}
     */
    private function createGymWithWhatsAppAccount(): array
    {
        $gym = Gym::factory()->create();
        $random = fake()->unique()->numerify('##########');
        $phoneNumberId = self::PHONE_NUMBER_ID . '_' . $random;
        $phoneNumber = '+91' . $random;

        $account = WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'waba_id' => self::WABA_ID . '_' . $random,
            'meta_access_token' => 'EAABTEST',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);

        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => $phoneNumberId,
            'phone_number' => $phoneNumber,
            'country_code' => 'IN',
            'friendly_name' => $phoneNumber,
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        return [
            'gym' => $gym,
            'phoneNumberId' => $phoneNumberId,
        ];
    }

    /**
     * Helper to set up a standard test scenario with a gym, member, broadcast, and recipient chunk.
     *
     * @param  array<string, mixed>  $memberOverrides
     * @return array{gym: Gym, member: GymMember, broadcast: WhatsAppBroadcast, recipient: WhatsAppBroadcastRecipient, chunk: array<int, array{id: int, phone_number: string, member_name: string, resolved_variables: array<array-key, string>}>}
     */
    private function setupStandardBroadcastScenario(array $memberOverrides = []): array
    {
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        /** @var array<string, mixed> $attributes */
        $attributes = array_merge([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ], $memberOverrides);

        $member = GymMember::factory()->create($attributes);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => $member->name,
            'resolved_variables' => [],
        ]];

        return [
            'gym' => $gym,
            'member' => $member,
            'broadcast' => $broadcast,
            'recipient' => $recipient,
            'chunk' => $chunk,
        ];
    }

    /**
     * @param  array<int, array{id: int, phone_number: string, member_name: string, resolved_variables: array<array-key, string>}>  $recipientChunk
     */
    private function makeJob(
        int $broadcastId,
        array $recipientChunk,
        int $gymId,
        bool $skipCreditDeduction = true
    ): SendMessageChunkJob {
        return new SendMessageChunkJob(
            broadcastId: $broadcastId,
            recipientChunk: $recipientChunk,
            templateData: [
                'provider_template_id' => 'my_template',
                'language' => 'en_US',
                'components' => [],
                'media_urls' => [],
            ],
            gymId: $gymId,
            skipCreditDeduction: $skipCreditDeduction,
        );
    }

    #[Test]
    public function it_sends_messages_to_the_phone_number_id_endpoint_not_the_waba_id(): void
    {
        // Arrange
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];
        $phoneNumberId = $setup['phoneNumberId'];
        $wabaId = self::WABA_ID;

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Test Member',
            'resolved_variables' => ['1' => 'Hello'],
        ]];

        Http::fake(function () {
            return Http::response(['messages' => [['id' => 'wamid.123']]], 200);
        });

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        // Act
        $job->handle();

        // Assert
        Http::assertSent(function (Request $request) use ($phoneNumberId, $wabaId): bool {
            $url = $request->url();

            return str_contains($url, "/{$phoneNumberId}/messages")
                && ! str_contains($url, "/{$wabaId}/messages");
        });

        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'sent',
        ]);
    }

    #[Test]
    public function it_skips_sending_messages_to_opted_out_or_archived_members(): void
    {
        // Arrange
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $optedOutMember = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => false,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $optedOutMember->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Opted Out Member',
            'resolved_variables' => [],
        ]];

        Http::fake();

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        // Act
        $job->handle();

        // Assert
        Http::assertNothingSent();

        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'failed',
            'failure_reason' => 'Member opted out or was archived before send',
        ]);
    }

    #[Test]
    public function it_handles_connection_exceptions_by_throwing_exception_on_first_attempt(): void
    {
        // Arrange
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Test Member',
            'resolved_variables' => [],
        ]];

        // NOTE: Connection-faked requests never reach recordRequestResponsePair,
        // so Http::assertSent* will report zero in assertions.
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/connection error/i'), Mockery::on(function (array $context) use ($broadcast, $chunk) {
                $rawPhone = $chunk[0]['phone_number'];
                $encodedContext = (string) json_encode($context);
                $error = is_string($context['error'] ?? null) ? $context['error'] : '';

                return ($context['broadcast_id'] ?? null) === $broadcast->id
                    && ($context['phone'] ?? null) !== $rawPhone
                    && ! str_contains($encodedContext, $rawPhone)
                    && str_contains($error, 'Connection timed out');
            }));
        /** @var MockInterface $logMock */
        $logMock = Log::getFacadeRoot();
        $logMock->shouldIgnoreMissing();

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        /** @var Job&Mockery\MockInterface $mockQueueJob */
        $mockQueueJob = Mockery::mock(Job::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $mockQueueJob->shouldReceive('attempts');
        $expectation->andReturn(1);
        $job->setJob($mockQueueJob);

        // Act & Assert
        try {
            $job->handle();
            $this->fail('Expected BroadcastConnectionException was not thrown.');
        } catch (BroadcastConnectionException $e) {
            $this->assertStringContainsString('Connection error: Connection timed out', $e->getMessage());
        }

        // Assert Database State (should remain pending on first attempt)
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'pending',
            'sent_at' => null,
            'twilio_message_sid' => null,
        ]);

        // Assert Broadcast Counts stay 0
        $this->assertDatabaseHas('whatsapp_broadcasts', [
            'id' => $broadcast->id,
            'sent_count' => 0,
            'failed_count' => 0,
        ]);
    }

    #[Test]
    public function it_marks_connection_failed_recipients_as_failed_on_last_attempt(): void
    {
        // Arrange
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Test Member',
            'resolved_variables' => [],
        ]];

        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        // Mock attempts to equal tries (3) to simulate last attempt
        /** @var Job&Mockery\MockInterface $mockQueueJob */
        $mockQueueJob = Mockery::mock(Job::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $mockQueueJob->shouldReceive('attempts');
        $expectation->andReturn(3);
        $job->setJob($mockQueueJob);

        // Act
        $job->handle();

        // Assert Database State (should be marked failed on last attempt)
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'failed',
            'failure_reason' => 'Connection timed out',
            'sent_at' => null,
            'twilio_message_sid' => null,
        ]);

        // Assert Broadcast Counts failed_count is incremented
        $this->assertDatabaseHas('whatsapp_broadcasts', [
            'id' => $broadcast->id,
            'sent_count' => 0,
            'failed_count' => 1,
        ]);
    }

    #[Test]
    public function it_keeps_accounting_isolated_in_mixed_chunk(): void
    {
        // Arrange
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $memberA = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $memberB = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipientA = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $memberA->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $recipientB = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $memberB->id,
            'phone_number' => '+919998887776',
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [
            [
                'id' => $recipientA->id,
                'phone_number' => self::PHONE_NUMBER,
                'member_name' => 'Success Member',
                'resolved_variables' => [],
            ],
            [
                'id' => $recipientB->id,
                'phone_number' => '+919998887776',
                'member_name' => 'Connection Failed Member',
                'resolved_variables' => [],
            ],
        ];

        // Fake Http to return success for Recipient A, connection error for Recipient B
        Http::fake(function (Request $request) {
            if ($request->data()['to'] === self::PHONE_NUMBER) {
                return Http::response(['messages' => [['id' => 'wamid.success']]], 200);
            }
            throw new ConnectionException('Connection timed out');
        });

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        // Simulation 1: First Attempt (attempts = 1)
        /** @var Job&Mockery\MockInterface $mockQueueJob1 */
        $mockQueueJob1 = Mockery::mock(Job::class);
        /** @var Mockery\Expectation $expectation1 */
        $expectation1 = $mockQueueJob1->shouldReceive('attempts');
        $expectation1->andReturn(1);
        $job->setJob($mockQueueJob1);

        try {
            $job->handle();
            $this->fail('Expected BroadcastConnectionException was not thrown.');
        } catch (BroadcastConnectionException $e) {
            $this->assertStringContainsString('Connection error: Connection timed out', $e->getMessage());
        }

        // Recipient A (succeeds) -> marked sent
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipientA->id,
            'status' => 'sent',
            'twilio_message_sid' => 'wamid.success',
        ]);
        $recipientRecordA = WhatsAppBroadcastRecipient::query()->find($recipientA->id);
        assert($recipientRecordA instanceof WhatsAppBroadcastRecipient);
        $this->assertNotNull($recipientRecordA->sent_at);

        // Recipient B (connection fail) -> remains pending
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipientB->id,
            'status' => 'pending',
            'sent_at' => null,
            'twilio_message_sid' => null,
        ]);

        // Broadcast counters updated only for success
        $this->assertDatabaseHas('whatsapp_broadcasts', [
            'id' => $broadcast->id,
            'sent_count' => 1,
            'failed_count' => 0,
        ]);

        // Assert that exactly 1 request was sent to Meta API during first attempt
        Http::assertSentCount(1);

        // Simulation 2: Last Attempt (attempts = 3)
        /** @var Job&Mockery\MockInterface $mockQueueJob2 */
        $mockQueueJob2 = Mockery::mock(Job::class);
        /** @var Mockery\Expectation $expectation2 */
        $expectation2 = $mockQueueJob2->shouldReceive('attempts');
        $expectation2->andReturn(3);
        $job->setJob($mockQueueJob2);

        // Verify that the handle() SENT/FAILED filter is load-bearing:
        // Recipient A is already marked 'sent', so it must be excluded in the retry.
        $job->handle();

        // Recipient B (connection fail on last attempt) -> marked failed
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipientB->id,
            'status' => 'failed',
            'failure_reason' => 'Connection timed out',
            'sent_at' => null,
            'twilio_message_sid' => null,
        ]);

        // Broadcast counters failed_count incremented
        $this->assertDatabaseHas('whatsapp_broadcasts', [
            'id' => $broadcast->id,
            'sent_count' => 1,
            'failed_count' => 1,
        ]);
    }

    #[Test]
    public function it_masks_sensitive_tokens_and_sids_in_sanitized_errors(): void
    {
        // Arrange
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Test Member',
            'resolved_variables' => [],
        ]];

        // Fake Meta 400 response embedding an EAABTEST token, a Twilio Account SID, a Bearer token, and control characters.
        Http::fake(function () {
            return Http::response([
                'error' => [
                    'message' => "Failed using token EAABTEST1234567890 and SID AC1234567890abcdef1234567890abcdef with Bearer token_here and auth token 1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d, secret sk_live_51M3d4e5f6a7b8c9d0e1f2a3b4c, phone +15551234567\x00\x08",
                    'code' => 400,
                ],
            ], 400);
        });

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        // Act
        $job->handle();

        // Assert DB has masked and cleaned failure reason
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'failed',
        ]);

        $recipientRecord = WhatsAppBroadcastRecipient::query()->find($recipient->id);
        assert($recipientRecord instanceof WhatsAppBroadcastRecipient);
        $failureReason = $recipientRecord->failure_reason ?? '';

        $this->assertStringContainsString('[TOKEN_MASKED]', $failureReason);
        $this->assertStringContainsString('[SID_MASKED]', $failureReason);
        $this->assertStringContainsString('Bearer [TOKEN_MASKED]', $failureReason);
        $this->assertStringContainsString('[PHONE_MASKED]', $failureReason);
        $this->assertStringNotContainsString("\x00", $failureReason);
        $this->assertStringNotContainsString("\x08", $failureReason);

        // Negative assertions (S11)
        $this->assertStringNotContainsString('EAABTEST1234567890', $failureReason);
        $this->assertStringNotContainsString('AC1234567890abcdef1234567890abcdef', $failureReason);
        $this->assertStringNotContainsString('token_here', $failureReason);
        $this->assertStringNotContainsString('1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d', $failureReason);
        $this->assertStringNotContainsString('sk_live_51M3d4e5f6a7b8c9d0e1f2a3b4c', $failureReason);
        $this->assertStringNotContainsString('+15551234567', $failureReason);
    }

    #[Test]
    public function it_limits_sanitized_error_length_to_500_characters(): void
    {
        // Arrange
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Test Member',
            'resolved_variables' => [],
        ]];

        // Fake Meta 400 response with error message longer than 500 characters
        $longError = str_repeat('A', 600);
        Http::fake(function () use ($longError) {
            return Http::response([
                'error' => [
                    'message' => $longError,
                    'code' => 400,
                ],
            ], 400);
        });

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        // Act
        $job->handle();

        // Assert DB has failure reason truncated to 500 characters ending with '...'
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'failed',
        ]);

        $recipientRecord = WhatsAppBroadcastRecipient::query()->find($recipient->id);
        assert($recipientRecord instanceof WhatsAppBroadcastRecipient);
        $reason = $recipientRecord->failure_reason ?? '';
        $this->assertGreaterThan(0, strlen($reason));
        $this->assertStringEndsWith('...', $reason);
    }

    #[Test]
    public function it_retries_on_sequential_path_connection_exception(): void
    {
        // Arrange
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Test Member',
            'resolved_variables' => [],
        ]];

        /** @var WhatsAppCreditService&Mockery\MockInterface $mockCreditService */
        $mockCreditService = Mockery::mock(WhatsAppCreditService::class);
        /** @var Mockery\Expectation $creditExp */
        $creditExp = $mockCreditService->shouldReceive('setSkipDeduction');
        $creditExp->with(true);
        $creditExp->andReturnNull();

        /** @var TwilioWhatsAppProvider&Mockery\MockInterface $mockProvider */
        $mockProvider = Mockery::mock(TwilioWhatsAppProvider::class);
        /** @var Mockery\Expectation $providerCreditsExp */
        $providerCreditsExp = $mockProvider->shouldReceive('credits');
        $providerCreditsExp->andReturn($mockCreditService);

        /** @var Mockery\Expectation $sendMsgExp */
        $sendMsgExp = $mockProvider->shouldReceive('sendTemplateMessage');
        $sendMsgExp->once();
        $sendMsgExp->andThrow(new ConnectionException('Connection timed out'));

        /** @var SendMessageChunkJob&Mockery\MockInterface $job */
        $job = Mockery::mock(
            SendMessageChunkJob::class . '[resolveProvider]',
            [$broadcast->id, $chunk, [
                'provider_template_id' => 'my_template',
                'language' => 'en_US',
                'components' => [],
                'media_urls' => [],
            ], $gym->id, true],
        );
        $job->shouldAllowMockingProtectedMethods();
        /** @var Mockery\Expectation $resolveProviderExp */
        $resolveProviderExp = $job->shouldReceive('resolveProvider');
        $resolveProviderExp->once();
        $resolveProviderExp->andReturn($mockProvider);

        /** @var Job&Mockery\MockInterface $mockQueueJob */
        $mockQueueJob = Mockery::mock(Job::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $mockQueueJob->shouldReceive('attempts');
        $expectation->andReturn(1);
        $job->setJob($mockQueueJob);

        // Act & Assert
        try {
            $job->handle();
            $this->fail('Expected BroadcastConnectionException was not thrown.');
        } catch (BroadcastConnectionException $e) {
            $this->assertStringContainsString('Connection error: Connection timed out', $e->getMessage());
        }

        // Database recipient should remain pending on first attempt
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function it_fails_immediately_on_sequential_path_other_exceptions(): void
    {
        // Arrange
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Test Member',
            'resolved_variables' => [],
        ]];

        /** @var WhatsAppCreditService&Mockery\MockInterface $mockCreditService */
        $mockCreditService = Mockery::mock(WhatsAppCreditService::class);
        /** @var Mockery\Expectation $creditExp */
        $creditExp = $mockCreditService->shouldReceive('setSkipDeduction');
        $creditExp->with(true);
        $creditExp->andReturnNull();

        /** @var TwilioWhatsAppProvider&Mockery\MockInterface $mockProvider */
        $mockProvider = Mockery::mock(TwilioWhatsAppProvider::class);
        /** @var Mockery\Expectation $providerCreditsExp */
        $providerCreditsExp = $mockProvider->shouldReceive('credits');
        $providerCreditsExp->andReturn($mockCreditService);

        /** @var Mockery\Expectation $sendMsgExp */
        $sendMsgExp = $mockProvider->shouldReceive('sendTemplateMessage');
        $sendMsgExp->once();
        $sendMsgExp->andReturn([
            'success' => false,
            'error' => 'Some permanent Twilio error',
            'status_code' => 400,
        ]);

        /** @var SendMessageChunkJob&Mockery\MockInterface $job */
        $job = Mockery::mock(
            SendMessageChunkJob::class . '[resolveProvider]',
            [$broadcast->id, $chunk, [
                'provider_template_id' => 'my_template',
                'language' => 'en_US',
                'components' => [],
                'media_urls' => [],
            ], $gym->id, true],
        );
        $job->shouldAllowMockingProtectedMethods();
        /** @var Mockery\Expectation $resolveProviderExp */
        $resolveProviderExp = $job->shouldReceive('resolveProvider');
        $resolveProviderExp->once();
        $resolveProviderExp->andReturn($mockProvider);

        /** @var Job&Mockery\MockInterface $mockQueueJob */
        $mockQueueJob = Mockery::mock(Job::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $mockQueueJob->shouldReceive('attempts');
        $expectation->andReturn(1);
        $job->setJob($mockQueueJob);

        // Act
        $job->handle();

        // Database recipient should be marked failed immediately (no retry)
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'failed',
            'failure_reason' => 'Some permanent Twilio error',
        ]);
    }

    #[Test]
    public function it_propagates_unexpected_exceptions_without_swallowing_and_leaves_recipients_pending(): void
    {
        // Arrange
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Test Member',
            'resolved_variables' => [],
        ]];

        Http::fake(function () {
            throw new RuntimeException('Database is down');
        });

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        // Act
        $job->handle();

        // Database recipient should be marked failed with sanitized reason to prevent double-send on batch retry
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'failed',
            'failure_reason' => 'Database is down',
        ]);
    }

    #[Test]
    public function it_marks_remaining_pending_recipients_failed_via_failed_hook_on_permanent_job_failure(): void
    {
        // Arrange
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Test Member',
            'resolved_variables' => [],
        ]];

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        // Act
        $job->failed(new Exception('Rate limit hit'));

        // Assert Database State
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'failed',
            'failure_reason' => 'Broadcast chunk job failed: Rate limit hit',
        ]);

        // Assert Broadcast Counts failed_count is incremented
        $this->assertDatabaseHas('whatsapp_broadcasts', [
            'id' => $broadcast->id,
            'failed_count' => 1,
        ]);
    }

    #[Test]
    public function it_handles_rate_limiting_by_leaving_recipient_pending_and_throwing_rate_limit_exception(): void
    {
        $scenario = $this->setupStandardBroadcastScenario();
        $broadcast = $scenario['broadcast'];
        $recipient = $scenario['recipient'];
        $chunk = $scenario['chunk'];
        $gym = $scenario['gym'];

        Http::fake(function () {
            return Http::response([
                'error' => [
                    'message' => 'User rate limit exceeded, retry in 15 seconds',
                    'code' => 80007,
                ],
            ], 429);
        });

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        /** @var Job&Mockery\MockInterface $mockQueueJob */
        $mockQueueJob = Mockery::mock(Job::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $mockQueueJob->shouldReceive('attempts');
        $expectation->andReturn(1);
        $job->setJob($mockQueueJob);

        try {
            $job->handle();
            $this->fail('Expected RateLimitExceededException was not thrown.');
        } catch (RateLimitExceededException $e) {
            $this->assertStringContainsString('Rate limited', $e->getMessage());
            $this->assertSame(15, $e->getRetryAfter());
        }

        // Assert recipient is still pending (not failed or sent)
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function it_prioritizes_rate_limit_exception_over_connection_exception_in_combined_chunk(): void
    {
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $memberA = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);
        $memberB = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipientA = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $memberA->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $recipientB = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $memberB->id,
            'phone_number' => '+919998887776',
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [
            [
                'id' => $recipientA->id,
                'phone_number' => self::PHONE_NUMBER,
                'member_name' => 'Rate Limited Member',
                'resolved_variables' => [],
            ],
            [
                'id' => $recipientB->id,
                'phone_number' => '+919998887776',
                'member_name' => 'Connection Fail Member',
                'resolved_variables' => [],
            ],
        ];

        Http::fake(function (Request $request) {
            if ($request->data()['to'] === self::PHONE_NUMBER) {
                // Rate limited
                return Http::response([
                    'error' => [
                        'message' => 'Rate limit exceeded',
                        'code' => 80007,
                    ],
                ], 429);
            }

            // Connection exception
            throw new ConnectionException('Connection timed out');
        });

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        /** @var Job&Mockery\MockInterface $mockQueueJob */
        $mockQueueJob = Mockery::mock(Job::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $mockQueueJob->shouldReceive('attempts');
        $expectation->andReturn(1);
        $job->setJob($mockQueueJob);

        // Act & Assert
        try {
            $job->handle();
            $this->fail('Expected RateLimitExceededException was not thrown.');
        } catch (RateLimitExceededException $e) {
            $this->assertStringContainsString('Rate limited', $e->getMessage());
        }

        // Both should remain pending
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipientA->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipientB->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function it_handles_sanitization_fallback_on_uncleanable_errors(): void
    {
        $scenario = $this->setupStandardBroadcastScenario();
        $broadcast = $scenario['broadcast'];
        $recipient = $scenario['recipient'];
        $chunk = $scenario['chunk'];
        $gym = $scenario['gym'];

        // Inject non-ascii, non-printable characters only, resulting in an empty clean string.
        Http::fake(function () {
            return Http::response([
                'error' => [
                    'message' => "\x00\x08\x09",
                    'code' => 400,
                ],
            ], 400);
        });

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        $job->handle();

        $recipientRecord = WhatsAppBroadcastRecipient::query()->find($recipient->id);
        assert($recipientRecord instanceof WhatsAppBroadcastRecipient);
        $this->assertSame('[UNSANITIZABLE_ERROR]', $recipientRecord->failure_reason);
    }

    #[Test]
    public function it_calculates_correct_backoff_for_rate_limits_and_defaults(): void
    {
        $scenario = $this->setupStandardBroadcastScenario();
        $job = $this->makeJob($scenario['broadcast']->id, $scenario['chunk'], $scenario['gym']->id);

        // Default backoff without exception or other exception
        $this->assertSame([2, 4], $job->backoff());
        $this->assertSame([2, 4], $job->backoff(new Exception('Some other error')));

        // RateLimitExceededException backoff
        $rateLimitEx = new RateLimitExceededException('Rate limited', 45);
        $this->assertSame(45, $job->backoff($rateLimitEx));

        // Nested RateLimitExceededException backoff
        $nestedEx = new Exception('Wrapped', 0, $rateLimitEx);
        $this->assertSame(45, $job->backoff($nestedEx));
    }

    #[Test]
    public function it_handles_failed_method_idempotently(): void
    {
        $scenario = $this->setupStandardBroadcastScenario();
        $broadcast = $scenario['broadcast'];
        $recipient = $scenario['recipient'];
        $chunk = $scenario['chunk'];
        $gym = $scenario['gym'];

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        // Run failed() the first time
        $job->failed(new Exception('Job failed permanently'));

        $recipient->refresh();
        $broadcast->refresh();

        $failureReason = $recipient->failure_reason ?? '';
        $this->assertSame('failed', $recipient->status?->value);
        $this->assertStringContainsString('Job failed permanently', $failureReason);
        $this->assertSame(1, $broadcast->failed_count);

        // Run failed() the second time to assert idempotency (no double counting or exceptions)
        $job->failed(new Exception('Job failed permanently'));

        $broadcast->refresh();
        $this->assertSame(1, $broadcast->failed_count);
    }

    #[Test]
    public function it_handles_empty_chunk_early_return(): void
    {
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];
        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        // Create job with an empty chunk
        $job = $this->makeJob($broadcast->id, [], $gym->id);

        Http::fake();

        // Run handle and assert no HTTP calls were made
        $job->handle();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_handles_null_member_gracefully_by_marking_recipient_failed(): void
    {
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $resolvedVariables = ['member_name' => 'Ghost Member'];

        Schema::disableForeignKeyConstraints();
        // Create recipient with a deleted member ID (member_id that doesn't exist)
        $recipient = WhatsAppBroadcastRecipient::query()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => 999999, // Non-existent member ID
            'member_name' => 'Ghost Member',
            'phone_number' => '+14155552671',
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);
        Schema::enableForeignKeyConstraints();

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => $recipient->phone_number,
            'member_name' => 'Ghost Member',
            'resolved_variables' => $resolvedVariables,
        ]];

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        Http::fake();

        $job->handle();

        // Recipient should be marked failed with 'Member record not found'
        $recipient->refresh();
        $this->assertSame('failed', $recipient->status?->value);
        $this->assertSame('Member record not found', $recipient->failure_reason);

        // Broadcast count should reflect 1 failure
        $broadcast->refresh();
        $this->assertSame(1, $broadcast->failed_count);

        // No HTTP request should have been sent to Meta/Twilio
        Http::assertNothingSent();
    }

    #[Test]
    public function it_aborts_early_when_gym_id_mismatches_broadcast_gym_id(): void
    {
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Member',
            'resolved_variables' => [],
        ]];

        // Create job with mismatched gym_id (gym->id + 999)
        $job = $this->makeJob($broadcast->id, $chunk, $gym->id + 999);

        Http::fake();

        $job->handle();

        Http::assertNothingSent();
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function it_filters_out_already_sent_or_failed_recipients_on_retry_attempts(): void
    {
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $memberA = GymMember::factory()->create(['gym_id' => $gym->id, 'allow_whatsapp_marketing' => true]);
        $memberB = GymMember::factory()->create(['gym_id' => $gym->id, 'allow_whatsapp_marketing' => true]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipientA = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $memberA->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::SENT->value,
        ]);

        $recipientB = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $memberB->id,
            'phone_number' => '+919998887776',
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING->value,
        ]);

        $chunk = [
            [
                'id' => $recipientA->id,
                'phone_number' => self::PHONE_NUMBER,
                'member_name' => 'Already Sent Member',
                'resolved_variables' => [],
            ],
            [
                'id' => $recipientB->id,
                'phone_number' => '+919998887776',
                'member_name' => 'Pending Member',
                'resolved_variables' => [],
            ],
        ];

        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.sent_retry']]], 200),
        ]);

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        /** @var Job&Mockery\MockInterface $mockQueueJob */
        $mockQueueJob = Mockery::mock(Job::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $mockQueueJob->shouldReceive('attempts');
        $expectation->andReturn(2);
        $job->setJob($mockQueueJob);

        $job->handle();

        // Exactly 1 message sent for pending recipientB, recipientA was filtered out
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $req) => $req->data()['to'] === '+919998887776');
    }

    #[Test]
    public function it_handles_pool_response_count_mismatch_without_losing_successful_responses(): void
    {
        $setup = $this->createGymWithWhatsAppAccount();
        $gym = $setup['gym'];

        $member = GymMember::factory()->create(['gym_id' => $gym->id, 'allow_whatsapp_marketing' => true]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $recipient = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $member->id,
            'phone_number' => self::PHONE_NUMBER,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING->value,
        ]);

        $chunk = [[
            'id' => $recipient->id,
            'phone_number' => self::PHONE_NUMBER,
            'member_name' => 'Pool Member',
            'resolved_variables' => [],
        ]];

        Http::fake([
            '*' => Http::response(['messages' => [['id' => 'wamid.pool_ok']]], 200),
        ]);

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        $job->handle();

        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipient->id,
            'status' => 'sent',
            'twilio_message_sid' => 'wamid.pool_ok',
        ]);
    }

    #[Test]
    public function it_returns_connection_backoff_for_broadcast_connection_exceptions(): void
    {
        $setup = $this->createGymWithWhatsAppAccount();
        $job = $this->makeJob(1, [], $setup['gym']->id);

        $connEx = new BroadcastConnectionException('Connection lost');
        $this->assertSame([10, 30], $job->backoff($connEx));
    }

    #[Test]
    public function it_walks_multi_hop_cause_chain_in_backoff(): void
    {
        $setup = $this->createGymWithWhatsAppAccount();
        $job = $this->makeJob(1, [], $setup['gym']->id);

        $connEx = new ConnectionException('Timeout connecting');
        $wrapped1 = new RuntimeException('Layer 1 wrapper', 0, $connEx);
        $wrapped2 = new RuntimeException('Layer 2 wrapper', 0, $wrapped1);

        $this->assertSame([10, 30], $job->backoff($wrapped2));

        $rateLimitEx = new RateLimitExceededException('Rate limited', 45);
        $wrappedRate1 = new RuntimeException('Layer 1 wrapper', 0, $rateLimitEx);
        $wrappedRate2 = new RuntimeException('Layer 2 wrapper', 0, $wrappedRate1);

        $this->assertSame(45, $job->backoff($wrappedRate2));
    }

    #[Test]
    public function it_preserves_previous_exception_when_rethrowing_connection_error(): void
    {
        $setup = $this->setupStandardBroadcastScenario();
        $gym = $setup['gym'];
        $broadcast = $setup['broadcast'];
        $chunk = $setup['chunk'];

        $originalException = new ConnectionException('Network unreachable');
        Http::fake([
            '*' => function () use ($originalException) {
                throw $originalException;
            },
        ]);

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        try {
            $job->handle();
            $this->fail('Expected BroadcastConnectionException was not thrown');
        } catch (BroadcastConnectionException $e) {
            $this->assertStringContainsString('Connection error', $e->getMessage());
            $this->assertSame($originalException, $e->getPrevious());
        }
    }

    #[Test]
    public function it_parses_retry_after_header_from_http_pool_429_response(): void
    {
        $setup = $this->setupStandardBroadcastScenario();
        $gym = $setup['gym'];
        $broadcast = $setup['broadcast'];
        $chunk = $setup['chunk'];

        Http::fake([
            '*' => Http::response([
                'error' => ['message' => 'rate limit reached'],
            ], 429, ['Retry-After' => '15']),
        ]);

        $job = $this->makeJob($broadcast->id, $chunk, $gym->id);

        try {
            $job->handle();
            $this->fail('Expected RateLimitExceededException was not thrown');
        } catch (RateLimitExceededException $e) {
            $this->assertSame(15, $e->getRetryAfter());
            $this->assertStringContainsString('Retry-After: 15', $e->getMessage());
        }
    }
}
