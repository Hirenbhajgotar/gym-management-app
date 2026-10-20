<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\GymMemberResource\Pages\ListGymMembers;
use App\Filament\Imports\GymMemberImporter;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Models\Permission;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Archive state may only be written by an operator who holds the matching
 * permission, on every path that can write it.
 *
 * The panel's bulk actions were already gated. The importers were not, and both
 * are authorised on `import` alone — which a receptionist and a trainer hold —
 * so a desk user explicitly denied archive/unarchive could retire or, far worse,
 * revive a member by uploading a spreadsheet.
 */
class MemberArchiveAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $gymOwner;

    protected function setUp(): void
    {
        parent::setUp();

        // The importer fetches an avatar per row; keep the suite off the network.
        Http::fake(['ui-avatars.com/*' => Http::response('', 404)]);
        Http::preventStrayRequests();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $role = Role::findOrCreate('gym_owner', 'web');

        $this->gymOwner = User::factory()->gymOwner()->create();
        $this->gymOwner->gyms()->attach($this->gym->id, ['role' => DefaultRole::GYM_OWNER->value]);

        foreach (['view_any_gym_member', 'view_gym_member', 'create_gym_member', 'update_gym_member', 'import_gym_member', 'archive_gym_member', 'unarchive_gym_member'] as $name) {
            $role->givePermissionTo(
                Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web'])
            );
        }

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function deskUser(): User
    {
        $receptionist = User::factory()->create();
        $receptionist->gyms()->attach($this->gym->id, ['role' => DefaultRole::RECEPTIONIST->value]);

        return $receptionist;
    }

    /**
     * Run the Filament importer over one row, as a specific import's operator.
     *
     * The importer resolves the operator from the import record's own user_id
     * — the person who dispatched it — rather than from the ambient guard, so
     * the operator is a property of the import, not of who happens to be
     * acting during the test.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $columnMap
     */
    private function runFilamentImporter(array $row, ?User $as = null, array $columnMap = ['name' => 'Name', 'phone' => 'Phone', 'membership_end' => 'Membership End']): void
    {
        $importModel = Import::query()->create([
            'user_id' => ($as ?? $this->gymOwner)->id,
            'importer' => GymMemberImporter::class,
            'total_rows' => 1,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'file_name' => 'test.csv',
            'file_path' => 'test.csv',
        ]);

        $importer = new GymMemberImporter(
            $importModel,
            $columnMap,
            ['tenantId' => $this->gym->id]
        );

        $importer($row);
    }

    public function test_the_filament_importer_refuses_to_archive_for_an_operator_without_the_permission(): void
    {
        $this->expectException(RowImportFailedException::class);
        $this->expectExceptionMessage('not authorised to archive gym members');

        $this->runFilamentImporter([
            'Name' => 'Desk Import',
            'Phone' => '+919876500201',
            'Membership End' => now()->subYears(3)->toDateString(),
        ], $this->deskUser());
    }

    public function test_the_filament_importer_refuses_to_revive_for_an_operator_without_the_permission(): void
    {
        $archived = GymMember::factory()->archived(marketingAllowedBeforeArchive: true)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500202',
            'membership_end' => now()->subYears(3)->toDateString(),
        ]);

        $this->expectException(RowImportFailedException::class);
        $this->expectExceptionMessage('not authorised to restore archived gym members');

        try {
            $this->runFilamentImporter([
                'Name' => 'Desk Import',
                'Phone' => '+919876500202',
                'Membership End' => now()->addYear()->toDateString(),
            ], $this->deskUser());
        } finally {
            // The refusal must leave the member exactly as it was, in both
            // directions — not revived, and not stripped of its consent record.
            $untouched = $archived->fresh();

            $this->assertNotNull($untouched);
            $this->assertTrue($untouched->is_archived);
            $this->assertFalse($untouched->allow_whatsapp_marketing);
            $this->assertTrue($untouched->whatsapp_marketing_snapshot);
        }
    }

    public function test_importing_a_live_member_does_not_require_the_unarchive_permission(): void
    {
        // The gate must fire on a real TRANSITION only. Demanding unarchive for
        // every ordinary import of a live member would lock out the exact roles
        // that are supposed to be able to import — and the operator here is the
        // desk role that holds `import` but not `unarchive`, which is what makes
        // this case meaningful.
        $this->runFilamentImporter([
            'Name' => 'Ordinary Import',
            'Phone' => '+919876500203',
            'Membership End' => now()->addYear()->toDateString(),
        ], $this->deskUser());

        $member = GymMember::query()->where('phone', '+919876500203')->first();

        $this->assertNotNull($member);
        $this->assertFalse($member->is_archived);
    }

    public function test_the_importer_establishes_the_tenant_context_a_worker_never_has(): void
    {
        // A queued import runs with no session and no Spatie team context:
        // TeamsPermission is web-middleware only, so getPermissionsTeamId() is
        // null on a worker and every gym-scoped role becomes invisible. Without
        // the importer setting the team for the gym being imported into, the
        // gate refuses EVERY import-driven transition in production — for
        // exactly the roles that hold the permission. Forgetting the guards
        // alone cannot see this, so the team id goes too.
        setPermissionsTeamId(null);
        Auth::forgetGuards();

        $this->runFilamentImporter([
            'Name' => 'Worker Import',
            'Phone' => '+919876500209',
            'Membership End' => now()->subYears(3)->toDateString(),
        ]);

        $member = GymMember::query()->where('phone', '+919876500209')->first();

        $this->assertNotNull($member, 'The import must not have failed: a gym_owner running it has the archive permission.');
        $this->assertTrue($member->is_archived);
    }

    public function test_reimporting_an_archived_member_does_not_restamp_archived_at(): void
    {
        $archived = GymMember::factory()->archived(marketingAllowedBeforeArchive: true)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500204',
            'membership_end' => now()->subYears(3)->toDateString(),
        ]);

        // Pinned well in the past so a restamp with now() is unmistakable rather
        // than a comparison between two timestamps in the same second.
        $originalArchivedAt = now()->subYear()->startOfSecond();
        $archived->forceFill(['archived_at' => $originalArchivedAt])->save();

        $originalSnapshot = $archived->whatsapp_marketing_snapshot;

        try {
            $this->runFilamentImporter([
                'Name' => 'Still Archived',
                'Phone' => '+919876500204',
                'Membership End' => now()->subYears(4)->toDateString(),
            ], $this->deskUser());
        } finally {
            $reimported = $archived->fresh();

            $this->assertNotNull($reimported);
            // archived_at answers "when was this member retired" — a re-import
            // is not a retirement.
            $this->assertTrue(
                $reimported->archived_at?->equalTo($originalArchivedAt),
                'Re-importing an already archived member must not restamp archived_at.'
            );

            // And the pre-archive consent must survive, or unarchive() would
            // later restore a value that was never the member's decision.
            $this->assertSame($originalSnapshot, $reimported->whatsapp_marketing_snapshot);
            $this->assertTrue($reimported->is_archived);
        }
    }

    public function test_a_stale_row_that_opts_in_snapshots_that_consent_rather_than_writing_the_flag(): void
    {
        // The Filament importer resolves the record by phone, so — unlike the
        // CSV importer, whose unique-phone rule reserves only live numbers —
        // the transition into archive IS reachable here with an explicit row
        // consent. While archived the flag is forced false; the row's stated
        // decision has to land in the snapshot, and the member must not end up
        // archived with marketing switched on.
        $live = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500210',
            'allow_whatsapp_marketing' => false,
            'is_archived' => false,
        ]);

        $this->runFilamentImporter([
            'Name' => 'Stale Opt In',
            'Phone' => '+919876500210',
            'Membership End' => now()->subYears(3)->toDateString(),
            'Allow WhatsApp Marketing' => 'yes',
        ], null, [
            'name' => 'Name',
            'phone' => 'Phone',
            'membership_end' => 'Membership End',
            'allow_whatsapp_marketing' => 'Allow WhatsApp Marketing',
        ]);

        $archived = $live->fresh();

        $this->assertNotNull($archived);
        $this->assertTrue($archived->is_archived);
        $this->assertFalse($archived->allow_whatsapp_marketing);
        $this->assertTrue(
            (bool) $archived->whatsapp_marketing_snapshot,
            "The row's stated consent must survive in the snapshot, or unarchive() can never hand it back."
        );
    }

    public function test_a_blank_membership_end_cell_does_not_null_the_stored_date(): void
    {
        // A member WITH a real plan, so the date-recompute branch in beforeSave()
        // is actually reachable. A plan-less member could never reach it, which
        // is how the original version of this test passed while the code it
        // claimed to cover overwrote the stored date regardless.
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Gold Plan',
            'duration' => 6,
        ]);

        $storedStart = now()->subMonths(4)->startOfDay();
        $storedEnd = now()->addYear()->startOfDay();

        $live = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500205',
            'gym_membership_plan_id' => $plan->id,
            'membership_start' => $storedStart->toDateString(),
            'membership_end' => $storedEnd->toDateString(),
        ]);

        // The plan cell is filled and the Membership End cell carries nothing.
        // Before the guard this recomputed the dates as now()..now()+duration,
        // overwriting a date the row never mentioned.
        $this->runFilamentImporter([
            'Name' => 'Sparse Row',
            'Phone' => '+919876500205',
            'Membership Plan' => $plan->name,
            'Membership End' => '',
        ], null, [
            'name' => 'Name',
            'phone' => 'Phone',
            'membership_plan' => 'Membership Plan',
            'membership_end' => 'Membership End',
        ]);

        $fresh = $live->fresh();

        $this->assertNotNull($fresh);
        $this->assertTrue(
            $fresh->membership_end?->equalTo($storedEnd),
            'A blank cell must not null or overwrite the stored membership_end.'
        );
        $this->assertTrue(
            $fresh->membership_start?->equalTo($storedStart),
            'A blank cell must not overwrite the stored membership_start either.'
        );
    }

    public function test_a_plan_only_row_revives_an_archived_member_and_persists_the_plan_derived_end(): void
    {
        // Row authority: a plan names a term (its duration), so a row that fills
        // the Membership Plan cell and leaves Membership End blank states a live
        // term and must revive the member — matching the CSV importer on the same
        // row shape. The revived member must come back with the plan-derived end,
        // or canAccessGym() keeps denying them and the next 23:00 archive sweep
        // re-archives them.
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Silver Plan',
            'duration' => 6,
        ]);

        $storedStart = now()->subYears(3)->toDateString();
        $storedEnd = now()->subYears(3)->startOfDay();
        $originalArchivedAt = now()->subYear()->startOfSecond();

        $archived = GymMember::factory()->archived(marketingAllowedBeforeArchive: true)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500207',
            'gym_membership_plan_id' => $plan->id,
            'membership_start' => $storedStart,
            'membership_end' => $storedEnd->toDateString(),
            'archived_at' => $originalArchivedAt,
        ]);

        $expectedEnd = now()->addMonths(6);

        $this->runFilamentImporter([
            'Name' => 'Sparse Row',
            'Phone' => '+919876500207',
            'Membership Plan' => $plan->name,
            'Membership End' => '',
        ], null, [
            'name' => 'Name',
            'phone' => 'Phone',
            'membership_plan' => 'Membership Plan',
            'membership_end' => 'Membership End',
        ]);

        $fresh = $archived->fresh();

        $this->assertNotNull($fresh);
        $this->assertFalse(
            $fresh->is_archived,
            'A plan-backed row states a live term and must revive the member.'
        );
        $this->assertNull($fresh->archived_at);
        $this->assertNull($fresh->whatsapp_marketing_snapshot);
        $this->assertTrue(
            $fresh->allow_whatsapp_marketing,
            'Unarchiving must hand back the pre-archive consent the snapshot held.'
        );
        $this->assertSame(
            $expectedEnd->toDateString(),
            $fresh->membership_end?->toDateString(),
            'The revive must persist the plan-derived end, not leave the stale stored one.'
        );
    }

    public function test_a_row_that_omits_the_membership_end_column_does_not_revive_an_archived_member(): void
    {
        // The Filament importer derives archive state from the resolved
        // membership_end. A row that does not map the Membership End column at
        // all leaves the stored end untouched (ignoreBlankState + the
        // end-date-absent guard) — but the stored end may be null or future (a
        // manual archive), so the else branch must not treat "not older than the
        // cutoff" as a revive. This is the Filament leg of the absent-end
        // contract; the CSV suite covers the plan-less, no-cell case.
        $archived = GymMember::factory()->archived(marketingAllowedBeforeArchive: true)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500212',
            'membership_end' => now()->subYears(3)->toDateString(),
        ]);

        $originalArchivedAt = now()->subYear()->startOfSecond();
        $archived->forceFill(['archived_at' => $originalArchivedAt])->save();

        $originalEnd = $archived->fresh()?->membership_end;
        $originalSnapshot = $archived->fresh()?->whatsapp_marketing_snapshot;

        $this->runFilamentImporter([
            'Name' => 'No End Column',
            'Phone' => '+919876500212',
        ], null, [
            'name' => 'Name',
            'phone' => 'Phone',
        ]);

        $fresh = $archived->fresh();

        $this->assertNotNull($fresh);
        $this->assertTrue(
            $fresh->is_archived,
            'A row that never mapped Membership End must not revive an archived member.'
        );
        $this->assertTrue($fresh->archived_at?->equalTo($originalArchivedAt));
        $this->assertSame($originalSnapshot, $fresh->whatsapp_marketing_snapshot);
        $this->assertTrue(
            $fresh->membership_end?->equalTo($originalEnd),
            'An unmapped Membership End column must not null the stored date.'
        );
    }

    public function test_a_row_that_states_no_term_does_not_archive_a_live_member_with_a_stale_stored_end(): void
    {
        // Row authority, the opposite direction. A live member whose STORED end
        // is stale must not be archived by a row that states no term at all:
        // deriving the decision from the stored record — as the Filament
        // importer used to — archived them, while the CSV importer left them
        // alone. A row that states no decision expresses null, which moves no
        // archive state either way.
        $live = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500211',
            'membership_end' => now()->subYears(3)->toDateString(),
            'is_archived' => false,
        ]);

        $this->runFilamentImporter([
            'Name' => 'No Term',
            'Phone' => '+919876500211',
        ], null, [
            'name' => 'Name',
            'phone' => 'Phone',
        ]);

        $fresh = $live->fresh();

        $this->assertNotNull($fresh);
        $this->assertFalse(
            $fresh->is_archived,
            'A row that states no term must not archive a live member from a stale stored end.'
        );
        $this->assertNull($fresh->archived_at);
    }

    public function test_the_gym_member_archive_helper_refuses_an_operator_without_the_permission(): void
    {
        // The model helper is a public writer, so it carries the same gate the
        // panel and the importers use rather than trusting every caller to have
        // checked first. The operator is passed in explicitly — the helper no
        // longer reads the ambient guard — so an unprivileged desk user is
        // refused with the member left untouched.
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500213',
            'membership_end' => now()->addYear()->toDateString(),
            'is_archived' => false,
        ]);

        try {
            $member->archive($this->deskUser());
            $this->fail('archive() must refuse an operator without the archive permission.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('not authorised to archive', $exception->getMessage());
        }

        $untouched = $member->fresh();

        $this->assertNotNull($untouched);
        $this->assertFalse($untouched->is_archived);
        $this->assertNull($untouched->archived_at);
    }

    public function test_the_gym_member_archive_helper_allows_an_authorised_operator(): void
    {
        // The positive control: the same call succeeds for the gym owner, so the
        // refusal above cannot be passing for an unrelated reason.
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500214',
            'membership_end' => now()->addYear()->toDateString(),
            'is_archived' => false,
        ]);

        $this->actingAs($this->gymOwner);

        $this->assertTrue($member->archive($this->gymOwner));
        $this->assertTrue((bool) $member->fresh()?->is_archived);

        $this->assertTrue($member->unarchive($this->gymOwner));
        $this->assertFalse((bool) $member->fresh()?->is_archived);
    }

    public function test_the_gym_member_archive_helper_refuses_when_no_operator_can_be_attributed(): void
    {
        // The helper no longer reads the ambient guard, so calling it with no
        // operator must fail closed rather than authorise an unattributable
        // write as whatever identity happens to be logged in. Both directions
        // are checked, and a real operator is the positive control.
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500215',
            'membership_end' => now()->addYear()->toDateString(),
            'is_archived' => false,
        ]);

        $this->actingAs($this->gymOwner);

        try {
            $member->archive();
            $this->fail('archive() must refuse when no operator is passed.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('no authenticated operator', $exception->getMessage());
        }

        $untouched = $member->fresh();

        $this->assertNotNull($untouched);
        $this->assertFalse((bool) $untouched->is_archived);
        $this->assertNull($untouched->archived_at);

        $this->assertTrue($member->archive($this->gymOwner));
        $this->assertTrue((bool) $member->fresh()?->is_archived);

        try {
            $member->unarchive();
            $this->fail('unarchive() must refuse when no operator is passed.');
        } catch (AuthorizationException $exception) {
            $this->assertStringContainsString('no authenticated operator', $exception->getMessage());
        }

        $this->assertTrue((bool) $member->fresh()?->is_archived);
    }

    public function test_the_importer_no_longer_offers_an_is_archived_column(): void
    {
        $columns = collect(GymMemberImporter::getColumns())->map(
            fn ($column): string => $column->getName()
        );

        // Archive state is derived from membership_end, not supplied. An
        // Is Archived column would be a second, ungated way to flip it.
        $this->assertFalse(
            $columns->contains('is_archived'),
            'The importer must not accept an operator-supplied archive state.'
        );

        $this->assertTrue($columns->contains('membership_end'));
    }

    public function test_the_policy_refuses_a_record_from_a_gym_the_operator_does_not_own(): void
    {
        // The bulk-action gate only ever receives the model CLASS, so the
        // policy's per-record tenant branch is unreachable from there. This is
        // that branch, exercised directly — it must be a real check, not
        // decoration.
        $otherGym = Gym::factory()->create();

        $outsider = GymMember::factory()->create([
            'gym_id' => $otherGym->id,
            'membership_end' => now()->addYear()->toDateString(),
            'is_archived' => false,
        ]);

        $this->assertFalse($this->gymOwner->can('archive', $outsider));
        $this->assertFalse($this->gymOwner->can('unarchive', $outsider));

        $insider = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addYear()->toDateString(),
            'is_archived' => false,
        ]);

        $this->assertTrue($this->gymOwner->can('archive', $insider));
        $this->assertTrue($this->gymOwner->can('unarchive', $insider));
    }

    public function test_the_bulk_action_gate_still_works_off_the_model_class(): void
    {
        // What BulkAction actually passes: Gate::check('archive', GymMember::class).
        $this->assertTrue($this->gymOwner->can('archive', GymMember::class));
        $this->assertFalse($this->deskUser()->can('archive', GymMember::class));
    }

    public function test_the_marketing_bulk_action_skips_an_archived_member(): void
    {
        $archived = GymMember::factory()->archived(marketingAllowedBeforeArchive: false)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addYear()->toDateString(),
        ]);

        $live = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addYear()->toDateString(),
            'allow_whatsapp_marketing' => false,
        ]);

        /** @var Testable $testable */
        $testable = Livewire::actingAs($this->gymOwner)->test(ListGymMembers::class);
        $testable->callTableBulkAction('enableWhatsappMarketing', [$archived, $live]);

        // The archived member's consent is owned by MemberArchiveService for as
        // long as they are archived; the panel must not write that column.
        $this->assertFalse((bool) $archived->fresh()?->allow_whatsapp_marketing);
        $this->assertTrue((bool) $live->fresh()?->allow_whatsapp_marketing);
    }

    public function test_the_import_actions_are_hidden_without_the_import_permission(): void
    {
        Role::findOrCreate('gym_owner', 'web')->revokePermissionTo('import_gym_member');
        $this->gymOwner->forgetCachedPermissions();

        $this->assertFalse($this->gymOwner->can('import_gym_member'));

        /** @var Testable $testable */
        $testable = Livewire::actingAs($this->gymOwner)->test(ListGymMembers::class);

        $testable->assertActionHidden('import');
    }
}
