<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Jobs\SendBroadcastJob;
use App\Jobs\SendMessageChunkJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\TestCase;

class WhatsAppBroadcastIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppBroadcast $broadcast;

    private Gym $gym;

    private WhatsAppTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();

        $this->template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'test_template_name',
            'is_active' => true,
        ]);

        $this->broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'target_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
        ]);
    }

    public function test_dispatches_batch_with_correct_chunk_count(): void
    {
        $members = GymMember::factory()->count(150)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+1234567890',
        ]);

        $members->each(function (GymMember $member): void {
            WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'member_id' => $member->id,
                'phone_number' => $member->phone,
                'member_name' => $member->name,
                'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
            ]);
        });

        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        Bus::assertBatchCount(1);

        $capturedBatch = null;
        Bus::assertBatched(function ($batch) use (&$capturedBatch): bool {
            $capturedBatch = $batch;

            return true;
        });

        $this->assertCount(3, $capturedBatch->jobs);
        $this->assertContainsOnlyInstancesOf(SendMessageChunkJob::class, $capturedBatch->jobs);

        $this->broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::SENDING, $this->broadcast->status);
        $this->assertNotNull($this->broadcast->batch_id);
        $this->assertNotNull($this->broadcast->started_at);
    }

    public function test_chunk_jobs_contain_correct_data(): void
    {
        $members = GymMember::factory()->count(3)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+1234567890',
        ]);

        $members->each(function (GymMember $member): void {
            WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'member_id' => $member->id,
                'phone_number' => $member->phone,
                'member_name' => $member->name,
                'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
            ]);
        });

        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        $capturedBatch = null;
        Bus::assertBatched(function ($batch) use (&$capturedBatch): bool {
            $capturedBatch = $batch;

            return true;
        });

        $this->assertCount(1, $capturedBatch->jobs);
        /** @var SendMessageChunkJob $chunkJob */
        $chunkJob = $capturedBatch->jobs->first();

        $this->assertSame($this->broadcast->id, $chunkJob->broadcastId);
        $this->assertSame($this->broadcast->gym_id, $chunkJob->gymId);
        $this->assertSame('test_template_name', $chunkJob->templateData['provider_template_id']);
        $this->assertSame('en', $chunkJob->templateData['language']);
        $this->assertCount(3, $chunkJob->recipientChunk);

        $recipient = $chunkJob->recipientChunk[0];
        $this->assertArrayHasKey('id', $recipient);
        $this->assertArrayHasKey('phone_number', $recipient);
        $this->assertArrayHasKey('member_name', $recipient);
        $this->assertArrayHasKey('resolved_variables', $recipient);
        $this->assertIsArray($recipient['resolved_variables']);
    }

    public function test_skips_batch_when_broadcast_already_completed(): void
    {
        $this->broadcast->update(['status' => WhatsAppBroadcastStatusEnum::COMPLETED]);

        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        Bus::assertBatchCount(0);
    }

    public function test_skips_batch_when_broadcast_already_cancelled(): void
    {
        $this->broadcast->update(['status' => WhatsAppBroadcastStatusEnum::CANCELLED]);

        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        Bus::assertBatchCount(0);
    }

    public function test_marks_broadcast_completed_when_no_pending_recipients(): void
    {
        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        Bus::assertBatchCount(0);

        $this->broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED, $this->broadcast->status);
        $this->assertNotNull($this->broadcast->completed_at);
    }

    public function test_failed_method_sets_broadcast_status_to_failed(): void
    {
        $exception = new RuntimeException('Test failure');
        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->failed($exception);

        $this->broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $this->broadcast->status);
    }

    public function test_early_return_when_broadcast_not_found(): void
    {
        Bus::fake();

        $job = new SendBroadcastJob(99999, 1);
        $job->handle();

        Bus::assertBatchCount(0);
    }

    public function test_batch_jobs_assigned_to_broadcast_queue(): void
    {
        $members = GymMember::factory()->count(3)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+1234567890',
        ]);

        $members->each(function (GymMember $member): void {
            WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'member_id' => $member->id,
                'phone_number' => $member->phone,
                'member_name' => $member->name,
                'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
            ]);
        });

        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        $capturedBatch = null;
        Bus::assertBatched(function ($batch) use (&$capturedBatch): bool {
            $capturedBatch = $batch;

            return true;
        });

        $this->assertCount(1, $capturedBatch->jobs);
        /** @var SendMessageChunkJob $chunkJob */
        $chunkJob = $capturedBatch->jobs->first();
        $this->assertSame('broadcast', $chunkJob->queue);
    }

    public function test_handles_exactly_chunk_size_multiple(): void
    {
        $members = GymMember::factory()->count(50)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+1234567890',
        ]);

        $members->each(function (GymMember $member): void {
            WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'member_id' => $member->id,
                'phone_number' => $member->phone,
                'member_name' => $member->name,
                'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
            ]);
        });

        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        Bus::assertBatchCount(1);
        Bus::assertBatched(fn ($batch): bool => $batch->jobs->count() === 1);
    }

    public function test_handles_remainder_chunk(): void
    {
        $members = GymMember::factory()->count(120)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+1234567890',
        ]);

        $members->each(function (GymMember $member): void {
            WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'member_id' => $member->id,
                'phone_number' => $member->phone,
                'member_name' => $member->name,
                'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
            ]);
        });

        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        Bus::assertBatchCount(1);
        Bus::assertBatched(fn ($batch): bool => $batch->jobs->count() === 3);

        $capturedBatch = null;
        Bus::assertBatched(function ($batch) use (&$capturedBatch): bool {
            $capturedBatch = $batch;

            return true;
        });

        /** @var SendMessageChunkJob $lastJob */
        $lastJob = $capturedBatch->jobs->last();
        $this->assertCount(20, $lastJob->recipientChunk);
    }

    public function test_skip_credit_deduction_propagates_to_chunk_jobs(): void
    {
        $members = GymMember::factory()->count(10)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+1234567890',
        ]);

        $members->each(function (GymMember $member): void {
            WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'member_id' => $member->id,
                'phone_number' => $member->phone,
                'member_name' => $member->name,
                'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
            ]);
        });

        Bus::fake();

        $job = new SendBroadcastJob(
            broadcastId: $this->broadcast->id,
            gymId: $this->broadcast->gym_id,
            skipCreditDeduction: true,
        );
        $job->handle();

        $capturedBatch = null;
        Bus::assertBatched(function ($batch) use (&$capturedBatch): bool {
            $capturedBatch = $batch;

            return true;
        });

        $this->assertCount(1, $capturedBatch->jobs);
        /** @var SendMessageChunkJob $chunkJob */
        $chunkJob = $capturedBatch->jobs->first();
        $this->assertTrue($chunkJob->skipCreditDeduction);
    }

    public function test_mark_as_sending_only_on_pending_broadcast(): void
    {
        $members = GymMember::factory()->count(3)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+1234567890',
        ]);

        $members->each(function (GymMember $member): void {
            WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'member_id' => $member->id,
                'phone_number' => $member->phone,
                'member_name' => $member->name,
                'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
            ]);
        });

        $this->broadcast->update(['status' => WhatsAppBroadcastStatusEnum::SENDING]);

        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        Bus::assertBatchCount(1);

        $this->broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::SENDING, $this->broadcast->status);
    }
}
