<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\User;
use App\Policies\GymMemberPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GymMemberPolicyEnrollBiometricTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gymA;

    protected Gym $gymB;

    protected GymMember $memberA;

    protected GymMember $memberB;

    protected GymMemberPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gymA = Gym::factory()->create();
        $this->gymB = Gym::factory()->create();

        $this->memberA = GymMember::factory()->create(['gym_id' => $this->gymA->id]);
        $this->memberB = GymMember::factory()->create(['gym_id' => $this->gymB->id]);

        $this->policy = new GymMemberPolicy();

        setPermissionsTeamId($this->gymA->id);
    }

    #[Test]
    public function super_admin_can_enroll_biometrics_across_all_tenants(): void
    {
        $superAdminRole = Role::findOrCreate(DefaultRole::SUPER_ADMIN->value, 'web');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole($superAdminRole);

        $this->assertTrue($this->policy->enrollBiometric($superAdmin, $this->memberA));
        $this->assertTrue($this->policy->enrollBiometric($superAdmin, $this->memberB));
    }

    #[Test]
    public function gym_owner_can_enroll_for_own_gym_member_but_not_other_gym(): void
    {
        $ownerRole = Role::findOrCreate(DefaultRole::GYM_OWNER->value, 'web');
        $owner = User::factory()->create();
        $this->gymA->users()->attach($owner->id, ['role' => DefaultRole::GYM_OWNER->value]);
        $owner->assignRole($ownerRole);

        $this->assertTrue($this->policy->enrollBiometric($owner, $this->memberA));
        $this->assertFalse($this->policy->enrollBiometric($owner, $this->memberB));
    }

    #[Test]
    public function admin_can_enroll_for_own_gym_member_but_not_other_gym(): void
    {
        $adminRole = Role::findOrCreate(DefaultRole::ADMIN->value, 'web');
        $admin = User::factory()->create();
        $this->gymA->users()->attach($admin->id, ['role' => DefaultRole::ADMIN->value]);
        $admin->assignRole($adminRole);

        $this->assertTrue($this->policy->enrollBiometric($admin, $this->memberA));
        $this->assertFalse($this->policy->enrollBiometric($admin, $this->memberB));
    }

    #[Test]
    public function trainer_cannot_enroll_biometrics_even_for_own_gym_member(): void
    {
        $trainerRole = Role::findOrCreate(DefaultRole::TRAINER->value, 'web');
        $trainer = User::factory()->create();
        $this->gymA->users()->attach($trainer->id, ['role' => DefaultRole::TRAINER->value]);
        $trainer->assignRole($trainerRole);

        $this->assertFalse($this->policy->enrollBiometric($trainer, $this->memberA));
    }
}
