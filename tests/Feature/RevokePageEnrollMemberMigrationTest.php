<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DefaultRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RevokePageEnrollMemberMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoke_page_enroll_member_migration_up_revokes_and_deletes_permission(): void
    {
        $trainerRole = Role::findOrCreate(DefaultRole::TRAINER->value, 'web');
        $receptionistRole = Role::findOrCreate(DefaultRole::RECEPTIONIST->value, 'web');
        $permission = Permission::findOrCreate('page_enroll_member', 'web');

        $trainerRole->givePermissionTo($permission);
        $receptionistRole->givePermissionTo($permission);

        $this->assertTrue($trainerRole->hasPermissionTo('page_enroll_member', 'web'));
        $this->assertTrue($receptionistRole->hasPermissionTo('page_enroll_member', 'web'));

        $migration = require database_path('migrations/2026_10_01_000000_revoke_page_enroll_member_from_trainer_role.php');

        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, 'up'), 'The migration must expose up().');

        call_user_func([$migration, 'up']);

        $this->assertFalse(Permission::query()->where('name', 'page_enroll_member')->where('guard_name', 'web')->exists());
    }
}
