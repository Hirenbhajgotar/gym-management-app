<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class GymOwnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure gym_owner role exists
        Role::query()->firstOrCreate(['name' => 'gym_owner']);

        // Create 5 Gym Owners using UserFactory
        $owners = User::factory()
            ->count(5)
            ->gymOwner()
            ->create();
        $faker = Faker::create();

        foreach ($owners as $owner) {
            // Create 1-3 Gyms for each owner
            $numGyms = rand(1, 3);
            $gyms = Gym::factory()
                ->count($numGyms)
                ->create(['owner_id' => $owner->id]);

            foreach ($gyms as $gym) {
                // Create 1-3 Membership Plans for each gym
                $numPlans = rand(1, 3);
                $plans = MembershipPlan::factory()
                    ->count($numPlans)
                    ->create(['gym_id' => $gym->id]);

                // Create 1-3 trainers for each gym
                $numTrainers = rand(1, 3);
                Trainer::factory()
                    ->count($numTrainers)
                    ->create(['gym_id' => $gym->id]);

                // Create 1-5 Gym Members for each gym
                $numMembers = rand(1, 5);
                foreach (range(1, $numMembers) as $i) {
                    $plan = $plans->random();
                    $member = GymMember::factory()
                        ->create([
                            'gym_id' => $gym->id,
                            'gym_membership_plan_id' => $plan->id,
                            'user_id' => $owner->id,
                        ]);

                    // Create 1-3 Member Payments for each member
                    $numPayments = rand(1, 3);
                    MemberPayment::factory()
                        ->count($numPayments)
                        ->create([
                            'member_id' => $member->id,
                            'membership_plan_id' => $plan->id,
                            'payment_date' => $faker->dateTimeBetween($member->membership_start, 'now'),
                            'currency' => $plan->currency, // Match currency with MembershipPlan
                            'amount' => $plan->price, // Match amount with MembershipPlan price
                            // 'payment_date' => $this->faker->dateTimeBetween($member->membership_start, 'now'),
                        ]);
                }
            }
        }
    }
}
