<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\WhatsAppPhoneNumber;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppPhoneNumberTest extends TestCase
{
    #[Test]
    public function it_strips_spaces_from_international_number(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '+91 92612 12121']);

        $this->assertEquals('+919261212121', $number->phone_number);
    }

    #[Test]
    public function it_strips_parens_and_dashes_from_us_number(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '+1 (555) 123-1234']);

        $this->assertEquals('+15551231234', $number->phone_number);
    }

    #[Test]
    public function it_prepends_plus_when_missing(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '919261212121']);

        $this->assertEquals('+919261212121', $number->phone_number);
    }

    #[Test]
    public function it_strips_dashes_from_number_with_plus(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '+91-92612-12121']);

        $this->assertEquals('+919261212121', $number->phone_number);
    }

    #[Test]
    public function it_prepends_country_code_when_not_present_in_number(): void
    {
        $number = new WhatsAppPhoneNumber([
            'country_code' => '91',
            'phone_number' => '9261212121',
        ]);

        $this->assertEquals('+919261212121', $number->phone_number);
    }

    #[Test]
    public function it_does_not_double_prepend_country_code(): void
    {
        $number = new WhatsAppPhoneNumber([
            'country_code' => '91',
            'phone_number' => '919261212121',
        ]);

        $this->assertEquals('+919261212121', $number->phone_number);
    }

    #[Test]
    public function it_returns_null_for_blank_value(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '']);

        $this->assertNull($number->phone_number);
    }

    #[Test]
    public function it_returns_null_for_whitespace_only(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '   ']);

        $this->assertNull($number->phone_number);
    }

    #[Test]
    public function it_returns_null_for_dashes_only(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '---']);

        $this->assertNull($number->phone_number);
    }

    #[Test]
    public function it_returns_null_for_plus_only(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '+']);

        $this->assertNull($number->phone_number);
    }

    #[Test]
    public function get_formatted_number_returns_e164_format(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '+91 92612 12121']);

        $this->assertEquals('+919261212121', $number->phone_number);
    }

    #[Test]
    public function get_formatted_number_returns_null_for_blank(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '']);

        $this->assertNull($number->phone_number);
    }

    #[Test]
    public function it_handles_uk_number(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '+44 7911 123456']);

        $this->assertEquals('+447911123456', $number->phone_number);
    }

    #[Test]
    public function accessor_is_idempotent_on_stored_value(): void
    {
        $number = new WhatsAppPhoneNumber(['phone_number' => '+918469290865']);

        $this->assertEquals('+918469290865', $number->phone_number);
    }
}
