<?php

declare(strict_types=1);

namespace App\Helpers;

use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Support\Facades\Cache;
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
     * @throws InvalidArgumentException
     */
    public static function formatPhoneNumberWithCountryCode(string|Stringable $phone, $defaultCountryCode = null): string
    {
        $phone = (string) $phone;

        // 1. Strip everything that is not a digit or a leading "+"
        $clean = preg_replace("/(?!^\+)\D/", '', $phone);

        // 2. If the number already starts with "+" we are done
        if (str_starts_with($clean, '+')) {
            return $clean;
        }

        // 3. Otherwise prepend the default country code
        $countryCode =
            $defaultCountryCode ?? config('app.default_country_code', '+91'); // fallback to +91

        // Ensure we have a single "+" and no spaces
        $countryCode = '+' . ltrim($countryCode, '+');

        return $countryCode . $clean;
    }

    /**
     * Normalize phone number by removing country code prefix (+91, +1, etc.)
     * Also removes spaces, dashes, and parentheses.
     */
    public static function normalizePhoneNumber(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * Check if the email is valid.
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Get the ISO alpha-2 country code from a given phone number.
     *
     * @return string|null The country code (e.g., 'IN', 'US') or null on failure.
     */
    public static function getCountryCode(string $phoneNumber, ?string $defaultCountryCode = null): ?string
    {
        try {
            $formattedNumber = self::formatPhoneNumberWithCountryCode($phoneNumber, $defaultCountryCode);
            $phone = new PhoneNumber($formattedNumber);

            return $phone->getCountry();
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getDurationLabel(int $months): string
    {
        if ($months % 12 === 0) {
            $years = $months / 12;

            return "{$years} " . ($years === 1 ? 'Year' : 'Years');
        }

        return "{$months} " . ($months === 1 ? 'Month' : 'Months');
    }

    public static function clearCache(string|array $key): void
    {
        if (is_array($key)) {
            foreach ($key as $cacheKey) {
                Cache::forget($cacheKey);
            }
        } else {
            Cache::forget($key);
        }
    }
}
