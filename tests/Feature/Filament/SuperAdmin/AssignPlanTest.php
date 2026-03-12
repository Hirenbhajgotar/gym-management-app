<?php

namespace Tests\Feature\Filament\SuperAdmin;

use App\Enums\SubscriptionStatus;
use App\Filament\SuperAdmin\Resources\UserResource\Pages\ListUsers;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssignPlanTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $gymOwner;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $superAdminRole = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $gymOwnerRole = Role::create(['name' => 'gym_owner', 'guard_name' => 'web']);

        // Create users
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($superAdminRole);

        $this->gymOwner = User::factory()->create();
        $this->gymOwner->assignRole($gymOwnerRole);

        // Create a plan with features
        $this->plan = Plan::factory()->create([
            'price_monthly' => 100,
            'price_yearly' => 1000,
        ]);

        PlanFeature::factory()->create([
            'plan_id' => $this->plan->id,
            'feature_name' => 'max_gyms',
            'value' => '1',
        ]);

        // Authenticate as super admin
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel('super-admin');
    }

    public function test_super_admin_can_assign_plan_to_gym_owner(): void
    {
        Livewire::test(ListUsers::class)
            ->callTableAction('assign_plan', $this->gymOwner, data: [
                'plan_id' => $this->plan->id,
                'billing_cycle' => 'monthly',
                'status' => SubscriptionStatus::ACTIVE->value,
                'trial_ends_at' => null,
                'notes' => 'Manual assignment for testing.',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Plan assigned successfully');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->gymOwner->id,
            'plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::ACTIVE->value,
            'billing_cycle' => 'monthly',
            'price' => $this->plan->price_monthly,
        ]);

        $subscription = Subscription::where('user_id', $this->gymOwner->id)->first();
        $this->assertNotNull($subscription);

        $this->assertDatabaseHas('subscription_features', [
            'subscription_id' => $subscription->id,
            'feature_name' => 'max_gyms',
            'value' => '1',
        ]);
    }

    public function test_assigning_new_plan_cancels_old_active_plan(): void
    {
        // First, assign an initial plan
        $initialSubscription = $this->gymOwner->assignPlan($this->plan->id, 'monthly');

        $this->assertEquals(SubscriptionStatus::ACTIVE, $initialSubscription->status);

        // Now, assign a new plan
        $newPlan = Plan::factory()->create();

        Livewire::test(ListUsers::class)
            ->callTableAction('assign_plan', $this->gymOwner, data: [
                'plan_id' => $newPlan->id,
                'billing_cycle' => 'yearly',
                'status' => SubscriptionStatus::ACTIVE->value,
            ]);

        // Assert the old subscription is now cancelled
        $this->assertEquals(SubscriptionStatus::CANCELLED, $initialSubscription->fresh()->status);

        // Assert the new subscription is active
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->gymOwner->id,
            'plan_id' => $newPlan->id,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);
    }

    public function test_super_admin_can_bulk_assign_plan(): void
    {
        $gymOwner2 = User::factory()->create();
        $gymOwner2->assignRole('gym_owner');

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$this->gymOwner, $gymOwner2])
            ->callTableBulkAction('bulk_assign_plan', [$this->gymOwner, $gymOwner2], data: [
                'plan_id' => $this->plan->id,
                'billing_cycle' => 'yearly',
                'status' => SubscriptionStatus::ACTIVE->value,
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Plans assigned successfully');

        // Assert for first gym owner
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->gymOwner->id,
            'plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::ACTIVE->value,
            'billing_cycle' => 'yearly',
            'price' => $this->plan->price_yearly,
        ]);

        // Assert for second gym owner
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $gymOwner2->id,
            'plan_id' => $this->plan->id,
            'status' => SubscriptionStatus::ACTIVE->value,
            'billing_cycle' => 'yearly',
            'price' => $this->plan->price_yearly,
        ]);
    }
}
