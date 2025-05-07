<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate([
            'email' => 'admin@sidcraft.com',
        ], [
            'name' => 'Super Admin',
            'password' => 'Sidcraft.gym.123',
            'email_verified_at' => now(),
        ]);
    }
}
