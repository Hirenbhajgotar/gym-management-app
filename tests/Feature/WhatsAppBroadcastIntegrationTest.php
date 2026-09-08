<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Exceptions\BroadcastConnectionException;
use App\Jobs\SendBroadcastJob;
use App\Jobs\SendMessageChunkJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Testing\Fakes\PendingBatchFake;
use Mockery;
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
            'used_credits' => 6,
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
        $this->assertSame(10, $this->broadcast->refunded_credits);

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
            'used_credits' => 8,
            'remaining_credits' => 90,
            'welcome_credits_used' => 2,
        ]);

        $job = new SendBroadcastJob(
            broadcastId: $this->broadcast->id,
            gymId: $this->broadcast->gym_id,
            skipCreditDeduction: true,
        );
        $job->refundFailedUpfrontCredits($this->broadcast->gym_id, $this->broadcast->id, creditsWereDeductedUpfront: true);

        $this->broadcast->refresh();
        $this->assertSame(10, $this->broadcast->credits_used);
        $this->assertSame(3, $this->broadcast->refunded_credits);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);
        $this->assertSame(93, $credit->remaining_credits);
        $this->assertSame(7, $credit->used_credits);
        $this->assertSame(0, $credit->welcome_credits_used);
    }

    #[Test]
    public function it_refunds_upfront_credits_when_broadcast_completed_with_failures(): void
    {
        $this->broadcast->update([
            'status' => WhatsAppBroadcastStatusEnum::COMPLETED,
            'target_count' => 10,
            'sent_count' => 7,
            'failed_count' => 3,
            'credits_used' => 10,
        ]);

        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 8,
            'remaining_credits' => 90,
            'welcome_credits_used' => 2,
        ]);

        $job = new SendBroadcastJob(
            broadcastId: $this->broadcast->id,
            gymId: $this->broadcast->gym_id,
            skipCreditDeduction: true,
        );
        $job->refundFailedUpfrontCredits($this->broadcast->gym_id, $this->broadcast->id, creditsWereDeductedUpfront: true);

        $this->broadcast->refresh();
        $this->assertSame(10, $this->broadcast->credits_used);
        $this->assertSame(3, $this->broadcast->refunded_credits);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);
        $this->assertSame(93, $credit->remaining_credits);
        $this->assertSame(7, $credit->used_credits);
        $this->assertSame(0, $credit->welcome_credits_used);
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
        $job->refundFailedUpfrontCredits($this->broadcast->gym_id, $this->broadcast->id, creditsWereDeductedUpfront: true);

        $this->broadcast->refresh();
        $this->assertSame(10, $this->broadcast->credits_used);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);
        $this->assertSame(90, $credit->remaining_credits);
        $this->assertSame(10, $credit->used_credits);
        $this->assertSame(2, $credit->welcome_credits_used);
    }

    #[Test]
    public function it_handles_multi_chunk_broadcast_where_one_fails_and_another_succeeds_correctly(): void
    {
        // Setup gym credits
        WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 4,
            'remaining_credits' => 96,
            'welcome_credits_used' => 0,
        ]);

        // Create 4 members (2 chunks of size 2)
        $members = GymMember::factory()->count(4)->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        // Create 4 recipients
        $recipients = [];
        foreach ($members as $i => $member) {
            $recipients[] = WhatsAppBroadcastRecipient::factory()->create([
                'whatsapp_broadcast_id' => $this->broadcast->id,
                'member_id' => $member->id,
                'phone_number' => $member->phone ?? '+1555555555' . $i,
                'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
            ]);
        }

        // Configure broadcast for 4 target recipients with upfront deduction
        $this->broadcast->update([
            'target_count' => 4,
            'credits_used' => 4,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $phoneNumber = '+155510000001';
        WhatsAppPhoneNumber::query()->where('phone_number', $phoneNumber)->delete();

        // Mock Meta API phone details config in database for pool fallback detection
        $account = WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'waba_id' => 'WABA_999888_0001',
            'meta_access_token' => 'EAAB_TEST_TOKEN',
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
        ]);
        WhatsAppPhoneNumber::query()->create([
            'whatsapp_account_id' => $account->id,
            'waba_phone_number_id' => 'PHONE_ID_123_0001',
            'phone_number' => $phoneNumber,
            'country_code' => 'US',
            'friendly_name' => $phoneNumber,
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
        ]);

        // Fake HTTP responses:
        // Chunk 1 has recipients 0 and 1. Force both to fail with ConnectionException (timed out).
        // Chunk 2 has recipients 2 and 3. Let both succeed.
        Http::fake(function (Request $request) use ($recipients) {
            $to = $request->data()['to'] ?? '';
            $toStr = is_string($to) ? $to : '';

            throw_if($toStr === $recipients[0]->phone_number || $toStr === $recipients[1]->phone_number, ConnectionException::class, 'Connection timed out');

            return Http::response(['messages' => [['id' => 'wamid.success.' . md5($toStr)]]], 200);
        });

        // Run chunk 1 job manually. Since it fails with ConnectionException, we want to simulate
        // the last attempt (attempts = 3) to assert that recipients are marked as failed.
        $member0 = $members[0] ?? null;
        $member1 = $members[1] ?? null;
        $member2 = $members[2] ?? null;
        $member3 = $members[3] ?? null;
        assert($member0 instanceof GymMember);
        assert($member1 instanceof GymMember);
        assert($member2 instanceof GymMember);
        assert($member3 instanceof GymMember);

        $job1 = new SendMessageChunkJob(
            broadcastId: $this->broadcast->id,
            recipientChunk: [
                [
                    'id' => $recipients[0]->id,
                    'phone_number' => $recipients[0]->phone_number,
                    'member_name' => $member0->name,
                    'resolved_variables' => [],
                ],
                [
                    'id' => $recipients[1]->id,
                    'phone_number' => $recipients[1]->phone_number,
                    'member_name' => $member1->name,
                    'resolved_variables' => [],
                ],
            ],
            templateData: [
                'provider_template_id' => 'my_template',
                'language' => 'en_US',
                'components' => [],
                'media_urls' => [],
            ],
            gymId: $this->broadcast->gym_id,
            skipCreditDeduction: true,
        );

        /** @var Job&Mockery\MockInterface $mockQueueJob */
        $mockQueueJob = Mockery::mock(Job::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $mockQueueJob->shouldReceive('attempts');
        $expectation->andReturn(3);
        $job1->setJob($mockQueueJob);

        try {
            $job1->handle();
        } catch (BroadcastConnectionException $e) {
            // Expected propagation of connection exception
        }

        // Run chunk 2 job manually. It succeeds.
        $job2 = new SendMessageChunkJob(
            broadcastId: $this->broadcast->id,
            recipientChunk: [
                [
                    'id' => $recipients[2]->id,
                    'phone_number' => $recipients[2]->phone_number,
                    'member_name' => $member2->name,
                    'resolved_variables' => [],
                ],
                [
                    'id' => $recipients[3]->id,
                    'phone_number' => $recipients[3]->phone_number,
                    'member_name' => $member3->name,
                    'resolved_variables' => [],
                ],
            ],
            templateData: [
                'provider_template_id' => 'my_template',
                'language' => 'en_US',
                'components' => [],
                'media_urls' => [],
            ],
            gymId: $this->broadcast->gym_id,
            skipCreditDeduction: true,
        );

        $job2->handle();

        // Simulate Batch Catch block callback: marks broadcast failed
        $this->broadcast->update(['status' => WhatsAppBroadcastStatusEnum::FAILED]);

        // Simulate Batch Finally block callback: refunds failed upfront credits
        $refundJob = new SendBroadcastJob(
            broadcastId: $this->broadcast->id,
            gymId: $this->broadcast->gym_id,
            skipCreditDeduction: true,
        );
        $refundJob->refundFailedUpfrontCredits($this->broadcast->gym_id, $this->broadcast->id, true);

        // Refresh broadcast to verify states
        $this->broadcast->refresh();

        // 1. Assert chunk 2 succeeded (2 recipients marked sent)
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipients[2]->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipients[3]->id,
            'status' => 'sent',
        ]);

        // 2. Assert chunk 1 terminally failed on its 3rd attempt
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipients[0]->id,
            'status' => 'failed',
            'failure_reason' => 'Connection timed out',
        ]);
        $this->assertDatabaseHas('whatsapp_broadcast_recipients', [
            'id' => $recipients[1]->id,
            'status' => 'failed',
            'failure_reason' => 'Connection timed out',
        ]);

        // 3. Assert broadcast counts: sent_count = 2, failed_count = 2, target_count = 4
        $this->assertSame(2, $this->broadcast->sent_count);
        $this->assertSame(2, $this->broadcast->failed_count);

        // 4. Assert broadcast status is FAILED
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $this->broadcast->status);

        // 5. Assert upfront credits refund: 2 failed recipients should be refunded
        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
        assert($credit instanceof WhatsAppMessageCredit);
        $this->assertSame(98, $credit->remaining_credits);
        $this->assertSame(2, $credit->used_credits);
    }

    #[Test]
    public function it_refunds_failed_credits_end_to_end_on_batch_completion_using_sync_driver(): void
    {
        $this->withQueueDriver('sync', function (): void {
            WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->delete();
            WhatsAppMessageCredit::query()->create([
                'gym_id' => $this->gym->id,
                'total_credits' => 100,
                'used_credits' => 2,
                'remaining_credits' => 98,
                'welcome_credits_used' => 0,
            ]);

            $members = GymMember::factory()->count(2)->create([
                'gym_id' => $this->gym->id,
                'is_archived' => false,
                'allow_whatsapp_marketing' => true,
            ]);

            $recipients = [];
            foreach ($members as $i => $member) {
                $recipients[] = WhatsAppBroadcastRecipient::factory()->create([
                    'whatsapp_broadcast_id' => $this->broadcast->id,
                    'member_id' => $member->id,
                    'phone_number' => $member->phone ?? '+1555555555' . $i,
                    'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
                ]);
            }

            $this->broadcast->update([
                'target_count' => 2,
                'credits_used' => 2,
                'status' => WhatsAppBroadcastStatusEnum::SENDING,
            ]);

            $phoneNumber = '+155510000002';
            WhatsAppPhoneNumber::query()->where('phone_number', $phoneNumber)->delete();

            $account = WhatsAppAccount::query()->create([
                'gym_id' => $this->gym->id,
                'waba_id' => 'WABA_999888_0002',
                'meta_access_token' => 'EAAB_TEST_TOKEN',
                'provider_type' => WhatsAppProviderTypeEnum::WABA,
                'status' => WhatsAppAccountStatusEnum::ACTIVE,
            ]);
            WhatsAppPhoneNumber::query()->create([
                'whatsapp_account_id' => $account->id,
                'waba_phone_number_id' => 'PHONE_ID_123_0002',
                'phone_number' => $phoneNumber,
                'country_code' => 'US',
                'friendly_name' => $phoneNumber,
                'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
                'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
            ]);

            // Mock Http to make 1 succeed and 1 fail terminally with a 400 response
            Http::fake(function (Request $request) use ($recipients) {
                $to = $request->data()['to'] ?? '';
                if ($to === $recipients[0]->phone_number) {
                    return Http::response(['messages' => [['id' => 'wamid.success']]], 200);
                }

                return Http::response([
                    'error' => [
                        'message' => 'Some terminal error',
                        'code' => 100,
                    ],
                ], 400);
            });

            // Dispatches the batch synchronously
            $job = new SendBroadcastJob(
                broadcastId: $this->broadcast->id,
                gymId: $this->broadcast->gym_id,
                skipCreditDeduction: true,
            );
            $job->handle();

            // Refresh and check status and credits
            $this->broadcast->refresh();

            // The broadcast completed with errors
            $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED_WITH_ERRORS, $this->broadcast->status);
            $this->assertSame(1, $this->broadcast->sent_count);
            $this->assertSame(1, $this->broadcast->failed_count);

            // 1 failed recipient should have credit refunded
            $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
            assert($credit instanceof WhatsAppMessageCredit);
            $this->assertSame(99, $credit->remaining_credits);
            $this->assertSame(1, $credit->used_credits);
            $this->assertSame(2, $this->broadcast->credits_used);
            $this->assertSame(1, $this->broadcast->refunded_credits);
        });
    }

    #[Test]
    public function it_refunds_failed_credits_end_to_end_on_connection_failure_using_database_driver(): void
    {
        $this->withQueueDriver('database', function (): void {
            DB::table('jobs')->delete();
            WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->delete();

            WhatsAppMessageCredit::query()->create([
                'gym_id' => $this->gym->id,
                'total_credits' => 100,
                'used_credits' => 2,
                'remaining_credits' => 98,
                'welcome_credits_used' => 0,
            ]);

            $members = GymMember::factory()->count(2)->create([
                'gym_id' => $this->gym->id,
                'is_archived' => false,
                'allow_whatsapp_marketing' => true,
            ]);

            $recipients = [];
            foreach ($members as $i => $member) {
                $recipients[] = WhatsAppBroadcastRecipient::factory()->create([
                    'whatsapp_broadcast_id' => $this->broadcast->id,
                    'member_id' => $member->id,
                    'phone_number' => $member->phone ?? '+1555555555' . $i,
                    'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
                ]);
            }

            $this->broadcast->update([
                'target_count' => 2,
                'credits_used' => 2,
                'status' => WhatsAppBroadcastStatusEnum::SENDING,
            ]);

            $phoneNumber = '+155510000003';
            WhatsAppPhoneNumber::query()->where('phone_number', $phoneNumber)->delete();

            $account = WhatsAppAccount::query()->create([
                'gym_id' => $this->gym->id,
                'waba_id' => 'WABA_999888_0003',
                'meta_access_token' => 'EAAB_TEST_TOKEN',
                'provider_type' => WhatsAppProviderTypeEnum::WABA,
                'status' => WhatsAppAccountStatusEnum::ACTIVE,
            ]);
            WhatsAppPhoneNumber::query()->create([
                'whatsapp_account_id' => $account->id,
                'waba_phone_number_id' => 'PHONE_ID_123_0003',
                'phone_number' => $phoneNumber,
                'country_code' => 'US',
                'friendly_name' => $phoneNumber,
                'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
                'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
            ]);

            // Mock Http to simulate:
            // Attempt 1: Recipient 0 succeeds, Recipient 1 fails with connection timeout
            // Attempt 2: Recipient 1 fails with connection timeout
            // Attempt 3: Recipient 1 fails with connection timeout
            Http::fake([
                'https://graph.facebook.com/*' => Http::sequence()
                    ->push(['messages' => [['id' => 'wamid.success']]], 200) // Attempt 1: Recipient 0 succeeds
                    ->pushFailedConnection('Connection timed out') // Attempt 1: Recipient 1 fails
                    ->pushFailedConnection('Connection timed out') // Attempt 2: Recipient 1 fails
                    ->pushFailedConnection('Connection timed out'), // Attempt 3: Recipient 1 fails (terminal)
            ]);

            // Dispatches the batch to database queue
            $job = new SendBroadcastJob(
                broadcastId: $this->broadcast->id,
                gymId: $this->broadcast->gym_id,
                skipCreditDeduction: true,
            );
            $job->handle();

            $initialNow = now();

            try {
                // Run Attempt 1
                $this->artisan('queue:work', ['--once' => true, '--queue' => 'broadcast']);

                // Run Attempt 2 (backoff attempt 1 is 10s -> advance to +12s)
                Date::setTestNow($initialNow->copy()->addSeconds(12));
                $this->artisan('queue:work', ['--once' => true, '--queue' => 'broadcast']);

                // Run Attempt 3 (terminal: backoff attempt 2 is 30s -> advance to +48s)
                Date::setTestNow($initialNow->copy()->addSeconds(48));
                $this->artisan('queue:work', ['--once' => true, '--queue' => 'broadcast']);
            } finally {
                Date::setTestNow();
            }

            // Refresh and check status and credits
            $this->broadcast->refresh();

            // The broadcast completed with errors
            $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED_WITH_ERRORS, $this->broadcast->status);
            $this->assertSame(1, $this->broadcast->sent_count);
            $this->assertSame(1, $this->broadcast->failed_count);

            // 1 failed recipient should have credit refunded
            $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
            assert($credit instanceof WhatsAppMessageCredit);
            $this->assertSame(99, $credit->remaining_credits);
            $this->assertSame(1, $credit->used_credits);
            $this->assertSame(2, $this->broadcast->credits_used);
            $this->assertSame(1, $this->broadcast->refunded_credits);
        });
    }

    #[Test]
    public function it_isolates_broadcast_sending_to_the_correct_gym_and_broadcast(): void
    {
        // Setup Gym B and Broadcast B
        $gymB = Gym::factory()->create();
        $templateB = WhatsAppTemplate::factory()->create([
            'gym_id' => $gymB->id,
            'name' => 'template_b',
            'is_active' => true,
        ]);
        $broadcastB = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gymB->id,
            'whatsapp_template_id' => $templateB->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);

        $memberA = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);
        $memberB = GymMember::factory()->create([
            'gym_id' => $gymB->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $recipientA = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $this->broadcast->id,
            'member_id' => $memberA->id,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        $recipientB = WhatsAppBroadcastRecipient::factory()->create([
            'whatsapp_broadcast_id' => $broadcastB->id,
            'member_id' => $memberB->id,
            'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
        ]);

        Bus::fake();

        // Dispatch SendBroadcastJob for Gym A
        $job = new SendBroadcastJob($this->broadcast->id, $this->gym->id);
        $job->handle();

        Bus::assertBatchCount(1);
        Bus::assertBatched(function (PendingBatchFake $batch) use ($recipientA, $recipientB): bool {
            $jobs = $batch->jobs;
            $this->assertCount(1, $jobs);
            $chunkJob = $jobs->first();
            assert($chunkJob instanceof SendMessageChunkJob);

            // Should contain recipient A
            $ids = array_column($chunkJob->recipientChunk, 'id');
            $this->assertContains($recipientA->id, $ids);

            // Should NOT contain recipient B
            $this->assertNotContains($recipientB->id, $ids);

            return true;
        });
    }

    #[Test]
    public function it_ensures_refund_failed_upfront_credits_is_idempotent(): void
    {
        // Setup credit balance
        WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->delete();
        $credit = WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 10,
            'used_credits' => 5,
            'remaining_credits' => 5,
            'welcome_credits_used' => 0,
        ]);
        $this->broadcast->update([
            'status' => WhatsAppBroadcastStatusEnum::FAILED,
            'credits_used' => 5,
            'failed_count' => 3,
            'sent_count' => 2,
        ]);

        // First call should refund 3 credits
        SendBroadcastJob::refundFailedUpfrontCredits($this->gym->id, $this->broadcast->id, true);

        $this->broadcast->refresh();
        $credit->refresh();

        // 3 credits should have been refunded
        $this->assertEquals(8, $credit->remaining_credits);
        $this->assertEquals(2, $credit->used_credits);
        $this->assertEquals(5, $this->broadcast->credits_used);
        $this->assertEquals(3, $this->broadcast->refunded_credits);

        // Second call should not refund anything because credits_used - sent_count - refunded_credits returns 0
        SendBroadcastJob::refundFailedUpfrontCredits($this->gym->id, $this->broadcast->id, true);

        $credit->refresh();
        $this->broadcast->refresh();
        $this->assertEquals(8, $credit->remaining_credits);
        $this->assertEquals(2, $credit->used_credits);
        $this->assertEquals(5, $this->broadcast->credits_used);
        $this->assertEquals(3, $this->broadcast->refunded_credits);
    }

    #[Test]
    public function it_refunds_failed_credits_on_rate_limit_failure_end_to_end_using_database_driver(): void
    {
        $this->withQueueDriver('database', function (): void {
            DB::table('jobs')->delete();
            WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->delete();

            WhatsAppMessageCredit::query()->create([
                'gym_id' => $this->gym->id,
                'total_credits' => 100,
                'used_credits' => 2,
                'remaining_credits' => 98,
                'welcome_credits_used' => 0,
            ]);

            $members = GymMember::factory()->count(2)->create([
                'gym_id' => $this->gym->id,
                'is_archived' => false,
                'allow_whatsapp_marketing' => true,
            ]);

            $recipients = [];
            foreach ($members as $i => $member) {
                $recipients[] = WhatsAppBroadcastRecipient::factory()->create([
                    'whatsapp_broadcast_id' => $this->broadcast->id,
                    'member_id' => $member->id,
                    'phone_number' => $member->phone ?? '+1555555555' . $i,
                    'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING,
                ]);
            }

            $this->broadcast->update([
                'target_count' => 2,
                'credits_used' => 2,
                'status' => WhatsAppBroadcastStatusEnum::SENDING,
            ]);

            $phoneNumber = '+155510000004';
            WhatsAppPhoneNumber::query()->where('phone_number', $phoneNumber)->delete();

            $account = WhatsAppAccount::query()->create([
                'gym_id' => $this->gym->id,
                'waba_id' => 'WABA_999888_0004',
                'meta_access_token' => 'EAAB_TEST_TOKEN',
                'provider_type' => WhatsAppProviderTypeEnum::WABA,
                'status' => WhatsAppAccountStatusEnum::ACTIVE,
            ]);
            WhatsAppPhoneNumber::query()->create([
                'whatsapp_account_id' => $account->id,
                'waba_phone_number_id' => 'PHONE_ID_123_0004',
                'phone_number' => $phoneNumber,
                'country_code' => 'US',
                'friendly_name' => $phoneNumber,
                'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
                'type' => WhatsAppPhoneNumberTypesEnum::BUSINESS,
            ]);

            // Mock Http to simulate:
            // Attempt 1: Recipient 0 succeeds, Recipient 1 fails with 429 rate limit
            // Attempt 2: Recipient 1 fails with 429 rate limit
            // Attempt 3: Recipient 1 fails with 429 rate limit (terminal)
            Http::fake([
                'https://graph.facebook.com/*' => Http::sequence()
                    ->push(['messages' => [['id' => 'wamid.success']]], 200) // Attempt 1: Recipient 0 succeeds
                    ->push(['error' => ['message' => 'rate limit exceeded']], 429, ['Retry-After' => 10]) // Attempt 1: Recipient 1 fails
                    ->push(['error' => ['message' => 'rate limit exceeded']], 429, ['Retry-After' => 10]) // Attempt 2: Recipient 1 fails
                    ->push(['error' => ['message' => 'rate limit exceeded']], 429, ['Retry-After' => 10]), // Attempt 3: Recipient 1 fails (terminal)
            ]);

            // Dispatches the batch to database queue
            $job = new SendBroadcastJob(
                broadcastId: $this->broadcast->id,
                gymId: $this->broadcast->gym_id,
                skipCreditDeduction: true,
            );
            $job->handle();

            // Run Attempt 1
            $this->artisan('queue:work', ['--once' => true, '--queue' => 'broadcast']);

            // Run Attempt 2
            Date::setTestNow(now()->addSeconds(12));
            $this->artisan('queue:work', ['--once' => true, '--queue' => 'broadcast']);

            // Run Attempt 3 (terminal)
            Date::setTestNow(now()->addSeconds(24));
            $this->artisan('queue:work', ['--once' => true, '--queue' => 'broadcast']);

            // Refresh and check status and credits
            $this->broadcast->refresh();

            // The broadcast completed with errors
            $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED_WITH_ERRORS, $this->broadcast->status);
            $this->assertSame(1, $this->broadcast->sent_count);
            $this->assertSame(1, $this->broadcast->failed_count);

            // 1 failed recipient should have credit refunded
            $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->gym->id)->first();
            assert($credit instanceof WhatsAppMessageCredit);
            $this->assertSame(99, $credit->remaining_credits);
            $this->assertSame(1, $credit->used_credits);
            $this->assertSame(2, $this->broadcast->credits_used);
            $this->assertSame(1, $this->broadcast->refunded_credits);
        });
    }

    #[Test]
    public function it_asserts_failure_reason_column_is_text_type(): void
    {
        $schema = DB::getSchemaBuilder();
        $type = $schema->getColumnType('whatsapp_broadcast_recipients', 'failure_reason');
        $this->assertSame('text', $type);
    }

    private function withQueueDriver(string $driver, callable $callback): void
    {
        $originalQueue = config('queue.default');
        config(['queue.default' => $driver]);
        $this->refreshQueueInstances();

        try {
            $callback();
        } finally {
            config(['queue.default' => $originalQueue]);
            $this->refreshQueueInstances();
        }
    }

    private function refreshQueueInstances(): void
    {
        app()->forgetInstance('queue');
        app()->forgetInstance('queue.connection');
        app()->forgetInstance('queue.worker');
        app()->forgetInstance(Dispatcher::class);
        app()->forgetInstance(QueueingDispatcher::class);
        app()->forgetInstance(\Illuminate\Bus\Dispatcher::class);
        Facade::clearResolvedInstances();
    }
}
