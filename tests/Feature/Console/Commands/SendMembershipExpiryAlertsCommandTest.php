<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\SendMembershipExpiryAlerts;
use App\Models\Gym;
use App\Models\GymMember;
use App\Notifications\MembershipExpiryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\TestCase;

use function assert;

#[CoversClass(SendMembershipExpiryAlerts::class)]
#[UsesClass(Gym::class)]
#[UsesClass(GymMember::class)]
#[UsesClass(MembershipExpiryNotification::class)]
final class SendMembershipExpiryAlertsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The GymMemberObserver generates a profile avatar via ui-avatars.com
        // on every member creation. Fake it so tests stay fast and offline.
        Http::fake();
    }

    #[Test]
    public function it_notifies_members_with_membership_expiring_in_7_days(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => today()->addDays(7),
            'is_frozen' => false,
        ]);

        $this->artisanCommand('notification:send-membership-expiry-alerts')
            ->assertExitCode(0);

        Notification::assertSentTo($member, MembershipExpiryNotification::class, function (MembershipExpiryNotification $notification) {
            return $notification->daysLeft === 7;
        });
    }

    #[Test]
    public function it_notifies_members_with_membership_expiring_today(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => today(),
            'is_frozen' => false,
        ]);

        $this->artisanCommand('notification:send-membership-expiry-alerts')
            ->assertExitCode(0);

        Notification::assertSentTo($member, MembershipExpiryNotification::class, function (MembershipExpiryNotification $notification) {
            return $notification->daysLeft === 0;
        });
    }

    #[Test]
    public function it_notifies_members_with_membership_expired_3_days_ago(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => today()->subDays(3),
            'is_frozen' => false,
        ]);

        $this->artisanCommand('notification:send-membership-expiry-alerts')
            ->assertExitCode(0);

        Notification::assertSentTo($member, MembershipExpiryNotification::class, function (MembershipExpiryNotification $notification) {
            return $notification->daysLeft === -3;
        });
    }

    #[Test]
    public function it_does_not_notify_members_with_non_matching_dates(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();
        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => today()->addDays(5),
            'is_frozen' => false,
        ]);

        $this->artisanCommand('notification:send-membership-expiry-alerts')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_does_not_notify_frozen_members(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();
        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => today()->addDays(7),
            'is_frozen' => true,
        ]);

        $this->artisanCommand('notification:send-membership-expiry-alerts')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_outputs_success_message_and_returns_success_exit_code(): void
    {
        $this->artisanCommand('notification:send-membership-expiry-alerts')
            ->expectsOutputToContain('Expiry alerts queued')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_is_idempotent_on_second_run(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => today()->addDays(7),
            'is_frozen' => false,
        ]);

        // First run — should notify
        $this->artisanCommand('notification:send-membership-expiry-alerts')
            ->assertExitCode(0);

        Notification::assertSentToTimes($member, MembershipExpiryNotification::class, 1);

        // Second run — should NOT notify again (cache dedup)
        $this->artisanCommand('notification:send-membership-expiry-alerts')
            ->assertExitCode(0);

        // Still only 1 notification sent (dedup prevented duplicate)
        Notification::assertSentToTimes($member, MembershipExpiryNotification::class, 1);
    }

    #[Test]
    public function it_outputs_per_offset_counts(): void
    {
        Notification::fake();

        $gym = Gym::factory()->create();

        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => today()->addDays(7),
            'is_frozen' => false,
        ]);

        $this->artisanCommand('notification:send-membership-expiry-alerts')
            ->expectsOutputToContain('7 days')
            ->expectsOutputToContain('Total:')
            ->assertExitCode(0);
    }

    /**
     * Run artisan command and narrow PendingCommand type for PHPStan.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function artisanCommand(string $command, array $parameters = []): PendingCommand
    {
        $result = $this->artisan($command, $parameters);
        assert($result instanceof PendingCommand);

        return $result;
    }

    #[Test]
    public function it_returns_failure_when_exception_occurs(): void
    {
        $gym = Gym::factory()->create();
        GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => today()->addDays(7),
            'is_frozen' => false,
        ]);

        // The command reaches Cache::has() once a matching member is found,
        // so forcing it to throw exercises the command's failure path.
        Cache::shouldReceive('has')
            ->once()
            ->andThrow(new RuntimeException('DB lost'));

        $this->artisanCommand('notification:send-membership-expiry-alerts')
            ->assertExitCode(1);  // Command::FAILURE
    }

    #[Test]
    public function it_notifies_members_with_expired_freeze(): void
    {
        Notification::fake();
        $gym = Gym::factory()->create();
        $member = GymMember::factory()->create([
            'gym_id' => $gym->id,
            'membership_end' => today()->addDays(7),
            'is_frozen' => true,
            'frozen_until' => now()->subDay(),
        ]);
        $this->artisanCommand('notification:send-membership-expiry-alerts')->assertExitCode(0);
        Notification::assertSentTo($member, MembershipExpiryNotification::class);
    }
}
