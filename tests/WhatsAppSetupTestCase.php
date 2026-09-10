<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\DefaultRole;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base test case for the WhatsAppSetup suite.
 *
 * Uses `RefreshDatabase` to ensure a clean, fully-migrated test database
 * for every test. The same production-database safety guard from
 * `Tests\TestCase` is kept: if the resolved connection ever points at
 * `gym_app`, the test aborts.
 */
abstract class WhatsAppSetupTestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Mirror the safety guard in Tests\TestCase so we never target gym_app.
        putenv('DB_DATABASE=gym_app_test');
        $_ENV['DB_DATABASE'] = 'gym_app_test';
        $_SERVER['DB_DATABASE'] = 'gym_app_test';

        parent::setUp();

        $database = $this->app->make('config')->get('database.connections.mysql.database');

        if ($database === 'gym_app') {
            $this->fail('SAFETY: Tests are targeting the production database "gym_app". Aborting to prevent data loss.');
        }
    }

    /**
     * Build a gym-owner user + tenant and authenticate as them.
     *
     * Roles are team-scoped (permission.teams=true): each gym owns its own
     * role rows, so the GYM_OWNER role must be created for the test gym and
     * the permissions team id set before assigning/querying roles. Without
     * this, spatie's Role::findByName() applies a gym_id scope and throws
     * "There is no role named gym_owner".
     */
    protected function actingAsGymOwner(): User
    {
        $gym = Gym::factory()->create(['name' => 'FitZone Gym']);

        resolve(PermissionRegistrar::class)->setPermissionsTeamId($gym->id);

        Role::query()->firstOrCreate([
            'name' => DefaultRole::GYM_OWNER->value,
            'guard_name' => 'web',
            'gym_id' => $gym->id,
        ]);

        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $user->gyms()->attach($gym);
        $user->assignRole(DefaultRole::GYM_OWNER->value);

        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($gym);

        return $user;
    }

    /**
     * Seed session state for embedded signup tests.
     */
    protected function seedEmbeddedSignupSession(Gym $gym): void
    {
        $this->session([
            'embedded_signup_tenant_id' => $gym->id,
            'embedded_signup_tenant_slug' => $gym->slug,
        ]);
    }
}
