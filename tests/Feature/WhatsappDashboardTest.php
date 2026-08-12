<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\WhatsAppAccount\UpdateBillingModeAction;
use App\Enums\DefaultRole;
use App\Enums\WhatsAppBillingModeEnum;
use App\Enums\WhatsAppProviderTypeEnum;
use App\Filament\Admin\Pages\WhatsApp\WhatsappDashboard;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Exception;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Expectation;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\WhatsAppSetupTestCase;
use Throwable;

/**
 * WhatsappDashboard tests: the change-billing-mode header action and the
 * super_admin / admin / gym_owner role gate on canAccess().
 */
class WhatsappDashboardTest extends WhatsAppSetupTestCase
{
    use MockeryPHPUnitIntegration;

    protected User $user;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsGymOwner();
        $tenant = Filament::getTenant();
        assert($tenant instanceof Gym);
        $this->gym = $tenant;
    }

    protected function tearDown(): void
    {
        try {
            Filament::setTenant(null);
        } catch (Throwable $_) {
            // ignore
        }

        parent::tearDown();
    }

    #[Test]
    public function change_billing_mode_action_updates_the_account(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'provider_type' => WhatsAppProviderTypeEnum::WABA,
            'billing_mode' => WhatsAppBillingModeEnum::PAY_VIA_APP,
        ]);

        /** @var Testable $page */
        $page = Livewire::actingAs($this->user)
            ->test(WhatsappDashboard::class);

        $page->callAction('changeBillingMode', data: [
            'newBillingMode' => WhatsAppBillingModeEnum::SELF_MANAGED->value,
        ])->assertNotified('Billing mode updated successfully');

        $this->assertDatabaseHas('whatsapp_accounts', [
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::SELF_MANAGED->value,
        ]);
    }

    #[Test]
    public function change_billing_mode_action_notifies_when_update_fails(): void
    {
        WhatsAppAccount::query()->create([
            'gym_id' => $this->gym->id,
            'billing_mode' => WhatsAppBillingModeEnum::PAY_VIA_APP,
        ]);

        $mock = Mockery::mock(UpdateBillingModeAction::class);

        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('__invoke');
        $expectation->once();
        $expectation->andThrow(new Exception('Simulated failure'));

        $this->app->instance(UpdateBillingModeAction::class, $mock);

        /** @var Testable $page */
        $page = Livewire::actingAs($this->user)
            ->test(WhatsappDashboard::class);

        $page->callAction('changeBillingMode', data: [
            'newBillingMode' => WhatsAppBillingModeEnum::SELF_MANAGED->value,
        ])->assertNotified('Failed to update billing mode');
    }

    #[Test]
    public function super_admin_can_access_the_dashboard(): void
    {
        $user = $this->createUserWithRole(DefaultRole::SUPER_ADMIN->value, tenantScoped: false);

        $this->actingAs($user);

        $this->assertTrue(WhatsappDashboard::canAccess());
    }

    #[Test]
    public function admin_can_access_the_dashboard(): void
    {
        $user = $this->createUserWithRole(DefaultRole::ADMIN->value, tenantScoped: false);

        $this->actingAs($user);

        $this->assertTrue(WhatsappDashboard::canAccess());
    }

    #[Test]
    public function gym_owner_can_access_the_dashboard(): void
    {
        $user = $this->createUserWithRole(DefaultRole::GYM_OWNER->value, tenantScoped: true);

        $this->actingAs($user);

        $this->assertTrue(WhatsappDashboard::canAccess());
    }

    #[Test]
    public function non_privileged_roles_cannot_access_the_dashboard(): void
    {
        foreach (DefaultRole::tenantRoles() as $role) {
            if ($role === DefaultRole::GYM_OWNER) {
                continue;
            }

            $user = $this->createUserWithRole($role->value, tenantScoped: true);

            $this->actingAs($user);

            $this->assertFalse(WhatsappDashboard::canAccess(), "Role {$role->value} must not access the dashboard.");
        }
    }

    #[Test]
    public function unauthenticated_users_cannot_access_the_dashboard(): void
    {
        auth()->logout();

        $this->assertFalse(WhatsappDashboard::canAccess());
    }

    private function createUserWithRole(string $roleName, bool $tenantScoped): User
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = resolve(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenantScoped ? $this->gym->id : null);

        Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            ...($tenantScoped ? ['gym_id' => $this->gym->id] : []),
        ]);

        $user = User::factory()->create();

        if ($tenantScoped) {
            $user->gyms()->attach($this->gym);
        }

        $user->assignRole($roleName);

        return $user;
    }
}
