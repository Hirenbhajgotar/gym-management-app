<?php

namespace App\Services;

use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

class WhatsAppTemplateService
{
    private Client $masterClient;

    private string $masterAccountSid;

    private string $masterAuthToken;

    private bool $useSandbox = true;

    public function __construct()
    {
        $this->useSandbox = config('services.twilio.use_sandbox', true);
        $this->masterAccountSid = config('services.twilio.master_sid');
        $this->masterAuthToken = config('services.twilio.master_token');
        $this->masterClient = new Client($this->masterAccountSid, $this->masterAuthToken);
    }

    public function createDefaultTemplates(int $gymId): void
    {
        $templates = $this->getDefaultTemplates();

        foreach ($templates as $template) {
            $this->createTemplate($gymId, $template);
        }

        Log::info("Default WhatsApp templates created for gym: {$gymId}");
    }

    protected function getDefaultTemplates(): array
    {
        return [
            [
                'name' => 'booking_confirmation',
                'display_name' => 'Booking Confirmation',
                'category' => 'utility',
                'body' => "Hi {{1}},\n\nYour booking for {{2}} at {{3}} is confirmed!\n\n📅 Date: {{4}}\n⏰ Time: {{5}}\n🏋️ Workout: {{6}}\n\nSee you there!",
                'variables' => [
                    ['name' => 'member_name', 'format' => 'text', 'example' => 'John'],
                    ['name' => 'class_name', 'format' => 'text', 'example' => 'Morning Yoga'],
                    ['name' => 'gym_name', 'format' => 'text', 'example' => 'FitZone Gym'],
                    ['name' => 'booking_date', 'format' => 'text', 'example' => 'Jan 15, 2024'],
                    ['name' => 'booking_time', 'format' => 'text', 'example' => '7:00 AM'],
                    ['name' => 'workout_type', 'format' => 'text', 'example' => 'Vinyasa Flow'],
                ],
                'is_default' => true,
            ],
            [
                'name' => 'booking_reminder',
                'display_name' => 'Booking Reminder',
                'category' => 'utility',
                'body' => "⏰ Reminder: You have a booking tomorrow!\n\nHi {{1}},\n\nThis is a friendly reminder about your upcoming session:\n\n📅 Date: {{2}}\n⏰ Time: {{3}}\n🏋️ Workout: {{4}}\n\nPlease arrive 10 minutes early. Reply CONFIRM to confirm or CANCEL to cancel.",
                'variables' => [
                    ['name' => 'member_name', 'format' => 'text', 'example' => 'John'],
                    ['name' => 'booking_date', 'format' => 'text', 'example' => 'Jan 15, 2024'],
                    ['name' => 'booking_time', 'format' => 'text', 'example' => '7:00 AM'],
                    ['name' => 'workout_type', 'format' => 'text', 'example' => 'Morning Yoga'],
                ],
                'is_default' => true,
            ],
            [
                'name' => 'gym_closure_announcement',
                'display_name' => 'Gym Closure Announcement',
                'category' => 'utility',
                'body' => "📢 Important Announcement from {{1}},\n\nDear Members,\n\nWe wanted to inform you that the gym will be closed on:\n\n📅 Date: {{2}}\n\nReason: {{3}}\n\nWe apologize for any inconvenience. Regular operations will resume on {{4}}.\n\nStay fit!\n\n- {{1}} Team",
                'variables' => [
                    ['name' => 'gym_name', 'format' => 'text', 'example' => 'FitZone Gym'],
                    ['name' => 'closure_date', 'format' => 'text', 'example' => 'January 26th'],
                    ['name' => 'closure_reason', 'format' => 'text', 'example' => 'National Holiday'],
                    ['name' => 'reopen_date', 'format' => 'text', 'example' => 'January 27th'],
                ],
                'is_default' => true,
            ],
            [
                'name' => 'payment_receipt',
                'display_name' => 'Payment Receipt',
                'category' => 'utility',
                'body' => "✅ Payment Received\n\nHi {{1}},\n\nWe've received your payment of {{2}} for {{3}}.\n\n💳 Card: {{4}}\n📋 Receipt No: {{5}}\n📅 Date: {{6}}\n\nThank you for your payment!\n\n- {{7}}",
                'variables' => [
                    ['name' => 'member_name', 'format' => 'text', 'example' => 'John'],
                    ['name' => 'amount', 'format' => 'text', 'example' => '₹2,499'],
                    ['name' => 'payment_description', 'format' => 'text', 'example' => 'Monthly Membership'],
                    ['name' => 'card_last4', 'format' => 'text', 'example' => '4242'],
                    ['name' => 'receipt_number', 'format' => 'text', 'example' => 'RCP-2024-001234'],
                    ['name' => 'payment_date', 'format' => 'text', 'example' => 'Jan 15, 2024'],
                    ['name' => 'gym_name', 'format' => 'text', 'example' => 'FitZone Gym'],
                ],
                'is_default' => true,
            ],
            [
                'name' => 'membership_expiry_reminder',
                'display_name' => 'Membership Expiry Reminder',
                'category' => 'utility',
                'body' => "⚠️ Membership Expiring Soon\n\nHi {{1}},\n\nYour {{2}} membership at {{3}} will expire on {{4}}.\n\nTo continue enjoying our facilities, please renew before {{5}}.\n\n💰 Renewal Fee: {{6}}\n\nVisit our reception or renew online.\n\n- {{3}} Team",
                'variables' => [
                    ['name' => 'member_name', 'format' => 'text', 'example' => 'John'],
                    ['name' => 'plan_name', 'format' => 'text', 'example' => 'Premium'],
                    ['name' => 'gym_name', 'format' => 'text', 'example' => 'FitZone Gym'],
                    ['name' => 'expiry_date', 'format' => 'text', 'example' => 'Jan 31, 2024'],
                    ['name' => 'grace_period_end', 'format' => 'text', 'example' => 'Feb 5, 2024'],
                    ['name' => 'renewal_amount', 'format' => 'text', 'example' => '₹2,499'],
                ],
                'is_default' => true,
            ],
            [
                'name' => 'welcome_message',
                'display_name' => 'Welcome Message',
                'category' => 'utility',
                'body' => "🎉 Welcome to {{1}}, {{2}}!\n\nWe're thrilled to have you join our fitness family!\n\nYour membership is now active:\n\n📋 Plan: {{3}}\n📅 Valid Until: {{4}}\n\nTo get started:\n✅ Download our app\n✅ Book your first class\n✅ Meet our trainers\n\nQuestions? Reply to this message!\n\nLet's crush those fitness goals! 💪\n\n- {{1}} Team",
                'variables' => [
                    ['name' => 'gym_name', 'format' => 'text', 'example' => 'FitZone Gym'],
                    ['name' => 'member_name', 'format' => 'text', 'example' => 'John'],
                    ['name' => 'plan_name', 'format' => 'text', 'example' => 'Premium'],
                    ['name' => 'valid_until', 'format' => 'text', 'example' => 'Feb 15, 2024'],
                ],
                'is_default' => true,
            ],
            [
                'name' => 'class_cancellation',
                'display_name' => 'Class Cancellation',
                'category' => 'utility',
                'body' => "📢 Class Cancellation Notice\n\nHi {{1}},\n\nUnfortunately, the following class has been cancelled:\n\n🏋️ Class: {{2}}\n📅 Date: {{3}}\n⏰ Time: {{4}}\n\nWe apologize for any inconvenience. Your credits have been refunded.\n\nWould you like to book an alternative class?\n\n- {{5}} Team",
                'variables' => [
                    ['name' => 'member_name', 'format' => 'text', 'example' => 'John'],
                    ['name' => 'class_name', 'format' => 'text', 'example' => 'Morning Yoga'],
                    ['name' => 'class_date', 'format' => 'text', 'example' => 'Jan 15, 2024'],
                    ['name' => 'class_time', 'format' => 'text', 'example' => '7:00 AM'],
                    ['name' => 'gym_name', 'format' => 'text', 'example' => 'FitZone Gym'],
                ],
                'is_default' => true,
            ],
            [
                'name' => 'birthday_wishes',
                'display_name' => 'Birthday Wishes',
                'category' => 'utility',
                'body' => "🎂 Happy Birthday, {{1}}! 🎂\n\n🎉 Wishing you an amazing birthday!\n\nMay your year be filled with health, happiness, and personal bests!\n\nEnjoy your special day! 🎈\n\n- {{2}} Team",
                'variables' => [
                    ['name' => 'member_name', 'format' => 'text', 'example' => 'John'],
                    ['name' => 'gym_name', 'format' => 'text', 'example' => 'FitZone Gym'],
                ],
                'is_default' => true,
            ],
        ];
    }

