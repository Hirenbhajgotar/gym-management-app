<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\WhatsAppProviderTypeEnum;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use App\Services\Meta\MetaApiClient;
use App\Services\Meta\MetaContext;
use App\Services\Meta\MetaTemplateService;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Expectation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetaTemplateServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    #[Test]
    public function invalidate_cache_clears_the_gym_waba_template_key(): void
    {
        $gymId = 42;
        $wabaId = 'WABA_XYZ';
        $key = MetaTemplateService::cacheKey($gymId, $wabaId);

        Cache::put($key, ['stale' => true], 300);
        $this->assertTrue(Cache::has($key));

        MetaTemplateService::invalidateCache($gymId, $wabaId);

        $this->assertFalse(Cache::has($key));
    }

    #[Test]
    public function invalidate_cache_is_a_noop_when_waba_id_is_null(): void
    {
        $gymId = 42;
        $key = MetaTemplateService::cacheKey($gymId, null);

        Cache::put($key, ['stale' => true], 300);
        $this->assertTrue(Cache::has($key));

        MetaTemplateService::invalidateCache($gymId, null);

        $this->assertTrue(Cache::has($key));
    }

    #[Test]
    public function get_templates_repopulates_cache_after_invalidation(): void
    {
        $gymId = 42;
        $wabaId = 'WABA_XYZ';

        Gym::factory()->create(['id' => $gymId]);
        WhatsAppAccount::query()->create([
            'gym_id' => $gymId,
            'waba_id' => $wabaId,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
        ]);

        $context = new MetaContext($gymId);
        /** @var MetaApiClient&Mockery\MockInterface $apiMock */
        $apiMock = Mockery::mock(MetaApiClient::class);
        /** @var Expectation $expectation */
        $expectation = $apiMock->shouldReceive('get');
        $expectation
            ->with("/{$wabaId}/message_templates")
            ->andReturn(['success' => true, 'data' => ['data' => [['id' => 't1', 'name' => 'N']]]]);

        $service = new MetaTemplateService($context, $apiMock);

        $first = $service->getTemplates($gymId);
        $this->assertTrue($first->success);
        $key = MetaTemplateService::cacheKey($gymId, $wabaId);
        $this->assertTrue(Cache::has($key));

        MetaTemplateService::invalidateCache($gymId, $wabaId);
        $this->assertFalse(Cache::has($key));

        $second = $service->getTemplates($gymId);
        $this->assertTrue($second->success);
        $this->assertTrue(Cache::has($key));
    }

    #[Test]
    public function resolve_language_preserves_two_letter_and_full_locale_codes(): void
    {
        $tplEn = new WhatsAppTemplate(['language' => 'en']);
        $this->assertSame('en', MetaTemplateService::resolveLanguage($tplEn));

        $tplEnUs = new WhatsAppTemplate(['language' => 'en_US']);
        $this->assertSame('en_US', MetaTemplateService::resolveLanguage($tplEnUs));

        $tplHi = new WhatsAppTemplate(['language' => 'hi']);
        $this->assertSame('hi', MetaTemplateService::resolveLanguage($tplHi));

        $tplNull = new WhatsAppTemplate(['language' => null]);
        $this->assertSame('en_US', MetaTemplateService::resolveLanguage($tplNull));
    }
}
