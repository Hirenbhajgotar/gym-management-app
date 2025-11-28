<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\NotificationTemplate;
use App\Models\User;
use Filament\Facades\Filament;

class EnhancedNotificationService extends NotificationService
{
    public function sendNotification(
        User $recipient,
        string $type,
        array $channels = ['whatsapp'],
        array $variables = []
    ): void {
        $tenantId = Filament::getTenant()->id;
        $gymOwner = $this->getGymOwner($tenantId);

        $settings = NotificationSetting::where('gym_id', $tenantId)
            ->where('gym_owner_id', $gymOwner->id)
            ->first();

        if (! $settings) {
            $this->logError($recipient, 'No notification settings found');

            return;
        }

        foreach ($channels as $channel) {
            if (! $settings->isNotificationEnabled($type, $channel)) {
                continue;
            }

            // Check if WhatsApp account is properly set up
            if ($channel === 'whatsapp' && $settings->verification_status !== 'verified') {
                // Fallback to SMS or email
                $fallbackChannel = $this->getFallbackChannel($channel);
                if ($fallbackChannel && $settings->isNotificationEnabled($type, $fallbackChannel)) {
                    $channel = $fallbackChannel;
                } else {
                    $this->logError($recipient, 'WhatsApp not verified, no fallback available');

                    continue;
                }
            }

            $template = NotificationTemplate::where('gym_id', $tenantId)
                ->where('type', $type)
                ->where('channel', $channel)
                ->where('is_active', true)
                ->first();

            if (! $template) {
                continue;
            }

            $message = $template->parseTemplate($variables);

            if ($channel === 'whatsapp') {
                $this->sendWhatsAppFromOwnerNumber($recipient, $settings, $type, $message);
            } elseif ($channel === 'email') {
                $this->sendEmailNotification($recipient, $template, $variables);
            }
        }
    }

    private function sendWhatsAppFromOwnerNumber(
        User $recipient,
        NotificationSetting $settings,
        string $type,
        string $message
    ): void {
        if (! $recipient->phone) {
            return;
        }

        // Use gym owner's individual Twilio account
        $whatsappService = new TwilioWhatsAppService($settings->gym_id);
        $result = $whatsappService->sendMessage($recipient->phone, $message);

        // Enhanced logging with owner's number info
        NotificationLog::create([
            'gym_id' => $settings->gym_id,
            'user_id' => $recipient->id,
            'type' => $type,
            'channel' => 'whatsapp',
            'status' => $result['success'] ? 'sent' : 'failed',
            'message' => $message,
            'response_data' => array_merge($result['data'] ?? [], [
                'from_number' => $settings->whatsapp_business_number,
                'to_number' => $recipient->phone,
            ]),
            'external_id' => $result['message_id'] ?? null,
            'sent_at' => $result['success'] ? now() : null,
        ]);
    }

    private function getFallbackChannel(string $channel): ?string
    {
        $fallbacks = [
            'whatsapp' => 'sms',
            'sms' => 'email',
        ];

        return $fallbacks[$channel] ?? null;
    }

    private function logError(User $recipient, string $error): void
    {
        NotificationLog::create([
            'gym_id' => Filament::getTenant()->id,
            'user_id' => $recipient->id,
            'type' => 'system_error',
            'channel' => 'system',
            'status' => 'failed',
            'message' => $error,
            'response_data' => null,
            'external_id' => null,
            'sent_at' => null,
        ]);
    }
}
