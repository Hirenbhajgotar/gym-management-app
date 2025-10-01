<?php

namespace App\Helpers;

use Illuminate\Support\Stringable;
use InvalidArgumentException;

class ParserHelper
{
    /**
     * Clear phone number formats.
     */
    public static function clearPhoneNumberFormat(string $phoneNumber): string
    {
        return preg_replace('/[^0-9]/', '', $phoneNumber);
    }

    /**
     * Normalize an international phone number.
     *
     * @param  string|Stringable  $phone
     * @return string
     * @throws InvalidArgumentException
     */
    public static function formatPhoneNumberWithCountryCode(string|Stringable $phone, $defaultCountryCode = null): string
    {
        $phone = (string) $phone;

        // 1. Strip everything that is not a digit or a leading "+"
        $clean = preg_replace('/(?!^\+)\D/', '', $phone);

        // 2. If the number already starts with "+" we are done
        if (str_starts_with($clean, '+')) {
            return $clean;
        }

        // 3. Otherwise prepend the default country code
        $countryCode = $defaultCountryCode
            ?? config('app.default_country_code', '+91');   // fallback to +91

        // Ensure we have a single "+" and no spaces
        $countryCode = '+' . ltrim($countryCode, '+');

        return $countryCode . $clean;
    }
}
