<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidEmailDomain implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string):PotentiallyTranslatedString  $fail
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

        $domain = $parts[1];

        // Check for valid MX records
        if (! checkdnsrr($domain, 'MX')) {
            $fail('The :attribute domain does not appear to be valid. Please check for typos.');
        }
    }
}
