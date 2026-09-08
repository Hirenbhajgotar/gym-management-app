<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Enums\WhatsAppBroadcastRecipientStatusEnum;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Jobs\SendBroadcastJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use Illuminate\Bus\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

use function assert;

class ProcessScheduledBroadcastsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private WhatsAppTemplate $template;

    private GymMember $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
        ]);
        $this->member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
        ]);
    }

    #[Test]
    public function it_dispatches_scheduled_broadcast_due_now_and_marks_sending(): void
    {
        Queue::fake();

        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Scheduled Promo',
            'status' => WhatsAppBroadcastStatusEnum::SCHEDULED,
            'scheduled_at' => now()->subMinutes(5),
            'target_count' => 10,
            'credits_used' => 10,
            'credits_deducted_upfront' => true,
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::SENDING, $broadcast->status);
        $this->assertNotNull($broadcast->started_at);

        Queue::assertPushed(SendBroadcastJob::class, function (SendBroadcastJob $job) use ($broadcast) {
            return $job->broadcastId === $broadcast->id
                && $job->gymId === $this->gym->id
                && $job->skipCreditDeduction === true;
        });
    }

    #[Test]
    public function it_skips_scheduled_broadcasts_in_the_future(): void
    {
        Queue::fake();

        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Future Promo',
            'status' => WhatsAppBroadcastStatusEnum::SCHEDULED,
            'scheduled_at' => now()->addHour(),
            'target_count' => 10,
            'credits_used' => 10,
            'credits_deducted_upfront' => true,
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::SCHEDULED, $broadcast->status);
        Queue::assertNotPushed(SendBroadcastJob::class);
    }

    #[Test]
    public function it_reconciles_stuck_sending_broadcast_older_than_two_hours_and_refunds_if_upfront(): void
    {
        $credit = WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 10,
            'remaining_credits' => 90,
            'welcome_credits_used' => 0,
        ]);

        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Stuck Partial Broadcast',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now()->subHours(3),
            'target_count' => 10,
            'sent_count' => 4,
            'failed_count' => 0,
            'credits_used' => 10,
            'credits_deducted_upfront' => true,
        ]);

        for ($i = 0; $i < 6; $i++) {
            WhatsAppBroadcastRecipient::query()->create([
                'whatsapp_broadcast_id' => $broadcast->id,
                'member_id' => $this->member->id,
                'member_name' => $this->member->name,
                'phone_number' => '+1555123456' . $i,
                'status' => WhatsAppBroadcastRecipientStatusEnum::PENDING->value,
            ]);
        }

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED_WITH_ERRORS, $broadcast->status);
        $this->assertSame(4, $broadcast->sent_count);
        $this->assertSame(6, $broadcast->failed_count);
        $this->assertNotNull($broadcast->completed_at);

        // 6 pending recipients failed
        $this->assertSame(6, WhatsAppBroadcastRecipient::query()
            ->where('whatsapp_broadcast_id', $broadcast->id)
            ->where('status', WhatsAppBroadcastRecipientStatusEnum::FAILED->value)
            ->count());

        // 6 credits refunded
        $credit->refresh();
        $this->assertSame(96, $credit->remaining_credits);
        $this->assertSame(4, $credit->used_credits);
    }

    #[Test]
    public function it_does_not_refund_for_self_managed_stuck_broadcast(): void
    {
        $credit = WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 0,
            'remaining_credits' => 100,
            'welcome_credits_used' => 0,
        ]);

        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Stuck Self-Managed Broadcast',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now()->subHours(3),
            'target_count' => 10,
            'sent_count' => 0,
            'failed_count' => 0,
            'credits_used' => 10,
            'credits_deducted_upfront' => false,
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $broadcast->status);

        // Zero refund fabricated!
        $credit->refresh();
        $this->assertSame(100, $credit->remaining_credits);
        $this->assertSame(0, $credit->used_credits);
        $this->assertSame(0, $broadcast->refunded_credits);
    }

    #[Test]
    public function it_reconciles_broadcast_with_null_started_at_and_old_created_at(): void
    {
        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Null started_at Broadcast',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'target_count' => 5,
            'sent_count' => 0,
            'failed_count' => 0,
            'credits_used' => 5,
            'credits_deducted_upfront' => false,
        ]);

        DB::table('whatsapp_broadcasts')->where('id', $broadcast->id)->update([
            'started_at' => null,
            'created_at' => now()->subHours(3),
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $broadcast->status);
    }

    #[Test]
    public function it_skips_fresh_sending_broadcasts_under_two_hours(): void
    {
        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Active In-Progress Broadcast',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now()->subMinutes(30),
            'target_count' => 10,
            'sent_count' => 3,
            'failed_count' => 0,
            'credits_used' => 10,
            'credits_deducted_upfront' => true,
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::SENDING, $broadcast->status);
    }

    #[Test]
    public function it_resolves_never_sent_stuck_broadcast_to_failed_with_full_refund(): void
    {
        $credit = WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 8,
            'remaining_credits' => 92,
            'welcome_credits_used' => 0,
        ]);

        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Zero Sent Abandoned Broadcast',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now()->subHours(4),
            'target_count' => 8,
            'sent_count' => 0,
            'failed_count' => 0,
            'credits_used' => 8,
            'credits_deducted_upfront' => true,
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::FAILED, $broadcast->status);
        $this->assertNull($broadcast->completed_at);

        $credit->refresh();
        $this->assertSame(100, $credit->remaining_credits);
        $this->assertSame(0, $credit->used_credits);
    }

    #[Test]
    public function it_reconciles_broadcast_with_zero_target_and_zero_sent_to_completed(): void
    {
        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Zero Target Broadcast',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now()->subHours(3),
            'target_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'credits_used' => 0,
            'credits_deducted_upfront' => false,
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED, $broadcast->status);
        $this->assertNotNull($broadcast->completed_at);
    }

    #[Test]
    public function it_reconciles_broadcast_with_sent_equals_target_and_failed_greater_than_zero_to_completed_with_errors(): void
    {
        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Sent Equals Target With Failures',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now()->subHours(3),
            'target_count' => 5,
            'sent_count' => 5,
            'failed_count' => 2,
            'credits_used' => 5,
            'credits_deducted_upfront' => false,
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED_WITH_ERRORS, $broadcast->status);
        $this->assertNotNull($broadcast->completed_at);
    }

    #[Test]
    public function it_reconciles_broadcast_at_exact_two_hour_boundary(): void
    {
        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Exact Boundary Broadcast',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now()->subHours(2),
            'target_count' => 5,
            'sent_count' => 5,
            'failed_count' => 0,
            'credits_used' => 5,
            'credits_deducted_upfront' => false,
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED, $broadcast->status);
        $this->assertNotNull($broadcast->completed_at);
    }

    #[Test]
    public function it_skips_reconciling_broadcast_with_active_bus_batch(): void
    {
        /** @var Batch&Mockery\MockInterface $mockBatch */
        $mockBatch = Mockery::mock(Batch::class);
        $mockBatch->allows([
            'finished' => false,
            'cancelled' => false,
        ]);
        $mockBatch->pendingJobs = 5;

        Bus::shouldReceive('findBatch')
            ->with('batch-active-123')
            ->andReturn($mockBatch);

        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Active Batch Broadcast',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now()->subHours(3),
            'target_count' => 10,
            'sent_count' => 2,
            'failed_count' => 0,
            'credits_used' => 10,
            'credits_deducted_upfront' => false,
            'batch_id' => 'batch-active-123',
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::SENDING, $broadcast->status);
    }

    #[Test]
    public function it_respects_max_broadcasts_per_run_cap(): void
    {
        // Create 105 stuck broadcasts (> 2 hours old)
        $broadcasts = [];
        for ($i = 0; $i < 105; $i++) {
            $broadcasts[] = [
                'gym_id' => $this->gym->id,
                'whatsapp_template_id' => $this->template->id,
                'title' => "Bulk Stuck Broadcast {$i}",
                'status' => WhatsAppBroadcastStatusEnum::SENDING->value,
                'started_at' => now()->subHours(3),
                'target_count' => 1,
                'sent_count' => 1,
                'failed_count' => 0,
                'credits_used' => 1,
                'credits_deducted_upfront' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('whatsapp_broadcasts')->insert($broadcasts);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        // Exactly 100 should be reconciled to COMPLETED (cap is 100), leaving 5 in SENDING
        $completedCount = WhatsAppBroadcast::query()
            ->where('gym_id', $this->gym->id)
            ->where('status', WhatsAppBroadcastStatusEnum::COMPLETED)
            ->count();
        $sendingCount = WhatsAppBroadcast::query()
            ->where('gym_id', $this->gym->id)
            ->where('status', WhatsAppBroadcastStatusEnum::SENDING)
            ->count();

        $this->assertSame(100, $completedCount);
        $this->assertSame(5, $sendingCount);
    }

    #[Test]
    public function it_skips_broadcast_at_one_hour_fifty_nine_minutes(): void
    {
        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Almost Two Hours Broadcast',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now()->subMinutes(119),
            'target_count' => 5,
            'sent_count' => 1,
            'failed_count' => 0,
            'credits_used' => 5,
            'credits_deducted_upfront' => false,
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::SENDING, $broadcast->status);
    }

    #[Test]
    public function it_reconciles_broadcast_with_finished_bus_batch(): void
    {
        /** @var Batch&Mockery\MockInterface $mockBatch */
        $mockBatch = Mockery::mock(Batch::class);
        $mockBatch->allows([
            'finished' => true,
            'cancelled' => false,
        ]);
        $mockBatch->pendingJobs = 0;

        Bus::shouldReceive('findBatch')
            ->with('batch-finished-456')
            ->andReturn($mockBatch);

        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Finished Batch Broadcast',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now()->subHours(3),
            'target_count' => 5,
            'sent_count' => 5,
            'failed_count' => 0,
            'credits_used' => 5,
            'credits_deducted_upfront' => false,
            'batch_id' => 'batch-finished-456',
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED, $broadcast->status);
        $this->assertNotNull($broadcast->completed_at);
    }

    #[Test]
    public function it_skips_refund_when_broadcast_already_refunded_in_sweep(): void
    {
        $credit = WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 10,
            'remaining_credits' => 90,
            'welcome_credits_used' => 0,
        ]);

        $broadcast = WhatsAppBroadcast::query()->create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Already Refunded Broadcast',
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
            'started_at' => now()->subHours(3),
            'target_count' => 10,
            'sent_count' => 5,
            'failed_count' => 5,
            'credits_used' => 10,
            'credits_deducted_upfront' => true,
            'refunded_credits' => 5,
            'refunded_at' => now()->subHour(),
        ]);

        $this->artisanCommand('whatsapp:process-scheduled')
            ->assertSuccessful();

        $broadcast->refresh();
        $this->assertSame(WhatsAppBroadcastStatusEnum::COMPLETED_WITH_ERRORS, $broadcast->status);
        $this->assertSame(5, $broadcast->refunded_credits);

        $credit->refresh();
        // Credits should remain 90 (no second refund)
        $this->assertSame(90, $credit->remaining_credits);
        $this->assertSame(10, $credit->used_credits);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function artisanCommand(string $command, array $parameters = []): PendingCommand
    {
        $result = $this->artisan($command, $parameters);
        assert($result instanceof PendingCommand);

        return $result;
    }
}
