<?php

namespace App\Providers;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Interfaces\WhatsAppProviderInterface;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Provider Factory.
 *
 * Creates the appropriate WhatsApp provider based on:
 * 1. Per-tenant provider_type in whatsapp_accounts table (preferred)
 * 2. Config fallback for backward compatibility
 *
 * Caching: Uses Cache::flexible() for SWR (Stale-While-Revalidate) pattern.
 * Caches for 1 day minimum, up to 7 days max. Auto-refreshes before expiration.
 * Use invalidateProviderCache() to manually clear cache when needed.
 */
class WhatsAppProviderFactory
{
    /**
     * Cache TTL - minimum 1 day, maximum 1 week (SWR pattern).
     */
    private const CACHE_MIN_TTL = 86400;    // 1 day in seconds

    private const CACHE_MAX_TTL = 604800;  // 1 week in seconds

    /**
     * Cache key prefix.
     */
    private const CACHE_PREFIX = 'whatsapp_provider:';

    /**
     * Supported providers.
     */
    public const PROVIDER_TWILIO = 'twilio';

    public const PROVIDER_META = 'waba';

    public const DEFAULT_PROVIDER = self::PROVIDER_META;

    /**
     * Create a WhatsApp provider instance for the given gym.
     *
     * @param  int  $gymId  The gym ID
     * @param  bool  $isSystemMessage  If true, uses Twilio for system messages (super-admin)
     *
     * @throws \InvalidArgumentException If provider is not supported
     */
    public static function create(int $gymId, bool $isSystemMessage = false): WhatsAppProviderInterface
    {
        // System messages always use Twilio
        if ($isSystemMessage) {
            Log::debug('Creating WhatsApp provider for system message', [
                'gym_id' => $gymId,
                'provider' => self::PROVIDER_TWILIO,
            ]);

            return new TwilioWhatsAppProvider($gymId);
        }

        // Get provider from cache or database
        $provider = self::getProviderForGym($gymId);

        Log::debug('Creating WhatsApp provider', [
            'gym_id' => $gymId,
            'provider' => $provider,
        ]);

        return match ($provider) {
            self::PROVIDER_TWILIO => new TwilioWhatsAppProvider($gymId),
            self::PROVIDER_META => new MetaWhatsAppProvider($gymId),
            default => throw new \InvalidArgumentException(
                "Unsupported WhatsApp provider: {$provider}. " .
                'Supported providers: ' . implode(', ', [self::PROVIDER_TWILIO, self::PROVIDER_META])
            ),
        };
    }

    /**
     * Get the provider for a specific gym (per-tenant) with config fallback.
     * Uses Cache::flexible() for SWR pattern - caches 1 day min, 1 week max.
     *
     * @param  int  $gymId  The gym ID
     * @return string The provider name
     */
    public static function getProviderForGym(int $gymId): string
    {
        $cacheKey = self::CACHE_PREFIX . $gymId;

        return Cache::flexible(
            $cacheKey,
            [self::CACHE_MIN_TTL, self::CACHE_MAX_TTL],
            function () use ($gymId) {
                // Try to get provider from WhatsAppAccount (per-tenant)
                $account = WhatsAppAccount::where('gym_id', $gymId)->first();

                if ($account && $account->provider_type) {
                    // Convert enum to string constant
                    return match ($account->provider_type) {
                        WhatsAppProviderTypeEnum::TWILIO => self::PROVIDER_TWILIO,
                        WhatsAppProviderTypeEnum::WABA => self::PROVIDER_META,
                    };
                }

                // Fallback to config (backward compatibility)
                return config('services.whatsapp.default_provider', self::DEFAULT_PROVIDER);
            }
        );
    }

    /**
     * Invalidate provider cache for a specific gym.
     * Call this when provider_type is changed in WhatsAppAccount.
     *
     * @param  int  $gymId  The gym ID
     */
    public static function invalidateProviderCache(int $gymId): void
    {
        Cache::forget(self::CACHE_PREFIX . $gymId);
    }

    /**
     * Invalidate all provider caches.
     * Use with caution - clears all cached provider types.
     */
    public static function invalidateAllProviderCache(): void
    {
        Cache::flush();
    }

    /**
     * Get all supported providers.
     *
     * @return array<string, string>
     */
    public static function getSupportedProviders(): array
    {
        return [
            self::PROVIDER_TWILIO => 'Twilio',
            self::PROVIDER_META => 'Meta WhatsApp Business API',
        ];
    }
}
