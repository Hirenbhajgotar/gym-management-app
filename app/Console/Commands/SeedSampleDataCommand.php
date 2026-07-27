<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Currency;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\MemberPayment;
use App\Models\MembershipPlan;
use App\Models\Permission;
use App\Models\Trainer;
use App\Models\User;
use App\Services\RolesAndPermissionsService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class SeedSampleDataCommand extends Command
{
    protected $signature = 'app:seed-owner-data';

    protected $description = 'Seed sample data: 1 gym owner, 1 gym, 2 trainers, 1 manager, 3 plans, 10 members with assignments';

    public function handle(): int
    {
        $this->info('🏋️ Seeding owner with sample gym data...');

        if (Gym::query()->where('slug', 'joy-fitness')->exists()) {
            $this->warn('⚠️  owner data already exists. Use --force to re-seed.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () {
                $this->ensureRolesExist();

                $owner = $this->createGymOwner();
                $this->info("   ✅ Gym Owner: {$owner->name} ({$owner->email})");

                $gym = $this->createGym($owner);
                $this->info("   ✅ Gym: {$gym->name}");

                $manager = $this->createManager($gym);
                $this->info("   ✅ Manager: {$manager->name} ({$manager->email})");

                $trainers = $this->createTrainers($gym);
                $this->info("   ✅ Trainers: {$trainers->pluck('name')->implode(', ')}");

                $plans = $this->createMembershipPlans($gym);
                $this->info("   ✅ Plans: {$plans->pluck('name')->implode(', ')}");

                $members = $this->createGymMembers($gym, $trainers, $plans);
                $this->info("   ✅ Members: {$members->count()} created");

                $this->createMemberPayments($members, $plans, $gym);

                $this->newLine();
                $this->info('🎉 Sample data seeded successfully!');
                $this->newLine();
                $this->displaySummary($owner, $gym, $manager, $trainers, $plans, $members);
            });

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Seeding failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function ensureRolesExist(): void
    {
        $guardName = config()->string('filament-permissions.guard_name', 'web');

        foreach ([DefaultRole::GYM_OWNER, DefaultRole::MANAGER] as $role) {
            Role::query()->firstOrCreate([
                'name' => $role->value,
                'guard_name' => $guardName,
            ]);
        }
    }

    private function createGymOwner(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'bhajgotar8918514@gmail.com'],
            [
                'name' => 'Joy',
                'password' => Hash::make(config()->string('app.test_password', 'password')),
                'status' => UserStatus::ACTIVE,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }

    private function createGym(User $owner): Gym
    {
        $gym = Gym::query()->firstOrCreate(
            ['slug' => 'joy-fitness'],
            [
                'name' => 'Joy Fitness',
            ]
        );

        // Create default roles and permissions for the gym
        resolve(RolesAndPermissionsService::class)->createDefaultTenantRolesAndAssignPermissions($owner, $gym);

        // Assign the gym to the user with proper role
        resolve(RolesAndPermissionsService::class)->assignRoleToTenant($gym, $owner, DefaultRole::GYM_OWNER);

        $gym->users()->syncWithoutDetaching([
            $owner->id => [
                'role' => DefaultRole::GYM_OWNER->value,
                'active' => true,
            ],
        ]);

        $this->grantAllPermissionsToGymOwner($owner, $gym);

        return $gym;
    }

    private function createManager(Gym $gym): User
    {
        $manager = User::query()->firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => fake()->name(),
                'password' => Hash::make(config()->string('app.test_password', 'password')),
                'status' => UserStatus::ACTIVE,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $manager->assignRole(DefaultRole::MANAGER->value);

        $manager->gyms()->attach($gym->id, [
            'role' => DefaultRole::MANAGER->value,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $manager;
    }

    /**
     * @return Collection<int, Trainer>
     */
    private function createTrainers(Gym $gym): Collection
    {
        $specializations = ['Strength Training', 'Yoga & Flexibility', 'CrossFit', 'Cardio & HIIT', 'Bodybuilding'];

        $trainers = [];

        for ($i = 0; $i < 2; $i++) {
            $trainers[] = Trainer::query()->create([
                'gym_id' => $gym->id,
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'phone' => fake()->phoneNumber(),
                'specialization' => fake()->randomElement($specializations),
                'experience_years' => fake()->numberBetween(2, 15),
                'certifications' => fake()->randomElement([
                    'Certified Personal Trainer (CPT)',
                    'ACE Certified',
                    'NSCA Certified',
                    'Yoga Alliance RYT-200',
                    'CrossFit Level 1',
                ]),
                'is_available' => true,
                'available_hours' => fake()->randomElement([
                    'Mon-Fri: 6 AM - 2 PM',
                    'Mon-Sat: 8 AM - 4 PM',
                    'Tue-Sun: 10 AM - 6 PM',
                ]),
                'basic_salary' => fake()->randomFloat(2, 15000, 35000),
                'pt_salary_per_member' => fake()->randomFloat(2, 5, 25),
                'currency' => Currency::INR,
            ]);
        }

        return collect($trainers);
    }

    /**
     * @return Collection<int, MembershipPlan>
     */
    private function createMembershipPlans(Gym $gym): Collection
    {
        $planData = [
            ['name' => 'Basic Monthly', 'duration' => 1, 'price' => 999],
            ['name' => 'Standard Quarterly', 'duration' => 3, 'price' => 2499],
            ['name' => 'Premium Annual', 'duration' => 12, 'price' => 8999],
        ];

        $plans = [];

        foreach ($planData as $data) {
            $plans[] = MembershipPlan::query()->create([
                'gym_id' => $gym->id,
                'name' => $data['name'],
                'description' => "{$data['name']} membership plan with {$data['duration']} month(s) access",
                'price' => $data['price'],
                'duration' => $data['duration'],
                'currency' => Currency::INR,
                'is_active' => true,
            ]);
        }

        return collect($plans);
    }

    /**
     * @param  Collection<int, Trainer>  $trainers
     * @param  Collection<int, MembershipPlan>  $plans
     * @return Collection<int, GymMember>
     */
    private function createGymMembers(Gym $gym, Collection $trainers, Collection $plans): Collection
    {
        $members = [];

        for ($i = 0; $i < 10; $i++) {
            $plan = $plans->random();
            $trainer = $trainers->random();
            $membershipStart = fake()->dateTimeBetween('-6 months', '-1 month');
            $membershipEnd = (clone $membershipStart)->modify("+{$plan->duration} months");

            $members[] = GymMember::query()->create([
                'gym_id' => $gym->id,
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'phone' => fake()->unique()->phoneNumber(),
                'age' => fake()->numberBetween(18, 55),
                'dob' => fake()->dateTimeBetween('-55 years', '-18 years'),
                'membership_start' => $membershipStart,
                'membership_end' => $membershipEnd,
                'gym_membership_plan_id' => $plan->id,
                'trainer_id' => $trainer->id,
                'payment_status' => PaymentStatus::COMPLETED,
                'latest_payment_at' => fake()->dateTimeBetween('-2 months', 'now'),
            ]);
        }

        return collect($members);
    }

    /**
     * @param  Collection<int, GymMember>  $members
     * @param  Collection<int, MembershipPlan>  $plans
     */
    private function createMemberPayments(
        Collection $members,
        Collection $plans,
        Gym $gym
    ): void {
        $currency = Currency::INR;

        foreach ($members as $member) {
            $plan = $plans->firstWhere('id', $member->gym_membership_plan_id);

            if ($plan === null) {
                continue;
            }

            MemberPayment::query()->create([
                'gym_id' => $gym->id,
                'member_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'amount' => $plan->price,
                'currency' => $currency,
                'payment_method' => fake()->randomElement(['cash', 'card', 'upi', 'bank_transfer']),
                'status' => PaymentStatus::COMPLETED,
                'payment_date' => fake()->dateTimeBetween($member->membership_start, 'now'),
                'membership_start' => $member->membership_start,
                'membership_end' => $member->membership_end,
                'notes' => fake()->optional(0.3)->sentence(),
            ]);
        }
    }

    /**
     * @param  Collection<int, Trainer>  $trainers
     * @param  Collection<int, MembershipPlan>  $plans
     * @param  Collection<int, GymMember>  $members
     */
    private function displaySummary(
        User $owner,
        Gym $gym,
        User $manager,
        Collection $trainers,
        Collection $plans,
        Collection $members
    ): void {
        $this->table(
            ['Entity', 'Count', 'Details'],
            [
                ['Gym Owner', 1, "{$owner->name} ({$owner->email})"],
                ['Gym', 1, $gym->name],
                ['Manager', 1, "{$manager->name} ({$manager->email})"],
                ['Trainers', $trainers->count(), $trainers->pluck('name')->implode(', ')],
                ['Membership Plans', $plans->count(), $plans->pluck('name')->implode(', ')],
                ['Gym Members', $members->count(), 'Assigned to trainers and plans'],
                ['Member Payments', $members->count(), 'One payment per member'],
            ]
        );

        $password = config()->string('app.test_password', 'password');

        $this->newLine();
        $this->info('📋 Login Credentials:');
        $this->info("   Owner:   {$owner->email} / {$password}");
        $this->info("   Manager: {$manager->email} / {$password}");
    }

    protected function grantAllPermissionsToGymOwner(User $user, Gym $gym): void
    {
        // Set the permission team context to the gym
        setPermissionsTeamId($gym->id);

        // Get the gym_owner role for this specific gym
        $gymOwnerRoleId = Role::query()->where('name', DefaultRole::GYM_OWNER->value)
            ->where('gym_id', $gym->id)
            ->value('id');

        if ($gymOwnerRoleId) {
            // Get ALL permission IDs efficiently
            $allPermissionIds = Permission::query()->pluck('id');

            $insertData = $allPermissionIds->map(fn ($permId) => [
                'permission_id' => $permId,
                'role_id' => $gymOwnerRoleId,
            ])->all();

            // Clear existing permissions first (though likely empty for new gym)
            DB::table('role_has_permissions')->where('role_id', $gymOwnerRoleId)->delete();

            // Bulk insert
            if (filled($insertData)) {
                foreach (array_chunk($insertData, 1000) as $chunk) {
                    DB::table('role_has_permissions')->insert($chunk);
                }
            }

            // Clear permission cache to ensure changes take effect immediately
            resolve(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
