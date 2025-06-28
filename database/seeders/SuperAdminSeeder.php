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
        $superAdmin = User::updateOrCreate([
            'email' => 'admin@sidcraft.com',
        ], [
            'name' => 'Super Admin',
            'password' => '123456789',
            'email_verified_at' => now(),
        ]);
        // $role = Role::create(['name' => 'super_admin']);
        $superAdmin->assignRole('super_admin');

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
