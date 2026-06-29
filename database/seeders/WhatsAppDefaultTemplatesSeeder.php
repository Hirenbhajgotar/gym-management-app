<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Seeder;

class WhatsAppDefaultTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $defaultTemplates = [
            [
                'name' => NotificationType::WELCOME->value,
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
                'name' => NotificationType::PAYMENT_REMINDER->value,
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
                'name' => NotificationType::MEMBERSHIP_EXPIRY->value,
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
                'name' => NotificationType::CLASS_REMINDER->value,
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
                'name' => NotificationType::BIRTHDAY->value,
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
                'name' => NotificationType::PROMOTIONAL->value,
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
            [
                'name' => NotificationType::FOLLOWUP_REMINDER->value,
                'display_name' => 'Follow-up Reminder',
                'category' => 'utility',
                'body' => "Follow-Up Reminder Notification\n\nHello {{1}},\nYou have a scheduled follow-up that is still pending.\n\nName: {{2}}\nScheduled Date: {{3}}\nPurpose: {{4}}\n\nKindly complete the follow-up to maintain proper communication.",
                'variables' => [
                    ['name' => 'variable_1', 'format' => 'text', 'example' => 'John Doe'],
                    ['name' => 'variable_2', 'format' => 'text', 'example' => 'Jane Smith'],
                    ['name' => 'variable_3', 'format' => 'date', 'example' => '2026-02-26'],
                    ['name' => 'variable_4', 'format' => 'text', 'example' => 'membership plans'],
                ],
                'variable_count' => 4,
                'variable_placeholder' => [
                    '1' => ['placeholder' => 'Name', 'name' => 'name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'custom'],
                    '2' => ['placeholder' => 'Lead Name', 'name' => 'lead_name', 'example' => 'Jane Smith', 'type' => 'text', 'source' => 'custom'],
                    '3' => ['placeholder' => 'Scheduled Date', 'name' => 'scheduled_date', 'example' => '2026-02-26', 'type' => 'date', 'source' => 'custom'],
                    '4' => ['placeholder' => 'Notes', 'name' => 'notes', 'example' => 'membership plans', 'type' => 'text', 'source' => 'custom'],
                ],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => NotificationType::LEAD_REMINDER->value,
                'display_name' => 'Lead Reminder',
                'category' => 'utility',
                'body' => "Lead Reminder Notification\n\nHello {{1}},\nA lead is currently pending and requires your attention.\n\nLead Name: {{2}}\nContact Number: {{3}}\nInquiry Details: {{4}}\n\nPlease follow up as soon as possible to ensure timely response.",
                'variables' => [
                    ['name' => 'variable_1', 'format' => 'text', 'example' => 'John Doe'],
                    ['name' => 'variable_2', 'format' => 'text', 'example' => 'Jane Smith'],
                    ['name' => 'variable_3', 'format' => 'text', 'example' => '1234567890'],
                    ['name' => 'variable_4', 'format' => 'text', 'example' => 'membership plans'],
                ],
                'variable_count' => 4,
                'variable_placeholder' => [
                    '1' => ['placeholder' => 'Name', 'name' => 'name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'custom'],
                    '2' => ['placeholder' => 'Lead Name', 'name' => 'lead_name', 'example' => 'Jane Smith', 'type' => 'text', 'source' => 'custom'],
                    '3' => ['placeholder' => 'Contact Number', 'name' => 'phone', 'example' => '1234567890', 'type' => 'text', 'source' => 'custom'],
                    '4' => ['placeholder' => 'Inquiry Details', 'name' => 'inquiry_details', 'example' => 'membership plans', 'type' => 'text', 'source' => 'custom'],
                ],
                'status' => 'approved',
                'is_default' => true,
            ],
            [
                'name' => NotificationType::ATTENDANCE_REMINDER->value,
                'display_name' => 'Attendance Reminder',
                'category' => 'utility',
                'body' => "Attendance Reminder Notification\n\nHello {{1}},\nWe noticed you haven't visited the gym recently. To stay on track with your fitness goals, we encourage you to come back and continue your workout routine.\n\nLast Visit: {{2}}\nDays Since Last Visit: {{3}}\n\nWe look forward to seeing you soon!",
                'variables' => [
                    ['name' => 'variable_1', 'format' => 'text', 'example' => 'John Doe'],
                    ['name' => 'variable_2', 'format' => 'text', 'example' => '2024-01-01'],
                    ['name' => 'variable_3', 'format' => 'text', 'example' => '7'],
                ],
                'variable_count' => 3,
                'variable_placeholder' => [
                    '1' => ['placeholder' => 'Member Name', 'name' => 'member_name', 'example' => 'John Doe', 'type' => 'text', 'source' => 'member.name'],
                    '2' => ['placeholder' => 'Last Visit', 'name' => 'last_visit', 'example' => '2024-01-01', 'type' => 'date', 'source' => 'custom'],
                    '3' => ['placeholder' => 'Days Since Last Visit', 'name' => 'days_since_last_visit', 'example' => '7', 'type' => 'text', 'source' => 'custom'],
                ],
                'status' => 'approved',
                'is_default' => true,
            ],
        ];

        foreach ($defaultTemplates as $template) {
            WhatsAppTemplate::query()->updateOrCreate([
                'name' => $template['name'],
                'gym_id' => null, // Global defaults
            ], $template);
        }
    }
}
