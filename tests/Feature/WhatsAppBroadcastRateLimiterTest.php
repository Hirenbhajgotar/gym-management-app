<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Enums\WhatsAppBroadcastStatusEnum;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\BroadcastRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppBroadcastRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Gym $gym;

    protected BroadcastRateLimiter $rateLimiter;

    protected WhatsAppTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
        ]);

        $this->user = User::factory()->create()->assignRole(DefaultRole::GYM_OWNER->value);

        $this->gym = Gym::factory()->create();
        $this->user->gyms()->attach($this->gym);

        $this->template = WhatsAppTemplate::factory()->create([
            'gym_id' => $this->gym->id,
            'is_active' => true,
        ]);

        $this->rateLimiter = resolve(BroadcastRateLimiter::class);
    }

    private function createBroadcast(): WhatsAppBroadcast
    {
        return WhatsAppBroadcast::create([
            'gym_id' => $this->gym->id,
            'whatsapp_template_id' => $this->template->id,
            'title' => 'Test broadcast',
            'target_count' => 1,
            'status' => WhatsAppBroadcastStatusEnum::SENDING,
        ]);
    }

    public function test_allows_send_when_within_daily_limit(): void
    {
        $this->assertTrue($this->rateLimiter->canSend($this->gym->id));
    }

    public function test_blocks_send_when_daily_limit_exceeded(): void
    {
        $this->createBroadcast();
        $this->createBroadcast();

        $this->assertFalse($this->rateLimiter->canSend($this->gym->id));
    }

    public function test_returns_quota_usage(): void
    {
        $this->createBroadcast();

        $quota = $this->rateLimiter->getQuota($this->gym->id);

        $this->assertEquals(1, $quota['used']);
        $this->assertEquals(1, $quota['remaining']);
        $this->assertEquals(2, $quota['limit']);
    }

    public function test_quota_resets_daily(): void
    {
        $this->createBroadcast();

        WhatsAppBroadcast::where('gym_id', $this->gym->id)->update([
            'created_at' => now()->subDay(),
        ]);

        $quota = $this->rateLimiter->getQuota($this->gym->id);

        $this->assertEquals(0, $quota['used']);
        $this->assertEquals(2, $quota['remaining']);
    }

    public function test_cooldown_active_after_recent_broadcast(): void
    {
        $this->createBroadcast();

        WhatsAppBroadcast::where('gym_id', $this->gym->id)->update([
            'created_at' => now()->subMinutes(10),
        ]);

        $cooldown = $this->rateLimiter->getCooldown($this->gym->id);

        $this->assertNotNull($cooldown);
        $this->assertLessThanOrEqual(50, $cooldown);
    }

    public function test_no_cooldown_when_no_previous_broadcast(): void
    {
        $this->assertNull($this->rateLimiter->getCooldown($this->gym->id));
    }

    public function test_no_cooldown_after_one_hour(): void
    {
        $this->createBroadcast();

        WhatsAppBroadcast::where('gym_id', $this->gym->id)->update([
            'created_at' => now()->subMinutes(70),
        ]);

        $this->assertNull($this->rateLimiter->getCooldown($this->gym->id));
    }

    public function test_acquires_lock(): void
    {
        $this->assertTrue($this->rateLimiter->acquireLock($this->gym->id));
    }

    public function test_acquire_lock_blocks_concurrent(): void
    {
        $this->assertTrue($this->rateLimiter->acquireLock($this->gym->id));

        $this->assertFalse($this->rateLimiter->acquireLock($this->gym->id));
    }
}
