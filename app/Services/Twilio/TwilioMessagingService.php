<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use App\Exceptions\BroadcastConnectionException;
use App\Helpers\WhatsAppFailureClassifier;
use App\Helpers\WhatsAppSanitizer;
use App\Interfaces\MessagingInterface;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppUsageLog;
use App\Services\WhatsApp\WhatsAppBroadcastService;
use App\Services\WhatsApp\WhatsAppCreditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;
use Twilio\Exceptions\RestException;

/**
 * Twilio Messaging Service.
 *
 * Implements MessagingInterface for sending WhatsApp messages
 * via Twilio API.
 */
class TwilioMessagingService implements MessagingInterface
{
    private ?WhatsAppBroadcastService $broadcastService = null;

    public function __construct(
        protected TwilioContext $context,
        protected WhatsAppCreditService $credits,
    ) {}

    /**
     * Get or create the broadcast service instance.
     */
    private function getBroadcastService(): WhatsAppBroadcastService
    {
        return $this->broadcastService ??= new WhatsAppBroadcastService($this->context->gymId);
    }

    /**
     * Send a WhatsApp message.
     *
     * @return array{success: bool, message_id?: string|null, status?: string|null, error?: string|null, status_code?: int|null, data?: mixed}
     */
    public function sendMessage(string $to, string $message): array
    {
        if (str_starts_with($message, 'template:')) {
            return $this->sendMessageAsTemplate($to, $message);
        }

        return $this->sendTextMessage($to, $message);
    }

    /**
     * Send a text message.
     *
     * @return array{success: bool, message_id?: string|null, status?: string|null, error?: string|null, status_code?: int|null, data?: mixed}
     */
    private function sendTextMessage(string $to, string $message): array
    {
        $phoneNumber = $this->context->getSenderPhoneNumber();

        if (! $phoneNumber) {
            return [
                'success' => false,
                'error' => 'No phone number configured for this gym',
                'message_id' => null,
                'data' => null,
            ];
        }

        $deducted = false;
        try {
            $this->credits->checkAndDeduct(1);
            $deducted = true;

            $twilioMessage = $this->context->getClient()
                ->messages
                ->create("whatsapp:$to", [
                    'from' => "whatsapp:$phoneNumber",
                    'body' => $message,
                ]);

            return [
                'success' => true,
                'message_id' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
                'data' => [
                    'sid' => $twilioMessage->sid,
                    'status' => $twilioMessage->status,
                ],
            ];
        } catch (Throwable $e) {
            if ($deducted) {
                $this->credits->refund(1);
            }

            return $this->handleSendThrowable($e, 'Text message');
        }
    }

    /**
     * Send message with template syntax.
     *
     * @return array{success: bool, message_id?: string|null, status?: string|null, error?: string|null, status_code?: int|null, data?: mixed}
     */
    private function sendMessageAsTemplate(string $to, string $message): array
    {
        $parts = explode('|', substr($message, strlen('template:')));
        $templateName = trim($parts[0]);

        $variables = [];
        foreach (array_slice($parts, 1) as $index => $part) {
            $keyValue = explode('=', $part, 2);
            $variables[count($keyValue) === 2 ? trim($keyValue[0]) : (string) ($index + 1)] = trim($keyValue[1] ?? $part);
        }

        $result = $this->sendTemplateMessage($to, $templateName, $variables);
        $messageId = $result['message_id'] ?? null;
        $status = $result['status'] ?? null;

        $result['data'] = $result['success'] ? ['sid' => $messageId, 'status' => $status] : null;

        return $result;
    }

    /**
     * Send a template message.
     *
     * @return array{success: bool, message_id?: string|null, status?: string|null, error?: string|null, status_code?: int|null}
     */
    public function sendTemplateMessage(string $to, string $templateName, array $variables = []): array
    {
        $template = $this->findTemplate($templateName);

        if (! $template) {
            return [
                'success' => false,
                'error' => "Template '{$templateName}' not found or not approved. Only approved templates can be used.",
            ];
        }

        if (! $template->validateVariables($variables)) {
            return [
                'success' => false,
                'error' => 'Invalid variables provided for template',
            ];
        }

        $phoneNumber = $this->context->getSenderPhoneNumber();

        if (! $phoneNumber) {
            return [
                'success' => false,
                'error' => 'No phone number configured for this gym',
            ];
        }

        $deducted = false;
        try {
            $this->credits->checkAndDeduct(1);
            $deducted = true;

            $twilioMessage = $this->context->getClient()
                ->messages
                ->create("whatsapp:$to", [
                    'from' => "whatsapp:$phoneNumber",
                    'contentSid' => $template->twilio_template_sid,
                    'contentVariables' => json_encode($variables),
                ]);

            $this->logUsage($to, $template, $twilioMessage->sid);
            $template->incrementUsage();

            return [
                'success' => true,
                'message_id' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
            ];
        } catch (Throwable $e) {
            if ($deducted) {
                $this->credits->refund(1);
            }

            return $this->handleSendThrowable($e, 'Template message');
        }
    }

