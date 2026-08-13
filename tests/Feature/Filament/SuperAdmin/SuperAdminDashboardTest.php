<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\SuperAdmin;

use App\Enums\DefaultRole;
use App\Filament\SuperAdmin\Pages\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate([
            'name' => DefaultRole::SUPER_ADMIN->value,
            'guard_name' => 'web',
        ]);
    }

    #[Test]
    public function super_admin_can_access_dashboard_and_see_log_viewer_action(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(DefaultRole::SUPER_ADMIN->value);

        $this->actingAs($superAdmin);

        Livewire::test(Dashboard::class)
            ->assertActionExists('logViewer')
            ->assertActionHasUrl('logViewer', url(config()->string('log-viewer.route_path', 'log-viewer')));
    }

    #[Test]
    public function super_admin_can_access_dashboard_and_see_vantage_queue_action(): void
    {
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(DefaultRole::SUPER_ADMIN->value);

        $this->actingAs($superAdmin);

        Livewire::test(Dashboard::class)
            ->assertActionExists('vantageQueue')
            ->assertActionHasUrl('vantageQueue', url(config()->string('vantage.route_prefix', 'vantage')));
    }
}
