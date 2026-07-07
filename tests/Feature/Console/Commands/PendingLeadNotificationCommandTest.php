<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Enums\DefaultRole;
use App\Enums\LeadStatus;
use App\Models\FollowUp;
use App\Models\Gym;
use App\Models\GymSetting;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\PendingLeadNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

use function assert;

class PendingLeadNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the gym_owner role (global, no gym_id) for test usage
        Role::query()->firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
        ]);
    }

    /**
     * @return array{gym: Gym, owner: User}
     */
    private function createGymWithOwner(): array
    {
        $owner = User::factory()->gymOwner()->create();

        /** @var Gym $gym */
        $gym = Gym::withoutEvents(fn () => Gym::factory()->create());
        $gym->users()->attach($owner->id, ['role' => DefaultRole::GYM_OWNER->value]);

        $this->enableLeadReminders($gym);

        return ['gym' => $gym, 'owner' => $owner];
    }

    private function createGymWithoutOwner(): Gym
    {
        /** @var Gym $gym */
        $gym = Gym::withoutEvents(fn () => Gym::factory()->create());
        $this->enableLeadReminders($gym);

        return $gym;
    }

    private function enableLeadReminders(Gym $gym): void
    {
        GymSetting::query()->create([
            'gym_id' => $gym->id,
            'key' => 'lead_reminders_notifications',
            'value' => 'true',
            'type' => 'boolean',
        ]);
    }

    #[Test]
    public function it_sends_reminders_for_new_leads_pending_2_days(): void
    {
        Notification::fake();

        ['gym' => $gym, 'owner' => $owner] = $this->createGymWithOwner();

        $lead = Lead::factory()->create([
            'gym_id' => $gym->id,
            'status' => LeadStatus::NEW,
            'created_at' => now()->subDays(3),
            'last_follow_up_date' => null,
        ]);

        $this->runCommand('leads:send-pending-reminders')
            ->expectsOutputToContain('Done. Sent: 1, Skipped: 0')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $owner,
            PendingLeadNotification::class,
            fn (PendingLeadNotification $notification) => $notification->lead->id === $lead->id,
        );

        $this->assertNotNull($lead->fresh()?->last_reminded_at);
    }

    #[Test]
    public function it_sends_reminders_for_contacted_leads(): void
    {
        Notification::fake();

        ['gym' => $gym, 'owner' => $owner] = $this->createGymWithOwner();

        $lead = Lead::factory()->create([
            'gym_id' => $gym->id,
            'status' => LeadStatus::CONTACTED,
            'created_at' => now()->subDays(3),
            'last_follow_up_date' => null,
        ]);

        $this->runCommand('leads:send-pending-reminders')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $owner,
            PendingLeadNotification::class,
            fn (PendingLeadNotification $notification) => $notification->lead->id === $lead->id,
        );
    }

    #[Test]
    public function it_skips_leads_created_recently(): void
    {
        Notification::fake();

        ['gym' => $gym] = $this->createGymWithOwner();

        Lead::factory()->create([
            'gym_id' => $gym->id,
            'status' => LeadStatus::NEW,
            'created_at' => now()->subDay(),
            'last_follow_up_date' => null,
        ]);

        $this->runCommand('leads:send-pending-reminders')
            ->expectsOutputToContain('Done. Sent: 0, Skipped: 0')
            ->assertExitCode(0);

        Notification::assertSentTimes(PendingLeadNotification::class, 0);
    }

    #[Test]
    public function it_skips_leads_with_recent_follow_ups(): void
    {
        Notification::fake();

        ['gym' => $gym] = $this->createGymWithOwner();

        $lead = Lead::factory()->create([
            'gym_id' => $gym->id,
            'status' => LeadStatus::NEW,
            'created_at' => now()->subDays(5),
            'last_follow_up_date' => now()->subDay(),
        ]);

        FollowUp::factory()->create([
            'lead_id' => $lead->id,
            'gym_id' => $gym->id,
            'status' => 'completed',
            'scheduled_date' => now()->subDay(),
        ]);

        $this->runCommand('leads:send-pending-reminders')
            ->expectsOutputToContain('Done. Sent: 0, Skipped: 0')
            ->assertExitCode(0);

        Notification::assertSentTimes(PendingLeadNotification::class, 0);
    }

    #[Test]
    public function it_skips_converted_leads(): void
    {
        Notification::fake();

        ['gym' => $gym] = $this->createGymWithOwner();

        Lead::factory()->create([
            'gym_id' => $gym->id,
            'status' => LeadStatus::CONVERTED,
            'created_at' => now()->subDays(3),
        ]);

        $this->runCommand('leads:send-pending-reminders')
            ->expectsOutputToContain('Done. Sent: 0, Skipped: 0')
            ->assertExitCode(0);

        Notification::assertSentTimes(PendingLeadNotification::class, 0);
    }

    #[Test]
    public function it_skips_lost_leads(): void
    {
        Notification::fake();

        ['gym' => $gym] = $this->createGymWithOwner();

        Lead::factory()->create([
            'gym_id' => $gym->id,
            'status' => LeadStatus::LOST,
            'created_at' => now()->subDays(3),
        ]);

        $this->runCommand('leads:send-pending-reminders')
            ->expectsOutputToContain('Done. Sent: 0, Skipped: 0')
            ->assertExitCode(0);

        Notification::assertSentTimes(PendingLeadNotification::class, 0);
    }

    #[Test]
    public function it_handles_lead_without_gym_owner(): void
    {
        Notification::fake();

        $gym = $this->createGymWithoutOwner();

        $lead = Lead::factory()->create([
            'gym_id' => $gym->id,
            'status' => LeadStatus::NEW,
            'created_at' => now()->subDays(3),
            'last_follow_up_date' => null,
        ]);

        $this->runCommand('leads:send-pending-reminders')
            ->expectsOutputToContain("No owner found for gym {$gym->name}, skipping lead {$lead->name}")
            ->assertExitCode(0);

        Notification::assertSentTimes(PendingLeadNotification::class, 0);
    }

    #[Test]
    public function it_reports_when_no_pending_leads_found(): void
    {
        $this->runCommand('leads:send-pending-reminders')
            ->expectsOutputToContain('Done. Sent: 0, Skipped: 0')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_sends_reminders_for_multiple_leads(): void
    {
        Notification::fake();

        ['gym' => $gym] = $this->createGymWithOwner();

        Lead::factory()->count(3)->create([
            'gym_id' => $gym->id,
            'status' => LeadStatus::NEW,
            'created_at' => now()->subDays(3),
            'last_follow_up_date' => null,
        ]);

        $this->runCommand('leads:send-pending-reminders')
            ->expectsOutputToContain('Done. Sent: 3, Skipped: 0')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_skips_leads_reminded_recently(): void
    {
        Notification::fake();

        ['gym' => $gym] = $this->createGymWithOwner();

        Lead::factory()->create([
            'gym_id' => $gym->id,
            'status' => LeadStatus::NEW,
            'created_at' => now()->subDays(3),
            'last_follow_up_date' => null,
            'last_reminded_at' => now()->subDay(),
        ]);

        $this->runCommand('leads:send-pending-reminders')
            ->expectsOutputToContain('Done. Sent: 0, Skipped: 0')
            ->assertExitCode(0);

        Notification::assertSentTimes(PendingLeadNotification::class, 0);
    }

    #[Test]
    public function it_sends_reminders_for_leads_reminded_more_than_2_days_ago(): void
    {
        Notification::fake();

        ['gym' => $gym, 'owner' => $owner] = $this->createGymWithOwner();

        $lead = Lead::factory()->create([
            'gym_id' => $gym->id,
            'status' => LeadStatus::NEW,
            'created_at' => now()->subDays(5),
            'last_follow_up_date' => null,
            'last_reminded_at' => now()->subDays(3),
        ]);

        $this->runCommand('leads:send-pending-reminders')
            ->expectsOutputToContain('Done. Sent: 1, Skipped: 0')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $owner,
            PendingLeadNotification::class,
            fn (PendingLeadNotification $notification) => $notification->lead->id === $lead->id,
        );

        $this->assertNotNull($lead->fresh()?->last_reminded_at);
    }

    private function runCommand(string $command): PendingCommand
    {
        $result = $this->artisan($command);
        assert($result instanceof PendingCommand);

        return $result;
    }
}
