<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Admin\Resources\GymMemberResource\Pages\CreateGymMember;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateGymMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_create_gym_member_page()
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach($gym);
        $this->actingAs($user);
        Filament::setTenant($gym);

        // This should fail if the form schema is invalid (e.g. TypeError)
        Livewire::test(CreateGymMember::class)
            ->assertOk();
    }

    public function test_can_create_gym_member_with_payment()
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach($gym);
        Filament::setTenant($gym);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id, 'price' => 100]);

        $this->actingAs($user);

        Livewire::test(CreateGymMember::class)
            ->fillForm([
                'name' => 'John Gym Member',
                'email' => 'john@gym.com',
                'phone' => '+15551234567',
                'gym_membership_plan_id' => $plan->id,
                'membership_start' => now()->toDateString(),
                'membership_end' => now()->addMonth()->toDateString(),
                'amount' => '100',
                'currency' => 'INR',
                'payment_method' => 'cash',
                'status' => 'completed',
                'payment_date' => now()->toDateString(),
                // 'notes' => 'Some payment notes', // optional
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('gym_members', [
            'email' => 'john@gym.com',
        ]);

        $this->assertDatabaseHas('payments', [
            'amount' => 100,
            'status' => 'completed',
        ]);
    }
}
