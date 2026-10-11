<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\DefaultRole;
use App\Enums\UserStatus;
use App\Filament\Admin\Actions\RequestBiometricConsentAction;
use App\Filament\Admin\Resources\GymMemberResource\Pages\ListGymMembers;
use App\Jobs\FanOutBiometricConsentRequestsJob;
use App\Jobs\SendBiometricConsentNotificationJob;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\Permission;
use App\Models\User;
use App\Services\Biometric\BiometricConsentEligibility;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BiometricConsentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const MEMBER_PERMISSIONS = [
        'view_any_gym_member',
        'view_gym_member',
        'create_gym_member',
        'update_gym_member',
        'delete_gym_member',
        'delete_any_gym_member',
    ];

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    #[Test]
    public function a_trainer_is_denied_the_header_consent_action_and_nothing_is_dispatched(): void
    {
        $trainer = $this->actingAsRole(DefaultRole::TRAINER);

        Queue::fake();

        Livewire::actingAs($trainer)
            ->test(ListGymMembers::class)
            ->assertActionHidden(RequestBiometricConsentAction::HEADER_ACTION);

        Livewire::actingAs($trainer)
            ->test(ListGymMembers::class)
            ->mountAction(RequestBiometricConsentAction::HEADER_ACTION)
            ->setActionData(['send_email' => true, 'send_whatsapp' => false])
            ->callMountedAction();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_trainer_is_denied_the_bulk_consent_action_and_nothing_is_dispatched(): void
    {
        $trainer = $this->actingAsRole(DefaultRole::TRAINER);
        $member = $this->makeEligibleMember();

        Queue::fake();

        Livewire::actingAs($trainer)
            ->test(ListGymMembers::class)
            ->assertTableBulkActionHidden(RequestBiometricConsentAction::BULK_ACTION);

        $report = RequestBiometricConsentAction::dispatch(EloquentCollection::make([$member]), true, false);

        $this->assertSame(0, $report->dispatched);
        Queue::assertNothingPushed();
    }

    #[Test]
    #[DataProvider('permittedRoles')]
    public function a_permitted_role_may_run_the_header_consent_action(string $role): void
    {
        $user = $this->actingAsRole(DefaultRole::from($role));
        $this->makeEligibleMember();

        Queue::fake();

        Livewire::actingAs($user)
            ->test(ListGymMembers::class)
            ->assertActionVisible(RequestBiometricConsentAction::HEADER_ACTION)
            ->callAction(RequestBiometricConsentAction::HEADER_ACTION, [
                'send_email' => true,
                'send_whatsapp' => false,
            ])
            ->assertNotified('Biometric Consent Requests Queued');

        // One queue push for the whole gym, not one per member (SPEC constraint C4).
        Queue::assertPushed(FanOutBiometricConsentRequestsJob::class, 1);
        Queue::assertPushed(
            FanOutBiometricConsentRequestsJob::class,
            fn (FanOutBiometricConsentRequestsJob $job): bool => $job->gymId === $this->gym->id
                && $job->actorId === $user->id
                && $job->sendEmail === true
                && $job->sendWhatsApp === false
        );

        $this->assertSame(
            1,
            BiometricConsentEligibility::constrain(GymMember::query(), $this->gym->id)->count(),
            'The header action should fan out over every eligible member.'
        );
    }

    #[Test]
    #[DataProvider('permittedRoles')]
    public function a_permitted_role_may_run_the_bulk_consent_action(string $role): void
    {
        $user = $this->actingAsRole(DefaultRole::from($role));
        $member = $this->makeEligibleMember();

        Queue::fake();

        Livewire::actingAs($user)
            ->test(ListGymMembers::class)
            ->assertTableBulkActionVisible(RequestBiometricConsentAction::BULK_ACTION)
            ->callTableBulkAction(RequestBiometricConsentAction::BULK_ACTION, [$member], [
                'send_email' => true,
                'send_whatsapp' => false,
            ])
            ->assertNotified('Biometric Consent Requests Queued');

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, 1);
        Queue::assertPushed(SendBiometricConsentNotificationJob::class, function (SendBiometricConsentNotificationJob $job) use ($member): bool {
            return $job->gymMember->id === $member->id && $job->sendEmail === true;
        });
    }

    /**
     * ->authorize() hides the action, and that is enough to stop a trainer
     * through the panel: CanBeDisabled::isDisabled() returns
     * `evaluate($isDisabled) || $this->isHidden()`, and CanBeHidden::isHiddenInGroup()
     * ends in `return ! $this->isAuthorized()`. Both mountTableAction() and
     * callMountedTableAction() bail out on a disabled action, so a trainer can
     * neither mount nor call it.
     *
     * The in-handler throw_unless() is therefore not reachable through the panel.
     * It is kept as defence in depth for a caller that reaches the handler
     * without going through Filament — a console command, a future API route, or
     * a direct dispatch — and the tests below pin the predicate those guards use
     * rather than pretending the mount reached them.
     */
    #[Test]
    public function a_trainer_cannot_dispatch_a_consent_email_through_the_row_action(): void
    {
        $trainer = $this->actingAsRole(DefaultRole::TRAINER);
        $member = $this->makeEligibleMember();

        Queue::fake();

        Livewire::actingAs($trainer)->test(ListGymMembers::class)
            ->assertTableActionHidden('resend_consent_email', $member);

        // The panel refuses the mount, so reaching the handler requires the
        // handler's own guard. Assert the predicate it guards on.
        $this->assertFalse(
            RequestBiometricConsentAction::canAuthorize($member),
            'The guard in resend_consent_email must refuse a trainer for this member.'
        );

        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_trainer_cannot_copy_a_consent_link_through_the_row_action(): void
    {
        $trainer = $this->actingAsRole(DefaultRole::TRAINER);
        $member = $this->makeEligibleMember();

        Queue::fake();

        Livewire::actingAs($trainer)->test(ListGymMembers::class)
            ->assertTableActionHidden('copy_consent_link', $member);

        $this->assertFalse(
            RequestBiometricConsentAction::canAuthorize($member),
            'The guard in copy_consent_link must refuse a trainer for this member.'
        );

        Queue::assertNothingPushed();
    }

    /**
     * The other half of the guard: if canAuthorize() ever stopped distinguishing
     * roles, the assertion above would pass for the wrong reason.
     */
    #[Test]
    public function the_same_row_action_guard_permits_a_gym_owner(): void
    {
        $this->actingAsRole(DefaultRole::GYM_OWNER);

        $member = $this->makeEligibleMember();

        $this->assertTrue(
            RequestBiometricConsentAction::canAuthorize($member),
            'A gym owner must still pass the guard, otherwise the row actions are dead for everyone.'
        );
    }

    #[Test]
    public function a_permitted_role_can_still_resend_a_consent_email(): void
    {
        $user = $this->actingAsRole(DefaultRole::GYM_OWNER);
        $member = $this->makeEligibleMember();

        Queue::fake();

        Livewire::actingAs($user)
            ->test(ListGymMembers::class)
            ->assertTableActionVisible('resend_consent_email', $member)
            ->mountTableAction('resend_consent_email', $member)
            ->callMountedTableAction();

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, 1);
    }

    #[Test]
    public function the_fan_out_job_only_dispatches_to_members_the_actor_may_enroll(): void
    {
        $trainer = $this->actingAsRole(DefaultRole::TRAINER);
        $this->makeEligibleMember();

        Queue::fake();

        (new FanOutBiometricConsentRequestsJob(
            gymId: $this->gym->id,
            actorId: $trainer->id,
            sendEmail: true,
            sendWhatsApp: false,
        ))->handle();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_fan_out_job_dispatches_for_an_authorized_actor(): void
    {
        $owner = $this->actingAsRole(DefaultRole::GYM_OWNER);
        $member = $this->makeEligibleMember();

        Queue::fake();

        (new FanOutBiometricConsentRequestsJob(
            gymId: $this->gym->id,
            actorId: $owner->id,
            sendEmail: true,
            sendWhatsApp: false,
        ))->handle();

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, 1);
        Queue::assertPushed(
            SendBiometricConsentNotificationJob::class,
            fn (SendBiometricConsentNotificationJob $job): bool => $job->gymMember->id === $member->id
        );
    }

    /**
     * The round-3 blocker. A queue worker never runs TeamsPermission, which is
     * the only caller of setPermissionsTeamId(), so the per-record policy check
     * resolved against gym_id IS NULL roles and denied every record — the job
     * logged dispatched => 0 and exited successfully while the admin had already
     * been told the requests were queued.
     *
     * setUp() sets a team id because an HTTP test always has one, so the job must
     * clear it here to reproduce the worker's starting state. Without this the
     * suite cannot fail at the exact point the production deployment does.
     */
    #[Test]
    public function the_fan_out_job_dispatches_with_no_spatie_team_context(): void
    {
        $owner = $this->actingAsRole(DefaultRole::GYM_OWNER);
        $member = $this->makeEligibleMember();

        Queue::fake();
        setPermissionsTeamId(null);

        (new FanOutBiometricConsentRequestsJob(
            gymId: $this->gym->id,
            actorId: $owner->id,
            sendEmail: true,
            sendWhatsApp: false,
        ))->handle();

        Queue::assertPushed(SendBiometricConsentNotificationJob::class, 1);
        Queue::assertPushed(
            SendBiometricConsentNotificationJob::class,
            fn (SendBiometricConsentNotificationJob $job): bool => $job->gymMember->id === $member->id
        );

        $this->assertNull(
            getPermissionsTeamId(),
            'The job must restore the previous team context so the next job in the worker is unaffected.'
        );
    }

    /**
     * An operator who passes the panel gate can be deactivated in the window
     * between the request and the worker running, and this is the only place
     * authority is re-evaluated outside a request.
     */
    #[Test]
    public function the_fan_out_job_dispatches_nothing_for_an_operator_who_is_no_longer_active(): void
    {
        $owner = $this->actingAsRole(DefaultRole::GYM_OWNER);
        $this->makeEligibleMember();

        $owner->forceFill(['status' => UserStatus::INACTIVE])->save();

        Queue::fake();
        setPermissionsTeamId(null);

        (new FanOutBiometricConsentRequestsJob(
            gymId: $this->gym->id,
            actorId: $owner->id,
            sendEmail: true,
            sendWhatsApp: false,
        ))->handle();

        Queue::assertNothingPushed();
    }

    /**
     * Zero dispatched against a non-empty eligible set means the per-record check
     * refused everything, which used to be reported as a routine success.
     */
    #[Test]
    public function the_fan_out_job_warns_when_every_member_was_refused(): void
    {
        $trainer = $this->actingAsRole(DefaultRole::TRAINER);
        $this->makeEligibleMember();

        Queue::fake();
        Log::spy();
        setPermissionsTeamId(null);

        (new FanOutBiometricConsentRequestsJob(
            gymId: $this->gym->id,
            actorId: $trainer->id,
            sendEmail: true,
            sendWhatsApp: false,
        ))->handle();

        Queue::assertNothingPushed();
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Biometric consent fan-out completed'
                && ($context['dispatched'] ?? null) === 0
                && ($context['skipped'] ?? null) === 1);
    }

    /**
     * The header action is one click, and the throttle still permits twenty an
     * hour. Without this guard an operator who fat-fingers the button queues
     * twenty broadcasts for the same gym inside a minute.
     */
    #[Test]
    public function it_declares_a_fan_out_unique_key_per_gym_actor_and_day(): void
    {
        $owner = $this->actingAsRole(DefaultRole::GYM_OWNER);
        $otherGym = Gym::factory()->create();

        $fanOut = fn (int $gymId, ?int $actorId): FanOutBiometricConsentRequestsJob => new FanOutBiometricConsentRequestsJob(
            gymId: $gymId,
            actorId: $actorId,
        );

        $this->assertInstanceOf(ShouldBeUnique::class, $fanOut($this->gym->id, $owner->id));

        $this->assertSame(
            $fanOut($this->gym->id, $owner->id)->uniqueId(),
            $fanOut($this->gym->id, $owner->id)->uniqueId(),
            'Re-clicking for the same gym and operator must collapse onto one job.'
        );

        $this->assertNotSame(
            $fanOut($this->gym->id, $owner->id)->uniqueId(),
            $fanOut($otherGym->id, $owner->id)->uniqueId(),
            'A different gym must not be blocked by another gym\'s fan-out.'
        );

        $this->assertNotSame(
            $fanOut($this->gym->id, $owner->id)->uniqueId(),
            $fanOut($this->gym->id, null)->uniqueId(),
            'A fan-out with no resolvable actor must not collide with one that has an actor.'
        );
    }

    #[Test]
    public function it_holds_the_fan_out_unique_lock_for_five_minutes(): void
    {
        $job = new FanOutBiometricConsentRequestsJob(gymId: $this->gym->id);

        $this->assertSame(
            300,
            $job->uniqueFor(),
            'Far shorter than the hourly throttle: this collapses the queue amplification of an immediate re-click, not a deliberate second broadcast.'
        );
    }

    #[Test]
    public function it_rotates_the_fan_out_unique_key_by_day(): void
    {
        $job = new FanOutBiometricConsentRequestsJob(gymId: $this->gym->id);

        $today = $job->uniqueId();

        Date::setTestNow(now()->addDay());

        $this->assertNotSame(
            $today,
            $job->uniqueId(),
            'Yesterday\'s broadcast must not block today\'s.'
        );

        Date::setTestNow();
    }

    /**
     * The behaviour the shape tests above only describe. uniqueId() being stable
     * and uniqueFor() being 300 mean nothing on their own — every one of them
     * would still pass against a class that implemented ShouldBeUnique and was
     * dispatched unconditionally, which is what an operator who fat-fingers the
     * button actually experiences: twenty broadcasts inside a minute.
     *
     * This goes through the real path. PendingDispatch acquires the unique lock
     * before the job reaches the queue, so a second identical dispatch inside the
     * lock's window is dropped there rather than pushed.
     */
    #[Test]
    public function a_second_identical_fan_out_inside_the_lock_window_is_never_queued(): void
    {
        $owner = $this->actingAsRole(DefaultRole::GYM_OWNER);

        Queue::fake();

        $fanOut = fn (): FanOutBiometricConsentRequestsJob => new FanOutBiometricConsentRequestsJob(
            gymId: $this->gym->id,
            actorId: $owner->id,
        );

        dispatch($fanOut());
        dispatch($fanOut());

        Queue::assertPushed(
            FanOutBiometricConsentRequestsJob::class,
            1,
            'A re-click inside the five-minute lock window must collapse onto the queued broadcast.'
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function permittedRoles(): array
    {
        return [
            'receptionist' => [DefaultRole::RECEPTIONIST->value],
            'manager' => [DefaultRole::MANAGER->value],
            'gym owner' => [DefaultRole::GYM_OWNER->value],
        ];
    }

    private function actingAsRole(DefaultRole $role): User
    {
        $roleModel = Role::findOrCreate($role->value, 'web');

        /** @var User $user */
        $user = User::factory()->create();

        $user->gyms()->attach($this->gym->id, ['role' => $role->value]);
        $user->assignRole($roleModel);

        foreach (self::MEMBER_PERMISSIONS as $name) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
            $roleModel->givePermissionTo($permission);
            $user->givePermissionTo($permission);
        }

        $this->actingAs($user);
        Filament::setTenant($this->gym);

        return $user;
    }

    private function makeEligibleMember(): GymMember
    {
        return GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'email' => 'consent_candidate@example.com',
        ]);
    }
}
