<?php

namespace Database\Seeders;

use App\Models\WhatsAppTemplate;
use Illuminate\Database\Seeder;

class WhatsAppDefaultTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $defaultTemplates = [
            [
                'name' => 'welcome_message',
                'category' => 'utility',
                'body' => 'Hello {{1}}! Welcome to {{2}}. We\'re excited to have you as a member. Your membership is now active.',
                'variables' => ['member_name', 'gym_name'],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'payment_reminder',
                'category' => 'utility',
                'body' => 'Hi {{1}}, this is a reminder that your membership payment of ₹{{2}} for {{3}} is due on {{4}}. Please renew to continue enjoying our facilities.',
                'variables' => ['member_name', 'amount', 'gym_name', 'due_date'],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'membership_expiry',
                'category' => 'utility',
                'body' => 'Hi {{1}}, your membership at {{2}} will expire on {{3}}. Renew now to continue your fitness journey without interruption!',
                'variables' => ['member_name', 'gym_name', 'expiry_date'],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'class_reminder',
                'category' => 'utility',
                'body' => 'Hi {{1}}, just a reminder about your {{2}} class at {{3}} on {{4}}. See you there!',
                'variables' => ['member_name', 'class_name', 'gym_name', 'class_time'],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'birthday_wish',
                'category' => 'marketing',
                'body' => 'Happy Birthday {{1}}! Wishing you a fantastic day from all of us at {{2}}. Come celebrate with a special workout!',
                'variables' => ['member_name', 'gym_name'],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'promotional_offer',
                'category' => 'marketing',
                'body' => 'Hi {{1}}! 🔥 Great news from {{2}}: {{3}}. Don\'t miss out — offer valid until {{4}}. Visit us today!',
                'variables' => ['member_name', 'gym_name', 'offer_details', 'expiry_date'],
                'status' => 'approved',
                'is_default' => true,
            ],
        ];

        foreach ($defaultTemplates as $template) {
            WhatsAppTemplate::updateOrCreate(
                [
                    'name' => $template['name'],
                    'gym_id' => null, // Global defaults
                ],
                $template
            );
        }
    }
}
