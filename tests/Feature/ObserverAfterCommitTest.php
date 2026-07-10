<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Gym;
use App\Models\ReportGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ObserverAfterCommitTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_goal_cache_is_cleared_only_after_commit(): void
    {
        $gym = Gym::factory()->create();
        $key = ReportGoal::CACHE_KEY_PREFIX . $gym->id;

        Cache::forever($key, 'stale');

        DB::transaction(function () use ($gym): void {
            ReportGoal::query()->create([
                'gym_id' => $gym->id,
                'metric_type' => 'revenue',
                'period' => 'monthly',
                'target_value' => 1000,
                'period_start' => now()->startOfMonth(),
                'period_end' => now()->endOfMonth(),
            ]);
        });

        $this->assertNull(Cache::get($key), 'Observer should forget cache after commit');
    }

    public function test_report_goal_cache_is_preserved_on_rollback(): void
    {
        $gym = Gym::factory()->create();
        $key = ReportGoal::CACHE_KEY_PREFIX . $gym->id;

        Cache::forever($key, 'stale');

        try {
            DB::transaction(function () use ($gym): void {
                ReportGoal::query()->create([
                    'gym_id' => $gym->id,
                    'metric_type' => 'revenue',
                    'period' => 'monthly',
                    'target_value' => 1000,
                    'period_start' => now()->startOfMonth(),
                    'period_end' => now()->endOfMonth(),
                ]);
                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertEquals('stale', Cache::get($key), 'Observer should NOT run on rollback');
    }

    public function test_user_is_active_is_persisted_from_status_via_mutator(): void
    {
        $user = new User();
        $user->status = UserStatus::ACTIVE;
        $this->assertTrue($user->getAttributes()['is_active']);

        $user->status = UserStatus::INACTIVE;
        $this->assertFalse($user->getAttributes()['is_active']);
    }
}
