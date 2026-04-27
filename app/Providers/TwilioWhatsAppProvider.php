<?php

namespace App\Providers;

use App\Enums\WabaModeEnum;
use App\Enums\WabaStatusEnum;
use App\Enums\WhatsAppAccountStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Helpers\ParserHelper;
use App\Interfaces\AccountManagementInterface;
use App\Interfaces\CreditManagementInterface;
use App\Interfaces\MessagingInterface;
use App\Interfaces\PhoneNumberManagementInterface;
use App\Interfaces\TemplateManagementInterface;
use App\Interfaces\TestMessagingInterface;
use App\Interfaces\WabaSetupInterface;
use App\Models\Gym;
use App\Models\GymMember;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppUsageLog;
use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;
use Twilio\Rest\Messaging\V2\ChannelsSenderModels;

/**
 * Twilio WhatsApp Provider.
 *
 * This provider implements all WhatsApp messaging interfaces
 * with inlined Twilio service logic.
 */
class TwilioWhatsAppProvider implements AccountManagementInterface, CreditManagementInterface, MessagingInterface, PhoneNumberManagementInterface, TemplateManagementInterface, TestMessagingInterface, WabaSetupInterface
{
    // =========================
    // Private State
    // =========================

    protected int $gymId;

    protected ?Client $client = null;

    protected bool $useSandbox = true;

    protected string $masterAccountSid;

    protected string $masterAuthToken;

    protected string $sharedWabaId;

    protected bool $useIndividualWaba;

    protected ?Client $masterClient = null;

    protected ?WhatsAppAccount $whatsAppAccount = null;

    protected ?WhatsAppPhoneNumber $whatsAppPhoneNumber = null;

    // =========================
    // Constructor
    // =========================

    public function __construct(int $gymId)
    {
        $this->gymId = $gymId;
        $this->useSandbox = config('services.twilio.use_sandbox', true);
        $this->sharedWabaId = config('services.twilio.shared_waba_id', '');
        $this->useIndividualWaba = config('services.twilio.use_individual_waba', false);
        $this->masterAccountSid = config('services.twilio.master_sid', '');
        $this->masterAuthToken = config('services.twilio.master_token', '');

        if (empty($this->masterAccountSid) || empty($this->masterAuthToken)) {
            throw new Exception('Twilio credentials not configured');
        }

        $this->whatsAppAccount = WhatsAppAccount::where('gym_id', $gymId)->first();

        $this->whatsAppPhoneNumber = $this->whatsAppAccount?->phoneNumber;

        $this->masterClient = new Client($this->masterAccountSid, $this->masterAuthToken);
    }

    // =========================
    // Twilio Client Management
    // =========================

    private function isSystemTenant(): bool
    {
        return $this->gymId === config('app.system_tenant_id', 'system') ||
            $this->gymId === 'system';
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

        if (! $this->whatsAppAccount) {
            throw new Exception('No WhatsApp account found for this gym');
        }

        return new Client(
            $this->whatsAppAccount->twilio_account_sid,
            $this->whatsAppAccount->getRawOriginal('twilio_auth_token'),
        );
    }

    private function makeSubaccountClient(WhatsAppAccount $account): Client
    {
        if ($this->useSandbox) {
            return new Client($this->masterAccountSid, $this->masterAuthToken);
        }

        return new Client(
            $account->twilio_account_sid,
            $account->getRawOriginal('twilio_auth_token'),
        );
    }

    private function getMasterClient(): Client
    {
        if ($this->masterClient) {
            return $this->masterClient;
        }

        $this->masterClient = new Client(
            $this->masterAccountSid,
            $this->masterAuthToken,
        );

        return $this->masterClient;
    }

    // =========================
    // Messaging Operations
    // =========================

    /**
     * {@inheritdoc}
     */
    public function sendMessage(string $to, string $message): array
    {
        // Detect template messages (format: "template:template_name|var1=val1|var2=val2")
        if (str_starts_with($message, 'template:')) {
            return $this->sendMessageAsTemplate($to, $message);
        }

        return $this->sendTextMessage($to, $message);
    }

