<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEmailDomain implements ValidationRule
{
    /**
     * List of popular email domains to check against for typos.
     */
    protected array $popularDomains = [
        'gmail.com',
        'yahoo.com',
        'hotmail.com',
        'outlook.com',
        'icloud.com',
        'aol.com',
        'protonmail.com',
        'zoho.com',
        'yandex.com',
        'live.com',
        'msn.com',
        'ymail.com',
    ];

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // Extract domain
        $parts = explode('@', $value);
        if (count($parts) !== 2) {
            return; // Let 'email' rule handle invalid format
        }

        $domain = strtolower($parts[1]);

        // Check if it's one of the popular domains exactly (valid)
        if (in_array($domain, $this->popularDomains)) {
            return;
        }

        // Check for typos in popular domains
        foreach ($this->popularDomains as $popularDomain) {
            // Calculate Levenshtein distance
            $distance = levenshtein($domain, $popularDomain);

            // If the distance is small (1 or 2), it's likely a typo
            // We use a flat threshold of 2 to catch swaps (e.g. gmali.com -> gmail.com, dist=2)
            if ($distance > 0 && $distance <= 2) {
                $fail("The :attribute domain '{$domain}' looks like a typo of '{$popularDomain}'.");

                return;
            }
        }
    }
}
