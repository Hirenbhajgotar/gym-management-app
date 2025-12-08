<?php

namespace Database\Seeders;

use App\Enums\DefaultRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Add role for super-admin if not exists
        $role = Role::firstOrCreate(['name' => DefaultRole::SUPER_ADMIN->value]);

        $superAdmin = User::updateOrCreate([
            'email' => 'hiren@fluxfitness.in',
        ], [
            'name' => 'Hiren Super Admin',
            'password' => 'hiren@8918514',
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole(DefaultRole::SUPER_ADMIN->value);

        // $gymOwner = User::updateOrCreate([
        //     'email' => 'hiren@example.com',
        // ], [
        //     'name' => 'Hiren Bhajgotar',
        //     'password' => '123456789',
        //     'email_verified_at' => now(),
        // ]);

        // $role = Role::create(['name' => 'gym_owner']);

        // $gymOwner->assignRole('gym_owner');
    }
}
