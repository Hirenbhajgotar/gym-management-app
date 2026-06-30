<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Admin\Resources\GymMemberResource\Pages\EditGymMember;
use App\Filament\Admin\Resources\GymMemberResource\RelationManagers\PaymentsRelationManager;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GymMemberPaymentUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_attributes_are_updated_on_payment_creation()
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create(['gym_id' => $gym->id]);
        $member = GymMember::factory()->create(['gym_id' => $gym->id]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id, 'duration' => 1, 'price' => 100]);

        $this->actingAs($user);

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => EditGymMember::class,
        ])
            ->callTableAction('create', null, [
                'membership_plan_id' => $plan->id,
                'amount' => 100,
                'currency' => 'INR',
                'payment_method' => 'cash',
                'status' => 'completed',
                'membership_start' => now()->toDateString(),
                'membership_end' => now()->addMonth()->toDateString(),
                'payment_date' => now()->toDateString(),
                'password' => 'password', // Assuming default factory password
            ])
            ->assertHasNoTableActionErrors();

        $member->refresh();

        $this->assertEquals($plan->id, $member->gym_membership_plan_id);
        $this->assertEquals('completed', $member->payment_status->value);
        $this->assertEquals(now()->toDateString(), $member->membership_start->toDateString());
        $this->assertEquals(now()->addMonth()->toDateString(), $member->membership_end->toDateString());
    }

    public function test_member_attributes_are_updated_on_payment_update()
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->create(['gym_id' => $gym->id]);
        $member = GymMember::factory()->create(['gym_id' => $gym->id]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id, 'duration' => 1, 'price' => 100]);
        $payment = $member->payments()->create([
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'amount' => 100,
            'currency' => 'INR',
            'payment_method' => 'cash',
            'status' => 'pending',
            'membership_start' => now()->subMonth()->toDateString(),
            'membership_end' => now()->toDateString(),
            'payment_date' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($user);

        Livewire::test(PaymentsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => EditGymMember::class,
        ])
            ->callTableAction('edit', $payment, [
                'status' => 'completed',
                'membership_start' => now()->toDateString(),
                'membership_end' => now()->addMonth()->toDateString(),
                'password' => 'password',
            ])
            ->assertHasNoTableActionErrors();

        $member->refresh();

        $this->assertEquals('completed', $member->payment_status->value);
        // Note: The current implementation might not update start/end dates on edit,
        // but the requirement is to update them.
        // So we assert what we WANT to happen.
        $this->assertEquals(now()->toDateString(), $member->membership_start->toDateString());
        $this->assertEquals(now()->addMonth()->toDateString(), $member->membership_end->toDateString());
    }
}
