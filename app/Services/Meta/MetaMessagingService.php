<?php

namespace App\Services\Meta;

use App\Interfaces\MessagingInterface;
use App\Models\Gym;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppBroadcastService;
use App\Services\WhatsApp\WhatsAppCreditService;

/**
 * Meta Messaging Service.
 *
 * Implements MessagingInterface for sending WhatsApp messages
 * via Meta Graph API.
 */
class MetaMessagingService implements MessagingInterface
{
    private ?WhatsAppBroadcastService $broadcastService = null;

    public function __construct(
        protected MetaContext $context,
        protected MetaApiClient $api,
        protected WhatsAppCreditService $credits
    ) {}

    private function getBroadcastService(): WhatsAppBroadcastService
    {
        return $this->broadcastService ??= new WhatsAppBroadcastService($this->context->gymId);
    }

    /**
     * Send a WhatsApp message.
     */
    public function sendMessage(string $to, string $message): array
    {
        if (! $this->context->hasAccount()) {
            return [
                'success' => false,
                'error' => 'WhatsApp account not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        if (! $this->context->hasAccessToken()) {
            return [
                'success' => false,
                'error' => 'Meta access token not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        $phoneNumberId = $this->context->getPhoneNumberId();

        if (! $phoneNumberId) {
            return [
                'success' => false,
                'error' => 'Phone number not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        $this->credits->checkAndDeduct(1);

        $response = $this->api->post("/{$wabaId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message,
            ],
        ]);

        if ($response['success']) {
            $messageId = $response['data']['messages'][0]['id'] ?? null;

            $this->credits->logUsage($to, null, $messageId);

            return [
                'success' => true,
                'message_id' => $messageId,
                'status' => 'sent',
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'error' => $response['error'] ?? 'Failed to send message',
            'status' => 'failed',
            'message_id' => null,
        ];
    }

    /**
     * Send a template message.
     */
    public function sendTemplateMessage(string $to, string $templateName, array $variables = []): array
    {
        $phoneNumberId = $this->context->getPhoneNumberId();

        if (! $phoneNumberId) {
            return [
                'success' => false,
                'error' => 'Phone number not configured',
            ];
        }

        // Find the template
        $template = WhatsAppTemplate::query()->where('gym_id', $this->context->gymId)
            ->where('name', $templateName)
            ->where('status', 'approved')
            ->first();

        if (! $template) {
            return [
                'success' => false,
                'error' => "Template '{$templateName}' not found or not approved",
            ];
        }

        $this->credits->checkAndDeduct(1);

        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
            ];
        }

        // Build template payload
        $templatePayload = [
            'name' => $templateName,
        ];

        if (filled($variables)) {
            $templatePayload['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(
                        fn ($value, $key) => [
                            'type' => 'text',
                            'parameter' => $value,
                        ],
                        $variables,
                        array_keys($variables)
                    ),
                ],
            ];
        }

        $response = $this->api->post("/{$wabaId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => $templatePayload,
        ]);

        if ($response['success']) {
            $messageId = $response['data']['messages'][0]['id'] ?? null;

            $this->credits->logUsage($to, $template->id, $messageId);
            $template->incrementUsage();

            return [
                'success' => true,
                'message_id' => $messageId,
                'status' => 'sent',
            ];
        }

        return [
            'success' => false,
            'error' => $response['error'] ?? 'Failed to send template message',
        ];
    }

    /**
     * Send a test message.
     */
    public function sendTestMessage(string $to, string $message): array
    {
        return $this->sendMessage($to, $message);
    }

    /**
     * Send a test WhatsApp message to verify setup.
     */
    public function sendTestWhatsAppMessage(int $gymId, ?string $to = null): array
    {
        /**
         * @var WhatsAppPhoneNumber|null $phoneNumber
         */
        $account = $this->context->getAccount();
        $phoneNumber = $account?->phoneNumber?->phone_number;
        $recipient = $to ?? $phoneNumber;

        if (! $recipient) {
            return [
                'success' => false,
                'error' => 'No phone number configured',
            ];
        }

        $gym = Gym::query()->find($gymId);
        $gymName = $gym?->name ?? 'Your Gym';

        $message = "WhatsApp Integration Active!\n\nHello from {$gymName}!";

        return $this->sendMessage($recipient, $message);
    }

    /**
     * Send a broadcast to multiple recipients.
     */
    public function sendBroadcast(int $broadcastId): void
    {
        $this->getBroadcastService()->dispatchBroadcast($broadcastId);
    }

    /**
     * Get message status.
     */
    public function getMessageStatus(string $messageId): array
    {
        return $this->api->get("/{$messageId}");
    }
}
