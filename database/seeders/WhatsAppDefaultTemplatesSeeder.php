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
                'variables' => [
                    ['name' => 'variable_1', 'format' => 'text', 'example' => 'John Doe'],
                    ['name' => 'variable_2', 'format' => 'text', 'example' => 'FitZone Gym'],
                ],
                'variable_count' => 2,
                'variable_placeholder' => [
                    '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'member.name'],
                    '2' => ['placeholder' => 'Gym Name', 'name' => 'gym_name', 'example' => 'FitZone Gym', 'type' => 'text', 'source' => 'gym.name'],
                ],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'payment_reminder',
                'display_name' => 'Payment Reminder',
                'category' => 'utility',
                'body' => 'Hi {{1}}, this is a reminder that your membership payment of ₹{{2}} for {{3}} is due on {{4}}. Please renew to continue enjoying our facilities.',
                'variables' => [
                    ['name' => 'variable_1', 'format' => 'text', 'example' => 'John Doe'],
                    ['name' => 'variable_2', 'format' => 'text', 'example' => '1,500'],
                    ['name' => 'variable_3', 'format' => 'text', 'example' => 'Premium Monthly'],
                    ['name' => 'variable_4', 'format' => 'text', 'example' => 'January 15, 2026'],
                ],
                'variable_count' => 4,
                'variable_placeholder' => [
                    '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'member.name'],
                    '2' => ['placeholder' => 'Payment Amount', 'name' => 'payment_amount', 'example' => '1,500', 'type' => 'currency', 'source' => 'payment.formatted_amount'],
                    '3' => ['placeholder' => 'Plan Name', 'name' => 'plan_name', 'example' => 'Premium Monthly', 'type' => 'text', 'source' => 'plan.name'],
                    '4' => ['placeholder' => 'Due Date', 'name' => 'due_date', 'example' => 'January 15, 2026', 'type' => 'date', 'source' => 'member.membership_end'],
                ],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'membership_expiry',
                'display_name' => 'Membership Expiry',
                'category' => 'utility',
                'body' => 'Hi {{1}}, your membership at {{2}} will expire on {{3}}. Renew now to continue your fitness journey without interruption!',
                'variables' => [
                    ['name' => 'variable_1', 'format' => 'text', 'example' => 'John Doe'],
                    ['name' => 'variable_2', 'format' => 'text', 'example' => 'FitZone Gym'],
                    ['name' => 'variable_3', 'format' => 'text', 'example' => 'March 15, 2026'],
                ],
                'variable_count' => 3,
                'variable_placeholder' => [
                    '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'member.name'],
                    '2' => ['placeholder' => 'Gym Name', 'name' => 'gym_name', 'example' => 'FitZone Gym', 'type' => 'text', 'source' => 'gym.name'],
                    '3' => ['placeholder' => 'Expiry Date', 'name' => 'expiry_date', 'example' => 'March 15, 2026', 'type' => 'date', 'source' => 'member.membership_end'],
                ],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'class_reminder',
                'display_name' => 'Class Reminder',
                'category' => 'utility',
                'body' => 'Hi {{1}}, just a reminder about your {{2}} class at {{3}} on {{4}}. See you there!',
                'variables' => [
                    ['name' => 'variable_1', 'format' => 'text', 'example' => 'John Doe'],
                    ['name' => 'variable_2', 'format' => 'text', 'example' => 'Yoga'],
                    ['name' => 'variable_3', 'format' => 'text', 'example' => 'FitZone Gym'],
                    ['name' => 'variable_4', 'format' => 'text', 'example' => 'Monday, 7:00 AM'],
                ],
                'variable_count' => 4,
                'variable_placeholder' => [
                    '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'member.name'],
                    '2' => ['placeholder' => 'Class Name', 'name' => 'class_name', 'example' => 'Yoga', 'type' => 'text', 'source' => 'custom'],
                    '3' => ['placeholder' => 'Gym Name', 'name' => 'gym_name', 'example' => 'FitZone Gym', 'type' => 'text', 'source' => 'gym.name'],
                    '4' => ['placeholder' => 'Schedule', 'name' => 'schedule', 'example' => 'Monday, 7:00 AM', 'type' => 'text', 'source' => 'custom'],
                ],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'birthday_wish',
                'display_name' => 'Birthday Wish',
                'category' => 'utility',
                'body' => 'Happy Birthday {{1}}! Wishing you a fantastic day from all of us at {{2}}. Come celebrate with a special workout!',
                'variables' => [
                    ['name' => 'variable_1', 'format' => 'text', 'example' => 'John Doe'],
                    ['name' => 'variable_2', 'format' => 'text', 'example' => 'FitZone Gym'],
                ],
                'variable_count' => 2,
                'variable_placeholder' => [
                    '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'member.name'],
                    '2' => ['placeholder' => 'Gym Name', 'name' => 'gym_name', 'example' => 'FitZone Gym', 'type' => 'text', 'source' => 'gym.name'],
                ],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => 'promotional_offer',
                'display_name' => 'Promotional Offer',
                'category' => 'marketing',
                'body' => 'Hi {{1}}! 🔥 Great news from {{2}}: {{3}}. Don\'t miss out — offer valid until {{4}}. Visit us today!',
                'variables' => [
                    ['name' => 'variable_1', 'format' => 'text', 'example' => 'John Doe'],
                    ['name' => 'variable_2', 'format' => 'text', 'example' => 'FitZone Gym'],
                    ['name' => 'variable_3', 'format' => 'text', 'example' => '50% off annual membership'],
                    ['name' => 'variable_4', 'format' => 'text', 'example' => 'March 31, 2026'],
                ],
                'variable_count' => 4,
                'variable_placeholder' => [
                    '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'member.name'],
                    '2' => ['placeholder' => 'Gym Name', 'name' => 'gym_name', 'example' => 'FitZone Gym', 'type' => 'text', 'source' => 'gym.name'],
                    '3' => ['placeholder' => 'Offer Details', 'name' => 'offer_details', 'example' => '50% off annual membership', 'type' => 'text', 'source' => 'custom'],
                    '4' => ['placeholder' => 'Offer Expiry', 'name' => 'offer_expiry', 'example' => 'March 31, 2026', 'type' => 'date', 'source' => 'custom'],
                ],
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
