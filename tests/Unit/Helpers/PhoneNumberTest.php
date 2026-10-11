<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\PhoneNumber;
use Illuminate\Support\Stringable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    #[Test]
    public function get_iso_country_code_returns_in_for_indian_number(): void
    {
        $this->assertEquals('IN', PhoneNumber::getIsoCountryCode('+919261212121'));
    }

    #[Test]
    public function get_iso_country_code_returns_null_for_us_number_due_to_shared_country_code(): void
    {
        $this->assertNull(PhoneNumber::getIsoCountryCode('+15551234567'));
    }

    #[Test]
    public function get_iso_country_code_returns_gg_for_guernsey_number(): void
    {
        $this->assertEquals('GG', PhoneNumber::getIsoCountryCode('+447911123456'));
    }

    #[Test]
    public function get_iso_country_code_returns_null_for_invalid_number(): void
    {
        $this->assertNull(PhoneNumber::getIsoCountryCode('not-a-number'));
    }

    #[Test]
    public function get_iso_country_code_returns_null_when_default_country_code_is_shared(): void
    {
        $this->assertNull(PhoneNumber::getIsoCountryCode('5551234567', '+1'));
    }

    #[Test]
    public function get_iso_country_code_handles_number_with_spaces(): void
    {
        $this->assertEquals('IN', PhoneNumber::getIsoCountryCode('+91 92612 12121'));
    }

    #[Test]
    public function get_intl_calling_code_returns_91_for_in(): void
    {
        $this->assertEquals('91', PhoneNumber::getIntlCallingCode('IN'));
    }

    #[Test]
    public function get_intl_calling_code_returns_1_for_us(): void
    {
        $this->assertEquals('1', PhoneNumber::getIntlCallingCode('US'));
    }

    #[Test]
    public function get_intl_calling_code_returns_44_for_gb(): void
    {
        $this->assertEquals('44', PhoneNumber::getIntlCallingCode('GB'));
    }

    #[Test]
    public function get_intl_calling_code_returns_null_for_invalid_iso_code(): void
    {
        $this->assertNull(PhoneNumber::getIntlCallingCode('XX'));
    }

    #[Test]
    public function get_intl_calling_code_returns_null_for_empty_string(): void
    {
        $this->assertNull(PhoneNumber::getIntlCallingCode(''));
    }

    #[Test]
    public function get_intl_calling_code_returns_null_for_too_long_string(): void
    {
        $this->assertNull(PhoneNumber::getIntlCallingCode('USA'));
    }

    #[Test]
    public function get_intl_calling_code_is_case_insensitive(): void
    {
        $this->assertEquals('91', PhoneNumber::getIntlCallingCode('in'));
        $this->assertEquals('1', PhoneNumber::getIntlCallingCode('us'));
    }

    #[Test]
    public function get_intl_calling_code_returns_null_for_numeric_string(): void
    {
        $this->assertNull(PhoneNumber::getIntlCallingCode('91'));
    }

    #[Test]
    public function get_dialing_code_from_number_returns_91_for_indian_number(): void
    {
        $this->assertEquals('91', PhoneNumber::getDialingCodeFromNumber('+919261212121'));
    }

    #[Test]
    public function get_dialing_code_from_number_returns_null_for_us_number(): void
    {
        $this->assertNull(PhoneNumber::getDialingCodeFromNumber('+15551234567'));
    }

    #[Test]
    public function get_dialing_code_from_number_returns_null_for_invalid_number(): void
    {
        $this->assertNull(PhoneNumber::getDialingCodeFromNumber('garbage'));
    }

    #[Test]
    public function get_dialing_code_from_number_returns_null_when_iso_resolution_fails(): void
    {
        $this->assertNull(PhoneNumber::getDialingCodeFromNumber('5551234567', '+1'));
    }

    #[Test]
    public function format_phone_number_preserves_existing_plus_prefix(): void
    {
        $this->assertEquals('+919261212121', PhoneNumber::formatPhoneNumberWithCountryCode('+919261212121'));
    }

    #[Test]
    public function format_phone_number_prepends_default_country_code_when_missing(): void
    {
        $this->assertEquals('+91919261212121', PhoneNumber::formatPhoneNumberWithCountryCode('919261212121'));
    }

    #[Test]
    public function format_phone_number_strips_spaces_and_special_characters(): void
    {
        $this->assertEquals('+919261212121', PhoneNumber::formatPhoneNumberWithCountryCode('+91 92612 12121'));
    }

    #[Test]
    public function format_phone_number_strips_parens_and_dashes(): void
    {
        $this->assertEquals('+15551234567', PhoneNumber::formatPhoneNumberWithCountryCode('+1 (555) 123-4567'));
    }

    #[Test]
    public function format_phone_number_uses_custom_default_country_code(): void
    {
        $this->assertEquals('+15551234567', PhoneNumber::formatPhoneNumberWithCountryCode('5551234567', '+1'));
    }

    #[Test]
    public function format_phone_number_handles_stringable_input(): void
    {
        $this->assertEquals('+919261212121', PhoneNumber::formatPhoneNumberWithCountryCode(new Stringable('+91 92612 12121')));
    }

    #[Test]
    public function format_phone_number_returns_plus_digits_for_numeric_only_input(): void
    {
        $this->assertEquals('+91919261212121', PhoneNumber::formatPhoneNumberWithCountryCode('919261212121'));
    }

    #[Test]
    public function normalize_phone_number_strips_all_non_digits(): void
    {
        $this->assertEquals('919261212121', PhoneNumber::normalizePhoneNumber('+91 92612 12121'));
    }

    #[Test]
    public function normalize_phone_number_strips_plus_sign(): void
    {
        $this->assertEquals('15551234567', PhoneNumber::normalizePhoneNumber('+1 (555) 123-4567'));
    }

    #[Test]
    public function normalize_phone_number_returns_digits_only_for_clean_input(): void
    {
        $this->assertEquals('919261212121', PhoneNumber::normalizePhoneNumber('919261212121'));
    }

    #[Test]
    public function normalize_phone_number_returns_empty_string_for_empty_input(): void
    {
        $this->assertEquals('', PhoneNumber::normalizePhoneNumber(''));
    }

    #[Test]
    public function normalize_phone_number_handles_dashes_only(): void
    {
        $this->assertEquals('', PhoneNumber::normalizePhoneNumber('---'));
    }

    #[Test]
    public function to_dialable_e164_returns_a_valid_number(): void
    {
        $this->assertSame('+919261212121', PhoneNumber::toDialableE164('+919261212121'));
    }

    #[Test]
    public function to_dialable_e164_normalises_a_local_number_using_the_default_country_code(): void
    {
        $this->assertSame('+919261212121', PhoneNumber::toDialableE164('9261212121'));
    }

    #[DataProvider('nonCanonicalInputs')]
    public function to_dialable_e164_returns_the_canonical_form_of_the_number_it_validated(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toDialableE164($input));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function nonCanonicalInputs(): array
    {
        return [
            'already canonical' => ['+919261212121', '+919261212121'],
            'national trunk zero' => ['09261212121', '+919261212121'],
            'separators and spaces' => ['+91 92612 12121', '+919261212121'],
            'bare national number' => ['9261212121', '+919261212121'],
        ];
    }

    /**
     * An international access prefix is refused rather than dialled.
     * formatPhoneNumberWithCountryCode() strips to digits and prepends the
     * default code, so "009191261212121" becomes "+910091912612121" — a
     * different, invalid number. Null is the safe outcome; teaching that shared
     * formatter to read the prefix belongs there, not here.
     */
    #[Test]
    public function to_dialable_e164_refuses_an_international_access_prefix(): void
    {
        $this->assertNull(PhoneNumber::toDialableE164('009191261212121'));
    }

    /**
     * Whatever came out before, the returned value has to survive a re-parse as
     * the same number it validated. That round-trip is the property callers
     * actually depend on, and it is what the trunk-zero case broke.
     */
    #[Test]
    public function to_dialable_e164_output_round_trips_to_the_same_number(): void
    {
        foreach (['09261212121', '9261212121', '+91 92612 12121'] as $input) {
            $dialable = PhoneNumber::toDialableE164($input);

            $this->assertNotNull($dialable, "[{$input}] should resolve.");
            $this->assertSame(
                PhoneNumber::toDialableE164($dialable),
                $dialable,
                "[{$input}] did not round-trip: [{$dialable}] parsed as a different number."
            );
        }
    }

    /**
     * formatPhoneNumberWithCountryCode() turns this into "+91", a string that
     * passes every emptiness check but is not a number.
     */
    #[Test]
    public function to_dialable_e164_returns_null_for_alpha_input(): void
    {
        $this->assertNull(PhoneNumber::toDialableE164('abc'));
    }

    #[Test]
    public function to_dialable_e164_returns_null_for_empty_input(): void
    {
        $this->assertNull(PhoneNumber::toDialableE164(''));
        $this->assertNull(PhoneNumber::toDialableE164('   '));
    }

    /**
     * The validity check, not just possibility: libphonenumber reports this
     * number as possible (right country code, right length) but not valid,
     * because no assignable Indian range covers the 0000 operator prefix.
     */
    #[Test]
    public function to_dialable_e164_returns_null_for_a_possible_but_invalid_number(): void
    {
        $this->assertNull(PhoneNumber::toDialableE164('+910000000000'));
    }

    #[Test]
    public function to_dialable_e164_handles_stringable_input(): void
    {
        $this->assertSame('+919261212121', PhoneNumber::toDialableE164(new Stringable('+91 92612 12121')));
    }
}
