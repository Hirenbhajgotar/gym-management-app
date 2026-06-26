<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Date;
use App\Enums\PaymentStatus;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MemberPayment;
use Illuminate\Database\Seeder;

class MemberPaymentSeeder extends Seeder
{
    public function run()
    {
        $members = GymMember::with(['gym', 'membershipPlan'])->get();

        if ($members->isEmpty()) {
            $this->command->warn('⚠️  No members found - nothing to seed.');

            return;
        }

        $payments = [];

        $gyms = Gym::query()->get()->keyBy('id');

        foreach ($members as $member) {
            // How many payments per member (1 to 3)
            $count = rand(1, 3);

            $gymId = $gyms->get($member->gym_id)->id ?? null;

            for ($i = 0; $i < $count; $i++) {
                $paymentDate = Date::today()
                    ->subDays(rand(0, 90)) // last 3 months
                    ->subHours(rand(0, 23));

                // Optional: make membership_start/end sequential for multiple payments
                $membershipStart = $paymentDate->copy()->subDays(rand(0, 7));
                $membershipEnd = $membershipStart->copy()->addMonths(
                    $member->membershipPlan->duration ?? 1
                );

                $payments[] = [
                    'gym_id' => $gymId,
                    'member_id' => $member->id,
                    'membership_plan_id' => $member->gym_membership_plan_id,
                    'amount' => $member->membershipPlan->price ?? fake()->randomFloat(2, 10, 200),
                    'currency' => $member->gym->currency ?? 'INR',
                    'payment_method' => fake()->randomElement(['cash', 'card', 'online']),
                    'status' => PaymentStatus::COMPLETED, // always paid for seeding
                    'notes' => fake()->optional()->sentence(),
                    'payment_date' => $paymentDate,
                    'membership_start' => $membershipStart,
                    'membership_end' => $membershipEnd,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Chunk insert for speed
        collect($payments)->chunk(500)->each(
            fn ($chunk) => MemberPayment::query()->insert($chunk->all())
        );

        $this->command->info('✅ Seeded ' . count($payments) . ' payments for ' . $members->count() . ' members.');
    }
}
