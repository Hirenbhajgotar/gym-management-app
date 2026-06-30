<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\User;
use App\Policies\LeadPolicy;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\Filament\StaffPanel\StaffPanelTestCase;

class LeadPolicyTest extends StaffPanelTestCase
{
    #[Test]
    public function view_any_returns_false_when_user_lacks_view_any_lead_permission(): void
    {
        $this->assertFalse($this->managerUser->fresh()->can('view_any_lead'));

        $this->assertFalse(
            (new LeadPolicy())->viewAny($this->managerUser),
            'viewAny should return false when view_any_lead permission is missing',
        );
    }

    #[Test]
    public function view_any_returns_true_when_user_has_view_any_lead_permission(): void
    {
        setPermissionsTeamId($this->testGym->id);

        $this->managerUser->givePermissionTo(
            Permission::query()->firstOrCreate(['name' => 'view_any_lead', 'guard_name' => 'web']),
        );

        $this->assertTrue(
            (new LeadPolicy())->viewAny($this->managerUser->fresh()),
            'viewAny should return true when view_any_lead permission is granted',
        );
    }

    #[Test]
    public function view_any_does_not_short_circuit_on_role_when_permission_is_missing(): void
    {
        $bareUser = User::factory()->create();

        setPermissionsTeamId($this->testGym->id);
        $bareUser->assignRole(
            Role::query()->firstOrCreate([
                'name' => 'manager',
                'guard_name' => 'web',
                'gym_id' => $this->testGym->id,
            ]),
        );

        $this->assertFalse(
            (new LeadPolicy())->viewAny($bareUser),
            'viewAny must be permission-based, not role-based',
        );
    }
}
