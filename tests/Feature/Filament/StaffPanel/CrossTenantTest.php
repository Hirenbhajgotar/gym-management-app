<?php

namespace Tests\Feature\Filament\StaffPanel;

use App\Models\Gym;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;

class CrossTenantTest extends StaffPanelTestCase
{
    protected Gym $testGymB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testGymB = Gym::factory()->create([
            'name' => 'Test Gym B',
            'slug' => 'test-gym-b',
        ]);
    }

    #[Test]
    public function manager_in_gym_a_with_permission_sees_resource(): void
    {
        $this->grantPermission($this->managerUser, 'view_any_gym_member');

        $this->actAsManager()
            ->switchToGym($this->testGym)
            ->get($this->buildStaffUrl($this->testGym, 'gym-members'))
            ->assertSuccessful();
    }

    #[Test]
    public function same_manager_in_gym_b_without_permission_is_denied(): void
    {
        $this->grantPermission($this->managerUser, 'view_any_gym_member');

        $this->actAsManager()
            ->switchToGym($this->testGymB)
            ->get($this->buildStaffUrl($this->testGymB, 'gym-members'))
            ->assertNotFound();
    }

    #[Test]
    public function switching_gym_id_reloads_permissions(): void
    {
        $this->grantPermission($this->managerUser, 'view_any_gym_member');

        $this->actAsManager();

        $this->switchToGym($this->testGym)
            ->get($this->buildStaffUrl($this->testGym, 'gym-members'))
            ->assertSuccessful();

        $this->switchToGym($this->testGymB)
            ->get($this->buildStaffUrl($this->testGymB, 'gym-members'))
            ->assertNotFound();

        $this->switchToGym($this->testGym)
            ->get($this->buildStaffUrl($this->testGym, 'gym-members'))
            ->assertSuccessful();
    }

    #[Test]
    public function permission_cache_is_cleared_on_tenant_change(): void
    {
        $this->grantPermission($this->managerUser, 'view_any_gym_member');

        $this->actAsManager();

        $this->switchToGym($this->testGym);
        $this->assertTrue(
            $this->managerUser->fresh()->can('view_any_gym_member'),
            'manager should have view_any_gym_member in gym A context',
        );

        $this->switchToGym($this->testGymB);
        $this->assertFalse(
            $this->managerUser->fresh()->can('view_any_gym_member'),
            'manager should NOT have view_any_gym_member in gym B context (different team)',
        );

        $this->switchToGym($this->testGym);
        $this->assertTrue(
            $this->managerUser->fresh()->can('view_any_gym_member'),
            'permission should re-appear when switching back to gym A',
        );
    }

    #[Test]
    public function role_check_uses_correct_team_context(): void
    {
        $this->actAsManager();

        $this->switchToGym($this->testGym);
        $this->assertTrue(
            $this->managerUser->fresh()->hasRole('manager'),
            'manager should have manager role in gym A',
        );

        $this->switchToGym($this->testGymB);
        $this->assertFalse(
            $this->managerUser->fresh()->hasRole('manager'),
            'manager should NOT have manager role in gym B',
        );
    }

    protected function switchToGym(Gym $gym): self
    {
        setPermissionsTeamId($gym->id);
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->managerUser->forgetCachedPermissions();
        $this->managerUser->load('roles');

        return $this;
    }

    protected function buildStaffUrl(Gym $gym, string $resource = ''): string
    {
        $base = '/staff/' . $gym->slug;

        return $resource === '' ? $base : $base . '/' . ltrim($resource, '/');
    }
}
