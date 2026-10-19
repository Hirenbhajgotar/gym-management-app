<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The per-gym permission cache is keyed, so a "flush everything" call has to
 * reach both the base key and every per-gym variant. This is observable
 * behaviour with no other test reaching it.
 */
class TeamPermissionRegistrarCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_forget_all_cached_permissions_flushes_the_base_and_per_gym_keys(): void
    {
        $gym = Gym::factory()->create();

        // The registrar enumerates gym ids from roles.gym_id, so a role has to
        // exist for the per-gym key to be discoverable and reported.
        Role::query()->create([
            'name' => 'gym_owner',
            'guard_name' => 'web',
            'gym_id' => $gym->id,
        ]);

        // A team context is the case that exposes the bug: the subclass
        // reassigns $this->cacheKey to the per-gym key, so "forget the base"
        // has to be explicit.
        setPermissionsTeamId($gym->id);

        /** @var PermissionRegistrar $registrar */
        $registrar = resolve(PermissionRegistrar::class);
        $store = $registrar->getCacheStore();

        /** @var string $baseKey */
        $baseKey = config('permission.cache.key');
        $gymKey = "{$baseKey}.gym.{$gym->id}";

        $store->put($baseKey, ['seeded' => 'base'], 3600);
        $store->put($gymKey, ['seeded' => 'gym'], 3600);

        // Preconditions: both keys really are populated.
        $this->assertNotNull($store->get($baseKey));
        $this->assertNotNull($store->get($gymKey));

        $flushed = $registrar->forgetAllCachedPermissions();

        $this->assertSame(1, $flushed, 'Exactly the one seeded gym key should be reported flushed.');
        $this->assertNull(
            $store->get($baseKey),
            'The base/global key must be flushed even under a team context.'
        );
        $this->assertNull($store->get($gymKey), 'The per-gym key must be flushed.');
    }
}
