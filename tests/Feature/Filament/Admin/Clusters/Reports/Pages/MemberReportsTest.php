<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Clusters\Reports\Pages;

use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Clusters\Reports\Pages\MemberReports;
use App\Filament\Admin\Resources\GymMemberResource;
use App\Filament\Admin\Resources\GymMemberResource\Pages\EditGymMember;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Models\Permission;
use App\Models\Trainer;
use App\Models\User;
use App\Services\ReportCacheService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Livewire\Livewire\Features\SupportTesting\Testable;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class MemberReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $gymOwner;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);

        $role = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');

        $this->gymOwner = User::factory()->gymOwner()->create([
            'email_verified_at' => now(),
        ]);
        $this->gymOwner->gyms()->attach($this->gym->id, ['role' => DefaultRole::GYM_OWNER->value]);

        $permissions = [
            'page_member_reports',
            'view_any_gym_member',
            'view_gym_member',
            'update_gym_member',
            'create_gym_member',
            'delete_gym_member',
        ];

        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($permission);
            $this->gymOwner->givePermissionTo($permission);
        }

        $this->actingAs($this->gymOwner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->gym);
    }

    public function test_super_admin_can_access_member_reports_page(): void
    {
        $superAdmin = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $superAdminRole = Role::findOrCreate(DefaultRole::SUPER_ADMIN->value, 'web');
        $superAdmin->assignRole($superAdminRole);

        $this->actingAs($superAdmin);

        $this->assertTrue(MemberReports::canAccess());
    }

    public function test_user_with_permission_can_access_member_reports_page(): void
    {
        $this->actingAs($this->gymOwner);

        $this->assertTrue(MemberReports::canAccess());
    }

    public function test_user_without_permission_cannot_access_member_reports_page(): void
    {
        $unauthorizedUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($unauthorizedUser);

        $this->assertFalse(MemberReports::canAccess());
    }

    public function test_unauthenticated_user_cannot_access_member_reports_page(): void
    {
        auth()->logout();

        $this->assertFalse(MemberReports::canAccess());
    }

    public function test_member_reports_page_mounts_and_renders_successfully(): void
    {
        Livewire::test(MemberReports::class)
            ->set('manualGymId', $this->gym->id)
            ->assertSuccessful()
            ->assertSee('Member Analytics')
            ->assertSee('Total Members')
            ->assertSee('Active Members')
            ->assertSee('Expired Members')
            ->assertSee('With Trainer')
            ->assertSee('Retention Rate');
    }

    public function test_default_filters_are_initialized_on_mount(): void
    {
        $component = Livewire::test(MemberReports::class)
            ->set('manualGymId', $this->gym->id);

        $expectedStartDate = now()->subYear()->format('Y-m-d');
        $expectedEndDate = now()->format('Y-m-d');

        $component->assertSet('filters.start_date', $expectedStartDate);
        $component->assertSet('filters.end_date', $expectedEndDate);
        $component->assertSet('filters.status', null);
        $component->assertSet('filters.membership_plan_id', null);
    }

    public function test_filtered_members_query_filters_by_date_range(): void
    {
        $oldMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'created_at' => now()->subYears(2),
        ]);

        $recentMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'created_at' => now()->subMonths(3),
        ]);

        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->subMonths(6)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => null,
            'membership_plan_id' => null,
        ];

        $results = $page->getFilteredMembersQuery()->get();

        $this->assertTrue($results->contains('id', $recentMember->id));
        $this->assertFalse($results->contains('id', $oldMember->id));
    }

    public function test_filtered_members_query_filters_by_active_status(): void
    {
        $activeMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
        ]);

        $expiredMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonths(3),
            'membership_end' => now()->subDay(),
        ]);

        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => 'active',
            'membership_plan_id' => null,
        ];

        $results = $page->getFilteredMembersQuery()->get();

        $this->assertTrue($results->contains('id', $activeMember->id));
        $this->assertFalse($results->contains('id', $expiredMember->id));
    }

    public function test_filtered_members_query_filters_by_expired_status(): void
    {
        $activeMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
        ]);

        $expiredMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subMonths(3),
            'membership_end' => now()->subDay(),
        ]);

        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => 'expired',
            'membership_plan_id' => null,
        ];

        $results = $page->getFilteredMembersQuery()->get();

        $this->assertFalse($results->contains('id', $activeMember->id));
        $this->assertTrue($results->contains('id', $expiredMember->id));
    }

    public function test_filtered_members_query_filters_by_membership_plan(): void
    {
        $planA = MembershipPlan::factory()->create(['gym_id' => $this->gym->id, 'name' => 'Plan A']);
        $planB = MembershipPlan::factory()->create(['gym_id' => $this->gym->id, 'name' => 'Plan B']);

        $memberWithPlanA = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_membership_plan_id' => $planA->id,
        ]);

        $memberWithPlanB = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'gym_membership_plan_id' => $planB->id,
        ]);

        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => null,
            'membership_plan_id' => $planA->id,
        ];

        $results = $page->getFilteredMembersQuery()->get();

        $this->assertTrue($results->contains('id', $memberWithPlanA->id));
        $this->assertFalse($results->contains('id', $memberWithPlanB->id));
    }

    public function test_filtered_members_query_enforces_tenant_isolation(): void
    {
        $otherGym = Gym::factory()->create();

        $ourMember = GymMember::factory()->create(['gym_id' => $this->gym->id]);
        $otherMember = GymMember::factory()->create(['gym_id' => $otherGym->id]);

        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => null,
            'membership_plan_id' => null,
        ];

        $results = $page->getFilteredMembersQuery()->get();

        $this->assertTrue($results->contains('id', $ourMember->id));
        $this->assertFalse($results->contains('id', $otherMember->id));
    }

    public function test_summary_stats_calculates_correct_metrics(): void
    {
        $trainer = Trainer::factory()->create(['gym_id' => $this->gym->id]);

        $active1 = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subDays(30),
            'membership_end' => now()->addDays(30),
            'trainer_id' => $trainer->id,
        ]);

        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subDays(10),
            'membership_end' => now()->addDays(50),
            'trainer_id' => null,
        ]);

        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'membership_start' => now()->subDays(90),
            'membership_end' => now()->subDays(10),
            'trainer_id' => null,
        ]);

        // Add 2 completed payments to active1 for retention calculation
        MemberPayment::factory()->count(2)->create([
            'gym_id' => $this->gym->id,
            'member_id' => $active1->id,
            'status' => PaymentStatus::COMPLETED,
        ]);

        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => null,
            'membership_plan_id' => null,
        ];

        $stats = $page->getSummaryStats();

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['active']);
        $this->assertEquals(1, $stats['expired']);
        $this->assertEquals(1, $stats['with_trainer']);
        $this->assertEquals(round((2 / 3) * 100, 1), $stats['active_rate']);
        $this->assertEquals(round((1 / 3) * 100, 1), $stats['retention_rate']);
        $this->assertGreaterThan(0, $stats['avg_duration_days']);
    }

    public function test_summary_stats_handles_zero_members_gracefully(): void
    {
        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => null,
            'membership_plan_id' => null,
        ];

        $stats = $page->getSummaryStats();

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['active']);
        $this->assertEquals(0, $stats['expired']);
        $this->assertEquals(0, $stats['with_trainer']);
        $this->assertEquals(0, $stats['retention_rate']);
        $this->assertEquals(0, $stats['active_rate']);
        $this->assertEquals(0, $stats['avg_duration_days']);
    }

    public function test_growth_data_groups_by_month_and_calculates_growth_rate(): void
    {
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'created_at' => Date::parse('2026-01-15'),
        ]);

        GymMember::factory()->count(2)->create([
            'gym_id' => $this->gym->id,
            'created_at' => Date::parse('2026-02-15'),
        ]);

        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-28',
            'status' => null,
            'membership_plan_id' => null,
        ];

        /** @var array<int, array{month: string, count: int, growth_rate: float, retention_rate: int}> $growth */
        $growth = $page->getGrowthData()->all();

        $this->assertCount(2, $growth);
        $this->assertEquals('Jan 2026', $growth[0]['month']);
        $this->assertEquals(1, $growth[0]['count']);
        $this->assertEquals(0, $growth[0]['growth_rate']);

        $this->assertEquals('Feb 2026', $growth[1]['month']);
        $this->assertEquals(2, $growth[1]['count']);
        $this->assertEquals(100.0, $growth[1]['growth_rate']);
    }

    public function test_plan_distribution_groups_by_plan_and_calculates_revenue(): void
    {
        $planGold = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Gold VIP',
            'price' => 5000.00,
        ]);

        $planSilver = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Silver Standard',
            'price' => 2500.00,
        ]);

        GymMember::factory()->count(3)->create([
            'gym_id' => $this->gym->id,
            'gym_membership_plan_id' => $planGold->id,
        ]);

        GymMember::factory()->count(2)->create([
            'gym_id' => $this->gym->id,
            'gym_membership_plan_id' => $planSilver->id,
        ]);

        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => null,
            'membership_plan_id' => null,
        ];

        $distribution = $page->getPlanDistribution();

        $this->assertCount(2, $distribution);

        /** @var array{name: string, count: int, revenue: float|int, color: string}|null $goldEntry */
        $goldEntry = $distribution->firstWhere('name', 'Gold VIP');
        $this->assertNotNull($goldEntry);
        $this->assertEquals(3, $goldEntry['count']);
        $this->assertEquals(15000.00, $goldEntry['revenue']);
        $this->assertNotEmpty($goldEntry['color']);

        /** @var array{name: string, count: int, revenue: float|int, color: string}|null $silverEntry */
        $silverEntry = $distribution->firstWhere('name', 'Silver Standard');
        $this->assertNotNull($silverEntry);
        $this->assertEquals(2, $silverEntry['count']);
        $this->assertEquals(5000.00, $silverEntry['revenue']);
    }

    public function test_churn_stats_returns_churn_metrics_and_top_at_risk_members(): void
    {
        GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'created_at' => now()->subMonths(2),
            'membership_start' => now()->subMonths(2),
            'membership_end' => now()->addDays(5),
        ]);

        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $churnStats = $page->getChurnStats();

        $this->assertArrayHasKey('predicted_churn_rate', $churnStats);
        $this->assertArrayHasKey('at_risk_count', $churnStats);
        $this->assertArrayHasKey('top_at_risk', $churnStats);
    }

    public function test_handle_member_stats_update_clears_cache_and_dispatches_refresh(): void
    {
        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $page->filters = [
            'start_date' => now()->subYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'status' => null,
            'membership_plan_id' => null,
        ];

        $summaryKey = "reports:member_summary:gym:{$this->gym->id}:" . md5((string) json_encode($page->filters));

        Cache::put($summaryKey, ['cached' => true], 600);
        Cache::put(ReportCacheService::CHURN_STATS . $this->gym->id, ['cached' => true], 600);

        $page->handleMemberStatsUpdate();

        $this->assertFalse(Cache::has(ReportCacheService::CHURN_STATS . $this->gym->id));
        $this->assertFalse(Cache::has($summaryKey));

        Livewire::test(MemberReports::class)
            ->set('manualGymId', $this->gym->id)
            ->call('handleMemberStatsUpdate')
            ->assertDispatched('refresh-widgets');
    }

    public function test_shared_report_data_and_title(): void
    {
        $page = new MemberReports();
        $page->manualGymId = $this->gym->id;
        $page->mount();

        $sharedData = $page->getSharedReportData();

        $this->assertArrayHasKey('stats', $sharedData);
        $this->assertArrayHasKey('growth', $sharedData);
        $this->assertArrayHasKey('planDistribution', $sharedData);
        $this->assertArrayHasKey('churnStats', $sharedData);
        $this->assertArrayHasKey('chart_data', $sharedData);
        $this->assertEquals('Member Report', $page->getSharedReportTitle());
    }

    public function test_member_resource_url_helper_generates_correct_gym_member_resource_urls(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
        ]);

        $expectedEditUrl = GymMemberResource::getUrl('edit', ['record' => $member->id]);
        $expectedIndexUrl = GymMemberResource::getUrl('index');

        // Test passing model instance with 'edit'
        $editUrlFromModel = $member->memberResourceUrl($member, 'edit');
        $this->assertEquals($expectedEditUrl, $editUrlFromModel);
        $this->assertStringContainsString("gym-members/{$member->id}/edit", $editUrlFromModel);

        // Test passing integer ID with 'edit'
        $editUrlFromId = $member->memberResourceUrl($member->id, 'edit');
        $this->assertEquals($expectedEditUrl, $editUrlFromId);

        // Test passing model instance with 'index'
        $indexUrl = $member->memberResourceUrl($member, 'index');
        $this->assertEquals($expectedIndexUrl, $indexUrl);
        $this->assertStringContainsString('gym-members', $indexUrl);

        // Test default parameter fallback
        $defaultUrl = $member->memberResourceUrl($member);
        $this->assertEquals($expectedIndexUrl, $defaultUrl);
    }

    public function test_blade_view_renders_high_risk_members_with_resource_edit_url(): void
    {
        Cache::forget(ReportCacheService::CHURN_STATS . $this->gym->id);

        $atRiskMember = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'High Risk Member',
            'email' => 'highrisk@example.com',
            'created_at' => now()->subDays(45),
            'membership_start' => now()->subDays(45),
            'membership_end' => now()->addDays(3),
        ]);

        MemberPayment::factory()->create([
            'gym_id' => $this->gym->id,
            'member_id' => $atRiskMember->id,
            'status' => PaymentStatus::FAILED,
        ]);

        $atRiskMember->memberResourceUrl($atRiskMember, 'edit');

        $component = Livewire::test(MemberReports::class)
            ->set('manualGymId', $this->gym->id)
            ->assertSuccessful()
            ->assertSee($atRiskMember->name)
            ->assertSee((string) $atRiskMember->email)
            ->assertSee('Risk Score:')
            ->assertSee('View');

        $component->assertSeeHtml("gym-members/{$atRiskMember->id}/edit");
    }

    public function test_blade_view_renders_empty_state_when_no_high_risk_members(): void
    {
        Cache::forget(ReportCacheService::CHURN_STATS . $this->gym->id);

        Livewire::test(MemberReports::class)
            ->set('manualGymId', $this->gym->id)
            ->assertSuccessful()
            ->assertSee('No high-risk members detected at this time.');
    }

    public function test_member_resource_edit_url_properly_routes_to_gym_member_edit_page(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Jane Member',
            'email' => 'jane@example.com',
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
        ]);

        $editUrl = $member->memberResourceUrl($member, 'edit');

        $response = $this->get($editUrl);

        $response->assertSuccessful();
        $response->assertSee('Jane Member');
    }

    public function test_gym_member_edit_page_component_renders_record(): void
    {
        $member = GymMember::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Jane Component Member',
            'email' => 'janecomponent@example.com',
            'membership_start' => now()->subMonth(),
            'membership_end' => now()->addMonth(),
        ]);

        /** @var Testable $testable */
        $testable = Livewire::test(EditGymMember::class, ['record' => $member->id]);
        $testable->assertSuccessful();
        $testable->assertFormSet([
            'name' => 'Jane Component Member',
            'email' => 'janecomponent@example.com',
        ]);
    }
}
