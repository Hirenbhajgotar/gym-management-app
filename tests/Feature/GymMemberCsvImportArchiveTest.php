<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Member\ArchiveInactiveMembersAction;
use App\Enums\DefaultRole;
use App\Imports\GymMemberImport;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Models\Permission;
use App\Models\User;
use App\Services\MemberArchiveAuthorization;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Archive-state coverage for the CSV importer.
 *
 * Every case drives collection() or the real rules() rather than processRow()
 * alone, because the importer's unit tests call processRow() directly and so
 * never reach the validation gate that decides what counts as a duplicate.
 */
class GymMemberCsvImportArchiveTest extends TestCase
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

        foreach (['view_any_gym_member', 'view_gym_member', 'create_gym_member', 'update_gym_member', 'delete_gym_member', 'delete_any_gym_member', 'archive_gym_member', 'unarchive_gym_member'] as $name) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
            // Granted through the role only. Spatie's hasPermissionTo() is
            // hasDirectPermission() || hasPermissionViaRole(), so a direct
            // grant as well would let a positive path pass even if the role
            // wiring were broken — and the refusal paths below would be testing
            // a configuration the panel never has.
            $role->givePermissionTo($permission);
        }

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    #[Test]
    public function it_imports_a_brand_new_member_row(): void
    {
        $import = new GymMemberImport($this->gym->id);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Brand New Member',
                'email' => 'brand-new@example.com',
                'phone' => '9876500001',
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);
        $this->assertDatabaseHas('gym_members', [
            'gym_id' => $this->gym->id,
            'name' => 'Brand New Member',
            'email' => 'brand-new@example.com',
            'phone' => '+919876500001',
            'is_archived' => false,
        ]);

        $this->assertNotNull($import->getImportRecord());
        $this->assertSame(1, $import->getImportRecord()->successful_rows);
    }

    #[Test]
    public function an_archived_members_phone_number_passes_validation(): void
    {
        GymMember::factory()->archived(marketingAllowedBeforeArchive: true)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500010',
            'email' => 'archived@example.com',
        ]);

        $import = new GymMemberImport($this->gym->id);

        // Post-transformForValidation shape, which is what the unique rule compares.
        $validator = Validator::make([
            'name' => 'Archived Member',
            'email' => 'archived@example.com',
            'phone' => '+919876500010',
            'membership_start' => now()->toDateString(),
            'membership_end' => now()->addYear()->toDateString(),
        ], $import->rules());

        $this->assertTrue(
            $validator->passes(),
            'An archived member must stay re-importable, otherwise the row can never reach the revive branch.'
        );
    }

    #[Test]
    public function an_active_members_phone_number_still_fails_validation(): void
    {
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500020',
            'email' => 'active@example.com',
        ]);

        $import = new GymMemberImport($this->gym->id);

        $validator = Validator::make([
            'name' => 'Duplicate Attempt',
            'email' => 'duplicate@example.com',
            'phone' => '+919876500020',
        ], $import->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('phone'));
    }

    #[Test]
    public function re_importing_an_archived_member_revives_them_and_restores_their_consent_snapshot(): void
    {
        $archivedMember = GymMember::factory()->archived(marketingAllowedBeforeArchive: true)->create([
            'gym_id' => $this->gym->id,
            'name' => 'Stale Name',
            'email' => 'returning@example.com',
            'phone' => '+919876500030',
        ]);

        $import = new GymMemberImport($this->gym->id);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Returning Member',
                'email' => 'returning@example.com',
                'phone' => '9876500030',
                'membership_start' => now()->toDateString(),
                'membership_end' => now()->addYear()->toDateString(),
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);
        $this->assertSame(1, GymMember::query()->where('phone', '+919876500030')->count());

        $revived = $archivedMember->fresh();

        $this->assertNotNull($revived);
        $this->assertSame('Returning Member', $revived->name);
        $this->assertFalse($revived->is_archived);
        $this->assertNull($revived->archived_at);
        $this->assertTrue($revived->allow_whatsapp_marketing);
        $this->assertNull($revived->whatsapp_marketing_snapshot);
    }

    #[Test]
    public function an_expired_row_writes_the_archive_state_and_the_pre_archive_consent_snapshot(): void
    {
        $import = new GymMemberImport($this->gym->id);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Old Legacy Member',
                'email' => 'legacy@example.com',
                'phone' => '9876500040',
                'membership_start' => now()->subYears(3)->toDateString(),
                'membership_end' => now()->subYears(3)->toDateString(),
                // States the OPPOSITE of the importer's default (opt-in), so the
                // snapshot assertion below can tell "the row's decision was
                // folded in" from "the default leaked through".
                'allow_whatsapp_marketing' => 'no',
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);

        $member = GymMember::query()->where('email', 'legacy@example.com')->first();

        $this->assertNotNull($member);
        $this->assertTrue($member->is_archived);
        $this->assertNotNull($member->archived_at);
        $this->assertFalse($member->allow_whatsapp_marketing);
        // Without the snapshot the pre-archive opt-out would be lost and
        // reviving this member could silently hand consent back. A default
        // opt-in would have stored true here.
        $this->assertFalse(
            (bool) $member->whatsapp_marketing_snapshot,
            "The row's stated opt-out must be the snapshot, not the importer's default opt-in."
        );
    }

    #[Test]
    public function an_operator_without_the_archive_ability_cannot_write_the_archive_state(): void
    {
        $receptionist = User::factory()->create();
        $receptionist->gyms()->attach($this->gym->id, ['role' => DefaultRole::RECEPTIONIST->value]);

        // Acting as the operator BEFORE the import is constructed: the gate is
        // evaluated against the user the import row recorded, which is the user
        // who dispatched it — acting afterwards would leave the gym_owner from
        // setUp as the recorded operator and this test would assert nothing.
        $this->actingAs($receptionist);

        $import = new GymMemberImport($this->gym->id);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Unauthorized Archive',
                'email' => 'unauthorized@example.com',
                'phone' => '9876500050',
                'membership_start' => now()->subYears(3)->toDateString(),
                'membership_end' => now()->subYears(3)->toDateString(),
            ]),
        ]));

        $this->assertDatabaseHas('failed_import_rows', [
            'validation_error' => 'You are not authorised to archive gym members.',
        ]);
        $this->assertDatabaseMissing('gym_members', [
            'email' => 'unauthorized@example.com',
        ]);
    }

    /**
     * The revive direction is the dangerous one: it restores
     * allow_whatsapp_marketing from the archive snapshot, puts the member back on
     * the roster, and hands door access straight back. Import is a permission a
     * receptionist and a trainer hold; unarchive is not. Without a gate on this
     * branch, either could return a deliberately revoked member with a spreadsheet.
     */
    #[Test]
    public function an_operator_without_the_unarchive_ability_cannot_revive_an_archived_member(): void
    {
        $archivedMember = GymMember::factory()->archived(marketingAllowedBeforeArchive: true)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500070',
            'email' => 'revive-me@example.com',
            'membership_end' => now()->subYears(3)->toDateString(),
        ]);

        $receptionist = User::factory()->create();
        $receptionist->gyms()->attach($this->gym->id, ['role' => DefaultRole::RECEPTIONIST->value]);

        // Before construction, for the reason documented in the test above.
        $this->actingAs($receptionist);

        $import = new GymMemberImport($this->gym->id);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Revived By Import',
                'email' => 'revive-me@example.com',
                'phone' => '9876500070',
                'membership_start' => now()->toDateString(),
                'membership_end' => now()->addYear()->toDateString(),
            ]),
        ]));

        $this->assertDatabaseHas('failed_import_rows', [
            'validation_error' => 'You are not authorised to restore archived gym members.',
        ]);

        // The member must be untouched in BOTH directions: still archived, still
        // opted out, snapshot intact, and the stale end date left alone.
        $untouched = $archivedMember->fresh();

        $this->assertNotNull($untouched);
        $this->assertTrue($untouched->is_archived);
        $this->assertNotNull($untouched->archived_at);
        $this->assertFalse($untouched->allow_whatsapp_marketing);
        $this->assertTrue($untouched->whatsapp_marketing_snapshot);
    }

    #[Test]
    public function a_revived_member_keeps_the_membership_end_the_import_supplied(): void
    {
        GymMember::factory()->archived(marketingAllowedBeforeArchive: true)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500080',
            'email' => 'renewing@example.com',
            'membership_end' => now()->subYears(3)->toDateString(),
        ]);

        $renewedEnd = now()->addMonths(9)->startOfDay();

        $import = new GymMemberImport($this->gym->id);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Renewing Member',
                'email' => 'renewing@example.com',
                'phone' => '9876500080',
                'membership_start' => now()->toDateString(),
                'membership_end' => $renewedEnd->toDateString(),
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);

        $revived = GymMember::query()->where('phone', '+919876500080')->first();

        $this->assertNotNull($revived);
        $this->assertFalse($revived->is_archived);

        // The whole point of persisting the end date: the decision to revive was
        // taken FROM it, so discarding it would leave the member still expired —
        // denied by canAccessGym() and re-archived by the very next sweep, silently
        // undoing the operator's instruction.
        $this->assertTrue(
            $revived->membership_end?->isSameDay($renewedEnd),
            'The revived member must keep the membership_end the import supplied.'
        );

        $secondSweep = resolve(ArchiveInactiveMembersAction::class)->execute(2);

        $this->assertSame(
            0,
            $secondSweep->archivedCount,
            'A member revived with a current membership_end must not be re-archived by the next sweep.'
        );
    }

    #[Test]
    public function a_plan_less_row_still_persists_the_membership_end_it_was_given(): void
    {
        // No membership_plan on the row, so the model's saving hook cannot derive an
        // end date from a plan duration. Before, $memberData carried no
        // membership_end at all and the create path stored NULL, leaving a member who
        // could never be revived and could never be swept either.
        $import = new GymMemberImport($this->gym->id);

        $end = now()->addMonths(6)->startOfDay();

        $import->collection(new Collection([
            new Collection([
                'name' => 'No Plan Member',
                'email' => 'noplan@example.com',
                'phone' => '9876500090',
                'membership_start' => now()->toDateString(),
                'membership_end' => $end->toDateString(),
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);

        $member = GymMember::query()->where('email', 'noplan@example.com')->first();

        $this->assertNotNull($member);
        $this->assertTrue(
            $member->membership_end?->isSameDay($end),
            'A row without a plan must still store the end date it supplied.'
        );
    }

    /**
     * The gate refuses when there is no operator to attribute the write to.
     *
     * Exercised against the guard directly rather than through the importer,
     * because `imports.user_id` is NOT NULL: BaseImporter cannot record an import
     * with nobody behind it in the first place. The guard still fails closed
     * because restoreImportingOperator() can leave no operator for a partially
     * restored instance, and an unattributable write must never be allowed.
     */
    #[Test]
    public function the_gate_refuses_when_no_operator_can_be_attributed(): void
    {
        Auth::forgetGuards();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('no authenticated operator is attached');

        resolve(MemberArchiveAuthorization::class)->assertCanArchive();
    }

    #[Test]
    public function the_gate_refuses_the_restore_direction_too_when_no_operator_can_be_attributed(): void
    {
        Auth::forgetGuards();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('no authenticated operator is attached');

        resolve(MemberArchiveAuthorization::class)->assertCanUnarchive();
    }

    #[Test]
    public function a_queued_import_without_a_session_is_still_evaluated_against_its_recorded_operator(): void
    {
        // The realistic production shape: the operator exists at dispatch time, then
        // the row runs on a worker with no session and no Spatie team context —
        // TeamsPermission is web-middleware only, so a worker resolves the
        // operator's gym-scoped role to nothing and the gate refuses EVERY
        // transition unless the importer establishes the team itself. Forgetting
        // the guards alone cannot see that, so the team id goes too.
        $import = new GymMemberImport($this->gym->id);

        Auth::forgetGuards();
        setPermissionsTeamId(null);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Queued Archive',
                'email' => 'queued-ok@example.com',
                'phone' => '9876500061',
                'membership_start' => now()->subYears(3)->toDateString(),
                'membership_end' => now()->subYears(3)->toDateString(),
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);

        $member = GymMember::query()->where('email', 'queued-ok@example.com')->first();

        $this->assertNotNull($member);
        $this->assertTrue($member->is_archived);
    }

    #[Test]
    public function a_queued_import_survives_serialization_with_its_recorded_operator(): void
    {
        // The importer crosses a queue boundary as a serialized payload, so the
        // operator it resolved at dispatch time has to survive that round trip.
        // BaseImporter uses SerializesModels, which stores the import record by
        // identifier and re-fetches it on unserialize — an in-process run can
        // never tell whether that works, so this asserts the actual
        // serialize()/unserialize() behaviour rather than inspecting it.
        $import = new GymMemberImport($this->gym->id);

        $restored = unserialize(serialize($import));

        $this->assertInstanceOf(GymMemberImport::class, $restored);
        $this->assertNotSame($import, $restored);
        $this->assertNotNull(
            $restored->getImportRecord(),
            'The import record must be re-fetched after a queue round trip.'
        );

        // No session: the restored importer must still resolve the operator from
        // its own recorded user_id, exactly as it does on a worker.
        Auth::forgetGuards();
        setPermissionsTeamId(null);

        $restored->collection(new Collection([
            new Collection([
                'name' => 'Serialized Archive',
                'email' => 'serialized-archive@example.com',
                'phone' => '9876500170',
                'membership_start' => now()->subYears(3)->toDateString(),
                'membership_end' => now()->subYears(3)->toDateString(),
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);

        $member = GymMember::query()->where('email', 'serialized-archive@example.com')->first();

        $this->assertNotNull($member);
        $this->assertTrue(
            $member->is_archived,
            'A serialized importer must still archive: the operator attribution survived the queue boundary.'
        );
    }

    #[Test]
    public function a_new_row_that_is_stale_and_opts_out_records_that_decision_in_the_snapshot(): void
    {
        // The invariant the whole service exists to hold: while a member is
        // archived the consent flag is forced false, so a row stating an
        // explicit consent decision has to land in the snapshot. The row here
        // states the OPPOSITE of the importer's default (opt-in) so the
        // snapshot assertion proves the row's decision was folded in rather
        // than a default leaking through.
        $import = new GymMemberImport($this->gym->id);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Stale Opt Out',
                'email' => 'stale-opt-out@example.com',
                'phone' => '9876500110',
                'membership_start' => now()->subYears(3)->toDateString(),
                'membership_end' => now()->subYears(3)->toDateString(),
                'allow_whatsapp_marketing' => 'no',
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);

        $member = GymMember::query()->where('email', 'stale-opt-out@example.com')->first();

        $this->assertNotNull($member);
        $this->assertTrue($member->is_archived);
        $this->assertFalse($member->allow_whatsapp_marketing);
        $this->assertFalse(
            (bool) $member->whatsapp_marketing_snapshot,
            "The row's stated opt-out must be the snapshot, not the importer's default opt-in."
        );
    }

    #[Test]
    public function a_new_stale_row_that_opts_in_never_gets_the_flag_while_archived(): void
    {
        // The dangerous direction: a row that opts IN must not produce
        // is_archived = true together with allow_whatsapp_marketing = true —
        // the state the feature calls impossible. The flag must stay forced
        // false however the row leans.
        $import = new GymMemberImport($this->gym->id);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Stale Opt In',
                'email' => 'stale-opt-in@example.com',
                'phone' => '9876500111',
                'membership_start' => now()->subYears(3)->toDateString(),
                'membership_end' => now()->subYears(3)->toDateString(),
                'allow_whatsapp_marketing' => 'yes',
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);

        $member = GymMember::query()->where('email', 'stale-opt-in@example.com')->first();

        $this->assertNotNull($member);
        $this->assertTrue($member->is_archived);
        $this->assertFalse(
            $member->allow_whatsapp_marketing,
            'An archived member must never carry the flag, however the row states consent.'
        );
    }

    #[Test]
    public function re_importing_an_archived_member_with_explicit_consent_records_it_in_the_snapshot(): void
    {
        // Same contract when no transition happens: the member stays archived,
        // so the row's decision still cannot touch the forced-false flag — but
        // it is the operator's latest statement of what the member decided, so
        // it belongs in the snapshot and must not be silently dropped.
        GymMember::factory()->archived(marketingAllowedBeforeArchive: false)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500120',
            'email' => 'still-archived@example.com',
            'membership_end' => now()->subYears(3)->toDateString(),
        ]);

        $import = new GymMemberImport($this->gym->id);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Still Archived',
                'email' => 'still-archived@example.com',
                'phone' => '9876500120',
                'membership_start' => now()->subYears(4)->toDateString(),
                'membership_end' => now()->subYears(4)->toDateString(),
                'allow_whatsapp_marketing' => 'yes',
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);

        $member = GymMember::query()->where('email', 'still-archived@example.com')->first();

        $this->assertNotNull($member);
        $this->assertTrue($member->is_archived);
        $this->assertFalse($member->allow_whatsapp_marketing);
        $this->assertTrue((bool) $member->whatsapp_marketing_snapshot);
    }

    #[Test]
    public function a_revived_member_keeps_the_rows_explicit_consent_over_the_snapshot(): void
    {
        // unarchive() restores the archived decision; a row that states its own
        // outranks it, because the operator is re-answering for the member now
        // that they are live again.
        GymMember::factory()->archived(marketingAllowedBeforeArchive: true)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500130',
            'email' => 'revive-optout@example.com',
            'membership_end' => now()->subYears(3)->toDateString(),
        ]);

        $import = new GymMemberImport($this->gym->id);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Revived Opt Out',
                'email' => 'revive-optout@example.com',
                'phone' => '9876500130',
                'membership_start' => now()->toDateString(),
                'membership_end' => now()->addYear()->toDateString(),
                'allow_whatsapp_marketing' => 'no',
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);

        $member = GymMember::query()->where('email', 'revive-optout@example.com')->first();

        $this->assertNotNull($member);
        $this->assertFalse($member->is_archived);
        $this->assertFalse(
            $member->allow_whatsapp_marketing,
            "The row's stated opt-out must outrank the archived snapshot, which said opted in."
        );
        $this->assertNull($member->whatsapp_marketing_snapshot);
    }

    /**
     * The absence of an end date is not a decision to revive.
     *
     * Guarding the date REWRITE is not enough: the archive-state DECISION is a
     * separate question. A row with no plan and no membership_end cell computed
     * $calculatedEnd = null, which the old code read as "not expired" — for an
     * already-archived member that is the unarchive() transition. Because
     * collection() strips blank cells, this fired for essentially every archived
     * member whose re-import row omitted the end date: the member was revived,
     * their consent restored, and the next 23:00 archive sweep re-archived them.
     */
    #[Test]
    public function re_importing_an_archived_member_with_no_end_date_does_not_revive_them(): void
    {
        $archivedAt = now()->subDays(10)->startOfSecond();

        $archivedMember = GymMember::factory()->archived(marketingAllowedBeforeArchive: true)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500140',
            'email' => 'no-end@example.com',
            'membership_end' => now()->subYears(3)->toDateString(),
        ]);

        $archivedMember->forceFill(['archived_at' => $archivedAt])->save();
        $originalSnapshot = $archivedMember->fresh()->whatsapp_marketing_snapshot;

        $import = new GymMemberImport($this->gym->id);

        // No membership_plan and no membership_end cell at all.
        $import->collection(new Collection([
            new Collection([
                'name' => 'Still Retired',
                'email' => 'no-end@example.com',
                'phone' => '9876500140',
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);

        $fresh = $archivedMember->fresh();

        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->is_archived, 'A row that states no expiry decision must not revive an archived member.');
        $this->assertTrue($fresh->archived_at?->equalTo($archivedAt), 'A no-op re-import must not restamp archived_at.');
        $this->assertFalse($fresh->allow_whatsapp_marketing);
        $this->assertSame($originalSnapshot, $fresh->whatsapp_marketing_snapshot);
    }

    /**
     * A revive decided from the row's PLAN still persists the end it revived
     * against, even though the end cell is absent.
     */
    #[Test]
    public function a_plan_only_revive_persists_the_plan_derived_membership_end(): void
    {
        GymMember::factory()->archived(marketingAllowedBeforeArchive: true)->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876500150',
            'email' => 'plan-revive@example.com',
            'membership_end' => now()->subYears(3)->toDateString(),
        ]);

        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Annual Plan',
            'duration' => 12,
        ]);

        $import = new GymMemberImport($this->gym->id);

        // The end date comes from the plan's duration, not from a cell.
        $import->collection(new Collection([
            new Collection([
                'name' => 'Plan Revive',
                'email' => 'plan-revive@example.com',
                'phone' => '9876500150',
                'membership_plan' => $plan->name,
            ]),
        ]));

        $this->assertDatabaseCount('failed_import_rows', 0);

        $member = GymMember::query()->where('email', 'plan-revive@example.com')->first();

        $this->assertNotNull($member);
        $this->assertFalse($member->is_archived);
        $this->assertNotNull(
            $member->membership_end,
            'A revive decided from the plan must persist the plan-derived end date.'
        );
        $this->assertTrue($member->membership_end->isFuture());
    }

    /**
     * The importer sets the Spatie team context for the row's own tenant and
     * must hand back whatever context it found. A queue worker is shared across
     * jobs, so leaking the import's tenant into the next job would resolve that
     * job's roles against the wrong gym.
     */
    #[Test]
    public function the_importer_restores_the_previous_team_context(): void
    {
        $otherGym = Gym::factory()->create();
        setPermissionsTeamId($otherGym->id);

        $import = new GymMemberImport($this->gym->id);

        $import->collection(new Collection([
            new Collection([
                'name' => 'Context Restore',
                'email' => 'context-restore@example.com',
                'phone' => '9876500160',
                'membership_start' => now()->subYears(3)->toDateString(),
                'membership_end' => now()->subYears(3)->toDateString(),
            ]),
        ]));

        $this->assertSame(
            $otherGym->id,
            getPermissionsTeamId(),
            'processRow() must restore the caller team context in finally, not leave the import tenant behind.'
        );

        // The row still archived correctly, so the restore did not happen at
        // the cost of the gate losing its team context.
        $member = GymMember::query()->where('email', 'context-restore@example.com')->first();
        $this->assertNotNull($member);
        $this->assertTrue($member->is_archived);
    }
}
