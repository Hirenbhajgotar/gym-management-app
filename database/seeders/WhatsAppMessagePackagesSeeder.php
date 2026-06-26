<?php

namespace Database\Seeders;

use App\Models\WhatsAppPackage;
use Illuminate\Database\Seeder;

class WhatsAppMessagePackagesSeeder extends Seeder
{
    public function run(): void
    {
        $twilioCostPerMessage = 0.15;
        $twilioNumberCost = 800.00;

        $packages = [
            [
                'name' => 'Starter Pack',
                'description' => 'Perfect for small gyms starting with WhatsApp notifications',
                'message_count' => 100,
                // 'price' => 199.00,
                'selling_price' => 199.00,
                'twilio_number_cost' => 0,
                'twilio_msg_cost_per_unit' => $twilioCostPerMessage,
                'validity_days' => 365,
                'sort_order' => 1,
                'is_active' => true,
                'is_popular' => false,
                'includes_number' => false,
                'features' => [
                    '100 message credits',
                    'No expiration date',
                    'Template-based messaging',
                    'Broadcast to members',
                ],
            ],
            [
                'name' => 'Growth Pack',
                'description' => 'Most popular choice for growing gyms',
                'message_count' => 500,
                // 'price' => 749.00,
                'selling_price' => 749.00,
                'twilio_number_cost' => 0,
                'twilio_msg_cost_per_unit' => $twilioCostPerMessage,
                'validity_days' => 365,
                'sort_order' => 2,
                'is_active' => true,
                'is_popular' => true,
                'includes_number' => false,
                'features' => [
                    '500 message credits',
                    'No expiration date',
                    'Template-based messaging',
                    'Broadcast to members',
                    'Priority support',
                ],
            ],
            [
                'name' => 'Professional Pack',
                'description' => 'Ideal for established gyms with high member engagement',
                'message_count' => 1000,
                // 'price' => 1299.00,
                'selling_price' => 1299.00,
                'twilio_number_cost' => 0,
                'twilio_msg_cost_per_unit' => $twilioCostPerMessage,
                'validity_days' => 365,
                'sort_order' => 3,
                'is_active' => true,
                'is_popular' => false,
                'includes_number' => false,
                'features' => [
                    '1,000 message credits',
                    'No expiration date',
                    'Template-based messaging',
                    'Broadcast to members',
                    'Priority support',
                    'Usage analytics',
                ],
            ],
            [
                'name' => 'Business Pack',
                'description' => 'Best value for large gyms and fitness chains',
                'message_count' => 3000,
                // 'price' => 3499.00,
                'selling_price' => 3499.00,
                'twilio_number_cost' => 0,
                'twilio_msg_cost_per_unit' => $twilioCostPerMessage,
                'validity_days' => 365,
                'sort_order' => 4,
                'is_active' => true,
                'is_popular' => false,
                'includes_number' => false,
                'features' => [
                    '3,000 message credits',
                    'No expiration date',
                    'Template-based messaging',
                    'Broadcast to members',
                    'Priority support',
                    'Usage analytics',
                    'Dedicated account manager',
                ],
            ],
            [
                'name' => 'Enterprise Pack',
                'description' => 'For large fitness chains with dedicated WhatsApp number',
                'message_count' => 5000,
                // 'price' => 4999.00,
                'selling_price' => 4999.00,
                'twilio_number_cost' => $twilioNumberCost,
                'twilio_msg_cost_per_unit' => $twilioCostPerMessage,
                'validity_days' => 365,
                'sort_order' => 5,
                'is_active' => true,
                'is_popular' => false,
                'includes_number' => true,
                'features' => [
                    '5,000 message credits',
                    'Dedicated WhatsApp number',
                    'Custom business profile',
                    'Template-based messaging',
                    'Broadcast to members',
                    'Priority support',
                    'Usage analytics',
                    'Dedicated account manager',
                    'Custom integrations',
                ],
            ],
        ];

        foreach ($packages as $package) {
            WhatsAppPackage::query()->updateOrCreate(['name' => $package['name']], $package);
        }
    }
}
