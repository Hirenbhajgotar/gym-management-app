<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendMessageChunkJob;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Providers\MetaWhatsAppProvider;
use App\Services\Meta\MetaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class WhatsAppBroadcastChunkJobTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppBroadcast $broadcast;

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
        if ($mockProvider instanceof Mockery\LegacyMockInterface) {
            $contextMock = Mockery::mock(MetaContext::class);
            $contextMock->shouldReceive('getWabaId')->andReturn(null);
            $contextMock->shouldReceive('getAccessToken')->andReturn(null);
            $contextMock->shouldReceive('getApiBaseUrl')->andReturn('https://graph.facebook.com/v22.0');
            $mockProvider->shouldReceive('getContext')
                ->andReturn($contextMock);
        }

        $job = Mockery::mock(
            SendMessageChunkJob::class . '[resolveProvider]',
            [$broadcastId, $recipientChunk, $templateData, $gymId, $skipCreditDeduction],
        );
        $job->shouldAllowMockingProtectedMethods();
        $job->shouldReceive('resolveProvider')
            ->once()
            ->andReturn($mockProvider);

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
        $mockProvider->shouldReceive('sendTemplateMessage')
            ->times(5)
            ->andReturn(['success' => true, 'message_id' => 'SM123']);

        $job = $this->createJobWithMockProvider(
            broadcastId: $this->broadcast->id,
            recipientChunk: $recipients,
            templateData: $this->templateData,
            gymId: $this->broadcast->gym_id,
            mockProvider: $mockProvider,
        );

        $job->handle();

        $updatedRecipients = WhatsAppBroadcastRecipient::where('whatsapp_broadcast_id', $this->broadcast->id)->get();
        foreach ($updatedRecipients as $recipient) {
            $this->assertEquals('sent', $recipient->status->value);
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
        $mockProvider->shouldReceive('sendTemplateMessage')
            ->andReturn(['success' => true, 'message_id' => 'SM123']);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
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
        $mockProvider->shouldReceive('sendTemplateMessage')
            ->times(3)
            ->andReturnUsing(function () use (&$callCount): array {
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

        $updatedRecipients = WhatsAppBroadcastRecipient::where('whatsapp_broadcast_id', $this->broadcast->id)
            ->orderBy('id')
            ->get();

        $this->assertEquals('sent', $updatedRecipients[0]->status->value);
        $this->assertEquals('failed', $updatedRecipients[1]->status->value);
        $this->assertEquals('sent', $updatedRecipients[2]->status->value);

        $this->assertNotNull($updatedRecipients[1]->failure_reason);
        $this->assertNull($updatedRecipients[0]->failure_reason);
        $this->assertNull($updatedRecipients[2]->failure_reason);
    }

    public function test_job_dispatches_on_broadcast_queue(): void
    {
        $job = new SendMessageChunkJob(
            broadcastId: 1,
            recipientChunk: [],
            templateData: [],
            gymId: 1,
        );

        $this->assertEquals('broadcast', $job->queue);
    }

    public function test_job_accepts_skip_credit_deduction_parameter(): void
    {
        $job = new SendMessageChunkJob(
            broadcastId: 1,
            recipientChunk: [],
            templateData: [],
            gymId: 1,
            skipCreditDeduction: true,
        );

        $this->assertTrue($job->skipCreditDeduction);

        $jobDefault = new SendMessageChunkJob(
            broadcastId: 1,
            recipientChunk: [],
            templateData: [],
            gymId: 1,
        );

        $this->assertFalse($jobDefault->skipCreditDeduction);
    }

    public function test_job_performs_zero_eloquent_member_queries(): void
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
        $mockProvider->shouldReceive('sendTemplateMessage')
            ->andReturn(['success' => true, 'message_id' => 'SM123']);

        $memberQueries = [];
        DB::listen(function ($query) use (&$memberQueries): void {
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

        $this->assertCount(0, $memberQueries, 'Expected zero queries on gym_members table');
    }
}
