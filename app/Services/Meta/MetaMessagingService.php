<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Enums\WhatsAppTemplateStatusEnum;
use App\Interfaces\MessagingInterface;
use App\Models\Gym;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppBroadcastService;
use App\Services\WhatsApp\WhatsAppCreditService;
use Illuminate\Contracts\Database\Query\Builder;

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
     *
     * @return array{success: bool, message_id?: ?string, status?: ?string, error?: ?string, data?: mixed}
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

        $response = $this->api->post("/{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message,
            ],
        ]);

        if ($response['success']) {
            $rawMessageId = isset($response['data']['messages'][0]['id']) ? $response['data']['messages'][0]['id'] : null;
            $messageId = is_string($rawMessageId) ? $rawMessageId : null;

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
    public function sendTemplateMessage(string $to, string $templateIdOrName, array $variables = []): array
    {
        $phoneNumberId = $this->context->getPhoneNumberId();

        if (! $phoneNumberId) {
            return [
                'success' => false,
                'error' => 'Phone number not configured',
            ];
        }

        $wabaId = $this->context->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
            ];
        }

        // Find the template
        $template = WhatsAppTemplate::query()->where('gym_id', $this->context->gymId)
            ->where(function (Builder $query) use ($templateIdOrName) {
                $query->where('meta_template_id', $templateIdOrName)
                    ->orWhere('twilio_template_sid', $templateIdOrName)
                    ->orWhere('name', $templateIdOrName);
            })
            ->where('status', WhatsAppTemplateStatusEnum::APPROVED)
            ->first();

        if (! $template) {
            return [
                'success' => false,
                'error' => "Template '{$templateIdOrName}' not found or not approved",
            ];
        }

        $this->credits->checkAndDeduct(1);

        // Build template payload
        $templatePayload = [
            'name' => $template->name,
        ];

        if (filled($variables)) {
            $templatePayload['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(
                        fn ($value, $key) => [
                            'type' => 'text',
                            'text' => $value,
                        ],
                        $variables,
                        array_keys($variables)
                    ),
                ],
            ];
        }
        $templatePayload['language'] = ['code' => 'en'];

        $response = $this->api->post("/{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => $templatePayload,
        ]);

        if ($response['success']) {
            $rawMessageId = isset($response['data']['messages'][0]['id']) ? $response['data']['messages'][0]['id'] : null;
            $messageId = is_string($rawMessageId) ? $rawMessageId : null;

            $this->credits->logUsage($to, $template, $messageId);
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
     *
     * @param  string  $messageId  Message SID or ID
     * @return array{success: bool, status?: string, delivered_at?: ?string, read_at?: ?string, error?: string, data?: array<array-key, mixed>}
     */
    public function getMessageStatus(string $messageId): array
    {
        return $this->api->get("/{$messageId}");
    }
}
