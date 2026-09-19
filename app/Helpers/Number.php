<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Number as SupportNumber;

class Number extends SupportNumber
{
    /**
     * The current default currency for Indian operations.
     */
    protected static string $indianCurrency = 'INR';

    /**
     * The currency symbol for Indian Rupee.
     */
    protected static string $indianCurrencySymbol = '₹';

    /**
     * Convert the given number to its currency equivalent (defaults to INR with Indian locale formatting).
     */
    public static function currency(int|float $number, string $in = 'INR', ?string $locale = 'en_IN', ?int $precision = null): string
    {
        return (string) parent::currency($number, in: $in, locale: $locale ?? 'en_IN', precision: $precision);
    }

    /**
     * Format the given number according to Indian grouping rules (lakhs, crores).
     */
    public static function formatIndian(int|float $number, ?int $precision = null, ?int $maxPrecision = null, ?string $locale = 'en_IN'): string
    {
        return (string) static::format($number, precision: $precision, maxPrecision: $maxPrecision, locale: $locale ?? 'en_IN');
    }

    /**
     * Convert the number to its Indian human-readable abbreviated equivalent (K, Lakh/L, Cr, Arab).
     */
    public static function abbreviateIndian(int|float $number, int $precision = 0, ?int $maxPrecision = 2, bool $shortUnits = false): string
    {
        return static::forHumansIndian($number, precision: $precision, maxPrecision: $maxPrecision, abbreviate: true, shortUnits: $shortUnits);
    }

    /**
     * Convert the number to its Indian human-readable equivalent in words or abbreviations.
     */
    public static function forHumansIndian(
        int|float $number,
        int $precision = 0,
        ?int $maxPrecision = 2,
        bool $abbreviate = false,
        bool $shortUnits = false
    ): string {
        if ($abbreviate) {
            $units = $shortUnits ? [
                12 => 'T',
                7 => 'Cr',
                5 => 'L',
                3 => 'K',
            ] : [
                12 => ' Trillion',
                7 => ' Cr',
                5 => ' Lakh',
                3 => 'K',
            ];
        } else {
            $units = [
                12 => ' trillion',
                7 => ' crore',
                5 => ' lakh',
                3 => ' thousand',
            ];
        }

        return static::summarizeIndian($number, precision: $precision, maxPrecision: $maxPrecision, units: $units);
    }

    /**
     * Convert the number to its human-readable Indian currency representation (e.g. ₹42.3 Lakh, ₹2.5 Cr, ₹50K, ₹500).
     */
    public static function currencyForHumans(
        int|float $number,
        string $in = 'INR',
        int $precision = 0,
        ?int $maxPrecision = 2,
        bool $abbreviate = true,
        bool $shortUnits = false,
        string $symbol = '₹'
    ): string {
        $isNegative = $number < 0;
        $absNumber = abs($number);

        if ($absNumber < 1000) {
            $formatted = static::currency($absNumber, in: $in, precision: $precision);

            // If currency symbol returned by intl matches or has currency, return standard
            return $isNegative ? '-' . $formatted : $formatted;
        }

        $humanValue = static::forHumansIndian(
            $absNumber,
            precision: $precision,
            maxPrecision: $maxPrecision,
            abbreviate: $abbreviate,
            shortUnits: $shortUnits
        );

        $result = $symbol . ltrim($humanValue);

        return $isNegative ? '-' . $result : $result;
    }

    /**
     * Abbreviate a number with Indian currency formatting above a threshold value.
     *
     * @param  int|float|string  $value
     * @param  int  $maxValue
     * @param  string  $in
     */
    public static function abbreviateCurrency($value, $maxValue = 50000, $in = 'INR', int $precision = 0, ?int $maxPrecision = 2): string
    {
        $value = (float) $value;

        if (! $value) {
            return static::currency(0, in: $in, precision: $precision);
        }

        if (abs($value) < $maxValue) {
            return static::currency($value, in: $in, precision: $precision);
        }

        return static::currencyForHumans($value, in: $in, precision: $precision, maxPrecision: $maxPrecision);
    }

