<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Number;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Number::class)]
final class NumberHelperTest extends TestCase
{
    #[Test]
    public function it_formats_indian_numbers_with_lakh_crore_grouping(): void
    {
        $this->assertSame('1,23,45,678', Number::formatIndian(12345678));
        $this->assertSame('1,00,000', Number::formatIndian(100000));
        $this->assertSame('1,500.5', Number::formatIndian(1500.5, maxPrecision: 1));
    }

    #[Test]
    public function it_formats_currency_in_inr_by_default(): void
    {
        $formatted = Number::currency(150000, precision: 0);

        $this->assertStringContainsString('1,50,000', $formatted);
        $this->assertStringContainsString('₹', $formatted);
    }

    #[Test]
    public function it_abbreviates_numbers_in_indian_units(): void
    {
        $this->assertSame('0', Number::abbreviateIndian(0));
        $this->assertSame('500', Number::abbreviateIndian(500));
        $this->assertSame('1.5K', Number::abbreviateIndian(1500));
        $this->assertSame('50K', Number::abbreviateIndian(50000));
        $this->assertSame('1.5 Lakh', Number::abbreviateIndian(150000));
        $this->assertSame('42.3 Lakh', Number::abbreviateIndian(4230000));
        $this->assertSame('2.5 Cr', Number::abbreviateIndian(25000000));
        $this->assertSame('100 Cr', Number::abbreviateIndian(1000000000));
        $this->assertSame('5,000 Cr', Number::abbreviateIndian(50000000000));
        $this->assertSame('10 Trillion', Number::abbreviateIndian(10000000000000));
        $this->assertSame('15.5 Trillion', Number::abbreviateIndian(15500000000000));
        $this->assertSame('-4.5 Lakh', Number::abbreviateIndian(-450000));
    }

    #[Test]
    public function it_abbreviates_numbers_with_short_units_when_requested(): void
    {
        $this->assertSame('1.5L', Number::abbreviateIndian(150000, shortUnits: true));
        $this->assertSame('2.5Cr', Number::abbreviateIndian(25000000, shortUnits: true));
        $this->assertSame('100Cr', Number::abbreviateIndian(1000000000, shortUnits: true));
        $this->assertSame('10T', Number::abbreviateIndian(10000000000000, shortUnits: true));
    }

    #[Test]
    public function it_formats_indian_numbers_for_humans_in_words(): void
    {
        $this->assertSame('1.5 thousand', Number::forHumansIndian(1500, abbreviate: false));
        $this->assertSame('42.3 lakh', Number::forHumansIndian(4230000, abbreviate: false));
        $this->assertSame('2.5 crore', Number::forHumansIndian(25000000, abbreviate: false));
        $this->assertSame('100 crore', Number::forHumansIndian(1000000000, abbreviate: false));
        $this->assertSame('10 trillion', Number::forHumansIndian(10000000000000, abbreviate: false));
    }

    #[Test]
    public function it_formats_currency_for_humans(): void
    {
        $this->assertSame('₹1.5K', Number::currencyForHumans(1500));
        $this->assertSame('₹50K', Number::currencyForHumans(50000));
        $this->assertSame('₹42.3 Lakh', Number::currencyForHumans(4230000));
        $this->assertSame('₹2.5 Cr', Number::currencyForHumans(25000000));
        $this->assertSame('-₹1.5 Lakh', Number::currencyForHumans(-150000));
    }

    #[Test]
    public function it_abbreviates_currency_above_threshold(): void
    {
        // Below 50,000 threshold -> standard formatted currency
        $below = Number::abbreviateCurrency(12000, maxValue: 50000);
        $this->assertStringContainsString('12,000', $below);
        $this->assertStringContainsString('₹', $below);

        // Above 50,000 threshold -> human readable abbreviated currency
        $above = Number::abbreviateCurrency(150000, maxValue: 50000);
        $this->assertSame('₹1.5 Lakh', $above);

        $crore = Number::abbreviateCurrency(25000000, maxValue: 50000);
        $this->assertSame('₹2.5 Cr', $crore);

        $zero = Number::abbreviateCurrency(0);
        $this->assertStringContainsString('0', $zero);
    }

    #[Test]
    public function it_converts_amounts_to_indian_words_for_invoices(): void
    {
        $this->assertSame('Rupees Zero Only', Number::inWords(0));
        $this->assertSame('Minus Rupees Five Thousand Only', Number::inWords(-5000));
        $this->assertSame('Rupees Five Thousand Only', Number::inWords(5000));
        $this->assertSame('Rupees One Lakh Only', Number::inWords(100000));
        $this->assertSame('Rupees One Crore Only', Number::inWords(10000000));
        $this->assertSame(
            'Rupees One Lakh Fifty Two Thousand Three Hundred Forty and Fifty Paise Only',
            Number::inWords(152340.50),
        );
    }

    #[Test]
    public function it_inherits_all_base_laravel_number_helpers(): void
    {
        $this->assertSame('1 KB', Number::fileSize(1024));
        $this->assertSame('50%', Number::percentage(50));
        $this->assertSame(10, Number::clamp(15, 0, 10));
        $this->assertSame('1st', Number::ordinal(1));
    }
}
