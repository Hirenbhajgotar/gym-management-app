<?php

namespace App\Services;

use App\Interfaces\WhatsAppServiceInterface;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppUsageLog;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

/**
 * @deprecated: This class is deprected and will be removed in future version.
 */
class TwilioWhatsAppService implements WhatsAppServiceInterface
{
    private string $tenantId;

    private ?Client $client;

    private bool $useSandbox = true;

    public function __construct(string $tenantId, ?Client $client = null)
    {
        $this->tenantId = $tenantId;
        $this->client = $client;
        $this->useSandbox = config('services.twilio.use_sandbox', true);
    }

    public function sendMessage(string $to, string $message): array
    {
        // Detect template messages (format: "template:template_name|var1=val1|var2=val2")
        if (str_starts_with($message, 'template:')) {
            return $this->sendMessageAsTemplate($to, $message);
        }

        return $this->sendTextMessage($to, $message);
    }

    public function sendTestMessage(string $to, string $message): array
    {
        try {
            $client = $this->getClient();

            // Use sandbox sender when in sandbox mode
            $phoneNumber = config('services.twilio.sandbox_number', '+15557626186');

            if (! $phoneNumber) {
                return [
                    'success' => false,
                    'error' => 'No phone number configured for this gym',
                    'message_id' => null,
                    'data' => null,
                ];
            }

            $twilioMessage = $client->messages->create("whatsapp:$to", [
                'from' => "whatsapp:$phoneNumber",
                'body' => $message,
            ]);

            Log::info('Twilio WhatsApp text message sent', [
                'gym_id' => $this->tenantId,
                'to' => $to,
                'message' => $message,
                'message_id' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
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
            Log::error('Twilio WhatsApp text message error', [
                'gym_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message_id' => null,
                'data' => null,
            ];
        } catch (Exception $e) {
            Log::error('WhatsApp text message error', [
                'gym_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message_id' => null,
                'data' => null,
            ];
        }
    }

    public function sendTemplateTestMessage(string $to): array
    {
        try {
            $client = $this->getClient();

            // Use sandbox sender when in sandbox mode
            $phoneNumber = config('services.twilio.sandbox_number', '+15557626186');

            throw_unless($phoneNumber, Exception::class, 'No phone number configured for this gym');

            $contentSid = config('services.twilio.whatsapp_integration_active_v5_template_sid');

            throw_unless($contentSid, Exception::class, 'No template SID configured for this gym');

            $gym = Gym::query()->where('id', $this->tenantId)->first();

            throw_unless($gym, Exception::class, 'No gym found for this tenant ID');

            $twilioMessage = $client->messages->create("whatsapp:$to", [
                'from' => "whatsapp:$phoneNumber",
                'contentSid' => $contentSid,
                'contentVariables' => json_encode([
                    1 => $gym->name,
                ]),
            ]);

            Log::info('Twilio WhatsApp template message sent', [
                'gym_id' => $this->tenantId,
                'to' => $to,
                'message_id' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
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
            Log::error('Twilio WhatsApp text message error', [
                'gym_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message_id' => null,
                'data' => null,
            ];
        } catch (Exception $e) {
            Log::error('WhatsApp text message error', [
                'gym_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message_id' => null,
                'data' => null,
            ];
        }
    }

    protected function sendTextMessage(string $to, string $message): array
    {
        try {
            $this->checkAndDeductCredits(1);

            $client = $this->getClient();

            $account = WhatsAppAccount::query()->where('gym_id', $this->tenantId)->first();
            $phoneNumber = $account?->phoneNumber?->phone_number;

            if (! $phoneNumber) {
                return [
                    'success' => false,
                    'error' => 'No phone number configured for this gym',
                    'message_id' => null,
                    'data' => null,
                ];
            }

            $twilioMessage = $client->messages->create("whatsapp:$to", [
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
            Log::error('Twilio WhatsApp text message error', [
                'gym_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message_id' => null,
                'data' => null,
            ];
        } catch (Exception $e) {
            Log::error('WhatsApp text message error', [
                'gym_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message_id' => null,
                'data' => null,
            ];
        }
    }

    protected function sendMessageAsTemplate(string $to, string $message): array
    {
        // Parse format: "template:template_name|var1=val1|var2=val2"
        $parts = explode('|', substr($message, strlen('template:')));
        $templateName = trim($parts[0]);

        $variables = [];
        foreach (array_slice($parts, 1) as $index => $part) {
            $keyValue = explode('=', $part, 2);
            if (count($keyValue) === 2) {
                $variables[trim($keyValue[0])] = trim($keyValue[1]);
            } else {
                // Positional variable (1-indexed for Twilio content variables)
                $variables[(string) ($index + 1)] = trim($part);
            }
        }

        $result = $this->sendTemplateMessage($to, $templateName, $variables);

        // Normalize response to include 'data' key expected by NotificationService
        $result['data'] = $result['success'] ? [
            'sid' => $result['message_id'] ?? null,
            'status' => $result['status'] ?? null,
        ] : null;

        return $result;
    }

    private function isSystemTenant(): bool
    {
        return $this->tenantId === config('app.system_tenant_id', 'system') ||
            $this->tenantId === 'system';
    }

    private function getClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        if ($this->useSandbox || $this->isSystemTenant()) {
            return new Client(
                config('services.twilio.master_sid'),
                config('services.twilio.master_token'),
            );
        }

        $account = WhatsAppAccount::query()->where('gym_id', $this->tenantId)->first();

        throw_unless($account, Exception::class, 'No WhatsApp account found for this gym');

        return new Client(
            $account->twilio_account_sid,
            $account->twilio_auth_token,
        );
    }

    public function sendTemplateMessage(
        string $to,
        string $templateName,
        array $variables = []
    ): array {
        try {
            $query = WhatsAppTemplate::query()
                ->where('name', $templateName)
                ->where('status', 'approved')
                ->where(function (Builder $q) {
                    $q->where('gym_id', $this->tenantId);

                    if ($this->useSandbox) {
                        $q->orWhereNull('gym_id');
                    }
                });

            $template = $query->first();

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

            $this->checkAndDeductCredits(1);

            $client = $this->getClient();

            $account = WhatsAppAccount::query()->where('gym_id', $this->tenantId)->first();
            $phoneNumber = $account?->phoneNumber?->phone_number;

            if (! $phoneNumber) {
                return [
                    'success' => false,
                    'error' => 'No phone number configured for this gym',
                ];
            }

            $twilioMessage = $client->messages->create("whatsapp:$to", [
                'from' => "whatsapp:$phoneNumber",
                'contentSid' => $template->twilio_template_sid,
                'contentVariables' => json_encode($variables),
            ]);

            $this->logUsage($to, $template, $twilioMessage);

            $template->incrementUsage();

            return [
                'success' => true,
                'message_id' => $twilioMessage->sid,
                'status' => $twilioMessage->status,
            ];
        } catch (RestException $e) {
            Log::error('Twilio WhatsApp message error', [
                'gym_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        } catch (Exception $e) {
            Log::error('WhatsApp message error', [
                'gym_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function sendBroadcast(int $broadcastId): array
    {
        $results = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        try {
            $broadcast = WhatsAppBroadcast::with('template')
                ->where('gym_id', $this->tenantId)
                ->findOrFail($broadcastId);

            $broadcast->markAsSending();

            $query = $broadcast->recipients()->where('status', 'pending');
            $results['total'] = $query->count();

            $query->chunkById(100, function ($recipients) use ($broadcast, &$results) {
                foreach ($recipients as $recipient) {
                    try {
                        $variables = $this->prepareVariables($broadcast, $recipient);

                        $result = $this->sendTemplateMessage(
                            $recipient->phone_number,
                            $broadcast->template->name,
                            $variables
                        );

                        if ($result['success']) {
                            $recipient->markAsSent($result['message_id']);
                            $broadcast->incrementSentCount();
                            $results['sent']++;
                        } else {
                            $recipient->markAsFailed($result['error']);
                            $broadcast->incrementFailedCount();
                            $results['failed']++;
                        }
                    } catch (Exception $e) {
                        $recipient->markAsFailed($e->getMessage());
                        $broadcast->incrementFailedCount();
                        $results['failed']++;
                    }

                    // Add a small delay to avoid hitting Twilio rate limits (approx. 20 messages per second)
                    Sleep::usleep(50000);
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

    protected function prepareVariables(WhatsAppBroadcast $broadcast, WhatsAppBroadcastRecipient $recipient): array
    {
        $template = $broadcast->template;
        $customVariables = $broadcast->message_variables ?? [];

        // Load the member with relationships needed for variable resolution
        $member = GymMember::with(['gym', 'membershipPlan', 'trainer', 'latestPayment'])
            ->find($recipient->member_id);

        $resolver = resolve(WhatsAppVariableResolver::class);

        return $resolver->resolve($template, [
            'member' => $member,
            'custom' => $customVariables,
        ]);
    }

    protected function checkAndDeductCredits(int $count = 1): void
    {
        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->tenantId)->first();

        throw_if(! $credit || ! $credit->canSendMessage($count), Exception::class, 'Insufficient message credits. Please purchase more credits.');

        if ($credit->hasWelcomeCredits() && $credit->getWelcomeCreditsRemaining() >= $count) {
            $credit->useWelcomeCredit();
        } else {
            $credit->deductCredits($count);
        }
    }

    protected function logUsage(
        string $recipient,
        WhatsAppTemplate $template,
        $twilioMessage
    ): void {
        WhatsAppUsageLog::query()->create([
            'gym_id' => $this->tenantId,
            'whatsapp_template_id' => $template->id,
            'twilio_message_sid' => $twilioMessage->sid,
            'recipient' => $recipient,
            'credits_used' => 1,
            'twilio_cost' => 0.15,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function getCreditBalance(): array
    {
        $credit = WhatsAppMessageCredit::query()->where('gym_id', $this->tenantId)->first();

        if (! $credit) {
            return [
                'total_credits' => 0,
                'used_credits' => 0,
                'remaining_credits' => 0,
                'welcome_credits_remaining' => 0,
                'can_send' => false,
            ];
        }

        return [
            'total_credits' => $credit->total_credits,
            'used_credits' => $credit->used_credits,
            'remaining_credits' => $credit->remaining_credits,
            'welcome_credits_remaining' => $credit->getWelcomeCreditsRemaining(),
            'can_send' => $credit->canSendMessage(),
            'usage_percentage' => $credit->getUsagePercentage(),
        ];
    }

    public function getMonthlyUsage(int $year, int $month): array
    {
        $logs = WhatsAppUsageLog::query()->where('gym_id', $this->tenantId)
            ->whereYear('sent_at', $year)
            ->whereMonth('sent_at', $month)
            ->get();

        return [
            'total_messages' => $logs->count(),
            'delivered' => $logs->where('status', 'delivered')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'total_cost' => $logs->sum('twilio_cost'),
        ];
    }
}
