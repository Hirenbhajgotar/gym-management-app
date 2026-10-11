<?php

declare(strict_types=1);

namespace App\Biometrics\Services;

use App\Models\BiometricDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

class BiometricTimestampParser
{
    /**
     * Parse device local timestamp string, normalize to application timezone, and enforce skew window.
     *
     * @throws InvalidArgumentException on empty string, unparseable date, or skew violation
     */
    public static function parse(string $timestampStr, BiometricDevice $device): Carbon
    {
        throw_if(blank($timestampStr), InvalidArgumentException::class, 'Timestamp string cannot be empty');

        $defaultTimezone = config()->string('app.timezone', 'UTC');
        $setting = $device->gym->settings->firstWhere('key', 'timezone');
        $rawTz = $setting ? $setting->value : null;

        if (is_string($rawTz) && str_starts_with($rawTz, '"') && str_ends_with($rawTz, '"')) {
            $rawTz = json_decode($rawTz, true);
        }

        $validTz = filled($rawTz) && in_array($rawTz, timezone_identifiers_list(), true)
            ? $rawTz
            : $defaultTimezone;

        $parsed = Date::parse(trim($timestampStr), $validTz)->setTimezone($defaultTimezone);

        throw_if($parsed->isAfter(now()->addMinutes(10)) || $parsed->isBefore(now()->subDays(30)), InvalidArgumentException::class, 'Timestamp outside acceptable skew window');

        return $parsed;
    }
}
