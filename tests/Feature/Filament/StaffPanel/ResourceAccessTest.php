<?php

namespace Tests\Feature\Filament\StaffPanel;

use App\Policies\BaseEmployeePolicy;
use App\Policies\BasePolicy;
use App\Policies\BiometricDevicePolicy;
use App\Policies\GymMemberAttendancePolicy;
use App\Policies\ReportGoalPolicy;
use App\Policies\ScheduledReportPolicy;
use App\Policies\TenantSocialConfigPolicy;
use App\Policies\TrainerAttendancePolicy;
use App\Policies\TrainerPolicy;
use App\Policies\WhatsAppTemplatePolicy;
use App\Policies\WhatsAppTransactionPolicy;
use App\Policies\WhatsAppUsageLogPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Spatie\Permission\PermissionRegistrar;

class ResourceAccessTest extends StaffPanelTestCase
{
    #[Test]
    public function manager_with_view_any_gym_member_can_access_staff_gym_members_index(): void
    {
        $this->grantPermission($this->managerUser, 'view_any_gym_member');

        $this->actAsManager();

        $this->get($this->getStaffUrl('gym-members'))
            ->assertSuccessful();
    }

    #[Test]
    public function staff_panel_does_not_show_permissions_cluster_in_nav(): void
    {
        $this->actAsManager()
            ->get($this->getStaffUrl(''))
            ->assertSuccessful()
            ->assertDontSee('Manager Permissions')
            ->assertDontSee('Receptionist Permissions');
    }

    #[Test]
    public function staff_panel_hides_resources_without_permission(): void
    {
        $this->grantPermission($this->managerUser, 'view_any_gym_member');

        $this->actAsManager()
            ->get($this->getStaffUrl(''))
            ->assertSuccessful()
            ->assertSee('Gym Members')
            ->assertDontSee('Leads');
    }

    #[Test]
    public function admin_owner_sees_all_resources_in_nav(): void
    {
        $this->grantPermission($this->adminUser, 'view_any_gym_member');
        $this->grantPermission($this->adminUser, 'view_any_lead');
        $this->grantPermission($this->adminUser, 'view_any_trainer');

        $this->actAsAdmin()
            ->get($this->getStaffUrl(''))
            ->assertSuccessful()
            ->assertSee('Gym Members')
            ->assertSee('Leads')
            ->assertSee('Trainers');
    }

    #[Test]
    public function receptionist_with_view_any_biometric_device_can_access_biometric_devices(): void
    {
        $this->grantPermission($this->receptionistUser, 'view_any_biometric_device');

        $this->actAsReceptionist()
            ->get($this->getStaffUrl('biometric-devices'))
            ->assertSuccessful();
    }

    #[Test]
    #[DataProvider('newPolicyProvider')]
    public function new_policy_resources_use_basepolicy(string $policyClass): void
    {
        $tableName = (new ReflectionClass($policyClass))->getStaticPropertyValue('tableName');
        $expectedPermission = 'view_any_' . $tableName;

        $policy = new $policyClass;
        $user = $this->managerUser;

        $this->assertInstanceOf(BasePolicy::class, $policy);
        $this->assertFalse(
            $policy->viewAny($user),
            "{$policyClass}::viewAny should deny without {$expectedPermission}",
        );

        $this->grantPermission($user, $expectedPermission);

        $freshUser = $user->fresh();
        setPermissionsTeamId($this->testGym->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $freshUser->forgetCachedPermissions();
        $freshUser->unsetRelation('permissions');
        $freshUser->unsetRelation('roles');
        $freshUser->load(['permissions', 'roles.permissions']);

        $this->assertTrue(
            $policy->viewAny($freshUser),
            "{$policyClass}::viewAny should allow with {$expectedPermission}",
        );
    }

    /**
     * @return array<string, array{0: class-string<BasePolicy>}>
     */
    public static function newPolicyProvider(): array
    {
        return [
            'BiometricDevice' => [BiometricDevicePolicy::class],
            'Trainer' => [TrainerPolicy::class],
            'WhatsAppTemplate' => [WhatsAppTemplatePolicy::class],
            'WhatsAppTransaction' => [WhatsAppTransactionPolicy::class],
            'WhatsAppUsageLog' => [WhatsAppUsageLogPolicy::class],
            'ReportGoal' => [ReportGoalPolicy::class],
            // 'BaseEmployee' => [BaseEmployeePolicy::class],
            'TenantSocialConfig' => [TenantSocialConfigPolicy::class],
            'GymMemberAttendance' => [GymMemberAttendancePolicy::class],
            'TrainerAttendance' => [TrainerAttendancePolicy::class],
            'ScheduledReport' => [ScheduledReportPolicy::class],
        ];
    }
}
