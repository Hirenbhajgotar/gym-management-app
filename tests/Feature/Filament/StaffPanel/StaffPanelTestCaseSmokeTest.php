<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\StaffPanel;

use PHPUnit\Framework\Attributes\Test;

/**
 * Smoke tests for StaffPanelTestCase.
 *
 * Verifies the shared test infrastructure works (gym + 3 users + role
 * assignment + panel switching) so subsequent ResourceAccessTest and
 * CrossTenantTest cases can rely on the base class.
 */
class StaffPanelTestCaseSmokeTest extends StaffPanelTestCase
{
    #[Test]
    public function set_up_creates_one_gym_and_three_users(): void
    {
        $this->assertDatabaseHas('gyms', [
            'id' => $this->testGym->id,
            'slug' => 'test-gym',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'admin@test-gym.com']);
        $this->assertDatabaseHas('users', ['email' => 'manager@test-gym.com']);
        $this->assertDatabaseHas('users', ['email' => 'receptionist@test-gym.com']);

        $this->assertSame('Test Gym', $this->testGym->name);
        $this->assertSame('test-gym', $this->testGym->slug);
    }

    #[Test]
    public function each_user_has_their_expected_gym_scoped_role(): void
    {
        $this->assertTrue($this->adminUser->fresh()->hasRole('gym_owner'));
        $this->assertTrue($this->managerUser->fresh()->hasRole('manager'));
        $this->assertTrue($this->receptionistUser->fresh()->hasRole('receptionist'));
    }

    #[Test]
    public function grant_permission_helper_assigns_permission_to_user(): void
    {
        $this->grantPermission($this->managerUser, 'view_any_gym_member');

        // The user is loaded with its roles/permissions; re-resolve the
        // model to confirm the pivot row was written and the team-scoped
        // permission lookup returns true.
        $fresh = $this->managerUser->fresh();

        $this->assertTrue(
            $fresh->hasPermissionTo('view_any_gym_member'),
            'manager should have view_any_gym_member after grantPermission()',
        );
    }
}
