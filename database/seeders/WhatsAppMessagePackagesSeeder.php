<?php

namespace Database\Seeders;

use App\Models\WhatsAppMessagePackage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WhatsAppMessagePackagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Starter Pack',
                'message_count' => 100,
                'price' => 199.00,
                'currency' => 'INR',
                'description' => 'Perfect for small gyms starting with WhatsApp notifications',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Popular Pack',
                'message_count' => 500,
                'price' => 999.00,
                'currency' => 'INR',
                'description' => 'Most popular choice for growing gyms',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro Pack',
                'message_count' => 1000,
                'price' => 1999.00,
                'currency' => 'INR',
                'description' => 'Ideal for established gyms with high member engagement',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise Pack',
                'message_count' => 3000,
                'price' => 5700.00,
                'currency' => 'INR',
                'description' => 'Best value for large gyms and fitness chains',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($packages as $package) {
            WhatsAppMessagePackage::create($package);
        }
    }
}
