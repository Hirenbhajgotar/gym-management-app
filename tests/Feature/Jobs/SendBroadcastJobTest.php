<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Jobs\SendBroadcastJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SendBroadcastJobTest extends TestCase
{
    use RefreshDatabase;

    private function seedCredit(int $gymId, int $remaining): void
    {
        DB::table('whatsapp_message_credits')->insert([
            'gym_id' => $gymId,
            'remaining_credits' => $remaining,
            'used_credits' => 0,
            'welcome_credits_used' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeFailedBroadcast(Gym $gym, int $failedCount, int $creditsUsed): WhatsAppBroadcast
    {
        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::FAILED,
        ]);

        DB::table('whatsapp_broadcasts')
            ->where('id', $broadcast->id)
            ->update(['failed_count' => $failedCount, 'credits_used' => $creditsUsed]);

        return $broadcast;
    }

    #[Test]
    public function it_does_not_refund_when_credits_were_not_deducted_upfront(): void
    {
        $gym = Gym::factory()->create();
        $this->seedCredit($gym->id, 10);

        $broadcast = $this->makeFailedBroadcast($gym, 2, 2);

        SendBroadcastJob::refundFailedUpfrontCredits($gym->id, $broadcast->id, false);

        $this->assertEquals(10, DB::table('whatsapp_message_credits')->where('gym_id', $gym->id)->value('remaining_credits'));
        $this->assertNull(DB::table('whatsapp_broadcasts')->where('id', $broadcast->id)->value('refunded_at'));
    }

    #[Test]
    public function it_refunds_failed_count_when_upfront_deduction_and_broadcast_failed(): void
    {
        $gym = Gym::factory()->create();
        $this->seedCredit($gym->id, 10);

        $broadcast = $this->makeFailedBroadcast($gym, 2, 2);

        SendBroadcastJob::refundFailedUpfrontCredits($gym->id, $broadcast->id, true);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $gym->id)->first();
        $this->assertNotNull($credit);
        $this->assertEquals(12, $credit->remaining_credits);
        $this->assertEquals(0, $credit->used_credits);

        $broadcast->refresh();
        $this->assertEquals(2, $broadcast->refunded_credits);
        $this->assertNotNull($broadcast->refunded_at);
    }

    #[Test]
    public function it_does_not_refund_when_no_failures_occurred(): void
    {
        $gym = Gym::factory()->create();
        $this->seedCredit($gym->id, 10);

        $broadcast = $this->makeFailedBroadcast($gym, 0, 5);
        DB::table('whatsapp_broadcasts')
            ->where('id', $broadcast->id)
            ->update(['sent_count' => 5, 'failed_count' => 0, 'credits_used' => 5]);

        SendBroadcastJob::refundFailedUpfrontCredits($gym->id, $broadcast->id, true);

        $this->assertEquals(10, DB::table('whatsapp_message_credits')->where('gym_id', $gym->id)->value('remaining_credits'));
        $this->assertNull(DB::table('whatsapp_broadcasts')->where('id', $broadcast->id)->value('refunded_at'));
    }

    #[Test]
    public function refund_is_idempotent_across_multiple_invocations(): void
    {
        $gym = Gym::factory()->create();
        $this->seedCredit($gym->id, 10);

        $broadcast = $this->makeFailedBroadcast($gym, 2, 2);

        SendBroadcastJob::refundFailedUpfrontCredits($gym->id, $broadcast->id, true);
        SendBroadcastJob::refundFailedUpfrontCredits($gym->id, $broadcast->id, true);

        $this->assertEquals(12, DB::table('whatsapp_message_credits')->where('gym_id', $gym->id)->value('remaining_credits'));
        $this->assertEquals(2, DB::table('whatsapp_broadcasts')->where('id', $broadcast->id)->value('refunded_credits'));
    }

    #[Test]
    public function it_does_not_overwrite_completed_status_when_job_fails(): void
    {
        $gym = Gym::factory()->create();
        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::COMPLETED,
        ]);

        $job = new SendBroadcastJob($broadcast->id, $gym->id);
        $job->failed(new RuntimeException('Dispatch failed'));

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED, $broadcast->status);
    }

    #[Test]
    public function it_refunds_upfront_credits_when_failed_called_with_null_batch_id(): void
    {
        $gym = Gym::factory()->create();
        $this->seedCredit($gym->id, 90);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'credits_used' => 10,
            'target_count' => 10,
            'sent_count' => 0,
            'failed_count' => 0,
            'batch_id' => null,
        ]);

        $job = new SendBroadcastJob(
            broadcastId: $broadcast->id,
            gymId: $gym->id,
            skipCreditDeduction: true,
        );

        $job->failed(new RuntimeException('Queue dispatch failed'));

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $broadcast->status);

        $remaining = DB::table('whatsapp_message_credits')->where('gym_id', $gym->id)->value('remaining_credits');
        $this->assertEquals(100, $remaining);

        $this->assertEquals(10, $broadcast->refunded_credits);
        $this->assertNotNull($broadcast->refunded_at);
    }

    #[Test]
    public function it_refunds_upfront_credits_when_template_is_missing_on_early_return(): void
    {
        $gym = Gym::factory()->create();
        $this->seedCredit($gym->id, 95);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gym->id,
            'name' => 'temp_template',
            'display_name' => 'Temp Template',
            'body' => 'Hello',
            'category' => 'utility',
            'status' => 'approved',
            'is_default' => true,
            'is_active' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'whatsapp_template_id' => $template->id,
            'credits_used' => 5,
            'target_count' => 5,
            'sent_count' => 0,
            'failed_count' => 0,
            'batch_id' => null,
        ]);

        // Soft delete template so $broadcast->template resolves to null via SoftDeletes scope
        $template->delete();

        $job = new SendBroadcastJob(
            broadcastId: $broadcast->id,
            gymId: $gym->id,
            skipCreditDeduction: true,
        );

        $job->handle();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $broadcast->status);

        $remaining = DB::table('whatsapp_message_credits')->where('gym_id', $gym->id)->value('remaining_credits');
        $this->assertEquals(100, $remaining);

        $this->assertEquals(5, $broadcast->refunded_credits);
        $this->assertNotNull($broadcast->refunded_at);
    }

    #[Test]
    public function it_marks_as_failed_and_refunds_upfront_credits_when_all_recipients_opted_out(): void
    {
        $gym = Gym::factory()->create();
        $this->seedCredit($gym->id, 90);

        $template = WhatsAppTemplate::query()->create([
            'gym_id' => $gym->id,
            'name' => 'optout_test_template',
            'display_name' => 'Optout Test Template',
            'body' => 'Hello',
            'category' => 'utility',
            'status' => 'approved',
            'is_default' => true,
            'is_active' => true,
        ]);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'whatsapp_template_id' => $template->id,
            'credits_used' => 2,
            'target_count' => 2,
            'sent_count' => 0,
            'failed_count' => 0,
            'batch_id' => null,
        ]);

        $memberOptedOut = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'allow_whatsapp_marketing' => false,
        ]);

        WhatsAppBroadcastRecipient::query()->create([
            'whatsapp_broadcast_id' => $broadcast->id,
            'member_id' => $memberOptedOut->id,
            'phone_number' => '+14155550001',
            'member_name' => 'Opted Out User',
            'status' => 'pending',
        ]);

        $job = new SendBroadcastJob(
            broadcastId: $broadcast->id,
            gymId: $gym->id,
            skipCreditDeduction: true,
        );

        $job->handle();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $broadcast->status);

        $remaining = DB::table('whatsapp_message_credits')->where('gym_id', $gym->id)->value('remaining_credits');
        $this->assertEquals(92, $remaining);

        $this->assertEquals(2, $broadcast->refunded_credits);
        $this->assertNotNull($broadcast->refunded_at);
    }

    #[Test]
    public function it_refunds_mixed_welcome_and_paid_credits_preserving_ledger_invariant(): void
    {
        $gym = Gym::factory()->create();

        // Simulate upfront deduction of 100 credits: 60 welcome credits and 40 paid credits
        DB::table('whatsapp_message_credits')->insert([
            'gym_id' => $gym->id,
            'total_credits' => 100,
            'remaining_credits' => 0,
            'used_credits' => 40,
            'welcome_credits_used' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 95 recipients failed out of 100
        $broadcast = $this->makeFailedBroadcast($gym, 95, 100);
        DB::table('whatsapp_broadcasts')
            ->where('id', $broadcast->id)
            ->update(['sent_count' => 5, 'failed_count' => 95, 'credits_used' => 100]);

        SendBroadcastJob::refundFailedUpfrontCredits($gym->id, $broadcast->id, true);

        $credit = WhatsAppMessageCredit::query()->where('gym_id', $gym->id)->first();
        $this->assertNotNull($credit);

        // 95 refunded: 60 restored to welcome, 35 restored to paid
        $this->assertEquals(95, $credit->remaining_credits);
        $this->assertEquals(5, $credit->used_credits);
        $this->assertEquals(0, $credit->welcome_credits_used);

        // Invariant: remaining = total - used - welcome_used (95 = 100 - 5 - 0)
        $this->assertEquals($credit->remaining_credits, ($credit->total_credits ?? 0) - ($credit->used_credits ?? 0) - ($credit->welcome_credits_used ?? 0));
    }

    #[Test]
    public function it_early_returns_and_does_not_mutate_or_refund_when_gym_id_mismatches_in_failed(): void
    {
        $gymA = Gym::factory()->create();
        $gymB = Gym::factory()->create();

        $this->seedCredit($gymA->id, 90);
        $this->seedCredit($gymB->id, 90);

        $broadcastGymA = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gymA->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'credits_used' => 10,
            'target_count' => 10,
            'sent_count' => 0,
            'failed_count' => 0,
            'batch_id' => null,
        ]);

        // Dispatch SendBroadcastJob with gymB's ID (mismatch)
        $job = new SendBroadcastJob(
            broadcastId: $broadcastGymA->id,
            gymId: $gymB->id,
            skipCreditDeduction: true,
        );

        $job->failed(new RuntimeException('Attacker tenant mismatch attempt'));

        // Assert broadcast status remains SENDING (unmodified)
        $broadcastGymA->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::SENDING, $broadcastGymA->status);
        $this->assertSame(0, $broadcastGymA->refunded_credits);

        // Assert no credits refunded to either gym
        $creditA = DB::table('whatsapp_message_credits')->where('gym_id', $gymA->id)->value('remaining_credits');
        $creditB = DB::table('whatsapp_message_credits')->where('gym_id', $gymB->id)->value('remaining_credits');
        $this->assertEquals(90, $creditA);
        $this->assertEquals(90, $creditB);
    }

    #[Test]
    public function it_persists_batch_id_and_skips_refund_when_batch_was_dispatched_prior_to_failed(): void
    {
        $gym = Gym::factory()->create();
        $this->seedCredit($gym->id, 90);

        $broadcast = WhatsAppBroadcast::factory()->create([
            'gym_id' => $gym->id,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'credits_used' => 10,
            'target_count' => 10,
            'sent_count' => 0,
            'failed_count' => 0,
            'batch_id' => null,
        ]);

        $job = new SendBroadcastJob(
            broadcastId: $broadcast->id,
            gymId: $gym->id,
            skipCreditDeduction: true,
        );
        $job->dispatchedBatchId = 'batch-toctou-live-123';

        $job->failed(new RuntimeException('Simulated crash between dispatch and broadcast update'));

        $broadcast->refresh();
        $this->assertSame('batch-toctou-live-123', $broadcast->batch_id);
        $this->assertSame(0, $broadcast->refunded_credits);

        $remaining = DB::table('whatsapp_message_credits')->where('gym_id', $gym->id)->value('remaining_credits');
        $this->assertEquals(90, $remaining);
    }
}
