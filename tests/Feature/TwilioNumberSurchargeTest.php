<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Enums\WhatsAppPhoneNumberTypesEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppCreditTransaction;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPackage;
use App\Models\WhatsAppPhoneNumber;
use App\Services\WhatsAppBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TwilioNumberSurchargeTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
    }

    protected function createTwilioAccountWithPhone(?string $lastChargedAt = null): WhatsAppAccount
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
        ]);

        WhatsAppPhoneNumber::create([
            'whatsapp_account_id' => $account->id,
            'phone_number' => '1234567890',
            'phone_number_sid' => 'PNtest123',
            'country_code' => 'US',
            'monthly_cost' => 1.50,
            'number_cost' => 0.00,
            'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
            'type' => WhatsAppPhoneNumberTypesEnum::LOCAL,
            'last_number_cost_charged_at' => $lastChargedAt,
        ]);

        return $account->fresh();
    }

    protected function createWabaAccount(): WhatsAppAccount
    {
        $account = WhatsAppAccount::create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'status' => WhatsAppAccountStatusEnum::ACTIVE,
            'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
        ]);

        return $account;
    }

    #[Test]
    public function surcharge_is_detected_for_eligible_twilio_tenant(): void
    {
        $account = $this->createTwilioAccountWithPhone(null);

        $this->assertTrue($account->isTwilioWithActiveNumber());
        $this->assertTrue($account->isPhoneSurchargeDue());
        $this->assertTrue($account->phoneNumber->isSurchargeDue());
        $this->assertEquals(136.41, config('services.twilio.number_cost'));

        $billingService = app(WhatsAppBillingService::class);
        $surcharge = $billingService->getTwilioNumberSurcharge($this->gym->id);

        $this->assertTrue($surcharge['eligible']);
        $this->assertEquals(136.41, $surcharge['amount']);
        $this->assertNotNull($surcharge['phone']);
    }

    #[Test]
    public function surcharge_not_detected_for_recently_charged_tenant(): void
    {
        $this->createTwilioAccountWithPhone(now()->subDays(5));

        $billingService = app(WhatsAppBillingService::class);
        $surcharge = $billingService->getTwilioNumberSurcharge($this->gym->id);

        $this->assertFalse($surcharge['eligible']);
        $this->assertEquals(0.0, $surcharge['amount']);
    }

    #[Test]
    public function surcharge_not_detected_for_waba_tenant(): void
    {
        $this->createWabaAccount();

        $billingService = app(WhatsAppBillingService::class);
        $surcharge = $billingService->getTwilioNumberSurcharge($this->gym->id);

        $this->assertFalse($surcharge['eligible']);
        $this->assertEquals(0.0, $surcharge['amount']);
    }

    #[Test]
    public function purchase_package_with_surcharge_records_correctly(): void
    {
        $credit = WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 0,
            'used_credits' => 0,
            'remaining_credits' => 0,
            'cost_per_message' => 0.15,
            'selling_price_per_msg' => 0.25,
        ]);

        $package = WhatsAppPackage::create([
            'name' => 'Test Package',
            'message_count' => 200,
            'selling_price' => 500.00,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $billingService = app(WhatsAppBillingService::class);
        $result = $billingService->purchasePackage(
            $this->gym->id,
            $package->id,
            'test_payment_ref',
            null,
            135.0
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(135.0, $result['phone_number_surcharge']);

        $transaction = WhatsAppCreditTransaction::where('gym_id', $this->gym->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(135.0, (float) $transaction->phone_number_surcharge);
        $this->assertEquals(500.00, (float) $transaction->amount);
    }

    #[Test]
    public function purchase_package_without_surcharge_backward_compatible(): void
    {
        WhatsAppMessageCredit::create([
            'gym_id' => $this->gym->id,
            'total_credits' => 0,
            'used_credits' => 0,
            'remaining_credits' => 0,
            'cost_per_message' => 0.15,
            'selling_price_per_msg' => 0.25,
        ]);

        $package = WhatsAppPackage::create([
            'name' => 'Test Package',
            'message_count' => 200,
            'selling_price' => 500.00,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $billingService = app(WhatsAppBillingService::class);
        $result = $billingService->purchasePackage(
            $this->gym->id,
            $package->id,
            'test_payment_ref_2'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(0.0, $result['phone_number_surcharge']);

        $transaction = WhatsAppCreditTransaction::where('gym_id', $this->gym->id)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(0.0, $transaction->phone_number_surcharge);
    }

    #[Test]
    public function surcharge_is_due_after_30_days(): void
    {
        $account = $this->createTwilioAccountWithPhone(now()->subDays(30));

        $this->assertTrue($account->phoneNumber->isSurchargeDue(),
            'Surcharge should be due exactly 30 days after last charge');

        $account->phoneNumber->update(['last_number_cost_charged_at' => now()->subDays(29)]);

        $this->assertFalse($account->phoneNumber->fresh()->isSurchargeDue(),
            'Surcharge should NOT be due at 29 days');
    }
}
