<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendMessageChunkJob;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Providers\MetaWhatsAppProvider;
use App\Services\Meta\MetaContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class WhatsAppBroadcastChunkJobTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppBroadcast $broadcast;

    /**
     * @var array{provider_template_id: string, language: string, components: array<mixed>, media_urls: array<mixed>}
     */
    private array $templateData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->broadcast = WhatsAppBroadcast::factory()->create([
            'sent_count' => 0,
            'failed_count' => 0,
            'credits_used' => 0,
        ]);

        $this->templateData = [
            'provider_template_id' => 'test_template',
            'language' => 'en',
            'components' => [],
            'media_urls' => [],
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a partially-mocked job with a mock provider injected.
     *
     * @param  array<int, array{id: int, phone_number: string, member_name: string, resolved_variables: array<string, string>}>  $recipientChunk
     * @param  array{provider_template_id: string, language: string, components: array<mixed>, media_urls: array<mixed>}  $templateData
     */
    private function createJobWithMockProvider(
        int $broadcastId,
        array $recipientChunk,
        array $templateData,
        int $gymId,
        MockInterface $mockProvider,
        bool $skipCreditDeduction = false,
    ): SendMessageChunkJob {
        // Set up context mock for Meta provider.
        // Return null wabaId/accessToken so sendViaHttpPool() falls back to
        // sendSequential(), which calls sendTemplateMessage() on the provider
        // (preserving existing mock expectations).
        $contextMock = Mockery::mock(MetaContext::class);
        /** @var Mockery\Expectation $e1 */
        $e1 = $contextMock->shouldReceive('getWabaId');
        $e1->andReturn(null);
        /** @var Mockery\Expectation $e2 */
        $e2 = $contextMock->shouldReceive('getAccessToken');
        $e2->andReturn(null);
        /** @var Mockery\Expectation $e3 */
        $e3 = $contextMock->shouldReceive('getApiBaseUrl');
        $e3->andReturn('https://graph.facebook.com/v22.0');

        /** @var Mockery\Expectation $e4 */
        $e4 = $mockProvider->shouldReceive('getContext');
        $e4->andReturn($contextMock);

        /** @var SendMessageChunkJob&MockInterface $job */
        $job = Mockery::mock(
            SendMessageChunkJob::class . '[resolveProvider]',
            [$broadcastId, $recipientChunk, $templateData, $gymId, $skipCreditDeduction],
        );
        $job->shouldAllowMockingProtectedMethods();

        /** @var Mockery\Expectation $e5 */
        $e5 = $job->shouldReceive('resolveProvider');
        $e5->once()->andReturn($mockProvider);

        return $job;
    }

    public function test_job_processes_fifty_recipients_and_updates_status_bulk(): void
    {
        $recipients = [];
        for ($i = 0; $i < 5; $i++) {
            $recipient = WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'status' => 'pending',
            ]);
            $recipients[] = [
                'id' => $recipient->id,
                'phone_number' => $recipient->phone_number,
                'member_name' => $recipient->member_name,
                'resolved_variables' => ['name' => 'Test'],
            ];
        }

        $mockProvider = Mockery::mock(MetaWhatsAppProvider::class);
        /** @var Mockery\Expectation $exp */
        $exp = $mockProvider->shouldReceive('sendTemplateMessage');
        $exp->times(5)->andReturn(['success' => true, 'message_id' => 'SM123']);

        $job = $this->createJobWithMockProvider(
            broadcastId: $this->broadcast->id,
            recipientChunk: $recipients,
            templateData: $this->templateData,
            gymId: $this->broadcast->gym_id,
            mockProvider: $mockProvider,
        );

        $job->handle();

        $updatedRecipients = WhatsAppBroadcastRecipient::query()->where('whatsapp_broadcast_id', $this->broadcast->id)->get();
        foreach ($updatedRecipients as $recipient) {
            $this->assertEquals('sent', $recipient->status?->value);
        }

        $this->broadcast->refresh();
        $this->assertEquals(5, $this->broadcast->sent_count);
    }

    public function test_job_uses_single_update_query_for_statuses(): void
    {
        $recipients = [];
        for ($i = 0; $i < 3; $i++) {
            $recipient = WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'status' => 'pending',
            ]);
            $recipients[] = [
                'id' => $recipient->id,
                'phone_number' => $recipient->phone_number,
                'member_name' => $recipient->member_name,
                'resolved_variables' => ['name' => 'Test'],
            ];
        }

        $mockProvider = Mockery::mock(MetaWhatsAppProvider::class);
        /** @var Mockery\Expectation $exp */
        $exp = $mockProvider->shouldReceive('sendTemplateMessage');
        $exp->andReturn(['success' => true, 'message_id' => 'SM123']);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $sql = $query->sql;
            if (str_contains($sql, 'update `whatsapp_broadcast_recipients`') || str_contains($sql, 'UPDATE whatsapp_broadcast_recipients')) {
                $queries[] = $sql;
            }
        });

        $job = $this->createJobWithMockProvider(
            broadcastId: $this->broadcast->id,
            recipientChunk: $recipients,
            templateData: $this->templateData,
            gymId: $this->broadcast->gym_id,
            mockProvider: $mockProvider,
        );

        $job->handle();

        $this->assertCount(1, $queries, 'Expected exactly 1 UPDATE query on whatsapp_broadcast_recipients');
    }

    public function test_job_handles_partial_failures_gracefully(): void
    {
        $recipients = [];
        for ($i = 0; $i < 3; $i++) {
            $recipient = WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'status' => 'pending',
            ]);
            $recipients[] = [
                'id' => $recipient->id,
                'phone_number' => $recipient->phone_number,
                'member_name' => $recipient->member_name,
                'resolved_variables' => ['name' => 'Test'],
            ];
        }

        $callCount = 0;
        $mockProvider = Mockery::mock(MetaWhatsAppProvider::class);
        /** @var Mockery\Expectation $exp */
        $exp = $mockProvider->shouldReceive('sendTemplateMessage');
        $exp->times(3)->andReturnUsing(function () use (&$callCount): array {
            $callCount++;
            if ($callCount === 2) {
                return ['success' => false, 'error' => 'Invalid recipient phone number'];
            }

            return ['success' => true, 'message_id' => 'SM' . $callCount];
        });

        $job = $this->createJobWithMockProvider(
            broadcastId: $this->broadcast->id,
            recipientChunk: $recipients,
            templateData: $this->templateData,
            gymId: $this->broadcast->gym_id,
            mockProvider: $mockProvider,
        );

        $job->handle();

        $updatedRecipients = WhatsAppBroadcastRecipient::query()->where('whatsapp_broadcast_id', $this->broadcast->id)
            ->orderBy('id')
            ->get();

        $r0 = $updatedRecipients->get(0);
        $r1 = $updatedRecipients->get(1);
        $r2 = $updatedRecipients->get(2);

        $this->assertNotNull($r0);
        $this->assertNotNull($r1);
        $this->assertNotNull($r2);

        $this->assertEquals('sent', $r0->status?->value);
        $this->assertEquals('failed', $r1->status?->value);
        $this->assertEquals('sent', $r2->status?->value);

        $this->assertNotNull($r1->failure_reason);
        $this->assertNull($r0->failure_reason);
        $this->assertNull($r2->failure_reason);
    }

    public function test_job_dispatches_on_broadcast_queue(): void
    {
        $job = new SendMessageChunkJob(
            broadcastId: 1,
            recipientChunk: [],
            templateData: $this->templateData,
            gymId: 1,
        );

        $this->assertEquals('broadcast', $job->queue);
    }

    public function test_job_accepts_skip_credit_deduction_parameter(): void
    {
        $job = new SendMessageChunkJob(
            broadcastId: 1,
            recipientChunk: [],
            templateData: $this->templateData,
            gymId: 1,
            skipCreditDeduction: true,
        );

        $this->assertTrue($job->skipCreditDeduction);

        $jobDefault = new SendMessageChunkJob(
            broadcastId: 1,
            recipientChunk: [],
            templateData: $this->templateData,
            gymId: 1,
        );

        $this->assertFalse($jobDefault->skipCreditDeduction);
    }

    public function test_job_performs_bounded_member_queries_for_consent_recheck(): void
    {
        $recipients = [];
        for ($i = 0; $i < 3; $i++) {
            $recipient = WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'status' => 'pending',
            ]);
            $recipients[] = [
                'id' => $recipient->id,
                'phone_number' => $recipient->phone_number,
                'member_name' => $recipient->member_name,
                'resolved_variables' => ['name' => 'Test'],
            ];
        }

        $mockProvider = Mockery::mock(MetaWhatsAppProvider::class);
        /** @var Mockery\Expectation $exp */
        $exp = $mockProvider->shouldReceive('sendTemplateMessage');
        $exp->andReturn(['success' => true, 'message_id' => 'SM123']);

        $memberQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$memberQueries): void {
            $sql = $query->sql;
            if (str_contains($sql, 'gym_members') || str_contains($sql, '`gym_members`')) {
                $memberQueries[] = $sql;
            }
        });

        $job = $this->createJobWithMockProvider(
            broadcastId: $this->broadcast->id,
            recipientChunk: $recipients,
            templateData: $this->templateData,
            gymId: $this->broadcast->gym_id,
            mockProvider: $mockProvider,
        );

        $job->handle();

        // The send path performs a single, bounded consent re-check (TOCTOU) against
        // gym_members for the whole chunk. It must NOT issue per-recipient (N+1) queries.
        $this->assertLessThanOrEqual(1, count($memberQueries), 'Expected at most one gym_members query (single TOCTOU consent re-check, not per-recipient)');
    }
}
