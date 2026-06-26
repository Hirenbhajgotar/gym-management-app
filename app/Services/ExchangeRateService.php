<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for fetching and caching USD to INR exchange rates.
 * Uses Frankfurter API (free, no API key required).
 */
class ExchangeRateService
{
    public const CACHE_KEY = 'exchange_rate_usd_inr';

    public const CACHE_MIN_TTL_SECONDS = 3600; // 1 hour minimum

    public const CACHE_MAX_TTL_SECONDS = 43200; // 12 hours maximum

    public const LOCK_KEY = 'exchange_rate_fetch_lock';

    public const LOCK_TTL_SECONDS = 300; // 5 minutes

    private ?array $cachedRates = null;

    public const API_URL = 'https://api.frankfurter.dev/v1/latest?from=USD';

    public const FALLBACK_RATES = [
        'INR' => 83.0,
        'PKR' => 278.0,
        'BDT' => 110.0,
        'LKR' => 305.0,
        'NPR' => 133.0,
        'AED' => 3.67,
        'SAR' => 3.75,
        'GBP' => 0.79,
        'EUR' => 0.92,
        'MYR' => 4.70,
        'SGD' => 1.34,
        'AUD' => 1.53,
        'CAD' => 1.36,
        'JPY' => 149.0,
        'CNY' => 7.24,
    ];

    /**
     * Convert USD amount to INR.
     */
    public function toInr(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'INR');
    }

    /**
     * Convert USD amount to PKR.
     */
    public function toPkr(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'PKR');
    }

    /**
     * Convert USD amount to BDT.
     */
    public function toBdt(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'BDT');
    }

    /**
     * Convert USD amount to LKR.
     */
    public function toLkr(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'LKR');
    }

    /**
     * Convert USD amount to NPR.
     */
    public function toNpr(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'NPR');
    }

    /**
     * Convert USD amount to AED.
     */
    public function toAed(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'AED');
    }

    /**
     * Convert USD amount to SAR.
     */
    public function toSar(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'SAR');
    }

    /**
     * Convert USD amount to GBP.
     */
    public function toGbp(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'GBP');
    }

    /**
     * Convert USD amount to EUR.
     */
    public function toEur(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'EUR');
    }

    /**
     * Convert USD amount to MYR.
     */
    public function toMyr(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'MYR');
    }

    /**
     * Convert USD amount to SGD.
     */
    public function toSgd(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'SGD');
    }

    /**
     * Convert USD amount to AUD.
     */
    public function toAud(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'AUD');
    }

    /**
     * Convert USD amount to CAD.
     */
    public function toCad(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'CAD');
    }

    /**
     * Convert USD amount to JPY.
     */
    public function toJpy(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'JPY');
    }

    /**
     * Convert USD amount to CNY.
     */
    public function toCny(float $usdAmount): float
    {
        return $this->convert($usdAmount, 'CNY');
    }

    /**
     * Magic method to handle dynamic currency conversion methods.
     * Allows calling toXxx($amount) for any 3-letter currency code.
     *
     * @example $service->toMxr(100) will convert to MXR
     */
    public function __call(string $method, array $arguments): float
    {
        if (str_starts_with($method, 'to') && strlen($method) === 5) {
            $currency = substr($method, 2);
            $amount = $arguments[0] ?? 0.0;

            return $this->convert((float) $amount, $currency);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on " . static::class);
    }

    /**
     * Convert USD amount to target currency.
     */
    public function convert(float $usdAmount, string $currency): float
    {
        $rate = $this->getRate($currency);

        return round($usdAmount * $rate, 4);
    }

    /**
     * Get the USD to target currency exchange rate.
     * Uses Cache::flexible for SWR pattern and Cache::lock for concurrent request protection.
     */
    public function getRate(string $currency): float
    {
        $currency = strtoupper($currency);
        $rates = $this->getAllRates();

        return (float) ($rates[$currency] ?? $this->getFallbackRate($currency));
    }

    /**
     * Get all cached exchange rates.
     */
    public function getAllRates(): array
    {
        if ($this->cachedRates !== null) {
            return $this->cachedRates;
        }

        if (Cache::has(self::CACHE_KEY)) {
            return $this->cachedRates = (array) Cache::get(self::CACHE_KEY);
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

        return $this->cachedRates = $lock->block(10, function () {
            // Double-check cache inside lock
            if (Cache::has(self::CACHE_KEY)) {
                return (array) Cache::get(self::CACHE_KEY);
            }

            return $this->fetchAndCacheRates();
        });
    }

    /**
     * Force refresh the exchange rates from API.
     */
    public function refreshRates(): array
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

        return $lock->block(10, function () {
            Cache::forget(self::CACHE_KEY);

            return $this->fetchAndCacheRates();
        });
    }

    /**
     * Fetch exchange rate from API and cache it.
     */
    private function fetchAndCacheRates(): array
    {
        try {
            $response = Http::timeout(5)->get(self::API_URL);

            if ($response->successful()) {
                $rates = (array) $response->json('rates');

                if (filled($rates)) {
                    // Cache with flexible TTL (SWR pattern)
                    // Cache will be valid for at least 1 hour, at most 12 hours
                    Cache::flexible(
                        self::CACHE_KEY,
                        [self::CACHE_MIN_TTL_SECONDS, self::CACHE_MAX_TTL_SECONDS],
                        fn () => $rates
                    );

                    Log::info('Exchange rates updated', [
                        'currencies' => count($rates),
                        'source' => 'frankfurter',
                    ]);

                    return $rates;
                }
            }

            Log::warning('Exchange rate API returned invalid rate', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch exchange rate', [
                'error' => $e->getMessage(),
            ]);
        }

        // Return cached rates if available, otherwise fallback
        $cachedRates = Cache::get(self::CACHE_KEY);

        if ($cachedRates !== null) {
            return (array) $cachedRates;
        }

        return self::FALLBACK_RATES;
    }

    /**
     * Get fallback rate for a specific currency.
     */
    private function getFallbackRate(string $currency): float
    {
        return (float) (self::FALLBACK_RATES[$currency] ?? 1.0);
    }
}
