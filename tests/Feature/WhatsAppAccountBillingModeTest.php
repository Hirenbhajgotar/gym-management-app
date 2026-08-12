<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * WhatsAppAccount billing-mode helper tests: isSelfManaged / isPayViaApp /
 * hasBillingMode / requiresCreditCheck semantics and the billing-mode
 * scopes used by the credit service and orchestrator.
 */
class WhatsAppAccountBillingModeTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAccount(array $attributes = []): WhatsAppAccount
    {
        return WhatsAppAccount::query()->create(array_merge(['gym_id' => $this->gym->id], $attributes));
    }

    #[Test]
    public function is_self_managed_returns_true_for_self_managed_accounts(): void
    {
        $account = $this->createAccount(['billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED]);

        $this->assertTrue($account->isSelfManaged());
        $this->assertFalse($account->isPayViaApp());
        $this->assertTrue($account->hasBillingMode());
        $this->assertFalse($account->requiresCreditCheck());
    }

    #[Test]
    public function is_pay_via_app_returns_true_for_pay_via_app_accounts(): void
    {
        $account = $this->createAccount(['billing_mode' => WhatsAppBillingModeEnum::PAY_VIA_APP]);

        $this->assertFalse($account->isSelfManaged());
        $this->assertTrue($account->isPayViaApp());
        $this->assertTrue($account->hasBillingMode());
        $this->assertTrue($account->requiresCreditCheck());
    }

    #[Test]
    public function null_billing_mode_defaults_to_credit_check_for_twilio_accounts(): void
    {
        $account = $this->createAccount([
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'billing_mode' => null,
        ]);

        $this->assertFalse($account->hasBillingMode());
        $this->assertTrue($account->requiresCreditCheck());
    }

    #[Test]
    public function null_billing_mode_defaults_to_credit_check_for_waba_accounts(): void
    {
        $account = $this->createAccount([
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'billing_mode' => null,
        ]);

        $this->assertFalse($account->hasBillingMode());
        $this->assertTrue($account->requiresCreditCheck());
    }

    #[Test]
    public function billing_mode_is_cast_to_enum(): void
    {
        $account = $this->createAccount(['billing_mode' => 'self_managed']);

        $this->assertInstanceOf(WhatsAppBillingModeEnum::class, $account->billing_mode);
        $this->assertSame(WhatsAppBillingModeEnum::SELF_MANAGED, $account->billing_mode);
    }

    #[Test]
    public function self_manage_billing_mode_scope_filters_self_managed_accounts(): void
    {
        $this->createAccount(['billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED]);
        $this->createAccount(['billing_mode' => WhatsAppBillingModeEnum::PAY_VIA_APP]);

        $selfManaged = WhatsAppAccount::query()->selfManageBillingMode()->get();

        $this->assertCount(1, $selfManaged);

        $selfManagedAccount = $selfManaged->first();
        assert($selfManagedAccount instanceof WhatsAppAccount);

        $this->assertTrue($selfManagedAccount->isSelfManaged());
    }

    #[Test]
    public function pay_via_app_billing_mode_scope_filters_pay_via_app_accounts(): void
    {
        $this->createAccount(['billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED]);
        $this->createAccount(['billing_mode' => WhatsAppBillingModeEnum::PAY_VIA_APP]);

        $payViaApp = WhatsAppAccount::query()->payViaAppBillingMode()->get();

        $this->assertCount(1, $payViaApp);

        $payViaAppAccount = $payViaApp->first();
        assert($payViaAppAccount instanceof WhatsAppAccount);

        $this->assertTrue($payViaAppAccount->isPayViaApp());
    }
}
