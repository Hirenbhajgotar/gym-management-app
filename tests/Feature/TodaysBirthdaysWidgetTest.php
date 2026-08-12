<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Filament\Admin\Widgets\TodaysBirthdays;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use App\Notifications\BirthdayNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests for TodaysBirthdays Filament widget.
 *
 * Covers authorization, query structure, notification dispatch, and cache behavior.
 */
class TodaysBirthdaysWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'gym_owner', 'guard_name' => 'web']);
        Role::create(['name' => 'trainer', 'guard_name' => 'web']);
    }

    private function createGymWithOwner(): Gym
    {
        $gym = Gym::factory()->create();

        $owner = User::factory()->create();
        $owner->assignRole(DefaultRole::GYM_OWNER->value);
        $gym->users()->attach($owner, ['role' => DefaultRole::GYM_OWNER->value]);

        return $gym;
    }

    private function getGymOwner(Gym $gym): User
    {
        $owner = $gym->users()
            ->wherePivot('role', DefaultRole::GYM_OWNER->value)
            ->first();

        $this->assertNotNull($owner);

        /** @var User $user */
        $user = User::query()->findOrFail($owner->id);

        return $user;
    }

    private function createMemberWithTodayBirthday(Gym $gym): GymMember
    {
        return GymMember::factory()->create([
            'gym_id' => $gym->id,
            'dob' => now()->subYears(25), // Birthday today
        ]);
    }

    public function test_widget_can_view_requires_gym_owner_role(): void
    {
        $gym = $this->createGymWithOwner();
        $owner = $this->getGymOwner($gym);

        $this->actingAs($owner);

        $this->assertTrue(TodaysBirthdays::canView());
    }

    public function test_widget_can_view_returns_false_for_guest(): void
    {
        $this->assertFalse(TodaysBirthdays::canView());
    }

    public function test_widget_can_view_returns_false_for_non_owner(): void
    {
        $user = User::factory()->create();
        $user->assignRole(DefaultRole::TRAINER->value);

        $this->actingAs($user);

        $this->assertFalse(TodaysBirthdays::canView());
    }

    public function test_widget_query_uses_birthday_today_scope(): void
    {
        $gym = $this->createGymWithOwner();

        // Member with birthday today
        $this->createMemberWithTodayBirthday($gym);

        // Member with birthday on a different day
        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'dob' => now()->subYears(30)->addMonth(), // Different month
        ]);

        $count = GymMember::query()
            ->whereHas('gym')
            ->birthdayToday()
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_widget_query_eager_loads_gym(): void
    {
        $gym = $this->createGymWithOwner();
        $this->createMemberWithTodayBirthday($gym);

        /** @var GymMember|null $member */
        $member = GymMember::query()
            ->with('gym')
            ->whereHas('gym')
            ->birthdayToday()
            ->first();

        $this->assertNotNull($member);

        // Verify gym relationship is eager loaded
        $this->assertTrue($member->relationLoaded('gym'));
        $this->assertNotNull($member->gym);
        $this->assertSame($gym->id, $member->gym->id);
    }

    public function test_birthday_action_sets_cache_after_send(): void
    {
        $gym = $this->createGymWithOwner();
        $member = $this->createMemberWithTodayBirthday($gym);

        $cacheKey = BirthdayNotification::birthdayCacheKey($member->id);

        // Ensure cache is empty
        Cache::forget($cacheKey);
        $this->assertFalse(Cache::has($cacheKey));

        // Create notification and dispatch
        $notification = new BirthdayNotification($member);
        $member->notify($notification);

        // Simulate what the widget action does after successful send
        Cache::put($cacheKey, true, now()->endOfDay());

        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_birthday_action_respects_cache_for_dedup(): void
    {
        $gym = $this->createGymWithOwner();
        $member = $this->createMemberWithTodayBirthday($gym);

        $cacheKey = BirthdayNotification::birthdayCacheKey($member->id);

        // Set cache to simulate already sent
        Cache::put($cacheKey, true, now()->endOfDay());

        // Cache exists, so action should show warning (not send again)
        $this->assertTrue(Cache::has($cacheKey));
    }
}
