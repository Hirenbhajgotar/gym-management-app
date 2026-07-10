<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Currency;
use App\Models\Payment;
use PHPUnit\Framework\TestCase;

class PaymentCurrencyFormattingTest extends TestCase
{
    /**
     * The `amount` column is cast as `decimal:2`, which yields a string
     * when read from the database. Number::currency() requires int|float,
     * so every formatting path must coerce it. This guards against the
     * TypeError reported when a Payment is created (activity log fires
     * getDescriptionForEvent during the created event).
     */
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePayment(array $attributes = []): Payment
    {
        $payment = new Payment(array_merge([
            'amount' => '25000.00',
            'currency' => Currency::INR->value,
            'payment_type' => 'subscription_upgrade',
        ], $attributes));

        // Avoid hitting the database for the optional gym relation in this
        // unit test; we only care about the currency coercion path.
        $payment->setRelation('gym', null);

        return $payment;
    }

    public function test_formatted_amount_accepts_decimal_string(): void
    {
        $payment = $this->makePayment();

        $formatted = $payment->formatted_amount;

        $this->assertStringContainsString('25', $formatted);
    }

    public function test_get_description_for_event_accepts_decimal_string(): void
    {
        $payment = $this->makePayment();

        $this->assertStringContainsString('25,000', $payment->getDescriptionForEvent('created'));
        $this->assertStringContainsString('25,000', $payment->getDescriptionForEvent('updated'));
        $this->assertStringContainsString('25,000', $payment->getDescriptionForEvent('deleted'));
        // The default case intentionally omits the amount.
        $this->assertStringContainsString('restored', $payment->getDescriptionForEvent('restored'));
        $this->assertStringContainsString('subscription_upgrade', $payment->getDescriptionForEvent('restored'));
    }

    public function test_numeric_amount_still_formats(): void
    {
        $payment = $this->makePayment(['amount' => 199.99]);

        $this->assertStringContainsString('199', $payment->formatted_amount);
        $this->assertStringContainsString('199', $payment->getDescriptionForEvent('created'));
    }
}
