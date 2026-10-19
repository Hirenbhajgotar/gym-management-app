<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ArchiveInactiveGymMembersCommand;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\GymMemberResource\Pages\ListGymMembers;
use App\Filament\Imports\GymMemberImporter;
use App\Imports\GymMemberImport;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Models\Permission;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GymMemberArchivingTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $gymOwner;

    protected function setUp(): void
    {
        parent::setUp();

        // Both importers fetch an avatar per row through AvatarService; keep the
        // suite off the network so it is hermetic and CI-proof.
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
            // hasDirectPermission() || hasPermissionViaRole(), so a direct grant
            // would survive any role revoke and make the gating test below
            // unrepresentative of how the panel is actually configured.
            $role->givePermissionTo($permission);
        }

        $this->actingAs($this->gymOwner);
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_model_scopes_filter_members_by_archiving_and_marketing_status_correctly(): void
    {
        $activeMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $optOutMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => false,
        ]);

        $archivedMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => true,
            'archived_at' => now(),
            'allow_whatsapp_marketing' => false,
        ]);

        $notArchived = GymMember::query()->notArchived()->pluck('id');
        $this->assertTrue($notArchived->contains($activeMember->id));
        $this->assertTrue($notArchived->contains($optOutMember->id));
        $this->assertFalse($notArchived->contains($archivedMember->id));

        $archived = GymMember::query()->archived()->pluck('id');
        $this->assertTrue($archived->contains($archivedMember->id));
        $this->assertFalse($archived->contains($activeMember->id));
        $this->assertFalse($archived->contains($optOutMember->id));

        $marketingAllowed = GymMember::query()->whatsappMarketingAllowed()->pluck('id');
        $this->assertTrue($marketingAllowed->contains($activeMember->id));
        $this->assertFalse($marketingAllowed->contains($optOutMember->id));
        $this->assertFalse($marketingAllowed->contains($archivedMember->id));
    }

    public function test_helper_methods_update_archiving_and_marketing_flags(): void
    {
        /** @var GymMember $member */
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $member->archive($this->gymOwner);

        $fresh1 = $member->fresh();
        $this->assertNotNull($fresh1);
        $this->assertTrue($fresh1->is_archived);
        $this->assertNotNull($fresh1->archived_at);
        $this->assertFalse($fresh1->allow_whatsapp_marketing);

        $member->unarchive($this->gymOwner);

        $fresh2 = $member->fresh();
        $this->assertNotNull($fresh2);
        $this->assertFalse($fresh2->is_archived);
        $this->assertNull($fresh2->archived_at);

        $member->toggleWhatsappMarketing(true);
        $fresh3 = $member->fresh();
        $this->assertNotNull($fresh3);
        $this->assertTrue($fresh3->allow_whatsapp_marketing);
    }

    public function test_toggling_whatsapp_marketing_on_an_archived_member_writes_the_snapshot_not_the_flag(): void
    {
        // The consent column is forced false for as long as the member is
        // archived, so the toggle must route through MemberArchiveService and land
        // in the snapshot — writing the column directly would leave the member
        // marked opted-in while archived and corrupt the unarchive round trip.
        /** @var GymMember $member */
        $member = GymMember::factory()->archived(marketingAllowedBeforeArchive: false)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addYear()->toDateString(),
        ]);

        $this->assertFalse($member->allow_whatsapp_marketing);
        $this->assertFalse($member->whatsapp_marketing_snapshot);

        $this->assertTrue($member->toggleWhatsappMarketing(true));

        $fresh = $member->fresh();
        $this->assertNotNull($fresh);
        // The flag stays forced false; the member's real decision is the snapshot.
        $this->assertFalse($fresh->allow_whatsapp_marketing);
        $this->assertTrue($fresh->whatsapp_marketing_snapshot);

        // And unarchiving hands the newly stated decision back.
        $this->assertTrue($fresh->unarchive($this->gymOwner));
        $this->assertTrue((bool) $fresh->fresh()?->allow_whatsapp_marketing);
    }

    public function test_archive_and_consent_columns_are_not_mass_assignable(): void
    {
        // The four columns are owned by MemberArchiveService and written through
        // forceFill(). Keeping them out of $fillable means a future
        // update($request->validated()) / fill($request->all()) cannot flip
        // archive state or the consent round-trip without going through the gate.
        $member = new GymMember();

        $member->fill([
            'gym_id' => $this->gym->id,
            'name' => 'Mass Assignment Probe',
            'phone' => '+919876500321',
            'is_archived' => true,
            'archived_at' => now(),
            'allow_whatsapp_marketing' => true,
            'whatsapp_marketing_snapshot' => true,
        ]);

        $this->assertSame('Mass Assignment Probe', $member->name);
        $this->assertNull($member->is_archived);
        $this->assertNull($member->archived_at);
        $this->assertNull($member->allow_whatsapp_marketing);
        $this->assertNull($member->whatsapp_marketing_snapshot);
    }

    public function test_csv_member_import_auto_archives_members_expired_over_two_years_ago(): void
    {
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Gold Plan',
            'duration' => 12,
        ]);

        $import = new GymMemberImport($this->gym->id);

        $rowOld = [
            'name' => 'Old Legacy Member',
            'phone' => '+919876543210',
            'email' => 'old@example.com',
            'membership_plan' => $plan->name,
            'membership_start' => now()->subYears(3)->toDateString(),
            'membership_end' => now()->subYears(2)->subMonths(1)->toDateString(),
        ];

        $import->processRow($rowOld);

        $oldMember = GymMember::query()->where('email', 'old@example.com')->first();
        $this->assertNotNull($oldMember);
        $this->assertTrue($oldMember->is_archived);
        $this->assertNotNull($oldMember->archived_at);
        $this->assertFalse($oldMember->allow_whatsapp_marketing);

        // The importer archives inline, so it owns the consent snapshot too —
        // without it, restoring this member can never give their marketing
        // consent back and the pre-archive decision is destroyed.
        $this->assertTrue($oldMember->whatsapp_marketing_snapshot);

        $oldMember->unarchive($this->gymOwner);

        $restored = GymMember::query()->where('email', 'old@example.com')->first();
        $this->assertNotNull($restored);
        $this->assertFalse($restored->is_archived);
        $this->assertTrue($restored->allow_whatsapp_marketing);
        $this->assertNull($restored->whatsapp_marketing_snapshot);

        $rowActive = [
            'name' => 'Current Active Member',
            'phone' => '+919876543211',
            'email' => 'active@example.com',
            'membership_plan' => $plan->name,
            'membership_start' => now()->toDateString(),
            'membership_end' => now()->addYear()->toDateString(),
        ];

        $import->processRow($rowActive);

        $activeMember = GymMember::query()->where('email', 'active@example.com')->first();
        $this->assertNotNull($activeMember);
        $this->assertFalse($activeMember->is_archived);
        $this->assertTrue($activeMember->allow_whatsapp_marketing);
    }

    public function test_filament_gym_member_importer_auto_archives_stale_members(): void
    {
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Silver Plan',
            'duration' => 6,
        ]);

        $importModel = Import::query()->create([
            'user_id' => $this->gymOwner->id,
            'importer' => GymMemberImporter::class,
            'total_rows' => 1,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'file_name' => 'test.csv',
            'file_path' => 'test.csv',
        ]);

        $importer = new GymMemberImporter(
            $importModel,
            [
                'name' => 'Name',
                'phone' => 'Phone',
                'membership_plan' => 'Membership Plan',
                'membership_end' => 'Membership End',
            ],
            ['tenantId' => $this->gym->id]
        );

        $importer([
            'Name' => 'Filament Old Member',
            'Phone' => '+919876543299',
            'Membership Plan' => $plan->name,
            'Membership End' => now()->subYears(3)->toDateString(),
        ]);

        $importedMember = GymMember::query()->where('phone', '+919876543299')->first();
        $this->assertNotNull($importedMember);
        $this->assertTrue($importedMember->is_archived);
        $this->assertNotNull($importedMember->archived_at);
        $this->assertFalse($importedMember->allow_whatsapp_marketing);
        $this->assertTrue($importedMember->whatsapp_marketing_snapshot);
    }

    public function test_filament_importer_updates_an_archived_member_instead_of_creating_a_duplicate(): void
    {
        $archivedMember = GymMember::factory()->archived()->create([
            'gym_id' => $this->gym->id,
            'phone' => '+919876543298',
            'email' => 'returning@example.com',
            'membership_end' => now()->subYears(3),
        ]);

        $importModel = Import::query()->create([
            'user_id' => $this->gymOwner->id,
            'importer' => GymMemberImporter::class,
            'total_rows' => 1,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'file_name' => 'test.csv',
            'file_path' => 'test.csv',
        ]);

        $importer = new GymMemberImporter(
            $importModel,
            [
                'name' => 'Name',
                'phone' => 'Phone',
                'membership_end' => 'Membership End',
            ],
            ['tenantId' => $this->gym->id]
        );

        $importer([
            'Name' => 'Returning Member',
            'Phone' => '+919876543298',
            'Membership End' => now()->addYear()->toDateString(),
        ]);

        // The archived row has to be found by phone, or the import collides with
        // the unique index on (gym_id, phone) instead of reviving the member.
        $this->assertSame(1, GymMember::query()->where('phone', '+919876543298')->count());

        $revived = GymMember::query()->where('phone', '+919876543298')->first();
        $this->assertNotNull($revived);
        $this->assertFalse($revived->is_archived);
        $this->assertNull($revived->archived_at);
        $this->assertNull($revived->whatsapp_marketing_snapshot);
        $this->assertSame($archivedMember->id, $revived->id);
    }

    public function test_filament_importer_treats_a_zero_duration_plan_as_a_same_day_term(): void
    {
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Zero Term Plan',
            'duration' => 0,
        ]);

        $importModel = Import::query()->create([
            'user_id' => $this->gymOwner->id,
            'importer' => GymMemberImporter::class,
            'total_rows' => 1,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'file_name' => 'test.csv',
            'file_path' => 'test.csv',
        ]);

        $importer = new GymMemberImporter(
            $importModel,
            [
                'name' => 'Name',
                'phone' => 'Phone',
                'membership_plan' => 'Membership Plan',
            ],
            ['tenantId' => $this->gym->id]
        );

        $importer([
            'Name' => 'Zero Term Member',
            'Phone' => '+919876543297',
            'Membership Plan' => $plan->name,
        ]);

        $member = GymMember::query()->where('phone', '+919876543297')->first();
        $this->assertNotNull($member);

        // A zero-length term derives end == start (today). That is a determinate
        // term, not a null decision: the row must still write both dates, and a
        // same-day end is not stale, so the member must not be archived.
        $this->assertNotNull($member->membership_start);
        $this->assertNotNull($member->membership_end);
        $this->assertSame(
            $member->membership_start->toDateString(),
            $member->membership_end->toDateString()
        );
        $this->assertFalse($member->is_archived);
    }

    public function test_archive_bulk_actions_are_gated_on_their_own_permissions(): void
    {
        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $role = Role::findOrCreate('gym_owner', 'web');

        foreach (['archive_gym_member', 'unarchive_gym_member'] as $name) {
            $role->revokePermissionTo($name);
        }

        $this->gymOwner->forgetCachedPermissions();

        /** @var Testable $unauthorized */
        $unauthorized = Livewire::actingAs($this->gymOwner)->test(ListGymMembers::class);
        $unauthorized->assertTableBulkActionHidden('archiveMembers')
            ->assertTableBulkActionHidden('unarchiveMembers');

        $role->givePermissionTo(
            Permission::query()->firstOrCreate(['name' => 'archive_gym_member', 'guard_name' => 'web']),
            Permission::query()->firstOrCreate(['name' => 'unarchive_gym_member', 'guard_name' => 'web']),
        );
        $this->gymOwner->forgetCachedPermissions();

        /** @var Testable $authorized */
        $authorized = Livewire::actingAs($this->gymOwner)->test(ListGymMembers::class);
        $authorized->assertTableBulkActionVisible('archiveMembers')
            ->assertTableBulkActionVisible('unarchiveMembers');
    }

    public function test_archive_bulk_actions_are_not_implied_by_the_update_permission(): void
    {
        // A role that can edit a member is deliberately not thereby able to retire
        // one: archive() and unarchive() are their own permissions, so revoking
        // only those — while leaving update_gym_member in place — must still hide
        // both actions.
        $limitedRole = Role::findOrCreate('limited_staff', 'web');

        foreach (['view_any_gym_member', 'view_gym_member', 'update_gym_member'] as $name) {
            $limitedRole->givePermissionTo(
                Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web'])
            );
        }

        $limitedUser = User::factory()->create();
        $limitedUser->gyms()->attach($this->gym->id, ['role' => DefaultRole::RECEPTIONIST->value]);
        $limitedUser->assignRole($limitedRole);
        $limitedUser->forgetCachedPermissions();

        $this->assertTrue($limitedUser->can('update_gym_member'));
        $this->assertFalse($limitedUser->can('archive_gym_member'));

        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        /** @var Testable $testable */
        $testable = Livewire::actingAs($limitedUser)->test(ListGymMembers::class);
        $testable->assertTableBulkActionHidden('archiveMembers')
            ->assertTableBulkActionHidden('unarchiveMembers');
    }

    public function test_unarchiving_restores_the_consent_snapshot_of_a_member_who_had_opted_in(): void
    {
        // The factory takes the pre-archive consent as a parameter precisely so
        // this case is expressible: without it every fixture is archived while
        // opted out and the round-trip proves nothing.
        /** @var GymMember $member */
        $member = GymMember::factory()->archived(true)->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addYear(),
        ]);

        $this->assertFalse($member->allow_whatsapp_marketing);
        $this->assertTrue($member->whatsapp_marketing_snapshot);

        $this->assertTrue($member->unarchive($this->gymOwner));

        $restored = $member->fresh();
        $this->assertNotNull($restored);
        $this->assertFalse($restored->is_archived);
        $this->assertTrue($restored->allow_whatsapp_marketing);
        $this->assertNull($restored->whatsapp_marketing_snapshot);
    }

    public function test_archive_inactive_members_artisan_command_archives_members_expired_over_2_years_ago(): void
    {
        $recentMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subYear(),
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $staleMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subYears(3),
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        /** @var PendingCommand $command */
        $command = $this->artisan(ArchiveInactiveGymMembersCommand::class);
        $command->expectsOutputToContain('Successfully archived 1 inactive member(s).');
        $command->execute();

        $freshStale = $staleMember->fresh();
        $freshRecent = $recentMember->fresh();
        $this->assertNotNull($freshStale);
        $this->assertNotNull($freshRecent);
        $this->assertTrue($freshStale->is_archived);
        $this->assertFalse($freshStale->allow_whatsapp_marketing);
        $this->assertFalse($freshRecent->is_archived);
    }

    public function test_list_gym_members_tabs_filter_active_expired_and_archived_records_properly(): void
    {
        $activeMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
        ]);

        $expiredMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subMonth(),
            'is_archived' => false,
        ]);

        $archivedMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->subYears(3),
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        /** @var Testable $testable */
        $testable = Livewire::actingAs($this->gymOwner)->test(ListGymMembers::class);
        $testable->set('activeTab', 'active')
            ->assertCanSeeTableRecords([$activeMember])
            ->assertCanNotSeeTableRecords([$expiredMember, $archivedMember])
            ->set('activeTab', 'expired')
            ->assertCanSeeTableRecords([$expiredMember])
            ->assertCanNotSeeTableRecords([$activeMember, $archivedMember])
            ->set('activeTab', 'archived')
            ->assertCanSeeTableRecords([$archivedMember])
            ->assertCanNotSeeTableRecords([$activeMember, $expiredMember]);
    }

    public function test_filament_bulk_actions_can_update_marketing_preference_and_archive_members(): void
    {
        $member1 = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        $member2 = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_end' => now()->addMonth(),
            'is_archived' => false,
            'allow_whatsapp_marketing' => true,
        ]);

        Filament::setTenant($this->gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        /** @var Testable $testable1 */
        $testable1 = Livewire::actingAs($this->gymOwner)->test(ListGymMembers::class);
        $testable1->callTableBulkAction('disableWhatsappMarketing', [$member1, $member2]);

        $fresh1 = $member1->fresh();
        $fresh2 = $member2->fresh();
        $this->assertNotNull($fresh1);
        $this->assertNotNull($fresh2);
        $this->assertFalse($fresh1->allow_whatsapp_marketing);
        $this->assertFalse($fresh2->allow_whatsapp_marketing);

        /** @var Testable $testable2 */
        $testable2 = Livewire::actingAs($this->gymOwner)->test(ListGymMembers::class);
        $testable2->callTableBulkAction('archiveMembers', [$member1]);

        $fresh1After = $member1->fresh();
        $this->assertNotNull($fresh1After);
        $this->assertTrue($fresh1After->is_archived);
        $this->assertNotNull($fresh1After->archived_at);
    }
}
