<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Biometrics\BiometricProviderRegistry;
use App\Biometrics\Contracts\BiometricProvider;
use App\Biometrics\Services\BiometricDataDeletionService;
use App\Biometrics\Services\BiometricDeProvisioner;
use App\Biometrics\ValueObjects\AdmsCommandKey;
use App\Enums\AdmsCommandStatusEnum;
use App\Enums\BiometricTemplateSyncStatusEnum;
use App\Enums\BiometricTypeEnum;
use App\Enums\DefaultRole;
use App\Filament\Admin\Widgets\BiometricOperatorAttentionWidget;
use App\Models\AdmsDeviceCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricTemplate;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The operator surface for an erasure the system cannot discharge by itself: the
 * exception-sourced residual, the re-attempt of a reference-present terminal row, the
 * aggregate widget, and the enum state sets the surface reads.
 */
class BiometricTemplateOperatorSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected BiometricDevice $deviceA;

    protected BiometricDevice $deviceB;

    protected GymMember $member;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // The cloud branch must never reach a real provider endpoint.
        Http::preventStrayRequests();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $ownerRole = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');

        $this->owner = User::factory()->create();
        $this->gym->users()->attach($this->owner->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $this->owner->assignRole($ownerRole);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->owner);

        $this->deviceA = BiometricDevice::factory()->admsEnabled('SN-OPERATOR-A')->create([
            'gym_id' => $this->gym->id,
            'device_type' => 'fingerprint',
        ]);

        $this->deviceB = BiometricDevice::factory()->admsEnabled('SN-OPERATOR-B')->create([
            'gym_id' => $this->gym->id,
            'device_type' => 'fingerprint',
        ]);

        $this->member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Operator Surface Member',
        ]);
    }

    #[Test]
    public function an_exception_sourced_failure_is_recorded_as_an_outstanding_erasure(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked('custom_cloud')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => 'live-ref-1',
            'sync_status' => BiometricTemplateSyncStatusEnum::Synced,
            'is_active' => true,
        ]);

        $this->app->instance(BiometricDataDeletionService::class, new class(resolve(BiometricDeProvisioner::class)) extends BiometricDataDeletionService
        {
            public function deleteSingleTemplate(BiometricTemplate $template): bool
            {
                throw new RuntimeException('simulated de-provisioning crash');
            }
        });

        resolve(BiometricDataDeletionService::class)
            ->deleteForGymMember($this->member->fresh(), finalAttempt: true);

        // The member's own erasure still completes...
        $this->assertDatabaseMissing('biometric_consents', ['gym_member_id' => $this->member->id]);

        // ...but a raw Throwable must be recorded as an outstanding erasure, never as a
        // clean completion.
        $activity = Activity::query()
            ->where('log_name', 'biometric')
            ->where('subject_id', $this->member->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Expected a biometric activity entry for the erasure');
        $this->assertStringContainsString('unexpected error', $activity->description);
        $this->assertStringContainsString('outstanding', $activity->description);

        $properties = $activity->properties->toArray();

        $this->assertCount(1, $properties['exception_erasures']);
        $this->assertSame($template->id, $properties['exception_erasures'][0]['template_id']);
        $this->assertSame('custom_cloud', $properties['exception_erasures'][0]['provider']);
        $this->assertSame(RuntimeException::class, $properties['exception_erasures'][0]['error_type']);
    }

    #[Test]
    public function a_fresh_erasure_request_re_attempts_a_reference_present_unresolvable_row(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked('custom_cloud')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => '{"provider":"custom_cloud","externalId":"face-live","meta":[]}',
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
            'erasure_attempts' => 2,
        ]);

        // `erasure_first_attempted_at` is not mass-assignable: this is the provider that
        // outlasted its cooldown, and the reference is what makes the row re-drivable.
        $template->forceFill(['erasure_first_attempted_at' => now()->subHours(48)])->save();

        $provider = Mockery::mock(BiometricProvider::class);
        $provider->shouldReceive('deleteTemplate')
            ->once()
            ->andReturn(true);

        $registry = Mockery::mock(BiometricProviderRegistry::class);
        $registry->shouldReceive('resolve')
            ->once()
            ->with('custom_cloud')
            ->andReturn($provider);
        $this->app->instance(BiometricProviderRegistry::class, $registry);

        resolve(BiometricDataDeletionService::class)->deleteForGymMember($this->member->fresh());

        // The terminal `DeletionUnresolvable` state is not a permanent write-off: a
        // reference-present row is re-attempted by a fresh erasure request.
        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::Deleted, $template->sync_status);
        $this->assertSame(0, $template->erasure_attempts);
        $this->assertNotNull($template->erasure_requested_at);
    }

    #[Test]
    public function a_blank_reference_unresolvable_row_is_still_recorded_and_not_re_driven(): void
    {
        $template = BiometricTemplate::factory()->cloudBacked('unconfigured_cloud_provider')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => null,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
        ]);

        // Nothing to erase with: resolving a provider for it could only re-log the same
        // failure on every erasure request.
        $registry = Mockery::mock(BiometricProviderRegistry::class);
        $registry->shouldNotReceive('resolve');
        $this->app->instance(BiometricProviderRegistry::class, $registry);

        resolve(BiometricDataDeletionService::class)->deleteForGymMember($this->member->fresh());

        $template->refresh();
        $this->assertSame(BiometricTemplateSyncStatusEnum::DeletionUnresolvable, $template->sync_status);
        $this->assertNull($template->erasure_requested_at);

        $activity = Activity::query()
            ->where('log_name', 'biometric')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $properties = $activity->properties->toArray();
        $this->assertSame($template->id, $properties['unresolvable_erasures'][0]['template_id']);
    }

    #[Test]
    public function the_operator_attention_widget_lists_only_rows_that_need_an_operator(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->gym);

        // A cap-exhausted hardware erase: the template records no terminal state for it, so
        // the predicate has to read the command log.
        $exhaustedHardware = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceA->id,
            'biometric_type' => BiometricTypeEnum::Fingerprint,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        AdmsDeviceCommand::query()->create([
            'gym_id' => $this->gym->id,
            'device_id' => $this->deviceA->id,
            'command_type' => 'user_delete',
            'command_payload' => ['Pin' => (string) $this->member->id],
            'status' => AdmsCommandStatusEnum::FAILED,
            'attempt_count' => AdmsCommandKey::maxAttempts(),
            'idempotency_key' => AdmsDeviceCommand::deleteIdempotencyKeyFor($this->deviceA->id, (string) $this->member->id),
        ]);

        // A blank-reference cloud row: nothing to erase with, so it is an operator problem.
        $blankReferenceCloud = BiometricTemplate::factory()->cloudBacked('custom_cloud')->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => null,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
        ]);

        // A delete still within its delivery budget belongs to the reconciler, not the operator.
        $healthy = BiometricTemplate::factory()->deviceNative()->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => $this->deviceB->id,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => (string) $this->member->id,
            'sync_status' => BiometricTemplateSyncStatusEnum::PendingDeletion,
            'is_active' => false,
        ]);

        // A row in another gym must never surface in this tenant's widget.
        $otherGym = Gym::factory()->create();
        $otherMember = GymMember::factory()->create(['gym_id' => $otherGym->id]);

        $otherTenantRow = BiometricTemplate::factory()->cloudBacked('custom_cloud')->create([
            'gym_member_id' => $otherMember->id,
            'gym_id' => $otherGym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => null,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
        ]);

        Livewire::test(BiometricOperatorAttentionWidget::class)
            ->assertCanSeeTableRecords([$exhaustedHardware, $blankReferenceCloud])
            ->assertCanNotSeeTableRecords([$healthy, $otherTenantRow]);
    }

    #[Test]
    public function the_operator_attention_widget_renders_a_bounded_first_page_and_remains_paginated(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->gym);

        // More than one page at the default 10-per-page. Every row is a terminal
        // `DeletionUnresolvable` cloud row, so all of them are candidates without touching
        // the command log.
        $total = 12;

        BiometricTemplate::factory()->cloudBacked('custom_cloud')->count($total)->create([
            'gym_member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
            'enrolled_by_device_id' => null,
            'biometric_type' => BiometricTypeEnum::Face,
            'template_ref' => null,
            'sync_status' => BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
            'is_active' => false,
        ]);

        $component = Livewire::test(BiometricOperatorAttentionWidget::class);

        // The discovery surface must stay paginated rather than render every candidate: the
        // first page is bounded at the default page size. Filament's TableWidget paginates
        // with a simple paginator, which has no total, so the full candidate count is pinned
        // through `assertCountTableRecords()` — the query is never truncated, only the render
        // is bounded, and no row is silently dropped from the surface.
        $records = $component->instance()->getTableRecords();

        $this->assertInstanceOf(Paginator::class, $records);
        $this->assertSame(10, $records->perPage());
        $this->assertCount(10, $records->items());
        $component->assertCountTableRecords($total);
    }

    #[Test]
    public function the_operator_attention_widget_admits_the_roles_that_can_repair_an_erasure(): void
    {
        // The owner acting from setUp is admitted...
        $this->assertTrue(BiometricOperatorAttentionWidget::canView());

        // ...and so are the desk roles the `enrollBiometric` ability admits...
        foreach ([DefaultRole::MANAGER, DefaultRole::RECEPTIONIST] as $role) {
            $user = User::factory()->create();
            $this->gym->users()->attach($user->id, ['role' => $role->value]);
            $user->assignRole(Role::findOrCreate($role->value, 'web'));

            $this->actingAs($user);
            $this->assertTrue(
                BiometricOperatorAttentionWidget::canView(),
                "{$role->value} must see the surface for an erasure they can repair."
            );
        }

        // ...while a trainer is refused, mirroring the ability.
        $trainer = User::factory()->create();
        $this->gym->users()->attach($trainer->id, ['role' => DefaultRole::TRAINER->value]);
        $trainer->assignRole(Role::findOrCreate(DefaultRole::TRAINER->value, 'web'));

        $this->actingAs($trainer);
        $this->assertFalse(BiometricOperatorAttentionWidget::canView());
    }

    #[Test]
    public function biometric_sync_status_state_sets_agree_with_their_predicates(): void
    {
        foreach (BiometricTemplateSyncStatusEnum::cases() as $status) {
            $this->assertSame(
                $status->representsTerminalHeldCredential(),
                in_array($status, BiometricTemplateSyncStatusEnum::terminalCredentialStates(), true),
                "terminalCredentialStates() drifted from representsTerminalHeldCredential() for {$status->value}"
            );

            $this->assertSame(
                $status->representsAwaitingHardwareConfirmation(),
                in_array($status, BiometricTemplateSyncStatusEnum::awaitingHardwareConfirmationStates(), true),
                "awaitingHardwareConfirmationStates() drifted from representsAwaitingHardwareConfirmation() for {$status->value}"
            );

            $this->assertSame(
                $status->representsOperatorAttentionCandidate(),
                in_array($status, BiometricTemplateSyncStatusEnum::operatorAttentionCandidateStates(), true),
                "operatorAttentionCandidateStates() drifted from representsOperatorAttentionCandidate() for {$status->value}"
            );
        }

        $terminal = BiometricTemplateSyncStatusEnum::terminalCredentialStates();
        $awaiting = BiometricTemplateSyncStatusEnum::awaitingHardwareConfirmationStates();
        $liveOrPending = BiometricTemplateSyncStatusEnum::liveOrPendingCredentialStates();

        $this->assertEqualsCanonicalizing(
            [BiometricTemplateSyncStatusEnum::Synced, BiometricTemplateSyncStatusEnum::Propagated],
            $terminal
        );
        $this->assertEqualsCanonicalizing(
            [BiometricTemplateSyncStatusEnum::Pending, BiometricTemplateSyncStatusEnum::Propagated],
            $awaiting
        );
        $this->assertEqualsCanonicalizing(
            [BiometricTemplateSyncStatusEnum::PendingDeletion, BiometricTemplateSyncStatusEnum::DeletionUnresolvable],
            BiometricTemplateSyncStatusEnum::operatorAttentionCandidateStates()
        );

        // Both sets are subsets of the live-or-pending credential set...
        foreach ([...$terminal, ...$awaiting] as $status) {
            $this->assertContains($status, $liveOrPending);
        }

        // ...and each holds exactly one state the other does not: `Propagated` is the only
        // state a terminal may hold while a hardware acknowledgement can still promote it.
        $shared = array_values(array_filter(
            $terminal,
            static fn (BiometricTemplateSyncStatusEnum $status): bool => in_array($status, $awaiting, true)
        ));
        $this->assertSame([BiometricTemplateSyncStatusEnum::Propagated], $shared);
        $this->assertContains(BiometricTemplateSyncStatusEnum::Synced, $terminal);
        $this->assertNotContains(BiometricTemplateSyncStatusEnum::Synced, $awaiting);
        $this->assertContains(BiometricTemplateSyncStatusEnum::Pending, $awaiting);
        $this->assertNotContains(BiometricTemplateSyncStatusEnum::Pending, $terminal);

        // The erased and terminal states are in neither set, so no access decision can read
        // them as a held credential.
        foreach ([
            BiometricTemplateSyncStatusEnum::PendingDeletion,
            BiometricTemplateSyncStatusEnum::Deleted,
            BiometricTemplateSyncStatusEnum::Superseded,
            BiometricTemplateSyncStatusEnum::DeletionUnresolvable,
        ] as $status) {
            $this->assertNotContains($status, $terminal);
            $this->assertNotContains($status, $awaiting);
        }
    }
}
