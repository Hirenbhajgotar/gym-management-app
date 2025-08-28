<?php

namespace App\Helpers;

use Illuminate\Support\Number as SupportNumber;

class Number
{
    /**
     * Abbreviate a number with a currency symbol.
     *
     * @param  int|string  $value
     * @param  int  $maxValue
     * @param  string  $in
     * @return string
     */
    public static function abbreviateCurrency($value, $maxValue = 50000, $in = 'INR')
    {
        if (! $value) {
            // If the value is 0, return the formatted currency.
            return SupportNumber::currency($value, in: $in);
        }

        if ($value < $maxValue) {
            // If the value is less than the maximum, return the formatted currency.
            return SupportNumber::currency($value, in: $in);
        }

        // If the value is greater than the maximum, abbreviate the number.
        return SupportNumber::abbreviate($value, maxPrecision: 2);
    }

    /**
     * Clear phone number formats.
     */
    public static function clearPhoneNumberFormat(string $phoneNumber): string
    {
        return preg_replace('/[^0-9]/', '', $phoneNumber);
    }
}
