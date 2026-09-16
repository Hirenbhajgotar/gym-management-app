<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

class ValidEmailDomain implements ValidationRule
{
    /**
     * Optional closure seam for testing DNS resolution.
     *
     * @var (Closure(string): bool)|null
     */
    public static ?Closure $resolver = null;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string):PotentiallyTranslatedString  $fail
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

        $domain = strtolower(trim($parts[1]));
        if ($domain === '' || strlen($domain) > 253) {
            return;
        }

        // If DNS checking is disabled via config, pass through
        if (! config('request-demo.dns_check_enabled', true)) {
            return;
        }

        if (! $this->isDomainResolvable($domain)) {
            $fail('The :attribute domain does not appear to be valid. Please check for typos.');
        }
    }

    /**
     * Check whether domain has valid MX or A DNS records, with positive & short negative caching.
     */
    protected function isDomainResolvable(string $domain): bool
    {
        if (static::$resolver !== null) {
            return (static::$resolver)($domain);
        }

        $cacheKey = 'valid_email_domain:' . $domain;

        if (Cache::has($cacheKey)) {
            return (bool) Cache::get($cacheKey);
        }

        if (! function_exists('checkdnsrr')) {
            return true;
        }

        try {
            $hasDns = @checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A');

            $ttl = $hasDns
                ? config()->integer('request-demo.dns_cache_ttl_seconds', 86400)
                : config()->integer('request-demo.dns_negative_cache_ttl_seconds', 300);

            Cache::put($cacheKey, $hasDns, $ttl);

            return $hasDns;
        } catch (Throwable) {
            // Fail-open on lookup exception so transient DNS/network glitches do not block legitimate users
            return true;
        }
    }
}
