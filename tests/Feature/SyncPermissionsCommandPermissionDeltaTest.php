<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SyncPermissionsCommandPermissionDeltaTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConsoleOutput = false;
        $this->gym = Gym::factory()->create();
        setPermissionsTeamId($this->gym->id);
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    public function sync_without_flags_does_not_grant_destructive_member_permissions_to_trainer(): void
    {
        /** @var Role $trainerRole */
        $trainerRole = Role::findOrCreate(DefaultRole::TRAINER->value, 'web');
        $trainerRole->gym_id = $this->gym->id;
        $trainerRole->save();

        Permission::findOrCreate('force_delete_gym_member', 'web');
        Permission::findOrCreate('delete_gym_member', 'web');
        Permission::findOrCreate('restore_gym_member', 'web');

        Artisan::call('permissions:sync', ['--gym' => $this->gym->id]);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(
            $trainerRole->permissions()->where('name', 'force_delete_gym_member')->exists(),
            'Sync must not grant force_delete_gym_member to trainer'
        );
        $this->assertFalse(
            $trainerRole->permissions()->where('name', 'delete_gym_member')->exists(),
            'Sync must not grant delete_gym_member to trainer'
        );
        $this->assertFalse(
            $trainerRole->permissions()->where('name', 'restore_gym_member')->exists(),
            'Sync must not grant restore_gym_member to trainer'
        );
    }

    #[Test]
    public function sync_without_flags_does_not_grant_unauthorized_permissions_to_receptionist(): void
    {
        /** @var Role $receptionistRole */
        $receptionistRole = Role::findOrCreate(DefaultRole::RECEPTIONIST->value, 'web');
        $receptionistRole->gym_id = $this->gym->id;
        $receptionistRole->save();

        Permission::findOrCreate('restore_gym_member', 'web');
        Permission::findOrCreate('update_receptionist', 'web');

        Artisan::call('permissions:sync', ['--gym' => $this->gym->id]);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(
            $receptionistRole->permissions()->where('name', 'restore_gym_member')->exists(),
            'Sync must not grant restore_gym_member to receptionist'
        );
        $this->assertFalse(
            $receptionistRole->permissions()->where('name', 'update_receptionist')->exists(),
            'Sync must not grant update_receptionist to receptionist'
        );
    }
}
