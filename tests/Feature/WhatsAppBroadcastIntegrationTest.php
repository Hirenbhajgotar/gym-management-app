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
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Testing\Fakes\PendingBatchFake;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class WhatsAppBroadcastIntegrationTest extends TestCase
{
    use DatabaseTransactions;

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

    #[Test]
    public function it_dispatches_batch_with_correct_chunk_count(): void
    {
        $members = GymMember::factory()->count(150)
            ->sequence(fn (Sequence $sequence) => [
                'phone' => '+1' . str_pad((string) $sequence->index, 10, '0', STR_PAD_LEFT),
            ])
            ->create([
                'gym_id' => $this->gym->id,
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

        /** @var PendingBatchFake|null $capturedBatch */
        $capturedBatch = null;
        Bus::assertBatched(function (PendingBatchFake $batch) use (&$capturedBatch): bool {
            $capturedBatch = $batch;

            return true;
        });

        assert($capturedBatch instanceof PendingBatchFake);

        /** @var Collection<int, object> $jobs */
        $jobs = $capturedBatch->jobs;

        $this->assertCount(3, $jobs);
        $this->assertContainsOnlyInstancesOf(SendMessageChunkJob::class, $jobs);

        $this->broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::SENDING, $this->broadcast->status);
        $this->assertNotNull($this->broadcast->batch_id);
        $this->assertNotNull($this->broadcast->started_at);
    }

    #[Test]
    public function it_chunk_jobs_contain_correct_data(): void
    {
        $members = GymMember::factory()->count(3)
            ->sequence(fn (Sequence $sequence) => [
                'phone' => '+1' . str_pad((string) $sequence->index, 10, '0', STR_PAD_LEFT),
            ])
            ->create([
                'gym_id' => $this->gym->id,
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

        /** @var PendingBatchFake|null $capturedBatch */
        $capturedBatch = null;
        Bus::assertBatched(function (PendingBatchFake $batch) use (&$capturedBatch): bool {
            $capturedBatch = $batch;

            return true;
        });

        assert($capturedBatch instanceof PendingBatchFake);

        /** @var Collection<int, SendMessageChunkJob> $jobs */
        $jobs = $capturedBatch->jobs;

        $this->assertCount(1, $jobs);
        /** @var SendMessageChunkJob $chunkJob */
        $chunkJob = $jobs->first();

        $this->assertSame($this->broadcast->id, $chunkJob->broadcastId);
        $this->assertSame($this->broadcast->gym_id, $chunkJob->gymId);
        $this->assertSame('test_template_name', $chunkJob->templateData['provider_template_id']);
        $this->assertSame('en_US', $chunkJob->templateData['language']);
        $this->assertCount(3, $chunkJob->recipientChunk);

        /** @var array<string, mixed> $recipient */
        $recipient = $chunkJob->recipientChunk[0];
        $this->assertArrayHasKey('id', $recipient);
        $this->assertArrayHasKey('phone_number', $recipient);
        $this->assertArrayHasKey('member_name', $recipient);
        $this->assertArrayHasKey('resolved_variables', $recipient);
        $this->assertIsArray($recipient['resolved_variables']);
    }

    #[Test]
    public function it_skips_batch_when_broadcast_already_completed(): void
    {
        $this->broadcast->update(['status' => WhatsAppBroadcastStatusEnum::COMPLETED]);

        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        Bus::assertBatchCount(0);
    }

    #[Test]
    public function it_skips_batch_when_broadcast_already_cancelled(): void
    {
        $this->broadcast->update(['status' => WhatsAppBroadcastStatusEnum::CANCELLED]);

        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        Bus::assertBatchCount(0);
    }

    #[Test]
    public function it_marks_broadcast_completed_when_no_pending_recipients(): void
    {
        Bus::fake();

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->handle();

        Bus::assertBatchCount(0);

        $this->broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED, $this->broadcast->status);
        $this->assertNotNull($this->broadcast->completed_at);
    }

    #[Test]
    public function it_failed_method_sets_broadcast_status_to_failed(): void
    {
        $exception = new RuntimeException('Test failure');
        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->failed($exception);

        $this->broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $this->broadcast->status);
    }

    #[Test]
    public function it_early_return_when_broadcast_not_found(): void
    {
        Bus::fake();

        $job = new SendBroadcastJob(99999, 1);
        $job->handle();

        Bus::assertBatchCount(0);
    }

    #[Test]
    public function it_assigns_batch_jobs_to_broadcast_queue(): void
    {
        $members = GymMember::factory()->count(3)
            ->sequence(fn (Sequence $sequence) => [
                'phone' => '+1' . str_pad((string) $sequence->index, 10, '0', STR_PAD_LEFT),
            ])
            ->create([
                'gym_id' => $this->gym->id,
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

        /** @var PendingBatchFake|null $capturedBatch */
        $capturedBatch = null;
        Bus::assertBatched(function (PendingBatchFake $batch) use (&$capturedBatch): bool {
            $capturedBatch = $batch;

            return true;
        });

        assert($capturedBatch instanceof PendingBatchFake);

        /** @var Collection<int, SendMessageChunkJob> $jobs */
        $jobs = $capturedBatch->jobs;

        $this->assertCount(1, $jobs);
        /** @var SendMessageChunkJob $chunkJob */
        $chunkJob = $jobs->first();
        $this->assertSame('broadcast', $chunkJob->queue);
    }

    #[Test]
    public function it_handles_exactly_chunk_size_multiple(): void
    {
        $members = GymMember::factory()->count(50)
            ->sequence(fn (Sequence $sequence) => [
                'phone' => '+1' . str_pad((string) $sequence->index, 10, '0', STR_PAD_LEFT),
            ])
            ->create([
                'gym_id' => $this->gym->id,
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
        Bus::assertBatched(function (PendingBatchFake $batch): bool {
            /** @var Collection<int, SendMessageChunkJob> $jobs */
            $jobs = $batch->jobs;

            return $jobs->count() === 1;
        });
    }

    #[Test]
    public function it_handles_remainder_chunk(): void
    {
        $members = GymMember::factory()->count(120)
            ->sequence(fn (Sequence $sequence) => [
                'phone' => '+1' . str_pad((string) $sequence->index, 10, '0', STR_PAD_LEFT),
            ])
            ->create([
                'gym_id' => $this->gym->id,
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
        Bus::assertBatched(function (PendingBatchFake $batch): bool {
            /** @var Collection<int, SendMessageChunkJob> $jobs */
            $jobs = $batch->jobs;

            return $jobs->count() === 3;
        });

        /** @var PendingBatchFake|null $capturedBatch */
        $capturedBatch = null;
        Bus::assertBatched(function (PendingBatchFake $batch) use (&$capturedBatch): bool {
            $capturedBatch = $batch;

            return true;
        });

        assert($capturedBatch instanceof PendingBatchFake);

        /** @var Collection<int, SendMessageChunkJob> $jobs */
        $jobs = $capturedBatch->jobs;

        /** @var SendMessageChunkJob $lastJob */
        $lastJob = $jobs->last();
        $this->assertCount(20, $lastJob->recipientChunk);
    }

    #[Test]
    public function it_propagates_skip_credit_deduction_to_chunk_jobs(): void
    {
        $members = GymMember::factory()->count(10)
            ->sequence(fn (Sequence $sequence) => [
                'phone' => '+1' . str_pad((string) $sequence->index, 10, '0', STR_PAD_LEFT),
            ])
            ->create([
                'gym_id' => $this->gym->id,
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

        /** @var PendingBatchFake|null $capturedBatch */
        $capturedBatch = null;
        Bus::assertBatched(function (PendingBatchFake $batch) use (&$capturedBatch): bool {
            $capturedBatch = $batch;

            return true;
        });

        assert($capturedBatch instanceof PendingBatchFake);

        /** @var Collection<int, SendMessageChunkJob> $jobs */
        $jobs = $capturedBatch->jobs;

        $this->assertCount(1, $jobs);
        /** @var SendMessageChunkJob $chunkJob */
        $chunkJob = $jobs->first();
        $this->assertTrue($chunkJob->skipCreditDeduction);
    }

    #[Test]
    public function it_marks_as_sending_only_on_pending_broadcast(): void
    {
        $members = GymMember::factory()->count(3)
            ->sequence(fn (Sequence $sequence) => [
                'phone' => '+1' . str_pad((string) $sequence->index, 10, '0', STR_PAD_LEFT),
            ])
            ->create([
                'gym_id' => $this->gym->id,
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

    #[Test]
    public function it_refunds_upfront_credits_and_restores_welcome_credits_when_batch_never_dispatched(): void
    {
        $this->broadcast->update([
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'target_count' => 10,
            'credits_used' => 10,
            'batch_id' => null,
        ]);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 10,
            'remaining_credits' => 90,
            'welcome_credits_used' => 4,
        ]);

        $job = new SendBroadcastJob(
            broadcastId: $this->broadcast->id,
            gymId: $this->broadcast->gym_id,
            skipCreditDeduction: true,
        );
        $job->failed(new RuntimeException('Dispatch failed'));

        $this->broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $this->broadcast->status);
        $this->assertSame(0, $this->broadcast->credits_used);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);
        $this->assertSame(100, $credit->remaining_credits);
        $this->assertSame(0, $credit->used_credits);
        $this->assertSame(0, $credit->welcome_credits_used);
    }

    #[Test]
    public function it_does_not_refund_when_batch_was_dispatched(): void
    {
        $this->broadcast->update([
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'target_count' => 10,
            'credits_used' => 10,
            'batch_id' => 'batch-123',
        ]);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 10,
            'remaining_credits' => 90,
            'welcome_credits_used' => 4,
        ]);

        $job = new SendBroadcastJob(
            broadcastId: $this->broadcast->id,
            gymId: $this->broadcast->gym_id,
            skipCreditDeduction: true,
        );
        $job->failed(new RuntimeException('Dispatch failed'));

        $this->broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $this->broadcast->status);
        $this->assertSame(10, $this->broadcast->credits_used);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);
        $this->assertSame(90, $credit->remaining_credits);
        $this->assertSame(10, $credit->used_credits);
        $this->assertSame(4, $credit->welcome_credits_used);
    }

    #[Test]
    public function it_does_not_refund_when_credits_deducted_per_message(): void
    {
        $this->broadcast->update([
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'target_count' => 10,
            'credits_used' => 10,
        ]);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 10,
            'remaining_credits' => 90,
            'welcome_credits_used' => 4,
        ]);

        $job = new SendBroadcastJob($this->broadcast->id, $this->broadcast->gym_id);
        $job->failed(new RuntimeException('Dispatch failed'));

        $this->broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $this->broadcast->status);
        $this->assertSame(10, $this->broadcast->credits_used);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);
        $this->assertSame(90, $credit->remaining_credits);
        $this->assertSame(10, $credit->used_credits);
        $this->assertSame(4, $credit->welcome_credits_used);
    }

    #[Test]
    public function it_refunds_failed_count_and_restores_welcome_credits(): void
    {
        $this->broadcast->update([
            'status' => WhatsAppBroadcastStatusEnum::FAILED,
            'target_count' => 10,
            'sent_count' => 7,
            'failed_count' => 3,
            'credits_used' => 10,
        ]);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 10,
            'remaining_credits' => 90,
            'welcome_credits_used' => 2,
        ]);

        $job = new SendBroadcastJob(
            broadcastId: $this->broadcast->id,
            gymId: $this->broadcast->gym_id,
            skipCreditDeduction: true,
        );
        $job->refundFailedUpfrontCredits($this->broadcast->gym_id, $this->broadcast->id, skipCreditDeduction: true);

        $this->broadcast->refresh();
        $this->assertSame(7, $this->broadcast->credits_used);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);
        $this->assertSame(93, $credit->remaining_credits);
        $this->assertSame(7, $credit->used_credits);
        $this->assertSame(0, $credit->welcome_credits_used);
    }

    #[Test]
    public function it_skips_upfront_refund_when_broadcast_not_failed(): void
    {
        $this->broadcast->update([
            'status' => WhatsAppBroadcastStatusEnum::COMPLETED,
            'target_count' => 10,
            'sent_count' => 10,
            'failed_count' => 3,
            'credits_used' => 10,
        ]);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 10,
            'remaining_credits' => 90,
            'welcome_credits_used' => 2,
        ]);

        $job = new SendBroadcastJob(
            broadcastId: $this->broadcast->id,
            gymId: $this->broadcast->gym_id,
            skipCreditDeduction: true,
        );
        $job->refundFailedUpfrontCredits($this->broadcast->gym_id, $this->broadcast->id, skipCreditDeduction: true);

        $this->broadcast->refresh();
        $this->assertSame(10, $this->broadcast->credits_used);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);
        $this->assertSame(90, $credit->remaining_credits);
        $this->assertSame(10, $credit->used_credits);
        $this->assertSame(2, $credit->welcome_credits_used);
    }

    #[Test]
    public function it_skips_upfront_refund_when_no_failures(): void
    {
        $this->broadcast->update([
            'status' => WhatsAppBroadcastStatusEnum::FAILED,
            'target_count' => 10,
            'sent_count' => 10,
            'failed_count' => 0,
            'credits_used' => 10,
        ]);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 10,
            'remaining_credits' => 90,
            'welcome_credits_used' => 2,
        ]);

        $job = new SendBroadcastJob(
            broadcastId: $this->broadcast->id,
            gymId: $this->broadcast->gym_id,
            skipCreditDeduction: true,
        );
        $job->refundFailedUpfrontCredits($this->broadcast->gym_id, $this->broadcast->id, skipCreditDeduction: true);

        $this->broadcast->refresh();
        $this->assertSame(10, $this->broadcast->credits_used);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);
        $this->assertSame(90, $credit->remaining_credits);
        $this->assertSame(10, $credit->used_credits);
        $this->assertSame(2, $credit->welcome_credits_used);
    }
}
