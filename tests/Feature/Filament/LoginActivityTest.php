<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\SuperAdmin\Pages\LoginActivity;
use App\Models\Gym;
use App\Models\User;
use App\Models\UserLoginLog;
use App\Services\LoginActivityService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginActivityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $gymOwner;

    protected User $trainer;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $superAdminRole = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $gymOwnerRole = Role::create(['name' => 'gym_owner', 'guard_name' => 'web']);
        $trainerRole = Role::create(['name' => 'trainer', 'guard_name' => 'web']);

        // Create super admin
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($superAdminRole);

        // Create gym owner
        $this->gymOwner = User::factory()->create();
        $this->gymOwner->assignRole($gymOwnerRole);

        // Create trainer
        $this->trainer = User::factory()->create();
        $this->trainer->assignRole($trainerRole);

        // Create gym
        $this->gym = Gym::factory()->create();

        // Associate users with gym
        $this->gym->users()->attach($this->gymOwner->id, ['role' => 'gym_owner', 'active' => true]);
        $this->gym->users()->attach($this->trainer->id, ['role' => 'trainer', 'active' => true]);

        // Authenticate as super admin
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel('super-admin');
    }

    public function test_page_loads_successfully(): void
    {
        Livewire::test(LoginActivity::class)
            ->assertStatus(200);
    }

    public function test_page_shows_login_logs(): void
    {
        // Create some login logs
        UserLoginLog::query()->create([
            'user_id' => $this->gymOwner->id,
            'gym_id' => $this->gym->id,
            'login_at' => now(),
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'device_type' => 'desktop',
            'login_method' => 'password',
        ]);

        UserLoginLog::query()->create([
            'user_id' => $this->trainer->id,
            'gym_id' => $this->gym->id,
            'login_at' => now()->subDay(),
            'ip_address' => '192.168.1.2',
            'user_agent' => 'Mozilla/5.0 (iPhone)',
            'device_type' => 'mobile',
            'login_method' => 'password',
        ]);

        Livewire::test(LoginActivity::class)
            ->assertStatus(200)
            ->assertSee('192.168.1.1')
            ->assertSee('192.168.1.2');
    }

    public function test_filter_by_role_works(): void
    {
        // Create login logs
        UserLoginLog::query()->create([
            'user_id' => $this->gymOwner->id,
            'gym_id' => $this->gym->id,
            'login_at' => now(),
            'ip_address' => '192.168.1.1',
            'device_type' => 'desktop',
            'login_method' => 'password',
        ]);

        UserLoginLog::query()->create([
            'user_id' => $this->trainer->id,
            'gym_id' => $this->gym->id,
            'login_at' => now(),
            'ip_address' => '192.168.1.2',
            'device_type' => 'mobile',
            'login_method' => 'password',
        ]);

        Livewire::test(LoginActivity::class)
            ->set('tableFilters', ['role' => 'gym_owner'])
            ->assertStatus(200)
            ->assertSee('192.168.1.1')
            ->assertDontSee('192.168.1.2');
    }

    public function test_filter_by_gym_works(): void
    {
        // Create another gym
        $gym2 = Gym::factory()->create();
        $gym2->users()->attach($this->gymOwner->id, ['role' => 'gym_owner', 'active' => true]);

        // Create login logs for both gyms
        UserLoginLog::query()->create([
            'user_id' => $this->gymOwner->id,
            'gym_id' => $this->gym->id,
            'login_at' => now(),
            'ip_address' => '192.168.1.1',
            'device_type' => 'desktop',
            'login_method' => 'password',
        ]);

        UserLoginLog::query()->create([
            'user_id' => $this->gymOwner->id,
            'gym_id' => $gym2->id,
            'login_at' => now(),
            'ip_address' => '192.168.1.2',
            'device_type' => 'desktop',
            'login_method' => 'password',
        ]);

        Livewire::test(LoginActivity::class)
            ->set('tableFilters', ['gym_id' => $this->gym->id])
            ->assertStatus(200)
            ->assertSee('192.168.1.1')
            ->assertDontSee('192.168.1.2');
    }

    public function test_non_staff_roles_not_tracked(): void
    {
        // Create a user with non-staff role
        $memberRole = Role::create(['name' => 'gym_member', 'guard_name' => 'web']);
        $member = User::factory()->create();
        $member->assignRole($memberRole);

        $this->gym->users()->attach($member->id, ['role' => 'gym_member', 'active' => true]);

        // Create login log for member
        UserLoginLog::query()->create([
            'user_id' => $member->id,
            'gym_id' => $this->gym->id,
            'login_at' => now(),
            'ip_address' => '192.168.1.1',
            'device_type' => 'desktop',
            'login_method' => 'password',
        ]);

        // Service should exclude non-staff
        $service = resolve(LoginActivityService::class);
        $stats = $service->getAggregateStats();

        // Member logins should not be counted
        $this->assertArrayNotHasKey('gym_member', $stats['by_role'] ?? []);
    }
}
