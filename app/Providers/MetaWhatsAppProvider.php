<?php

namespace App\Providers;

use App\Enums\WabaModeEnum;
use App\Interfaces\WhatsAppProviderInterface;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppUsageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Business API Provider.
 *
 * This provider implements WhatsAppProviderInterface using Meta's
 * WhatsApp Business API (WABA). It uses the WhatsAppAccount model
 * to store and retrieve credentials.
 */
class MetaWhatsAppProvider implements WhatsAppProviderInterface
{
    protected int $gymId;

    protected ?WhatsAppAccount $account = null;

    protected string $apiVersion;

    public function __construct(int $gymId)
    {
        $this->gymId = $gymId;
        $this->account = WhatsAppAccount::with('phoneNumber')->where('gym_id', $gymId)->first();
        $this->apiVersion = config('services.whatsapp.api_version', 'v25.0');
    }

    /**
     * Get the Meta API base URL.
     */
    protected function getApiBaseUrl(): string
    {
        return 'https://graph.facebook.com/' . $this->apiVersion;
    }

    /**
     * Get the access token for API calls.
     */
    protected function getAccessToken(): ?string
    {
        return $this->account?->meta_access_token;
    }

    /**
     * Get the WABA ID for API calls.
     */
    protected function getWabaId(): ?string
    {
        return $this->account?->waba_id;
    }

    /**
     * Make an API request to Meta Graph API.
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getAccessToken();
        $baseUrl = $this->getApiBaseUrl();

        if (! $token) {
            return [
                'success' => false,
                'error' => 'Meta access token not configured',
            ];
        }

        try {
            $response = Http::withToken($token)
                ->$method("{$baseUrl}{$endpoint}", $data);

            if ($response->failed()) {
                $error = $response->json('error.message') ?? $response->body();

                return [
                    'success' => false,
                    'error' => $error,
                ];
            }

            return [
                'success' => true,
                'data' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Meta API request failed', [
                'gym_id' => $this->gymId,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // =========================
    // Messaging Operations
    // =========================

    /**
     * {@inheritdoc}
     */
    public function sendMessage(string $to, string $message): array
    {
        if (! $this->account) {
            return [
                'success' => false,
                'error' => 'WhatsApp account not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        if (! $this->getAccessToken()) {
            return [
                'success' => false,
                'error' => 'Meta access token not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        $phoneNumberId = $this->account?->phoneNumber?->channel_sender_sid;

        if (! $phoneNumberId) {
            return [
                'success' => false,
                'error' => 'Phone number not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        $this->checkAndDeductCredits(1);

        $wabaId = $this->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
                'status' => 'failed',
                'message_id' => null,
            ];
        }

        $response = $this->makeRequest('POST', "/{$wabaId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message,
            ],
        ]);

        if ($response['success']) {
            $messageId = $response['data']['messages'][0]['id'] ?? null;

            $this->logUsage($to, null, $messageId);

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
     * {@inheritdoc}
     */
    public function sendTemplateMessage(string $to, string $templateName, array $variables = []): array
    {
        $phoneNumberId = $this->account?->phoneNumber?->channel_sender_sid;

        if (! $phoneNumberId) {
            return [
                'success' => false,
                'error' => 'Phone number not configured',
            ];
        }

        // Find the template
        $template = WhatsAppTemplate::where('gym_id', $this->gymId)
            ->where('name', $templateName)
            ->where('status', 'approved')
            ->first();

        if (! $template) {
            return [
                'success' => false,
                'error' => "Template '{$templateName}' not found or not approved",
            ];
        }

        $this->checkAndDeductCredits(1);

        $wabaId = $this->getWabaId();

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

        if (! empty($variables)) {
            $templatePayload['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(fn ($value, $key) => [
                        'type' => 'text',
                        'parameter' => $value,
                    ], $variables, array_keys($variables)),
                ],
            ];
        }

        $response = $this->makeRequest('POST', "/{$wabaId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => $templatePayload,
        ]);

