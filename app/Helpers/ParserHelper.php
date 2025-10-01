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
    public static function formatPhoneNumberWithCountryCode(string|Stringable $phone): string
    {
        // Make sure we are working with a plain string
        $phone = (string) $phone;

        // 1. Remove every non-digit except an optional leading “+”
        $clean = preg_replace('/(?!^\+)\D/', '', $phone);

        // 2. Must start with “+” followed by at least one digit
        if (!preg_match('/^\+\d+$/', $clean)) {
            throw new InvalidArgumentException(
                'Phone number must include country code (e.g. +911234567897).'
            );
        }

        return $clean;
    }
}
