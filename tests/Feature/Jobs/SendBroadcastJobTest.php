<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Jobs\SendBroadcastJob;
use App\Models\Gym;
use App\Models\WhatsAppBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
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
    }

    #[Test]
    public function it_refunds_failed_count_when_upfront_deduction_and_broadcast_failed(): void
    {
        $gym = Gym::factory()->create();
        $this->seedCredit($gym->id, 10);

        $broadcast = $this->makeFailedBroadcast($gym, 2, 2);

        SendBroadcastJob::refundFailedUpfrontCredits($gym->id, $broadcast->id, true);

        $credit = DB::table('whatsapp_message_credits')->where('gym_id', $gym->id)->first();
        $this->assertNotNull($credit);
        $this->assertEquals(12, $credit->remaining_credits);
        $this->assertEquals(0, $credit->used_credits);
    }

    #[Test]
    public function it_does_not_refund_when_no_failures_occurred(): void
    {
        $gym = Gym::factory()->create();
        $this->seedCredit($gym->id, 10);

        $broadcast = $this->makeFailedBroadcast($gym, 0, 5);

        SendBroadcastJob::refundFailedUpfrontCredits($gym->id, $broadcast->id, true);

        $this->assertEquals(10, DB::table('whatsapp_message_credits')->where('gym_id', $gym->id)->value('remaining_credits'));
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
    }
}