        if ($response['success']) {
            $messageId = $response['data']['messages'][0]['id'] ?? null;

            $this->logUsage($to, $template, $messageId);
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
     * {@inheritdoc}
     */
    public function sendTestMessage(string $to, string $message): array
    {
        return $this->sendMessage($to, $message);
    }

    /**
     * {@inheritdoc}
     */
    public function sendBroadcast(int $broadcastId): array
    {
        // Broadcast implementation similar to Twilio
        $broadcast = \App\Models\WhatsAppBroadcast::with('template')
            ->where('gym_id', $this->gymId)
            ->findOrFail($broadcastId);

        $broadcast->markAsSending();

        $results = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

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
                } catch (\Exception $e) {
                    $recipient->markAsFailed($e->getMessage());
                    $broadcast->incrementFailedCount();
                    $results['failed']++;
                }
            }
        });

        $broadcast->markAsCompleted();

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageStatus(string $messageId): array
    {
        $wabaId = $this->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
            ];
        }

        return $this->makeRequest('GET', "/{$messageId}");
    }

    // =========================
    // Account Management
    // =========================

    /**
     * {@inheritdoc}
     */
    public function createSubAccount(int $gymId, string $gymName, string $email): array
    {
        // Meta doesn't have sub-accounts like Twilio
        // The WhatsAppAccount model already represents the account
        return [
            'success' => false,
            'error' => 'Meta WABA does not support sub-accounts. Use WhatsAppAccount model instead.',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getAccountStatus(int $gymId): array
    {
        $wabaId = $this->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
            ];
        }

        $response = $this->makeRequest('GET', "/{$wabaId}");

        if (! $response['success']) {
            return $response;
        }

        return [
            'success' => true,
            'status' => $response['data']['status'] ?? 'unknown',
            'business_info' => $response['data']['business_info'] ?? null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function cleanupAccount(int $gymId): array
    {
        // Cleanup would involve disconnecting phone numbers, etc.
        // This is a placeholder - actual cleanup depends on Meta's API capabilities
        return [
            'success' => true,
            'message' => 'Account cleanup not implemented for Meta WABA',
            'cleaned' => [],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getWabaMode(int $gymId): string
    {
        return $this->account?->waba_mode?->value ?? 'not_created';
    }

    /**
     * {@inheritdoc}
     */
    public function requiresEmbeddedSignup(int $gymId): bool
    {
        return $this->account?->hasIndividualWaba()
            && ! $this->account?->isEmbeddedSignupComplete();
    }

    // =========================
    // Phone Number Management
    // =========================

    /**
     * {@inheritdoc}
     */
    public function getAvailableNumbers(string $country, string $type = 'local', int $limit = 5): array
    {
        // Meta doesn't expose phone number search API like Twilio
        return [
            'success' => false,
            'error' => 'Phone number search not available for Meta WABA. Use Meta Business Suite to purchase numbers.',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function purchaseNumber(int $gymId, string $phoneNumber): array
    {
        // Phone number purchase is done through Meta Business Suite
        return [
            'success' => false,
            'error' => 'Phone numbers must be purchased through Meta Business Suite, not API.',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function registerOwnNumber(int $gymId, string $phoneNumber): array
    {
        $wabaId = $this->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
            ];
        }

        // Register phone number through Meta API
        $response = $this->makeRequest('POST', "/{$wabaId}/phone_numbers", [
            'phone_number' => $phoneNumber,
        ]);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function verifyOwnNumber(int $gymId, string $senderSid, string $otpCode): array
    {
        // OTP verification is handled through Meta's flow
        return [
            'success' => false,
            'error' => 'Phone verification must be completed through Meta Business Suite',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function resendOwnNumberOtp(int $gymId, string $senderSid, string $channel = 'sms'): array
    {
        return [
            'success' => false,
            'error' => 'OTP resend not available through API',
        ];
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
        $wabaId = $this->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
            ];
        }

        // Submit template to Meta for approval
        $response = $this->makeRequest('POST', "/{$wabaId}/message_templates", [
            'name' => $template->name,
            'language' => 'en_US',
            'category' => strtoupper($template->category ?? 'UTILITY'),
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $template->body,
                ],
            ],
        ]);

        if ($response['success']) {
            $template->update([
                'meta_template_id' => $response['data']['id'] ?? null,
                'status' => 'pending',
            ]);

            return [
                'success' => true,
                'status' => 'pending',
                'template_id' => $response['data']['id'],
            ];
        }

        return [
            'success' => false,
            'error' => $response['error'] ?? 'Failed to submit template',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function syncTemplateStatus(WhatsAppTemplate $template): void
    {
        if (! $template->meta_template_id) {
            return;
        }

        $response = $this->makeRequest('GET', "/{$template->meta_template_id}");

        if (! $response['success']) {
            return;
        }

        $status = $response['data']['status'] ?? 'unknown';

        $statusMap = [
            'PENDING' => 'pending',
            'APPROVED' => 'approved',
            'REJECTED' => 'rejected',
            'DISABLED' => 'disabled',
        ];

        $newStatus = $statusMap[$status] ?? $template->status;

        $update = ['status' => $newStatus];

        if ($status === 'REJECTED') {
            $update['rejection_reason'] = $response['data']['rejection_reason'] ?? null;
        }

        $template->update($update);
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplates(int $gymId): array
    {
        $wabaId = $this->getWabaId();

        if (! $wabaId) {
            return [
                'success' => false,
                'error' => 'WABA not configured',
                'templates' => [],
            ];
        }

        $response = $this->makeRequest('GET', "/{$wabaId}/message_templates");

        if (! $response['success']) {
            return [
                'success' => false,
                'error' => $response['error'],
                'templates' => [],
            ];
        }

        $templates = $response['data']['data'] ?? [];

        return [
            'success' => true,
            'templates' => $templates,
            'count' => count($templates),
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

    // =========================
    // WABA Setup
    // =========================

    /**
     * {@inheritdoc}
     */
    public function createWaba(int $gymId, array $businessProfile): array
    {
        // WABA creation requires embedded signup or manual setup through Meta
        return [
            'success' => false,
            'error' => 'WABA creation must be done through Meta Business Manager or embedded signup',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function connectIndividualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken = null): array
    {
        // Update account with WABA details
        if ($this->account) {
            $this->account->update([
                'waba_id' => $wabaId,
                'waba_mode' => WabaModeEnum::INDIVIDUAL,
                'meta_access_token' => $metaAccessToken,
            ]);
        }

        return [
            'success' => true,
            'waba_id' => $wabaId,
            'message' => 'Individual WABA connected successfully',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function connectManualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken = null): array
    {
        // Update account with WABA details
        if ($this->account) {
            $this->account->update([
                'waba_id' => $wabaId,
                'waba_mode' => 'manual',
                'meta_access_token' => $metaAccessToken,
            ]);
        }

        return [
            'success' => true,
            'waba_id' => $wabaId,
            'message' => 'Manual WABA connected successfully',
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
        $phoneNumber = $this->account?->phoneNumber?->phone_number;
        $recipient = $to ?? $phoneNumber;

        if (! $recipient) {
            return [
                'success' => false,
                'error' => 'No phone number configured',
            ];
        }

        $gym = \App\Models\Gym::find($gymId);
        $gymName = $gym?->name ?? 'Your Gym';

        $message = "WhatsApp Integration Active!\n\nHello from {$gymName}!";

        return $this->sendMessage($recipient, $message);
    }

    // =========================
    // Helper Methods
    // =========================

    /**
     * Check and deduct credits before sending.
     */
    protected function checkAndDeductCredits(int $count = 1): void
    {
        $credit = WhatsAppMessageCredit::where('gym_id', $this->gymId)->first();

        if (! $credit || ! $credit->canSendMessage($count)) {
            throw new \Exception('Insufficient message credits. Please purchase more credits.');
        }

        if ($credit->hasWelcomeCredits() && $credit->getWelcomeCreditsRemaining() >= $count) {
            $credit->useWelcomeCredit();
        } else {
            $credit->deductCredits($count);
        }
    }

    /**
     * Log message usage.
     */
    protected function logUsage(string $recipient, ?WhatsAppTemplate $template, ?string $messageId): void
    {
        WhatsAppUsageLog::create([
            'gym_id' => $this->gymId,
            'whatsapp_template_id' => $template?->id,
            'twilio_message_sid' => $messageId,
            'recipient' => $recipient,
            'credits_used' => 1,
            'twilio_cost' => 0,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Prepare variables for template messages.
     */
    protected function prepareVariables($broadcast, $recipient): array
    {
        $template = $broadcast->template;
        $customVariables = $broadcast->message_variables ?? [];

        $member = \App\Models\GymMember::with(['gym', 'membershipPlan', 'trainer', 'latestPayment'])
            ->find($recipient->member_id);

        $resolver = app(\App\Services\WhatsAppVariableResolver::class);

        return $resolver->resolve($template, [
            'member' => $member,
            'custom' => $customVariables,
        ]);
    }
}
