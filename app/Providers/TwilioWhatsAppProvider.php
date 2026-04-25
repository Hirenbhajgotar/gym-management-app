<?php

namespace App\Providers;

use App\Interfaces\WhatsAppProviderInterface;
use App\Models\WhatsAppTemplate;
use App\Services\TwilioAccountManager;
use App\Services\TwilioWhatsAppService;

/**
 * Twilio WhatsApp Provider.
 *
 * This provider wraps existing Twilio services to implement
 * the WhatsAppProviderInterface for sending WhatsApp messages
 * via Twilio's API.
 *
 * @implements WhatsAppProviderInterface
 */
class TwilioWhatsAppProvider implements WhatsAppProviderInterface
{
    protected TwilioWhatsAppService $messageService;

    protected TwilioAccountManager $accountManager;

    protected int $gymId;

    public function __construct(int $gymId)
    {
        $this->gymId = $gymId;
        $this->messageService = new TwilioWhatsAppService((string) $gymId);
        $this->accountManager = new TwilioAccountManager;
    }

    // =========================
    // Messaging Operations
    // =========================

    /**
     * {@inheritdoc}
     */
    public function sendMessage(string $to, string $message): array
    {
        return $this->messageService->sendMessage($to, $message);
    }

    /**
     * {@inheritdoc}
     */
    public function sendTemplateMessage(string $to, string $templateName, array $variables = []): array
    {
        return $this->messageService->sendTemplateMessage($to, $templateName, $variables);
    }

    /**
     * {@inheritdoc}
     */
    public function sendTestMessage(string $to, string $message): array
    {
        return $this->messageService->sendTestMessage($to, $message);
    }

    /**
     * {@inheritdoc}
     */
    public function sendBroadcast(int $broadcastId): array
    {
        return $this->messageService->sendBroadcast($broadcastId);
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
    // Account Management
    // =========================

    /**
     * {@inheritdoc}
     */
    public function createSubAccount(int $gymId, string $gymName, string $email): array
    {
        return $this->accountManager->createSubaccount($gymId, $gymName, $email);
    }

    /**
     * {@inheritdoc}
     */
    public function getAccountStatus(int $gymId): array
    {
        return $this->accountManager->getAccountStatus((string) $gymId);
    }

    /**
     * {@inheritdoc}
     */
    public function cleanupAccount(int $gymId): array
    {
        return $this->accountManager->cleanupTenantResources($gymId);
    }

    /**
     * {@inheritdoc}
     */
    public function getWabaMode(int $gymId): string
    {
        return $this->accountManager->getWabaMode($gymId);
    }

    /**
     * {@inheritdoc}
     */
    public function requiresEmbeddedSignup(int $gymId): bool
    {
        return $this->accountManager->requiresEmbeddedSignup($gymId);
    }

    // =========================
    // Phone Number Management
    // =========================

    /**
     * {@inheritdoc}
     */
    public function getAvailableNumbers(string $country, string $type = 'local', int $limit = 5): array
    {
        return $this->accountManager->searchNumbers($country, $type, $limit);
    }

    /**
     * {@inheritdoc}
     */
    public function purchaseNumber(int $gymId, string $phoneNumber): array
    {
        return $this->accountManager->purchaseNumber($gymId, $phoneNumber);
    }

    /**
     * {@inheritdoc}
     */
    public function registerOwnNumber(int $gymId, string $phoneNumber): array
    {
        return $this->accountManager->registerOwnNumber($gymId, $phoneNumber);
    }

    /**
     * {@inheritdoc}
     */
    public function verifyOwnNumber(int $gymId, string $senderSid, string $otpCode): array
    {
        return $this->accountManager->verifyOwnNumber($gymId, $senderSid, $otpCode);
    }

    /**
     * {@inheritdoc}
     */
    public function resendOwnNumberOtp(int $gymId, string $senderSid, string $channel = 'sms'): array
    {
        return $this->accountManager->resendOwnNumberOtp($gymId, $senderSid, $channel);
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
        return $this->accountManager->submitTemplate($template);
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
    // Credit Management
    // =========================

    /**
     * {@inheritdoc}
     */
    public function getCreditBalance(): array
    {
        return $this->messageService->getCreditBalance();
    }

    /**
     * {@inheritdoc}
     */
    public function getMonthlyUsage(int $year, int $month): array
    {
        return $this->messageService->getMonthlyUsage($year, $month);
    }

    // =========================
    // WABA Setup (Meta-specific)
    // =========================

    /**
     * {@inheritdoc}
     */
    public function createWaba(int $gymId, array $businessProfile): array
    {
        return $this->accountManager->createWaba($gymId, $businessProfile);
    }

    /**
     * {@inheritdoc}
     */
    public function connectIndividualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken = null): array
    {
        return $this->accountManager->connectIndividualWaba($gymId, $wabaId, $phoneNumber, $metaAccessToken);
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
        // Twilio doesn't support manual WABA connection - delegate to account manager
        return $this->accountManager->connectManualWaba($gymId, $wabaId, $phoneNumber, $metaAccessToken, $phoneNumberId);
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
        $account = \App\Models\WhatsAppAccount::where('gym_id', $gymId)->first();

        if (! $account) {
            return [
                'success' => false,
                'error' => 'WhatsApp account not found',
            ];
        }

        return $this->accountManager->sendTestWhatsAppMessage($account, $to);
    }
}
