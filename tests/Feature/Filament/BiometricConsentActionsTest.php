<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DefaultRole;
use App\Filament\Admin\Actions\RequestBiometricConsentAction;
use App\Filament\Admin\Resources\GymMemberResource\Pages\ListGymMembers;
use App\Jobs\FanOutBiometricConsentRequestsJob;
use App\Jobs\SendBiometricConsentNotificationJob;
use App\Models\BiometricConsent;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BiometricConsentActionsTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    private User $gymOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');

        /** @var User $owner */
        $owner = User::factory()->gymOwner()->create();

        $owner->gyms()->attach($this->gym->id, ['role' => DefaultRole::GYM_OWNER->value]);

        foreach (['view_any_gym_member', 'view_gym_member', 'create_gym_member', 'update_gym_member', 'delete_gym_member', 'delete_any_gym_member'] as $name) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($permission);
            $owner->givePermissionTo($permission);
        }

        $this->gymOwner = $owner;

        $this->actingAs($this->gymOwner);

        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_header_action_dispatches_jobs_for_all_unconsented_active_members(): void
    {
        Queue::fake();

        // 1. Unconsented active members (eligible)
        $member1 = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'email' => 'unconsented1@example.com',
        ]);

        $member2 = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'email' => 'unconsented2@example.com',
        ]);

        // 2. Active member with active consent (ineligible)
        $memberWithConsent = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'email' => 'consented@example.com',
        ]);
        BiometricConsent::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $memberWithConsent->id,
            'revoked_at' => null,
        ]);

        // 3. Archived member (ineligible)
        $archivedMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => true,
            'archived_at' => now(),
            'email' => 'archived@example.com',
        ]);

        // 4. Inactive/expired membership member (ineligible)
        $expiredMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subDay(),
            'is_archived' => false,
            'email' => 'expired@example.com',
        ]);

        Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->callAction('sendBiometricConsentToAll', [
                'send_email' => true,
                'send_whatsapp' => false,
            ])
            ->assertNotified('Biometric Consent Requests Queued');

        // The header path now hands the whole gym to one fan-out job (SPEC constraint
        // C4), so run it to prove the eligible set is exactly the two unconsented
        // active members and that archived, expired and consented members are out.
        (new FanOutBiometricConsentRequestsJob(
            gymId: $this->gym->id,
            actorId: $this->gymOwner->id,
            sendEmail: true,
            sendWhatsApp: false,
        ))->handle();

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, 2);

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($member1) {
            return $job->gymMember->id === $member1->id && $job->sendEmail === true && $job->sendWhatsApp === false;
        });

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($member2) {
            return $job->gymMember->id === $member2->id && $job->sendEmail === true && $job->sendWhatsApp === false;
        });

        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($memberWithConsent) {
            return $job->gymMember->id === $memberWithConsent->id;
        });

        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($archivedMember) {
            return $job->gymMember->id === $archivedMember->id;
        });

        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($expiredMember) {
            return $job->gymMember->id === $expiredMember->id;
        });
    }

    public function test_header_action_shows_warning_when_no_eligible_members(): void
    {
        // Only consented members
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
        ]);
        BiometricConsent::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'revoked_at' => null,
        ]);

        Queue::fake();

        Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->callAction('sendBiometricConsentToAll', [
                'send_email' => true,
                'send_whatsapp' => false,
            ])
            ->assertNotified('No eligible members');

        Queue::assertNothingPushed();
    }

    public function test_header_action_requires_at_least_one_channel_selected(): void
    {
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
        ]);

        Queue::fake();

        Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->callAction('sendBiometricConsentToAll', [
                'send_email' => false,
                'send_whatsapp' => false,
            ])
            ->assertNotified('No delivery channel selected');

        Queue::assertNothingPushed();
    }

    public function test_bulk_action_dispatches_jobs_for_selected_unconsented_members(): void
    {
        $memberA = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
        ]);

        $memberB = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
        ]);
        BiometricConsent::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $memberB->id,
            'revoked_at' => null,
        ]);

        Queue::fake();

        Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->callTableBulkAction('sendBiometricConsent', [$memberA, $memberB], [
                'send_email' => true,
                'send_whatsapp' => false,
            ])
            ->assertNotified('Biometric Consent Requests Queued');

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, 1);
        Queue::assertPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($memberA) {
            return $job->gymMember->id === $memberA->id;
        });
        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($memberB) {
            return $job->gymMember->id === $memberB->id;
        });
    }

    public function test_bulk_action_shows_warning_when_all_selected_members_are_already_consented(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
        ]);
        BiometricConsent::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_member_id' => $member->id,
            'revoked_at' => null,
        ]);

        Queue::fake();

        Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->callTableBulkAction('sendBiometricConsent', [$member], [
                'send_email' => true,
                'send_whatsapp' => false,
            ])
            ->assertNotified('No eligible members');

        Queue::assertNothingPushed();
    }

    public function test_bulk_action_requires_at_least_one_channel_selected(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
        ]);

        Queue::fake();

        Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->callTableBulkAction('sendBiometricConsent', [$member], [
                'send_email' => false,
                'send_whatsapp' => false,
            ])
            ->assertNotified('No delivery channel selected');

        Queue::assertNothingPushed();
    }

    public function test_bulk_action_dispatches_nothing_for_archived_and_expired_members(): void
    {
        $archivedMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => true,
            'archived_at' => now(),
            'email' => 'bulk_archived@example.com',
        ]);

        $expiredMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subDay(),
            'is_archived' => false,
            'email' => 'bulk_expired@example.com',
        ]);

        Queue::fake();

        Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->callTableBulkAction('sendBiometricConsent', [$archivedMember, $expiredMember], [
                'send_email' => true,
                'send_whatsapp' => false,
            ])
            ->assertNotified('No eligible members');

        Queue::assertNothingPushed();
    }

    public function test_bulk_action_only_dispatches_for_the_eligible_member_of_the_selection(): void
    {
        $eligibleMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'email' => 'bulk_eligible@example.com',
        ]);

        $archivedMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => true,
            'archived_at' => now(),
            'email' => 'bulk_mixed_archived@example.com',
        ]);

        $expiredMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subDay(),
            'is_archived' => false,
            'email' => 'bulk_mixed_expired@example.com',
        ]);

        Queue::fake();

        Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->callTableBulkAction('sendBiometricConsent', [$eligibleMember, $archivedMember, $expiredMember], [
                'send_email' => true,
                'send_whatsapp' => false,
            ])
            ->assertNotified('Biometric Consent Requests Queued');

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, 1);
        Queue::assertPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($eligibleMember): bool {
            return $job->gymMember->id === $eligibleMember->id;
        });
        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($archivedMember): bool {
            return $job->gymMember->id === $archivedMember->id;
        });
        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($expiredMember): bool {
            return $job->gymMember->id === $expiredMember->id;
        });
    }

    public function test_bulk_action_dispatches_nothing_for_a_member_of_another_gym(): void
    {
        $otherGym = Gym::factory()->create();

        $foreignMember = GymMember::factory()->create([
            'gym_id' => $otherGym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'email' => 'foreign_member@example.com',
        ]);

        Queue::fake();

        Livewire::actingAs($this->gymOwner)
            ->test(ListGymMembers::class)
            ->callTableBulkAction('sendBiometricConsent', [$foreignMember], [
                'send_email' => true,
                'send_whatsapp' => false,
            ]);

        Queue::assertNothingPushed();

        $report = RequestBiometricConsentAction::dispatch(
            EloquentCollection::make([$foreignMember]),
            true,
            false
        );

        $this->assertSame(0, $report->dispatched);
        $this->assertSame(1, $report->unauthorized, 'A cross-gym selection is an authorization refusal, not an eligibility skip.');
        $this->assertSame(0, $report->ineligible);
        Queue::assertNothingPushed();
    }

    public function test_bulk_action_still_dispatches_for_own_members_when_a_foreign_member_is_in_the_selection(): void
    {
        $otherGym = Gym::factory()->create();

        $ownMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'email' => 'own_member@example.com',
        ]);

        $foreignMember = GymMember::factory()->create([
            'gym_id' => $otherGym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'email' => 'foreign_member_mixed@example.com',
        ]);

        Queue::fake();

        $report = RequestBiometricConsentAction::dispatch(
            EloquentCollection::make([$ownMember, $foreignMember]),
            true,
            false
        );

        $this->assertSame(1, $report->dispatched);
        $this->assertSame(1, $report->unauthorized);

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, 1);
        Queue::assertPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($ownMember): bool {
            return $job->gymMember->id === $ownMember->id;
        });
        Queue::assertNotPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($foreignMember): bool {
            return $job->gymMember->id === $foreignMember->id;
        });
    }

    public function test_a_repeated_bulk_broadcast_is_throttled(): void
    {
        Queue::fake();

        // The ceiling lives at the action entry point, not in dispatch(), so the
        // throttle is what a repeated click runs through.
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->assertTrue(
                RequestBiometricConsentAction::throttle($this->gym->id),
                "Attempt {$attempt} should be inside the hourly ceiling."
            );
        }

        $this->assertFalse(
            RequestBiometricConsentAction::throttle($this->gym->id),
            'The broadcast throttle must refuse once the hourly ceiling is reached.'
        );
    }

    /**
     * The rate-limit body was a single-quoted string, so PHP never interpolated
     * the ceiling and the admin who tripped it saw the literal token
     * "{self::MAX_ATTEMPTS_PER_HOUR}" where the number belonged. This is the only
     * feedback the security control produces, and it was the only place the
     * feature leaked a raw template.
     */
    public function test_the_rate_limit_notification_states_the_actual_ceiling(): void
    {
        Queue::fake();

        for ($attempt = 0; $attempt <= 20; $attempt++) {
            RequestBiometricConsentAction::throttle($this->gym->id);
        }

        $notifications = session('filament.notifications');

        $this->assertIsArray($notifications);
        $this->assertNotEmpty($notifications);

        $refusal = collect($notifications)->first(
            fn (array $notification): bool => ($notification['title'] ?? null) === 'Too many consent broadcasts'
        );

        $this->assertNotNull($refusal, 'Tripping the ceiling must produce a refusal notification.');
        $this->assertStringContainsString('20 times', (string) ($refusal['body'] ?? ''));
        $this->assertStringNotContainsString('{self::', (string) ($refusal['body'] ?? ''));
    }
}
