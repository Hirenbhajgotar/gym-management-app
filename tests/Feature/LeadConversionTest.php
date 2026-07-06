<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\LeadConversionService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_can_be_converted_to_member_with_notes_and_payment(): void
    {
        // 1. Setup
        $gym = Gym::factory()->create();
        $user = User::factory()->create();
        $user->gyms()->attach($gym);
        $this->actingAs($user);
        Filament::setTenant($gym);

        $lead = Lead::factory()->create(['gym_id' => $gym->id]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);

        $extraData = [
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'trainer_id' => null,
            'membership_start' => now()->toDateString(),
            'notes' => 'Payment notes for the receipt.',
            'conversion_notes' => 'Member seemed interested in personal training later.',
            'amount' => 1000,
            'currency' => 'INR',
            'payment_method' => 'cash',
            'status' => PaymentStatus::COMPLETED->value,
            'payment_date' => now()->toDateString(),
            'conversion_date' => now()->toDateString(),
        ];

        // 2. Act
        $service = resolve(LeadConversionService::class);
        $member = $service->convert($lead, $extraData);

        // 3. Assert
        $this->assertDatabaseHas('gym_members', [
            'id' => $member->id,
            'gym_id' => $gym->id,
            'email' => $lead->email,
        ]);

        $this->assertDatabaseHas('lead_conversions', [
            'lead_id' => $lead->id,
            'gym_member_id' => $member->id,
            'notes' => 'Member seemed interested in personal training later.',
        ]);

        $this->assertDatabaseHas('member_payments', [
            'member_id' => $member->id,
            'amount' => 1000,
            'notes' => 'Payment notes for the receipt.',
        ]);

        $lead->refresh();
        $this->assertEquals('converted', $lead->status->value);
    }
}
