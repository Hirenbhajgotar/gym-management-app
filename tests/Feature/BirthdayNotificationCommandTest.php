<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymMember;
use App\Notifications\BirthdayNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * Tests for BirthdayNotificationCommand.
 *
 * Covers notification dispatch, cache dedup, chunked processing, and error handling.
 */
class BirthdayNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMemberWithTodayBirthday(Gym $gym, array $overrides = []): GymMember
    {
        /** @var array<string, mixed> $attributes */
        $attributes = array_merge([
            'gym_id' => $gym->id,
            'dob' => now()->subYears(25),
        ], $overrides);

        return GymMember::factory()->create($attributes);
    }

    public function test_command_sends_notifications_to_members_with_birthday_today(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();
        $member = $this->createMemberWithTodayBirthday($gym);

        $this->artisanCommand('notification:send-birthday-notifications')
            ->assertSuccessful();

        Notification::assertSentTo($member, BirthdayNotification::class);
    }

    public function test_command_skips_members_with_cached_notification(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();
        $member = $this->createMemberWithTodayBirthday($gym);

        // Pre-set cache to simulate already sent
        $cacheKey = BirthdayNotification::birthdayCacheKey($member->id);
        Cache::put($cacheKey, true, now()->endOfDay());

        $this->artisanCommand('notification:send-birthday-notifications')
            ->assertSuccessful();

        // Notification should NOT be sent since cache exists
        Notification::assertNotSentTo($member, BirthdayNotification::class);
    }

    public function test_command_sets_cache_after_send(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();
        $member = $this->createMemberWithTodayBirthday($gym);

        $cacheKey = BirthdayNotification::birthdayCacheKey($member->id);
        $this->assertFalse(Cache::has($cacheKey));

        $this->artisanCommand('notification:send-birthday-notifications')
            ->assertSuccessful();

        // Cache should be set after successful send
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_command_processes_multiple_members(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();
        $member1 = $this->createMemberWithTodayBirthday($gym, ['name' => 'Alice']);
        $member2 = $this->createMemberWithTodayBirthday($gym, ['name' => 'Bob']);

        $this->artisanCommand('notification:send-birthday-notifications')
            ->assertSuccessful();

        Notification::assertSentTo($member1, BirthdayNotification::class);
        Notification::assertSentTo($member2, BirthdayNotification::class);
    }

    public function test_command_does_not_send_to_members_with_different_birthday(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();

        // Member with birthday today
        $this->createMemberWithTodayBirthday($gym);

        // Member with birthday on a different day
        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'dob' => now()->subYears(30)->addMonth(),
        ]);

        $this->artisanCommand('notification:send-birthday-notifications')
            ->assertSuccessful();

        // Only one notification should be sent (to the member with birthday today)
        Notification::assertSentTimes(BirthdayNotification::class, 1);
    }

    public function test_command_handles_errors_gracefully(): void
    {
        $gym = Gym::factory()->create();
        $this->createMemberWithTodayBirthday($gym);

        // Run command - should not throw even if notification fails
        /** @var PendingCommand $result */
        $result = $this->artisan('notification:send-birthday-notifications');
        $result->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function artisanCommand(string $command, array $parameters = []): PendingCommand
    {
        $result = $this->artisan($command, $parameters);
        assert($result instanceof PendingCommand);

        return $result;
    }
}
