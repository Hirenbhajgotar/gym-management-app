<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Perfect for getting started with basic gym management features.',
                'brief_description' => 'Basic gym management for small operations',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'currency' => 'INR',
                'is_active' => false,
                'trial_days' => 14,
                'features' => [
                    ['feature_name' => 'max_gyms', 'value' => '1', 'description' => 'Maximum number of gyms'],
                    ['feature_name' => 'max_users', 'value' => '50', 'description' => 'Maximum gym members'],
                    ['feature_name' => 'max_leads', 'value' => '50', 'description' => 'Maximum leads'],
                    ['feature_name' => 'max_staff', 'value' => '5', 'description' => 'Maximum staff members'],
                    ['feature_name' => 'whatsapp_enabled', 'value' => 'false', 'description' => 'WhatsApp integration'],
                    ['feature_name' => 'whatsapp_credit', 'value' => '0', 'description' => 'Monthly WhatsApp credits'],
                    ['feature_name' => 'basic_crm', 'value' => 'true', 'description' => 'Basic CRM features'],
                    ['feature_name' => 'basic_reporting', 'value' => 'true', 'description' => 'Basic reporting'],
                ],
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Essential features for growing gyms with WhatsApp integration.',
                'brief_description' => 'Essential features for growing gyms',
                'price_monthly' => 1000,
                'price_yearly' => 10000,
                'currency' => 'INR',
                'is_active' => true,
                'trial_days' => 7,
                'features' => [
                    ['feature_name' => 'max_gyms', 'value' => '1', 'description' => 'Maximum number of gyms'],
                    ['feature_name' => 'max_users', 'value' => '500', 'description' => 'Maximum gym members'],
                    ['feature_name' => 'max_leads', 'value' => '500', 'description' => 'Maximum leads'],
                    ['feature_name' => 'max_staff', 'value' => '10', 'description' => 'Maximum staff members'],
                    ['feature_name' => 'whatsapp_enabled', 'value' => 'true', 'description' => 'WhatsApp integration'],
                    ['feature_name' => 'whatsapp_credit', 'value' => '1000', 'description' => 'Monthly WhatsApp credits'],
                    ['feature_name' => 'basic_crm', 'value' => 'true', 'description' => 'Basic CRM features'],
                    ['feature_name' => 'basic_reporting', 'value' => 'true', 'description' => 'Basic reporting'],
                    ['feature_name' => 'whatsapp_integration', 'value' => 'true', 'description' => 'WhatsApp Integration'],
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Advanced features for established gyms with website integration.',
                'brief_description' => 'Advanced features for established gyms',
                'price_monthly' => 2500,
                'price_yearly' => 25000,
                'currency' => 'INR',
                'is_active' => true,
                'trial_days' => 7,
                'features' => [
                    ['feature_name' => 'max_gyms', 'value' => '5', 'description' => 'Maximum number of gyms'],
                    ['feature_name' => 'max_users', 'value' => '1500', 'description' => 'Maximum gym members'],
                    ['feature_name' => 'max_leads', 'value' => '1500', 'description' => 'Maximum leads'],
                    ['feature_name' => 'max_staff', 'value' => '25', 'description' => 'Maximum staff members'],
                    ['feature_name' => 'whatsapp_enabled', 'value' => 'true', 'description' => 'WhatsApp integration'],
                    ['feature_name' => 'whatsapp_credit', 'value' => '3000', 'description' => 'Monthly WhatsApp credits'],
                    ['feature_name' => 'advanced_crm', 'value' => 'true', 'description' => 'Advanced CRM features'],
                    ['feature_name' => 'advanced_reporting', 'value' => 'true', 'description' => 'Advanced reporting'],
                    ['feature_name' => 'whatsapp_integration', 'value' => 'true', 'description' => 'WhatsApp Integration'],
                    ['feature_name' => 'website_integration', 'value' => 'true', 'description' => 'Website Integration'],
                ],
            ],
            [
                'name' => 'Ultimate',
                'slug' => 'ultimate',
                'description' => 'Complete solution for large gym chains with all premium features.',
                'brief_description' => 'Complete solution for large gym chains',
                'price_monthly' => 5000,
                'price_yearly' => 50000,
                'currency' => 'INR',
                'is_active' => true,
                'trial_days' => 7,
                'features' => [
                    ['feature_name' => 'max_gyms', 'value' => '-1', 'description' => 'Maximum number of gyms (Unlimited)'],
                    ['feature_name' => 'max_users', 'value' => '-1', 'description' => 'Maximum gym members (Unlimited)'],
                    ['feature_name' => 'max_leads', 'value' => '-1', 'description' => 'Maximum leads (Unlimited)'],
                    ['feature_name' => 'max_staff', 'value' => '-1', 'description' => 'Maximum staff members (Unlimited)'],
                    ['feature_name' => 'whatsapp_enabled', 'value' => 'true', 'description' => 'WhatsApp integration'],
                    ['feature_name' => 'whatsapp_credit', 'value' => '7000', 'description' => 'Monthly WhatsApp credits'],
                    ['feature_name' => 'advanced_crm', 'value' => 'true', 'description' => 'Advanced CRM features'],
                    ['feature_name' => 'advanced_reporting', 'value' => 'true', 'description' => 'Advanced reporting'],
                    ['feature_name' => 'whatsapp_integration', 'value' => 'true', 'description' => 'WhatsApp Integration'],
                    ['feature_name' => 'website_integration', 'value' => 'true', 'description' => 'Website Integration'],
                    ['feature_name' => 'mobile_app', 'value' => 'true', 'description' => 'Mobile App'],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $features = $planData['features'];
            unset($planData['features']);

            $plan = Plan::create($planData);

            foreach ($features as $feature) {
                PlanFeature::create([
                    'plan_id' => $plan->id,
                    'feature_name' => $feature['feature_name'],
                    'value' => $feature['value'],
                    'brief_description' => $feature['description'],
                ]);
            }
        }
    }
}
