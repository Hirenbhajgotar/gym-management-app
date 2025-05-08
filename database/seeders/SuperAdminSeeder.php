<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // User::updateOrCreate([
        //     'email' => 'admin@sidcraft.com',
        // ], [
        //     'name' => 'Super Admin',
        //     'password' => 'Sidcraft.gym.123',
        //     'email_verified_at' => now(),
        // ]);

        $gymOwner = User::updateOrCreate([
            'email' => 'alpa@sidcraft.com',
        ], [
            'name' => 'Alpa Bhajgotar',
            'password' => '12345',
            'email_verified_at' => now(),
        ]);

        $role = Role::create(['name' => 'gym_owner']);

        $gymOwner->assignRole('gym_owner');
    }
}
