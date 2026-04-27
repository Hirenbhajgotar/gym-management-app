<?php

namespace App\Services\Twilio;

use App\Interfaces\MessagingInterface;
use App\Models\GymMember;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppUsageLog;
use App\Services\WhatsAppVariableResolver;
use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;

/**
 * Twilio Messaging Service.
 *
 * Implements MessagingInterface for sending WhatsApp messages
 * via Twilio API.
 */
class TwilioMessagingService implements MessagingInterface
{
    public function __construct(
        protected TwilioContext $context,
        protected TwilioCreditService $credits,
    ) {}

    /**
     * Send a WhatsApp message.
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
     */
    private function sendTextMessage(string $to, string $message): array
    {
        try {
            $this->credits->checkAndDeduct(1);

            $phoneNumber = $this->context->getSenderPhoneNumber();

            if (! $phoneNumber) {
                return [
                    'success' => false,
                    'error' => 'No phone number configured for this gym',
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
                'data' => [
                    'sid' => $twilioMessage->sid,
                    'status' => $twilioMessage->status,
                ],
            ];
        } catch (RestException $e) {
            return $this->handleTwilioError('Template message', $e);
        } catch (Exception $e) {
            return $this->handleError('Template message', $e);
        }
    }

    /**
     * Send message with template syntax.
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
        $result['data'] = $result['success'] ? ['sid' => $result['message_id'], 'status' => $result['status']] : null;

        return $result;
    }

    /**
     * Send a template message.
     */
    public function sendTemplateMessage(string $to, string $templateName, array $variables = []): array
    {
        try {
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

            $this->credits->checkAndDeduct(1);

            $phoneNumber = $this->context->getSenderPhoneNumber();

            if (! $phoneNumber) {
                return [
                    'success' => false,
                    'error' => 'No phone number configured for this gym',
                ];
            }

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
        } catch (RestException $e) {
            return $this->handleTwilioError('Template message', $e);
        } catch (Exception $e) {
            return $this->handleError('Template message', $e);
        }
    }

    /**
     * Send a test message.
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
        } catch (RestException $e) {
            return $this->handleTwilioError('Test message', $e);
        }
    }

    /**
     * Send a broadcast to multiple recipients.
     */
    public function sendBroadcast(int $broadcastId): array
    {
        $results = ['total' => 0, 'sent' => 0, 'failed' => 0];

        try {
            $broadcast = WhatsAppBroadcast::with('template')
                ->where('gym_id', $this->context->gymId)
                ->findOrFail($broadcastId);

            $broadcast->markAsSending();

            $query = $broadcast->recipients()->where('status', 'pending');
            $results['total'] = $query->count();

            $query->chunkById(100, function ($recipients) use ($broadcast, &$results) {
                foreach ($recipients as $recipient) {
                    $this->processBroadcastRecipient($broadcast, $recipient, $results);
                    usleep(50000); // Rate limit protection
                }
            });

            $broadcast->markAsCompleted();

            Log::info('Broadcast completed', [
                'broadcast_id' => $broadcastId,
                'results' => $results,
            ]);

            return $results;
        } catch (Exception $e) {
            Log::error('Broadcast error', [
                'broadcast_id' => $broadcastId,
                'error' => $e->getMessage(),
            ]);

            return $results;
        }
    }

    private function processBroadcastRecipient(
        WhatsAppBroadcast $broadcast,
        WhatsAppBroadcastRecipient $recipient,
        array &$results
    ): void {
        try {
            $variables = $this->prepareBroadcastVariables($broadcast, $recipient);
            $result = $this->sendTemplateMessage($recipient->phone_number, $broadcast->template->name, $variables);

            if ($result['success']) {
                $recipient->markAsSent($result['message_id']);
                $broadcast->incrementSentCount();
                $results['sent']++;
            } else {
                $recipient->markAsFailed($result['error']);
                $broadcast->incrementFailedCount();
                $results['failed']++;
            }
        } catch (\Exception $e) {
            $recipient->markAsFailed($e->getMessage());
            $broadcast->incrementFailedCount();
            $results['failed']++;
        }
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
        WhatsAppUsageLog::create([
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

    /**
     * Prepare variables for broadcast message.
     */
    private function prepareBroadcastVariables(WhatsAppBroadcast $broadcast, WhatsAppBroadcastRecipient $recipient): array
    {
        $member = GymMember::with(['gym', 'membershipPlan', 'trainer', 'latestPayment'])
            ->find($recipient->member_id);

        return app(WhatsAppVariableResolver::class)->resolve(
            $broadcast->template,
            ['member' => $member, 'custom' => $broadcast->message_variables ?? []]
        );
    }

    private function findTemplate(string $templateName): ?WhatsAppTemplate
    {
        return WhatsAppTemplate::query()
            ->where('name', $templateName)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->where('gym_id', $this->context->gymId);
                if ($this->context->useSandbox) {
                    $q->orWhereNull('gym_id');
                }
            })
            ->first();
    }

    private function handleTwilioError(string $context, RestException $e): array
    {
        Log::error("Twilio {$context} error", [
            'gym_id' => $this->context->gymId,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);

        return ['success' => false, 'error' => $e->getMessage(), 'message_id' => null, 'data' => null];
    }

    private function handleError(string $context, \Exception $e): array
    {
        Log::error("WhatsApp {$context} error", [
            'gym_id' => $this->context->gymId,
            'error' => $e->getMessage(),
        ]);

        return ['success' => false, 'error' => $e->getMessage(), 'message_id' => null, 'data' => null];
    }
}
