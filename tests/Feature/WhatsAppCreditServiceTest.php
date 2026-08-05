<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WhatsAppBillingModeEnum;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Services\WhatsApp\WhatsAppCreditService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Credit service tests for the billing-mode feature.
 *
 * Self-managed accounts pay Meta directly and must bypass credit checks,
 * deductions, and usage logging entirely. Pay-via-app accounts go through
 * the normal deduction + logging flow.
 */
class WhatsAppCreditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
    }

    private function createAccount(WhatsAppBillingModeEnum $billingMode): WhatsAppAccount
    {
        return WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'billing_mode' => $billingMode,
        ]);
    }

    private function createCreditRecord(int $total = 100, int $used = 0): WhatsAppMessageCredit
    {
        return WhatsAppMessageCredit::query()->create([
            'gym_id' => $this->gym->id,
            'total_credits' => $total,
            'used_credits' => $used,
            'remaining_credits' => $total - $used,
        ]);
    }

    private function service(): WhatsAppCreditService
    {
        return new WhatsAppCreditService($this->gym->id);
    }

    #[Test]
    public function self_managed_account_does_not_deduct_credits(): void
    {
        $this->createAccount(WhatsAppBillingModeEnum::SELF_MANAGED);
        $this->createCreditRecord(100, 0);

        $this->service()->checkAndDeduct(5);

        $this->assertDatabaseHas('whatsapp_message_credits', [
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 0,
            'remaining_credits' => 100,
        ]);
    }

    #[Test]
    public function self_managed_account_without_credit_record_does_not_throw(): void
    {
        $this->createAccount(WhatsAppBillingModeEnum::SELF_MANAGED);

        $this->service()->checkAndDeduct(5);

        $this->assertDatabaseCount('whatsapp_message_credits', 0);
    }

    #[Test]
    public function self_managed_account_does_not_log_usage(): void
    {
        $this->createAccount(WhatsAppBillingModeEnum::SELF_MANAGED);

        $this->service()->logUsage('+1234567890', null, null);

        $this->assertDatabaseCount('whatsapp_usage_logs', 0);
    }

    #[Test]
    public function pay_via_app_account_deducts_credits(): void
    {
        $this->createAccount(WhatsAppBillingModeEnum::PAY_VIA_APP);
        $this->createCreditRecord(100, 0);

        $this->service()->checkAndDeduct(3);

        $this->assertDatabaseHas('whatsapp_message_credits', [
            'gym_id' => $this->gym->id,
            'total_credits' => 100,
            'used_credits' => 3,
            'remaining_credits' => 97,
        ]);
    }

    #[Test]
    public function pay_via_app_account_without_credit_record_throws(): void
    {
        $this->createAccount(WhatsAppBillingModeEnum::PAY_VIA_APP);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient message credits');

        $this->service()->checkAndDeduct(1);
    }

    #[Test]
    public function pay_via_app_account_with_insufficient_credits_throws(): void
    {
        $this->createAccount(WhatsAppBillingModeEnum::PAY_VIA_APP);
        $this->createCreditRecord(2, 2);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient message credits');

        $this->service()->checkAndDeduct(1);
    }

    #[Test]
    public function pay_via_app_account_logs_usage(): void
    {
        $this->createAccount(WhatsAppBillingModeEnum::PAY_VIA_APP);

        $this->service()->logUsage('+1234567890', null, 'SM123', 0.05);

        $this->assertDatabaseHas('whatsapp_usage_logs', [
            'gym_id' => $this->gym->id,
            'recipient' => '+1234567890',
            'twilio_message_sid' => 'SM123',
            'credits_used' => 1,
            'status' => 'sent',
        ]);
    }

    #[Test]
    public function skip_deduction_mode_makes_check_and_deduct_a_noop(): void
    {
        $this->createAccount(WhatsAppBillingModeEnum::PAY_VIA_APP);
        $this->createCreditRecord(100, 0);

        $service = $this->service();
        $service->setSkipDeduction(true);
        $service->checkAndDeduct(5);

        $this->assertDatabaseHas('whatsapp_message_credits', [
            'gym_id' => $this->gym->id,
            'used_credits' => 0,
            'remaining_credits' => 100,
        ]);
    }

    #[Test]
    public function skip_deduction_mode_does_not_throw_when_credits_are_insufficient(): void
    {
        $this->createAccount(WhatsAppBillingModeEnum::PAY_VIA_APP);
        $this->createCreditRecord(2, 2);

        $service = $this->service();
        $service->setSkipDeduction(true);
        $service->checkAndDeduct(1);

        $this->assertDatabaseHas('whatsapp_message_credits', [
            'gym_id' => $this->gym->id,
            'remaining_credits' => 0,
        ]);
    }
}
