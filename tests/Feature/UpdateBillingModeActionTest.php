<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\WhatsAppAccount\UpdateBillingModeAction;
use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the UpdateBillingModeAction used by the BillingModeStep
 * (setup wizard) and the WhatsappDashboard change-billing-mode action.
 */
class UpdateBillingModeActionTest extends TestCase
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
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'billing_mode' => $billingMode,
        ]);
    }

    #[Test]
    public function it_updates_account_from_pay_via_app_to_self_managed(): void
    {
        $account = $this->createAccount(WhatsAppBillingModeEnum::PAY_VIA_APP);

        $result = resolve(UpdateBillingModeAction::class)($account, WhatsAppBillingModeEnum::SELF_MANAGED);

        $this->assertTrue($result);
        $this->assertSame(WhatsAppBillingModeEnum::SELF_MANAGED, $account->refresh()->billing_mode);
    }

    #[Test]
    public function it_updates_account_from_self_managed_back_to_pay_via_app(): void
    {
        $account = $this->createAccount(WhatsAppBillingModeEnum::SELF_MANAGED);

        $result = resolve(UpdateBillingModeAction::class)($account, WhatsAppBillingModeEnum::PAY_VIA_APP);

        $this->assertTrue($result);
        $this->assertSame(WhatsAppBillingModeEnum::PAY_VIA_APP, $account->refresh()->billing_mode);
    }

    #[Test]
    public function it_persists_billing_mode_to_the_database(): void
    {
        $account = $this->createAccount(WhatsAppBillingModeEnum::PAY_VIA_APP);

        resolve(UpdateBillingModeAction::class)($account, WhatsAppBillingModeEnum::SELF_MANAGED);

        $this->assertDatabaseHas('whatsapp_accounts', [
            'id' => $account->id,
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED->value,
        ]);
    }
}
