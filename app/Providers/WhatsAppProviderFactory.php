<?php

namespace App\Providers;

use App\Interfaces\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Provider Factory.
 *
 * Creates the appropriate WhatsApp provider based on the configured
 * provider type in services.php config.
 */
class WhatsAppProviderFactory
{
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
     *
     * @throws \InvalidArgumentException If provider is not supported
     */
    public static function create(int $gymId): WhatsAppProviderInterface
    {
        $provider = (string) config('services.whatsapp.provider', self::DEFAULT_PROVIDER);

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
     * Get the currently configured provider.
     *
     * @return string The provider name
     */
    public static function getProviderName(): string
    {
        return (string) config('services.whatsapp.provider', self::DEFAULT_PROVIDER);
    }

    /**
     * Check if a specific provider is configured.
     *
     * @param  string  $provider  The provider to check
     */
    public static function isProvider(string $provider): bool
    {
        return self::getProviderName() === $provider;
    }

    /**
     * Check if Twilio provider is configured.
     */
    public static function isTwilio(): bool
    {
        return self::isProvider(self::PROVIDER_TWILIO);
    }

    /**
     * Check if Meta WABA provider is configured.
     */
    public static function isMeta(): bool
    {
        return self::isProvider(self::PROVIDER_META);
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