    /**
     * Send a test message.
     *
     * @return array{success: bool, message_id?: string|null, status?: string|null, error?: string|null, data?: mixed}
     */
    public function sendTestMessage(string $to, string $message): array
    {
        try {
            $phoneNumber = $this->context->useSandbox
                ? config('services.twilio.sandbox_number', '+15557626186')
                : $this->context->getSenderPhoneNumber();

            if (! $phoneNumber) {
                return [
                    'success' => false,
                    'error' => 'No phone number configured',
                    'message_id' => null,
                    'data' => null,
                ];
            }

            $twilioMessage = $this->context->getClient()
                ->messages
                ->create("whatsapp:$to", [
                    'from' => "whatsapp:$phoneNumber",
                    'body' => $message,
                ]);

            return [
                'success' => true,
                'message_id' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
                'data' => ['sid' => $twilioMessage->sid, 'status' => $twilioMessage->status],
            ];
        } catch (Throwable $e) {
            return $this->handleSendThrowable($e, 'Test message');
        }
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
        return [
            'success' => false,
            'error' => 'Message status tracking via API is not supported. Use webhooks.',
        ];
    }

    private function logUsage(string $recipient, WhatsAppTemplate $template, string $messageSid): void
    {
        WhatsAppUsageLog::query()->create([
            'gym_id' => $this->context->gymId,
            'whatsapp_template_id' => $template->id,
            'twilio_message_sid' => $messageSid,
            'recipient' => $recipient,
            'credits_used' => 1,
            'twilio_cost' => 0.15,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    // =========================
    // Private Helpers
    // =========================

    private function findTemplate(string $templateName): ?WhatsAppTemplate
    {
        return WhatsAppTemplate::query()
            ->where('name', $templateName)
            ->where('status', 'approved')
            ->where(function (Builder $q) {
                $q->where('gym_id', $this->context->gymId);
                if ($this->context->useSandbox) {
                    $q->orWhereNull('gym_id');
                }
            })
            ->first();
    }

    /**
     * @return array{success: false, error: string, status_code?: int|null, message_id: null, data: null}
     */
    private function handleSendThrowable(Throwable $e, string $context): array
    {
        if (WhatsAppFailureClassifier::isConnectionException($e)) {
            $sanitizedMessage = WhatsAppSanitizer::sanitizeError($e->getMessage());
            throw new BroadcastConnectionException($sanitizedMessage, $e->getCode(), $e);
        }

        if ($e instanceof RestException) {
            return $this->handleTwilioError($context, $e);
        }

        return $this->handleError($context, $e);
    }

    /**
     * @return array{success: false, error: string, status_code?: int|null, message_id: null, data: null}
     */
    private function handleTwilioError(string $context, RestException $e): array
    {
        $sanitizedMessage = WhatsAppSanitizer::sanitizeError($e->getMessage());
        Log::error("Twilio {$context} error", [
            'gym_id' => $this->context->gymId,
            'error' => $sanitizedMessage,
            'code' => $e->getCode(),
        ]);

        return [
            'success' => false,
            'error' => $sanitizedMessage,
            'status_code' => $e->getStatusCode(),
            'message_id' => null,
            'data' => null,
        ];
    }

    /**
     * @return array{success: false, error: string, status_code?: int|null, message_id: null, data: null}
     */
    private function handleError(string $context, Throwable $e): array
    {
        $sanitizedMessage = WhatsAppSanitizer::sanitizeError($e->getMessage());
        Log::error("WhatsApp {$context} error", [
            'gym_id' => $this->context->gymId,
            'error' => $sanitizedMessage,
        ]);

        return [
            'success' => false,
            'error' => $sanitizedMessage,
            'status_code' => $e->getCode(),
            'message_id' => null,
            'data' => null,
        ];
    }
}
