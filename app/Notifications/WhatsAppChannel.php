<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Providers\WhatsAppProviderFactory;
use App\Services\GymContextResolver;
use App\Services\WhatsApp\WhatsAppCreditService;
use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * WhatsApp channel for sending notifications via WhatsApp.
 *
 * This channel uses WhatsAppProviderFactory to create the appropriate
 * provider based on the configured provider in services.php config.
 */
class WhatsAppChannel
{
    public function __construct(
        private readonly GymContextResolver $gymContextResolver,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function send(mixed $notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return null;
        }

        /**
         * Get the WhatsApp message from the notification
         *
         * @var array<string, mixed>|null $message
         */
        $message = $notification->toWhatsApp($notifiable);

        if (! is_array($message)) {
            Log::warning('Invalid WhatsApp message format', ['message' => $message]);

            return null;
        }

        $hasTemplateId = Arr::hasAny($message, ['template_sid', 'meta_template_id']);

        if (blank($message) || (! $hasTemplateId && ! Arr::has($message, 'to'))) {
            Log::warning('Invalid WhatsApp message format', ['message' => $message]);

            return null;
        }

        // Ensure notifiable is an object for property access
        if (! is_object($notifiable)) {
            Log::error('WhatsApp notification notifiable is not an object', [
                'notification_type' => get_class($notification),
            ]);

            return null;
        }

        // Get the gym/tenant ID
        $gymId = (int) $this->gymContextResolver->resolve($notifiable, $notification);

        if (! $gymId) {
            Log::error('Unable to determine gym ID for WhatsApp notification', [
                'notifiable_id' => property_exists($notifiable, 'id') ? $notifiable->id : null,
                'notifiable_type' => get_class($notifiable),
                'notification_type' => get_class($notification),
            ]);

            return null;
        }

        // Check if this is a system message (super-admin) or owner message
        $isSystemMessage = method_exists($notification, 'isSystemMessage') && (bool) $notification->isSystemMessage();

        // Create provider using factory (based on config and system message flag)
        $provider = WhatsAppProviderFactory::create($gymId, $isSystemMessage);

        $logContext = [
            'notifiable_id' => property_exists($notifiable, 'id') ? $notifiable->id : null,
            'gym_id' => $gymId,
            'provider' => WhatsAppProviderFactory::getProviderForGym($gymId),
        ];

        try {
            $sendTo = Arr::string($message, 'to');

            if ($hasTemplateId) {
                $templateName = Arr::string($message, 'template_name');
                $templateSid = Arr::string($message, 'template_sid');
                $metaTemplateId = Arr::string($message, 'meta_template_id');
                $templateIdOrName = $metaTemplateId ?: $templateSid ?: $templateName;

                /** @var array<string, string> $templateVariables */
                $templateVariables = Arr::array($message, 'template_variables', []);

                $result = $provider->sendTemplateMessage(
                    $sendTo,
                    $templateIdOrName,
                    $templateVariables,
                );

                if (! Arr::boolean($result, 'success', false)) {
                    throw new RuntimeException(Arr::string($result, 'error', 'WhatsApp send failed'));
                }

                return $result;
            }

            if (Arr::string($message, 'body')) {
                $body = Arr::string($message, 'body');
                $result = $provider->sendMessage($sendTo, $body);

                if (! Arr::boolean($result, 'success', false)) {
                    throw new RuntimeException(Arr::string($result, 'error', 'WhatsApp send failed'));
                }

                return $result;
            }
        } catch (Exception $e) {
            Log::error('WhatsApp notification encountered an exception', [
                ...$logContext,
                'error' => $e->getMessage(),
            ]);

            $this->refundCredit($gymId, $notifiable);
        }

        return null;
    }

    private function refundCredit(int $gymId, object $notifiable): void
    {
        try {
            $creditService = new WhatsAppCreditService($gymId);
            $creditService->refund(1);
        } catch (Exception $th) {
            Log::critical('Failed to refund WhatsApp credit', [
                'notifiable_id' => property_exists($notifiable, 'id') ? $notifiable->id : null,
                'gym_id' => $gymId,
                'provider' => WhatsAppProviderFactory::getProviderForGym($gymId),
                'error' => $th->getMessage(),
            ]);
        }
    }
}
