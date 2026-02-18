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
                'display_name' => 'Welcome Message',
                'category' => 'utility',
                'body' => 'Hello {{1}}! Welcome to {{2}}. We\'re excited to have you as a member. Your membership is now active.',
                'variables' => ['{{1}}', '{{2}}'],
                'variable_count' => 2,
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'payment_reminder',
                'display_name' => 'Payment Reminder',
                'category' => 'utility',
                'body' => 'Hi {{1}}, this is a reminder that your membership payment of ₹{{2}} for {{3}} is due on {{4}}. Please renew to continue enjoying our facilities.',
                'variables' => ['{{1}}', '{{2}}', '{{3}}', '{{4}}'],
                'variable_count' => 4,
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'membership_expiry',
                'display_name' => 'Membership Expiry',
                'category' => 'utility',
                'body' => 'Hi {{1}}, your membership at {{2}} will expire on {{3}}. Renew now to continue your fitness journey without interruption!',
                'variables' => ['{{1}}', '{{2}}', '{{3}}'],
                'variable_count' => 3,
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'class_reminder',
                'display_name' => 'Class Reminder',
                'category' => 'utility',
                'body' => 'Hi {{1}}, just a reminder about your {{2}} class at {{3}} on {{4}}. See you there!',
                'variables' => ['{{1}}', '{{2}}', '{{3}}', '{{4}}'],
                'variable_count' => 4,
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'birthday_wish',
                'display_name' => 'Birthday Wish',
                'category' => 'utility',
                'body' => 'Happy Birthday {{1}}! Wishing you a fantastic day from all of us at {{2}}. Come celebrate with a special workout!',
                'variables' => ['{{1}}', '{{2}}'],
                'variable_count' => 2,
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'promotional_offer',
                'display_name' => 'Promotional Offer',
                'category' => 'marketing',
                'body' => 'Hi {{1}}! 🔥 Great news from {{2}}: {{3}}. Don\'t miss out — offer valid until {{4}}. Visit us today!',
                'variables' => ['{{1}}', '{{2}}', '{{3}}', '{{4}}'],
                'variable_count' => 4,
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
