<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

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
use Spatie\Permission\Models\Permission;
use Tests\Feature\Filament\StaffPanel\StaffPanelTestCase;

class BasePolicyIntegrationTest extends StaffPanelTestCase
{
    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function policyPermissionMap(): array
    {
        return [
            'BiometricDevicePolicy' => [BiometricDevicePolicy::class, 'view_any_biometric_device'],
            'TrainerPolicy' => [TrainerPolicy::class, 'view_any_trainer'],
            'WhatsAppTemplatePolicy' => [WhatsAppTemplatePolicy::class, 'view_any_whatsapp_template'],
            'WhatsAppTransactionPolicy' => [WhatsAppTransactionPolicy::class, 'view_any_whatsapp_transaction'],
            'WhatsAppUsageLogPolicy' => [WhatsAppUsageLogPolicy::class, 'view_any_whatsapp_usage_log'],
            'ReportGoalPolicy' => [ReportGoalPolicy::class, 'view_any_report_goal'],
            // 'BaseEmployeePolicy' => [BaseEmployeePolicy::class, 'view_any_base_employee'],
            'TenantSocialConfigPolicy' => [TenantSocialConfigPolicy::class, 'view_any_tenant_social_config'],
            'GymMemberAttendancePolicy' => [GymMemberAttendancePolicy::class, 'view_any_gym_member_attendance'],
            'TrainerAttendancePolicy' => [TrainerAttendancePolicy::class, 'view_any_trainer_attendance'],
            'ScheduledReportPolicy' => [ScheduledReportPolicy::class, 'view_any_scheduled_report'],
        ];
    }

    #[Test]
    #[DataProvider('policyPermissionMap')]
    public function view_any_returns_false_without_permission(string $policyClass, string $permissionSlug): void
    {
        setPermissionsTeamId($this->testGym->id);
        $this->managerUser->revokePermissionTo(
            Permission::query()->firstOrCreate(['name' => $permissionSlug, 'guard_name' => 'web']),
        );

        $this->assertFalse(
            (new $policyClass())->viewAny($this->managerUser),
            "{$policyClass}::viewAny should return false without '{$permissionSlug}'",
        );
    }

    #[Test]
    #[DataProvider('policyPermissionMap')]
    public function view_any_returns_true_with_permission(string $policyClass, string $permissionSlug): void
    {
        setPermissionsTeamId($this->testGym->id);
        $this->managerUser->givePermissionTo(
            Permission::query()->firstOrCreate(['name' => $permissionSlug, 'guard_name' => 'web']),
        );

        $this->assertTrue(
            (new $policyClass())->viewAny($this->managerUser->fresh()),
            "{$policyClass}::viewAny should return true with '{$permissionSlug}'",
        );
    }
}
