<?php

declare(strict_types=1);

namespace App\Providers;

use App\DTOs\WhatsAppTemplates\CreateTemplateData;
use App\DTOs\WhatsAppTemplates\TemplateCollectionResult;
use App\DTOs\WhatsAppTemplates\TemplateSubmissionResult;
use App\Interfaces\CreditManagementInterface;
use App\Interfaces\EmbeddedSignupInterface;
use App\Interfaces\MessagingInterface;
use App\Interfaces\TemplateManagementInterface;
use App\Interfaces\TestMessagingInterface;
use App\Interfaces\WabaSetupInterface;
use App\Models\WhatsAppTemplate;
use App\Services\Meta\MetaApiClient;
use App\Services\Meta\MetaContext;
use App\Services\Meta\MetaEmbeddedSignupService;
use App\Services\Meta\MetaMessagingService;
use App\Services\Meta\MetaTemplateService;
use App\Services\Meta\MetaWabaService;
use App\Services\WhatsApp\WhatsAppCreditService;
use App\Services\WhatsAppTemplateService;

/**
 * Meta WhatsApp Business API Provider.
 *
 * This provider delegates to focused services in App\Services\Meta\ for
 * cleaner separation of concerns. Each service handles a specific domain.
 *
 * @implements MessagingInterface, TemplateManagementInterface, CreditManagementInterface, WabaSetupInterface, EmbeddedSignupInterface, TestMessagingInterface
 */
class MetaWhatsAppProvider implements CreditManagementInterface, EmbeddedSignupInterface, MessagingInterface, TemplateManagementInterface, TestMessagingInterface, WabaSetupInterface
{
    private MetaContext $context;

    private MetaApiClient $api;

    private WhatsAppCreditService $credits;

    private ?MetaMessagingService $messaging = null;

    private ?MetaTemplateService $templates = null;

    private ?MetaWabaService $waba = null;

    private ?MetaEmbeddedSignupService $embedded = null;

    public function __construct(int $gymId)
    {
        $this->context = new MetaContext($gymId);
        $this->api = new MetaApiClient($this->context);
        $this->credits = new WhatsAppCreditService($gymId);
    }

    // =========================
    // Service Accessors
    // =========================

    protected function messaging(): MetaMessagingService
    {
        return $this->messaging ??= new MetaMessagingService($this->context, $this->api, $this->credits());
    }

    protected function credits(): WhatsAppCreditService
    {
        return $this->credits;
    }

    protected function templates(): MetaTemplateService
    {
        return $this->templates ??= new MetaTemplateService(
            $this->context,
            $this->api,
            app(WhatsAppTemplateService::class)
        );
    }

    protected function wabaSetup(): MetaWabaService
    {
        return $this->waba ??= new MetaWabaService($this->context, $this->api);
    }

    protected function embeddedSignup(): MetaEmbeddedSignupService
    {
        return $this->embedded ??= new MetaEmbeddedSignupService($this->context, $this->api);
    }

    // =========================
    // MessagingInterface - Delegation
    // =========================

    public function sendMessage(string $to, string $message): array
    {
        return $this->messaging()->sendMessage($to, $message);
    }

    public function sendTemplateMessage(string $to, string $templateName, array $variables = []): array
    {
        return $this->messaging()->sendTemplateMessage($to, $templateName, $variables);
    }

    public function sendBroadcast(int $broadcastId): array
    {
        return $this->messaging()->sendBroadcast($broadcastId);
    }

    public function getMessageStatus(string $messageId): array
    {
        return $this->messaging()->getMessageStatus($messageId);
    }

    public function sendTestMessage(string $to, string $message): array
    {
        return $this->messaging()->sendTestMessage($to, $message);
    }

    public function sendTestWhatsAppMessage(int $gymId, ?string $to = null): array
    {
        return $this->messaging()->sendTestWhatsAppMessage($gymId, $to);
    }

    // =========================
    // TemplateManagementInterface - Delegation
    // =========================

    public function createTemplate(int $gymId, CreateTemplateData $data): WhatsAppTemplate
    {
        return $this->templates()->createTemplate($gymId, $data);
    }

    public function submitTemplate(?WhatsAppTemplate $template = null): TemplateSubmissionResult
    {
        return $this->templates()->submitTemplate($template);
    }

    public function syncTemplateStatus(WhatsAppTemplate $template): void
    {
        $this->templates()->syncTemplateStatus($template);
    }

    public function getTemplates(int $gymId): TemplateCollectionResult
    {
        return $this->templates()->getTemplates($gymId);
    }

    // =========================
    // CreditManagementInterface - Delegation
    // =========================

    public function getCreditBalance(): array
    {
        return $this->credits()->getCreditBalance();
    }

    public function getMonthlyUsage(int $year, int $month): array
    {
        return $this->credits()->getMonthlyUsage($year, $month);
    }

    // =========================
    // WabaSetupInterface - Delegation
    // =========================

    public function createWaba(int $gymId, array $businessProfile): array
    {
        return $this->wabaSetup()->createWaba($gymId, $businessProfile);
    }

    public function connectIndividualWaba(int $gymId, string $wabaId, string $phoneNumber, ?string $metaAccessToken = null): array
    {
        return $this->wabaSetup()->connectIndividualWaba($gymId, $wabaId, $phoneNumber, $metaAccessToken);
    }

    public function connectManualWaba(
        int $gymId,
        string $wabaId,
        string $phoneNumber,
        ?string $metaAccessToken = null,
        ?string $phoneNumberId = null,
        ?array $businessData = null
    ): array {
        return $this->wabaSetup()->connectManualWaba($gymId, $wabaId, $phoneNumber, $metaAccessToken, $phoneNumberId, $businessData);
    }

    public function verifyWabaConnection(string $wabaId, string $phoneNumberId, ?string $accessToken = null): array
    {
        return $this->wabaSetup()->verifyWabaConnection($wabaId, $phoneNumberId, $accessToken);
    }

    public function completeEmbeddedSignup(int $gymId, string $wabaId, string $accessToken, ?string $phoneNumberId = null): array
    {
        return $this->wabaSetup()->completeEmbeddedSignup($gymId, $wabaId, $accessToken, $phoneNumberId);
    }

    public function startEmbeddedSignup(int $gymId): string
    {
        return $this->embeddedSignup()->startEmbeddedSignup($gymId);
    }

    public function getEmbeddedSignupLoginUrl(int $gymId): string
    {
        return $this->embeddedSignup()->getEmbeddedSignupLoginUrl($gymId);
    }

    public function handleEmbeddedSignupCallback(string $code, string $state): array
    {
        return $this->embeddedSignup()->handleEmbeddedSignupCallback($code, $state);
    }

    public function isEmbeddedSignupEnabled(): bool
    {
        return $this->embeddedSignup()->isEnabled();
    }

    // =========================
    // WabaSetupInterface - Extended Methods
    // =========================

    /**
     * Get WABA account details including review and verification status.
     *
     * Used by SyncWabaStatus job to fetch current account_review_status and
     * business_verification_status from Meta API.
     *
     * @param  string  $wabaId  The WhatsApp Business Account ID
     * @param  string  $accessToken  The Meta access token
     * @return array{
     *     success: bool,
     *     data?: array{
     *         id: string,
     *         name: string,
     *         account_review_status: string,
     *         business_verification_status: string,
     *         currency?: string,
     *         timezone?: string
     *     },
     *     error?: string
     * }
     */
    public function getWabaDetails(string $wabaId, string $accessToken): array
    {
        return $this->wabaSetup()->getWabaDetails($wabaId, $accessToken);
    }
}
