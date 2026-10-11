<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Stringable;
use InvalidArgumentException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Propaganistas\LaravelPhone\PhoneNumber as LaravelPhonePhoneNumber;
use Throwable;

class PhoneNumber
{
    /**
     * Get ISO 3166-1 alpha-2 country code from a phone number (e.g., 'IN', 'US').
     */
    public static function getIsoCountryCode(string $phoneNumber, ?string $defaultCountryCode = null): ?string
    {
        try {
            $formattedNumber = ParserHelper::formatPhoneNumberWithCountryCode($phoneNumber, $defaultCountryCode);
            $phone = new LaravelPhonePhoneNumber($formattedNumber);

            return $phone->getCountry();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get international calling/dialing code (e.g., '91', '1') for a given ISO 2-letter country code.
     */
    public static function getIntlCallingCode(string $isoCountryCode): ?string
    {
        $isoCountryCode = strtoupper(trim($isoCountryCode));

        if (strlen($isoCountryCode) !== 2 || ! ctype_alpha($isoCountryCode)) {
            return null;
        }

        try {
            $dialCode = PhoneNumberUtil::getInstance()->getCountryCodeForRegion($isoCountryCode);

            return $dialCode > 0 ? (string) $dialCode : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get international country dialing code directly from a phone number (e.g., '91' for '+919261212121').
     */
    public static function getDialingCodeFromNumber(string $phoneNumber, ?string $defaultCountryCode = null): ?string
    {
        $isoCode = self::getIsoCountryCode($phoneNumber, $defaultCountryCode);

        return $isoCode !== null ? self::getIntlCallingCode($isoCode) : null;
    }

    /**
     * Normalize an international phone number.
     *
     * @throws InvalidArgumentException
     */
    public static function formatPhoneNumberWithCountryCode(string|Stringable $phone, ?string $defaultCountryCode = null): string
    {
        $phone = (string) $phone;

        // 1. Strip everything that is not a digit or a leading "+"
        $clean = preg_replace("/(?!^\+)\D/", '', $phone) ?? '';

        // 2. If the number already starts with "+" we are done
        if (str_starts_with($clean, '+')) {
            return $clean;
        }

        // 3. Otherwise prepend the default country code
        $countryCode = $defaultCountryCode ?? config()->string('app.default_country_code', '+91');

        // Ensure we have a single "+" and no spaces
        $countryCode = '+' . ltrim($countryCode, '+');

        return $countryCode . $clean;
    }

    /**
     * Normalize a phone number to E.164, or return null when the result could
     * not actually be dialled.
     *
     * formatPhoneNumberWithCountryCode() only strips characters and prepends the
     * default country code, so a value such as "abc" becomes "+91" — a string
     * that passes every emptiness check but is not a real number. Callers that
     * hand the result to a messaging provider must use this; callers that only
     * display or store the number may keep using formatPhoneNumberWithCountryCode().
     *
     * Both isPossibleNumber() and isValidNumber() are required. Possibility
     * alone accepts values that carry the right shape but cannot be dialled, so
     * a "possible but invalid" number would still reach a provider.
     *
     * What comes back is the library's own E.164 rendering of the number it
     * validated, not the string that was parsed. The two disagree whenever the
     * input needs normalising — "09876543210" strips to "+9109876543210", which
     * parses as country code 91 plus a valid ten-digit national number, so it
     * validates while being a digit away from the number its owner registered.
     */
    public static function toDialableE164(string|Stringable $phone, ?string $defaultCountryCode = null): ?string
    {
        $raw = trim((string) $phone);

        if ($raw === '') {
            return null;
        }

        $formatted = self::formatPhoneNumberWithCountryCode($raw, $defaultCountryCode);

        try {
            $util = PhoneNumberUtil::getInstance();
            $parsed = $util->parse($formatted);

            if (! $util->isPossibleNumber($parsed) || ! $util->isValidNumber($parsed)) {
                return null;
            }

            return $util->format($parsed, PhoneNumberFormat::E164);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Normalize phone number by removing country code prefix (+91, +1, etc.)
     * Also removes spaces, dashes, and parentheses.
     */
    public static function normalizePhoneNumber(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }
}
