<?php

namespace App\Providers;

use App\Enums\WabaModeEnum;
use App\Enums\WabaStatusEnum;
use App\Enums\WhatsAppAccountVerificationStatusEnum;
use App\Enums\WhatsAppPhoneNumberStatusEnum;
use App\Interfaces\WhatsAppProviderInterface;
use App\Models\WabaSetupSession;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageCredit;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppUsageLog;
use Illuminate\Support\Facades\DB;
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

        $phoneNumberId = $this->account?->phoneNumber?->waba_phone_number_id;

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
        $phoneNumberId = $this->account?->phoneNumber?->waba_phone_number_id;

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
    public function connectManualWaba(
        int $gymId,
        string $wabaId,
        string $phoneNumber,
        ?string $metaAccessToken = null,
        ?string $phoneNumberId = null,
        ?array $businessData = null
    ): array {
        // Security: Meta access token and phone number ID are REQUIRED for manual WABA connection
        // This ensures the user actually owns the WhatsApp Business Account
        // Without verification, anyone could connect random WABA IDs
        if (! $metaAccessToken || ! $phoneNumberId) {
            return [
                'success' => false,
                'error' => 'Meta Access Token and Phone Number ID are required to connect a WhatsApp Business Account. This ensures your credentials are validated with Meta before connecting.',
            ];
        }

        // Verify credentials via Meta API (REQUIRED)
        $verificationResult = $this->verifyWabaConnection($wabaId, $phoneNumberId, $metaAccessToken);

        if (! $verificationResult['success']) {
            return [
                'success' => false,
                'error' => $verificationResult['error'] ?? 'Failed to verify WABA connection with Meta',
            ];
        }

        if (! ($verificationResult['verified'] ?? false)) {
            return [
                'success' => false,
                'error' => 'The provided Phone Number ID does not exist or is not valid in Meta system',
            ];
        }

        return DB::transaction(function () use ($gymId, $businessData, $wabaId, $phoneNumberId, $metaAccessToken, $verificationResult, $phoneNumber) {
            // Step 2: Get business name - use Meta API authoritative name if available
            $businessName = $verificationResult['business_name']
                ?? ($businessData['business_name'] ?? null);

            $businessEmail = $businessData['business_email'] ?? null;
            $businessDescription = $businessData['business_description'] ?? '';

            // Step 3: Get verified phone details
            $displayPhoneNumber = $verificationResult['display_phone_number'] ?? $phoneNumber;
            $verifiedPhoneNumberId = $verificationResult['phone_number_id'] ?? $phoneNumberId;

            // Step 4: Normalize phone number (keep + country code, remove other special chars)
            $normalizedPhoneNumber = $this->normalizePhoneNumber($displayPhoneNumber);

            // Step 5: Extract country code from phone number
            $countryCode = $this->extractCountryCode($normalizedPhoneNumber);

            // Step 6: Determine phone type based on code verification status
            $phoneType = $this->determinePhoneType($verificationResult);

            // Step 7: Get verification status for account
            $verificationStatus = $verificationResult['business_verification_status'];

            // Step 8: Get WABA status from verification
            $wabaStatusEnum = $verificationResult['waba_status']
                ?? \App\Enums\WabaStatusEnum::ACTIVE;

            $accountData = [
                'gym_id' => $gymId,
                'business_name' => $businessName,
                'business_email' => $businessEmail,
                'business_description' => $businessDescription,
                'waba_id' => $wabaId,
                'waba_name' => $businessName,
                'waba_mode' => WabaModeEnum::MANUAL,
                'meta_access_token' => $metaAccessToken,
                'embedded_signup_completed' => true,
                'waba_status' => $wabaStatusEnum,
                'provider_type' => \App\Enums\WhatsAppProviderTypeEnum::WABA,
                'status' => \App\Enums\WhatsAppAccountStatusEnum::ACTIVE,
                'verification_status' => $verificationStatus,
                'verified_at' => now(),
            ];

            // Step 9: Update or create WhatsAppAccount
            $account = WhatsAppAccount::updateOrCreate([
                'gym_id' => $gymId,
            ], $accountData);

            // Step 10: Update or create WhatsAppPhoneNumber
            WhatsAppPhoneNumber::updateOrCreate([
                'whatsapp_account_id' => $account->id,
            ], [
                'waba_phone_number_id' => $verifiedPhoneNumberId,
                'phone_number' => $normalizedPhoneNumber,
                'country_code' => $countryCode,
                'friendly_name' => $normalizedPhoneNumber,
                'status' => WhatsAppPhoneNumberStatusEnum::ACTIVE,
                'type' => $phoneType,
            ]);

            // Step 11: Invalidate provider cache
            WhatsAppProviderFactory::invalidateProviderCache($gymId);

            return [
                'success' => true,
                'waba_id' => $wabaId,
                'phone_number_id' => $verifiedPhoneNumberId,
                'display_phone_number' => $normalizedPhoneNumber,
                'business_name' => $businessName,
                'waba_status' => $wabaStatusEnum?->value,
                'verification_status' => $verificationStatus?->value,
                'message' => 'Manual WABA connected successfully',
            ];
        });

    }

    /**
     * Normalize phone number - keep + prefix with country code, remove other special chars.
     */
    protected function normalizePhoneNumber(string $phoneNumber): string
    {
        // If already has + prefix, keep it
        if (str_starts_with($phoneNumber, '+')) {
            return $phoneNumber;
        }

        // Add + prefix if missing
        return '+' . ltrim($phoneNumber, '+');
    }

    /**
     * Extract country code from phone number.
     */
    protected function extractCountryCode(string $phoneNumber): ?string
    {
        // Extract country code from +CountryCode format
        // Examples: +14155551234 -> 1, +919876543210 -> 91
        if (preg_match('/^\+(\d{1,3})/', $phoneNumber, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Determine phone number type based on verification status.
     */
    protected function determinePhoneType(?array $verificationResult): \App\Enums\WhatsAppPhoneNumberTypesEnum
    {
        // If code is verified, it's a business number
        $codeStatus = $verificationResult['code_verification_status'] ?? null;

        if ($codeStatus === 'VERIFIED') {
            return \App\Enums\WhatsAppPhoneNumberTypesEnum::BUSINESS;
        }

        return \App\Enums\WhatsAppPhoneNumberTypesEnum::BUSINESS;
    }

    // =========================
    // WABA Verification
    // =========================

    /**
     * {@inheritdoc}
     */
    public function verifyWabaConnection(string $wabaId, string $phoneNumberId, ?string $accessToken = null): array
    {
        $token = $accessToken ?? $this->getAccessToken();

        if (! $token) {
            return [
                'success' => false,
                'error' => 'Meta access token is required for verification',
                'verified' => false,
                'phone_number_id' => null,
                'display_phone_number' => null,
                'verified_name' => null,
                'code_verification_status' => null,
                'business_verification_status' => null,
                'certificate' => null,
                'quality_score' => null,
                'waba_id' => null,
                'business_name' => null,
                'waba_status' => null,
                'currency' => null,
                'timezone' => null,
            ];
        }

        try {
            // Call Meta Graph API to get phone number details
            // Endpoint: GET /{phone_number_id}
            // Request both phone details AND associated WABA info
            $response = Http::withToken($token)
                ->get("{$this->getApiBaseUrl()}/{$phoneNumberId}", [
                    'fields' => 'id,display_phone_number,code_verification_status,certificate,verified_name,quality_score,message_template_namespace',
                ]);

            if ($response->failed()) {
                $errorData = $response->json();
                $errorMessage = $errorData['error']['message'] ?? $response->body();

                return [
                    'success' => false,
                    'error' => "Meta API error: {$errorMessage}",
                    'verified' => false,
                    'phone_number_id' => null,
                    'display_phone_number' => null,
                    'verified_name' => null,
                    'code_verification_status' => null,
                    'business_verification_status' => null,
                    'certificate' => null,
                    'quality_score' => null,
                    'waba_id' => null,
                    'business_name' => null,
                    'waba_status' => null,
                    'currency' => null,
                    'timezone' => null,
                ];
            }

            $data = $response->json();

            // Also fetch WABA details to get business name
            $wabaResponse = Http::withToken($token)
                ->get("{$this->getApiBaseUrl()}/{$wabaId}", [
                    'fields' => 'id,name,business,business_verification_status,currency,timezone',
                ]);

            $wabaData = $wabaResponse->successful() ? $wabaResponse->json() : [];

            // Extract business name from WABA response
            $businessName = $wabaData['business']['name'] ?? $wabaData['name'] ?? $data['verified_name'] ?? null;

            $businessVerificationStatus = match ($wabaData['business_verification_status']) {
                'VERIFIED' => WhatsAppAccountVerificationStatusEnum::VERIFIED,
                'UNVERIFIED' => WhatsAppAccountVerificationStatusEnum::PENDING,
                'PENDING' => WhatsAppAccountVerificationStatusEnum::PENDING,
                'IN_REVIEW' => WhatsAppAccountVerificationStatusEnum::IN_REVIEW,
                'REJECTED' => WhatsAppAccountVerificationStatusEnum::REJECTED,
                default => WhatsAppAccountVerificationStatusEnum::PENDING,
            };

            $wabaStatus = match ($wabaData['business_verification_status'] ?? $wabaData['status']) {
                'VERIFIED' => WabaStatusEnum::ACTIVE,
                'UNVERIFIED' => WabaStatusEnum::PENDING,
                'PENDING' => WabaStatusEnum::PENDING,
                'IN_REVIEW' => WabaStatusEnum::PENDING,
                default => WabaStatusEnum::PENDING,
            };

            return [
                'success' => true,
                'verified' => true,
                'phone_number_id' => $data['id'] ?? $phoneNumberId,
                'display_phone_number' => $data['display_phone_number'] ?? null,
                'verified_name' => $data['verified_name'] ?? null,
                'business_verification_status' => $businessVerificationStatus->value,
                'code_verification_status' => $data['code_verification_status'] ?? null,
                'certificate' => $data['certificate'] ?? null,
                'quality_score' => $data['quality_score'] ?? null,
                // Business info from WABA
                'waba_id' => $wabaData['id'] ?? $wabaId,
                'business_name' => $businessName,
                'waba_status' => $wabaStatus->value,
                'currency' => $wabaData['currency'] ?? null,
                'timezone' => $wabaData['timezone'] ?? null,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to verify WABA connection', [
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to verify WABA connection: ' . $e->getMessage(),
                'verified' => false,
                'phone_number_id' => null,
                'display_phone_number' => null,
                'verified_name' => null,
                'code_verification_status' => null,
                'business_verification_status' => null,
                'certificate' => null,
                'quality_score' => null,
                'waba_id' => null,
                'business_name' => null,
                'waba_status' => null,
                'currency' => null,
                'timezone' => null,
            ];
        }
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

    // =========================
    // Embedded Signup Implementation
    // =========================

    /**
     * Get Meta app configuration for embedded signup.
     */
    protected function getMetaAppConfig(): array
    {
        return [
            'app_id' => config('services.twilio.meta.app_id', ''),
            'app_secret' => config('services.twilio.meta.app_secret', ''),
            'api_version' => config('services.twilio.meta.api_version', 'v19.0'),
            'redirect_uri' => config('services.twilio.meta.redirect_uri', ''),
            'config_id' => config('services.twilio.meta.config_id', ''),
        ];
    }

    /**
     * Start the embedded signup flow for a gym.
     *
     * Creates a WABA setup session and returns the Meta OAuth login URL.
     */
    public function startEmbeddedSignup(int $gymId): string
    {
        $session = WabaSetupSession::createForGym($gymId);
        $config = $this->getMetaAppConfig();

        $params = http_build_query([
            'client_id' => $config['app_id'],
            'redirect_uri' => $config['redirect_uri'],
            'state' => $session->session_id,
            'config_id' => $config['config_id'],
            'response_type' => 'code',
            'scope' => 'whatsapp_business_management,whatsapp_business_messaging,business_management',
        ]);

        return "https://www.facebook.com/{$config['api_version']}/dialog/oauth?{$params}";
    }

    /**
     * Get the embedded signup login URL.
     */
    public function getEmbeddedSignupLoginUrl(int $gymId): string
    {
        return $this->startEmbeddedSignup($gymId);
    }

    /**
     * Handle the embedded signup OAuth callback.
     *
     * Exchanges the authorization code for an access token, verifies the token,
     * and retrieves the WABA and phone number IDs.
     */
    public function handleEmbeddedSignupCallback(string $code, string $state): array
    {
        $session = WabaSetupSession::where('session_id', $state)->first();

        if (! $session) {
            return [
                'success' => false,
                'error' => 'Invalid session',
            ];
        }

        if ($session->isExpired()) {
            $session->markAsExpired();

            return [
                'success' => false,
                'error' => 'Session expired',
            ];
        }

        $tokenData = $this->exchangeCodeForToken($code);

        if (! $tokenData['success']) {
            $session->markAsFailed();

            return $tokenData;
        }

        $accessToken = $tokenData['access_token'];
        $debugData = $this->debugToken($accessToken);

        if (! $debugData['success']) {
            $session->markAsFailed();

            return $debugData;
        }

        $wabaId = $debugData['waba_id'] ?? null;
        $phoneNumberId = $debugData['phone_number_id'] ?? null;

        if (! $wabaId) {
            $session->markAsFailed();

            return [
                'success' => false,
                'error' => 'No WABA ID found in response',
            ];
        }

        $session->update([
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
        ]);

        $session->markAsCompleted($debugData);

        return [
            'success' => true,
            'session_id' => $session->session_id,
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
            'access_token' => $accessToken,
        ];
    }

    /**
     * Exchange authorization code for access token.
     */
    protected function exchangeCodeForToken(string $code): array
    {
        $config = $this->getMetaAppConfig();

        $response = Http::get("https://graph.facebook.com/{$config['api_version']}/oauth/access_token", [
            'client_id' => $config['app_id'],
            'client_secret' => $config['app_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'code' => $code,
        ]);

        if ($response->failed()) {
            Log::error('Failed to exchange code for token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to exchange code for token',
            ];
        }

        $data = $response->json();

        return [
            'success' => true,
            'access_token' => $data['access_token'],
            'token_type' => $data['token_type'] ?? 'bearer',
            'expires_in' => $data['expires_in'] ?? null,
        ];
    }

    /**
     * Debug access token and extract WABA ID.
     */
    protected function debugToken(string $accessToken): array
    {
        $config = $this->getMetaAppConfig();
        $appAccessToken = "{$config['app_id']}|{$config['app_secret']}";

        $response = Http::get("https://graph.facebook.com/{$config['api_version']}/debug_token", [
            'input_token' => $accessToken,
            'access_token' => $appAccessToken,
        ]);

        if ($response->failed()) {
            Log::error('Failed to debug token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to validate token',
            ];
        }

        $data = $response->json()['data'] ?? [];

        $wabaId = null;
        $phoneNumberId = null;

        $granularScopes = $data['granular_scopes'] ?? [];
        foreach ($granularScopes as $scope) {
            if ($scope['scope'] === 'whatsapp_business_management' || $scope['scope'] === 'whatsapp_business_messaging') {
                $targetIds = $scope['target_ids'] ?? [];
                if (! empty($targetIds)) {
                    $wabaId = $targetIds[0];
                }
            }
        }

        return [
            'success' => true,
            'user_id' => $data['user_id'] ?? null,
            'app_id' => $data['app_id'] ?? null,
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
            'scopes' => $data['scopes'] ?? [],
            'expires_at' => $data['expires_at'] ?? null,
        ];
    }
}
