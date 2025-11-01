<?php

namespace App\Notifications;

use App\Services\TwilioWhatsAppService;
use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function send($notifiable, Notification $notification)
    {
        // Get the WhatsApp message from the notification
        $message = $notification->toWhatsApp($notifiable);

        if (! $message) {
            return;
        }

        // Get the gym/tenant ID
        $gymId = $notifiable->gym_id ?? $notifiable->gym->id ?? null;

        if (! $gymId) {
            throw new \Exception('Unable to determine gym ID for WhatsApp notification');
        }

        // Initialize Twilio service
        $twilioService = new TwilioWhatsAppService($gymId);

        // Check if this is a template message or plain text
        $isTemplate = isset($message['template_sid']);

        if ($isTemplate) {
            // Send template message
            $result = $twilioService->sendTemplateMessage(
                $message['to'],
                $message['template_sid'],
                $message['template_variables'] ?? []
            );
        } else {
            // Send plain text message (sandbox mode)
            $result = $twilioService->sendMessage(
                $message['to'],
                $message['body']
            );
        }

        if (! $result['success']) {
            Log::error('WhatsApp notification failed', [
                'error' => $result['error'] ?? 'Unknown error',
                'notifiable_id' => $notifiable->id,
            ]);

            // Optionally throw exception to retry via queue
            // throw new \Exception($result['error']);
        }

        return $result;
    }
}