    /**
     * Summarize numbers using Indian numbering brackets (Thousands, Lakhs, Crores up to 10 Trillion, Trillions).
     *
     * @param  array<int, string>  $units
     */
    protected static function summarizeIndian(
        int|float $number,
        int $precision = 0,
        ?int $maxPrecision = null,
        array $units = []
    ): string {
        if (blank($units)) {
            $units = [
                12 => ' Trillion',
                7 => ' Cr',
                5 => ' Lakh',
                3 => 'K',
            ];
        }

        if ((float) $number === 0.0) {
            return $precision > 0 ? (string) static::format(0, precision: $precision, maxPrecision: $maxPrecision) : '0';
        }

        if ($number < 0) {
            return sprintf('-%s', static::summarizeIndian(abs($number), precision: $precision, maxPrecision: $maxPrecision, units: $units));
        }

        $scales = [
            12 => [
                'threshold' => 10000000000000, // 10 Trillion (10^13)
                'divisor' => 1000000000000,    // 1 Trillion (10^12)
            ],
            7 => [
                'threshold' => 10000000,       // 1 Crore (10^7)
                'divisor' => 10000000,         // 1 Crore (10^7)
            ],
            5 => [
                'threshold' => 100000,         // 1 Lakh (10^5)
                'divisor' => 100000,           // 1 Lakh (10^5)
            ],
            3 => [
                'threshold' => 1000,           // 1 Thousand (10^3)
                'divisor' => 1000,             // 1 Thousand (10^3)
            ],
        ];

        foreach ($scales as $exponent => $config) {
            if ($number >= $config['threshold']) {
                $scaled = $number / $config['divisor'];
                $unit = $units[$exponent] ?? '';

                $formatted = (string) static::format($scaled, precision: $precision, maxPrecision: $maxPrecision, locale: 'en_IN');

                return trim($formatted . $unit);
            }
        }

        return (string) static::format($number, precision: $precision, maxPrecision: $maxPrecision, locale: 'en_IN');
    }

    /**
     * Spell out the given number in Indian English words (Lakhs, Crores).
     */
    public static function spellIndian(int|float $number, ?string $locale = 'en_IN'): string
    {
        return static::spell($number, locale: $locale ?? 'en_IN');
    }

    /**
     * Convert an amount into formal Indian English words (e.g. for receipts and invoices).
     */
    public static function inWords(int|float $number, string $currency = 'Rupees', string $fraction = 'Paise'): string
    {
        $amount = (float) $number;
        $isNegative = $amount < 0;
        $amount = abs($amount);

        $whole = (int) floor($amount);
        $paise = (int) round(($amount - $whole) * 100);

        $words = static::convertWholeNumberToIndianWords($whole);

        $result = $currency . ' ' . $words;

        if ($paise > 0) {
            $paiseWords = static::convertWholeNumberToIndianWords($paise);
            $result .= ' and ' . $paiseWords . ' ' . $fraction;
        }

        $result .= ' Only';

        return $isNegative ? 'Minus ' . $result : $result;
    }

    /**
     * Convert a whole integer to Indian numbering words recursively.
     */
    protected static function convertWholeNumberToIndianWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        if ($number < 0) {
            return 'Minus ' . static::convertWholeNumberToIndianWords(abs($number));
        }

        $units = [
            0 => '',
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
            6 => 'Six',
            7 => 'Seven',
            8 => 'Eight',
            9 => 'Nine',
            10 => 'Ten',
            11 => 'Eleven',
            12 => 'Twelve',
            13 => 'Thirteen',
            14 => 'Fourteen',
            15 => 'Fifteen',
            16 => 'Sixteen',
            17 => 'Seventeen',
            18 => 'Eighteen',
            19 => 'Nineteen',
        ];

        $tens = [
            2 => 'Twenty',
            3 => 'Thirty',
            4 => 'Forty',
            5 => 'Fifty',
            6 => 'Sixty',
            7 => 'Seventy',
            8 => 'Eighty',
            9 => 'Ninety',
        ];

        if ($number < 20) {
            return $units[$number];
        }

        if ($number < 100) {
            $ten = (int) floor($number / 10);
            $remainder = $number % 10;

            return trim($tens[$ten] . ' ' . $units[$remainder]);
        }

        if ($number < 1000) {
            $hundred = (int) floor($number / 100);
            $remainder = $number % 100;

            $words = $units[$hundred] . ' Hundred';
            if ($remainder > 0) {
                $words .= ' ' . static::convertWholeNumberToIndianWords($remainder);
            }

            return $words;
        }

        $brackets = [
            10000000 => 'Crore',
            100000 => 'Lakh',
            1000 => 'Thousand',
        ];

        foreach ($brackets as $divisor => $label) {
            if ($number >= $divisor) {
                $quotient = (int) floor($number / $divisor);
                $remainder = $number % $divisor;

                $words = static::convertWholeNumberToIndianWords($quotient) . ' ' . $label;
                if ($remainder > 0) {
                    $words .= ' ' . static::convertWholeNumberToIndianWords($remainder);
                }

                return $words;
            }
        }

        return (string) $number;
    }
}
