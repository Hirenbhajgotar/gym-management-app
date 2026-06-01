<?php

namespace App\Services\Twilio;

use App\Interfaces\TestMessagingInterface;
use App\Models\Gym;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Log;

/**
 * Twilio Test Message Service.
 *
 * Implements TestMessagingInterface for sending test messages
 * to verify configuration.
 */
class TwilioTestMessageService implements TestMessagingInterface
{
    public function __construct(
        protected int $gymId,
        protected TwilioMessagingService $messagingService,
    ) {}

    /**
     * Get if using sandbox mode.
     */
    private function isSandbox(): bool
    {
        return config('services.twilio.use_sandbox', true);
    }

    /**
     * Send a test WhatsApp message.
     */
    public function sendTestWhatsAppMessage(int $gymId, ?string $to = null): array
    {
        try {
            $account = WhatsAppAccount::where('gym_id', $gymId)->first();

            if (! $account) {
                return [
                    'success' => false,
                    'error' => 'WhatsApp account not found',
                ];
            }

            if (empty($account->twilio_account_sid) || empty($account->twilio_auth_token)) {
                return [
                    'success' => false,
                    'error' => 'Twilio credentials are not configured.',
                ];
            }

            $gym = Gym::find($gymId);
            $gym = Gym::find($gymId);
            $gymName = $gym?->name ?? 'Your Gym';

            $phoneNumberRecord = $account->phoneNumber()->first();
            $recipientNumber = $to ?? $phoneNumberRecord?->phone_number;

            if (! $recipientNumber) {
                return [
                    'success' => false,
                    'error' => 'No phone number configured.',
                ];
            }

            if ($this->isSandbox()) {
                $messageBody = "🎉 *WhatsApp Integration Active!*\n\n";
                $messageBody .= "Hello from {$gymName}! 👋\n\n";
                $messageBody .= "Your WhatsApp Business integration is now successfully set up.\n\n";
                $messageBody .= '_Sent at: ' . now()->format('M d, Y H:i A') . '_';

                $result = $this->messagingService->sendMessage($recipientNumber, $messageBody);
            } else {
                $templateSid = config('services.twilio.whatsapp_integration_active_v5_template_sid');

                if (! $templateSid) {
                    return [
                        'success' => false,
                        'error' => 'No approved WhatsApp template found.',
                        'requires_template' => true,
                    ];
                }

                $result = $this->messagingService->sendTemplateMessage($recipientNumber, '', ['1' => $gymName]);
            }

            Log::info('Test WhatsApp message sent', [
                'gym_id' => $gymId,
                'success' => $result['success'] ?? false,
                'mode' => $this->isSandbox() ? 'sandbox' : 'production',
            ]);

            return [
                'success' => $result['success'] ?? false,
                'message_id' => $result['message_id'] ?? null,
                'status' => $result['status'] ?? null,
                'to' => $recipientNumber,
                'mode' => $this->isSandbox() ? 'sandbox' : 'production',
                'error' => $result['error'] ?? null,
                'requires_template' => $result['requires_template'] ?? false,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to send test WhatsApp message', [
                'gym_id' => $gymId ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