    private function sendTextMessage(string $to, string $message): array
    {
        try {
            $this->checkAndDeductCredits(1);

            $client = $this->getClient();

            $phoneNumber = $this->whatsAppPhoneNumber?->phone_number;

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
                'gym_id' => $this->gymId,
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
                'gym_id' => $this->gymId,
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

    private function sendMessageAsTemplate(string $to, string $message): array
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

    /**
     * {@inheritdoc}
     */
    public function sendTemplateMessage(string $to, string $templateName, array $variables = []): array
    {
        try {
            $query = WhatsAppTemplate::query()
                ->where('name', $templateName)
                ->where('status', 'approved')
                ->where(function ($q) {
                    $q->where('gym_id', $this->gymId);

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

            $phoneNumber = $this->whatsAppPhoneNumber?->phone_number;

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
                'gym_id' => $this->gymId,
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
                'gym_id' => $this->gymId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
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
                'gym_id' => $this->gymId,
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
                'gym_id' => $this->gymId,
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
                'gym_id' => $this->gymId,
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

    /**
     * {@inheritdoc}
     */
    public function sendBroadcast(int $broadcastId): array
    {
        $results = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        try {
            $broadcast = WhatsAppBroadcast::with('template')
                ->where('gym_id', $this->gymId)
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
                    usleep(50000);
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

    private function prepareVariables(WhatsAppBroadcast $broadcast, WhatsAppBroadcastRecipient $recipient): array
    {
        $template = $broadcast->template;
        $customVariables = $broadcast->message_variables ?? [];

        // Load the member with relationships needed for variable resolution
        $member = GymMember::with(['gym', 'membershipPlan', 'trainer', 'latestPayment'])
            ->find($recipient->member_id);

        $resolver = app(\App\Services\WhatsAppVariableResolver::class);

        return $resolver->resolve($template, [
            'member' => $member,
            'custom' => $customVariables,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageStatus(string $messageId): array
    {
        // Twilio doesn't have a direct status lookup API for messages
        // Status is typically obtained via webhooks
        // This is a placeholder implementation
        return [
            'success' => false,
            'error' => 'Message status tracking via API is not supported. Use webhooks.',
        ];
    }

    // =========================
    // Credit Management
    // =========================

    /**
     * {@inheritdoc}
     */
    public function getCreditBalance(): array
    {
        $credit = WhatsAppMessageCredit::where('gym_id', $this->gymId)->first();

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

    /**
     * {@inheritdoc}
     */
    public function getMonthlyUsage(int $year, int $month): array
    {
        $logs = WhatsAppUsageLog::where('gym_id', $this->gymId)
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

    private function checkAndDeductCredits(int $count = 1): void
    {
        $credit = WhatsAppMessageCredit::where('gym_id', $this->gymId)->first();

        if (! $credit || ! $credit->canSendMessage($count)) {
            throw new Exception('Insufficient message credits. Please purchase more credits.');
        }

        if ($credit->hasWelcomeCredits() && $credit->getWelcomeCreditsRemaining() >= $count) {
            $credit->useWelcomeCredit();
        } else {
            $credit->deductCredits($count);
        }
    }

    private function logUsage(string $recipient, WhatsAppTemplate $template, $twilioMessage): void
    {
        WhatsAppUsageLog::create([
            'gym_id' => $this->gymId,
            'whatsapp_template_id' => $template->id,
            'twilio_message_sid' => $twilioMessage->sid,
            'recipient' => $recipient,
            'credits_used' => 1,
            'twilio_cost' => 0.15,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    // =========================
    // Account Management
    // =========================

    /**
     * {@inheritdoc}
     */
    public function createSubAccount(int $gymId, string $gymName, string $email): array
    {
        try {
            if ($this->whatsAppAccount?->twilio_account_sid) {
                return [
                    'success' => true,
                    'account_sid' => $this->whatsAppAccount?->twilio_account_sid,
                    'message' => 'Account already exists',
                ];
            }

            $client = $this->getMasterClient();
            $subaccount = $client->api->v2010->accounts->create([
                'friendlyName' => "Gym_{$gymId}_{$gymName}",
            ]);

            WhatsAppAccount::create([
                'gym_id' => $gymId,
                'twilio_account_sid' => $subaccount->sid,
                'twilio_auth_token' => $subaccount->authToken,
                'status' => WhatsAppAccountStatusEnum::PENDING,
                'verification_status' => WhatsAppAccountVerificationStatusEnum::PENDING,
                'business_name' => $gymName,
                'business_email' => $email,
            ]);

            return [
                'success' => true,
                'account_sid' => $subaccount->sid,
                'message' => 'Subaccount created successfully',
            ];
        } catch (RestException $e) {
            Log::error('Failed to create Twilio subaccount', [
                'gym_id' => $gymId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $message = $e->getCode() === 21217
                ? 'Maximum subaccounts reached. Please upgrade your Twilio plan.'
                : 'Failed to create Twilio subaccount';

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $message,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAccountStatus(int $gymId): array
    {
        if (! $this->whatsAppAccount || ! $this->whatsAppAccount->twilio_account_sid) {
            return [
                'status' => 'not_created',
                'message' => 'No WhatsApp account found',
            ];
        }

        try {
            $client = $this->makeSubaccountClientForGym($gymId);

            $usage = $client->usage->records->read([
                'category' => 'totalprice',
                'startDate' => now()->startOfMonth()->format('Y-m-d'),
                'endDate' => now()->format('Y-m-d'),
            ]);

            $messageCount = 0;
            $cost = 0;
            foreach ($usage as $record) {
                $messageCount += $record->count;
                $cost += (float) $record->price;
            }

            return [
                'status' => $this->whatsAppAccount->status->value,
                'verification_status' => $this->whatsAppAccount->verification_status->value,
                'messages_sent' => $messageCount,
                'monthly_cost' => $cost,
                'account_created' => $this->whatsAppAccount->created_at,
                'last_activity' => now(),
            ];
        } catch (RestException $e) {
            Log::error('Failed to fetch account status', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function makeSubaccountClientForGym(int $gymId): Client
    {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account) {
            throw new Exception('WhatsApp account not found for this gym');
        }

        if ($this->useSandbox) {
            return new Client($this->masterAccountSid, $this->masterAuthToken);
        }

        return new Client(
            $account->twilio_account_sid,
            $account->getRawOriginal('twilio_auth_token'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function cleanupAccount(int $gymId): array
    {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account || ! $account->twilio_account_sid) {
            Log::info('No Twilio resources to clean up for gym', ['gym_id' => $gymId]);

            return [
                'success' => true,
                'message' => 'No Twilio resources to clean up',
                'cleaned' => [],
            ];
        }

        $cleaned = [];
        $errors = [];

        Log::info('Starting Twilio resource cleanup for gym', [
            'gym_id' => $gymId,
            'twilio_account_sid' => $account->twilio_account_sid,
            'waba_mode' => $account->waba_mode?->value,
        ]);

        // Step 1: Remove phone numbers from messaging service and release them
        $phoneResult = $this->releasePhoneNumbers($account);
        $cleaned['phone_numbers'] = $phoneResult;
        if (! $phoneResult['success']) {
            $errors[] = $phoneResult['error'];
        }

        // Step 2: Delete the messaging service
        $serviceResult = $this->deleteMessagingService($account);
        $cleaned['messaging_service'] = $serviceResult;
        if (! $serviceResult['success']) {
            $errors[] = $serviceResult['error'];
        }

        // Step 3: Close the Twilio subaccount
        $accountResult = $this->closeSubaccount($account);
        $cleaned['subaccount'] = $accountResult;
        if (! $accountResult['success']) {
            $errors[] = $accountResult['error'];
        }

        // Update local account status
        $account->update([
            'status' => WhatsAppAccountStatusEnum::SUSPENDED,
        ]);

        $success = empty($errors);

        Log::info('Twilio resource cleanup completed', [
            'gym_id' => $gymId,
            'success' => $success,
            'cleaned' => $cleaned,
            'errors' => $errors,
        ]);

        return [
            'success' => $success,
            'message' => $success
                ? 'All Twilio resources cleaned up successfully'
                : 'Twilio cleanup completed with errors',
            'cleaned' => $cleaned,
            'errors' => $errors,
        ];
    }

    private function releasePhoneNumbers(WhatsAppAccount $account): array
    {
        $phoneNumbers = $account->phoneNumber()->get();

        if ($phoneNumbers->isEmpty()) {
            return ['success' => true, 'message' => 'No phone numbers to release', 'released' => 0];
        }

        $released = 0;
        $failed = 0;
        $lastError = null;

        try {
            $subClient = $this->makeSubaccountClientForAccount($account);

            foreach ($phoneNumbers as $phoneNumber) {
                try {
                    $channelSenderSid = $phoneNumber->getChannelSenderSid();
                    if ($account->messaging_service_sid && $channelSenderSid) {
                        try {
                            $subClient->messaging->v1->services($account->messaging_service_sid)
                                ->channelSenders($channelSenderSid)
                                ->delete();

                            Log::info('Channel sender removed from messaging service', [
                                'phone_number' => $phoneNumber->phone_number,
                                'channel_sender_sid' => $channelSenderSid,
                            ]);
                        } catch (RestException $e) {
                            if ($e->getStatusCode() !== 404) {
                                throw $e;
                            }
                        }
                    }

                    $incomingPhoneSid = $phoneNumber->getIncomingPhoneNumberSid();
                    if ($incomingPhoneSid && $phoneNumber->isValidIncomingPhoneNumberSid()) {
                        try {
                            $subClient->incomingPhoneNumbers($incomingPhoneSid)->delete();

                            Log::info('Phone number released from Twilio', [
                                'phone_number' => $phoneNumber->phone_number,
                            ]);
                        } catch (RestException $e) {
                            if ($e->getStatusCode() !== 404) {
                                throw $e;
                            }
                        }
                    }

                    $phoneNumber->update(['status' => 'released']);
                    $released++;
                } catch (\Exception $e) {
                    $failed++;
                    $lastError = $e->getMessage();

                    Log::error('Failed to release phone number', [
                        'phone_number' => $phoneNumber->phone_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'released' => $released,
                'failed' => $phoneNumbers->count(),
            ];
        }

        return [
            'success' => $failed === 0,
            'message' => "Released {$released} phone number(s), {$failed} failed",
            'released' => $released,
            'failed' => $failed,
            'error' => $lastError,
        ];
    }

    private function makeSubaccountClientForAccount(WhatsAppAccount $account): Client
    {
        if ($this->useSandbox) {
            return new Client($this->masterAccountSid, $this->masterAuthToken);
        }

        return new Client(
            $account->twilio_account_sid,
            $account->getRawOriginal('twilio_auth_token'),
        );
    }

    private function deleteMessagingService(WhatsAppAccount $account): array
    {
        if (! $account->messaging_service_sid) {
            return ['success' => true, 'message' => 'No messaging service to delete'];
        }

        try {
            $subClient = $this->makeSubaccountClientForAccount($account);

            $subClient->messaging->v1
                ->services($account->messaging_service_sid)
                ->delete();

            Log::info('Messaging service deleted', [
                'messaging_service_sid' => $account->messaging_service_sid,
            ]);

            return ['success' => true, 'message' => 'Messaging service deleted'];
        } catch (RestException $e) {
            if ($e->getStatusCode() === 404) {
                return ['success' => true, 'message' => 'Messaging service already deleted'];
            }

            Log::error('Failed to delete messaging service', [
                'messaging_service_sid' => $account->messaging_service_sid,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function closeSubaccount(WhatsAppAccount $account): array
    {
        if (! $account->twilio_account_sid) {
            return ['success' => true, 'message' => 'No subaccount to close'];
        }

        try {
            $this->getMasterClient()->api->v2010
                ->accounts($account->twilio_account_sid)
                ->update(['status' => 'closed']);

            Log::info('Twilio subaccount closed', [
                'account_sid' => $account->twilio_account_sid,
            ]);

            return ['success' => true, 'message' => 'Subaccount closed'];
        } catch (RestException $e) {
            if ($e->getStatusCode() === 404) {
                return ['success' => true, 'message' => 'Subaccount already closed or not found'];
            }

            Log::error('Failed to close Twilio subaccount', [
                'account_sid' => $account->twilio_account_sid,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getWabaMode(int $gymId): string
    {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account) {
            return 'not_created';
        }

        return $account->waba_mode?->value ?? 'shared';
    }

    /**
     * {@inheritdoc}
     */
    public function requiresEmbeddedSignup(int $gymId): bool
    {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account) {
            return false;
        }

        return $account->hasIndividualWaba() && ! $account->isEmbeddedSignupComplete();
    }

    // =========================
    // Phone Number Management
    // =========================

    /**
     * {@inheritdoc}
     */
    public function getAvailableNumbers(string $country, string $type = 'local', int $limit = 5): array
    {
        try {
            $client = $this->getMasterClient();
            $numbers = $client->api->v2010->availablePhoneNumbers($country)
                ->$type->read([
                    'smsEnabled' => true,
                    'voiceEnabled' => true,
                ], $limit);

            return array_map(fn ($number) => [
                'phoneNumber' => $number->phoneNumber,
                'friendlyName' => $number->friendlyName,
                'locality' => $number->locality,
                'region' => $number->region,
                'isoCountry' => $number->isoCountry,
            ], $numbers);
        } catch (RestException $e) {
            Log::error('Twilio number search failed', [
                'country' => $country,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function purchaseNumber(int $gymId, string $phoneNumber): array
    {
        try {
            if (! $phoneNumber) {
                throw new \Exception('Phone Number is required');
            }

            $account = $this->getAccountOrFail($gymId);
            $subClient = $this->makeSubaccountClientForGym($gymId);

            $existingNumber = WhatsAppPhoneNumber::where('phone_number', $phoneNumber)->first();
            if ($existingNumber) {
                return [
                    'success' => true,
                    'phone_number' => $phoneNumber,
                    'message' => 'Number already purchased',
                ];
            }

            $existingNumbers = $subClient->incomingPhoneNumbers->read([], 1);
            if (! empty($existingNumbers)) {
                $existing = $existingNumbers[0];

                $channelSenderSid = $this->registerWhatsAppSender($subClient, $account, $existing->phoneNumber);

                WhatsAppPhoneNumber::firstOrCreate(
                    [
                        'whatsapp_account_id' => $account->id,
                        'phone_number' => $existing->phoneNumber,
                    ],
                    [
                        'phone_number_sid' => $existing->sid,
                        'channel_sender_sid' => $channelSenderSid,
                        'country_code' => $existing->isoCountry ?? 'US',
                        'friendly_name' => $existing->friendlyName,
                        'locality' => $existing->locality,
                        'region' => $existing->region,
                        'status' => 'active',
                        'type' => 'local',
                        'purchased_at' => now(),
                    ]
                );

                return [
                    'success' => true,
                    'phone_number' => $existing->phoneNumber,
                    'message' => 'Using existing number',
                ];
            }

            $incomingNumber = $subClient->incomingPhoneNumbers->create([
                'phoneNumber' => $phoneNumber,
            ]);

            if ($account->messaging_service_sid) {
                try {
                    $subClient->messaging->v1->services($account->messaging_service_sid)
                        ->phoneNumbers->create($incomingNumber->sid);
                } catch (RestException $e) {
                    if ($e->getStatusCode() !== 409) {
                        throw $e;
                    }
                }
            }

            $channelSenderSid = $this->registerWhatsAppSender($subClient, $account, $incomingNumber->phoneNumber);

            WhatsAppPhoneNumber::create([
                'whatsapp_account_id' => $account->id,
                'phone_number' => $incomingNumber->phoneNumber,
                'phone_number_sid' => $incomingNumber->sid,
                'channel_sender_sid' => $channelSenderSid,
                'country_code' => $incomingNumber->isoCountry ?? 'US',
                'friendly_name' => $incomingNumber->friendlyName,
                'locality' => $incomingNumber->locality,
                'region' => $incomingNumber->region,
                'status' => 'active',
                'type' => 'local',
                'purchased_at' => now(),
            ]);

            $account->update([
                'status' => WhatsAppAccountStatusEnum::ACTIVE,
                'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
                'verified_at' => now(),
            ]);

            Log::info('Phone number purchased', [
                'gym_id' => $gymId,
                'phone_number' => $incomingNumber->phoneNumber,
            ]);

            return [
                'success' => true,
                'phone_number' => $incomingNumber->phoneNumber,
                'message' => 'Number purchased successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to purchase number', [
                'gym_id' => $gymId,
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to purchase number',
            ];
        }
    }

    private function registerWhatsAppSender(Client $subClient, WhatsAppAccount $account, string $phoneNumber): ?string
    {
        // This is a simplified version - actual implementation depends on account configuration
        try {
            $channelSenderSid = null;

            return $channelSenderSid;
        } catch (\Exception $e) {
            Log::warning('Failed to register WhatsApp sender', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function getAccountOrFail(int $gymId): WhatsAppAccount
    {
        $account = WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account) {
            throw new \Exception('WhatsApp account not found for this gym');
        }

        return $account;
    }

    /**
     * {@inheritdoc}
     */
    public function registerOwnNumber(int $gymId, string $phoneNumber): array
    {
        try {
            $account = $this->getAccountOrFail($gymId);
            $subClient = $this->makeSubaccountClientForGym($gymId);

            if (! $account->waba_id) {
                throw new \Exception('WABA ID is not configured. Please complete Step 2 first.');
            }

            if (! $account->messaging_service_sid) {
                throw new \Exception('Messaging Service is not configured. Please complete Step 2 first.');
            }

            $request = ChannelsSenderModels::createMessagingV2ChannelsSenderRequestsCreate([
                'sender_id' => 'whatsapp:' . $phoneNumber,
                'configuration' => ChannelsSenderModels::createMessagingV2ChannelsSenderConfiguration([
                    'waba_id' => $account->waba_id,
                ]),
                'profile' => ChannelsSenderModels::createMessagingV2ChannelsSenderProfile([
                    'name' => $account->business_name ?? $account->waba_name ?? 'Gym Business',
                ]),
            ]);

            $sender = $subClient->messaging->v2->channelsSenders->create($request);

            try {
                $subClient->messaging->v1->services($account->messaging_service_sid)
                    ->channelSenders
                    ->create($sender->sid);
            } catch (RestException $e) {
                if ($e->getStatusCode() !== 409) {
                    Log::error('Failed to register own number', [
                        'gym_id' => $gymId,
                        'phone_number' => strlen($phoneNumber) > 6
                            ? substr($phoneNumber, 0, 4) . '****' . substr($phoneNumber, -2)
                            : str_repeat('*', max(0, strlen($phoneNumber))),
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }

            WhatsAppPhoneNumber::updateOrCreate(
                ['whatsapp_account_id' => $account->id],
                [
                    'phone_number' => $phoneNumber,
                    'channel_sender_sid' => $sender->sid,
                    'country_code' => ParserHelper::getCountryCode($phoneNumber) ?? 'IN',
                    'status' => 'purchased',
                ]
            );

            Log::info('BYOD number registered, OTP sent', [
                'gym_id' => $gymId,
                'phone_number' => $phoneNumber,
                'sender_sid' => $sender->sid,
            ]);

            return [
                'success' => true,
                'sender_sid' => $sender->sid,
                'status' => 'purchased',
                'message' => 'Number registered. Please enter the OTP sent to your phone.',
            ];
        } catch (\Exception $e) {
            Log::error('BYOD registration failed', [
                'gym_id' => $gymId,
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to register phone number',
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyOwnNumber(int $gymId, string $senderSid, string $otpCode): array
    {
        try {
            $account = $this->getAccountOrFail($gymId);
            $subClient = $this->makeSubaccountClientForGym($gymId);

            if (! $account->waba_id) {
                throw new \Exception('WABA ID is not configured. Please complete Step 2 first.');
            }

            if (! $account->messaging_service_sid) {
                throw new \Exception('Messaging Service is not configured. Please complete Step 2 first.');
            }

            $subClient->messaging->v2->channelsSenders($senderSid)
                ->update([
                    'configuration' => [
                        'verification_code' => $otpCode,
                    ],
                ]);

            WhatsAppPhoneNumber::where('whatsapp_account_id', $account->id)
                ->where('phone_number_sid', $senderSid)
                ->update(['status' => 'active']);

            $account->update([
                'status' => WhatsAppAccountStatusEnum::ACTIVE,
                'verification_status' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
                'verified_at' => now(),
            ]);

            Log::info('BYOD number verified', [
                'gym_id' => $gymId,
                'sender_sid' => $senderSid,
            ]);

            return [
                'success' => true,
                'message' => 'Phone number verified successfully.',
            ];
        } catch (RestException $e) {
            Log::error('BYOD OTP verification failed', [
                'gym_id' => $gymId,
                'sender_sid' => $senderSid,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $message = $e->getCode() === 60022
                ? 'Invalid or expired OTP code. Please try again.'
                : 'Verification failed: ' . $e->getMessage();

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $message,
            ];
        } catch (\Exception $e) {
            Log::error('BYOD verification error', [
                'gym_id' => $gymId,
                'sender_sid' => $senderSid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Verification failed',
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resendOwnNumberOtp(int $gymId, string $senderSid, string $channel = 'sms'): array
    {
        try {
            $account = $this->getAccountOrFail($gymId);
            $subClient = $this->makeSubaccountClientForGym($gymId);

            if (! $account->waba_id) {
                throw new \Exception('WABA ID is not configured. Please complete Step 2 first.');
            }

            $phoneNumberRecord = WhatsAppPhoneNumber::where('whatsapp_account_id', $account->id)
                ->where('phone_number_sid', $senderSid)
                ->first();

            if (! $phoneNumberRecord) {
                throw new \Exception('Phone number record not found.');
            }

            $phoneNumber = $phoneNumberRecord->phone_number;

            // For BYOD, re-registering the sender triggers a new OTP from Meta
            $request = ChannelsSenderModels::createMessagingV2ChannelsSenderRequestsCreate([
                'sender_id' => 'whatsapp:' . $phoneNumber,
                'configuration' => ChannelsSenderModels::createMessagingV2ChannelsSenderConfiguration([
                    'waba_id' => $account->waba_id,
                ]),
            ]);

            $subClient->messaging->v2->channelsSenders->create($request);

            Log::info('BYOD OTP resent', [
                'gym_id' => $gymId,
                'sender_sid' => $senderSid,
                'channel' => $channel,
            ]);

            return [
                'success' => true,
                'message' => 'Verification code has been resent.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to resend BYOD OTP', [
                'gym_id' => $gymId,
                'sender_sid' => $senderSid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to resend verification code',
            ];
        }
    }

    // =========================
    // Template Management
    // =========================

    /**
     * {@inheritdoc}
     */
    public function createTemplate(int $gymId, array $data): WhatsAppTemplate
    {
        $templateService = app(\App\Services\WhatsAppTemplateService::class);

        return $templateService->createTemplate($gymId, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function submitTemplate(WhatsAppTemplate $template): array
    {
        return app(\App\Services\WhatsAppTemplateService::class)->submitForVerification($template);
    }

    /**
     * {@inheritdoc}
     */
    public function syncTemplateStatus(WhatsAppTemplate $template): void
    {
        $templateService = app(\App\Services\WhatsAppTemplateService::class);
        $templateService->syncTemplateStatus($template);
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplates(int $gymId): array
    {
        $templateManager = new \App\Services\WhatsAppTemplateManager((string) $gymId);

        return $templateManager->fetchTemplates();
    }

    // =========================
    // WABA Setup
    // =========================

    /**
     * {@inheritdoc}
     */
    public function createWaba(int $gymId, array $businessProfile): array
    {
        try {
            $account = $this->getAccountOrFail($gymId);

            if ($account->waba_id && $account->messaging_service_sid) {
                return [
                    'success' => true,
                    'waba_id' => $account->waba_id,
                    'message' => 'WABA already exists',
                ];
            }

            $subClient = $this->makeSubaccountClientForGym($gymId);

            if ($this->useIndividualWaba) {
                return $this->createIndividualWaba($account, $gymId, $subClient, $businessProfile);
            }

            return $this->createSharedWaba($account, $gymId, $subClient, $businessProfile);
        } catch (\Exception $e) {
            Log::error('Failed to create WABA', [
                'gym_id' => $gymId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to create WABA',
            ];
        }
    }

    private function createSharedWaba(WhatsAppAccount $account, int $gymId, Client $subClient, array $businessProfile): array
    {
        if (! $account->waba_id) {
            $account->update([
                'waba_id' => $this->sharedWabaId,
                'waba_mode' => WabaModeEnum::SHARED->value,
            ]);
        }

        if (! $account->messaging_service_sid) {
            $service = $subClient->messaging->v1->services->create("Gym_{$gymId}_WABA");
            $account->update(['messaging_service_sid' => $service->sid]);
        }

        if (! empty($businessProfile['business_name'])) {
            $account->update([
                'business_name' => $businessProfile['business_name'],
                'business_email' => $businessProfile['business_email'] ?? $account->business_email,
                'business_description' => $businessProfile['business_description'] ?? '',
            ]);
        }

        Log::info('Shared WABA setup completed', [
            'gym_id' => $gymId,
            'waba_id' => $account->waba_id,
            'messaging_service_sid' => $account->messaging_service_sid,
        ]);

        return [
            'success' => true,
            'waba_id' => $account->waba_id,
            'messaging_service_sid' => $account->messaging_service_sid,
            'waba_mode' => 'shared',
            'message' => 'Shared WABA created successfully',
        ];
    }

    private function createIndividualWaba(WhatsAppAccount $account, int $gymId, Client $subClient, array $businessProfile): array
    {
        $account->update([
            'waba_mode' => WabaModeEnum::INDIVIDUAL->value,
            'waba_status' => WabaStatusEnum::PENDING,
        ]);

        if (! $account->messaging_service_sid) {
            $service = $subClient->messaging->v1->services->create("Gym_{$gymId}_WABA");
            $account->update(['messaging_service_sid' => $service->sid]);
        }

        if (! empty($businessProfile['business_name'])) {
            $account->update([
                'business_name' => $businessProfile['business_name'],
                'business_email' => $businessProfile['business_email'] ?? $account->business_email,
                'business_description' => $businessProfile['business_description'] ?? '',
            ]);
        }

        Log::info('Individual WABA setup initiated', [
            'gym_id' => $gymId,
            'messaging_service_sid' => $account->messaging_service_sid,
            'requires_embedded_signup' => true,
        ]);

        return [
            'success' => true,
            'messaging_service_sid' => $account->messaging_service_sid,
            'waba_mode' => WabaModeEnum::INDIVIDUAL->value,
            'requires_embedded_signup' => true,
            'message' => 'Please complete Embedded Signup to connect your WABA',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function connectIndividualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken = null): array
    {
        try {
            if (! $wabaId) {
                throw new \Exception('WABA ID is required');
            }

            if (! $phoneNumber) {
                throw new \Exception('Phone Number is required');
            }

            $account = $this->getAccountOrFail($gymId);

            if (! $account) {
                throw new \Exception('Account not found');
            }

            $subClient = $this->makeSubaccountClientForGym($gymId);

            $senderSid = $this->registerWhatsAppSender($subClient, $account, $phoneNumber);

            $account->update([
                'waba_id' => $wabaId,
                'waba_mode' => WabaModeEnum::INDIVIDUAL->value,
                'waba_status' => WabaStatusEnum::PENDING,
                'embedded_signup_completed' => true,
            ]);

            if ($metaAccessToken) {
                $account->update(['meta_access_token' => $metaAccessToken]);
            }

            Log::info('Individual WABA connected', [
                'gym_id' => $gymId,
                'waba_id' => $wabaId,
                'sender_sid' => $senderSid,
            ]);

            return [
                'success' => true,
                'waba_id' => $wabaId,
                'sender_sid' => $senderSid,
                'message' => 'Individual WABA connected successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to connect individual WABA', [
                'gym_id' => $gymId,
                'waba_id' => $wabaId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to connect individual WABA',
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function connectManualWaba(
        int $gymId,
        string $wabaId,
        string $phoneNumber,
        ?string $metaAccessToken = null,
        ?string $phoneNumberId = null,
        ?array $businessData = null
    ): array {
        try {
            $account = $this->getAccountOrFail($gymId);
            $subClient = $this->makeSubaccountClientForGym($gymId);

            if (! $wabaId) {
                throw new \Exception('WABA ID is required');
            }

            if (! $phoneNumber) {
                throw new \Exception('Phone Number is required');
            }

            // 1. Ensure Messaging Service exists
            if (! $account->messaging_service_sid) {
                $service = $subClient->messaging->v1->services->create("Gym_{$gymId}_WABA");
                $account->update(['messaging_service_sid' => $service->sid]);
                $account->refresh();
            }

            // 2 & 3. Register WhatsApp sender and attach to messaging service
            $senderSid = $this->registerWhatsAppSender($subClient, $account, $phoneNumber);

            if (! $senderSid) {
                return [
                    'success' => false,
                    'error' => 'Failed to register WhatsApp sender',
                    'message' => 'Failed to register WhatsApp sender',
                ];
            }

            // 4. Create local phone number record
            WhatsAppPhoneNumber::updateOrCreate(
                ['whatsapp_account_id' => $account->id],
                [
                    'phone_number' => $phoneNumber,
                    'channel_sender_sid' => $senderSid,
                    'country_code' => ParserHelper::getCountryCode($phoneNumber) ?? 'IN',
                    'status' => 'active',
                ]
            );

            // 5. Update account details
            $updateData = [
                'waba_id' => $wabaId,
                'waba_mode' => WabaModeEnum::MANUAL->value,
                'waba_status' => WabaStatusEnum::PENDING,
                'embedded_signup_completed' => false,
                'status' => WhatsAppAccountStatusEnum::PENDING,
            ];

            if ($metaAccessToken) {
                $updateData['meta_access_token'] = $metaAccessToken;
            }

            $account->update($updateData);

            Log::info('Manual WABA and Phone Number connected', [
                'gym_id' => $gymId,
                'waba_id' => $wabaId,
                'phone_number' => $phoneNumber,
                'sender_sid' => $senderSid,
            ]);

            return [
                'success' => true,
                'waba_id' => $wabaId,
                'sender_sid' => $senderSid,
                'message' => 'Manual WABA connected successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to connect manual WABA', [
                'gym_id' => $gymId,
                'waba_id' => $wabaId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to connect manual WABA',
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyWabaConnection(string $wabaId, string $phoneNumberId, ?string $accessToken = null): array
    {
        // Twilio provider doesn't support Meta API verification
        // This is only applicable for Meta provider
        return [
            'success' => false,
            'error' => 'WABA verification is only supported for Meta provider',
        ];
    }

    // =========================
    // Test Messages
    // =========================

    /**
     * {@inheritdoc}
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
            $gymName = $gym ? $gym->name : 'Your Gym';

            $phoneNumberRecord = $account->phoneNumber()->first();
            $recipientNumber = $to ?? $phoneNumberRecord?->phone_number;

            if (! $recipientNumber) {
                return [
                    'success' => false,
                    'error' => 'No phone number configured.',
                ];
            }

            if ($this->useSandbox) {
                $messageBody = "🎉 *WhatsApp Integration Active!*\n\n";
                $messageBody .= "Hello from {$gymName}! 👋\n\n";
                $messageBody .= "Your WhatsApp Business integration is now successfully set up.\n\n";
                $messageBody .= '_Sent at: ' . now()->format('M d, Y H:i A') . '_';

                $result = $this->sendMessage($recipientNumber, $messageBody);
            } else {
                $templateSid = config('services.twilio.whatsapp_integration_active_v5_template_sid');

                if (! $templateSid) {
                    return [
                        'success' => false,
                        'error' => 'No approved WhatsApp template found.',
                        'requires_template' => true,
                    ];
                }

                $result = $this->sendTemplateMessage($recipientNumber, '', ['1' => $gymName]);
            }

            Log::info('Test WhatsApp message sent', [
                'gym_id' => $gymId,
                'success' => $result['success'] ?? false,
                'mode' => $this->useSandbox ? 'sandbox' : 'production',
            ]);

            return [
                'success' => $result['success'] ?? false,
                'message_id' => $result['message_id'] ?? null,
                'status' => $result['status'] ?? null,
                'to' => $recipientNumber,
                'mode' => $this->useSandbox ? 'sandbox' : 'production',
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