    public function createTemplate(int $gymId, array $data): WhatsAppTemplate
    {
        $variables = $this->extractVariables($data['body']);

        $template = WhatsAppTemplate::create([
            'gym_id' => $gymId,
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'body' => $data['body'],
            'category' => $data['category'] ?? 'utility',
            'variables' => $variables,
            'variable_count' => count($variables),
            'status' => 'draft',
            'is_default' => $data['is_default'] ?? false,
            'is_active' => true,
        ]);

        return $template;
    }

    public function updateTemplate(WhatsAppTemplate $template, array $data): WhatsAppTemplate
    {
        $needsResubmission = in_array($template->status, ['pending', 'approved', 'rejected'])
            && isset($data['body'])
            && $data['body'] !== $template->body;

        if (isset($data['body'])) {
            $data['variables'] = $this->extractVariables($data['body']);
            $data['variable_count'] = count($data['variables']);
        }

        $template->update($data);

        if ($needsResubmission && $template->twilio_template_sid) {
            $this->submitForVerification($template);
        }

        return $template;
    }

    public function submitForVerification(WhatsAppTemplate $template): array
    {
        try {
            $twilioTemplate = $this->masterClient->conversations->v1->configuration
                ->templates
                ->whatsappTemplates
                ->create([
                    'friendlyName' => $template->display_name,
                    'language' => 'en',
                    'body' => $template->body,
                    'category' => strtoupper($template->category),
                ]);

            $template->update([
                'twilio_template_sid' => $twilioTemplate->sid,
                'status' => 'pending',
            ]);

            Log::info('Template submitted for verification', [
                'template_id' => $template->id,
                'twilio_sid' => $twilioTemplate->sid,
            ]);

            return ['success' => true, 'status' => 'pending', 'sid' => $twilioTemplate->sid];
        } catch (RestException $e) {
            Log::error('Failed to submit template for verification', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncTemplateStatus(WhatsAppTemplate $template): void
    {
        if (! $template->twilio_template_sid) {
            return;
        }

        try {
            $twilioTemplate = $this->masterClient->conversations->v1->configuration
                ->templates
                ->whatsappTemplates($template->twilio_template_sid)
                ->fetch();

            $statusMap = [
                'PENDING' => 'pending',
                'APPROVED' => 'approved',
                'REJECTED' => 'rejected',
                'DISABLED' => 'disabled',
            ];

            $newStatus = $statusMap[$twilioTemplate->status] ?? $template->status;

            $update = ['status' => $newStatus];

            if ($twilioTemplate->status === 'REJECTED') {
                $update['rejection_reason'] = $twilioTemplate->rejectionReason ?? 'No reason provided';
            }

            $template->update($update);

            Log::info('Template status synced', [
                'template_id' => $template->id,
                'new_status' => $newStatus,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync template status', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getApprovedTemplates(int $gymId): \Illuminate\Database\Eloquent\Collection
    {
        return WhatsAppTemplate::where('gym_id', $gymId)
            ->where('status', 'approved')
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();
    }

    public function validateVariables(WhatsAppTemplate $template, array $variables): bool
    {
        $requiredVariables = $template->variable_count;
        $providedVariables = count($variables);

        return $providedVariables >= $requiredVariables;
    }

    public function parseVariables(string $body, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return $body;
    }

    protected function extractVariables(string $body): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

        $variables = [];
        $usedNumbers = [];

        foreach ($matches[1] as $number) {
            if (! in_array($number, $usedNumbers)) {
                $usedNumbers[] = $number;
                $variables[] = [
                    'name' => 'variable_' . $number,
                    'format' => 'text',
                    'example' => 'Example ' . $number,
                ];
            }
        }

        return $variables;
    }
}
