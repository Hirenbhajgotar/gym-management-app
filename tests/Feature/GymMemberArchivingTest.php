<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ArchiveInactiveGymMembersCommand;
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

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $role = Role::findOrCreate('gym_owner', 'web');

        $this->gymOwner = User::factory()->gymOwner()->create();
        $this->gymOwner->gyms()->attach($this->gym->id);

        foreach (['view_any_gym_member', 'view_gym_member', 'create_gym_member', 'update_gym_member', 'delete_gym_member', 'delete_any_gym_member'] as $name) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($permission);
            $this->gymOwner->givePermissionTo($permission);
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

        $member->archive();

        $fresh1 = $member->fresh();
        $this->assertNotNull($fresh1);
        $this->assertTrue($fresh1->is_archived);
        $this->assertNotNull($fresh1->archived_at);
        $this->assertFalse($fresh1->allow_whatsapp_marketing);

        $member->unarchive();

        $fresh2 = $member->fresh();
        $this->assertNotNull($fresh2);
        $this->assertFalse($fresh2->is_archived);
        $this->assertNull($fresh2->archived_at);

        $member->toggleWhatsappMarketing(true);
        $fresh3 = $member->fresh();
        $this->assertNotNull($fresh3);
        $this->assertTrue($fresh3->allow_whatsapp_marketing);
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
