<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The punch-attribution lookup is the hot path: `AdmsEventService` compares against an
 * encrypted column it cannot match, so every ATTLOG record resolves its template here.
 *
 * A cache key that ignored the terminal scope made two terminals sharing a PIN evict each
 * other on every alternating punch, turning the lookup back into a full active-template scan
 * with in-memory decryption. The key now carries the scope and a per-gym generation.
 */
class BiometricTemplateRefCacheScopeTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected GymMember $member;

    protected BiometricDevice $deviceA;

    protected BiometricDevice $deviceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->member = GymMember::factory()->create(['gym_id' => $this->gym->id]);

        $this->deviceA = BiometricDevice::factory()->create(['gym_id' => $this->gym->id]);
        $this->deviceB = BiometricDevice::factory()->create(['gym_id' => $this->gym->id]);
    }

    #[Test]
    public function alternating_terminals_keep_their_own_cached_resolution(): void
    {
        $templateA = $this->templateOn($this->deviceA);
        $templateB = $this->templateOn($this->deviceB);

        $this->assertSame($templateA->id, BiometricTemplate::findByTemplateRef($this->gym->id, 'PIN-1', $this->deviceA->id)?->id);
        $this->assertSame($templateB->id, BiometricTemplate::findByTemplateRef($this->gym->id, 'PIN-1', $this->deviceB->id)?->id);

        // Both terminals must still be cached: the second lookup must not have destroyed the
        // first one's entry.
        $this->assertSame(
            $templateA->id,
            Cache::get(BiometricTemplate::templateRefCacheKey($this->gym->id, 'PIN-1', $this->deviceA->id))
        );
        $this->assertSame(
            $templateB->id,
            Cache::get(BiometricTemplate::templateRefCacheKey($this->gym->id, 'PIN-1', $this->deviceB->id))
        );

        // And alternating punches keep resolving through the cache rather than thrashing.
        $this->assertSame($templateA->id, BiometricTemplate::findByTemplateRef($this->gym->id, 'PIN-1', $this->deviceA->id)?->id);
        $this->assertSame($templateB->id, BiometricTemplate::findByTemplateRef($this->gym->id, 'PIN-1', $this->deviceB->id)?->id);
    }

    #[Test]
    public function the_scope_is_part_of_the_key(): void
    {
        $this->assertNotSame(
            BiometricTemplate::templateRefCacheKey($this->gym->id, 'PIN-1', $this->deviceA->id),
            BiometricTemplate::templateRefCacheKey($this->gym->id, 'PIN-1', $this->deviceB->id)
        );

        $this->assertNotSame(
            BiometricTemplate::templateRefCacheKey($this->gym->id, 'PIN-1', null),
            BiometricTemplate::templateRefCacheKey($this->gym->id, 'PIN-1', $this->deviceA->id)
        );
    }

    #[Test]
    public function saving_a_template_invalidates_every_scope_with_one_generation_bump(): void
    {
        $template = $this->templateOn($this->deviceA);

        $before = BiometricTemplate::templateRefCacheKey($this->gym->id, 'PIN-1', $this->deviceA->id);

        $template->update(['quality_score' => 0.91]);

        $after = BiometricTemplate::templateRefCacheKey($this->gym->id, 'PIN-1', $this->deviceA->id);

        $this->assertNotSame($before, $after, 'A save must make every previous key unreachable in O(1).');
        $this->assertSame(
            $template->id,
            BiometricTemplate::findByTemplateRef($this->gym->id, 'PIN-1', $this->deviceA->id)?->id,
            'The invalidated lookup must re-query and rebuild its own entry.'
        );
    }

    #[Test]
    public function a_scoped_miss_is_not_cached(): void
    {
        // A negative result from one terminal must not poison another terminal's lookup.
        $this->assertNull(BiometricTemplate::findByTemplateRef($this->gym->id, 'PIN-ABSENT', $this->deviceA->id));

        $this->templateOn($this->deviceB, 'PIN-ABSENT');

        $this->assertNotNull(
            BiometricTemplate::findByTemplateRef($this->gym->id, 'PIN-ABSENT', $this->deviceB->id),
            'A scoped negative must never be cached.'
        );
    }

    private function templateOn(BiometricDevice $device, string $ref = 'PIN-1'): BiometricTemplate
    {
        return BiometricTemplate::factory()->deviceNative()->synced()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $this->member->id,
            'enrolled_by_device_id' => $device->id,
            'template_ref' => $ref,
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
        ]);
    }
}
