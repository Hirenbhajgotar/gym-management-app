<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\WhatsAppCreditTransaction;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPackage;
use App\Services\WhatsAppBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected WhatsAppBillingService $billingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->billingService = resolve(WhatsAppBillingService::class);
    }

    // ========== WELCOME CREDITS ==========

    #[Test]
    public function it_adds_welcome_credits_for_new_gym(): void
    {
        $this->billingService->addWelcomeCredits($this->gym->id);

        $credit = WhatsAppMessageCredit::where('gym_id', $this->gym->id)->first();

        $this->assertNotNull($credit);
        $this->assertEquals(50, $credit->remaining_credits);
        $this->assertEquals(50, $credit->total_credits);
        $this->assertTrue($credit->welcome_credits_claimed);
    }

    #[Test]
    public function it_does_not_add_welcome_credits_twice(): void
    {
        $this->billingService->addWelcomeCredits($this->gym->id);

        $this->expectException(\Exception::class);
        $this->billingService->addWelcomeCredits($this->gym->id);
    }

    #[Test]
    public function it_checks_welcome_credits_claimed_status(): void
    {
        $this->assertFalse($this->billingService->hasClaimedWelcomeCredits($this->gym->id));

        $this->billingService->addWelcomeCredits($this->gym->id);

        $this->assertTrue($this->billingService->hasClaimedWelcomeCredits($this->gym->id));
    }

    // ========== CREDIT BALANCE ==========

    #[Test]
    public function it_returns_zero_balance_when_no_credits_exist(): void
    {
        $balance = $this->billingService->getCreditBalance($this->gym->id);

        $this->assertEquals(0, $balance);
    }

    #[Test]
    public function it_returns_correct_credit_balance(): void
    {
        WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 30,
            'remaining_credits' => 70,
        ]);

        $balance = $this->billingService->getCreditBalance($this->gym->id);

        $this->assertEquals(70, $balance);
    }

    // ========== CREDIT USAGE ==========

    #[Test]
    public function it_uses_credits_successfully(): void
    {
        WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 0,
            'remaining_credits' => 100,
        ]);

        $this->billingService->useCredits($this->gym->id, 5, 'Sent 5 messages');

        $credit = WhatsAppMessageCredit::where('gym_id', $this->gym->id)->first();
        $this->assertEquals(95, $credit->remaining_credits);
        $this->assertEquals(5, $credit->used_credits);
    }

    #[Test]
    public function it_throws_when_insufficient_credits(): void
    {
        WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 10,
            'used_credits' => 8,
            'remaining_credits' => 2,
        ]);

        $this->expectException(\Exception::class);
        $this->billingService->useCredits($this->gym->id, 5, 'Trying to send 5 messages');
    }

    // ========== CREDIT ADJUSTMENT ==========

    #[Test]
    public function it_adjusts_credits_positively(): void
    {
        WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 0,
            'remaining_credits' => 100,
        ]);

        $this->billingService->adjustCredits($this->gym->id, 25, 'Bonus credits', 1);

        $credit = WhatsAppMessageCredit::where('gym_id', $this->gym->id)->first();
        $this->assertEquals(125, $credit->remaining_credits);
    }

    // ========== PACKAGE PURCHASE ==========

    #[Test]
    public function it_purchases_package_and_adds_credits(): void
    {
        $package = WhatsAppPackage::create([
            'name' => 'Starter Pack',
            'description' => '100 message credits',
            'message_count' => 100,
            'selling_price' => 199.00,
            'twilio_msg_cost_per_unit' => 0.15,
            'validity_days' => 365,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 50,
            'used_credits' => 0,
            'remaining_credits' => 50,
        ]);

        $this->billingService->purchasePackage(
            $this->gym->id,
            $package->id,
            'pay_test_123',
            1
        );

        $credit = WhatsAppMessageCredit::where('gym_id', $this->gym->id)->first();
        $this->assertEquals(150, $credit->remaining_credits);
        $this->assertEquals(150, $credit->total_credits);
    }

    // ========== REFUND CREDITS ==========

    #[Test]
    public function it_refunds_credits(): void
    {
        WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 30,
            'remaining_credits' => 70,
        ]);

        $this->billingService->refundCredits($this->gym->id, 10, 'Failed messages refund', 1);

        $credit = WhatsAppMessageCredit::where('gym_id', $this->gym->id)->first();
        $this->assertEquals(80, $credit->remaining_credits);
    }

    // ========== TRANSACTION HISTORY ==========

    #[Test]
    public function it_returns_transaction_history(): void
    {
        $credit = WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 0,
            'remaining_credits' => 100,
        ]);

        WhatsAppCreditTransaction::create([
            'gym_id' => $this->gym->id,
            'whatsapp_message_credit_id' => $credit->id,
            'type' => 'purchase',
            'credits' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'amount' => 199.00,
            'description' => 'Purchased Starter Pack',
        ]);

        $history = $this->billingService->getTransactionHistory($this->gym->id, 10);

        $this->assertNotEmpty($history);
        $this->assertEquals('purchase', $history->first()->type);
    }

    // ========== USAGE REPORT ==========

    #[Test]
    public function it_generates_usage_report(): void
    {
        WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 10,
            'remaining_credits' => 90,
        ]);

        $report = $this->billingService->getUsageReport(
            $this->gym->id,
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertIsArray($report);
    }
}
