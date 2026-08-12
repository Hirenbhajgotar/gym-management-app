<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Providers\MetaWhatsAppProvider;
use App\Providers\TwilioWhatsAppProvider;
use App\Providers\WhatsAppProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Tests for WhatsAppProviderFactory — provider resolution, caching, and invalidation.
 *
 * @see WhatsAppChannelTest for channel-level tests
 */
class WhatsAppProviderFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_create_returns_twilio_provider_for_system_messages(): void
    {
        $gym = Gym::factory()->create();

        $provider = WhatsAppProviderFactory::create($gym->id, true);

        $this->assertInstanceOf(TwilioWhatsAppProvider::class, $provider);
    }

    public function test_create_returns_twilio_provider_when_account_configured_with_twilio(): void
    {
        $gym = Gym::factory()->create();

        WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
        ]);

        $provider = WhatsAppProviderFactory::create($gym->id);

        $this->assertInstanceOf(TwilioWhatsAppProvider::class, $provider);
    }

    public function test_create_returns_meta_provider_when_account_configured_with_waba(): void
    {
        $gym = Gym::factory()->create();

        WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
        ]);

        $provider = WhatsAppProviderFactory::create($gym->id);

        $this->assertInstanceOf(MetaWhatsAppProvider::class, $provider);
    }

    public function test_create_uses_config_fallback_when_no_account_exists(): void
    {
        $gym = Gym::factory()->create();

        config(['services.whatsapp.default_provider' => 'twilio']);

        $provider = WhatsAppProviderFactory::create($gym->id);

        $this->assertInstanceOf(TwilioWhatsAppProvider::class, $provider);
    }

    public function test_create_throws_exception_for_unsupported_provider_type(): void
    {
        $gym = Gym::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported WhatsApp provider');

        WhatsAppProviderFactory::create($gym->id, false, 'unsupported_provider');
    }

    public function test_get_provider_for_gym_caches_result(): void
    {
        $gym = Gym::factory()->create();

        WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
        ]);

        $provider = WhatsAppProviderFactory::getProviderForGym($gym->id);
        $this->assertSame('twilio', $provider);

        WhatsAppAccount::query()->where('gym_id', $gym->id)->delete();

        $cachedProvider = WhatsAppProviderFactory::getProviderForGym($gym->id);
        $this->assertSame('twilio', $cachedProvider);
    }

    public function test_invalidate_provider_cache_clears_cached_result(): void
    {
        $gym = Gym::factory()->create();

        WhatsAppAccount::query()->create([
            'gym_id' => $gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
        ]);

        WhatsAppProviderFactory::getProviderForGym($gym->id);

        WhatsAppProviderFactory::invalidateProviderCache($gym->id);

        WhatsAppAccount::query()->where('gym_id', $gym->id)->delete();

        config(['services.whatsapp.default_provider' => 'waba']);

        $provider = WhatsAppProviderFactory::getProviderForGym($gym->id);
        $this->assertSame('waba', $provider);
    }

    public function test_invalidate_all_provider_cache_clears_tagged_entries(): void
    {
        $gym1 = Gym::factory()->create();
        $gym2 = Gym::factory()->create();

        WhatsAppAccount::query()->create([
            'gym_id' => $gym1->id,
            'provider_type' => WhatsAppProviderTypeEnum::TWILIO,
            'twilio_account_sid' => 'ACtest123',
            'twilio_auth_token' => 'test_token',
        ]);

        WhatsAppAccount::query()->create([
            'gym_id' => $gym2->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
        ]);

        WhatsAppProviderFactory::getProviderForGym($gym1->id);
        WhatsAppProviderFactory::getProviderForGym($gym2->id);

        WhatsAppProviderFactory::invalidateAllProviderCache();

        WhatsAppAccount::query()->delete();

        config(['services.whatsapp.default_provider' => 'waba']);

        $this->assertSame('waba', WhatsAppProviderFactory::getProviderForGym($gym1->id));
        $this->assertSame('waba', WhatsAppProviderFactory::getProviderForGym($gym2->id));
    }

    public function test_get_supported_providers_returns_both_providers(): void
    {
        $providers = WhatsAppProviderFactory::getSupportedProviders();

        $this->assertArrayHasKey('twilio', $providers);
        $this->assertArrayHasKey('waba', $providers);
        $this->assertCount(2, $providers);
    }

    public function test_provider_constants_match_enum_values(): void
    {
        $this->assertSame(
            WhatsAppProviderTypeEnum::TWILIO->value,
            WhatsAppProviderFactory::PROVIDER_TWILIO,
        );

        $this->assertSame(
            WhatsAppProviderTypeEnum::WABA->value,
            WhatsAppProviderFactory::PROVIDER_META,
        );
    }
}
