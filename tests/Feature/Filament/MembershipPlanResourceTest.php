<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\Currency;
use App\Filament\Admin\Resources\MembershipPlanResource\Pages\ListMembershipPlans;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\Permission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipPlanResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * BaseResource::canAccess() requires the "view_any_{table}" permission
     * (or SUPER_ADMIN). Grant it so the table can be rendered.
     */
    private function grantMembershipPlanAccess(User $user): void
    {
        foreach (['view_any_membership_plan', 'view_membership_plan'] as $name) {
            $permission = Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);

            $user->givePermissionTo($permission);
        }
    }

    public function test_table_renders_price_with_enum_currency_without_type_error(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach($gym);
        $this->grantMembershipPlanAccess($user);

        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'price' => 1999.00,
            'currency' => Currency::USD,
        ]);

        $this->actingAs($user);
        Filament::setTenant($gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListMembershipPlans::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$plan]);
    }

    public function test_table_renders_with_null_currency_falls_back_to_inr(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach($gym);
        $this->grantMembershipPlanAccess($user);

        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'price' => 500.00,
            'currency' => null,
        ]);

        $this->actingAs($user);
        Filament::setTenant($gym);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListMembershipPlans::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$plan]);
    }
}
